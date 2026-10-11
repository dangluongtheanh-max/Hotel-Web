import React from 'react';
import { SearchBar } from './SearchBar';

export const HeroSection = () => {
  return (
    <section className="relative w-full min-h-[620px] lg:min-h-[680px] flex flex-col justify-between">
      {/* Background with luxury resort image and dark gradient overlay for readability */}
      <div className="absolute inset-0 z-0 overflow-hidden">
        <img
          src="https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?auto=format&fit=crop&w=2000&q=85"
          alt="Lumière Resort & Retreats"
          className="w-full h-full object-cover object-center filter brightness-[0.88] contrast-[1.05] scale-[1.02] transform transition-transform duration-1000"
        />
        <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-black/40 to-black/30" />
      </div>

      {/* Hero Content */}
      <div className="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-20 sm:pt-28 pb-16 w-full text-left">
        <div className="max-w-2xl">
          {/* Collection Tag */}
          <span className="inline-block text-xs sm:text-sm font-semibold tracking-[0.25em] text-[#dfc28d] uppercase mb-4 drop-shadow-sm">
            LUMIÈRE COLLECTION
          </span>

          {/* Slogan */}
          <h1 className="text-4xl sm:text-5xl lg:text-6xl font-sans font-bold text-white tracking-tight leading-[1.15] mb-5 drop-shadow-md">
            Chạm vào những <br />
            <span className="font-serif italic font-normal text-[#edd19b] tracking-normal">
              khoảnh khắc riêng.
            </span>
          </h1>

          {/* Subtitle */}
          <p className="text-sm sm:text-base text-gray-200/90 font-light max-w-xl leading-relaxed drop-shadow">
            Không chỉ là một kỳ nghỉ. Đó là nơi mỗi khoảnh khắc được chăm chút để trở thành ký ức.
          </p>
        </div>
      </div>

      {/* Floating Search Bar (Anchored at the bottom) */}
      <div className="relative z-20 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full -mb-10 sm:-mb-8">
        <SearchBar />
      </div>
    </section>
  );
};

