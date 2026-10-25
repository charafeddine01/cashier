export type BusinessMode = 'SUPERMARKET' | 'RESTAURANT' | 'RETAIL' | 'PHARMACY';

export type UserRole = 'ADMIN' | 'MANAGER' | 'CASHIER' | 'WAITER' | 'KITCHEN';

export interface User {
  id: number;
  username: string;
  full_name: string;
  role: UserRole;
  email?: string;
  phone?: string;
  is_active: number | boolean;
}

export interface CompanySettings {
  id: number;
  company_name: string;
  company_name_ar?: string;
  tax_id: string;
  address: string;
  address_ar?: string;
  phone: string;
  email: string;
  website: string;
  primary_logo_url?: string;
  receipt_logo_url?: string;
  favicon_url?: string;
  primary_color: string;
  secondary_color: string;
  theme_mode: 'light' | 'dark' | 'system';
  business_mode: BusinessMode;
  primary_currency_code: string;
  primary_currency_symbol: string;
  secondary_currency_code: string;
  secondary_currency_symbol: string;
  secondary_currency_rate: number;
  tax_enabled: number | boolean;
  tax_inclusive: number | boolean;
  default_tax_rate: number;
  receipt_header?: string;
  receipt_footer?: string;
  receipt_width: '58mm' | '80mm' | 'A4';
  enable_customer_display: number | boolean;
  scale_barcode_prefix: string;
}

export interface Category {
  id: number;
  name: string;
  name_ar?: string;
  icon: string;
  color: string;
  sort_order: number;
  is_active: number;
}

export interface Unit {
  id: number;
  code: string;
  name: string;
  name_ar?: string;
  is_fractional: number;
}

export interface TaxRate {
  id: number;
  name: string;
  rate_percent: number;
  is_default: number;
  is_active: number;
}

export interface Warehouse {
  id: number;
  code: string;
  name: string;
  location?: string;
  is_default: number;
  is_active: number;
}

export interface ProductVariant {
  id: number;
  product_id: number;
  sku: string;
  barcode?: string;
  variant_name: string;
  attribute_matrix: Record<string, string>;
  cost_price: number;
  selling_price: number;
}

export interface ProductUnit {
  id: number;
  product_id: number;
  unit_id: number;
  unit_name?: string;
  unit_code?: string;
  conversion_factor: number;
  barcode: string;
  selling_price: number;
  is_default_sale: number;
}

export interface ProductBatch {
  id: number;
  product_id: number;
  warehouse_id: number;
  batch_number: string;
  expiry_date: string;
  quantity_received: number;
  quantity_remaining: number;
  cost_price: number;
}

export interface ProductRecipe {
  id: number;
  parent_product_id: number;
  ingredient_product_id: number;
  ingredient_name?: string;
  unit_code?: string;
  quantity: number;
  unit_id: number;
}

export interface Product {
  id: number;
  sku: string;
  barcode?: string;
  name: string;
  name_ar?: string;
  category_id?: number;
  category_name?: string;
  unit_id: number;
  unit_code?: string;
  is_fractional?: number;
  tax_rate_id?: number;
  tax_rate_percent?: number;
  type: 'STANDARD' | 'VARIABLE' | 'COMBO' | 'SCALE';
  cost_price: number;
  selling_price: number;
  secondary_selling_price?: number;
  track_inventory: number;
  alert_quantity: number;
  stock_quantity?: number;
  scale_plu?: string;
  image_url?: string;
  modifiers_schema?: string;
  is_kitchen_item?: number;
  kitchen_station?: string;
  warranty_months?: number;
  is_active: number;
  variants?: ProductVariant[];
  recipes?: ProductRecipe[];
  units?: ProductUnit[];
  batches?: ProductBatch[];
}

export interface ModifierOption {
  name: string;
  price: number;
}

export interface ModifierGroup {
  group: string;
  required?: boolean;
  options: ModifierOption[];
}

export interface CartItemModifier {
  group: string;
  name: string;
  price: number;
}

