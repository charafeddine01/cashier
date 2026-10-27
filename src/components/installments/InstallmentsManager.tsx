import React, { useState, useEffect } from 'react';
import { CompanySettings, InstallmentPlan, InstallmentScheduleItem, InstallmentPaymentRecord } from '../../types';
import { ApiService } from '../../services/api';
import { I18nService } from '../../services/i18nService';
import {
  Calendar,
  CheckCircle2,
  Clock,
  AlertTriangle,
  Search,
  DollarSign,
  User,
  Phone,
  FileText,
  CreditCard,
  Banknote,
  Receipt,
  ChevronRight,
  Printer,
  RefreshCw,
  X
} from 'lucide-react';

interface Props {
  settings: CompanySettings | null;
}

export const InstallmentsManager: React.FC<Props> = ({ settings }) => {
  const primarySymbol = settings?.primary_currency_symbol || '$';
  const secondarySymbol = settings?.secondary_currency_symbol || 'L.L.';
  const secondaryRate = settings?.secondary_currency_rate || 89500;

  const [plans, setPlans] = useState<InstallmentPlan[]>([]);
  const [summary, setSummary] = useState<any>(null);
  const [loading, setLoading] = useState(false);
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState<'ALL' | 'ACTIVE' | 'COMPLETED'>('ACTIVE');

  // Selected plan for modal details
  const [selectedPlan, setSelectedPlan] = useState<InstallmentPlan | null>(null);
  const [schedule, setSchedule] = useState<InstallmentScheduleItem[]>([]);
  const [paymentHistory, setPaymentHistory] = useState<InstallmentPaymentRecord[]>([]);
  const [isModalOpen, setIsModalOpen] = useState(false);

  // Payment form state
  const [payAmount, setPayAmount] = useState<number>(0);
  const [selectedScheduleId, setSelectedScheduleId] = useState<number | null>(null);
  const [payMethod, setPayMethod] = useState<'CASH' | 'CARD' | 'BANK'>('CASH');
  const [payCurrency, setPayCurrency] = useState<'USD' | 'LBP'>('USD');
  const [payNotes, setPayNotes] = useState('');
  const [isSubmittingPay, setIsSubmittingPay] = useState(false);
  const [lastReceipt, setLastReceipt] = useState<any>(null);

  useEffect(() => {
    loadData();
  }, [statusFilter]);

  const loadData = async () => {
    setLoading(true);
    try {
      const [resPlans, resSum] = await Promise.all([
        ApiService.request('installments.list', { status: statusFilter, search }),
        ApiService.request('installments.overdueSummary'),
      ]);

      if (resPlans.success && resPlans.data) {
        setPlans(resPlans.data.plans || []);
      }
      if (resSum.success && resSum.data) {
        setSummary(resSum.data);
      }
    } catch (e) {
      console.error('Failed to load installments:', e);
    } finally {
      setLoading(false);
    }
  };

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    loadData();
  };

  const handleOpenPlan = async (plan: InstallmentPlan) => {
    setSelectedPlan(plan);
    setIsModalOpen(true);
    setLastReceipt(null);
    try {
      const res = await ApiService.request('installments.getSchedule', { plan_id: plan.id });
      if (res.success && res.data) {
        setSchedule(res.data.schedule || []);
        setPaymentHistory(res.data.payments || []);

        // Pre-fill next due installment amount
        const nextPending = res.data.schedule.find((s: InstallmentScheduleItem) => s.status !== 'PAID');
        if (nextPending) {
          setSelectedScheduleId(nextPending.id);
          setPayAmount(Number(nextPending.remaining_amount));
        } else {
          setPayAmount(0);
          setSelectedScheduleId(null);
        }
      }
    } catch (e) {
      console.error('Failed to get plan schedule:', e);
    }
  };

  const handleRecordPayment = async () => {
    if (!selectedPlan || payAmount <= 0) return;
    setIsSubmittingPay(true);
    try {
      const res = await ApiService.request('installments.recordPayment', {
        plan_id: selectedPlan.id,
        schedule_id: selectedScheduleId,
        amount_paid: payAmount,
        payment_method: payMethod,
        currency: payCurrency,
        exchange_rate: secondaryRate,
        notes: payNotes,
      });

      if (res.success && res.data) {
        setLastReceipt(res.data);
        // Refresh plan schedule and main list
        await handleOpenPlan(selectedPlan);
        loadData();
      } else {
        alert(res.message || 'Payment failed.');
      }
    } catch (e: any) {
      alert(e.message || 'Payment processing error.');
    } finally {
      setIsSubmittingPay(false);
    }
  };

  return (
    <div className="flex-1 flex flex-col bg-slate-950 overflow-hidden p-6 select-none">
      {/* Top Header & Metrics */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
          <h1 className="text-2xl font-black text-white flex items-center gap-2.5">
            <Calendar className="w-7 h-7 text-amber-400" />
            <span>Installment Management (الأقساط)</span>
          </h1>
          <p className="text-xs text-slate-400 mt-1">
            Track solar system installment plans, overdue alerts, and record customer installment collections.
          </p>
        </div>

        <button
          onClick={loadData}
          disabled={loading}
          className="flex items-center gap-2 bg-slate-800 hover:bg-slate-700 border border-slate-700 px-4 py-2.5 rounded-2xl text-xs font-bold text-slate-200 transition"
        >
          <RefreshCw className={`w-4 h-4 text-blue-400 ${loading ? 'animate-spin' : ''}`} />
          <span>Refresh</span>
        </button>
      </div>

      {/* KPI Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div className="bg-slate-900 border border-slate-800 rounded-3xl p-5 shadow-lg relative overflow-hidden">
          <div className="flex items-center justify-between mb-2">
            <span className="text-xs font-semibold text-slate-400 uppercase tracking-wider">Active Portfolio</span>
            <DollarSign className="w-5 h-5 text-emerald-400" />
          </div>
          <div className="text-2xl font-black text-white">
            {primarySymbol}{Number(summary?.portfolio?.total_portfolio || 0).toLocaleString()}
          </div>
          <span className="text-[11px] text-slate-400 mt-1 block">
            {summary?.portfolio?.active_plans || 0} active installment plans
          </span>
        </div>

        <div className="bg-slate-900 border border-slate-800 rounded-3xl p-5 shadow-lg relative overflow-hidden">
          <div className="flex items-center justify-between mb-2">
            <span className="text-xs font-semibold text-slate-400 uppercase tracking-wider">Due This Week</span>
            <Clock className="w-5 h-5 text-blue-400" />
          </div>
          <div className="text-2xl font-black text-blue-300">
            {primarySymbol}{Number(summary?.due_this_week?.total_amount || 0).toLocaleString()}
          </div>
          <span className="text-[11px] text-slate-400 mt-1 block">
            {summary?.due_this_week?.count || 0} upcoming installment(s)
          </span>
        </div>

        <div className="bg-slate-900 border border-rose-500/30 rounded-3xl p-5 shadow-lg relative overflow-hidden bg-gradient-to-br from-rose-950/20 to-slate-900">
          <div className="flex items-center justify-between mb-2">
            <span className="text-xs font-semibold text-rose-400 uppercase tracking-wider">Overdue Payments</span>
            <AlertTriangle className="w-5 h-5 text-rose-400 animate-pulse" />
          </div>
          <div className="text-2xl font-black text-rose-400">
            {primarySymbol}{Number(summary?.overdue?.total_amount || 0).toLocaleString()}
          </div>
          <span className="text-[11px] text-rose-300 mt-1 block font-medium">
            {summary?.overdue?.count || 0} customer(s) late on installments
          </span>
        </div>
      </div>

      {/* Controls & Search */}
      <div className="flex flex-col sm:flex-row items-center justify-between gap-3 mb-5">
        <form onSubmit={handleSearch} className="flex-1 w-full flex items-center gap-2">
          <div className="relative flex-1">
            <Search className="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
            <input
              type="text"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Search by customer name, phone, or plan number..."
              className="w-full bg-slate-900 border border-slate-800 rounded-2xl pl-10 pr-4 py-2.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-blue-500"
            />
          </div>
          <button
            type="submit"
            className="bg-blue-600 hover:bg-blue-500 text-white font-bold px-4 py-2.5 rounded-2xl text-xs transition"
          >
            Search
          </button>
        </form>

        <div className="flex items-center gap-1.5 bg-slate-900 border border-slate-800 p-1 rounded-2xl shrink-0">
          {(['ALL', 'ACTIVE', 'COMPLETED'] as const).map((st) => (
            <button
              key={st}
              onClick={() => setStatusFilter(st)}
              className={`px-3 py-1.5 rounded-xl text-xs font-bold transition ${
                statusFilter === st ? 'bg-blue-600 text-white shadow' : 'text-slate-400 hover:text-white'
              }`}
            >
              {st === 'ALL' ? 'All Plans' : st === 'ACTIVE' ? 'Active' : 'Completed'}
            </button>
          ))}
        </div>
      </div>

      {/* Plans List Table */}
      <div className="flex-1 bg-slate-900 border border-slate-800 rounded-3xl overflow-hidden flex flex-col shadow-xl">
        <div className="overflow-x-auto flex-1">
          <table className="w-full text-left text-xs text-slate-300">
            <thead className="bg-slate-950/70 border-b border-slate-800 text-[11px] uppercase tracking-wider text-slate-400">
              <tr>
                <th className="py-3.5 px-4 font-bold">Plan / Invoice</th>
                <th className="py-3.5 px-4 font-bold">Customer</th>
                <th className="py-3.5 px-4 font-bold">Total Financed</th>
                <th className="py-3.5 px-4 font-bold">Down Payment</th>
                <th className="py-3.5 px-4 font-bold">Remaining Balance</th>
                <th className="py-3.5 px-4 font-bold">Installments</th>
                <th className="py-3.5 px-4 font-bold">Next Due Date</th>
                <th className="py-3.5 px-4 font-bold">Status</th>
                <th className="py-3.5 px-4 font-bold text-right">Action</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-800/60">
              {plans.length === 0 ? (
                <tr>
                  <td colSpan={9} className="text-center py-12 text-slate-500 font-medium">
                    No installment plans found. Orders with installment payments will appear here.
                  </td>
                </tr>
              ) : (
                plans.map((p) => {
                  const isCompleted = p.status === 'COMPLETED';
                  const progressPct = Math.round(((Number(p.paid_installments_count) || 0) / p.total_installments) * 100);

                  return (
                    <tr key={p.id} className="hover:bg-slate-800/40 transition">
                      <td className="py-3.5 px-4 font-mono">
                        <div className="font-bold text-white">{p.plan_number}</div>
                        <div className="text-[10px] text-slate-400">{p.invoice_number}</div>
                      </td>
                      <td className="py-3.5 px-4">
                        <div className="font-bold text-white flex items-center gap-1.5">
                          <User className="w-3.5 h-3.5 text-blue-400 shrink-0" />
                          <span>{p.customer_name}</span>
                        </div>
                        <div className="text-[11px] text-slate-400 font-mono mt-0.5">{p.customer_phone}</div>
                      </td>
                      <td className="py-3.5 px-4 font-bold text-white">
                        {primarySymbol}{Number(p.total_amount).toFixed(2)}
                      </td>
                      <td className="py-3.5 px-4 text-emerald-400 font-medium">
                        {primarySymbol}{Number(p.down_payment).toFixed(2)}
                      </td>
                      <td className="py-3.5 px-4 font-black text-amber-400">
                        {primarySymbol}{Number(p.remaining_balance).toFixed(2)}
                      </td>
                      <td className="py-3.5 px-4">
                        <div className="flex items-center gap-2">
                          <div className="w-20 bg-slate-800 h-2 rounded-full overflow-hidden">
                            <div
                              className="bg-emerald-500 h-full transition-all duration-300"
                              style={{ width: `${progressPct}%` }}
                            />
                          </div>
                          <span className="text-[11px] font-mono text-slate-300">
                            {p.paid_installments_count || 0}/{p.total_installments}
                          </span>
                        </div>
                      </td>
                      <td className="py-3.5 px-4">
                        {p.next_due_date ? (
                          <div>
                            <div className="font-semibold text-slate-200">{p.next_due_date}</div>
                            <div className="text-[10px] text-amber-400 font-mono">
                              Due: {primarySymbol}{Number(p.next_due_amount).toFixed(2)}
                            </div>
                          </div>
                        ) : (
                          <span className="text-slate-500">—</span>
                        )}
                      </td>
                      <td className="py-3.5 px-4">
                        <span
                          className={`px-2.5 py-1 rounded-full text-[10px] font-bold tracking-wide uppercase ${
                            isCompleted
                              ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/30'
                              : 'bg-amber-500/10 text-amber-400 border border-amber-500/30'
                          }`}
                        >
                          {p.status}
                        </span>
                      </td>
                      <td className="py-3.5 px-4 text-right">
                        <button
                          onClick={() => handleOpenPlan(p)}
                          className="bg-blue-600/20 hover:bg-blue-600/40 text-blue-300 border border-blue-500/30 font-bold px-3 py-1.5 rounded-xl transition text-xs inline-flex items-center gap-1"
                        >
                          <span>Manage</span>
                          <ChevronRight className="w-3.5 h-3.5" />
                        </button>
                      </td>
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Plan Details & Record Payment Modal */}
      {isModalOpen && selectedPlan && (
        <div className="fixed inset-0 z-50 bg-slate-950/85 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-slate-900 border border-slate-700 rounded-3xl w-full max-w-4xl p-6 shadow-2xl relative max-h-[92vh] flex flex-col">
            <button
              onClick={() => setIsModalOpen(false)}
              className="absolute top-4 right-4 text-slate-400 hover:text-white p-1 rounded-full hover:bg-slate-800 transition"
            >
              <X className="w-5 h-5" />
            </button>

            {/* Modal Header */}
            <div className="border-b border-slate-800 pb-4 mb-4 flex items-center justify-between pr-8">
              <div>
                <div className="flex items-center gap-2">
                  <h2 className="text-lg font-black text-white">{selectedPlan.plan_number}</h2>
                  <span
                    className={`px-2.5 py-0.5 rounded-full text-[10px] font-bold ${
                      selectedPlan.status === 'COMPLETED'
                        ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/30'
                        : 'bg-amber-500/10 text-amber-400 border border-amber-500/30'
                    }`}
                  >
                    {selectedPlan.status}
                  </span>
                </div>
                <p className="text-xs text-slate-400 mt-0.5">
                  Customer: <span className="font-bold text-white">{selectedPlan.customer_name}</span> ({selectedPlan.customer_phone}) • Invoice: {selectedPlan.invoice_number}
                </p>
              </div>

              <div className="text-right">
                <span className="text-[10px] text-slate-400 uppercase font-semibold block">Remaining Balance</span>
                <span className="text-xl font-black text-amber-400">
                  {primarySymbol}{Number(selectedPlan.remaining_balance).toFixed(2)}
                </span>
              </div>
            </div>

            {/* Modal Body: Left Schedule, Right Record Payment Form */}
            <div className="grid grid-cols-1 md:grid-cols-12 gap-6 flex-1 overflow-y-auto pr-1">
              {/* Left Column: Installment Schedule (7 cols) */}
              <div className="md:col-span-7 space-y-4">
                <h3 className="text-xs font-bold text-slate-300 uppercase tracking-wider flex items-center gap-1.5">
                  <Calendar className="w-4 h-4 text-blue-400" />
                  <span>Payment Schedule & Status</span>
                </h3>

                <div className="space-y-2 max-h-80 overflow-y-auto pr-1">
                  {schedule.map((item) => {
                    const isPaid = item.status === 'PAID';
                    const isSelected = selectedScheduleId === item.id;

                    return (
                      <div
                        key={item.id}
                        onClick={() => {
                          if (!isPaid) {
                            setSelectedScheduleId(item.id);
                            setPayAmount(Number(item.remaining_amount));
                          }
                        }}
                        className={`p-3.5 rounded-2xl border transition cursor-pointer flex items-center justify-between ${
                          isPaid
                            ? 'bg-emerald-500/5 border-emerald-500/20 text-slate-300 opacity-80'
                            : isSelected
                            ? 'bg-blue-600/15 border-blue-500 text-white shadow-md'
                            : 'bg-slate-950 border-slate-800 hover:border-slate-700 text-slate-300'
                        }`}
                      >
                        <div className="flex items-center gap-3">
                          <div
                            className={`w-7 h-7 rounded-full flex items-center justify-center font-bold text-xs ${
                              isPaid ? 'bg-emerald-500/20 text-emerald-400' : 'bg-slate-800 text-slate-300'
                            }`}
                          >
                            #{item.installment_number}
                          </div>
                          <div>
                            <div className="font-semibold text-xs text-white">
                              Due: {item.due_date}
                            </div>
                            <div className="text-[11px] text-slate-400 font-mono">
                              Due: {primarySymbol}{Number(item.amount_due).toFixed(2)}
                              {Number(item.amount_paid) > 0 && ` • Paid: ${primarySymbol}${Number(item.amount_paid).toFixed(2)}`}
                            </div>
                          </div>
                        </div>

                        <div className="text-right">
                          <span
                            className={`px-2 py-0.5 rounded-md text-[10px] font-bold block mb-1 ${
                              isPaid
                                ? 'bg-emerald-500/20 text-emerald-300'
                                : 'bg-amber-500/20 text-amber-300'
                            }`}
                          >
                            {item.status}
                          </span>
                          {!isPaid && (
                            <span className="text-xs font-bold text-white font-mono">
                              {primarySymbol}{Number(item.remaining_amount).toFixed(2)}
                            </span>
                          )}
                        </div>
                      </div>
                    );
                  })}
                </div>

                {/* Payment Receipts History */}
                {paymentHistory.length > 0 && (
                  <div className="pt-2">
                    <h4 className="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                      <Receipt className="w-3.5 h-3.5 text-slate-400" />
                      <span>Payment History & Receipts</span>
                    </h4>
                    <div className="space-y-1.5 max-h-40 overflow-y-auto pr-1">
                      {paymentHistory.map((h) => (
                        <div
                          key={h.id}
                          className="bg-slate-950 border border-slate-800/80 rounded-xl p-2.5 flex items-center justify-between text-xs"
                        >
                          <div>
                            <div className="font-mono font-bold text-slate-200">{h.receipt_number}</div>
                            <div className="text-[10px] text-slate-400">{h.payment_date} • Cashier: {h.cashier_name}</div>
                          </div>
                          <div className="text-right">
                            <span className="font-bold text-emerald-400 font-mono">
                              +{primarySymbol}{Number(h.amount_paid).toFixed(2)}
                            </span>
                            <span className="text-[10px] text-slate-400 block">{h.payment_method}</span>
                          </div>
                        </div>
                      ))}
                    </div>
                  </div>
                )}
              </div>

              {/* Right Column: Collect Payment Form (5 cols) */}
              <div className="md:col-span-5 bg-slate-950 border border-slate-800 rounded-2xl p-5 flex flex-col justify-between">
                <div>
                  <h3 className="text-xs font-bold text-white uppercase tracking-wider mb-4 flex items-center gap-1.5">
                    <CreditCard className="w-4 h-4 text-emerald-400" />
                    <span>Record Installment Payment</span>
                  </h3>

                  {lastReceipt && (
                    <div className="bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 rounded-xl p-3 mb-4 text-xs flex items-center gap-2">
                      <CheckCircle2 className="w-5 h-5 text-emerald-400 shrink-0" />
                      <div>
                        <div className="font-bold">Payment Recorded!</div>
                        <div className="text-[11px] font-mono">Receipt: {lastReceipt.receipt_number}</div>
                      </div>
                    </div>
                  )}

                  {/* Payment Amount */}
                  <div className="mb-3.5">
                    <label className="block text-xs font-semibold text-slate-400 mb-1">
                      Payment Amount ({payCurrency === 'USD' ? primarySymbol : secondarySymbol})
                    </label>
                    <input
                      type="number"
                      step="0.01"
                      value={payAmount}
                      onChange={(e) => setPayAmount(parseFloat(e.target.value) || 0)}
                      className="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2.5 text-lg font-black text-white focus:outline-none focus:border-blue-500 font-mono"
                    />
                    {payCurrency === 'USD' && (
                      <span className="text-[11px] text-slate-400 mt-1 block">
                        Equivalent: {(payAmount * secondaryRate).toLocaleString()} L.L. (@ 89,500)
                      </span>
                    )}
                  </div>

                  {/* Currency Selector */}
                  <div className="mb-3.5">
                    <label className="block text-xs font-semibold text-slate-400 mb-1">Currency</label>
                    <div className="grid grid-cols-2 gap-2">
                      <button
                        type="button"
                        onClick={() => setPayCurrency('USD')}
                        className={`py-2 rounded-xl text-xs font-bold border transition ${
                          payCurrency === 'USD'
                            ? 'bg-blue-600 text-white border-blue-500'
                            : 'bg-slate-900 text-slate-400 border-slate-800'
                        }`}
                      >
                        USD ($)
                      </button>
                      <button
                        type="button"
                        onClick={() => setPayCurrency('LBP')}
                        className={`py-2 rounded-xl text-xs font-bold border transition ${
                          payCurrency === 'LBP'
                            ? 'bg-blue-600 text-white border-blue-500'
                            : 'bg-slate-900 text-slate-400 border-slate-800'
                        }`}
                      >
                        LBP (L.L.)
                      </button>
                    </div>
                  </div>

                  {/* Payment Method */}
                  <div className="mb-3.5">
                    <label className="block text-xs font-semibold text-slate-400 mb-1">Method</label>
                    <div className="grid grid-cols-3 gap-2">
                      <button
                        type="button"
                        onClick={() => setPayMethod('CASH')}
                        className={`py-2 rounded-xl text-xs font-bold border transition ${
                          payMethod === 'CASH'
                            ? 'bg-emerald-600 text-white border-emerald-500'
                            : 'bg-slate-900 text-slate-400 border-slate-800'
                        }`}
                      >
                        Cash
                      </button>
                      <button
                        type="button"
                        onClick={() => setPayMethod('CARD')}
                        className={`py-2 rounded-xl text-xs font-bold border transition ${
                          payMethod === 'CARD'
                            ? 'bg-emerald-600 text-white border-emerald-500'
                            : 'bg-slate-900 text-slate-400 border-slate-800'
                        }`}
                      >
                        Card
                      </button>
                      <button
                        type="button"
                        onClick={() => setPayMethod('BANK')}
                        className={`py-2 rounded-xl text-xs font-bold border transition ${
                          payMethod === 'BANK'
                            ? 'bg-emerald-600 text-white border-emerald-500'
                            : 'bg-slate-900 text-slate-400 border-slate-800'
                        }`}
                      >
                        Transfer
                      </button>
                    </div>
                  </div>

                  {/* Notes */}
                  <div className="mb-4">
                    <label className="block text-xs font-semibold text-slate-400 mb-1">Notes / Description</label>
                    <input
                      type="text"
                      value={payNotes}
                      onChange={(e) => setPayNotes(e.target.value)}
                      placeholder="Optional payment notes..."
                      className="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-blue-500"
                    />
                  </div>
                </div>

                {/* Submit Payment Button */}
                <button
                  type="button"
                  onClick={handleRecordPayment}
                  disabled={isSubmittingPay || payAmount <= 0 || selectedPlan.status === 'COMPLETED'}
                  className="w-full bg-emerald-600 hover:bg-emerald-500 disabled:opacity-40 text-white font-black py-3 rounded-xl shadow-lg shadow-emerald-600/30 transition text-xs flex items-center justify-center gap-2"
                >
                  <CheckCircle2 className="w-4 h-4" />
                  <span>
                    {isSubmittingPay ? 'Recording Payment...' : `Record Payment (${primarySymbol}${payAmount.toFixed(2)})`}
                  </span>
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
