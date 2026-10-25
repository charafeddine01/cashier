import React, { useState, useEffect, useCallback } from 'react';
import { User, CompanySettings, Shift, LicenseStatus } from './types';
import { ApiService } from './services/api';
import { HardwareService } from './services/hardwareService';
import { OfflineSyncService } from './services/offlineSyncService';
import { I18nService } from './services/i18nService';

// Layout Components
import { TopBar } from './components/layout/TopBar';
import { Sidebar, ActiveTab } from './components/layout/Sidebar';
import { CashierSwitchModal } from './components/layout/CashierSwitchModal';

// Views & Modals
import { PosTerminal } from './components/pos/PosTerminal';
import { FloorPlanView } from './components/restaurant/FloorPlanView';
import { KitchenDisplayView } from './components/restaurant/KitchenDisplayView';
import { ProductsManager } from './components/products/ProductsManager';
import { InventoryManager } from './components/inventory/InventoryManager';
import { CustomersManager } from './components/customers/CustomersManager';
import { ReportsDashboard } from './components/reports/ReportsDashboard';
import { SettingsManager } from './components/settings/SettingsManager';
import { CustomerDisplayView } from './components/customerDisplay/CustomerDisplayView';
import { LicenseActivationGate } from './components/license/LicenseActivationGate';
import { ShiftModal } from './components/shifts/ShiftModal';
import { ReturnsModal } from './components/returns/ReturnsModal';
import { LoginView } from './components/auth/LoginView';

const DEFAULT_SETTINGS: CompanySettings = {
  id: 1,
  company_name: 'Vendra POS',
  company_name_ar: 'فيندرا بي أو إس',
  tax_id: 'TAX-00000000',
  address: 'Commercial POS Station',
  phone: '+1 (800) 555-0100',
  email: 'contact@vendra.charafeddine.xyz',
  website: 'https://vendra.charafeddine.xyz',
  primary_color: '#10b981',
  secondary_color: '#6366f1',
  theme_mode: 'light',
  business_mode: 'SUPERMARKET',
  primary_currency_code: 'USD',
  primary_currency_symbol: '$',
  secondary_currency_code: 'EUR',
  secondary_currency_symbol: '€',
  secondary_currency_rate: 0.92,
  tax_enabled: 1,
  tax_inclusive: 0,
  default_tax_rate: 10,
  receipt_width: '80mm',
  enable_customer_display: 1,
  scale_barcode_prefix: '21',
};

