/* Quản lý tài khoản & Lịch sử đặt phòng: Đồng bộ 100% với Backend PHP/MySQL */
(function () {
  "use strict";

  var API_BASE = (window.HotelAPI && window.HotelAPI.base) ? window.HotelAPI.base : "/Backend/public/index.php";

  var $ = function (id) { return document.getElementById(id); };

  function esc(s) {
    return String(s == null ? "" : s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function fmtMoney(n) {
    var num = Number(n || 0);
    if (isNaN(num)) num = 0;
    return Math.round(num).toLocaleString("vi-VN") + " VNĐ";
  }

  function fmtDateTime(str) {
    if (!str) return "—";
    var s = String(str).trim();
    var m = /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/.exec(s);
    if (m) {
      return m[3] + "/" + m[2] + "/" + m[1] + (m[4] ? " " + m[4] + ":" + m[5] : "");
    }
    return s;
  }

  function fmtDateOnly(str) {
    if (!str) return "";
    var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(str).trim());
    return m ? m[1] + "-" + m[2] + "-" + m[3] : "";
  }

  var currentUser = null;
  var currentTab = "bookings";
  var allBookings = [];
  var curFilter = "ALL";
  var loadingBookings = false;
  var bookingsError = "";
  var currentSlipDetail = null;
  var currentPayItem = null;
  var currentReviewFilter = "all";
  var reviewRoomItems = [];
  var currentReviewMaPhieu = "";
  var currentReviewMaPhong = "";
  var reviewReadOnlyMode = false;
  var selectedStars = 5;

  var STAR_TEXTS = {
    1: "1 Sao - Rất tệ, cần cải thiện nhiều",
    2: "2 Sao - Kém, chưa hài lòng",
    3: "3 Sao - Bình thường, tạm ổn",
    4: "4 Sao - Tốt, hài lòng",
    5: "5 Sao - Tuyệt vời, vượt mong đợi!"
  };

  /* ================== SESSION & USER RESOLUTION ================== */
  function readStoredSessionUser() {
    var stores = [window.localStorage];
    try {
      if (window.parent && window.parent !== window && window.parent.localStorage) {
        stores.unshift(window.parent.localStorage);
      }
    } catch (e) {}

    for (var s = 0; s < stores.length; s++) {
      var st = stores[s];
      if (!st) continue;
      try {
        var raw = st.getItem("sky_session_v1");
        if (raw) {
          var parsed = JSON.parse(raw);
          var u = parsed && parsed.user ? parsed.user : parsed;
          if (u && (u.email || u.username || u.maTK || u.name)) return u;
        }
      } catch (e1) {}
      try {
        var rawLegacy = st.getItem("userData");
        if (rawLegacy) {
          var u2 = JSON.parse(rawLegacy);
          if (u2 && (u2.MaTK || u2.maTK || u2.Email || u2.email)) {
            return {
              maTK: u2.MaTK || u2.maTK,
              maKH: u2.MaKH || u2.maKH,
              username: u2.TenDangNhap || u2.username,
              name: u2.HoTen || u2.name,
              email: u2.Email || u2.email,
              phone: u2.SDT || u2.phone,
              role: u2.VaiTro || u2.role,
              be: u2
            };
          }
        }
      } catch (e2) {}
    }
    return null;
  }

  function getMaTK(u) {
    var user = u || currentUser || {};
    var be = user.be || {};
    return user.maTK || user.MaTK || be.MaTK || be.maTK || "";
  }

  function getMaKH(u) {
    var user = u || currentUser || {};
    var be = user.be || {};
    return user.maKH || user.MaKH || be.MaKH || be.maKH || "";
  }

  function isAdminAccount(u) {
    if (window.HotelAPI && window.HotelAPI.isAdminSession && window.HotelAPI.isAdminSession()) return true;
    var user = u || currentUser || readStoredSessionUser();
    if (!user) return false;
    var be = user.be || {};
    return String(user.role || be.VaiTro || "").toLowerCase() === "admin";
  }

  function redirectAdminAwayFromHistory() {
    if (parent !== window) {
      parent.postMessage({ skyToast: "Tài khoản Quản trị viên không sử dụng mục Lịch sử đặt phòng.", skyGo: "mgr" }, "*");
    } else {
      window.location.replace("dashboard-quan-ly.html");
    }
  }

  function getUserRole(u) {
    var user = u || currentUser || {};
    var be = user.be || {};
    var r = String(user.role || be.VaiTro || "").toLowerCase();
    if (r === "letan" || r === "staff") return "letan";
    return "customer";
  }

  function apiFetch(path, options) {
    options = options || {};
    var headers = options.headers || {};
    headers["Content-Type"] = "application/json";
    var maTK = getMaTK();
    if (maTK && !headers["Authorization"]) {
      headers["Authorization"] = "Bearer " + maTK;
    }
    options.headers = headers;
    return fetch(API_BASE + path, options).then(function (res) {
      return res.json().catch(function () {
        return { success: false, message: "Phản hồi máy chủ không hợp lệ (HTTP " + res.status + ")" };
      });
    });
  }

  /* ================== TAB SWITCHING ================== */
  function switchTab(tabId) {
    if (!tabId) tabId = "bookings";
    currentTab = tabId;

    var panes = document.querySelectorAll(".tab-pane");
    panes.forEach(function (p) { p.style.display = "none"; });

    var targetPane = $("pane-" + tabId);
    if (targetPane) {
      targetPane.style.display = "block";
    } else if ($("pane-bookings")) {
      $("pane-bookings").style.display = "block";
    }

    var links = document.querySelectorAll("aside [data-tab]");
    links.forEach(function (l) {
      if (l.getAttribute("data-tab") === tabId) l.classList.add("on");
      else l.classList.remove("on");
    });

    if (tabId === "reviews") {
      loadReviewableRooms();
    } else if (tabId === "support") {
      renderSupportCancelPolicy();
    }
  }

  /**
   * Hiển thị bảng chính sách hủy phòng & hoàn tiền trong tab Hỗ trợ 24/7
   * từ nguồn chuẩn HotelAPI.policy (khớp Backend/shared/ChinhSachHoanHuy.php).
   */
  function renderSupportCancelPolicy() {
    var box = $("supportCancelPolicyBox");
    if (!box) return;

    function buildTable(rules) {
      if (!Array.isArray(rules) || !rules.length) return "";
      var rows = rules.map(function (r) {
        var hoan = Number(r.tyLeHoan || 0);
        var phi = Number(r.tyLePhiHuy != null ? r.tyLePhiHuy : Math.max(0, 100 - hoan));
        var cls = hoan >= 100 ? "rate-100" : (hoan > 0 ? "rate-50" : "rate-0");
        return (
          '<tr>' +
            '<td><b>' + esc(r.thoiDiem || "") + '</b></td>' +
            '<td><span class="support-policy-badge ' + cls + '">Hoàn ' + hoan + '%</span></td>' +
            '<td><span style="color:var(--muted);font-weight:600">Phí hủy ' + phi + '%</span></td>' +
          '</tr>'
        );
      }).join("");
      return (
        '<table class="support-policy-table">' +
          '<thead><tr><th>Thời điểm hủy phòng</th><th>Tỷ lệ hoàn tiền</th><th>Phí hủy</th></tr></thead>' +
          '<tbody>' + rows + '</tbody>' +
        '</table>'
      );
    }

    var localRules = (window.HotelAPI && window.HotelAPI.policy && typeof window.HotelAPI.policy.getRules === "function")
      ? window.HotelAPI.policy.getRules()
      : [];
    if (localRules.length && !box.dataset.policyLoaded) {
      box.innerHTML = buildTable(localRules);
    }

    if (!box.dataset.policySyncing && window.HotelAPI && window.HotelAPI.datPhong && typeof window.HotelAPI.datPhong.getCancellationPolicy === "function") {
      box.dataset.policySyncing = "1";
      window.HotelAPI.datPhong.getCancellationPolicy().then(function (res) {
        var apiRules = res && res.data && Array.isArray(res.data.quyTac) ? res.data.quyTac : null;
        if (apiRules && apiRules.length) {
          box.innerHTML = buildTable(apiRules);
          box.dataset.policyLoaded = "1";
        }
      }).catch(function () {}).finally(function () {
        delete box.dataset.policySyncing;
      });
    }
  }

  function checkHash() {
    var h = (window.location.hash || "").replace("#", "").trim();
    if (h && ["profile", "bookings", "reviews", "support"].indexOf(h) > -1) {
      switchTab(h);
    }
  }

  /* ================== PROFILE DATA & FORM ================== */
  function showAlert(targetId, msg, isError) {
    var el = $(targetId);
    if (!el) return;
    el.className = "prof-alert " + (isError ? "error" : "success");
    el.textContent = msg;
    el.style.display = "block";
    clearTimeout(showAlert[targetId + "Timer"]);
    showAlert[targetId + "Timer"] = setTimeout(function () {
      el.style.display = "none";
    }, 4500);
  }

  var PROFILE_FIELD_IDS = [
    "prof-name",
    "prof-phone",
    "prof-email",
    "prof-dob",
    "prof-nationality",
    "prof-idtype",
    "prof-cccd"
  ];
  var initialProfileSnapshot = null;

  function getProfileSnapshot() {
    var snap = {};
    PROFILE_FIELD_IDS.forEach(function (id) {
      var el = $(id);
      snap[id] = el ? String(el.value || "").trim() : "";
    });
    return snap;
  }

  function isProfileDirty() {
    if (!initialProfileSnapshot) return false;
    var cur = getProfileSnapshot();
    return PROFILE_FIELD_IDS.some(function (id) {
      return cur[id] !== (initialProfileSnapshot[id] || "");
    });
  }

  function syncProfileActionButtons(isSaving) {
    var btnSave = $("btn-save-profile");
    var btnReset = $("btn-reset-profile");
    if (isSaving) {
      if (btnSave) {
        btnSave.disabled = true;
        btnSave.textContent = "⏳ Đang lưu...";
      }
      if (btnReset) btnReset.disabled = true;
      return;
    }
    var dirty = isProfileDirty();
    if (btnSave) {
      btnSave.disabled = !dirty;
      btnSave.textContent = "💾 Lưu thay đổi thông tin";
    }
    if (btnReset) {
      btnReset.disabled = !dirty;
    }
  }

  function captureProfileSnapshot() {
    initialProfileSnapshot = getProfileSnapshot();
    syncProfileActionButtons(false);
  }

  function populateProfileUI(u) {
    if (!u) return;
    currentUser = u;
    var be = u.be || {};
    var fullName = u.name || be.HoTen || u.username || "Khách hàng";
    var emailVal = u.email || be.Email || "";
    var phoneVal = u.phone || be.SDT || "";
    var maTK = getMaTK(u) || "—";
    var maKH = getMaKH(u) || "—";
    var usernameVal = u.username || be.TenDangNhap || emailVal;
    var userRole = getUserRole(u);
    var roleLabel = userRole === "letan" ? "Lễ tân · SKY HOTEL" : "Khách hàng Thành viên";
    var roleBadge = userRole === "letan" ? "🛎️ NHÂN VIÊN LỄ TÂN" : "⭐ SKY ELITE MEMBER";

    if ($("side-name")) $("side-name").textContent = fullName;
    if ($("side-email")) $("side-email").textContent = emailVal || phoneVal;
    if ($("side-av")) $("side-av").textContent = (fullName || "K").charAt(0).toUpperCase();
    if ($("side-tier")) $("side-tier").textContent = roleLabel;
    var vipPill = document.querySelector(".vip-badge-pill");
    if (vipPill) vipPill.textContent = roleBadge;

    if ($("vip-user-name")) $("vip-user-name").textContent = fullName;
    if ($("vip-code")) $("vip-code").textContent = maKH !== "—" ? maKH : maTK;
    if ($("vip-username")) $("vip-username").textContent = usernameVal || "—";
    if ($("vip-email")) $("vip-email").textContent = emailVal || "—";
    if ($("vip-matk")) $("vip-matk").textContent = maTK;

    if ($("prof-name")) $("prof-name").value = fullName;
    if ($("prof-email")) $("prof-email").value = emailVal;
    if ($("prof-phone")) $("prof-phone").value = phoneVal;
    if ($("prof-dob")) $("prof-dob").value = fmtDateOnly(u.dob || be.NgaySinh || "");
    if ($("prof-nationality")) $("prof-nationality").value = u.quocTich || be.QuocTich || "Việt Nam";
    if ($("prof-idtype")) $("prof-idtype").value = u.loaiGiayTo || be.LoaiGiayTo || "CCCD";
    if ($("prof-cccd")) $("prof-cccd").value = u.cccd || be.SoGiayTo || "";
    if ($("prof-username")) $("prof-username").value = usernameVal;
    if ($("prof-makh")) $("prof-makh").value = (maKH !== "—" ? maKH + " / " : "") + maTK;

    captureProfileSnapshot();
  }

  function loadBackendProfile() {
    var maTK = getMaTK();
    if (!maTK) return;
    apiFetch("/khachhang/xemthongtincanhan", {
      method: "POST",
      body: JSON.stringify({ maTK: maTK })
    }).then(function (res) {
      if (res && res.success && res.data) {
        var d = res.data;
        currentUser = currentUser || {};
        currentUser.maTK = d.MaTK || maTK;
        currentUser.maKH = d.MaKH || currentUser.maKH || "";
        currentUser.username = d.TenDangNhap || currentUser.username || "";
        currentUser.name = d.HoTen || currentUser.name || "";
        currentUser.email = d.Email || currentUser.email || "";
        currentUser.phone = d.SDT || currentUser.phone || "";
        currentUser.dob = fmtDateOnly(d.NgaySinh);
        currentUser.quocTich = d.QuocTich || "Việt Nam";
        currentUser.loaiGiayTo = d.LoaiGiayTo || "CCCD";
        currentUser.cccd = d.SoGiayTo || "";
        populateProfileUI(currentUser);
      }
    }).catch(function () {});
  }

  var formProfile = $("form-profile");
  if (formProfile) {
    PROFILE_FIELD_IDS.forEach(function (id) {
      var el = $(id);
      if (!el) return;
      el.addEventListener("input", function () { syncProfileActionButtons(false); });
      el.addEventListener("change", function () { syncProfileActionButtons(false); });
    });

    formProfile.addEventListener("submit", function (e) {
      e.preventDefault();
      if (!isProfileDirty()) return;

      var hoTen = ($("prof-name").value || "").replace(/\s+/g, " ").trim();
      var sdt = ($("prof-phone").value || "").trim();
      var emailVal = ($("prof-email").value || "").trim();
      var ngaySinh = ($("prof-dob").value || "").trim();
      var quocTich = ($("prof-nationality").value || "Việt Nam").trim();
      var loaiGiayTo = ($("prof-idtype").value || "CCCD").trim();
      var soGiayTo = ($("prof-cccd").value || "").trim();

      if (!hoTen || !sdt || !emailVal) {
        showAlert("prof-alert", "Vui lòng điền đầy đủ Họ tên, Số điện thoại và Email.", true);
        return;
      }
      if (!/^[\p{L}\s]{2,100}$/u.test(hoTen)) {
        showAlert("prof-alert", "Họ và tên chỉ được chứa chữ cái và khoảng trắng, không chứa số hoặc ký tự đặc biệt.", true);
        if ($("prof-name")) $("prof-name").focus();
        return;
      }

      var maTK = getMaTK();
      syncProfileActionButtons(true);

      apiFetch("/khachhang/capnhatthongtincanhan", {
        method: "POST",
        body: JSON.stringify({
          maTK: maTK,
          hoTen: hoTen,
          sdt: sdt,
          email: emailVal,
          ngaySinh: ngaySinh || null,
          quocTich: quocTich,
          loaiGiayTo: loaiGiayTo,
          soGiayTo: soGiayTo
        })
      }).then(function (res) {
        var updated = {
          name: hoTen,
          phone: sdt,
          email: emailVal,
          dob: ngaySinh,
          quocTich: quocTich,
          loaiGiayTo: loaiGiayTo,
          cccd: soGiayTo
        };
        if (currentUser) {
          Object.assign(currentUser, updated);
          populateProfileUI(currentUser);
        } else {
          captureProfileSnapshot();
        }
        parent.postMessage({ skyUpdateProfile: updated }, "*");
        if (res && res.success) {
          showAlert("prof-alert", "✓ " + (res.message || "Cập nhật thông tin cá nhân thành công!"), false);
        } else {
          showAlert("prof-alert", "✓ Đã lưu thông tin tài khoản" + (res && res.message ? " (" + res.message + ")" : "!"), false);
        }
      }).catch(function () {
        syncProfileActionButtons(false);
        showAlert("prof-alert", "Không thể kết nối máy chủ để cập nhật hồ sơ.", true);
      });
    });
  }

  var btnResetProfile = $("btn-reset-profile");
  if (btnResetProfile) {
    btnResetProfile.addEventListener("click", function () {
      if (initialProfileSnapshot) {
        PROFILE_FIELD_IDS.forEach(function (id) {
          var el = $(id);
          if (el) el.value = initialProfileSnapshot[id] || "";
        });
      }
      if (currentUser) populateProfileUI(currentUser);
      loadBackendProfile();
      syncProfileActionButtons(false);
      showAlert("prof-alert", "Đã khôi phục thông tin ban đầu.", false);
    });
  }

  var formPw = $("form-password");
  if (formPw) {
    formPw.addEventListener("submit", function (e) {
      e.preventDefault();
      var oldPw = ($("old-pw").value || "").trim();
      var newPw = ($("new-pw").value || "").trim();
      var newPw2 = ($("new-pw2").value || "").trim();

      if (!oldPw || !newPw || !newPw2) {
        showAlert("pw-alert", "Vui lòng nhập đầy đủ mật khẩu hiện tại và mật khẩu mới.", true);
        return;
      }
      if (newPw.length < 6) {
        showAlert("pw-alert", "Mật khẩu mới phải từ 6 ký tự trở lên.", true);
        return;
      }
      if (newPw !== newPw2) {
        showAlert("pw-alert", "Mật khẩu mới và xác nhận mật khẩu không khớp.", true);
        return;
      }

      var maTK = getMaTK();
      apiFetch("/khachhang/doimatkhau", {
        method: "POST",
        body: JSON.stringify({
          maTK: maTK,
          matKhauCu: oldPw,
          matKhauMoi: newPw,
          xacNhanMatKhau: newPw2
        })
      }).then(function (res) {
        if (res && res.success) {
          parent.postMessage({ skyUpdateProfile: { pw: newPw } }, "*");
          showAlert("pw-alert", "✓ " + (res.message || "Đổi mật khẩu thành công!"), false);
          $("old-pw").value = "";
          $("new-pw").value = "";
          $("new-pw2").value = "";
        } else {
          showAlert("pw-alert", (res && res.message) || "Đổi mật khẩu thất bại. Vui lòng kiểm tra mật khẩu cũ.", true);
        }
      }).catch(function () {
        showAlert("pw-alert", "Lỗi kết nối máy chủ khi đổi mật khẩu.", true);
      });
    });
  }

  /* ================== BOOKING HISTORY (BACKEND + FALLBACK) ================== */
  var STATUS_TABS = [
    ["ALL", "Tất cả"],
    ["ChoThanhToan", "⏳ Chờ thanh toán"],
    ["DaDatPhong", "📅 Đã đặt phòng"],
    ["DaNhanPhong", "🏨 Đang lưu trú"],
    ["DaTraPhong", "✅ Đã trả phòng"],
    ["DaHuy", "❌ Đã hủy / Lỗi TT"]
  ];

  var STATUS_META = {
    ChoThanhToan: { label: "⏳ Chờ thanh toán", cls: "st-ChoThanhToan" },
    DaDatPhong: { label: "📅 Đã đặt phòng", cls: "st-DaDatPhong" },
    DaNhanPhong: { label: "🏨 Đang lưu trú", cls: "st-DaNhanPhong" },
    DaTraPhong: { label: "✅ Đã trả phòng", cls: "st-DaTraPhong" },
    DaHuy: { label: "❌ Đã hủy", cls: "st-DaHuy" },
    ThanhToanThatBai: { label: "⚠️ Lỗi thanh toán", cls: "st-ThanhToanThatBai" }
  };

  function normalizeSlipItem(raw) {
    var st = raw.TrangThai || raw.TrangThaiPhieu || "DaDatPhong";
    return {
      MaPhieu: raw.MaPhieu || raw.code || "",
      NgayLap: raw.NgayLap || raw.createdAt || "",
      TrangThai: st,
      NgayNhan: raw.NgayNhan || raw.checkIn || "",
      NgayTra: raw.NgayTra || raw.checkOut || "",
      DanhSachPhong: raw.DanhSachPhong || "Chưa gán phòng",
      SoNguoi: Number(raw.SoNguoi || raw.TongSoKhach || 1),
      TongSoPhong: Number(raw.TongSoPhong || raw.SoPhong || 1),
      SoPhongDuDieuKien: Number(raw.SoPhongDuDieuKien || 0),
      SoPhongDaDanhGia: Number(raw.SoPhongDaDanhGia || 0),
      MaHD: raw.MaHD || "",
      TongTien: Number(raw.TongTien != null ? raw.TongTien : (raw.TongTienSauVAT || 0)),
      TrangThaiThanhToan: raw.TrangThaiThanhToan || (st === "ChoThanhToan" || st === "DaHuy" || st === "ThanhToanThatBai" ? "ChuaThanhToan" : "DaThanhToan"),
      KenhDat: raw.KenhDat || "Online"
    };
  }

  function updateStatsCounters() {
    var total = allBookings.length;
    var done = allBookings.filter(function (b) { return b.TrangThai === "DaTraPhong"; }).length;
    var revCount = allBookings.reduce(function (s, b) { return s + Number(b.SoPhongDaDanhGia || 0); }, 0);
    if ($("statTongDon")) $("statTongDon").textContent = String(total);
    if ($("statDonHoanTat")) $("statDonHoanTat").textContent = String(done);
    if ($("statDanhGia")) $("statDanhGia").textContent = String(revCount);
  }

  function drawBookingTabs() {
    var tabsEl = $("tabs");
    if (!tabsEl) return;
    tabsEl.innerHTML = STATUS_TABS.map(function (t) {
      var code = t[0];
      var count = code === "ALL"
        ? allBookings.length
        : allBookings.filter(function (b) {
            if (code === "DaHuy") return b.TrangThai === "DaHuy" || b.TrangThai === "ThanhToanThatBai";
            return b.TrangThai === code;
          }).length;
      return '<button type="button" class="tab' + (code === curFilter ? " on" : "") + '" data-k="' + code + '">' +
        t[1] + ' <span style="opacity:0.75;font-weight:700">(' + count + ')</span></button>';
    }).join("");
  }

  function drawBookings() {
    drawBookingTabs();
    updateStatsCounters();

    var L = $("list");
    if (!L) return;

    if (loadingBookings) {
      L.innerHTML = '<div class="empty">⏳ Đang tải lịch sử đặt phòng...</div>';
      return;
    }

    if (bookingsError) {
      L.innerHTML = '<div class="empty">⚠️ ' + esc(bookingsError) + ' <br><br><button type="button" class="btn-gold btn-sm" id="btn-retry-load">🔄 Thử lại</button></div>';
      var retryBtn = $("btn-retry-load");
      if (retryBtn) retryBtn.onclick = loadBookings;
      return;
    }

    var filtered = allBookings.filter(function (b) {
      if (curFilter === "ALL") return true;
      if (curFilter === "DaHuy") return b.TrangThai === "DaHuy" || b.TrangThai === "ThanhToanThatBai";
      return b.TrangThai === curFilter;
    });

    if (!filtered.length) {
      L.innerHTML =
        '<div class="empty">' +
          '<div style="font-size:32px;margin-bottom:8px">🛎️</div>' +
          '<b>Chưa có phiếu đặt phòng nào trong mục này</b>' +
          '<p style="margin:6px 0 14px;font-size:13px">Hãy chọn phòng nghỉ yêu thích và đặt kỳ nghỉ tại SKY HOTEL Quận 10.</p>' +
          '<button type="button" class="btn-gold btn-sm" data-goto-booking="1">+ Đặt phòng mới ngay</button>' +
        '</div>';
      return;
    }

    L.innerHTML = filtered.map(function (b) {
      var stMeta = STATUS_META[b.TrangThai] || { label: b.TrangThai, cls: "st-DaDatPhong" };
      var isPaid = b.TrangThaiThanhToan === "DaThanhToan" || b.TrangThai === "DaDatPhong" || b.TrangThai === "DaNhanPhong" || b.TrangThai === "DaTraPhong";
      var payBadge = isPaid
        ? '<span class="bd paid">✓ Đã thanh toán</span>'
        : (b.TrangThai === "ChoThanhToan" ? '<span class="bd pend">⏳ Chờ thanh toán</span>' : '<span class="bd can">Chưa thanh toán</span>');

      var canPayNow = b.TrangThai === "ChoThanhToan" && b.MaHD;
      var canSelfCancel = b.TrangThai === "DaDatPhong" || b.TrangThai === "ChoThanhToan";
      var canReview = b.TrangThai === "DaTraPhong";

      return (
        '<article class="bk-card">' +
          '<div class="bk-card-top">' +
            '<div class="bk-code-wrap">' +
              '<span class="bk-code">🔖 Mã phiếu: ' + esc(b.MaPhieu) + '</span>' +
              '<span class="bk-created">Ngày đặt: ' + esc(fmtDateTime(b.NgayLap)) + '</span>' +
              (b.MaHD ? '<span class="bk-created">· Mã HĐ: <b>' + esc(b.MaHD) + '</b></span>' : '') +
            '</div>' +
            '<div style="display:flex;gap:8px;align-items:center">' +
              payBadge +
              '<span class="st-badge ' + stMeta.cls + '">' + stMeta.label + '</span>' +
            '</div>' +
          '</div>' +
          '<div class="bk-card-body">' +
            '<div class="bk-info-col">' +
              '<div class="bk-rooms-title">🏨 Phòng: ' + esc(b.DanhSachPhong || "Đang cập nhật") + '</div>' +
              '<div class="bk-meta-line bk-meta-chips">' +
                '<span class="bk-meta-chip">🛏️ Số lượng: <b>' + b.TongSoPhong + ' phòng</b></span>' +
                '<span aria-hidden="true">·</span>' +
                '<span class="bk-meta-chip">👥 Khách lưu trú: <b>' + b.SoNguoi + ' người</b></span>' +
              '</div>' +
              '<div class="bk-meta-line bk-meta-chips bk-meta-addr">' +
                '<span class="bk-meta-chip">📍 SKY HOTEL District 10</span>' +
                '<span aria-hidden="true">·</span>' +
                '<span class="bk-meta-chip">590 Cách Mạng Tháng 8, Quận 3 / Quận 10, TP.HCM</span>' +
              '</div>' +
            '</div>' +
            '<div class="bk-dates-box">' +
              '<div class="bk-dates-row"><span style="color:var(--muted)">📅 Nhận phòng:</span><strong>' + esc(fmtDateTime(b.NgayNhan)) + '</strong></div>' +
              '<div class="bk-dates-row"><span style="color:var(--muted)">📅 Trả phòng:</span><strong>' + esc(fmtDateTime(b.NgayTra)) + '</strong></div>' +
            '</div>' +
            '<div class="bk-price-col">' +
              '<div class="bk-total-lbl">Tổng thanh toán (Đã gồm VAT)</div>' +
              '<div class="bk-total-val">' + fmtMoney(b.TongTien) + '</div>' +
            '</div>' +
          '</div>' +
          '<div class="bk-card-foot">' +
            '<div style="font-size:12px;color:var(--muted)">' +
              (b.TrangThai === "ChoThanhToan"
                ? '⚠️ Đơn đang giữ chỗ tạm thời. Vui lòng hoàn tất thanh toán để xác nhận phòng.'
                : (b.TrangThai === "DaDatPhong"
                    ? '✓ Phòng đã được giữ chỗ thành công. Vui lòng xuất trình CCCD khi làm thủ tục Check-in.'
                    : (b.TrangThai === "DaTraPhong"
                        ? '🌟 Cảm ơn quý khách đã lưu trú tại SKY HOTEL!'
                        : (b.TrangThai === "DaHuy"
                            ? '❌ Đơn đặt phòng đã được hủy.'
                            : '')))) +
            '</div>' +
            '<div class="bk-actions">' +
              (canPayNow
                ? '<button type="button" class="btn-act pay" data-pay-phieu="' + esc(b.MaPhieu) + '">💳 Thanh toán ngay</button>'
                : '') +
              '<button type="button" class="btn-act primary" data-detail-phieu="' + esc(b.MaPhieu) + '">📄 Xem phiếu</button>' +
              (b.MaHD
                ? '<button type="button" class="btn-act" data-invoice-phieu="' + esc(b.MaPhieu) + '" data-invoice-hd="' + esc(b.MaHD) + '">🧾 Xem hóa đơn GTGT</button>'
                : '') +
              (canSelfCancel
                ? '<button type="button" class="btn-act cancel" data-cancel-phieu="' + esc(b.MaPhieu) + '">🚫 Hủy đặt phòng</button>'
                : '') +
              (canReview
                ? '<button type="button" class="btn-act review" data-review-phieu="' + esc(b.MaPhieu) + '">⭐ Đánh giá phòng</button>'
                : '') +
            '</div>' +
          '</div>' +
        '</article>'
      );
    }).join("");
  }

  function loadBookings() {
    if (isAdminAccount()) {
      loadingBookings = false;
      allBookings = [];
      redirectAdminAwayFromHistory();
      return;
    }
    if (!currentUser) {
      var stored = readStoredSessionUser();
      if (stored) populateProfileUI(stored);
    }

    var maTK = getMaTK();
    var maKH = getMaKH();
    var userPhone = ((currentUser && currentUser.phone) || (currentUser && currentUser.be && currentUser.be.SDT) || "").trim();
    var userEmail = ((currentUser && currentUser.email) || (currentUser && currentUser.be && currentUser.be.Email) || "").trim().toLowerCase();
    var userName = ((currentUser && currentUser.name) || (currentUser && currentUser.be && currentUser.be.HoTen) || "").trim().toLowerCase();

    if (!maTK && !userPhone && !userEmail) {
      loadingBookings = false;
      allBookings = [];
      drawBookings();
      return;
    }

    loadingBookings = true;
    bookingsError = "";
    drawBookings();

    // 1. Gọi API chuẩn của khách hàng: POST /datphong/lichsu
    var pHistory = maTK
      ? apiFetch("/datphong/lichsu", {
          method: "POST",
          body: JSON.stringify({ maTK: maTK, limit: 100, offset: 0 })
        }).catch(function () { return { success: false }; })
      : Promise.resolve({ success: false });

    // 2. Đồng thời lấy danh sách phiếu từ /letan/xemdsphieu (GET hoặc POST)
    // Đảm bảo hiển thị đầy đủ mọi phiếu: theo MaLeTan của lễ tân
    // HOẶC theo SĐT, Tên người đặt, Mã KH, Email
    var maLeTan = (currentUser && (currentUser.maLeTan || (currentUser.be && currentUser.be.MaLeTan))) || "";
    var pLetanList = apiFetch("/letan/xemdsphieu", { method: "GET" })
      .catch(function () { return { success: false }; });

    Promise.all([pHistory, pLetanList]).then(function (results) {
      var resHist = results[0];
      var resLetan = results[1];
      var mapByCode = {};
      var merged = [];

      function addSlip(item) {
        if (!item || !item.MaPhieu) return;
        if (mapByCode[item.MaPhieu]) return;
        mapByCode[item.MaPhieu] = true;
        merged.push(normalizeSlipItem(item));
      }

      if (resHist && resHist.success && resHist.data && Array.isArray(resHist.data.items)) {
        resHist.data.items.forEach(addSlip);
      }

      // Xử lý dữ liệu trả về từ /letan/xemdsphieu
      var letanItems = [];
      if (resLetan && resLetan.success && resLetan.data) {
        if (Array.isArray(resLetan.data.items)) {
          letanItems = resLetan.data.items;
        } else if (Array.isArray(resLetan.data)) {
          letanItems = resLetan.data;
        }
      }

      letanItems.forEach(function (row) {
        var rowPhone = String(row.SDT || "").trim();
        var rowEmail = String(row.Email || "").trim().toLowerCase();
        var rowName = String(row.TenKH || "").trim().toLowerCase();
        var rowMaKH = String(row.MaKH || "").trim();
        var rowMaLeTan = String(row.MaLeTan || "").trim();

        var match =
          (maLeTan && rowMaLeTan === maLeTan) ||
          (maKH && rowMaKH === maKH) ||
          (userPhone && rowPhone === userPhone) ||
          (userEmail && rowEmail && rowEmail === userEmail) ||
          (userName && rowName && (rowName === userName || rowName.indexOf(userName) > -1 || userName.indexOf(rowName) > -1));

        if (match) {
          addSlip(row);
        }
      });

      // Nếu người dùng là lễ tân và chưa có phiếu riêng lẻ, hiển thị các phiếu gần nhất
      if (!merged.length && (currentUser && (currentUser.role === "staff" || currentUser.role === "letan" || (currentUser.be && currentUser.be.VaiTro === "LeTan")))) {
        letanItems.slice(0, 15).forEach(addSlip);
      }

      // Sắp xếp theo mã phiếu / ngày lập mới nhất lên đầu
      merged.sort(function (a, b) {
        var nA = parseInt(String(a.MaPhieu).replace(/\D/g, ""), 10) || 0;
        var nB = parseInt(String(b.MaPhieu).replace(/\D/g, ""), 10) || 0;
        if (nB !== nA) return nB - nA;
        return String(b.NgayLap).localeCompare(String(a.NgayLap));
      });

      allBookings = merged;
      loadingBookings = false;
      drawBookings();
    }).catch(function (err) {
      loadingBookings = false;
      bookingsError = (err && err.message) || "Không thể tải danh sách lịch sử đặt phòng.";
      drawBookings();
    });
  }

  /* ================== SLIP DETAIL MODAL (#pnpDetailModal) ================== */
  function fetchSlipDetail(maPhieu) {
    var maTK = getMaTK();
    return apiFetch("/datphong/xemctphieu", {
      method: "POST",
      body: JSON.stringify({ maPhieu: maPhieu, maTK: maTK })
    }).then(function (res) {
      if (res && res.success && res.data) return res;
      // Fallback sang /letan/xemctphieu (POST { maPhieu })
      return apiFetch("/letan/xemctphieu", {
        method: "POST",
        body: JSON.stringify({ maPhieu: maPhieu })
      }).then(function (res2) {
        if (res2 && res2.success && res2.data) {
          var payload = res2.data.data ? res2.data.data : res2.data;
          var fallbackHd = payload.hoaDon || {};
          return {
            success: true,
            data: {
              Phieu: payload.phieu,
              DanhSachPhong: payload.danhSachPhong,
              NguoiLuuTru: payload.danhSachNguoiLuuTru,
              HoaDon: payload.hoaDon,
              ChiTietHoaDon: payload.chiTietHoaDon || [],
              TongHop: payload.tongHop || {
                tongTienPhong: fallbackHd.TongTienSauVAT || 0,
                tongTienSauVAT: fallbackHd.TongTienSauVAT || 0
              }
            }
          };
        }
        return res2;
      });
    });
  }

  function openSlipDetailModal(maPhieu) {
    var modal = $("pnpDetailModal");
    var body = $("pnpModalBody");
    var title = $("pnpModalTitle");
    if (!modal || !body) return;

    if (title) title.innerHTML = '📋 Chi Tiết Phiếu Nhận Phòng <span style="color:var(--gold-text,#8a6829)">#' + esc(maPhieu) + '</span>';
    if ($("pnpModalSub")) $("pnpModalSub").style.display = "none";
    body.innerHTML = '<div class="empty">⏳ Đang tải thông tin chi tiết phiếu ' + esc(maPhieu) + '...</div>';
    modal.style.display = "flex";
    try { (parent !== window ? parent : window).scrollTo({ top: 0, behavior: "smooth" }); } catch (e) {}

    fetchSlipDetail(maPhieu).then(function (res) {
      if (!res || !res.success || !res.data) {
        body.innerHTML = '<div class="empty">⚠️ ' + esc((res && res.message) || "Không tải được chi tiết phiếu.") + '</div>';
        return;
      }

      var d = res.data;
      currentSlipDetail = d;
      var phieu = d.Phieu || d.phieu || {};
      var rooms = d.DanhSachPhong || d.danhSachPhong || [];
      var guests = d.NguoiLuuTru || d.nguoiLuuTru || [];
      var hd = d.HoaDon || d.hoaDon || {};
      var cthd = d.ChiTietHoaDon || d.chiTietHoaDon || [];
      var th = d.TongHop || d.tongHop || {};

      var stCode = phieu.TrangThai || phieu.TrangThaiPhieu || "DaDatPhong";
      var stMeta = STATUS_META[stCode] || { label: stCode, cls: "st-DaDatPhong" };
      var userRole = getUserRole();
      var isStaffOrAdmin = userRole === "letan";
      var canEditGuest = isStaffOrAdmin && (stCode === "ChoThanhToan" || stCode === "DaDatPhong" || stCode === "DaNhanPhong");

      var isCounter = String(phieu.KenhDat || "").toLowerCase().indexOf("quay") > -1 || !!phieu.MaLeTan;
      var kenhLabel = isCounter ? "🏨 Tại Quầy" : "🌐 Đặt Trực Tuyến";
      var leTanLabel = phieu.TenLeTan || phieu.MaLeTan || "<i>Khách tự đặt trực tuyến</i>";

      var roomCodes = rooms.map(function (r) { return r.MaPhong; }).filter(Boolean);
      var roomCodesStr = roomCodes.length ? roomCodes.join(", ") : "—";
      var nights = Number(phieu.SoDem || 0);
      var rawIn = rooms[0] && (rooms[0].NgayNhan || rooms[0].ThoiGianNhanPhong);
      var rawOut = rooms[0] && (rooms[0].NgayTra || rooms[0].ThoiGianTraPhong);
      if (!nights && rawIn && rawOut) {
        var dIn = new Date(String(rawIn).slice(0, 10) + "T00:00:00");
        var dOut = new Date(String(rawOut).slice(0, 10) + "T00:00:00");
        if (!isNaN(dIn.getTime()) && !isNaN(dOut.getTime())) {
          nights = Math.max(1, Math.round((dOut - dIn) / 86400000));
        }
      }
      if (!nights) nights = 1;

      var guestsHtml = guests.length ? guests.map(function (g) {
        return (
          '<tr>' +
            '<td><span style="display:inline-block;padding:2px 8px;border:1px solid var(--line);border-radius:12px;font-size:12px;color:var(--muted)">' + esc(g.MaNguoiLuuTru || "—") + '</span></td>' +
            '<td><b>' + esc(g.HoTen || "—") + '</b></td>' +
            '<td><span style="color:#1b4d7e;font-weight:600">' + esc(g.SoGiayTo || "—") + '</span></td>' +
            '<td>' + esc(g.SDT || "—") + '</td>' +
            '<td>' + esc(g.QuocTich || "Việt Nam") + '</td>' +
            '<td>' + esc(g.GioiTinh || "—") + '</td>' +
            '<td><span class="bd paid">🔑 ' + esc(g.MaPhong || "—") + '</span></td>' +
            (canEditGuest
              ? '<td>' +
                  (g.MaNguoiLuuTru
                    ? '<button type="button" class="btn-act" data-edit-guest="' + esc(g.MaNguoiLuuTru) + '">✏️ Sửa</button>'
                    : '<span style="color:var(--muted);font-size:12px">—</span>') +
                '</td>'
              : '') +
          '</tr>'
        );
      }).join("") : '<tr><td colspan="' + (canEditGuest ? 8 : 7) + '" style="text-align:center;color:var(--muted)">Chưa khai báo danh sách khách lưu trú</td></tr>';

      var roomsHtml = rooms.length ? rooms.map(function (r) {
        var loaiName = String(r.TenLoaiPhong || "Tiêu chuẩn").replace(/^Phong\s+/i, "").trim();
        var rStCode = r.TrangThaiPhong || r.TrangThai || stCode;
        var rStMeta = STATUS_META[rStCode] || stMeta;
        var roomPrice = Number(r.DonGia != null ? r.DonGia : (r.Gia != null ? r.Gia : (r.GiaCoBan || 0)));
        var rInTime = r.NgayNhan || r.ThoiGianNhanPhong || phieu.NgayNhan;
        var rOutTime = r.NgayTra || r.ThoiGianTraPhong || phieu.NgayTra;
        return (
          '<tr>' +
            '<td><b style="color:#1b4d7e">🔑 ' + esc(r.MaPhong || "—") + '</b></td>' +
            '<td><b>' + esc(loaiName) + '</b></td>' +
            '<td style="color:var(--gold-text,#8a6829);font-weight:700">' + fmtMoney(roomPrice) + '</td>' +
            '<td><span class="st-badge ' + rStMeta.cls + '">' + rStMeta.label + '</span></td>' +
            (isStaffOrAdmin
              ? '<td>' + esc(r.LeTanCheckIn || r.TenLeTanCheckin || r.MaLeTanCheckIn || r.MaLeTanCheckin || "—") + '</td><td>' + esc(r.LeTanCheckOut || r.TenLeTanCheckout || r.MaLeTanCheckOut || r.MaLeTanCheckout || "—") + '</td>'
              : '<td>' + esc(fmtDateTime(rInTime)) + '</td><td>' + esc(fmtDateTime(rOutTime)) + '</td>') +
          '</tr>'
        );
      }).join("") : '<tr><td colspan="6" style="text-align:center;color:var(--muted)">Chưa có dữ liệu phòng</td></tr>';

      var cthdHtml = cthd.length ? cthd.map(function (c) {
        var isRoom = !c.MaCTDV && (c.MaPhong || !c.TenDV);
        var loaiStr = c.TenLoaiPhong ? String(c.TenLoaiPhong).replace(/^Phong\s+/i, "").trim() : "";
        var itemTitle = c.MoTa || c.LoaiChiTiet || (isRoom
          ? ("Tiền phòng " + (c.MaPhong || "") + (loaiStr ? " (" + loaiStr + ")" : ""))
          : (c.TenCTDV || c.TenDV || "Dịch vụ phát sinh"));
        var qtyLabel = isRoom && Number(c.SoDem || 0) > 0 ? (c.SoDem + " đêm") : (c.SoLuong || 1);
        var donGiaVal = Number(c.DonGia || 0);
        var thanhTienVal = c.ThanhTien != null ? Number(c.ThanhTien) : (Number(isRoom && c.SoDem ? c.SoDem : (c.SoLuong || 1)) * donGiaVal);
        return (
          '<tr>' +
            '<td>' + esc(itemTitle.trim() || "Khoản thu") + '</td>' +
            '<td>' + esc(qtyLabel) + '</td>' +
            '<td>' + fmtMoney(donGiaVal) + '</td>' +
            '<td><b>' + fmtMoney(thanhTienVal) + '</b></td>' +
          '</tr>'
        );
      }).join("") : "";

      var payStatusMap = {
        HoanTat: "Đã thanh toán",
        DaThanhToan: "Đã thanh toán",
        ChuaThanhToan: "Chờ thanh toán",
        ChoThanhToan: "Chờ thanh toán",
        DaHoanTien: "Đã hoàn tiền",
        ThatBai: "Thanh toán thất bại"
      };
      var rawPayStatus = (hd && (hd.TrangThai || hd.TrangThaiThanhToan)) || "";
      var payStatusLabel = payStatusMap[rawPayStatus] || rawPayStatus || "—";

      var cthdRoomSum = cthd.reduce(function (acc, c) {
        if (!c.MaCTDV && (c.MaPhong || !c.TenDV)) {
          var q = Number(c.SoDem || c.SoLuong || 1);
          return acc + Number(c.ThanhTien != null ? c.ThanhTien : q * Number(c.DonGia || 0));
        }
        return acc;
      }, 0);
      var tienPhong = Number(
        th.tongTienPhong != null ? th.tongTienPhong
        : (th.TongTienPhong != null ? th.TongTienPhong : cthdRoomSum)
      );
      var tienGiamKM = Number(th.tienGiamKM || 0);
      var tienDichVu = Number(th.tongTienDichVu || 0);
      var tongVat = Number(th.vatPhong || 0) + Number(th.vatDichVu || 0);
      var vatPct = Math.round(Number(th.vatPhongRate || 0.08) * 100);
      var tongThanhToan = Number(
        th.tongTienSauVAT != null ? th.tongTienSauVAT
        : (th.tongThanhToan != null ? th.tongThanhToan
          : (th.TongThanhToan != null ? th.TongThanhToan
            : (hd && (hd.TongTienSauVAT != null ? hd.TongTienSauVAT : hd.TongTien)) || 0))
      );

      var breakdownParts = ["Tiền phòng: " + fmtMoney(tienPhong)];
      if (tienGiamKM > 0) breakdownParts.push("− KM: " + fmtMoney(tienGiamKM));
      if (tienDichVu > 0) breakdownParts.push("+ Dịch vụ: " + fmtMoney(tienDichVu));
      breakdownParts.push("+ VAT (" + vatPct + "%)" + (tongVat > 0 ? ": " + fmtMoney(tongVat) : ""));

      body.innerHTML =
        '<div class="pnp-info-grid" style="grid-template-columns:repeat(3,1fr);gap:12px">' +
          '<div class="pnp-info-item"><small>KHÁCH ĐẶT PHIẾU</small><strong>' + esc(phieu.TenKH || phieu.TenKhachHang || phieu.HoTenKhachHang || (currentUser && currentUser.name) || "—") + '</strong></div>' +
          '<div class="pnp-info-item"><small>SỐ ĐIỆN THOẠI</small><strong>📞 ' + esc(phieu.SDT || (currentUser && currentUser.phone) || "—") + '</strong></div>' +
          '<div class="pnp-info-item"><small>KÊNH ĐẶT PHÒNG</small><span class="st-badge st-DaDatPhong">' + kenhLabel + '</span></div>' +
          (isStaffOrAdmin
            ? '<div class="pnp-info-item"><small>LỄ TÂN TẠO PHIẾU</small><span style="font-size:13.5px;color:var(--muted)">' + leTanLabel + '</span></div>'
            : '<div class="pnp-info-item"><small>HÌNH THỨC ĐẶT</small><strong style="font-size:13.5px;color:#1b4d7e">' + (isCounter ? "🏨 Đặt tại quầy" : "🌐 Đặt trực tuyến") + '</strong></div>') +
          '<div class="pnp-info-item"><small>TRẠNG THÁI PHIẾU</small><span class="st-badge ' + stMeta.cls + '">' + stMeta.label + '</span></div>' +
          '<div class="pnp-info-item"><small>PHÒNG &amp; LƯU TRÚ</small><strong style="color:#1b7a46">' + esc(roomCodesStr) + ' (' + rooms.length + ' phòng · ' + nights + ' đêm)</strong></div>' +
        '</div>' +

        '<div class="pnp-section-title">🪪 Danh Sách Khách Lưu Trú Đã Khai Báo (' + guests.length + ' khách)</div>' +
        '<table class="sky-table">' +
          '<thead><tr><th>MÃ NLT</th><th>HỌ VÀ TÊN</th><th>CCCD / SỐ GIẤY TỜ</th><th>SỐ ĐIỆN THOẠI</th><th>QUỐC TỊCH</th><th>GIỚI TÍNH</th><th>PHÒNG GÁN</th>' +
          (canEditGuest ? '<th>THAO TÁC</th>' : '') +
          '</tr></thead>' +
          '<tbody>' + guestsHtml + '</tbody>' +
        '</table>' +

        '<div class="pnp-section-title">🚪 Danh Sách Phòng Phân Bổ &amp; Trạng Thái Chi Tiết (' + rooms.length + ' phòng)</div>' +
        '<table class="sky-table">' +
          '<thead><tr><th>PHÒNG</th><th>HẠNG / LOẠI PHÒNG</th><th>ĐƠN GIÁ / ĐÊM</th><th>TRẠNG THÁI PHÒNG</th>' +
          (isStaffOrAdmin ? '<th>LỄ TÂN CHECK-IN</th><th>LỄ TÂN CHECK-OUT</th>' : '<th>NGÀY NHẬN</th><th>NGÀY TRẢ</th>') +
          '</tr></thead>' +
          '<tbody>' + roomsHtml + '</tbody>' +
        '</table>' +

        (cthdHtml
          ? '<div class="pnp-section-title">🧾 Chi Tiết Các Khoản Thu (' + esc(hd.MaHD || "") + ')</div>' +
            '<table class="sky-table">' +
              '<thead><tr><th>Nội Dung Khoản Thu</th><th>SL</th><th>Đơn Giá</th><th>Thành Tiền</th></tr></thead>' +
              '<tbody>' + cthdHtml + '</tbody>' +
            '</table>'
          : '') +

        '<div class="pnp-summary-box">' +
          '<div>' +
            '<div style="font-size:12.5px;color:var(--muted)">Mã Hóa Đơn: <b>' + esc((hd && hd.MaHD) || "Chưa xuất") + '</b> · Trạng thái TT: <b>' + esc(payStatusLabel) + '</b></div>' +
            '<div style="font-size:12px;color:var(--muted);margin-top:3px">' + esc(breakdownParts.join(" ")) + '</div>' +
          '</div>' +
          '<div style="text-align:right">' +
            '<small style="color:var(--muted);display:block">TỔNG THANH TOÁN</small>' +
            '<strong style="font-size:20px;color:var(--gold-text,#926f2f)">' + fmtMoney(tongThanhToan) + '</strong>' +
          '</div>' +
        '</div>';
    });
  }

  /* ================== EDIT GUEST MODAL (#editGuestModal) ================== */
  function openEditGuestModal(maNguoiLuuTru) {
    if (!currentSlipDetail) return;
    var guests = currentSlipDetail.NguoiLuuTru || currentSlipDetail.nguoiLuuTru || [];
    var g = guests.find(function (x) { return String(x.MaNguoiLuuTru) === String(maNguoiLuuTru); });
    if (!g) return;

    $("editGuestId").value = g.MaNguoiLuuTru;
    $("editGuestRoomLabel").textContent = (g.MaPhong || "—") + " (" + (g.TenLoaiPhong || "Phòng lưu trú") + ")";
    $("editGuestName").value = g.HoTen || "";
    $("editGuestDob").value = fmtDateOnly(g.NgaySinh);
    $("editGuestNationality").value = g.QuocTich || "Việt Nam";
    $("editGuestIdType").value = g.LoaiGiayTo || "CCCD";
    $("editGuestIdNumber").value = g.SoGiayTo || "";
    $("editGuestNote").value = g.GhiChu || "";
    if ($("editGuestAlert")) $("editGuestAlert").style.display = "none";

    $("editGuestModal").style.display = "flex";
  }

  var editGuestForm = $("editGuestForm");
  if (editGuestForm) {
    editGuestForm.addEventListener("submit", function (e) {
      e.preventDefault();
      var maNguoiLuuTru = $("editGuestId").value;
      var hoTen = ($("editGuestName").value || "").trim();
      var ngaySinh = ($("editGuestDob").value || "").trim();
      var quocTich = ($("editGuestNationality").value || "Việt Nam").trim();
      var loaiGiayTo = ($("editGuestIdType").value || "CCCD").trim();
      var soGiayTo = ($("editGuestIdNumber").value || "").trim();
      var ghiChu = ($("editGuestNote").value || "").trim();

      if (!hoTen || !soGiayTo) {
        showAlert("editGuestAlert", "Vui lòng nhập đầy đủ Họ tên và Số giấy tờ tùy thân.", true);
        return;
      }

      var btn = $("btnSaveGuestSubmit");
      if (btn) { btn.disabled = true; btn.textContent = "⏳ Đang lưu..."; }

      apiFetch("/datphong/capnhatnguoiluutru", {
        method: "POST",
        body: JSON.stringify({
          maTK: getMaTK(),
          maNguoiLuuTru: maNguoiLuuTru,
          hoTen: hoTen,
          ngaySinh: ngaySinh || null,
          quocTich: quocTich,
          loaiGiayTo: loaiGiayTo,
          soGiayTo: soGiayTo,
          ghiChu: ghiChu
        })
      }).then(function (res) {
        if (btn) { btn.disabled = false; btn.textContent = "💾 Lưu Thông Tin"; }
        if (res && res.success) {
          $("editGuestModal").style.display = "none";
          var phieu = currentSlipDetail && (currentSlipDetail.Phieu || currentSlipDetail.phieu);
          if (phieu && phieu.MaPhieu) {
            openSlipDetailModal(phieu.MaPhieu);
          }
        } else {
          showAlert("editGuestAlert", (res && res.message) || "Không thể cập nhật người lưu trú.", true);
        }
      }).catch(function () {
        if (btn) { btn.disabled = false; btn.textContent = "💾 Lưu Thông Tin"; }
        showAlert("editGuestAlert", "Lỗi kết nối máy chủ.", true);
      });
    });
  }

  /* ================== INVOICE MODAL (#invoiceModal) ================== */
  function openInvoiceModal(maPhieu, maHD) {
    var modal = $("invoiceModal");
    var body = $("invoiceModalBody");
    if (!modal || !body) return;

    body.innerHTML = '<div class="empty">⏳ Đang tải hóa đơn điện tử GTGT...</div>';
    modal.style.display = "flex";
    try { (parent !== window ? parent : window).scrollTo({ top: 0, behavior: "smooth" }); } catch (e) {}

    var qs = "?maPhieu=" + encodeURIComponent(maPhieu || "") + (maHD ? "&maHD=" + encodeURIComponent(maHD) : "");
    apiFetch("/hoadon/xemhoadon" + qs, { method: "GET" }).then(function (res) {
      if (!res || !res.success || !res.data) {
        body.innerHTML = '<div class="empty">⚠️ ' + esc((res && res.message) || "Không tìm thấy hóa đơn.") + '</div>';
        return;
      }

      var hd = res.data.hoaDon || {};
      var phieu = res.data.phieu || {};
      var items = res.data.chiTiet || [];
      var payments = res.data.thanhToan || [];
      var th = res.data.tongHop || {};

      var isPaid = payments.some(function (p) { return p && p.TrangThaiThanhToan === "HoanTat"; }) ||
        hd.TrangThaiThanhToan === "HoanTat" || hd.TrangThai === "DaThanhToan";
      var hdStatusText = hd.TrangThai || (isPaid ? "Đã thanh toán" : "Chờ thanh toán");
      var issuedAt = hd.NgayLap || hd.NgayXuat || (payments[0] && payments[0].ThoiGianThanhToan) || "";

      var rowsHtml = items.map(function (it, i) {
        var isRoom = !it.MaCTDV;
        var itemTitle = isRoom
          ? ("Tiền thuê phòng " + (it.MaPhong || "") + " — " + String(it.TenLoaiPhong || it.MaLoaiPhong || "Phòng").replace(/^Phong\s+/i, "").trim())
          : (it.TenCTDV || it.TenDV || it.MoTa || it.LoaiChiTiet || "Dịch vụ phát sinh");
        var qtyLabel = isRoom ? ((it.SoDem || 1) + " đêm") : (it.SoLuong || 1);
        return (
          '<tr>' +
            '<td>' + (i + 1) + '</td>' +
            '<td>' + esc(itemTitle) + '</td>' +
            '<td>' + esc(qtyLabel) + '</td>' +
            '<td>' + fmtMoney(it.DonGia || 0) + '</td>' +
            '<td style="text-align:right"><b>' + fmtMoney(it.ThanhTien || 0) + '</b></td>' +
          '</tr>'
        );
      }).join("");

      var tongTruocThue = Number(th.tongTienPhong || th.TongTruocThue || hd.TongTienTruocThue || 0);
      var tienGiamKM = Number(th.tienGiamKM || 0);
      var tongThueVat = Number(th.vatPhong || th.TongThueVaPhi || 0);
      var tongDichVuSauVAT = Number(th.tongTienDichVuSauVAT || 0);
      var tongCong = Number(th.tongThanhToan || th.TongThanhToan || hd.TongTienSauVAT || hd.TongTien || 0);
      var vatPct = Math.round((Number(th.vatPhongRate || 0.08)) * 100);

      body.innerHTML =
        '<div id="printableInvoiceArea" style="padding:8px">' +
          '<div style="display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid var(--navy);padding-bottom:14px;margin-bottom:16px">' +
            '<div>' +
              '<h2 style="margin:0;color:var(--navy);font-family:var(--serif)">SKY HOTEL DISTRICT 10</h2>' +
              '<div style="font-size:12px;color:var(--muted);margin-top:4px">Địa chỉ: 123 Thành Thái, Quận 10, TP. Hồ Chí Minh</div>' +
              '<div style="font-size:12px;color:var(--muted)">Hotline: 028 3930 8888 · Email: billing@skyhotel.vn</div>' +
            '</div>' +
            '<div style="text-align:right">' +
              '<div style="font-size:16px;font-weight:800;color:var(--gold-text,#926f2f)">HÓA ĐƠN GIÁ TRỊ GIA TĂNG</div>' +
              '<div style="font-size:12.5px;margin-top:4px">Số HĐ: <b>' + esc(hd.MaHD || maHD) + '</b></div>' +
              '<div style="font-size:12px;color:var(--muted)">Ngày xuất: ' + esc(fmtDateTime(issuedAt)) + '</div>' +
            '</div>' +
          '</div>' +

          '<div class="pnp-info-grid" style="grid-template-columns:repeat(3,1fr)">' +
            '<div class="pnp-info-item"><small>Khách hàng</small><strong>' + esc(phieu.TenKH || phieu.HoTenKhachHang || hd.TenKhachHang || (currentUser && currentUser.name) || "Khách lưu trú") + '</strong></div>' +
            '<div class="pnp-info-item"><small>Số điện thoại / Email</small><strong>' + esc((phieu.SDT || hd.SDT || "") + (phieu.Email ? " · " + phieu.Email : "")) + '</strong></div>' +
            '<div class="pnp-info-item"><small>Mã phiếu tham chiếu</small><strong>#' + esc(phieu.MaPhieu || maPhieu) + ' (' + esc(hdStatusText) + ')</strong></div>' +
          '</div>' +

          '<table class="sky-table">' +
            '<thead><tr><th>STT</th><th>Nội Dung Dịch Vụ / Tiền Phòng</th><th>SL</th><th>Đơn Giá</th><th style="text-align:right">Thành Tiền</th></tr></thead>' +
            '<tbody>' + rowsHtml + '</tbody>' +
          '</table>' +

          '<div style="margin-left:auto;max-width:360px;margin-top:14px;border-top:1px solid var(--line);padding-top:10px">' +
            '<div style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:13px"><span>Tổng tiền phòng (chưa VAT):</span><b>' + fmtMoney(tongTruocThue) + '</b></div>' +
            (tienGiamKM > 0 ? '<div style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:13px;color:#1b7a46"><span>Khuyến mãi giảm trừ:</span><b>- ' + fmtMoney(tienGiamKM) + '</b></div>' : '') +
            '<div style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:13px"><span>Thuế VAT phòng (' + vatPct + '%):</span><b>' + fmtMoney(tongThueVat) + '</b></div>' +
            (tongDichVuSauVAT > 0 ? '<div style="display:flex;justify-content:space-between;margin-bottom:6px;font-size:13px"><span>Tiền dịch vụ (đã gồm VAT):</span><b>' + fmtMoney(tongDichVuSauVAT) + '</b></div>' : '') +
            '<div style="display:flex;justify-content:space-between;font-size:16px;font-weight:800;color:var(--gold-text,#926f2f);border-top:1px dashed var(--line);padding-top:8px"><span>TỔNG CỘNG:</span><span>' + fmtMoney(tongCong) + '</span></div>' +
          '</div>' +
        '</div>';
    });
  }

  function printSinglePageDocument(docTitle, bodyHtml) {
    var w = window.open("", "_blank", "width=960,height=720");
    if (!w) {
      window.print();
      return;
    }
    w.document.open();
    w.document.write(
      '<!doctype html><html lang="vi"><head><meta charset="utf-8"><title>' + esc(docTitle) + '</title>' +
      '<style>' +
        '@page{size:A4;margin:10mm;}' +
        'body{font-family:Inter,Arial,sans-serif;color:#1a2433;margin:0;padding:12px;font-size:12.5px;}' +
        'h1,h2,h3{margin:0 0 8px;color:#16283a;}' +
        '.doc-top{display:flex;justify-content:space-between;align-items:flex-end;border-bottom:2px solid #16283a;padding-bottom:8px;margin-bottom:12px;}' +
        '.pnp-info-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:12px;}' +
        '.pnp-info-item{border:1px solid #d8d2c5;border-radius:6px;padding:7px 10px;background:#faf8f3;}' +
        '.pnp-info-item small{display:block;font-size:10px;color:#667085;text-transform:uppercase;margin-bottom:2px;}' +
        '.pnp-section-title{font-weight:700;font-size:13px;color:#16283a;margin:12px 0 6px;}' +
        '.sky-table{width:100%;border-collapse:collapse;font-size:12px;margin-bottom:10px;}' +
        '.sky-table th,.sky-table td{border:1px solid #dfd9cc;padding:6px 8px;text-align:left;}' +
        '.sky-table th{background:#f4f1ea;font-size:11px;text-transform:uppercase;}' +
        '.pnp-summary-box{display:flex;justify-content:space-between;align-items:center;border:1px solid #d8d2c5;border-radius:6px;padding:10px 12px;margin-top:8px;background:#faf8f3;}' +
        '.st-badge,.bd{display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;background:#eef4fb;color:#1b4d7e;}' +
        'button,[data-edit-guest]{display:none !important;}' +
      '</style></head><body>' +
      '<div class="doc-top"><div><h2>' + esc(docTitle) + '</h2><div style="font-size:11.5px;color:#667085">SKY HOTEL · 123 Thành Thái, Quận 10, TP. Hồ Chí Minh</div></div><div style="font-size:11.5px;color:#667085">Ngày in: ' + esc(fmtDateTime(new Date().toISOString())) + '</div></div>' +
      bodyHtml +
      '<script>window.onload=function(){window.focus();window.print();};</script></body></html>'
    );
    w.document.close();
  }

  var btnPrintPnpSlip = $("btnPrintPnpSlip");
  if (btnPrintPnpSlip) {
    btnPrintPnpSlip.addEventListener("click", function () {
      var body = $("pnpModalBody");
      var titleEl = $("pnpModalTitle");
      if (!body) return;
      printSinglePageDocument((titleEl && titleEl.textContent) || "Chi Tiết Phiếu Nhận Phòng", body.innerHTML);
    });
  }

  var btnPrintInvoice = $("btnPrintInvoice");
  if (btnPrintInvoice) {
    btnPrintInvoice.addEventListener("click", function () {
      var area = $("printableInvoiceArea") || $("invoiceModalBody");
      if (!area) return;
      printSinglePageDocument("Hóa Đơn Điện Tử GTGT - SKY HOTEL", area.innerHTML);
    });
  }

  /* ================== VIETQR PAYMENT MODAL (#vietQrPayModal) ================== */
  var historyQrWatcher = null;

  function stopHistoryQrWatcher() {
    if (historyQrWatcher) {
      historyQrWatcher.stop();
      historyQrWatcher = null;
    }
  }

  function completeHistoryQrSuccess() {
    stopHistoryQrWatcher();
    showAlert("qrPayAlert", "✓ Thanh toán thành công! Phiếu đặt phòng đã chuyển sang Đã đặt phòng.", false);
    if ($("qrPayAutoStatus")) $("qrPayAutoStatus").textContent = "✅ Đã nhận tiền thanh toán thành công!";
    setTimeout(function () {
      if ($("vietQrPayModal")) $("vietQrPayModal").style.display = "none";
      loadBookings();
    }, 900);
  }

  function openVietQrModal(maPhieu) {
    var b = allBookings.find(function (x) { return x.MaPhieu === maPhieu; });
    if (!b) return;
    stopHistoryQrWatcher();
    currentPayItem = b;

    var amount = Math.round(Number(b.TongTien || 0));
    var maHD = b.MaHD || b.MaPhieu || "";
    var memo = "THANHTOAN " + maHD;
    var accountNo = "0589822346";
    var accountName = "NGUYEN HOANG ANH";
    var qrUrl = "https://img.vietqr.io/image/MB-" + accountNo + "-qr_only.png?amount=" + amount +
      "&addInfo=" + encodeURIComponent(memo) +
      "&accountName=" + encodeURIComponent(accountName);

    if ($("qrPaySub")) {
      $("qrPaySub").textContent = "Phiếu đặt phòng: #" + (b.MaPhieu || "---") + " · Hóa đơn: #" + (b.MaHD || "---");
    }
    if (window.SkyMBBankQR && $("qrPayImg")) {
      window.SkyMBBankQR.applyQrImage($("qrPayImg"), {
        amount: amount,
        orderCode: maHD,
        memo: memo,
        accountNo: accountNo,
        accountName: accountName,
        fallbackUrl: qrUrl
      });
    } else if ($("qrPayImg")) {
      $("qrPayImg").src = qrUrl;
    }
    if ($("qrPayAmount")) $("qrPayAmount").textContent = fmtMoney(amount);
    if ($("qrPayMemo")) $("qrPayMemo").textContent = memo;
    if ($("qrPayAlert")) $("qrPayAlert").style.display = "none";
    if ($("qrPayAutoStatus")) $("qrPayAutoStatus").textContent = "🔄 Đang tự động kiểm tra giao dịch mỗi 2 giây...";

    var simLink = $("btnOpenHistoryMbSim");
    if (simLink && window.SkyMBBankQR) {
      var refreshHistorySimLink = function () {
        simLink.href = window.SkyMBBankQR.buildSimulatorUrl({
          amount: amount,
          orderCode: maHD,
          memo: memo,
          accountNo: accountNo,
          accountName: accountName,
          bank: "MB"
        });
      };
      refreshHistorySimLink();
      if ($("btnConfigHistoryMbSim")) {
        $("btnConfigHistoryMbSim").onclick = function () {
          window.SkyMBBankQR.promptSimulatorBaseUrl();
          refreshHistorySimLink();
          if (window.SkyMBBankQR && $("qrPayImg")) {
            window.SkyMBBankQR.applyQrImage($("qrPayImg"), {
              amount: amount,
              orderCode: maHD,
              memo: memo,
              accountNo: accountNo,
              accountName: accountName,
              fallbackUrl: qrUrl
            });
          }
        };
      }
    }

    if ($("vietQrPayModal")) $("vietQrPayModal").style.display = "flex";

    if (window.SkyMBBankQR && b.MaHD) {
      historyQrWatcher = window.SkyMBBankQR.startPaymentWatcher({
        apiBase: API_BASE,
        maHD: b.MaHD,
        intervalMs: 2000,
        onPaid: completeHistoryQrSuccess,
        onExpired: function () {
          stopHistoryQrWatcher();
          if ($("qrPayAutoStatus")) $("qrPayAutoStatus").textContent = "✕ Hóa đơn đã hết hạn thanh toán.";
          showAlert("qrPayAlert", "Hóa đơn đã quá hạn giữ phòng hoặc bị hủy.", true);
          loadBookings();
        }
      });
    }
  }

  var btnConfirmQrPaid = $("btnConfirmQrPaid") || $("btnConfirmQrPay");
  if (btnConfirmQrPaid) {
    btnConfirmQrPaid.addEventListener("click", function () {
      if (!currentPayItem) return;
      var maHD = currentPayItem.MaHD;
      var amount = Math.round(Number(currentPayItem.TongTien || 0));

      btnConfirmQrPaid.disabled = true;
      btnConfirmQrPaid.textContent = "⏳ Đang xác thực giao dịch...";

      apiFetch("/thanhtoan/mophong", {
        method: "POST",
        body: JSON.stringify({
          MaHD: maHD,
          SoTien: amount,
          NoiDung: "THANHTOAN " + maHD
        })
      }).then(function (res) {
        btnConfirmQrPaid.disabled = false;
        btnConfirmQrPaid.textContent = "✅ Tôi đã chuyển khoản thành công";
        if (res && res.success) {
          completeHistoryQrSuccess();
        } else {
          showAlert("qrPayAlert", (res && res.message) || "Xác nhận thanh toán chưa thành công.", true);
        }
      }).catch(function () {
        btnConfirmQrPaid.disabled = false;
        btnConfirmQrPaid.textContent = "✅ Tôi đã chuyển khoản thành công";
        showAlert("qrPayAlert", "Lỗi kết nối máy chủ khi xác nhận thanh toán.", true);
      });
    });
  }

  /* ================== KHÁCH HÀNG TỰ HỦY ĐẶT PHÒNG & HOÀN TIỀN ================== */
  var currentCancelState = { maPhieu: "", maPhong: "", preview: null };

  function renderCancelPreviewBox(d) {
    var box = $("cancelCalcResult");
    var bankBox = $("cancelBankFields");
    var btnExec = $("btnConfirmCustomerCancel");
    if (!box) return;

    currentCancelState.preview = d;
    var rate = Number(d.tiLeHoan || 0);
    var refundAmt = Number(d.soTienHoan || 0);
    var feeAmt = Number(d.phiHuy || 0);
    var pillCls = rate >= 100 ? "rate-100" : (rate >= 50 ? "rate-50" : "rate-0");
    var diffDays = Number(d.diffDays || 0);
    var timeNote = diffDays > 0
      ? ("Còn " + diffDays + " ngày trước giờ nhận phòng")
      : "Đã đến hoặc qua giờ nhận phòng dự kiến";

    var roomsNote = (d.danhSachPhongHuy || []).map(function (r) {
      return esc(r.maPhong + (r.tenLoaiPhong ? " (" + r.tenLoaiPhong + ")" : ""));
    }).join(", ");

    box.innerHTML =
      '<div class="cancel-summary-row"><span>Phòng áp dụng:</span><strong>' + (roomsNote || esc(d.maPhieu)) + '</strong></div>' +
      '<div class="cancel-summary-row"><span>Thời gian còn lại:</span><strong>' + esc(timeNote) + '</strong></div>' +
      '<div class="cancel-summary-row"><span>Số tiền áp dụng hoàn hủy:</span><strong>' + (d.daThanhToan ? fmtMoney(d.tongTienHuy || d.soTienCoSo || 0) : '0 đ (Chưa thanh toán)') + '</strong></div>' +
      '<div class="cancel-summary-row"><span>Tỷ lệ hoàn / Phí hủy:</span><strong>Hoàn ' + rate + '% · Phí hủy ' + Number(d.tyLePhiHuy != null ? d.tyLePhiHuy : (d.tiLePhiHuy || 0)) + '%</strong></div>' +
      '<div class="cancel-summary-row"><span>Phí hủy khấu trừ:</span><span class="cancel-fee-amt">' + fmtMoney(feeAmt) + '</span></div>' +
      '<div class="cancel-summary-row total"><span>Số tiền được hoàn dự kiến:</span><span class="cancel-refund-amt">' + fmtMoney(refundAmt) + '</span></div>' +
      '<div class="cancel-policy-pill ' + pillCls + '">📌 ' + esc(d.moTaChinhSach || '') + '</div>';

    if (bankBox) {
      bankBox.style.display = refundAmt > 0 ? "block" : "none";
    }
    if (btnExec) {
      btnExec.disabled = false;
    }
  }

  function loadCancelPreview(maPhieu, maPhong) {
    var box = $("cancelCalcResult");
    var bankBox = $("cancelBankFields");
    var btnExec = $("btnConfirmCustomerCancel");
    var alertEl = $("cancelModalAlert");
    if (alertEl) alertEl.style.display = "none";
    if (bankBox) bankBox.style.display = "none";
    if (btnExec) btnExec.disabled = true;
    if (box) box.innerHTML = '<div class="empty">⏳ Đang tính toán số tiền hoàn theo chính sách...</div>';

    currentCancelState.maPhong = maPhong || "";

    if (!window.HotelAPI || !window.HotelAPI.calcCustomerRefund) {
      if (box) box.innerHTML = '<div class="empty">⚠️ Không tải được mô-đun kết nối máy chủ.</div>';
      return;
    }

    window.HotelAPI.calcCustomerRefund(maPhieu, maPhong, getMaTK())
      .then(function (res) {
        renderCancelPreviewBox((res && res.data) || {});
      })
      .catch(function (err) {
        if (box) {
          box.innerHTML = '<div class="empty" style="color:#b91c1c">⚠️ ' + esc((err && err.message) || "Không tính được số tiền hoàn cho đơn này.") + '</div>';
        }
      });
  }

  function openCustomerCancelModal(maPhieu) {
    var modal = $("cancelBookingModal");
    if (!modal || !maPhieu) return;

    currentCancelState = { maPhieu: maPhieu, maPhong: "", preview: null };
    if ($("cancelBookingSub")) $("cancelBookingSub").textContent = "Phiếu đặt phòng: #" + maPhieu;
    if ($("cancelModalAlert")) $("cancelModalAlert").style.display = "none";
    if ($("cancelBankSelect")) $("cancelBankSelect").value = "";
    if ($("cancelBankStk")) $("cancelBankStk").value = "";
    if ($("cancelBankOwner")) $("cancelBankOwner").value = "";
    if ($("cancelReasonText")) $("cancelReasonText").value = "Khách hàng chủ động hủy đặt phòng trực tuyến";

    var policyBox = $("cancelPolicyTableBox");
    if (policyBox && window.HotelAPI && window.HotelAPI.policy) {
      policyBox.innerHTML = window.HotelAPI.policy.renderTableHtml();
    }

    var scopeWrap = $("cancelScopeWrap");
    var roomSelect = $("cancelRoomSelect");
    if (scopeWrap) scopeWrap.style.display = "none";
    if (roomSelect) roomSelect.innerHTML = '<option value="">Toàn bộ phiếu đặt phòng</option>';

    modal.style.display = "flex";
    loadCancelPreview(maPhieu, "");

    fetchSlipDetail(maPhieu).then(function (res) {
      if (!res || !res.success || !res.data || !roomSelect || !scopeWrap) return;
      var rooms = res.data.DanhSachPhong || res.data.danhSachPhong || [];
      var cancellable = rooms.filter(function (r) {
        return r.TrangThai === "DaDatPhong" || r.TrangThai === "ChoThanhToan";
      });
      if (cancellable.length > 1) {
        var opts = '<option value="">Hủy toàn bộ phiếu (' + cancellable.length + ' phòng)</option>';
        cancellable.forEach(function (r) {
          opts += '<option value="' + esc(r.MaPhong) + '">Chỉ hủy phòng ' + esc(r.MaPhong) +
            (r.TenLoaiPhong ? ' — ' + esc(r.TenLoaiPhong) : '') + '</option>';
        });
        roomSelect.innerHTML = opts;
        scopeWrap.style.display = "block";
      }
    }).catch(function () {});
  }

  if ($("cancelRoomSelect")) {
    $("cancelRoomSelect").addEventListener("change", function () {
      if (!currentCancelState.maPhieu) return;
      loadCancelPreview(currentCancelState.maPhieu, this.value || "");
    });
  }

  if ($("btnConfirmCustomerCancel")) {
    $("btnConfirmCustomerCancel").addEventListener("click", function () {
      var preview = currentCancelState.preview;
      if (!currentCancelState.maPhieu || !preview) {
        showAlert("cancelModalAlert", "Không có dữ liệu tính tiền hoàn hợp lệ. Vui lòng đóng hộp thoại và thử lại.", true);
        return;
      }

      var refundAmt = Number(preview.soTienHoan || 0);
      var bank = ($("cancelBankSelect") ? $("cancelBankSelect").value : "").trim();
      var stk = ($("cancelBankStk") ? $("cancelBankStk").value : "").trim();
      var owner = ($("cancelBankOwner") ? $("cancelBankOwner").value : "").trim().toUpperCase();
      var lyDo = ($("cancelReasonText") ? $("cancelReasonText").value : "").trim() || "Khách hàng chủ động hủy đặt phòng trực tuyến";

      if (refundAmt > 0 && (!bank || !stk || !owner)) {
        showAlert("cancelModalAlert", "Vui lòng nhập đầy đủ Ngân hàng, Số tài khoản và Tên chủ tài khoản để nhận tiền hoàn.", true);
        var bankBox = $("cancelBankFields");
        if (bankBox && typeof bankBox.scrollIntoView === "function") {
          bankBox.scrollIntoView({ behavior: "smooth", block: "center" });
        }
        var firstMissing = !bank ? $("cancelBankSelect") : (!stk ? $("cancelBankStk") : $("cancelBankOwner"));
        if (firstMissing && typeof firstMissing.focus === "function") {
          firstMissing.focus();
        }
        return;
      }

      var btn = $("btnConfirmCustomerCancel");
      var origText = "🚫 Xác nhận hủy đặt phòng";
      btn.disabled = true;
      btn.textContent = "⏳ Đang xử lý hủy...";

      window.HotelAPI.cancelCustomerBooking({
        maPhieu: currentCancelState.maPhieu,
        maPhong: currentCancelState.maPhong || "",
        lyDoHuy: lyDo,
        nganHangHoan: bank,
        stkHoan: stk,
        tenChuTKHoan: owner,
        maTK: getMaTK()
      }).then(function (res) {
        btn.disabled = false;
        btn.textContent = origText;
        if ($("cancelBookingModal")) $("cancelBookingModal").style.display = "none";
        showAlert("booking-alert", (res && res.message) || "Đã hủy đặt phòng thành công.", false);
        loadBookings();
      }).catch(function (err) {
        btn.disabled = false;
        btn.textContent = origText;
        showAlert("cancelModalAlert", (err && err.message) || "Không thể hủy đặt phòng lúc này.", true);
      });
    });
  }

  /* ================== REVIEWS TAB & STAR MODAL ================== */
  function loadReviewableRooms() {
    var container = $("reviews-list");
    if (!container) return;

    var doneSlips = allBookings.filter(function (b) { return b.TrangThai === "DaTraPhong"; });
    if (!doneSlips.length) {
      container.innerHTML =
        '<div class="empty">' +
          '<div style="font-size:28px;margin-bottom:8px">⭐</div>' +
          '<b>Chưa có kỳ nghỉ hoàn tất (Đã trả phòng) để đánh giá</b>' +
          '<p style="margin:6px 0 0;font-size:13px">Sau khi quý khách Check-out hoàn tất kỳ nghỉ, danh sách phòng sẽ hiển thị tại đây để quý khách gửi đánh giá 1–5 sao.</p>' +
        '</div>';
      return;
    }

    container.innerHTML = '<div class="empty">⏳ Đang tải danh sách phòng đủ điều kiện đánh giá...</div>';

    Promise.all(doneSlips.map(function (s) {
      return fetchSlipDetail(s.MaPhieu).catch(function () { return null; });
    })).then(function (details) {
      var list = [];
      details.forEach(function (res, idx) {
        var slip = doneSlips[idx];
        var rooms = (res && res.success && res.data && (res.data.DanhSachPhong || res.data.danhSachPhong)) || [];
        if (rooms.length) {
          rooms.forEach(function (r) {
            var dg = r.DanhGia || {};
            var daDanhGia = r.DaDanhGia === true || Number(r.DaDanhGia || 0) === 1;
            list.push({
              MaPhieu: slip.MaPhieu,
              MaPhong: r.MaPhong,
              TenLoaiPhong: r.TenLoaiPhong || "Phòng nghỉ SKY HOTEL",
              NgayNhan: r.NgayNhan || slip.NgayNhan,
              NgayTra: r.NgayTra || slip.NgayTra,
              DaDanhGia: daDanhGia,
              SoSao: Number(dg.SoSao || r.SoSao || 5),
              NoiDungDanhGia: dg.NoiDung || r.NoiDungDanhGia || "",
              NgayDanhGia: dg.NgayDanhGia || r.NgayDanhGia || ""
            });
          });
        }
      });
      reviewRoomItems = list;
      renderReviewItems();
    });
  }

  function renderReviewItems() {
    var container = $("reviews-list");
    if (!container) return;

    var filtered = reviewRoomItems.filter(function (it) {
      if (currentReviewFilter === "pending") return !it.DaDanhGia;
      if (currentReviewFilter === "reviewed") return it.DaDanhGia;
      return true;
    });

    if (!filtered.length) {
      container.innerHTML = '<div class="empty">Không có phòng nào trong bộ lọc đánh giá này.</div>';
      return;
    }

    container.innerHTML = filtered.map(function (it) {
      var starsStr = "★".repeat(Math.max(1, Math.min(5, it.SoSao || 5)));
      return (
        '<div class="review-card">' +
          '<div class="rev-top">' +
            '<span class="rev-room">🏨 Phòng ' + esc(it.MaPhong) + ' · ' + esc(it.TenLoaiPhong) + ' (Phiếu #' + esc(it.MaPhieu) + ')</span>' +
            (it.DaDanhGia
              ? '<div style="display:flex;align-items:center;gap:8px"><span class="rev-stars" title="' + esc(it.SoSao + ' sao') + '">' + starsStr + '</span><span class="bd paid">✓ Đã đánh giá</span></div>'
              : '<button type="button" class="btn-act review" data-open-review-modal="1" data-phieu="' + esc(it.MaPhieu) + '" data-phong="' + esc(it.MaPhong) + '" data-loai="' + esc(it.TenLoaiPhong) + '">✍️ Viết đánh giá ngay</button>') +
          '</div>' +
          '<div class="rev-date">📅 Kỳ nghỉ: ' + esc(fmtDateTime(it.NgayNhan)) + ' → ' + esc(fmtDateTime(it.NgayTra)) + '</div>' +
          (it.DaDanhGia && it.NoiDungDanhGia
            ? '<div class="rev-content">' + esc(it.NoiDungDanhGia) + '</div>'
            : '<div class="rev-content" style="color:var(--muted)">Hãy chia sẻ cảm nhận của quý khách về chất lượng phòng và dịch vụ tại SKY HOTEL.</div>') +
        '</div>'
      );
    }).join("");
  }

  function updateReviewCharCount() {
    var textarea = $("reviewNoiDung");
    var counter = $("reviewCharCount");
    if (!counter) return;
    var len = textarea ? (textarea.value || "").length : 0;
    counter.textContent = len + " / 1000 ký tự";
  }

  function setStarSelection(stars) {
    var val = Math.max(1, Math.min(5, Number(stars) || 5));
    selectedStars = val;
    if ($("starRatingDesc")) $("starRatingDesc").textContent = STAR_TEXTS[selectedStars] || "";
    if ($("starRatingText")) $("starRatingText").textContent = STAR_TEXTS[selectedStars] || "";
    var btns = document.querySelectorAll("#starRatingBox .star-item, #starRatingBox .star-btn");
    btns.forEach(function (b) {
      var v = Number(b.getAttribute("data-val"));
      if (v <= selectedStars) b.classList.add("active");
      else b.classList.remove("active");
    });
  }

  function applyReviewModalRoomState(item) {
    if (!item) return;
    currentReviewMaPhieu = item.MaPhieu || currentReviewMaPhieu;
    currentReviewMaPhong = item.MaPhong || "";
    reviewReadOnlyMode = !!item.DaDanhGia;

    if ($("rmPhieuCode")) $("rmPhieuCode").textContent = currentReviewMaPhieu || "---";
    if ($("reviewMaPhong")) $("reviewMaPhong").value = currentReviewMaPhong;
    if ($("reviewModalTitle")) {
      $("reviewModalTitle").textContent = reviewReadOnlyMode
        ? ("⭐ Đánh Giá Phòng " + currentReviewMaPhong)
        : ("⭐ Đánh Giá Phòng " + currentReviewMaPhong + (item.TenLoaiPhong ? " — " + item.TenLoaiPhong : ""));
    }

    var starBox = $("starRatingBox");
    var textarea = $("reviewNoiDung");
    var viewBox = $("reviewViewContent");
    var charCount = $("reviewCharCount");
    var reqMark = $("reviewRequiredMark");
    var dateInfo = $("reviewDateInfo");
    var submitBtn = $("btnSubmitReview");

    if (reviewReadOnlyMode) {
      setStarSelection(item.SoSao || 5);
      if (starBox) starBox.classList.add("readonly");
      if (textarea) textarea.style.display = "none";
      if (charCount) charCount.style.display = "none";
      if (reqMark) reqMark.style.display = "none";
      if (viewBox) {
        viewBox.textContent = item.NoiDungDanhGia || "(Không có nhận xét)";
        viewBox.style.display = "block";
      }
      if (dateInfo) {
        dateInfo.textContent = item.NgayDanhGia ? ("Đã gửi đánh giá vào: " + fmtDateTime(item.NgayDanhGia)) : "Phòng này đã được đánh giá.";
        dateInfo.style.display = "block";
      }
      if (submitBtn) submitBtn.style.display = "none";
    } else {
      setStarSelection(5);
      if (starBox) starBox.classList.remove("readonly");
      if (viewBox) viewBox.style.display = "none";
      if (dateInfo) dateInfo.style.display = "none";
      if (reqMark) reqMark.style.display = "inline";
      if (textarea) {
        textarea.style.display = "block";
        textarea.value = "";
      }
      if (charCount) charCount.style.display = "block";
      updateReviewCharCount();
      if (submitBtn) {
        submitBtn.style.display = "inline-flex";
        submitBtn.disabled = false;
        submitBtn.textContent = "⭐ Gửi đánh giá";
      }
    }
  }

  function openReviewModal(maPhieu, maPhong, tenLoai) {
    currentReviewMaPhieu = maPhieu || "";
    currentReviewMaPhong = maPhong || "";

    if ($("reviewAlertBox")) $("reviewAlertBox").style.display = "none";
    if ($("rmPhieuCode")) $("rmPhieuCode").textContent = currentReviewMaPhieu || "---";

    var slipRooms = reviewRoomItems.filter(function (it) { return it.MaPhieu === currentReviewMaPhieu; });
    var selectWrap = $("modalRoomSelectContainer");
    var selectEl = $("reviewRoomSelect");

    if (slipRooms.length > 1 && selectWrap && selectEl) {
      selectEl.innerHTML = slipRooms.map(function (it) {
        var lbl = "Phòng " + it.MaPhong + " — " + (it.TenLoaiPhong || "SKY HOTEL") + (it.DaDanhGia ? " (Đã đánh giá)" : " (Chờ đánh giá)");
        return '<option value="' + esc(it.MaPhong) + '">' + esc(lbl) + '</option>';
      }).join("");
      if (currentReviewMaPhong) selectEl.value = currentReviewMaPhong;
      selectWrap.style.display = "block";
    } else if (selectWrap) {
      selectWrap.style.display = "none";
    }

    var activeItem = slipRooms.find(function (it) { return it.MaPhong === currentReviewMaPhong; }) || {
      MaPhieu: currentReviewMaPhieu,
      MaPhong: currentReviewMaPhong,
      TenLoaiPhong: tenLoai || "SKY HOTEL",
      DaDanhGia: false
    };
    applyReviewModalRoomState(activeItem);

    if ($("reviewModal")) $("reviewModal").style.display = "flex";
  }

  function submitReview() {
    if (reviewReadOnlyMode) return;
    var maPhieu = currentReviewMaPhieu;
    var maPhong = ($("reviewMaPhong") && $("reviewMaPhong").value) || currentReviewMaPhong;
    var soSao = Number(selectedStars || 5);
    var noiDung = (($("reviewNoiDung") && $("reviewNoiDung").value) || "").trim();

    if (!maPhieu || !maPhong) {
      showAlert("reviewAlertBox", "Thiếu thông tin phiếu đặt phòng hoặc mã phòng cần đánh giá.", true);
      return;
    }
    if (!noiDung) {
      showAlert("reviewAlertBox", "Vui lòng nhập nhận xét chi tiết trước khi gửi đánh giá.", true);
      if ($("reviewNoiDung")) $("reviewNoiDung").focus();
      return;
    }

    var btn = $("btnSubmitReview");
    if (btn) { btn.disabled = true; btn.textContent = "⏳ Đang gửi..."; }

    apiFetch("/danhgia/themdanhgia", {
      method: "POST",
      body: JSON.stringify({
        maTK: getMaTK(),
        maKH: getMaKH(),
        maPhieu: maPhieu,
        maPhong: maPhong,
        soSao: soSao,
        noiDung: noiDung
      })
    }).then(function (res) {
      if (btn) { btn.disabled = false; btn.textContent = "⭐ Gửi đánh giá"; }
      if (res && res.success) {
        if ($("reviewModal")) $("reviewModal").style.display = "none";
        showAlert("booking-alert", "✓ Cảm ơn quý khách! Đánh giá phòng " + maPhong + " đã được ghi nhận.", false);
        loadBookings();
        loadReviewableRooms();
      } else {
        showAlert("reviewAlertBox", (res && (res.message || res.error)) || "Không thể gửi đánh giá.", true);
      }
    }).catch(function () {
      if (btn) { btn.disabled = false; btn.textContent = "⭐ Gửi đánh giá"; }
      showAlert("reviewAlertBox", "Lỗi kết nối máy chủ khi gửi đánh giá.", true);
    });
  }

  var reviewForm = $("reviewForm");
  if (reviewForm) {
    reviewForm.addEventListener("submit", function (e) {
      e.preventDefault();
      submitReview();
    });
  }
  if ($("btnSubmitReview")) {
    $("btnSubmitReview").addEventListener("click", submitReview);
  }
  if ($("reviewNoiDung")) {
    $("reviewNoiDung").addEventListener("input", updateReviewCharCount);
  }
  if ($("reviewRoomSelect")) {
    $("reviewRoomSelect").addEventListener("change", function () {
      var chosen = this.value;
      var found = reviewRoomItems.find(function (it) {
        return it.MaPhieu === currentReviewMaPhieu && it.MaPhong === chosen;
      });
      if (found) applyReviewModalRoomState(found);
    });
  }

  /* ================== GLOBAL CLICK & EVENT LISTENERS ================== */
  document.addEventListener("click", function (e) {
    // 1. Sidebar tab switch
    var tabLink = e.target.closest("aside [data-tab]");
    if (tabLink) {
      e.preventDefault();
      switchTab(tabLink.getAttribute("data-tab"));
      return;
    }

    // 2. Booking filter tab
    var filterBtn = e.target.closest("#tabs .tab[data-k]");
    if (filterBtn) {
      curFilter = filterBtn.getAttribute("data-k");
      drawBookings();
      return;
    }

    // 3. Review filter tab
    var revFilterBtn = e.target.closest("#review-filter-tabs .tab[data-rev-filter]");
    if (revFilterBtn) {
      currentReviewFilter = revFilterBtn.getAttribute("data-rev-filter");
      document.querySelectorAll("#review-filter-tabs .tab").forEach(function (b) {
        b.classList.toggle("on", b === revFilterBtn);
      });
      renderReviewItems();
      return;
    }

    // 4. Close modal
    var closeBtn = e.target.closest("[data-close-modal]");
    if (closeBtn) {
      var mId = closeBtn.getAttribute("data-close-modal");
      if (mId === "vietQrPayModal") stopHistoryQrWatcher();
      if ($(mId)) $(mId).style.display = "none";
      return;
    }
    if (e.target.classList && (e.target.classList.contains("sky-modal-overlay") || e.target.classList.contains("sky-modal-backdrop"))) {
      if (e.target.id === "vietQrPayModal") stopHistoryQrWatcher();
      e.target.style.display = "none";
      return;
    }

    // 5. Open Slip Detail
    var btnDetail = e.target.closest("[data-detail-phieu]");
    if (btnDetail) {
      openSlipDetailModal(btnDetail.getAttribute("data-detail-phieu"));
      return;
    }

    // 6. Open Edit Guest (chỉ Lễ tân)
    var btnEditGuest = e.target.closest("[data-edit-guest]");
    if (btnEditGuest) {
      var role = getUserRole();
      if (role !== "letan") {
        showAlert("prof-alert", "Chỉ Lễ tân mới có quyền cập nhật người lưu trú trên phiếu.", true);
        return;
      }
      openEditGuestModal(btnEditGuest.getAttribute("data-edit-guest"));
      return;
    }

    // 7. Open Invoice
    var btnInv = e.target.closest("[data-invoice-phieu]");
    if (btnInv) {
      openInvoiceModal(btnInv.getAttribute("data-invoice-phieu"), btnInv.getAttribute("data-invoice-hd"));
      return;
    }

    // 8. Open VietQR Payment
    var btnPay = e.target.closest("[data-pay-phieu]");
    if (btnPay) {
      openVietQrModal(btnPay.getAttribute("data-pay-phieu"));
      return;
    }

    // 8b. Open Customer Self-Cancel Modal
    var btnCancelBk = e.target.closest("[data-cancel-phieu]");
    if (btnCancelBk) {
      openCustomerCancelModal(btnCancelBk.getAttribute("data-cancel-phieu"));
      return;
    }

    // 8c. Preset cancel reason buttons
    var btnPresetReason = e.target.closest("[data-cancel-reason-preset]");
    if (btnPresetReason) {
      var reasonInput = $("cancelReasonText");
      if (reasonInput) reasonInput.value = btnPresetReason.getAttribute("data-cancel-reason-preset") || "";
      return;
    }

    // 9. Switch to Review tab or open Review modal
    var btnRev = e.target.closest("[data-review-phieu]");
    if (btnRev) {
      switchTab("reviews");
      return;
    }
    var btnOpenRevModal = e.target.closest("[data-open-review-modal]");
    if (btnOpenRevModal) {
      openReviewModal(
        btnOpenRevModal.getAttribute("data-phieu"),
        btnOpenRevModal.getAttribute("data-phong"),
        btnOpenRevModal.getAttribute("data-loai")
      );
      return;
    }

    // 10. Star picker
    var starBtn = e.target.closest("#starRatingBox .star-item, #starRatingBox .star-btn");
    if (starBtn && !reviewReadOnlyMode) {
      setStarSelection(starBtn.getAttribute("data-val"));
      return;
    }

    // 11. Go to booking page (chọn phòng mới, xóa trạng thái đơn cũ)
    var gotoBook = e.target.closest("[data-goto-booking]");
    if (gotoBook) {
      try {
        sessionStorage.removeItem("sky_booking_flow_state");
        localStorage.removeItem("sky_sel");
      } catch (err) {}
      if (parent !== window) {
        parent.postMessage({ skyGo: "rooms" }, (window.location.protocol === "file:") ? "*" : window.location.origin);
      } else {
        window.location.href = "chon-phong.html";
      }
      return;
    }
  });

  if ($("btn-refresh-bookings")) {
    $("btn-refresh-bookings").addEventListener("click", loadBookings);
  }
  if ($("btn-new-booking")) {
    $("btn-new-booking").addEventListener("click", function () {
      try {
        sessionStorage.removeItem("sky_booking_flow_state");
        localStorage.removeItem("sky_sel");
      } catch (err) {}
      if (parent !== window) {
        parent.postMessage({ skyGo: "rooms" }, (window.location.protocol === "file:") ? "*" : window.location.origin);
      } else {
        window.location.href = "chon-phong.html";
      }
    });
  }
  if ($("btn-refresh-reviews")) {
    $("btn-refresh-reviews").addEventListener("click", loadReviewableRooms);
  }

  window.addEventListener("hashchange", checkHash);

  window.addEventListener("message", function (e) {
    var d = e.data || {};
    if (d.skyHash) switchTab(d.skyHash);
    if (d.skyTab) switchTab(d.skyTab);
    if (d.skyProfileUpdated && d.user) {
      populateProfileUI(d.user);
    }
    if ("skyInit" in d) {
      var initUser = d.skyInit.user || readStoredSessionUser();
      if (isAdminAccount(initUser)) {
        redirectAdminAwayFromHistory();
        return;
      }
      if (initUser) {
        populateProfileUI(initUser);
        loadBackendProfile();
      }
      if (d.skyInit.tab) {
        switchTab(d.skyInit.tab);
      } else {
        checkHash();
      }
      loadBookings();
    }
  });

  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") {
      stopHistoryQrWatcher();
      document.querySelectorAll(".sky-modal-backdrop, .sky-modal-overlay").forEach(function (m) {
        if (m.style.display && m.style.display !== "none") m.style.display = "none";
      });
    }
  });

  // Khởi tạo ngay khi trang vừa nạp
  var initialUser = readStoredSessionUser();
  if (isAdminAccount(initialUser)) {
    redirectAdminAwayFromHistory();
    return;
  }
  if (initialUser) {
    populateProfileUI(initialUser);
    loadBackendProfile();
  }
  checkHash();
  renderSupportCancelPolicy();
  loadBookings();
})();
