import React, { useState } from 'react';
import { I18nService } from '../../services/i18nService';
import { X, Camera, Barcode } from 'lucide-react';

interface Props {
  isOpen: boolean;
  onClose: () => void;
  onScan: (barcode: string) => void;
}

export const CameraScannerModal: React.FC<Props> = ({ isOpen, onClose, onScan }) => {
  const [manualCode, setManualCode] = useState('');

  if (!isOpen) return null;

  const handleManualSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (manualCode.trim()) {
      onScan(manualCode.trim());
      setManualCode('');
      onClose();
    }
  };

  return (
    <div className="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
      <div className="bg-slate-900 border border-slate-700 rounded-3xl w-full max-w-sm p-6 shadow-2xl relative">
        <button
          onClick={onClose}
          className="absolute top-4 right-4 text-slate-400 hover:text-white p-1 rounded-full hover:bg-slate-800 transition"
        >
          <X className="w-5 h-5" />
        </button>

        <div className="text-center mb-6">
          <div className="w-12 h-12 bg-blue-600/20 text-blue-400 border border-blue-500/30 rounded-2xl mx-auto flex items-center justify-center mb-2">
            <Camera className="w-6 h-6" />
          </div>
          <h3 className="text-base font-bold text-white">{I18nService.t('pos.barcode_camera')}</h3>
          <p className="text-xs text-slate-400">Aim camera at barcode or type digits</p>
        </div>

        {/* Viewfinder simulation */}
        <div className="w-full h-44 bg-slate-950 rounded-2xl border-2 border-dashed border-blue-500/50 flex flex-col items-center justify-center relative overflow-hidden mb-5">
          <div className="w-4/5 h-0.5 bg-rose-500 shadow-lg shadow-rose-500/80 animate-pulse" />
          <div className="text-center mt-3 text-[11px] text-slate-400 flex items-center gap-1.5">
            <Barcode className="w-4 h-4 text-blue-400" />
            <span>Scanning for EAN-13, UPC, Code-128...</span>
          </div>
        </div>

        {/* Manual Barcode Input Form */}
        <form onSubmit={handleManualSubmit} className="space-y-3">
          <div>
            <label className="block text-xs font-semibold text-slate-400 mb-1">Manual Barcode / PLU</label>
            <input
              type="text"
              autoFocus
              value={manualCode}
              onChange={(e) => setManualCode(e.target.value)}
              placeholder="e.g. 5449000131805 or 2101001004505"
              className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2.5 text-xs text-white focus:outline-none focus:border-blue-500 font-mono"
            />
          </div>
          <button
            type="submit"
            disabled={!manualCode.trim()}
            className="w-full bg-blue-600 hover:bg-blue-500 disabled:opacity-50 text-white font-bold py-3 rounded-xl transition text-xs shadow-md shadow-blue-600/30"
          >
            Submit Barcode
          </button>
        </form>
      </div>
    </div>
  );
};
