import React, { useState } from 'react';
import { X, CreditCard, Banknote, QrCode, CheckCircle2, ShieldCheck } from 'lucide-react';
import { useReception } from '../../../context/ReceptionContext';

export const PaymentModal = () => {
  const { paymentModal, closePayment, performPayment } = useReception();
  const [method, setMethod] = useState('qr'); // 'cash' | 'qr' | 'card'

  if (!paymentModal.isOpen || !paymentModal.room) return null;

  const room = paymentModal.room;
  const remaining = room.remainingAmount || (room.totalAmount - room.paidAmount);

  const formatVND = (num) => new Intl.NumberFormat('vi-VN').format(num) + 'đ';

  const handleConfirmPayment = (e) => {
    e.preventDefault();
    performPayment(room.id, remaining);
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      {/* Backdrop */}
      <div className="fixed inset-0 bg-black/60 backdrop-blur-xs transition-opacity" onClick={closePayment} />

      {/* Modal Dialog */}
      <div className="relative w-full max-w-md bg-white rounded-lg shadow-2xl border border-gray-100 p-6 sm:p-8 z-10 animate-fade-in text-[#1c2920]">
        <button
          onClick={closePayment}
          className="absolute top-5 right-5 p-2 rounded-full hover:bg-gray-100 text-gray-400 hover:text-gray-700 transition-colors"
          title="Đóng"
        >
          <X className="w-5 h-5" />
        </button>

        {/* Header */}
        <div className="mb-5">
          <span className="block text-[11px] font-bold tracking-[0.2em] text-[#865e26] uppercase mb-1">
            Quản lý thanh toán
          </span>
          <h3 className="font-serif text-2xl font-bold text-[#1a3e2d]">
            Thu tiền phòng {room.number}
          </h3>
          <p className="text-xs text-[#6C756B] mt-0.5">
            Khách hàng: <strong className="text-[#1c2920]">{room.guestName}</strong>
          </p>
        </div>

        {/* Billing details */}
        <div className="bg-[#FAF8F5] border border-[#ECE6DE] rounded-md p-4 space-y-2 text-xs mb-5">
          <div className="flex justify-between">
            <span className="text-[#6C756B]">Tổng số tiền:</span>
            <span className="font-semibold text-[#1c2920]">{formatVND(room.totalAmount)}</span>
          </div>
          <div className="flex justify-between">
            <span className="text-[#6C756B]">Đã thanh toán trước:</span>
            <span className="font-semibold text-emerald-700">{formatVND(room.paidAmount)}</span>
          </div>
          <div className="flex justify-between pt-2 border-t border-[#ECE6DE] text-sm">
            <span className="font-bold text-[#1c2920]">Số tiền cần thu:</span>
            <span className="font-bold text-rose-700 text-base">{formatVND(remaining)}</span>
          </div>
        </div>

        {/* Payment Methods */}
        <div className="space-y-3 mb-6">
          <label className="block text-xs font-semibold text-[#49544c]">
            Phương thức thanh toán
          </label>
          <div className="grid grid-cols-3 gap-2">
            <button
              type="button"
              onClick={() => setMethod('qr')}
              className={`p-3 rounded-md border text-center transition-all flex flex-col items-center gap-1.5 ${
                method === 'qr'
                  ? 'border-[#1a3e2d] bg-[#f0f6f2] text-[#1a3e2d] font-bold shadow-xs'
                  : 'border-gray-200 hover:border-gray-300 text-gray-600'
              }`}
            >
              <QrCode className="w-5 h-5 text-[#865e26]" />
              <span className="text-[11px]">Chuyển QR</span>
            </button>

            <button
              type="button"
              onClick={() => setMethod('card')}
              className={`p-3 rounded-md border text-center transition-all flex flex-col items-center gap-1.5 ${
                method === 'card'
                  ? 'border-[#1a3e2d] bg-[#f0f6f2] text-[#1a3e2d] font-bold shadow-xs'
                  : 'border-gray-200 hover:border-gray-300 text-gray-600'
              }`}
            >
              <CreditCard className="w-5 h-5 text-[#865e26]" />
              <span className="text-[11px]">Thẻ POS</span>
            </button>

            <button
              type="button"
              onClick={() => setMethod('cash')}
              className={`p-3 rounded-md border text-center transition-all flex flex-col items-center gap-1.5 ${
                method === 'cash'
                  ? 'border-[#1a3e2d] bg-[#f0f6f2] text-[#1a3e2d] font-bold shadow-xs'
                  : 'border-gray-200 hover:border-gray-300 text-gray-600'
              }`}
            >
              <Banknote className="w-5 h-5 text-[#865e26]" />
              <span className="text-[11px]">Tiền mặt</span>
            </button>
          </div>
        </div>

        {/* Footer actions */}
        <div className="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
          <button
            type="button"
            onClick={closePayment}
            className="px-5 py-2.5 rounded-md border border-[#D5CEC2] text-xs font-semibold text-[#49544c] hover:bg-gray-50 transition-colors"
          >
            Hủy
          </button>
          <button
            type="button"
            onClick={handleConfirmPayment}
            className="px-6 py-2.5 rounded-md bg-[#1a3e2d] hover:bg-[#143124] text-white text-xs font-semibold flex items-center gap-2 transition-all shadow-sm active:scale-95"
          >
            <CheckCircle2 className="w-4 h-4" />
            <span>Xác nhận thu tiền</span>
          </button>
        </div>
      </div>
    </div>
  );
};

