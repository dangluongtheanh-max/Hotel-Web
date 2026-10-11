import React from 'react';
import { Outlet } from 'react-router-dom';
import { Header } from './Header';
import { Footer } from './Footer';
import { CheckoutModal } from '../modals/CheckoutModal';
import { BookingHistoryModal } from '../modals/BookingHistoryModal';
import { BookingDetailModal } from '../modals/BookingDetailModal';
import { ReviewModal } from '../modals/ReviewModal';
import { AuthModal } from '../modals/AuthModal';
import { Toast } from '../common/Toast';

export const MainLayout = () => {
  return (
    <div className="min-h-screen flex flex-col bg-[#FAF8F5] text-[#1c2920]">
      {/* Top Navigation */}
      <Header />

      {/* Main Content Area */}
      <main className="flex-1">
        <Outlet />
      </main>

      {/* Footer */}
      <Footer />

      {/* Global Modals & Overlays */}
      <CheckoutModal />
      <BookingHistoryModal />
      <BookingDetailModal />
      <ReviewModal />
      <AuthModal />

      {/* Toast Notification */}
      <Toast />
    </div>
  );
};