export const App: React.FC = () => {
  const [currentUser, setCurrentUser] = useState<User | null>(null);
  const [settings, setSettings] = useState<CompanySettings | null>(null);
  const [activeShift, setActiveShift] = useState<Shift | null>(null);
  const [licenseStatus, setLicenseStatus] = useState<LicenseStatus | null>(null);

  const [activeTab, setActiveTab] = useState<ActiveTab>('pos');
  const [isSwitchCashierOpen, setIsSwitchCashierOpen] = useState(false);
  const [isShiftModalOpen, setIsShiftModalOpen] = useState(false);
  const [isReturnsModalOpen, setIsReturnsModalOpen] = useState(false);
  const [isLicenseLocked, setIsLicenseLocked] = useState(false);
  const [isInitialized, setIsInitialized] = useState(false);

  // Initialize App Core with safe guards
  useEffect(() => {
    try {
      I18nService.init();
    } catch (e) {
      console.warn('[I18n] Initialization skipped:', e);
    }

    try {
      HardwareService.initScannerListener();
    } catch (e) {
      console.warn('[Hardware] Scanner listener skipped:', e);
    }

    try {
      OfflineSyncService.initAutoSync();
    } catch (e) {
      console.warn('[OfflineSync] Auto-sync skipped:', e);
    }

    // Setup global license error listener
    ApiService.setLicenseErrorCallback((err) => {
      console.warn('[LicenseGuard] Error callback triggered:', err);
      setIsLicenseLocked(true);
    });

    initAppData();
  }, []);

  const initAppData = async () => {
    try {
      // 1. Fetch license status
      const licRes = await ApiService.request('license.getStatus');
      if (licRes.success && licRes.data) {
        setLicenseStatus(licRes.data);
        if (!licRes.data.is_active || licRes.data.is_tampered) {
          setIsLicenseLocked(true);
        }
      }

      // 2. Fetch company settings
      const setRes = await ApiService.request('settings.get');
      if (setRes.success && setRes.data?.settings) {
        setSettings(setRes.data.settings);
        if (setRes.data.settings.primary_color) {
          document.documentElement.style.setProperty('--primary-color', setRes.data.settings.primary_color);
        }
        if (setRes.data.settings.secondary_color) {
          document.documentElement.style.setProperty('--secondary-color', setRes.data.settings.secondary_color);
        }
      } else {
        setSettings(DEFAULT_SETTINGS);
      }

      // 3. Fetch auth status if token exists
      const token = ApiService.getAuthToken();
      if (token) {
        const meRes = await ApiService.request('auth.me');
        if (meRes.success && meRes.data?.user) {
          setCurrentUser(meRes.data.user);
          fetchActiveShift();
        } else {
          ApiService.setAuthToken(null);
        }
      }
    } catch (err) {
      console.error('[OmniPOS] App boot initialization warning:', err);
      setSettings(DEFAULT_SETTINGS);
    } finally {
      setIsInitialized(true);
    }
  };

  const fetchActiveShift = useCallback(async () => {
    const shiftRes = await ApiService.request('shifts.getCurrent');
    if (shiftRes.success && shiftRes.data?.has_open_shift) {
      setActiveShift(shiftRes.data.shift);
    } else {
      setActiveShift(null);
    }
  }, []);

  const handleLoginSuccess = (user: User, token: string) => {
    setCurrentUser(user);
    fetchActiveShift();
  };

  const handleLogout = () => {
    ApiService.setAuthToken(null);
    setCurrentUser(null);
    setActiveShift(null);
  };

  const handleBusinessModeChange = async (mode: 'SUPERMARKET' | 'RESTAURANT' | 'RETAIL' | 'PHARMACY') => {
    if (!settings) return;
    const updated = { ...settings, business_mode: mode };
    setSettings(updated);
    await ApiService.request('settings.update', { business_mode: mode });
  };

  // If path is /customer-display or customer pole display tab selected
  const isPoleDisplayOnly = typeof window !== 'undefined' && window.location.pathname === '/customer-display';
  if (isPoleDisplayOnly || activeTab === 'customer_display') {
    return <CustomerDisplayView settings={settings} />;
  }

  // 1. Loading screen
  if (!isInitialized) {
    return (
      <div className="min-h-screen bg-slate-950 flex flex-col items-center justify-center text-white">
        <div className="w-12 h-12 border-4 border-emerald-500 border-t-transparent rounded-full animate-spin mb-4" />
        <p className="text-xs text-slate-400 font-mono tracking-wider uppercase">Initializing Vendra POS...</p>
      </div>
    );
  }

  // 2. License Activation Gate (if license expired or tampered)
  if (isLicenseLocked) {
    return (
      <LicenseActivationGate
        onLicenseActivated={() => {
          setIsLicenseLocked(false);
          initAppData();
        }}
      />
    );
  }

  // 3. Login / PIN Gate (if unauthenticated)
  if (!currentUser) {
    return <LoginView settings={settings} onLoginSuccess={handleLoginSuccess} />;
  }

  // 4. Main POS Workspace
  return (
    <div className="h-screen w-screen flex flex-col bg-slate-950 text-slate-100 overflow-hidden select-none">
      {/* Top Header */}
      <TopBar
        settings={settings}
        currentUser={currentUser}
        activeShift={activeShift}
        onOpenSwitchModal={() => setIsSwitchCashierOpen(true)}
        onOpenShiftModal={() => setIsShiftModalOpen(true)}
        onModeChange={handleBusinessModeChange}
      />

      {/* Main Layout Area */}
      <div className="flex-1 flex overflow-hidden">
        {/* Navigation Sidebar */}
        <Sidebar
          activeTab={activeTab}
          onSelectTab={(tab) => {
            if (tab === 'returns') {
              setIsReturnsModalOpen(true);
            } else if (tab === 'shifts') {
              setIsShiftModalOpen(true);
            } else {
              setActiveTab(tab);
            }
          }}
          onLogout={handleLogout}
        />

        {/* Dynamic Center Viewport */}
        <main className="flex-1 flex overflow-hidden">
          {activeTab === 'pos' && (
            <PosTerminal
              settings={settings}
              activeShift={activeShift}
              onOpenShiftModal={() => setIsShiftModalOpen(true)}
            />
          )}

          {activeTab === 'restaurant' && (
            <FloorPlanView
              onSelectTableForOrder={(table) => {
                setActiveTab('pos');
              }}
            />
          )}

          {activeTab === 'kds' && <KitchenDisplayView />}

          {activeTab === 'products' && <ProductsManager settings={settings} />}

          {activeTab === 'inventory' && <InventoryManager settings={settings} />}

          {activeTab === 'customers' && <CustomersManager />}

          {activeTab === 'reports' && <ReportsDashboard />}

          {activeTab === 'settings' && (
            <SettingsManager
              settings={settings}
              onSettingsUpdated={(updated) => setSettings(updated)}
            />
          )}

          {activeTab === 'license' && (
            <div className="flex-1 overflow-y-auto">
              <LicenseActivationGate onLicenseActivated={initAppData} />
            </div>
          )}
        </main>
      </div>

      {/* Quick Cashier PIN Switch Modal */}
      <CashierSwitchModal
        isOpen={isSwitchCashierOpen}
        onClose={() => setIsSwitchCashierOpen(false)}
        onSuccess={(user) => {
          setCurrentUser(user);
          fetchActiveShift();
        }}
      />

      {/* Shifts & Drawer Management Modal */}
      <ShiftModal
        isOpen={isShiftModalOpen}
        activeShift={activeShift}
        settings={settings}
        onClose={() => setIsShiftModalOpen(false)}
        onShiftUpdated={fetchActiveShift}
      />

      {/* Returns & Refunds Modal */}
      <ReturnsModal
        isOpen={isReturnsModalOpen}
        onClose={() => setIsReturnsModalOpen(false)}
        onReturnProcessed={() => {
          fetchActiveShift();
        }}
      />
    </div>
  );
};
