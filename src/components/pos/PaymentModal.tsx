import React, { useState } from 'react';
import { CartItem, Customer, CompanySettings, RestaurantTable } from '../../types';
import { I18nService } from '../../services/i18nService';
import {
  X,
  CreditCard,
  Banknote,
  Split,
  FileText,
  Calendar,
  Printer,
  CheckCircle2,
  DollarSign,
  AlertCircle
} from 'lucide-react';

interface Props {
  isOpen: boolean;
  items: CartItem[];
  customer: Customer | null;
  table: RestaurantTable | null;
  settings: CompanySettings | null;
  onClose: () => void;
  onConfirmOrder: (paymentData: {
    paidCash: number;
    paidCard: number;
    paidCredit: number;
    paidOnAccount: number;
    changeGiven: number;
    changeCurrency: 'PRIMARY' | 'SECONDARY';
    exchangeRate: number;
    installmentPlan?: {
      total_amount: number;
      down_payment: number;
      total_installments: number;
      frequency: 'MONTHLY' | 'WEEKLY';
      first_due_date: string;
      notes?: string;
    };
  }) => Promise<void>;
}

export const PaymentModal: React.FC<Props> = ({
  isOpen,
  items,
  customer,
  table,
  settings,
  onClose,
  onConfirmOrder,
}) => {
  if (!isOpen) return null;

  const primarySymbol = settings?.primary_currency_symbol || '$';
  const secondarySymbol = settings?.secondary_currency_symbol || 'L.L.';
  const secondaryRate = settings?.secondary_currency_rate || 89500;

  const subtotal = items.reduce((sum, item) => sum + (item.unit_price * item.quantity), 0);
  const discountTotal = items.reduce((sum, item) => sum + (item.discount_amount || 0), 0);
  const taxTotal = items.reduce((sum, item) => sum + (item.tax_amount || 0), 0);
  const grandTotal = Math.max(0, subtotal + taxTotal - discountTotal);
  const grandTotalSecondary = grandTotal * secondaryRate;

  const [paymentType, setPaymentType] = useState<'CASH' | 'CARD' | 'SPLIT' | 'ON_ACCOUNT' | 'INSTALLMENT'>('CASH');
  const [tenderedCash, setTenderedCash] = useState<number>(grandTotal);
  const [tenderedCard, setTenderedCard] = useState<number>(0);
  const [tenderedOnAccount, setTenderedOnAccount] = useState<number>(0);
  const [changeCurrency, setChangeCurrency] = useState<'PRIMARY' | 'SECONDARY'>('PRIMARY');

  // Installment specific states
  const [downPayment, setDownPayment] = useState<number>(Math.round(grandTotal * 0.3));
  const [installmentCount, setInstallmentCount] = useState<number>(4);
  const defaultDueDate = new Date();
  defaultDueDate.setDate(defaultDueDate.getDate() + 30);
  const [firstDueDate, setFirstDueDate] = useState<string>(defaultDueDate.toISOString().split('T')[0]);

  const [isSubmitting, setIsSubmitting] = useState(false);
  const [errorMsg, setErrorMsg] = useState<string | null>(null);

  // Quick preset button click
  const handleQuickPreset = (amount: number) => {
    setTenderedCash(amount);
  };

  const financedRemaining = Math.max(0, grandTotal - downPayment);
  const monthlyInstallment = installmentCount > 0 ? (financedRemaining / installmentCount) : 0;

  // Change computation
  const totalPaid = (paymentType === 'CASH' ? tenderedCash : 0) +
                    (paymentType === 'CARD' ? grandTotal : (paymentType === 'SPLIT' ? tenderedCash + tenderedCard : 0)) +
                    (paymentType === 'ON_ACCOUNT' ? grandTotal : 0) +
                    (paymentType === 'INSTALLMENT' ? downPayment : 0);

  const changeDuePrimary = Math.max(0, (paymentType === 'CASH' ? tenderedCash - grandTotal : (paymentType === 'SPLIT' ? (tenderedCash + tenderedCard) - grandTotal : 0)));
  const changeDueSecondary = changeDuePrimary * secondaryRate;

  const handleFinish = async () => {
    setErrorMsg(null);

    // Validate on account
    if (paymentType === 'ON_ACCOUNT') {
      if (!customer || customer.id === 1) {
        setErrorMsg('Please select a registered customer with credit account for on-account charges.');
        return;
      }
      const availableCredit = customer.credit_limit - customer.current_balance;
      if (grandTotal > availableCredit) {
        setErrorMsg(`Customer credit limit exceeded! Available credit: $${availableCredit.toFixed(2)}.`);
        return;
      }
    }

    // Validate installment plan
    if (paymentType === 'INSTALLMENT') {
      if (!customer || customer.id === 1) {
        setErrorMsg('Please select a registered customer to create an installment plan (الأقساط).');
        return;
      }
      if (downPayment >= grandTotal) {
        setErrorMsg('Down payment equals or exceeds total amount. Please use standard Cash/Card payment.');
        return;
      }
      if (installmentCount < 1) {
        setErrorMsg('Number of installments must be at least 1.');
        return;
      }
    }

    if (paymentType === 'CASH' && tenderedCash < grandTotal) {
      setErrorMsg('Tendered cash is less than grand total.');
      return;
    }

    if (paymentType === 'SPLIT' && (tenderedCash + tenderedCard) < grandTotal) {
      setErrorMsg('Combined Cash + Card must equal or exceed total amount.');
      return;
    }

    setIsSubmitting(true);

    try {
      await onConfirmOrder({
        paidCash: paymentType === 'CASH' ? tenderedCash : (paymentType === 'SPLIT' ? tenderedCash : (paymentType === 'INSTALLMENT' ? downPayment : 0)),
        paidCard: paymentType === 'CARD' ? grandTotal : (paymentType === 'SPLIT' ? tenderedCard : 0),
        paidCredit: 0,
        paidOnAccount: paymentType === 'ON_ACCOUNT' ? grandTotal : (paymentType === 'INSTALLMENT' ? financedRemaining : 0),
        changeGiven: changeCurrency === 'PRIMARY' ? changeDuePrimary : changeDueSecondary,
        changeCurrency,
        exchangeRate: secondaryRate,
        installmentPlan: paymentType === 'INSTALLMENT' ? {
          total_amount: grandTotal,
          down_payment: downPayment,
          total_installments: installmentCount,
          frequency: 'MONTHLY',
          first_due_date: firstDueDate,
          notes: `Solar Showroom Installment Plan (${installmentCount} months @ $${monthlyInstallment.toFixed(2)}/mo)`,
        } : undefined,
      });
      setIsSubmitting(false);
      onClose();
    } catch (e: any) {
      setIsSubmitting(false);
      setErrorMsg(e.message || 'Payment processing failed.');
    }
  };

  return (
    <div className="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
      <div className="bg-slate-900 border border-slate-700 rounded-3xl w-full max-w-xl p-6 shadow-2xl relative select-none">
        <button
          onClick={onClose}
          className="absolute top-4 right-4 text-slate-400 hover:text-white p-1 rounded-full hover:bg-slate-800 transition"
        >
          <X className="w-5 h-5" />
        </button>

        {/* Header */}
        <div className="mb-6 flex items-center justify-between border-b border-slate-800 pb-4">
          <div>
            <h2 className="text-xl font-black text-white">{I18nService.t('pos.charge')}</h2>
            <p className="text-xs text-slate-400 mt-0.5">
              {table ? `Table: ${table.table_number}` : ''} {customer ? `• Customer: ${customer.name}` : ''}
            </p>
          </div>
          <div className="text-right">
            <div className="text-2xl font-black text-white">
              {primarySymbol}{grandTotal.toFixed(2)}
            </div>
            <div className="text-xs text-slate-400 font-semibold">
              {secondarySymbol}{grandTotalSecondary.toFixed(2)}
            </div>
          </div>
        </div>

        {errorMsg && (
          <div className="bg-red-500/10 border border-red-500/30 text-red-400 px-4 py-2.5 rounded-xl text-xs flex items-center gap-2 mb-4">
            <AlertCircle className="w-4 h-4 shrink-0" />
            <span>{errorMsg}</span>
          </div>
        )}

        {/* Tender Method Selector */}
        <div className="grid grid-cols-5 gap-2 mb-5">
          <button
            onClick={() => { setPaymentType('CASH'); setTenderedCash(grandTotal); }}
            className={`py-3 px-1.5 rounded-2xl border text-xs font-bold flex flex-col items-center gap-1.5 transition ${
              paymentType === 'CASH'
                ? 'bg-blue-600 text-white border-blue-500 shadow-lg shadow-blue-600/30'
                : 'bg-slate-800 text-slate-300 border-slate-700 hover:bg-slate-750'
            }`}
          >
            <Banknote className="w-5 h-5" />
            <span>{I18nService.t('pos.pay_cash')}</span>
          </button>

          <button
            onClick={() => { setPaymentType('CARD'); }}
            className={`py-3 px-1.5 rounded-2xl border text-xs font-bold flex flex-col items-center gap-1.5 transition ${
              paymentType === 'CARD'
                ? 'bg-blue-600 text-white border-blue-500 shadow-lg shadow-blue-600/30'
                : 'bg-slate-800 text-slate-300 border-slate-700 hover:bg-slate-750'
            }`}
          >
            <CreditCard className="w-5 h-5" />
            <span>{I18nService.t('pos.pay_card')}</span>
          </button>

          <button
            onClick={() => { setPaymentType('SPLIT'); setTenderedCash(Math.floor(grandTotal / 2)); setTenderedCard(grandTotal - Math.floor(grandTotal / 2)); }}
            className={`py-3 px-1.5 rounded-2xl border text-xs font-bold flex flex-col items-center gap-1.5 transition ${
              paymentType === 'SPLIT'
                ? 'bg-blue-600 text-white border-blue-500 shadow-lg shadow-blue-600/30'
                : 'bg-slate-800 text-slate-300 border-slate-700 hover:bg-slate-750'
            }`}
          >
            <Split className="w-5 h-5" />
            <span>{I18nService.t('pos.pay_split')}</span>
          </button>

          <button
            onClick={() => { setPaymentType('ON_ACCOUNT'); }}
            className={`py-3 px-1.5 rounded-2xl border text-xs font-bold flex flex-col items-center gap-1.5 transition ${
              paymentType === 'ON_ACCOUNT'
                ? 'bg-blue-600 text-white border-blue-500 shadow-lg shadow-blue-600/30'
                : 'bg-slate-800 text-slate-300 border-slate-700 hover:bg-slate-750'
            }`}
          >
            <FileText className="w-5 h-5" />
            <span>On Account</span>
          </button>

          <button
            onClick={() => { setPaymentType('INSTALLMENT'); }}
            className={`py-3 px-1.5 rounded-2xl border text-xs font-bold flex flex-col items-center gap-1.5 transition ${
              paymentType === 'INSTALLMENT'
                ? 'bg-amber-600 text-white border-amber-500 shadow-lg shadow-amber-600/30'
                : 'bg-slate-800 text-slate-300 border-slate-700 hover:bg-slate-750'
            }`}
          >
            <Calendar className="w-5 h-5 text-amber-300" />
            <span>Installments</span>
          </button>
        </div>

        {/* CASH / SPLIT Controls */}
        {(paymentType === 'CASH' || paymentType === 'SPLIT') && (
          <div className="space-y-4 mb-5">
            {/* Quick cash denomination chips */}
            {paymentType === 'CASH' && (
              <div className="grid grid-cols-5 gap-2">
                {[grandTotal, Math.ceil(grandTotal / 10) * 10, 20, 50, 100].map((val, idx) => (
                  <button
                    key={idx}
                    onClick={() => handleQuickPreset(val)}
                    className="bg-slate-800 hover:bg-slate-700 border border-slate-700 text-white font-bold py-2 rounded-xl text-xs transition"
                  >
                    {idx === 0 ? 'Exact' : `${primarySymbol}${val}`}
                  </button>
                ))}
              </div>
            )}

            {/* Input row */}
            <div className="grid grid-cols-2 gap-3">
              <div>
                <label className="block text-xs font-semibold text-slate-400 mb-1">
                  {I18nService.t('pos.amount_tendered')} ({primarySymbol})
                </label>
                <input
                  type="number"
                  step="0.01"
                  value={tenderedCash}
                  onChange={(e) => setTenderedCash(parseFloat(e.target.value) || 0)}
                  className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2.5 text-base font-bold text-white focus:outline-none focus:border-blue-500"
                />
              </div>

              {paymentType === 'SPLIT' && (
                <div>
                  <label className="block text-xs font-semibold text-slate-400 mb-1">
                    Card Amount ({primarySymbol})
                  </label>
                  <input
                    type="number"
                    step="0.01"
                    value={tenderedCard}
                    onChange={(e) => setTenderedCard(parseFloat(e.target.value) || 0)}
                    className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2.5 text-base font-bold text-white focus:outline-none focus:border-blue-500"
                  />
                </div>
              )}
            </div>

            {/* Change Due Box */}
            <div className="bg-slate-950 border border-slate-800 rounded-2xl p-4 flex items-center justify-between">
              <div>
                <span className="text-xs text-slate-400 font-semibold block">{I18nService.t('pos.change_due')}</span>
                <span className="text-2xl font-black text-emerald-400">
                  {changeCurrency === 'PRIMARY' ? `${primarySymbol}${changeDuePrimary.toFixed(2)}` : `${secondarySymbol}${changeDueSecondary.toFixed(2)}`}
                </span>
              </div>
              <div className="flex items-center gap-1 bg-slate-800 p-1 rounded-xl">
                <button
                  onClick={() => setChangeCurrency('PRIMARY')}
                  className={`px-2.5 py-1 rounded-lg text-xs font-bold transition ${
                    changeCurrency === 'PRIMARY' ? 'bg-blue-600 text-white' : 'text-slate-400 hover:text-white'
                  }`}
                >
                  {primarySymbol}
                </button>
                <button
                  onClick={() => setChangeCurrency('SECONDARY')}
                  className={`px-2.5 py-1 rounded-lg text-xs font-bold transition ${
                    changeCurrency === 'SECONDARY' ? 'bg-blue-600 text-white' : 'text-slate-400 hover:text-white'
                  }`}
                >
                  {secondarySymbol}
                </button>
              </div>
            </div>
          </div>
        )}

        {/* ON-ACCOUNT Notice */}
        {paymentType === 'ON_ACCOUNT' && (
          <div className="bg-slate-950 border border-slate-800 rounded-2xl p-4 mb-5 text-xs text-slate-300 space-y-2">
            <div className="flex justify-between">
              <span className="text-slate-400">Customer:</span>
              <span className="font-bold text-white">{customer?.name || 'No Customer Assigned'}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-slate-400">Current Balance (Owed):</span>
              <span className="font-bold text-amber-400">${Number(customer?.current_balance || 0).toFixed(2)}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-slate-400">Credit Limit:</span>
              <span className="font-bold text-emerald-400">${Number(customer?.credit_limit || 0).toFixed(2)}</span>
            </div>
          </div>
        )}

        {/* INSTALLMENT Configuration Box */}
        {paymentType === 'INSTALLMENT' && (
          <div className="bg-slate-950 border border-amber-500/30 rounded-2xl p-4 mb-5 space-y-3.5">
            <div className="flex items-center justify-between border-b border-slate-800 pb-2">
              <span className="text-xs font-bold text-amber-400 flex items-center gap-1.5">
                <Calendar className="w-4 h-4" />
                <span>Installment Financing Plan (الأقساط)</span>
              </span>
              <span className="text-[11px] font-semibold text-slate-400">
                Customer: <span className="text-white font-bold">{customer?.name || 'Please select customer'}</span>
              </span>
            </div>

            <div className="grid grid-cols-2 gap-3">
              {/* Down payment */}
              <div>
                <label className="block text-xs font-semibold text-slate-400 mb-1">
                  Down Payment (الدفعة الأولى) ({primarySymbol})
                </label>
                <input
                  type="number"
                  step="0.01"
                  value={downPayment}
                  onChange={(e) => setDownPayment(parseFloat(e.target.value) || 0)}
                  className="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-sm font-bold text-white focus:outline-none focus:border-amber-500"
                />
              </div>

              {/* Number of installments */}
              <div>
                <label className="block text-xs font-semibold text-slate-400 mb-1">
                  Installments (عدد الدفعات)
                </label>
                <select
                  value={installmentCount}
                  onChange={(e) => setInstallmentCount(parseInt(e.target.value) || 1)}
                  className="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-xs font-bold text-white focus:outline-none focus:border-amber-500"
                >
                  <option value={2}>2 Months (شهران)</option>
                  <option value={3}>3 Months (3 أشهر)</option>
                  <option value={4}>4 Months (4 أشهر)</option>
                  <option value={6}>6 Months (6 أشهر)</option>
                  <option value={10}>10 Months (10 أشهر)</option>
                  <option value={12}>12 Months (سنة كاملة)</option>
                  <option value={24}>24 Months (سنتان)</option>
                </select>
              </div>
            </div>

            {/* First due date */}
            <div>
              <label className="block text-xs font-semibold text-slate-400 mb-1">
                First Due Date (تاريخ أول قسط)
              </label>
              <input
                type="date"
                value={firstDueDate}
                onChange={(e) => setFirstDueDate(e.target.value)}
                className="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-xs font-bold text-white focus:outline-none focus:border-amber-500"
              />
            </div>

            {/* Installment Summary */}
            <div className="bg-slate-900/80 border border-slate-800 rounded-xl p-3 grid grid-cols-2 gap-3 text-xs">
              <div>
                <span className="text-slate-400 block text-[11px]">Financed Balance</span>
                <span className="font-black text-amber-400 text-sm">{primarySymbol}{financedRemaining.toFixed(2)}</span>
              </div>
              <div className="text-right">
                <span className="text-slate-400 block text-[11px]">Monthly Payment</span>
                <span className="font-black text-emerald-400 text-sm">
                  {primarySymbol}{monthlyInstallment.toFixed(2)} / mo
                </span>
              </div>
            </div>
          </div>
        )}

        {/* Confirm Button */}
        <button
          onClick={handleFinish}
          disabled={isSubmitting}
          className="w-full bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white font-black py-4 rounded-2xl shadow-xl shadow-emerald-600/30 transition text-sm flex items-center justify-center gap-2"
        >
          {isSubmitting ? (
            <span>Processing Order...</span>
          ) : (
            <>
              <CheckCircle2 className="w-5 h-5" />
              <span>{I18nService.t('pos.finish_sale')}</span>
            </>
          )}
        </button>
      </div>
    </div>
  );
};
