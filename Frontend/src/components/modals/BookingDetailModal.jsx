import React from 'react';
import { X, Calendar, MapPin, CheckCircle2, CreditCard, ShieldCheck } from 'lucide-react';
import { useBooking } from '../../context/BookingContext';

export const BookingDetailModal = () => {
  const { activeModal, modalPayload, closeModal, cancelBooking } = useBooking();

  if (activeModal !== 'bookingDetail') return null;

  const booking = modalPayload?.booking;
  if (!booking) return null;

  const formatVND = (num) => {
    return new Intl.NumberFormat('vi-VN').format(num) + 'đ';
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      {/* Backdrop */}
      <div className="fixed inset-0 bg-black/60 backdrop-blur-xs" onClick={closeModal} />

      {/* Modal Dialog */}
      <div className="relative w-full max-w-2xl bg-[#FAF8F5] text-[#1c2920] shadow-2xl rounded-none sm:rounded-md border border-[#E7E2D9] p-6 sm:p-8 z-10 animate-fade-in max-h-[90vh] overflow-y-auto">
        <button
          onClick={closeModal}
          className="absolute top-4 right-4 p-2 text-gray-500 hover:text-black"
        >
          <X className="w-5 h-5" />
        </button>

        <span className="block text-[11px] font-bold tracking-[0.2em] text-[#865e26] uppercase mb-1">
          Chi tiết đặt phòng
        </span>
        <div className="flex items-center justify-between mb-4">
          <h3 className="font-serif text-2xl font-bold text-[#1a3e2d]">
            {booking.roomTitle}
          </h3>
          <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-[#2e5d45] text-[#b3e6c9]">
            <CheckCircle2 className="w-3.5 h-3.5" />
            {booking.statusText}
          </span>
        </div>

        {/* Room banner */}
        <div className="aspect-[16/8] overflow-hidden rounded-xs mb-6 relative">
          <img
            src={booking.image}
            alt={booking.roomTitle}
            className="w-full h-full object-cover"
          />
          <div className="absolute bottom-2 left-2 bg-black/60 backdrop-blur-xs text-white text-xs px-2.5 py-1 rounded-xs flex items-center gap-1.5">
            <MapPin className="w-3.5 h-3.5 text-[#dfc28d]" />
            <span>{booking.location}</span>
          </div>
        </div>

        {/* Info Grid */}
        <div className="grid grid-cols-2 gap-4 text-xs bg-white p-4 rounded-xs border border-[#ECE6DE] mb-6">
          <div>
            <span className="text-[#6C756B] block">Mã đặt phòng:</span>
            <span className="font-bold text-[#1a3e2d] text-sm">{booking.id}</span>
          </div>
          <div>
            <span className="text-[#6C756B] block">Khách hàng:</span>
            <span className="font-semibold text-[#1c2920]">{booking.guestName}</span>
          </div>
          <div>
            <span className="text-[#6C756B] block">Thời gian:</span>
            <span className="font-semibold text-[#1c2920]">{booking.dateRangeDisplay} ({booking.nights} đêm)</span>
          </div>
          <div>
            <span className="text-[#6C756B] block">Số lượng:</span>
            <span className="font-semibold text-[#1c2920]">{booking.guests}</span>
          </div>
          <div>
            <span className="text-[#6C756B] block">Liên hệ:</span>
            <span className="text-[#1c2920]">{booking.phone} · {booking.email}</span>
          </div>
          <div>
            <span className="text-[#6C756B] block">Phương thức:</span>
            <span className="text-[#1c2920]">
              {booking.paymentMethod === 'credit_card' ? 'Thẻ ngân hàng' : 'Ví điện tử'}
            </span>
          </div>
        </div>

        {/* Special requests if any */}
        {booking.specialRequests && (
          <div className="bg-[#f5efe6] p-3 rounded-xs text-xs mb-6 text-[#525f54]">
            <span className="font-bold text-[#865e26] block mb-0.5">Yêu cầu đặc biệt:</span>
            <span>{booking.specialRequests}</span>
          </div>
        )}

        {/* Price table */}
        <div className="space-y-2 text-xs border-t border-[#ECE6DE] pt-4 mb-6">
          <div className="flex justify-between text-[#6C756B]">
            <span>Tiền phòng ({booking.nights} đêm)</span>
            <span className="font-medium text-[#1c2920]">{formatVND(booking.roomTotal || booking.totalPrice)}</span>
          </div>
          <div className="flex justify-between text-[#6C756B]">
            <span>Thuế & Phí dịch vụ (10%)</span>
            <span className="font-medium text-[#1c2920]">{formatVND(booking.taxAndFees || 0)}</span>
          </div>
          {booking.extraServices && booking.extraServices.length > 0 && (
            <div className="border-t border-dashed border-[#ECE6DE] pt-1">
              {booking.extraServices.map((s, idx) => (
                <div key={idx} className="flex justify-between text-[#6C756B]">
                  <span>+ {s.name}</span>
                  <span>{formatVND(s.price)}</span>
                </div>
              ))}
            </div>
          )}
          <div className="flex justify-between text-sm font-bold text-[#1a3e2d] pt-2 border-t border-[#ECE6DE]">
            <span>Tổng số tiền đã thanh toán</span>
            <span>{formatVND(booking.totalPrice)}</span>
          </div>
        </div>

        {/* Actions */}
        <div className="flex items-center justify-end gap-3">
          {booking.status === 'upcoming' && (
            <button
              onClick={() => {
                if (window.confirm('Bạn có chắc chắn muốn hủy đặt phòng này không?')) {
                  cancelBooking(booking.id);
                  closeModal();
                }
              }}
              className="px-4 py-2 text-xs font-semibold text-rose-700 border border-rose-300 hover:bg-rose-50 transition-colors"
            >
              Hủy đặt phòng
            </button>
          )}
          <button
            onClick={closeModal}
            className="px-5 py-2 text-xs font-semibold bg-[#1a3e2d] text-white hover:bg-[#143124] transition-colors"
          >
            Đóng
          </button>
        </div>
      </div>
    </div>
  );
};

