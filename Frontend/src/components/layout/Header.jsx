import React, { useState } from 'react';
import { NavLink, Link, useNavigate, useLocation } from 'react-router-dom';
import { User, ChevronDown, Calendar, Check, Globe, LogOut } from 'lucide-react';
import { useBooking } from '../../context/BookingContext';

export const Header = () => {
  const {
    openBookingHistory,
    currentUser,
    language,
    setLanguage,
    isLoggedIn,
    openLogin,
    openRegister,
    logout
  } = useBooking();
  const [langMenuOpen, setLangMenuOpen] = useState(false);
  const [userMenuOpen, setUserMenuOpen] = useState(false);
  const location = useLocation();
  const navigate = useNavigate();

  const handleNavClick = (sectionId, path = '/') => {
    if (location.pathname !== '/') {
      navigate(path);
      setTimeout(() => {
        const el = document.getElementById(sectionId);
        if (el) el.scrollIntoView({ behavior: 'smooth' });
      }, 100);
    } else {
      const el = document.getElementById(sectionId);
      if (el) {
        el.scrollIntoView({ behavior: 'smooth' });
      } else if (path !== '/') {
        navigate(path);
      }
    }
  };

  return (
    <header className="sticky top-0 z-40 bg-white/95 backdrop-blur-md border-b border-[#ECE6DE] transition-all">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
        {/* Brand Logo */}
        <Link to="/" className="flex items-center gap-3.5 group">
          <div className="w-10 h-10 border border-[#b88d43] flex items-center justify-center bg-white group-hover:bg-[#fbf7f0] transition-colors shadow-sm">
            <span className="font-serif text-xl font-bold text-[#1a3e2d] tracking-widest">L</span>
          </div>
          <div className="flex flex-col">
            <span className="font-serif text-lg tracking-[0.2em] font-semibold text-[#1a3e2d] leading-none">
              LUMIÈRE
            </span>
            <span className="text-[9px] tracking-[0.3em] text-[#865e26] font-medium mt-1">
              HOTELS & RETREATS
            </span>
          </div>
        </Link>

        {/* Navigation Bar */}
        <nav className="hidden md:flex items-center gap-8 lg:gap-10">
          <NavLink
            to="/"
            end
            className={({ isActive }) =>
              `relative text-sm tracking-wide font-medium py-2 transition-colors ${
                isActive
                  ? 'text-[#1a3e2d] font-semibold after:absolute after:bottom-0 after:left-0 after:right-0 after:h-0.5 after:bg-[#b88d43]'
                  : 'text-[#5a645d] hover:text-[#1a3e2d]'
              }`
            }
          >
            Khám phá
          </NavLink>

          <button
            onClick={() => handleNavClick('rooms-section', '/rooms')}
            className="text-sm tracking-wide font-medium py-2 text-[#5a645d] hover:text-[#1a3e2d] transition-colors"
          >
            Phòng nghỉ
          </button>

          <button
            onClick={() => handleNavClick('services-section', '/experiences')}
            className="text-sm tracking-wide font-medium py-2 text-[#5a645d] hover:text-[#1a3e2d] transition-colors"
          >
            Trải nghiệm
          </button>

          <button
            onClick={() => openBookingHistory('upcoming')}
            className="text-sm tracking-wide font-medium py-2 text-[#5a645d] hover:text-[#1a3e2d] transition-colors flex items-center gap-1.5"
          >
            Chuyến đi của tôi
          </button>
        </nav>

        {/* Right Section: Language & User */}
        <div className="flex items-center gap-4 sm:gap-6">
          {/* Language Selector */}
          <div className="relative">
            <button
              onClick={() => setLangMenuOpen(!langMenuOpen)}
              className="flex items-center gap-1.5 text-xs font-semibold tracking-wider text-[#49544c] hover:text-[#1a3e2d] px-2.5 py-1.5 rounded transition-colors"
            >
              <span>{language}</span>
              <ChevronDown className="w-3.5 h-3.5 text-[#738075]" />
            </button>

            {langMenuOpen && (
              <div className="absolute right-0 mt-1.5 w-32 bg-white rounded-md shadow-lg border border-[#eae3d8] py-1 text-xs z-50 animate-fade-in">
                <button
                  onClick={() => {
                    setLanguage('VI');
                    setLangMenuOpen(false);
                  }}
                  className={`w-full flex items-center justify-between px-3 py-2 text-left hover:bg-[#FAF8F5] ${
                    language === 'VI' ? 'font-semibold text-[#1a3e2d]' : 'text-[#6C756B]'
                  }`}
                >
                  <span>Tiếng Việt</span>
                  {language === 'VI' && <Check className="w-3.5 h-3.5 text-[#b88d43]" />}
                </button>
                <button
                  onClick={() => {
                    setLanguage('EN');
                    setLangMenuOpen(false);
                  }}
                  className={`w-full flex items-center justify-between px-3 py-2 text-left hover:bg-[#FAF8F5] ${
                    language === 'EN' ? 'font-semibold text-[#1a3e2d]' : 'text-[#6C756B]'
                  }`}
                >
                  <span>English</span>
                  {language === 'EN' && <Check className="w-3.5 h-3.5 text-[#b88d43]" />}
                </button>
              </div>
            )}
          </div>

          {/* Authentication: Hai nút riêng biệt Đăng ký và Đăng nhập */}
          {!isLoggedIn ? (
            <div className="flex items-center gap-2 sm:gap-3">
              <button
                onClick={openLogin}
                className="text-xs sm:text-sm font-semibold text-[#1a3e2d] hover:text-[#865e26] px-2.5 sm:px-3 py-2 transition-colors whitespace-nowrap"
              >
                Đăng nhập
              </button>

              <button
                onClick={openRegister}
                className="text-xs sm:text-sm font-semibold text-white bg-[#1a3e2d] hover:bg-[#143124] px-3.5 sm:px-4 py-2 rounded-none sm:rounded-xs shadow-xs transition-all active:scale-95 whitespace-nowrap"
              >
                Đăng ký
              </button>
            </div>
          ) : (
            /* Khi đã đăng nhập */
            <div className="relative">
              <button
                onClick={() => setUserMenuOpen(!userMenuOpen)}
                className="flex items-center gap-2 py-1.5 px-3 rounded-full hover:bg-[#f6f1e8] transition-colors group cursor-pointer border border-transparent hover:border-[#dfc28d]"
              >
                <div className="w-8 h-8 rounded-full border border-[#865e26]/40 flex items-center justify-center bg-[#FAF8F5] text-[#1a3e2d] group-hover:bg-[#1a3e2d] group-hover:text-white transition-all shadow-xs">
                  <User className="w-4 h-4" />
                </div>
                <span className="text-sm font-medium text-[#1a3e2d] hidden sm:inline">
                  {currentUser.name}
                </span>
                <ChevronDown className="w-3.5 h-3.5 text-[#738075]" />
              </button>

              {userMenuOpen && (
                <div className="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-xl border border-[#ECE6DE] py-1 text-xs z-50 animate-fade-in">
                  <div className="px-4 py-2 border-b border-gray-100">
                    <p className="font-bold text-[#1c2920] truncate">{currentUser.fullName}</p>
                    <p className="text-[11px] text-[#6C756B] truncate">{currentUser.email}</p>
                  </div>
                  <button
                    onClick={() => {
                      setUserMenuOpen(false);
                      openBookingHistory('upcoming');
                    }}
                    className="w-full px-4 py-2.5 text-left text-[#1c2920] hover:bg-[#FAF8F5] flex items-center gap-2"
                  >
                    <span>Chuyến đi của tôi</span>
                  </button>
                  <button
                    onClick={() => {
                      setUserMenuOpen(false);
                      logout();
                    }}
                    className="w-full px-4 py-2.5 text-left text-rose-700 hover:bg-rose-50 border-t border-gray-100 flex items-center gap-2"
                  >
                    <LogOut className="w-3.5 h-3.5" />
                    <span>Đăng xuất</span>
                  </button>
                </div>
              )}
            </div>
          )}
        </div>
      </div>
    </header>
  );
};

