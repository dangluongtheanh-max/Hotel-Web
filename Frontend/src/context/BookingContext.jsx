import React, { createContext, useContext, useState } from 'react';
import { initialRooms } from '../data/roomsData';
import { initialServices } from '../data/servicesData';
import { initialBookings } from '../data/bookingsData';

const BookingContext = createContext();

export const BookingProvider = ({ children }) => {
  // Search parameters
  const [searchParams, setSearchParams] = useState({
    location: 'Nha Trang, Việt Nam',
    checkIn: '2025-05-24',
    checkInDisplay: '24 Thg 05, 2025',
    checkOut: '2025-05-27',
    checkOutDisplay: '27 Thg 05, 2025',
    adults: 2,
    children: 0,
    roomsCount: 1,
    guestsDisplay: '2 khách, 1 phòng',
    nights: 3
  });

  // User details & Authentication state
  const [isLoggedIn, setIsLoggedIn] = useState(false);
  const [currentUser, setCurrentUser] = useState({
    name: 'Minh Anh',
    fullName: 'Nguyễn Minh Anh',
    phone: '090 123 4567',
    email: 'minhanh@gmail.com'
  });
  const [authModal, setAuthModal] = useState({ isOpen: false, tab: 'login' }); // 'login' | 'register'

  const openLogin = () => setAuthModal({ isOpen: true, tab: 'login' });
  const openRegister = () => setAuthModal({ isOpen: true, tab: 'register' });
  const closeAuthModal = () => setAuthModal({ isOpen: false, tab: 'login' });

  const login = (user) => {
    setIsLoggedIn(true);
    if (user) {
      setCurrentUser((prev) => ({ ...prev, ...user }));
    }
    closeAuthModal();
    showToast(`Đăng nhập thành công! Chào mừng ${user?.name || currentUser.name}`, 'success');
  };

  const logout = () => {
    setIsLoggedIn(false);
    showToast('Đã đăng xuất tài khoản', 'info');
  };

  // Language state
  const [language, setLanguage] = useState('VI');

  // Rooms and favorites
  const [rooms, setRooms] = useState(initialRooms);
  const [favorites, setFavorites] = useState(['room-1']);

  // Services
  const [services] = useState(initialServices);
  const [selectedServices, setSelectedServices] = useState([]);

  // Bookings list
  const [bookings, setBookings] = useState(initialBookings);

  // Modals state
  const [activeModal, setActiveModal] = useState(null); // 'checkout' | 'bookingHistory' | 'bookingDetail' | 'review'
  const [modalPayload, setModalPayload] = useState(null);
  const [historyActiveTab, setHistoryActiveTab] = useState('upcoming');

  // Toast notification state
  const [toast, setToast] = useState({ show: false, message: '', type: 'success' });

  const showToast = (message, type = 'success') => {
    setToast({ show: true, message, type });
    setTimeout(() => {
      setToast({ show: false, message: '', type: 'success' });
    }, 3800);
  };

  const toggleFavorite = (roomId) => {
    setFavorites((prev) => {
      const isFav = prev.includes(roomId);
      const updated = isFav ? prev.filter((id) => id !== roomId) : [...prev, roomId];
      showToast(isFav ? 'Đã xóa khỏi danh sách yêu thích' : 'Đã lưu vào danh sách yêu thích', 'info');
      return updated;
    });
  };

  const openCheckout = (room) => {
    const targetRoom = room || rooms[0];
    setModalPayload({ room: targetRoom });
    setActiveModal('checkout');
  };

  const openBookingHistory = (tab = 'upcoming') => {
    setHistoryActiveTab(tab);
    setActiveModal('bookingHistory');
  };

  const openBookingDetail = (booking) => {
    setModalPayload({ booking });
    setActiveModal('bookingDetail');
  };

  const openReviewModal = (booking) => {
    setModalPayload({ booking });
    setActiveModal('review');
  };

  const closeModal = () => {
    setActiveModal(null);
    setModalPayload(null);
  };

  const addExtraService = (service) => {
    const exists = selectedServices.some((s) => s.id === service.id);
    if (!exists) {
      setSelectedServices((prev) => [...prev, service]);
      showToast(`Đã thêm dịch vụ "${service.name}" vào phòng!`, 'success');
    } else {
      showToast(`Dịch vụ "${service.name}" đã có trong danh sách của bạn`, 'info');
    }
  };

  const removeExtraService = (serviceId) => {
    setSelectedServices((prev) => prev.filter((s) => s.id !== serviceId));
    showToast('Đã xóa dịch vụ', 'info');
  };

  const cancelBooking = (bookingId) => {
    setBookings((prev) =>
      prev.map((b) =>
        b.id === bookingId
          ? { ...b, status: 'cancelled', statusText: 'Đã hủy', cancelledAt: new Date().toISOString() }
          : b
      )
    );
    setHistoryActiveTab('cancelled');
    showToast('Hủy đặt phòng thành công.', 'info');
  };

  const submitReview = (bookingId, rating, comment) => {
    setBookings((prev) =>
      prev.map((b) =>
        b.id === bookingId
          ? { ...b, review: { rating, comment, date: new Date().toLocaleDateString('vi-VN') } }
          : b
      )
    );
    closeModal();
    showToast('Cảm ơn bạn đã gửi đánh giá trải nghiệm!', 'success');
  };

  const createBooking = (newBooking) => {
    setBookings((prev) => [newBooking, ...prev]);
    showToast('Đặt phòng thành công! Mã đơn: ' + newBooking.id, 'success');
  };

  // Find upcoming trip for banner
  const upcomingTrip = bookings.find((b) => b.status === 'upcoming') || null;

  return (
    <BookingContext.Provider
      value={{
        searchParams,
        setSearchParams,
        currentUser,
        language,
        setLanguage,
        rooms,
        setRooms,
        favorites,
        toggleFavorite,
        services,
        selectedServices,
        addExtraService,
        removeExtraService,
        setSelectedServices,
        bookings,
        upcomingTrip,
        activeModal,
        modalPayload,
        historyActiveTab,
        setHistoryActiveTab,
        openCheckout,
        openBookingHistory,
        openBookingDetail,
        openReviewModal,
        closeModal,
        cancelBooking,
        submitReview,
        isLoggedIn,
        currentUser,
        authModal,
        openLogin,
        openRegister,
        closeAuthModal,
        login,
        logout,
        toast,
        showToast
      }}
    >
      {children}
    </BookingContext.Provider>
  );
};

export const useBooking = () => {
  const context = useContext(BookingContext);
  if (!context) {
    throw new Error('useBooking must be used within a BookingProvider');
  }
  return context;
};

