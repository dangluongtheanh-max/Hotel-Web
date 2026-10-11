import React from 'react';
import { ChevronRight } from 'lucide-react';
import { useBooking } from '../../context/BookingContext';

export const UpcomingTripBanner = () => {
  const { upcomingTrip, openBookingHistory, openBookingDetail } = useBooking();

  if (!upcomingTrip) return null;

  return (
    <section className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-16 sm:mt-20">
      <div className="relative overflow-hidden bg-[#1a3e2d] text-white rounded-none sm:rounded-sm shadow-lg border border-[#25523d] transition-all hover:border-[#b88d43]/50">
        {/* Subtle background motif */}
        <div className="absolute inset-0 bg-gradient-to-r from-black/40 via-transparent to-black/30 pointer-events-none" />

        <div className="relative z-10 px-6 sm:px-10 py-6 sm:py-8 flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
          {/* Left Column */}
          <div className="space-y-1.5">
            <span className="block text-[11px] font-bold tracking-[0.2em] text-[#dfc28d] uppercase">
              Chuyến đi sắp tới
            </span>
            <h2 className="text-xl sm:text-2xl font-serif font-medium text-white tracking-wide">
              {upcomingTrip.roomTitle} đang chờ bạn.
            </h2>
            <p className="text-xs sm:text-sm text-[#b2c2b7] font-light">
              {upcomingTrip.dateRangeDisplay} · {upcomingTrip.location.split(',')[0]}
            </p>
          </div>

          {/* Right Column */}
          <div className="flex flex-wrap items-center gap-6 sm:gap-10 w-full md:w-auto justify-between md:justify-end border-t md:border-t-0 pt-4 md:pt-0 border-[#26533e]">
            {/* Booking Code & Status */}
            <div className="space-y-1 text-left">
              <span className="block text-[10px] font-bold tracking-[0.2em] text-[#dfc28d] uppercase">
                Mã đặt phòng
              </span>
              <div className="flex items-center gap-3">
                <span className="font-semibold text-sm sm:text-base tracking-wider text-white">
                  {upcomingTrip.id}
                </span>
                <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-[#2e5d45] text-[#b3e6c9] border border-[#3e7a5c]">
                  <span className="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse" />
                  {upcomingTrip.statusText}
                </span>
              </div>
            </div>

            {/* Action CTA */}
            <button
              onClick={() => openBookingDetail ? openBookingDetail(upcomingTrip) : openBookingHistory('upcoming')}
              className="px-5 py-2.5 text-xs sm:text-sm font-medium text-white border border-white/60 hover:border-white hover:bg-white/10 transition-all flex items-center gap-2 group whitespace-nowrap active:scale-[0.98]"
            >
              <span>Xem chi tiết</span>
              <ChevronRight className="w-4 h-4 text-white group-hover:translate-x-1 transition-transform" />
            </button>
          </div>
        </div>
      </div>
    </section>
  );
};

