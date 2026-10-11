import React, { useState, useEffect, useRef } from 'react';
import { Search, User, Phone, FileText, Bed, ArrowRight, X } from 'lucide-react';
import { useReception } from '../../context/ReceptionContext';

export const GlobalSearchBar = () => {
  const { rooms, expectedArrivals, openCheckIn, openRoomDetail } = useReception();
  const [query, setQuery] = useState('');
  const [isOpen, setIsOpen] = useState(false);
  const inputRef = useRef(null);

  // Global hotkey: Ctrl+K or Cmd+K
  useEffect(() => {
    const handleKeyDown = (e) => {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        inputRef.current?.focus();
        setIsOpen(true);
      }
      if (e.key === 'Escape') {
        setIsOpen(false);
      }
    };
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, []);

  // Filter results
  const trimmed = query.trim().toLowerCase();
  const filteredGuests = trimmed
    ? [
        ...rooms
          .filter((r) => r.guestName && (r.guestName.toLowerCase().includes(trimmed) || r.phone?.includes(trimmed) || r.cccd?.includes(trimmed) || r.bookingId?.toLowerCase().includes(trimmed)))
          .map((r) => ({ type: 'staying', title: r.guestName, sub: `Phòng ${r.number} · SĐT: ${r.phone}`, raw: r })),
        ...expectedArrivals
          .filter((a) => a.guestName.toLowerCase().includes(trimmed) || a.phone.includes(trimmed) || a.cccd.includes(trimmed) || a.bookingId.toLowerCase().includes(trimmed))
          .map((a) => ({ type: 'arrival', title: a.guestName, sub: `Dự kiến đến: ${a.roomSuggested} · SĐT: ${a.phone}`, raw: a }))
      ]
    : [];

  return (
    <div className="relative w-full max-w-md">
      <div className="relative flex items-center">
        <Search className="w-4 h-4 text-gray-400 absolute left-3.5 pointer-events-none" />
        <input
          ref={inputRef}
          type="text"
          value={query}
          onChange={(e) => {
            setQuery(e.target.value);
            setIsOpen(true);
          }}
          onFocus={() => setIsOpen(true)}
          placeholder="Tìm khách hàng bằng SĐT, CCCD, tên..."
          className="w-full bg-[#f6f8f7] border border-[#e5e9e6] rounded-md pl-10 pr-14 py-2 text-xs sm:text-sm text-[#1c2920] placeholder:text-gray-400 focus:outline-none focus:border-[#1a3e2d] focus:bg-white transition-all shadow-2xs"
        />
        {/* Shortcut badge */}
        <kbd className="absolute right-3 px-1.5 py-0.5 text-[10px] font-semibold text-gray-400 bg-white border border-gray-200 rounded shadow-2xs pointer-events-none">
          ⌘K
        </kbd>
      </div>

      {/* Results Dropdown */}
      {isOpen && query.trim() && (
        <div className="absolute top-full left-0 right-0 mt-2 bg-white rounded-md shadow-xl border border-gray-200 py-2 z-50 animate-fade-in max-h-72 overflow-y-auto">
          <div className="px-3 py-1 text-[11px] font-bold text-[#865e26] uppercase tracking-wider flex items-center justify-between">
            <span>Kết quả tra cứu ({filteredGuests.length})</span>
            <button onClick={() => setIsOpen(false)} className="text-gray-400 hover:text-black">
              <X className="w-3.5 h-3.5" />
            </button>
          </div>

          {filteredGuests.length > 0 ? (
            <div className="divide-y divide-gray-50">
              {filteredGuests.map((item, idx) => (
                <button
                  key={idx}
                  onClick={() => {
                    setIsOpen(false);
                    if (item.type === 'staying') {
                      openRoomDetail(item.raw);
                    } else {
                      openCheckIn(item.raw);
                    }
                  }}
                  className="w-full px-3.5 py-2.5 text-left hover:bg-[#FAF8F5] transition-colors flex items-center justify-between group"
                >
                  <div className="flex items-center gap-2.5">
                    <div className="w-7 h-7 rounded-full bg-[#f0f6f2] text-[#1a3e2d] flex items-center justify-center shrink-0">
                      <User className="w-3.5 h-3.5" />
                    </div>
                    <div>
                      <div className="font-semibold text-xs text-[#1c2920] group-hover:text-[#1a3e2d]">
                        {item.title}
                      </div>
                      <div className="text-[11px] text-[#6C756B]">{item.sub}</div>
                    </div>
                  </div>
                  <span className="text-[10px] font-semibold text-[#865e26] px-2 py-0.5 rounded bg-[#fcf8f2] border border-[#f0e6d6]">
                    {item.type === 'staying' ? 'Đang ở' : 'Chờ check-in'}
                  </span>
                </button>
              ))}
            </div>
          ) : (
            <div className="px-4 py-6 text-center text-xs text-gray-500">
              Không tìm thấy khách hàng nào khớp với "{query}".
            </div>
          )}
        </div>
      )}
    </div>
  );
};

