/**
 * OmniPOS Pro - Multilingual & RTL/LTR Internationalization Engine
 * Built-in Arabic and English dictionaries with instant document direction switching.
 */

export type LanguageCode = 'en' | 'ar';

export interface Translations {
  [key: string]: string;
}

const enTranslations: Translations = {
  // Navigation & General
  'app.title': 'OmniPOS Pro',
  'nav.pos': 'Register / POS',
  'nav.restaurant': 'Tables & Floor',
  'nav.kds': 'Kitchen (KDS)',
  'nav.inventory': 'Inventory & Stock',
  'nav.products': 'Products & Catalog',
  'nav.returns': 'Returns & Refunds',
  'nav.shifts': 'Shifts & Drawer',
  'nav.customers': 'Customers & CRM',
  'nav.reports': 'Reports & Analytics',
  'nav.settings': 'Settings & Branding',
  'nav.customer_display': 'Pole Display',
  'common.search': 'Search items, SKU, or barcode...',
  'common.all': 'All',
  'common.cancel': 'Cancel',
  'common.confirm': 'Confirm',
  'common.save': 'Save Changes',
  'common.delete': 'Delete',
  'common.edit': 'Edit',
  'common.close': 'Close',
  'common.loading': 'Loading...',
  'common.success': 'Success',
  'common.error': 'Error',
  'common.offline': 'Offline Mode',
  'common.online': 'Online',
  'common.cashier': 'Cashier',
  'common.switch_cashier': 'Switch Cashier',
  'common.logout': 'Logout',

  // POS Register
  'pos.cart_empty': 'Cart is empty. Scan an item or select a product.',
  'pos.subtotal': 'Subtotal',
  'pos.tax': 'Tax',
  'pos.discount': 'Discount',
  'pos.grand_total': 'Grand Total',
  'pos.secondary_total': 'Secondary Total',
  'pos.charge': 'Charge / Pay',
  'pos.hold_order': 'Hold Order',
  'pos.recall_order': 'Held Orders',
  'pos.clear_cart': 'Clear Cart',
  'pos.select_customer': 'Assign Customer',
  'pos.walk_in': 'Walk-in Cash Customer',
  'pos.pay_cash': 'Cash',
  'pos.pay_card': 'Credit / Debit Card',
  'pos.pay_split': 'Split Tender',
  'pos.pay_on_account': 'On-Account (Debt)',
  'pos.amount_tendered': 'Amount Tendered',
  'pos.change_due': 'Change Due',
  'pos.finish_sale': 'Complete Sale & Print',
  'pos.print_receipt': 'Print Receipt',
  'pos.new_sale': 'New Sale',
  'pos.barcode_camera': 'Camera Scanner',
  'pos.scale_weighed': 'Weighed Item',
  'pos.scale_detected': 'Supermarket Scale Barcode Detected',
  'pos.modifiers': 'Item Modifiers',

  // Shifts
  'shift.open_title': 'Open Register Shift',
  'shift.opening_float': 'Opening Float Cash',
  'shift.open_btn': 'Start Shift',
  'shift.active_shift': 'Active Shift Session',
  'shift.cash_in': 'Cash In',
  'shift.cash_out': 'Cash Out',
  'shift.x_report': 'Print X-Report (Mid-Shift)',
  'shift.z_report': 'End Shift & Z-Report (Blind Count)',
  'shift.counted_cash': 'Counted Cash in Drawer',
  'shift.expected_cash': 'Expected Cash in Drawer',
  'shift.variance': 'Cash Variance (Short/Over)',
  'shift.denominations': 'Denomination Counter',
  'shift.close_btn': 'Close Shift & Finalize',

  // Restaurant
  'rest.tables': 'Table Layout',
  'rest.dine_in': 'Dine-In',
  'rest.takeaway': 'Takeaway',
  'rest.delivery': 'Delivery',
  'rest.table_available': 'Available',
  'rest.table_occupied': 'Occupied',
  'rest.bill_printed': 'Bill Printed',
  'rest.assign_table': 'Select Table',
  'rest.kds_title': 'Kitchen Display System (KDS)',
  'rest.kot_title': 'Kitchen Order Ticket (KOT)',
  'rest.mark_preparing': 'Preparing',
  'rest.mark_ready': 'Ready for Server',
  'rest.mark_served': 'Served',

  // Returns
  'returns.title': 'Returns & Refund Terminal',
  'returns.scan_invoice': 'Scan or Enter Original Invoice Number',
  'returns.items_to_return': 'Select Items & Quantities to Return',
  'returns.restock_inventory': 'Restock into Inventory',
  'returns.waste_damaged': 'Discard as Damaged / Waste',
  'returns.refund_via_cash': 'Refund as Cash',
  'returns.refund_via_credit': 'Credit Customer Store Account',
  'returns.process_btn': 'Process Return & Issue Refund',

  // Licensing
  'lic.title': 'OmniPOS Commercial License',
  'lic.status': 'License Status',
  'lic.days_remaining': 'Days Remaining',
  'lic.activate_key': 'Activate License Key',
  'lic.paste_key': 'Paste Base64 License Certificate',
  'lic.clock_tampered': 'Clock Rollback Detected! Server time was rolled back.',
  'lic.tamper_notice': 'POS is locked for security integrity until online verification.',
};

