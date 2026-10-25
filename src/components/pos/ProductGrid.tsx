import React, { useState, useMemo } from 'react';
import { Product, Category, CompanySettings } from '../../types';
import { I18nService } from '../../services/i18nService';
import { Search, Camera, Scale, Sparkles, Layers, Package } from 'lucide-react';

interface Props {
  products: Product[];
  categories: Category[];
  settings: CompanySettings | null;
  onSelectProduct: (product: Product) => void;
  onOpenScanner: () => void;
}

export const ProductGrid: React.FC<Props> = ({
  products,
  categories,
  settings,
  onSelectProduct,
  onOpenScanner,
}) => {
  const [selectedCatId, setSelectedCatId] = useState<number | 'ALL'>('ALL');
  const [searchQuery, setSearchQuery] = useState('');

  const primarySymbol = settings?.primary_currency_symbol || '$';
  const secondarySymbol = settings?.secondary_currency_symbol || '€';
  const secondaryRate = settings?.secondary_currency_rate || 0.92;

  const filteredProducts = useMemo(() => {
    return products.filter((p) => {
      // Category filter
      if (selectedCatId !== 'ALL' && p.category_id !== selectedCatId) {
        return false;
      }
      // Search filter
      if (!searchQuery.trim()) return true;

      const q = searchQuery.toLowerCase().trim();
      return (
        p.name.toLowerCase().includes(q) ||
        (p.name_ar && p.name_ar.toLowerCase().includes(q)) ||
        p.sku.toLowerCase().includes(q) ||
        (p.barcode && p.barcode.toLowerCase().includes(q)) ||
        (p.scale_plu && p.scale_plu.includes(q))
      );
    });
  }, [products, selectedCatId, searchQuery]);

  return (
    <div className="flex flex-col h-full overflow-hidden bg-slate-900/50">
      {/* Search & Actions Bar */}
      <div className="p-3 bg-slate-900 border-b border-slate-800 flex items-center gap-2 shrink-0">
        <div className="relative flex-1">
          <Search className="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
          <input
            type="text"
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            placeholder={I18nService.t('common.search')}
            className="w-full bg-slate-800 border border-slate-700/80 rounded-2xl pl-10 pr-4 py-2.5 text-xs text-white placeholder-slate-400 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition"
          />
          {searchQuery && (
            <button
              onClick={() => setSearchQuery('')}
              className="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 hover:text-white"
            >
              Clear
            </button>
          )}
        </div>

        <button
          onClick={onOpenScanner}
          className="bg-slate-800 hover:bg-slate-700 border border-slate-700 p-2.5 rounded-2xl text-slate-300 hover:text-white transition flex items-center gap-1.5 text-xs font-semibold shrink-0"
          title={I18nService.t('pos.barcode_camera')}
        >
          <Camera className="w-4 h-4 text-blue-400" />
          <span className="hidden sm:inline">{I18nService.t('pos.barcode_camera')}</span>
        </button>
      </div>

      {/* Category Pills Bar */}
      <div className="px-3 py-2 bg-slate-900/80 border-b border-slate-800/80 flex items-center gap-2 overflow-x-auto no-scrollbar shrink-0">
        <button
          onClick={() => setSelectedCatId('ALL')}
          className={`px-3.5 py-1.5 rounded-xl text-xs font-bold transition whitespace-nowrap shrink-0 ${
            selectedCatId === 'ALL'
              ? 'bg-blue-600 text-white shadow-md shadow-blue-600/30'
              : 'bg-slate-800 text-slate-400 hover:bg-slate-700 hover:text-white border border-slate-700/60'
          }`}
        >
          {I18nService.t('common.all')} ({products.length})
        </button>

        {categories.map((cat) => {
          const isSelected = selectedCatId === cat.id;
          return (
            <button
              key={cat.id}
              onClick={() => setSelectedCatId(cat.id)}
              className={`px-3.5 py-1.5 rounded-xl text-xs font-bold transition whitespace-nowrap flex items-center gap-1.5 shrink-0 ${
                isSelected
                  ? 'bg-blue-600 text-white shadow-md shadow-blue-600/30'
                  : 'bg-slate-800 text-slate-300 hover:bg-slate-700 hover:text-white border border-slate-700/60'
              }`}
            >
              <span className="w-2 h-2 rounded-full" style={{ backgroundColor: cat.color || '#3b82f6' }} />
              <span>{cat.name}</span>
            </button>
          );
        })}
      </div>

      {/* Products Grid */}
      <div className="flex-1 overflow-y-auto p-3">
        {filteredProducts.length === 0 ? (
          <div className="h-full flex flex-col items-center justify-center text-center p-6 text-slate-400">
            <Package className="w-12 h-12 text-slate-600 mb-2 stroke-1" />
            <p className="text-sm font-semibold">No products found</p>
            <p className="text-xs text-slate-500 mt-1">Try another search keyword or select a different category.</p>
          </div>
        ) : (
          <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-2.5">
            {filteredProducts.map((p) => {
              const priceSec = p.secondary_selling_price || p.selling_price * secondaryRate;
              const isScale = p.type === 'SCALE';
              const isCombo = p.type === 'COMBO';
              const isVariable = p.type === 'VARIABLE';
              const isLowStock = (p.stock_quantity ?? 0) <= p.alert_quantity && p.track_inventory === 1;

              return (
                <button
                  key={p.id}
                  onClick={() => onSelectProduct(p)}
                  className="group bg-slate-800/90 hover:bg-slate-750 active:scale-[0.98] border border-slate-700/70 hover:border-blue-500/50 rounded-2xl p-2.5 text-left flex flex-col justify-between transition-all duration-150 shadow-sm relative overflow-hidden"
                >
                  {/* Top Badges */}
                  <div className="flex items-center justify-between gap-1 w-full mb-1">
                    {isScale && (
                      <span className="bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 text-[9px] font-bold px-1.5 py-0.5 rounded-md flex items-center gap-0.5">
                        <Scale className="w-2.5 h-2.5" /> PLU {p.scale_plu || 'SCALE'}
                      </span>
                    )}
                    {isCombo && (
                      <span className="bg-purple-500/20 text-purple-400 border border-purple-500/30 text-[9px] font-bold px-1.5 py-0.5 rounded-md flex items-center gap-0.5">
                        <Sparkles className="w-2.5 h-2.5" /> Combo
                      </span>
                    )}
                    {isVariable && (
                      <span className="bg-indigo-500/20 text-indigo-400 border border-indigo-500/30 text-[9px] font-bold px-1.5 py-0.5 rounded-md flex items-center gap-0.5">
                        <Layers className="w-2.5 h-2.5" /> Variants
                      </span>
                    )}
                    {isLowStock && (
                      <span className="bg-rose-500/20 text-rose-400 border border-rose-500/30 text-[9px] font-bold px-1.5 py-0.5 rounded-md ml-auto">
                        Low: {Number(p.stock_quantity).toFixed(0)}
                      </span>
                    )}
                  </div>

                  {/* Title & SKU */}
                  <div className="my-1">
                    <h3 className="text-xs font-bold text-white line-clamp-2 leading-tight group-hover:text-blue-400 transition">
                      {p.name}
                    </h3>
                    <p className="text-[10px] text-slate-400 font-mono mt-0.5 truncate">
                      {p.barcode || p.sku}
                    </p>
                  </div>

                  {/* Price Row */}
                  <div className="pt-2 border-t border-slate-700/60 flex items-baseline justify-between w-full mt-1">
                    <div className="text-sm font-black text-white">
                      {primarySymbol}{Number(p.selling_price).toFixed(2)}
                      {p.unit_code && <span className="text-[10px] font-normal text-slate-400 ml-0.5">/{p.unit_code}</span>}
                    </div>
                    <div className="text-[10px] font-semibold text-slate-400">
                      {secondarySymbol}{priceSec.toFixed(2)}
                    </div>
                  </div>
                </button>
              );
            })}
          </div>
        )}
      </div>
    </div>
  );
};
