/* Trang Thanh toán: kết nối trực tiếp với Backend thật (HotelAPI) và đồng bộ với phiếu nhận phòng */
(function () {
  "use strict";
  var API = window.SkyAPI, U = API ? API.util : {
    fmtMoney: function (n) { return Math.round(n || 0).toLocaleString("vi-VN") + "đ"; },
    fmtDate: function (s) { var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s || ""); return m ? m[3] + "/" + m[2] + "/" + m[1] : s; }
  };
  var $ = function (i) { return document.getElementById(i); };
  function esc(s) { return String(s == null ? "" : s).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;"); }
  function money(n) { return U.fmtMoney(n); }
  function go(k, href) { if (parent !== window) parent.postMessage({ skyGo: k }, "*"); else location.href = href; }

  var booking = null, tick = null;
  var currentPayMethod = "VietQR";

  function holdText(b) {
    if (b.status !== "pending" && b.status !== "ChoThanhToan") return "";
    var until = b.holdUntil || b.ThoiGianHetHan;
    if (!until) return "";
    var ms = typeof until === "number" ? until : (new Date(until)).getTime();
    var left = Math.max(0, Math.round((ms - Date.now()) / 1000));
    return "Phòng được giữ cho bạn trong " + Math.floor(left / 60) + ":" + ("0" + (left % 60)).slice(-2) + " nữa";
  }

  function problem(msg, linkText, key, href) {
    $("sumBody").innerHTML = '<div class="msg">' + esc(msg) + '</div><p><a href="#" id="lk">' + esc(linkText) + "</a></p>";
    if ($("lk")) $("lk").onclick = function (e) { e.preventDefault(); go(key, href); };
    if ($("pay")) $("pay").disabled = true;
  }

  function render() {
    var b = booking, t = b.totals || {}, h = "";
    var maDon = b.code || b.MaPhieu || "SKY";
    var checkIn = b.checkIn || b.NgayNhan || "";
    var checkOut = b.checkOut || b.NgayTra || "";
    var nights = b.nights || b.soDem || 1;
    var guests = b.guests || b.soNguoi || 2;
    var customerName = (b.customer && b.customer.name) || b.tenNguoiDat || b.HoTenKhachHang || "Khách hàng";

    h += '<div class="rm-head"><small>Mã đặt phòng</small><b>' + esc(maDon) + "</b></div>";
    h += '<small class="dl">📅 ' + U.fmtDate(checkIn) + " – " + U.fmtDate(checkOut) + " · " + nights + " đêm</small>";
    h += '<small class="dl">👤 ' + guests + " khách · Người đặt: " + esc(customerName) + "</small>";

    var gList = b.guestsList || b.nguoiLuuTru || [];
    if (gList.length) {
      h += '<div class="pay-guests-wrap">';
      h += '<div class="pay-guests-hdr"><b>Danh sách khách lưu trú (' + gList.length + ' khách):</b></div>';
      gList.forEach(function (g, idx) {
        var gName = g.name || g.HoTen || "";
        var gId = g.cccd || g.SoGiayTo || "";
        var gDob = g.dob || g.NgaySinh || "";
        var rName = g.roomName || g.MaPhong || "";
        h += '<div class="pay-guest-row">' +
          '<span>' + (idx + 1) + '. <b>' + esc(gName) + '</b>' + (rName ? ' <small style="color:var(--muted)">(' + esc(rName) + ')</small>' : '') + '</span>' +
          '<small>CCCD: ' + esc(gId || "—") + (gDob ? ' · NS: ' + U.fmtDate(gDob) : '') + '</small>' +
          '</div>';
      });
      h += '</div>';
    }

    var items = b.items || b.danhSachPhong || [];
    items.forEach(function (i) {
      var iName = i.name || i.TenLoaiPhong || i.maLoaiPhong || "Phòng nghỉ";
      var iQty = i.qty || i.soLuong || 1;
      var iPrice = i.price || i.donGia || i.Gia || 0;
      h += '<div class="r"><span>' + esc(iName) + " × " + iQty + "<br><small>" + money(iPrice) + " × " + nights + " đêm</small></span><b>" + money(iPrice * nights * iQty) + "</b></div>";
    });

    var subtotal = t.subtotal || t.tienPhong || 0;
    var discount = t.discount || t.tienGiamKM || 0;
    var service = t.service || 0;
    var vat = t.vat || t.vatPhong || 0;
    var totalVal = t.total || t.tongTienSauVAT || b.tongTien || 0;

    if (discount) h += '<div class="r"><span>Mã giảm giá ' + esc(b.promo || (b.thongTinKM && b.thongTinKM.tenKM) || "") + '</span><b style="color:#1a9a5a">-' + money(discount) + "</b></div>";
    if (service) h += '<div class="r"><span>Phí dịch vụ (5%)</span><b>' + money(service) + "</b></div>";
    if (vat) h += '<div class="r"><span>Thuế VAT (8%)</span><b>' + money(vat) + "</b></div>";
    h += '<div class="t"><span>Tổng thanh toán</span><strong>' + money(totalVal) + "</strong></div>";
    h += '<div class="r"><span id="hold" style="color:var(--gold-2)"></span></div>';
    $("sumBody").innerHTML = h;

    renderPromoSection();
    renderPaymentMethodUI(totalVal, maDon);

    var upd = function () {
      var el = $("hold"); if (!el) return;
      el.textContent = holdText(booking);
    };
    upd(); clearInterval(tick); tick = setInterval(upd, 1000);
  }

  function renderPaymentMethodUI(amount, code) {
    var memo = "THANHTOAN " + (booking.maHD || booking.MaHD || code);
    var qrUrl = "https://img.vietqr.io/image/MB-0348041105-compact2.png?amount=" + Math.round(amount) +
      "&addInfo=" + encodeURIComponent(memo) +
      "&accountName=" + encodeURIComponent("DANG LUONG THE ANH");

    var payBox = document.querySelector(".cols .card:first-child");
    if (payBox) {
      payBox.innerHTML =
        '<h3>Phương thức thanh toán</h3>' +
        '<div class="opt on" style="margin-bottom:14px;border:1px solid var(--gold,#b08a4f);padding:14px;border-radius:10px;background:rgba(176,138,79,0.06);">' +
          '<div class="h" style="display:flex;justify-content:space-between;align-items:center;">' +
            '<span><b>Chuyển khoản VietQR (MBBank)</b></span>' +
            '<span style="background:#1a9a5a;color:#fff;font-size:11px;padding:3px 8px;border-radius:12px;font-weight:700;">Khuyên dùng</span>' +
          '</div>' +
          '<div class="sub" style="margin-top:10px;">' +
            '<p style="margin:0 0 10px;font-size:12.5px;color:var(--muted)">Quét mã QR bằng ứng dụng ngân hàng bất kỳ để hoàn tất thanh toán tự động trong giây lát.</p>' +
            '<div style="text-align:center;padding:8px;background:#fff;border-radius:8px;display:inline-block;box-shadow:0 2px 8px rgba(0,0,0,0.08);">' +
              '<img src="' + qrUrl + '" alt="VietQR MBBank" style="width:190px;height:190px;object-fit:contain;display:block;">' +
            '</div>' +
            '<div style="font-size:12px;margin-top:10px;line-height:1.6;">' +
              '<div>Ngân hàng: <b>MBBank (Ngân hàng Quân Đội)</b></div>' +
              '<div>Chủ TK: <b>DANG LUONG THE ANH</b></div>' +
              '<div>Số TK: <b style="letter-spacing:1px">0348041105</b></div>' +
              '<div>Số tiền: <b style="color:var(--gold-text,#926f2f)">' + money(amount) + '</b></div>' +
              '<div>Nội dung CK: <code style="background:var(--gold-soft);padding:2px 6px;border-radius:4px;font-weight:700">' + esc(memo) + '</code></div>' +
            '</div>' +
          '</div>' +
        '</div>';
    }

    var payBtn = $("pay");
    if (payBtn) {
      payBtn.disabled = false;
      payBtn.className = "pay";
      payBtn.textContent = "✅ Tôi đã chuyển khoản xong (Xác nhận đơn)";
      payBtn.onclick = confirmVietQRPayment;
    }
    if ($("e-pay")) $("e-pay").textContent = "";
  }

  function confirmVietQRPayment() {
    if (!booking) return;
    var payBtn = $("pay");
    var maHD = booking.maHD || booking.MaHD || booking.code || booking.MaPhieu;
    var amount = (booking.totals && booking.totals.total) || (booking.chiPhiDuKien && booking.chiPhiDuKien.tongTienSauVAT) || booking.tongTien || 0;

    if (payBtn) {
      payBtn.disabled = true;
      payBtn.textContent = "⏳ Đang xác nhận thanh toán với hệ thống...";
    }

    var doConfirm = (window.HotelAPI && window.HotelAPI.confirmPayment)
      ? window.HotelAPI.confirmPayment(maHD, amount, "THANHTOAN " + maHD)
      : Promise.resolve({ success: true });

    doConfirm.then(function (res) {
      if (payBtn) {
        payBtn.disabled = false;
        payBtn.textContent = "✅ Tôi đã chuyển khoản xong (Xác nhận đơn)";
      }
      booking.status = "paid";
      done(booking);
    }).catch(function (err) {
      if (payBtn) {
        payBtn.disabled = false;
        payBtn.textContent = "✅ Tôi đã chuyển khoản xong (Xác nhận đơn)";
      }
      if ($("e-pay")) $("e-pay").textContent = (err && err.message) || "Không thể xác thực giao dịch, vui lòng thử lại.";
    });
  }

  function renderPromoSection() {
    var codeInput = $("pay-promo-code");
    var msgEl = $("pay-promo-msg");
    var hintsEl = $("pay-promo-hints");
    if (!codeInput) return;

    if (booking && booking.promo) {
      codeInput.value = booking.promo;
      if (msgEl) {
        msgEl.style.color = "#1a9a5a";
        msgEl.textContent = "✓ Đang áp dụng mã: " + booking.promo + (booking.totals && booking.totals.discount ? " (-" + money(booking.totals.discount) + ")" : "");
      }
    } else {
      if (msgEl) {
        msgEl.style.color = "var(--muted)";
        msgEl.textContent = "Bạn có mã giảm giá từ khách sạn? Nhập và bấm Áp dụng.";
      }
    }

    if (hintsEl && window.HotelAPI && window.HotelAPI.getPromos) {
      window.HotelAPI.getPromos({
        nights: booking.nights || 1,
        guests: booking.guests || 2,
        checkIn: booking.checkIn
      }).then(function (res) {
        var d = res.data;
        if (d && d.data) d = d.data;
        var vouchers = (d && d.active_vouchers) || [];
        if (!vouchers.length) return;
        hintsEl.innerHTML = '<span style="font-size:11px;color:var(--muted);width:100%">Mã ưu đãi khả dụng:</span>' +
          vouchers.map(function (p) {
            var isCurrent = booking && booking.promo === p.MaKM;
            return '<button type="button" class="promo-chip" data-pay-code="' + esc(p.MaKM) + '" style="cursor:pointer;border:' + (isCurrent ? '1px solid #1a9a5a' : '1px dashed #b08a4f') + ';background:' + (isCurrent ? '#e8f8f0' : 'rgba(176,138,79,0.08)') + ';color:' + (isCurrent ? '#1a9a5a' : '#725524') + ';font-size:11px;font-weight:600;padding:4px 8px;border-radius:14px;transition:.15s;margin-right:6px;margin-bottom:6px;">' +
              esc(p.MaKM) + ' (' + esc(p.GiaTriHienThi || (p.GiaTriGiam + '%')) + ')' +
            '</button>';
          }).join("");

        hintsEl.querySelectorAll("[data-pay-code]").forEach(function (btn) {
          btn.onclick = function () {
            var c = btn.getAttribute("data-pay-code");
            codeInput.value = c;
            applyPaymentPromo(c);
          };
        });
      }).catch(function () {});
    }
  }

  function applyPaymentPromo(rawCode) {
    if (!booking) return;
    var c = (rawCode !== undefined ? rawCode : ($("pay-promo-code") ? $("pay-promo-code").value : "")).trim().toUpperCase();
    var msgEl = $("pay-promo-msg");
    if (!c) {
      if (msgEl) { msgEl.style.color = "#c0392b"; msgEl.textContent = "Vui lòng nhập mã ưu đãi."; }
      return;
    }

    if (window.HotelAPI && window.HotelAPI.calculateCost) {
      var lines = (booking.items || []).map(function (it) {
        return { maLoaiPhong: it.typeId || it.MaLoaiPhong, soLuong: it.qty || it.soLuong || 1 };
      });
      window.HotelAPI.calculateCost({
        ngayNhan: booking.checkIn,
        ngayTra: booking.checkOut,
        soPhong: booking.rooms || 1,
        soNguoiLon: booking.guests || 2,
        soTreEm: 0,
        phongDaChon: lines,
        maKM: c,
        dichVuDaChon: []
      }).then(function (res) {
        var cost = res.data;
        if (cost && cost.thongTinKM && cost.thongTinKM.hopLe) {
          booking.promo = c;
          booking.totals = {
            subtotal: cost.tienPhong,
            discount: cost.tienGiamKM,
            service: 0,
            vat: cost.vatPhong,
            total: cost.tongTienSauVAT,
            serviceRate: 0,
            vatRate: cost.vatPhongRate || 0.08
          };
          render();
          if (msgEl) {
            msgEl.style.color = "#1a9a5a";
            msgEl.textContent = "✓ " + (cost.thongTinKM.thongBao || ("Đã áp dụng mã " + c));
          }
        } else {
          if (msgEl) {
            msgEl.style.color = "#c0392b";
            msgEl.textContent = (cost && cost.thongTinKM && cost.thongTinKM.thongBao) || "Mã không đủ điều kiện áp dụng.";
          }
        }
      }).catch(function (err) {
        if (msgEl) {
          msgEl.style.color = "#c0392b";
          msgEl.textContent = (err && err.message) || "Không thể kiểm tra mã khuyến mãi.";
        }
      });
    }
  }

  if ($("btn-pay-promo")) {
    $("btn-pay-promo").onclick = function () { applyPaymentPromo(); };
  }

  function load() {
    if (window.HotelAPI && window.HotelAPI.isAdminSession && window.HotelAPI.isAdminSession()) {
      if (parent !== window) {
        parent.postMessage({ skyToast: "Tài khoản Quản trị viên không sử dụng chức năng thanh toán đặt phòng.", skyGo: "mgr" }, "*");
      } else {
        window.location.replace("dashboard-quan-ly.html");
      }
      return;
    }
    var rawData = null;
    try { rawData = JSON.parse(localStorage.getItem("sky_booking_data") || "null"); } catch (e) {}

    var code = null;
    try { code = localStorage.getItem("sky_booking"); } catch (e) {}

    if (rawData && rawData.maPhieu) {
      booking = {
        code: rawData.maPhieu,
        maHD: rawData.maHD,
        checkIn: rawData.ngayNhan,
        checkOut: rawData.ngayTra,
        nights: rawData.chiPhiDuKien ? rawData.chiPhiDuKien.soDem : 1,
        guests: rawData.chiPhiDuKien ? rawData.chiPhiDuKien.tongKhach : 2,
        customer: { name: rawData.tenNguoiDat, phone: rawData.sdtNguoiDat },
        items: (rawData.danhSachPhong || []).map(function (p) {
          return { typeId: p.MaLoaiPhong, name: p.TenLoaiPhong, qty: 1, price: p.Gia };
        }),
        totals: {
          subtotal: rawData.chiPhiDuKien ? rawData.chiPhiDuKien.tienPhong : rawData.tongTien,
          discount: rawData.chiPhiDuKien ? rawData.chiPhiDuKien.tienGiamKM : 0,
          service: 0,
          vat: rawData.chiPhiDuKien ? rawData.chiPhiDuKien.vatPhong : 0,
          total: rawData.chiPhiDuKien ? rawData.chiPhiDuKien.tongTienSauVAT : rawData.tongTien,
          serviceRate: 0,
          vatRate: 0.08
        },
        status: "pending",
        holdUntil: Date.now() + 20 * 60000
      };
      render();
      return;
    }

    if (!code) return problem("Không có đơn đặt phòng đang chờ thanh toán.", "Chọn phòng", "rooms", "chon-phong.html");

    // Lấy thông tin phiếu từ Backend thật
    if (window.HotelAPI && window.HotelAPI.getBookingDetail) {
      window.HotelAPI.getBookingDetail(code).then(function (res) {
        var d = res.data;
        var p = d.Phieu || d.phieu || {};
        var hd = d.HoaDon || d.hoaDon || {};
        var rooms = d.DanhSachPhong || d.danhSachPhong || [];
        var guests = d.NguoiLuuTru || d.nguoiLuuTru || [];
        var th = d.TongHop || d.tongHop || {};

        booking = {
          code: p.MaPhieu || code,
          maHD: hd.MaHD || "",
          checkIn: p.NgayNhan || "",
          checkOut: p.NgayTra || "",
          nights: rooms[0] ? Math.max(1, Math.round(((new Date(rooms[0].NgayTra)) - (new Date(rooms[0].NgayNhan))) / 86400000)) : 1,
          guests: p.SoNguoi || guests.length || 2,
          customer: { name: p.HoTenKhachHang || p.TenKH, phone: p.SDT },
          items: rooms.map(function (r) {
            return { typeId: r.MaLoaiPhong, name: r.TenLoaiPhong || r.MaPhong, qty: 1, price: r.DonGia || r.Gia };
          }),
          totals: {
            subtotal: th.TongTienPhong || hd.TongTienSauVAT || 0,
            discount: 0,
            service: 0,
            vat: 0,
            total: hd.TongTienSauVAT || th.TongThanhToan || 0,
            serviceRate: 0,
            vatRate: 0.08
          },
          status: p.TrangThai === "DaDatPhong" || p.TrangThai === "DaNhanPhong" ? "paid" : "pending",
          holdUntil: Date.now() + 20 * 60000
        };

        if (booking.status === "paid") return done(booking);
        render();
      }).catch(function () {
        problem("Không kết nối được máy chủ hoặc không tìm thấy dữ liệu đơn đặt phòng.", "Chọn phòng", "rooms", "chon-phong.html");
      });
    }
  }

  function done(b) {
    clearInterval(tick);
    $("main").style.display = "none";
    $("done").style.display = "block";
    $("doneTitle").textContent = "Thanh toán & Đặt phòng đã được xác nhận";
    $("s2").className = "st done"; $("s2").firstChild.textContent = "✓";
    $("s3").className = "st on";
    $("doneMsg").textContent = "Mã phiếu #" + (b.code || b.MaPhieu) + " đã chuyển sang trạng thái ĐÃ ĐẶT PHÒNG. Đang chuyển đến Lịch sử đặt phòng…";
    try {
      localStorage.removeItem("sky_booking");
      localStorage.removeItem("sky_booking_data");
    } catch (e) {}
    setTimeout(function () { go("hist", "lich-su-dat-phong.html"); }, 2000);
  }

  load();
})();
