import React, { useState, useEffect } from 'react';
import { Warehouse, ProductBatch, CompanySettings } from '../../types';
import { ApiService } from '../../services/api';
import { I18nService } from '../../services/i18nService';
import {
  Boxes,
  ArrowRightLeft,
  Sliders,
  AlertTriangle,
  Clock,
  Warehouse as WhIcon,
  RefreshCw,
  Plus,
  CheckCircle2,
  X
} from 'lucide-react';

interface Props {
  settings: CompanySettings | null;
}

export const InventoryManager: React.FC<Props> = ({ settings }) => {
  const [warehouses, setWarehouses] = useState<Warehouse[]>([]);
  const [selectedWhId, setSelectedWhId] = useState<number>(1);
  const [stockLevels, setStockLevels] = useState<any[]>([]);
  const [nearExpiryBatches, setNearExpiryBatches] = useState<ProductBatch[]>([]);
  const [lowStockOnly, setLowStockOnly] = useState(false);
  const [loading, setLoading] = useState(false);

  // Stock Adjustment Modal
  const [isAdjustModalOpen, setIsAdjustModalOpen] = useState(false);
  const [adjustProductId, setAdjustProductId] = useState<number>(1);
  const [adjustQty, setAdjustQty] = useState<number>(0);
  const [adjustReason, setAdjustReason] = useState('Physical Stock Count');

  // Transfer Modal
  const [isTransferModalOpen, setIsTransferModalOpen] = useState(false);
  const [transferProductId, setTransferProductId] = useState<number>(1);
  const [transferToWh, setTransferToWh] = useState<number>(2);
  const [transferQty, setTransferQty] = useState<number>(5);

  const fetchInventory = async () => {
    setLoading(true);

    const [whRes, stockRes, expRes] = await Promise.all([
      ApiService.request('inventory.warehouses'),
      ApiService.request('inventory.stockLevels', { warehouse_id: selectedWhId, low_stock_only: lowStockOnly }),
      ApiService.request('inventory.nearExpiry', { days: 90 }),
    ]);

    setLoading(false);

    if (whRes.success && whRes.data) {
      setWarehouses(whRes.data.warehouses || []);
    }
    if (stockRes.success && stockRes.data) {
      setStockLevels(stockRes.data.stock_levels || []);
    }
    if (expRes.success && expRes.data) {
      setNearExpiryBatches(expRes.data.near_expiry_batches || []);
    }
  };

  useEffect(() => {
    fetchInventory();
  }, [selectedWhId, lowStockOnly]);

  const handleAdjustSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    const res = await ApiService.request('inventory.adjust', {
      product_id: adjustProductId,
      warehouse_id: selectedWhId,
      quantity: adjustQty,
      reason: adjustReason,
    });
    if (res.success) {
      setIsAdjustModalOpen(false);
      setAdjustQty(0);
      fetchInventory();
    } else {
      alert(res.error?.message || 'Adjustment failed');
    }
  };

  const handleTransferSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    const res = await ApiService.request('inventory.transfer', {
      product_id: transferProductId,
      from_warehouse_id: selectedWhId,
      to_warehouse_id: transferToWh,
      quantity: transferQty,
    });
    if (res.success) {
      setIsTransferModalOpen(false);
      fetchInventory();
    } else {
      alert(res.error?.message || 'Transfer failed');
    }
  };

  return (
    <div className="flex flex-col h-full bg-slate-950 select-none overflow-hidden font-sans">
      {/* Top Controls */}
      <div className="p-4 bg-slate-900 border-b border-slate-800 flex items-center justify-between shrink-0">
        <div className="flex items-center gap-3">
          <Boxes className="w-5 h-5 text-emerald-400" />
          <h2 className="text-sm font-bold text-white">{I18nService.t('nav.inventory')}</h2>

          {/* Warehouse Selector */}
          <div className="flex items-center gap-1.5 bg-slate-800 border border-slate-700 rounded-xl px-2.5 py-1 text-xs">
            <WhIcon className="w-3.5 h-3.5 text-slate-400" />
            <select
              value={selectedWhId}
              onChange={(e) => setSelectedWhId(parseInt(e.target.value, 10))}
              className="bg-transparent border-none text-xs font-bold text-white focus:outline-none cursor-pointer"
            >
              {warehouses.map((wh) => (
                <option key={wh.id} value={wh.id} className="bg-slate-800 text-white">
                  {wh.name}
                </option>
              ))}
            </select>
          </div>

          {/* Low Stock Toggle */}
          <button
            onClick={() => setLowStockOnly(!lowStockOnly)}
            className={`px-3 py-1 rounded-xl text-xs font-bold transition flex items-center gap-1.5 ${
              lowStockOnly ? 'bg-rose-600 text-white shadow-md shadow-rose-600/30' : 'bg-slate-800 text-slate-400 hover:text-white'
            }`}
          >
            <AlertTriangle className="w-3.5 h-3.5" />
            <span>Low Stock Only</span>
          </button>
        </div>

        <div className="flex items-center gap-2">
          <button
            onClick={() => { setAdjustProductId(stockLevels[0]?.product_id || 1); setIsAdjustModalOpen(true); }}
            className="bg-slate-800 hover:bg-slate-700 text-white font-bold px-3 py-1.5 rounded-xl text-xs transition border border-slate-700"
          >
            Adjust Stock
          </button>
          <button
            onClick={() => { setTransferProductId(stockLevels[0]?.product_id || 1); setIsTransferModalOpen(true); }}
            className="bg-blue-600 hover:bg-blue-500 text-white font-bold px-3 py-1.5 rounded-xl text-xs transition shadow-md shadow-blue-600/30"
          >
            Transfer Stock
          </button>
        </div>
      </div>

      {/* Near Expiry Banner (FEFO Alert) */}
      {nearExpiryBatches.length > 0 && (
        <div className="bg-amber-500/10 border-b border-amber-500/20 px-4 py-2.5 flex items-center justify-between text-xs text-amber-300 shrink-0">
          <div className="flex items-center gap-2">
            <Clock className="w-4 h-4 text-amber-400 shrink-0" />
            <span className="font-bold">FEFO Expiry Alert:</span>
            <span>{nearExpiryBatches.length} batch lots expiring within 90 days.</span>
          </div>
          <span className="text-[11px] font-mono text-amber-400">First-Expired, First-Out Enforced</span>
        </div>
      )}

      {/* Stock Levels Table */}
      <div className="flex-1 overflow-y-auto p-4">
        <div className="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
          <table className="w-full text-left text-xs text-slate-300">
            <thead className="bg-slate-950 text-slate-400 uppercase text-[10px] font-bold border-b border-slate-800">
              <tr>
                <th className="px-4 py-3">Product</th>
                <th className="px-4 py-3">Category</th>
                <th className="px-4 py-3">Current Stock</th>
                <th className="px-4 py-3">Alert Threshold</th>
                <th className="px-4 py-3">Stock Valuation</th>
                <th className="px-4 py-3">Status</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-800">
              {stockLevels.map((s) => {
                const isLow = s.is_low_stock === 1;
                const valuation = Number(s.current_quantity) * Number(s.cost_price);

                return (
                  <tr key={s.product_id} className="hover:bg-slate-850/50 transition">
                    <td className="px-4 py-3">
                      <span className="font-bold text-white block">{s.product_name}</span>
                      <span className="text-[10px] text-slate-400 font-mono">{s.sku}</span>
                    </td>
                    <td className="px-4 py-3">{s.category_name || 'General'}</td>
                    <td className="px-4 py-3 font-mono font-bold text-white text-sm">
                      {Number(s.current_quantity).toFixed(2)} {s.unit_code}
                    </td>
                    <td className="px-4 py-3 font-mono text-slate-400">
                      {Number(s.alert_quantity).toFixed(2)}
                    </td>
                    <td className="px-4 py-3 font-mono text-slate-300">
                      ${valuation.toFixed(2)}
                    </td>
                    <td className="px-4 py-3">
                      {isLow ? (
                        <span className="bg-rose-500/20 text-rose-400 border border-rose-500/30 text-[10px] font-bold px-2 py-0.5 rounded-full">
                          Low Stock
                        </span>
                      ) : (
                        <span className="bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 text-[10px] font-bold px-2 py-0.5 rounded-full">
                          Adequate
                        </span>
                      )}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </div>

      {/* Adjust Modal */}
      {isAdjustModalOpen && (
        <div className="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-slate-900 border border-slate-700 rounded-3xl w-full max-w-sm p-6 shadow-2xl relative">
            <button onClick={() => setIsAdjustModalOpen(false)} className="absolute top-4 right-4 text-slate-400 hover:text-white">
              <X className="w-5 h-5" />
            </button>
            <h3 className="font-bold text-white text-sm mb-4">Adjust Stock Quantity</h3>
            <form onSubmit={handleAdjustSubmit} className="space-y-3">
              <div>
                <label className="block text-xs font-semibold text-slate-400 mb-1">Adjustment (+ / -)</label>
                <input
                  type="number"
                  step="0.01"
                  value={adjustQty}
                  onChange={(e) => setAdjustQty(parseFloat(e.target.value) || 0)}
                  placeholder="e.g. +5 or -2"
                  className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-sm font-bold text-white focus:outline-none"
                />
              </div>
              <div>
                <label className="block text-xs font-semibold text-slate-400 mb-1">Reason</label>
                <input
                  type="text"
                  value={adjustReason}
                  onChange={(e) => setAdjustReason(e.target.value)}
                  className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:outline-none"
                />
              </div>
              <button
                type="submit"
                className="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-3 rounded-xl text-xs transition shadow-md shadow-blue-600/30"
              >
                Apply Adjustment
              </button>
            </form>
          </div>
        </div>
      )}

      {/* Transfer Modal */}
      {isTransferModalOpen && (
        <div className="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-slate-900 border border-slate-700 rounded-3xl w-full max-w-sm p-6 shadow-2xl relative">
            <button onClick={() => setIsTransferModalOpen(false)} className="absolute top-4 right-4 text-slate-400 hover:text-white">
              <X className="w-5 h-5" />
            </button>
            <h3 className="font-bold text-white text-sm mb-4">Inter-Warehouse Transfer</h3>
            <form onSubmit={handleTransferSubmit} className="space-y-3">
              <div>
                <label className="block text-xs font-semibold text-slate-400 mb-1">Destination Warehouse</label>
                <select
                  value={transferToWh}
                  onChange={(e) => setTransferToWh(parseInt(e.target.value, 10))}
                  className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs font-bold text-white focus:outline-none"
                >
                  {warehouses.filter((w) => w.id !== selectedWhId).map((w) => (
                    <option key={w.id} value={w.id}>{w.name}</option>
                  ))}
                </select>
              </div>
              <div>
                <label className="block text-xs font-semibold text-slate-400 mb-1">Transfer Quantity</label>
                <input
                  type="number"
                  min="0.01"
                  step="0.01"
                  value={transferQty}
                  onChange={(e) => setTransferQty(parseFloat(e.target.value) || 0)}
                  className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-sm font-bold text-white focus:outline-none"
                />
              </div>
              <button
                type="submit"
                className="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-3 rounded-xl text-xs transition shadow-md shadow-blue-600/30"
              >
                Execute Transfer
              </button>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};
