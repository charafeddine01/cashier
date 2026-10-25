import React from 'react';
import { I18nService } from '../../services/i18nService';
import {
  LayoutGrid,
  Utensils,
  ChefHat,
  Package,
  Boxes,
  RotateCcw,
  Coins,
  Users,
  BarChart3,
  Settings,
  Tv,
  LogOut,
  ShieldCheck,
  ChevronRight
} from 'lucide-react';

export type ActiveTab =
  | 'pos'
  | 'restaurant'
  | 'kds'
  | 'products'
  | 'inventory'
  | 'returns'
  | 'shifts'
  | 'customers'
  | 'reports'
  | 'settings'
  | 'customer_display'
  | 'license';

interface Props {
  activeTab: ActiveTab;
  onSelectTab: (tab: ActiveTab) => void;
  onLogout: () => void;
  isCollapsed?: boolean;
}

export const Sidebar: React.FC<Props> = ({ activeTab, onSelectTab, onLogout, isCollapsed = false }) => {
  const menuItems: Array<{ id: ActiveTab; label: string; icon: React.FC<{ className?: string }>; badge?: string }> = [
    { id: 'pos', label: I18nService.t('nav.pos'), icon: LayoutGrid },
    { id: 'restaurant', label: I18nService.t('nav.restaurant'), icon: Utensils },
    { id: 'kds', label: I18nService.t('nav.kds'), icon: ChefHat },
    { id: 'products', label: I18nService.t('nav.products'), icon: Package },
    { id: 'inventory', label: I18nService.t('nav.inventory'), icon: Boxes },
    { id: 'returns', label: I18nService.t('nav.returns'), icon: RotateCcw },
    { id: 'shifts', label: I18nService.t('nav.shifts'), icon: Coins },
    { id: 'customers', label: I18nService.t('nav.customers'), icon: Users },
    { id: 'reports', label: I18nService.t('nav.reports'), icon: BarChart3 },
    { id: 'settings', label: I18nService.t('nav.settings'), icon: Settings },
    { id: 'customer_display', label: I18nService.t('nav.customer_display'), icon: Tv },
    { id: 'license', label: I18nService.t('lic.title'), icon: ShieldCheck },
  ];

  return (
    <aside
      className={`bg-slate-900 border-r border-slate-800 text-slate-300 flex flex-col justify-between transition-all duration-300 shrink-0 select-none z-20 ${
        isCollapsed ? 'w-16' : 'w-56'
      }`}
    >
      {/* Navigation Items */}
      <nav className="p-3 space-y-1.5 overflow-y-auto">
        {menuItems.map((item) => {
          const Icon = item.icon;
          const isActive = activeTab === item.id;

          return (
            <button
              key={item.id}
              onClick={() => onSelectTab(item.id)}
              className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-2xl font-semibold text-xs transition duration-150 ${
                isActive
                  ? 'bg-blue-600 text-white shadow-lg shadow-blue-600/30'
                  : 'text-slate-400 hover:text-slate-100 hover:bg-slate-800/70'
              }`}
            >
              <Icon className="w-5 h-5 shrink-0" />
              {!isCollapsed && <span className="truncate">{item.label}</span>}
              {!isCollapsed && isActive && <ChevronRight className="w-3.5 h-3.5 ml-auto text-blue-200" />}
            </button>
          );
        })}
      </nav>

      {/* Footer / Logout */}
      <div className="p-3 border-t border-slate-800">
        <button
          onClick={onLogout}
          className="w-full flex items-center gap-3 px-3 py-2.5 rounded-2xl font-semibold text-xs text-rose-400 hover:text-rose-300 hover:bg-rose-500/10 transition"
        >
          <LogOut className="w-5 h-5 shrink-0" />
          {!isCollapsed && <span>{I18nService.t('common.logout')}</span>}
        </button>
      </div>
    </aside>
  );
};
