/**
 * OmniPOS Pro - Commercial Hardware Peripheral Engine
 * 1. Global USB/Bluetooth HID Barcode Scanner rapid-keystroke detection (<30ms buffer with Enter termination).
 * 2. Supermarket Scale Barcode Parser for EAN-13 prefixes 20-29 with strict checksum validation.
 * 3. ESC/POS Thermal Printer Engine (58mm/80mm) with WebSerial/WebUSB, cash drawer kick (\x1B\x70\x00\x19\xFA), and paper cut (\x1D\x56\x42\x00).
 * 4. Customer Pole Display / Second Screen sync via BroadcastChannel('omnipos_customer_display').
 */

export interface ParsedScaleBarcode {
  isValid: boolean;
  isScaleBarcode: boolean;
  prefix: string;
  plu: string;
  isWeightEmbedded: boolean;
  isPriceEmbedded: boolean;
  weightKg: number;
  embeddedPrice: number;
  rawBarcode: string;
  checkDigitValid: boolean;
}

export interface CustomerDisplayItem {
  id?: string;
  name: string;
  quantity: number;
  unitPrice: number;
  lineTotal: number;
  imageUrl?: string;
  isLatest?: boolean;
}

export interface CustomerDisplayPayload {
  type?: 'WELCOME' | 'CART_UPDATE' | 'ITEM_SCANNED' | 'PAYMENT' | 'COMPLETED';
  status?: 'WELCOME' | 'SCANNING' | 'PAYMENT' | 'COMPLETED';
  companyName: string;
  logoUrl?: string;
  primaryCurrencySymbol: string;
  secondaryCurrencySymbol: string;
  secondaryRate: number;
  items: CustomerDisplayItem[];
  subtotal: number;
  taxTotal: number;
  discountTotal: number;
  grandTotal: number;
  grandTotalSecondary: number;
  paidCash?: number;
  changeGiven?: number;
  changeCurrency?: string;
  activePromotion?: string;
  latestItem?: CustomerDisplayItem;
  timestamp?: number;
}

export class HardwareService {
  private static scannerBuffer: string = '';
  private static lastKeyTime: number = 0;
  // STRICT CONSTRAINT: <30ms buffer time between keystrokes indicates a hardware HID barcode gun
  private static readonly MAX_SCANNER_INTERVAL_MS = 30;
  private static scannerListeners: Array<(barcode: string) => void> = [];
  private static customerDisplayChannel: BroadcastChannel | null = null;

  /**
   * 1. Global USB/Bluetooth HID Barcode Scanner Listener
   * Detects rapid keystroke bursts (<30ms per character).
   * Works globally even if an input element is focused without polluting regular text typing.
   */
  public static initScannerListener(): () => void {
    if (typeof window === 'undefined') return () => {};

    const handleKeyDown = (event: KeyboardEvent) => {
      const now = performance.now();
      const timeDiff = now - this.lastKeyTime;
      this.lastKeyTime = now;

      const target = event.target as HTMLElement | null;
      const isInput = target && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.isContentEditable);

      // Check for Barcode Suffix Termination: Enter (\r or \n) or Tab (\t)
      if (event.key === 'Enter' || event.key === 'Tab') {
        if (this.scannerBuffer.length >= 3) {
          const barcode = this.cleanBarcode(this.scannerBuffer);
          this.scannerBuffer = '';

          // Intercept termination event
          event.preventDefault();
          event.stopPropagation();

          // If the burst was entered into a regular input, clear the raw barcode string from it
          if (isInput && target instanceof HTMLInputElement) {
            if (target.value.endsWith(barcode)) {
              target.value = target.value.slice(0, -barcode.length);
            }
          }

          this.notifyBarcodeScanned(barcode);
          return;
        }
        this.scannerBuffer = '';
        return;
      }

      // Single printable character
      if (event.key.length === 1) {
        // If interval is larger than 30ms, this is human manual typing: reset scanner buffer
        if (timeDiff > this.MAX_SCANNER_INTERVAL_MS) {
          this.scannerBuffer = '';
        }

        // If typing is fast (<30ms), accumulate into buffer
        this.scannerBuffer += event.key;

        // If burst has accumulated >= 3 characters at scanner speed inside an input,
        // intercept to prevent barcode noise from polluting the search input
        if (isInput && this.scannerBuffer.length >= 3 && timeDiff <= this.MAX_SCANNER_INTERVAL_MS) {
          event.preventDefault();
        }
      }
    };

