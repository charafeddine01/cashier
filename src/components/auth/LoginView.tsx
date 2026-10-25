import React, { useState, useEffect, useRef, useCallback } from 'react';
import { User, CompanySettings } from '../../types';
import { ApiService } from '../../services/api';
import {
  Lock,
  KeyRound,
  Delete,
  Store,
  ShieldCheck,
  User as UserIcon,
  Keyboard,
  ArrowRight,
  Eye,
  EyeOff,
  Sparkles,
  CheckCircle2,
  AlertCircle
} from 'lucide-react';

interface Props {
  settings: CompanySettings | null;
  onLoginSuccess: (user: User, token: string) => void;
}

export const LoginView: React.FC<Props> = ({ settings, onLoginSuccess }) => {
  const [authMode, setAuthMode] = useState<'PIN' | 'PASSWORD'>('PIN');
  const [users, setUsers] = useState<User[]>([]);
  const [selectedUser, setSelectedUser] = useState<User | null>(null);

  // PIN state
  const [pin, setPin] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [errorMsg, setErrorMsg] = useState<string | null>(null);
  const [activeKey, setActiveKey] = useState<string | null>(null);

  // Password state
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);

  // Invisible input ref to maintain keyboard focus and support virtual numpads & barcode scanners
  const pinInputRef = useRef<HTMLInputElement>(null);

  // Fetch active users for quick selection
  useEffect(() => {
    ApiService.request('auth.usersList').then((res) => {
      if (res.success && res.data?.users) {
        setUsers(res.data.users);
      }
    });
  }, []);

  // Ensure focus is always on the input in PIN mode
  useEffect(() => {
    if (authMode === 'PIN') {
      pinInputRef.current?.focus();
    }
  }, [authMode, pin]);

  // Execute PIN authentication
  const executePinLogin = useCallback(async (pinToSubmit: string, userToSubmit: User | null = selectedUser) => {
    if (!pinToSubmit || isSubmitting) return;

    setIsSubmitting(true);
    setErrorMsg(null);

    try {
      const payload: Record<string, any> = { pin: pinToSubmit };
      if (userToSubmit) {
        payload.user_id = userToSubmit.id;
      }

      const res = await ApiService.request('auth.pinLogin', payload);

      if (res.success && res.data) {
        ApiService.setAuthToken(res.data.token);
        onLoginSuccess(res.data.user, res.data.token);
      } else {
        setErrorMsg(res.error?.message || 'Invalid PIN code. Please try again.');
        setPin('');
        pinInputRef.current?.focus();
      }
    } catch (err: any) {
      setErrorMsg(err.message || 'Connection error during authentication.');
      setPin('');
    } finally {
      setIsSubmitting(false);
    }
  }, [selectedUser, isSubmitting, onLoginSuccess]);

  // Handle digit entry
  const handleDigit = useCallback((digit: string) => {
    if (isSubmitting) return;

    setErrorMsg(null);
    setPin((prev) => {
      if (prev.length >= 6) return prev;
      const nextPin = prev + digit;

      // Auto-submit when standard 4-digit PIN length is reached
      if (nextPin.length === 4) {
        // Trigger login with nextPin
        setTimeout(() => executePinLogin(nextPin), 60);
      }
      return nextPin;
    });

    // Visual button press flash
    setActiveKey(digit);
    setTimeout(() => setActiveKey(null), 150);
  }, [isSubmitting, executePinLogin]);

  // Handle backspace / clear
  const handleBackspace = useCallback(() => {
    setErrorMsg(null);
    setPin((prev) => prev.slice(0, -1));
  }, []);

  const handleClear = useCallback(() => {
    setErrorMsg(null);
    setPin('');
    pinInputRef.current?.focus();
  }, []);

  // Global Keyboard Event Listener: captures keyboard numbers, numpad, backspace, enter, escape
  useEffect(() => {
    if (authMode !== 'PIN') return;

    const handleKeyDown = (e: KeyboardEvent) => {
      // Ignore if focus is in a text input (e.g. search, though none on login screen)
      if (e.target instanceof HTMLInputElement && e.target !== pinInputRef.current) {
        return;
      }

      const key = e.key;

      // Digits 0-9 (Standard numbers & Numpad)
      if (/^[0-9]$/.test(key)) {
        e.preventDefault();
        handleDigit(key);
      } else if (key === 'Backspace') {
        e.preventDefault();
        handleBackspace();
      } else if (key === 'Escape' || key.toLowerCase() === 'c') {
        e.preventDefault();
        handleClear();
      } else if (key === 'Enter') {
        e.preventDefault();
        if (pin.length > 0) {
          executePinLogin(pin);
        }
      }
    };

    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [authMode, pin, handleDigit, handleBackspace, handleClear, executePinLogin]);

  // Password submission
  const handlePasswordSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (isSubmitting) return;

    setIsSubmitting(true);
    setErrorMsg(null);

    try {
      const res = await ApiService.request('auth.login', { username, password });
      if (res.success && res.data) {
        ApiService.setAuthToken(res.data.token);
        onLoginSuccess(res.data.user, res.data.token);
      } else {
        setErrorMsg(res.error?.message || 'Invalid username or password.');
      }
    } catch (err: any) {
      setErrorMsg(err.message || 'Authentication failed. Please check connection.');
    } finally {
      setIsSubmitting(false);
    }
  };

  // Quick 1-click test fill helper
  const handleQuickLogin = (quickPin: string, userObj?: User) => {
    if (userObj) setSelectedUser(userObj);
    setPin(quickPin);
    executePinLogin(quickPin, userObj || null);
  };

  return (
    <div className="min-h-screen bg-slate-950 flex flex-col items-center justify-center p-4 font-sans select-none text-slate-100 relative overflow-hidden">
      {/* Decorative ambient background glows */}
      <div className="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none" />
      <div className="absolute bottom-1/4 left-1/3 -translate-x-1/2 w-80 h-80 bg-teal-500/10 rounded-full blur-3xl pointer-events-none" />

      {/* Main Login Card */}
      <div className="bg-slate-900/95 border border-slate-800 rounded-3xl w-full max-w-sm p-6 sm:p-7 shadow-2xl backdrop-blur-xl relative z-10 transition-all">
        {/* Hidden input to automatically capture physical keyboard and show mobile numeric pad */}
        <input
          ref={pinInputRef}
          type="password"
          inputMode="numeric"
          pattern="[0-9]*"
          autoFocus
          value={pin}
          onChange={(e) => {
            const val = e.target.value.replace(/\D/g, '').slice(0, 6);
            setPin(val);
            if (val.length === 4) {
              setTimeout(() => executePinLogin(val), 60);
            }
          }}
          className="absolute -top-96 opacity-0 pointer-events-none"
          tabIndex={-1}
          aria-hidden="true"
        />

        {/* Brand Header */}
        <div className="text-center mb-5">
          {settings?.primary_logo_url ? (
            <img
              src={settings.primary_logo_url}
              alt="Logo"
              className="w-12 h-12 object-contain rounded-2xl mx-auto mb-2.5 bg-slate-850 p-2 border border-slate-700/60 shadow-lg"
            />
          ) : (
            <div className="w-12 h-12 bg-gradient-to-br from-emerald-500 to-teal-600 rounded-2xl mx-auto flex items-center justify-center text-white text-xl font-black mb-2.5 shadow-lg shadow-emerald-500/25">
              <Store className="w-6 h-6 text-white" />
            </div>
          )}

          <h1 className="text-xl font-black text-white tracking-tight">
            {settings?.company_name || 'Vendra POS'}
          </h1>
          <p className="text-xs text-slate-400 mt-0.5">
            {authMode === 'PIN' ? 'Enter Cashier PIN or use keyboard' : 'Administrator Sign In'}
          </p>
        </div>

        {/* Error Alert */}
        {errorMsg && (
          <div className="bg-rose-500/10 border border-rose-500/30 text-rose-300 px-3 py-2 rounded-xl text-xs mb-4 text-center flex items-center justify-center gap-1.5 animate-shake">
            <AlertCircle className="w-4 h-4 shrink-0 text-rose-400" />
            <span>{errorMsg}</span>
          </div>
        )}

        {/* MODE 1: CASHIER PIN PAD */}
        {authMode === 'PIN' ? (
          <div>
            {/* Quick Cashier Selector: Auto-Detect or Pick Staff */}
            <div className="mb-4">
              <div className="flex items-center justify-between text-[11px] text-slate-400 mb-1.5 px-1">
                <span>Select Cashier</span>
                <span className="text-emerald-400 font-medium">
                  {selectedUser ? selectedUser.full_name : 'Auto-Detect Staff'}
                </span>
              </div>
              <div className="grid grid-cols-3 gap-1.5 bg-slate-950/70 p-1.5 rounded-2xl border border-slate-800">
                <button
                  type="button"
                  onClick={() => { setSelectedUser(null); handleClear(); }}
                  className={`py-1.5 px-2 rounded-xl text-[11px] font-bold transition flex items-center justify-center gap-1 border ${
                    selectedUser === null
                      ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/50 shadow-sm'
                      : 'text-slate-400 border-transparent hover:text-slate-200 hover:bg-slate-850'
                  }`}
                >
                  <Sparkles className="w-3 h-3 text-emerald-400 shrink-0" />
                  <span className="truncate">Auto-Detect</span>
                </button>
                {users.slice(0, 2).map((u) => {
                  const isSelected = selectedUser?.id === u.id;
                  return (
                    <button
                      key={u.id}
                      type="button"
                      onClick={() => { setSelectedUser(u); handleClear(); }}
                      className={`py-1.5 px-2 rounded-xl text-[11px] font-bold transition flex items-center justify-center gap-1 border ${
                        isSelected
                          ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/50 shadow-sm'
                          : 'text-slate-400 border-transparent hover:text-slate-200 hover:bg-slate-850'
                      }`}
                    >
                      <span className="truncate">{u.full_name.split(' ')[0]}</span>
                    </button>
                  );
                })}
              </div>
            </div>

            {/* PIN Indicator Dots */}
            <div className="bg-slate-950/90 border border-slate-800 rounded-2xl p-3.5 mb-4 flex flex-col items-center justify-center shadow-inner">
              <div className="flex justify-center items-center gap-3.5">
                {[0, 1, 2, 3].map((index) => {
                  const isFilled = pin.length > index;
                  return (
                    <div
                      key={index}
                      className={`w-4 h-4 rounded-full transition-all duration-200 flex items-center justify-center ${
                        isFilled
                          ? 'bg-emerald-400 scale-125 shadow-lg shadow-emerald-400/50 ring-2 ring-emerald-400/30'
                          : 'bg-slate-800 border border-slate-700/80'
                      }`}
                    />
                  );
                })}
              </div>

              {/* Keyboard indicator tag */}
              <div className="flex items-center gap-1.5 mt-2.5 text-[10px] text-slate-400">
                <span className="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse" />
                <Keyboard className="w-3 h-3 text-emerald-400" />
                <span>Physical keyboard & numpad active</span>
              </div>
            </div>

            {/* Tactile Keypad */}
            <div className="grid grid-cols-3 gap-2 mb-4">
              {['1', '2', '3', '4', '5', '6', '7', '8', '9'].map((d) => {
                const isPressed = activeKey === d;
                return (
                  <button
                    key={d}
                    type="button"
                    onClick={() => handleDigit(d)}
                    className={`text-white text-xl font-bold py-3.5 rounded-2xl border transition-all duration-150 select-none ${
                      isPressed
                        ? 'bg-emerald-500 text-slate-950 border-emerald-400 scale-95 shadow-md shadow-emerald-500/40'
                        : 'bg-slate-800/80 hover:bg-slate-750 hover:border-slate-600 active:scale-95 border-slate-700/70 shadow-sm'
                    }`}
                  >
                    {d}
                  </button>
                );
              })}

              {/* Clear button */}
              <button
                type="button"
                onClick={handleClear}
                className="bg-slate-800/40 hover:bg-slate-800 active:scale-95 text-slate-400 hover:text-white text-xs font-bold py-3.5 rounded-2xl border border-slate-800 transition select-none flex items-center justify-center"
              >
                Clear
              </button>

              {/* 0 button */}
              <button
                type="button"
                onClick={() => handleDigit('0')}
                className={`text-white text-xl font-bold py-3.5 rounded-2xl border transition-all duration-150 select-none ${
                  activeKey === '0'
                    ? 'bg-emerald-500 text-slate-950 border-emerald-400 scale-95 shadow-md shadow-emerald-500/40'
                    : 'bg-slate-800/80 hover:bg-slate-750 hover:border-slate-600 active:scale-95 border-slate-700/70 shadow-sm'
                }`}
              >
                0
              </button>

              {/* Backspace button */}
              <button
                type="button"
                onClick={handleBackspace}
                className="bg-slate-800/40 hover:bg-slate-800 active:scale-95 text-slate-400 hover:text-white text-xs font-bold py-3.5 rounded-2xl border border-slate-800 transition select-none flex items-center justify-center"
                title="Backspace"
              >
                <Delete className="w-5 h-5 text-slate-400 hover:text-white" />
              </button>
            </div>

            {/* Quick Demo Credentials for Fast Testing */}
            <div className="bg-slate-950/60 border border-slate-800/80 rounded-2xl p-2.5 mb-3.5">
              <div className="text-[10px] text-slate-400 font-semibold mb-1.5 flex items-center justify-between px-1">
                <span>Quick Test PINs (1-Click Fill):</span>
                <span className="text-[9px] text-emerald-400 font-mono">Auto Login</span>
              </div>
              <div className="grid grid-cols-3 gap-1.5">
                <button
                  type="button"
                  onClick={() => handleQuickLogin('1234')}
                  className="bg-slate-900 hover:bg-emerald-950/40 hover:border-emerald-600/50 border border-slate-800 px-2 py-1.5 rounded-xl text-[10px] font-bold text-slate-300 hover:text-emerald-300 transition text-center truncate"
                >
                  👑 Admin <span className="text-emerald-400 font-mono">1234</span>
                </button>
                <button
                  type="button"
                  onClick={() => handleQuickLogin('2222')}
                  className="bg-slate-900 hover:bg-emerald-950/40 hover:border-emerald-600/50 border border-slate-800 px-2 py-1.5 rounded-xl text-[10px] font-bold text-slate-300 hover:text-emerald-300 transition text-center truncate"
                >
                  💼 Mgr <span className="text-emerald-400 font-mono">2222</span>
                </button>
                <button
                  type="button"
                  onClick={() => handleQuickLogin('3333')}
                  className="bg-slate-900 hover:bg-emerald-950/40 hover:border-emerald-600/50 border border-slate-800 px-2 py-1.5 rounded-xl text-[10px] font-bold text-slate-300 hover:text-emerald-300 transition text-center truncate"
                >
                  🛒 Cashier <span className="text-emerald-400 font-mono">3333</span>
                </button>
              </div>
            </div>

            {/* Submit button (if not auto-submitted) */}
            <button
              type="button"
              onClick={() => executePinLogin(pin)}
              disabled={isSubmitting || pin.length === 0}
              className="w-full bg-emerald-500 hover:bg-emerald-400 disabled:opacity-50 disabled:pointer-events-none text-slate-950 font-black py-3 rounded-2xl shadow-lg shadow-emerald-500/20 transition text-sm flex items-center justify-center gap-2"
            >
              {isSubmitting ? (
                <>
                  <span className="w-4 h-4 border-2 border-slate-950 border-t-transparent rounded-full animate-spin" />
                  <span>Signing In...</span>
                </>
              ) : (
                <>
                  <span>Sign In</span>
                  <ArrowRight className="w-4 h-4" />
                </>
              )}
            </button>
          </div>
        ) : (
          /* MODE 2: USERNAME & PASSWORD */
          <form onSubmit={handlePasswordSubmit} className="space-y-4">
            <div>
              <label className="block text-xs font-semibold text-slate-400 mb-1">Username</label>
              <input
                type="text"
                required
                autoFocus
                value={username}
                onChange={(e) => setUsername(e.target.value)}
                placeholder="e.g. admin"
                className="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2.5 text-xs text-white focus:outline-none focus:border-emerald-500 transition"
              />
            </div>

            <div>
              <label className="block text-xs font-semibold text-slate-400 mb-1">Password</label>
              <div className="relative">
                <input
                  type={showPassword ? 'text' : 'password'}
                  required
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  placeholder="e.g. admin123"
                  className="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2.5 text-xs text-white focus:outline-none focus:border-emerald-500 pr-9 transition"
                />
                <button
                  type="button"
                  onClick={() => setShowPassword(!showPassword)}
                  className="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-200"
                >
                  {showPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                </button>
              </div>
            </div>

            <div className="bg-slate-950/60 border border-slate-800 rounded-xl p-2 text-[10px] text-slate-400 flex items-center justify-between">
              <span>Default Master Login:</span>
              <span className="font-mono text-emerald-400">admin / admin123</span>
            </div>

            <button
              type="submit"
              disabled={isSubmitting}
              className="w-full bg-emerald-500 hover:bg-emerald-400 disabled:opacity-50 text-slate-950 font-black py-3 rounded-2xl shadow-lg shadow-emerald-500/20 transition text-sm flex items-center justify-center gap-2"
            >
              {isSubmitting ? (
                <>
                  <span className="w-4 h-4 border-2 border-slate-950 border-t-transparent rounded-full animate-spin" />
                  <span>Signing In...</span>
                </>
              ) : (
                <>
                  <span>Sign In</span>
                  <ArrowRight className="w-4 h-4" />
                </>
              )}
            </button>
          </form>
        )}

        {/* Toggle Mode Link (Clean, subtle footer switcher) */}
        <div className="mt-5 pt-4 border-t border-slate-800 text-center">
          <button
            type="button"
            onClick={() => {
              setAuthMode(authMode === 'PIN' ? 'PASSWORD' : 'PIN');
              setErrorMsg(null);
              setPin('');
            }}
            className="text-xs text-slate-400 hover:text-emerald-400 font-medium transition flex items-center justify-center gap-1.5 mx-auto"
          >
            {authMode === 'PIN' ? (
              <>
                <Lock className="w-3.5 h-3.5 text-slate-400" />
                <span>Switch to Username & Password</span>
              </>
            ) : (
              <>
                <KeyRound className="w-3.5 h-3.5 text-slate-400" />
                <span>Switch to Staff PIN Pad</span>
              </>
            )}
          </button>
        </div>

        {/* Security & System Info Footer */}
        <div className="mt-4 text-center text-[10px] text-slate-400 font-mono">
          Vendra POS • Ed25519 Verified • 100% POST
        </div>
      </div>
    </div>
  );
};
