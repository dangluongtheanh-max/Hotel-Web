/* ==========================================================================
   SkyHotel — Trang Đặt phòng & Thanh toán (dat-phong.js)
   Đồng bộ 100% nghiệp vụ đặt phòng từ legacy/datphong.html:
   1. Đọc hạng phòng & số lượng đã chọn từ trang Chọn phòng (sky_sel)
   2. Tra cứu thông tin người đặt theo SĐT (/khachhang/timtheosdt) & đồng bộ vào phòng lưu trú
   3. Khai báo đầy đủ thông tin từng người lưu trú (Họ tên, Ngày sinh, Quốc tịch, Loại giấy tờ, Số giấy tờ, Ghi chú)
      kèm tính tuổi tự động theo ngày nhận phòng (Trẻ em ≤ 11t, Người lớn > 11t) & Điền nhanh mẫu test
   4. Chọn Mã khuyến mãi qua Modal Voucher (/khuyenmai/danhsach) & tính chi phí dự kiến (/datphong/tinhchiphi)
   5. Tạo phiếu đặt phòng thật (/datphong/datphong), thanh toán VietQR MBBank (/thanhtoan/mophong) hoặc Tiền mặt tại quầy
   6. Hiển thị màn hình Đặt phòng thành công, phòng vật lý được phân bổ, mã NLT và xem chi tiết phiếu (/datphong/xemctphieu)
   ========================================================================== */
