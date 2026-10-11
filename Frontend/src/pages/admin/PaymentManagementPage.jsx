import React, { useState } from 'react';
import { CreditCard, Search, CheckCircle2, Clock, DollarSign, Filter } from 'lucide-react';
import { useReception } from '../../context/ReceptionContext';

export const PaymentManagementPage = () => {
  const { rooms, openPayment } = useReception();
  const [filter, setFilter] = useState('all'); // 'all' | 'UNPAID' | 'PAID'
  const [searchQuery, setSearchQuery] = useState('');

  const formatVND = (num) => new Intl.NumberFormat('vi-VN').format(num) + 'đ';

  // Only show rooms with financial tracking (occupied or with amount)
  const billingRooms = rooms.filter((r) => r.totalAmount > 0 || r.status === 'occupied');

  const filtered = billingRooms.filter((r) => {
    if (filter !== 'all' && r.paymentStatus !== filter) return false;
    if (searchQuery.trim()) {
      const q = searchQuery.toLowerCase();
      return (
        r.number.toLowerCase().includes(q) ||
        (r.guestName && r.guestName.toLowerCase().includes(q)) ||
        (r.bookingId && r.bookingId.toLowerCase().includes(q))
      );
    }
    return true;
  });

  // Financial stats
  const totalReceivables = billingRooms.reduce((sum, r) => sum + r.totalAmount, 0);
  const totalCollected = billingRooms.reduce((sum, r) => sum + r.paidAmount, 0);
  const totalPending = billingRooms.reduce((sum, r) => sum + r.remainingAmount, 0);

  return (
    <div className="space-y-6 animate-fade-in">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <span className="block text-[11px] font-bold tracking-[0.2em] text-[#865e26] uppercase mb-1">
            Quản trị tài chính quầy
          </span>
          <h1 className="font-serif text-2xl sm:text-3xl font-bold text-[#1c2920]">
            Quản Lý Thanh Toán Phòng
          </h1>
          <p className="text-xs sm:text-sm text-[#6C756B] mt-0.5">
            Theo dõi công nợ, tiền phòng, đặt cọc và hóa đơn thanh toán của khách hàng.
          </p>
        </div>

        {/* Filter search */}
        <div className="relative w-full sm:w-64">
          <Search className="w-3.5 h-3.5 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
          <input
            type="text"
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            placeholder="Tìm số phòng, tên khách..."
            className="w-full bg-white border border-[#D5CEC2] rounded-md pl-9 pr-3 py-2 text-xs text-[#1c2920] focus:outline-none focus:border-[#1a3e2d]"
          />
        </div>
      </div>

      {/* Financial Summary Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div className="bg-white border border-[#E7E2D9] rounded-lg p-5 shadow-2xs">
          <span className="block text-[10px] font-bold tracking-wider text-[#865e26] uppercase mb-1">
            Tổng giá trị đơn phòng
          </span>
          <div className="text-2xl font-bold text-[#1c2920]">
            {formatVND(totalReceivables)}
          </div>
          <span className="text-[11px] text-[#788579]">Trên {billingRooms.length} phòng đang vận hành</span>
        </div>

        <div className="bg-white border border-[#E7E2D9] rounded-lg p-5 shadow-2xs">
          <span className="block text-[10px] font-bold tracking-wider text-emerald-700 uppercase mb-1">
            Đã thu tiền / Đặt cọc
          </span>
          <div className="text-2xl font-bold text-emerald-700">
            {formatVND(totalCollected)}
          </div>
          <span className="text-[11px] text-[#788579]">Tiền mặt, QR & Thẻ POS</span>
        </div>

        <div className="bg-white border border-[#E7E2D9] rounded-lg p-5 shadow-2xs">
          <span className="block text-[10px] font-bold tracking-wider text-rose-700 uppercase mb-1">
            Còn lại cần thu (Công nợ)
          </span>
          <div className="text-2xl font-bold text-rose-700">
            {formatVND(totalPending)}
          </div>
          <span className="text-[11px] text-[#788579]">Cần thu khi khách check-out</span>
        </div>
      </div>

      {/* Payment Table (User Story 10 & 11) */}
      <div className="bg-white border border-[#E7E2D9] rounded-lg p-5 sm:p-6 shadow-xs">
        {/* Table Filters */}
        <div className="flex items-center justify-between gap-4 pb-4 border-b border-gray-100 mb-4">
          <h3 className="font-serif text-lg font-bold text-[#1c2920]">
            Danh sách hóa đơn phòng
          </h3>

          <div className="flex items-center gap-1.5 text-xs">
            <button
              onClick={() => setFilter('all')}
              className={`px-3 py-1.5 rounded-md font-semibold transition-all ${
                filter === 'all'
                  ? 'bg-[#1a3e2d] text-white shadow-2xs'
                  : 'bg-[#FAF8F5] text-gray-600 hover:bg-gray-100'
              }`}
            >
              Tất cả
            </button>
            <button
              onClick={() => setFilter('UNPAID')}
              className={`px-3 py-1.5 rounded-md font-semibold transition-all ${
                filter === 'UNPAID'
                  ? 'bg-rose-700 text-white shadow-2xs'
                  : 'bg-rose-50 text-rose-800 hover:bg-rose-100'
              }`}
            >
              Chưa thanh toán
            </button>
            <button
              onClick={() => setFilter('PAID')}
              className={`px-3 py-1.5 rounded-md font-semibold transition-all ${
                filter === 'PAID'
                  ? 'bg-emerald-700 text-white shadow-2xs'
                  : 'bg-emerald-50 text-emerald-800 hover:bg-emerald-100'
              }`}
            >
              Đã thanh toán
            </button>
          </div>
        </div>

        {/* Table Content */}
        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse text-xs">
            <thead>
              <tr className="border-b border-gray-100 text-[10px] uppercase tracking-wider text-[#865e26] font-bold bg-[#faf8f5]/60">
                <th className="py-3 px-3">Phòng</th>
                <th className="py-3 px-3">Khách hàng</th>
                <th className="py-3 px-3">Mã Booking</th>
                <th className="py-3 px-3">Tổng tiền</th>
                <th className="py-3 px-3">Đã cọc</th>
                <th className="py-3 px-3">Còn lại</th>
                <th className="py-3 px-3">Trạng thái</th>
                <th className="py-3 px-3 text-right">Thao tác</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {filtered.length > 0 ? (
                filtered.map((room) => {
                  const isPaid = room.paymentStatus === 'PAID';

                  return (
                    <tr key={room.id} className="hover:bg-[#FAF8F5]/80 transition-colors">
                      {/* Room */}
                      <td className="py-3.5 px-3">
                        <span className="font-serif font-bold text-sm text-[#1c2920]">
                          Phòng {room.number}
                        </span>
                        <div className="text-[11px] text-[#788579]">{room.type}</div>
                      </td>

                      {/* Guest */}
                      <td className="py-3.5 px-3">
                        <div className="font-semibold text-[#1c2920]">{room.guestName || '—'}</div>
                        <div className="text-[11px] text-[#788579]">{room.phone || ''}</div>
                      </td>

                      {/* Booking ID */}
                      <td className="py-3.5 px-3">
                        <span className="font-mono font-semibold text-emerald-800">
                          {room.bookingId || '—'}
                        </span>
                      </td>

                      {/* Total */}
                      <td className="py-3.5 px-3 font-semibold text-[#1c2920]">
                        {formatVND(room.totalAmount)}
                      </td>

                      {/* Paid */}
                      <td className="py-3.5 px-3 text-emerald-700 font-medium">
                        {formatVND(room.paidAmount)}
                      </td>

                      {/* Remaining */}
                      <td className="py-3.5 px-3">
                        <span className={`font-bold ${room.remainingAmount > 0 ? 'text-rose-700' : 'text-gray-400'}`}>
                          {formatVND(room.remainingAmount)}
                        </span>
                      </td>

                      {/* Status */}
                      <td className="py-3.5 px-3">
                        {isPaid ? (
                          <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200">
                            <span className="w-1.5 h-1.5 rounded-full bg-emerald-500" />
                            Đã thanh toán
                          </span>
                        ) : (
                          <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-semibold bg-rose-50 text-rose-800 border border-rose-200">
                            <span className="w-1.5 h-1.5 rounded-full bg-rose-500" />
                            Chưa thanh toán
                          </span>
                        )}
                      </td>

                      {/* User Story 11: ActionButton with logic disabled={status === 'PAID'} */}
                      <td className="py-3.5 px-3 text-right">
                        <button
                          onClick={() => openPayment(room)}
                          disabled={isPaid}
                          className={`px-3.5 py-1.5 rounded text-xs font-semibold transition-all ${
                            isPaid
                              ? 'bg-gray-100 text-gray-400 border border-gray-200 cursor-not-allowed opacity-60'
                              : 'bg-[#1a3e2d] hover:bg-[#143124] text-white shadow-2xs active:scale-95 cursor-pointer'
                          }`}
                          title={isPaid ? 'Hóa đơn đã được thanh toán toàn bộ' : 'Tiến hành thu tiền'}
                        >
                          {isPaid ? 'Đã hoàn tất' : 'Thanh toán'}
                        </button>
                      </td>
                    </tr>
                  );
                })
              ) : (
                <tr>
                  <td colSpan={8} className="py-8 text-center text-xs text-gray-400">
                    Không tìm thấy hóa đơn phòng nào.
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
};

