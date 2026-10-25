/**
 * OmniPOS Pro - Resilient Encrypted IndexedDB Offline Storage & Sync Engine
 * 1. Caches active product catalog, fast barcode & PLU maps, tax rates, and company settings.
 * 2. Queues sales payloads in an encrypted IndexedDB outbox using Web Crypto API (AES-GCM-256).
 * 3. Client-generated UUID idempotency keys prevent duplicate order insertion.
 * 4. Automatically syncs via POST /api/index.php (action: pos.order.syncBatch) on reconnection.
 */

import { ApiService } from './api';

const DB_NAME = 'omnipos_offline_vault_v2';
const DB_VERSION = 2;

// Fallback encryption salt for AES-GCM Web Crypto
const CRYPTO_SALT = new Uint8Array([79, 109, 110, 105, 80, 79, 83, 95, 83, 101, 99, 117, 114, 101, 50, 48]);

export interface EncryptedQueuedOrder {
  idempotency_key: string;
  client_uuid: string;
  iv_hex: string;
  ciphertext_base64: string;
  created_at: number;
  sync_attempts: number;
  last_error?: string;
}

export interface SyncStatus {
  isOnline: boolean;
  isSyncing: boolean;
  pendingCount: number;
  lastSyncTimestamp: number | null;
}

export class OfflineSyncService {
  private static dbPromise: Promise<IDBDatabase> | null = null;
  private static isSyncing: boolean = false;
  private static lastSyncTimestamp: number | null = null;
  private static syncListeners: Array<(status: SyncStatus) => void> = [];
  private static cachedCryptoKey: CryptoKey | null = null;

  /**
   * Derive or return persistent AES-GCM-256 CryptoKey
   */
  private static async getCryptoKey(): Promise<CryptoKey> {
    if (this.cachedCryptoKey) return this.cachedCryptoKey;

    const baseKeyMaterial = await crypto.subtle.importKey(
      'raw',
      new TextEncoder().encode('OmniPOS_Pro_Offline_Vault_Key_2026'),
      { name: 'PBKDF2' },
      false,
      ['deriveKey']
    );

    this.cachedCryptoKey = await crypto.subtle.deriveKey(
      {
        name: 'PBKDF2',
        salt: CRYPTO_SALT,
        iterations: 100000,
        hash: 'SHA-256',
      },
      baseKeyMaterial,
      { name: 'AES-GCM', length: 256 },
      false,
      ['encrypt', 'decrypt']
    );

    return this.cachedCryptoKey;
  }

  /**
   * Encrypt object with AES-GCM-256
   */
  private static async encryptPayload(payload: any): Promise<{ ciphertext_base64: string; iv_hex: string }> {
    const key = await this.getCryptoKey();
    const iv = crypto.getRandomValues(new Uint8Array(12)); // 96-bit IV
    const encoded = new TextEncoder().encode(JSON.stringify(payload));

    const cipherBuffer = await crypto.subtle.encrypt(
      { name: 'AES-GCM', iv },
      key,
      encoded
    );

    const ivHex = Array.from(iv).map((b) => b.toString(16).padStart(2, '0')).join('');
    const cipherArray = new Uint8Array(cipherBuffer);
    let binary = '';
    for (let i = 0; i < cipherArray.length; i++) binary += String.fromCharCode(cipherArray[i]);
    const ciphertextBase64 = btoa(binary);

    return { ciphertext_base64: ciphertextBase64, iv_hex: ivHex };
  }

  /**
   * Decrypt object with AES-GCM-256
   */
  private static async decryptPayload(ciphertextBase64: string, ivHex: string): Promise<any> {
    const key = await this.getCryptoKey();
    const iv = new Uint8Array(ivHex.match(/.{1,2}/g)!.map((byte) => parseInt(byte, 16)));
    const binary = atob(ciphertextBase64);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);

    const decryptedBuffer = await crypto.subtle.decrypt(
      { name: 'AES-GCM', iv },
      key,
      bytes
    );

