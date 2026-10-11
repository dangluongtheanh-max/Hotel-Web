import React from 'react';
import { Plus, Check } from 'lucide-react';
import { useBooking } from '../../context/BookingContext';

export const ServiceCard = ({ service }) => {
  const { addExtraService, selectedServices } = useBooking();

  const isSelected = selectedServices.some((s) => s.id === service.id);

  const formatVND = (amount) => {
    return new Intl.NumberFormat('vi-VN').format(amount) + 'đ';
  };

  return (
    <div className="bg-white border border-[#E7E2D9] rounded-none sm:rounded-sm overflow-hidden flex flex-col sm:flex-row transition-all duration-300 hover:shadow-lg hover:border-[#b88d43]/50 group">
      {/* Service Image */}
      <div className="sm:w-2/5 aspect-[16/10] sm:aspect-auto overflow-hidden bg-gray-100 relative shrink-0">
        <img
          src={service.image}
          alt={service.name}
          className="w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-700"
        />
      </div>

      {/* Service Information */}
      <div className="sm:w-3/5 p-4 sm:p-5 flex flex-col justify-between">
        <div>
          <span className="block text-[10px] sm:text-[11px] text-[#788579] font-medium tracking-wide mb-1">
            {service.tag}
          </span>
          <h4 className="font-serif text-base sm:text-lg font-semibold text-[#1c2920] mb-3 group-hover:text-[#1a3e2d] transition-colors">
            {service.name}
          </h4>
        </div>

        {/* Bottom price and add button */}
        <div className="pt-3 border-t border-[#ECE6DE] flex items-center justify-between gap-2">
          <span className="text-xs sm:text-sm font-bold text-[#1c2920]">
            {formatVND(service.price)}
          </span>

          <button
            onClick={() => addExtraService(service)}
            className={`text-[11px] sm:text-xs font-semibold py-1.5 px-2.5 rounded-none transition-all flex items-center gap-1 active:scale-95 ${
              isSelected
                ? 'text-[#1a3e2d] bg-[#f0f6f2] border border-[#1a3e2d]/30'
                : 'text-[#47574b] hover:text-[#1a3e2d] hover:bg-[#FAF8F5]'
            }`}
          >
            {isSelected ? (
              <>
                <Check className="w-3.5 h-3.5 text-[#1a3e2d]" />
                <span>Đã thêm</span>
              </>
            ) : (
              <>
                <Plus className="w-3.5 h-3.5 text-[#865e26]" />
                <span>Thêm vào phòng</span>
              </>
            )}
          </button>
        </div>
      </div>
    </div>
  );
};

