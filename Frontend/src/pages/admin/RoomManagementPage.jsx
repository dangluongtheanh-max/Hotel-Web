import React, { useState } from 'react';
import { Bed, Users, Filter, CheckCircle2, Clock, AlertCircle, Wrench, Search } from 'lucide-react';
import { useReception } from '../../context/ReceptionContext';

export const RoomManagementPage = () => {
  const { rooms, openRoomDetail, openCheckIn, openCheckOut } = useReception();

  const [activeFloor, setActiveFloor] = useState('all');
  const [activeStatus, setActiveStatus] = useState('all');
  const [searchRoom, setSearchRoom] = useState('');

  const formatVND = (num) => new Intl.NumberFormat('vi-VN').format(num) + 'đ';

  const filteredRooms = rooms.filter((r) => {
    if (activeFloor !== 'all' && r.floor.toString() !== activeFloor) return false;
    if (activeStatus !== 'all' && r.status !== activeStatus) return false;
    if (searchRoom.trim()) {
      const q = searchRoom.toLowerCase();
      return (
        r.number.toLowerCase().includes(q) ||
        r.type.toLowerCase().includes(q) ||
        (r.guestName && r.guestName.toLowerCase().includes(q))
      );
    }
    return true;
  });

  return (
    <div className="space-y-6 animate-fade-in">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <span className="block text-[11px] font-bold tracking-[0.2em] text-[#865e26] uppercase mb-1">
            Sơ đồ phòng chi tiết
          </span>
          <h1 className="font-serif text-2xl sm:text-3xl font-bold text-[#1c2920]">
            Quản Lý Danh Sách Phòng
          </h1>
          <p className="text-xs sm:text-sm text-[#6C756B] mt-0.5">
            Nhấp vào bất kỳ phòng nào để xem chi tiết hoặc thay đổi trạng thái dọn dẹp / bảo trì.
          </p>
        </div>

        {/* Quick Search */}
        <div className="relative w-full sm:w-64">
          <Search className="w-3.5 h-3.5 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
          <input
            type="text"
            value={searchRoom}
            onChange={(e) => setSearchRoom(e.target.value)}
            placeholder="Tìm theo số phòng, tên khách..."
            className="w-full bg-white border border-[#D5CEC2] rounded-md pl-9 pr-3 py-2 text-xs text-[#1c2920] focus:outline-none focus:border-[#1a3e2d]"
          />
        </div>
      </div>

      {/* Filter Tabs: Tầng & Trạng thái */}
      <div className="bg-white border border-[#E7E2D9] rounded-lg p-4 flex flex-wrap items-center justify-between gap-4 shadow-2xs">
        {/* Floor Filter */}
        <div className="flex items-center gap-2 text-xs">
          <span className="font-bold text-[#865e26] mr-1">Tầng:</span>
          {['all', '1', '2'].map((fl) => (
            <button
              key={fl}
              onClick={() => setActiveFloor(fl)}
              className={`px-3 py-1.5 rounded-md font-semibold transition-all ${
                activeFloor === fl
                  ? 'bg-[#1a3e2d] text-white shadow-2xs'
                  : 'bg-[#FAF8F5] text-gray-600 hover:bg-gray-100'
              }`}
            >
              {fl === 'all' ? 'Tất cả tầng' : `Tầng ${fl}`}
            </button>
          ))}
        </div>

        {/* Status Filter */}
        <div className="flex items-center gap-2 text-xs">
          <span className="font-bold text-[#865e26] mr-1">Trạng thái:</span>
          {[
            { key: 'all', label: 'Tất cả' },
            { key: 'available', label: 'Trống / Sẵn sàng' },
            { key: 'cleaning', label: 'Đang dọn dẹp' },
            { key: 'occupied', label: 'Có khách' },
          ].map((st) => (
            <button
              key={st.key}
              onClick={() => setActiveStatus(st.key)}
              className={`px-3 py-1.5 rounded-md font-semibold transition-all ${
                activeStatus === st.key
                  ? 'bg-[#235c40] text-white shadow-2xs'
                  : 'bg-[#FAF8F5] text-gray-600 hover:bg-gray-100'
              }`}
            >
              {st.label}
            </button>
          ))}
        </div>
      </div>

      {/* Detailed Room Cards Grid (User Story 12) */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        {filteredRooms.map((room) => {
          const isAvailable = room.status === 'available';
          const isCleaning = room.status === 'cleaning';
          const isOccupied = room.status === 'occupied';

          return (
            <div
              key={room.id}
              onClick={() => openRoomDetail(room)}
              className="bg-white border border-[#E7E2D9] rounded-lg p-5 shadow-xs hover:shadow-md transition-all cursor-pointer flex flex-col justify-between group hover:border-[#b88d43]"
            >
              <div>
                {/* Top: Room Number & Badge */}
                <div className="flex items-center justify-between pb-3 border-b border-gray-100 mb-3">
                  <div className="flex items-baseline gap-2">
                    <span className="font-serif text-2xl font-bold text-[#1c2920] group-hover:text-[#1a3e2d]">
                      Phòng {room.number}
                    </span>
                    <span className="text-[11px] text-[#788579] font-medium">Tầng {room.floor}</span>
                  </div>

                  {/* Status Badge */}
                  {isAvailable && (
                    <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">
                      <span className="w-1.5 h-1.5 rounded-full bg-emerald-500" />
                      Trống
                    </span>
                  )}
                  {isCleaning && (
                    <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-amber-50 text-amber-800 border border-amber-200">
                      <span className="w-1.5 h-1.5 rounded-full bg-amber-500" />
                      Đang dọn
                    </span>
                  )}
                  {isOccupied && (
                    <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-rose-50 text-rose-800 border border-rose-200">
                      <span className="w-1.5 h-1.5 rounded-full bg-rose-500" />
                      Có khách
                    </span>
                  )}
                </div>

                {/* Room specs */}
                <div className="space-y-1.5 text-xs text-[#525f54] mb-4">
                  <div className="flex justify-between">
                    <span className="text-[#6C756B]">Loại phòng:</span>
                    <span className="font-bold text-[#1c2920]">{room.type}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-[#6C756B]">Đơn giá:</span>
                    <span className="font-semibold text-[#1a3e2d]">{formatVND(room.pricePerNight)} / đêm</span>
                  </div>

                  {isOccupied && (
                    <>
                      <div className="flex justify-between pt-1 border-t border-dashed border-gray-200">
                        <span className="text-[#6C756B]">Khách lưu trú:</span>
                        <span className="font-bold text-[#1c2920]">{room.guestName}</span>
                      </div>
                      <div className="flex justify-between">
                        <span className="text-[#6C756B]">Mã booking:</span>
                        <span className="font-mono text-[#865e26] font-semibold">{room.bookingId}</span>
                      </div>
                      <div className="flex justify-between">
                        <span className="text-[#6C756B]">Dự kiến trả:</span>
                        <span className="text-gray-700">{room.expectedCheckOut}</span>
                      </div>
                    </>
                  )}

                  {isCleaning && (
                    <div className="flex justify-between text-amber-700 pt-1">
                      <span>Tiến độ buồng phòng:</span>
                      <span className="font-medium">{room.expectedCheckOut}</span>
                    </div>
                  )}

                  {isAvailable && (
                    <div className="flex justify-between text-emerald-700 pt-1">
                      <span>Tình trạng:</span>
                      <span className="font-medium">Sẵn sàng nhận khách</span>
                    </div>
                  )}
                </div>
              </div>

              {/* Bottom Actions */}
              <div className="pt-3 border-t border-gray-100 flex items-center justify-between text-xs">
                <span className="text-[#865e26] font-semibold group-hover:underline">
                  Xem chi tiết & đổi trạng thái →
                </span>

                {isAvailable && (
                  <button
                    onClick={(e) => {
                      e.stopPropagation();
                      openCheckIn(null, room.id);
                    }}
                    className="px-3 py-1 bg-[#1a3e2d] text-white rounded text-[11px] font-semibold hover:bg-[#143124]"
                  >
                    Check-in
                  </button>
                )}
                {isOccupied && (
                  <button
                    onClick={(e) => {
                      e.stopPropagation();
                      openCheckOut(room.id);
                    }}
                    className="px-3 py-1 bg-rose-700 text-white rounded text-[11px] font-semibold hover:bg-rose-800"
                  >
                    Check-out
                  </button>
                )}
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
};

