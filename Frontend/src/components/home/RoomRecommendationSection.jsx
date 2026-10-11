import React from 'react';
import { ArrowRight } from 'lucide-react';
import { RoomCard } from './RoomCard';
import { useBooking } from '../../context/BookingContext';

export const RoomRecommendationSection = () => {
  const { rooms } = useBooking();

  return (
    <section id="rooms-section" className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-16 sm:pt-20 pb-12">
      {/* Section Header */}
      <div className="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-8 sm:mb-10">
        <div>
          <span className="block text-[11px] font-bold tracking-[0.2em] text-[#865e26] uppercase mb-2">
            Lựa chọn dành cho bạn
          </span>
          <h2 className="text-2xl sm:text-3xl lg:text-4xl font-sans font-bold text-[#1c2920] tracking-tight">
            Không gian để bạn{' '}
            <span className="font-serif italic font-normal text-[#865e26]">
              thuộc về.
            </span>
          </h2>
        </div>

        <a
          href="#all-rooms"
          onClick={(e) => {
            e.preventDefault();
            const el = document.getElementById('rooms-section');
            if (el) el.scrollIntoView({ behavior: 'smooth' });
          }}
          className="inline-flex items-center gap-2 text-xs sm:text-sm font-medium text-[#1a3e2d] hover:text-[#865e26] transition-colors group"
        >
          <span>Xem tất cả phòng</span>
          <ArrowRight className="w-4 h-4 group-hover:translate-x-1 transition-transform" />
        </a>
      </div>

      {/* Rooms Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8">
        {rooms.map((room) => (
          <RoomCard key={room.id} room={room} />
        ))}
      </div>
    </section>
  );
};

