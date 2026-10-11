import React from 'react';
import { Outlet } from 'react-router-dom';
import { Sidebar } from './Sidebar';
import { TopBar } from './TopBar';
import { CheckInModal } from './modals/CheckInModal';
import { CheckOutModal } from './modals/CheckOutModal';
import { PaymentModal } from './modals/PaymentModal';
import { RoomDetailModal } from './modals/RoomDetailModal';
import { useReception } from '../../context/ReceptionContext';
import { CheckCircle2, Info, AlertCircle } from 'lucide-react';

export const AdminLayout = () => {
  const { toast } = useReception();

  return (
    <div className="min-h-screen flex bg-[#FAF8F5] text-[#1c2920] font-sans antialiased">
      {/* 1. Sidebar */}
      <Sidebar />

      {/* 2. Main Content Area */}
      <div className="flex-1 flex flex-col min-w-0">
        <TopBar />

        <main className="flex-1 p-5 sm:p-7 lg:p-8 overflow-y-auto">
          <Outlet />
        </main>
      </div>

      {/* Centralized Modals System */}
      <CheckInModal />
      <CheckOutModal />
      <PaymentModal />
      <RoomDetailModal />

      {/* Reception Toast */}
      {toast.show && (
        <div className="fixed bottom-6 right-6 z-50 flex items-center gap-3 bg-[#10271c] text-white px-5 py-3.5 rounded-lg shadow-2xl border border-[#b88d43]/50 animate-fade-in max-w-md">
          {toast.type === 'success' && <CheckCircle2 className="w-5 h-5 text-emerald-400 shrink-0" />}
          {toast.type === 'info' && <Info className="w-5 h-5 text-[#dfc28d] shrink-0" />}
          {toast.type === 'error' && <AlertCircle className="w-5 h-5 text-rose-400 shrink-0" />}
          <p className="text-xs sm:text-sm font-medium leading-relaxed">{toast.message}</p>
        </div>
      )}
    </div>
  );
};

