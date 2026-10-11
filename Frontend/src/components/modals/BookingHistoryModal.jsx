import React, { useState } from 'react';
import { X, AlertTriangle } from 'lucide-react';
import { useBooking } from '../../context/BookingContext';
import { BookingHistoryCard } from './BookingHistoryCard';
import { EmptyState } from '../common/EmptyState';

export const BookingHistoryModal = () => {
  const {
    activeModal,
    closeModal,
    bookings,
    historyActiveTab,
    setHistoryActiveTab,
    cancelBooking
  } = useBooking();

  // State for cancellation confirmation dialog
  const [cancellingBookingId, setCancellingBookingId] = useState(null);

  if (activeModal !== 'bookingHistory') return null;

  // Filter bookings by tab status
  const upcomingList = bookings.filter((b) => b.status === 'upcoming');
  const activeList = bookings.filter((b) => b.status === 'active');
  const completedList = bookings.filter((b) => b.status === 'completed');
  const cancelledList = bookings.filter((b) => b.status === 'cancelled');

  // Tab definitions matching User Story 5
  const tabs = [
    { key: 'upcoming', label: 'Sắp tới', count: upcomingList.length, data: upcomingList },
    { key: 'active', label: 'Đang ở', count: activeList.length, data: activeList },
    { key: 'completed', label: 'Đã hoàn thành', count: completedList.length, data: completedList },
    { key: 'cancelled', label: 'Đã hủy', count: cancelledList.length, data: cancelledList },
  ];

  const currentTabObj = tabs.find((t) => t.key === historyActiveTab) || tabs[0];

  const handleConfirmCancel = () => {
    if (cancellingBookingId) {
      cancelBooking(cancellingBookingId);
      setCancellingBookingId(null);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6 overflow-y-auto">
      {/* Backdrop */}
      <div
        className="fixed inset-0 bg-black/60 backdrop-blur-xs transition-opacity"
        onClick={closeModal}
      />

      {/* Main Modal Box */}
      <div className="relative w-full max-w-4xl bg-[#FAF8F5] text-[#1c2920] shadow-2xl rounded-none sm:rounded-md border border-[#E7E2D9] my-auto z-10 overflow-hidden max-h-[90vh] flex flex-col">
        {/* Header */}
        <div className="p-5 sm:p-7 border-b border-[#ECE6DE] flex items-center justify-between bg-white">
          <div>
            <span className="block text-[11px] font-bold tracking-[0.2em] text-[#865e26] uppercase mb-1">
              Quản lý lịch trình
            </span>
            <h2 className="font-serif text-2xl sm:text-3xl font-bold text-[#1a3e2d]">
              Chuyến đi của tôi
            </h2>
          </div>

          <button
            onClick={closeModal}
            className="p-2 rounded-full hover:bg-gray-100 text-[#4a554d] hover:text-black transition-colors"
            title="Đóng"
          >
            <X className="w-5 h-5 sm:w-6 sm:h-6" />
          </button>
        </div>

        {/* User Story 5: 4-Tabs Navigation */}
        <div className="bg-white border-b border-[#ECE6DE] px-5 sm:px-7 flex items-center gap-6 sm:gap-10 overflow-x-auto no-scrollbar">
          {tabs.map((tab) => {
            const isActive = historyActiveTab === tab.key;
            return (
              <button
                key={tab.key}
                onClick={() => setHistoryActiveTab(tab.key)}
                className={`py-4 text-xs sm:text-sm transition-all whitespace-nowrap flex items-center gap-2 border-b-2 -mb-[2px] ${
                  isActive
                    ? 'font-bold text-[#1a3e2d] border-[#865e26]'
                    : 'font-medium text-[#6C756B] border-transparent hover:text-[#1a3e2d]'
                }`}
              >
                <span>{tab.label}</span>
                <span
                  className={`text-[11px] px-2 py-0.5 rounded-full font-semibold transition-colors ${
                    isActive
                      ? 'bg-[#f4ede2] text-[#865e26]'
                      : 'bg-gray-100 text-gray-500'
                  }`}
                >
                  {tab.count}
                </span>
              </button>
            );
          })}
        </div>

        {/* Tab Content List / Empty State */}
        <div className="p-5 sm:p-7 overflow-y-auto flex-1">
          {currentTabObj.data.length > 0 ? (
            <div className="space-y-4">
              {currentTabObj.data.map((booking) => (
                <BookingHistoryCard
                  key={booking.id}
                  booking={booking}
                  onCancelClick={(id) => setCancellingBookingId(id)}
                />
              ))}
            </div>
          ) : (
            /* User Story 8: EmptyState component */
            <EmptyState
              title="Chưa có chuyến đi"
              subtitle="Các đặt phòng thuộc trạng thái này sẽ xuất hiện tại đây"
            />
          )}
        </div>

        {/* Cancellation Confirmation Dialog */}
        {cancellingBookingId && (
          <div className="absolute inset-0 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4 z-50 animate-fade-in">
            <div className="bg-white p-6 rounded-sm max-w-md w-full shadow-2xl border border-[#ECE6DE] text-center">
              <div className="w-12 h-12 rounded-full bg-rose-50 text-rose-600 flex items-center justify-center mx-auto mb-4 border border-rose-200">
                <AlertTriangle className="w-6 h-6" />
              </div>
              <h3 className="font-serif text-lg font-bold text-[#1c2920] mb-2">
                Xác nhận hủy đặt phòng
              </h3>
              <p className="text-xs sm:text-sm text-[#6C756B] leading-relaxed mb-6">
                Bạn có chắc chắn muốn hủy đặt phòng{' '}
                <span className="font-bold text-[#1c2920]">{cancellingBookingId}</span>?
                Thao tác này sẽ chuyển chuyến đi vào danh mục Đã hủy.
              </p>
              <div className="flex gap-3">
                <button
                  type="button"
                  onClick={() => setCancellingBookingId(null)}
                  className="flex-1 py-2.5 border border-[#D5CEC2] text-xs font-semibold text-[#1c2920] hover:bg-gray-50 transition-colors"
                >
                  Giữ lại đặt phòng
                </button>
                <button
                  type="button"
                  onClick={handleConfirmCancel}
                  className="flex-1 py-2.5 bg-rose-700 text-white text-xs font-semibold hover:bg-rose-800 transition-colors"
                >
                  Xác nhận hủy
                </button>
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

