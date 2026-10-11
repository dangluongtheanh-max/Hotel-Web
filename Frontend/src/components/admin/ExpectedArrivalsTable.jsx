import React, { useState } from 'react';
import { Search, Calendar, ChevronRight, Check } from 'lucide-react';
import { useReception } from '../../context/ReceptionContext';

export const ExpectedArrivalsTable = () => {
  const { expectedArrivals, openCheckIn } = useReception();
  const [searchTable, setSearchTable] = useState('');

  const filtered = expectedArrivals.filter(
    (a) =>
      a.guestName.toLowerCase().includes(searchTable.toLowerCase()) ||
      a.phone.includes(searchTable) ||
      a.bookingId.toLowerCase().includes(searchTable.toLowerCase())
  );

  return (
    <div className="bg-white border border-[#E7E2D9] rounded-lg p-5 sm:p-6 shadow-xs">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-5">
        <div>
          <h3 className="font-serif text-lg sm:text-xl font-bold text-[#1c2920]">
            Khách dự kiến đến
          </h3>
          <p className="text-xs text-[#6C756B] mt-0.5">
            Các booking cần xử lý hôm nay ({expectedArrivals.length} khách)
          </p>
        </div>

        {/* Search input in table */}
        <div className="relative w-full sm:w-72">
          <Search className="w-3.5 h-3.5 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
          <input
            type="text"
            value={searchTable}
            onChange={(e) => setSearchTable(e.target.value)}
            placeholder="Tìm mã booking, tên khách..."
            className="w-full bg-[#FAF8F5] border border-gray-200 rounded-md pl-9 pr-3 py-1.5 text-xs text-[#1c2920] focus:outline-none focus:border-[#1a3e2d] focus:bg-white transition-colors"
          />
        </div>
      </div>

      {/* Table */}
      <div className="overflow-x-auto">
        <table className="w-full text-left border-collapse text-xs">
          <thead>
            <tr className="border-b border-gray-100 text-[10px] uppercase tracking-wider text-[#865e26] font-bold bg-[#faf8f5]/60">
              <th className="py-3 px-3">Khách hàng</th>
              <th className="py-3 px-3">Mã Booking</th>
              <th className="py-3 px-3">Phòng</th>
              <th className="py-3 px-3">Thời gian</th>
              <th className="py-3 px-3">Trạng thái</th>
              <th className="py-3 px-3 text-right">Thao tác</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {filtered.length > 0 ? (
              filtered.map((item) => (
                <tr key={item.id} className="hover:bg-[#FAF8F5]/80 transition-colors">
                  {/* Guest */}
                  <td className="py-3.5 px-3">
                    <div className="flex items-center gap-3">
                      <div className="w-8 h-8 rounded-full bg-[#f0f6f2] text-[#1a3e2d] font-bold text-xs flex items-center justify-center border border-[#b8d6be]">
                        {item.initials}
                      </div>
                      <div>
                        <div className="font-semibold text-xs text-[#1c2920]">{item.guestName}</div>
                        <div className="text-[11px] text-[#788579]">{item.phone}</div>
                      </div>
                    </div>
                  </td>

                  {/* Booking Code */}
                  <td className="py-3.5 px-3">
                    <span className="font-semibold text-emerald-800 tracking-wide font-mono">
                      {item.bookingId}
                    </span>
                  </td>

                  {/* Room Suggested */}
                  <td className="py-3.5 px-3">
                    <span className="font-medium text-[#1c2920]">{item.roomSuggested}</span>
                  </td>

                  {/* Stay Dates */}
                  <td className="py-3.5 px-3 text-[#6C756B]">
                    <span>{item.stayDates}</span>
                  </td>

                  {/* Status */}
                  <td className="py-3.5 px-3">
                    {item.status === 'Chờ nhận phòng' ? (
                      <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-semibold bg-amber-50 text-amber-800 border border-amber-200">
                        <span className="w-1.5 h-1.5 rounded-full bg-amber-500" />
                        Chờ nhận phòng
                      </span>
                    ) : (
                      <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">
                        <span className="w-1.5 h-1.5 rounded-full bg-emerald-500" />
                        Đã xác nhận
                      </span>
                    )}
                  </td>

                  {/* Action Button */}
                  <td className="py-3.5 px-3 text-right">
                    <button
                      onClick={() => openCheckIn(item)}
                      className="px-3.5 py-1.5 rounded border border-[#1a3e2d] text-[#1a3e2d] hover:bg-[#1a3e2d] hover:text-white font-semibold text-xs transition-all active:scale-95 shadow-2xs"
                    >
                      Nhận phòng
                    </button>
                  </td>
                </tr>
              ))
            ) : (
              <tr>
                <td colSpan={6} className="py-8 text-center text-xs text-gray-400">
                  Không tìm thấy khách dự kiến nào.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
};

