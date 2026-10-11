export const initialBookings = [
  {
    id: 'LMR-250524',
    roomTitle: 'Suite hướng biển',
    roomId: 'room-1',
    location: 'Nha Trang, Khánh Hòa',
    checkIn: '2025-05-24',
    checkOut: '2025-05-27',
    checkInDisplay: '24 Thg 05, 2025',
    checkOutDisplay: '27 Thg 05, 2025',
    dateRangeDisplay: '24 — 27 tháng 05, 2025',
    nights: 3,
    guests: '2 khách, 1 phòng',
    status: 'upcoming', // upcoming | active | completed | cancelled
    statusText: 'Đã xác nhận',
    guestName: 'Nguyễn Minh Anh',
    phone: '090 123 4567',
    email: 'minhanh@gmail.com',
    specialRequests: 'Tầng cao, view trọn vẹn biển, nhận phòng sớm nếu có thể',
    paymentMethod: 'credit_card', // credit_card | e_wallet | qr_bank
    pricePerNight: 2850000,
    roomTotal: 8550000,
    taxAndFees: 855000,
    discount: 0,
    extraServices: [],
    totalPrice: 9405000,
    image: 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?auto=format&fit=crop&w=1000&q=80',
    createdAt: '2025-05-01'
  },
  {
    id: 'LMR-120424',
    roomTitle: 'Deluxe City View',
    roomId: 'room-2',
    location: 'Quận 1, TP. Hồ Chí Minh',
    checkIn: '2025-04-12',
    checkOut: '2025-04-14',
    checkInDisplay: '12 Thg 04, 2025',
    checkOutDisplay: '14 Thg 04, 2025',
    dateRangeDisplay: '12 — 14 tháng 04, 2025',
    nights: 2,
    guests: '2 khách, 1 phòng',
    status: 'completed',
    statusText: 'Đã hoàn thành',
    guestName: 'Nguyễn Minh Anh',
    phone: '090 123 4567',
    email: 'minhanh@gmail.com',
    specialRequests: '',
    paymentMethod: 'e_wallet',
    pricePerNight: 2150000,
    roomTotal: 4300000,
    taxAndFees: 430000,
    discount: 0,
    extraServices: [
      { id: 'srv-1', name: 'Trị liệu An Nhiên', price: 650000 }
    ],
    totalPrice: 5380000,
    image: 'https://images.unsplash.com/photo-1590490360182-c33d57733427?auto=format&fit=crop&w=1000&q=80',
    createdAt: '2025-04-01',
    review: null // or { rating: 5, comment: 'Tuyệt vời...' }
  }
];

