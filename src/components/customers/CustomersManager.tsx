import React, { useState, useEffect } from 'react';
import { Customer, CustomerLedgerEntry } from '../../types';
import { ApiService } from '../../services/api';
import { I18nService } from '../../services/i18nService';
import {
  Users,
  Search,
  Plus,
  FileText,
  DollarSign,
  Award,
  CreditCard,
  X,
  History
} from 'lucide-react';

export const CustomersManager: React.FC = () => {
  const [customers, setCustomers] = useState<Customer[]>([]);
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(false);

  // Ledger History Modal
  const [selectedCustomer, setSelectedCustomer] = useState<Customer | null>(null);
  const [ledgerEntries, setLedgerEntries] = useState<CustomerLedgerEntry[]>([]);
  const [isLedgerOpen, setIsLedgerOpen] = useState(false);

  // Pay Debt Modal
  const [isPayDebtOpen, setIsPayDebtOpen] = useState(false);
  const [payAmount, setPayAmount] = useState<number>(0);
  const [payMethod, setPayMethod] = useState<'CASH' | 'CARD' | 'BANK'>('CASH');

  const fetchCustomers = async () => {
    setLoading(true);
    const res = await ApiService.request('customers.list', { search });
    setLoading(false);
    if (res.success && res.data) {
      setCustomers(res.data.customers || []);
    }
  };

  useEffect(() => {
    fetchCustomers();
  }, [search]);

  const handleOpenLedger = async (cust: Customer) => {
    setSelectedCustomer(cust);
    setIsLedgerOpen(true);
    const res = await ApiService.request('customers.ledger', { customer_id: cust.id });
    if (res.success && res.data) {
      setLedgerEntries(res.data.ledger || []);
    }
  };

  const handlePayDebtSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedCustomer || payAmount <= 0) return;

    const res = await ApiService.request('customers.payDebt', {
      customer_id: selectedCustomer.id,
      amount: payAmount,
      payment_method: payMethod,
    });

    if (res.success) {
      setIsPayDebtOpen(false);
      setPayAmount(0);
      fetchCustomers();
      if (isLedgerOpen && selectedCustomer) {
        handleOpenLedger(selectedCustomer);
      }
    } else {
      alert(res.error?.message || 'Payment failed');
    }
  };

  return (
    <div className="flex flex-col h-full bg-slate-950 select-none overflow-hidden font-sans">
      {/* Top Controls */}
      <div className="p-4 bg-slate-900 border-b border-slate-800 flex items-center justify-between shrink-0">
        <div className="flex items-center gap-2">
          <Users className="w-5 h-5 text-blue-400" />
          <h2 className="text-sm font-bold text-white">{I18nService.t('nav.customers')} ({customers.length})</h2>
        </div>

        <div className="relative w-72">
          <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
          <input
            type="text"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Search customers by name or phone..."
            className="w-full bg-slate-950 border border-slate-700 rounded-xl pl-9 pr-3 py-1.5 text-xs text-white focus:outline-none focus:border-blue-500"
          />
        </div>
      </div>

      {/* Table Content */}
      <div className="flex-1 overflow-y-auto p-4">
        <div className="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
          <table className="w-full text-left text-xs text-slate-300">
            <thead className="bg-slate-950 text-slate-400 uppercase text-[10px] font-bold border-b border-slate-800">
              <tr>
                <th className="px-4 py-3">Customer Name</th>
                <th className="px-4 py-3">Phone</th>
                <th className="px-4 py-3">Credit Limit</th>
                <th className="px-4 py-3">Current Balance (Owed)</th>
                <th className="px-4 py-3">Loyalty Points</th>
                <th className="px-4 py-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-800">
              {customers.map((c) => {
                const hasDebt = Number(c.current_balance) > 0;

                return (
                  <tr key={c.id} className="hover:bg-slate-850/50 transition">
                    <td className="px-4 py-3">
                      <span className="font-bold text-white block">{c.name}</span>
                      {c.email && <span className="text-[10px] text-slate-400">{c.email}</span>}
                    </td>
                    <td className="px-4 py-3 font-mono text-[11px] text-slate-300">{c.phone || '—'}</td>
                    <td className="px-4 py-3 font-mono text-slate-400">${Number(c.credit_limit).toFixed(2)}</td>
                    <td className="px-4 py-3 font-mono font-bold">
                      <span className={hasDebt ? 'text-amber-400' : 'text-slate-400'}>
                        ${Number(c.current_balance).toFixed(2)}
                      </span>
                    </td>
                    <td className="px-4 py-3">
                      <span className="bg-purple-500/20 text-purple-300 border border-purple-500/30 text-[10px] font-bold px-2 py-0.5 rounded-full flex items-center gap-1 w-fit">
                        <Award className="w-3 h-3 text-purple-400" />
                        <span>{Number(c.loyalty_points).toFixed(0)} pts</span>
                      </span>
                    </td>
                    <td className="px-4 py-3 text-right">
                      <div className="flex items-center justify-end gap-2">
                        {hasDebt && (
                          <button
                            onClick={() => { setSelectedCustomer(c); setPayAmount(Number(c.current_balance)); setIsPayDebtOpen(true); }}
                            className="bg-emerald-600 hover:bg-emerald-500 text-white font-bold px-2.5 py-1 rounded-lg text-[10px] transition"
                          >
                            Pay Debt
                          </button>
                        )}
                        <button
                          onClick={() => handleOpenLedger(c)}
                          className="p-1.5 hover:bg-slate-800 text-slate-400 hover:text-white rounded-lg transition"
                          title="View Ledger Statement"
                        >
                          <History className="w-4 h-4 text-blue-400" />
                        </button>
                      </div>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </div>

      {/* Ledger Modal */}
      {isLedgerOpen && selectedCustomer && (
        <div className="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-slate-900 border border-slate-700 rounded-3xl w-full max-w-2xl p-6 shadow-2xl relative flex flex-col max-h-[85vh]">
            <div className="flex items-center justify-between pb-3 border-b border-slate-800 shrink-0">
              <div>
                <h3 className="font-bold text-white text-base">Customer Ledger: {selectedCustomer.name}</h3>
                <span className="text-xs text-amber-400 font-bold">Current Debt: ${Number(selectedCustomer.current_balance).toFixed(2)}</span>
              </div>
              <button onClick={() => setIsLedgerOpen(false)} className="p-1 text-slate-400 hover:text-white">
                <X className="w-5 h-5" />
              </button>
            </div>

            <div className="flex-1 overflow-y-auto py-3">
              <table className="w-full text-left text-xs text-slate-300">
                <thead className="bg-slate-950 text-slate-400 uppercase text-[10px] font-bold border-b border-slate-800">
                  <tr>
                    <th className="px-3 py-2">Date</th>
                    <th className="px-3 py-2">Type</th>
                    <th className="px-3 py-2">Amount</th>
                    <th className="px-3 py-2">Balance After</th>
                    <th className="px-3 py-2">Notes</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-800">
                  {ledgerEntries.map((le) => (
                    <tr key={le.id}>
                      <td className="px-3 py-2 font-mono text-[10px] text-slate-400">{le.created_at}</td>
                      <td className="px-3 py-2">
                        <span className={`text-[10px] font-bold px-1.5 py-0.5 rounded ${
                          le.transaction_type === 'INVOICE' ? 'bg-amber-500/20 text-amber-300' :
                          le.transaction_type === 'PAYMENT' ? 'bg-emerald-500/20 text-emerald-300' :
                          'bg-blue-500/20 text-blue-300'
                        }`}>
                          {le.transaction_type}
                        </span>
                      </td>
                      <td className="px-3 py-2 font-mono font-bold">
                        ${Number(le.amount).toFixed(2)}
                      </td>
                      <td className="px-3 py-2 font-mono text-slate-300">
                        ${Number(le.balance_after).toFixed(2)}
                      </td>
                      <td className="px-3 py-2 text-slate-400 text-[11px]">{le.notes}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* Pay Debt Modal */}
      {isPayDebtOpen && selectedCustomer && (
        <div className="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-slate-900 border border-slate-700 rounded-3xl w-full max-w-sm p-6 shadow-2xl relative">
            <button onClick={() => setIsPayDebtOpen(false)} className="absolute top-4 right-4 text-slate-400 hover:text-white">
              <X className="w-5 h-5" />
            </button>
            <h3 className="font-bold text-white text-sm mb-1">Repay Customer Debt</h3>
            <p className="text-xs text-slate-400 mb-4">{selectedCustomer.name}</p>

            <form onSubmit={handlePayDebtSubmit} className="space-y-3">
              <div>
                <label className="block text-xs font-semibold text-slate-400 mb-1">Amount ($)</label>
                <input
                  type="number"
                  step="0.01"
                  value={payAmount}
                  onChange={(e) => setPayAmount(parseFloat(e.target.value) || 0)}
                  className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-sm font-bold text-white focus:outline-none"
                />
              </div>
              <div>
                <label className="block text-xs font-semibold text-slate-400 mb-1">Payment Method</label>
                <select
                  value={payMethod}
                  onChange={(e) => setPayMethod(e.target.value as any)}
                  className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs font-bold text-white focus:outline-none"
                >
                  <option value="CASH">Cash (Registers into Drawer)</option>
                  <option value="CARD">Card</option>
                  <option value="BANK">Bank Transfer</option>
                </select>
              </div>
              <button
                type="submit"
                className="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-bold py-3 rounded-xl text-xs transition shadow-md shadow-emerald-600/30"
              >
                Record Payment
              </button>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};
