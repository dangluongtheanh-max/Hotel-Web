import React, { createContext, useContext, useState, useMemo } from 'react';
import { initialReceptionRooms, initialExpectedArrivals } from '../data/receptionData';

const ReceptionContext = createContext();

export const ReceptionProvider = ({ children }) => {
  const [rooms, setRooms] = useState(initialReceptionRooms);
  const [expectedArrivals, setExpectedArrivals] = useState(initialExpectedArrivals);
  const [globalSearch, setGlobalSearch] = useState('');

  // Staff profile
  const [staff] = useState({
    name: 'Linh Trần',
    role: 'Lễ tân',
    shift: 'Ca sáng · 06:00 — 14:00',
    avatar: 'LT'
  });

  // Modals management
  const [checkInModal, setCheckInModal] = useState({ isOpen: false, arrivalData: null, preselectedRoom: '' });
  const [checkOutModal, setCheckOutModal] = useState({ isOpen: false, preselectedRoomId: '' });
  const [paymentModal, setPaymentModal] = useState({ isOpen: false, room: null, returnToCheckOut: false });
  const [roomDetailModal, setRoomDetailModal] = useState({ isOpen: false, room: null });

  // Notifications
  const [toast, setToast] = useState({ show: false, message: '', type: 'success' });

  const showToast = (message, type = 'success') => {
    setToast({ show: true, message, type });
    setTimeout(() => {
      setToast({ show: false, message: '', type: 'success' });
    }, 3800);
  };

  // Calculated Stats for Dashboard
  const stats = useMemo(() => {
    const total = rooms.length;
    const available = rooms.filter((r) => r.status === 'available').length;
    const cleaning = rooms.filter((r) => r.status === 'cleaning').length;
    const occupied = rooms.filter((r) => r.status === 'occupied').length;

    return {
      total,
      available,
      cleaning,
      occupied,
      arrivalsCount: 12,
      arrivalsPending: expectedArrivals.length,
      departuresCount: 8,
      departuresPending: 3,
      cleaningPending: cleaning
    };
  }, [rooms, expectedArrivals]);

  // Open & Close CheckInModal
  const openCheckIn = (arrivalData = null, roomId = '') => {
    setCheckInModal({ isOpen: true, arrivalData, preselectedRoom: roomId });
  };
  const closeCheckIn = () => {
    setCheckInModal({ isOpen: false, arrivalData: null, preselectedRoom: '' });
  };

  // Confirm CheckIn
  const performCheckIn = ({ roomId, guestName, cccd, phone, bookingId, arrivalId }) => {
    setRooms((prev) =>
      prev.map((r) => {
        if (r.id === roomId) {
          return {
            ...r,
            status: 'occupied',
            guestName,
            cccd,
            phone,
            bookingId: bookingId || `BK-${Date.now().toString().slice(-6)}`,
            checkInTime: new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' }),
            expectedCheckOut: 'Trả phòng 12:00 ngày mai',
            paymentStatus: 'UNPAID',
            totalAmount: r.pricePerNight * 2,
            paidAmount: 0,
            remainingAmount: r.pricePerNight * 2
          };
        }
        return r;
      })
    );

    if (arrivalId) {
      setExpectedArrivals((prev) => prev.filter((a) => a.id !== arrivalId));
    }

    closeCheckIn();
    showToast(`Check-in phòng ${roomId} cho khách ${guestName} thành công!`, 'success');
  };

  // Open & Close CheckOutModal
  const openCheckOut = (preselectedRoomId = '') => {
    setCheckOutModal({ isOpen: true, preselectedRoomId });
  };
  const closeCheckOut = () => {
    setCheckOutModal({ isOpen: false, preselectedRoomId: '' });
  };

  // Handle Checkout (User Story 9: Nesting PaymentModal if UNPAID)
  const performCheckOut = (roomId) => {
    const room = rooms.find((r) => r.id === roomId);
    if (!room) return;

    if (room.paymentStatus === 'UNPAID' && room.remainingAmount > 0) {
      // User Story 9: If unpaid, pop PaymentModal on top of flow!
      closeCheckOut();
      openPayment(room, true);
      showToast(`Phòng ${room.number} còn chưa thanh toán ${new Intl.NumberFormat('vi-VN').format(room.remainingAmount)}đ. Vui lòng thu tiền trước khi trả phòng!`, 'info');
      return;
    }

    // Mark room as cleaning
    setRooms((prev) =>
      prev.map((r) => {
        if (r.id === roomId) {
          return {
            ...r,
            status: 'cleaning',
            guestName: null,
            phone: null,
            cccd: null,
            bookingId: null,
            expectedCheckOut: 'Đang dọn phòng',
            paymentStatus: 'PAID',
            remainingAmount: 0
          };
        }
        return r;
      })
    );

    closeCheckOut();
    showToast(`Đã hoàn tất trả phòng ${room.number}. Chuyển sang trạng thái Đang dọn dẹp.`, 'success');
  };

  // Open & Close PaymentModal
  const openPayment = (room, returnToCheckOut = false) => {
    setPaymentModal({ isOpen: true, room, returnToCheckOut });
  };
  const closePayment = () => {
    setPaymentModal({ isOpen: false, room: null, returnToCheckOut: false });
  };

  // Confirm Payment
  const performPayment = (roomId, amount) => {
    let completedRoom = null;
    setRooms((prev) =>
      prev.map((r) => {
        if (r.id === roomId) {
          const updated = {
            ...r,
            paymentStatus: 'PAID',
            paidAmount: r.totalAmount,
            remainingAmount: 0
          };
          completedRoom = updated;
          return updated;
        }
        return r;
      })
    );

    const wasNestedInCheckOut = paymentModal.returnToCheckOut;
    closePayment();
    showToast(`Đã thu đủ ${new Intl.NumberFormat('vi-VN').format(amount)}đ cho phòng ${roomId}!`, 'success');

    // If this payment was triggered during checkout, automatically complete the checkout now!
    if (wasNestedInCheckOut) {
      setTimeout(() => {
        setRooms((prev) =>
          prev.map((r) => {
            if (r.id === roomId) {
              return {
                ...r,
                status: 'cleaning',
                guestName: null,
                phone: null,
                cccd: null,
                bookingId: null,
                expectedCheckOut: 'Đang dọn phòng',
                remainingAmount: 0
              };
            }
            return r;
          })
        );
        showToast(`Đã hoàn tất thanh toán & trả phòng ${roomId}!`, 'success');
      }, 500);
    }
  };

  // Open & Close RoomDetailModal
  const openRoomDetail = (room) => {
    setRoomDetailModal({ isOpen: true, room });
  };
  const closeRoomDetail = () => {
    setRoomDetailModal({ isOpen: false, room: null });
  };

  // Update room status directly (User Story 13)
  const updateRoomStatus = (roomId, newStatus) => {
    const targetRoom = rooms.find((r) => r.id === roomId);
    // Nếu phòng đang trống hoặc chưa có khách mà đổi sang "Có khách", buộc mở modal check-in
    if (targetRoom && (targetRoom.status === 'available' || !targetRoom.guestName) && newStatus === 'occupied') {
      closeRoomDetail();
      openCheckIn(null, roomId);
      showToast(`Vui lòng hoàn tất thông tin nhận phòng để chuyển phòng ${roomId} sang Có khách.`, 'info');
      return;
    }

    setRooms((prev) =>
      prev.map((r) => {
        if (r.id === roomId) {
          return {
            ...r,
            status: newStatus,
            expectedCheckOut:
              newStatus === 'available'
                ? 'Sẵn sàng nhận khách'
                : newStatus === 'cleaning'
                ? 'Đang dọn dẹp'
                : r.expectedCheckOut
          };
        }
        return r;
      })
    );

    // Update in detail modal view as well
    if (roomDetailModal.room && roomDetailModal.room.id === roomId) {
      setRoomDetailModal((prev) => ({
        ...prev,
        room: { ...prev.room, status: newStatus }
      }));
    }

    const statusNames = {
      available: 'Trống / Sẵn sàng',
      cleaning: 'Đang dọn dẹp',
      occupied: 'Có khách',
      maintenance: 'Bảo trì'
    };
    showToast(`Đã cập nhật trạng thái phòng ${roomId} sang "${statusNames[newStatus]}".`, 'success');
  };

  return (
    <ReceptionContext.Provider
      value={{
        rooms,
        expectedArrivals,
        globalSearch,
        setGlobalSearch,
        staff,
        stats,
        // CheckIn
        checkInModal,
        openCheckIn,
        closeCheckIn,
        performCheckIn,
        // CheckOut
        checkOutModal,
        openCheckOut,
        closeCheckOut,
        performCheckOut,
        // Payment
        paymentModal,
        openPayment,
        closePayment,
        performPayment,
        // RoomDetail
        roomDetailModal,
        openRoomDetail,
        closeRoomDetail,
        updateRoomStatus,
        // Toast
        toast,
        showToast
      }}
    >
      {children}
    </ReceptionContext.Provider>
  );
};

export const useReception = () => {
  const context = useContext(ReceptionContext);
  if (!context) {
    throw new Error('useReception must be used within a ReceptionProvider');
  }
  return context;
};

