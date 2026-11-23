/* ==========================================================================
   SkyMBBankQR — Helper dùng chung cho thanh toán VietQR + MBBank Simulator
   - Sinh chuỗi VietQR EMVCo chuẩn Napas 247 (CRC16-CCITT)
   - Hiển thị ảnh QR sạch (qr_only) kèm fallback offline từ Node mbbank-simulator
   - Tạo link mở App MBBank giả lập (hỗ trợ cấu hình URL LAN / ngrok)
   - Auto-polling trạng thái /thanhtoan/xem mỗi 2 giây để tự chốt thành công
   ========================================================================== */
(function (root) {
  "use strict";

  var SIM_URL_STORAGE_KEY = "sky_mbbank_sim_url";
  var DEFAULT_BANK_BIN = "970422"; // MBBank
  var DEFAULT_ACCOUNT_NO = "0589822346";
  var DEFAULT_ACCOUNT_NAME = "NGUYEN HOANG ANH";

  function normalizeBaseUrl(url) {
    return String(url || "").trim().replace(/\/+$/, "");
  }

  function getSimulatorBaseUrl() {
    try {
      var saved = localStorage.getItem(SIM_URL_STORAGE_KEY);
      if (saved && /^https?:\/\//i.test(saved.trim())) {
        return normalizeBaseUrl(saved);
      }
    } catch (e) {}

    if (root.SKY_MBBANK_SIM_URL && /^https?:\/\//i.test(String(root.SKY_MBBANK_SIM_URL))) {
      return normalizeBaseUrl(root.SKY_MBBANK_SIM_URL);
    }

    var proto = root.location && root.location.protocol === "https:" ? "https:" : "http:";
    var host = (root.location && root.location.hostname) ? root.location.hostname : "localhost";
    return proto + "//" + host + ":4000";
  }

  function setSimulatorBaseUrl(url) {
    var clean = normalizeBaseUrl(url);
    try {
      if (!clean) localStorage.removeItem(SIM_URL_STORAGE_KEY);
      else localStorage.setItem(SIM_URL_STORAGE_KEY, clean);
    } catch (e) {}
    return getSimulatorBaseUrl();
  }

  function promptSimulatorBaseUrl() {
    var current = getSimulatorBaseUrl();
    var next = root.prompt(
      "Nhập địa chỉ Node MBBank Simulator (VD: http://localhost:4000, http://192.168.1.10:4000 hoặc link https://...ngrok-free.app).\nĐể trống để dùng mặc định:",
      current
    );
    if (next === null) return current;
    return setSimulatorBaseUrl(next);
  }

  function tlv(tag, value) {
    var v = String(value || "");
    return tag + String(v.length).padStart(2, "0") + v;
  }

  function crc16(str) {
    var crc = 0xffff;
    for (var i = 0; i < str.length; i++) {
      crc ^= str.charCodeAt(i) << 8;
      for (var j = 0; j < 8; j++) {
        crc = (crc & 0x8000) ? (((crc << 1) ^ 0x1021) & 0xffff) : ((crc << 1) & 0xffff);
      }
    }
    return crc.toString(16).toUpperCase().padStart(4, "0");
  }

  function generateVietQrEmv(opts) {
    opts = opts || {};
    var bankBin = String(opts.bankBin || DEFAULT_BANK_BIN);
    var accountNumber = String(opts.accountNo || opts.accountNumber || DEFAULT_ACCOUNT_NO);
    var amt = Math.max(0, Math.round(Number(opts.amount) || 0));
    var memo = String(opts.memo || ("THANHTOAN " + (opts.orderCode || ""))).trim();
    var beneficiaryName = String(opts.accountName || opts.beneficiaryName || DEFAULT_ACCOUNT_NAME);
    var city = String(opts.city || "HO CHI MINH");

    var tag38 = tlv("38", tlv("00", "A000000727") + tlv("01", tlv("00", bankBin) + tlv("01", accountNumber)) + tlv("02", "QRIBFTTA"));
    var tag62 = memo ? tlv("62", tlv("08", memo)) : "";
    var raw = [
      tlv("00", "01"),
      tlv("01", amt > 0 ? "12" : "11"),
      tag38,
      tlv("53", "704"),
      amt > 0 ? tlv("54", String(amt)) : "",
      tlv("58", "VN"),
      tlv("59", beneficiaryName),
      tlv("60", city),
      tag62,
      "6304"
    ].join("");
    return raw + crc16(raw);
  }

  function buildQrOnlyUrl(opts) {
    opts = opts || {};
    var acc = opts.accountNo || DEFAULT_ACCOUNT_NO;
    var accName = opts.accountName || DEFAULT_ACCOUNT_NAME;
    var amt = Math.max(0, Math.round(Number(opts.amount) || 0));
    var memo = opts.memo || ("THANHTOAN " + (opts.orderCode || ""));
    return "https://img.vietqr.io/image/MB-" + encodeURIComponent(acc) + "-qr_only.png" +
      "?amount=" + amt +
      "&addInfo=" + encodeURIComponent(memo) +
      "&accountName=" + encodeURIComponent(accName);
  }

  function applyQrImage(imgEl, opts) {
    if (!imgEl) return;
    opts = opts || {};
    var emv = generateVietQrEmv(opts);
    var localPngUrl = getSimulatorBaseUrl() + "/api/qr.png?data=" + encodeURIComponent(emv);
    var primaryUrl = buildQrOnlyUrl(opts);

    imgEl.dataset.qrStage = "primary";
    imgEl.onerror = function () {
      var stage = imgEl.dataset.qrStage || "primary";
      if (stage === "primary") {
        imgEl.dataset.qrStage = "sim";
        imgEl.src = localPngUrl;
      } else if (stage === "sim" && opts.fallbackUrl && opts.fallbackUrl !== primaryUrl) {
        imgEl.dataset.qrStage = "done";
        imgEl.src = opts.fallbackUrl;
      } else {
        imgEl.onerror = null;
      }
    };
    imgEl.src = primaryUrl;
  }

  function buildSimulatorUrl(opts) {
    opts = opts || {};
    var params = new URLSearchParams({
      amount: String(Math.max(0, Math.round(Number(opts.amount) || 0))),
      orderCode: String(opts.orderCode || ""),
      memo: String(opts.memo || ("THANHTOAN " + (opts.orderCode || ""))),
      name: String(opts.accountName || DEFAULT_ACCOUNT_NAME),
      account: String(opts.accountNo || DEFAULT_ACCOUNT_NO),
      bank: String(opts.bank || "MB")
    });
    return getSimulatorBaseUrl() + "/mbbank/index.html?" + params.toString();
  }

  function unwrapStatusPayload(json) {
    if (root.HotelAPI && typeof root.HotelAPI.unwrap === "function") {
      return root.HotelAPI.unwrap(json);
    }
    var d = (json && json.data !== undefined) ? json.data : json;
    if (d && typeof d === "object" && d.data !== undefined) d = d.data;
    return {
      ok: !!(json && json.success !== false),
      data: d,
      message: (json && (json.message || json.error)) || ""
    };
  }

  function startPaymentWatcher(opts) {
    opts = opts || {};
    var apiBase = String(opts.apiBase || "").replace(/\/+$/, "");
    var maHD = String(opts.maHD || "").trim();
    var intervalMs = Number(opts.intervalMs) || 2000;
    if (!apiBase || !maHD) {
      return { stop: function () {}, checkNow: function () {} };
    }

    var stopped = false;
    var inFlight = false;
    var timer = null;

    function stop() {
      if (stopped) return;
      stopped = true;
      if (timer) {
        clearInterval(timer);
        timer = null;
      }
      root.removeEventListener("focus", handleWake);
      if (typeof document !== "undefined") {
        document.removeEventListener("visibilitychange", handleWake);
      }
    }

    function checkNow() {
      if (stopped || inFlight) return;
      inFlight = true;
      fetch(apiBase + "/thanhtoan/xem?MaHD=" + encodeURIComponent(maHD) + "&_t=" + Date.now(), {
        cache: "no-store"
      })
        .then(function (r) { return r.json(); })
        .then(function (json) {
          inFlight = false;
          if (stopped) return;
          var res = unwrapStatusPayload(json);
          var stData = res && res.data;
          if (!res || !res.ok || !stData) return;

          if (stData.TrangThaiThanhToan === "HoanTat") {
            stop();
            if (typeof opts.onPaid === "function") opts.onPaid(stData);
            return;
          }
          if (stData.DaHetHan || stData.TrangThaiThanhToan === "DaHetHan" || stData.TrangThaiThanhToan === "DaHuy") {
            stop();
            if (typeof opts.onExpired === "function") opts.onExpired(stData);
          }
        })
        .catch(function () {
          inFlight = false;
        });
    }

    function handleWake() {
      if (typeof document !== "undefined" && document.hidden) return;
      checkNow();
    }

    timer = setInterval(checkNow, intervalMs);
    root.addEventListener("focus", handleWake);
    if (typeof document !== "undefined") {
      document.addEventListener("visibilitychange", handleWake);
    }

    return { stop: stop, checkNow: checkNow };
  }

  root.SkyMBBankQR = {
    getSimulatorBaseUrl: getSimulatorBaseUrl,
    setSimulatorBaseUrl: setSimulatorBaseUrl,
    promptSimulatorBaseUrl: promptSimulatorBaseUrl,
    generateVietQrEmv: generateVietQrEmv,
    applyQrImage: applyQrImage,
    buildSimulatorUrl: buildSimulatorUrl,
    startPaymentWatcher: startPaymentWatcher
  };
})(window);

