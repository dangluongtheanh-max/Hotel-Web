var PAGES = {
    home: "pages/trang-chu.html",
    about: "pages/ve-sky-hotel.html",
    rooms: "pages/chon-phong.html",
    detail: "pages/chi-tiet-phong.html",
    book: "pages/dat-phong.html",
    pay: "pages/thanh-toan.html",
    hist: "pages/lich-su-dat-phong.html",
    login: "pages/dang-nhap.html",
    service: "pages/chi-tiet-dich-vu.html",
    amenity: "pages/dich-vu-tien-ich.html",
    fav: "pages/danh-sach-yeu-thich.html",
    // Phân hệ quản trị hệ thống (đồng bộ về dashboard-quan-ly.html)
    mgr: "pages/dashboard-quan-ly.html",
    rec: "pages/dashboard-le-tan.html",
    rooms_mgr: "pages/dashboard-quan-ly.html",
    room_types: "pages/dashboard-quan-ly.html",
    customers: "pages/dashboard-quan-ly.html",
    invoices: "pages/dashboard-quan-ly.html",
    services_mgr: "pages/dashboard-quan-ly.html",
    promos: "pages/dashboard-quan-ly.html",
    staff: "pages/dashboard-quan-ly.html",
    hotel: "pages/dashboard-quan-ly.html",
  },
  cur = "",
  curArg = "",
  hadInitialRoute = false,
  pending = null,
  notice = "",
  user = null,
  loginTab = "";
var FILE_TO_KEY = {
  "pages/trang-chu.html": "home",
  "pages/ve-sky-hotel.html": "about",
  "pages/chon-phong.html": "rooms",
  "pages/chi-tiet-phong.html": "detail",
  "pages/dat-phong.html": "book",
  "pages/thanh-toan.html": "pay",
  "pages/lich-su-dat-phong.html": "hist",
  "pages/dang-nhap.html": "login",
  "pages/chi-tiet-dich-vu.html": "service",
  "pages/dich-vu-tien-ich.html": "amenity",
  "pages/danh-sach-yeu-thich.html": "fav",
  "pages/dashboard-le-tan.html": "rec",
  "pages/dashboard-quan-ly.html": "mgr",
};
/* Tài khoản do Backend (MySQL) quản lý; không còn tài khoản/mật khẩu mẫu trong code. */
var SEED = [];
var ROLE = { customer: "Khách hàng", admin: "Quản trị viên", letan: "Lễ tân" };
var ALL = ["customer", "letan", "admin"],
  CUST_LET = ["customer", "letan"],
  ADM = ["admin"],
  LET = ["letan", "admin"],
  APAGES = [
    "mgr",
    "rec",
    "rooms_mgr",
    "room_types",
    "customers",
    "invoices",
    "services_mgr",
    "promos",
    "staff",
    "hotel",
  ];
var RULES = {
  book: CUST_LET,
  pay: CUST_LET,
  hist: CUST_LET,
  fav: CUST_LET,
  rec: LET,
  mgr: ADM,
  rooms_mgr: ADM,
  room_types: ADM,
  customers: ADM,
  invoices: ADM,
  services_mgr: ADM,
  promos: ADM,
  staff: ADM,
  hotel: ADM,
};
var HOME = { customer: "home", admin: "mgr", letan: "rec" };
var SIDE_ADMIN = [
  ["mgr:people", "👥 Quản lý người dùng"],
  ["mgr:bookings", "📑 Đặt phòng & Hóa đơn"],
  ["mgr:services", "🛎️ Quản lý dịch vụ"],
  ["mgr:promotions", "🎁 Quản lý khuyến mãi"],
  ["mgr:rooms", "🏨 Quản lý phòng"],
  ["mgr:stats", "📊 Thống kê"],
];
var SIDE_LETAN = [
  ["rec:bookings", "🛎️ Tiếp đón & Quản lý phiếu"],
  ["rec:rooms", "📊 Sơ đồ & Trạng thái loại phòng"],
];
var SIDE = SIDE_ADMIN;
var accs = SEED.slice();
function ls(f) {
  try {
    return f();
  } catch (e) {
    return null;
  }
}
/* Chuyển dữ liệu đăng nhập của Backend -> đối tượng người dùng của giao diện Sky */
function userFromBackend(d) {
  d = d || {};
  return {
    name: d.HoTen || d.TenDangNhap || "Người dùng",
    email: d.Email || "",
    role: (window.HotelAPI && HotelAPI.roleMap[d.VaiTro]) || "customer",
    phone: d.SDT || "",
    username: d.TenDangNhap || "",
    maTK: d.MaTK || "",
    maKH: d.MaKH || "",
    maLeTan: d.MaLeTan || "",
    maAdmin: d.MaAdmin || "",
    be: d,
  };
}
/* Phiên đăng nhập thật nằm ở localStorage["hotel_user"] (do HotelAPI.session quản lý). */
ls(function () {
  localStorage.removeItem("sky_user");
  var a = JSON.parse(localStorage.getItem("sky_acc") || "[]");
  accs = SEED.concat(a);
  var raw = window.HotelAPI ? HotelAPI.session.get() : null;
  if (raw && raw.MaTK) {
    /* "Ghi nhớ đăng nhập" bỏ chọn -> phiên chỉ sống trong lần mở trình duyệt này */
    var keep =
      localStorage.getItem("sky_remember") !== "0" || sessionStorage.getItem("sky_alive");
    if (keep) {
      user = userFromBackend(raw);
    } else {
      HotelAPI.session.clear();
    }
  }
});
var $ = function (i) {
    return document.getElementById(i);
  },
  fr = $("fr");
