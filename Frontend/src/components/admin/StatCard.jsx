import React from 'react';

export const StatCard = ({ title, count, subtitle, icon: Icon, badgeColor = 'green' }) => {
  return (
    <div className="bg-white border border-[#E7E2D9] rounded-lg p-5 sm:p-6 shadow-xs hover:shadow-md transition-all flex items-start justify-between group">
      <div>
        <span className="block text-[10px] sm:text-[11px] font-bold tracking-[0.18em] text-[#865e26] uppercase mb-1.5">
          {title}
        </span>
        <div className="text-3xl sm:text-4xl font-sans font-bold text-[#1c2920] mb-2 tracking-tight">
          {count}
        </div>
        <p className="text-xs text-[#6C756B] font-medium leading-relaxed">
          {subtitle}
        </p>
      </div>

      <div className="w-12 h-12 rounded-xl bg-[#FAF8F5] border border-[#ECE6DE] flex items-center justify-center text-[#1a3e2d] group-hover:scale-105 group-hover:border-[#b88d43] transition-all shrink-0">
        <Icon className="w-6 h-6 stroke-[1.8]" />
      </div>
    </div>
  );
};

