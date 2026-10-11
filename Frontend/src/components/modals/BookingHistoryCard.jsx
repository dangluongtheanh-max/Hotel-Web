import React from 'react';
import { Star, MapPin, Calendar } from 'lucide-react';
import { useBooking } from '../../context/BookingContext';

export const BookingHistoryCard = ({ booking, onCancelClick }) => {
  const { openBookingDetail, openReviewModal } = useBooking();

  const formatVND = (num) => {
    return new Intl.NumberFormat('vi-VN').format(num) + 'đ';
  };

  return (
    <div className="bg-white border border-[#E7E2D9] rounded-none sm:rounded-sm overflow-hidden p-4 sm:p-5 flex flex-col md:flex-row gap-5 items-start md:items-center justify-between transition-all hover:border-[#b88d43]/50 hover:shadow-md">
      {/* Left: Thumbnail & Main Info */}
      <div className="flex items-start sm:items-center gap-4 w-full md:w-auto">
        <div className="w-24 h-20 sm:w-28 sm:h-24 rounded-xs overflow-hidden shrink-0 bg-gray-100">
          <img
            src={booking.image}
            alt={booking.roomTitle}
            className="w-full h-full object-cover"
          />
        </div>

        <div className="space-y-1">
          <div className="flex flex-wrap items-center gap-2">
            <span className="text-[11px] font-bold tracking-wider text-[#865e26] uppercase">
              {booking.id}
            </span>

            {/* Status Badges */}
            {booking.status === 'upcoming' && (
              <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                <span className="w-1.5 h-1.5 rounded-full bg-emerald-500" />
                Đã xác nhận
              </span>
            )}

            {booking.status === 'completed' && (
              <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                <span className="w-1.5 h-1.5 rounded-full bg-blue-500" />
                Đã hoàn thành
              </span>
            )}

            {booking.status === 'cancelled' && (
              <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-rose-50 text-rose-700 border border-rose-200">
                <span className="w-1.5 h-1.5 rounded-full bg-rose-500" />
                Đã hủy
              </span>
            )}

            {booking.status === 'active' && (
              <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                <span className="w-1.5 h-1.5 rounded-full bg-amber-500" />
                Đang ở
              </span>
            )}
          </div>

          <h4 className="font-serif text-base sm:text-lg font-bold text-[#1c2920]">
            {booking.roomTitle}
          </h4>

          <div className="flex flex-wrap items-center gap-3 text-xs text-[#6C756B]">
            <span className="flex items-center gap-1">
              <Calendar className="w-3.5 h-3.5 text-[#865e26]" />
              {booking.dateRangeDisplay} ({booking.nights} đêm)
            </span>
            <span>·</span>
            <span className="font-semibold text-[#1a3e2d]">
              {formatVND(booking.totalPrice)}
            </span>
          </div>

          {booking.review && (
            <div className="flex items-center gap-1 text-xs text-amber-600 font-medium pt-0.5">
              <Star className="w-3.5 h-3.5 fill-amber-500 text-amber-500" />
              <span>Đã đánh giá: {booking.review.rating}/5 sao</span>
            </div>
          )}
        </div>
      </div>

      {/* Right: Actions based on User Stories 6 & 7 */}
      <div className="flex items-center gap-3 w-full md:w-auto justify-end border-t md:border-t-0 pt-3 md:pt-0 border-gray-100">
        {/* User Story 6: Upcoming actions */}
        {booking.status === 'upcoming' && (
          <>
            <button
              onClick={() => onCancelClick(booking.id)}
              className="px-4 py-2 text-xs font-semibold text-[#8a3333] border border-[#d69f9f] hover:bg-rose-50 rounded-xs transition-colors whitespace-nowrap active:scale-[0.98]"
            >
              Hủy phòng
            </button>
            <button
              onClick={() => openBookingDetail(booking)}
              className="px-4 py-2 text-xs font-semibold text-white bg-[#1a3e2d] hover:bg-[#143124] rounded-xs transition-colors whitespace-nowrap active:scale-[0.98]"
            >
              Xem chi tiết
            </button>
          </>
        )}

        {/* User Story 7: Completed actions */}
        {booking.status === 'completed' && (
          <button
            onClick={() => openReviewModal(booking)}
            className="px-4 py-2 text-xs font-semibold text-[#865e26] border border-[#dfc28d] hover:bg-[#FAF8F5] rounded-xs transition-colors flex items-center gap-1.5 whitespace-nowrap active:scale-[0.98]"
          >
            <Star className="w-3.5 h-3.5 fill-[#865e26] text-[#865e26]" />
            <span>{booking.review ? 'Chỉnh sửa đánh giá' : 'Viết đánh giá'}</span>
          </button>
        )}

        {/* Cancelled or Active actions */}
        {booking.status === 'cancelled' && (
          <button
            onClick={() => openBookingDetail(booking)}
            className="px-4 py-2 text-xs font-medium text-[#6C756B] border border-[#ECE6DE] hover:bg-gray-50 rounded-xs transition-colors"
          >
            Xem lại
          </button>
        )}

        {booking.status === 'active' && (
          <button
            onClick={() => openBookingDetail(booking)}
            className="px-4 py-2 text-xs font-semibold text-white bg-[#1a3e2d] hover:bg-[#143124] rounded-xs transition-colors"
          >
            Xem chi tiết
          </button>
        )}
      </div>
    </div>
  );
};

