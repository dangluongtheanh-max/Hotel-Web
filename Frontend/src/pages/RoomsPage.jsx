import React, { useState } from 'react';
import { RoomCard } from '../components/home/RoomCard';
import { useBooking } from '../context/BookingContext';
import { Filter, SlidersHorizontal } from 'lucide-react';

export const RoomsPage = () => {
  const { rooms } = useBooking();
  const [selectedFilter, setSelectedFilter] = useState('all');

  const filteredRooms = rooms.filter((r) => {
    if (selectedFilter === 'all') return true;
    if (selectedFilter === 'suite') return r.name.toLowerCase().includes('suite');
    if (selectedFilter === 'deluxe') return r.name.toLowerCase().includes('deluxe');
    if (selectedFilter === 'studio') return r.name.toLowerCase().includes('studio');
    return true;
  });

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
      {/* Header */}
      <div className="text-center max-w-2xl mx-auto mb-10">
        <span className="block text-[11px] font-bold tracking-[0.2em] text-[#865e26] uppercase mb-2">
          Bộ sưu tập phòng
        </span>
        <h1 className="font-serif text-3xl sm:text-4xl font-bold text-[#1a3e2d] mb-4">
          Không Gian Nghỉ Dưỡng Thượng Lưu
        </h1>
        <p className="text-sm text-[#6C756B] leading-relaxed">
          Mỗi căn phòng tại Lumière là một tác phẩm được trau chuốt tỉ mỉ từ ánh sáng, vật liệu tự nhiên cho đến hương thơm dịu êm.
        </p>
      </div>

      {/* Filter Tabs */}
      <div className="flex items-center justify-center gap-2 mb-10 overflow-x-auto pb-2">
        {[
          { key: 'all', label: 'Tất cả các phòng' },
          { key: 'suite', label: 'Suite cao cấp' },
          { key: 'deluxe', label: 'Deluxe City View' },
          { key: 'studio', label: 'Garden Studio' },
        ].map((f) => (
          <button
            key={f.key}
            onClick={() => setSelectedFilter(f.key)}
            className={`px-4 py-2 text-xs font-semibold rounded-xs transition-all ${
              selectedFilter === f.key
                ? 'bg-[#1a3e2d] text-white shadow-sm'
                : 'bg-white text-[#49544c] border border-[#ECE6DE] hover:bg-[#FAF8F5]'
            }`}
          >
            {f.label}
          </button>
        ))}
      </div>

      {/* Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8">
        {filteredRooms.map((room) => (
          <RoomCard key={room.id} room={room} />
        ))}
      </div>
    </div>
  );
};

