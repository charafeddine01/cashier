import React, { useState, useEffect } from 'react';
import { Product, Category, Unit, TaxRate, CompanySettings } from '../../types';
import { ApiService } from '../../services/api';
import { I18nService } from '../../services/i18nService';
import {
  Package,
  Plus,
  Search,
  Edit2,
  Trash2,
  Barcode,
  Layers,
  Sparkles,
  Scale,
  Printer,
  CheckCircle2,
  X
} from 'lucide-react';

interface Props {
  settings: CompanySettings | null;
}

export const ProductsManager: React.FC<Props> = ({ settings }) => {
  const [products, setProducts] = useState<Product[]>([]);
  const [categories, setCategories] = useState<Category[]>([]);
  const [units, setUnits] = useState<Unit[]>([]);
  const [taxRates, setTaxRates] = useState<TaxRate[]>([]);
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(false);

  // Edit/Create Modal State
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingProduct, setEditingProduct] = useState<Partial<Product> | null>(null);

  // Barcode Label Print Modal State
  const [labelProduct, setLabelProduct] = useState<Product | null>(null);

  const fetchCatalog = async () => {
    setLoading(true);
    const res = await ApiService.request('pos.catalog');
    setLoading(false);
    if (res.success && res.data) {
      setProducts(res.data.products || []);
      setCategories(res.data.categories || []);
      setUnits(res.data.units || []);
      setTaxRates(res.data.tax_rates || []);
    }
  };

  useEffect(() => {
    fetchCatalog();
  }, []);

  const handleOpenCreate = () => {
    setEditingProduct({
      name: '',
      sku: 'SKU-' + Math.floor(100000 + Math.random() * 900000),
      barcode: '',
      category_id: categories[0]?.id || 1,
      unit_id: units[0]?.id || 1,
      tax_rate_id: taxRates[0]?.id || 1,
      type: 'STANDARD',
      cost_price: 1.0,
      selling_price: 2.0,
      track_inventory: 1,
      alert_quantity: 5,
    });
    setIsModalOpen(true);
  };

  const handleOpenEdit = (p: Product) => {
    setEditingProduct(p);
    setIsModalOpen(true);
  };

  const handleSaveProduct = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!editingProduct?.name) return;

    setLoading(true);
    const res = await ApiService.request('products.save', editingProduct);
    setLoading(false);

    if (res.success) {
      setIsModalOpen(false);
      fetchCatalog();
    } else {
      alert(res.error?.message || 'Failed to save product');
    }
  };

  const handleDeleteProduct = async (id: number) => {
    if (!confirm('Are you sure you want to deactivate this product?')) return;
    await ApiService.request('products.delete', { id });
    fetchCatalog();
  };

  const filtered = products.filter((p) => {
    if (!search.trim()) return true;
    const q = search.toLowerCase();
    return (
      p.name.toLowerCase().includes(q) ||
      p.sku.toLowerCase().includes(q) ||
      (p.barcode && p.barcode.toLowerCase().includes(q))
    );
  });

  return (
    <div className="flex flex-col h-full bg-slate-950 select-none overflow-hidden font-sans">
      {/* Top Header */}
      <div className="p-4 bg-slate-900 border-b border-slate-800 flex items-center justify-between shrink-0">
        <div className="flex items-center gap-2">
          <Package className="w-5 h-5 text-blue-400" />
          <h2 className="text-sm font-bold text-white">{I18nService.t('nav.products')} ({products.length})</h2>
        </div>

        <div className="flex items-center gap-3">
          <div className="relative w-64">
            <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
            <input
              type="text"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Search products, SKU, barcode..."
              className="w-full bg-slate-950 border border-slate-700 rounded-xl pl-9 pr-3 py-1.5 text-xs text-white focus:outline-none focus:border-blue-500"
            />
          </div>

          <button
            onClick={handleOpenCreate}
            className="bg-blue-600 hover:bg-blue-500 text-white font-bold px-4 py-2 rounded-xl text-xs transition flex items-center gap-1.5 shadow-md shadow-blue-600/30"
          >
            <Plus className="w-4 h-4" />
            <span>Add Product</span>
          </button>
        </div>
      </div>

      {/* Table Content */}
      <div className="flex-1 overflow-y-auto p-4">
        <div className="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
          <table className="w-full text-left text-xs text-slate-300">
            <thead className="bg-slate-950 text-slate-400 uppercase text-[10px] font-bold border-b border-slate-800">
              <tr>
                <th className="px-4 py-3">Product Name</th>
                <th className="px-4 py-3">SKU / Barcode</th>
                <th className="px-4 py-3">Type</th>
                <th className="px-4 py-3">Cost Price</th>
                <th className="px-4 py-3">Selling Price</th>
                <th className="px-4 py-3">Stock</th>
                <th className="px-4 py-3 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-800">
              {filtered.map((p) => (
                <tr key={p.id} className="hover:bg-slate-850/50 transition">
                  <td className="px-4 py-3">
                    <span className="font-bold text-white block">{p.name}</span>
                    {p.name_ar && <span className="text-[10px] text-slate-400">{p.name_ar}</span>}
                  </td>
                  <td className="px-4 py-3 font-mono text-[11px]">
                    <div>{p.sku}</div>
                    <div className="text-slate-400 text-[10px]">{p.barcode || '—'}</div>
                  </td>
                  <td className="px-4 py-3">
                    <span className={`text-[10px] font-bold px-2 py-0.5 rounded-full ${
                      p.type === 'SCALE' ? 'bg-emerald-500/20 text-emerald-400' :
                      p.type === 'COMBO' ? 'bg-purple-500/20 text-purple-400' :
                      p.type === 'VARIABLE' ? 'bg-indigo-500/20 text-indigo-400' :
                      'bg-slate-800 text-slate-300'
                    }`}>
                      {p.type}
                    </span>
                  </td>
                  <td className="px-4 py-3 font-mono">${Number(p.cost_price).toFixed(2)}</td>
                  <td className="px-4 py-3 font-mono font-bold text-white">${Number(p.selling_price).toFixed(2)}</td>
                  <td className="px-4 py-3 font-mono">
                    <span className={Number(p.stock_quantity ?? 0) <= p.alert_quantity ? 'text-rose-400 font-bold' : 'text-slate-300'}>
                      {Number(p.stock_quantity ?? 0).toFixed(0)} {p.unit_code}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-right">
                    <div className="flex items-center justify-end gap-1.5">
                      <button
                        onClick={() => setLabelProduct(p)}
                        className="p-1.5 hover:bg-slate-800 text-slate-400 hover:text-white rounded-lg transition"
                        title="Print Barcode Label"
                      >
                        <Barcode className="w-4 h-4 text-blue-400" />
                      </button>
                      <button
                        onClick={() => handleOpenEdit(p)}
                        className="p-1.5 hover:bg-slate-800 text-slate-400 hover:text-white rounded-lg transition"
                        title="Edit Product"
                      >
                        <Edit2 className="w-4 h-4" />
                      </button>
                      <button
                        onClick={() => handleDeleteProduct(p.id)}
                        className="p-1.5 hover:bg-rose-500/20 text-slate-400 hover:text-rose-400 rounded-lg transition"
                        title="Delete Product"
                      >
                        <Trash2 className="w-4 h-4" />
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {/* Product Form Modal */}
      {isModalOpen && editingProduct && (
        <div className="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-slate-900 border border-slate-700 rounded-3xl w-full max-w-xl p-6 shadow-2xl relative flex flex-col max-h-[90vh]">
            <div className="flex items-center justify-between pb-4 border-b border-slate-800 shrink-0">
              <h3 className="font-bold text-white text-base">
                {editingProduct.id ? 'Edit Product' : 'Create New Product'}
              </h3>
              <button onClick={() => setIsModalOpen(false)} className="p-1 text-slate-400 hover:text-white">
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleSaveProduct} className="flex-1 overflow-y-auto py-4 space-y-4">
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-semibold text-slate-400 mb-1">Product Name (EN)</label>
                  <input
                    type="text"
                    required
                    value={editingProduct.name || ''}
                    onChange={(e) => setEditingProduct({ ...editingProduct, name: e.target.value })}
                    className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500"
                  />
                </div>
                <div>
                  <label className="block text-xs font-semibold text-slate-400 mb-1">Product Name (Arabic)</label>
                  <input
                    type="text"
                    value={editingProduct.name_ar || ''}
                    onChange={(e) => setEditingProduct({ ...editingProduct, name_ar: e.target.value })}
                    className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:border-blue-500"
                  />
                </div>
              </div>

              <div className="grid grid-cols-3 gap-3">
                <div>
                  <label className="block text-xs font-semibold text-slate-400 mb-1">SKU</label>
                  <input
                    type="text"
                    value={editingProduct.sku || ''}
                    onChange={(e) => setEditingProduct({ ...editingProduct, sku: e.target.value })}
                    className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white font-mono focus:outline-none"
                  />
                </div>
                <div>
                  <label className="block text-xs font-semibold text-slate-400 mb-1">Barcode</label>
                  <input
                    type="text"
                    value={editingProduct.barcode || ''}
                    onChange={(e) => setEditingProduct({ ...editingProduct, barcode: e.target.value })}
                    className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white font-mono focus:outline-none"
                  />
                </div>
                <div>
                  <label className="block text-xs font-semibold text-slate-400 mb-1">Product Type</label>
                  <select
                    value={editingProduct.type || 'STANDARD'}
                    onChange={(e) => setEditingProduct({ ...editingProduct, type: e.target.value as any })}
                    className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs font-bold text-white focus:outline-none"
                  >
                    <option value="STANDARD">STANDARD</option>
                    <option value="SCALE">SCALE (Weighed)</option>
                    <option value="VARIABLE">VARIABLE (Matrix)</option>
                    <option value="COMBO">COMBO (Recipe BOM)</option>
                  </select>
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-semibold text-slate-400 mb-1">Cost Price ($)</label>
                  <input
                    type="number"
                    step="0.0001"
                    value={editingProduct.cost_price ?? 0}
                    onChange={(e) => setEditingProduct({ ...editingProduct, cost_price: parseFloat(e.target.value) || 0 })}
                    className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white font-mono focus:outline-none"
                  />
                </div>
                <div>
                  <label className="block text-xs font-semibold text-slate-400 mb-1">Selling Price ($)</label>
                  <input
                    type="number"
                    step="0.0001"
                    value={editingProduct.selling_price ?? 0}
                    onChange={(e) => setEditingProduct({ ...editingProduct, selling_price: parseFloat(e.target.value) || 0 })}
                    className="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white font-mono font-bold focus:outline-none"
                  />
                </div>
              </div>

              {editingProduct.type === 'SCALE' && (
                <div>
                  <label className="block text-xs font-semibold text-emerald-400 mb-1">Scale 5-Digit PLU Code</label>
                  <input
                    type="text"
                    maxLength={5}
                    value={editingProduct.scale_plu || ''}
                    onChange={(e) => setEditingProduct({ ...editingProduct, scale_plu: e.target.value })}
                    placeholder="e.g. 01001"
                    className="w-full bg-slate-950 border border-emerald-500/50 rounded-xl px-3 py-2 text-xs text-white font-mono focus:outline-none"
                  />
                </div>
              )}

              <div className="pt-4 border-t border-slate-800 flex justify-end gap-2 shrink-0">
                <button
                  type="button"
                  onClick={() => setIsModalOpen(false)}
                  className="bg-slate-800 hover:bg-slate-700 text-white font-bold px-4 py-2 rounded-xl text-xs"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={loading}
                  className="bg-blue-600 hover:bg-blue-500 text-white font-bold px-6 py-2 rounded-xl text-xs shadow-md shadow-blue-600/30"
                >
                  Save Product
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Barcode Label Designer Modal */}
      {labelProduct && (
        <div className="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
          <div className="bg-slate-900 border border-slate-700 rounded-3xl w-full max-w-sm p-6 shadow-2xl relative text-center">
            <button onClick={() => setLabelProduct(null)} className="absolute top-4 right-4 text-slate-400 hover:text-white">
              <X className="w-5 h-5" />
            </button>

            <h3 className="font-bold text-white text-sm mb-4">Print Barcode Shelf Label</h3>

            <div className="bg-white text-black p-4 rounded-xl shadow-lg inline-block border border-gray-300 w-full mb-4">
              <div className="text-xs font-black truncate">{labelProduct.name}</div>
              <div className="text-[10px] text-gray-600 font-mono">{labelProduct.sku}</div>
              <div className="my-2 bg-black text-white font-mono text-[11px] py-1 tracking-widest font-bold">
                * {labelProduct.barcode || labelProduct.sku} *
              </div>
              <div className="text-xl font-black text-black">
                ${Number(labelProduct.selling_price).toFixed(2)}
              </div>
            </div>

            <button
              onClick={() => window.print()}
              className="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-3 rounded-xl text-xs flex items-center justify-center gap-2 shadow-md shadow-blue-600/30"
            >
              <Printer className="w-4 h-4" />
              <span>Print Label</span>
            </button>
          </div>
        </div>
      )}
    </div>
  );
};
