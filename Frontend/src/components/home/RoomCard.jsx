import React from 'react';
import { MapPin, Star, Bed, Bath, Wifi, Heart } from 'lucide-react';
import { useBooking } from '../../context/BookingContext';

export const RoomCard = ({ room }) => {
  const { openCheckout, favorites, toggleFavorite } = useBooking();
  const isFavorite = favorites.includes(room.id);

  // Format currency
  const formatVND = (amount) => {
    return new Intl.NumberFormat('vi-VN').format(amount) + 'đ';
  };

  return (
    <div className="bg-white border border-[#E7E2D9] rounded-none sm:rounded-sm overflow-hidden flex flex-col transition-all duration-300 hover:shadow-xl hover:border-[#b88d43]/60 group">
      {/* Image Container with Badges */}
      <div className="relative aspect-[16/10] sm:aspect-[16/11] overflow-hidden bg-gray-100">
        <img
          src={room.image}
          alt={room.name}
          className="w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-700"
        />

        {/* Top-left Badge */}
        {room.badge && (
          <div className="absolute top-3 left-3 bg-white/95 backdrop-blur-xs text-[#1c2920] text-[11px] font-semibold px-2.5 py-1 rounded-sm shadow-xs border border-black/5 tracking-wide">
            {room.badge}
          </div>
        )}

        {/* Top-right Favorite Heart Button */}
        <button
          onClick={(e) => {
            e.stopPropagation();
            toggleFavorite(room.id);
          }}
          className="absolute top-3 right-3 w-8 h-8 rounded-full bg-black/35 hover:bg-black/50 backdrop-blur-xs flex items-center justify-center text-white transition-all active:scale-90"
          title={isFavorite ? 'Xóa khỏi yêu thích' : 'Lưu vào yêu thích'}
        >
          <Heart
            className={`w-4 h-4 transition-colors ${
              isFavorite ? 'fill-[#e05656] text-[#e05656]' : 'text-white'
            }`}
          />
        </button>
      </div>

      {/* Card Content */}
      <div className="p-5 sm:p-6 flex-1 flex flex-col justify-between">
        <div>
          {/* Location & Rating Header */}
          <div className="flex items-center justify-between text-xs text-[#6C756B] mb-2 gap-2">
            <div className="flex items-center gap-1.5 truncate">
              <MapPin className="w-3.5 h-3.5 text-[#865e26] shrink-0" />
              <span className="truncate">{room.location}</span>
            </div>
            <div className="flex items-center gap-1 text-[#1c2920] font-medium shrink-0">
              <Star className="w-3.5 h-3.5 text-amber-500 fill-amber-500" />
              <span>{room.rating}</span>
              <span className="text-[#8e978d] text-[11px]">({room.reviewsCount})</span>
            </div>
          </div>

          {/* Room Title */}
          <h3 className="font-serif text-lg sm:text-xl font-semibold text-[#1c2920] mb-3 group-hover:text-[#1a3e2d] transition-colors">
            {room.name}
          </h3>

          {/* Amenities Row */}
          <div className="flex flex-wrap items-center gap-4 text-xs text-[#6C756B] pb-5 mb-5 border-b border-[#ECE6DE]">
            <div className="flex items-center gap-1.5">
              <Bed className="w-3.5 h-3.5 text-[#865e26]" />
              <span>1 giường King</span>
            </div>
            <div className="flex items-center gap-1.5">
              <Bath className="w-3.5 h-3.5 text-[#865e26]" />
              <span>Bồn tắm</span>
            </div>
            <div className="flex items-center gap-1.5">
              <Wifi className="w-3.5 h-3.5 text-[#865e26]" />
              <span>Wifi</span>
            </div>
          </div>
        </div>

        {/* Pricing & CTA */}
        <div className="flex items-end justify-between gap-3 pt-1">
          <div>
            {room.originalPrice && (
              <span className="block text-xs text-[#8e978d] line-through font-normal">
                {formatVND(room.originalPrice)}
              </span>
            )}
            <div className="flex items-baseline gap-1">
              <span className="text-base sm:text-lg font-bold text-[#1a3e2d]">
                {formatVND(room.price)}
              </span>
              <span className="text-xs text-[#6C756B] font-light">/ đêm</span>
            </div>
          </div>

          <button
            onClick={() => openCheckout(room)}
            className="px-4 py-2 text-xs sm:text-sm font-medium text-[#1a3e2d] border border-[#1a3e2d] hover:bg-[#1a3e2d] hover:text-white transition-all rounded-none sm:rounded-xs whitespace-nowrap active:scale-[0.97]"
          >
            Chọn phòng
          </button>
        </div>
      </div>
    </div>
  );
};