(function () {
  "use strict";

  var API = window.SkyAPI;
  var U = API.util;
  var $ = function (id) { return document.getElementById(id); };

  var API_BASE = (window.HotelAPI && window.HotelAPI.base) ? window.HotelAPI.base : "/Backend/public/index.php";

  function unwrapRes(j) {
    if (window.HotelAPI && typeof window.HotelAPI.unwrap === "function") {
      return window.HotelAPI.unwrap(j);
    }
    var d = j && j.data !== undefined ? j.data : j;
    if (d && typeof d === "object" && d.data !== undefined) d = d.data;
    return { ok: !!(j && j.success !== false), data: d, message: (j && (j.message || j.error)) || "" };
  }

  function esc(s) {
    return String(s || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function money(n) {
    return Number(Math.round(Number(n) || 0)).toLocaleString("vi-VN") + " đ";
  }

  function formatDateOnly(str) {
    if (!str) return "-";
    var s = String(str);
    var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(s);
    if (m) return m[3] + "/" + m[2] + "/" + m[1];
    var d = new Date(s.indexOf(" ") > -1 && s.indexOf("T") === -1 ? s.replace(" ", "T") : s);
    if (isNaN(d.getTime())) return s;
    return String(d.getDate()).padStart(2, "0") + "/" + String(d.getMonth() + 1).padStart(2, "0") + "/" + d.getFullYear();
  }

  function formatDateTime(str) {
    if (!str) return "-";
    var s = String(str);
    var d = new Date(s.indexOf(" ") > -1 && s.indexOf("T") === -1 ? s.replace(" ", "T") : s);
    if (isNaN(d.getTime())) return s;
    return (
      String(d.getDate()).padStart(2, "0") + "/" +
      String(d.getMonth() + 1).padStart(2, "0") + "/" +
      d.getFullYear() + " " +
      String(d.getHours()).padStart(2, "0") + ":" +
      String(d.getMinutes()).padStart(2, "0")
    );
  }

  /* ---------------- Toast Notification ---------------- */
  var _toastTimer = null;
  function showToast(message, type) {
    type = type || "info";
    var toast = $("bookingToast");
    if (!toast) {
      toast = document.createElement("div");
      toast.id = "bookingToast";
      toast.style.cssText =
        "position:fixed;bottom:24px;right:24px;background:#0f1f30;color:#fff;padding:12px 18px;border-radius:10px;" +
        "box-shadow:0 10px 30px rgba(0,0,0,0.3);z-index:99999;border-left:4px solid #c6a15b;display:none;" +
        "align-items:center;gap:10px;font-size:13.5px;max-width:440px;line-height:1.45;white-space:pre-line;";
      document.body.appendChild(toast);
    }
    var iconMap = { success: "✅", error: "❌", warning: "⚠️", info: "ℹ️" };
    var colorMap = { success: "#22c55e", error: "#ef4444", warning: "#f59e0b", info: "#c6a15b" };
    toast.innerHTML = '<span style="font-size:17px">' + (iconMap[type] || "ℹ️") + "</span><span>" + esc(message) + "</span>";
    toast.style.borderLeftColor = colorMap[type] || "#c6a15b";
    toast.style.display = "flex";
    if (_toastTimer) clearTimeout(_toastTimer);
    _toastTimer = setTimeout(function () {
      toast.style.display = "none";
    }, 4200);
  }

  /* ---------------- Điều hướng khung SkyHotel ---------------- */
  function goRooms() {
    if (parent !== window) parent.postMessage({ skyGo: "rooms" }, "*");
    else location.href = "chon-phong.html";
  }

  function goAmenity() {
    if (parent !== window) parent.postMessage({ skyGo: "amenity" }, "*");
    else location.href = "dich-vu-tien-ich.html";
  }

  function goHistory() {
    if (parent !== window) parent.postMessage({ skyGo: "hist:bookings" }, "*");
    else location.href = "lich-su-dat-phong.html#bookings";
  }

  function setStepBar(stepNum) {
    var s1 = $("stepIndicator1");
    var s2 = $("stepIndicator2");
    var s3 = $("stepIndicator3");
    if (!s1 || !s2 || !s3) return;

    if (stepNum === 1) {
      s1.className = "st on"; s1.querySelector("i").textContent = "1";
      s2.className = "st"; s2.querySelector("i").textContent = "2";
      s3.className = "st"; s3.querySelector("i").textContent = "3";
    } else if (stepNum === 2) {
      s1.className = "st done"; s1.querySelector("i").textContent = "✓";
      s2.className = "st on"; s2.querySelector("i").textContent = "2";
      s3.className = "st"; s3.querySelector("i").textContent = "3";
    } else if (stepNum === 3) {
      s1.className = "st done"; s1.querySelector("i").textContent = "✓";
      s2.className = "st done"; s2.querySelector("i").textContent = "✓";
      s3.className = "st on"; s3.querySelector("i").textContent = "3";
    }
  }

  var BOOKING_FLOW_KEY = "sky_booking_flow_state";

  function saveBookingFlowState(stage, data) {
    try {
      if (!data) return;
      sessionStorage.setItem(BOOKING_FLOW_KEY, JSON.stringify({
        stage: stage,
        data: data,
        savedAt: Date.now()
      }));
    } catch (e) { }
  }

  function readBookingFlowState() {
    try {
      var raw = sessionStorage.getItem(BOOKING_FLOW_KEY);
      if (!raw) return null;
      var parsed = JSON.parse(raw);
      if (!parsed || !parsed.data || !parsed.savedAt) return null;
      if (Date.now() - parsed.savedAt > 2 * 60 * 60 * 1000) {
        sessionStorage.removeItem(BOOKING_FLOW_KEY);
        return null;
      }
      return parsed;
    } catch (e) {
      return null;
    }
  }

  function clearBookingFlowState() {
    try { sessionStorage.removeItem(BOOKING_FLOW_KEY); } catch (e) { }
  }

  /* ---------------- State phiên làm việc ---------------- */
  var sel = null;
  var ctx = null; // { lines, nights, adults, children, rooms, checkIn, checkOut }
  var currentRoomsSelectedList = [];
  var currentBillData = null;
  var currentBookerData = null;
  var currentBookerMaKH = null;
  var bookerPhoneDebounceTimer = null;
  var lastAssignedBookerRoomIdx = null;
  var selectedPaymentMethod = "ChuyenKhoan";
  var qrTimerInterval = null;
  var qrPaymentWatcher = null;
  var submitting = false;

  function getCurrentUser() {
    try {
      var hu = (window.HotelAPI && window.HotelAPI.session)
        ? window.HotelAPI.session.get()
        : JSON.parse(localStorage.getItem("hotel_user") || "null");
      if (hu && hu.MaTK) return hu;
    } catch (e) { }
    return null;
  }

  function isReceptionistRole() {
    var u = getCurrentUser();
    return !!(u && u.VaiTro === "LeTan");
  }

  // Lễ tân không có giao diện đặt phòng riêng trên web; luôn dùng luồng đặt phòng chuẩn
  function isCounterBooking() {
    return false;
  }

  function readSel() {
    try {
      var s = JSON.parse(localStorage.getItem("sky_sel") || "null");
      if (s && s.items && s.items.length && U.parse(s.checkIn) && s.checkIn >= U.today()) return s;
    } catch (e) { }
    return null;
  }

  function computeTargetGuests(s) {
    var adults = 0;
    var children = 0;
    if (Array.isArray(s.occupancy) && s.occupancy.length) {
      s.occupancy.forEach(function (r) {
        adults += Math.max(1, Number(r.adults) || 1);
        children += Math.max(0, Number(r.children) || 0);
      });
    } else {
      adults = Math.max(1, Number(s.adults) || Number(s.guests) || 2);
      children = Math.max(0, Number(s.children) || 0);
    }
    return { adults: adults, children: children, total: adults + children };
  }

  /* ---------------- Kiểm tra SĐT & Giấy tờ tùy thân (Đồng bộ legacy/datphong.html) ---------------- */
  function validatePhoneNumber(sdt) {
    var s = String(sdt || "").trim();
    if (!s) return { valid: false, message: "Vui lòng nhập số điện thoại." };
    if (!/^[0-9]+$/.test(s)) {
      return { valid: false, message: "Số điện thoại chỉ được chứa các chữ số (0-9), không chứa chữ cái hoặc ký tự đặc biệt." };
    }
    var len = s.length;
    if (len < 10) {
      return { valid: false, message: "Số điện thoại chỉ có " + len + " chữ số (thiếu " + (10 - len) + " số). Quy định phải gồm đúng 10 số." };
    }
    if (len > 10) {
      return { valid: false, message: "Số điện thoại có " + len + " chữ số (thừa " + (len - 10) + " số). Quy định chỉ gồm đúng 10 số." };
    }
    if (!/^(03|05|07|08|09)/.test(s)) {
      return { valid: false, message: "Số điện thoại không hợp lệ. Phải bắt đầu bằng các đầu số di động Việt Nam (03, 05, 07, 08, 09)." };
    }
    return { valid: true, message: "Số điện thoại hợp lệ (10 chữ số)." };
  }

  function validateDocumentNumber(loaiGiayTo, soGiayTo) {
    var s = String(soGiayTo || "").trim();
    if (!s) return { valid: false, message: "Vui lòng nhập số giấy tờ tùy thân." };
    var len = s.length;
    var norm = String(loaiGiayTo || "").toUpperCase().replace(/[^A-Z0-9]/g, "");

    if (norm.indexOf("CCCD") > -1) {
      if (!/^[0-9]+$/.test(s)) {
        return { valid: false, message: "Số CCCD chỉ được chứa các chữ số (0-9), không chứa chữ cái hoặc ký tự đặc biệt." };
      }
      if (len < 12) return { valid: false, message: "Số CCCD chỉ có " + len + " chữ số (thiếu " + (12 - len) + " số). Quy định CCCD phải gồm đúng 12 chữ số." };
      if (len > 12) return { valid: false, message: "Số CCCD có " + len + " chữ số (thừa " + (len - 12) + " số). Quy định CCCD chỉ gồm đúng 12 chữ số." };
    } else if (norm.indexOf("CMND") > -1) {
      if (!/^[0-9]+$/.test(s)) return { valid: false, message: "Số CMND chỉ được chứa các chữ số (0-9)." };
      if (len < 9) return { valid: false, message: "Số CMND chỉ có " + len + " chữ số (thiếu " + (9 - len) + " số). Quy định CMND phải gồm đúng 9 chữ số." };
      if (len > 9) return { valid: false, message: "Số CMND có " + len + " chữ số (thừa " + (len - 9) + " số). Quy định CMND chỉ gồm đúng 9 chữ số." };
    } else if (norm.indexOf("HOCHIEU") > -1 || norm.indexOf("PASSPORT") > -1) {
      if (/\s/.test(s) || /[^A-Za-z0-9]/.test(s)) {
        return { valid: false, message: "Số Hộ chiếu không được chứa khoảng trắng hoặc ký tự đặc biệt." };
      }
      if (len < 7) return { valid: false, message: "Số Hộ chiếu chỉ có " + len + " ký tự (thiếu tối thiểu " + (7 - len) + " ký tự). Quy định từ 7 đến 9 ký tự." };
      if (len > 9) return { valid: false, message: "Số Hộ chiếu có " + len + " ký tự (thừa " + (len - 9) + " ký tự). Quy định tối đa 9 ký tự." };
    } else if (norm.indexOf("GIAYKHAISINH") > -1) {
      if (len < 6) return { valid: false, message: "Số Giấy khai sinh chỉ có " + len + " ký tự (thiếu " + (6 - len) + " ký tự). Độ dài hợp lệ từ 6 đến 15 ký tự." };
      if (len > 15) return { valid: false, message: "Số Giấy khai sinh có " + len + " ký tự (thừa " + (len - 15) + " ký tự). Độ dài hợp lệ tối đa 15 ký tự." };
    } else {
      if (len < 5) return { valid: false, message: "Số giấy tờ tùy thân quá ngắn (tối thiểu 5 ký tự)." };
      if (len > 25) return { valid: false, message: "Số giấy tờ tùy thân quá dài (tối đa 25 ký tự)." };
    }
    return { valid: true, message: "Số giấy tờ hợp lệ." };
  }

  function calcAgeAtDate(dobStr, dateStr) {
    if (!dobStr || !dateStr) return null;
    var dob = new Date(dobStr);
    var target = new Date(dateStr);
    if (isNaN(dob.getTime()) || isNaN(target.getTime())) return null;
    if (dob > target) return -1;
    var age = target.getFullYear() - dob.getFullYear();
    var m = target.getMonth() - dob.getMonth();
    if (m < 0 || (m === 0 && target.getDate() < dob.getDate())) age--;
    return age;
  }

  /* ---------------- Tra cứu SĐT Người đặt & Đồng bộ vào Khách 1 ---------------- */
  function populateBookerRoomOptions(rooms) {
    var select = $("bookerStayRoomSelect");
    if (!select) return;
    var prevVal = select.value;
    select.innerHTML = "";
    if (!rooms || !rooms.length) return;
    rooms.forEach(function (room, roomIdx) {
      var opt = document.createElement("option");
      opt.value = String(roomIdx);
      opt.textContent = "Phòng " + (roomIdx + 1) + " - " + room.tenLoaiPhong;
      select.appendChild(opt);
    });
    if (prevVal !== "" && parseInt(prevVal, 10) < rooms.length) {
      select.value = prevVal;
    } else {
      select.value = "0";
    }
  }

  function syncBookerNameToGuest() {
    var bookerNameInput = $("bookerName");
    var nameVal = bookerNameInput ? bookerNameInput.value.trim() : "";

    var targetRoomBlock = $("room_block_0");
    if (!targetRoomBlock) return;
    var card = targetRoomBlock.querySelector(".guest-card");
    if (!card) return;

    var gIdx = card.dataset.globalidx;
    var nameInput = card.querySelector(".g-name");
    var dobInput = card.querySelector(".g-dob");
    var natSelect = card.querySelector(".g-nat");
    var idTypeSelect = card.querySelector(".g-idtype");
    var idNumInput = card.querySelector(".g-idnum");

    // Gắn listener một lần để tôn trọng chỉnh sửa thủ công của người dùng
    if (!card.dataset.manualWatchAttached) {
      card.dataset.manualWatchAttached = "1";
      [nameInput, dobInput, natSelect, idTypeSelect, idNumInput].forEach(function (el) {
        if (!el) return;
        el.addEventListener("input", function () { el.dataset.userEdited = "1"; });
        el.addEventListener("change", function () { el.dataset.userEdited = "1"; });
      });
    }

    var hoTenToSet = (currentBookerData && currentBookerData.HoTen) ? currentBookerData.HoTen : nameVal;
    if (nameInput && hoTenToSet && nameInput.dataset.userEdited !== "1") {
      nameInput.value = hoTenToSet;
    }

    if (dobInput && currentBookerData && currentBookerData.NgaySinh && dobInput.dataset.userEdited !== "1") {
      setGuestDobValue(dobInput, String(currentBookerData.NgaySinh).split(" ")[0]);
    }
    if (natSelect && natSelect.dataset.userEdited !== "1") {
      if (currentBookerData && currentBookerData.QuocTich) {
        natSelect.value = currentBookerData.QuocTich;
      } else if (!natSelect.value) {
        natSelect.value = "Việt Nam";
      }
    }
    handleGuestFieldChange(gIdx);

    if (idTypeSelect && currentBookerData && currentBookerData.LoaiGiayTo && idTypeSelect.dataset.userEdited !== "1") {
      idTypeSelect.value = currentBookerData.LoaiGiayTo;
    }
    if (idNumInput && currentBookerData && currentBookerData.SoGiayTo && idNumInput.dataset.userEdited !== "1") {
      idNumInput.value = currentBookerData.SoGiayTo;
    }
    handleGuestIdNumInput(gIdx);
    lastAssignedBookerRoomIdx = 0;
  }

  function lookupBookerByPhone(enrichProfileOnly) {
    var loggedUser = getCurrentUser();
    var phoneInput = $("bookerPhone");
    var phone = phoneInput ? phoneInput.value.trim() : "";
    var badge = $("bookerTypeBadge");
    var nameInput = $("bookerName");
    var emailInput = $("bookerEmail");
    if (!phone) return;

    var keepCurrentInputs = !!loggedUser && enrichProfileOnly === true;

    if (!keepCurrentInputs && badge) {
      badge.innerHTML = "Đang kiểm tra thông tin...";
      badge.style.background = "rgba(15, 31, 48, 0.06)";
      badge.style.color = "var(--muted)";
    }

    fetch(API_BASE + "/khachhang/timtheosdt", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ sdt: phone })
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        var d = data && data.data ? data.data : null;
        if (data && data.success && d && d.found && d.data) {
          var kh = d.data;
          if (keepCurrentInputs) {
            // Giữ nguyên Họ tên / SĐT / Email đang có trên form (mặc định theo tài khoản hoặc do người dùng tự sửa),
            // chỉ bổ sung hồ sơ (Ngày sinh, Quốc tịch, CCCD) cho khách #1 nếu khớp.
            currentBookerData = Object.assign({}, kh, {
              HoTen: ((nameInput && nameInput.value.trim()) || loggedUser.HoTen || loggedUser.name || kh.HoTen || "").trim(),
              SDT: (phone || loggedUser.SDT || loggedUser.phone || "").trim(),
              Email: ((emailInput && emailInput.value.trim()) || loggedUser.Email || loggedUser.email || kh.Email || "").trim()
            });
            currentBookerMaKH = loggedUser.MaKH || kh.MaKH || currentBookerMaKH;
          } else {
            currentBookerData = kh;
            currentBookerMaKH = kh.MaKH;
            if (nameInput && kh.HoTen && !nameInput.dataset.userEdited && !nameInput.value.trim()) nameInput.value = kh.HoTen;
            if (emailInput && kh.Email && !emailInput.dataset.userEdited && !emailInput.value.trim()) emailInput.value = kh.Email;

            if (badge) {
              if (kh.MaTK) {
                badge.innerHTML = "⭐ Khách hàng thành viên";
                badge.style.background = "rgba(22, 163, 74, 0.12)";
                badge.style.color = "#15803d";
                badge.style.borderColor = "rgba(22, 163, 74, 0.35)";
              } else {
                badge.innerHTML = "Khách hàng đã có hồ sơ";
                badge.style.background = "rgba(15, 113, 128, 0.1)";
                badge.style.color = "var(--color-primary, #0F7180)";
                badge.style.borderColor = "rgba(15, 113, 128, 0.25)";
              }
            }
          }
        } else if (!keepCurrentInputs) {
          currentBookerData = null;
          currentBookerMaKH = null;
          if (badge) {
            badge.innerHTML = "Khách hàng mới";
            badge.style.background = "rgba(212, 168, 83, 0.14)";
            badge.style.color = "#8a6829";
            badge.style.borderColor = "rgba(212, 168, 83, 0.35)";
          }
        }
        syncBookerNameToGuest();
        calculateAndReviewBill();
      })
      .catch(function () {
        if (!keepCurrentInputs && badge) badge.innerHTML = "⚠️ Không tra cứu được";
      });
  }

  function onBookerPhoneInput() {
    var phoneInput = $("bookerPhone");
    if (!phoneInput) return;
    var loggedUser = getCurrentUser();
    var phone = phoneInput.value.trim();
    var hint = $("bookerPhoneHint");

    if (bookerPhoneDebounceTimer) clearTimeout(bookerPhoneDebounceTimer);

    if (!phone) {
      if (hint) {
        hint.innerHTML = "";
        phoneInput.classList.remove("bad");
      }
      if (!loggedUser) {
        currentBookerData = null;
        currentBookerMaKH = null;
        var badge = $("bookerTypeBadge");
        if (badge) {
          badge.innerHTML = "Chưa nhập SĐT";
          badge.style.background = "rgba(15, 31, 48, 0.06)";
          badge.style.color = "var(--muted)";
        }
      }
      syncBookerNameToGuest();
      return;
    }

    var phoneCheck = validatePhoneNumber(phone);
    if (hint) {
      if (!phoneCheck.valid) {
        hint.className = "msg";
        hint.innerHTML = "⚠️ " + esc(phoneCheck.message);
        phoneInput.classList.add("bad");
      } else {
        hint.className = "msg ok";
        hint.innerHTML = "✓ " + esc(phoneCheck.message);
        phoneInput.classList.remove("bad");
      }
    }

    if (phone.length === 10 && phoneCheck.valid) {
      lookupBookerByPhone(!!loggedUser);
    } else {
      bookerPhoneDebounceTimer = setTimeout(function () {
        if (phone.length >= 9 && /^[0-9]+$/.test(phone)) {
          lookupBookerByPhone(!!loggedUser);
        }
      }, 350);
    }
  }

  /* ---------------- Biểu mẫu người lưu trú từng phòng (Đồng bộ legacy/datphong.html) ---------------- */
  function updateIdTypeOptions(selectElem, nationality, isChild) {
    if (!selectElem) return;
    var currentVal = selectElem.value;
    selectElem.innerHTML = "";

    if (!nationality) {
      selectElem.innerHTML = '<option value="">-- Vui lòng chọn Quốc tịch trước --</option>';
      return;
    }

    if (nationality === "Việt Nam") {
      if (isChild) {
        selectElem.innerHTML =
          '<option value="GiayKhaiSinh">Giấy khai sinh (Đề xuất cho trẻ em)</option>' +
          '<option value="CCCD">CCCD / Căn cước công dân</option>' +
          '<option value="HoChieu">Hộ chiếu riêng</option>' +
          '<option value="GiayToKhac">Giấy tờ tùy thân khác</option>';
      } else {
        selectElem.innerHTML =
          '<option value="CCCD">CCCD (Căn cước công dân)</option>' +
          '<option value="HoChieu">Hộ chiếu (Passport)</option>' +
          '<option value="GiayToKhac">Giấy tờ tùy thân khác</option>';
      }
    } else {
      if (isChild) {
        selectElem.innerHTML =
          '<option value="HoChieu">Hộ chiếu riêng (Đề xuất)</option>' +
          '<option value="GiayToDiLai">Giấy tờ đi lại hợp lệ</option>' +
          '<option value="GiayToKhac">Giấy tờ tùy thân khác</option>';
      } else {
        selectElem.innerHTML =
          '<option value="HoChieu">Hộ chiếu (Passport)</option>' +
          '<option value="GiayToKhac">Giấy tờ tùy thân khác</option>';
      }
    }

    var hasOld = [].some.call(selectElem.options, function (o) { return o.value === currentVal; });
    if (hasOld) selectElem.value = currentVal;
  }

  function handleGuestIdNumInput(globalIdx) {
    var idNumInput = $("g_idnum_" + globalIdx);
    var idTypeSelect = $("g_idtype_" + globalIdx);
    var hint = $("idnum_hint_" + globalIdx);
    if (!idNumInput || !hint) return;

    var val = idNumInput.value.trim();
    var idType = idTypeSelect ? idTypeSelect.value : "";

    if (!val) {
      hint.innerHTML = "";
      idNumInput.style.borderColor = "";
      return;
    }

    var check = validateDocumentNumber(idType, val);
    if (!check.valid) {
      hint.className = "msg";
      hint.innerHTML = "⚠️ " + esc(check.message);
      idNumInput.style.borderColor = "#dc2626";
    } else {
      hint.className = "msg ok";
      hint.innerHTML = "✓ " + esc(check.message) + " (" + val.length + " ký tự)";
      idNumInput.style.borderColor = "#16a34a";
    }
  }

  function isoToDisplayDate(isoStr) {
    var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(isoStr || "").trim());
    if (!m) return "";
    return m[3] + "/" + m[2] + "/" + m[1];
  }

  function displayToIsoDate(val) {
    var s = String(val || "").trim();
    if (!s) return "";
    if (/^\d{4}-\d{2}-\d{2}$/.test(s)) {
      return U.parse(s) ? s : "";
    }
    var m = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(s);
    if (!m) return "";
    var day = parseInt(m[1], 10);
    var month = parseInt(m[2], 10);
    var year = parseInt(m[3], 10);
    if (year < 1900 || year > 2100 || month < 1 || month > 12 || day < 1 || day > 31) return "";
    var dt = new Date(year, month - 1, day);
    if (dt.getFullYear() !== year || dt.getMonth() !== month - 1 || dt.getDate() !== day) return "";
    return m[3] + "-" + m[2] + "-" + m[1];
  }

  function formatDobTyping(raw) {
    var digits = String(raw || "").replace(/\D/g, "").slice(0, 8);
    if (digits.length <= 2) return digits;
    if (digits.length <= 4) return digits.slice(0, 2) + "/" + digits.slice(2);
    return digits.slice(0, 2) + "/" + digits.slice(2, 4) + "/" + digits.slice(4);
  }

  function getGuestDobIso(dobEl) {
    return dobEl ? displayToIsoDate(dobEl.value) : "";
  }

  function setGuestDobValue(dobEl, isoOrDisplay) {
    if (!dobEl) return;
    var iso = displayToIsoDate(isoOrDisplay);
    dobEl.value = iso ? isoToDisplayDate(iso) : String(isoOrDisplay || "");
    var wrap = dobEl.closest(".dob-input-wrap");
    var picker = wrap ? wrap.querySelector(".g-dob-picker") : null;
    if (picker && iso) picker.value = iso;
  }

  function updateGuestStructureStatus() {
    if (!ctx) return;
    var adultsTarget = ctx.adults || 1;
    var childrenTarget = ctx.children || 0;
    var checkInDate = ctx.checkIn;

    var cards = document.querySelectorAll(".guest-card");
    var currentAdults = 0;
    var currentChildren = 0;
    var filledCount = 0;
    var hasInvalidDob = false;

    cards.forEach(function (card) {
      var dobEl = card.querySelector(".g-dob");
      var dob = getGuestDobIso(dobEl);
      var rawDob = dobEl ? dobEl.value.trim() : "";
      if (rawDob.length === 10 && !dob) {
        hasInvalidDob = true;
      }
      if (dob && checkInDate) {
        var age = calcAgeAtDate(dob, checkInDate);
        if (age !== null && age >= 0) {
          filledCount++;
          if (age <= 11) currentChildren++;
          else currentAdults++;
        } else if (age !== null && age < 0) {
          hasInvalidDob = true;
        }
      }
    });

    var alertBox = $("guestValidationAlert");
    if (!alertBox) return;

    if (hasInvalidDob) {
      alertBox.innerHTML = "<strong>Ngày sinh không hợp lệ:</strong> Vui lòng nhập đúng định dạng dd/mm/yyyy và trước ngày nhận phòng (" + esc(U.fmtDate(checkInDate)) + ").";
      alertBox.style.display = "block";
      return;
    }

    if (cards.length > 0 && filledCount === cards.length && (currentAdults !== adultsTarget || currentChildren !== childrenTarget)) {
      alertBox.innerHTML =
        "<strong>Cơ cấu độ tuổi chưa khớp:</strong> Bạn đã đặt cho <strong>" +
        adultsTarget + " Người lớn</strong> (> 11 tuổi) và <strong>" + childrenTarget + " Trẻ em</strong> (≤ 11 tuổi), " +
        "nhưng ngày sinh hiện tại tính ra <strong>" + currentAdults + " Người lớn, " + currentChildren + " Trẻ em</strong>.";
      alertBox.style.display = "block";
    } else {
      alertBox.style.display = "none";
      alertBox.innerHTML = "";
    }
  }

  function handleGuestFieldChange(globalIdx) {
    var dobInput = $("g_dob_" + globalIdx);
    var natSelect = $("g_nat_" + globalIdx);
    var idTypeSelect = $("g_idtype_" + globalIdx);
    var ageBadge = $("age_badge_" + globalIdx);
    if (!dobInput || !natSelect || !idTypeSelect) return;

    var dobVal = getGuestDobIso(dobInput);
    var natVal = natSelect.value;
    var checkInDate = ctx ? ctx.checkIn : "";

    var isChild = false;
    if (dobVal && checkInDate) {
      var age = calcAgeAtDate(dobVal, checkInDate);
      if (age === -1) {
        if (ageBadge) {
          ageBadge.style.display = "inline-block";
          ageBadge.style.background = "rgba(220, 38, 38, 0.12)";
          ageBadge.style.color = "#b91c1c";
          ageBadge.textContent = "⚠️ Sau ngày nhận phòng";
        }
      } else if (age !== null) {
        isChild = age <= 11;
      }
    }
    if (ageBadge) {
      ageBadge.style.display = "none";
      ageBadge.textContent = "";
    }

    updateIdTypeOptions(idTypeSelect, natVal, isChild);
    updateGuestStructureStatus();
    handleGuestIdNumInput(globalIdx);
  }

  function createGuestCard(roomIdx, guestInRoomIdx, globalIdx, totalRooms) {
    var card = document.createElement("div");
    card.className = "guest-card";
    card.id = "guest_card_" + globalIdx;
    card.dataset.globalidx = String(globalIdx);
    card.dataset.roomindex = String(roomIdx);

    var headerTitle = totalRooms > 1
      ? ('Khách ' + guestInRoomIdx + ' · Phòng ' + (roomIdx + 1))
      : ('Khách lưu trú ' + (globalIdx + 1));

    card.innerHTML =
      '<div class="guest-card-header">' +
      '<span>👤 ' + headerTitle + '</span>' +
      (globalIdx === 0 ? '<span style="font-size: 11.5px; color: var(--color-primary, #0F7180); font-weight: 600;">Khách chính</span>' : '') +
      '</div>' +
      '<div class="guest-form-grid">' +
      '<div class="guest-field-group">' +
      '<label for="g_name_' + globalIdx + '">Họ và tên <em>*</em></label>' +
      '<input type="text" class="g-name" placeholder="Nhập họ và tên đầy đủ" id="g_name_' + globalIdx + '" />' +
      '</div>' +
      '<div class="guest-field-group">' +
      '<div style="display:flex;justify-content:space-between;align-items:center;">' +
      '<label for="g_dob_' + globalIdx + '">Ngày sinh <em>*</em></label>' +
      '<span id="age_badge_' + globalIdx + '" class="age-badge-pill" style="display:none;"></span>' +
      '</div>' +
      '<div class="dob-input-wrap">' +
      '<input type="text" class="g-dob" id="g_dob_' + globalIdx + '" placeholder="dd/mm/yyyy" maxlength="10" inputmode="numeric" />' +
      '<button type="button" class="dob-cal-btn" title="Chọn từ lịch" tabindex="-1">📅</button>' +
      '<input type="date" class="g-dob-picker" tabindex="-1" aria-hidden="true" />' +
      '</div>' +
      '</div>' +
      '<div class="guest-field-group">' +
      '<label for="g_nat_' + globalIdx + '">Quốc tịch <em>*</em></label>' +
      '<select class="g-nat" id="g_nat_' + globalIdx + '">' +
      '<option value="">-- Chọn quốc tịch --</option>' +
      '<option value="Việt Nam">Việt Nam</option>' +
      '<option value="Nước ngoài">Nước ngoài</option>' +
      '</select>' +
      '</div>' +
      '<div class="guest-field-group">' +
      '<label for="g_idtype_' + globalIdx + '">Loại giấy tờ tùy thân <em>*</em></label>' +
      '<select class="g-idtype" id="g_idtype_' + globalIdx + '">' +
      '<option value="">-- Vui lòng chọn Quốc tịch trước --</option>' +
      '</select>' +
      '</div>' +
      '<div class="guest-field-group">' +
      '<label for="g_idnum_' + globalIdx + '">Số giấy tờ tùy thân <em>*</em></label>' +
      '<input type="text" class="g-idnum" placeholder="Số CCCD / Hộ chiếu / Giấy khai sinh" id="g_idnum_' + globalIdx + '" />' +
      '<div id="idnum_hint_' + globalIdx + '" class="msg"></div>' +
      '</div>' +
      '<div class="guest-field-group">' +
      '<label for="g_note_' + globalIdx + '">Ghi chú (Tùy chọn)</label>' +
      '<input type="text" class="g-note" placeholder="Yêu cầu đặc biệt, dị ứng, người giám hộ..." id="g_note_' + globalIdx + '" />' +
      '</div>' +
      '</div>';

    var dobEl = card.querySelector(".g-dob");
    var dobPicker = card.querySelector(".g-dob-picker");
    var dobCalBtn = card.querySelector(".dob-cal-btn");
    var natEl = card.querySelector(".g-nat");
    var idTypeEl = card.querySelector(".g-idtype");
    var idNumEl = card.querySelector(".g-idnum");

    dobEl.addEventListener("input", function () {
      var val = dobEl.value.trim();
      if (/^\d{4}-\d{2}-\d{2}$/.test(val)) {
        dobEl.value = isoToDisplayDate(val);
      } else {
        dobEl.value = formatDobTyping(val);
      }
      if (dobEl.value.length === 10) {
        var iso = getGuestDobIso(dobEl);
        if (dobPicker && iso) dobPicker.value = iso;
        handleGuestFieldChange(globalIdx);
      }
    });
    dobEl.addEventListener("change", function () {
      var val = dobEl.value.trim();
      if (/^\d{4}-\d{2}-\d{2}$/.test(val)) {
        dobEl.value = isoToDisplayDate(val);
      }
      var iso = getGuestDobIso(dobEl);
      if (dobPicker && iso) dobPicker.value = iso;
      handleGuestFieldChange(globalIdx);
    });
    if (dobCalBtn && dobPicker) {
      dobCalBtn.addEventListener("click", function () {
        if (typeof dobPicker.showPicker === "function") dobPicker.showPicker();
        else dobPicker.click();
      });
      dobPicker.addEventListener("change", function () {
        if (dobPicker.value) {
          dobEl.value = isoToDisplayDate(dobPicker.value);
          dobEl.dataset.userEdited = "1";
          handleGuestFieldChange(globalIdx);
        }
      });
    }
    natEl.addEventListener("change", function () { handleGuestFieldChange(globalIdx); });
    idTypeEl.addEventListener("change", function () { handleGuestIdNumInput(globalIdx); });
    idNumEl.addEventListener("input", function () { handleGuestIdNumInput(globalIdx); });

    return card;
  }

  function renderGuestsForm() {
    var container = $("guests-container");
    if (!container || !ctx) return;
    container.innerHTML = "";

    var adultsTarget = ctx.adults || 1;
    var childrenTarget = ctx.children || 0;
    var totalGuestsTarget = adultsTarget + childrenTarget;

    var targetTextEl = $("filterGuestTargetText");
    if (targetTextEl) {
      targetTextEl.textContent = adultsTarget + " Người lớn, " + childrenTarget + " Trẻ em (Tổng cộng " + totalGuestsTarget + " khách)";
    }

    var roomsSelectedList = [];
    ctx.lines.forEach(function (l) {
      for (var i = 0; i < l.qty; i++) {
        roomsSelectedList.push({
          maLoaiPhong: l.typeId,
          tenLoaiPhong: l.name,
          maxCapacity: l.capacity
        });
      }
    });
    currentRoomsSelectedList = roomsSelectedList;

    var totalRooms = roomsSelectedList.length;
    if (!totalRooms) return;

    // Thuật toán phân bổ số biểu mẫu khách vào từng phòng (giống hệt legacy/datphong.html)
    var guestCountsPerRoom = [];
    for (var rIdx = 0; rIdx < totalRooms; rIdx++) guestCountsPerRoom.push(1);
    var remainingGuests = Math.max(0, totalGuestsTarget - totalRooms);

    for (var i = 0; i < totalRooms && remainingGuests > 0; i++) {
      var canAdd = Math.max(0, roomsSelectedList[i].maxCapacity - guestCountsPerRoom[i]);
      var add = Math.min(canAdd, remainingGuests);
      guestCountsPerRoom[i] += add;
      remainingGuests -= add;
    }
    while (remainingGuests > 0) {
      for (var j = 0; j < totalRooms && remainingGuests > 0; j++) {
        guestCountsPerRoom[j]++;
        remainingGuests--;
      }
    }

    var globalGuestIndex = 0;
    roomsSelectedList.forEach(function (room, roomIdx) {
      var countInThisRoom = guestCountsPerRoom[roomIdx];
      var block = document.createElement("div");
      block.className = "room-guest-block" + (totalRooms === 1 ? " single-room" : "");
      block.id = "room_block_" + roomIdx;
      block.dataset.maloai = room.maLoaiPhong;
      block.dataset.maxcapacity = String(room.maxCapacity);
      block.dataset.roomindex = String(roomIdx);

      block.innerHTML = (totalRooms > 1
        ? '<div class="room-guest-header">' +
          '<span>🔑 Phòng ' + (roomIdx + 1) + ': ' + esc(room.tenLoaiPhong) + '</span>' +
          '<span class="room-alloc-meta">Phân bổ: <strong>' + countInThisRoom + ' khách</strong> (Sức chứa tiêu chuẩn: ' + room.maxCapacity + ' người lớn)</span>' +
          '</div>'
        : '') +
        '<div id="guests_container_' + roomIdx + '"></div>';

      container.appendChild(block);
      var guestsContainer = block.querySelector("#guests_container_" + roomIdx);
      for (var g = 0; g < countInThisRoom; g++) {
        guestsContainer.appendChild(createGuestCard(roomIdx, g + 1, globalGuestIndex, totalRooms));
        globalGuestIndex++;
      }
    });

    populateBookerRoomOptions(roomsSelectedList);
    fillLoggedInUser();
    updateGuestStructureStatus();
  }

  /* ---------------- Điền nhanh mẫu test (Đồng bộ legacy/datphong.html) ---------------- */
  function autoFillGuests() {
    if (!ctx) return;
    var adultsTarget = ctx.adults || 1;
    var cards = document.querySelectorAll(".guest-card");
    var sampleAdultNames = ["Nguyễn Văn An", "Trần Thị Mai", "Lê Minh Hải", "Phạm Hoàng Nam", "Đỗ Thùy Trang", "Vũ Quốc Khánh"];
    var sampleChildNames = ["Nguyễn Gia Hân", "Trần Quốc Bảo", "Lê Khánh Linh", "Phạm Minh Khôi"];

    var adultIdx = 0;
    var childIdx = 0;

    if (!getCurrentUser()) {
      if ($("bookerPhone") && !$("bookerPhone").value.trim()) {
        $("bookerPhone").value = "0938182210";
        onBookerPhoneInput();
      }
      if ($("bookerName") && !$("bookerName").value.trim()) {
        $("bookerName").value = sampleAdultNames[0];
      }
    }

    cards.forEach(function (card) {
      var globalIdx = card.dataset.globalidx;
      var isAdult = adultIdx < adultsTarget;

      var nameInput = card.querySelector(".g-name");
      var dobInput = card.querySelector(".g-dob");
      var natSelect = card.querySelector(".g-nat");
      var idTypeSelect = card.querySelector(".g-idtype");
      var idNumInput = card.querySelector(".g-idnum");
      var noteInput = card.querySelector(".g-note");

      if (isAdult) {
        nameInput.value = sampleAdultNames[adultIdx % sampleAdultNames.length];
        setGuestDobValue(dobInput, "199" + ((adultIdx % 8) + 1) + "-05-15");
        natSelect.value = "Việt Nam";
        updateIdTypeOptions(idTypeSelect, "Việt Nam", false);
        idTypeSelect.value = "CCCD";
        idNumInput.value = "0012020000" + String(adultIdx + 10).slice(-2);
        noteInput.value = adultIdx === 0 ? "Người đại diện đặt phòng" : "Khách cùng đoàn";
        adultIdx++;
      } else {
        nameInput.value = sampleChildNames[childIdx % sampleChildNames.length];
        setGuestDobValue(dobInput, "201" + ((childIdx % 4) + 6) + "-08-20");
        natSelect.value = "Việt Nam";
        updateIdTypeOptions(idTypeSelect, "Việt Nam", true);
        idTypeSelect.value = "GiayKhaiSinh";
        idNumInput.value = "KS-2018-000" + (childIdx + 1);
        noteInput.value = "Trẻ em đi cùng gia đình";
        childIdx++;
      }

      handleGuestFieldChange(globalIdx);
    });

    updateGuestStructureStatus();
    if ($("chkBookerIsGuest") && $("chkBookerIsGuest").checked) {
      syncBookerNameToGuest();
    }
    showToast("Đã điền nhanh dữ liệu mẫu hợp lệ!", "success");
  }

  /* ---------------- Điền thông tin tài khoản đang đăng nhập (mặc định ban đầu, vẫn cho phép sửa) ---------------- */
  function fillLoggedInUser() {
    var u = getCurrentUser();
    var phoneEl = $("bookerPhone");
    var nameEl = $("bookerName");
    var emailEl = $("bookerEmail");
    var badge = $("bookerTypeBadge");
    var hint = $("bookerPhoneHint");

    [phoneEl, nameEl, emailEl].forEach(function (el) {
      if (!el) return;
      el.readOnly = false;
      el.classList.remove("is-account-locked");
      if (!el.dataset.editBound) {
        el.dataset.editBound = "1";
        el.addEventListener("input", function () {
          el.dataset.userEdited = "1";
        });
      }
    });

    if (!u) return;

    var phoneVal = String(u.SDT || u.phone || "").trim();
    var nameVal = String(u.HoTen || u.name || u.TenDangNhap || "").trim();
    var emailVal = String(u.Email || u.email || "").trim();

    if (nameEl && nameVal && !nameEl.dataset.userEdited && !nameEl.value.trim()) nameEl.value = nameVal;
    if (emailEl && emailVal && !emailEl.dataset.userEdited && !emailEl.value.trim()) emailEl.value = emailVal;
    if (phoneEl && phoneVal && !phoneEl.dataset.userEdited && !phoneEl.value.trim()) phoneEl.value = phoneVal;

    currentBookerMaKH = u.MaKH || null;
    currentBookerData = {
      MaKH: u.MaKH || null,
      HoTen: (nameEl && nameEl.value.trim()) || nameVal,
      SDT: (phoneEl && phoneEl.value.trim()) || phoneVal,
      Email: (emailEl && emailEl.value.trim()) || emailVal
    };

    if (badge) {
      var roleLabel = u.VaiTro === "LeTan" ? "Lễ tân" : "Khách hàng thành viên";
      badge.innerHTML = "✓ Theo tài khoản đặt (" + roleLabel + ")";
      badge.style.background = "rgba(22, 163, 74, 0.12)";
      badge.style.color = "#15803d";
      badge.style.borderColor = "rgba(22, 163, 74, 0.35)";
    }

    var activePhone = (phoneEl && phoneEl.value.trim()) || phoneVal;
    if (activePhone && hint) {
      var phoneCheck = validatePhoneNumber(activePhone);
      if (phoneCheck.valid) {
        hint.className = "msg ok";
        hint.innerHTML = "✓ " + esc(phoneCheck.message);
        if (phoneEl) phoneEl.classList.remove("bad");
      }
      // Lấy thêm hồ sơ chi tiết (Ngày sinh, Quốc tịch, Giấy tờ) của tài khoản nếu có
      if (u.VaiTro === "KhachHang") {
        lookupBookerByPhone(true);
      }
    }

    syncBookerNameToGuest();
  }

  /* ---------------- Tính toán chi phí dự kiến từ Backend (/datphong/tinhchiphi) ---------------- */
  function calcNightsBetween(checkInStr, checkOutStr, fallbackNights) {
    var dIn = U.parse(checkInStr);
    var dOut = U.parse(checkOutStr);
    if (dIn && dOut) {
      var diff = Math.round((dOut.getTime() - dIn.getTime()) / 86400000);
      if (diff > 0) return diff;
    }
    return Math.max(1, Number(fallbackNights) || 1);
  }

  function fmtWeekdayVi(dateStr) {
    var d = U.parse(dateStr);
    if (!d) return "";
    var days = ["Chủ Nhật", "Thứ Hai", "Thứ Ba", "Thứ Tư", "Thứ Năm", "Thứ Sáu", "Thứ Bảy"];
    return days[d.getDay()] || "";
  }

  function renderOrderSummaryMarkup(opts) {
    if (!ctx) return;
    var nights = calcNightsBetween(ctx.checkIn, ctx.checkOut, opts.nights || ctx.nights);
    var nightsBadge = $("orderNightsBadge");
    if (nightsBadge) {
      nightsBadge.textContent = nights + " đêm";
      nightsBadge.style.display = "inline-flex";
    }

    var inWeekday = fmtWeekdayVi(ctx.checkIn);
    var outWeekday = fmtWeekdayVi(ctx.checkOut);
    var guestLabel = ctx.adults + " người lớn" + (ctx.children > 0 ? " · " + ctx.children + " trẻ em" : "");

    var h = '<div class="booking-dates-box">' +
      '<div class="booking-date-col">' +
        '<span class="booking-date-label">CHECK-IN</span>' +
        '<span class="booking-date-value">' + esc(U.fmtDate(ctx.checkIn)) + '</span>' +
        '<span class="booking-date-meta">' + esc(inWeekday) + ' · Từ 14:00</span>' +
      '</div>' +
      '<div class="booking-date-col">' +
        '<span class="booking-date-label">CHECK-OUT</span>' +
        '<span class="booking-date-value">' + esc(U.fmtDate(ctx.checkOut)) + '</span>' +
        '<span class="booking-date-meta">' + esc(outWeekday) + ' · Trước 12:00</span>' +
      '</div>' +
    '</div>' +
    '<div class="booking-guest-meta">Số khách lưu trú: ' + esc(guestLabel) + '</div>';

    opts.roomLines.forEach(function (rm) {
      h += '<article class="room"><img class="img" src="' + esc(rm.image) + '" alt="Phòng ' + esc(rm.name) + '" loading="lazy"><div>' +
        '<b>' + esc(rm.name) + ' × ' + rm.qty + '</b>' +
        '<small>Tối đa ' + rm.capacity + ' khách/phòng · ' + esc(rm.bed) + '</small>' +
        '<small>' + money(rm.donGia) + ' × ' + nights + ' đêm × ' + rm.qty + ' phòng = <b>' + money(rm.thanhTien) + '</b></small></div></article>';
    });

    h += '<div class="rows">' +
      '<div class="r"><span>Tiền phòng (' + ctx.rooms + ' phòng × ' + nights + ' đêm)</span><span>' + money(opts.subtotal) + '</span></div>' +
      (Number(opts.discountAmount) > 0
        ? '<div class="r ok"><span>Khuyến mãi (' + esc(opts.discountLabel) + ')</span><span>-' + money(opts.discountAmount) + '</span></div>'
        : '') +
      '<div class="r vat"><span>Thuế VAT phòng (8%)</span><span>+' + money(opts.vat) + '</span></div>' +
      '</div>' +
      '<div class="tot"><span>Tổng cộng thanh toán (Đã gồm VAT)</span><strong id="total">' + money(opts.total) + '</strong></div>' +
      '<div class="note"><a href="#" id="back">← Thay đổi lựa chọn phòng</a></div>';

    $("orderBody").innerHTML = h;
    if ($("back")) $("back").onclick = function (e) { e.preventDefault(); goRooms(); };
    updateBookingPolicySummary();
  }

  function updateBookingPolicySummary() {
    var list = $("policySummaryList");
    if (!list || !ctx) return;

    var pol = window.HotelAPI && window.HotelAPI.policy;
    var detailBox = $("policyDetailCancellation");
    if (detailBox && pol) {
      detailBox.innerHTML = pol.renderBulletListHtml({ showNoShowNote: false });
    }

    var evalRes = pol ? pol.evaluateByDate(ctx.checkIn) : null;
    var cancelLine = evalRes && evalRes.rule
      ? (evalRes.rule.thoiDiem + ": " + evalRes.rule.moTa + ".")
      : "";
    var noShowRule = pol && pol.rules && pol.rules[3]
      ? (pol.rules[3].thoiDiem + ": " + pol.rules[3].moTa + ".")
      : "";

    list.innerHTML =
      "<li>" + esc(cancelLine) + "</li>" +
      (noShowRule ? "<li>" + esc(noShowRule) + "</li>" : "") +
      "<li>Nhận phòng từ 14:00, trả phòng trước 12:00.</li>";
  }

  function renderFallbackOrder() {
    if (!ctx) return;
    var nights = calcNightsBetween(ctx.checkIn, ctx.checkOut, ctx.nights);
    var sub = 0;
    var roomLines = ctx.lines.map(function (l) {
      var thanhTien = l.price * nights * l.qty;
      sub += thanhTien;
      return {
        image: l.image,
        name: l.name,
        qty: l.qty,
        capacity: l.capacity,
        bed: l.bed,
        donGia: l.price,
        thanhTien: thanhTien
      };
    });
    var vat = Math.round(sub * 0.08);
    var tot = sub + vat;

    renderOrderSummaryMarkup({
      nights: nights,
      roomLines: roomLines,
      subtotal: sub,
      discountAmount: 0,
      discountLabel: "",
      vat: vat,
      total: tot
    });
  }

  function renderBillSummary(bill) {
    if (!ctx) return;
    var nights = calcNightsBetween(ctx.checkIn, ctx.checkOut, bill.soDem || ctx.nights);
    var roomLines = ctx.lines.map(function (l) {
      var matchedRoom = (bill.chiTietPhong || []).filter(function (p) { return p.maLoaiPhong === l.typeId; })[0];
      var donGia = matchedRoom ? Number(matchedRoom.donGia) : l.price;
      var thanhTien = matchedRoom ? Number(matchedRoom.thanhTien) : (donGia * nights * l.qty);
      return {
        image: l.image,
        name: l.name,
        qty: l.qty,
        capacity: l.capacity,
        bed: l.bed,
        donGia: donGia,
        thanhTien: thanhTien
      };
    });

    var vatPhong = bill.vatPhong !== undefined ? Number(bill.vatPhong) : Math.round((Number(bill.tienPhong) - Number(bill.tienGiamKM || 0)) * 0.08);
    renderOrderSummaryMarkup({
      nights: nights,
      roomLines: roomLines,
      subtotal: bill.tienPhong,
      discountAmount: bill.tienGiamKM || 0,
      discountLabel: (bill.thongTinKM && bill.thongTinKM.tenKM) || ($("promoCodeInput") ? $("promoCodeInput").value : ""),
      vat: vatPhong,
      total: bill.tongTienSauVAT
    });
  }

  function calculateAndReviewBill() {
    if (!ctx) return Promise.resolve();
    var promo = ($("promoCodeInput") ? $("promoCodeInput").value : "").trim();
    var phongDaChon = ctx.lines.map(function (l) {
      return { maLoaiPhong: l.typeId, soLuong: l.qty };
    });

    var u = getCurrentUser();
    var isCounter = isCounterBooking();
    var maKH = currentBookerMaKH || (u && u.MaKH) || "";
    var maTK = isCounter ? "" : ((u && u.MaTK) || "");
    var sdt = ($("bookerPhone") ? $("bookerPhone").value.trim() : "") || (u && u.SDT) || "";

    return fetch(API_BASE + "/datphong/tinhchiphi", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        ngayNhan: ctx.checkIn,
        ngayTra: ctx.checkOut,
        soNguoiLon: ctx.adults,
        soTreEm: ctx.children,
        soPhong: ctx.rooms,
        phongDaChon: phongDaChon,
        maKM: promo,
        maKH: maKH,
        maTK: maTK,
        sdt: sdt,
        dichVuDaChon: []
      })
    })
      .then(function (r) { return r.json(); })
      .then(function (json) {
        var res = unwrapRes(json);
        if (!res.ok) {
          renderFallbackOrder();
          return;
        }
        currentBillData = res.data;

        var promoMsg = $("promoMessage");
        var notSelectedView = $("promoNotSelectedView");
        var selectedView = $("promoSelectedCard");

        if (promo && currentBillData.thongTinKM && currentBillData.thongTinKM.hopLe) {
          if (notSelectedView) notSelectedView.style.display = "none";
          if (selectedView) selectedView.style.display = "block";
          if ($("selectedPromoCodeBadge")) $("selectedPromoCodeBadge").textContent = promo;
          if ($("selectedPromoName")) $("selectedPromoName").textContent = currentBillData.thongTinKM.tenKM || promo;
          if ($("selectedPromoDiscountDesc")) {
            $("selectedPromoDiscountDesc").textContent = "Đã giảm " + money(currentBillData.tienGiamKM || 0);
          }
          if (promoMsg) {
            promoMsg.className = "msg ok";
            promoMsg.textContent = currentBillData.thongTinKM.thongBao || "";
          }
        } else {
          if (notSelectedView) notSelectedView.style.display = "block";
          if (selectedView) selectedView.style.display = "none";
          if (promo && promoMsg) {
            promoMsg.className = "msg";
            promoMsg.textContent = (currentBillData.thongTinKM && currentBillData.thongTinKM.thongBao) || "Mã khuyến mãi không hợp lệ hoặc chưa đủ điều kiện.";
          } else if (promoMsg) {
            promoMsg.textContent = "";
          }
        }

        renderBillSummary(currentBillData);
      })
      .catch(function () {
        renderFallbackOrder();
      });
  }

  /* ---------------- Hệ thống Modal Chọn Mã Khuyến Mãi (/khuyenmai/danhsach) ---------------- */
  function openPromoModal() {
    var modal = $("promoModal");
    if (!modal || !ctx) return;
    modal.style.display = "flex";
    var listContainer = $("promoModalList");
    listContainer.innerHTML = '<div style="text-align:center;padding:30px;color:var(--muted);">⏳ Đang kiểm tra các voucher ưu đãi khả dụng từ hệ thống…</div>';

    var u = getCurrentUser();
    var isCounter = isCounterBooking();
    var maKH = currentBookerMaKH || (u && u.MaKH) || "";
    var maTK = isCounter ? "" : ((u && u.MaTK) || "");
    var sdt = ($("bookerPhone") ? $("bookerPhone").value.trim() : "") || (u && u.SDT) || "";
    var soDem = ctx.nights || 1;
    var soNguoi = (ctx.adults || 1) + (ctx.children || 0);

    var url =
      API_BASE +
      "/khuyenmai/danhsach?maKH=" + encodeURIComponent(maKH) +
      "&maTK=" + encodeURIComponent(maTK) +
      "&sdt=" + encodeURIComponent(sdt) +
      "&soDem=" + encodeURIComponent(soDem) +
      "&soNguoi=" + encodeURIComponent(soNguoi) +
      "&ngay=" + encodeURIComponent(ctx.checkIn);

    fetch(url)
      .then(function (r) { return r.json(); })
      .then(function (json) {
        var res = unwrapRes(json);
        var data = res.data;
        var vouchers = (data && data.active_vouchers) || [];
        var soPhieu = (data && data.so_phieu_khach_hang) || 0;
        renderPromoModalList(vouchers, soPhieu);
      })
      .catch(function (e) {
        listContainer.innerHTML = '<div style="text-align:center;padding:24px;color:var(--err);">❌ Không thể tải danh sách khuyến mãi: ' + esc(e.message) + "</div>";
      });
  }

  function closePromoModal() {
    var modal = $("promoModal");
    if (modal) modal.style.display = "none";
  }

  function renderPromoModalList(vouchers, soPhieu) {
    var listContainer = $("promoModalList");
    if (!listContainer) return;

    if (!vouchers || !vouchers.length) {
      listContainer.innerHTML =
        '<div style="text-align:center;padding:32px 16px;color:var(--muted);">' +
        '<div style="font-size:36px;margin-bottom:8px;">📭</div>' +
        '<div style="font-weight:700;color:var(--primary);font-size:15px;margin-bottom:6px;">Không có mã khuyến mãi nào khả dụng</div>' +
        '<div style="font-size:12.5px;line-height:1.5;max-width:420px;margin:0 auto;">' +
        (soPhieu > 0
          ? "Các ưu đãi dành riêng cho khách hàng mới chỉ áp dụng cho lần đặt phòng đầu tiên. Hiện tại các mã khác có thể đã hết lượt hoặc chưa đến ngày áp dụng."
          : "Hiện tại tất cả voucher đã hết lượt sử dụng hoặc không phù hợp với thời gian lưu trú đã chọn.") +
        "</div>" +
        "</div>";
      return;
    }

    var currentPromo = ($("promoCodeInput") ? $("promoCodeInput").value : "").trim().toUpperCase();
    var html = "";

    vouchers.forEach(function (v) {
      var isSelected = currentPromo === String(v.MaKM || "").toUpperCase();
      var conLai = v.SoLuongConLai !== undefined ? Number(v.SoLuongConLai) : ((Number(v.SoLuong) || 100) - (Number(v.SoLuongDaDung) || 0));
      var isOutOfStock = conLai <= 0;
      var isEligible = v.DuDieuKien !== false;

      var cardClass = isSelected ? "selected" : (isOutOfStock ? "disabled" : (!isEligible ? "not-eligible" : ""));
      var badgeColor = conLai > 10 ? "#15803d" : (conLai > 3 ? "#b45309" : "#b91c1c");
      var badgeBg = conLai > 10 ? "rgba(22,163,74,0.12)" : (conLai > 3 ? "rgba(245,158,11,0.15)" : "rgba(220,38,38,0.12)");

      var actionBtnHtml = "";
      if (isOutOfStock) {
        actionBtnHtml = '<button type="button" class="btn-voucher-apply disabled" disabled>Hết lượt</button>';
      } else if (!isEligible) {
        actionBtnHtml = '<button type="button" class="btn-voucher-apply disabled" disabled>Chưa đủ điều kiện</button>';
      } else if (isSelected) {
        actionBtnHtml = '<button type="button" class="btn-voucher-apply active">✓ Đang áp dụng</button>';
      } else {
        actionBtnHtml = '<button type="button" class="btn-voucher-apply" data-select-voucher="' + esc(v.MaKM) + '">Áp dụng mã này</button>';
      }

      html +=
        '<div class="voucher-card ' + cardClass + '">' +
        '<div class="voucher-left">' +
        '<div class="voucher-discount-val">' + esc(v.GiaTriHienThi) + '</div>' +
        '<div class="voucher-discount-label">GIẢM GIÁ</div>' +
        '</div>' +
        '<div class="voucher-right">' +
        '<div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-bottom:5px;">' +
        '<span class="voucher-code-tag">' + esc(v.MaKM) + '</span>' +
        (v.LaKhachMoi ? '<span class="voucher-new-tag">🌟 KHÁCH MỚI (LẦN ĐẦU)</span>' : '') +
        '<span style="margin-left:auto;font-size:11.5px;font-weight:700;color:' + badgeColor + ';background:' + badgeBg + ';padding:2px 8px;border-radius:6px;">Còn lại: ' + conLai + ' lượt</span>' +
        '</div>' +
        '<div style="font-weight:700;color:var(--primary);font-size:14px;margin-bottom:4px;">' + esc(v.TenKM) + '</div>' +
        '<div style="font-size:12px;color:var(--muted);margin-bottom:8px;">📌 Điều kiện: <b>' + esc(v.DieuKien || "Không giới hạn") + '</b> | HSD: ' + esc(v.NgayKetThuc || "Vô thời hạn") + '</div>' +
        (!isEligible
          ? '<div class="voucher-not-eligible-msg">⚠️ Chưa đủ điều kiện áp dụng' + (v.DieuKien && v.DieuKien !== "Không giới hạn" ? " (" + esc(v.DieuKien) + ")" : "") + '</div>'
          : '') +
        '<div style="text-align:right;">' + actionBtnHtml + '</div>' +
        '</div>' +
        '</div>';
    });

    listContainer.innerHTML = html;
    listContainer.querySelectorAll("[data-select-voucher]").forEach(function (btn) {
      btn.onclick = function () {
        var code = btn.getAttribute("data-select-voucher");
        if ($("promoCodeInput")) $("promoCodeInput").value = code;
        closePromoModal();
        showToast("Đang kiểm tra và áp dụng mã " + code + "...", "info");
        calculateAndReviewBill();
      };
    });
  }

  function removePromoCode() {
    if ($("promoCodeInput")) $("promoCodeInput").value = "";
    if ($("promoNotSelectedView")) $("promoNotSelectedView").style.display = "block";
    if ($("promoSelectedCard")) $("promoSelectedCard").style.display = "none";
    if ($("promoMessage")) $("promoMessage").textContent = "";
    showToast("Đã hủy áp dụng mã khuyến mãi.", "info");
    calculateAndReviewBill();
  }

  /* ---------------- Chế độ Lễ tân / Đặt phòng tại quầy ---------------- */
  function getSubmitButtonLabel(method) {
    return method === "TienMat" ? "Xác nhận đặt phòng" : "Tiếp tục thanh toán";
  }

  function setupPaymentModeUI() {
    var counterPayArea = $("counterPaymentChoiceArea");
    var onlinePayArea = $("onlinePaymentInfoArea");
    var nextBtn = $("next");

    if (counterPayArea) counterPayArea.style.display = "none";
    if (onlinePayArea) onlinePayArea.style.display = "block";
    selectedPaymentMethod = "ChuyenKhoan";
    if (nextBtn) nextBtn.textContent = getSubmitButtonLabel("ChuyenKhoan");
  }

  function handlePaymentMethodChange(val) {
    selectedPaymentMethod = val;
    var btn = $("next");
    var lblTM = $("lblRadioTienMat");
    var lblCK = $("lblRadioChuyenKhoan");
    var note = $("paymentMethodNote");

    if (val === "TienMat") {
      if (btn) btn.textContent = getSubmitButtonLabel("TienMat");
      if (lblTM) lblTM.classList.add("active");
      if (lblCK) lblCK.classList.remove("active");
      if (note) {
        note.innerHTML = "Xác nhận thu tiền mặt trực tiếp tại quầy để hoàn tất phiếu đặt phòng.";
      }
    } else {
      if (btn) btn.textContent = getSubmitButtonLabel("ChuyenKhoan");
      if (lblCK) lblCK.classList.add("active");
      if (lblTM) lblTM.classList.remove("active");
      if (note) {
        note.innerHTML = "Bước tiếp theo sẽ tạo mã QR VietQR (MBBank Napas 247) để quét thanh toán.";
      }
    }
  }

  /* ---------------- Kiểm tra hợp lệ & Gửi đơn đặt phòng (/datphong/datphong) ---------------- */
  function validateBeforeSubmit() {
    if (!ctx) return false;
    var phoneInput = $("bookerPhone");
    var nameInput = $("bookerName");
    var bookerPhone = phoneInput ? phoneInput.value.trim() : "";
    var bookerName = nameInput ? nameInput.value.trim() : "";

    if (!bookerPhone) {
      showToast("Vui lòng nhập Số điện thoại người đặt phòng!", "warning");
      if (phoneInput) phoneInput.focus();
      return false;
    }
    var phoneCheck = validatePhoneNumber(bookerPhone);
    if (!phoneCheck.valid) {
      showToast("Thông tin người đặt: " + phoneCheck.message, "warning");
      if (phoneInput) phoneInput.focus();
      return false;
    }
    if (!bookerName) {
      showToast("Vui lòng nhập Họ và tên người đặt phòng!", "warning");
      if (nameInput) nameInput.focus();
      return false;
    }
    if (!/^[\p{L}\s]{2,100}$/u.test(bookerName.replace(/\s+/g, " "))) {
      showToast("Họ và tên người đặt phòng không được chứa số hoặc ký tự đặc biệt!", "warning");
      if (nameInput) nameInput.focus();
      return false;
    }

    var adultsTarget = ctx.adults || 1;
    var childrenTarget = ctx.children || 0;
    var checkInDate = ctx.checkIn;
    var cards = document.querySelectorAll(".guest-card");
    if (!cards.length) {
      showToast("Chưa có biểu mẫu người lưu trú nào được tạo!", "warning");
      return false;
    }

    var currentAdults = 0;
    var currentChildren = 0;

    for (var i = 0; i < cards.length; i++) {
      var card = cards[i];
      var roomIdx = parseInt(card.dataset.roomindex, 10) + 1;
      var name = card.querySelector(".g-name").value.trim();
      var dob = getGuestDobIso(card.querySelector(".g-dob"));
      var nat = card.querySelector(".g-nat").value;
      var idType = card.querySelector(".g-idtype").value;
      var idNum = card.querySelector(".g-idnum").value.trim();
      var guestLabel = "Biểu mẫu #" + (i + 1) + " (Phòng " + roomIdx + ")";

      if (!name) {
        showToast(guestLabel + ": Vui lòng nhập đầy đủ Họ và tên!", "warning");
        card.querySelector(".g-name").focus();
        return false;
      }
      if (!/^[\p{L}\s]{2,100}$/u.test(name.replace(/\s+/g, " "))) {
        showToast(guestLabel + ": Họ và tên không được chứa số hoặc ký tự đặc biệt!", "warning");
        card.querySelector(".g-name").focus();
        return false;
      }
      if (!dob) {
        showToast(guestLabel + " (" + name + "): Vui lòng chọn Ngày sinh!", "warning");
        card.querySelector(".g-dob").focus();
        return false;
      }
      var age = calcAgeAtDate(dob, checkInDate);
      if (age === -1) {
        showToast(guestLabel + " (" + name + "): Ngày sinh không thể nằm sau ngày nhận phòng (" + checkInDate + ")!", "warning");
        card.querySelector(".g-dob").focus();
        return false;
      }
      if (age <= 11) currentChildren++;
      else currentAdults++;

      if (!nat) {
        showToast(guestLabel + " (" + name + "): Vui lòng chọn Quốc tịch!", "warning");
        card.querySelector(".g-nat").focus();
        return false;
      }
      if (!idType) {
        showToast(guestLabel + " (" + name + "): Vui lòng chọn Loại giấy tờ tùy thân!", "warning");
        card.querySelector(".g-idtype").focus();
        return false;
      }
      if (!idNum) {
        showToast(guestLabel + " (" + name + "): Vui lòng nhập Số giấy tờ tùy thân!", "warning");
        card.querySelector(".g-idnum").focus();
        return false;
      }
      var docCheck = validateDocumentNumber(idType, idNum);
      if (!docCheck.valid) {
        showToast(guestLabel + " (" + name + "): " + docCheck.message, "warning");
        card.querySelector(".g-idnum").focus();
        return false;
      }
    }

    if (currentAdults !== adultsTarget || currentChildren !== childrenTarget) {
      showToast(
        "Cơ cấu người lưu trú tính theo ngày sinh không khớp với số lượng khách đã chọn!\n" +
        "• Đăng ký ban đầu: " + adultsTarget + " Người lớn, " + childrenTarget + " Trẻ em\n" +
        "• Khai báo theo ngày sinh: " + currentAdults + " Người lớn, " + currentChildren + " Trẻ em",
        "warning"
      );
      return false;
    }

    return true;
  }

  async function submitBookingAndPayment() {
    if (submitting || !ctx) return;
    if (!validateBeforeSubmit()) return;

    var isCounter = isCounterBooking();
    var u = getCurrentUser();

    var blocks = document.querySelectorAll(".room-guest-block");
    var nguoiLuuTruTheoPhong = [];

    for (var rIdx = 0; rIdx < blocks.length; rIdx++) {
      var b = blocks[rIdx];
      var maLoai = b.dataset.maloai;
      var cards = b.querySelectorAll(".guest-card");
      var guestList = [];

      for (var cIdx = 0; cIdx < cards.length; cIdx++) {
        var card = cards[cIdx];
        guestList.push({
          hoTen: card.querySelector(".g-name").value.trim(),
          ngaySinh: getGuestDobIso(card.querySelector(".g-dob")),
          quocTich: card.querySelector(".g-nat").value,
          loaiGiayTo: card.querySelector(".g-idtype").value,
          soGiayTo: card.querySelector(".g-idnum").value.trim(),
          ghiChu: card.querySelector(".g-note").value.trim()
        });
      }

      nguoiLuuTruTheoPhong.push({
        maLoaiPhong: maLoai,
        index: rIdx,
        khach: guestList
      });
    }

    var phongDaChon = ctx.lines.map(function (l) {
      return { maLoaiPhong: l.typeId, soLuong: l.qty };
    });

    var promo = ($("promoCodeInput") ? $("promoCodeInput").value : "").trim();
    var paymentMethod = isCounter ? selectedPaymentMethod : "ChuyenKhoan";

    if (paymentMethod === "TienMat") {
      var tongTien = currentBillData ? currentBillData.tongTienSauVAT : 0;
      var confirmMsg =
        "XÁC NHẬN THU TIỀN MẶT TẠI QUẦY:\n\n" +
        "- Số tiền thu: " + money(tongTien) + "\n" +
        "- Kênh đặt: Trực tiếp (Tại quầy)\n" +
        "- Người thao tác: " + ((u && (u.HoTen || u.TenDangNhap || u.MaLeTan)) || "Nhân viên lễ tân") + "\n\n" +
        "Lễ tân xác nhận đã nhận đủ tiền mặt từ khách hàng để tạo phiếu?";
      var confirmCash = window.SkyUI ? await window.SkyUI.confirm(confirmMsg) : false;
      if (!confirmCash) return;
    }

    submitting = true;
    var btn = $("next");
    if (btn) {
      btn.disabled = true;
      btn.textContent = "⏳ Đang xử lý đặt phòng...";
    }

    var bookerPhone = $("bookerPhone") ? $("bookerPhone").value.trim() : "";
    var bookerName = $("bookerName") ? $("bookerName").value.trim() : "";
    var bookerMaKH = currentBookerMaKH || (u && u.MaKH) || null;
    var maTK = (u && u.MaTK) ? u.MaTK : "TK004";

    fetch(API_BASE + "/datphong/datphong", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        maTK: maTK,
        kenhDat: isCounter ? "TrucTiep" : "TrucTuyen",
        maLeTan: isCounter ? ((u && u.MaLeTan) || null) : null,
        maKH: bookerMaKH,
        sdtNguoiDat: bookerPhone,
        tenNguoiDat: bookerName,
        ngayNhan: ctx.checkIn,
        ngayTra: ctx.checkOut,
        soNguoiLon: ctx.adults,
        soTreEm: ctx.children,
        soPhong: ctx.rooms,
        phongDaChon: phongDaChon,
        nguoiLuuTruTheoPhong: nguoiLuuTruTheoPhong,
        maKM: promo,
        dichVuDaChon: [],
        hinhThuc: paymentMethod
      })
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        submitting = false;
        if (btn) {
          btn.disabled = false;
          btn.textContent = getSubmitButtonLabel(paymentMethod);
        }

        var res = unwrapRes(data);
        if (!res.ok) {
          showToast("Đặt phòng thất bại: " + (res.message || "Lỗi máy chủ"), "error");
          return;
        }

        var bookingData = res.data;

        try { localStorage.removeItem("sky_sel"); } catch (e) { }

        if (paymentMethod === "TienMat") {
          showSuccessResult(bookingData);
        } else {
          window.pendingBookingData = bookingData;
          saveBookingFlowState("pending_qr", bookingData);
          setStepBar(2);
          openVietQRModal(bookingData);
        }
      })
      .catch(function (e) {
        submitting = false;
        if (btn) {
          btn.disabled = false;
          btn.textContent = getSubmitButtonLabel(paymentMethod);
        }
        showToast("Lỗi kết nối khi đặt phòng: " + e.message, "error");
      });
  }

  /* ---------------- Bước 2: Modal Thanh Toán Chuyển Khoản VietQR MBBank ---------------- */
  function stopQRMonitoring() {
    if (qrTimerInterval) {
      clearInterval(qrTimerInterval);
      qrTimerInterval = null;
    }
    if (qrPaymentWatcher) {
      qrPaymentWatcher.stop();
      qrPaymentWatcher = null;
    }
  }

  function markQRExpiredUI() {
    var badge = $("qrCountdownBadge");
    if (badge) badge.textContent = "⏱️ Hết hạn 20 phút";
    var statusEl = $("qrAutoSyncStatus");
    if (statusEl) {
      statusEl.textContent = "✕ Giao dịch đã hết hạn hoặc bị hủy";
      statusEl.classList.add("is-expired");
    }
    var btnConfirm = $("btnConfirmPaid");
    if (btnConfirm) {
      btnConfirm.disabled = true;
      btnConfirm.style.opacity = "0.5";
      btnConfirm.textContent = "✕ ĐÃ HẾT HẠN THANH TOÁN (20 PHÚT)";
    }
  }

  function completeVietQRPaymentSuccess(bookingData, toastMsg) {
    stopQRMonitoring();
    var modal = $("modalVietQR");
    if (modal) modal.style.display = "none";

    if (bookingData) {
      bookingData.trangThaiPhieu = "DaDatPhong";
      if (bookingData.thanhToan) bookingData.thanhToan.trangThai = "HoanTat";
      showSuccessResult(bookingData);
    }
    showToast(toastMsg || "Xác nhận thanh toán chuyển khoản thành công!", "success");
  }

  function openVietQRModal(bookingData) {
    var modal = $("modalVietQR");
    if (!modal) return;
    stopQRMonitoring();

    var tt = bookingData.thanhToan || {};
    var tongTien = bookingData.chiPhiDuKien ? bookingData.chiPhiDuKien.tongTienSauVAT : (bookingData.tongTien || 0);
    var amount = Math.round(tongTien);
    var maHD = bookingData.maHD || bookingData.maPhieu || "";
    var memo = tt.noiDung || ("THANHTOAN " + maHD);
    var accountNo = tt.stk || tt.accountNo || "0589822346";
    var accountName = tt.chuTK || tt.accountName || "NGUYEN HOANG ANH";
    var qrUrl = tt.qrUrl || ("https://img.vietqr.io/image/MB-" + accountNo + "-qr_only.png?amount=" + amount + "&addInfo=" + encodeURIComponent(memo) + "&accountName=" + encodeURIComponent(accountName));

    if (window.SkyMBBankQR && $("vietQrImg")) {
      window.SkyMBBankQR.applyQrImage($("vietQrImg"), {
        amount: amount,
        orderCode: maHD,
        memo: memo,
        accountNo: accountNo,
        accountName: accountName,
        fallbackUrl: qrUrl
      });
    } else if ($("vietQrImg")) {
      $("vietQrImg").src = qrUrl;
    }

    if ($("qrAccountNum")) $("qrAccountNum").textContent = accountNo;
    if ($("qrAccountName")) $("qrAccountName").textContent = accountName;
    if ($("qrAmountText")) $("qrAmountText").textContent = money(tongTien);
    if ($("qrTransferMemo")) $("qrTransferMemo").textContent = memo;

    var statusEl = $("qrAutoSyncStatus");
    if (statusEl) {
      statusEl.textContent = "🔄 Đang chờ quét mã & nhận tiền tự động...";
      statusEl.classList.remove("is-expired");
    }

    var simLink = $("btnOpenMbSim");
    if (simLink && window.SkyMBBankQR) {
      var refreshSimLink = function () {
        simLink.href = window.SkyMBBankQR.buildSimulatorUrl({
          amount: amount,
          orderCode: maHD,
          memo: memo,
          accountNo: accountNo,
          accountName: accountName,
          bank: "MB"
        });
      };
      refreshSimLink();
      if ($("btnConfigMbSim")) {
        $("btnConfigMbSim").onclick = function () {
          var nextUrl = window.SkyMBBankQR.promptSimulatorBaseUrl();
          refreshSimLink();
          if (window.SkyMBBankQR && $("vietQrImg")) {
            window.SkyMBBankQR.applyQrImage($("vietQrImg"), {
              amount: amount,
              orderCode: maHD,
              memo: memo,
              accountNo: accountNo,
              accountName: accountName,
              fallbackUrl: qrUrl
            });
          }
          showToast("Đã cập nhật link MBBank Simulator: " + nextUrl, "success");
        };
      }
    }

    window.currentQrMemo = memo;
    window.currentQrAmount = amount;
    window.currentBookingData = bookingData;

    var expireSec = (bookingData._remainingSeconds !== undefined)
      ? bookingData._remainingSeconds
      : (bookingData.thoiHanGiay || (tt && tt.thoiHanGiay) || 1200);
    startQRCountdown(expireSec, bookingData);

    if (window.SkyMBBankQR && bookingData.maHD) {
      qrPaymentWatcher = window.SkyMBBankQR.startPaymentWatcher({
        apiBase: API_BASE,
        maHD: bookingData.maHD,
        intervalMs: 2000,
        onPaid: function () {
          completeVietQRPaymentSuccess(bookingData, "🎉 Đã nhận chuyển khoản MBBank! Đặt phòng thành công!");
        },
        onExpired: function () {
          stopQRMonitoring();
          markQRExpiredUI();
          showToast("⏱️ Giao dịch đã hết hạn hoặc bị hủy.", "error");
        }
      });
    }

    var btnConfirm = $("btnConfirmPaid");
    if (btnConfirm) {
      btnConfirm.disabled = false;
      btnConfirm.style.opacity = "1";
      btnConfirm.textContent = "✅ TÔI ĐÃ CHUYỂN KHOẢN THÀNH CÔNG";
    }

    modal.style.display = "flex";
  }

  function startQRCountdown(seconds, bookingData) {
    if (qrTimerInterval) clearInterval(qrTimerInterval);
    var rem = seconds;
    var badge = $("qrCountdownBadge");

    function updateDisplay() {
      var m = Math.floor(rem / 60);
      var s = rem % 60;
      if (badge) badge.textContent = "⏱️ " + String(m).padStart(2, "0") + ":" + String(s).padStart(2, "0");
      if (rem <= 0) {
        stopQRMonitoring();
        markQRExpiredUI();
        if (bookingData && bookingData.maHD) {
          fetch(API_BASE + "/thanhtoan/xem?MaHD=" + encodeURIComponent(bookingData.maHD)).catch(function () { });
        }
        showToast("⏱️ ĐÃ HẾT HẠN GIỮ PHÒNG 20 PHÚT! Phiếu đã hủy và phòng đã được nhả lại.", "error");
      }
      rem--;
    }

    updateDisplay();
    qrTimerInterval = setInterval(updateDisplay, 1000);
  }

  function closeVietQRModal() {
    var modal = $("modalVietQR");
    if (modal) modal.style.display = "none";
    stopQRMonitoring();

    if (window.pendingBookingData) {
      var maHD = window.pendingBookingData.maHD;
      if (maHD) {
        fetch(API_BASE + "/thanhtoan/xem?MaHD=" + encodeURIComponent(maHD))
          .then(function (r) { return r.json(); })
          .then(function (data) {
            var res = unwrapRes(data);
            if (res.ok && res.data) {
              var stData = res.data;
              window.pendingBookingData.trangThaiPhieu =
                stData.TrangThaiThanhToan === "HoanTat"
                  ? "DaDatPhong"
                  : (stData.DaHetHan ? "ThanhToanThatBai" : "ChoThanhToan");
            }
            showSuccessResult(window.pendingBookingData);
          })
          .catch(function () {
            showSuccessResult(window.pendingBookingData);
          });
      } else {
        showSuccessResult(window.pendingBookingData);
      }
    }
  }

  function confirmPaymentSuccess() {
    var btn = $("btnConfirmPaid");
    if (btn) {
      btn.disabled = true;
      btn.textContent = "⏳ Đang xác thực với ngân hàng...";
    }

    var bData = window.pendingBookingData || window.currentBookingData;
    var maHD = bData ? bData.maHD : "";
    var tongTien = bData ? (bData.chiPhiDuKien ? bData.chiPhiDuKien.tongTienSauVAT : (bData.tongTien || 0)) : 0;

    fetch(API_BASE + "/thanhtoan/mophong", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        MaHD: maHD,
        SoTien: tongTien,
        NoiDung: "THANHTOAN " + maHD
      })
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data && data.success) {
          completeVietQRPaymentSuccess(bData, "Xác nhận thanh toán chuyển khoản thành công!");
        } else {
          showToast((data && (data.error || data.message)) || "Không thể xác thực giao dịch!", "error");
          if (btn) {
            btn.disabled = false;
            btn.textContent = "✅ TÔI ĐÃ CHUYỂN KHOẢN THÀNH CÔNG";
          }
        }
      })
      .catch(function (e) {
        showToast("Lỗi kết nối kiểm tra thanh toán: " + e.message, "error");
        if (btn) {
          btn.disabled = false;
          btn.textContent = "✅ TÔI ĐÃ CHUYỂN KHOẢN THÀNH CÔNG";
        }
      });
  }

  /* ---------------- Bước 3: Hiển thị Màn hình Đặt Phòng Thành Công ---------------- */
  function showSuccessResult(res) {
    if (res && res.data && (!res.danhSachPhong || !Array.isArray(res.danhSachPhong))) {
      res = res.data;
    }
    var qrModal = $("modalVietQR");
    if (qrModal) qrModal.style.display = "none";
    stopQRMonitoring();
    saveBookingFlowState("done", res);
    setStepBar(3);

    var mainCols = $("bookingMainCols");
    if (mainCols) mainCols.style.display = "none";

    var successBox = $("successBox");
    var tongTien = res.chiPhiDuKien ? res.chiPhiDuKien.tongTienSauVAT : (res.tongTien || 0);
    var tt = res.thanhToan || {};
    var isTienMat = tt.hinhThuc === "TienMat" || selectedPaymentMethod === "TienMat";
    var st = res.trangThaiPhieu || (isTienMat ? "DaDatPhong" : "ChoThanhToan");

    var hinhThucBadge = "";
    if (st === "ChoThanhToan") {
      hinhThucBadge = '<span style="background:rgba(245,158,11,0.15);color:#b45309;border:1px solid #f59e0b;padding:3px 10px;border-radius:20px;font-weight:700;font-size:12.5px;">⏳ Chờ thanh toán (Giữ phòng trong 20 phút)</span>';
    } else if (st === "ThanhToanThatBai") {
      hinhThucBadge = '<span style="background:rgba(220,38,38,0.12);color:#b91c1c;border:1px solid #ef4444;padding:3px 10px;border-radius:20px;font-weight:700;font-size:12.5px;">❌ Thanh toán thất bại (Quá hạn 20 phút)</span>';
    } else {
      hinhThucBadge = isTienMat
        ? '<span style="background:rgba(22,163,74,0.14);color:#15803d;border:1px solid #16a34a;padding:3px 10px;border-radius:20px;font-weight:700;font-size:12.5px;">📅 Đã đặt phòng (Đã thu Tiền mặt tại quầy)</span>'
        : '<span style="background:rgba(22,163,74,0.14);color:#15803d;border:1px solid #16a34a;padding:3px 10px;border-radius:20px;font-weight:700;font-size:12.5px;">📅 Đã đặt phòng (Đã thanh toán MBBank)</span>';
    }

    var hinhThucDetail = isTienMat
      ? "💵 Tiền mặt (Lễ tân thu trực tiếp tại quầy)"
      : "Chuyển khoản VietQR MBBank: 0589822346 - NGUYEN HOANG ANH";

    if ($("successMsgDetail")) {
      $("successMsgDetail").innerHTML =
        "Chúc mừng quý khách <strong>" + esc(res.khachHang || ($("bookerName") && $("bookerName").value) || "") + "</strong>!<br>" +
        'Mã phiếu đặt phòng: <strong style="color:var(--gold-text,#8a6829);font-size:18px;">#' + esc(res.maPhieu || "") + "</strong>" +
        (res.maHD ? ' &nbsp;|&nbsp; Mã hóa đơn: <strong style="color:#1d4ed8;font-size:18px;">#' + esc(res.maHD) + "</strong>" : "") + "<br>" +
        "Trạng thái phiếu: " + hinhThucBadge + "<br>" +
        "Kênh đặt: <strong>" + (res.kenhDat === "TrucTiep" ? "Trực Tiếp (Tại Quầy)" : "Trực Tuyến") + "</strong>" + (res.maLeTan ? " (Lễ tân: " + esc(res.maLeTan) + ")" : "") + "<br>" +
        "Thời gian: <strong>" + esc(res.ngayNhan || "") + "</strong> đến <strong>" + esc(res.ngayTra || "") + "</strong> (" + (res.soDem || 0) + " đêm)<br>" +
        'Tổng tiền thanh toán: <strong style="color:#15803d;font-size:18px;">' + money(tongTien) + "</strong> (" + hinhThucDetail + ")";
    }

    var btnInvoice = $("btnViewInvoiceSuccess");
    if (btnInvoice && res.maPhieu) {
      btnInvoice.href = "lich-su-dat-phong.html#bookings";
      btnInvoice.onclick = function (e) {
        e.preventDefault();
        if (parent !== window) {
          parent.postMessage({ skyGo: "hist" }, (window.location.protocol === "file:") ? "*" : window.location.origin);
        } else {
          location.href = "lich-su-dat-phong.html#bookings";
        }
      };
      btnInvoice.style.display = "inline-flex";
    }
    if (isCounterBooking() && $("btnReturnToReception")) {
      $("btnReturnToReception").style.display = "inline-flex";
    }

    var roomDisplay = $("allocatedRoomsDisplay");
    if (roomDisplay) {
      roomDisplay.innerHTML = "";
      var rooms = (res && Array.isArray(res.danhSachPhong)) ? res.danhSachPhong : [];
      rooms.forEach(function (p, idx) {
        roomDisplay.innerHTML +=
          '<div class="allocated-badge">🔑 Phòng ' + (idx + 1) + ": " + esc(p.maPhong) + " (" + esc(p.tenLoaiPhong) + ") - " + (p.soNguoi || 1) + " khách</div>";
      });
    }

    var guestsDisplay = $("allocatedGuestsDisplay");
    var guestList = (res && Array.isArray(res.danhSachNguoiLuuTru)) ? res.danhSachNguoiLuuTru : [];
    if (guestsDisplay) {
      if (!guestList.length) {
        guestsDisplay.innerHTML = '<p style="color:var(--muted);font-size:13px;">Chưa có thông tin người lưu trú.</p>';
      } else {
        var tableHtml =
          '<div style="overflow-x:auto;"><table class="guests-result-table"><thead><tr>' +
          "<th>Mã NLT</th><th>Họ và tên</th><th>Ngày sinh</th><th>Độ tuổi / Phân loại</th>" +
          "<th>Quốc tịch</th><th>Loại giấy tờ</th><th>Số giấy tờ</th><th>Phòng</th><th>Ghi chú</th>" +
          "</tr></thead><tbody>";

        guestList.forEach(function (g) {
          var ageDisplay = "-";
          if (g.tuoi !== undefined && g.nhomTuoi) {
            var isChild = g.nhomTuoi === "Trẻ em" || g.tuoi <= 11;
            ageDisplay = isChild
              ? '<span style="color:#1d4ed8;font-weight:600;">🧒 ' + g.tuoi + " tuổi (Trẻ em)</span>"
              : '<span style="color:#15803d;font-weight:600;">🧑 ' + g.tuoi + " tuổi (Người lớn)</span>";
          } else if (g.ngaySinh && res.ngayNhan) {
            var age = calcAgeAtDate(g.ngaySinh, res.ngayNhan);
            var isCh = age <= 11;
            ageDisplay = isCh
              ? '<span style="color:#1d4ed8;font-weight:600;">🧒 ' + age + " tuổi (Trẻ em)</span>"
              : '<span style="color:#15803d;font-weight:600;">🧑 ' + age + " tuổi (Người lớn)</span>";
          }

          tableHtml +=
            "<tr>" +
            '<td><strong style="color:var(--gold-text,#8a6829);">' + esc(g.maNguoiLuuTru) + "</strong></td>" +
            "<td><strong>" + esc(g.hoTen) + "</strong></td>" +
            "<td>" + esc(formatDateOnly(g.ngaySinh)) + "</td>" +
            "<td>" + ageDisplay + "</td>" +
            "<td>" + esc(g.quocTich) + "</td>" +
            "<td>" + esc(g.loaiGiayTo) + "</td>" +
            "<td><code>" + esc(g.soGiayTo) + "</code></td>" +
            "<td>Phòng " + esc(g.maPhong) + " (" + esc(g.tenLoaiPhong) + ")</td>" +
            "<td>" + (g.ghiChu ? esc(g.ghiChu) : '<span style="color:var(--muted);">-</span>') + "</td>" +
            "</tr>";
        });

        tableHtml += "</tbody></table></div>";
        guestsDisplay.innerHTML = tableHtml;
      }
    }

    window.lastBookedMaPhieu = res.maPhieu;
    if (successBox) {
      successBox.style.display = "block";
      successBox.scrollIntoView({ behavior: "smooth" });
    }
  }

  /* ---------------- Xem Chi Tiết Phiếu Nhận Phòng & CTPNP (/datphong/xemctphieu) ---------------- */
  function xemChiTietPhieuDat(maPhieu) {
    var targetMaPhieu = maPhieu || window.lastBookedMaPhieu;
    if (!targetMaPhieu) {
      showToast("Chưa có mã phiếu nhận phòng nào để xem chi tiết.", "warning");
      return;
    }

    var modal = $("modalChiTietPhieu");
    var modalBody = $("modalDetailBody");
    if (!modal || !modalBody) return;

    modalBody.innerHTML = '<div style="text-align:center;padding:30px;color:var(--muted);">⏳ Đang tải chi tiết phiếu <b>' + esc(targetMaPhieu) + "</b> từ hệ thống…</div>";
    modal.style.display = "flex";

    fetch(API_BASE + "/datphong/xemctphieu", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ maPhieu: targetMaPhieu })
    })
      .then(function (r) { return r.json(); })
      .then(function (responseData) {
        var res = unwrapRes(responseData);
        if (!res.ok) {
          modalBody.innerHTML = '<div style="color:var(--err);padding:20px;text-align:center;">❌ Không thể tải chi tiết phiếu: ' + esc(res.message || "Lỗi không xác định") + "</div>";
          return;
        }
        var data = res.data || {};
        var pnp = data.Phieu || {};
        var ctpnpList = data.DanhSachPhong || [];

        modalBody.innerHTML =
          '<div style="font-size:14.5px;font-weight:700;color:var(--primary);margin-bottom:10px;">📌 THÔNG TIN PHIẾU NHẬN PHÒNG (PNP)</div>' +
          '<div class="pnp-info-grid">' +
          '<div class="pnp-info-item"><label>Tên Khách Hàng (TenKH)</label><div class="val">' + esc(pnp.TenKH || pnp.TenKhachHang || "N/A") + '</div></div>' +
          '<div class="pnp-info-item"><label>Mã Phiếu (MaPhieu)</label><div class="val" style="color:var(--gold-text,#8a6829);">#' + esc(pnp.MaPhieu || targetMaPhieu) + '</div></div>' +
          '<div class="pnp-info-item"><label>Ngày Lập (NgayLap)</label><div class="val">' + esc(formatDateOnly(pnp.NgayLap)) + '</div></div>' +
          '<div class="pnp-info-item"><label>Kênh Đặt (KenhDat)</label><div class="val">' + esc(pnp.KenhDat || "TrucTuyen") + '</div></div>' +
          '</div>' +
          '<div style="font-size:14.5px;font-weight:700;color:var(--primary);margin-bottom:10px;display:flex;justify-content:space-between;">' +
          '<span>🏨 CHI TIẾT PHIẾU NHẬN PHÒNG (CTPNP)</span>' +
          '<span style="font-size:12.5px;color:var(--muted);font-weight:normal;">Tổng: <b>' + ctpnpList.length + '</b> phòng</span>' +
          '</div>' +
          '<div style="overflow-x:auto;"><table class="ctpnp-table"><thead><tr>' +
          '<th>Mã Phòng</th><th>Thời Gian Nhận Phòng</th><th>Thời Gian Trả Phòng</th><th>Số Người</th><th>Trạng Thái</th>' +
          '</tr></thead><tbody>' +
          (ctpnpList.length === 0
            ? '<tr><td colspan="5" style="text-align:center;padding:18px;color:var(--muted);">Chưa có dữ liệu phòng cho phiếu này.</td></tr>'
            : ctpnpList.map(function (ct) {
              return (
                '<tr>' +
                '<td><strong style="color:var(--primary);">🔑 ' + esc(ct.MaPhong) + '</strong>' + (ct.TenLoaiPhong ? '<div style="font-size:11.5px;color:var(--muted);">' + esc(ct.TenLoaiPhong) + '</div>' : '') + '</td>' +
                '<td>' + esc(formatDateTime(ct.ThoiGianNhanPhong)) + '</td>' +
                '<td>' + esc(formatDateTime(ct.ThoiGianTraPhong)) + '</td>' +
                '<td><b>' + (ct.SoNguoi || 1) + '</b> người</td>' +
                '<td><span class="status-badge-pnp">' + esc(ct.TrangThai || "DaDat") + '</span></td>' +
                '</tr>'
              );
            }).join("")) +
          '</tbody></table></div>';
      })
      .catch(function (e) {
        modalBody.innerHTML = '<div style="color:var(--err);padding:20px;text-align:center;">❌ Lỗi khi tải chi tiết phiếu: ' + esc(e.message) + "</div>";
      });
  }

  /* ---------------- Khởi tạo trang ---------------- */
  function renderEmpty(msg) {
    $("orderBody").innerHTML = '<div class="msg">' + esc(msg) + '</div><div class="note"><a href="#" id="back">← Quay lại chọn phòng</a></div>';
    if ($("back")) $("back").onclick = function (e) { e.preventDefault(); goRooms(); };
    if ($("next")) $("next").disabled = true;
  }

  function restorePendingQrState(savedFlow) {
    var bData = savedFlow.data;
    var tt = bData.thanhToan || {};
    var totalSec = bData.thoiHanGiay || tt.thoiHanGiay || 1200;
    var elapsedSec = Math.max(0, Math.floor((Date.now() - savedFlow.savedAt) / 1000));
    var remSec = Math.max(0, totalSec - elapsedSec);
    window.pendingBookingData = bData;

    if (bData.maHD) {
      fetch(API_BASE + "/thanhtoan/xem?MaHD=" + encodeURIComponent(bData.maHD))
        .then(function (r) { return r.json(); })
        .then(function (data) {
          var res = unwrapRes(data);
          if (res.ok && res.data) {
            var stData = res.data;
            if (stData.TrangThaiThanhToan === "HoanTat") {
              bData.trangThaiPhieu = "DaDatPhong";
              if (bData.thanhToan) bData.thanhToan.trangThai = "HoanTat";
              showSuccessResult(bData);
              return;
            }
            if (stData.DaHetHan || remSec <= 0) {
              bData.trangThaiPhieu = "ThanhToanThatBai";
              showSuccessResult(bData);
              return;
            }
          }
          bData._remainingSeconds = remSec;
          setStepBar(2);
          openVietQRModal(bData);
        })
        .catch(function () {
          bData._remainingSeconds = remSec;
          setStepBar(2);
          openVietQRModal(bData);
        });
      return;
    }

    bData._remainingSeconds = remSec;
    setStepBar(2);
    openVietQRModal(bData);
  }

  function load() {
    if (window.HotelAPI && window.HotelAPI.isAdminSession && window.HotelAPI.isAdminSession()) {
      clearBookingFlowState();
      if (parent !== window) {
        parent.postMessage({ skyToast: "Tài khoản Quản trị viên không sử dụng chức năng đặt phòng.", skyGo: "mgr" }, "*");
      } else {
        window.location.replace("dashboard-quan-ly.html");
      }
      return;
    }
    fillLoggedInUser();
    var urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get("mode") === "quay" || urlParams.get("fresh") === "1") {
      clearBookingFlowState();
    }
    sel = readSel();
    if (sel) {
      clearBookingFlowState();
    } else {
      var savedFlow = readBookingFlowState();
      if (savedFlow && savedFlow.data && savedFlow.data.maPhieu) {
        if (savedFlow.stage === "done") {
          return showSuccessResult(savedFlow.data);
        }
        if (savedFlow.stage === "pending_qr") {
          return restorePendingQrState(savedFlow);
        }
      }
      return renderEmpty("Bạn chưa chọn phòng hoặc lựa chọn đã hết hạn. Vui lòng chọn phòng trước khi đặt.");
    }

    var targetGuests = computeTargetGuests(sel);
    API.availability({
      checkIn: sel.checkIn,
      checkOut: sel.checkOut,
      guests: sel.guests,
      adults: targetGuests.adults,
      children: targetGuests.children,
      rooms: sel.rooms
    })
      .then(function (d) {
        var lines = [];
        var totalRoomsCount = 0;
        sel.items.forEach(function (it) {
          var t = d.types.filter(function (x) { return x.id === it.typeId; })[0] || API.getRoomType(it.typeId);
          if (!t) return;
          totalRoomsCount += it.qty;
          lines.push({
            typeId: t.id,
            name: t.name,
            qty: it.qty,
            price: t.price,
            capacity: t.capacity,
            bed: t.bed,
            image: t.image
          });
        });
        if (!lines.length) throw { message: "Không tìm thấy hạng phòng đã chọn." };

        ctx = {
          lines: lines,
          nights: d.nights || U.nights(sel.checkIn, sel.checkOut) || 1,
          adults: targetGuests.adults,
          children: targetGuests.children,
          rooms: totalRoomsCount || sel.rooms || 1,
          checkIn: sel.checkIn,
          checkOut: sel.checkOut
        };

        setupPaymentModeUI();
        renderGuestsForm();
        calculateAndReviewBill();
      })
      .catch(function (e) {
        renderEmpty((e && e.message) || "Không thể tải thông tin phòng đã chọn.");
      });
  }

  /* ---------------- Sự kiện giao diện ---------------- */
  if ($("bookerPhone")) $("bookerPhone").addEventListener("input", onBookerPhoneInput);
  if ($("bookerName")) $("bookerName").addEventListener("input", syncBookerNameToGuest);
  if ($("btnAutoFillGuests")) $("btnAutoFillGuests").onclick = autoFillGuests;

  if ($("btnTogglePolicy")) {
    $("btnTogglePolicy").onclick = function () {
      var panel = $("policyDetailPanel");
      if (!panel) return;
      var isOpen = panel.style.display !== "none";
      panel.style.display = isOpen ? "none" : "block";
      $("btnTogglePolicy").textContent = isOpen ? "Xem chi tiết" : "Thu gọn";
    };
  }
  if ($("btnOpenPromoModal")) $("btnOpenPromoModal").onclick = openPromoModal;
  if ($("btnChangePromo")) $("btnChangePromo").onclick = openPromoModal;
  if ($("btnRemovePromo")) $("btnRemovePromo").onclick = removePromoCode;
  if ($("btnClosePromoModal")) $("btnClosePromoModal").onclick = closePromoModal;
  if ($("btnClosePromoModalFoot")) $("btnClosePromoModalFoot").onclick = closePromoModal;
  if ($("promoModal")) {
    $("promoModal").onclick = function (e) {
      if (e.target === $("promoModal")) closePromoModal();
    };
  }

  if ($("radioTienMat")) $("radioTienMat").onchange = function () { handlePaymentMethodChange("TienMat"); };
  if ($("radioChuyenKhoan")) $("radioChuyenKhoan").onchange = function () { handlePaymentMethodChange("ChuyenKhoan"); };

  if ($("next")) $("next").onclick = submitBookingAndPayment;

  if ($("btnCloseVietQR")) $("btnCloseVietQR").onclick = closeVietQRModal;
  if ($("btnConfirmPaid")) $("btnConfirmPaid").onclick = confirmPaymentSuccess;
  if ($("btnCopyAmount")) {
    $("btnCopyAmount").onclick = function () {
      var val = String(window.currentQrAmount || 0);
      navigator.clipboard.writeText(val).then(function () { showToast("Đã sao chép số tiền: " + val, "success"); });
    };
  }
  if ($("btnCopyMemo")) {
    $("btnCopyMemo").onclick = function () {
      var val = String(window.currentQrMemo || "THANHTOAN");
      navigator.clipboard.writeText(val).then(function () { showToast("Đã sao chép nội dung: " + val, "success"); });
    };
  }
  document.querySelectorAll("[data-copy-target]").forEach(function (btn) {
    btn.onclick = function () {
      var t = $(btn.getAttribute("data-copy-target"));
      var val = t ? t.textContent.trim() : "";
      if (val) navigator.clipboard.writeText(val).then(function () { showToast("Đã sao chép: " + val, "success"); });
    };
  });

  if ($("btnViewDetailPhieu")) $("btnViewDetailPhieu").onclick = function () { xemChiTietPhieuDat(); };
  if ($("btnCloseDetailModal")) $("btnCloseDetailModal").onclick = function () { $("modalChiTietPhieu").style.display = "none"; };
  if ($("btnCloseDetailModalFoot")) $("btnCloseDetailModalFoot").onclick = function () { $("modalChiTietPhieu").style.display = "none"; };
  if ($("modalChiTietPhieu")) {
    $("modalChiTietPhieu").onclick = function (e) {
      if (e.target === $("modalChiTietPhieu")) $("modalChiTietPhieu").style.display = "none";
    };
  }

  if ($("linkToAmenityService")) {
    $("linkToAmenityService").onclick = function (e) { e.preventDefault(); goAmenity(); };
  }
  if ($("btnGoAmenityFromSuccess")) {
    $("btnGoAmenityFromSuccess").onclick = function (e) { e.preventDefault(); goAmenity(); };
  }
  if ($("btnGoHistorySuccess")) {
    $("btnGoHistorySuccess").onclick = function () { goHistory(); };
  }

  window.addEventListener("message", function (e) {
    var d = e.data || {};
    if (d.skyInit && d.skyInit.user) {
      fillLoggedInUser();
    }
  });

  window.addEventListener("pageshow", function () {
    if (!readSel()) {
      var savedFlow = readBookingFlowState();
      if (savedFlow && savedFlow.stage === "done" && savedFlow.data && savedFlow.data.maPhieu) {
        showSuccessResult(savedFlow.data);
      }
    }
  });

  load();
})();
