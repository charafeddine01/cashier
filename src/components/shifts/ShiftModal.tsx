import React, { useState } from 'react';
import { Shift, CompanySettings } from '../../types';
import { ApiService } from '../../services/api';
import { I18nService } from '../../services/i18nService';
import {
  X,
  Coins,
  ArrowDownRight,
  ArrowUpRight,
  FileSpreadsheet,
  CheckCircle2,
  AlertTriangle,
  Calculator,
  Lock
} from 'lucide-react';

interface Props {
  isOpen: boolean;
  activeShift: Shift | null;
  settings: CompanySettings | null;
  onClose: () => void;
  onShiftUpdated: () => void;
}

export const ShiftModal: React.FC<Props> = ({
  isOpen,
  activeShift,
  settings,
  onClose,
  onShiftUpdated,
}) => {
  if (!isOpen) return null;

  const [activeTab, setActiveTab] = useState<'OPEN' | 'MOVEMENTS' | 'X_REPORT' | 'Z_REPORT'>(
    activeShift ? 'MOVEMENTS' : 'OPEN'
  );

  // Open Shift Form State
  const [openingFloat, setOpeningFloat] = useState<number>(100);
  const [openNotes, setOpenNotes] = useState('');

  // Cash Movement State
  const [movementType, setMovementType] = useState<'CASH_IN' | 'CASH_OUT'>('CASH_IN');
  const [movementAmount, setMovementAmount] = useState<number>(0);
  const [movementReason, setMovementReason] = useState('');

  // X-Report State
  const [xReportData, setXReportData] = useState<any | null>(null);

  // Z-Report Blind Count State
  const [denominations, setDenominations] = useState<Record<string, number>>({
    '100': 0,
    '50': 0,
    '20': 0,
    '10': 0,
    '5': 0,
    '1': 0,
    '0.25': 0,
  });
  const [zNotes, setZNotes] = useState('');
  const [zReportResult, setZReportResult] = useState<any | null>(null);

  const [loading, setLoading] = useState(false);
  const [errorMsg, setErrorMsg] = useState<string | null>(null);
  const [successMsg, setSuccessMsg] = useState<string | null>(null);

  // Compute total counted from denominations
  const totalCountedCash = Object.entries(denominations).reduce(
    (sum, [face, count]) => sum + (parseFloat(face) * (count || 0)),
    0
  );

  // Handle Opening Shift
  const handleOpenShift = async () => {
    setLoading(true);
    setErrorMsg(null);
    const res = await ApiService.request('shifts.open', {
      opening_float: openingFloat,
      notes: openNotes,
    });
    setLoading(false);

    if (res.success) {
      setSuccessMsg('Shift opened successfully.');
      onShiftUpdated();
      setActiveTab('MOVEMENTS');
    } else {
      setErrorMsg(res.error?.message || 'Failed to open shift.');
    }
  };

  // Handle Cash Movement
  const handleCashMovement = async () => {
    if (movementAmount <= 0 || !movementReason.trim()) {
      setErrorMsg('Please enter a valid amount and reason.');
      return;
    }

    setLoading(true);
    setErrorMsg(null);
    const res = await ApiService.request('shifts.cashMovement', {
      shift_id: activeShift?.id,
      type: movementType,
      amount: movementAmount,
      reason: movementReason,
    });
    setLoading(false);

    if (res.success) {
      setSuccessMsg(`Recorded ${movementType} of $${movementAmount.toFixed(2)}.`);
      setMovementAmount(0);
      setMovementReason('');
      onShiftUpdated();
    } else {
      setErrorMsg(res.error?.message || 'Failed to record cash movement.');
    }
  };

  // Fetch X-Report
  const handleFetchXReport = async () => {
    if (!activeShift) return;
    setLoading(true);
    const res = await ApiService.request('shifts.getXReport', {
      shift_id: activeShift.id,
    });
    setLoading(false);
    if (res.success) {
      setXReportData(res.data);
    }
  };

  // Close Shift with Z-Report
  const handleCloseZReport = async () => {
    if (!activeShift) return;
    setLoading(true);
    setErrorMsg(null);

    const res = await ApiService.request('shifts.closeZReport', {
      shift_id: activeShift.id,
      counted_cash: totalCountedCash,
      denominations,
      notes: zNotes,
    });
    setLoading(false);

    if (res.success) {
      setZReportResult(res.data);
      onShiftUpdated();
    } else {
      setErrorMsg(res.error?.message || 'Failed to close shift.');
    }
  };

  return (
    <div className="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
      <div className="bg-slate-900 border border-slate-700 rounded-3xl w-full max-w-2xl p-6 shadow-2xl relative flex flex-col max-h-[90vh]">
        {/* Header */}
        <div className="flex items-center justify-between pb-4 border-b border-slate-800 shrink-0">
          <div className="flex items-center gap-2.5">
            <div className="w-10 h-10 bg-blue-600/20 text-blue-400 rounded-xl flex items-center justify-center border border-blue-500/30">
              <Coins className="w-5 h-5" />
            </div>
            <div>
              <h3 className="font-bold text-white text-base">{I18nService.t('nav.shifts')}</h3>
              <p className="text-xs text-slate-400">
                {activeShift ? `Shift #${activeShift.id} Opened at ${activeShift.opened_at}` : 'No active shift in session'}
              </p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="p-1 rounded-full text-slate-400 hover:text-white hover:bg-slate-800 transition"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Tab Pills */}
        <div className="flex items-center gap-2 pt-4 pb-2 border-b border-slate-800/80 shrink-0">
          {!activeShift && (
            <button
              onClick={() => setActiveTab('OPEN')}
              className={`px-4 py-2 rounded-xl text-xs font-bold transition ${
                activeTab === 'OPEN' ? 'bg-blue-600 text-white' : 'bg-slate-800 text-slate-400 hover:text-white'
              }`}
            >
              {I18nService.t('shift.open_title')}
            </button>
          )}

          {activeShift && (
            <>
              <button
                onClick={() => setActiveTab('MOVEMENTS')}
                className={`px-4 py-2 rounded-xl text-xs font-bold transition ${
                  activeTab === 'MOVEMENTS' ? 'bg-blue-600 text-white' : 'bg-slate-800 text-slate-400 hover:text-white'
                }`}
              >
                Cash In / Cash Out
              </button>
              <button
                onClick={() => { setActiveTab('X_REPORT'); handleFetchXReport(); }}
                className={`px-4 py-2 rounded-xl text-xs font-bold transition ${
                  activeTab === 'X_REPORT' ? 'bg-blue-600 text-white' : 'bg-slate-800 text-slate-400 hover:text-white'
                }`}
              >
                {I18nService.t('shift.x_report')}
              </button>
              <button
                onClick={() => setActiveTab('Z_REPORT')}
                className={`px-4 py-2 rounded-xl text-xs font-bold transition ${
                  activeTab === 'Z_REPORT' ? 'bg-rose-600 text-white' : 'bg-slate-800 text-slate-400 hover:text-white'
                }`}
              >
                {I18nService.t('shift.z_report')}
              </button>
            </>
          )}
        </div>

        {/* Alerts */}
        {errorMsg && (
          <div className="bg-red-500/10 border border-red-500/30 text-red-400 px-3 py-2 rounded-xl text-xs mt-3">
            {errorMsg}
          </div>
        )}
        {successMsg && (
          <div className="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 px-3 py-2 rounded-xl text-xs mt-3">
            {successMsg}
          </div>
        )}

        {/* Content Body */}
        <div className="flex-1 overflow-y-auto py-4">
          {/* 1. OPEN SHIFT TAB */}
          {activeTab === 'OPEN' && (
            <div className="space-y-4 max-w-md mx-auto">
              <div>
                <label className="block text-xs font-semibold text-slate-400 mb-1">
                  {I18nService.t('shift.opening_float')} ($)
                </label>
                <input
                  type="number"
                  step="0.01"
                  value={openingFloat}
                  onChange={(e) => setOpeningFloat(parseFloat(e.target.value) || 0)}
                  className="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-lg font-black text-white focus:outline-none focus:border-blue-500"
                />
              </div>
              <div>
                <label className="block text-xs font-semibold text-slate-400 mb-1">Opening Shift Notes</label>
                <input
                  type="text"
                  value={openNotes}
                  onChange={(e) => setOpenNotes(e.target.value)}
                  placeholder="e.g. Morning opening drawer float"
                  className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2.5 text-xs text-white focus:outline-none focus:border-blue-500"
                />
              </div>
              <button
                onClick={handleOpenShift}
                disabled={loading}
                className="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-3.5 rounded-2xl shadow-lg shadow-blue-600/30 transition text-sm flex items-center justify-center gap-2"
              >
                <CheckCircle2 className="w-5 h-5" />
                <span>{I18nService.t('shift.open_btn')}</span>
              </button>
            </div>
          )}

          {/* 2. CASH MOVEMENTS TAB */}
          {activeTab === 'MOVEMENTS' && (
            <div className="space-y-4 max-w-md mx-auto">
              <div className="grid grid-cols-2 gap-2">
                <button
                  onClick={() => setMovementType('CASH_IN')}
                  className={`py-3 rounded-2xl border text-xs font-bold flex items-center justify-center gap-2 transition ${
                    movementType === 'CASH_IN'
                      ? 'bg-emerald-600 text-white border-emerald-500 shadow-lg shadow-emerald-600/30'
                      : 'bg-slate-800 text-slate-400 border-slate-700'
                  }`}
                >
                  <ArrowDownRight className="w-4 h-4" />
                  <span>{I18nService.t('shift.cash_in')}</span>
                </button>
                <button
                  onClick={() => setMovementType('CASH_OUT')}
                  className={`py-3 rounded-2xl border text-xs font-bold flex items-center justify-center gap-2 transition ${
                    movementType === 'CASH_OUT'
                      ? 'bg-rose-600 text-white border-rose-500 shadow-lg shadow-rose-600/30'
                      : 'bg-slate-800 text-slate-400 border-slate-700'
                  }`}
                >
                  <ArrowUpRight className="w-4 h-4" />
                  <span>{I18nService.t('shift.cash_out')}</span>
                </button>
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-400 mb-1">Movement Amount ($)</label>
                <input
                  type="number"
                  step="0.01"
                  value={movementAmount}
                  onChange={(e) => setMovementAmount(parseFloat(e.target.value) || 0)}
                  placeholder="0.00"
                  className="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-3 text-lg font-black text-white focus:outline-none focus:border-blue-500"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-400 mb-1">Reason / Notes</label>
                <input
                  type="text"
                  value={movementReason}
                  onChange={(e) => setMovementReason(e.target.value)}
                  placeholder="e.g. Petty cash for office milk, safe drop, change fund"
                  className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2.5 text-xs text-white focus:outline-none focus:border-blue-500"
                />
              </div>

              <button
                onClick={handleCashMovement}
                disabled={loading}
                className="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-3.5 rounded-2xl shadow-lg shadow-blue-600/30 transition text-sm"
              >
                Record Movement
              </button>
            </div>
          )}

          {/* 3. X-REPORT TAB */}
          {activeTab === 'X_REPORT' && xReportData && (
            <div className="bg-slate-950 border border-slate-800 rounded-2xl p-5 space-y-3 font-mono text-xs text-slate-300">
              <div className="text-center pb-3 border-b border-slate-800 font-sans">
                <h4 className="font-bold text-white text-sm">{xReportData.report_type}</h4>
                <p className="text-[11px] text-slate-400">{xReportData.summary.current_time}</p>
              </div>
              <div className="flex justify-between">
                <span>Opening Float:</span>
                <span className="font-bold">${xReportData.summary.opening_float.toFixed(2)}</span>
              </div>
              <div className="flex justify-between text-emerald-400">
                <span>Total Cash Sales:</span>
                <span className="font-bold">+${xReportData.summary.total_sales_cash.toFixed(2)}</span>
              </div>
              <div className="flex justify-between text-blue-400">
                <span>Total Card Sales:</span>
                <span className="font-bold">${xReportData.summary.total_sales_card.toFixed(2)}</span>
              </div>
              <div className="flex justify-between text-amber-400">
                <span>Total Cash In:</span>
                <span>+${xReportData.summary.total_cash_in.toFixed(2)}</span>
              </div>
              <div className="flex justify-between text-rose-400">
                <span>Total Cash Out:</span>
                <span>-${xReportData.summary.total_cash_out.toFixed(2)}</span>
              </div>
              <div className="flex justify-between text-rose-400">
                <span>Total Returns:</span>
                <span>-${xReportData.summary.total_returns.toFixed(2)}</span>
              </div>
              <div className="pt-3 border-t border-slate-800 flex justify-between text-sm font-black text-white">
                <span>Expected Drawer Cash:</span>
                <span className="text-emerald-400">${xReportData.summary.expected_cash_in_drawer.toFixed(2)}</span>
              </div>
            </div>
          )}

          {/* 4. BLIND Z-REPORT END OF SHIFT */}
          {activeTab === 'Z_REPORT' && (
            <div className="space-y-4">
              {!zReportResult ? (
                <>
                  <div className="bg-amber-500/10 border border-amber-500/30 text-amber-400 p-3 rounded-2xl text-xs flex items-center gap-2">
                    <Calculator className="w-4 h-4 shrink-0" />
                    <span>Blind count: Count the bills and coins currently in your cash drawer.</span>
                  </div>

                  {/* Denominations Counter Grid */}
                  <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
                    {Object.keys(denominations).map((denom) => (
                      <div key={denom} className="bg-slate-950 border border-slate-800 p-2.5 rounded-xl">
                        <span className="text-xs text-slate-400 font-bold block mb-1">${denom} Bills</span>
                        <input
                          type="number"
                          min="0"
                          value={denominations[denom] || 0}
                          onChange={(e) => {
                            const val = parseInt(e.target.value, 10) || 0;
                            setDenominations((prev) => ({ ...prev, [denom]: val }));
                          }}
                          className="w-full bg-slate-900 border border-slate-700 rounded-lg px-2 py-1.5 text-sm font-bold text-white text-center focus:outline-none"
                        />
                      </div>
                    ))}
                  </div>

                  <div className="bg-slate-950 border border-slate-800 rounded-2xl p-4 flex items-center justify-between">
                    <span className="text-xs text-slate-400 font-semibold">{I18nService.t('shift.counted_cash')}:</span>
                    <span className="text-2xl font-black text-white">${totalCountedCash.toFixed(2)}</span>
                  </div>

                  <div>
                    <label className="block text-xs font-semibold text-slate-400 mb-1">Closing Remarks</label>
                    <input
                      type="text"
                      value={zNotes}
                      onChange={(e) => setZNotes(e.target.value)}
                      placeholder="e.g. End of evening shift"
                      className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2.5 text-xs text-white focus:outline-none"
                    />
                  </div>

                  <button
                    onClick={handleCloseZReport}
                    disabled={loading}
                    className="w-full bg-rose-600 hover:bg-rose-500 text-white font-black py-4 rounded-2xl shadow-xl shadow-rose-600/30 transition text-sm flex items-center justify-center gap-2"
                  >
                    <Lock className="w-5 h-5" />
                    <span>{I18nService.t('shift.close_btn')}</span>
                  </button>
                </>
              ) : (
                /* Z-Report Summary Outcome */
                <div className="bg-slate-950 border border-slate-800 rounded-2xl p-5 font-mono text-xs space-y-3">
                  <div className="text-center pb-3 border-b border-slate-800 font-sans">
                    <h4 className="font-bold text-emerald-400 text-sm">{zReportResult.report_type}</h4>
                    <p className="text-[11px] text-slate-400">Shift #{zReportResult.shift_id} Closed at {zReportResult.closed_at}</p>
                  </div>
                  <div className="flex justify-between">
                    <span>Expected Cash:</span>
                    <span>${zReportResult.expected_cash.toFixed(2)}</span>
                  </div>
                  <div className="flex justify-between">
                    <span>Counted Cash:</span>
                    <span className="font-bold">${zReportResult.counted_cash.toFixed(2)}</span>
                  </div>
                  <div className={`flex justify-between font-bold text-sm pt-2 border-t border-slate-800 ${
                    zReportResult.variance >= 0 ? 'text-emerald-400' : 'text-rose-400'
                  }`}>
                    <span>Variance (Short/Over):</span>
                    <span>{zReportResult.variance >= 0 ? `+$${zReportResult.variance.toFixed(2)} (Overage)` : `-$${Math.abs(zReportResult.variance).toFixed(2)} (Shortage)`}</span>
                  </div>
                </div>
              )}
            </div>
          )}
        </div>
      </div>
    </div>
  );
};
