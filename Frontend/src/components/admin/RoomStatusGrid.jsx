import React, { useState } from 'react';
import { RoomMiniCard } from './RoomMiniCard';
import { useReception } from '../../context/ReceptionContext';

export const RoomStatusGrid = () => {
  const { rooms } = useReception();
  const [filter, setFilter] = useState('all'); // 'all' | 'available' | 'cleaning' | 'occupied'

  // Counts
  const availableCount = rooms.filter((r) => r.status === 'available').length;
  const cleaningCount = rooms.filter((r) => r.status === 'cleaning').length;
  const occupiedCount = rooms.filter((r) => r.status === 'occupied').length;

  const filteredRooms = rooms.filter((r) => {
    if (filter === 'all') return true;
    return r.status === filter;
  });

  return (
    <div className="bg-white border border-[#E7E2D9] rounded-lg p-5 sm:p-6 shadow-xs">
      {/* Header & Filter Controls */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-5 border-b border-gray-100">
        <div>
          <h3 className="font-serif text-lg sm:text-xl font-bold text-[#1c2920]">
            Tình trạng phòng
          </h3>
          <p className="text-xs text-[#6C756B] mt-0.5">
            Cập nhật theo thời gian thực
          </p>
        </div>

        {/* Filter Pills */}
        <div className="flex items-center gap-1.5 overflow-x-auto pb-1 sm:pb-0">
          <button
            onClick={() => setFilter('all')}
            className={`px-3 py-1.5 rounded-md text-xs font-semibold transition-all ${
              filter === 'all'
                ? 'bg-[#1a3e2d] text-white'
                : 'bg-[#FAF8F5] text-[#525f54] hover:bg-gray-100'
            }`}
          >
            Tất cả
          </button>
          <button
            onClick={() => setFilter('available')}
            className={`px-3 py-1.5 rounded-md text-xs font-semibold transition-all ${
              filter === 'available'
                ? 'bg-emerald-700 text-white'
                : 'bg-emerald-50 text-emerald-800 hover:bg-emerald-100'
            }`}
          >
            Trống
          </button>
          <button
            onClick={() => setFilter('cleaning')}
            className={`px-3 py-1.5 rounded-md text-xs font-semibold transition-all ${
              filter === 'cleaning'
                ? 'bg-amber-700 text-white'
                : 'bg-amber-50 text-amber-800 hover:bg-amber-100'
            }`}
          >
            Đang dọn
          </button>
          <button
            onClick={() => setFilter('occupied')}
            className={`px-3 py-1.5 rounded-md text-xs font-semibold transition-all ${
              filter === 'occupied'
                ? 'bg-rose-700 text-white'
                : 'bg-rose-50 text-rose-800 hover:bg-rose-100'
            }`}
          >
            Có khách
          </button>
        </div>
      </div>

      {/* Legend summary row */}
      <div className="flex items-center gap-6 py-3 text-xs text-[#6C756B]">
        <div className="flex items-center gap-2">
          <span className="w-2.5 h-2.5 rounded-full bg-emerald-500" />
          <span>Trống <strong>{availableCount}</strong></span>
        </div>
        <div className="flex items-center gap-2">
          <span className="w-2.5 h-2.5 rounded-full bg-amber-500" />
          <span>Đang dọn <strong>{cleaningCount}</strong></span>
        </div>
        <div className="flex items-center gap-2">
          <span className="w-2.5 h-2.5 rounded-full bg-rose-500" />
          <span>Có khách <strong>{occupiedCount}</strong></span>
        </div>
      </div>

      {/* Rooms Grid */}
      <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-4 mt-2">
        {filteredRooms.map((room) => (
          <RoomMiniCard key={room.id} room={room} />
        ))}
      </div>
    </div>
  );
};

