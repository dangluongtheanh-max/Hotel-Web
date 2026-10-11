import React from 'react';
import { CheckCircle2, Info, AlertCircle, X } from 'lucide-react';
import { useBooking } from '../../context/BookingContext';

export const Toast = () => {
  const { toast } = useBooking();

  if (!toast.show) return null;

  return (
    <div className="fixed bottom-6 right-6 z-50 flex items-center gap-3 bg-[#173929] text-white px-5 py-4 rounded-lg shadow-2xl border border-[#cfa964]/40 animate-fade-in transition-all max-w-md">
      {toast.type === 'success' && <CheckCircle2 className="w-5 h-5 text-[#cfa964] shrink-0" />}
      {toast.type === 'info' && <Info className="w-5 h-5 text-[#cfa964] shrink-0" />}
      {toast.type === 'error' && <AlertCircle className="w-5 h-5 text-red-400 shrink-0" />}
      
      <p className="text-sm font-medium tracking-wide leading-relaxed">{toast.message}</p>
    </div>
  );
};

