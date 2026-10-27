/**
 * OmniPOS Pro - Database Backup & Cloud Sync Service
 * Manages USB and Google Drive dual backup targets, frequency scheduling, and status monitoring.
 */

import { ApiService } from './api';
import { BackupSettings, BackupLog, UsbDriveInfo } from '../types';

export interface BackupSyncEvent {
  status: 'IDLE' | 'SYNCING' | 'SUCCESS' | 'ERROR';
  lastSyncAt: string | null;
  message?: string;
  settings?: BackupSettings | null;
}

export class BackupSyncService {
  private static schedulerInterval: number | null = null;
  private static listeners: Array<(event: BackupSyncEvent) => void> = [];
  private static isRunning: boolean = false;
  private static cachedSettings: BackupSettings | null = null;

  /**
   * Register listener for backup/sync state changes
   */
  public static onSyncUpdate(callback: (event: BackupSyncEvent) => void): () => void {
    this.listeners.push(callback);
    return () => {
      this.listeners = this.listeners.filter((l) => l !== callback);
    };
  }

  private static notify(event: BackupSyncEvent): void {
    this.listeners.forEach((listener) => {
      try {
        listener(event);
      } catch (err) {
        console.error('[BackupSyncService] Listener error:', err);
      }
    });
  }

  /**
   * Start periodic background schedule checker (runs every 60 seconds)
   */
  public static startBackgroundScheduler(): void {
    if (this.schedulerInterval !== null) return;

    // Run first check after 10 seconds, then every 60 seconds
    setTimeout(() => {
      this.checkScheduledBackup();
    }, 10000);

    this.schedulerInterval = window.setInterval(() => {
      this.checkScheduledBackup();
    }, 60000);
  }

  public static stopBackgroundScheduler(): void {
    if (this.schedulerInterval !== null) {
      clearInterval(this.schedulerInterval);
      this.schedulerInterval = null;
    }
  }

  /**
   * Query settings, detected USB drives, and recent logs
   */
  public static async getSettings(): Promise<{
    settings: BackupSettings;
    detected_usb_drives: UsbDriveInfo[];
    recent_logs: BackupLog[];
  }> {
    const res = await ApiService.request('backup.getSettings');
    if (res.success && res.data) {
      this.cachedSettings = res.data.settings;
      return res.data;
    }
    throw new Error(res.error?.message || 'Failed to fetch backup settings');
  }

  /**
   * Save backup configuration
   */
  public static async updateSettings(settings: Partial<BackupSettings>): Promise<BackupSettings> {
    const res = await ApiService.request('backup.updateSettings', settings);
    if (res.success && res.data?.settings) {
      this.cachedSettings = res.data.settings;
      this.notify({
        status: 'IDLE',
        lastSyncAt: res.data.settings.last_sync_at || null,
        settings: res.data.settings,
      });
      return res.data.settings;
    }
    throw new Error(res.error?.message || 'Failed to update backup settings');
  }

  /**
   * Auto-detect mounted USB drives
   */
  public static async detectUsb(): Promise<UsbDriveInfo[]> {
    const res = await ApiService.request('backup.detectUsb');
    if (res.success && res.data?.drives) {
      return res.data.drives;
    }
    return [];
  }

  /**
   * Test write permissions to USB path
   */
  public static async testUsb(path: string): Promise<{ success: boolean; message: string; free_human?: string }> {
    const res = await ApiService.request('backup.testUsb', { path });
    return {
      success: res.success,
      message: res.message || (res.success ? 'USB path verified.' : (res.error?.message || 'Write test failed.')),
      free_human: res.data?.free_human,
    };
  }

  /**
   * Test Google Drive credentials & cloud folder
   */
  public static async testGoogleDrive(customSettings?: Partial<BackupSettings>): Promise<{
    success: boolean;
    message: string;
    account_email?: string;
    folder_name?: string;
    storage_used?: string;
  }> {
    const res = await ApiService.request('backup.testGoogleDrive', { settings: customSettings });
    return {
      success: res.success,
      message: res.message || (res.success ? 'Connected to Google Drive.' : (res.error?.message || 'Google Drive test failed.')),
      account_email: res.data?.account_email,
      folder_name: res.data?.folder_name,
      storage_used: res.data?.storage_used,
    };
  }

  /**
   * Trigger immediate database sync or backup
   */
  public static async triggerSync(options?: {
    destination?: 'ALL' | 'USB' | 'GDRIVE';
    mode?: 'SYNC' | 'BACKUP';
  }): Promise<{ success: boolean; message: string; details?: any }> {
    if (this.isRunning) {
      return { success: false, message: 'A database sync operation is already running.' };
    }

    this.isRunning = true;
    this.notify({ status: 'SYNCING', lastSyncAt: this.cachedSettings?.last_sync_at || null });

    try {
      const res = await ApiService.request('backup.run', options || {});
      this.isRunning = false;

      if (res.success) {
        this.notify({
          status: 'SUCCESS',
          lastSyncAt: res.data?.timestamp || new Date().toISOString(),
          message: res.message,
        });
        return { success: true, message: res.message, details: res.data };
      } else {
        this.notify({
          status: 'ERROR',
          lastSyncAt: this.cachedSettings?.last_sync_at || null,
          message: res.error?.message || 'Sync failed.',
        });
        return { success: false, message: res.error?.message || 'Sync failed.', details: res.data };
      }
    } catch (err: any) {
      this.isRunning = false;
      this.notify({
        status: 'ERROR',
        lastSyncAt: this.cachedSettings?.last_sync_at || null,
        message: err.message,
      });
      return { success: false, message: err.message };
    }
  }

  /**
   * Run background check for scheduled backup
   */
  public static async checkScheduledBackup(): Promise<void> {
    if (this.isRunning) return;

    try {
      const res = await ApiService.request('backup.checkAndRunScheduled');
      if (res.success && res.data?.due) {
        console.log('[BackupSyncService] Scheduled backup completed:', res.data.result);
        this.notify({
          status: 'SUCCESS',
          lastSyncAt: res.data.result?.timestamp || new Date().toISOString(),
          message: 'Scheduled backup finished successfully.',
        });
      }
    } catch (err) {
      // Quiet background failure
      console.warn('[BackupSyncService] Scheduled check check failed:', err);
    }
  }

  /**
   * Fetch recent backup activity history logs
   */
  public static async getLogs(limit: number = 20): Promise<BackupLog[]> {
    const res = await ApiService.request('backup.getLogs', { limit });
    if (res.success && res.data?.logs) {
      return res.data.logs;
    }
    return [];
  }

  /**
   * Download a raw snapshot file directly in browser
   */
  public static async downloadSnapshot(compress: boolean = true): Promise<void> {
    const res = await ApiService.request('backup.download', { compress });
    if (!res.success || !res.data?.base64) {
      throw new Error(res.error?.message || 'Failed to download snapshot.');
    }

    const byteCharacters = atob(res.data.base64);
    const byteNumbers = new Array(byteCharacters.length);
    for (let i = 0; i < byteCharacters.length; i++) {
      byteNumbers[i] = byteCharacters.charCodeAt(i);
    }
    const byteArray = new Uint8Array(byteNumbers);
    const blob = new Blob([byteArray], { type: res.data.mime_type || 'application/octet-stream' });

    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = res.data.filename || `omnipos_export_${Date.now()}.sqlite`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(link.href);
  }
}
