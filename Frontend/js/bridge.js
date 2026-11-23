/* Cầu nối giữa từng trang và khung website (index.html): chuyển trang, đăng nhập/đăng xuất */
(function () {
  var TARGET_ORIGIN = (window.location.protocol === "file:") ? "*" : window.location.origin;

  function isSafeOrigin(e) {
    if (window.location.protocol === "file:") return true;
    return e && e.origin === window.location.origin;
  }
  try {
    if (window.parent && window.parent !== window && window.parent.PAGES) {
      var P = window.parent;
      P.PAGES.amenity = "pages/dich-vu-tien-ich.html";
      var ensureAmenityNav = function () {
        if (!P.document) return;
        var nr = P.document.querySelector("#hd .nr");
        if (nr) {
          var uuDai = nr.querySelector('[data-sec="uu-dai"]');
          if (uuDai) uuDai.remove();
          var oldAm = nr.querySelector('[data-k="amenity"]');
          if (oldAm) {
            oldAm.removeAttribute("data-k");
            oldAm.setAttribute("data-sec", "dich-vu-tien-ich");
          }
          if (!nr.querySelector('[data-sec="dich-vu-tien-ich"]')) {
            var dv = nr.querySelector('[data-sec="dich-vu"]');
            if (dv) {
              var a = P.document.createElement("a");
              a.setAttribute("data-sec", "dich-vu-tien-ich");
              if (P.cur === "amenity") a.className = "on";
              a.textContent = "Dịch vụ tiện ích khách sạn";
              dv.insertAdjacentElement("afterend", a);
            }
          }
        }
        var mm = P.document.querySelector("#hd #mobile-menu");
        if (mm) {
          var uuDaiM = mm.querySelector('[data-sec="uu-dai"]');
          if (uuDaiM) uuDaiM.remove();
          var oldAmM = mm.querySelector('[data-k="amenity"]');
          if (oldAmM) {
            oldAmM.removeAttribute("data-k");
            oldAmM.setAttribute("data-sec", "dich-vu-tien-ich");
          }
          if (!mm.querySelector('[data-sec="dich-vu-tien-ich"]')) {
            var dvM = mm.querySelector('[data-sec="dich-vu"]');
            if (dvM) {
              var aM = P.document.createElement("a");
              aM.setAttribute("data-sec", "dich-vu-tien-ich");
              aM.textContent = "Dịch vụ tiện ích khách sạn";
              dvM.insertAdjacentElement("afterend", aM);
            }
          }
        }
      };
      ensureAmenityNav();
      if (typeof P.render === "function" && !P.render._amenityPatched) {
        var origRender = P.render;
        P.render = function () {
          var res = origRender.apply(this, arguments);
          ensureAmenityNav();
          return res;
        };
        P.render._amenityPatched = true;
      }
    }
  } catch (e) {}
  var K = document.body.getAttribute("data-page"),
    U = null;
  function go(p) {
    parent.postMessage({ skyGo: p }, TARGET_ORIGIN);
  }
  var C = { "Trang chủ": "home", "Khách sạn": "rooms", "Tài khoản": "hist" };
  var A = {
    "Thống kê": "mgr",
    "Báo cáo": "mgr",
    "Lễ tân": "rec",
    "Quản lý phòng": "rooms_mgr",
    "Loại phòng": "room_types",
    "Khách hàng": "customers",
    "Hóa đơn": "invoices",
    "Dịch vụ": "services_mgr",
    "Khuyến mãi": "promos",
    "Tài khoản": "staff",
    "Nhân viên": "staff",
    "Thông tin khách sạn": "hotel",
    "Trang chủ": "rec",
  };
  var x = function (n) {
    return ((n && n.textContent) || "").replace(/\s+/g, " ").trim();
  };
  window.addEventListener("message", function (e) {
    if (!isSafeOrigin(e)) return;
    var d = e.data || {};
    if (!("skyInit" in d)) return;
    U = d.skyInit.user;
    var nb = document.querySelector("nav .btn");
    if (nb && K == "home") nb.textContent = U ? "👤 " + U.name : "Đăng nhập";
    if (K == "hist" && U) {
      var m = document.querySelector(".me");
      if (m) {
        m.querySelector("b").textContent = U.name;
        m.querySelector("small").textContent = U.email;
        m.querySelector(".av").textContent = U.name.charAt(0).toUpperCase();
      }
    }
  });
  document.addEventListener("click", function (e) {
    var el = e.target,
      dg = el.closest("[data-go]"),
      n = el.closest("nav a"),
      s = el.closest(".side a"),
      h = el.closest("aside a");
    if (dg) {
      e.preventDefault();
      if (K !== "home" && K !== "service" && K !== "about") {
        var dest = dg.getAttribute("data-go");
        if (dest) go(dest);
      }
      return;
    }
    if (n) {
      e.preventDefault();
      var d = C[x(n)];
      if (d) go(d);
      return;
    }
    if (s) {
      e.preventDefault();
      var t2 = x(s);
      for (var k in A) {
        if (t2.indexOf(k) > -1) {
          if (A[k] != K) go(A[k]);
          break;
        }
      }
      return;
    }
    if (K == "hist" && h) {
      if (h.getAttribute("data-tab")) return;
      e.preventDefault();
      if (x(h).indexOf("Đăng xuất") > -1 || h.getAttribute("data-act") === "logout")
        parent.postMessage({ skyLogout: 1 }, TARGET_ORIGIN);
      return;
    }
  });
  parent.postMessage({ skyReady: 1 }, TARGET_ORIGIN);
})();