const arTranslations: Translations = {
  // Navigation & General
  'app.title': 'أومني بوس برو',
  'nav.pos': 'نقطة البيع / الكاشير',
  'nav.restaurant': 'الطاولات والصالة',
  'nav.kds': 'شاشة المطبخ (KDS)',
  'nav.inventory': 'المخزون والمستودع',
  'nav.products': 'المنتجات والكتالوج',
  'nav.returns': 'المرتجعات والاسترداد',
  'nav.shifts': 'الورديات ودرج النقد',
  'nav.customers': 'العملاء والذمم',
  'nav.reports': 'التقارير والأرباح',
  'nav.settings': 'الإعدادات والهوية',
  'nav.customer_display': 'شاشة العميل',
  'common.search': 'ابحث بالاسم، الكود، أو الباركود...',
  'common.all': 'الكل',
  'common.cancel': 'إلغاء',
  'common.confirm': 'تأكيد',
  'common.save': 'حفظ التعديلات',
  'common.delete': 'حذف',
  'common.edit': 'تعديل',
  'common.close': 'إغلاق',
  'common.loading': 'جاري التحميل...',
  'common.success': 'تم بنجاح',
  'common.error': 'خطأ',
  'common.offline': 'وضع عدم الاتصال (أوفلاين)',
  'common.online': 'متصل بالشبكة',
  'common.cashier': 'الكاشير',
  'common.switch_cashier': 'تبديل الكاشير (PIN)',
  'common.logout': 'تسجيل خروج',

  // POS Register
  'pos.cart_empty': 'السلة فارغة. قم بمسح باركود أو اختر منتجاً.',
  'pos.subtotal': 'المجموع الفرعي',
  'pos.tax': 'الضريبة',
  'pos.discount': 'الخصم',
  'pos.grand_total': 'المجموع الإجمالي',
  'pos.secondary_total': 'الإجمالي بالعملة الثانية',
  'pos.charge': 'دفع / محاسبة',
  'pos.hold_order': 'تعليق الفاتورة',
  'pos.recall_order': 'الفواتير المعلقة',
  'pos.clear_cart': 'إفراغ السلة',
  'pos.select_customer': 'تحديد العميل',
  'pos.walk_in': 'عميل نقدي عام',
  'pos.pay_cash': 'نقداً (كاش)',
  'pos.pay_card': 'بطاقة مصرفية / مدى',
  'pos.pay_split': 'دفع مجزأ (نقد + بطاقة)',
  'pos.pay_on_account': 'آجل على الحساب (ذمم)',
  'pos.amount_tendered': 'المبلغ المستلم',
  'pos.change_due': 'المتبقي للعميل',
  'pos.finish_sale': 'إتمام البيع وطباعة الإيصال',
  'pos.print_receipt': 'طباعة الإيصال',
  'pos.new_sale': 'فاتورة جديدة',
  'pos.barcode_camera': 'ماسح الكاميرا',
  'pos.scale_weighed': 'صنف وزني (ميزان)',
  'pos.scale_detected': 'تم كشف باركود ميزان إلكتروني',
  'pos.modifiers': 'الإضافات والتعديلات',

  // Shifts
  'shift.open_title': 'فتح وردية الكاشير',
  'shift.opening_float': 'رصيد العهدة الافتتاحي (الكاش)',
  'shift.open_btn': 'بدء الوردية',
  'shift.active_shift': 'وردية نشطة حالياً',
  'shift.cash_in': 'إيداع نقدي (Cash In)',
  'shift.cash_out': 'سحب نقدي (Cash Out)',
  'shift.x_report': 'تقرير X النصفي (بدون إغلاق)',
  'shift.z_report': 'تقرير Z النهائي وإغلاق الصندوق',
  'shift.counted_cash': 'النقد الفعلي في الدرج',
  'shift.expected_cash': 'النقد المتوقع في الدرج',
  'shift.variance': 'الفارق (عجز / زيادة)',
  'shift.denominations': 'حاسبة الفئات النقدية',
  'shift.close_btn': 'إغلاق الوردية والترحيل',

  // Restaurant
  'rest.tables': 'خريطة الطاولات',
  'rest.dine_in': 'محلي (داخل المطعم)',
  'rest.takeaway': 'سفري (تيك أواي)',
  'rest.delivery': 'توصيل منزلي',
  'rest.table_available': 'متاحة',
  'rest.table_occupied': 'مشغولة',
  'rest.bill_printed': 'تمت طباعة الحساب',
  'rest.assign_table': 'اختر الطاولة',
  'rest.kds_title': 'شاشة عرض المطبخ (KDS)',
  'rest.kot_title': 'تذكرة طلب المطبخ (KOT)',
  'rest.mark_preparing': 'جاري التحضير',
  'rest.mark_ready': 'جاهز للتقديم',
  'rest.mark_served': 'تم التقديم',

  // Returns
  'returns.title': 'شاشة المرتجعات واسترداد الأموال',
  'returns.scan_invoice': 'امسح أو أدخل رقم الفاتورة الأصلية',
  'returns.items_to_return': 'اختر الأصناف والكميات المراد إرجاعها',
  'returns.restock_inventory': 'إعادة إلى المستودع (صالح للبيع)',
  'returns.waste_damaged': 'إتلاف (صنف تالف / غير صالح)',
  'returns.refund_via_cash': 'استرداد نقدي (من درج الكاشير)',
  'returns.refund_via_credit': 'رصيد دائن في حساب العميل',
  'returns.process_btn': 'تنفيذ الإرجاع وطباعة سند الصرف',

  // Licensing
  'lic.title': 'ترخيص برنامج أومني بوس',
  'lic.status': 'حالة الترخيص',
  'lic.days_remaining': 'الأيام المتبقية',
  'lic.activate_key': 'تفعيل كود الترخيص',
  'lic.paste_key': 'الصق كود الترخيص المشفر (Base64)',
  'lic.clock_tampered': 'تم اكتشاف تلاعب بساعة النظام! تم إرجاع الوقت إلى الوراء.',
  'lic.tamper_notice': 'تم إيقاف النظام حمايةً للبيانات حتى تتم إعادة المزامنة مع خادم الترخيص.',
};

