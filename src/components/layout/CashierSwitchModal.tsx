import React, { useState } from 'react';
import { User } from '../../types';
import { ApiService } from '../../services/api';
import { I18nService } from '../../services/i18nService';
import { Delete, KeyRound, ShieldAlert, X } from 'lucide-react';

interface Props {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: (user: User, token: string) => void;
}

export const CashierSwitchModal: React.FC<Props> = ({ isOpen, onClose, onSuccess }) => {
  const [pin, setPin] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  if (!isOpen) return null;

  const handleDigit = (digit: string) => {
    if (pin.length < 6) {
      setPin((prev) => prev + digit);
      setError(null);
    }
  };

  const handleBackspace = () => {
    setPin((prev) => prev.slice(0, -1));
  };

  const handleClear = () => {
    setPin('');
    setError(null);
  };

  const handleSubmit = async () => {
    if (!pin) {
      setError('Please enter your PIN code.');
      return;
    }

    setLoading(true);
    setError(null);

    const res = await ApiService.request('auth.switchCashier', { pin });
    setLoading(false);

    if (res.success && res.data) {
      ApiService.setAuthToken(res.data.token);
      onSuccess(res.data.user, res.data.token);
      setPin('');
      onClose();
    } else {
      setError(res.error?.message || 'Invalid PIN code. Access denied.');
      setPin('');
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
          <div className="w-14 h-14 bg-blue-600/20 text-blue-400 border border-blue-500/30 rounded-2xl mx-auto flex items-center justify-center mb-3">
            <KeyRound className="w-7 h-7" />
          </div>
          <h2 className="text-xl font-bold text-white">{I18nService.t('common.switch_cashier')}</h2>
          <p className="text-xs text-slate-400 mt-1">Enter your 4-digit staff PIN code</p>
        </div>

        {/* PIN Dots Display */}
        <div className="flex justify-center items-center gap-3 mb-6">
          {[0, 1, 2, 3].map((index) => (
            <div
              key={index}
              className={`w-4 h-4 rounded-full transition-all duration-200 ${
                pin.length > index
                  ? 'bg-blue-500 scale-110 shadow-lg shadow-blue-500/50'
                  : 'bg-slate-800 border border-slate-700'
              }`}
            />
          ))}
        </div>

        {error && (
          <div className="bg-red-500/10 border border-red-500/30 text-red-400 px-3 py-2 rounded-xl text-xs flex items-center gap-2 mb-4">
            <ShieldAlert className="w-4 h-4 shrink-0" />
            <span>{error}</span>
          </div>
        )}

        {/* Keypad */}
        <div className="grid grid-cols-3 gap-3 mb-4">
          {['1', '2', '3', '4', '5', '6', '7', '8', '9'].map((d) => (
            <button
              key={d}
              onClick={() => handleDigit(d)}
              className="bg-slate-800/80 hover:bg-slate-700 active:scale-95 text-white text-xl font-bold py-4 rounded-2xl border border-slate-700/60 transition shadow-sm"
            >
              {d}
            </button>
          ))}
          <button
            onClick={handleClear}
            className="bg-slate-800/40 hover:bg-slate-800 text-slate-400 hover:text-white text-sm font-semibold py-4 rounded-2xl border border-slate-800 transition"
          >
            Clear
          </button>
          <button
            onClick={() => handleDigit('0')}
            className="bg-slate-800/80 hover:bg-slate-700 active:scale-95 text-white text-xl font-bold py-4 rounded-2xl border border-slate-700/60 transition shadow-sm"
          >
            0
          </button>
          <button
            onClick={handleBackspace}
            className="bg-slate-800/40 hover:bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center py-4 rounded-2xl border border-slate-800 transition"
          >
            <Delete className="w-5 h-5" />
          </button>
        </div>

        <button
          onClick={handleSubmit}
          disabled={loading || pin.length === 0}
          className="w-full bg-blue-600 hover:bg-blue-500 disabled:opacity-50 text-white font-bold py-3.5 rounded-2xl shadow-lg shadow-blue-600/30 transition text-sm flex items-center justify-center gap-2"
        >
          {loading ? 'Verifying PIN...' : 'Verify & Switch'}
        </button>
      </div>
    </div>
  );
};
