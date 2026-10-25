import React, { useState, useEffect } from 'react';
import { RestaurantFloor, RestaurantTable } from '../../types';
import { ApiService } from '../../services/api';
import { I18nService } from '../../services/i18nService';
import {
  Utensils,
  Plus,
  RefreshCw,
  Users,
  DollarSign,
  CheckCircle,
  FileText
} from 'lucide-react';

interface Props {
  onSelectTableForOrder: (table: RestaurantTable) => void;
}

export const FloorPlanView: React.FC<Props> = ({ onSelectTableForOrder }) => {
  const [floors, setFloors] = useState<RestaurantFloor[]>([]);
  const [tables, setTables] = useState<RestaurantTable[]>([]);
  const [selectedFloorId, setSelectedFloorId] = useState<number | null>(null);
  const [loading, setLoading] = useState(false);

  const fetchFloorPlan = async () => {
    setLoading(true);
    const res = await ApiService.request('restaurant.floorsAndTables');
    setLoading(false);
    if (res.success && res.data) {
      setFloors(res.data.floors);
      setTables(res.data.tables);
      if (res.data.floors.length > 0 && selectedFloorId === null) {
        setSelectedFloorId(res.data.floors[0].id);
      }
    }
  };

  useEffect(() => {
    fetchFloorPlan();
  }, []);

  const handleUpdateStatus = async (tableId: number, status: 'AVAILABLE' | 'OCCUPIED' | 'BILL_PRINTED') => {
    await ApiService.request('restaurant.updateTableStatus', {
      table_id: tableId,
      status,
    });
    fetchFloorPlan();
  };

  const currentTables = tables.filter((t) => t.floor_id === selectedFloorId);

  return (
    <div className="flex flex-col h-full bg-slate-950 select-none overflow-hidden">
      {/* Top Controls */}
      <div className="p-3 bg-slate-900 border-b border-slate-800 flex items-center justify-between shrink-0">
        <div className="flex items-center gap-2">
          <Utensils className="w-5 h-5 text-rose-400" />
          <h2 className="text-sm font-bold text-white">{I18nService.t('rest.tables')}</h2>

          {/* Floor tabs */}
          <div className="flex items-center gap-1.5 ml-4">
            {floors.map((f) => (
              <button
                key={f.id}
                onClick={() => setSelectedFloorId(f.id)}
                className={`px-3 py-1.5 rounded-xl text-xs font-bold transition ${
                  selectedFloorId === f.id
                    ? 'bg-rose-600 text-white shadow-md shadow-rose-600/30'
                    : 'bg-slate-800 text-slate-400 hover:text-white'
                }`}
              >
                {f.name}
              </button>
            ))}
          </div>
        </div>

        <button
          onClick={fetchFloorPlan}
          className="p-2 hover:bg-slate-800 text-slate-400 hover:text-white rounded-xl transition"
          title="Refresh Tables"
        >
          <RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} />
        </button>
      </div>

      {/* Visual Table Map Floor Canvas */}
      <div className="flex-1 relative bg-slate-950 overflow-auto p-8">
        <div className="w-[900px] h-[600px] bg-slate-900/60 border-2 border-dashed border-slate-800 rounded-3xl relative p-6 shadow-inner">
          {currentTables.map((t) => {
            const isAvailable = t.status === 'AVAILABLE';
            const isOccupied = t.status === 'OCCUPIED';
            const isBillPrinted = t.status === 'BILL_PRINTED';

            const statusColor = isAvailable
              ? 'border-emerald-500/50 bg-emerald-950/30 text-emerald-300 hover:border-emerald-400'
              : isOccupied
              ? 'border-amber-500/50 bg-amber-950/30 text-amber-300 hover:border-amber-400'
              : 'border-purple-500/50 bg-purple-950/30 text-purple-300 hover:border-purple-400';

            return (
              <div
                key={t.id}
                style={{
                  position: 'absolute',
                  left: `${t.position_x}px`,
                  top: `${t.position_y}px`,
                  width: `${t.width}px`,
                  height: `${t.height}px`,
                }}
                className={`border-2 rounded-2xl flex flex-col justify-between p-2.5 shadow-lg backdrop-blur-sm transition cursor-pointer ${statusColor}`}
                onClick={() => onSelectTableForOrder(t)}
              >
                <div className="flex items-center justify-between">
                  <span className="font-black text-sm text-white">{t.table_number}</span>
                  <span className="flex items-center gap-0.5 text-[10px] text-slate-400 font-semibold">
                    <Users className="w-2.5 h-2.5" /> {t.capacity}
                  </span>
                </div>

                {t.current_order_total ? (
                  <div className="text-right">
                    <span className="text-[10px] text-slate-400 block leading-none">Bill:</span>
                    <span className="text-xs font-black text-white">${Number(t.current_order_total).toFixed(2)}</span>
                  </div>
                ) : (
                  <div className="text-[10px] font-semibold text-slate-400 uppercase">
                    {t.status}
                  </div>
                )}

                {/* Table Quick Status Toggles */}
                <div className="flex items-center justify-between pt-1 border-t border-slate-700/50" onClick={(e) => e.stopPropagation()}>
                  <button
                    onClick={() => handleUpdateStatus(t.id, 'AVAILABLE')}
                    className="p-1 hover:text-emerald-400 text-slate-500"
                    title="Mark Available"
                  >
                    <CheckCircle className="w-3.5 h-3.5" />
                  </button>
                  <button
                    onClick={() => handleUpdateStatus(t.id, 'BILL_PRINTED')}
                    className="p-1 hover:text-purple-400 text-slate-500"
                    title="Print Bill"
                  >
                    <FileText className="w-3.5 h-3.5" />
                  </button>
                </div>
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
};
