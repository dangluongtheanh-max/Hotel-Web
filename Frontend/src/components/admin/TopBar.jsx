import React from 'react';
import { Link } from 'react-router-dom';
import { Bell, Calendar, Home } from 'lucide-react';
import { GlobalSearchBar } from './GlobalSearchBar';
import { useReception } from '../../context/ReceptionContext';

export const TopBar = () => {
  const { staff } = useReception();

  // Current formatted date
  const todayFormatted = new Intl.DateTimeFormat('vi-VN', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric'
  }).format(new Date());

  // Capitalize first letter
  const displayDate = todayFormatted.charAt(0).toUpperCase() + todayFormatted.slice(1);

  return (
    <header className="sticky top-0 z-20 bg-white/95 backdrop-blur-xs border-b border-[#E7E2D9] px-6 py-3.5 flex items-center justify-between gap-4 transition-all">
      {/* Left: Global Search Bar (Ctrl+K) */}
      <div className="flex-1 max-w-lg">
        <GlobalSearchBar />
      </div>

      {/* Right: Date, Notifications, and Website Link */}
      <div className="flex items-center gap-5 sm:gap-6">
        {/* Date & Time */}
        <div className="hidden md:flex items-center gap-2 text-xs text-[#525f54] bg-[#FAF8F5] border border-[#ECE6DE] px-3 py-1.5 rounded-md">
          <Calendar className="w-3.5 h-3.5 text-[#865e26]" />
          <span className="font-medium">{displayDate}</span>
        </div>

        {/* Notifications */}
        <div className="relative">
          <button
            className="p-2 rounded-full text-gray-500 hover:text-black hover:bg-gray-100 transition-colors relative"
            title="Thông báo"
          >
            <Bell className="w-5 h-5" />
            <span className="absolute top-1 right-1 w-2 h-2 rounded-full bg-rose-500 animate-pulse" />
          </button>
        </div>

        {/* Link back to Customer Website */}
        <Link
          to="/"
          className="hidden sm:inline-flex items-center gap-2 px-3 py-1.5 text-xs font-semibold text-[#1a3e2d] bg-[#f0f6f2] hover:bg-[#dbeade] border border-[#b8d6be] rounded-md transition-all shadow-2xs"
          title="Mở giao diện khách hàng"
        >
          <Home className="w-3.5 h-3.5" />
          <span>Giao diện khách</span>
        </Link>
      </div>
    </header>
  );
};

