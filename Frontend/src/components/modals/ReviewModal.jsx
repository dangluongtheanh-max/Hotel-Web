import React, { useState } from 'react';
import { X, Star } from 'lucide-react';
import { useBooking } from '../../context/BookingContext';

export const ReviewModal = () => {
  const { activeModal, modalPayload, closeModal, submitReview } = useBooking();
  const [rating, setRating] = useState(5);
  const [hoverRating, setHoverRating] = useState(0);
  const [comment, setComment] = useState('');

  if (activeModal !== 'review') return null;

  const booking = modalPayload?.booking;
  if (!booking) return null;

  const handleSubmit = (e) => {
    e.preventDefault();
    submitReview(booking.id, rating, comment);
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      {/* Backdrop */}
      <div className="fixed inset-0 bg-black/60 backdrop-blur-xs" onClick={closeModal} />

      {/* Modal Dialog */}
      <div className="relative w-full max-w-lg bg-[#FAF8F5] text-[#1c2920] shadow-2xl rounded-none sm:rounded-md border border-[#E7E2D9] p-6 sm:p-8 z-10 animate-fade-in">
        <button
          onClick={closeModal}
          className="absolute top-4 right-4 p-2 text-gray-500 hover:text-black"
        >
          <X className="w-5 h-5" />
        </button>

        <span className="block text-[11px] font-bold tracking-[0.2em] text-[#865e26] uppercase mb-1">
          Đánh giá trải nghiệm
        </span>
        <h3 className="font-serif text-xl sm:text-2xl font-bold text-[#1a3e2d] mb-1">
          {booking.roomTitle}
        </h3>
        <p className="text-xs text-[#6C756B] mb-6">
          Kỳ nghỉ: {booking.dateRangeDisplay} · Mã: {booking.id}
        </p>

        <form onSubmit={handleSubmit} className="space-y-5">
          {/* Star selector */}
          <div className="text-center py-2">
            <span className="block text-xs font-semibold text-[#49544c] mb-2">
              Mức độ hài lòng của bạn
            </span>
            <div className="flex items-center justify-center gap-2">
              {[1, 2, 3, 4, 5].map((star) => (
                <button
                  type="button"
                  key={star}
                  onClick={() => setRating(star)}
                  onMouseEnter={() => setHoverRating(star)}
                  onMouseLeave={() => setHoverRating(0)}
                  className="p-1 hover:scale-110 transition-transform"
                >
                  <Star
                    className={`w-7 h-7 sm:w-8 sm:h-8 ${
                      (hoverRating || rating) >= star
                        ? 'text-amber-500 fill-amber-500'
                        : 'text-gray-300'
                    }`}
                  />
                </button>
              ))}
            </div>
            <span className="block text-xs text-[#865e26] font-medium mt-1">
              {rating === 5 && 'Tuyệt vời, vượt xa mong đợi'}
              {rating === 4 && 'Rất tốt, dịch vụ chu đáo'}
              {rating === 3 && 'Hài lòng'}
              {rating === 2 && 'Cần cải thiện'}
              {rating === 1 && 'Chưa hài lòng'}
            </span>
          </div>

          {/* Comment */}
          <div>
            <label className="block text-xs font-medium text-[#49544c] mb-1.5">
              Chia sẻ cảm nghĩ của bạn
            </label>
            <textarea
              rows={4}
              required
              value={comment}
              onChange={(e) => setComment(e.target.value)}
              placeholder="Chia sẻ về không gian phòng, dịch vụ, thái độ nhân viên hoặc món ăn..."
              className="w-full bg-white border border-[#D5CEC2] rounded-xs p-3 text-xs sm:text-sm text-[#1c2920] focus:outline-none focus:border-[#1a3e2d]"
            />
          </div>

          <div className="flex gap-3 pt-2">
            <button
              type="button"
              onClick={closeModal}
              className="flex-1 py-2.5 border border-[#D5CEC2] text-xs sm:text-sm font-medium hover:bg-white transition-colors"
            >
              Hủy
            </button>
            <button
              type="submit"
              className="flex-1 py-2.5 bg-[#1a3e2d] text-white text-xs sm:text-sm font-medium hover:bg-[#143124] transition-colors"
            >
              Gửi đánh giá
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};

