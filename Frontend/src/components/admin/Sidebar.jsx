import React from 'react';
import { NavLink, Link, useNavigate, useLocation } from 'react-router-dom';
import {
  LayoutDashboard,
  Grid,
  CheckSquare,
  LogOut,
  CreditCard,
  User,
  Home,
  ChevronRight,
  HelpCircle
} from 'lucide-react';
import { useReception } from '../../context/ReceptionContext';

export const Sidebar = () => {
  const { staff, openCheckIn, openCheckOut } = useReception();
  const location = useLocation();
  const navigate = useNavigate();

  return (
    <aside className="w-64 bg-[#10271c] text-white flex flex-col justify-between shrink-0 h-screen sticky top-0 border-r border-[#1a3e2d] z-30 select-none">
      {/* Brand & Branch Selector */}
      <div className="p-5">
        <Link to="/admin" className="flex items-center gap-3 group mb-6">
          <div className="w-10 h-10 border border-[#b88d43] flex items-center justify-center bg-[#173929] group-hover:bg-[#1f4b36] transition-colors shadow-sm">
            <span className="font-serif text-xl font-bold text-white tracking-widest">L</span>
          </div>
          <div>
            <div className="font-serif text-base font-bold tracking-widest text-white leading-tight">
              LUMIÈRE
            </div>
            <div className="text-[9px] tracking-[0.25em] text-[#dfc28d] font-semibold uppercase">
              Front Office Lễ Tân
            </div>
          </div>
        </Link>

        {/* Branch / Property Badge */}
        <div className="bg-[#173929] border border-[#23523b] rounded-md p-3 flex items-center justify-between shadow-xs">
          <div className="flex items-center gap-2.5">
            <div className="w-8 h-8 rounded bg-[#1f4b36] text-[#dfc28d] text-xs font-bold flex items-center justify-center border border-[#b88d43]/30">
              LR
            </div>
            <div>
              <div className="text-xs font-bold text-white">Lumière Riverside</div>
              <div className="text-[10px] text-[#9db2a4]">Nha Trang, Việt Nam</div>
            </div>
          </div>
        </div>
      </div>

      {/* Navigation Menu (User Story 2) */}
      <div className="px-3 flex-1 py-2 overflow-y-auto space-y-6">
        <div>
          <span className="px-3 text-[10px] font-bold tracking-[0.2em] text-[#7d9b88] uppercase block mb-2">
            Quản lý
          </span>
          <nav className="space-y-1">
            {/* 1. Tổng quan */}
            <NavLink
              to="/admin"
              end
              className={({ isActive }) =>
                `flex items-center gap-3 px-3 py-2.5 rounded-md text-xs sm:text-sm font-medium transition-all ${
                  isActive
                    ? 'bg-[#235c40] text-white shadow-xs font-semibold'
                    : 'text-[#b2c4b8] hover:text-white hover:bg-[#173929]'
                }`
              }
            >
              <LayoutDashboard className="w-4 h-4 text-[#dfc28d]" />
              <span>Tổng quan</span>
            </NavLink>

            {/* 2. Quản lý phòng */}
            <NavLink
              to="/admin/rooms"
              className={({ isActive }) =>
                `flex items-center gap-3 px-3 py-2.5 rounded-md text-xs sm:text-sm font-medium transition-all ${
                  isActive
                    ? 'bg-[#235c40] text-white shadow-xs font-semibold'
                    : 'text-[#b2c4b8] hover:text-white hover:bg-[#173929]'
                }`
              }
            >
              <Grid className="w-4 h-4 text-[#dfc28d]" />
              <span>Quản lý phòng</span>
            </NavLink>
          </nav>
        </div>

        <div>
          <span className="px-3 text-[10px] font-bold tracking-[0.2em] text-[#7d9b88] uppercase block mb-2">
            Nghiệp vụ quầy
          </span>
          <nav className="space-y-1">
            {/* 3. Check-in (Quick action modal) */}
            <button
              onClick={() => openCheckIn()}
              className="w-full flex items-center justify-between px-3 py-2.5 rounded-md text-xs sm:text-sm font-medium text-[#b2c4b8] hover:text-white hover:bg-[#173929] transition-all group"
            >
              <div className="flex items-center gap-3">
                <CheckSquare className="w-4 h-4 text-[#dfc28d]" />
                <span>Check-in</span>
              </div>
              <span className="text-[10px] bg-[#1f4b36] text-[#dfc28d] px-1.5 py-0.5 rounded border border-[#2e684a]">
                F1
              </span>
            </button>

            {/* 4. Check-out (Quick action modal) */}
            <button
              onClick={() => openCheckOut()}
              className="w-full flex items-center justify-between px-3 py-2.5 rounded-md text-xs sm:text-sm font-medium text-[#b2c4b8] hover:text-white hover:bg-[#173929] transition-all group"
            >
              <div className="flex items-center gap-3">
                <LogOut className="w-4 h-4 text-[#dfc28d]" />
                <span>Check-out</span>
              </div>
              <span className="text-[10px] bg-[#1f4b36] text-[#dfc28d] px-1.5 py-0.5 rounded border border-[#2e684a]">
                F2
              </span>
            </button>

            {/* 5. Quản lý thanh toán */}
            <NavLink
              to="/admin/payments"
              className={({ isActive }) =>
                `flex items-center gap-3 px-3 py-2.5 rounded-md text-xs sm:text-sm font-medium transition-all ${
                  isActive
                    ? 'bg-[#235c40] text-white shadow-xs font-semibold'
                    : 'text-[#b2c4b8] hover:text-white hover:bg-[#173929]'
                }`
              }
            >
              <CreditCard className="w-4 h-4 text-[#dfc28d]" />
              <span>Quản lý thanh toán</span>
            </NavLink>
          </nav>
        </div>

        {/* Client Website Shortcut */}
        <div className="pt-2 border-t border-[#1a3e2d]">
          <Link
            to="/"
            className="flex items-center gap-3 px-3 py-2 rounded-md text-xs text-[#9db2a4] hover:text-[#dfc28d] hover:bg-[#173929] transition-colors"
          >
            <Home className="w-4 h-4" />
            <span>Về website khách hàng</span>
          </Link>
        </div>
      </div>

      {/* Staff Footer Profile (Linh Trần) */}
      <div className="p-4 bg-[#0d2117] border-t border-[#1a3e2d] flex items-center justify-between">
        <div className="flex items-center gap-3">
          <div className="w-9 h-9 rounded-full bg-[#1f4b36] border border-[#b88d43] flex items-center justify-center font-bold text-xs text-[#dfc28d] shadow-2xs">
            {staff.avatar}
          </div>
          <div>
            <div className="text-xs font-bold text-white">{staff.name}</div>
            <div className="text-[10px] text-[#8ea394]">{staff.shift}</div>
          </div>
        </div>
      </div>
    </aside>
  );
};

