import React, { useState, useEffect } from 'react';
import { User, CompanySettings } from '../../types';
import { ApiService } from '../../services/api';
import { I18nService } from '../../services/i18nService';
import {
  Lock,
  KeyRound,
  UserCheck,
  Delete,
  Store,
  ShieldCheck,
  ChevronRight
} from 'lucide-react';

interface Props {
  settings: CompanySettings | null;
  onLoginSuccess: (user: User, token: string) => void;
}

export const LoginView: React.FC<Props> = ({ settings, onLoginSuccess }) => {
  const [authMode, setAuthMode] = useState<'PIN' | 'PASSWORD'>('PIN');
  const [users, setUsers] = useState<User[]>([]);
  const [selectedUser, setSelectedUser] = useState<User | null>(null);

  // PIN mode state
  const [pin, setPin] = useState('');

  // Password mode state
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');

  const [loading, setLoading] = useState(false);
  const [errorMsg, setErrorMsg] = useState<string | null>(null);

  useEffect(() => {
    // Fetch active users list for quick PIN login
    ApiService.request('auth.usersList').then((res) => {
      if (res.success && res.data?.users) {
        setUsers(res.data.users);
        if (res.data.users.length > 0) {
          setSelectedUser(res.data.users[0]);
        }
      }
    });
  }, []);

  const handleDigit = (digit: string) => {
    if (pin.length < 6) {
      setPin((prev) => prev + digit);
      setErrorMsg(null);
    }
  };

  const handlePinSubmit = async (e?: React.FormEvent) => {
    if (e) e.preventDefault();
    if (!pin) return;

    setLoading(true);
    setErrorMsg(null);

    const res = await ApiService.request('auth.pinLogin', {
      user_id: selectedUser?.id,
      pin,
    });
    setLoading(false);

    if (res.success && res.data) {
      ApiService.setAuthToken(res.data.token);
      onLoginSuccess(res.data.user, res.data.token);
    } else {
      setErrorMsg(res.error?.message || 'Invalid PIN code.');
      setPin('');
    }
  };

  const handlePasswordSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setErrorMsg(null);

    const res = await ApiService.request('auth.login', { username, password });
    setLoading(false);

    if (res.success && res.data) {
      ApiService.setAuthToken(res.data.token);
      onLoginSuccess(res.data.user, res.data.token);
    } else {
      setErrorMsg(res.error?.message || 'Invalid credentials.');
    }
  };

  return (
    <div className="min-h-screen bg-slate-950 flex flex-col items-center justify-center p-4 font-sans select-none">
      <div className="bg-slate-900 border border-slate-800 rounded-3xl w-full max-w-md p-8 shadow-2xl relative">
        {/* Company Header */}
        <div className="text-center mb-6">
          {settings?.primary_logo_url ? (
            <img
              src={settings.primary_logo_url}
              alt="Logo"
              className="w-14 h-14 object-contain rounded-2xl mx-auto mb-3 bg-slate-850 p-2 border border-slate-700 shadow-lg"
            />
          ) : (
            <div className="w-14 h-14 bg-gradient-to-br from-blue-600 to-indigo-700 rounded-2xl mx-auto flex items-center justify-center text-white text-2xl font-black mb-3 shadow-xl shadow-blue-600/30">
              OP
            </div>
          )}

          <h1 className="text-xl font-black text-white tracking-tight">
            {settings?.company_name || 'OmniPOS Pro'}
          </h1>
          <p className="text-xs text-slate-400 mt-0.5">Commercial Cashier & Terminal Gateway</p>
        </div>

        {/* Auth Mode Toggle */}
        <div className="flex bg-slate-950 p-1 rounded-2xl mb-6 border border-slate-800">
          <button
            onClick={() => { setAuthMode('PIN'); setErrorMsg(null); }}
            className={`flex-1 py-2 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5 ${
              authMode === 'PIN' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/30' : 'text-slate-400 hover:text-white'
            }`}
          >
            <KeyRound className="w-3.5 h-3.5" />
            <span>Staff PIN Pad</span>
          </button>
          <button
            onClick={() => { setAuthMode('PASSWORD'); setErrorMsg(null); }}
            className={`flex-1 py-2 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5 ${
              authMode === 'PASSWORD' ? 'bg-blue-600 text-white shadow-md shadow-blue-600/30' : 'text-slate-400 hover:text-white'
            }`}
          >
            <Lock className="w-3.5 h-3.5" />
            <span>Admin Password</span>
          </button>
        </div>

        {errorMsg && (
          <div className="bg-red-500/10 border border-red-500/30 text-red-400 px-3 py-2 rounded-xl text-xs mb-4 text-center">
            {errorMsg}
          </div>
        )}

        {/* 1. PIN PAD MODE */}
        {authMode === 'PIN' ? (
          <div>
            {/* Quick staff avatar pills */}
            <div className="flex items-center gap-2 overflow-x-auto pb-3 mb-4 no-scrollbar">
              {users.map((u) => {
                const isSelected = selectedUser?.id === u.id;
                return (
                  <button
                    key={u.id}
                    onClick={() => { setSelectedUser(u); setPin(''); setErrorMsg(null); }}
                    className={`px-3 py-1.5 rounded-xl text-xs font-bold transition flex items-center gap-2 shrink-0 border ${
                      isSelected
                        ? 'bg-blue-600/20 text-blue-300 border-blue-500'
                        : 'bg-slate-950 text-slate-400 border-slate-800 hover:border-slate-700'
                    }`}
                  >
                    <span className="w-5 h-5 rounded-full bg-slate-800 flex items-center justify-center text-[10px]">
                      {u.full_name[0]}
                    </span>
                    <span>{u.full_name}</span>
                  </button>
                );
              })}
            </div>

            {/* PIN Dots */}
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

            {/* Digits Keypad */}
            <div className="grid grid-cols-3 gap-2.5 mb-4">
              {['1', '2', '3', '4', '5', '6', '7', '8', '9'].map((d) => (
                <button
                  key={d}
                  onClick={() => handleDigit(d)}
                  className="bg-slate-800/80 hover:bg-slate-750 active:scale-95 text-white text-lg font-bold py-3.5 rounded-2xl border border-slate-700/60 transition shadow-sm"
                >
                  {d}
                </button>
              ))}
              <button
                onClick={() => setPin('')}
                className="bg-slate-800/40 hover:bg-slate-800 text-slate-400 hover:text-white text-xs font-bold py-3.5 rounded-2xl border border-slate-800 transition"
              >
                Clear
              </button>
              <button
                onClick={() => handleDigit('0')}
                className="bg-slate-800/80 hover:bg-slate-750 active:scale-95 text-white text-lg font-bold py-3.5 rounded-2xl border border-slate-700/60 transition shadow-sm"
              >
                0
              </button>
              <button
                onClick={() => setPin((prev) => prev.slice(0, -1))}
                className="bg-slate-800/40 hover:bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center py-3.5 rounded-2xl border border-slate-800 transition"
              >
                <Delete className="w-5 h-5" />
              </button>
            </div>

            <button
              onClick={handlePinSubmit}
              disabled={loading || pin.length === 0}
              className="w-full bg-blue-600 hover:bg-blue-500 disabled:opacity-50 text-white font-bold py-3.5 rounded-2xl shadow-lg shadow-blue-600/30 transition text-sm flex items-center justify-center gap-2"
            >
              {loading ? 'Authenticating...' : 'Sign In with PIN'}
            </button>
          </div>
        ) : (
          /* 2. USERNAME & PASSWORD MODE */
          <form onSubmit={handlePasswordSubmit} className="space-y-4">
            <div>
              <label className="block text-xs font-semibold text-slate-400 mb-1">Username</label>
              <input
                type="text"
                required
                value={username}
                onChange={(e) => setUsername(e.target.value)}
                placeholder="admin"
                className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2.5 text-xs text-white focus:outline-none focus:border-blue-500"
              />
            </div>
            <div>
              <label className="block text-xs font-semibold text-slate-400 mb-1">Password</label>
              <input
                type="password"
                required
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder="admin123"
                className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2.5 text-xs text-white focus:outline-none focus:border-blue-500"
              />
            </div>
            <button
              type="submit"
              disabled={loading}
              className="w-full bg-blue-600 hover:bg-blue-500 disabled:opacity-50 text-white font-bold py-3.5 rounded-2xl shadow-lg shadow-blue-600/30 transition text-sm"
            >
              {loading ? 'Authenticating...' : 'Sign In'}
            </button>
          </form>
        )}

        {/* Footer info */}
        <div className="mt-6 text-center text-[10px] text-slate-500 font-mono">
          OmniPOS Pro 2.0 • Ed25519 Verified • 100% POST Protocol
        </div>
      </div>
    </div>
  );
};