function esc(s) {
  return String(s)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}
var TARGET_ORIGIN = (window.location.protocol === "file:") ? "*" : window.location.origin;

function can(k) {
  if (window.SkyGuard && window.SkyGuard.checkAccess) {
    var check = window.SkyGuard.checkAccess(k);
    return check.allowed;
  }
  var r = RULES[k];
  return !r || (user && r.indexOf(user.role) > -1);
}
function pub() {
  if (!user) return null;
  var uEm = (user.email || "").toLowerCase();
  var fullAcc = accs.filter(function (x) {
    return (x.email || "").toLowerCase() === uEm;
  })[0] || {};
  return {
    name: user.name || fullAcc.name || "Khách hàng",
    email: user.email || fullAcc.email || "",
    role: user.role || fullAcc.role || "customer",
    phone: user.phone || fullAcc.phone || "",
    username: user.username || "",
    maTK: user.maTK || "",
    maKH: user.maKH || "",
    maLeTan: user.maLeTan || "",
    maAdmin: user.maAdmin || "",
    be: user.be || null,
    dob: user.dob || fullAcc.dob || "",
    cccd: user.cccd || fullAcc.cccd || "",
    gender: user.gender || fullAcc.gender || "Nam",
    address: user.address || fullAcc.address || "",
    memberTier: user.memberTier || fullAcc.memberTier || "",
    memberSince: user.memberSince || fullAcc.memberSince || "",
    specialRequests: user.specialRequests || fullAcc.specialRequests || ""
  };
}
function toast(m) {
  var e = $("toast");
  e.textContent = m;
  e.style.display = "block";
  clearTimeout(toast.t);
  toast.t = setTimeout(function () {
    e.style.display = "none";
  }, 2200);
}
function setMobileMenu(open) {
  document.body.classList.toggle("mo", open);
  var button = document.querySelector("#hd [data-mb]");
  if (button) {
    button.setAttribute("aria-expanded", String(open));
    button.setAttribute("aria-label", open ? "Đóng menu" : "Mở menu");
  }
}
function render() {
  var adm = APAGES.indexOf(cur) > -1;
  document.body.className = (cur == "login" ? "lg" : "") + (adm ? " adm" : "");
  var on = function (a) {
    return a ? " on" : "";
  };
  var shop = ["rooms", "detail", "book", "pay", "fav"].indexOf(cur) > -1;
  var favCnt = window.SkyWishlist ? window.SkyWishlist.count() : 0;
  if (adm) {
    var isRec = cur == "rec";
    var quickBtns = "";
    if (isRec) {
      if (can("mgr")) {
        quickBtns += '<button class="adm-nav-btn" type="button" data-k="mgr:people">⚙️ Trang quản trị</button>';
      }
      quickBtns += '<button class="adm-nav-btn" type="button" data-k="home">🏠 Trang chủ</button>';
    } else {
      if (can("rec")) {
        quickBtns += '<button class="adm-nav-btn" type="button" data-k="rec">🛎️ Quầy lễ tân</button>';
      }
      quickBtns += '<button class="adm-nav-btn" type="button" data-k="home">🏠 Trang chủ</button>';
    }
    var adminH =
      '<button class="mb adm-toggle" data-adm-toggle="1" type="button" aria-label="Gạt thanh điều hướng quản trị" title="Gạt thanh quản trị vào / ra" aria-expanded="false"><span class="hamburger-icon" aria-hidden="true"><i></i></span></button>' +
      '<div class="lgo" data-k="' + (user ? HOME[user.role] : "mgr") + '"><span class="em"><img class="brand-svg" src="images/logo-sky-hotel-mark.svg" alt="" aria-hidden="true"></span><span class="lt"><b>SKY HOTEL</b><small>' + (user && user.role == "letan" ? "LỄ TÂN" : "QUẢN TRỊ VIÊN") + '</small></span></div>' +
      '<div class="act" style="margin-left: auto;">' +
      quickBtns +
      (user ?
        '<div class="um"><button class="ub" data-menu="1"><span class="av">' +
        esc(user.name.charAt(0).toUpperCase()) +
        '</span>' + esc(user.name) + ' ▾</button><div class="mn" id="mn"><small>' +
        esc(user.email) + ' · ' + ROLE[user.role] + '</small>' +
        '<div data-out="1">↪ Đăng xuất</div></div></div>' : '') +
      '</div>';
    $("hd").innerHTML = adminH;
  } else {
    var isAdminUser = !!(user && user.role == "admin");
    var h =
      '<div class="lgo" data-k="home"><span class="em"><img class="brand-svg" src="images/logo-sky-hotel-mark.svg" alt="" aria-hidden="true"></span><span class="lt"><b>SKY HOTEL</b><small>DISTRICT 10 · HCMC</small></span></div><nav class="nr" aria-label="Điều hướng chính"><a data-k="home" class="' +
      on(cur == "home") +
      '">Trang chủ</a><a data-sec="phong" class="' +
      on(shop) +
      '">Phòng nghỉ</a><a data-sec="dich-vu" class="' +
      on(cur == "service") +
      '">Dịch vụ</a><a data-sec="dich-vu-tien-ich" class="' +
      on(cur == "amenity") +
      '">Dịch vụ tiện ích khách sạn</a><a data-k="about" class="' +
      on(cur == "about") +
      '">Về chúng tôi</a><a data-sec="lien-he">Liên hệ</a><div class="act">' +
      (isAdminUser ? "" : '<button class="fav-btn" data-fav-open="1" type="button" title="Xem danh sách phòng yêu thích" aria-label="Phòng yêu thích"><span class="fav-ico">♥</span><span class="fav-txt">Yêu thích</span><span class="fav-badge" id="fav-badge">' + favCnt + '</span></button>');
    if (user) {
      var dropdownItems = isAdminUser
        ? '<div data-k="mgr">⚙️ Vào trang quản trị</div>' +
          '<div data-k="rec">🛎️ Quầy lễ tân</div>' +
          '<div data-out="1">↪ Đăng xuất</div>'
        : '<div data-k="hist:profile">👤 Thông tin cá nhân</div>' +
          '<div data-k="hist:bookings">📋 Lịch sử đặt phòng</div>' +
          (user.role == "letan" ? '<div data-k="rec">🛎 Vào quầy lễ tân</div>' : '') +
          '<div data-fav-open="1">❤️ Phòng yêu thích (<span class="user-fav-count">' + favCnt + '</span>)</div>' +
          '<div data-out="1">↪ Đăng xuất</div>';
      h +=
        '<div class="um"><button class="ub" data-menu="1"><span class="av">' +
        esc(user.name.charAt(0).toUpperCase()) +
        "</span>" +
        esc(user.name) +
        ' ▾</button><div class="mn" id="mn"><small>' +
        esc(user.email) +
        " · " +
        ROLE[user.role] +
        "</small>" +
        dropdownItems +
        "</div></div>";
    } else
      h +=
        '<button class="b" data-tab="login">Đăng nhập</button><button class="b w" data-tab="register">Đăng ký</button>';
    var mobileUserLinks = !user
      ? '<a data-tab="login">🔑 Đăng nhập</a><a data-tab="register">📝 Đăng ký</a>'
      : (isAdminUser
          ? '<a data-k="mgr">⚙️ Vào trang quản trị</a><a data-k="rec">🛎️ Quầy lễ tân</a><a data-out="1">↪ Đăng xuất</a>'
          : '<a data-k="hist:profile">👤 Thông tin cá nhân</a><a data-k="hist:bookings">📋 Lịch sử đặt phòng</a><a data-out="1">↪ Đăng xuất</a>');
    $("hd").innerHTML =
      h +
      '</div>' +
      (isAdminUser ? '' : '<button class="bk" data-k="rooms">Đặt phòng</button>') +
      '<button class="mb" data-mb="1" type="button" aria-label="Mở menu" aria-expanded="false"><span class="hamburger-icon" aria-hidden="true"><i></i></span></button></nav><nav class="mm" id="mobile-menu" aria-label="Điều hướng chính"><a data-k="home">Trang chủ</a><a data-sec="phong">Phòng nghỉ</a><a data-sec="dich-vu">Dịch vụ</a><a data-sec="dich-vu-tien-ich">Dịch vụ tiện ích khách sạn</a><a data-k="about">Về chúng tôi</a><a data-sec="lien-he">Liên hệ</a>' +
      (isAdminUser ? "" : '<a data-fav-open="1">❤️ Phòng yêu thích (<span class="mob-fav-count">' + favCnt + '</span>)</a>') +
      mobileUserLinks +
      "</nav>";
  }
  var s =
    '<div class="sd-head" style="justify-content: flex-end; margin: 0;"><button class="sd-close-btn" data-sd-close="1" type="button" aria-label="Đóng thanh quản trị">✕</button></div>';
  var sideList = cur == "rec" || (user && user.role == "letan") ? SIDE_LETAN : SIDE_ADMIN;
  sideList.forEach(function (x) {
    var sk = x[0].split(":");
    if (!can(sk[0])) return;
    var activeTab = sk[0] == "rec" ? recTab : mgrTab;
    s +=
      '<div class="it' +
      on(cur == sk[0] && (!sk[1] || sk[1] == activeTab)) +
      '" data-k="' +
      x[0] +
      '">' +
      x[1] +
      "</div>";
  });
  s +=
    '<div class="sp"></div>' +
    '<div class="it" data-out="1">↪ Đăng xuất</div>';
  if (user)
    s +=
      '<div class="who">👤 ' +
      esc(user.name) +
      "<br>" +
      ROLE[user.role] +
      "</div>";
  $("sd").innerHTML = s;
}
var pendSec = "",
  frameReady = false,
  recTab = "bookings", // tab đang mở trong trang Lễ tân: "bookings" | "rooms"
  mgrTab = "people"; // tab đang mở trong trang Admin: "people" | "bookings" | "services" | "promotions" | "rooms" | "stats"
