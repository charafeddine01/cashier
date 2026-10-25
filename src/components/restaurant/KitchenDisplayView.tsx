import React, { useState, useEffect } from 'react';
import { KdsOrder } from '../../types';
import { ApiService } from '../../services/api';
import { I18nService } from '../../services/i18nService';
import {
  ChefHat,
  Clock,
  CheckCircle2,
  Play,
  Check,
  RefreshCw,
  Flame,
  Utensils
} from 'lucide-react';

export const KitchenDisplayView: React.FC = () => {
  const [orders, setOrders] = useState<KdsOrder[]>([]);
  const [stationFilter, setStationFilter] = useState<string>('ALL');
  const [loading, setLoading] = useState(false);

  const fetchKds = async () => {
    setLoading(true);
    const res = await ApiService.request('restaurant.kdsOrders', {
      station: stationFilter !== 'ALL' ? stationFilter : undefined,
    });
    setLoading(false);
    if (res.success && res.data) {
      setOrders(res.data.kds_orders || []);
    }
  };

  useEffect(() => {
    fetchKds();
    const interval = setInterval(fetchKds, 10000); // Polling every 10s
    return () => clearInterval(interval);
  }, [stationFilter]);

  const handleUpdateStatus = async (orderId: number, status: 'PENDING' | 'PREPARING' | 'READY' | 'SERVED') => {
    await ApiService.request('restaurant.kdsUpdateStatus', {
      order_id: orderId,
      status,
    });
    fetchKds();
  };

  const handleUpdateItemStatus = async (itemId: number, status: 'PENDING' | 'PREPARING' | 'READY' | 'SERVED') => {
    await ApiService.request('restaurant.kdsUpdateStatus', {
      order_item_id: itemId,
      status,
    });
    fetchKds();
  };

  return (
    <div className="flex flex-col h-full bg-slate-950 select-none overflow-hidden">
      {/* Top Controls */}
      <div className="p-3 bg-slate-900 border-b border-slate-800 flex items-center justify-between shrink-0">
        <div className="flex items-center gap-3">
          <div className="w-8 h-8 rounded-xl bg-rose-600/20 text-rose-400 flex items-center justify-center border border-rose-500/30">
            <ChefHat className="w-4 h-4" />
          </div>
          <h2 className="text-sm font-bold text-white">{I18nService.t('rest.kds_title')}</h2>

          {/* Station Filters */}
          <div className="flex items-center gap-1.5 ml-4">
            {['ALL', 'Grill Station', 'Pizza Oven', 'Barista Station'].map((station) => (
              <button
                key={station}
                onClick={() => setStationFilter(station)}
                className={`px-3 py-1.5 rounded-xl text-xs font-bold transition ${
                  stationFilter === station
                    ? 'bg-rose-600 text-white shadow-md shadow-rose-600/30'
                    : 'bg-slate-800 text-slate-400 hover:text-white'
                }`}
              >
                {station}
              </button>
            ))}
          </div>
        </div>

        <button
          onClick={fetchKds}
          className="p-2 hover:bg-slate-800 text-slate-400 hover:text-white rounded-xl transition"
          title="Refresh KDS"
        >
          <RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} />
        </button>
      </div>

      {/* Orders Grid */}
      <div className="flex-1 overflow-y-auto p-4">
        {orders.length === 0 ? (
          <div className="h-full flex flex-col items-center justify-center text-center text-slate-500">
            <Utensils className="w-12 h-12 stroke-1 mb-2" />
            <p className="text-sm font-bold">Kitchen is clear!</p>
            <p className="text-xs text-slate-600 mt-1">No active kitchen orders pending.</p>
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            {orders.map((order) => {
              const elapsedMinutes = Math.floor((order.elapsed_seconds || 0) / 60);
              const isUrgent = elapsedMinutes > 15;

              return (
                <div
                  key={order.order_id}
                  className={`bg-slate-900 border rounded-3xl flex flex-col justify-between overflow-hidden shadow-xl transition ${
                    isUrgent ? 'border-rose-500/80' : 'border-slate-800'
                  }`}
                >
                  {/* Card Header */}
                  <div className={`p-3 border-b flex items-center justify-between ${
                    isUrgent ? 'bg-rose-950/40 border-rose-500/40 text-rose-300' : 'bg-slate-850 border-slate-800 text-slate-300'
                  }`}>
                    <div>
                      <div className="font-black text-sm text-white">
                        {order.table_number ? `Table: ${order.table_number}` : 'Takeaway'}
                      </div>
                      <span className="text-[10px] font-mono text-slate-400">
                        {order.invoice_number}
                      </span>
                    </div>

                    <div className="flex items-center gap-1 text-xs font-bold font-mono">
                      <Clock className="w-3.5 h-3.5" />
                      <span>{elapsedMinutes}m ago</span>
                    </div>
                  </div>

                  {/* Line Items List */}
                  <div className="p-3 space-y-2.5 flex-1 overflow-y-auto">
                    {order.items.map((it) => (
                      <div
                        key={it.id}
                        onClick={() => handleUpdateItemStatus(it.id, it.kitchen_status === 'PREPARING' ? 'READY' : 'PREPARING')}
                        className="bg-slate-950/80 border border-slate-800/80 p-2.5 rounded-2xl cursor-pointer hover:border-slate-700 transition"
                      >
                        <div className="flex items-start justify-between gap-2">
                          <span className="font-bold text-xs text-white">
                            <span className="text-blue-400 mr-1.5">{it.quantity}x</span>
                            {it.product_name}
                          </span>
                          <span className={`text-[9px] font-bold px-1.5 py-0.5 rounded-md ${
                            it.kitchen_status === 'READY'
                              ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30'
                              : it.kitchen_status === 'PREPARING'
                              ? 'bg-amber-500/20 text-amber-400 border border-amber-500/30'
                              : 'bg-slate-800 text-slate-400'
                          }`}>
                            {it.kitchen_status}
                          </span>
                        </div>

                        {/* Modifiers List */}
                        {it.modifiers_parsed && it.modifiers_parsed.length > 0 && (
                          <div className="mt-1 space-y-0.5">
                            {it.modifiers_parsed.map((mod, midx) => (
                              <div key={midx} className="text-[10px] text-purple-300 font-semibold flex items-center gap-1">
                                <span className="w-1 h-1 rounded-full bg-purple-400" />
                                <span>{mod.name}</span>
                              </div>
                            ))}
                          </div>
                        )}
                      </div>
                    ))}
                  </div>

                  {/* Card Actions Footer */}
                  <div className="p-3 bg-slate-850 border-t border-slate-800 grid grid-cols-2 gap-2">
                    <button
                      onClick={() => handleUpdateStatus(order.order_id, 'PREPARING')}
                      className="bg-amber-600/20 hover:bg-amber-600/30 border border-amber-500/40 text-amber-300 font-bold py-2 rounded-xl text-xs transition flex items-center justify-center gap-1"
                    >
                      <Play className="w-3.5 h-3.5" />
                      <span>{I18nService.t('rest.mark_preparing')}</span>
                    </button>
                    <button
                      onClick={() => handleUpdateStatus(order.order_id, 'READY')}
                      className="bg-emerald-600 hover:bg-emerald-500 text-white font-bold py-2 rounded-xl text-xs transition flex items-center justify-center gap-1 shadow-md shadow-emerald-600/30"
                    >
                      <Check className="w-3.5 h-3.5" />
                      <span>{I18nService.t('rest.mark_ready')}</span>
                    </button>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </div>
    </div>
  );
};
