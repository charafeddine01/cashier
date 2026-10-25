import React, { useState, useEffect, useCallback } from 'react';
import {
  Product,
  Category,
  CartItem,
  Customer,
  RestaurantTable,
  CompanySettings,
  Shift
} from '../../types';
import { ProductGrid } from './ProductGrid';
import { CartList } from './CartList';
import { PaymentModal } from './PaymentModal';
import { ThermalReceiptModal } from './ThermalReceiptModal';
import { HeldOrdersModal } from './HeldOrdersModal';
import { CameraScannerModal } from './CameraScannerModal';
import { ApiService } from '../../services/api';
import { HardwareService } from '../../services/hardwareService';
import { OfflineSyncService } from '../../services/offlineSyncService';
import { SoundService } from '../../services/soundService';
import { I18nService } from '../../services/i18nService';

interface Props {
  settings: CompanySettings | null;
  activeShift: Shift | null;
  onOpenShiftModal: () => void;
}

export const PosTerminal: React.FC<Props> = ({ settings, activeShift, onOpenShiftModal }) => {
  const [products, setProducts] = useState<Product[]>([]);
  const [categories, setCategories] = useState<Category[]>([]);
  const [cartItems, setCartItems] = useState<CartItem[]>([]);
  const [selectedCustomer, setSelectedCustomer] = useState<Customer | null>(null);
  const [selectedTable, setSelectedTable] = useState<RestaurantTable | null>(null);
  const [heldOrders, setHeldOrders] = useState<any[]>([]);

  // Modals
  const [isPaymentOpen, setIsPaymentOpen] = useState(false);
  const [isReceiptOpen, setIsReceiptOpen] = useState(false);
  const [isHeldModalOpen, setIsHeldModalOpen] = useState(false);
  const [isCameraOpen, setIsCameraOpen] = useState(false);
  const [lastCompletedOrder, setLastCompletedOrder] = useState<any | null>(null);

  // Load catalog & held orders
  const loadCatalog = useCallback(async () => {
    // Check cached catalog first for instant offline readiness
    const cached = await OfflineSyncService.getCachedCatalog();
    if (cached) {
      setProducts(cached.products || []);
      setCategories(cached.categories || []);
    }

    const res = await ApiService.request('pos.catalog');
    if (res.success && res.data) {
      setProducts(res.data.products || []);
      setCategories(res.data.categories || []);
      OfflineSyncService.cacheCatalog(res.data);
    }

    const heldRes = await ApiService.request('pos.order.getHeld');
    if (heldRes.success && heldRes.data) {
      setHeldOrders(heldRes.data.held_orders || []);
    }
  }, []);

  useEffect(() => {
    loadCatalog();
  }, [loadCatalog]);

  // Sync to Customer Pole Display whenever cart changes
  useEffect(() => {
    const subtotal = cartItems.reduce((s, it) => s + it.unit_price * it.quantity, 0);
    const taxTotal = cartItems.reduce((s, it) => s + it.tax_amount, 0);
    const discountTotal = cartItems.reduce((s, it) => s + it.discount_amount, 0);
    const grandTotal = Math.max(0, subtotal + taxTotal - discountTotal);
    const secRate = settings?.secondary_currency_rate || 0.92;

    HardwareService.broadcastToCustomerDisplay({
      companyName: settings?.company_name || 'OmniPOS SuperStore',
      primaryCurrencySymbol: settings?.primary_currency_symbol || '$',
      secondaryCurrencySymbol: settings?.secondary_currency_symbol || '€',
      secondaryRate: secRate,
      items: cartItems.map((it) => ({
        name: it.product_name,
        quantity: it.quantity,
        unitPrice: it.unit_price,
        lineTotal: it.line_total,
      })),
      subtotal,
      taxTotal,
      discountTotal,
      grandTotal,
      grandTotalSecondary: grandTotal * secRate,
      status: cartItems.length > 0 ? 'SCANNING' : 'WELCOME',
    });
  }, [cartItems, settings]);

  // Handle scanned barcodes (from hardware USB/Bluetooth scanner or camera)
  const handleBarcodeScanned = useCallback((rawBarcode: string) => {
    SoundService.playBeep();

    // 1. Check Supermarket Scale Barcode (EAN-13 prefixes 20 - 29)
    const scaleParsed = HardwareService.parseScaleBarcode(rawBarcode, settings?.scale_barcode_prefix || '21');
    if (scaleParsed.isScaleBarcode) {
      const matchedScaleProduct = products.find(
        (p) => p.scale_plu === scaleParsed.plu || p.scale_plu === scaleParsed.plu.replace(/^0+/, '')
      );

      if (matchedScaleProduct) {
        addScaleProductToCart(matchedScaleProduct, scaleParsed.weightKg, rawBarcode);
        return;
      }
    }

    // 2. Standard Product Barcode or Multi-Unit match
    const matched = products.find((p) => {
      if (p.barcode && p.barcode.toLowerCase() === rawBarcode.toLowerCase()) return true;
      if (p.sku && p.sku.toLowerCase() === rawBarcode.toLowerCase()) return true;
      return false;
    });

    if (matched) {
      addProductToCart(matched);
      return;
    }

    SoundService.playError();
    alert(`Scanned item '${rawBarcode}' not found in active catalog.`);
  }, [products, settings]);

  // Setup Global HID Barcode Scanner Listener
  useEffect(() => {
    const unsubScanner = HardwareService.onBarcode(handleBarcodeScanned);
    return () => unsubScanner();
  }, [handleBarcodeScanned]);

  // Keyboard Shortcuts bar (F1-F12)
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      // Check F1 - Search focus
      if (e.key === 'F1') {
        e.preventDefault();
        const searchInput = document.querySelector('input[type="text"]') as HTMLInputElement;
        if (searchInput) searchInput.focus();
      }
      // F2 - Camera scanner
      else if (e.key === 'F2') {
        e.preventDefault();
        setIsCameraOpen(true);
      }
      // F6 - Hold Order
      else if (e.key === 'F6') {
        e.preventDefault();
        handleHoldOrder();
      }
      // F7 - Recall
      else if (e.key === 'F7') {
        e.preventDefault();
        setIsHeldModalOpen(true);
      }
      // F8 - Clear
      else if (e.key === 'F8') {
        e.preventDefault();
        handleClearCart();
      }
      // F12 - Charge / Pay
      else if (e.key === 'F12') {
        e.preventDefault();
        if (cartItems.length > 0) setIsPaymentOpen(true);
      }
    };

    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [cartItems]);

  // Add standard product to cart
  const addProductToCart = (product: Product, quantity: number = 1) => {
    SoundService.playBeep();

    setCartItems((prev) => {
      const existingIdx = prev.findIndex((it) => it.product_id === product.id && !it.variant_id);
      const unitPrice = Number(product.selling_price);
      const taxRate = Number(product.tax_rate_percent || settings?.default_tax_rate || 0);

      if (existingIdx !== -1) {
        const item = prev[existingIdx];
        const newQty = item.quantity + quantity;
        const lineSubtotal = newQty * item.unit_price;
        const lineTax = lineSubtotal * (taxRate / 100);
        const lineTotal = lineSubtotal + lineTax - item.discount_amount;

        const updated = [...prev];
        updated[existingIdx] = {
          ...item,
          quantity: newQty,
          tax_amount: lineTax,
          line_total: lineTotal,
        };
        return updated;
      } else {
        const lineSubtotal = quantity * unitPrice;
        const lineTax = lineSubtotal * (taxRate / 100);
        const lineTotal = lineSubtotal + lineTax;

        const newItem: CartItem = {
          id: 'ITEM-' + Date.now() + '-' + Math.random().toString(36).substr(2, 5),
          product_id: product.id,
          product_name: product.name,
          sku: product.sku,
          barcode: product.barcode,
          unit_id: product.unit_id,
          unit_code: product.unit_code || 'pcs',
          is_fractional: Boolean(product.is_fractional),
          quantity,
          unit_cost: Number(product.cost_price || 0),
          unit_price: unitPrice,
          original_unit_price: unitPrice,
          discount_amount: 0,
          discount_percent: 0,
          tax_rate: taxRate,
          tax_amount: lineTax,
          line_total: lineTotal,
          modifiers: [],
          is_kitchen_item: product.is_kitchen_item,
        };
        return [...prev, newItem];
      }
    });
  };

  // Add weighed supermarket scale product
  const addScaleProductToCart = (product: Product, weightKg: number, rawBarcode: string) => {
    SoundService.playBeep();

    setCartItems((prev) => {
      const unitPrice = Number(product.selling_price);
      const taxRate = Number(product.tax_rate_percent || 0);
      const lineSubtotal = weightKg * unitPrice;
      const lineTax = lineSubtotal * (taxRate / 100);
      const lineTotal = lineSubtotal + lineTax;

      const newItem: CartItem = {
        id: 'SCALE-' + Date.now() + '-' + Math.random().toString(36).substr(2, 5),
        product_id: product.id,
        product_name: `${product.name} (${(weightKg * 1000).toFixed(0)}g)`,
        sku: product.sku,
        barcode: rawBarcode,
        unit_id: product.unit_id,
        unit_code: 'kg',
        is_fractional: true,
        quantity: weightKg,
        unit_cost: Number(product.cost_price || 0),
        unit_price: unitPrice,
        original_unit_price: unitPrice,
        discount_amount: 0,
        discount_percent: 0,
        tax_rate: taxRate,
        tax_amount: lineTax,
        line_total: lineTotal,
        modifiers: [],
        is_kitchen_item: product.is_kitchen_item,
      };
      return [...prev, newItem];
    });
  };

  // Update item quantity
  const handleUpdateQty = (itemId: string, newQty: number) => {
    if (newQty <= 0) {
      handleRemoveItem(itemId);
      return;
    }

    setCartItems((prev) =>
      prev.map((it) => {
        if (it.id !== itemId) return it;
        const lineSubtotal = newQty * it.unit_price;
        const lineTax = lineSubtotal * (it.tax_rate / 100);
        const lineTotal = lineSubtotal + lineTax - it.discount_amount;
        return {
          ...it,
          quantity: newQty,
          tax_amount: lineTax,
          line_total: lineTotal,
        };
      })
    );
  };

  // Remove item
  const handleRemoveItem = (itemId: string) => {
    setCartItems((prev) => prev.filter((it) => it.id !== itemId));
  };

  // Clear cart
  const handleClearCart = () => {
    if (cartItems.length > 0 && confirm('Are you sure you want to clear the entire cart?')) {
      setCartItems([]);
    }
  };

  // Hold / Park order
  const handleHoldOrder = async () => {
    if (cartItems.length === 0) return;

    const res = await ApiService.request('pos.order.hold', {
      title: selectedCustomer?.name || 'Customer Order',
      grand_total: cartItems.reduce((s, it) => s + it.line_total, 0),
      items: cartItems,
    });

    if (res.success) {
      setCartItems([]);
      loadCatalog();
    }
  };

  // Recall held order
  const handleRecallHeldOrder = async (heldId: number) => {
    const res = await ApiService.request('pos.order.recallHeld', { held_id: heldId });
    if (res.success && res.data?.order?.items) {
      setCartItems(res.data.order.items);
      setIsHeldModalOpen(false);
      loadCatalog();
    }
  };

  // Delete held order
  const handleDeleteHeldOrder = async (heldId: number) => {
    await ApiService.request('pos.order.deleteHeld', { held_id: heldId });
    loadCatalog();
  };

  // Order Submission & Payment Confirmation
  const handleConfirmOrder = async (paymentData: any) => {
    const subtotal = cartItems.reduce((sum, item) => sum + (item.unit_price * item.quantity), 0);
    const discountTotal = cartItems.reduce((sum, item) => sum + (item.discount_amount || 0), 0);
    const taxTotal = cartItems.reduce((sum, item) => sum + (item.tax_amount || 0), 0);
    const grandTotal = Math.max(0, subtotal + taxTotal - discountTotal);

    const orderPayload = {
      warehouse_id: 1,
      customer_id: selectedCustomer?.id || 1,
      table_id: selectedTable?.id || null,
      order_type: settings?.business_mode === 'RESTAURANT' ? 'DINE_IN' : 'RETAIL',
      subtotal,
      tax_total: taxTotal,
      discount_total: discountTotal,
      grand_total: grandTotal,
      grand_total_secondary: grandTotal * (paymentData.exchangeRate || 0.92),
      exchange_rate: paymentData.exchangeRate || 0.92,
      paid_cash: paymentData.paidCash,
      paid_card: paymentData.paidCard,
      paid_credit: paymentData.paidCredit,
      paid_on_account: paymentData.paidOnAccount,
      change_given: paymentData.changeGiven,
      change_currency: paymentData.changeCurrency,
      items: cartItems,
      idempotency_key: 'IDEM-' + crypto.randomUUID(),
    };

    // If online, call pos.order.create directly. If offline, queue in IndexedDB outbox!
    if (!navigator.onLine) {
      await OfflineSyncService.queueOfflineOrder(orderPayload);
      SoundService.playCashChime();
      alert('Network is offline. Sale successfully queued locally and will sync automatically upon reconnection.');
      setCartItems([]);
      return;
    }

    const res = await ApiService.request('pos.order.create', orderPayload);

    if (res.success && res.data) {
      SoundService.playCashChime();

      setLastCompletedOrder({
        ...orderPayload,
        invoice_number: res.data.invoice_number,
        created_at: new Date().toLocaleString(),
      });

      // Clear cart
      setCartItems([]);
      setSelectedTable(null);

      // Open receipt dialog
      setIsReceiptOpen(true);
    } else {
      // If network failure occurred mid-flight, queue offline
      if (res.error?.code === 'NETWORK_OFFLINE') {
        await OfflineSyncService.queueOfflineOrder(orderPayload);
        SoundService.playCashChime();
        alert('Network dropped. Order recorded offline.');
        setCartItems([]);
      } else {
        throw new Error(res.error?.message || 'Order creation failed.');
      }
    }
  };

  return (
    <div className="flex-1 flex overflow-hidden bg-slate-950">
      {/* Left Area: Product Catalog Grid */}
      <div className="flex-1 flex flex-col overflow-hidden">
        <ProductGrid
          products={products}
          categories={categories}
          settings={settings}
          onSelectProduct={(p) => addProductToCart(p)}
          onOpenScanner={() => setIsCameraOpen(true)}
        />

        {/* Keyboard Shortcut Helper Bar (F1-F12) */}
        <div className="bg-slate-900 border-t border-slate-800 px-3 py-1.5 flex items-center justify-between text-[11px] text-slate-400 font-mono overflow-x-auto shrink-0 select-none">
          <div className="flex items-center gap-4">
            <span><kbd className="bg-slate-800 text-slate-200 px-1 py-0.5 rounded">F1</kbd> Search</span>
            <span><kbd className="bg-slate-800 text-slate-200 px-1 py-0.5 rounded">F2</kbd> Camera</span>
            <span><kbd className="bg-slate-800 text-slate-200 px-1 py-0.5 rounded">F6</kbd> Hold</span>
            <span><kbd className="bg-slate-800 text-slate-200 px-1 py-0.5 rounded">F7</kbd> Recall</span>
            <span><kbd className="bg-slate-800 text-slate-200 px-1 py-0.5 rounded">F8</kbd> Clear</span>
            <span><kbd className="bg-blue-600 text-white px-1 py-0.5 rounded font-bold">F12</kbd> Pay</span>
          </div>
          <span className="text-[10px] text-emerald-400 font-semibold">HID Barcode Scanner Ready</span>
        </div>
      </div>

      {/* Right Area: Checkout Cart Drawer */}
      <div className="w-80 md:w-96 lg:w-[420px] shrink-0 h-full flex flex-col">
        <CartList
          items={cartItems}
          selectedCustomer={selectedCustomer}
          selectedTable={selectedTable}
          heldOrdersCount={heldOrders.length}
          settings={settings}
          onUpdateQty={handleUpdateQty}
          onRemoveItem={handleRemoveItem}
          onOpenCustomerSelect={() => alert('Customer Selector: Assign Walk-in or VIP customer.')}
          onOpenTableSelect={() => alert('Table Selector: Assign Dine-in Table.')}
          onHoldOrder={handleHoldOrder}
          onRecallHeldOrders={() => setIsHeldModalOpen(true)}
          onClearCart={handleClearCart}
          onOpenPayment={() => setIsPaymentOpen(true)}
          onOpenDiscountModal={(it) => {
            const pct = prompt('Enter discount percentage (e.g. 10 for 10%):', '10');
            if (pct && it) {
              const val = parseFloat(pct);
              if (val > 0) {
                const discAmt = (it.unit_price * it.quantity) * (val / 100);
                setCartItems((prev) =>
                  prev.map((i) => i.id === it.id ? { ...i, discount_amount: discAmt, discount_percent: val, line_total: Math.max(0, (i.unit_price * i.quantity) + i.tax_amount - discAmt) } : i)
                );
              }
            }
          }}
          onOpenPriceOverrideModal={(it) => {
            const newP = prompt('Enter override unit price ($):', it.unit_price.toString());
            if (newP) {
              const priceVal = parseFloat(newP);
              if (priceVal >= 0) {
                setCartItems((prev) =>
                  prev.map((i) => i.id === it.id ? { ...i, unit_price: priceVal, line_total: (priceVal * i.quantity) + i.tax_amount - i.discount_amount } : i)
                );
              }
            }
          }}
          onOpenModifierModal={() => {}}
        />
      </div>

      {/* Payment Modal */}
      <PaymentModal
        isOpen={isPaymentOpen}
        items={cartItems}
        customer={selectedCustomer}
        table={selectedTable}
        settings={settings}
        onClose={() => setIsPaymentOpen(false)}
        onConfirmOrder={handleConfirmOrder}
      />

      {/* Receipt Modal */}
      <ThermalReceiptModal
        isOpen={isReceiptOpen}
        order={lastCompletedOrder}
        items={lastCompletedOrder?.items || []}
        settings={settings}
        onClose={() => setIsReceiptOpen(false)}
      />

      {/* Held Orders Modal */}
      <HeldOrdersModal
        isOpen={isHeldModalOpen}
        heldOrders={heldOrders}
        onClose={() => setIsHeldModalOpen(false)}
        onRecall={handleRecallHeldOrder}
        onDelete={handleDeleteHeldOrder}
      />

      {/* Camera Scanner Modal */}
      <CameraScannerModal
        isOpen={isCameraOpen}
        onClose={() => setIsCameraOpen(false)}
        onScan={handleBarcodeScanned}
      />
    </div>
  );
};
