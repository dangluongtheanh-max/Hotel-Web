/* ==========================================================================
   SKY HOTEL - Lớp dữ liệu phòng & đặt phòng (SkyAPI)
   --------------------------------------------------------------------------
   Mọi trang chỉ gọi SkyAPI.*, không tự giữ số phòng/giá trong giao diện.

   Có 2 chế độ:
   1) CÓ BACKEND: đặt  window.SKY_API_BASE = "https://domain-cua-ban/api"
      (trước khi nạp file này). SkyAPI sẽ gọi các endpoint ở docs/API-DAT-PHONG.md.
   2) CHƯA CÓ BACKEND: mock localStorage chỉ dùng trên máy local để demo đặt phòng.
      Không bao giờ giả lập thành công thanh toán.
   ========================================================================== */
(function () {
  "use strict";

  var BASE = (window.SKY_API_BASE || "").replace(/\/$/, "");
  var isLocalDevelopment = window.location.protocol === "file:" ||
    /^(localhost|127\.0\.0\.1|\[::1\])$/i.test(window.location.hostname);
  var baseError = "";
  var HOLD_MIN = 20; // giữ phòng chờ thanh toán (phút) — khớp Backend 1200 giây
  var MAX_NIGHTS = 30;
  try { localStorage.removeItem("sky_db_v3"); } catch (e) {}

  if (BASE) {
    try {
      var apiUrl = new URL(BASE, window.location.href);
      var isLocalApi = /^(localhost|127\.0\.0\.1|\[::1\])$/i.test(apiUrl.hostname);
      if (apiUrl.username || apiUrl.password || apiUrl.search || apiUrl.hash ||
          (apiUrl.protocol !== "https:" && !(apiUrl.protocol === "http:" && isLocalDevelopment && isLocalApi))) {
        baseError = "Địa chỉ API phải dùng HTTPS hợp lệ. HTTP chỉ được phép trên localhost khi phát triển.";
      }
    } catch (e) {
      baseError = "Địa chỉ SKY_API_BASE không hợp lệ.";
    }
  }

  /* Phụ phí / thuế đồng bộ Backend (Thuế VAT phòng 8%, không thu phí dịch vụ 5%) */
  var PRICING = { serviceRate: 0, vatRate: 0.08 };

  var BACKEND_BASE = (window.HotelAPI && window.HotelAPI.base)
    ? window.HotelAPI.base
    : "/Backend/public/index.php";

  /* ---------------- Tiện ích ngày (chuỗi YYYY-MM-DD, không dính múi giờ) ---------------- */
  function pad(n) { return (n < 10 ? "0" : "") + n; }
  function toISO(d) { return d.getFullYear() + "-" + pad(d.getMonth() + 1) + "-" + pad(d.getDate()); }
  function parseISO(s) {
    var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s || "");
    if (!m) return null;
    var d = new Date(+m[1], +m[2] - 1, +m[3]);
    return toISO(d) === s ? d : null;
  }
  function addDays(s, n) { var d = parseISO(s); d.setDate(d.getDate() + n); return toISO(d); }
  function nightsBetween(a, b) {
    var x = parseISO(a), y = parseISO(b);
    if (!x || !y) return 0;
    return Math.round((y - x) / 86400000);
  }
  function today() { return toISO(new Date()); }
  function fmtDate(s) { var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s || ""); return m ? m[3] + "/" + m[2] + "/" + m[1] : ""; }
  function fmtMoney(n) { return Math.round(n).toLocaleString("vi-VN") + "đ"; }

  function err(code, message, extra) {
    var e = new Error(message);
    e.code = code;
    if (extra) for (var k in extra) e[k] = extra[k];
    return e;
  }

  /* ---------------- Kiểm tra đầu vào (dùng chung cho giao diện + máy chủ giả lập) ---------------- */
  function validateQuery(q) {
    if (!parseISO(q.checkIn)) throw err("BAD_DATE", "Ngày nhận phòng không hợp lệ.");
    if (!parseISO(q.checkOut)) throw err("BAD_DATE", "Ngày trả phòng không hợp lệ.");
    if (q.checkIn < today()) throw err("PAST_DATE", "Ngày nhận phòng không được nằm trong quá khứ.");
    var n = nightsBetween(q.checkIn, q.checkOut);
    if (n < 1) throw err("BAD_RANGE", "Ngày trả phòng phải sau ngày nhận phòng ít nhất 1 đêm.");
    if (n > MAX_NIGHTS) throw err("TOO_LONG", "Thời gian lưu trú tối đa là " + MAX_NIGHTS + " đêm.");
    var g = +q.guests, r = +q.rooms;
    if (!(g >= 1 && g <= 20 && g % 1 === 0)) throw err("BAD_GUESTS", "Số khách không hợp lệ.");
    if (!(r >= 1 && r <= 10 && r % 1 === 0)) throw err("BAD_ROOMS", "Số phòng không hợp lệ.");
    return n;
  }


  /* ---------------- Kiểm tra đủ 18 tuổi ----------------
     CCCD 12 số: chữ số thứ 4 = giới tính + thế kỷ sinh, 2 chữ số tiếp theo = năm sinh.
     (Không tra cứu được cổng dịch vụ công từ trình duyệt; muốn xác thực chính thức phải
     dùng dịch vụ eKYC/VNeID qua backend - xem docs/API-DAT-PHONG.md) */
  function birthYearFromCCCD(id) {
    if (!/^\d{12}$/.test(id || "")) return null;
    return 1900 + Math.floor(+id.charAt(3) / 2) * 100 + (+id.substr(4, 2));
  }
  function ageOn(dobISO, onISO) {
    var b = parseISO(dobISO), t = parseISO(onISO || today());
    var a = t.getFullYear() - b.getFullYear();
    if (t.getMonth() < b.getMonth() || (t.getMonth() === b.getMonth() && t.getDate() < b.getDate())) a--;
    return a;
  }
  function checkAdult(idNumber, dob) {
    if (!parseISO(dob)) return { ok: false, code: "BAD_DOB", message: "Vui lòng nhập ngày sinh hợp lệ." };
    if (dob >= today()) return { ok: false, code: "BAD_DOB", message: "Ngày sinh không hợp lệ." };
    var age = ageOn(dob);
    if (age > 120) return { ok: false, code: "BAD_DOB", message: "Ngày sinh không hợp lệ." };
    var by = birthYearFromCCCD(idNumber);
    if (by !== null && by !== parseISO(dob).getFullYear())
      return { ok: false, code: "ID_MISMATCH", message: "Ngày sinh không khớp với năm sinh trên số CCCD (" + by + ")." };
    if (age < 18) return { ok: false, code: "UNDERAGE", message: "Rất tiếc, chỉ khách từ đủ 18 tuổi trở lên mới được đặt phòng." };
    return { ok: true, age: age };
  }

  function validateCustomer(c) {
    c = c || {};
    var name = (c.name || "").trim();
    if (name.length > 100 || !/^[\p{L}\p{M}][\p{L}\p{M}'’.-]*(?:\s+[\p{L}\p{M}][\p{L}\p{M}'’.-]*)+$/u.test(name))
      return { ok: false, field: "name", message: "Nhập họ và tên gồm ít nhất hai từ, tối đa 100 ký tự." };
    if (!/^0\d{9}$/.test((c.phone || "").trim()))
      return { ok: false, field: "phone", message: "Số điện thoại phải gồm 10 số và bắt đầu bằng 0." };
    var email = (c.email || "").trim();
    var emailParts = email.split("@");
    if (email.length > 254 || emailParts.length !== 2 || emailParts[0].length > 64 ||
        !/^[^\s@]+@[^\s@]+\.[A-Za-z]{2,63}$/.test(email))
      return { ok: false, field: "email", message: "Email không hợp lệ (tối đa 254 ký tự)." };
    if (!/^(\d{9}|\d{12})$/.test((c.idNumber || "").trim()))
      return { ok: false, field: "cccd", message: "CMND/CCCD phải gồm 9 hoặc 12 chữ số." };
    var adult = checkAdult((c.idNumber || "").trim(), c.dob);
    if (!adult.ok) return { ok: false, field: "dob", message: adult.message, code: adult.code };
    return { ok: true };
  }

  function validateGuest(g) {
    g = g || {};
    var name = (g.name || "").trim();
    if (name.length > 100 || !/^[\p{L}\p{M}][\p{L}\p{M}'’.-]*(?:\s+[\p{L}\p{M}][\p{L}\p{M}'’.-]*)+$/u.test(name))
      return { ok: false, field: "name", message: "Họ và tên gồm ít nhất hai từ, tối đa 100 ký tự." };
    var idNumber = (g.cccd || g.idNumber || "").trim();
    if (!/^(\d{9}|\d{12})$/.test(idNumber))
      return { ok: false, field: "cccd", message: "CMND/CCCD phải gồm 9 hoặc 12 chữ số." };
    var adult = checkAdult(idNumber, g.dob);
    if (!adult.ok) return { ok: false, field: "dob", message: adult.message, code: adult.code };
    return { ok: true };
  }

  /* ---------------- Tính tiền: Giá × Số đêm × Số phòng (+ phụ phí, thuế) ---------------- */
  function calcTotals(lines, nights, pricing, discount) {
    pricing = pricing || PRICING;
    var sub = 0;
    lines.forEach(function (l) { l.lineTotal = l.price * nights * l.qty; sub += l.lineTotal; });
    var disc = Math.min(discount || 0, sub);
    var base = sub - disc;
    var service = Math.round(base * pricing.serviceRate);
    var vat = Math.round((base + service) * pricing.vatRate);
    return { subtotal: sub, discount: disc, service: service, vat: vat, total: base + service + vat,
      serviceRate: pricing.serviceRate, vatRate: pricing.vatRate };
  }

  /* ====================================================================
     CHẾ ĐỘ HTTP (backend thật)
     ==================================================================== */
  function http(method, path, body) {
    if (baseError) return Promise.reject(err("INSECURE_API", baseError));
    if (!isLocalDevelopment && window.location.protocol !== "https:")
      return Promise.reject(err("INSECURE_PAGE", "Trang đặt phòng phải được mở bằng HTTPS."));
    if (!BASE) return Promise.reject(err("API_NOT_CONFIGURED", "Chưa cấu hình máy chủ API HTTPS."));
    return fetch(BASE + path, {
      method: method,
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      credentials: "include",
      body: body ? JSON.stringify(body) : undefined
    }).then(function (res) {
      return res.json().catch(function () { return {}; }).then(function (j) {
        if (!res.ok) throw err(j.code || "HTTP_" + res.status, j.message || "Không thể kết nối máy chủ.", j);
        return j;
      });
    });
  }
  function qs(o) {
    return Object.keys(o).map(function (k) { return encodeURIComponent(k) + "=" + encodeURIComponent(o[k]); }).join("&");
  }

  var httpApi = {
    mode: "http",
    availability: function (q) { validateQuery(q); return http("GET", "/availability?" + qs(q)); },
    createBooking: function (p) { return http("POST", "/bookings", p); },
    getBooking: function (code) { return http("GET", "/bookings/" + encodeURIComponent(code)); },
    payBooking: function () { return Promise.reject(err("PAYMENT_NOT_CONFIGURED", "Thanh toán VNPay chưa được cấu hình ở máy chủ.")); },
    cancelBooking: function (code) { return http("POST", "/bookings/" + encodeURIComponent(code) + "/cancel"); },
    myBookings: function (email) { return http("GET", "/bookings?" + qs({ email: email })); },
    adminOverview: function () { return http("GET", "/admin/overview"); }
  };

  /* ====================================================================
     CHẾ ĐỘ GIẢ LẬP & DỮ LIỆU ĐỒNG BỘ 100% VỚI DATABASE GỐC (Database/data.sql)
     7 loại phòng (LP001 - LP007) & 66 phòng (P001 - P704)
     ==================================================================== */
  var TYPES = [
    {
      id: "LP004",
      name: "Economy",
      subtitle: "Economy Single Room",
      tagline: "Phòng tiết kiệm tiện ích cơ bản, phù hợp cho khách đi một mình",
      area: 20,
      capacity: 1,
      count: 10,
      floor: "Tầng 4 (P401 – P410)",
      bed: "1 giường đơn tiêu chuẩn (1m2 × 2m)",
      price: 300000,
      image: "../images/rooms/superior-2.jpg",
      gallery: ["../images/rooms/superior-2.jpg", "../images/rooms/superior-1.jpg", "../images/rooms/superior-3.jpg"],
      view: "Cửa sổ thoáng mát nội khu",
      bathroom: "Phòng tắm khép kín tiện lợi",
      desc: "Phòng tiết kiệm tiện ích cơ bản, phù hợp cho khách đi một mình.",
      fullDesc: "Phong Economy gồm 10 phòng tại tầng 4 (P401 – P410), được thiết kế tối giản và tiết kiệm dành riêng cho khách đi một mình (tối đa 1 người). Phòng trang bị đầy đủ Wi-Fi, quạt hoặc điều hòa, giường đơn và bàn nhỏ làm việc.",
      highlights: [
        { icon: "🧳", title: "Dành cho khách đi 1 mình", desc: "Sức chứa tối đa 1 người với không gian riêng tư, yên tĩnh" },
        { icon: "🏷️", title: "Giá siêu tiết kiệm 300.000đ/đêm", desc: "Lựa chọn kinh tế nhất trong hệ thống 7 hạng phòng" },
        { icon: "🪑", title: "Tiện ích thiết yếu", desc: "Wi-Fi, quạt hoặc điều hòa, giường đơn, bàn nhỏ" },
        { icon: "🏢", title: "10 phòng tầng 4", desc: "Bao gồm dãy phòng P401 – P410" }
      ],
      specs: {
        floor: "Tầng 4 (P401 – P410)",
        area: "20 m²",
        bed: "1 Giường đơn",
        capacity: "Tối đa 1 người",
        view: "Nội khu yên tĩnh",
        bathroom: "Phòng tắm vòi sen tiêu chuẩn",
        smoking: "Không hút thuốc"
      },
      amenityCategories: {
        bedroom: ["Giường đơn êm ái", "Quạt hoặc điều hòa", "Bàn nhỏ làm việc", "Móc treo quần áo"],
        bathroom: ["Vòi sen tắm nóng lạnh", "Khăn tắm & xà phòng", "Gương phòng tắm"],
        technology: ["Wi-Fi miễn phí", "Ổ cắm sạc tiện lợi"],
        dining: ["Nước uống đóng chai", "Ly uống nước cá nhân"],
        services: ["Dọn phòng hàng ngày", "Hỗ trợ đặt đồ ăn & thuê xe máy tại quầy"]
      },
      perks: [
        "Mức giá tiết kiệm nhất chỉ 300.000đ/đêm",
        "Đầy đủ Wi-Fi, điều hòa/quạt và bàn làm việc cá nhân",
        "Phù hợp khách công tác ngắn ngày hoặc du lịch tự túc"
      ],
      policies: {
        checkIn: "Từ 14:00",
        checkOut: "Trước 12:00 trưa",
        cancellation: (window.HotelAPI && window.HotelAPI.policy) ? window.HotelAPI.policy.getSummaryText() : "",
        children: "Phòng đơn tối đa 1 khách.",
        deposit: "Thanh toán trực tiếp tại quầy hoặc chuyển khoản VietQR."
      },
      amenities: ["Wi-Fi", "Quạt hoặc điều hòa", "Giường đơn", "Bàn nhỏ"]
    },
    {
      id: "LP001",
      name: "Standard",
      subtitle: "Standard Room",
      tagline: "Phòng tiêu chuẩn — Tiện nghi hiện đại, ấm cúng giữa lòng Sài Gòn",
      area: 28,
      capacity: 2,
      count: 12,
      floor: "Tầng trệt & Tầng 1 (P001, P004, P101 – P110)",
      bed: "1 giường đôi (1m8 × 2m) hoặc 2 giường đơn (1m2 × 2m)",
      price: 500000,
      image: "../images/rooms/superior-1.jpg",
      gallery: ["../images/rooms/superior-1.jpg", "../images/rooms/superior-2.jpg", "../images/rooms/superior-3.jpg"],
      view: "Hướng phố trung tâm Quận 10",
      bathroom: "Phòng tắm đứng vòi sen nóng lạnh tiêu chuẩn",
      desc: "Phòng tiêu chuẩn — Trang bị đầy đủ Wi-Fi, TV, điều hòa dành cho tối đa 2 khách.",
      fullDesc: "Phòng Standard mang đến không gian lưu trú tiêu chuẩn, sạch sẽ và tiện nghi dành cho tối đa 2 khách. Phòng được trang bị đầy đủ Wi-Fi tốc độ cao, TV màn hình phẳng và điều hòa nhiệt độ êm ái.",
      highlights: [
        { icon: "🛏️", title: "12 phòng tiêu chuẩn", desc: "Bao gồm các phòng P001, P004 và P101 – P110" },
        { icon: "📺", title: "Tiện nghi tiêu chuẩn", desc: "Trang bị đầy đủ Wi-Fi, TV, điều hòa nhiệt độ" },
        { icon: "👥", title: "Sức chứa 2 khách", desc: "Lựa chọn tối ưu cho cặp đôi hoặc khách công tác" },
        { icon: "💰", title: "Giá niêm yết 500.000đ/đêm", desc: "Chi phí hợp lý cùng chất lượng dịch vụ chu đáo" }
      ],
      specs: {
        floor: "Tầng trệt & Tầng 1 (P001, P004, P101 – P110)",
        area: "28 m²",
        bed: "1 Giường đôi (1m8 × 2m) hoặc 2 Giường đơn",
        capacity: "Tối đa 2 người",
        view: "Hướng phố trung tâm",
        bathroom: "Phòng tắm vòi sen nóng lạnh",
        smoking: "Không hút thuốc"
      },
      amenityCategories: {
        bedroom: ["Giường đôi êm ái chuẩn khách sạn", "Điều hòa nhiệt độ 2 chiều", "Rèm cản sáng 2 lớp", "Tủ quần áo & bàn làm việc"],
        bathroom: ["Vòi sen tắm nóng lạnh", "Máy sấy tóc", "Khăn tắm cotton mềm mịn", "Bộ vệ sinh cá nhân tiêu chuẩn"],
        technology: ["Wi-Fi tốc độ cao miễn phí", "TV màn hình phẳng", "Ổ cắm điện đa năng đầu giường"],
        dining: ["Ấm đun nước siêu tốc", "2 chai nước suối miễn phí mỗi ngày", "Trà & cà phê hòa tan"],
        services: ["Dọn phòng hàng ngày", "Lễ tân hỗ trợ 24/7", "Khóa phòng an toàn"]
      },
      perks: [
        "Miễn phí Wi-Fi tốc độ cao trong toàn bộ khuôn viên",
        "Miễn phí trà, cà phê và 2 chai nước suối mỗi ngày",
        "Hỗ trợ nhận phòng nhanh tại quầy Lễ tân"
      ],
      policies: {
        checkIn: "Từ 14:00",
        checkOut: "Trước 12:00 trưa",
        cancellation: (window.HotelAPI && window.HotelAPI.policy) ? window.HotelAPI.policy.getSummaryText() : "",
        children: "Trẻ em ≤ 11 tuổi được tính theo quy định cơ cấu khách của phòng.",
        deposit: "Thanh toán trực tiếp tại quầy hoặc chuyển khoản VietQR."
      },
      amenities: ["Wi-Fi", "TV", "Điều hòa"]
    },
    {
      id: "LP002",
      name: "Deluxe",
      subtitle: "Deluxe Room",
      tagline: "Phòng cao cấp — Không gian rộng mở với đầy đủ Wi-Fi, TV, điều hòa và tủ lạnh",
      area: 35,
      capacity: 3,
      count: 11,
      floor: "Tầng trệt & Tầng 2 (P002, P201 – P210)",
      bed: "1 giường King lớn + 1 giường đơn phụ",
      price: 800000,
      image: "../images/rooms/deluxe-1.jpg",
      gallery: ["../images/rooms/deluxe-1.jpg", "../images/rooms/deluxe-2.jpg", "../images/rooms/deluxe-3.jpg"],
      view: "Hướng đại lộ thoáng đãng",
      bathroom: "Phòng tắm kính cường lực & vòi sen mưa",
      desc: "Phòng cao cấp — Không gian cao cấp dành cho tối đa 3 người với Wi-Fi, TV, điều hòa, tủ lạnh.",
      fullDesc: "Phòng Deluxe là hạng phòng cao cấp gồm 11 phòng (P002, P201 – P210) với sức chứa tối đa 3 khách. Phòng được trang bị đầy đủ Wi-Fi, TV, điều hòa nhiệt độ và tủ lạnh tiện lợi.",
      highlights: [
        { icon: "🏨", title: "11 phòng cao cấp", desc: "Bao gồm phòng P002 và dãy phòng tầng 2 (P201 – P210)" },
        { icon: "❄️", title: "Đầy đủ tủ lạnh & điều hòa", desc: "Tiện nghi: Wi-Fi, TV, điều hòa, tủ lạnh" },
        { icon: "👨‍👩‍👦", title: "Sức chứa tối đa 3 người", desc: "Thoải mái cho nhóm bạn hoặc gia đình nhỏ 3 thành viên" },
        { icon: "💎", title: "Giá niêm yết 800.000đ/đêm", desc: "Trải nghiệm nâng cấp với không gian rộng 35m²" }
      ],
      specs: {
        floor: "Tầng trệt & Tầng 2 (P002, P201 – P210)",
        area: "35 m²",
        bed: "1 Giường King + 1 Giường phụ",
        capacity: "Tối đa 3 người",
        view: "Hướng phố thoáng đãng",
        bathroom: "Phòng tắm đứng kính cường lực cao cấp",
        smoking: "Không hút thuốc"
      },
      amenityCategories: {
        bedroom: ["Giường King êm ái", "Điều hòa làm lạnh nhanh", "Bàn trà & ghế thư giãn", "Tủ quần áo gỗ cao cấp"],
        bathroom: ["Vòi sen tắm mưa nhiệt đới", "Máy sấy tóc công suất lớn", "Khăn tắm & bộ đồ dùng cá nhân"],
        technology: ["Wi-Fi tốc độ cao", "TV màn hình phẳng", "Hệ thống chiếu sáng ấm cúng"],
        dining: ["Tủ lạnh làm mát đồ uống", "Ấm đun siêu tốc & trà/cà phê", "Nước đóng chai miễn phí"],
        services: ["Dịch vụ phòng (Đồ ăn, Giặt ủi, Thuê xe)", "Dọn phòng hàng ngày", "Lễ tân 24/7"]
      },
      perks: [
        "Trang bị tủ lạnh riêng trong phòng",
        "Hỗ trợ đặt nhanh dịch vụ ẩm thực, giặt ủi và thuê xe máy tận phòng",
        "Không gian rộng rãi cho 3 người"
      ],
      policies: {
        checkIn: "Từ 14:00",
        checkOut: "Trước 12:00 trưa",
        cancellation: (window.HotelAPI && window.HotelAPI.policy) ? window.HotelAPI.policy.getSummaryText() : "",
        children: "Trẻ em ≤ 11 tuổi được tính theo quy định cơ cấu khách của phòng.",
        deposit: "Thanh toán trực tiếp tại quầy hoặc chuyển khoản VietQR."
      },
      amenities: ["Wi-Fi", "TV", "Điều hòa", "Tủ lạnh"]
    },
    {
      id: "LP003",
      name: "Suite",
      subtitle: "Luxury Suite Room",
      tagline: "Phòng hạng sang — Tận hưởng bồn tắm thư giãn và không gian sang trọng cho 4 người",
      area: 48,
      capacity: 4,
      count: 11,
      floor: "Tầng trệt & Tầng 3 (P003, P301 – P310)",
      bed: "2 giường đôi lớn (Queen Size)",
      price: 1200000,
      image: "../images/rooms/premier-1.jpg",
      gallery: ["../images/rooms/premier-1.jpg", "../images/rooms/premier-2.jpg", "../images/rooms/premier-3.jpg"],
      view: "Tầm nhìn Panorama thành phố",
      bathroom: "Bồn tắm nằm thư giãn & vòi sen tắm đứng riêng",
      desc: "Phòng hạng sang — Trang bị Wi-Fi, TV, điều hòa, tủ lạnh và bồn tắm sang trọng.",
      fullDesc: "Phòng Suite là hạng phòng hạng sang gồm 11 phòng (P003, P301 – P310), đáp ứng tối đa 4 người lưu trú. Điểm nhấn của phòng là phòng tắm lớn có bồn tắm nằm thư giãn cùng đầy đủ Wi-Fi, TV, điều hòa và tủ lạnh.",
      highlights: [
        { icon: "🛁", title: "Bồn tắm nằm thư giãn", desc: "Tiện nghi: Wi-Fi, TV, điều hòa, tủ lạnh, bồn tắm" },
        { icon: "🏛️", title: "11 phòng hạng sang", desc: "Bao gồm phòng P003 và dãy phòng tầng 3 (P301 – P310)" },
        { icon: "👨‍👩‍👧‍👦", title: "Sức chứa tối đa 4 người", desc: "Không gian rộng 48m² lý tưởng cho gia đình 4 người" },
        { icon: "✨", title: "Giá niêm yết 1.200.000đ/đêm", desc: "Đẳng cấp nghỉ dưỡng hạng sang tại Sky Hotel" }
      ],
      specs: {
        floor: "Tầng trệt & Tầng 3 (P003, P301 – P310)",
        area: "48 m²",
        bed: "2 Giường đôi lớn (Queen Size)",
        capacity: "Tối đa 4 người",
        view: "Tầm nhìn thành phố từ tầng 3",
        bathroom: "Bồn tắm nằm cao cấp & vòi sen nóng lạnh",
        smoking: "Không hút thuốc"
      },
      amenityCategories: {
        bedroom: ["2 giường đôi êm ái", "Khu vực ghế sofa tiếp khách", "Điều hòa nhiệt độ", "Rèm cửa 2 lớp sang trọng"],
        bathroom: ["Bồn tắm nằm thư giãn", "Vòi sen tắm mưa", "Máy sấy tóc & áo choàng tắm"],
        technology: ["Wi-Fi tốc độ cao", "TV màn hình lớn", "Ổ cắm sạc đầu giường"],
        dining: ["Tủ lạnh", "Bộ ấm trà & cà phê miễn phí", "Nước suối đóng chai mỗi ngày"],
        services: ["Phục vụ đồ ăn & giặt ủi tận phòng", "Hỗ trợ thuê xe máy tại quầy", "Lễ tân phục vụ 24/7"]
      },
      perks: [
        "Bồn tắm nằm riêng biệt trong phòng",
        "Không gian hạng sang rộng rãi cho 4 khách",
        "Ưu tiên hỗ trợ nhận phòng sớm khi có phòng trống"
      ],
      policies: {
        checkIn: "Từ 14:00",
        checkOut: "Trước 12:00 trưa",
        cancellation: (window.HotelAPI && window.HotelAPI.policy) ? window.HotelAPI.policy.getSummaryText() : "",
        children: "Trẻ em ≤ 11 tuổi được tính theo quy định cơ cấu khách của phòng.",
        deposit: "Thanh toán trực tiếp tại quầy hoặc chuyển khoản VietQR."
      },
      amenities: ["Wi-Fi", "TV", "Điều hòa", "Tủ lạnh", "Bồn tắm"]
    },
    {
      id: "LP005",
      name: "Family",
      subtitle: "Family Connecting Suite",
      tagline: "Phòng gia đình tiện nghi, ấm cúng dành cho gia đình và nhóm bạn",
      area: 58,
      capacity: 5,
      count: 10,
      floor: "Tầng 5 (P501 – P510)",
      bed: "2 giường đôi lớn + bàn ăn nhỏ gia đình",
      price: 1500000,
      image: "../images/rooms/deluxe-2.jpg",
      gallery: ["../images/rooms/deluxe-2.jpg", "../images/rooms/deluxe-1.jpg", "../images/rooms/premier-2.jpg"],
      view: "Hướng phố & công viên thoáng rộng",
      bathroom: "Phòng tắm gia đình rộng rãi",
      desc: "Phòng gia đình tiện nghi, ấm cúng dành cho gia đình và nhóm bạn.",
      fullDesc: "Phong Family gồm 10 phòng tại tầng 5 (P501 – P510) với sức chứa lên tới 5 người. Không gian ấm cúng được trang bị Wi-Fi, Smart TV, điều hòa, tủ lạnh, 2 giường đôi rộng rãi và bàn ăn nhỏ dành cho cả gia đình.",
      highlights: [
        { icon: "👨‍👩‍👧‍👦", title: "Sức chứa tối đa 5 người", desc: "Lý tưởng cho gia đình nhiều thế hệ hoặc nhóm bạn 5 người" },
        { icon: "🍽️", title: "2 giường đôi & bàn ăn nhỏ", desc: "Tiện nghi: Wi-Fi, Smart TV, điều hòa, tủ lạnh, 2 giường đôi, bàn ăn nhỏ" },
        { icon: "🏢", title: "10 phòng tầng 5", desc: "Bao gồm dãy phòng P501 – P510" },
        { icon: "🌟", title: "Giá niêm yết 1.500.000đ/đêm", desc: "Chỉ 300.000đ/người khi đi đủ nhóm 5 khách" }
      ],
      specs: {
        floor: "Tầng 5 (P501 – P510)",
        area: "58 m²",
        bed: "2 Giường đôi lớn",
        capacity: "Tối đa 5 người",
        view: "Hướng phố từ tầng 5",
        bathroom: "Phòng tắm lớn tiện nghi",
        smoking: "Không hút thuốc"
      },
      amenityCategories: {
        bedroom: ["2 giường đôi lớn", "Điều hòa công suất lớn", "Tủ quần áo gia đình", "Rèm cản sáng 2 lớp"],
        bathroom: ["Phòng tắm rộng rãi", "Máy sấy tóc", "Đầy đủ khăn tắm & đồ vệ sinh cho 5 khách"],
        technology: ["Wi-Fi tốc độ cao", "Smart TV kết nối Internet", "Nhiều ổ cắm sạc tiện lợi"],
        dining: ["Bàn ăn nhỏ trong phòng", "Tủ lạnh", "Ấm đun siêu tốc & nước suối miễn phí"],
        services: ["Đặt đồ ăn phục vụ tại bàn ăn phòng", "Dịch vụ giặt ủi & thuê xe máy", "Dọn phòng hàng ngày"]
      },
      perks: [
        "Có khu vực bàn ăn nhỏ riêng trong phòng",
        "Trang bị Smart TV và 2 giường đôi rộng rãi cho 5 người",
        "Thuận tiện gọi dịch vụ ẩm thực phục vụ tận phòng"
      ],
      policies: {
        checkIn: "Từ 14:00",
        checkOut: "Trước 12:00 trưa",
        cancellation: (window.HotelAPI && window.HotelAPI.policy) ? window.HotelAPI.policy.getSummaryText() : "",
        children: "Trẻ em ≤ 11 tuổi được tính theo quy định cơ cấu khách của phòng.",
        deposit: "Thanh toán trực tiếp tại quầy hoặc chuyển khoản VietQR."
      },
      amenities: ["Wi-Fi", "Smart TV", "Điều hòa", "Tủ lạnh", "2 giường đôi", "Bàn ăn nhỏ"]
    },
    {
      id: "LP006",
      name: "Executive Entertainment",
      subtitle: "Executive Entertainment Suite",
      tagline: "Phòng giải trí đỉnh cao với máy PS5, Smart TV lớn và sofa sang trọng",
      area: 72,
      capacity: 6,
      count: 8,
      floor: "Tầng 6 (P601 – P608)",
      bed: "2 giường đôi lớn + khu sofa giải trí PS5",
      price: 2000000,
      image: "../images/rooms/executive-1.jpg",
      gallery: ["../images/rooms/executive-1.jpg", "../images/rooms/executive-2.jpg", "../images/rooms/executive-3.jpg"],
      view: "Tầm nhìn tầng cao toàn cảnh thành phố",
      bathroom: "Phòng tắm sang trọng chuẩn 5 sao",
      desc: "Phòng giải trí đỉnh cao với máy PS5, Smart TV lớn và sofa sang trọng.",
      fullDesc: "Phong Executive Entertainment gồm 8 phòng tại tầng 6 (P601 – P608) với sức chứa tối đa 6 người. Đây là hạng phòng giải trí chuyên biệt được trang bị máy chơi game Sony PlayStation 5 (PS5), Smart TV màn hình lớn, sofa giải trí sang trọng, Wi-Fi tốc độ cao, điều hòa, tủ lạnh và 2 giường đôi.",
      highlights: [
        { icon: "🎮", title: "Máy chơi game PS5 & Smart TV lớn", desc: "Trải nghiệm giải trí đỉnh cao ngay trong phòng nghỉ" },
        { icon: "🛋️", title: "Sofa giải trí sang trọng", desc: "Không gian sinh hoạt chung rộng rãi cho nhóm 6 người" },
        { icon: "🏢", title: "8 phòng VIP tầng 6", desc: "Bao gồm dãy phòng P601 – P608" },
        { icon: "⚡", title: "Wi-Fi tốc độ cao", desc: "Kết nối mạng siêu tốc không giật lag" }
      ],
      specs: {
        floor: "Tầng 6 (P601 – P608)",
        area: "72 m²",
        bed: "2 Giường đôi + Sofa giải trí lớn",
        capacity: "Tối đa 6 người",
        view: "Tầng 6 ngắm toàn cảnh phố đêm",
        bathroom: "Phòng tắm cao cấp rộng rãi",
        smoking: "Không hút thuốc"
      },
      amenityCategories: {
        bedroom: ["2 giường đôi cao cấp", "Sofa giải trí êm ái", "Điều hòa nhiệt độ", "Rèm cản sáng 100%"],
        bathroom: ["Phòng tắm đá sang trọng", "Vòi sen tắm mưa", "Máy sấy tóc & áo choàng tắm"],
        technology: ["Máy chơi game PS5 kèm tay cầm đôi", "Smart TV màn hình lớn 4K", "Wi-Fi tốc độ cao chuyên dụng"],
        dining: ["Tủ lạnh", "Quầy trà & cà phê miễn phí", "Phục vụ đồ ăn đêm tận phòng"],
        services: ["Hỗ trợ kỹ thuật giải trí 24/7", "Dịch vụ phòng nhanh chóng", "Dọn phòng hàng ngày"]
      },
      perks: [
        "Miễn phí sử dụng máy chơi game PS5 và kho game giải trí",
        "Smart TV màn hình lớn kết nối Netflix, YouTube",
        "Không gian rộng 72m² cho nhóm tối đa 6 khách"
      ],
      policies: {
        checkIn: "Từ 14:00",
        checkOut: "Trước 12:00 trưa",
        cancellation: (window.HotelAPI && window.HotelAPI.policy) ? window.HotelAPI.policy.getSummaryText() : "",
        children: "Trẻ em ≤ 11 tuổi được tính theo quy định cơ cấu khách của phòng.",
        deposit: "Thanh toán trực tiếp tại quầy hoặc chuyển khoản VietQR."
      },
      amenities: ["Wi-Fi tốc độ cao", "Smart TV lớn", "PS5", "Sofa giải trí", "Điều hòa", "Tủ lạnh", "2 giường đôi"]
    },
    {
      id: "LP007",
      name: "Presidential Grand Suite",
      subtitle: "Presidential Grand Suite",
      tagline: "Phòng Tổng Thống thượng đỉnh ngắm hoàng hôn với bàn bida, minibar và ban công riêng",
      area: 120,
      capacity: 8,
      count: 4,
      floor: "Tầng 7 Thượng Đỉnh (P701 – P704)",
      bed: "3 giường đôi thượng hạng + phòng khách riêng + bàn bida",
      price: 2800000,
      image: "../images/rooms/presidential-1.jpg",
      gallery: ["../images/rooms/presidential-1.jpg", "../images/rooms/presidential-2.jpg", "../images/rooms/presidential-3.jpg"],
      view: "Ban công riêng view ngắm hoàng hôn toàn cảnh Sài Gòn",
      bathroom: "Bồn tắm lớn, minibar & tiện nghi Tổng Thống",
      desc: "Phòng Tổng Thống thượng đỉnh ngắm hoàng hôn với bàn bida, minibar và ban công riêng.",
      fullDesc: "Phong Presidential Grand Suite gồm 4 tuyệt tác phòng Tổng Thống tại tầng 7 (P701 – P704) với sức chứa lên tới 8 khách. Không gian rộng 120m² hội tụ đầy đủ Wi-Fi tốc độ cao, Smart TV, máy PS5, bàn bida tiêu chuẩn, phòng khách riêng, 3 giường đôi, tủ lạnh lớn, minibar, bồn tắm nằm và ban công riêng ngắm hoàng hôn.",
      highlights: [
        { icon: "🎱", title: "Bàn Bida & Máy PS5 riêng", desc: "Tiện ích giải trí thượng lưu độc quyền ngay trong Suite" },
        { icon: "🌅", title: "Ban công view ngắm hoàng hôn", desc: "Tầm nhìn đắt giá từ tầng cao nhất của khách sạn" },
        { icon: "👑", title: "3 giường đôi & Phòng khách riêng", desc: "Sức chứa tối đa 8 người — chỉ có 4 phòng giới hạn (P701 – P704)" },
        { icon: "🥂", title: "Tủ lạnh lớn, Minibar & Bồn tắm", desc: "Trải nghiệm nghỉ dưỡng Tổng Thống trọn vẹn với giá 2.800.000đ/đêm" }
      ],
      specs: {
        floor: "Tầng 7 Thượng Đỉnh (P701 – P704)",
        area: "120 m²",
        bed: "3 Giường đôi lớn + Phòng khách riêng",
        capacity: "Tối đa 8 người",
        view: "Ban công riêng ngắm hoàng hôn",
        bathroom: "Bồn tắm lớn & khu vệ sinh cao cấp",
        smoking: "Có ban công thoáng khí riêng"
      },
      amenityCategories: {
        bedroom: ["3 giường đôi thượng hạng", "Phòng khách riêng biệt", "Ban công riêng ngắm hoàng hôn", "Điều hòa trung tâm độc lập"],
        bathroom: ["Bồn tắm nằm cỡ lớn", "Vòi sen tắm mưa nhiệt đới", "Áo choàng tắm & bộ mỹ phẩm cao cấp"],
        technology: ["Bàn bida giải trí tại phòng", "Máy chơi game PS5", "Smart TV màn hình lớn", "Wi-Fi tốc độ cao"],
        dining: ["Tủ lạnh lớn", "Quầy Minibar đầy đủ đồ uống", "Bàn tiệc & khu vực tiếp khách"],
        services: ["Ưu tiên phục vụ VIP 24/7", "Đặt nhanh ẩm thực, giặt ủi, thuê xe", "Hỗ trợ nhận/trả phòng linh hoạt"]
      },
      perks: [
        "Sở hữu bàn bida, máy PS5 và phòng khách riêng biệt",
        "Ban công riêng ngắm hoàng hôn tuyệt đẹp",
        "Sức chứa lên đến 8 khách với 3 giường đôi rộng rãi"
      ],
      policies: {
        checkIn: "Từ 14:00",
        checkOut: "Trước 12:00 trưa",
        cancellation: (window.HotelAPI && window.HotelAPI.policy) ? window.HotelAPI.policy.getSummaryText() : "",
        children: "Trẻ em ≤ 11 tuổi được tính theo quy định cơ cấu khách của phòng.",
        deposit: "Thanh toán trực tiếp tại quầy hoặc chuyển khoản VietQR."
      },
      amenities: ["Wi-Fi tốc độ cao", "Smart TV", "PS5", "Bàn bida", "Phòng khách riêng", "3 giường đôi", "Tủ lạnh lớn", "Minibar", "Bồn tắm", "Ban công view ngắm hoàng hôn"]
    }
  ];

  var TYPE_ALIASES = {
    "standard": "LP001",
    "superior": "LP001",
    "deluxe": "LP002",
    "suite": "LP003",
    "premier": "LP003",
    "economy": "LP004",
    "family": "LP005",
    "executive": "LP006",
    "presidential": "LP007"
  };

  /* Danh sách 66 phòng vật lý thực tế từ bảng Phong trong Database/data.sql */
  var DB_ROOMS_BY_TYPE = {
    LP001: ["P001", "P004", "P101", "P102", "P103", "P104", "P105", "P106", "P107", "P108", "P109", "P110"],
    LP002: ["P002", "P201", "P202", "P203", "P204", "P205", "P206", "P207", "P208", "P209", "P210"],
    LP003: ["P003", "P301", "P302", "P303", "P304", "P305", "P306", "P307", "P308", "P309", "P310"],
    LP004: ["P401", "P402", "P403", "P404", "P405", "P406", "P407", "P408", "P409", "P410"],
    LP005: ["P501", "P502", "P503", "P504", "P505", "P506", "P507", "P508", "P509", "P510"],
    LP006: ["P601", "P602", "P603", "P604", "P605", "P606", "P607", "P608"],
    LP007: ["P701", "P702", "P703", "P704"]
  };

  var INVENTORY = { LP001: 12, LP002: 11, LP003: 11, LP004: 10, LP005: 10, LP006: 8, LP007: 4 };
  var FLOOR = { LP001: 1, LP002: 2, LP003: 3, LP004: 4, LP005: 5, LP006: 6, LP007: 7 };

  function resolveTypeId(id) {
    if (!id) return "";
    var raw = String(id).trim();
    var upper = raw.toUpperCase();
    if (DB_ROOMS_BY_TYPE[upper]) return upper;
    var lower = raw.toLowerCase();
    return TYPE_ALIASES[lower] || raw;
  }

  function fail(e) {
    return Promise.reject(e);
  }

  function clone(o) { return JSON.parse(JSON.stringify(o)); }

  /* ---------------- Khuyến mãi & Voucher đồng bộ Quản trị hệ thống ---------------- */
  var DEFAULT_PROMOS = [
    {
      code: "SKYWELCOME",
      name: "Chào mừng thành viên mới",
      type: "percent",
      value: "10%",
      apply: "Tất cả các hạng phòng",
      min: 0,
      start: "2026-01-01",
      end: "2026-12-31",
      used: 94,
      limit: 500,
      status: "active"
    },
    {
      code: "SKY10",
      name: "Ưu đãi trực tuyến Sky Hotel",
      type: "percent",
      value: "10%",
      apply: "Tất cả các hạng phòng",
      min: 0,
      start: "2026-01-01",
      end: "2026-12-31",
      used: 15,
      limit: 500,
      status: "active"
    },
    {
      code: "VIPELITE15",
      name: "Tri ân Hội viên VIP Elite",
      type: "percent",
      value: "15%",
      apply: "Chỉ dành cho khách VIP",
      min: 2000000,
      start: "2026-01-01",
      end: "2026-12-31",
      used: 48,
      limit: 200,
      status: "active"
    },
    {
      code: "FLASH300K",
      name: "Ưu đãi Flash Sale cuối tuần",
      type: "fixed",
      value: "300.000đ",
      apply: "Phòng Premier trở lên",
      min: 2500000,
      start: "2026-10-01",
      end: "2026-10-31",
      used: 18,
      limit: 50,
      status: "active"
    },
    {
      code: "SUMMER2026",
      name: "Kỳ nghỉ mùa hè rực rỡ",
      type: "percent",
      value: "20%",
      apply: "Đặt từ 2 đêm trở lên",
      min: 3000000,
      start: "2026-05-01",
      end: "2026-08-31",
      used: 120,
      limit: 120,
      status: "expired"
    },
    {
      code: "WINTERVIBES",
      name: "Đón lễ hội Giáng sinh & Năm mới",
      type: "free",
      value: "Tặng Set Tối Lãng Mạn",
      apply: "Phòng Executive Suite trở lên",
      min: 8000000,
      start: "2026-12-01",
      end: "2027-01-05",
      used: 0,
      limit: 30,
      status: "scheduled"
    }
  ];

  function loadPromos() {
    var stored = null;
    try {
      stored = JSON.parse(localStorage.getItem("sky_admin_promos") || "null");
    } catch (e) {}
    if (stored && Array.isArray(stored) && stored.length) {
      var hasSky10 = stored.some(function (p) { return String(p.code || "").toUpperCase() === "SKY10"; });
      if (!hasSky10) {
        stored.push({
          code: "SKY10",
          name: "Ưu đãi trực tuyến Sky Hotel",
          type: "percent",
          value: "10%",
          apply: "Tất cả các hạng phòng",
          min: 0,
          start: "2026-01-01",
          end: "2026-12-31",
          used: 15,
          limit: 500,
          status: "active"
        });
        savePromos(stored);
      }
      return stored;
    }
    try {
      localStorage.setItem("sky_admin_promos", JSON.stringify(DEFAULT_PROMOS));
    } catch (e) {}
    return DEFAULT_PROMOS;
  }

  function savePromos(promos) {
    try {
      localStorage.setItem("sky_admin_promos", JSON.stringify(promos));
    } catch (e) {}
  }

  function findPromo(code) {
    var list = loadPromos();
    var c = String(code || "").trim().toUpperCase();
    for (var i = 0; i < list.length; i++) {
      if (String(list[i].code || "").trim().toUpperCase() === c) return list[i];
    }
    return null;
  }

  function checkPromo(code, subtotal, ctx) {
    if (!code) return { ok: false, message: "Vui lòng nhập mã khuyến mãi." };
    var p = findPromo(code);
    if (!p) return { ok: false, message: "Mã ưu đãi \"" + code + "\" không tồn tại." };
    if (p.status !== "active") return { ok: false, message: "Mã ưu đãi " + p.code + " hiện đang tạm dừng hoặc đã hết hạn." };

    var todayStr = today();
    if (p.start && todayStr < p.start) return { ok: false, message: "Mã ưu đãi chỉ có hiệu lực từ ngày " + p.start + "." };
    if (p.end && todayStr > p.end) return { ok: false, message: "Mã ưu đãi đã hết hạn sử dụng vào ngày " + p.end + "." };

    if (p.limit && Number(p.used) >= Number(p.limit)) return { ok: false, message: "Mã ưu đãi đã đạt giới hạn số lượt sử dụng (" + p.limit + " lượt)." };

    var minAmt = Number(p.min) || 0;
    if (minAmt > 0 && subtotal < minAmt) {
      return { ok: false, message: "Đơn đặt phòng tối thiểu " + minAmt.toLocaleString("vi-VN") + "đ để áp dụng mã này (hiện tại: " + Number(subtotal).toLocaleString("vi-VN") + "đ)." };
    }

    ctx = ctx || {};
    var nights = Number(ctx.nights) || 1;
    var applyDesc = String(p.apply || "").toLowerCase();
    if (applyDesc.indexOf("2 đêm") !== -1 && nights < 2) {
      return { ok: false, message: "Mã ưu đãi này chỉ áp dụng khi đặt từ 2 đêm trở lên (hiện tại: " + nights + " đêm)." };
    }

    if (ctx.items && Array.isArray(ctx.items)) {
      if (applyDesc.indexOf("premier") !== -1) {
        var hasPremier = ctx.items.some(function (it) {
          var id = String(it.typeId || "").toLowerCase();
          return id === "premier" || id === "executive" || id === "presidential";
        });
        if (!hasPremier) return { ok: false, message: "Mã chỉ áp dụng cho các hạng phòng từ Premier trở lên." };
      }
      if (applyDesc.indexOf("executive") !== -1) {
        var hasExe = ctx.items.some(function (it) {
          var id = String(it.typeId || "").toLowerCase();
          return id === "executive" || id === "presidential";
        });
        if (!hasExe) return { ok: false, message: "Mã chỉ áp dụng cho các hạng phòng từ Executive Suite trở lên." };
      }
    }

    var discount = 0;
    if (p.type === "percent") {
      var pct = parseFloat(String(p.value).replace(/[^0-9.]/g, "")) || 0;
      discount = Math.round(subtotal * (pct / 100));
    } else if (p.type === "fixed") {
      var fixedAmt = parseInt(String(p.value).replace(/\D/g, ""), 10) || 0;
      discount = Math.min(subtotal, fixedAmt);
    } else {
      discount = 0;
    }

    return {
      ok: true,
      promo: p,
      discount: discount,
      message: "Đã áp dụng mã " + p.code + " (" + p.name + "): " + (discount > 0 ? "-" + discount.toLocaleString("vi-VN") + "đ" : p.value)
    };
  }

  function recordPromoUse(code) {
    if (!code) return;
    var list = loadPromos();
    var c = String(code).trim().toUpperCase();
    var found = false;
    for (var i = 0; i < list.length; i++) {
      if (String(list[i].code || "").trim().toUpperCase() === c) {
        list[i].used = (Number(list[i].used) || 0) + 1;
        found = true;
        break;
      }
    }
    if (found) savePromos(list);
  }

  var backendApi = {
    mode: "backend",

    availability: function (q, preloadedBackendData) {
      var nights;
      try {
        nights = validateQuery(q);
      } catch (e) {
        return fail(e);
      }
      var adults = Number(q.adults) || Number(q.guests) || 2;
      var children = Number(q.children) || 0;
      var roomsReq = Number(q.rooms) || 1;

      var pBackend = preloadedBackendData
        ? Promise.resolve({ success: true, data: preloadedBackendData })
        : fetch(BACKEND_BASE + "/datphong/timkiem", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
              ngayNhan: q.checkIn,
              ngayTra: q.checkOut,
              soNguoiLon: adults,
              soTreEm: children,
              soPhong: roomsReq,
              maLoaiPhong: ""
            })
          }).then(function (res) {
            if (!res.ok) throw err("HTTP_" + res.status, "Không kết nối được máy chủ, vui lòng thử lại");
            return res.json();
          });

      return pBackend.then(function (json) {
        if (!json || json.success === false) {
          throw err("SERVER_ERROR", (json && json.message) || "Không kết nối được máy chủ, vui lòng thử lại");
        }
        var bData = (window.HotelAPI && window.HotelAPI.unwrap)
          ? window.HotelAPI.unwrap(json).data
          : (json.data || null);
        if (bData && bData.data && !bData.danhSach) bData = bData.data;
        if (!bData || !Array.isArray(bData.danhSach)) {
          throw err("SERVER_OFFLINE", "Không kết nối được máy chủ, vui lòng thử lại");
        }
        var dbMap = {};
        bData.danhSach.forEach(function (r) {
          if (r && r.MaLoaiPhong) dbMap[r.MaLoaiPhong] = r;
        });
        return {
          checkIn: q.checkIn,
          checkOut: q.checkOut,
          nights: bData.soDem ? Number(bData.soDem) : nights,
          pricing: PRICING,
          goiYCombo: bData.goiYCombo || "",
          comboToiUu: bData.comboToiUu || null,
          tongKhachQuyDoi: Number(bData.tongKhach || adults),
          types: TYPES.map(function (t) {
            var o = clone(t);
            var matched = dbMap[t.id];
            if (matched) {
              o.available = Number(matched.SoPhongTrong !== undefined ? matched.SoPhongTrong : 0);
              if (matched.Gia) o.price = Number(matched.Gia);
              if (matched.SoNguoiToiDa) o.capacity = Number(matched.SoNguoiToiDa);
              if (matched.TenLoaiPhong) o.name = String(matched.TenLoaiPhong).replace(/^Phong\s+/i, "").trim();
              o.totalActive = Number(matched.TongSoPhong || INVENTORY[t.id] || 0);
            } else {
              o.available = 0;
              o.totalActive = Number(INVENTORY[t.id] || 0);
            }
            return o;
          })
        };
      }).catch(function (e) {
        if (e && e.code && e.code !== "TypeError") throw e;
        throw err("SERVER_OFFLINE", "Không kết nối được máy chủ, vui lòng thử lại");
      });
    }
  };

  function getMergedRoomTypes() {
    var stored = null;
    try {
      stored = JSON.parse(localStorage.getItem("sky_admin_room_types") || "null");
    } catch (e) {}

    // Tự động loại bỏ cache cũ nếu chưa đồng bộ sang mã LP001..LP007 của database gốc
    if (stored && Array.isArray(stored) && !stored.some(function (x) { return x && x.id === "LP001"; })) {
      try { localStorage.removeItem("sky_admin_room_types"); } catch (e) {}
      stored = null;
    }

    var baseMap = {};
    TYPES.forEach(function (t) {
      baseMap[t.id] = clone(t);
    });

    if (stored && Array.isArray(stored) && stored.length) {
      stored.forEach(function (st) {
        if (!st || !st.id) return;
        var existing = baseMap[st.id] || {};
        var merged = Object.assign({}, existing, st);
        if (!merged.image) merged.image = "../images/rooms/superior-1.jpg";
        if (!merged.images || !merged.images.length) merged.images = [merged.image];
        if (!merged.specs) {
          merged.specs = {
            area: (merged.area || 30) + " m²",
            floor: merged.floor || "Tầng cao",
            bed: merged.bed || "1 giường King (1m8 × 2m)",
            capacity: (merged.capacity || 2) + " khách",
            view: merged.view || "View thành phố thoáng đãng",
            bathroom: merged.bathroom || "Bồn tắm nằm & Vòi sen đứng Rain Shower",
            smoking: "100% Không hút thuốc"
          };
        }
        if (!merged.amenityCategories) {
          var amList = Array.isArray(merged.amenities) ? merged.amenities : String(merged.amenities || "").split(/[,;\n]+/).map(function (s) { return s.trim(); }).filter(Boolean);
          merged.amenityCategories = {
            bedroom: amList.slice(0, 3).length ? amList.slice(0, 3) : ["Nệm lò xo túi 7 vùng cao cấp", "Menu chọn gối êm ái", "Rèm cản sáng tự động"],
            bathroom: [merged.bathroom || "Bồn tắm nằm thư giãn", "Vòi sen tắm mưa nhiệt đới", "Bộ đồ chăm sóc hữu cơ cao cấp L'Occitane"],
            technology: ["Smart TV 4K 65 inch", "Wifi 6 tốc độ cao miễn phí", "Điều hòa trung tâm 2 chiều"],
            beverage: ["Quầy Minibar cao cấp", "Máy pha trà & cà phê Nespresso", "Nước suối miễn phí mỗi ngày"]
          };
        }
        if (!merged.perks || !merged.perks.length) {
          merged.perks = [
            "Miễn phí Wi-Fi tốc độ cao trong toàn bộ khuôn viên",
            "Miễn phí trà, cà phê và nước suối mỗi ngày",
            "Hỗ trợ đặt nhanh dịch vụ phòng 24/7"
          ];
        }
        baseMap[st.id] = merged;
      });
    }

    return Object.keys(baseMap).map(function (k) { return clone(baseMap[k]); });
  }

  window.SkyAPI = BASE ? httpApi : backendApi;
  window.SkyAPI.getRoomType = function (id) {
    if (!id) return null;
    var resolved = resolveTypeId(id);
    var all = getMergedRoomTypes();
    var found = all.find(function (t) {
      return t.id === resolved || t.id === id || String(t.id).toLowerCase() === String(resolved).toLowerCase();
    });
    return found ? clone(found) : null;
  };
  window.SkyAPI.getRoomTypes = function () {
    return getMergedRoomTypes();
  };
  window.SkyAPI.DB_ROOMS_BY_TYPE = DB_ROOMS_BY_TYPE;
  window.SkyAPI.getPromos = loadPromos;
  window.SkyAPI.checkPromo = checkPromo;
  window.SkyAPI.recordPromoUse = recordPromoUse;
  window.SkyAPI.util = {
    today: today, addDays: addDays, nights: nightsBetween, parse: parseISO, toISO: toISO,
    fmtDate: fmtDate, checkAdult: checkAdult, fmtMoney: fmtMoney, calcTotals: calcTotals, validateQuery: validateQuery,
    validateCustomer: validateCustomer, validateGuest: validateGuest,
    MAX_NIGHTS: MAX_NIGHTS, HOLD_MIN: HOLD_MIN, PRICING: PRICING
  };
})();