function sec(id) {
  setMobileMenu(false);
  pendSec = id;
  if (cur != "home") {
    go("home");
  } else if (frameReady) {
    fr.contentWindow.postMessage({ skyScroll: pendSec }, TARGET_ORIGIN);
    pendSec = "";
  }
}
var LEGACY_MGR_MAP = {
  customers: "people",
  staff: "people",
  invoices: "bookings",
  services_mgr: "services",
  promos: "promotions",
  rooms_mgr: "rooms",
  room_types: "rooms",
  hotel: "stats",
};
var PAGE_TITLES = {
  home: "SkyHotel - Khách Sạn Nghỉ Dưỡng Sang Trọng Quận 10",
  about: "SkyHotel - Về Chúng Tôi",
  rooms: "SkyHotel - Chọn Phòng Nghỉ Dưỡng",
  detail: "SkyHotel - Chi Tiết Phòng Nghỉ",
  book: "SkyHotel - Đặt Phòng Trực Tuyến",
  pay: "SkyHotel - Thanh Toán",
  hist: "SkyHotel - Lịch Sử Đặt Phòng & Hồ Sơ",
  login: "SkyHotel - Đăng Nhập / Đăng Ký",
  service: "SkyHotel - Dịch Vụ Cao Cấp",
  amenity: "SkyHotel - Dịch Vụ Tiện Ích",
  fav: "SkyHotel - Danh Sách Phòng Yêu Thích",
  rec: "SkyHotel - Nghiệp Vụ Lễ Tân",
  mgr: "SkyHotel - Quản Trị Hệ Thống"
};
function getFrameFile() {
  try {
    var p = (fr && fr.contentWindow && fr.contentWindow.location && fr.contentWindow.location.pathname) || "";
    var idx = p.lastIndexOf("/pages/");
    if (idx > -1) return "pages/" + p.slice(idx + 7);
  } catch (e) {}
  return "";
}
function loadFrameUrl(nextUrl) {
  if ((hadInitialRoute || fr.getAttribute("src") || getFrameFile()) && fr && fr.contentWindow && fr.contentWindow.location) {
    try {
      fr.contentWindow.location.replace(nextUrl);
      return;
    } catch (e) {}
  }
  fr.src = nextUrl;
}
function syncHistory(k, arg, fromHistory) {
  try {
    var newHash = "#/" + k + (arg ? "?tab=" + encodeURIComponent(arg) : "");
    if (!fromHistory && window.location.hash !== newHash) {
      if (hadInitialRoute) {
        window.history.pushState({ skyKey: k, skyArg: arg }, "", newHash);
      } else {
        window.history.replaceState({ skyKey: k, skyArg: arg }, "", newHash);
      }
    }
    hadInitialRoute = true;
    document.title = PAGE_TITLES[k] || "SkyHotel";
  } catch (eHash) {}
}
function go(k, opts) {
  var fromHistory = !!(opts && opts.fromHistory);
  var arg = "";
  if (String(k).indexOf(":") > -1) {
    arg = k.split(":")[1];
    k = k.split(":")[0];
  }
  if (LEGACY_MGR_MAP[k]) {
    arg = LEGACY_MGR_MAP[k];
    k = "mgr";
  }
  if (!can(k)) {
    if (user && user.role === "admin") {
      toast("Tài khoản Quản trị viên không sử dụng chức năng đặt phòng và xem lịch sử đặt phòng.");
      if (!cur || !getFrameFile()) {
        k = HOME.admin;
        arg = "";
      } else {
        try {
          var prevHash = "#/" + cur + (curArg ? "?tab=" + encodeURIComponent(curArg) : "");
          if (window.location.hash !== prevHash) {
            window.history.replaceState({ skyKey: cur, skyArg: curArg }, "", prevHash);
          }
        } catch (eRestore) {}
        return;
      }
    } else {
      pending = k;
      notice = user
        ? "Tài khoản của bạn không có quyền truy cập trang này. Hãy đăng nhập bằng tài khoản khác."
        : "Vui lòng đăng nhập để tiếp tục.";
      k = "login";
      arg = "";
    }
  }
  if (!PAGES[k]) return;
  if (k == "rec") recTab = arg == "rooms" ? "rooms" : "bookings";
  if (k == "mgr" && arg) mgrTab = arg;
  setMobileMenu(false);

  var curFile = getFrameFile();
  var sameLoadedPage = curFile === PAGES[k];
  if (cur == k && fr.contentWindow && sameLoadedPage) {
    curArg = arg;
    syncHistory(k, arg, fromHistory);
    if (arg) {
      var hashStr = "#" + encodeURIComponent(arg);
      try {
        fr.contentWindow.history.replaceState(null, "", hashStr);
      } catch (e) {
        try { fr.contentWindow.location.hash = hashStr; } catch (e2) {}
      }
      fr.contentWindow.postMessage({ skyHash: arg, skyTab: arg }, TARGET_ORIGIN);
    }
    render();
    return;
  }

  cur = k;
  curArg = arg;
  frameReady = false;
  loadFrameUrl(PAGES[k] + (arg ? "#" + encodeURIComponent(arg) : ""));
  syncHistory(k, arg, fromHistory);
  render();
}
function setUser(a, rem) {
  user = {
    name: a.name,
    email: a.email,
    role: a.role,
    phone: a.phone || "",
    username: a.username || "",
    maTK: a.maTK || "",
    maKH: a.maKH || "",
    maLeTan: a.maLeTan || "",
    maAdmin: a.maAdmin || "",
    be: a.be || null,
    dob: a.dob || "",
    cccd: a.cccd || "",
    gender: a.gender || "Nam",
    address: a.address || "",
    memberTier: a.memberTier || "",
    memberSince: a.memberSince || "",
    specialRequests: a.specialRequests || ""
  };
  ls(function () {
    if (window.HotelAPI && user.be) HotelAPI.session.set(user.be);
    localStorage.setItem("sky_remember", rem ? "1" : "0");
    sessionStorage.setItem("sky_alive", "1");
  });
}
function finish() {
  var t = pending && can(pending) ? pending : HOME[user.role];
  pending = null;
  notice = "";
  go(t);
}
function logout() {
  user = null;
  pending = null;
  notice = "";
  ls(function () {
    localStorage.removeItem("sky_remember");
    sessionStorage.removeItem("sky_alive");
    if (window.HotelAPI) HotelAPI.session.clear();
  });
  go("home");
}
function reply(o) {
  fr.contentWindow.postMessage({ skyAuthRes: o }, TARGET_ORIGIN);
}
function auth(a) {
  if (a.op == "login") {
    var un = (a.email || "").trim();
    if (un.indexOf("@") > -1)
      return reply({
        op: "login",
        ok: false,
        msg: "Hệ thống đăng nhập bằng tên đăng nhập, không dùng email.",
      });
    HotelAPI.login(un, a.pw)
      .then(function (r) {
        setUser(userFromBackend(r.data), a.remember);
        finish();
      })
      .catch(function (e) {
        reply({ op: "login", ok: false, msg: e.message });
      });
  } else if (a.op == "register") {
    HotelAPI.register({
      hoTen: a.name,
      sdt: a.phone,
      email: a.email,
      tenDangNhap: a.username,
      matKhau: a.pw,
    })
      .then(function () {
        /* Đăng ký xong thì đăng nhập luôn */
        return HotelAPI.login(a.username, a.pw).then(
          function (r) {
            setUser(userFromBackend(r.data), true);
            finish();
          },
          function () {
            reply({
              op: "register",
              ok: true,
              msg: "Đăng ký thành công! Hãy đăng nhập bằng tên đăng nhập vừa tạo.",
            });
          },
        );
      })
      .catch(function (e) {
        reply({ op: "register", ok: false, msg: e.message });
      });
  } else if (a.op == "social") {
    reply({
      op: "social",
      ok: false,
      msg: "Đăng nhập bằng " + a.provider + " chưa được hỗ trợ. Vui lòng dùng tên đăng nhập và mật khẩu.",
    });
  }
}
/* ===== Wishlist Drawer Controller ===== */
function updateFavBadges() {
  var count = window.SkyWishlist ? window.SkyWishlist.count() : 0;
  var b1 = $("fav-badge"), b2 = $("drawer-fav-count");
  if (b1) b1.textContent = count;
  if (b2) b2.textContent = count;
  document.querySelectorAll(".user-fav-count, .mob-fav-count").forEach(function (el) {
    el.textContent = count;
  });
}
function openWishlistDrawer() {
  if (user && user.role === "admin") {
    toast("Tài khoản Quản trị viên không sử dụng danh sách phòng yêu thích.");
    return;
  }
  var overlay = $("fav-overlay"), drawer = $("fav-drawer");
  if (!overlay || !drawer) return;
  renderWishlistDrawer();
  overlay.classList.add("open");
  drawer.classList.add("open");
  overlay.setAttribute("aria-hidden", "false");
  drawer.setAttribute("aria-hidden", "false");
  setMobileMenu(false);
}
function closeWishlistDrawer() {
  var overlay = $("fav-overlay"), drawer = $("fav-drawer");
  if (!overlay || !drawer) return;
  overlay.classList.remove("open");
  drawer.classList.remove("open");
  overlay.setAttribute("aria-hidden", "true");
  drawer.setAttribute("aria-hidden", "true");
}
function fmtMoney(n) {
  return Number(n).toLocaleString("vi-VN") + "đ";
}
function resolveShellAsset(src) {
  var fallback = "images/rooms/superior-1.jpg";
  if (!src) return fallback;
  var s = String(src).trim();
  if (s.indexOf("../") === 0) return s.slice(3);
  return s;
}
function renderWishlistDrawer() {
  var body = $("fav-drawer-body");
  if (!body) return;
  updateFavBadges();
  var ids = window.SkyWishlist ? window.SkyWishlist.getAll() : [];
  if (!ids.length) {
    body.innerHTML =
      '<div class="sky-fav-drawer-empty">' +
      '<div class="sky-fav-drawer-empty-ico">🤍</div>' +
      '<h4>Chưa có phòng yêu thích</h4>' +
      '<p>Khám phá các hạng phòng và bấm nút “Lưu vào danh sách” để lưu phòng tại đây.</p>' +
      '<button type="button" class="drawer-go-rooms">Khám phá phòng ngay</button>' +
      '</div>';
    var b = body.querySelector(".drawer-go-rooms");
    if (b) b.onclick = function () { closeWishlistDrawer(); go("rooms"); };
    return;
  }

  var rooms = ids.map(function (id) {
    return window.SkyAPI ? window.SkyAPI.getRoomType(id) : null;
  }).filter(Boolean);

  body.innerHTML = rooms.map(function (r) {
    var imgSrc = resolveShellAsset(r.image || (r.gallery && r.gallery[0]));
    return (
      '<div class="sky-fav-item">' +
        '<div class="sky-fav-item-top">' +
          '<img class="sky-fav-item-img" src="' + esc(imgSrc) + '" alt="' + esc(r.name) + '" onerror="this.onerror=null;this.src=\'images/rooms/superior-1.jpg\';">' +
          '<div class="sky-fav-item-info">' +
            '<h5 class="sky-fav-item-title">' + esc(r.name) + '</h5>' +
            '<div class="sky-fav-item-view">' + esc(r.view || "View thành phố") + '</div>' +
            '<div class="sky-fav-item-specs">' + r.area + ' m² · ' + r.capacity + ' khách</div>' +
            '<div class="sky-fav-item-price">' + fmtMoney(r.price) + ' <small>/đêm</small></div>' +
          '</div>' +
        '</div>' +
        '<div class="sky-fav-item-actions">' +
          '<button type="button" data-act-detail="' + r.id + '">Xem chi tiết</button>' +
          '<button type="button" data-act-book="' + r.id + '">Đặt ngay</button>' +
          '<button type="button" class="btn-rm" data-act-rm="' + r.id + '" title="Bỏ lưu">Bỏ lưu</button>' +
        '</div>' +
      '</div>'
    );
  }).join("");

  body.querySelectorAll("[data-act-detail]").forEach(function (btn) {
    btn.onclick = function () {
      var id = btn.getAttribute("data-act-detail");
      closeWishlistDrawer();
      go("detail:" + id);
    };
  });
  body.querySelectorAll("[data-act-book]").forEach(function (btn) {
    btn.onclick = function () {
      closeWishlistDrawer();
      go("rooms");
    };
  });
  body.querySelectorAll("[data-act-rm]").forEach(function (btn) {
    btn.onclick = function () {
      var id = btn.getAttribute("data-act-rm");
      if (window.SkyWishlist) {
        window.SkyWishlist.remove(id);
        renderWishlistDrawer();
        toast("Đã bỏ lưu phòng");
      }
    };
  });
}

