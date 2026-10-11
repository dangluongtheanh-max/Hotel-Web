import React, { useState, useEffect } from 'react';
import { X, LogOut, AlertCircle, CheckCircle2 } from 'lucide-react';
import { useReception } from '../../../context/ReceptionContext';

export const CheckOutModal = () => {
  const { checkOutModal, closeCheckOut, performCheckOut, rooms } = useReception();

  // Rooms currently occupied
  const occupiedRooms = rooms.filter((r) => r.status === 'occupied');

  const [selectedRoomId, setSelectedRoomId] = useState('');

  useEffect(() => {
    if (checkOutModal.isOpen) {
      if (checkOutModal.preselectedRoomId) {
        setSelectedRoomId(checkOutModal.preselectedRoomId);
      } else if (occupiedRooms.length > 0) {
        setSelectedRoomId(occupiedRooms[0].id);
      } else {
        setSelectedRoomId('');
      }
    }
  }, [checkOutModal]);

  if (!checkOutModal.isOpen) return null;

  const currentRoom = rooms.find((r) => r.id === selectedRoomId);

  const formatVND = (num) => new Intl.NumberFormat('vi-VN').format(num) + 'đ';

  const handleConfirmCheckout = () => {
    if (!selectedRoomId) return;
    performCheckOut(selectedRoomId);
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      {/* Backdrop */}
      <div className="fixed inset-0 bg-black/60 backdrop-blur-xs transition-opacity" onClick={closeCheckOut} />

      {/* Modal Dialog */}
      <div className="relative w-full max-w-lg bg-white rounded-lg shadow-2xl border border-gray-100 p-6 sm:p-8 z-10 animate-fade-in text-[#1c2920]">
        <button
          onClick={closeCheckOut}
          className="absolute top-5 right-5 p-2 rounded-full hover:bg-gray-100 text-gray-400 hover:text-gray-700 transition-colors"
          title="Đóng"
        >
          <X className="w-5 h-5" />
        </button>

        {/* Header */}
        <div className="mb-5">
          <span className="block text-[11px] font-bold tracking-[0.2em] text-[#865e26] uppercase mb-1">
            Nghiệp vụ trả phòng
          </span>
          <h3 className="font-serif text-2xl font-bold text-[#1a3e2d]">
            Check-out phòng khách
          </h3>
          <p className="text-xs text-[#6C756B] mt-0.5">
            Chọn phòng đang có khách để kiểm tra thanh toán và hoàn tất trả phòng
          </p>
        </div>

        {occupiedRooms.length === 0 ? (
          <div className="py-8 text-center text-gray-500">
            <p className="text-sm">Hiện tại không có phòng nào đang có khách lưu trú.</p>
          </div>
        ) : (
          <div className="space-y-4">
            {/* Select Room */}
            <div>
              <label className="block text-xs font-semibold text-[#49544c] mb-1.5">
                Chọn phòng đang có khách
              </label>
              <select
                value={selectedRoomId}
                onChange={(e) => setSelectedRoomId(e.target.value)}
                className="w-full bg-[#FAF8F5] border border-[#D5CEC2] rounded-md px-3.5 py-2.5 text-xs sm:text-sm text-[#1c2920] focus:outline-none focus:border-[#1a3e2d] cursor-pointer"
              >
                {occupiedRooms.map((r) => (
                  <option key={r.id} value={r.id}>
                    Phòng {r.number} - {r.type} (Khách: {r.guestName})
                  </option>
                ))}
              </select>
            </div>

            {/* Room & Guest Details Summary */}
            {currentRoom && (
              <div className="bg-[#FAF8F5] border border-[#ECE6DE] rounded-md p-4 space-y-2.5 text-xs">
                <div className="flex justify-between">
                  <span className="text-[#6C756B]">Khách hàng:</span>
                  <span className="font-bold text-[#1c2920]">{currentRoom.guestName}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-[#6C756B]">Số điện thoại:</span>
                  <span className="font-medium text-[#1c2920]">{currentRoom.phone || '090 123 4567'}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-[#6C756B]">Mã đặt phòng:</span>
                  <span className="font-semibold text-[#865e26]">{currentRoom.bookingId}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-[#6C756B]">Tổng chi phí phòng:</span>
                  <span className="font-semibold text-[#1c2920]">{formatVND(currentRoom.totalAmount)}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-[#6C756B]">Đã thanh toán / đặt cọc:</span>
                  <span className="font-medium text-emerald-700">{formatVND(currentRoom.paidAmount)}</span>
                </div>
                <div className="flex justify-between pt-2 border-t border-[#ECE6DE] text-sm">
                  <span className="font-bold text-[#1c2920]">Còn lại cần thanh toán:</span>
                  <span className={`font-bold ${currentRoom.remainingAmount > 0 ? 'text-rose-700' : 'text-emerald-700'}`}>
                    {formatVND(currentRoom.remainingAmount)}
                  </span>
                </div>

                {/* Status Alert */}
                {currentRoom.remainingAmount > 0 ? (
                  <div className="mt-3 p-2.5 bg-rose-50 border border-rose-200 rounded text-rose-800 text-[11px] flex items-center gap-2">
                    <AlertCircle className="w-4 h-4 shrink-0 text-rose-600" />
                    <span>Phòng chưa thanh toán đủ. Hệ thống sẽ tự động mở hộp thoại thu tiền!</span>
                  </div>
                ) : (
                  <div className="mt-3 p-2.5 bg-emerald-50 border border-emerald-200 rounded text-emerald-800 text-[11px] flex items-center gap-2">
                    <CheckCircle2 className="w-4 h-4 shrink-0 text-emerald-600" />
                    <span>Hóa đơn đã được thanh toán toàn bộ. Có thể trả phòng ngay!</span>
                  </div>
                )}
              </div>
            )}

            {/* Action buttons */}
            <div className="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
              <button
                type="button"
                onClick={closeCheckOut}
                className="px-5 py-2.5 rounded-md border border-[#D5CEC2] text-xs font-semibold text-[#49544c] hover:bg-gray-50 transition-colors"
              >
                Hủy
              </button>
              <button
                type="button"
                onClick={handleConfirmCheckout}
                className="px-6 py-2.5 rounded-md bg-[#1a3e2d] hover:bg-[#143124] text-white text-xs font-semibold flex items-center gap-2 transition-all shadow-sm active:scale-95"
              >
                <LogOut className="w-4 h-4" />
                <span>Tiến hành trả phòng</span>
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

