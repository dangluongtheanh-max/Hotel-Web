import React, { useState } from 'react';
import { X, CreditCard, Wallet, ArrowRight, CheckCircle2, ShieldCheck, Star } from 'lucide-react';
import { useBooking } from '../../context/BookingContext';

export const CheckoutModal = () => {
  const {
    activeModal,
    modalPayload,
    closeModal,
    searchParams,
    currentUser,
    selectedServices,
    createBooking
  } = useBooking();

  // If not open, return null
  if (activeModal !== 'checkout') return null;

  const room = modalPayload?.room;
  if (!room) return null;

  // Form states
  const [guestName, setGuestName] = useState(currentUser.fullName || 'Nguyễn Minh Anh');
  const [phone, setPhone] = useState(currentUser.phone || '090 123 4567');
  const [email, setEmail] = useState(currentUser.email || 'minhanh@gmail.com');
  const [specialRequests, setSpecialRequests] = useState('');
  const [paymentMethod, setPaymentMethod] = useState('card'); // 'card' | 'wallet'
  const [agreedTerms, setAgreedTerms] = useState(true);

  // Promo code states
  const [promoCode, setPromoCode] = useState('');
  const [discountPercent, setDiscountPercent] = useState(0);
  const [promoMessage, setPromoMessage] = useState('');

  // Success state after booking
  const [bookingSuccess, setBookingSuccess] = useState(null);

  // Nights calculation
  const nights = searchParams.nights || 3;
  const roomPricePerNight = room.price;
  const roomSubtotal = roomPricePerNight * nights;

  // Selected services sum
  const servicesSubtotal = selectedServices.reduce((sum, s) => sum + s.price, 0);

  // Tax & fees (10%)
  const taxAndFees = Math.round(roomSubtotal * 0.1);

  // Discount calculation
  const discountAmount = Math.round((roomSubtotal * discountPercent) / 100);

  // Grand total
  const grandTotal = roomSubtotal + taxAndFees + servicesSubtotal - discountAmount;

  const formatVND = (num) => {
    return new Intl.NumberFormat('vi-VN').format(num) + 'đ';
  };

  const handleApplyPromo = (e) => {
    e.preventDefault();
    const cleanCode = promoCode.trim().toUpperCase();
    if (!cleanCode) {
      setPromoMessage('');
      setDiscountPercent(0);
      return;
    }

    if (cleanCode === 'LUMIERE10' || cleanCode === 'SUMMER10' || cleanCode === 'UUDAI10') {
      setDiscountPercent(10);
      setPromoMessage('Đã áp dụng mã giảm 10% thành công!');
    } else if (cleanCode === 'LUMIERE15' || cleanCode === 'VIP15') {
      setDiscountPercent(15);
      setPromoMessage('Đã áp dụng mã giảm 15% VIP thành công!');
    } else {
      // Default give 5% for any valid-looking code to be friendly
      setDiscountPercent(5);
      setPromoMessage(`Áp dụng mã "${cleanCode}" giảm 5%!`);
    }
  };

  const handleSubmitBooking = (e) => {
    e.preventDefault();
    if (!agreedTerms) {
      alert('Vui lòng đồng ý với điều khoản đặt phòng để tiếp tục.');
      return;
    }

    const randomNum = Math.floor(100000 + Math.random() * 900000);
    const bookingId = `LMR-${randomNum}`;

    const newBooking = {
      id: bookingId,
      roomTitle: room.name,
      roomId: room.id,
      location: room.location,
      checkIn: searchParams.checkIn,
      checkOut: searchParams.checkOut,
      checkInDisplay: searchParams.checkInDisplay,
      checkOutDisplay: searchParams.checkOutDisplay,
      dateRangeDisplay: `${searchParams.checkInDisplay.split(',')[0]} — ${searchParams.checkOutDisplay}`,
      nights: nights,
      guests: searchParams.guestsDisplay,
      status: 'upcoming',
      statusText: 'Đã xác nhận',
      guestName: guestName,
      phone: phone,
      email: email,
      specialRequests: specialRequests,
      paymentMethod: paymentMethod === 'card' ? 'credit_card' : 'e_wallet',
      pricePerNight: room.price,
      roomTotal: roomSubtotal,
      taxAndFees: taxAndFees,
      discount: discountAmount,
      extraServices: selectedServices,
      totalPrice: grandTotal,
      image: room.image,
      createdAt: new Date().toISOString()
    };

    createBooking(newBooking);
    setBookingSuccess(newBooking);
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-6 overflow-y-auto">
      {/* Backdrop */}
      <div
        className="fixed inset-0 bg-black/60 backdrop-blur-xs transition-opacity"
        onClick={closeModal}
      />

      {/* Modal Dialog */}
      <div className="relative w-full max-w-5xl bg-[#FAF8F5] text-[#1c2920] shadow-2xl rounded-none sm:rounded-md border border-[#E7E2D9] my-auto z-10 overflow-hidden max-h-[92vh] flex flex-col">
        {/* Close button */}
        <button
          onClick={closeModal}
          className="absolute top-4 right-4 sm:top-5 sm:right-5 p-2 rounded-full hover:bg-black/5 text-[#4a554d] hover:text-black transition-colors z-20"
          title="Đóng"
        >
          <X className="w-5 h-5 sm:w-6 sm:h-6" />
        </button>

        {bookingSuccess ? (
          /* Confirmation Success Screen */
          <div className="p-8 sm:p-12 text-center flex flex-col items-center justify-center max-w-lg mx-auto my-auto animate-fade-in">
            <div className="w-16 h-16 rounded-full bg-[#1a3e2d] text-[#cfa964] flex items-center justify-center mb-6 shadow-md">
              <CheckCircle2 className="w-10 h-10" />
            </div>
            <span className="text-xs font-bold uppercase tracking-[0.2em] text-[#865e26] mb-2">
              Đặt phòng hoàn tất
            </span>
            <h2 className="font-serif text-2xl sm:text-3xl font-bold text-[#1a3e2d] mb-3">
              Cảm ơn quý khách {bookingSuccess.guestName}!
            </h2>
            <p className="text-sm text-[#5f6a62] leading-relaxed mb-6">
              Đơn đặt phòng cho <span className="font-semibold text-[#1c2920]">{bookingSuccess.roomTitle}</span> đã được ghi nhận vào hệ thống. Email xác nhận chi tiết đã được gửi tới{' '}
              <span className="font-semibold text-[#1c2920]">{bookingSuccess.email}</span>.
            </p>

            <div className="w-full bg-white border border-[#E7E2D9] p-4 rounded-sm mb-6 text-left space-y-2 text-xs">
              <div className="flex justify-between">
                <span className="text-[#6C756B]">Mã đặt phòng:</span>
                <span className="font-bold text-[#1a3e2d]">{bookingSuccess.id}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-[#6C756B]">Thời gian lưu trú:</span>
                <span className="font-medium text-[#1c2920]">{bookingSuccess.dateRangeDisplay}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-[#6C756B]">Tổng số tiền:</span>
                <span className="font-bold text-[#1a3e2d]">{formatVND(bookingSuccess.totalPrice)}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-[#6C756B]">Trạng thái:</span>
                <span className="text-emerald-700 font-semibold">{bookingSuccess.statusText}</span>
              </div>
            </div>

            <button
              onClick={closeModal}
              className="w-full py-3 bg-[#1a3e2d] text-white font-medium text-sm hover:bg-[#143124] transition-all rounded-xs shadow-sm"
            >
              Hoàn tất & Quay về trang chủ
            </button>
          </div>
        ) : (
          /* Main 2-Column Checkout Form */
          <div className="overflow-y-auto p-5 sm:p-8 lg:p-10">
            <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-10">
              
              {/* LEFT COLUMN: Customer Form & Payment Method (User Story 3) */}
              <div className="lg:col-span-7 space-y-7">
                {/* Header Tag */}
                <div>
                  <span className="block text-[11px] font-bold tracking-[0.2em] text-[#865e26] uppercase mb-1">
                    Hoàn tất đặt phòng
                  </span>
                  <h2 className="font-sans text-2xl sm:text-3xl font-bold text-[#1a3e2d] tracking-tight">
                    Thông tin lưu trú
                  </h2>
                </div>

                <form onSubmit={handleSubmitBooking} id="checkout-form" className="space-y-7">
                  {/* Step 1: Guest Info */}
                  <div>
                    <div className="flex items-center gap-2.5 mb-4">
                      <span className="w-5 h-5 rounded-xs border border-[#865e26] text-[#865e26] flex items-center justify-center text-xs font-bold">
                        1
                      </span>
                      <h3 className="text-sm sm:text-base font-bold text-[#1c2920] tracking-wide">
                        Thông tin khách
                      </h3>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                      {/* Name */}
                      <div>
                        <label className="block text-xs font-medium text-[#49544c] mb-1.5">
                          Họ và tên
                        </label>
                        <input
                          type="text"
                          required
                          value={guestName}
                          onChange={(e) => setGuestName(e.target.value)}
                          className="w-full bg-white border border-[#D5CEC2] rounded-xs px-3.5 py-2.5 text-xs sm:text-sm text-[#1c2920] focus:outline-none focus:border-[#1a3e2d] focus:ring-1 focus:ring-[#1a3e2d]"
                          placeholder="Nguyễn Minh Anh"
                        />
                      </div>

                      {/* Phone */}
                      <div>
                        <label className="block text-xs font-medium text-[#49544c] mb-1.5">
                          Số điện thoại
                        </label>
                        <input
                          type="tel"
                          required
                          value={phone}
                          onChange={(e) => setPhone(e.target.value)}
                          className="w-full bg-white border border-[#D5CEC2] rounded-xs px-3.5 py-2.5 text-xs sm:text-sm text-[#1c2920] focus:outline-none focus:border-[#1a3e2d] focus:ring-1 focus:ring-[#1a3e2d]"
                          placeholder="090 123 4567"
                        />
                      </div>

                      {/* Email */}
                      <div>
                        <label className="block text-xs font-medium text-[#49544c] mb-1.5">
                          Email
                        </label>
                        <input
                          type="email"
                          required
                          value={email}
                          onChange={(e) => setEmail(e.target.value)}
                          className="w-full bg-white border border-[#D5CEC2] rounded-xs px-3.5 py-2.5 text-xs sm:text-sm text-[#1c2920] focus:outline-none focus:border-[#1a3e2d] focus:ring-1 focus:ring-[#1a3e2d]"
                          placeholder="minhanh@gmail.com"
                        />
                      </div>

                      {/* Special Request */}
                      <div>
                        <label className="block text-xs font-medium text-[#49544c] mb-1.5">
                          Yêu cầu đặc biệt
                        </label>
                        <input
                          type="text"
                          value={specialRequests}
                          onChange={(e) => setSpecialRequests(e.target.value)}
                          className="w-full bg-white border border-[#D5CEC2] rounded-xs px-3.5 py-2.5 text-xs sm:text-sm text-[#1c2920] focus:outline-none focus:border-[#1a3e2d] focus:ring-1 focus:ring-[#1a3e2d]"
                          placeholder="Ví dụ: Tầng cao, nhận phòng sớm..."
                        />
                      </div>
                    </div>
                  </div>

                  {/* Step 2: Payment Method */}
                  <div>
                    <div className="flex items-center gap-2.5 mb-4">
                      <span className="w-5 h-5 rounded-xs border border-[#865e26] text-[#865e26] flex items-center justify-center text-xs font-bold">
                        2
                      </span>
                      <h3 className="text-sm sm:text-base font-bold text-[#1c2920] tracking-wide">
                        Phương thức thanh toán
                      </h3>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                      {/* Credit Card Option */}
                      <label
                        className={`flex items-center justify-between p-4 bg-white border rounded-xs cursor-pointer transition-all ${
                          paymentMethod === 'card'
                            ? 'border-[#1a3e2d] ring-1 ring-[#1a3e2d] shadow-xs'
                            : 'border-[#D5CEC2] hover:border-gray-400'
                        }`}
                      >
                        <div className="flex items-center gap-3">
                          <div className="p-1.5 bg-[#FAF8F5] rounded border border-[#E7E2D9]">
                            <CreditCard className="w-5 h-5 text-[#1a3e2d]" />
                          </div>
                          <div>
                            <div className="text-xs sm:text-sm font-bold text-[#1c2920]">
                              Thẻ ngân hàng
                            </div>
                            <div className="text-[11px] text-[#6C756B]">
                              Visa, Mastercard, JCB
                            </div>
                          </div>
                        </div>

                        {/* Custom Radio Circle */}
                        <div className="w-5 h-5 rounded-full border border-[#1a3e2d] flex items-center justify-center">
                          {paymentMethod === 'card' && (
                            <div className="w-2.5 h-2.5 rounded-full bg-[#1a3e2d]" />
                          )}
                        </div>
                        <input
                          type="radio"
                          name="payment"
                          value="card"
                          checked={paymentMethod === 'card'}
                          onChange={() => setPaymentMethod('card')}
                          className="sr-only"
                        />
                      </label>

                      {/* E-Wallet Option */}
                      <label
                        className={`flex items-center justify-between p-4 bg-white border rounded-xs cursor-pointer transition-all ${
                          paymentMethod === 'wallet'
                            ? 'border-[#1a3e2d] ring-1 ring-[#1a3e2d] shadow-xs'
                            : 'border-[#D5CEC2] hover:border-gray-400'
                        }`}
                      >
                        <div className="flex items-center gap-3">
                          <div className="p-1.5 bg-[#FAF8F5] rounded border border-[#E7E2D9]">
                            <Wallet className="w-5 h-5 text-[#1a3e2d]" />
                          </div>
                          <div>
                            <div className="text-xs sm:text-sm font-bold text-[#1c2920]">
                              Ví điện tử
                            </div>
                            <div className="text-[11px] text-[#6C756B]">
                              MoMo, ZaloPay
                            </div>
                          </div>
                        </div>

                        {/* Custom Radio Circle */}
                        <div className="w-5 h-5 rounded-full border border-gray-300 flex items-center justify-center">
                          {paymentMethod === 'wallet' && (
                            <div className="w-2.5 h-2.5 rounded-full bg-[#1a3e2d]" />
                          )}
                        </div>
                        <input
                          type="radio"
                          name="payment"
                          value="wallet"
                          checked={paymentMethod === 'wallet'}
                          onChange={() => setPaymentMethod('wallet')}
                          className="sr-only"
                        />
                      </label>
                    </div>
                  </div>

                  {/* Terms Checkbox */}
                  <div className="pt-2">
                    <label className="flex items-start gap-2.5 text-xs text-[#525f54] cursor-pointer select-none">
                      <input
                        type="checkbox"
                        checked={agreedTerms}
                        onChange={(e) => setAgreedTerms(e.target.checked)}
                        className="mt-0.5 rounded border-gray-300 text-[#1a3e2d] focus:ring-[#1a3e2d] h-4 w-4 cursor-pointer"
                      />
                      <span>
                        Tôi đồng ý với điều khoản đặt phòng và chính sách hủy phòng.
                      </span>
                    </label>
                  </div>
                </form>
              </div>

              {/* RIGHT COLUMN: Booking Summary (User Story 4) */}
              <div className="lg:col-span-5 bg-white border border-[#E7E2D9] rounded-xs p-5 sm:p-6 flex flex-col justify-between shadow-xs">
                <div>
                  <h3 className="font-sans text-base sm:text-lg font-bold text-[#1c2920] mb-4">
                    Thông tin đặt phòng
                  </h3>

                  {/* Room Thumbnail & Title */}
                  <div className="flex items-start gap-3.5 pb-4 border-b border-[#ECE6DE]">
                    <img
                      src={room.image}
                      alt={room.name}
                      className="w-20 h-16 sm:w-24 sm:h-20 object-cover rounded-xs shrink-0"
                    />
                    <div>
                      <span className="block text-[10px] font-bold text-[#865e26] uppercase tracking-wider mb-0.5">
                        Phòng của bạn
                      </span>
                      <h4 className="font-serif text-base font-semibold text-[#1c2920] leading-snug">
                        {room.name}
                      </h4>
                      <div className="flex items-center gap-1 text-xs text-[#6C756B] mt-1">
                        <Star className="w-3.5 h-3.5 text-amber-500 fill-amber-500" />
                        <span className="font-medium text-[#1c2920]">{room.rating}</span>
                      </div>
                    </div>
                  </div>

                  {/* Checkin / Checkout summary block */}
                  <div className="py-4 border-b border-[#ECE6DE] flex items-center justify-between text-xs">
                    <div>
                      <span className="block text-[10px] font-bold uppercase text-[#865e26] tracking-wider mb-0.5">
                        Nhận phòng
                      </span>
                      <span className="font-bold text-sm text-[#1c2920]">
                        {searchParams.checkInDisplay.split(',')[0]}
                      </span>
                      <span className="block text-[10px] text-[#6C756B]">Sau 14:00</span>
                    </div>

                    <ArrowRight className="w-4 h-4 text-[#865e26]" />

                    <div className="text-right">
                      <span className="block text-[10px] font-bold uppercase text-[#865e26] tracking-wider mb-0.5">
                        Trả phòng
                      </span>
                      <span className="font-bold text-sm text-[#1c2920]">
                        {searchParams.checkOutDisplay.split(',')[0]}
                      </span>
                      <span className="block text-[10px] text-[#6C756B]">Trước 12:00</span>
                    </div>
                  </div>

                  {/* Promo Code Input */}
                  <div className="py-4 border-b border-[#ECE6DE]">
                    <form onSubmit={handleApplyPromo} className="flex gap-2">
                      <input
                        type="text"
                        value={promoCode}
                        onChange={(e) => setPromoCode(e.target.value)}
                        placeholder="Mã khuyến mãi (vd: LUMIERE10)"
                        className="flex-1 bg-[#FAF8F5] border border-[#D5CEC2] px-3 py-2 text-xs text-[#1c2920] rounded-xs uppercase tracking-wider placeholder:normal-case focus:outline-none focus:border-[#1a3e2d]"
                      />
                      <button
                        type="submit"
                        className="bg-[#dfd7c9] hover:bg-[#d0c6b5] text-[#1c2920] text-xs font-semibold px-4 py-2 rounded-xs transition-colors whitespace-nowrap"
                      >
                        Áp dụng
                      </button>
                    </form>
                    {promoMessage && (
                      <p className="text-[11px] font-medium text-emerald-700 mt-1.5 flex items-center gap-1">
                        <CheckCircle2 className="w-3 h-3" />
                        {promoMessage}
                      </p>
                    )}
                  </div>

                  {/* Cost Breakdown */}
                  <div className="py-4 space-y-2.5 text-xs text-[#525f54]">
                    <div className="flex justify-between">
                      <span>{nights} đêm × {formatVND(room.price)}</span>
                      <span className="font-medium text-[#1c2920]">{formatVND(roomSubtotal)}</span>
                    </div>

                    <div className="flex justify-between">
                      <span>Thuế & phí dịch vụ (10%)</span>
                      <span className="font-medium text-[#1c2920]">{formatVND(taxAndFees)}</span>
                    </div>

                    {selectedServices.length > 0 && (
                      <div className="space-y-1 pt-1 border-t border-dashed border-[#ECE6DE]">
                        <span className="text-[10px] font-bold text-[#865e26] uppercase">Dịch vụ bổ sung:</span>
                        {selectedServices.map((s) => (
                          <div key={s.id} className="flex justify-between text-[11px] text-[#6C756B]">
                            <span>+ {s.name}</span>
                            <span>{formatVND(s.price)}</span>
                          </div>
                        ))}
                      </div>
                    )}

                    {discountAmount > 0 && (
                      <div className="flex justify-between text-emerald-700 font-semibold pt-1">
                        <span>Giảm giá khuyến mãi</span>
                        <span>-{formatVND(discountAmount)}</span>
                      </div>
                    )}
                  </div>
                </div>

                {/* Total & CTA */}
                <div className="pt-4 border-t border-[#ECE6DE] space-y-4">
                  <div className="flex items-baseline justify-between">
                    <div>
                      <span className="text-xs sm:text-sm font-bold text-[#1c2920] block">
                        Tổng thanh toán
                      </span>
                      <span className="text-[10px] text-[#6C756B]">
                        Đã bao gồm thuế, phí
                      </span>
                    </div>
                    <span className="font-sans text-xl sm:text-2xl font-bold text-[#1a3e2d]">
                      {formatVND(grandTotal)}
                    </span>
                  </div>

                  {/* Call-to-action button */}
                  <button
                    type="submit"
                    form="checkout-form"
                    className="w-full bg-[#1a3e2d] hover:bg-[#143124] text-white py-3.5 px-4 rounded-xs font-semibold text-xs sm:text-sm tracking-wide flex items-center justify-center gap-2 transition-all shadow-md group active:scale-[0.98]"
                  >
                    <span>Tiến hành đặt phòng</span>
                    <ArrowRight className="w-4 h-4 group-hover:translate-x-1 transition-transform" />
                  </button>

                  {/* Trust badge */}
                  <div className="flex items-center justify-center gap-1.5 text-[10px] text-[#788579] font-medium pt-1">
                    <ShieldCheck className="w-3.5 h-3.5 text-[#865e26]" />
                    <span>Thanh toán bảo mật · Xác nhận tức thì</span>
                  </div>
                </div>
              </div>

            </div>
          </div>
        )}
      </div>
    </div>
  );
};

