import React, { useState, useEffect } from 'react';
import { BackupSettings, BackupLog, UsbDriveInfo, SyncMode, BackupFrequency } from '../../types';
import { BackupSyncService } from '../../services/backupSyncService';
import {
  HardDrive,
  Cloud,
  Clock,
  RefreshCw,
  CheckCircle2,
  AlertTriangle,
  Folder,
  Download,
  Terminal,
  ShieldCheck,
  Save,
  Check,
  FileText,
  Key,
  Layers,
  Sparkles,
  Play
} from 'lucide-react';

export const BackupSyncManager: React.FC = () => {
  const [settings, setSettings] = useState<BackupSettings | null>(null);
  const [detectedDrives, setDetectedDrives] = useState<UsbDriveInfo[]>([]);
  const [logs, setLogs] = useState<BackupLog[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [syncing, setSyncing] = useState(false);
  const [testingUsb, setTestingUsb] = useState(false);
  const [testingGdrive, setTestingGdrive] = useState(false);
  const [statusMsg, setStatusMsg] = useState<{ type: 'success' | 'error' | 'info'; text: string } | null>(null);
  const [usbTestResult, setUsbTestResult] = useState<{ success: boolean; message: string; free_human?: string } | null>(null);
  const [gdriveTestResult, setGdriveTestResult] = useState<{ success: boolean; message: string; account_email?: string; storage_used?: string } | null>(null);

  // Form edit state
  const [formData, setFormData] = useState<Partial<BackupSettings>>({});

  useEffect(() => {
    loadData();

    // Listen to real-time sync events
    const unsubscribe = BackupSyncService.onSyncUpdate((evt) => {
      if (evt.status === 'SYNCING') {
        setSyncing(true);
      } else {
        setSyncing(false);
        if (evt.status === 'SUCCESS') {
          setStatusMsg({ type: 'success', text: evt.message || 'Database synchronized successfully.' });
          loadData(false);
        } else if (evt.status === 'ERROR') {
          setStatusMsg({ type: 'error', text: evt.message || 'Sync failed.' });
        }
      }
    });

    return () => unsubscribe();
  }, []);

  const loadData = async (showLoading: boolean = true) => {
    if (showLoading) setLoading(true);
    try {
      const data = await BackupSyncService.getSettings();
      setSettings(data.settings);
      setFormData(data.settings);
      setDetectedDrives(data.detected_usb_drives || []);
      setLogs(data.recent_logs || []);
    } catch (err: any) {
      setStatusMsg({ type: 'error', text: err.message || 'Failed to load backup configuration.' });
    } finally {
      if (showLoading) setLoading(false);
    }
  };

  const handleSaveSettings = async (e?: React.FormEvent) => {
    if (e) e.preventDefault();
    setSaving(true);
    setStatusMsg(null);
    try {
      const updated = await BackupSyncService.updateSettings(formData);
      setSettings(updated);
      setFormData(updated);
      setStatusMsg({ type: 'success', text: 'Backup & sync settings updated successfully.' });
    } catch (err: any) {
      setStatusMsg({ type: 'error', text: err.message || 'Failed to update settings.' });
    } finally {
      setSaving(false);
    }
  };

  const handleManualSync = async (mode?: 'SYNC' | 'BACKUP') => {
    setSyncing(true);
    setStatusMsg(null);
    try {
      const res = await BackupSyncService.triggerSync({
        destination: 'ALL',
        mode: mode || (formData.sync_mode as any) || 'SYNC',
      });
      if (res.success) {
        setStatusMsg({ type: 'success', text: res.message || 'Database synchronized successfully!' });
        loadData(false);
      } else {
        setStatusMsg({ type: 'error', text: res.message || 'Sync operation encountered errors.' });
      }
    } catch (err: any) {
      setStatusMsg({ type: 'error', text: err.message || 'Sync operation failed.' });
    } finally {
      setSyncing(false);
    }
  };

  const handleTestUsb = async () => {
    const path = formData.usb_path || '';
    if (!path) {
      setStatusMsg({ type: 'error', text: 'Please enter a USB directory path first.' });
      return;
    }
    setTestingUsb(true);
    setUsbTestResult(null);
    try {
      const res = await BackupSyncService.testUsb(path);
      setUsbTestResult(res);
    } catch (err: any) {
      setUsbTestResult({ success: false, message: err.message || 'Failed testing USB path.' });
    } finally {
      setTestingUsb(false);
    }
  };

  const handleTestGdrive = async () => {
    setTestingGdrive(true);
    setGdriveTestResult(null);
    try {
      const res = await BackupSyncService.testGoogleDrive(formData);
      setGdriveTestResult(res);
    } catch (err: any) {
      setGdriveTestResult({ success: false, message: err.message || 'Failed connecting to Google Drive.' });
    } finally {
      setTestingGdrive(false);
    }
  };

  const handleDownloadSnapshot = async () => {
    try {
      setStatusMsg({ type: 'info', text: 'Generating database image for download...' });
      await BackupSyncService.downloadSnapshot(true);
      setStatusMsg({ type: 'success', text: 'Database snapshot downloaded successfully.' });
    } catch (err: any) {
      setStatusMsg({ type: 'error', text: err.message || 'Download failed.' });
    }
  };

  const handlePasteServiceAccountJson = (jsonString: string) => {
    try {
      const parsed = JSON.parse(jsonString);
      if (parsed.client_email && parsed.private_key) {
        setFormData((prev) => ({
          ...prev,
          gdrive_client_email: parsed.client_email,
          gdrive_private_key: parsed.private_key,
        }));
        setStatusMsg({ type: 'success', text: 'Google Service Account credentials parsed successfully from JSON!' });
      } else {
        setStatusMsg({ type: 'error', text: 'Invalid Google Service Account JSON. Missing client_email or private_key.' });
      }
    } catch {
      setStatusMsg({ type: 'error', text: 'Could not parse JSON. Please ensure valid Service Account JSON format.' });
    }
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center p-12 text-slate-400">
        <RefreshCw className="w-6 h-6 animate-spin mr-2 text-blue-500" />
        <span>Loading Database Backup & Sync Engine...</span>
      </div>
    );
  }

  const isSyncMode = (formData.sync_mode || 'SYNC') === 'SYNC';

  return (
    <div className="space-y-6">
      {/* Alert Messages */}
      {statusMsg && (
        <div
          className={`p-4 rounded-2xl text-xs font-semibold flex items-center justify-between border ${
            statusMsg.type === 'success'
              ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400'
              : statusMsg.type === 'error'
              ? 'bg-rose-500/10 border-rose-500/30 text-rose-400'
              : 'bg-blue-500/10 border-blue-500/30 text-blue-400'
          }`}
        >
          <div className="flex items-center gap-2">
            {statusMsg.type === 'success' ? (
              <CheckCircle2 className="w-4 h-4 shrink-0" />
            ) : statusMsg.type === 'error' ? (
              <AlertTriangle className="w-4 h-4 shrink-0" />
            ) : (
              <RefreshCw className="w-4 h-4 shrink-0 animate-spin" />
            )}
            <span>{statusMsg.text}</span>
          </div>
          <button
            onClick={() => setStatusMsg(null)}
            className="text-slate-400 hover:text-white text-xs px-2 py-0.5"
          >
            ✕
          </button>
        </div>
      )}

      {/* Top Action Ribbon & Status Overview */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        {/* Destination 1 Card: Local USB */}
        <div className={`p-4 rounded-2xl border transition-all ${
          formData.usb_enabled
            ? settings?.last_usb_sync_status === 'SUCCESS'
              ? 'bg-slate-900 border-emerald-500/40'
              : 'bg-slate-900 border-blue-500/40'
            : 'bg-slate-900/50 border-slate-800 opacity-60'
        }`}>
          <div className="flex items-center justify-between mb-2">
            <div className="flex items-center gap-2">
              <div className="w-8 h-8 rounded-xl bg-blue-600/20 text-blue-400 flex items-center justify-center font-bold">
                <HardDrive className="w-4 h-4" />
              </div>
              <div>
                <span className="text-xs font-bold text-white block">Local External USB</span>
                <span className="text-[10px] text-slate-400">Destination 1</span>
              </div>
            </div>
            <span className={`text-[10px] font-bold px-2 py-0.5 rounded-full ${
              formData.usb_enabled
                ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30'
                : 'bg-slate-800 text-slate-400'
            }`}>
              {formData.usb_enabled ? 'Active' : 'Disabled'}
            </span>
          </div>
          <div className="space-y-1 text-[11px] text-slate-400 font-mono mt-3">
            <div className="truncate text-slate-300" title={formData.usb_path}>
              Path: {formData.usb_path || 'Not set'}
            </div>
            <div>
              Last Sync: {settings?.last_usb_sync_at ? new Date(settings.last_usb_sync_at).toLocaleString() : 'Never'}
            </div>
            {settings?.last_usb_sync_status && (
              <div className={`text-[10px] font-bold ${settings.last_usb_sync_status === 'SUCCESS' ? 'text-emerald-400' : 'text-rose-400'}`}>
                Status: {settings.last_usb_sync_status}
              </div>
            )}
          </div>
        </div>

        {/* Destination 2 Card: Google Drive */}
        <div className={`p-4 rounded-2xl border transition-all ${
          formData.gdrive_enabled
            ? settings?.last_gdrive_sync_status === 'SUCCESS'
              ? 'bg-slate-900 border-emerald-500/40'
              : 'bg-slate-900 border-indigo-500/40'
            : 'bg-slate-900/50 border-slate-800 opacity-60'
        }`}>
          <div className="flex items-center justify-between mb-2">
            <div className="flex items-center gap-2">
              <div className="w-8 h-8 rounded-xl bg-indigo-600/20 text-indigo-400 flex items-center justify-center font-bold">
                <Cloud className="w-4 h-4" />
              </div>
              <div>
                <span className="text-xs font-bold text-white block">Google Drive Cloud</span>
                <span className="text-[10px] text-slate-400">Destination 2</span>
              </div>
            </div>
            <span className={`text-[10px] font-bold px-2 py-0.5 rounded-full ${
              formData.gdrive_enabled
                ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30'
                : 'bg-slate-800 text-slate-400'
            }`}>
              {formData.gdrive_enabled ? 'Active' : 'Disabled'}
            </span>
          </div>
          <div className="space-y-1 text-[11px] text-slate-400 font-mono mt-3">
            <div className="truncate text-slate-300" title={formData.gdrive_folder_name}>
              Folder: {formData.gdrive_folder_name || 'OmniPOS_Cloud_Sync'}
            </div>
            <div>
              Last Sync: {settings?.last_gdrive_sync_at ? new Date(settings.last_gdrive_sync_at).toLocaleString() : 'Never'}
            </div>
            {settings?.last_gdrive_sync_status && (
              <div className={`text-[10px] font-bold ${settings.last_gdrive_sync_status === 'SUCCESS' ? 'text-emerald-400' : 'text-rose-400'}`}>
                Status: {settings.last_gdrive_sync_status}
              </div>
            )}
          </div>
        </div>

        {/* Sync Mode & Frequency Card */}
        <div className="p-4 rounded-2xl border border-slate-800 bg-slate-900 flex flex-col justify-between">
          <div>
            <div className="flex items-center justify-between mb-2">
              <div className="flex items-center gap-2">
                <div className="w-8 h-8 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center font-bold">
                  <Clock className="w-4 h-4" />
                </div>
                <div>
                  <span className="text-xs font-bold text-white block">Engine Schedule</span>
                  <span className="text-[10px] text-slate-400">Automation Mode</span>
                </div>
              </div>
              <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-blue-600/20 text-blue-400 border border-blue-500/30">
                {isSyncMode ? 'Mirror Sync' : 'Snapshots'}
              </span>
            </div>
            <div className="space-y-1 text-[11px] text-slate-400 mt-2">
              <div className="text-white font-semibold">
                Frequency: <span className="text-amber-400 font-mono">{formData.frequency}</span>
              </div>
              <div className="text-[10px] text-slate-400">
                {formData.frequency === 'ON_SHIFT_CLOSE' && 'Secures database instantly upon each Z-Report closing.'}
                {formData.frequency === 'HOURLY' && 'Background worker syncs every 60 minutes.'}
                {formData.frequency === 'DAILY' && `Syncs once daily at ${formData.daily_time || '23:00'}.`}
                {formData.frequency === 'MANUAL' && 'Manual execution via toolbar.'}
              </div>
            </div>
          </div>

          {/* Quick Sync Button */}
          <div className="mt-3 flex items-center gap-2">
            <button
              type="button"
              onClick={() => handleManualSync('SYNC')}
              disabled={syncing}
              className="flex-1 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-bold py-2 px-3 rounded-xl text-xs transition flex items-center justify-center gap-1.5 shadow-md shadow-blue-600/20 disabled:opacity-50"
            >
              <RefreshCw className={`w-3.5 h-3.5 ${syncing ? 'animate-spin' : ''}`} />
              <span>{syncing ? 'Syncing...' : 'Sync Now'}</span>
            </button>
            <button
              type="button"
              onClick={handleDownloadSnapshot}
              title="Download standalone SQLite database snapshot file"
              className="bg-slate-800 hover:bg-slate-700 text-slate-200 p-2 rounded-xl border border-slate-700 transition"
            >
              <Download className="w-3.5 h-3.5" />
            </button>
          </div>
        </div>
      </div>

      <form onSubmit={handleSaveSettings} className="space-y-6">
        {/* SECTION 1: Destination 1 - Local External USB */}
        <div className="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-5">
          <div className="flex items-center justify-between pb-3 border-b border-slate-800">
            <div className="flex items-center gap-3">
              <div className="w-9 h-9 rounded-xl bg-blue-600/20 text-blue-400 flex items-center justify-center">
                <HardDrive className="w-5 h-5" />
              </div>
              <div>
                <h3 className="text-sm font-bold text-white">Destination 1: Local External USB / Removable Storage</h3>
                <p className="text-[11px] text-slate-400">
                  Automatically maintains a clean mirror copy (<code className="text-blue-300">omnipos_synced.sqlite</code>) on connected USB storage.
                </p>
              </div>
            </div>
            <label className="relative inline-flex items-center cursor-pointer">
              <input
                type="checkbox"
                checked={!!formData.usb_enabled}
                onChange={(e) => setFormData({ ...formData, usb_enabled: e.target.checked ? 1 : 0 })}
                className="sr-only peer"
              />
              <div className="w-11 h-6 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
            </label>
          </div>

          {formData.usb_enabled && (
            <div className="space-y-4">
              {/* Auto-detected Drives Box */}
              {detectedDrives.length > 0 && (
                <div className="bg-slate-950/80 border border-slate-800 rounded-2xl p-4 space-y-2">
                  <span className="text-xs font-bold text-slate-300 flex items-center gap-1.5">
                    <Sparkles className="w-3.5 h-3.5 text-amber-400" />
                    Auto-Detected Mounted Drives on System:
                  </span>
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    {detectedDrives.map((d, idx) => (
                      <div
                        key={idx}
                        className="p-3 bg-slate-900 border border-slate-800 hover:border-blue-500/50 rounded-xl flex items-center justify-between text-xs transition"
                      >
                        <div className="truncate mr-2">
                          <span className="font-bold text-white block truncate">{d.name}</span>
                          <span className="text-[11px] text-slate-400 block font-mono truncate">{d.path}</span>
                          <span className="text-[10px] text-emerald-400 font-mono">Free: {d.free_human}</span>
                        </div>
                        <button
                          type="button"
                          onClick={() => setFormData({ ...formData, usb_path: d.suggested_backup_dir })}
                          className="bg-blue-600/20 hover:bg-blue-600 text-blue-300 hover:text-white px-2.5 py-1 rounded-lg text-[11px] font-semibold transition shrink-0"
                        >
                          Use Drive
                        </button>
                      </div>
                    ))}
                  </div>
                </div>
              )}

              {/* USB Path & Test Action */}
              <div>
                <label className="block text-xs font-semibold text-slate-300 mb-1">
                  Target USB Directory / Mount Point Path
                </label>
                <div className="flex items-center gap-2">
                  <div className="relative flex-1">
                    <Folder className="w-4 h-4 text-slate-500 absolute left-3 top-3" />
                    <input
                      type="text"
                      value={formData.usb_path || ''}
                      onChange={(e) => setFormData({ ...formData, usb_path: e.target.value })}
                      placeholder="/media/usb/omnipos_backups or D:\OmniPOS_Backups"
                      className="w-full bg-slate-950 border border-slate-700 rounded-xl pl-9 pr-3 py-2.5 text-xs text-white font-mono focus:outline-none focus:border-blue-500"
                    />
                  </div>
                  <button
                    type="button"
                    onClick={handleTestUsb}
                    disabled={testingUsb}
                    className="bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 px-4 py-2.5 rounded-xl text-xs font-bold transition flex items-center gap-1.5 shrink-0"
                  >
                    <RefreshCw className={`w-3.5 h-3.5 ${testingUsb ? 'animate-spin' : ''}`} />
                    <span>Test USB Access</span>
                  </button>
                </div>
                <span className="text-[11px] text-slate-500 mt-1 block">
                  OmniPOS creates an atomic, defragmented SQLite replica on this drive. Unplugging safely requires safe unmount or completed sync status.
                </span>
              </div>

              {/* USB Test Feedback */}
              {usbTestResult && (
                <div className={`p-3 rounded-xl text-xs flex items-center gap-2 border ${
                  usbTestResult.success
                    ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400'
                    : 'bg-rose-500/10 border-rose-500/30 text-rose-400'
                }`}>
                  {usbTestResult.success ? <CheckCircle2 className="w-4 h-4 shrink-0" /> : <AlertTriangle className="w-4 h-4 shrink-0" />}
                  <span>{usbTestResult.message} {usbTestResult.free_human && `(Available Space: ${usbTestResult.free_human})`}</span>
                </div>
              )}
            </div>
          )}
        </div>

        {/* SECTION 2: Destination 2 - Google Drive Cloud Sync */}
        <div className="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-5">
          <div className="flex items-center justify-between pb-3 border-b border-slate-800">
            <div className="flex items-center gap-3">
              <div className="w-9 h-9 rounded-xl bg-indigo-600/20 text-indigo-400 flex items-center justify-center">
                <Cloud className="w-5 h-5" />
              </div>
              <div>
                <h3 className="text-sm font-bold text-white">Destination 2: Google Drive Cloud Sync</h3>
                <p className="text-[11px] text-slate-400">
                  Off-site cloud replication. Updates the existing cloud replica file directly in your Google Drive folder.
                </p>
              </div>
            </div>
            <label className="relative inline-flex items-center cursor-pointer">
              <input
                type="checkbox"
                checked={!!formData.gdrive_enabled}
                onChange={(e) => setFormData({ ...formData, gdrive_enabled: e.target.checked ? 1 : 0 })}
                className="sr-only peer"
              />
              <div className="w-11 h-6 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
            </label>
          </div>

          {formData.gdrive_enabled && (
            <div className="space-y-4">
              {/* Auth Mode Tabs */}
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-2 p-1 bg-slate-950 rounded-2xl border border-slate-800">
                <button
                  type="button"
                  onClick={() => setFormData({ ...formData, gdrive_auth_type: 'SERVICE_ACCOUNT' })}
                  className={`py-2 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5 ${
                    formData.gdrive_auth_type === 'SERVICE_ACCOUNT'
                      ? 'bg-indigo-600 text-white shadow-md'
                      : 'text-slate-400 hover:text-white'
                  }`}
                >
                  <Key className="w-3.5 h-3.5" />
                  <span>Service Account JSON</span>
                </button>
                <button
                  type="button"
                  onClick={() => setFormData({ ...formData, gdrive_auth_type: 'OAUTH_TOKEN' })}
                  className={`py-2 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5 ${
                    formData.gdrive_auth_type === 'OAUTH_TOKEN'
                      ? 'bg-indigo-600 text-white shadow-md'
                      : 'text-slate-400 hover:text-white'
                  }`}
                >
                  <ShieldCheck className="w-3.5 h-3.5" />
                  <span>OAuth 2.0 Client</span>
                </button>
                <button
                  type="button"
                  onClick={() => setFormData({ ...formData, gdrive_auth_type: 'LOCAL_FOLDER' })}
                  className={`py-2 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5 ${
                    formData.gdrive_auth_type === 'LOCAL_FOLDER'
                      ? 'bg-indigo-600 text-white shadow-md'
                      : 'text-slate-400 hover:text-white'
                  }`}
                >
                  <Folder className="w-3.5 h-3.5" />
                  <span>Local Desktop GDrive</span>
                </button>
              </div>

              {/* Service Account Form */}
              {formData.gdrive_auth_type === 'SERVICE_ACCOUNT' && (
                <div className="space-y-4 bg-slate-950/60 p-4 rounded-2xl border border-slate-800/80">
                  <div className="flex items-center justify-between">
                    <span className="text-xs font-bold text-white flex items-center gap-1.5">
                      <Key className="w-3.5 h-3.5 text-indigo-400" />
                      Google Cloud Service Account Credentials
                    </span>
                    <label className="text-[11px] text-indigo-400 hover:underline cursor-pointer">
                      Import service-account.json
                      <input
                        type="file"
                        accept=".json"
                        className="hidden"
                        onChange={(e) => {
                          const file = e.target.files?.[0];
                          if (file) {
                            const reader = new FileReader();
                            reader.onload = (event) => {
                              handlePasteServiceAccountJson(event.target?.result as string);
                            };
                            reader.readAsText(file);
                          }
                        }}
                      />
                    </label>
                  </div>

                  <div>
                    <label className="block text-[11px] font-semibold text-slate-400 mb-1">
                      Service Account Email (client_email)
                    </label>
                    <input
                      type="email"
                      value={formData.gdrive_client_email || ''}
                      onChange={(e) => setFormData({ ...formData, gdrive_client_email: e.target.value })}
                      placeholder="omnipos-backup@my-pos-project.iam.gserviceaccount.com"
                      className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white font-mono focus:outline-none focus:border-indigo-500"
                    />
                  </div>

                  <div>
                    <label className="block text-[11px] font-semibold text-slate-400 mb-1">
                      RSA Private Key (private_key)
                    </label>
                    <textarea
                      rows={3}
                      value={formData.gdrive_private_key || ''}
                      onChange={(e) => setFormData({ ...formData, gdrive_private_key: e.target.value })}
                      placeholder="-----BEGIN RSA PRIVATE KEY-----&#10;...&#10;-----END RSA PRIVATE KEY-----"
                      className="w-full bg-slate-950 border border-slate-700 rounded-xl p-3 text-xs text-white font-mono focus:outline-none focus:border-indigo-500"
                    />
                    <span className="text-[10px] text-slate-500">
                      {formData.has_gdrive_private_key
                        ? '✓ Private key securely saved on server.'
                        : 'Paste RSA private key or import your service account JSON above.'}
                    </span>
                  </div>
                </div>
              )}

              {/* OAuth 2.0 Form */}
              {formData.gdrive_auth_type === 'OAUTH_TOKEN' && (
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-slate-950/60 p-4 rounded-2xl border border-slate-800/80">
                  <div className="sm:col-span-2">
                    <label className="block text-[11px] font-semibold text-slate-400 mb-1">OAuth Refresh Token</label>
                    <input
                      type="text"
                      value={formData.gdrive_refresh_token || ''}
                      onChange={(e) => setFormData({ ...formData, gdrive_refresh_token: e.target.value })}
                      placeholder="1//04xxx...-xxx"
                      className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white font-mono focus:outline-none focus:border-indigo-500"
                    />
                  </div>
                  <div>
                    <label className="block text-[11px] font-semibold text-slate-400 mb-1">Client ID</label>
                    <input
                      type="text"
                      value={formData.gdrive_client_id || ''}
                      onChange={(e) => setFormData({ ...formData, gdrive_client_id: e.target.value })}
                      className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white font-mono focus:outline-none"
                    />
                  </div>
                  <div>
                    <label className="block text-[11px] font-semibold text-slate-400 mb-1">Client Secret</label>
                    <input
                      type="password"
                      value={formData.gdrive_client_secret || ''}
                      onChange={(e) => setFormData({ ...formData, gdrive_client_secret: e.target.value })}
                      className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white font-mono focus:outline-none"
                    />
                  </div>
                </div>
              )}

              {/* Local GDrive Folder */}
              {formData.gdrive_auth_type === 'LOCAL_FOLDER' && (
                <div className="bg-slate-950/60 p-4 rounded-2xl border border-slate-800/80">
                  <label className="block text-[11px] font-semibold text-slate-400 mb-1">
                    Google Drive Desktop Folder Path
                  </label>
                  <input
                    type="text"
                    value={formData.gdrive_local_path || ''}
                    onChange={(e) => setFormData({ ...formData, gdrive_local_path: e.target.value })}
                    placeholder="G:\My Drive\OmniPOS or /home/user/GoogleDrive/Backups"
                    className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white font-mono focus:outline-none focus:border-indigo-500"
                  />
                  <span className="text-[10px] text-slate-500 mt-1 block">
                    Use this if you have the official Google Drive for Desktop application installed on this POS terminal.
                  </span>
                </div>
              )}

              {/* Target Cloud Folder & Connection Test */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-semibold text-slate-300 mb-1">
                    Google Drive Cloud Folder Name
                  </label>
                  <input
                    type="text"
                    value={formData.gdrive_folder_name || 'OmniPOS_Cloud_Sync'}
                    onChange={(e) => setFormData({ ...formData, gdrive_folder_name: e.target.value })}
                    className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2.5 text-xs text-white font-bold focus:outline-none focus:border-indigo-500"
                  />
                  <span className="text-[10px] text-slate-500 mt-0.5 block">
                    Folder is auto-created in Google Drive if it does not exist yet.
                  </span>
                </div>

                <div className="flex flex-col justify-end">
                  <button
                    type="button"
                    onClick={handleTestGdrive}
                    disabled={testingGdrive}
                    className="bg-indigo-600/20 hover:bg-indigo-600/30 text-indigo-300 border border-indigo-500/40 px-4 py-2.5 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2"
                  >
                    <RefreshCw className={`w-3.5 h-3.5 ${testingGdrive ? 'animate-spin' : ''}`} />
                    <span>Test Google Drive Connection</span>
                  </button>
                </div>
              </div>

              {/* GDrive Test Feedback */}
              {gdriveTestResult && (
                <div className={`p-3 rounded-xl text-xs flex items-center gap-2 border ${
                  gdriveTestResult.success
                    ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400'
                    : 'bg-rose-500/10 border-rose-500/30 text-rose-400'
                }`}>
                  {gdriveTestResult.success ? <CheckCircle2 className="w-4 h-4 shrink-0" /> : <AlertTriangle className="w-4 h-4 shrink-0" />}
                  <span>{gdriveTestResult.message} {gdriveTestResult.storage_used && `(Quota Used: ${gdriveTestResult.storage_used})`}</span>
                </div>
              )}
            </div>
          )}
        </div>

        {/* SECTION 3: Mode & Frequency Automation */}
        <div className="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-5">
          <div className="flex items-center gap-3 pb-3 border-b border-slate-800">
            <div className="w-9 h-9 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center">
              <Layers className="w-5 h-5" />
            </div>
            <div>
              <h3 className="text-sm font-bold text-white">Sync vs. Backup Mode & Frequency Schedule</h3>
              <p className="text-[11px] text-slate-400">
                Choose between single live mirror synchronization (replaces replica without clutter) and timestamped archives.
              </p>
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            {/* Mode Selection */}
            <div>
              <label className="block text-xs font-semibold text-slate-300 mb-2">Replication Strategy</label>
              <div className="space-y-2">
                <label className={`p-3 rounded-2xl border flex items-start gap-3 cursor-pointer transition ${
                  formData.sync_mode === 'SYNC'
                    ? 'bg-blue-600/10 border-blue-500/50 text-white'
                    : 'bg-slate-950 border-slate-800 text-slate-400 hover:border-slate-700'
                }`}>
                  <input
                    type="radio"
                    name="sync_mode"
                    value="SYNC"
                    checked={formData.sync_mode === 'SYNC'}
                    onChange={() => setFormData({ ...formData, sync_mode: 'SYNC' })}
                    className="mt-1"
                  />
                  <div>
                    <span className="text-xs font-bold block text-white">Live Mirror Sync (Recommended)</span>
                    <span className="text-[11px] text-slate-400 block leading-relaxed">
                      Maintains exactly 1 up-to-date replica file (<code className="text-blue-300">omnipos_synced.sqlite</code>) on both USB and Google Drive. No disk clutter or endless archives.
                    </span>
                  </div>
                </label>

                <label className={`p-3 rounded-2xl border flex items-start gap-3 cursor-pointer transition ${
                  formData.sync_mode === 'BACKUP'
                    ? 'bg-blue-600/10 border-blue-500/50 text-white'
                    : 'bg-slate-950 border-slate-800 text-slate-400 hover:border-slate-700'
                }`}>
                  <input
                    type="radio"
                    name="sync_mode"
                    value="BACKUP"
                    checked={formData.sync_mode === 'BACKUP'}
                    onChange={() => setFormData({ ...formData, sync_mode: 'BACKUP' })}
                    className="mt-1"
                  />
                  <div>
                    <span className="text-xs font-bold block text-white">Versioned Snapshots (Point-in-Time)</span>
                    <span className="text-[11px] text-slate-400 block leading-relaxed">
                      Creates timestamped gzip files (<code className="text-blue-300">omnipos_backup_YYYYMMDD.sqlite.gz</code>) with rolling retention pruning.
                    </span>
                  </div>
                </label>
              </div>
            </div>

            {/* Frequency Selection */}
            <div>
              <label className="block text-xs font-semibold text-slate-300 mb-2">Automated Sync Frequency</label>
              <select
                value={formData.frequency || 'ON_SHIFT_CLOSE'}
                onChange={(e) => setFormData({ ...formData, frequency: e.target.value as BackupFrequency })}
                className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2.5 text-xs text-white font-bold focus:outline-none focus:border-blue-500 mb-3"
              >
                <option value="ON_SHIFT_CLOSE">On Every Shift Close / Z-Report (Instant Retail Security)</option>
                <option value="HOURLY">Hourly (Every 60 minutes)</option>
                <option value="EVERY_15_MIN">Every 15 Minutes (High-Frequency Sync)</option>
                <option value="EVERY_30_MIN">Every 30 Minutes</option>
                <option value="EVERY_6_HOURS">Every 6 Hours</option>
                <option value="DAILY">Daily at Specified Time</option>
                <option value="MANUAL">Manual Only (No automated triggers)</option>
              </select>

              {formData.frequency === 'DAILY' && (
                <div className="mb-3">
                  <label className="block text-[11px] font-semibold text-slate-400 mb-1">Daily Sync Time</label>
                  <input
                    type="time"
                    value={formData.daily_time || '23:00'}
                    onChange={(e) => setFormData({ ...formData, daily_time: e.target.value })}
                    className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white font-mono font-bold focus:outline-none"
                  />
                </div>
              )}

              {formData.sync_mode === 'BACKUP' && (
                <div className="mb-3">
                  <label className="block text-[11px] font-semibold text-slate-400 mb-1">
                    Retention: Max Backups to Keep
                  </label>
                  <input
                    type="number"
                    min={1}
                    max={100}
                    value={formData.keep_snapshots_count || 7}
                    onChange={(e) => setFormData({ ...formData, keep_snapshots_count: parseInt(e.target.value, 10) || 7 })}
                    className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white font-mono font-bold focus:outline-none"
                  />
                </div>
              )}

              {/* Cron command helper */}
              <div className="bg-slate-950 border border-slate-800 rounded-xl p-3 text-[11px]">
                <div className="text-slate-400 font-semibold mb-1 flex items-center gap-1">
                  <Terminal className="w-3.5 h-3.5 text-emerald-400" />
                  <span>cPanel / Linux Crontab Command</span>
                </div>
                <code className="text-[10px] text-emerald-400 block bg-slate-900 p-1.5 rounded select-all font-mono break-all">
                  * * * * * php /path/to/omnipos-pro/api/cron/backup-sync.php
                </code>
              </div>
            </div>
          </div>
        </div>

        {/* Save Bar */}
        <div className="flex items-center justify-between pt-2">
          <div className="text-[11px] text-slate-400">
            Database Driver: <span className="text-white font-mono font-bold">SQLite / Atomic Zero-Lock</span>
          </div>
          <button
            type="submit"
            disabled={saving}
            className="bg-blue-600 hover:bg-blue-500 text-white font-bold px-8 py-3 rounded-2xl text-xs transition flex items-center gap-2 shadow-lg shadow-blue-600/30 disabled:opacity-50"
          >
            <Save className="w-4 h-4" />
            <span>{saving ? 'Saving...' : 'Save Configuration'}</span>
          </button>
        </div>
      </form>

      {/* SECTION 4: Activity History Log Table */}
      <div className="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
        <div className="flex items-center justify-between pb-3 border-b border-slate-800">
          <div className="flex items-center gap-2">
            <FileText className="w-4 h-4 text-purple-400" />
            <h3 className="text-sm font-bold text-white">Recent Sync & Backup Activity History</h3>
          </div>
          <button
            type="button"
            onClick={() => loadData(false)}
            className="text-xs text-blue-400 hover:text-blue-300 font-semibold flex items-center gap-1"
          >
            <RefreshCw className="w-3 h-3" />
            <span>Refresh Logs</span>
          </button>
        </div>

        {logs.length === 0 ? (
          <div className="text-center py-6 text-slate-500 text-xs">
            No backup operations recorded yet. Click "Sync Now" to run your first database backup.
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs">
              <thead>
                <tr className="border-b border-slate-800 text-slate-400 text-[11px]">
                  <th className="pb-2">Date / Time</th>
                  <th className="pb-2">Trigger</th>
                  <th className="pb-2">Mode</th>
                  <th className="pb-2">Destination</th>
                  <th className="pb-2">Status</th>
                  <th className="pb-2">Size</th>
                  <th className="pb-2">Duration</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-800/60 font-mono">
                {logs.map((log) => (
                  <tr key={log.id} className="text-slate-300 hover:bg-slate-800/30">
                    <td className="py-2.5 whitespace-nowrap text-slate-400">
                      {new Date(log.created_at).toLocaleString()}
                    </td>
                    <td className="py-2.5">
                      <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-800 text-slate-300">
                        {log.trigger_type}
                      </span>
                    </td>
                    <td className="py-2.5">
                      <span className={`text-[10px] font-bold px-2 py-0.5 rounded-full ${
                        log.action_type === 'SYNC' ? 'bg-blue-600/20 text-blue-400' : 'bg-purple-600/20 text-purple-400'
                      }`}>
                        {log.action_type}
                      </span>
                    </td>
                    <td className="py-2.5 text-slate-300">
                      {log.destination}
                    </td>
                    <td className="py-2.5">
                      <span className={`text-[10px] font-bold px-2 py-0.5 rounded-full ${
                        log.status === 'SUCCESS'
                          ? 'bg-emerald-500/20 text-emerald-400'
                          : log.status === 'PARTIAL'
                          ? 'bg-amber-500/20 text-amber-400'
                          : 'bg-rose-500/20 text-rose-400'
                      }`}>
                        {log.status}
                      </span>
                    </td>
                    <td className="py-2.5 text-slate-300">{log.file_size_human}</td>
                    <td className="py-2.5 text-slate-400">{log.duration_ms} ms</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  );
};