    const decryptedText = new TextDecoder().decode(decryptedBuffer);
    return JSON.parse(decryptedText);
  }

  /**
   * Open / Upgrade IndexedDB
   */
  private static getDB(): Promise<IDBDatabase> {
    if (this.dbPromise) return this.dbPromise;

    this.dbPromise = new Promise((resolve, reject) => {
      const request = indexedDB.open(DB_NAME, DB_VERSION);

      request.onupgradeneeded = (event: IDBVersionChangeEvent) => {
        const db = (event.target as IDBOpenDBRequest).result;

        // Store 1: Local Cache (catalog, tax rates, settings, barcode index map)
        if (!db.objectStoreNames.contains('cache_store')) {
          db.createObjectStore('cache_store', { keyPath: 'key' });
        }

        // Store 2: Encrypted Order Outbox (queued offline transactions)
        if (!db.objectStoreNames.contains('encrypted_outbox')) {
          const outboxStore = db.createObjectStore('encrypted_outbox', { keyPath: 'idempotency_key' });
          outboxStore.createIndex('idx_created_at', 'created_at', { unique: false });
        }
      };

      request.onsuccess = () => resolve(request.result);
      request.onerror = () => reject(request.error);
    });

    return this.dbPromise;
  }

  /**
   * Initialize Auto-Sync listeners
   */
  public static initAutoSync(): void {
    if (typeof window === 'undefined') return;

    window.addEventListener('online', () => {
      console.log('[OfflineSync] Network reconnected. Executing automatic outbox batch sync...');
      this.syncOutboxWithServer();
    });

    window.addEventListener('offline', () => {
      this.notifyListeners();
    });

    // Background interval sync every 30 seconds
    setInterval(() => {
      if (navigator.onLine && !this.isSyncing) {
        this.syncOutboxWithServer();
      }
    }, 30000);
  }

  public static onSyncUpdate(callback: (status: SyncStatus) => void): () => void {
    this.syncListeners.push(callback);
    this.notifyListeners();
    return () => {
      this.syncListeners = this.syncListeners.filter((cb) => cb !== callback);
    };
  }

  private static async notifyListeners(): Promise<void> {
    const pendingCount = await this.getPendingOrderCount();
    const status: SyncStatus = {
      isOnline: typeof navigator !== 'undefined' ? navigator.onLine : true,
      isSyncing: this.isSyncing,
      pendingCount,
      lastSyncTimestamp: this.lastSyncTimestamp,
    };
    for (const listener of this.syncListeners) {
      try {
        listener(status);
      } catch (err) {
        console.error('[OfflineSync] Error in listener:', err);
      }
    }
  }

  /**
   * Cache active product catalog, tax rates, and settings with pre-computed lookup maps
   */
  public static async cacheCatalog(catalogData: any): Promise<void> {
    try {
      const db = await this.getDB();
      const tx = db.transaction('cache_store', 'readwrite');
      const store = tx.objectStore('cache_store');

      // 1. Raw Catalog Data
      store.put({ key: 'catalog', data: catalogData, cached_at: Date.now() });

      // 2. Barcode & Scale PLU Fast Lookup Map
      const barcodeMap: Record<string, any> = {};
      const pluMap: Record<string, any> = {};

      if (catalogData.products && Array.isArray(catalogData.products)) {
        for (const prod of catalogData.products) {
          if (prod.barcode) barcodeMap[prod.barcode.toLowerCase()] = prod;
          if (prod.sku) barcodeMap[prod.sku.toLowerCase()] = prod;
          if (prod.scale_plu) pluMap[prod.scale_plu] = prod;
        }
      }

      if (catalogData.product_units && Array.isArray(catalogData.product_units)) {
        for (const pu of catalogData.product_units) {
          if (pu.barcode) barcodeMap[pu.barcode.toLowerCase()] = pu;
        }
      }

      store.put({ key: 'barcode_lookup_map', data: barcodeMap, cached_at: Date.now() });
      store.put({ key: 'scale_plu_map', data: pluMap, cached_at: Date.now() });

      // 3. Tax Rates
      if (catalogData.tax_rates) {
        store.put({ key: 'tax_rates', data: catalogData.tax_rates, cached_at: Date.now() });
      }
    } catch (err) {
      console.warn('[OfflineSync] Failed to cache catalog to IndexedDB:', err);
    }
  }

  public static async cacheCompanySettings(settings: any): Promise<void> {
    try {
      const db = await this.getDB();
      const tx = db.transaction('cache_store', 'readwrite');
      tx.objectStore('cache_store').put({ key: 'company_settings', data: settings, cached_at: Date.now() });
    } catch (err) {
      console.warn('[OfflineSync] Failed to cache company settings:', err);
    }
  }

  public static async getCachedCatalog(): Promise<any | null> {
    try {
      const db = await this.getDB();
      const tx = db.transaction('cache_store', 'readonly');
      const store = tx.objectStore('cache_store');
      const req = store.get('catalog');

      return new Promise((resolve) => {
        req.onsuccess = () => resolve(req.result ? req.result.data : null);
        req.onerror = () => resolve(null);
      });
    } catch {
      return null;
    }
  }

  public static async getCachedBarcodeLookup(barcode: string): Promise<any | null> {
    try {
      const db = await this.getDB();
      const tx = db.transaction('cache_store', 'readonly');
      const store = tx.objectStore('cache_store');
      const req = store.get('barcode_lookup_map');

      return new Promise((resolve) => {
        req.onsuccess = () => {
          const map = req.result ? req.result.data : {};
          resolve(map[barcode.toLowerCase()] || null);
        };
        req.onerror = () => resolve(null);
      });
    } catch {
      return null;
    }
  }

  /**
   * Queue offline order in the AES-GCM Encrypted IndexedDB Outbox
   * Generates a unique client UUID and idempotency key
   */
  public static async queueOfflineOrder(orderPayload: any): Promise<string> {
    const db = await this.getDB();
    const clientUuid = crypto.randomUUID();
    const idempotencyKey = orderPayload.idempotency_key || ('IDEM-OFFLINE-' + clientUuid);
    orderPayload.idempotency_key = idempotencyKey;

    // Encrypt order payload
    const { ciphertext_base64, iv_hex } = await this.encryptPayload(orderPayload);

    const queuedItem: EncryptedQueuedOrder = {
      idempotency_key: idempotencyKey,
      client_uuid: clientUuid,
      iv_hex,
      ciphertext_base64,
      created_at: Date.now(),
      sync_attempts: 0,
    };

    return new Promise((resolve, reject) => {
      const tx = db.transaction('encrypted_outbox', 'readwrite');
      const store = tx.objectStore('encrypted_outbox');
      const req = store.put(queuedItem);

      req.onsuccess = async () => {
        await this.notifyListeners();
        resolve(idempotencyKey);
      };
      req.onerror = () => reject(req.error);
    });
  }

  public static async getPendingOrderCount(): Promise<number> {
    try {
      const db = await this.getDB();
      const tx = db.transaction('encrypted_outbox', 'readonly');
      const store = tx.objectStore('encrypted_outbox');
      const req = store.count();

      return new Promise((resolve) => {
        req.onsuccess = () => resolve(req.result || 0);
        req.onerror = () => resolve(0);
      });
    } catch {
      return 0;
    }
  }

  private static async getPendingEncryptedOrders(): Promise<EncryptedQueuedOrder[]> {
    try {
      const db = await this.getDB();
      const tx = db.transaction('encrypted_outbox', 'readonly');
      const store = tx.objectStore('encrypted_outbox');
      const req = store.getAll();

      return new Promise((resolve) => {
        req.onsuccess = () => resolve(req.result || []);
        req.onerror = () => resolve([]);
      });
    } catch {
      return [];
    }
  }

  public static async removeQueuedOrder(idempotencyKey: string): Promise<void> {
    try {
      const db = await this.getDB();
      const tx = db.transaction('encrypted_outbox', 'readwrite');
      const store = tx.objectStore('encrypted_outbox');
      store.delete(idempotencyKey);
      await this.notifyListeners();
    } catch (err) {
      console.warn('[OfflineSync] Failed to delete synced order:', err);
    }
  }

  /**
   * Decrypt and Synchronize Outbox Batch with Server
   * Calls POST /api/index.php (action: pos.order.syncBatch)
   * Skips duplicates safely via idempotency keys
   */
  public static async syncOutboxWithServer(): Promise<{ synced: number; remaining: number }> {
    if (this.isSyncing) {
      return { synced: 0, remaining: await this.getPendingOrderCount() };
    }

    const pendingEncrypted = await this.getPendingEncryptedOrders();
    if (pendingEncrypted.length === 0) {
      return { synced: 0, remaining: 0 };
    }

    this.isSyncing = true;
    await this.notifyListeners();

    let syncedCount = 0;

    try {
      // Decrypt all queued orders
      const decryptedOrders: any[] = [];
      for (const item of pendingEncrypted) {
        try {
          const decrypted = await this.decryptPayload(item.ciphertext_base64, item.iv_hex);
          decryptedOrders.push(decrypted);
        } catch (decryptErr) {
          console.error('[OfflineSync] Failed to decrypt queued order:', decryptErr);
        }
      }

      if (decryptedOrders.length > 0) {
        // Send batch request to server
        const response = await ApiService.request('pos.order.syncBatch', {
          orders: decryptedOrders,
        });

        if (response.success) {
          // Clear successfully processed orders from outbox
          for (const item of pendingEncrypted) {
            await this.removeQueuedOrder(item.idempotency_key);
          }
          syncedCount = decryptedOrders.length;
          this.lastSyncTimestamp = Date.now();
        }
      }
    } catch (err) {
      console.warn('[OfflineSync] Outbox synchronization failed (will retry):', err);
    } finally {
      this.isSyncing = false;
      await this.notifyListeners();
      const remaining = await this.getPendingOrderCount();
      return { synced: syncedCount, remaining };
    }
  }
}
