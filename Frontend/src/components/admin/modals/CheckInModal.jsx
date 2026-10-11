import React, { useState, useEffect } from 'react';
import { X, Sparkles, Check, ChevronDown } from 'lucide-react';
import { useReception } from '../../../context/ReceptionContext';

export const CheckInModal = () => {
  const { checkInModal, closeCheckIn, performCheckIn, rooms } = useReception();

  const [guestName, setGuestName] = useState('');
  const [cccd, setCccd] = useState('');
  const [phone, setPhone] = useState('');
  const [selectedRoomId, setSelectedRoomId] = useState('');
  const [bookingId, setBookingId] = useState('');

  // Available rooms for dropdown (plus preselected room if specified)
  const availableRooms = rooms.filter(
    (r) => r.status === 'available' || r.id === checkInModal.preselectedRoom
  );

  useEffect(() => {
    if (checkInModal.isOpen) {
      if (checkInModal.arrivalData) {
        setGuestName(checkInModal.arrivalData.guestName || '');
        setCccd(checkInModal.arrivalData.cccd || '079201001234');
        setPhone(checkInModal.arrivalData.phone || '');
        setBookingId(checkInModal.arrivalData.bookingId || '');
        setSelectedRoomId(checkInModal.arrivalData.roomId || (availableRooms[0]?.id || ''));
      } else {
        setGuestName('');
        setCccd('');
        setPhone('');
        setBookingId(`BK-${new Date().toLocaleDateString('vi-VN').replace(/\//g, '')}-${Math.floor(100 + Math.random() * 900)}`);
        setSelectedRoomId(checkInModal.preselectedRoom || (availableRooms[0]?.id || ''));
      }
    }
  }, [checkInModal]);

  if (!checkInModal.isOpen) return null;

  const handleSubmit = (e) => {
    e.preventDefault();
    if (!selectedRoomId) {
      alert('Vui lòng chọn một phòng còn trống để bàn giao.');
      return;
    }
    performCheckIn({
      roomId: selectedRoomId,
      guestName,
      cccd,
      phone,
      bookingId,
      arrivalId: checkInModal.arrivalData?.id
    });
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      {/* Backdrop */}
      <div className="fixed inset-0 bg-black/60 backdrop-blur-xs transition-opacity" onClick={closeCheckIn} />

      {/* Modal Dialog */}
      <div className="relative w-full max-w-xl bg-white rounded-lg shadow-2xl border border-gray-100 p-6 sm:p-8 z-10 animate-fade-in text-[#1c2920]">
        {/* Close Button */}
        <button
          onClick={closeCheckIn}
          className="absolute top-5 right-5 p-2 rounded-full hover:bg-gray-100 text-gray-400 hover:text-gray-700 transition-colors"
          title="Đóng"
        >
          <X className="w-5 h-5" />
        </button>

        {/* Header */}
        <div className="mb-5">
          <span className="block text-[11px] font-bold tracking-[0.2em] text-[#865e26] uppercase mb-1">
            Nhận phòng
          </span>
          <h3 className="font-serif text-2xl font-bold text-[#1a3e2d]">
            Check-in cho khách
          </h3>
          <p className="text-xs text-[#6C756B] mt-0.5">
            Xác minh thông tin và bàn giao phòng {selectedRoomId ? `phòng ${selectedRoomId}` : ''}
          </p>
        </div>

        {/* Notice alert */}
        <div className="bg-[#f0f6f2] border border-[#b8d6be] rounded-md p-3.5 mb-6 flex items-start gap-3 text-xs text-[#1a3e2d]">
          <Sparkles className="w-4 h-4 text-[#865e26] shrink-0 mt-0.5" />
          <div>
            Booking <strong className="font-bold text-[#1a3e2d]">{bookingId || 'BK-250618-042'}</strong> đã được tìm thấy và sẵn sàng nhận phòng.
          </div>
        </div>

        <form onSubmit={handleSubmit} className="space-y-4">
          {/* Guest Name */}
          <div>
            <label className="block text-xs font-semibold text-[#49544c] mb-1.5">
              Họ và tên khách
            </label>
            <input
              type="text"
              required
              value={guestName}
              onChange={(e) => setGuestName(e.target.value)}
              placeholder="Nguyễn Hoàng Nam"
              className="w-full bg-[#FAF8F5] border border-[#D5CEC2] rounded-md px-3.5 py-2.5 text-xs sm:text-sm text-[#1c2920] focus:outline-none focus:border-[#1a3e2d] focus:bg-white transition-colors"
            />
          </div>

          {/* CCCD & Phone in 2 columns */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-semibold text-[#49544c] mb-1.5">
                Số CCCD / Hộ chiếu
              </label>
              <input
                type="text"
                required
                value={cccd}
                onChange={(e) => setCccd(e.target.value)}
                placeholder="079201001234"
                className="w-full bg-[#FAF8F5] border border-[#D5CEC2] rounded-md px-3.5 py-2.5 text-xs sm:text-sm text-[#1c2920] focus:outline-none focus:border-[#1a3e2d] focus:bg-white transition-colors"
              />
            </div>

            <div>
              <label className="block text-xs font-semibold text-[#49544c] mb-1.5">
                Số điện thoại
              </label>
              <input
                type="tel"
                required
                value={phone}
                onChange={(e) => setPhone(e.target.value)}
                placeholder="0903 456 789"
                className="w-full bg-[#FAF8F5] border border-[#D5CEC2] rounded-md px-3.5 py-2.5 text-xs sm:text-sm text-[#1c2920] focus:outline-none focus:border-[#1a3e2d] focus:bg-white transition-colors"
              />
            </div>
          </div>

          {/* SelectDropdown for Room assignment */}
          <div>
            <label className="block text-xs font-semibold text-[#49544c] mb-1.5">
              Phòng bàn giao
            </label>
            <div className="relative">
              <select
                value={selectedRoomId}
                onChange={(e) => setSelectedRoomId(e.target.value)}
                className="w-full bg-[#FAF8F5] border border-[#D5CEC2] rounded-md px-3.5 py-2.5 text-xs sm:text-sm text-[#1c2920] appearance-none focus:outline-none focus:border-[#1a3e2d] focus:bg-white transition-colors cursor-pointer"
              >
                {availableRooms.map((r) => (
                  <option key={r.id} value={r.id}>
                    {r.number} · {r.type} · Tầng {r.floor} (Sẵn sàng)
                  </option>
                ))}
              </select>
              <ChevronDown className="w-4 h-4 text-[#865e26] absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" />
            </div>
            {availableRooms.length === 0 && (
              <p className="text-[11px] text-amber-600 mt-1">Hiện tại không có phòng nào ở trạng thái "Trống".</p>
            )}
          </div>

          {/* Footer buttons */}
          <div className="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
            <button
              type="button"
              onClick={closeCheckIn}
              className="px-5 py-2.5 rounded-md border border-[#D5CEC2] text-xs font-semibold text-[#49544c] hover:bg-gray-50 transition-colors"
            >
              Hủy
            </button>
            <button
              type="submit"
              className="px-6 py-2.5 rounded-md bg-[#1a3e2d] hover:bg-[#143124] text-white text-xs font-semibold flex items-center gap-2 transition-all shadow-sm active:scale-95"
            >
              <Check className="w-4 h-4" />
              <span>Xác nhận check-in</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};

