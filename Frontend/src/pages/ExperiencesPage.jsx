import React from 'react';
import { ServiceCard } from '../components/home/ServiceCard';
import { useBooking } from '../context/BookingContext';
import { Sparkles, Coffee, HeartPulse, Compass } from 'lucide-react';

export const ExperiencesPage = () => {
  const { services } = useBooking();

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
      {/* Header */}
      <div className="text-center max-w-2xl mx-auto mb-12">
        <span className="block text-[11px] font-bold tracking-[0.2em] text-[#865e26] uppercase mb-2">
          Dịch vụ & Trải nghiệm
        </span>
        <h1 className="font-serif text-3xl sm:text-4xl font-bold text-[#1a3e2d] mb-4">
          Nâng Tầm Kỳ Nghỉ Của Bạn
        </h1>
        <p className="text-sm text-[#6C756B] leading-relaxed">
          Tận hưởng những giây phút thư thái tối đa cùng các dịch vụ chăm sóc sức khỏe, ẩm thực thượng hạng và hành trình khám phá riêng tư.
        </p>
      </div>

      {/* Feature highlight items */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-14 text-center">
        <div className="bg-white p-5 border border-[#ECE6DE] rounded-xs">
          <div className="w-10 h-10 rounded-full bg-[#f6efe4] text-[#865e26] flex items-center justify-center mx-auto mb-3">
            <HeartPulse className="w-5 h-5" />
          </div>
          <h4 className="font-bold text-xs text-[#1c2920] mb-1">Spa & Trị Liệu</h4>
          <p className="text-[11px] text-[#6C756B]">Liệu pháp tự nhiên</p>
        </div>

        <div className="bg-white p-5 border border-[#ECE6DE] rounded-xs">
          <div className="w-10 h-10 rounded-full bg-[#f6efe4] text-[#865e26] flex items-center justify-center mx-auto mb-3">
            <Coffee className="w-5 h-5" />
          </div>
          <h4 className="font-bold text-xs text-[#1c2920] mb-1">Ẩm Thực Tại Phòng</h4>
          <p className="text-[11px] text-[#6C756B]">Set menu 5 sao</p>
        </div>

        <div className="bg-white p-5 border border-[#ECE6DE] rounded-xs">
          <div className="w-10 h-10 rounded-full bg-[#f6efe4] text-[#865e26] flex items-center justify-center mx-auto mb-3">
            <Compass className="w-5 h-5" />
          </div>
          <h4 className="font-bold text-xs text-[#1c2920] mb-1">Đón Tiễn Cao Cấp</h4>
          <p className="text-[11px] text-[#6C756B]">Xe riêng đưa đón</p>
        </div>

        <div className="bg-white p-5 border border-[#ECE6DE] rounded-xs">
          <div className="w-10 h-10 rounded-full bg-[#f6efe4] text-[#865e26] flex items-center justify-center mx-auto mb-3">
            <Sparkles className="w-5 h-5" />
          </div>
          <h4 className="font-bold text-xs text-[#1c2920] mb-1">Dịch Vụ Cá Nhân</h4>
          <p className="text-[11px] text-[#6C756B]">Quản gia 24/7</p>
        </div>
      </div>

      {/* Services List */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-7">
        {services.map((service) => (
          <ServiceCard key={service.id} service={service} />
        ))}
      </div>
    </div>
  );
};

