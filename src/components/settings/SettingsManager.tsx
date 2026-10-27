import React, { useState } from 'react';
import { CompanySettings } from '../../types';
import { ApiService } from '../../services/api';
import { I18nService } from '../../services/i18nService';
import { BackupSyncManager } from './BackupSyncManager';
import {
  Settings,
  Upload,
  Palette,
  Store,
  DollarSign,
  Printer,
  CheckCircle2,
  Image,
  Save,
  HardDrive
} from 'lucide-react';

interface Props {
  settings: CompanySettings | null;
  onSettingsUpdated: (updated: CompanySettings) => void;
  initialSubTab?: 'profile' | 'hardware' | 'backup';
}

export const SettingsManager: React.FC<Props> = ({ settings, onSettingsUpdated, initialSubTab = 'profile' }) => {
  const [activeSubTab, setActiveSubTab] = useState<'profile' | 'hardware' | 'backup'>(initialSubTab);
  const [formData, setFormData] = useState<Partial<CompanySettings>>(settings || {});
  const [loading, setLoading] = useState(false);
  const [successMsg, setSuccessMsg] = useState<string | null>(null);

  // Logo file upload converted to Base64
  const handleLogoUpload = (e: React.ChangeEvent<HTMLInputElement>, type: 'primary' | 'receipt' | 'favicon') => {
    const file = e.target.files?.[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = async () => {
      const base64 = reader.result as string;
      const res = await ApiService.request('settings.uploadLogo', {
        logo_type: type,
        image_base64: base64,
      });
      if (res.success) {
        const key = type === 'receipt' ? 'receipt_logo_url' : type === 'favicon' ? 'favicon_url' : 'primary_logo_url';
        setFormData((prev) => ({ ...prev, [key]: base64 }));
        setSuccessMsg(`Uploaded ${type} logo successfully!`);
      }
    };
    reader.readAsDataURL(file);
  };

  const handleSave = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setSuccessMsg(null);

    const res = await ApiService.request('settings.update', formData);
    setLoading(false);

    if (res.success && res.data?.settings) {
      setSuccessMsg('Settings saved successfully!');
      onSettingsUpdated(res.data.settings);

      // Apply CSS variables live
      if (formData.primary_color) {
        document.documentElement.style.setProperty('--primary-color', formData.primary_color);
      }
      if (formData.secondary_color) {
        document.documentElement.style.setProperty('--secondary-color', formData.secondary_color);
      }
    } else {
      alert(res.error?.message || 'Failed to update settings');
    }
  };

  return (
    <div className="flex flex-col h-full bg-slate-950 select-none overflow-y-auto p-6 font-sans">
      <div className="max-w-4xl mx-auto w-full space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between pb-4 border-b border-slate-800">
          <div className="flex items-center gap-2">
            <Settings className="w-6 h-6 text-blue-400" />
            <h2 className="text-xl font-black text-white">{I18nService.t('nav.settings')}</h2>
          </div>
          {activeSubTab !== 'backup' && (
            <button
              onClick={handleSave}
              disabled={loading}
              className="bg-blue-600 hover:bg-blue-500 text-white font-bold px-6 py-2.5 rounded-2xl text-xs transition flex items-center gap-2 shadow-lg shadow-blue-600/30"
            >
              <Save className="w-4 h-4" />
              <span>{loading ? 'Saving...' : 'Save Settings'}</span>
            </button>
          )}
        </div>

        {/* Sub-navigation Tabs */}
        <div className="flex items-center gap-2 border-b border-slate-800 pb-3">
          <button
            type="button"
            onClick={() => setActiveSubTab('profile')}
            className={`flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition ${
              activeSubTab === 'profile'
                ? 'bg-blue-600 text-white shadow-md shadow-blue-600/30'
                : 'text-slate-400 hover:text-white hover:bg-slate-900'
            }`}
          >
            <Store className="w-3.5 h-3.5" />
            <span>Store Profile & Logos</span>
          </button>
          <button
            type="button"
            onClick={() => setActiveSubTab('hardware')}
            className={`flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition ${
              activeSubTab === 'hardware'
                ? 'bg-blue-600 text-white shadow-md shadow-blue-600/30'
                : 'text-slate-400 hover:text-white hover:bg-slate-900'
            }`}
          >
            <Printer className="w-3.5 h-3.5" />
            <span>Currencies & Hardware</span>
          </button>
          <button
            type="button"
            onClick={() => setActiveSubTab('backup')}
            className={`flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition ${
              activeSubTab === 'backup'
                ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30'
                : 'text-slate-400 hover:text-white hover:bg-slate-900'
            }`}
          >
            <HardDrive className="w-3.5 h-3.5" />
            <span>Database Backup & Cloud Sync</span>
            <span className="bg-indigo-500/30 text-indigo-200 text-[10px] px-1.5 py-0.2 rounded-full font-mono">
              USB + Google Drive
            </span>
          </button>
        </div>

        {activeSubTab === 'backup' ? (
          <BackupSyncManager />
        ) : (
          <form onSubmit={handleSave} className="space-y-6">
            {successMsg && (
              <div className="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 px-4 py-3 rounded-2xl text-xs flex items-center gap-2">
                <CheckCircle2 className="w-4 h-4 shrink-0" />
                <span>{successMsg}</span>
              </div>
            )}

            {activeSubTab === 'profile' && (
              <>
                {/* Section 1: Logos & Branding */}
          <div className="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
            <h3 className="text-sm font-bold text-white flex items-center gap-2">
              <Image className="w-4 h-4 text-purple-400" />
              <span>Company Logos & Visual Branding</span>
            </h3>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              {/* Primary Logo */}
              <div className="bg-slate-950 border border-slate-800 rounded-2xl p-4 flex items-center gap-4">
                {formData.primary_logo_url ? (
                  <img src={formData.primary_logo_url} alt="Logo" className="w-16 h-16 object-contain rounded-xl bg-slate-900 p-1 border border-slate-700" />
                ) : (
                  <div className="w-16 h-16 rounded-xl bg-slate-800 border border-slate-700 flex items-center justify-center text-slate-500">
                    No Logo
                  </div>
                )}
                <div>
                  <span className="text-xs font-bold text-white block">Primary App Logo</span>
                  <span className="text-[11px] text-slate-400 block mb-2">High-res PNG or SVG logo</span>
                  <label className="bg-slate-800 hover:bg-slate-700 text-white px-3 py-1.5 rounded-xl text-xs font-semibold cursor-pointer transition inline-flex items-center gap-1.5 border border-slate-700">
                    <Upload className="w-3.5 h-3.5" />
                    <span>Upload Image</span>
                    <input type="file" accept="image/*" className="hidden" onChange={(e) => handleLogoUpload(e, 'primary')} />
                  </label>
                </div>
              </div>

              {/* Monochrome Receipt Logo */}
              <div className="bg-slate-950 border border-slate-800 rounded-2xl p-4 flex items-center gap-4">
                {formData.receipt_logo_url ? (
                  <img src={formData.receipt_logo_url} alt="Receipt Logo" className="w-16 h-16 object-contain rounded-xl bg-white p-1 grayscale contrast-200" />
                ) : (
                  <div className="w-16 h-16 rounded-xl bg-slate-800 border border-slate-700 flex items-center justify-center text-slate-500">
                    No Logo
                  </div>
                )}
                <div>
                  <span className="text-xs font-bold text-white block">Thermal Receipt Logo</span>
                  <span className="text-[11px] text-slate-400 block mb-2">High-contrast monochrome graphic</span>
                  <label className="bg-slate-800 hover:bg-slate-700 text-white px-3 py-1.5 rounded-xl text-xs font-semibold cursor-pointer transition inline-flex items-center gap-1.5 border border-slate-700">
                    <Upload className="w-3.5 h-3.5" />
                    <span>Upload Logo</span>
                    <input type="file" accept="image/*" className="hidden" onChange={(e) => handleLogoUpload(e, 'receipt')} />
                  </label>
                </div>
              </div>
            </div>

            {/* Theme Colors */}
            <div className="grid grid-cols-2 gap-4 pt-2">
              <div>
                <label className="block text-xs font-semibold text-slate-400 mb-1">Primary Theme Color</label>
                <div className="flex items-center gap-2">
                  <input
                    type="color"
                    value={formData.primary_color || '#2563eb'}
                    onChange={(e) => setFormData({ ...formData, primary_color: e.target.value })}
                    className="w-10 h-10 rounded-xl bg-transparent cursor-pointer border border-slate-700"
                  />
                  <input
                    type="text"
                    value={formData.primary_color || '#2563eb'}
                    onChange={(e) => setFormData({ ...formData, primary_color: e.target.value })}
                    className="flex-1 bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white font-mono"
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-400 mb-1">Secondary Theme Color</label>
                <div className="flex items-center gap-2">
                  <input
                    type="color"
                    value={formData.secondary_color || '#10b981'}
                    onChange={(e) => setFormData({ ...formData, secondary_color: e.target.value })}
                    className="w-10 h-10 rounded-xl bg-transparent cursor-pointer border border-slate-700"
                  />
                  <input
                    type="text"
                    value={formData.secondary_color || '#10b981'}
                    onChange={(e) => setFormData({ ...formData, secondary_color: e.target.value })}
                    className="flex-1 bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white font-mono"
                  />
                </div>
              </div>
            </div>
          </div>

          {/* Section 2: Business Profile & Tax */}
          <div className="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
            <h3 className="text-sm font-bold text-white flex items-center gap-2">
              <Store className="w-4 h-4 text-blue-400" />
              <span>Company Information & Industry Mode</span>
            </h3>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label className="block text-xs font-semibold text-slate-400 mb-1">Company Name</label>
                <input
                  type="text"
                  value={formData.company_name || ''}
                  onChange={(e) => setFormData({ ...formData, company_name: e.target.value })}
                  className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white font-bold focus:outline-none"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-400 mb-1">Company Name (Arabic)</label>
                <input
                  type="text"
                  value={formData.company_name_ar || ''}
                  onChange={(e) => setFormData({ ...formData, company_name_ar: e.target.value })}
                  className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white font-bold focus:outline-none"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-400 mb-1">Business Mode</label>
                <select
                  value={formData.business_mode || 'SUPERMARKET'}
                  onChange={(e) => setFormData({ ...formData, business_mode: e.target.value as any })}
                  className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white font-bold focus:outline-none cursor-pointer"
                >
                  <option value="SUPERMARKET">SUPERMARKET</option>
                  <option value="RESTAURANT">RESTAURANT</option>
                  <option value="RETAIL">RETAIL</option>
                  <option value="PHARMACY">PHARMACY</option>
                </select>
              </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label className="block text-xs font-semibold text-slate-400 mb-1">Tax ID / VAT Registration</label>
                <input
                  type="text"
                  value={formData.tax_id || ''}
                  onChange={(e) => setFormData({ ...formData, tax_id: e.target.value })}
                  className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white font-mono focus:outline-none"
                />
              </div>
              <div>
                <label className="block text-xs font-semibold text-slate-400 mb-1">Phone Number</label>
                <input
                  type="text"
                  value={formData.phone || ''}
                  onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
                  className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:outline-none"
                />
              </div>
              <div>
                <label className="block text-xs font-semibold text-slate-400 mb-1">Email</label>
                <input
                  type="email"
                  value={formData.email || ''}
                  onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                  className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:outline-none"
                />
              </div>
            </div>

            <div>
              <label className="block text-xs font-semibold text-slate-400 mb-1">Physical Address</label>
              <input
                type="text"
                value={formData.address || ''}
                onChange={(e) => setFormData({ ...formData, address: e.target.value })}
                className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:outline-none"
              />
            </div>
          </div>
        </>
      )}

      {activeSubTab === 'hardware' && (
        <>
          {/* Section 3: Dual Currency & Exchange Rates */}
          <div className="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
            <h3 className="text-sm font-bold text-white flex items-center gap-2">
              <DollarSign className="w-4 h-4 text-emerald-400" />
              <span>Multi-Currency Tender & Rates</span>
            </h3>

            <div className="grid grid-cols-2 sm:grid-cols-5 gap-3">
              <div>
                <label className="block text-xs font-semibold text-slate-400 mb-1">Primary Code</label>
                <input
                  type="text"
                  value={formData.primary_currency_code || 'USD'}
                  onChange={(e) => setFormData({ ...formData, primary_currency_code: e.target.value })}
                  className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white font-mono font-bold focus:outline-none"
                />
              </div>
              <div>
                <label className="block text-xs font-semibold text-slate-400 mb-1">Primary Symbol</label>
                <input
                  type="text"
                  value={formData.primary_currency_symbol || '$'}
                  onChange={(e) => setFormData({ ...formData, primary_currency_symbol: e.target.value })}
                  className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white font-bold text-center focus:outline-none"
                />
              </div>
              <div>
                <label className="block text-xs font-semibold text-slate-400 mb-1">Secondary Code</label>
                <input
                  type="text"
                  value={formData.secondary_currency_code || 'EUR'}
                  onChange={(e) => setFormData({ ...formData, secondary_currency_code: e.target.value })}
                  className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white font-mono font-bold focus:outline-none"
                />
              </div>
              <div>
                <label className="block text-xs font-semibold text-slate-400 mb-1">Secondary Symbol</label>
                <input
                  type="text"
                  value={formData.secondary_currency_symbol || '€'}
                  onChange={(e) => setFormData({ ...formData, secondary_currency_symbol: e.target.value })}
                  className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white font-bold text-center focus:outline-none"
                />
              </div>
              <div>
                <label className="block text-xs font-semibold text-slate-400 mb-1">Exchange Rate</label>
                <input
                  type="number"
                  step="0.0001"
                  value={formData.secondary_currency_rate || 0.92}
                  onChange={(e) => setFormData({ ...formData, secondary_currency_rate: parseFloat(e.target.value) || 1 })}
                  className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white font-mono font-bold focus:outline-none"
                />
              </div>
            </div>
          </div>

          {/* Section 4: Thermal Receipt Settings */}
          <div className="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
            <h3 className="text-sm font-bold text-white flex items-center gap-2">
              <Printer className="w-4 h-4 text-amber-400" />
              <span>Thermal Receipt Engine Setup</span>
            </h3>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="block text-xs font-semibold text-slate-400 mb-1">Receipt Paper Format</label>
                <select
                  value={formData.receipt_width || '80mm'}
                  onChange={(e) => setFormData({ ...formData, receipt_width: e.target.value as any })}
                  className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white font-bold focus:outline-none"
                >
                  <option value="80mm">80mm Standard Thermal Paper</option>
                  <option value="58mm">58mm Compact Mobile POS Paper</option>
                  <option value="A4">A4 Full Sheet Invoice</option>
                </select>
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-400 mb-1">Scale Barcode Prefix</label>
                <input
                  type="text"
                  maxLength={2}
                  value={formData.scale_barcode_prefix || '21'}
                  onChange={(e) => setFormData({ ...formData, scale_barcode_prefix: e.target.value })}
                  placeholder="20, 21, etc."
                  className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white font-mono focus:outline-none"
                />
              </div>
            </div>

            <div>
              <label className="block text-xs font-semibold text-slate-400 mb-1">Receipt Footer Note / Return Policy</label>
              <textarea
                rows={2}
                value={formData.receipt_footer || ''}
                onChange={(e) => setFormData({ ...formData, receipt_footer: e.target.value })}
                className="w-full bg-slate-950 border border-slate-700 rounded-xl p-3 text-xs text-white focus:outline-none"
              />
            </div>
          </div>
        </>
      )}
    </form>
  )}
      </div>
    </div>
  );
};
