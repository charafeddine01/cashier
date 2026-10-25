import React from 'react';
import { CartItem, Customer, CompanySettings, RestaurantTable } from '../../types';
import { I18nService } from '../../services/i18nService';
import {
  Trash2,
  Plus,
  Minus,
  User,
  PauseCircle,
  Tag,
  CreditCard,
  Percent,
  Edit2,
  Utensils,
  ChevronDown
} from 'lucide-react';

interface Props {
  items: CartItem[];
  selectedCustomer: Customer | null;
  selectedTable: RestaurantTable | null;
  heldOrdersCount: number;
  settings: CompanySettings | null;
  onUpdateQty: (itemId: string, newQty: number) => void;
  onRemoveItem: (itemId: string) => void;
  onOpenCustomerSelect: () => void;
  onOpenTableSelect: () => void;
  onHoldOrder: () => void;
  onRecallHeldOrders: () => void;
  onClearCart: () => void;
  onOpenPayment: () => void;
  onOpenDiscountModal: (item?: CartItem) => void;
  onOpenPriceOverrideModal: (item: CartItem) => void;
  onOpenModifierModal: (item: CartItem) => void;
}

export const CartList: React.FC<Props> = ({
  items,
  selectedCustomer,
  selectedTable,
  heldOrdersCount,
  settings,
  onUpdateQty,
  onRemoveItem,
  onOpenCustomerSelect,
  onOpenTableSelect,
  onHoldOrder,
  onRecallHeldOrders,
  onClearCart,
  onOpenPayment,
  onOpenDiscountModal,
  onOpenPriceOverrideModal,
  onOpenModifierModal,
}) => {
  const primarySymbol = settings?.primary_currency_symbol || '$';
  const secondarySymbol = settings?.secondary_currency_symbol || '€';
  const secondaryRate = settings?.secondary_currency_rate || 0.92;
  const isRestaurant = settings?.business_mode === 'RESTAURANT';

  // Compute totals
  const subtotal = items.reduce((sum, item) => sum + (item.unit_price * item.quantity), 0);
  const discountTotal = items.reduce((sum, item) => sum + (item.discount_amount || 0), 0);
  const taxTotal = items.reduce((sum, item) => sum + (item.tax_amount || 0), 0);
  const grandTotal = Math.max(0, subtotal + taxTotal - discountTotal);
  const grandTotalSecondary = grandTotal * secondaryRate;

  return (
    <div className="flex flex-col h-full bg-slate-900 border-l border-slate-800 select-none">
      {/* Customer & Restaurant Table Bar */}
      <div className="p-2.5 bg-slate-900 border-b border-slate-800 grid grid-cols-2 gap-2 shrink-0">
        <button
          onClick={onOpenCustomerSelect}
          className="flex items-center justify-between gap-1.5 bg-slate-800 hover:bg-slate-750 border border-slate-700/80 rounded-xl px-2.5 py-1.5 text-xs text-left transition"
        >
          <div className="flex items-center gap-1.5 truncate">
            <User className="w-3.5 h-3.5 text-blue-400 shrink-0" />
            <span className="font-semibold text-white truncate">
              {selectedCustomer ? selectedCustomer.name : I18nService.t('pos.walk_in')}
            </span>
          </div>
          <ChevronDown className="w-3 h-3 text-slate-400 shrink-0" />
        </button>

        {isRestaurant ? (
          <button
            onClick={onOpenTableSelect}
            className="flex items-center justify-between gap-1.5 bg-slate-800 hover:bg-slate-750 border border-slate-700/80 rounded-xl px-2.5 py-1.5 text-xs text-left transition"
          >
            <div className="flex items-center gap-1.5 truncate">
              <Utensils className="w-3.5 h-3.5 text-rose-400 shrink-0" />
              <span className="font-semibold text-white truncate">
                {selectedTable ? `Table: ${selectedTable.table_number}` : 'Select Table'}
              </span>
            </div>
            <ChevronDown className="w-3 h-3 text-slate-400 shrink-0" />
          </button>
        ) : (
          <button
            onClick={onRecallHeldOrders}
            className="flex items-center justify-between gap-1.5 bg-slate-800 hover:bg-slate-750 border border-slate-700/80 rounded-xl px-2.5 py-1.5 text-xs text-left transition"
          >
            <div className="flex items-center gap-1.5 truncate">
              <PauseCircle className="w-3.5 h-3.5 text-amber-400 shrink-0" />
              <span className="font-semibold text-white truncate">{I18nService.t('pos.recall_order')}</span>
            </div>
            {heldOrdersCount > 0 && (
              <span className="bg-amber-500 text-slate-950 font-black px-1.5 py-0.2 rounded-full text-[10px]">
                {heldOrdersCount}
              </span>
            )}
          </button>
        )}
      </div>

      {/* Cart Items List */}
      <div className="flex-1 overflow-y-auto p-2.5 space-y-2">
        {items.length === 0 ? (
          <div className="h-full flex flex-col items-center justify-center text-center p-6 text-slate-400">
            <div className="w-12 h-12 bg-slate-800/80 rounded-2xl flex items-center justify-center mb-2">
              <Tag className="w-6 h-6 text-slate-500 stroke-1" />
            </div>
            <p className="text-xs font-semibold text-slate-300">{I18nService.t('pos.cart_empty')}</p>
          </div>
        ) : (
          items.map((item) => (
            <div
              key={item.id}
              className="bg-slate-800/70 border border-slate-700/60 rounded-2xl p-2.5 flex flex-col gap-1.5 hover:border-slate-600 transition"
            >
              {/* Top Row: Name & Delete */}
              <div className="flex items-start justify-between gap-2">
                <div className="flex-1">
                  <h4 className="text-xs font-bold text-white leading-tight">{item.product_name}</h4>
                  <div className="flex items-center gap-1.5 text-[10px] text-slate-400 font-mono mt-0.5">
                    {item.variant_name && <span className="text-indigo-400 font-semibold">{item.variant_name}</span>}
                    {item.batch_number && <span className="text-emerald-400">Exp: {item.expiry_date}</span>}
                    <span>{primarySymbol}{item.unit_price.toFixed(2)}</span>
                  </div>

                  {/* Modifiers Pill list */}
                  {item.modifiers && item.modifiers.length > 0 && (
                    <div className="flex flex-wrap gap-1 mt-1">
                      {item.modifiers.map((m, idx) => (
                        <span key={idx} className="bg-slate-900 border border-slate-700 text-[9px] text-purple-300 px-1.5 py-0.5 rounded-md">
                          +{m.name} {m.price > 0 && `($${m.price.toFixed(2)})`}
                        </span>
                      ))}
                    </div>
                  )}
                </div>

                <button
                  onClick={() => onRemoveItem(item.id)}
                  className="p-1 hover:bg-rose-500/20 text-slate-400 hover:text-rose-400 rounded-lg transition"
                  title="Remove item"
                >
                  <Trash2 className="w-3.5 h-3.5" />
                </button>
              </div>

              {/* Bottom Row: Quantity Stepper & Line Total */}
              <div className="flex items-center justify-between pt-1 border-t border-slate-700/40">
                {/* Stepper */}
                <div className="flex items-center gap-1 bg-slate-900 border border-slate-700/80 rounded-xl p-0.5">
                  <button
                    onClick={() => onUpdateQty(item.id, item.quantity - (item.is_fractional ? 0.25 : 1))}
                    className="w-6 h-6 rounded-lg bg-slate-800 hover:bg-slate-700 text-white flex items-center justify-center transition"
                  >
                    <Minus className="w-3 h-3" />
                  </button>
                  <input
                    type="number"
                    step={item.is_fractional ? '0.001' : '1'}
                    value={item.quantity}
                    onChange={(e) => onUpdateQty(item.id, parseFloat(e.target.value) || 0)}
                    className="w-12 bg-transparent text-center text-xs font-bold text-white focus:outline-none"
                  />
                  <button
                    onClick={() => onUpdateQty(item.id, item.quantity + (item.is_fractional ? 0.25 : 1))}
                    className="w-6 h-6 rounded-lg bg-slate-800 hover:bg-slate-700 text-white flex items-center justify-center transition"
                  >
                    <Plus className="w-3 h-3" />
                  </button>
                </div>

                {/* Quick item action triggers */}
                <div className="flex items-center gap-1">
                  <button
                    onClick={() => onOpenDiscountModal(item)}
                    className="p-1 text-slate-400 hover:text-amber-400 hover:bg-slate-700 rounded-lg transition"
                    title="Line Discount"
                  >
                    <Percent className="w-3.5 h-3.5" />
                  </button>
                  <button
                    onClick={() => onOpenPriceOverrideModal(item)}
                    className="p-1 text-slate-400 hover:text-blue-400 hover:bg-slate-700 rounded-lg transition"
                    title="Price Override"
                  >
                    <Edit2 className="w-3.5 h-3.5" />
                  </button>
                </div>

                {/* Line Total */}
                <div className="text-right">
                  <div className="text-xs font-black text-white">
                    {primarySymbol}{item.line_total.toFixed(2)}
                  </div>
                  {item.discount_amount > 0 && (
                    <div className="text-[9px] text-amber-400 font-semibold">
                      Disc: -{primarySymbol}{item.discount_amount.toFixed(2)}
                    </div>
                  )}
                </div>
              </div>
            </div>
          ))
        )}
      </div>

      {/* Cart Summary & Checkout Actions */}
      <div className="p-3 bg-slate-900 border-t border-slate-800 shrink-0 space-y-2.5">
        {/* Breakdown */}
        <div className="space-y-1 text-xs">
          <div className="flex justify-between text-slate-400">
            <span>{I18nService.t('pos.subtotal')}</span>
            <span className="font-semibold text-slate-200">{primarySymbol}{subtotal.toFixed(2)}</span>
          </div>
          {discountTotal > 0 && (
            <div className="flex justify-between text-amber-400">
              <span>{I18nService.t('pos.discount')}</span>
              <span className="font-semibold">-{primarySymbol}{discountTotal.toFixed(2)}</span>
            </div>
          )}
          <div className="flex justify-between text-slate-400">
            <span>{I18nService.t('pos.tax')}</span>
            <span className="font-semibold text-slate-200">{primarySymbol}{taxTotal.toFixed(2)}</span>
          </div>

          <div className="pt-2 border-t border-slate-800 flex justify-between items-baseline">
            <div>
              <span className="text-xs font-bold text-slate-300">{I18nService.t('pos.grand_total')}</span>
              <div className="text-[10px] text-slate-400 font-semibold">
                {secondarySymbol}{grandTotalSecondary.toFixed(2)}
              </div>
            </div>
            <div className="text-2xl font-black text-white tracking-tight text-right">
              {primarySymbol}{grandTotal.toFixed(2)}
            </div>
          </div>
        </div>

        {/* Buttons Grid */}
        <div className="grid grid-cols-2 gap-2">
          <button
            onClick={onHoldOrder}
            disabled={items.length === 0}
            className="bg-slate-800 hover:bg-slate-750 disabled:opacity-40 border border-slate-700 py-2.5 rounded-2xl text-xs font-bold text-amber-400 transition flex items-center justify-center gap-1.5"
          >
            <PauseCircle className="w-4 h-4" />
            <span>{I18nService.t('pos.hold_order')}</span>
          </button>

          <button
            onClick={onClearCart}
            disabled={items.length === 0}
            className="bg-slate-800 hover:bg-rose-500/20 disabled:opacity-40 border border-slate-700 hover:border-rose-500/40 py-2.5 rounded-2xl text-xs font-bold text-slate-400 hover:text-rose-400 transition flex items-center justify-center gap-1.5"
          >
            <Trash2 className="w-4 h-4" />
            <span>{I18nService.t('pos.clear_cart')}</span>
          </button>
        </div>

        {/* Big Pay Button */}
        <button
          onClick={onOpenPayment}
          disabled={items.length === 0}
          className="w-full bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 disabled:opacity-40 text-white font-black py-4 rounded-2xl shadow-xl shadow-blue-600/30 transition active:scale-[0.99] flex items-center justify-between px-5"
        >
          <div className="flex items-center gap-2">
            <CreditCard className="w-5 h-5" />
            <span className="text-sm tracking-wide">{I18nService.t('pos.charge')} [F12]</span>
          </div>
          <span className="text-lg font-black">{primarySymbol}{grandTotal.toFixed(2)}</span>
        </button>
      </div>
    </div>
  );
};
