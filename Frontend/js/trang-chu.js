/* Trang chủ SKY HOTEL: chuyển trang, cuộn mục, đánh giá khách hàng, đăng ký nhận ưu đãi */
(function () {
  var $ = function (i) { return document.getElementById(i); };
  try {
    var h = JSON.parse(localStorage.getItem("sky_hotel") || "null");
    if (h)
      document.querySelectorAll("[data-h]").forEach(function (e) {
        if (h[e.dataset.h]) e.textContent = h[e.dataset.h];
      });
  } catch (e) {}
  function scroll(id) {
    var el = $(id);
    if (el) el.scrollIntoView({ behavior: "smooth" });
  }
  /* Ngày địa phương để tránh lệch ngày do chuyển múi giờ khi dùng toISOString(). */
  function iso(d) {
    return d.getFullYear() + "-" + String(d.getMonth() + 1).padStart(2, "0") + "-" + String(d.getDate()).padStart(2, "0");
  }
  function addDays(value, days) {
    var parts = value.split("-").map(Number);
    var date = new Date(parts[0], parts[1] - 1, parts[2]);
    date.setDate(date.getDate() + days);
    return iso(date);
  }
  var today = iso(new Date());
  var initialOccupancy = { adults: 2, children: 0, rooms: 1 };
  var initialCheckIn = today;
  var initialCheckOut = addDays(today, 1);
  try {
    var savedSel = JSON.parse(localStorage.getItem("sky_sel") || "null");
    if (savedSel) {
      if (savedSel.checkIn && savedSel.checkIn >= today) {
        initialCheckIn = savedSel.checkIn;
        if (savedSel.checkOut && savedSel.checkOut > savedSel.checkIn) {
          initialCheckOut = savedSel.checkOut;
        } else {
          initialCheckOut = addDays(initialCheckIn, 1);
        }
      }
      if (savedSel.occupancy) {
        initialOccupancy = window.SkyOccupancyPicker.toState(savedSel.occupancy);
      } else if (savedSel.adults || savedSel.rooms) {
        initialOccupancy = window.SkyOccupancyPicker.toState({
          adults: savedSel.adults || savedSel.guests || 2,
          children: savedSel.children || 0,
          rooms: savedSel.rooms || 1
        });
      }
    }
  } catch (e) {}

  function saveSearchToStorage(clearItems) {
    var checkIn = $("d1").value;
    var checkOut = $("d2").value;
    var occ = occupancyPicker.getValue();
    var guests = occ.adults + occ.children;
    var existingItems = [];
    if (!clearItems) {
      try {
        var prev = JSON.parse(localStorage.getItem("sky_sel") || "null");
        if (prev && Array.isArray(prev.items)) existingItems = prev.items;
      } catch (e) {}
    }
    localStorage.setItem("sky_sel", JSON.stringify({
      checkIn: checkIn,
      checkOut: checkOut,
      guests: guests,
      adults: occ.adults,
      children: occ.children,
      rooms: occ.rooms,
      occupancy: occ,
      items: existingItems,
      savedAt: Date.now(),
    }));
  }

  var occupancyPicker = window.SkyOccupancyPicker.init(
    $("home-occupancy"),
    initialOccupancy,
    function () {
      try { saveSearchToStorage(true); } catch (e) {}
    }
  );
  function startRoomSearch() {
    var checkIn = $("d1").value;
    var checkOut = $("d2").value;
    var nights = (new Date(checkOut + "T00:00:00") - new Date(checkIn + "T00:00:00")) / 86400000;
    var error = "";
    if (!checkIn || checkIn < today) error = "Vui lòng chọn ngày nhận phòng từ hôm nay trở đi.";
    else if (!checkOut || checkOut <= checkIn) error = "Ngày trả phòng phải sau ngày nhận phòng.";
    else if (nights > 30) error = "Thời gian lưu trú tối đa là 30 đêm.";
    if (error) {
      $("home-search-error").textContent = error;
      return false;
    }
    try {
      saveSearchToStorage(true);
    } catch (e) {
      $("home-search-error").textContent = "Không thể lưu thông tin tìm phòng trong trình duyệt.";
      return false;
    }
    $("home-search-error").textContent = "";
    return true;
  }
  $("home-booking-form").addEventListener("submit", function (e) {
    e.preventDefault();
    if (!startRoomSearch()) return;
    parent.postMessage({ skyGo: "rooms" }, "*");
  });
  function isAdminViewer() {
    try {
      var hu = JSON.parse(localStorage.getItem("hotel_user") || "null");
      if (hu && (hu.VaiTro === "Admin" || hu.role === "admin")) return true;
    } catch (e) {}
    try {
      if (parent && parent !== window && parent.user && parent.user.role === "admin") return true;
    } catch (e2) {}
    return false;
  }
  document.addEventListener("click", function (e) {
    var g = e.target.closest("[data-go]"), s = e.target.closest("[data-sc]");
    if (g) {
      var targetGo = g.dataset.go;
      if (isAdminViewer() && (targetGo === "book" || targetGo === "pay" || targetGo === "hist" || targetGo === "fav")) {
        e.preventDefault();
        parent.postMessage({ skyToast: "Tài khoản Quản trị viên không sử dụng chức năng đặt phòng và xem lịch sử đặt phòng." }, "*");
        return;
      }
      parent.postMessage({ skyGo: targetGo }, "*");
    }
    else if (s) scroll(s.dataset.sc);
  });
  window.addEventListener("message", function (e) {
    if (e.data && e.data.skyScroll) scroll(e.data.skyScroll);
  });
  $("d1").min = today;
  $("d1").value = initialCheckIn;
  $("d2").value = initialCheckOut;
  function syncCheckout() {
    var checkIn = $("d1").value || today;
    var earliestCheckout = addDays(checkIn, 1);
    $("d2").min = earliestCheckout;
    if (!$("d2").value || $("d2").value < earliestCheckout) {
      $("d2").value = earliestCheckout;
    }
  }
  syncCheckout();
  $("d1").onchange = function () {
    syncCheckout();
    try { saveSearchToStorage(true); } catch (e) {}
  };
  $("d2").onchange = function () {
    syncCheckout();
    try { saveSearchToStorage(true); } catch (e) {}
  };
  /* đánh giá */
  var R = [
    ["Nguyễn Thị Mai Anh", "Khách hàng từ TP. Hồ Chí Minh", "Khách sạn có vị trí rất thuận tiện, phòng ốc sạch sẽ, nhân viên thân thiện và chuyên nghiệp. Tôi chắc chắn sẽ quay lại trong những chuyến đi tới!"],
    ["Trần Minh Khoa", "Khách hàng từ Hà Nội", "Bữa sáng phong phú, Skybar lên đèn về đêm rất đẹp. Lễ tân chu đáo từ lúc nhận phòng đến khi trả phòng."],
    ["Sarah Johnson", "Khách hàng từ Sydney, Úc", "Căn phòng rộng rãi với tầm nhìn thành phố tuyệt đẹp. Nhân viên chăm sóc cả gia đình tôi rất chu đáo."]
  ], k = 0, tm;
  var dots = $("dots");
  R.forEach(function (_, i) { var d = document.createElement("i"); d.onclick = function () { show(i); }; dots.appendChild(d); });
  function show(i) {
    k = (i + R.length) % R.length;
    $("tn").textContent = R[k][0]; $("tl").textContent = R[k][1]; $("tq").textContent = "“" + R[k][2] + "”";
    $("tav").textContent = R[k][0].split(" ").pop().charAt(0);
    [].forEach.call(dots.children, function (d, j) { d.className = j == k ? "on" : ""; });
    clearInterval(tm); tm = setInterval(function () { show(k + 1); }, 7000);
  }
  $("pv").onclick = function () { show(k - 1); };
  $("nx").onclick = function () { show(k + 1); };
  show(0);
  $("nlb").onclick = function () {
    var ok = /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test($("nlm").value.trim());
    $("nlr").textContent = ok ? "Cảm ơn bạn đã đăng ký (mô phỏng)." : "Email chưa hợp lệ.";
  };
  var filterBar = $("home-room-filters");
  if (filterBar) {
    filterBar.addEventListener("click", function (e) {
      var btn = e.target.closest(".rm-filter-btn");
      if (!btn) return;
      var filter = btn.getAttribute("data-filter") || "all";
      filterBar.querySelectorAll(".rm-filter-btn").forEach(function (b) {
        var on = b === btn;
        b.classList.toggle("is-on", on);
        b.setAttribute("aria-pressed", on ? "true" : "false");
      });
      document.querySelectorAll("#phong .rm").forEach(function (card) {
        var cap = parseInt(card.getAttribute("data-cap") || "0", 10);
        var showCard =
          filter === "all" ||
          (filter === "couple" && cap >= 1 && cap <= 2) ||
          (filter === "family" && cap >= 3 && cap <= 5) ||
          (filter === "vip" && cap >= 6);
        card.classList.toggle("is-filtered-out", !showCard);
        if (showCard) card.classList.add("in");
      });
    });
  }
  if (!("IntersectionObserver" in window)) return;
  var io = new IntersectionObserver(function (l) {
    l.forEach(function (x) { if (x.isIntersecting) { x.target.classList.add("in"); io.unobserve(x.target); } });
  }, { threshold: 0.1 });
  document.querySelectorAll(".rm, .sv, .hd2, .tc").forEach(function (e) { e.classList.add("rv"); io.observe(e); });
})();
