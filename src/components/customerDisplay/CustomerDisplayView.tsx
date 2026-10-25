import React, { useState, useEffect } from 'react';
import { CustomerDisplayPayload, HardwareService } from '../../services/hardwareService';
import { CompanySettings } from '../../types';
import {
  Tv,
  CheckCircle2,
  Sparkles,
  ShoppingBag,
  CreditCard,
  Tag
} from 'lucide-react';

interface Props {
  settings: CompanySettings | null;
}

export const CustomerDisplayView: React.FC<Props> = ({ settings }) => {
  const [displayData, setDisplayData] = useState<CustomerDisplayPayload>({
    companyName: settings?.company_name || 'OmniPOS SuperStore',
    primaryCurrencySymbol: settings?.primary_currency_symbol || '$',
    secondaryCurrencySymbol: settings?.secondary_currency_symbol || '€',
    secondaryRate: settings?.secondary_currency_rate || 0.92,
    items: [],
    subtotal: 0,
    taxTotal: 0,
    discountTotal: 0,
    grandTotal: 0,
    grandTotalSecondary: 0,
    status: 'WELCOME',
  });

  useEffect(() => {
    const unsubscribe = HardwareService.onCustomerDisplayMessage((payload) => {
      setDisplayData(payload);
    });
    return () => unsubscribe();
  }, []);

  const pSymbol = displayData.primaryCurrencySymbol;
  const sSymbol = displayData.secondaryCurrencySymbol;

  return (
    <div className="min-h-screen bg-slate-950 text-white flex flex-col justify-between p-6 select-none font-sans">
      {/* Top Header */}
      <header className="flex items-center justify-between border-b border-slate-800 pb-4">
        <div className="flex items-center gap-3">
          {settings?.primary_logo_url ? (
            <img
              src={settings.primary_logo_url}
              alt="Logo"
              className="w-12 h-12 object-contain bg-slate-900 rounded-2xl p-1.5 border border-slate-700"
            />
          ) : (
            <div className="w-12 h-12 rounded-2xl bg-gradient-to-br from-blue-600 to-indigo-600 flex items-center justify-center text-xl font-black text-white shadow-lg shadow-blue-500/30">
              OP
            </div>
          )}
          <div>
            <h1 className="text-xl font-black text-white tracking-tight">
              {displayData.companyName}
            </h1>
            <p className="text-xs text-emerald-400 font-semibold flex items-center gap-1.5">
              <span className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse" />
              Customer Pole Display Active
            </p>
          </div>
        </div>

        <div className="text-right">
          <span className="text-xs text-slate-400 block font-mono">Current Register Session</span>
          <span className="text-xs font-bold text-slate-200">Terminal #01</span>
        </div>
      </header>

      {/* Main Body */}
      <main className="flex-1 grid grid-cols-1 lg:grid-cols-3 gap-6 my-6 overflow-hidden">
        {/* Left 2 Cols: Scanned Items List */}
        <div className="lg:col-span-2 bg-slate-900 border border-slate-800 rounded-3xl p-6 flex flex-col justify-between overflow-hidden shadow-2xl">
          <div className="flex items-center justify-between pb-3 border-b border-slate-800 shrink-0">
            <h2 className="text-sm font-bold text-slate-300 flex items-center gap-2">
              <ShoppingBag className="w-4 h-4 text-blue-400" />
              <span>Scanned Items ({displayData.items.length})</span>
            </h2>
            <span className="text-xs font-mono text-slate-400">Live Checkout Feed</span>
          </div>

          <div className="flex-1 overflow-y-auto py-3 space-y-2.5">
            {displayData.items.length === 0 ? (
              <div className="h-full flex flex-col items-center justify-center text-center p-8 text-slate-500">
                <Sparkles className="w-16 h-16 stroke-1 mb-3 text-blue-500 animate-bounce" />
                <h3 className="text-lg font-bold text-slate-200">Welcome to Our Store!</h3>
                <p className="text-xs text-slate-400 max-w-xs mt-1">
                  Your scanned items, weight breakdown, and savings will appear here in real time.
                </p>
              </div>
            ) : (
              displayData.items.map((it, idx) => (
                <div
                  key={idx}
                  className="bg-slate-950/80 border border-slate-800 p-3.5 rounded-2xl flex items-center justify-between text-sm"
                >
                  <div>
                    <span className="font-bold text-white block">{it.name}</span>
                    <span className="text-xs text-slate-400">
                      {it.quantity} x {pSymbol}{it.unitPrice.toFixed(2)}
                    </span>
                  </div>
                  <div className="text-base font-black text-white">
                    {pSymbol}{it.lineTotal.toFixed(2)}
                  </div>
                </div>
              ))
            )}
          </div>

          {/* Subtotal strip */}
          <div className="pt-3 border-t border-slate-800 grid grid-cols-3 text-xs text-slate-400">
            <div>
              <span>Subtotal:</span>
              <span className="font-bold text-slate-200 ml-1.5">{pSymbol}{displayData.subtotal.toFixed(2)}</span>
            </div>
            {displayData.discountTotal > 0 && (
              <div className="text-amber-400">
                <span>Savings:</span>
                <span className="font-bold ml-1.5">-{pSymbol}{displayData.discountTotal.toFixed(2)}</span>
              </div>
            )}
            <div className="text-right">
              <span>Tax:</span>
              <span className="font-bold text-slate-200 ml-1.5">{pSymbol}{displayData.taxTotal.toFixed(2)}</span>
            </div>
          </div>
        </div>

        {/* Right Col: Grand Total Card & Change */}
        <div className="bg-gradient-to-br from-blue-900/40 via-slate-900 to-slate-900 border border-blue-500/30 rounded-3xl p-6 flex flex-col justify-between shadow-2xl">
          <div>
            <span className="text-xs font-bold uppercase tracking-wider text-blue-400 block mb-2">
              Amount Due / Grand Total
            </span>

            {/* Primary Currency */}
            <div className="text-5xl font-black text-white tracking-tight my-2">
              {pSymbol}{displayData.grandTotal.toFixed(2)}
            </div>

            {/* Secondary Currency */}
            <div className="text-xl font-bold text-slate-400 mb-6">
              {sSymbol}{displayData.grandTotalSecondary.toFixed(2)}
            </div>

            {/* Change Due if completed */}
            {displayData.changeGiven !== undefined && displayData.changeGiven > 0 && (
              <div className="bg-emerald-500/10 border border-emerald-500/30 rounded-2xl p-4 my-4">
                <span className="text-xs font-semibold text-emerald-400 block">Change Due to Customer:</span>
                <div className="text-3xl font-black text-emerald-400 mt-1">
                  {pSymbol}{displayData.changeGiven.toFixed(2)}
                </div>
              </div>
            )}
          </div>

          {/* Promotional Banner */}
          <div className="bg-slate-950/80 border border-slate-800 rounded-2xl p-4 text-xs text-slate-400 space-y-1">
            <div className="flex items-center gap-1.5 font-bold text-white mb-1">
              <Tag className="w-4 h-4 text-amber-400" />
              <span>Loyalty Points Rewards</span>
            </div>
            <p>Earn 1 point for every $10 spent on all fresh groceries and pharmacy products!</p>
          </div>
        </div>
      </main>

      {/* Footer */}
      <footer className="text-center text-xs text-slate-500 pt-2 border-t border-slate-800/80">
        Thank you for shopping at {displayData.companyName} • Powered by OmniPOS Pro Commercial POS
      </footer>
    </div>
  );
};
