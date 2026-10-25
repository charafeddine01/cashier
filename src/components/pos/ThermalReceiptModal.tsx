import React from 'react';
import { CompanySettings } from '../../types';
import { HardwareService } from '../../services/hardwareService';
import { X, Printer, Usb } from 'lucide-react';

interface Props {
  isOpen: boolean;
  order: any;
  items: any[];
  settings: CompanySettings | null;
  onClose: () => void;
}

export const ThermalReceiptModal: React.FC<Props> = ({
  isOpen,
  order,
  items,
  settings,
  onClose,
}) => {
  if (!isOpen || !order) return null;

  const width = settings?.receipt_width || '80mm';
  const primarySymbol = settings?.primary_currency_symbol || '$';

  const handlePrintStandard = () => {
    window.print();
  };

  const handleEscPosRawPrint = async () => {
    const rawBytes = HardwareService.generateEscPosBytes({
      companyName: settings?.company_name || 'OmniPOS Store',
      invoiceNumber: order.invoice_number,
      dateStr: order.created_at || new Date().toISOString(),
      cashierName: order.cashier_name || 'Staff',
      items: items.map((it) => ({
        name: it.product_name || it.name,
        qty: it.quantity,
        price: it.unit_price,
        total: it.line_total,
      })),
      subtotal: Number(order.subtotal || 0),
      tax: Number(order.tax_total || 0),
      discount: Number(order.discount_total || 0),
      grandTotal: Number(order.grand_total || 0),
      paidCash: Number(order.paid_cash || 0),
      changeGiven: Number(order.change_given || 0),
      footerText: settings?.receipt_footer || 'Thank You!',
      paperWidth: width === '58mm' ? '58mm' : '80mm',
      kickDrawer: true,
      cutPaper: true,
    });

    await HardwareService.printEscPosSerial(rawBytes);
  };

  return (
    <div className="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
      <div className="bg-slate-900 border border-slate-700 rounded-3xl w-full max-w-md p-6 shadow-2xl relative flex flex-col max-h-[90vh]">
        {/* Modal Controls */}
        <div className="flex items-center justify-between pb-4 border-b border-slate-800 shrink-0">
          <div className="flex items-center gap-2">
            <Printer className="w-5 h-5 text-blue-400" />
            <h3 className="font-bold text-white text-base">Receipt Preview</h3>
          </div>
          <button
            onClick={onClose}
            className="p-1 rounded-full text-slate-400 hover:text-white hover:bg-slate-800 transition"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Thermal Receipt Paper Viewport */}
        <div className="flex-1 overflow-y-auto py-4 flex justify-center">
          <div
            id="receipt-print-area"
            className="bg-white text-black font-mono p-5 text-xs shadow-2xl rounded-sm leading-tight"
            style={{ width: width === '58mm' ? '280px' : '360px' }}
          >
            {/* Logo if present */}
            {settings?.receipt_logo_url && (
              <div className="text-center mb-3">
                <img
                  src={settings.receipt_logo_url}
                  alt="Receipt Logo"
                  className="max-h-12 mx-auto grayscale contrast-200"
                />
              </div>
            )}

            {/* Header */}
            <div className="text-center border-b border-black pb-2 mb-2">
              <h1 className="font-black text-sm uppercase tracking-wide">
                {settings?.company_name || 'OmniPOS Pro'}
              </h1>
              {settings?.address && <p className="text-[10px] mt-0.5">{settings.address}</p>}
              {settings?.phone && <p className="text-[10px]">Tel: {settings.phone}</p>}
              {settings?.tax_id && <p className="text-[10px] font-bold">Tax ID: {settings.tax_id}</p>}
            </div>

            {/* Invoice Meta */}
            <div className="border-b border-black pb-2 mb-2 text-[10px] space-y-0.5">
              <div className="flex justify-between">
                <span>INVOICE:</span>
                <span className="font-bold">{order.invoice_number}</span>
              </div>
              <div className="flex justify-between">
                <span>DATE:</span>
                <span>{order.created_at || new Date().toLocaleString()}</span>
              </div>
              <div className="flex justify-between">
                <span>TERMINAL:</span>
                <span>{order.terminal_id || 'TERM-01'}</span>
              </div>
            </div>

            {/* Items */}
            <div className="border-b border-black pb-2 mb-2 space-y-1.5">
              {items.map((it, idx) => (
                <div key={idx} className="flex justify-between items-start text-[11px]">
                  <div className="flex-1 pr-2">
                    <span className="font-bold">{it.product_name || it.name}</span>
                    <div className="text-[9px] text-gray-700">
                      {it.quantity} x {primarySymbol}{Number(it.unit_price).toFixed(2)}
                    </div>
                  </div>
                  <div className="font-bold shrink-0">
                    {primarySymbol}{Number(it.line_total).toFixed(2)}
                  </div>
                </div>
              ))}
            </div>

            {/* Totals */}
            <div className="border-b border-black pb-2 mb-2 space-y-1 text-xs">
              <div className="flex justify-between">
                <span>Subtotal:</span>
                <span>{primarySymbol}{Number(order.subtotal || 0).toFixed(2)}</span>
              </div>
              {order.discount_total > 0 && (
                <div className="flex justify-between">
                  <span>Discount:</span>
                  <span>-{primarySymbol}{Number(order.discount_total).toFixed(2)}</span>
                </div>
              )}
              <div className="flex justify-between">
                <span>Tax:</span>
                <span>{primarySymbol}{Number(order.tax_total || 0).toFixed(2)}</span>
              </div>
              <div className="flex justify-between font-black text-sm pt-1 border-t border-black">
                <span>TOTAL:</span>
                <span>{primarySymbol}{Number(order.grand_total || 0).toFixed(2)}</span>
              </div>
            </div>

            {/* Payments */}
            <div className="border-b border-black pb-2 mb-2 text-[10px] space-y-0.5">
              {order.paid_cash > 0 && (
                <div className="flex justify-between">
                  <span>Cash Paid:</span>
                  <span>{primarySymbol}{Number(order.paid_cash).toFixed(2)}</span>
                </div>
              )}
              {order.paid_card > 0 && (
                <div className="flex justify-between">
                  <span>Card Paid:</span>
                  <span>{primarySymbol}{Number(order.paid_card).toFixed(2)}</span>
                </div>
              )}
              {order.change_given > 0 && (
                <div className="flex justify-between font-bold">
                  <span>Change Due:</span>
                  <span>{primarySymbol}{Number(order.change_given).toFixed(2)}</span>
                </div>
              )}
            </div>

            {/* Barcode representation */}
            <div className="text-center my-3">
              <div className="font-mono tracking-widest text-[11px] font-bold py-1 bg-black text-white inline-block px-3 rounded-sm">
                * {order.invoice_number} *
              </div>
            </div>

            {/* Footer Notice */}
            <div className="text-center text-[9px] text-gray-700 space-y-0.5">
              <p>{settings?.receipt_footer || 'Thank you for your patronage!'}</p>
              <p>Powered by OmniPOS Pro</p>
            </div>
          </div>
        </div>

        {/* Print Buttons */}
        <div className="pt-4 border-t border-slate-800 grid grid-cols-2 gap-2 shrink-0">
          <button
            onClick={handlePrintStandard}
            className="bg-blue-600 hover:bg-blue-500 text-white font-bold py-3 rounded-2xl transition text-xs flex items-center justify-center gap-2 shadow-lg shadow-blue-600/30"
          >
            <Printer className="w-4 h-4" />
            <span>Print System [Ctrl+P]</span>
          </button>

          <button
            onClick={handleEscPosRawPrint}
            className="bg-slate-800 hover:bg-slate-700 border border-slate-700 text-white font-bold py-3 rounded-2xl transition text-xs flex items-center justify-center gap-2"
            title="Send raw ESC/POS command over USB/Serial with cash drawer kick"
          >
            <Usb className="w-4 h-4 text-emerald-400" />
            <span>ESC/POS + Drawer</span>
          </button>
        </div>
      </div>
    </div>
  );
};
