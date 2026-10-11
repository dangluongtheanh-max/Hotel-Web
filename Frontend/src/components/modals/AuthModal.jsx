import React, { useState } from 'react';
import { X, Lock, Mail, User, Phone, ArrowRight, ShieldCheck } from 'lucide-react';
import { useBooking } from '../../context/BookingContext';

export const AuthModal = () => {
  const { authModal, closeAuthModal, login } = useBooking();
  const [tab, setTab] = useState(authModal.tab || 'login');

  // Login form states
  const [loginEmail, setLoginEmail] = useState('minhanh@gmail.com');
  const [loginPassword, setLoginPassword] = useState('123456');

  // Register form states
  const [regName, setRegName] = useState('');
  const [regPhone, setRegPhone] = useState('');
  const [regEmail, setRegEmail] = useState('');
  const [regPassword, setRegPassword] = useState('');

  // Keep tab synced when authModal.tab changes
  React.useEffect(() => {
    if (authModal.isOpen) {
      setTab(authModal.tab || 'login');
    }
  }, [authModal]);

  if (!authModal.isOpen) return null;

  const handleLoginSubmit = (e) => {
    e.preventDefault();
    login({
      name: loginEmail.includes('admin') ? 'Admin' : 'Minh Anh',
      fullName: loginEmail.includes('admin') ? 'Quản trị viên' : 'Nguyễn Minh Anh',
      email: loginEmail,
      phone: '090 123 4567'
    });
  };

  const handleRegisterSubmit = (e) => {
    e.preventDefault();
    login({
      name: regName.split(' ').pop() || regName || 'Khách hàng',
      fullName: regName || 'Nguyễn Minh Anh',
      email: regEmail,
      phone: regPhone
    });
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      {/* Backdrop */}
      <div className="fixed inset-0 bg-black/60 backdrop-blur-xs" onClick={closeAuthModal} />

      {/* Dialog */}
      <div className="relative w-full max-w-md bg-[#FAF8F5] text-[#1c2920] shadow-2xl rounded-none sm:rounded-md border border-[#E7E2D9] p-6 sm:p-8 z-10 animate-fade-in">
        {/* Close button */}
        <button
          onClick={closeAuthModal}
          className="absolute top-4 right-4 p-2 text-gray-500 hover:text-black transition-colors"
          title="Đóng"
        >
          <X className="w-5 h-5" />
        </button>

        {/* Brand Header */}
        <div className="text-center mb-6">
          <div className="w-10 h-10 border border-[#b88d43] flex items-center justify-center mx-auto mb-2.5 bg-white shadow-xs">
            <span className="font-serif text-xl font-bold text-[#1a3e2d] tracking-widest">L</span>
          </div>
          <span className="text-[10px] tracking-[0.25em] text-[#865e26] font-semibold uppercase block">
            Lumière Hotels & Retreats
          </span>
          <h3 className="font-serif text-2xl font-bold text-[#1a3e2d] mt-1">
            {tab === 'login' ? 'Đăng Nhập' : 'Tạo Tài Khoản'}
          </h3>
        </div>

        {/* Tabs: Đăng nhập vs Đăng ký */}
        <div className="grid grid-cols-2 border-b border-[#ECE6DE] mb-6">
          <button
            type="button"
            onClick={() => setTab('login')}
            className={`py-2.5 text-xs sm:text-sm font-semibold transition-all border-b-2 -mb-[1px] ${
              tab === 'login'
                ? 'text-[#1a3e2d] border-[#865e26]'
                : 'text-[#6C756B] border-transparent hover:text-[#1a3e2d]'
            }`}
          >
            Đăng nhập
          </button>
          <button
            type="button"
            onClick={() => setTab('register')}
            className={`py-2.5 text-xs sm:text-sm font-semibold transition-all border-b-2 -mb-[1px] ${
              tab === 'register'
                ? 'text-[#1a3e2d] border-[#865e26]'
                : 'text-[#6C756B] border-transparent hover:text-[#1a3e2d]'
            }`}
          >
            Đăng ký
          </button>
        </div>

        {/* Form Content */}
        {tab === 'login' ? (
          <form onSubmit={handleLoginSubmit} className="space-y-4">
            <div>
              <label className="block text-xs font-medium text-[#49544c] mb-1">
                Email hoặc Tên đăng nhập
              </label>
              <div className="relative">
                <Mail className="w-4 h-4 text-[#865e26] absolute left-3 top-1/2 -translate-y-1/2" />
                <input
                  type="text"
                  required
                  value={loginEmail}
                  onChange={(e) => setLoginEmail(e.target.value)}
                  className="w-full bg-white border border-[#D5CEC2] rounded-xs pl-9 pr-3.5 py-2.5 text-xs sm:text-sm text-[#1c2920] focus:outline-none focus:border-[#1a3e2d]"
                  placeholder="name@example.com"
                />
              </div>
            </div>

            <div>
              <div className="flex items-center justify-between mb-1">
                <label className="text-xs font-medium text-[#49544c]">
                  Mật khẩu
                </label>
                <a
                  href="#forgot"
                  onClick={(e) => e.preventDefault()}
                  className="text-[11px] text-[#865e26] hover:underline"
                >
                  Quên mật khẩu?
                </a>
              </div>
              <div className="relative">
                <Lock className="w-4 h-4 text-[#865e26] absolute left-3 top-1/2 -translate-y-1/2" />
                <input
                  type="password"
                  required
                  value={loginPassword}
                  onChange={(e) => setLoginPassword(e.target.value)}
                  className="w-full bg-white border border-[#D5CEC2] rounded-xs pl-9 pr-3.5 py-2.5 text-xs sm:text-sm text-[#1c2920] focus:outline-none focus:border-[#1a3e2d]"
                  placeholder="••••••••"
                />
              </div>
            </div>

            <button
              type="submit"
              className="w-full mt-2 py-3 bg-[#1a3e2d] hover:bg-[#143124] text-white text-xs sm:text-sm font-semibold rounded-xs shadow-sm flex items-center justify-center gap-2 transition-all active:scale-[0.98]"
            >
              <span>Đăng nhập</span>
              <ArrowRight className="w-4 h-4" />
            </button>

            {/* Quick autofill sample */}
            <div className="pt-2 text-center">
              <span className="text-[11px] text-[#6C756B]">
                Tài khoản mẫu: <strong className="text-[#1a3e2d]">minhanh@gmail.com</strong> (mk: 123456)
              </span>
            </div>
          </form>
        ) : (
          <form onSubmit={handleRegisterSubmit} className="space-y-3.5">
            <div>
              <label className="block text-xs font-medium text-[#49544c] mb-1">
                Họ và tên
              </label>
              <div className="relative">
                <User className="w-4 h-4 text-[#865e26] absolute left-3 top-1/2 -translate-y-1/2" />
                <input
                  type="text"
                  required
                  value={regName}
                  onChange={(e) => setRegName(e.target.value)}
                  className="w-full bg-white border border-[#D5CEC2] rounded-xs pl-9 pr-3.5 py-2 text-xs sm:text-sm text-[#1c2920] focus:outline-none focus:border-[#1a3e2d]"
                  placeholder="Nguyễn Minh Anh"
                />
              </div>
            </div>

            <div>
              <label className="block text-xs font-medium text-[#49544c] mb-1">
                Số điện thoại
              </label>
              <div className="relative">
                <Phone className="w-4 h-4 text-[#865e26] absolute left-3 top-1/2 -translate-y-1/2" />
                <input
                  type="tel"
                  required
                  value={regPhone}
                  onChange={(e) => setRegPhone(e.target.value)}
                  className="w-full bg-white border border-[#D5CEC2] rounded-xs pl-9 pr-3.5 py-2 text-xs sm:text-sm text-[#1c2920] focus:outline-none focus:border-[#1a3e2d]"
                  placeholder="090 123 4567"
                />
              </div>
            </div>

            <div>
              <label className="block text-xs font-medium text-[#49544c] mb-1">
                Email
              </label>
              <div className="relative">
                <Mail className="w-4 h-4 text-[#865e26] absolute left-3 top-1/2 -translate-y-1/2" />
                <input
                  type="email"
                  required
                  value={regEmail}
                  onChange={(e) => setRegEmail(e.target.value)}
                  className="w-full bg-white border border-[#D5CEC2] rounded-xs pl-9 pr-3.5 py-2 text-xs sm:text-sm text-[#1c2920] focus:outline-none focus:border-[#1a3e2d]"
                  placeholder="minhanh@gmail.com"
                />
              </div>
            </div>

            <div>
              <label className="block text-xs font-medium text-[#49544c] mb-1">
                Mật khẩu
              </label>
              <div className="relative">
                <Lock className="w-4 h-4 text-[#865e26] absolute left-3 top-1/2 -translate-y-1/2" />
                <input
                  type="password"
                  required
                  value={regPassword}
                  onChange={(e) => setRegPassword(e.target.value)}
                  className="w-full bg-white border border-[#D5CEC2] rounded-xs pl-9 pr-3.5 py-2 text-xs sm:text-sm text-[#1c2920] focus:outline-none focus:border-[#1a3e2d]"
                  placeholder="Tối thiểu 6 ký tự"
                />
              </div>
            </div>

            <button
              type="submit"
              className="w-full mt-3 py-3 bg-[#1a3e2d] hover:bg-[#143124] text-white text-xs sm:text-sm font-semibold rounded-xs shadow-sm flex items-center justify-center gap-2 transition-all active:scale-[0.98]"
            >
              <span>Đăng ký tài khoản</span>
              <ArrowRight className="w-4 h-4" />
            </button>
          </form>
        )}

        <div className="mt-6 pt-4 border-t border-[#ECE6DE] flex items-center justify-center gap-1.5 text-[10px] text-[#788579]">
          <ShieldCheck className="w-3.5 h-3.5 text-[#865e26]" />
          <span>Bảo mật thông tin theo tiêu chuẩn quốc tế</span>
        </div>
      </div>
    </div>
  );
};

