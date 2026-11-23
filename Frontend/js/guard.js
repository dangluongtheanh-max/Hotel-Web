/* ==========================================================================
   SKY HOTEL - Security & Guard Module (js/guard.js)
   --------------------------------------------------------------------------
   1. Phân quyền tập trung (Client-side Guard)
      - Bảng phân quyền cấu hình các trang yêu cầu vai trò.
      - Chú ý: Đây chỉ là lớp UX bảo vệ giao diện, quyền thật sự do Backend quyết định.
   2. Tiện ích Escape chống XSS dùng chung (esc / safeText)
   3. Kiểm tra an toàn nguồn postMessage
   ========================================================================== */
(function () {
  "use strict";

  // Hàm escape chuỗi HTML chống XSS tập trung cho toàn bộ ứng dụng
  function esc(s) {
    if (s == null) return "";
    return String(s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  // Bảng cấu hình vai trò cho từng trang (chỉ liệt kê trang yêu cầu đăng nhập/phân quyền)
  var ROLE_CONFIG = {
    // Khách hàng & Lễ tân (yêu cầu đăng nhập; Admin không đặt phòng / xem lịch sử cá nhân)
    book: ["customer", "letan"],
    pay: ["customer", "letan"],
    hist: ["customer", "letan"],
    fav: ["customer", "letan"],

    // Nhân viên Lễ tân & Quản trị
    rec: ["letan", "admin"],

    // Quản trị viên (Admin)
    mgr: ["admin"],
    rooms_mgr: ["admin"],
    room_types: ["admin"],
    customers: ["admin"],
    invoices: ["admin"],
    services_mgr: ["admin"],
    promos: ["admin"],
    staff: ["admin"],
    hotel: ["admin"]
  };

  // Đọc thông tin người dùng an toàn từ session / localStorage (chỉ dùng hotel_user)
  function getCurrentUser() {
    try {
      var hu = JSON.parse(localStorage.getItem("hotel_user") || "null");
      if (hu) {
        var role = (window.HotelAPI && window.HotelAPI.roleMap && window.HotelAPI.roleMap[hu.VaiTro]) ||
          (hu.VaiTro === "Admin" ? "admin" : (hu.VaiTro === "LeTan" ? "letan" : "customer"));
        return {
          name: hu.HoTen || hu.TenDangNhap,
          role: role,
          maTK: hu.MaTK,
          raw: hu
        };
      }
    } catch (e) {}
    return null;
  }

  // Kiểm tra quyền truy cập vào một trang
  function checkAccess(pageKey) {
    if (!pageKey) return { allowed: true };
    var cleanKey = String(pageKey).split(":")[0].replace(/\.html$/i, "");
    var allowedRoles = ROLE_CONFIG[cleanKey];
    if (!allowedRoles) return { allowed: true }; // Trang công khai nếu không cấu hình

    var u = getCurrentUser();
    var userRole = u ? (u.role || "customer") : "";

    if (!userRole) {
      return { allowed: false, reason: "NOT_LOGGED_IN", message: "Vui lòng đăng nhập để tiếp tục." };
    }

    if (allowedRoles.indexOf(userRole) > -1) {
      return { allowed: true };
    }

    return {
      allowed: false,
      reason: "FORBIDDEN",
      message: "Tài khoản của bạn không có quyền truy cập trang này. Hãy đăng nhập bằng tài khoản khác."
    };
  }

  // Tiện ích gửi postMessage an toàn (chỉ gửi tới cùng domain)
  function safePostMessage(targetWin, data) {
    if (!targetWin || typeof targetWin.postMessage !== "function") return;
    try {
      var origin = (window.location.protocol === "file:") ? "*" : window.location.origin;
      targetWin.postMessage(data, origin);
    } catch (e) {}
  }

  // Kiểm tra event message an toàn
  function isSafeOrigin(event) {
    if (!event) return false;
    if (window.location.protocol === "file:") return true; // hỗ trợ local development
    return event.origin === window.location.origin;
  }

  // Dọn dẹp mật khẩu hoặc khóa phiên cũ sky_user khỏi localStorage
  function sanitizeLocalStorage() {
    try {
      localStorage.removeItem("sky_user");
      var huRaw = localStorage.getItem("hotel_user");
      if (huRaw && (huRaw.indexOf("MatKhau") > -1 || huRaw.indexOf("matKhau") > -1 || huRaw.indexOf('"pw"') > -1)) {
        var hu = JSON.parse(huRaw);
        if (hu) {
          delete hu.MatKhau;
          delete hu.matKhau;
          delete hu.pw;
          localStorage.setItem("hotel_user", JSON.stringify(hu));
        }
      }
    } catch (e) {}
  }

  sanitizeLocalStorage();

  window.SkyGuard = {
    esc: esc,
    ROLE_CONFIG: ROLE_CONFIG,
    getCurrentUser: getCurrentUser,
    checkAccess: checkAccess,
    safePostMessage: safePostMessage,
    isSafeOrigin: isSafeOrigin,
    sanitizeLocalStorage: sanitizeLocalStorage
  };
})();

