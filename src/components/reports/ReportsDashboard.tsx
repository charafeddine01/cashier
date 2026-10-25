import React, { useState, useEffect } from 'react';
import { ApiService } from '../../services/api';
import { I18nService } from '../../services/i18nService';
import {
  BarChart3,
  TrendingUp,
  DollarSign,
  ShoppingBag,
  Users,
  AlertTriangle,
  RefreshCw,
  PieChart,
  Award
} from 'lucide-react';

export const ReportsDashboard: React.FC = () => {
  const [dashboard, setDashboard] = useState<any | null>(null);
  const [hourlySales, setHourlySales] = useState<any[]>([]);
  const [cogsData, setCogsData] = useState<any | null>(null);
  const [topProducts, setTopProducts] = useState<any[]>([]);
  const [cashierStats, setCashierStats] = useState<any[]>([]);
  const [loading, setLoading] = useState(false);

  const fetchReports = async () => {
    setLoading(true);
    const [dashRes, hrRes, cogsRes, topRes, cashRes] = await Promise.all([
      ApiService.request('reports.dashboard'),
      ApiService.request('reports.salesHourly'),
      ApiService.request('reports.profitAndCogs'),
      ApiService.request('reports.topProducts'),
      ApiService.request('reports.cashierPerformance'),
    ]);
    setLoading(false);

    if (dashRes.success) setDashboard(dashRes.data);
    if (hrRes.success) setHourlySales(hrRes.data.hourly_sales || []);
    if (cogsRes.success) setCogsData(cogsRes.data);
    if (topRes.success) setTopProducts(topRes.data.top_products || []);
    if (cashRes.success) setCashierStats(cashRes.data.cashier_performance || []);
  };

  useEffect(() => {
    fetchReports();
  }, []);

  return (
    <div className="flex flex-col h-full bg-slate-950 select-none overflow-y-auto p-6 font-sans space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2">
          <BarChart3 className="w-6 h-6 text-blue-400" />
          <h2 className="text-xl font-black text-white">{I18nService.t('nav.reports')}</h2>
        </div>

        <button
          onClick={fetchReports}
          className="bg-slate-900 hover:bg-slate-800 border border-slate-800 text-slate-300 font-bold px-3 py-1.5 rounded-xl text-xs transition flex items-center gap-1.5"
        >
          <RefreshCw className={`w-3.5 h-3.5 ${loading ? 'animate-spin' : ''}`} />
          <span>Refresh</span>
        </button>
      </div>

      {/* KPI Cards Row */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div className="bg-slate-900 border border-slate-800 rounded-3xl p-5 shadow-xl">
          <div className="flex items-center justify-between text-slate-400 mb-2">
            <span className="text-xs font-bold uppercase tracking-wider">Today's Revenue</span>
            <DollarSign className="w-5 h-5 text-emerald-400" />
          </div>
          <div className="text-3xl font-black text-white">
            ${Number(dashboard?.sales_today?.revenue || 0).toFixed(2)}
          </div>
          <span className="text-[11px] text-emerald-400 font-semibold mt-1 block">
            {dashboard?.sales_today?.orders_count || 0} orders completed
          </span>
        </div>

        <div className="bg-slate-900 border border-slate-800 rounded-3xl p-5 shadow-xl">
          <div className="flex items-center justify-between text-slate-400 mb-2">
            <span className="text-xs font-bold uppercase tracking-wider">Gross Profit</span>
            <TrendingUp className="w-5 h-5 text-blue-400" />
          </div>
          <div className="text-3xl font-black text-white">
            ${Number(cogsData?.gross_profit || 0).toFixed(2)}
          </div>
          <span className="text-[11px] text-blue-400 font-semibold mt-1 block">
            {cogsData?.profit_margin_percent || 0}% Profit Margin
          </span>
        </div>

        <div className="bg-slate-900 border border-slate-800 rounded-3xl p-5 shadow-xl">
          <div className="flex items-center justify-between text-slate-400 mb-2">
            <span className="text-xs font-bold uppercase tracking-wider">Average Order</span>
            <ShoppingBag className="w-5 h-5 text-purple-400" />
          </div>
          <div className="text-3xl font-black text-white">
            ${Number(dashboard?.sales_today?.avg_order_value || 0).toFixed(2)}
          </div>
          <span className="text-[11px] text-purple-400 font-semibold mt-1 block">Per checkout transaction</span>
        </div>

        <div className="bg-slate-900 border border-slate-800 rounded-3xl p-5 shadow-xl">
          <div className="flex items-center justify-between text-slate-400 mb-2">
            <span className="text-xs font-bold uppercase tracking-wider">Customer Debt</span>
            <Users className="w-5 h-5 text-amber-400" />
          </div>
          <div className="text-3xl font-black text-white">
            ${Number(dashboard?.total_customer_debt || 0).toFixed(2)}
          </div>
          <span className="text-[11px] text-amber-400 font-semibold mt-1 block">
            {dashboard?.customers_count || 0} active accounts
          </span>
        </div>
      </div>

      {/* Hourly Sales Bar Chart (Pure CSS) */}
      <div className="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
        <h3 className="text-sm font-bold text-white flex items-center gap-2">
          <TrendingUp className="w-4 h-4 text-blue-400" />
          <span>Hourly Sales Distribution (Today)</span>
        </h3>

        <div className="h-44 flex items-end gap-1.5 pt-6 pb-2 border-b border-slate-800 overflow-x-auto">
          {hourlySales.map((h, idx) => {
            const maxRev = Math.max(...hourlySales.map((s) => s.revenue), 50);
            const heightPercent = Math.min(100, Math.max(8, (h.revenue / maxRev) * 100));

            return (
              <div key={idx} className="flex-1 min-w-[28px] flex flex-col items-center gap-1 group relative">
                {/* Tooltip */}
                <div className="absolute -top-10 opacity-0 group-hover:opacity-100 bg-slate-950 text-white text-[10px] font-mono px-2 py-1 rounded-md border border-slate-700 pointer-events-none transition whitespace-nowrap z-10">
                  ${h.revenue.toFixed(2)} ({h.order_count} orders)
                </div>

                <div
                  style={{ height: `${heightPercent}%` }}
                  className={`w-full rounded-t-lg transition-all duration-300 ${
                    h.revenue > 0 ? 'bg-blue-600 group-hover:bg-blue-500' : 'bg-slate-800/40'
                  }`}
                />
                <span className="text-[9px] text-slate-500 font-mono">{h.hour}</span>
              </div>
            );
          })}
        </div>
      </div>

      {/* Bottom Grid: Top Selling Products & Cashier Performance */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Top 10 Products */}
        <div className="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-3">
          <h3 className="text-sm font-bold text-white flex items-center gap-2">
            <Award className="w-4 h-4 text-purple-400" />
            <span>Top 10 Selling Products</span>
          </h3>

          <div className="space-y-2">
            {topProducts.map((p, idx) => (
              <div
                key={idx}
                className="bg-slate-950 border border-slate-800 p-3 rounded-2xl flex items-center justify-between text-xs"
              >
                <div className="flex items-center gap-3">
                  <span className="w-5 h-5 rounded-full bg-slate-800 font-bold text-[10px] text-slate-400 flex items-center justify-center">
                    {idx + 1}
                  </span>
                  <div>
                    <span className="font-bold text-white block">{p.product_name}</span>
                    <span className="text-[10px] text-slate-400 font-mono">{p.sku}</span>
                  </div>
                </div>

                <div className="text-right">
                  <span className="font-black text-white block">${Number(p.total_sales_revenue).toFixed(2)}</span>
                  <span className="text-[10px] text-slate-400 font-mono">{Number(p.total_quantity_sold).toFixed(0)} sold</span>
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Cashier Audit & Performance */}
        <div className="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-3">
          <h3 className="text-sm font-bold text-white flex items-center gap-2">
            <Users className="w-4 h-4 text-emerald-400" />
            <span>Cashier Performance & Audit</span>
          </h3>

          <div className="space-y-2">
            {cashierStats.map((c) => (
              <div
                key={c.user_id}
                className="bg-slate-950 border border-slate-800 p-3 rounded-2xl flex items-center justify-between text-xs"
              >
                <div>
                  <span className="font-bold text-white block">{c.cashier_name}</span>
                  <span className="text-[10px] text-blue-400 font-mono">{c.role}</span>
                </div>

                <div className="text-right">
                  <span className="font-black text-white block">${Number(c.total_sales_amount).toFixed(2)}</span>
                  <span className="text-[10px] text-slate-400 font-mono">{c.orders_served} checkouts</span>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
};
