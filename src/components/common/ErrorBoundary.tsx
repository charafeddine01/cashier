import React, { Component, ErrorInfo, ReactNode } from 'react';

interface Props {
  children: ReactNode;
}

interface State {
  hasError: boolean;
  error: Error | null;
  errorInfo: ErrorInfo | null;
}

export class ErrorBoundary extends Component<Props, State> {
  public state: State = {
    hasError: false,
    error: null,
    errorInfo: null,
  };

  public static getDerivedStateFromError(error: Error): State {
    return { hasError: true, error, errorInfo: null };
  }

  public componentDidCatch(error: Error, errorInfo: ErrorInfo): void {
    console.error('[OmniPOS ErrorBoundary] Caught runtime exception:', error, errorInfo);
    this.setState({ error, errorInfo });
  }

  private handleReload = (): void => {
    window.location.reload();
  };

  private handleClearStorage = (): void => {
    try {
      localStorage.clear();
      sessionStorage.clear();
      if ('caches' in window) {
        caches.keys().then((keys) => {
          for (const key of keys) caches.delete(key);
        });
      }
    } catch {}
    window.location.href = '/';
  };

  public render(): ReactNode {
    if (this.state.hasError) {
      const err = this.state.error;
      const stack = err?.stack || this.state.errorInfo?.componentStack || '';

      return (
        <div className="min-h-screen bg-slate-950 text-slate-100 flex flex-col items-center justify-center p-4 font-sans select-none">
          <div className="bg-slate-900 border border-rose-500/30 rounded-3xl w-full max-w-xl p-6 sm:p-8 shadow-2xl relative space-y-6">
            {/* Header */}
            <div className="flex items-center gap-3 border-b border-slate-800 pb-4">
              <div className="w-12 h-12 rounded-2xl bg-rose-500/10 border border-rose-500/30 flex items-center justify-center text-rose-400 shrink-0">
                <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
              </div>
              <div>
                <span className="text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded bg-rose-500/20 text-rose-300">
                  Runtime Error Intercepted
                </span>
                <h1 className="text-lg font-black text-white tracking-tight mt-1">Vendra POS Safe Boot Fallback</h1>
              </div>
            </div>

            {/* Error Details */}
            <div className="space-y-2">
              <p className="text-xs text-slate-300">
                A JavaScript runtime exception occurred while rendering the application. Details:
              </p>
              <div className="bg-slate-950 border border-slate-800 rounded-2xl p-4 text-xs font-mono text-rose-300 max-h-48 overflow-y-auto whitespace-pre-wrap select-text">
                {err?.name}: {err?.message || 'Unknown runtime error'}
                {stack ? `\n\nStack:\n${stack}` : ''}
              </div>
            </div>

            {/* Action Controls */}
            <div className="pt-2 flex flex-col sm:flex-row items-center gap-3">
              <button
                onClick={this.handleReload}
                className="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs transition shadow-lg shadow-blue-600/30"
              >
                Reload Application
              </button>

              <button
                onClick={this.handleClearStorage}
                className="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold text-xs transition"
              >
                Clear Cache & Reset
              </button>

              <a
                href="/install/"
                className="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold text-xs transition text-center"
              >
                Open Web Installer
              </a>
            </div>
          </div>
        </div>
      );
    }

    return this.props.children;
  }
}
