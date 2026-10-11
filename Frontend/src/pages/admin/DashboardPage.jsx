import React from 'react';
import { Calendar, LogOut, Bed, Sparkles, CheckSquare } from 'lucide-react';
import { StatCard } from '../../components/admin/StatCard';
import { RoomStatusGrid } from '../../components/admin/RoomStatusGrid';
import { ExpectedArrivalsTable } from '../../components/admin/ExpectedArrivalsTable';
import { useReception } from '../../context/ReceptionContext';

export const DashboardPage = () => {
  const { staff, stats, openCheckIn, openCheckOut } = useReception();

  return (
    <div className="space-y-6 sm:space-y-8 animate-fade-in">
      {/* Welcome Banner & Quick Action Buttons */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <span className="block text-[11px] font-bold tracking-[0.2em] text-[#865e26] uppercase mb-1">
            {staff.shift}
          </span>
          <h1 className="font-serif text-2xl sm:text-3xl font-bold text-[#1c2920]">
            Chào buổi sáng, {staff.name.split(' ').pop()}
          </h1>
          <p className="text-xs sm:text-sm text-[#6C756B] mt-0.5">
            Đây là tình hình vận hành khách sạn Lumière hôm nay.
          </p>
        </div>

        {/* Quick actions */}
        <div className="flex items-center gap-3">
          <button
            onClick={() => openCheckOut()}
            className="px-4 sm:px-5 py-2.5 rounded-md border border-[#1a3e2d] text-[#1a3e2d] hover:bg-[#FAF8F5] text-xs sm:text-sm font-semibold flex items-center gap-2 transition-all shadow-2xs active:scale-95"
          >
            <LogOut className="w-4 h-4 text-[#865e26]" />
            <span>Check-out</span>
          </button>
          <button
            onClick={() => openCheckIn()}
            className="px-4 sm:px-5 py-2.5 rounded-md bg-[#1a3e2d] hover:bg-[#143124] text-white text-xs sm:text-sm font-semibold flex items-center gap-2 transition-all shadow-sm active:scale-95"
          >
            <CheckSquare className="w-4 h-4 text-[#dfc28d]" />
            <span>Check-in khách</span>
          </button>
        </div>
      </div>

      {/* 4 StatCards (User Story 3) */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5">
        <StatCard
          title="Khách đến hôm nay"
          count={stats.arrivalsCount}
          subtitle={`${stats.arrivalsPending} khách đang chờ`}
          icon={Calendar}
        />
        <StatCard
          title="Khách trả phòng"
          count={stats.departuresCount}
          subtitle={`${stats.departuresPending} phòng trước 12:00`}
          icon={LogOut}
        />
        <StatCard
          title="Phòng trống"
          count={stats.available}
          subtitle={`Trên tổng số ${stats.total} phòng`}
          icon={Bed}
        />
        <StatCard
          title="Đang dọn dẹp"
          count={stats.cleaning}
          subtitle={`${stats.cleaningPending} phòng sắp hoàn tất`}
          icon={Sparkles}
        />
      </div>

      {/* Room Status Grid (User Story 4) */}
      <RoomStatusGrid />

      {/* Expected Arrivals Table (User Story 5) */}
      <ExpectedArrivalsTable />
    </div>
  );
};

