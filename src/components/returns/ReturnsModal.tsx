import React, { useState } from 'react';
import { ApiService } from '../../services/api';
import { I18nService } from '../../services/i18nService';
import {
  X,
  RotateCcw,
  Search,
  Package,
  CheckCircle2,
  AlertCircle,
  Coins,
  CreditCard,
  Archive,
  Trash2
} from 'lucide-react';

interface Props {
  isOpen: boolean;
  onClose: () => void;
  onReturnProcessed: () => void;
}

export const ReturnsModal: React.FC<Props> = ({ isOpen, onClose, onReturnProcessed }) => {
  if (!isOpen) return null;

  const [invoiceNumber, setInvoiceNumber] = useState('');
  const [order, setOrder] = useState<any | null>(null);
  const [items, setItems] = useState<any[]>([]);
  const [returnQtys, setReturnQtys] = useState<Record<number, number>>({});
  const [restockActions, setRestockActions] = useState<Record<number, 'RESTOCK' | 'WASTE_DAMAGED'>>({});
  const [refundMethod, setRefundMethod] = useState<'CASH' | 'CARD' | 'STORE_CREDIT'>('CASH');
  const [reason, setReason] = useState('Customer Return');
  const [loading, setLoading] = useState(false);
  const [errorMsg, setErrorMsg] = useState<string | null>(null);
  const [successResult, setSuccessResult] = useState<any | null>(null);

  const handleLookup = async (e?: React.FormEvent) => {
    if (e) e.preventDefault();
    if (!invoiceNumber.trim()) return;

    setLoading(true);
    setErrorMsg(null);
    setOrder(null);
    setItems([]);

    const res = await ApiService.request('returns.lookupInvoice', {
      invoice_number: invoiceNumber.trim(),
    });
    setLoading(false);

    if (res.success && res.data) {
      setOrder(res.data.order);
      setItems(res.data.items);
      // Initialize return quantities to 0
      const initQtys: Record<number, number> = {};
      const initActions: Record<number, 'RESTOCK' | 'WASTE_DAMAGED'> = {};
      res.data.items.forEach((it: any) => {
        initQtys[it.id] = 0;
        initActions[it.id] = 'RESTOCK';
      });
      setReturnQtys(initQtys);
      setRestockActions(initActions);
    } else {
      setErrorMsg(res.error?.message || 'Invoice not found.');
    }
  };

  // Compute total refund amount
  const totalRefundAmount = items.reduce((sum, it) => {
    const qty = returnQtys[it.id] || 0;
    return sum + (qty * Number(it.unit_price || 0));
  }, 0);

  const handleProcessReturn = async () => {
    const itemsToReturn: any[] = [];
    items.forEach((it) => {
      const q = returnQtys[it.id] || 0;
      if (q > 0) {
        itemsToReturn.push({
          order_item_id: it.id,
          product_id: it.product_id,
          quantity: q,
          unit_price: Number(it.unit_price),
          restock_action: restockActions[it.id] || 'RESTOCK',
        });
      }
    });

    if (itemsToReturn.length === 0) {
      setErrorMsg('Please select at least one item quantity to return.');
      return;
    }

    setLoading(true);
    setErrorMsg(null);

    const res = await ApiService.request('returns.process', {
      order_id: order.id,
      items: itemsToReturn,
      refund_method: refundMethod,
      reason,
    });
    setLoading(false);

    if (res.success) {
      setSuccessResult(res.data);
      onReturnProcessed();
    } else {
      setErrorMsg(res.error?.message || 'Failed to process return.');
    }
  };

  return (
    <div className="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
      <div className="bg-slate-900 border border-slate-700 rounded-3xl w-full max-w-2xl p-6 shadow-2xl relative flex flex-col max-h-[90vh]">
        <div className="flex items-center justify-between pb-4 border-b border-slate-800 shrink-0">
          <div className="flex items-center gap-2">
            <RotateCcw className="w-5 h-5 text-rose-400" />
            <h3 className="font-bold text-white text-base">{I18nService.t('returns.title')}</h3>
          </div>
          <button
            onClick={onClose}
            className="p-1 rounded-full text-slate-400 hover:text-white hover:bg-slate-800 transition"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Search Bar */}
        <form onSubmit={handleLookup} className="flex gap-2 py-4 border-b border-slate-800 shrink-0">
          <div className="relative flex-1">
            <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
            <input
              type="text"
              autoFocus
              value={invoiceNumber}
              onChange={(e) => setInvoiceNumber(e.target.value)}
              placeholder="Enter Invoice Number (e.g. INV-20261025-XXXX)..."
              className="w-full bg-slate-950 border border-slate-700 rounded-xl pl-9 pr-4 py-2.5 text-xs text-white focus:outline-none focus:border-blue-500 font-mono"
            />
          </div>
          <button
            type="submit"
            disabled={loading}
            className="bg-blue-600 hover:bg-blue-500 text-white font-bold px-4 py-2.5 rounded-xl text-xs transition"
          >
            Lookup
          </button>
        </form>

        {errorMsg && (
          <div className="bg-red-500/10 border border-red-500/30 text-red-400 px-3 py-2 rounded-xl text-xs my-3 flex items-center gap-2">
            <AlertCircle className="w-4 h-4 shrink-0" />
            <span>{errorMsg}</span>
          </div>
        )}

        <div className="flex-1 overflow-y-auto py-3 space-y-4">
          {successResult ? (
            <div className="bg-slate-950 border border-emerald-500/40 rounded-2xl p-6 text-center space-y-3">
              <CheckCircle2 className="w-12 h-12 text-emerald-400 mx-auto" />
              <h4 className="text-base font-bold text-white">Refund Successfully Issued!</h4>
              <p className="text-xs text-slate-400">Return Voucher: <span className="font-mono text-emerald-300">{successResult.return_number}</span></p>
              <div className="text-2xl font-black text-emerald-400">${Number(successResult.total_refund).toFixed(2)}</div>
              <p className="text-xs text-slate-400">Method: <span className="font-semibold text-white">{successResult.refund_method}</span></p>
              <button
                onClick={onClose}
                className="bg-slate-800 hover:bg-slate-700 text-white px-6 py-2 rounded-xl text-xs font-bold mt-2"
              >
                Close
              </button>
            </div>
          ) : order ? (
            <>
              {/* Order Info Banner */}
              <div className="bg-slate-950 border border-slate-800 rounded-2xl p-3 flex items-center justify-between text-xs">
                <div>
                  <span className="font-bold text-white block">{order.invoice_number}</span>
                  <span className="text-[11px] text-slate-400">{order.created_at} • Cashier: {order.cashier_name}</span>
                </div>
                <div className="text-right">
                  <span className="text-[10px] text-slate-400 block">Original Total:</span>
                  <span className="font-bold text-white">${Number(order.grand_total).toFixed(2)}</span>
                </div>
              </div>

              {/* Items List */}
              <div className="space-y-2">
                <span className="text-xs font-bold text-slate-400 block">Select Items to Return:</span>
                {items.map((it) => {
                  const returnable = it.remaining_returnable_qty || 0;
                  const currentQty = returnQtys[it.id] || 0;
                  const action = restockActions[it.id] || 'RESTOCK';

                  return (
                    <div
                      key={it.id}
                      className="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-3 flex items-center justify-between gap-3 text-xs"
                    >
                      <div className="flex-1">
                        <span className="font-bold text-white block">{it.product_name}</span>
                        <span className="text-[10px] text-slate-400">
                          Sold: {it.quantity} | Returnable: {returnable} | Price: ${Number(it.unit_price).toFixed(2)}
                        </span>
                      </div>

                      {returnable > 0 ? (
                        <div className="flex items-center gap-2">
                          {/* Restock vs Waste selector */}
                          <select
                            value={action}
                            onChange={(e) => setRestockActions((prev) => ({ ...prev, [it.id]: e.target.value as any }))}
                            className="bg-slate-950 border border-slate-700 text-[10px] font-semibold text-slate-300 rounded-lg p-1.5 focus:outline-none"
                          >
                            <option value="RESTOCK">Restock into Inventory</option>
                            <option value="WASTE_DAMAGED">Waste / Damaged</option>
                          </select>

                          {/* Qty input */}
                          <div className="flex items-center gap-1 bg-slate-950 border border-slate-700 rounded-lg p-1">
                            <span className="text-[10px] text-slate-400 px-1">Qty:</span>
                            <input
                              type="number"
                              min="0"
                              max={returnable}
                              value={currentQty}
                              onChange={(e) => {
                                const val = Math.min(returnable, Math.max(0, parseFloat(e.target.value) || 0));
                                setReturnQtys((prev) => ({ ...prev, [it.id]: val }));
                              }}
                              className="w-12 bg-transparent text-center font-bold text-white focus:outline-none"
                            />
                          </div>
                        </div>
                      ) : (
                        <span className="text-[10px] text-rose-400 font-semibold bg-rose-500/10 px-2 py-1 rounded-lg">
                          Fully Returned
                        </span>
                      )}
                    </div>
                  );
                })}
              </div>

              {/* Refund Method & Reason */}
              <div className="grid grid-cols-2 gap-3 pt-2">
                <div>
                  <label className="block text-xs font-semibold text-slate-400 mb-1">Refund Method</label>
                  <select
                    value={refundMethod}
                    onChange={(e) => setRefundMethod(e.target.value as any)}
                    className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs font-bold text-white focus:outline-none"
                  >
                    <option value="CASH">Cash Refund (Drawer)</option>
                    <option value="STORE_CREDIT">Customer Store Credit</option>
                    <option value="CARD">Card Refund</option>
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-semibold text-slate-400 mb-1">Return Reason</label>
                  <input
                    type="text"
                    value={reason}
                    onChange={(e) => setReason(e.target.value)}
                    className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:outline-none"
                  />
                </div>
              </div>
            </>
          ) : (
            <div className="text-center py-12 text-slate-400">
              <Package className="w-12 h-12 text-slate-600 mx-auto mb-2 stroke-1" />
              <p className="text-xs">Scan or enter an invoice number above to start refund inspection.</p>
            </div>
          )}
        </div>

        {order && !successResult && (
          <div className="pt-4 border-t border-slate-800 flex items-center justify-between shrink-0">
            <div>
              <span className="text-xs text-slate-400 block">Total Refund:</span>
              <span className="text-xl font-black text-rose-400">${totalRefundAmount.toFixed(2)}</span>
            </div>
            <button
              onClick={handleProcessReturn}
              disabled={loading || totalRefundAmount <= 0}
              className="bg-rose-600 hover:bg-rose-500 disabled:opacity-40 text-white font-bold px-6 py-3 rounded-2xl shadow-lg shadow-rose-600/30 transition text-xs"
            >
              Process Refund
            </button>
          </div>
        )}
      </div>
    </div>
  );
};
