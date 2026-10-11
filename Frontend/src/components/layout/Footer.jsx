import React from 'react';
import { Link } from 'react-router-dom';

export const Footer = () => {
  return (
    <footer className="bg-[#143124] text-[#d6ded8] border-t border-[#1d4633] py-12 transition-colors">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex flex-col md:flex-row items-center justify-between gap-8">
          {/* Left: Brand Logo */}
          <div className="flex items-center gap-3.5">
            <div className="w-10 h-10 border border-[#b88d43]/70 flex items-center justify-center bg-[#143124]">
              <span className="font-serif text-xl font-bold text-white tracking-widest">L</span>
            </div>
            <div className="flex flex-col">
              <span className="font-serif text-lg tracking-[0.2em] font-semibold text-white leading-none">
                LUMIÈRE
              </span>
              <span className="text-[9px] tracking-[0.3em] text-[#cfa964] font-medium mt-1">
                HOTELS & RETREATS
              </span>
            </div>
          </div>

          {/* Center: Brand Tagline */}
          <div className="text-center">
            <p className="text-sm font-light text-[#b2c2b7] tracking-wide">
              Những kỳ nghỉ được tạo nên từ sự tinh tế.
            </p>
          </div>

          {/* Right: Policy Links */}
          <div className="flex items-center gap-6 text-xs text-[#a2b5a8] tracking-wide">
            <a href="#terms" onClick={(e) => e.preventDefault()} className="hover:text-white transition-colors">
              Điều khoản
            </a>
            <a href="#privacy" onClick={(e) => e.preventDefault()} className="hover:text-white transition-colors">
              Quyền riêng tư
            </a>
            <a href="#support" onClick={(e) => e.preventDefault()} className="hover:text-white transition-colors">
              Hỗ trợ
            </a>
          </div>
        </div>

        {/* Bottom copyright */}
        <div className="mt-8 pt-6 border-t border-[#1d4633]/60 text-center text-[11px] text-[#7d9485]">
          © {new Date().getFullYear()} Lumière Hotels & Retreats Collection. Tất cả các quyền được bảo lưu.
        </div>
      </div>
    </footer>
  );
};