export class I18nService {
  private static currentLang: LanguageCode = (localStorage.getItem('omnipos_lang') as LanguageCode) || 'en';
  private static listeners: Array<(lang: LanguageCode) => void> = [];

  public static init(): void {
    this.applyLanguage(this.currentLang);
  }

  public static getLanguage(): LanguageCode {
    return this.currentLang;
  }

  public static setLanguage(lang: LanguageCode): void {
    this.currentLang = lang;
    localStorage.setItem('omnipos_lang', lang);
    this.applyLanguage(lang);
    for (const listener of this.listeners) {
      listener(lang);
    }
  }

  public static onLanguageChange(callback: (lang: LanguageCode) => void): () => void {
    this.listeners.push(callback);
    return () => {
      this.listeners = this.listeners.filter((cb) => cb !== callback);
    };
  }

  private static applyLanguage(lang: LanguageCode): void {
    if (typeof document === 'undefined') return;
    const isRtl = lang === 'ar';
    document.documentElement.dir = isRtl ? 'rtl' : 'ltr';
    document.documentElement.lang = lang;
    if (isRtl) {
      document.body.classList.add('rtl');
    } else {
      document.body.classList.remove('rtl');
    }
  }

  public static t(key: string, fallback?: string): string {
    const dict = this.currentLang === 'ar' ? arTranslations : enTranslations;
    return dict[key] || fallback || key;
  }
}
