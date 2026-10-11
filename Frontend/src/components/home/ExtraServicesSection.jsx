import React from 'react';
import { ServiceCard } from './ServiceCard';
import { useBooking } from '../../context/BookingContext';

export const ExtraServicesSection = () => {
  const { services } = useBooking();

  return (
    <section id="services-section" className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-20">
      {/* Section Header */}
      <div className="mb-8 sm:mb-10">
        <span className="block text-[11px] font-bold tracking-[0.2em] text-[#865e26] uppercase mb-2">
          Trọn vẹn kỳ nghỉ
        </span>
        <h2 className="text-2xl sm:text-3xl lg:text-4xl font-sans font-bold text-[#1c2920] tracking-tight">
          Thêm một chút{' '}
          <span className="font-serif italic font-normal text-[#865e26]">
            thảnh thơi.
          </span>
        </h2>
      </div>

      {/* Services Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-7">
        {services.map((service) => (
          <ServiceCard key={service.id} service={service} />
        ))}
      </div>
    </section>
  );
};

