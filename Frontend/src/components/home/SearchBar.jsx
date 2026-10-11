import React, { useState } from 'react';
import { MapPin, Calendar, Users, Search, Plus, Minus, Check } from 'lucide-react';
import { useBooking } from '../../context/BookingContext';

export const SearchBar = () => {
  const { searchParams, setSearchParams, showToast } = useBooking();

  const [isLocationOpen, setIsLocationOpen] = useState(false);
  const [isGuestsOpen, setIsGuestsOpen] = useState(false);
  const [isDatesOpen, setIsDatesOpen] = useState(false);

  const locations = [
    'Nha Trang, Việt Nam',
    'TP. Hồ Chí Minh, Việt Nam',
    'Hội An, Quảng Nam',
    'Phú Quốc, Kiên Giang',
    'Đà Lạt, Lâm Đồng'
  ];

  const handleLocationSelect = (loc) => {
    setSearchParams((prev) => ({ ...prev, location: loc }));
    setIsLocationOpen(false);
  };

  const updateGuests = (field, delta) => {
    setSearchParams((prev) => {
      const current = prev[field] || 0;
      const nextVal = Math.max(field === 'roomsCount' || field === 'adults' ? 1 : 0, current + delta);
      const updated = { ...prev, [field]: nextVal };
      const totalGuests = (updated.adults || 1) + (updated.children || 0);
      updated.guestsDisplay = `${totalGuests} khách, ${updated.roomsCount || 1} phòng`;
      return updated;
    });
  };

  const handleSearchSubmit = (e) => {
    e.preventDefault();
    setIsLocationOpen(false);
    setIsGuestsOpen(false);
    setIsDatesOpen(false);

    showToast(`Đang tìm kiếm phòng tại ${searchParams.location}...`, 'info');

    // Smooth scroll down to rooms section
    const roomsEl = document.getElementById('rooms-section');
    if (roomsEl) {
      roomsEl.scrollIntoView({ behavior: 'smooth' });
    }
  };

  return (
    <div className="relative w-full max-w-5xl mx-auto shadow-2xl rounded-none sm:rounded-md bg-white border border-[#E5E0D8]">
      <form onSubmit={handleSearchSubmit} className="grid grid-cols-1 md:grid-cols-12 items-stretch divide-y md:divide-y-0 md:divide-x divide-[#ECE6DE]">
        
        {/* 1. Location */}
        <div className="relative md:col-span-3 p-3.5 sm:p-4 text-left group hover:bg-[#FAF8F5] transition-colors cursor-pointer">
          <div
            onClick={() => {
              setIsLocationOpen(!isLocationOpen);
              setIsGuestsOpen(false);
              setIsDatesOpen(false);
            }}
          >
            <span className="block text-[10px] font-bold tracking-[0.18em] text-[#865e26] uppercase mb-1">
              Địa điểm
            </span>
            <div className="flex items-center gap-2.5">
              <MapPin className="w-4 h-4 text-[#865e26] shrink-0" />
              <input
                type="text"
                readOnly
                value={searchParams.location}
                className="w-full text-xs sm:text-sm font-semibold text-[#1c2920] bg-transparent cursor-pointer outline-none truncate"
              />
            </div>
          </div>

          {/* Location dropdown */}
          {isLocationOpen && (
            <div className="absolute top-full left-0 mt-2 w-64 bg-white rounded-md shadow-xl border border-[#ECE6DE] py-2 z-50 animate-fade-in">
              <div className="px-3 py-1.5 text-[11px] font-semibold text-[#865e26] uppercase tracking-wider">
                Điểm đến phổ biến
              </div>
              {locations.map((loc) => (
                <button
                  type="button"
                  key={loc}
                  onClick={() => handleLocationSelect(loc)}
                  className={`w-full flex items-center justify-between px-3 py-2 text-xs sm:text-sm text-left hover:bg-[#FAF8F5] transition-colors ${
                    searchParams.location === loc ? 'font-semibold text-[#1a3e2d] bg-[#fbf7f0]' : 'text-[#49544c]'
                  }`}
                >
                  <span className="flex items-center gap-2">
                    <MapPin className="w-3.5 h-3.5 text-[#865e26]" />
                    {loc}
                  </span>
                  {searchParams.location === loc && <Check className="w-3.5 h-3.5 text-[#1a3e2d]" />}
                </button>
              ))}
            </div>
          )}
        </div>

        {/* 2. Check-in Date */}
        <div className="relative md:col-span-2.5 p-3.5 sm:p-4 text-left group hover:bg-[#FAF8F5] transition-colors cursor-pointer">
          <div
            onClick={() => {
              setIsDatesOpen(!isDatesOpen);
              setIsLocationOpen(false);
              setIsGuestsOpen(false);
            }}
          >
            <span className="block text-[10px] font-bold tracking-[0.18em] text-[#865e26] uppercase mb-1">
              Nhận phòng
            </span>
            <div className="flex items-center gap-2.5">
              <Calendar className="w-4 h-4 text-[#865e26] shrink-0" />
              <span className="text-xs sm:text-sm font-semibold text-[#1c2920] truncate">
                {searchParams.checkInDisplay}
              </span>
            </div>
          </div>
        </div>

        {/* 3. Check-out Date */}
        <div className="relative md:col-span-2.5 p-3.5 sm:p-4 text-left group hover:bg-[#FAF8F5] transition-colors cursor-pointer">
          <div
            onClick={() => {
              setIsDatesOpen(!isDatesOpen);
              setIsLocationOpen(false);
              setIsGuestsOpen(false);
            }}
          >
            <span className="block text-[10px] font-bold tracking-[0.18em] text-[#865e26] uppercase mb-1">
              Trả phòng
            </span>
            <div className="flex items-center gap-2.5">
              <Calendar className="w-4 h-4 text-[#865e26] shrink-0" />
              <span className="text-xs sm:text-sm font-semibold text-[#1c2920] truncate">
                {searchParams.checkOutDisplay}
              </span>
            </div>
          </div>

          {/* Quick Date Picker Popover */}
          {isDatesOpen && (
            <div className="absolute top-full left-0 md:-left-24 mt-2 w-72 bg-white rounded-md shadow-xl border border-[#ECE6DE] p-4 z-50 animate-fade-in text-xs">
              <div className="font-semibold text-[#1a3e2d] mb-2">Chọn khoảng thời gian lưu trú:</div>
              <div className="grid grid-cols-2 gap-2 mb-3">
                <button
                  type="button"
                  onClick={() => {
                    setSearchParams((prev) => ({
                      ...prev,
                      checkInDisplay: '24 Thg 05, 2025',
                      checkOutDisplay: '27 Thg 05, 2025',
                      nights: 3
                    }));
                    setIsDatesOpen(false);
                  }}
                  className="p-2 border border-[#b88d43] rounded bg-[#FAF8F5] text-left hover:border-[#1a3e2d]"
                >
                  <div className="font-bold text-[#1a3e2d]">24 - 27 Thg 05</div>
                  <div className="text-[10px] text-[#6C756B]">3 đêm (Mặc định)</div>
                </button>
                <button
                  type="button"
                  onClick={() => {
                    setSearchParams((prev) => ({
                      ...prev,
                      checkInDisplay: '01 Thg 06, 2025',
                      checkOutDisplay: '05 Thg 06, 2025',
                      nights: 4
                    }));
                    setIsDatesOpen(false);
                  }}
                  className="p-2 border border-[#ECE6DE] rounded hover:border-[#b88d43] text-left"
                >
                  <div className="font-bold text-[#1a3e2d]">01 - 05 Thg 06</div>
                  <div className="text-[10px] text-[#6C756B]">4 đêm (Đầu tháng 6)</div>
                </button>
              </div>
              <button
                type="button"
                onClick={() => setIsDatesOpen(false)}
                className="w-full py-1 text-center text-xs font-semibold text-[#865e26] hover:underline"
              >
                Xác nhận ngày này
              </button>
            </div>
          )}
        </div>

        {/* 4. Guests & Rooms */}
        <div className="relative md:col-span-2 p-3.5 sm:p-4 text-left group hover:bg-[#FAF8F5] transition-colors cursor-pointer">
          <div
            onClick={() => {
              setIsGuestsOpen(!isGuestsOpen);
              setIsLocationOpen(false);
              setIsDatesOpen(false);
            }}
          >
            <span className="block text-[10px] font-bold tracking-[0.18em] text-[#865e26] uppercase mb-1">
              Khách & Phòng
            </span>
            <div className="flex items-center gap-2.5">
              <Users className="w-4 h-4 text-[#865e26] shrink-0" />
              <span className="text-xs sm:text-sm font-semibold text-[#1c2920] truncate">
                {searchParams.guestsDisplay}
              </span>
            </div>
          </div>

          {/* Guests Popover */}
          {isGuestsOpen && (
            <div className="absolute top-full right-0 mt-2 w-64 bg-white rounded-md shadow-xl border border-[#ECE6DE] p-4 z-50 animate-fade-in text-xs space-y-3.5">
              <div className="flex items-center justify-between">
                <div>
                  <div className="font-semibold text-[#1c2920]">Người lớn</div>
                  <div className="text-[10px] text-[#6C756B]">Từ 12 tuổi trở lên</div>
                </div>
                <div className="flex items-center gap-2">
                  <button
                    type="button"
                    onClick={() => updateGuests('adults', -1)}
                    className="w-6 h-6 rounded border border-gray-300 flex items-center justify-center hover:bg-gray-100"
                  >
                    <Minus className="w-3 h-3 text-gray-600" />
                  </button>
                  <span className="w-5 text-center font-bold text-sm">{searchParams.adults || 2}</span>
                  <button
                    type="button"
                    onClick={() => updateGuests('adults', 1)}
                    className="w-6 h-6 rounded border border-gray-300 flex items-center justify-center hover:bg-gray-100"
                  >
                    <Plus className="w-3 h-3 text-gray-600" />
                  </button>
                </div>
              </div>

              <div className="flex items-center justify-between pt-2 border-t border-gray-100">
                <div>
                  <div className="font-semibold text-[#1c2920]">Trẻ em</div>
                  <div className="text-[10px] text-[#6C756B]">Dưới 12 tuổi</div>
                </div>
                <div className="flex items-center gap-2">
                  <button
                    type="button"
                    onClick={() => updateGuests('children', -1)}
                    className="w-6 h-6 rounded border border-gray-300 flex items-center justify-center hover:bg-gray-100"
                  >
                    <Minus className="w-3 h-3 text-gray-600" />
                  </button>
                  <span className="w-5 text-center font-bold text-sm">{searchParams.children || 0}</span>
                  <button
                    type="button"
                    onClick={() => updateGuests('children', 1)}
                    className="w-6 h-6 rounded border border-gray-300 flex items-center justify-center hover:bg-gray-100"
                  >
                    <Plus className="w-3 h-3 text-gray-600" />
                  </button>
                </div>
              </div>

              <div className="flex items-center justify-between pt-2 border-t border-gray-100">
                <div>
                  <div className="font-semibold text-[#1c2920]">Số phòng</div>
                  <div className="text-[10px] text-[#6C756B]">Số lượng phòng đặt</div>
                </div>
                <div className="flex items-center gap-2">
                  <button
                    type="button"
                    onClick={() => updateGuests('roomsCount', -1)}
                    className="w-6 h-6 rounded border border-gray-300 flex items-center justify-center hover:bg-gray-100"
                  >
                    <Minus className="w-3 h-3 text-gray-600" />
                  </button>
                  <span className="w-5 text-center font-bold text-sm">{searchParams.roomsCount || 1}</span>
                  <button
                    type="button"
                    onClick={() => updateGuests('roomsCount', 1)}
                    className="w-6 h-6 rounded border border-gray-300 flex items-center justify-center hover:bg-gray-100"
                  >
                    <Plus className="w-3 h-3 text-gray-600" />
                  </button>
                </div>
              </div>

              <button
                type="button"
                onClick={() => setIsGuestsOpen(false)}
                className="w-full mt-2 py-1.5 bg-[#1a3e2d] text-white rounded font-medium text-xs hover:bg-[#143124] transition-colors"
              >
                Xong
              </button>
            </div>
          )}
        </div>

        {/* 5. Search Button */}
        <div className="md:col-span-2 flex items-center p-2 sm:p-2.5">
          <button
            type="submit"
            className="w-full h-full min-h-[48px] bg-[#1a3e2d] hover:bg-[#143124] text-white font-medium text-xs sm:text-sm px-4 py-3 rounded-none sm:rounded-sm flex items-center justify-center gap-2.5 transition-all shadow-sm group active:scale-[0.98]"
          >
            <Search className="w-4 h-4 text-white group-hover:scale-110 transition-transform" />
            <span className="tracking-wide">Tìm phòng</span>
          </button>
        </div>
      </form>
    </div>
  );
};

