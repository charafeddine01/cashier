import React, { useState, useEffect } from 'react';
import { CompanySettings, User, Shift } from '../../types';
import { I18nService, LanguageCode } from '../../services/i18nService';
import { OfflineSyncService } from '../../services/offlineSyncService';
import {
  Store,
  UtensilsCrossed,
  Shirt,
  Pill,
  Wifi,
  WifiOff,
  UserCheck,
  RefreshCw,
  Clock,
  Globe,
  Sliders,
  DollarSign
} from 'lucide-react';

interface Props {
  settings: CompanySettings | null;
  currentUser: User | null;
  activeShift: Shift | null;
  onOpenSwitchModal: () => void;
  onOpenShiftModal: () => void;
  onModeChange: (mode: 'SUPERMARKET' | 'RESTAURANT' | 'RETAIL' | 'PHARMACY') => void;
}

export const TopBar: React.FC<Props> = ({
  settings,
  currentUser,
  activeShift,
  onOpenSwitchModal,
  onOpenShiftModal,
  onModeChange,
}) => {
  const [isOnline, setIsOnline] = useState(navigator.onLine);
  const [pendingSyncCount, setPendingSyncCount] = useState(0);
  const [timeStr, setTimeStr] = useState('');
  const [lang, setLang] = useState<LanguageCode>(I18nService.getLanguage());

  useEffect(() => {
    const handleOnline = () => setIsOnline(true);
    const handleOffline = () => setIsOnline(false);

    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);

    const unsubscribeSync = OfflineSyncService.onSyncUpdate((status) => {
      setPendingSyncCount(status.pendingCount);
    });

    const timer = setInterval(() => {
      const now = new Date();
      setTimeStr(now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' }));
    }, 1000);

    return () => {
      window.removeEventListener('online', handleOnline);
      window.removeEventListener('offline', handleOffline);
      unsubscribeSync();
      clearInterval(timer);
    };
  }, []);

  const toggleLanguage = () => {
    const next = lang === 'en' ? 'ar' : 'en';
    I18nService.setLanguage(next);
    setLang(next);
  };

  const currentMode = settings?.business_mode || 'SUPERMARKET';

  const getModeIcon = () => {
    switch (currentMode) {
      case 'RESTAURANT':
        return <UtensilsCrossed className="w-4 h-4 text-rose-400" />;
      case 'RETAIL':
        return <Shirt className="w-4 h-4 text-purple-400" />;
      case 'PHARMACY':
        return <Pill className="w-4 h-4 text-emerald-400" />;
      default:
        return <Store className="w-4 h-4 text-blue-400" />;
    }
  };

  return (
    <header className="bg-slate-900 border-b border-slate-800 text-white h-16 px-4 flex items-center justify-between shrink-0 select-none z-30 shadow-md">
      {/* Left: Branding & Business Mode */}
      <div className="flex items-center gap-3">
        {settings?.primary_logo_url ? (
          <img
            src={settings.primary_logo_url}
            alt="Logo"
            className="w-9 h-9 rounded-xl object-contain bg-slate-800 p-1 border border-slate-700"
          />
        ) : (
          <div className="w-9 h-9 rounded-xl bg-gradient-to-br from-blue-600 to-indigo-700 flex items-center justify-center font-black text-white text-base shadow-lg shadow-blue-600/30">
            OP
          </div>
        )}

        <div>
          <h1 className="font-bold text-sm tracking-tight leading-none text-white flex items-center gap-2">
            <span>{lang === 'ar' && settings?.company_name_ar ? settings.company_name_ar : (settings?.company_name || 'OmniPOS Pro')}</span>
          </h1>
          <span className="text-[10px] text-slate-400 font-mono tracking-wide">
            {settings?.tax_id ? `TAX: ${settings.tax_id}` : 'Universal Cashier System'}
          </span>
        </div>

        {/* Business Mode Quick Switcher */}
        <div className="hidden sm:flex items-center gap-1.5 bg-slate-800/80 border border-slate-700/80 rounded-xl px-2.5 py-1 text-xs">
          {getModeIcon()}
          <select
            value={currentMode}
            onChange={(e) => onModeChange(e.target.value as any)}
            className="bg-transparent border-none text-xs font-semibold text-slate-200 focus:outline-none cursor-pointer"
          >
            <option value="SUPERMARKET" className="bg-slate-800 text-white">Supermarket Mode</option>
            <option value="RESTAURANT" className="bg-slate-800 text-white">Restaurant Mode</option>
            <option value="RETAIL" className="bg-slate-800 text-white">Retail Mode</option>
            <option value="PHARMACY" className="bg-slate-800 text-white">Pharmacy Mode</option>
          </select>
        </div>
      </div>

      {/* Right Controls */}
      <div className="flex items-center gap-2.5">
        {/* Offline / Online Status */}
        <div
          className={`flex items-center gap-1.5 px-2.5 py-1 rounded-xl text-xs font-medium border transition ${
            isOnline
              ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400'
              : 'bg-amber-500/10 border-amber-500/30 text-amber-400 animate-pulse'
          }`}
        >
          {isOnline ? <Wifi className="w-3.5 h-3.5" /> : <WifiOff className="w-3.5 h-3.5" />}
          <span className="hidden md:inline">{isOnline ? I18nService.t('common.online') : I18nService.t('common.offline')}</span>
          {pendingSyncCount > 0 && (
            <span
              onClick={() => OfflineSyncService.syncOutboxWithServer()}
              title="Click to sync offline orders"
              className="bg-amber-500 text-slate-950 font-bold px-1.5 py-0.2 rounded-full text-[10px] cursor-pointer hover:bg-amber-400"
            >
              {pendingSyncCount} sync
            </span>
          )}
        </div>

        {/* Active Shift Indicator */}
        <button
          onClick={onOpenShiftModal}
          className={`flex items-center gap-1.5 px-3 py-1 rounded-xl text-xs font-semibold border transition ${
            activeShift
              ? 'bg-blue-600/20 border-blue-500/30 text-blue-300 hover:bg-blue-600/30'
              : 'bg-rose-500/20 border-rose-500/30 text-rose-300 hover:bg-rose-500/30 animate-pulse'
          }`}
        >
          <DollarSign className="w-3.5 h-3.5" />
          <span className="hidden lg:inline">{activeShift ? `Shift #${activeShift.id} Open` : 'No Open Shift'}</span>
        </button>

        {/* Clock */}
        <div className="hidden xl:flex items-center gap-1 text-slate-400 text-xs font-mono bg-slate-800/60 border border-slate-700/60 px-2.5 py-1 rounded-xl">
          <Clock className="w-3 h-3 text-slate-400" />
          <span>{timeStr}</span>
        </div>

        {/* Language Switcher */}
        <button
          onClick={toggleLanguage}
          className="flex items-center gap-1 bg-slate-800 hover:bg-slate-700 border border-slate-700 px-2.5 py-1 rounded-xl text-xs font-bold text-slate-200 transition"
          title="Switch Language (English / Arabic)"
        >
          <Globe className="w-3.5 h-3.5 text-blue-400" />
          <span>{lang === 'en' ? 'العربية' : 'EN'}</span>
        </button>

        {/* Cashier Badge & PIN Switch */}
        <div className="flex items-center gap-2 bg-slate-800/80 border border-slate-700/80 pl-2 pr-1 py-1 rounded-2xl">
          <div className="w-6 h-6 rounded-full bg-blue-600 flex items-center justify-center text-[10px] font-bold text-white shrink-0">
            {currentUser?.full_name ? currentUser.full_name[0] : 'U'}
          </div>
          <div className="hidden sm:block text-left">
            <div className="text-xs font-bold text-slate-100 leading-none">{currentUser?.full_name || 'Cashier'}</div>
            <div className="text-[10px] text-blue-400 font-mono leading-none mt-0.5">{currentUser?.role || 'CASHIER'}</div>
          </div>
          <button
            onClick={onOpenSwitchModal}
            className="p-1.5 hover:bg-slate-700 rounded-xl text-slate-400 hover:text-white transition"
            title="Fast Cashier PIN Switch"
          >
            <RefreshCw className="w-3.5 h-3.5" />
          </button>
        </div>
      </div>
    </header>
  );
};
