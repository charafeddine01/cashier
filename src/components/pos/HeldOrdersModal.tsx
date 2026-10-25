import React from 'react';
import { I18nService } from '../../services/i18nService';
import { X, Play, Trash2, Clock, PauseCircle, ShoppingBag } from 'lucide-react';

interface Props {
  isOpen: boolean;
  heldOrders: any[];
  onClose: () => void;
  onRecall: (heldId: number) => void;
  onDelete: (heldId: number) => void;
}

export const HeldOrdersModal: React.FC<Props> = ({
  isOpen,
  heldOrders,
  onClose,
  onRecall,
  onDelete,
}) => {
  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
      <div className="bg-slate-900 border border-slate-700 rounded-3xl w-full max-w-lg p-6 shadow-2xl relative flex flex-col max-h-[85vh]">
        <div className="flex items-center justify-between pb-4 border-b border-slate-800 shrink-0">
          <div className="flex items-center gap-2">
            <PauseCircle className="w-5 h-5 text-amber-400" />
            <h3 className="font-bold text-white text-base">{I18nService.t('pos.recall_order')}</h3>
          </div>
          <button
            onClick={onClose}
            className="p-1 rounded-full text-slate-400 hover:text-white hover:bg-slate-800 transition"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        <div className="flex-1 overflow-y-auto py-4 space-y-2.5">
          {heldOrders.length === 0 ? (
            <div className="text-center py-12 text-slate-400">
              <ShoppingBag className="w-12 h-12 text-slate-600 mx-auto mb-2 stroke-1" />
              <p className="text-xs font-semibold">No held orders found.</p>
            </div>
          ) : (
            heldOrders.map((ho) => {
              const items = ho.order_data?.items || [];
              const itemCount = items.reduce((acc: number, it: any) => acc + (it.quantity || 1), 0);

              return (
                <div
                  key={ho.id}
                  className="bg-slate-800/80 border border-slate-700/80 rounded-2xl p-4 flex items-center justify-between gap-4 hover:border-slate-600 transition"
                >
                  <div className="space-y-1">
                    <div className="flex items-center gap-2">
                      <span className="font-bold text-sm text-white">{ho.invoice_number}</span>
                      <span className="text-[10px] bg-slate-700 text-slate-300 px-2 py-0.5 rounded-full font-mono">
                        {itemCount} items
                      </span>
                    </div>
                    <div className="flex items-center gap-2 text-xs text-slate-400">
                      <Clock className="w-3.5 h-3.5" />
                      <span>{ho.created_at}</span>
                    </div>
                    <div className="text-sm font-black text-amber-400">
                      ${Number(ho.grand_total).toFixed(2)}
                    </div>
                  </div>

                  <div className="flex items-center gap-2">
                    <button
                      onClick={() => onRecall(ho.id)}
                      className="bg-blue-600 hover:bg-blue-500 text-white font-bold px-3.5 py-2 rounded-xl text-xs transition flex items-center gap-1.5 shadow-md shadow-blue-600/30"
                    >
                      <Play className="w-3.5 h-3.5" />
                      <span>Recall</span>
                    </button>
                    <button
                      onClick={() => onDelete(ho.id)}
                      className="p-2 hover:bg-rose-500/20 text-slate-400 hover:text-rose-400 rounded-xl transition"
                      title="Delete held order"
                    >
                      <Trash2 className="w-4 h-4" />
                    </button>
                  </div>
                </div>
              );
            })
          )}
        </div>
      </div>
    </div>
  );
};