// Drawer Event Listeners
var overlayEl = $("fav-overlay"), closeBtnEl = $("fav-close-btn");
if (overlayEl) overlayEl.onclick = closeWishlistDrawer;
if (closeBtnEl) closeBtnEl.onclick = closeWishlistDrawer;

var clearDrawerBtn = $("fav-drawer-clear");
if (clearDrawerBtn) {
  clearDrawerBtn.onclick = function () {
    var msg = "Bạn có chắc muốn xóa tất cả phòng yêu thích?";
    var doClear = function () {
      if (window.SkyWishlist) window.SkyWishlist.clear();
      renderWishlistDrawer();
      toast("Đã xóa toàn bộ danh sách phòng yêu thích");
    };
    if (window.SkyUI) {
      window.SkyUI.confirm(msg).then(function (ok) {
        if (ok) doClear();
      });
    }
  };
}

var viewFullBtn = $("fav-view-full");
if (viewFullBtn) {
  viewFullBtn.onclick = function () {
    closeWishlistDrawer();
    go("fav");
  };
}

window.addEventListener("sky_wishlist_change", updateFavBadges);

document.addEventListener("click", function (e) {
  if (e.target && e.target.id === "sd-overlay") {
    document.body.classList.remove("sd-open");
    return;
  }
  var t = e.target.closest(
      "[data-k],[data-tab],[data-out],[data-menu],[data-soon],[data-sec],[data-mb],[data-fav-open],[data-adm-toggle],[data-sd-close]",
    ),
    mn = $("mn");
  if (
    document.body.classList.contains("mo") &&
    !(t && t.closest("#hd"))
  )
    setMobileMenu(false);
  if (mn && !(t && t.dataset.menu)) mn.classList.remove("on");
  if (!t) return;
  var d = t.dataset;
  if (d.admToggle) {
    if (window.innerWidth <= 1024) {
      document.body.classList.toggle("sd-open");
    } else {
      document.body.classList.toggle("sd-collapsed");
    }
    return;
  }
  if (d.sdClose) {
    document.body.classList.remove("sd-open");
    return;
  }
  if (d.favOpen) {
    openWishlistDrawer();
    return;
  }
  if (d.menu && mn) mn.classList.toggle("on");
  else if (d.k) {
    if (document.body.classList.contains("sd-open")) {
      document.body.classList.remove("sd-open");
    }
    go(d.k);
  } else if (d.tab) {
    loginTab = d.tab;
    if (cur == "login" && fr && fr.contentWindow) {
      try {
        fr.contentWindow.postMessage({ skyTab: d.tab, skyInit: { tab: d.tab } }, "*");
      } catch (err) {}
    } else {
      go("login");
    }
  } else if (d.out) logout();
  else if (d.mb) setMobileMenu(!document.body.classList.contains("mo"));
  else if (d.sec) sec(d.sec);
  else if (d.soon) toast(d.soon + " sẽ sớm ra mắt");
});
document.addEventListener("keydown", function (e) {
  if (e.key == "Escape") {
    if (document.body.classList.contains("sd-open")) {
      document.body.classList.remove("sd-open");
      return;
    }
    if ($("fav-drawer") && $("fav-drawer").classList.contains("open")) {
      closeWishlistDrawer();
      return;
    }
    if (document.body.classList.contains("mo")) {
      setMobileMenu(false);
      var button = document.querySelector("#hd [data-mb]");
      if (button) button.focus();
    }
  }
});
window.addEventListener("message", function (e) {
  if (window.SkyGuard && !window.SkyGuard.isSafeOrigin(e)) return;
  if (e.source !== fr.contentWindow) return;
  var d = e.data || {};
  if (d.skyMenu) setMobileMenu(!document.body.classList.contains("mo"));
  if (d.skySec) sec(d.skySec);
  else if (d.skyGo) go(d.skyGo);
  if (d.skyLogout) logout();
  if (d.skyAuth) auth(d.skyAuth);
  if (d.skyToast) toast(d.skyToast);
  if (d.skyRecTab && cur == "rec" && d.skyRecTab != recTab) {
    recTab = d.skyRecTab == "rooms" ? "rooms" : "bookings";
    render();
  }
  if (d.skyMgrTab && cur == "mgr" && d.skyMgrTab != mgrTab) {
    mgrTab = d.skyMgrTab;
    render();
  }
  if (d.skyOpenWishlist) openWishlistDrawer();
  if (d.skyRoomTypesUpdated) {
    window.dispatchEvent(new CustomEvent("sky_room_types_updated"));
  }
  if (d.skyFavChange !== undefined) {
    updateFavBadges();
    if ($("fav-drawer") && $("fav-drawer").classList.contains("open")) {
      renderWishlistDrawer();
    }
  }
  if (d.skyUpdateProfile) {
    var p = d.skyUpdateProfile;
    if (user) {
      if (p.name) user.name = p.name;
      if (p.phone !== undefined) user.phone = p.phone;
      if (p.dob !== undefined) user.dob = p.dob;
      if (p.cccd !== undefined) user.cccd = p.cccd;
      if (p.gender !== undefined) user.gender = p.gender;
      if (p.address !== undefined) user.address = p.address;
      if (p.specialRequests !== undefined) user.specialRequests = p.specialRequests;

      var foundAcc = false;
      var uEm = (user.email || "").toLowerCase();
      for (var i = 0; i < accs.length; i++) {
        if ((accs[i].email || "").toLowerCase() === uEm) {
          Object.assign(accs[i], p);
          if (p.pw) accs[i].pw = p.pw;
          foundAcc = true;
          break;
        }
      }
      if (!foundAcc) {
        var nAcc = Object.assign({ email: user.email, role: user.role, name: user.name }, p);
        accs.push(nAcc);
      }
      ls(function () {
        if (window.HotelAPI) {
          var hu = HotelAPI.session.get() || user.be || {};
          if (p.name !== undefined) hu.HoTen = p.name;
          if (p.phone !== undefined) hu.SDT = p.phone;
          if (p.email !== undefined) hu.Email = p.email;
          if (p.dob !== undefined) hu.NgaySinh = p.dob;
          if (p.cccd !== undefined) hu.SoGiayTo = p.cccd;
          HotelAPI.session.set(hu);
        }
        localStorage.setItem("sky_acc", JSON.stringify(accs.slice(SEED.length)));
      });
      render();
      toast("Đã cập nhật thông tin tài khoản thành công!");
      try {
        fr.contentWindow.postMessage({ skyProfileUpdated: true, user: pub() }, TARGET_ORIGIN);
      } catch (err) {}
    }
  }
  if (d.skyReady) {
    frameReady = true;
    updateFavBadges();
    if (pendSec && cur == "home") {
      fr.contentWindow.postMessage({ skyScroll: pendSec }, TARGET_ORIGIN);
      pendSec = "";
    }
    var L = cur == "login";
    var frameHashTab = "";
    try {
      frameHashTab = fr.contentWindow && fr.contentWindow.location && fr.contentWindow.location.hash
        ? fr.contentWindow.location.hash.slice(1)
        : "";
    } catch (eHash) {}
    var initTab = L ? loginTab : (cur == "hist" ? (curArg || frameHashTab || (fr.src.indexOf("#") > -1 ? fr.src.split("#")[1] : "")) : "");
    fr.contentWindow.postMessage(
      {
        skyInit: {
          user: pub(),
          notice: L ? notice : "",
          tab: initTab,
        },
      },
      TARGET_ORIGIN,
    );
    if (L) {
      notice = "";
      loginTab = "";
    }
  }
});
function parseHashRoute() {
  var h = (window.location.hash || "").replace(/^#\/?/, "").trim();
  if (!h) return { page: "home", arg: "", valid: false };
  var parts = h.split("?");
  var page = parts[0];
  var query = parts[1] || "";
  var arg = "";
  if (query) {
    var match = /[?&]tab=([^&]+)/.exec("?" + query);
    if (match) arg = decodeURIComponent(match[1]);
  }
  return { page: page, arg: arg, valid: !!PAGES[page] };
}

function initHashRouting(fromHistory) {
  var r = parseHashRoute();
  if (r.valid) {
    go(r.arg ? r.page + ":" + r.arg : r.page, { fromHistory: !!fromHistory });
  } else {
    go("home", { fromHistory: !!fromHistory });
  }
}

function handleHistoryNav() {
  var r = parseHashRoute();
  var targetPage = r.valid ? r.page : "home";
  var targetArg = r.valid ? r.arg : "";
  var curFile = getFrameFile();
  if (targetPage !== cur || targetArg !== curArg || (PAGES[targetPage] && curFile !== PAGES[targetPage])) {
    initHashRouting(true);
  }
}

window.addEventListener("popstate", handleHistoryNav);
window.addEventListener("hashchange", handleHistoryNav);

function syncFrameWithShell() {
  var loadedFile = getFrameFile();
  if (!loadedFile || !PAGES[cur] || loadedFile === PAGES[cur]) return;
  var syncedKey = FILE_TO_KEY[loadedFile];
  if (!syncedKey) return;
  var frameArg = "";
  try {
    frameArg = decodeURIComponent((fr.contentWindow.location.hash || "").slice(1)).trim();
  } catch (e) {}
  cur = syncedKey;
  curArg = frameArg;
  try {
    var fixedHash = "#/" + syncedKey + (frameArg ? "?tab=" + encodeURIComponent(frameArg) : "");
    if (window.location.hash !== fixedHash) {
      window.history.replaceState({ skyKey: syncedKey, skyArg: frameArg }, "", fixedHash);
    }
    document.title = PAGE_TITLES[syncedKey] || "SkyHotel";
  } catch (e2) {}
  render();
}

if (fr) {
  fr.addEventListener("load", function () {
    syncFrameWithShell();
    try {
      if (fr.contentWindow) {
        fr.contentWindow.removeEventListener("pageshow", syncFrameWithShell);
        fr.contentWindow.addEventListener("pageshow", syncFrameWithShell);
      }
    } catch (ePS) {}
  });
}

initHashRouting(false);
