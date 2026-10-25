import React, { useState, useEffect } from 'react';
import { LicenseStatus } from '../../types';
import { ApiService } from '../../services/api';
import { I18nService } from '../../services/i18nService';
import {
  ShieldAlert,
  ShieldCheck,
  KeyRound,
  Copy,
  Clock,
  Layers,
  CheckCircle2,
  AlertTriangle,
  RefreshCw,
  ExternalLink
} from 'lucide-react';

interface Props {
  onLicenseActivated: () => void;
}

export const LicenseActivationGate: React.FC<Props> = ({ onLicenseActivated }) => {
  const [licenseStatus, setLicenseStatus] = useState<LicenseStatus | null>(null);
  const [keyInput, setKeyInput] = useState('');
  const [loading, setLoading] = useState(false);
  const [errorMsg, setErrorMsg] = useState<string | null>(null);
  const [successMsg, setSuccessMsg] = useState<string | null>(null);
  const [copiedFingerprint, setCopiedFingerprint] = useState(false);

  const fetchStatus = async () => {
    setLoading(true);
    const res = await ApiService.request('license.getStatus');
    setLoading(false);
    if (res.success && res.data) {
      setLicenseStatus(res.data);
    }
  };

  useEffect(() => {
    fetchStatus();
  }, []);

  const handleActivate = async () => {
    if (!keyInput.trim()) {
      setErrorMsg('Please paste your license key.');
      return;
    }

    setLoading(true);
    setErrorMsg(null);
    setSuccessMsg(null);

    const res = await ApiService.request('license.activate', {
      license_key: keyInput.trim(),
    });
    setLoading(false);

    if (res.success) {
      setSuccessMsg('License certificate verified and successfully activated!');
      setKeyInput('');
      fetchStatus();
      onLicenseActivated();
    } else {
      setErrorMsg(res.error?.message || 'Invalid or tampered license certificate.');
    }
  };

  const isTampered = licenseStatus?.is_tampered || false;
  const isExpired = licenseStatus?.status === 'EXPIRED';
  const isActive = licenseStatus?.is_active || false;

  return (
    <div className="min-h-screen bg-slate-950 flex items-center justify-center p-4 select-none font-sans">
      <div className="bg-slate-900 border border-slate-700 rounded-3xl w-full max-w-xl p-8 shadow-2xl relative">
        {/* Header Icon */}
        <div className="text-center mb-6">
          <div className="w-16 h-16 rounded-3xl mx-auto flex items-center justify-center text-3xl mb-3 shadow-xl bg-gradient-to-tr from-emerald-500 to-indigo-600 text-white font-black shadow-emerald-500/20">
            V
          </div>
          <h2 className="text-2xl font-black text-white">Vendra POS License Activation</h2>
          <p className="text-xs text-slate-400 mt-1">4-Layer Tamper-Resistant Ed25519 Commercial License Guard</p>
          <div className="mt-2">
            <a
              href="https://vendra.charafeddine.xyz"
              target="_blank"
              rel="noreferrer"
              className="text-xs font-semibold text-emerald-400 hover:text-emerald-300 inline-flex items-center gap-1 bg-emerald-500/10 border border-emerald-500/20 px-3 py-1 rounded-full"
            >
              <span>Need a license? Purchase or renew at vendra.charafeddine.xyz</span>
              <ExternalLink className="w-3 h-3" />
            </a>
          </div>
        </div>

        {/* Security Warning Banners */}
        {isTampered && (
          <div className="bg-rose-500/10 border border-rose-500/40 text-rose-300 p-4 rounded-2xl text-xs mb-6 space-y-1">
            <div className="font-bold flex items-center gap-1.5 text-rose-400">
              <ShieldAlert className="w-4 h-4" />
              <span>{I18nService.t('lic.clock_tampered')}</span>
            </div>
            <p>{licenseStatus?.tamper_reason || I18nService.t('lic.tamper_notice')}</p>
          </div>
        )}

        {isExpired && !isTampered && (
          <div className="bg-amber-500/10 border border-amber-500/40 text-amber-300 p-4 rounded-2xl text-xs mb-6 space-y-1">
            <div className="font-bold flex items-center gap-1.5 text-amber-400">
              <Clock className="w-4 h-4" />
              <span>License Expired</span>
            </div>
            <p>Your commercial license expired. Please enter a renewed license certificate to resume transactions.</p>
          </div>
        )}

        {/* Current License Details */}
        {licenseStatus && (
          <div className="bg-slate-950 border border-slate-800 rounded-2xl p-4 mb-6 space-y-2 text-xs">
            <div className="flex justify-between items-center">
              <span className="text-slate-400">Status:</span>
              <span className={`font-bold px-2 py-0.5 rounded-full ${
                isActive ? 'bg-emerald-500/20 text-emerald-400' : 'bg-rose-500/20 text-rose-400'
              }`}>
                {licenseStatus.status}
              </span>
            </div>
            <div className="flex justify-between">
              <span className="text-slate-400">Client / Store:</span>
              <span className="font-bold text-white">{licenseStatus.client_name || 'Unregistered'}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-slate-400">Plan Tier:</span>
              <span className="font-bold text-blue-400">{licenseStatus.plan_tier} ({licenseStatus.max_terminals} Terminals)</span>
            </div>
            <div className="flex justify-between">
              <span className="text-slate-400">Days Remaining:</span>
              <span className="font-bold text-emerald-400">{licenseStatus.days_remaining} Days</span>
            </div>

            {/* Hardware Fingerprint */}
            <div className="pt-2 border-t border-slate-800">
              <span className="text-slate-400 block mb-1">Server Installation Fingerprint:</span>
              <div className="flex items-center justify-between bg-slate-900 p-2 rounded-xl border border-slate-800">
                <span className="font-mono text-[10px] text-slate-300 truncate mr-2 select-all">
                  {licenseStatus.installation_fingerprint}
                </span>
                <button
                  onClick={() => {
                    navigator.clipboard.writeText(licenseStatus.installation_fingerprint);
                    setCopiedFingerprint(true);
                    setTimeout(() => setCopiedFingerprint(false), 2000);
                  }}
                  className="text-slate-400 hover:text-white px-2 py-1 rounded bg-slate-800 text-[10px] font-bold flex items-center gap-1"
                  title="Copy Fingerprint"
                >
                  {copiedFingerprint ? <CheckCircle2 className="w-3.5 h-3.5 text-emerald-400" /> : <Copy className="w-3.5 h-3.5" />}
                  <span>{copiedFingerprint ? 'Copied!' : 'Copy'}</span>
                </button>
              </div>
            </div>
          </div>
        )}

        {errorMsg && (
          <div className="bg-red-500/10 border border-red-500/30 text-red-400 px-3 py-2 rounded-xl text-xs mb-4">
            {errorMsg}
          </div>
        )}
        {successMsg && (
          <div className="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 px-3 py-2 rounded-xl text-xs mb-4">
            {successMsg}
          </div>
        )}

        {/* License Key Input Box */}
        <div className="space-y-4">
          <div>
            <label className="block text-xs font-semibold text-slate-400 mb-1">
              {I18nService.t('lic.paste_key')}
            </label>
            <textarea
              rows={4}
              value={keyInput}
              onChange={(e) => setKeyInput(e.target.value)}
              placeholder="Paste Base64 license certificate string here..."
              className="w-full bg-slate-950 border border-slate-700 rounded-2xl p-3 text-xs text-white font-mono focus:outline-none focus:border-blue-500"
            />
          </div>

          <button
            onClick={handleActivate}
            disabled={loading || !keyInput.trim()}
            className="w-full bg-blue-600 hover:bg-blue-500 disabled:opacity-50 text-white font-black py-4 rounded-2xl shadow-xl shadow-blue-600/30 transition text-sm flex items-center justify-center gap-2"
          >
            {loading ? <RefreshCw className="w-4 h-4 animate-spin" /> : <KeyRound className="w-4 h-4" />}
            <span>{I18nService.t('lic.activate_key')}</span>
          </button>
        </div>

        {/* Link to Vendor Manager */}
        <div className="text-center mt-6 pt-4 border-t border-slate-800 text-xs text-slate-500">
          Software Vendor?{' '}
          <a
            href="/vendor-license-manager/"
            target="_blank"
            rel="noreferrer"
            className="text-blue-400 hover:text-blue-300 font-semibold inline-flex items-center gap-1"
          >
            <span>Open Vendor License Manager</span>
            <ExternalLink className="w-3 h-3" />
          </a>
        </div>
      </div>
    </div>
  );
};