export interface CartItem {
  id: string; // unique item identifier in cart
  product_id: number;
  product_name: string;
  sku: string;
  barcode?: string;
  unit_id: number;
  unit_code: string;
  is_fractional: boolean;
  quantity: number;
  unit_cost: number;
  unit_price: number;
  original_unit_price: number;
  discount_amount: number;
  discount_percent: number;
  tax_rate: number;
  tax_amount: number;
  line_total: number;
  variant_id?: number;
  variant_name?: string;
  batch_id?: number;
  batch_number?: string;
  expiry_date?: string;
  selected_unit_id?: number;
  unit_conversion_factor?: number;
  modifiers: CartItemModifier[];
  notes?: string;
  serial_numbers?: string[];
  is_kitchen_item?: number;
}

export interface Customer {
  id: number;
  name: string;
  phone?: string;
  email?: string;
  address?: string;
  tax_number?: string;
  credit_limit: number;
  current_balance: number;
  loyalty_points: number;
  is_active: number;
}

export interface CustomerLedgerEntry {
  id: number;
  customer_id: number;
  transaction_type: 'INVOICE' | 'PAYMENT' | 'REFUND_CREDIT' | 'ADJUSTMENT';
  amount: number;
  balance_after: number;
  reference_type?: string;
  reference_id?: number;
  notes?: string;
  created_at: string;
}

export interface Shift {
  id: number;
  cashier_id: number;
  cashier_name?: string;
  terminal_id: string;
  opened_at: string;
  closed_at?: string;
  opening_float: number;
  expected_cash: number;
  counted_cash: number;
  cash_difference: number;
  total_sales_cash: number;
  total_sales_card: number;
  total_sales_credit: number;
  total_sales_on_account: number;
  total_returns: number;
  total_cash_in: number;
  total_cash_out: number;
  status: 'OPEN' | 'CLOSED';
  notes?: string;
  closing_breakdown?: string;
}

export interface CashMovement {
  id: number;
  shift_id: number;
  cashier_id: number;
  type: 'CASH_IN' | 'CASH_OUT';
  amount: number;
  reason: string;
  created_at: string;
}

export interface RestaurantTable {
  id: number;
  floor_id: number;
  table_number: string;
  capacity: number;
  position_x: number;
  position_y: number;
  width: number;
  height: number;
  shape: 'RECTANGLE' | 'ROUND';
  status: 'AVAILABLE' | 'OCCUPIED' | 'BILL_PRINTED';
  current_order_id?: number;
  current_order_total?: number;
}

export interface RestaurantFloor {
  id: number;
  name: string;
  sort_order: number;
  is_active: number;
}

export interface KdsOrder {
  order_id: number;
  invoice_number: string;
  created_at: string;
  order_type: string;
  table_number?: string;
  waiter_name?: string;
  elapsed_seconds: number;
  items: Array<{
    id: number;
    product_name: string;
    quantity: number;
    kitchen_status: 'PENDING' | 'PREPARING' | 'READY' | 'SERVED';
    kitchen_station?: string;
    notes?: string;
    modifiers_parsed?: CartItemModifier[];
  }>;
}

export interface LicenseStatus {
  is_active: boolean;
  status: 'ACTIVE' | 'EXPIRED' | 'TAMPERED' | 'UNLICENSED';
  license_id: string;
  client_name: string;
  plan_tier: string;
  max_terminals: number;
  enabled_modules: string[];
  allowed_domains: string[];
  issued_at: number;
  expires_at: number;
  days_remaining: number;
  is_tampered: boolean;
  tamper_reason?: string;
  installation_fingerprint: string;
  server_time: number;
}

export interface ApiEnvelope<T = any> {
  action: string;
  terminal_id: string;
  client_timestamp: number;
  auth_token?: string | null;
  payload?: T;
}

export interface ApiResponse<T = any> {
  success: boolean;
  message: string;
  data: T;
  error?: {
    code: string;
    message: string;
    details?: any;
  } | null;
  code: number;
  timestamp: number;
}
