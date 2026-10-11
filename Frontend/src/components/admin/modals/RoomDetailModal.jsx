import React, { useState, useEffect } from 'react';
import { X, Bed, Calendar, Phone, CheckCircle2, RefreshCw, AlertTriangle, ShieldCheck } from 'lucide-react';
import { useReception } from '../../../context/ReceptionContext';

export const RoomDetailModal = () => {
  const { roomDetailModal, closeRoomDetail, updateRoomStatus, openCheckIn, openCheckOut } = useReception();
  const room = roomDetailModal.room;

  const [currentStatus, setCurrentStatus] = useState(room?.status || 'available');

  useEffect(() => {
    if (room) {
      setCurrentStatus(room.status);
    }
  }, [room]);

  if (!roomDetailModal.isOpen || !room) return null;

  const formatVND = (num) => new Intl.NumberFormat('vi-VN').format(num) + 'đ';

  const statusOptions = [
    { key: 'available', label: 'Trống / Sẵn sàng', color: 'bg-emerald-50 text-emerald-800 border-emerald-300 hover:bg-emerald-100', dot: 'bg-emerald-500' },
    { key: 'cleaning', label: 'Đang dọn dẹp', color: 'bg-amber-50 text-amber-800 border-amber-300 hover:bg-amber-100', dot: 'bg-amber-500' },
    { key: 'occupied', label: 'Có khách', color: 'bg-rose-50 text-rose-800 border-rose-300 hover:bg-rose-100', dot: 'bg-rose-500' },
    { key: 'maintenance', label: 'Bảo trì', color: 'bg-gray-100 text-gray-700 border-gray-300 hover:bg-gray-200', dot: 'bg-gray-400' }
  ];

  const handleStatusChange = (newStat) => {
    // Nếu phòng đang trống (hoặc chưa có thông tin khách) mà chọn "Có khách", buộc hiện Modal Check-in
    if (newStat === 'occupied' && (room.status === 'available' || !room.guestName)) {
      closeRoomDetail();
      openCheckIn(null, room.id);
      return;
    }
    setCurrentStatus(newStat);
    updateRoomStatus(room.id, newStat);
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      {/* Backdrop */}
      <div className="fixed inset-0 bg-black/60 backdrop-blur-xs transition-opacity" onClick={closeRoomDetail} />

      {/* Modal Dialog */}
      <div className="relative w-full max-w-lg bg-white rounded-lg shadow-2xl border border-gray-100 p-6 sm:p-8 z-10 animate-fade-in text-[#1c2920]">
        <button
          onClick={closeRoomDetail}
          className="absolute top-5 right-5 p-2 rounded-full hover:bg-gray-100 text-gray-400 hover:text-gray-700 transition-colors"
          title="Đóng"
        >
          <X className="w-5 h-5" />
        </button>

        {/* Header */}
        <div className="mb-5 flex items-start justify-between pr-8">
          <div>
            <span className="block text-[11px] font-bold tracking-[0.2em] text-[#865e26] uppercase mb-1">
              Chi tiết phòng
            </span>
            <h3 className="font-serif text-2xl font-bold text-[#1a3e2d] flex items-center gap-3">
              <span>Phòng {room.number}</span>
              <span className="text-xs font-sans font-semibold px-2.5 py-0.5 rounded bg-[#f5efe6] text-[#865e26] border border-[#e5dcce]">
                Tầng {room.floor}
              </span>
            </h3>
            <p className="text-xs text-[#6C756B] mt-0.5">{room.type} · {formatVND(room.pricePerNight)} / đêm</p>
          </div>
        </div>

        {/* StatusToggleGroup (User Story 13) */}
        <div className="mb-6">
          <label className="block text-xs font-bold text-[#49544c] mb-2 uppercase tracking-wider">
            Chuyển đổi trạng thái phòng (StatusToggleGroup)
          </label>
          <div className="grid grid-cols-2 gap-2">
            {statusOptions.map((opt) => (
              <button
                key={opt.key}
                type="button"
                onClick={() => handleStatusChange(opt.key)}
                className={`p-2.5 rounded-md border text-xs font-semibold flex items-center justify-start gap-2.5 transition-all ${opt.color} ${
                  currentStatus === opt.key
                    ? 'ring-2 ring-[#1a3e2d] font-bold shadow-xs'
                    : 'opacity-70 hover:opacity-100'
                }`}
              >
                <span className={`w-2 h-2 rounded-full ${opt.dot}`} />
                <span>{opt.label}</span>
                {opt.key === 'occupied' && room.status === 'available' && (
                  <span className="text-[10px] text-rose-700 bg-rose-100/90 px-1.5 py-0.5 rounded font-semibold ml-auto">
                    Check-in
                  </span>
                )}
                {currentStatus === opt.key && <CheckCircle2 className="w-3.5 h-3.5 ml-auto text-[#1a3e2d]" />}
              </button>
            ))}
          </div>
        </div>

        {/* Guest info if occupied */}
        {room.status === 'occupied' && (
          <div className="bg-[#FAF8F5] border border-[#ECE6DE] rounded-md p-4 space-y-2 text-xs mb-6">
            <div className="flex items-center justify-between pb-2 border-b border-[#ECE6DE]">
              <span className="font-bold text-[#1a3e2d]">Khách đang lưu trú:</span>
              <span className="font-bold text-sm text-[#1c2920]">{room.guestName}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-[#6C756B]">Mã đặt phòng:</span>
              <span className="font-semibold text-[#865e26]">{room.bookingId}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-[#6C756B]">Số điện thoại:</span>
              <span className="text-[#1c2920]">{room.phone || '090 123 4567'}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-[#6C756B]">Dự kiến trả phòng:</span>
              <span className="text-[#1c2920]">{room.expectedCheckOut}</span>
            </div>
            <div className="flex justify-between pt-1">
              <span className="text-[#6C756B]">Trạng thái thanh toán:</span>
              <span className={`font-bold ${room.paymentStatus === 'PAID' ? 'text-emerald-700' : 'text-rose-700'}`}>
                {room.paymentStatus === 'PAID' ? 'Đã thanh toán đủ' : `Còn nợ ${formatVND(room.remainingAmount)}`}
              </span>
            </div>
          </div>
        )}

        {/* Room notes */}
        <div className="text-xs text-[#525f54] bg-[#fdfbf7] p-3 rounded border border-gray-100 mb-6">
          <span className="font-bold text-[#865e26] block mb-1">Ghi chú buồng phòng:</span>
          <p>{room.notes || 'Không có ghi chú đặc biệt.'}</p>
        </div>

        {/* Quick action buttons */}
        <div className="flex items-center justify-between gap-3 pt-3 border-t border-gray-100">
          <div>
            {room.status === 'available' && (
              <button
                type="button"
                onClick={() => {
                  closeRoomDetail();
                  openCheckIn(null, room.id);
                }}
                className="px-4 py-2 bg-[#1a3e2d] text-white text-xs font-semibold rounded hover:bg-[#143124] transition-colors"
              >
                Nhận phòng này
              </button>
            )}
            {room.status === 'occupied' && (
              <button
                type="button"
                onClick={() => {
                  closeRoomDetail();
                  openCheckOut(room.id);
                }}
                className="px-4 py-2 bg-rose-700 text-white text-xs font-semibold rounded hover:bg-rose-800 transition-colors"
              >
                Trả phòng này
              </button>
            )}
          </div>

          <button
            type="button"
            onClick={closeRoomDetail}
            className="px-5 py-2 rounded border border-[#D5CEC2] text-xs font-semibold text-[#49544c] hover:bg-gray-50 transition-colors"
          >
            Đóng
          </button>
        </div>
      </div>
    </div>
  );
};

