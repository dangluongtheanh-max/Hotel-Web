/* ==========================================================================
   HotelAPI - Lớp kết nối Backend PHP (Hotel Web) cho giao diện SkyHotel
   --------------------------------------------------------------------------
   - Tự nhận diện địa chỉ Backend (file://, php -S localhost:8000, XAMPP).
     Muốn ép địa chỉ khác: đặt  window.HOTEL_API_BASE = "http://..."  TRƯỚC khi nạp file này.
   - Backend trả JSON dạng {success, data} hoặc {success:false, message}.
     Một số API bị bọc 2 lần ({success, data:{success, message, data}}) -> file này tự bóc.
   - Phiên đăng nhập dùng chung khóa localStorage "hotel_user" với các trang cũ
     (Frontend/legacy) nên đăng nhập một nơi là dùng được ở cả hai.
   ========================================================================== */
(function () {
  "use strict";

  var USER_KEY = "hotel_user";

  function detectBase() {
    if (window.HOTEL_API_BASE) return String(window.HOTEL_API_BASE).replace(/\/$/, "");
    if (window.location.protocol === "file:") return "http://localhost:8000/Backend/public/index.php";
    var p = window.location.pathname;
    var i = p.indexOf("/Frontend/");
    // Mở qua XAMPP (/HotelWeb/Frontend/...): router Backend không hiểu tiền tố thư mục -> gọi server PHP cổng 8000
    if (i > 0) return window.location.protocol + "//" + window.location.hostname + ":8000/Backend/public/index.php";
    if (i === 0) return "/Backend/public/index.php";
    return "/Backend/public/index.php";
  }
  var BASE = detectBase();

  var OFFLINE_MSG =
    "Không kết nối được máy chủ. Hãy chạy Backend (php -S localhost:8000 ở thư mục gốc dự án) " +
    "và mở website qua http://localhost:8000/Frontend/";

  function HotelError(message, status, payload) {
    var e = new Error(message);
    e.name = "HotelError";
    e.status = status || 0;
    e.payload = payload || null;
    return e;
  }

  /* Đọc JSON, chịu được trường hợp PHP in cảnh báo/HTML trước JSON (APP_DEBUG=true) */
  function parseBody(text) {
    try { return JSON.parse(text); } catch (e) {}
    var i = text.indexOf("{");
    if (i > -1) { try { return JSON.parse(text.slice(i)); } catch (e2) {} }
    return null;
  }

  /* Bóc lớp {success:true, data:{success:true, message, data}} */
  function unwrap(j) {
    var message = (j && (j.message || j.error)) || "";
    var data = j ? j.data : null;
    var guard = 0;
    while (data && typeof data === "object" && !Array.isArray(data) &&
           "success" in data && "data" in data && guard++ < 4) {
      if (data.message) message = data.message;
      if (data.success === false) return { ok: false, data: null, message: message };
      data = data.data;
    }
    return { ok: !!(j && j.success !== false && !j.error), data: data, message: message };
  }

  function sanitizeUser(u) {
    if (!u || typeof u !== "object") return u;
    var copy = {};
    Object.keys(u).forEach(function (k) {
      if (k !== "MatKhau" && k !== "matKhau" && k !== "pw") copy[k] = u[k];
    });
    return copy;
  }

  function request(method, path, payload) {
    var url = BASE + path;
    var opt = { method: method, headers: { Accept: "application/json" } };
    try {
      var sessUser = JSON.parse(localStorage.getItem(USER_KEY) || "null");
      var sessMaTK = sessUser && (sessUser.MaTK || sessUser.id);
      if (sessMaTK) opt.headers["Authorization"] = "Bearer " + sessMaTK;
    } catch (e) {}
    if (method === "GET") {
      if (payload) {
        var q = Object.keys(payload)
          .filter(function (k) { return payload[k] !== undefined && payload[k] !== null && payload[k] !== ""; })
          .map(function (k) { return encodeURIComponent(k) + "=" + encodeURIComponent(payload[k]); })
          .join("&");
        if (q) url += (url.indexOf("?") > -1 ? "&" : "?") + q;
      }
    } else {
      opt.headers["Content-Type"] = "application/json";
      opt.body = JSON.stringify(payload || {});
    }
    return fetch(url, opt).then(
      function (res) {
        return res.text().then(function (text) {
          var j = parseBody(text);
          if (!j) throw HotelError("Máy chủ trả về dữ liệu không hợp lệ (HTTP " + res.status + ").", res.status);
          if (!res.ok || j.success === false || j.error) {
            throw HotelError(j.message || j.error || "Yêu cầu thất bại (HTTP " + res.status + ").", res.status, j);
          }
          var u = unwrap(j);
          return { data: u.data, message: u.message, raw: j };
        });
      },
      function () { throw HotelError(OFFLINE_MSG, 0); }
    );
  }

  /* ---------------- Phiên đăng nhập (dùng chung toàn hệ thống) ---------------- */
  try { localStorage.removeItem("sky_user"); } catch (e) {}
  var session = {
    get: function () {
      try {
        var u = JSON.parse(localStorage.getItem(USER_KEY) || "null");
        if (u && (u.MatKhau || u.matKhau || u.pw)) {
          u = sanitizeUser(u);
          localStorage.setItem(USER_KEY, JSON.stringify(u));
        }
        return u;
      } catch (e) { return null; }
    },
    set: function (u) {
      try { localStorage.setItem(USER_KEY, JSON.stringify(sanitizeUser(u))); } catch (e) {}
    },
    clear: function () {
      try {
        localStorage.removeItem(USER_KEY);
        localStorage.removeItem("sky_user");
      } catch (e) {}
    }
  };

  /* ---------------- API ---------------- */
  var HotelAPI = {
    base: BASE,
    unwrap: unwrap,
    get: function (path, params) { return request("GET", path, params); },
    post: function (path, body) { return request("POST", path, body); },
    session: session,

    /* Backend đăng nhập bằng TÊN ĐĂNG NHẬP (không nhận email) */
    login: function (tenDangNhap, matKhau) {
      return request("POST", "/taikhoan/dangnhap", { tenDangNhap: tenDangNhap, matKhau: matKhau });
    },

    register: function (p) {
      return request("POST", "/khachhang/dangky", {
        hoTen: p.hoTen,
        sdt: p.sdt,
        email: p.email,
        tenDangNhap: p.tenDangNhap,
        matKhau: p.matKhau,
        ngaySinh: p.ngaySinh || null,
        quocTich: p.quocTich || "Việt Nam",
        loaiGiayTo: p.loaiGiayTo || "CCCD",
        soGiayTo: p.soGiayTo || null
      });
    },

    /* Quên mật khẩu: 3 bước (gửi OTP -> xác minh OTP -> đặt lại mật khẩu) */
    forgotSendOtp: function (email) {
      return request("POST", "/taikhoan/quenmatkhau", { action: "gui_otp", email: email });
    },
    forgotVerifyOtp: function (email, otp) {
      return request("POST", "/taikhoan/quenmatkhau", { action: "xacminh_otp", email: email, otp: otp }).then(function (r) {
        var d = r.data || {};
        var token = d.reset_token || (d.data && d.data.reset_token) || (r.raw && r.raw.reset_token);
        if (!token) throw HotelError(r.message || "Xác minh OTP thất bại.", 400);
        return { resetToken: token, message: r.message };
      });
    },
    forgotReset: function (email, resetToken, matKhauMoi, xacNhanMatKhau) {
      return request("POST", "/taikhoan/quenmatkhau", {
        action: "datlai_matkhau",
        email: email,
        reset_token: resetToken,
        matKhauMoi: matKhauMoi,
        xacNhanMatKhau: xacNhanMatKhau
      });
    },

    /* ==========================================================================
       Các hàm nghiệp vụ đặt phòng & thanh toán trực tuyến kết nối Backend thật
       ========================================================================== */

    /* 1. Tìm kiếm phòng trống theo ngày & số lượng khách */
    searchRooms: function (params) {
      return request("POST", "/datphong/timkiem", {
        ngayNhan: params.checkIn || params.ngayNhan,
        ngayTra: params.checkOut || params.ngayTra,
        soNguoiLon: params.adults != null ? Number(params.adults) : (Number(params.guests) || 2),
        soTreEm: params.children != null ? Number(params.children) : 0,
        soPhong: Number(params.rooms) || 1
      });
    },

    /* 2. Lấy danh sách dịch vụ đi kèm khi đặt phòng (đồ ăn, giặt ủi, thuê xe máy) */
    getServices: function () {
      return request("GET", "/datphong/dichvu");
    },

    /* 3. Lấy danh sách mã khuyến mãi phù hợp với điều kiện lưu trú */
    getPromos: function (params) {
      params = params || {};
      return request("GET", "/khuyenmai/danhsach", {
        soDem: params.nights || params.soDem || 1,
        soNguoi: params.guests || params.soNguoi || 2,
        ngay: params.checkIn || params.ngay || "",
        maTK: params.maTK || "",
        sdt: params.phone || params.sdt || ""
      });
    },

    /* 4. Tính toán chi phí đơn đặt phòng, thuế VAT & kiểm tra mã ưu đãi (Server-side) */
    calculateCost: function (payload) {
      return request("POST", "/datphong/tinhchiphi", payload);
    },

    /* 5. Tạo phiếu nhận phòng & đơn thanh toán mới */
    createBooking: function (payload) {
      return request("POST", "/datphong/datphong", payload);
    },

    /* 6. Chi tiết phiếu nhận phòng */
    getBookingDetail: function (maPhieu, maTK) {
      var body = { maPhieu: maPhieu };
      if (maTK) body.maTK = maTK;
      return request("POST", "/datphong/xemctphieu", body).catch(function () {
        return request("POST", "/letan/xemctphieu", { maPhieu: maPhieu });
      });
    },

    /* 7. Xác nhận thanh toán chuyển khoản VietQR MBBank mô phỏng */
    confirmPayment: function (maHD, soTien, noiDung) {
      return request("POST", "/thanhtoan/mophong", {
        MaHD: maHD,
        SoTien: Math.round(Number(soTien || 0)),
        NoiDung: noiDung || ("THANHTOAN " + maHD)
      });
    },

    /* 8. Lấy chính sách hủy phòng chuẩn từ Backend */
    getCancellationPolicy: function () {
      return request("GET", "/datphong/chinhsachhuy");
    },

    /* 9. Tính trước tiền hoàn hủy phòng của khách hàng đang đăng nhập */
    calcCustomerRefund: function (maPhieu, maPhong, maTK) {
      var u = session.get();
      var payload = { maPhieu: maPhieu };
      if (maPhong) payload.maPhong = maPhong;
      if (maTK || (u && (u.MaTK || u.id))) payload.maTK = maTK || u.MaTK || u.id;
      return request("POST", "/datphong/tinhtienhoan", payload);
    },

    /* 10. Khách hàng tự hủy đặt phòng & gửi yêu cầu hoàn tiền */
    cancelCustomerBooking: function (payload) {
      var u = session.get();
      var body = Object.assign({}, payload || {});
      if (!body.maTK && u && (u.MaTK || u.id)) body.maTK = u.MaTK || u.id;
      return request("POST", "/datphong/huy", body);
    }
  };

  /* ==========================================================================
     NGUỒN CHUẨN DUY NHẤT CHO CHÍNH SÁCH HỦY PHÒNG & HOÀN TIỀN (FRONTEND)
     Khớp 100% với Backend/shared/ChinhSachHoanHuy.php
     ========================================================================== */
  var CANCELLATION_POLICY_RULES = [
    {
      maMoc: "BEFORE_7_DAYS",
      thoiDiem: "Trước ngày nhận phòng từ 7 ngày trở lên",
      minDays: 7,
      maxDays: null,
      tyLeHoan: 100,
      tyLePhiHuy: 0,
      moTa: "Hoàn 100%, Phí hủy 0%"
    },
    {
      maMoc: "FROM_3_TO_7_DAYS",
      thoiDiem: "Trước ngày nhận phòng từ 3 đến dưới 7 ngày",
      minDays: 3,
      maxDays: 7,
      tyLeHoan: 50,
      tyLePhiHuy: 50,
      moTa: "Hoàn 50%, Phí hủy 50%"
    },
    {
      maMoc: "UNDER_3_DAYS",
      thoiDiem: "Trước ngày nhận phòng dưới 3 ngày",
      minDays: 0,
      maxDays: 3,
      tyLeHoan: 0,
      tyLePhiHuy: 100,
      moTa: "Hoàn 0%, Phí hủy 100%"
    },
    {
      maMoc: "NO_SHOW",
      thoiDiem: "Không đến nhận phòng, không báo hủy",
      minDays: null,
      maxDays: 0,
      tyLeHoan: 0,
      tyLePhiHuy: 100,
      moTa: "Hoàn 0%, Phí hủy 100%"
    }
  ];

  function escHtml(s) {
    return String(s == null ? "" : s).replace(/[&<>"']/g, function (m) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", "\"": "&quot;", "'": "&#39;" }[m];
    });
  }

  HotelAPI.policy = {
    rules: CANCELLATION_POLICY_RULES,
    getRules: function () {
      return CANCELLATION_POLICY_RULES.slice();
    },
    getSummaryText: function () {
      return CANCELLATION_POLICY_RULES.map(function (r) {
        return r.thoiDiem + ": " + r.moTa + ".";
      }).join(" ");
    },
    evaluateByDate: function (checkInValue) {
      if (!checkInValue) return { diffDays: null, rule: CANCELLATION_POLICY_RULES[2] };
      var strVal = String(checkInValue).trim();
      if (/^\d{4}-\d{2}-\d{2}$/.test(strVal)) strVal += "T14:00:00";
      var d = new Date(strVal.replace(" ", "T"));
      if (isNaN(d.getTime())) return { diffDays: null, rule: CANCELLATION_POLICY_RULES[2] };
      var now = new Date();
      var rawDays = (d.getTime() - now.getTime()) / 86400000;
      var diffDays = Math.round(rawDays * 100) / 100;
      var rule = rawDays >= 7
        ? CANCELLATION_POLICY_RULES[0]
        : (rawDays >= 3 ? CANCELLATION_POLICY_RULES[1] : CANCELLATION_POLICY_RULES[2]);
      return { diffDays: diffDays, rule: rule };
    },
    renderBulletListHtml: function (options) {
      options = options || {};
      var showNote = options.showNoShowNote !== false;
      var html = CANCELLATION_POLICY_RULES.map(function (r) {
        return "<div>• <strong>" + escHtml(r.thoiDiem) + ":</strong> " + escHtml(r.moTa) + "</div>";
      }).join("");
      if (showNote) {
        html += "<div style=\"margin-top:4px;opacity:0.85;\">* Lưu ý: Đơn đã thanh toán 100% được giữ phòng trọn thời gian đặt; chỉ tự động hủy No-show sau khi quá giờ trả phòng dự kiến nếu khách không đến nhận.</div>";
      }
      return html;
    },
    renderTableHtml: function () {
      return (
        "<table class=\"policy-ref-table\">" +
        "<thead><tr><th>Thời điểm khách hủy</th><th>Hoàn tiền</th><th>Phí hủy</th></tr></thead>" +
        "<tbody>" +
        CANCELLATION_POLICY_RULES.map(function (r) {
          return "<tr><td>" + escHtml(r.thoiDiem) + "</td><td><strong>" + r.tyLeHoan + "%</strong></td><td>" + r.tyLePhiHuy + "%</td></tr>";
        }).join("") +
        "</tbody></table>"
      );
    }
  };

  window.HOTEL_CANCELLATION_POLICY = HotelAPI.policy;

  /* Vai trò Backend -> vai trò giao diện Sky */
  HotelAPI.roleMap = { Admin: "admin", LeTan: "letan", KhachHang: "customer" };

  /** Kiểm tra phiên hiện tại có phải Admin hay không (từ session hoặc parent shell). */
  HotelAPI.isAdminSession = function () {
    try {
      var u = HotelAPI.session && HotelAPI.session.get ? HotelAPI.session.get() : null;
      if (u && (u.VaiTro === "Admin" || u.role === "admin")) return true;
    } catch (e) {}
    try {
      if (window.parent && window.parent !== window && window.parent.user && window.parent.user.role === "admin") {
        return true;
      }
    } catch (e2) {}
    return false;
  };

  /** Ẩn các liên kết chỉ dành cho khách hàng (Lịch sử đặt phòng, Yêu thích) khi Admin xem trang công khai. */
  HotelAPI.hideCustomerOnlyLinks = function () {
    if (!HotelAPI.isAdminSession()) return;
    document.querySelectorAll('[data-go="hist"], [data-go="book"], #foot-fav-link').forEach(function (el) {
      var row = el.closest("p");
      if (row) row.style.display = "none";
      else el.style.display = "none";
    });
  };

  window.HotelAPI = HotelAPI;
})();