    // Use capturing phase (true) to intercept scanner keystrokes before any child component handles them
    window.addEventListener('keydown', handleKeyDown, true);

    return () => {
      window.removeEventListener('keydown', handleKeyDown, true);
    };
  }

  public static onBarcode(callback: (barcode: string) => void): () => void {
    this.scannerListeners.push(callback);
    return () => {
      this.scannerListeners = this.scannerListeners.filter((cb) => cb !== callback);
    };
  }

  private static notifyBarcodeScanned(barcode: string): void {
    for (const listener of this.scannerListeners) {
      try {
        listener(barcode);
      } catch (err) {
        console.error('[HardwareService] Error in scanner listener:', err);
      }
    }
  }

  private static cleanBarcode(raw: string): string {
    // Strip non-printable ASCII, STX (0x02), ETX (0x03), CR, LF, and trim
    return raw.replace(/[\x00-\x1F\x7F-\x9F]/g, '').trim();
  }

  /**
   * 2. Supermarket Scale Barcode Parser (EAN-13 Prefixes 20 through 29)
   * Evaluates standard retail in-store scale barcodes:
   * Format: PP CCCCC WWWWW C
   * - PP: 2-digit prefix (20 to 29)
   * - CCCCC: 5-digit item PLU (Product Look-Up code)
   * - WWWWW: 5-digit embedded weight in grams (e.g. 00450 = 0.450 kg) OR embedded price in cents (00450 = $4.50)
   * - C: 1-digit EAN-13 Modulo-10 checksum digit
   */
  public static parseScaleBarcode(barcode: string, configuredPrefix: string = '21'): ParsedScaleBarcode {
    const clean = this.cleanBarcode(barcode);

    // Default negative result
    const invalidResult: ParsedScaleBarcode = {
      isValid: false,
      isScaleBarcode: false,
      prefix: '',
      plu: '',
      isWeightEmbedded: false,
      isPriceEmbedded: false,
      weightKg: 0,
      embeddedPrice: 0,
      rawBarcode: barcode,
      checkDigitValid: false,
    };

    if (clean.length !== 13 || !/^\d{13}$/.test(clean)) {
      return invalidResult;
    }

    const prefix = clean.substring(0, 2);
    const prefixNum = parseInt(prefix, 10);

    // Standard scale prefixes: 20 through 29
    if (prefixNum < 20 || prefixNum > 29) {
      return invalidResult;
    }

    // Verify EAN-13 Modulo-10 Checksum
    const checkDigitValid = this.validateEan13Checksum(clean);

    const plu = clean.substring(2, 7); // 5-digit item PLU code
    const valuePart = clean.substring(7, 12); // 5-digit value
    const rawVal = parseInt(valuePart, 10);

    // Prefixes 20 and 22 are conventionally price-embedded (cents: $PP.CC)
    // Prefixes 21, 23, 24, 28 are weight-embedded (grams: WW.WWW kg)
    const isPriceEmbedded = prefix === '20' || prefix === '22';
    const isWeightEmbedded = !isPriceEmbedded;

    const weightKg = isWeightEmbedded ? rawVal / 1000 : 0;
    const embeddedPrice = isPriceEmbedded ? rawVal / 100 : 0;

    return {
      isValid: checkDigitValid,
      isScaleBarcode: true,
      prefix,
      plu,
      isWeightEmbedded,
      isPriceEmbedded,
      weightKg,
      embeddedPrice,
      rawBarcode: clean,
      checkDigitValid,
    };
  }

  /**
   * EAN-13 Standard Modulo-10 Checksum Algorithm
   * Sum odd positions (weight 1) + sum even positions (weight 3)
   * Check digit = (10 - (sum % 10)) % 10
   */
  public static validateEan13Checksum(digits: string): boolean {
    if (digits.length !== 13) return false;

    let sum = 0;
    for (let i = 0; i < 12; i++) {
      const digit = parseInt(digits[i], 10);
      sum += i % 2 === 0 ? digit : digit * 3;
    }

    const expectedChecksum = (10 - (sum % 10)) % 10;
    const actualChecksum = parseInt(digits[12], 10);

    return expectedChecksum === actualChecksum;
  }

  /**
   * 3. ESC/POS Thermal Printer Engine (58mm & 80mm)
   * Generates binary ESC/POS bytecode including text formatting, barcode, drawer kick, and paper cut.
   */
  public static generateEscPosBytes(options: {
    companyName: string;
    invoiceNumber: string;
    dateStr: string;
    cashierName: string;
    items: Array<{ name: string; qty: number; price: number; total: number }>;
    subtotal: number;
    tax: number;
    discount: number;
    grandTotal: number;
    paidCash?: number;
    changeGiven?: number;
    footerText?: string;
    paperWidth?: '58mm' | '80mm';
    kickDrawer?: boolean;
    cutPaper?: boolean;
    includeBarcode?: boolean;
  }): Uint8Array {
    const bytes: number[] = [];
    const is58mm = options.paperWidth === '58mm';
    const lineWidth = is58mm ? 32 : 48;

    // ESC @: Initialize printer
    bytes.push(0x1b, 0x40);

    // ESC p m t1 t2: Kick Cash Drawer Pulse (\x1B\x70\x00\x19\xFA)
    if (options.kickDrawer !== false) {
      bytes.push(0x1b, 0x70, 0x00, 0x19, 0xfa);
    }

    const enc = new TextEncoder();
    const addRaw = (str: string) => {
      const arr = enc.encode(str);
      for (let i = 0; i < arr.length; i++) bytes.push(arr[i]);
    };

    const separator = '-'.repeat(lineWidth) + '\n';

    // Alignment: Center (ESC a 1)
    bytes.push(0x1b, 0x61, 0x01);

    // Double Height + Double Width for Company Header (GS ! 0x11)
    bytes.push(0x1d, 0x21, 0x11);
    addRaw(options.companyName + '\n');

    // Normal Text (GS ! 0x00)
    bytes.push(0x1d, 0x21, 0x00);
    addRaw(separator);

    // Alignment: Left (ESC a 0)
    bytes.push(0x1b, 0x61, 0x00);
    addRaw(`INVOICE: ${options.invoiceNumber}\n`);
    addRaw(`DATE:    ${options.dateStr}\n`);
    addRaw(`CASHIER: ${options.cashierName}\n`);
    addRaw(separator);

    // Line Items formatted with column width
    for (const item of options.items) {
      const totalStr = '$' + item.total.toFixed(2);
      const detailStr = `  ${item.qty} x $${item.price.toFixed(2)}`;
      const spaces = Math.max(1, lineWidth - detailStr.length - totalStr.length);

      addRaw(item.name.substring(0, lineWidth) + '\n');
      addRaw(detailStr + ' '.repeat(spaces) + totalStr + '\n');
    }

    addRaw(separator);

    // Totals Block
    const formatLine = (label: string, value: string) => {
      const spaces = Math.max(1, lineWidth - label.length - value.length);
      return label + ' '.repeat(spaces) + value + '\n';
    };

    addRaw(formatLine('Subtotal:', '$' + options.subtotal.toFixed(2)));
    if (options.discount > 0) {
      addRaw(formatLine('Discount:', '-$' + options.discount.toFixed(2)));
    }
    addRaw(formatLine('Tax:', '$' + options.tax.toFixed(2)));

    // Bold Total
    bytes.push(0x1b, 0x45, 0x01);
    bytes.push(0x1d, 0x21, 0x01); // Double height
    addRaw(formatLine('TOTAL:', '$' + options.grandTotal.toFixed(2)));
    bytes.push(0x1d, 0x21, 0x00);
    bytes.push(0x1b, 0x45, 0x00);

    if (options.paidCash !== undefined && options.paidCash > 0) {
      addRaw(formatLine('Cash Tendered:', '$' + options.paidCash.toFixed(2)));
      addRaw(formatLine('Change Due:', '$' + (options.changeGiven || 0).toFixed(2)));
    }

    // Barcode of invoice number (GS k)
    if (options.includeBarcode !== false) {
      // Center
      bytes.push(0x1b, 0x61, 0x01);
      bytes.push(0x1d, 0x68, 0x40); // Height: 64 dots
      bytes.push(0x1d, 0x77, 0x02); // Width: 2 dots
      bytes.push(0x1d, 0x48, 0x02); // Text below barcode
      // Code128 barcode: GS k 73 len data
      const cleanInv = options.invoiceNumber.replace(/[^A-Za-z0-9-]/g, '');
      const code128Bytes = [0x1d, 0x6b, 0x49, cleanInv.length];
      for (let i = 0; i < cleanInv.length; i++) code128Bytes.push(cleanInv.charCodeAt(i));
      bytes.push(...code128Bytes);
      addRaw('\n');
    }

    // Footer
    bytes.push(0x1b, 0x61, 0x01);
    addRaw(separator);
    if (options.footerText) {
      addRaw(options.footerText + '\n');
    }
    addRaw('Thank You for Shopping With Us!\n\n\n\n');

    // Automatic Paper Cut: GS V B 0 (\x1D\x56\x42\x00)
    if (options.cutPaper !== false) {
      bytes.push(0x1d, 0x56, 0x42, 0x00);
    }

    return new Uint8Array(bytes);
  }

  /**
   * Alias for generateEscPosBytes
   */
  public static generateEscPosReceipt = HardwareService.generateEscPosBytes;

  /**
   * Cash Drawer Kick Standalone Pulse (\x1B\x70\x00\x19\xFA)
   */
  public static getCashDrawerKickBytes(): Uint8Array {
    return new Uint8Array([0x1b, 0x70, 0x00, 0x19, 0xfa]);
  }

  /**
   * ESC/POS Serial Printer Alias
   */
  public static async printEscPosSerial(bytes: Uint8Array, baudRate: number = 9600): Promise<boolean> {
    return this.printViaWebSerial(bytes, baudRate);
  }

  /**
   * WebSerial Direct Port Connection
   */
  public static async printViaWebSerial(bytes: Uint8Array, baudRate: number = 9600): Promise<boolean> {
    if (typeof navigator !== 'undefined' && 'serial' in navigator) {
      try {
        const port = await (navigator as any).serial.requestPort();
        await port.open({ baudRate });
        const writer = port.writable.getWriter();
        await writer.write(bytes);
        writer.releaseLock();
        await port.close();
        return true;
      } catch (err) {
        console.warn('[HardwareService] WebSerial print error or cancelled:', err);
      }
    }
    // Fallback to standard window.print()
    window.print();
    return true;
  }

  /**
   * WebUSB Direct Thermal Printer Connection (Class 0x07 Printer)
   */
  public static async printViaWebUSB(bytes: Uint8Array): Promise<boolean> {
    if (typeof navigator !== 'undefined' && 'usb' in navigator) {
      try {
        const device = await (navigator as any).usb.requestDevice({
          filters: [{ classCode: 0x07 }] // Standard USB Printer Class
        });

        await device.open();
        if (device.configuration === null) {
          await device.selectConfiguration(1);
        }
        await device.claimInterface(0);

        // Find OUT bulk endpoint
        const endpoint = device.configuration.interfaces[0].alternate.endpoints.find(
          (ep: any) => ep.direction === 'out'
        );

        if (endpoint) {
          await device.transferOut(endpoint.endpointNumber, bytes);
        }

        await device.close();
        return true;
      } catch (err) {
        console.warn('[HardwareService] WebUSB printer error or cancelled:', err);
      }
    }
    // Fallback
    return this.printViaWebSerial(bytes);
  }

  /**
   * Standalone Cash Drawer Kick Trigger
   */
  public static async kickCashDrawer(): Promise<boolean> {
    const kickBytes = this.getCashDrawerKickBytes();
    return this.printViaWebSerial(kickBytes);
  }

  /**
   * 4. Customer Pole Display / Second Screen Sync via BroadcastChannel
   */
  public static initCustomerDisplayChannel(): BroadcastChannel {
    if (!this.customerDisplayChannel) {
      this.customerDisplayChannel = new BroadcastChannel('omnipos_customer_display');
    }
    return this.customerDisplayChannel;
  }

  public static broadcastToCustomerDisplay(payload: Omit<CustomerDisplayPayload, 'timestamp'>): void {
    const channel = this.initCustomerDisplayChannel();
    const fullPayload: CustomerDisplayPayload = {
      ...payload,
      timestamp: Date.now(),
    };
    channel.postMessage(fullPayload);
  }

  public static onCustomerDisplayMessage(callback: (payload: CustomerDisplayPayload) => void): () => void {
    const channel = this.initCustomerDisplayChannel();
    const handler = (event: MessageEvent) => {
      if (event.data) {
        callback(event.data);
      }
    };
    channel.addEventListener('message', handler);
    return () => channel.removeEventListener('message', handler);
  }
}
