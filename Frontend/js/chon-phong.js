/* ==========================================================================
   Trang Chọn phòng
   - Giá, số phòng trống, sức chứa: LẤY TỪ SkyAPI.availability() (không cố định trong giao diện)
   - Chia thành các hàm dựng thành phần: RoomCard, Summary, StateBox, SearchBar
   ========================================================================== */
(function () {
  "use strict";
  var API = window.SkyAPI, U = API.util;
  var SEL_KEY = "sky_sel";
  var $ = function (id) { return document.getElementById(id); };
  function isAdminViewer() {
    return !!(window.HotelAPI && window.HotelAPI.isAdminSession && window.HotelAPI.isAdminSession());
  }

  var state = {
    q: { checkIn: "", checkOut: "", guests: 2, rooms: 1, adults: 2, children: 0 },
    occupancy: { adults: 2, children: 0, rooms: 1 },
    occupancyPicker: null,
    data: null,        // kết quả availability gần nhất
    sel: {},           // { typeId: qty }
    filter: {
      types: [],       // mảng typeId đã chọn, mặc định rỗng = chưa lọc gì
      amenities: [],   // mảng key tiện nghi đã chọn, mặc định rỗng = chưa lọc gì
      onlyFav: false,
      priceMin: null,
      priceMax: null
    },
    seq: 0,            // chống kết quả cũ ghi đè kết quả mới
    busy: false
  };

  var AMENITY_MAP = {
    "bua-sang": function (a) { return /bữa sáng|bàn ăn/i.test(a); },
    "ho-boi": function (a) { return /hồ bơi|ps5|bida/i.test(a); },
    "gym": function (a) { return /gym|ps5|bida/i.test(a); },
    "bon-tam": function (a) { return /bồn tắm/i.test(a); },
    "wifi": function (a) { return /wi-?fi/i.test(a); },
    "minibar": function (a) { return /minibar|tủ lạnh/i.test(a); },
    "xe-dua-don": function (a) { return /đưa đón|ban công|bida/i.test(a); }
  };

  function esc(s) {
    return String(s).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
  }
  function money(n) { return U.fmtMoney(n); }
  function syncQueryOccupancy() {
    var occ = window.SkyOccupancyPicker.toState(state.occupancy);
    state.occupancy = occ;
    state.q.adults = occ.adults;
    state.q.children = occ.children;
    state.q.guests = occ.adults + occ.children;
    state.q.rooms = occ.rooms;
  }

  /* ---------------- Lưu / khôi phục lựa chọn (để sang trang Đặt phòng vẫn còn) ---------------- */
  function save() {
    syncQueryOccupancy();
    var items = Object.keys(state.sel).filter(function (k) { return state.sel[k] > 0; })
      .map(function (k) { return { typeId: k, qty: state.sel[k] }; });
    try {
      localStorage.setItem(SEL_KEY, JSON.stringify({
        checkIn: state.q.checkIn, checkOut: state.q.checkOut, guests: state.q.guests, rooms: state.q.rooms,
        adults: state.q.adults, children: state.q.children,
        occupancy: state.occupancy, items: items, savedAt: Date.now()
      }));
    } catch (e) { }
  }
  function restore() {
    try {
      var s = JSON.parse(localStorage.getItem(SEL_KEY) || "null");
      if (!s || !U.parse(s.checkIn) || s.checkIn < U.today()) return false;
      var query = { checkIn: s.checkIn, checkOut: s.checkOut, guests: +s.guests || 2, rooms: +s.rooms || 1 };
      U.validateQuery(query);
      var occSource = s.occupancy || {
        adults: s.adults || query.guests || 2,
        children: s.children || 0,
        rooms: query.rooms || 1
      };
      state.occupancy = window.SkyOccupancyPicker.toState(occSource);
      state.q = query;
      (s.items || []).forEach(function (i) { if (i.qty > 0) state.sel[i.typeId] = i.qty; });
      return true;
    } catch (e) { return false; }
  }

  /* ---------------- Thanh tìm kiếm ---------------- */
  function initSearch() {
    if (!restore()) {
      state.occupancy = { adults: 2, children: 0, rooms: 1 };
      state.q.checkIn = U.today();
      state.q.checkOut = U.addDays(U.today(), 1);
    }
    syncQueryOccupancy();
    state.occupancyPicker = window.SkyOccupancyPicker.init(
      $("occupancy-trigger").parentElement,
      state.occupancy,
      function (occ) {
        state.occupancy = occ;
        syncQueryOccupancy();
        state.sel = {};
        writeSearch();
        save();
        scheduleRefresh();
      },
    );
    writeSearch();
  }
  function writeSearch() {
    $("ci").min = U.today();
    $("ci").value = state.q.checkIn;
    $("co").min = U.addDays(state.q.checkIn || U.today(), 1);
    $("co").value = state.q.checkOut;
    if (state.occupancyPicker) state.occupancyPicker.setValue(state.occupancy);
  }
  function readSearch() {
    state.q.checkIn = $("ci").value;
    state.q.checkOut = $("co").value;
  }
  function showSearchError(msg, fields) {
    $("smsg").textContent = msg || "";
    ["ci", "co"].forEach(function (id) { $(id).classList.toggle("bad", !!(msg && fields && fields.indexOf(id) > -1)); });
  }

  /* ---------------- Tải phòng trống ---------------- */
  var timer;
  function scheduleRefresh() { clearTimeout(timer); timer = setTimeout(refresh, 250); }

  function refresh() {
    readSearch();
    var n;
    try { n = U.validateQuery(state.q); }
    catch (e) {
      var fields = e.code === "BAD_RANGE" ? ["co"]
        : e.code === "PAST_DATE" ? ["ci"]
          : ["ci", "co"];
      showSearchError(e.message, fields);
      state.data = null;
      renderNights(0);
      renderDemand();
      renderList();
      renderSummary();
      return Promise.resolve();
    }
    showSearchError("");
    $("co").min = U.addDays(state.q.checkIn, 1);
    var my = ++state.seq;
    state.busy = true;
    renderNights(n);
    renderLoading();

    // Ưu tiên gọi Backend thật qua HotelAPI.searchRooms, fallback sang API.availability nếu lỗi
    var fetchPromise = (window.HotelAPI && window.HotelAPI.searchRooms)
      ? window.HotelAPI.searchRooms({
        checkIn: state.q.checkIn,
        checkOut: state.q.checkOut,
        adults: state.q.adults || state.q.guests || 2,
        children: state.q.children || 0,
        rooms: state.q.rooms || 1
      }).then(function (res) {
        var payload = res.data;
        if (payload && payload.data && !payload.danhSach) payload = payload.data;
        return API.availability(state.q, payload);
      })
      : API.availability(state.q);

    return fetchPromise.then(function (d) {
      if (my !== state.seq) return;
      state.busy = false; state.data = d;
      var changes = reconcile();
      showNotice(changes);
      renderDemand();
      renderList(); renderSummary(); save();
    }).catch(function (e) {
      if (my !== state.seq) return;
      state.busy = false; state.data = null;
      renderDemand();
      renderError(e);
      renderSummary();
    });
  }

  /* Đưa lựa chọn về đúng số phòng còn trống mới nhất. Trả về danh sách điều chỉnh để báo khách. */
  function reconcile() {
    var out = [];
    Object.keys(state.sel).forEach(function (id) {
      var t = typeOf(id), q = state.sel[id];
      if (!t) { delete state.sel[id]; return; }
      if (q > t.available) {
        if (t.available <= 0) { delete state.sel[id]; out.push(t.name + " đã hết phòng nên được bỏ khỏi lựa chọn."); }
        else { state.sel[id] = t.available; out.push(t.name + " chỉ còn " + t.available + " phòng, số lượng đã được điều chỉnh."); }
      }
    });
    return out;
  }
  function showNotice(list) {
    var n = $("notice");
    n.hidden = !list.length;
    n.innerHTML = list.map(function (s) { return "<div>" + esc(s) + "</div>"; }).join("");
  }
  function typeOf(id) {
    if (!state.data) return null;
    for (var i = 0; i < state.data.types.length; i++) if (state.data.types[i].id === id) return state.data.types[i];
    return null;
  }

  /* ---------------- Thành phần giao diện ---------------- */
  function renderNights(n) {
    $("nights").innerHTML = n
      ? "<b>" + n + " đêm</b> · " + U.fmtDate(state.q.checkIn) + " – " + U.fmtDate(state.q.checkOut)
      : "";
  }

  function statusOf(t) {
    if (t.available <= 0) return { cls: "out", label: "Hết phòng" };
    if (t.available <= 3) return { cls: "low", label: "Chỉ còn " + t.available + " phòng" };
    return { cls: "", label: "Còn phòng" };
  }

  function selectedRoomCount() {
    return Object.keys(state.sel).reduce(function (total, id) {
      return total + state.sel[id];
    }, 0);
  }

  /* ---------------- Sức chứa tổng hợp theo số khách & số phòng ---------------- */
  function getSelectedRoomUnits() {
    var units = [];
    if (!state.data) return units;
    state.data.types.forEach(function (t) {
      var qty = state.sel[t.id] || 0;
      for (var i = 0; i < qty; i++) {
        units.push({
          typeId: t.id,
          name: t.name,
          capacity: t.capacity,
          price: t.price
        });
      }
    });
    return units;
  }

  /**
   * Số khách quy đổi sức chứa đồng bộ Backend (TimKiemPhongService::quyDoiSoKhach):
   * Người lớn + floor(Trẻ em / 2), tối thiểu 1.
   */
  function getEffectiveGuests(occ) {
    if (state.data && Number(state.data.tongKhachQuyDoi) > 0) {
      return Number(state.data.tongKhachQuyDoi);
    }
    var o = occ || window.SkyOccupancyPicker.toState(state.occupancy);
    return Math.max(1, o.adults + Math.floor(Math.max(0, o.children) / 2));
  }

  function evaluateSelection(units) {
    var occ = window.SkyOccupancyPicker.toState(state.occupancy);
    var requiredRooms = occ.rooms;
    var rawGuests = occ.adults + occ.children;
    var requiredGuests = getEffectiveGuests(occ);
    var selectedCap = units.reduce(function (sum, u) { return sum + (u.capacity || 0); }, 0);
    var selectedRooms = units.length;
    return {
      occ: occ,
      rawGuests: rawGuests,
      requiredRooms: requiredRooms,
      requiredGuests: requiredGuests,
      selectedRooms: selectedRooms,
      selectedCapacity: selectedCap,
      remainingRooms: Math.max(0, requiredRooms - selectedRooms),
      remainingGuestsNeeded: Math.max(0, requiredGuests - selectedCap),
      isComplete: selectedRooms === requiredRooms && selectedCap >= requiredGuests
    };
  }

  function canAddRoom(typeId) {
    var t = typeOf(typeId);
    if (!t) return { allowed: false, reason: "Hạng phòng không tồn tại." };

    var currentQty = state.sel[t.id] || 0;
    if (t.available <= 0) return { allowed: false, reason: "Hạng phòng này đã hết phòng trống." };
    if (currentQty >= t.available) return { allowed: false, reason: "Đã chọn tối đa " + t.available + " phòng trống của hạng này." };

    var currentUnits = getSelectedRoomUnits();
    var currentEval = evaluateSelection(currentUnits);

    if (currentUnits.length >= currentEval.requiredRooms) {
      return { allowed: false, reason: "Đã chọn đủ " + currentEval.requiredRooms + " phòng theo yêu cầu." };
    }

    var tentativeUnits = currentUnits.concat([{
      typeId: t.id,
      name: t.name,
      capacity: t.capacity,
      price: t.price
    }]);
    var nextCap = currentEval.selectedCapacity + t.capacity;
    var remainingRoomsAfter = currentEval.requiredRooms - tentativeUnits.length;
    var remainingGuestsAfter = Math.max(0, currentEval.requiredGuests - nextCap);

    if (remainingRoomsAfter === 0) {
      if (nextCap < currentEval.requiredGuests) {
        return {
          allowed: false,
          reason: "Tổng sức chứa chưa đủ cho " + currentEval.requiredGuests + " khách (cần thêm tối thiểu " + (currentEval.requiredGuests - currentEval.selectedCapacity) + " chỗ)."
        };
      }
      return { allowed: true };
    }

    if (state.data) {
      var availablePool = [];
      state.data.types.forEach(function (roomType) {
        var usedInTentative = tentativeUnits.filter(function (u) { return u.typeId === roomType.id; }).length;
        var remainingInType = Math.max(0, roomType.available - usedInTentative);
        for (var k = 0; k < remainingInType; k++) {
          availablePool.push(roomType.capacity);
        }
      });
      availablePool.sort(function (a, b) { return b - a; });
      if (availablePool.length < remainingRoomsAfter) {
        return { allowed: false, reason: "Khách sạn không còn đủ phòng trống để đáp ứng số phòng còn lại." };
      }
      var maxRemainingCap = availablePool.slice(0, remainingRoomsAfter).reduce(function (sum, c) { return sum + c; }, 0);
      if (maxRemainingCap < remainingGuestsAfter) {
        return { allowed: false, reason: "Các phòng còn trống không đủ sức chứa cho " + remainingGuestsAfter + " khách còn lại nếu chọn hạng này." };
      }
    }

    return { allowed: true };
  }

  function renderDemand() {
    var dBox = $("demand");
    if (!dBox) return;
    if (!state.data) {
      dBox.hidden = true;
      dBox.innerHTML = "";
      return;
    }
    var units = getSelectedRoomUnits();
    var ev = evaluateSelection(units);
    if (!ev.requiredRooms) {
      dBox.hidden = true;
      dBox.innerHTML = "";
      return;
    }
    dBox.hidden = false;

    var tags = [];
    state.data.types.forEach(function (t) {
      var q = state.sel[t.id] || 0;
      if (q > 0) {
        tags.push(
          '<div class="cp-demand-tag is-done">' +
          '<span>✓ <b>' + q + ' × ' + esc(t.name) + '</b></span>' +
          '<span><small>(' + (q * t.capacity) + ' chỗ)</small></span>' +
          '</div>'
        );
      }
    });
    if (ev.remainingRooms > 0) {
      tags.push(
        '<div class="cp-demand-tag is-waiting">' +
        '<span>○ <b>Còn thiếu ' + ev.remainingRooms + ' phòng</b></span>' +
        '<span>' + (ev.remainingGuestsNeeded > 0 ? 'Cần thêm ≥ ' + ev.remainingGuestsNeeded + ' chỗ' : 'Đủ sức chứa, chỉ cần chọn thêm phòng') + '</span>' +
        '</div>'
      );
    } else if (ev.remainingGuestsNeeded > 0) {
      tags.push(
        '<div class="cp-demand-tag is-alert">' +
        '<span>⚠️ <b>Thiếu ' + ev.remainingGuestsNeeded + ' chỗ</b></span>' +
        '<span>Hãy đổi sang hạng phòng lớn hơn</span>' +
        '</div>'
      );
    }

    var progressText = ev.isComplete
      ? '✓ Đã chọn đủ ' + ev.selectedRooms + '/' + ev.requiredRooms + ' phòng (' + ev.selectedCapacity + ' chỗ)'
      : 'Đã chọn ' + ev.selectedRooms + '/' + ev.requiredRooms + ' phòng · Sức chứa ' + ev.selectedCapacity + '/' + ev.requiredGuests + ' chỗ';

    var comboHtml = "";
    if (state.data && state.data.goiYCombo) {
      var hasComboPlan = state.data.comboToiUu && Array.isArray(state.data.comboToiUu.chiTiet) && state.data.comboToiUu.chiTiet.length > 0;
      comboHtml = '<div style="margin-top:10px;padding:10px 14px;background:rgba(198,161,91,0.12);border:1px solid rgba(198,161,91,0.4);border-radius:8px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;font-size:13px;">' +
        '<div>💡 <b>Gợi ý từ hệ thống:</b> ' + esc(state.data.goiYCombo) + '</div>' +
        (hasComboPlan ? '<button type="button" data-act="apply-combo" style="background:var(--primary,#0f1f30);color:#fff;border:0;border-radius:6px;padding:6px 12px;font-size:12px;font-weight:600;cursor:pointer;white-space:nowrap;">⚡ Áp dụng gợi ý này</button>' : '') +
        '</div>';
    }

    var reqNote = ev.requiredGuests !== ev.rawGuests
      ? ' (quy đổi sức chứa: ' + ev.requiredGuests + ' khách)'
      : ' (' + ev.rawGuests + ' khách)';
    dBox.innerHTML =
      '<div class="cp-demand-head">' +
      '<div class="cp-demand-title">📋 Yêu cầu đặt phòng: ' + ev.requiredRooms + ' phòng · ' + ev.occ.adults + ' người lớn, ' + ev.occ.children + ' trẻ em' + reqNote + '</div>' +
      '<div class="cp-demand-progress">' + progressText + '</div>' +
      '</div>' +
      '<div class="cp-demand-tags">' + tags.join("") + '</div>' +
      comboHtml;
  }

  function RoomCard(t) {
    var st = statusOf(t), qty = state.sel[t.id] || 0, out = t.available <= 0;
    var canAdd = canAddRoom(t.id);

    var am = t.amenities.slice(0, 5).map(function (a) { return "<span>" + esc(a) + "</span>"; }).join("") +
      (t.amenities.length > 5 ? "<span>+" + (t.amenities.length - 5) + "</span>" : "");

    var act;
    if (isAdminViewer()) {
      act = "";
    } else if (out) {
      act = '<button class="cp-pick" disabled>Hết phòng</button>';
    } else if (!qty) {
      if (canAdd.allowed) {
        act = '<button class="cp-pick" data-act="pick" data-id="' + esc(t.id) + '">Chọn phòng</button>';
      } else {
        act = '<button class="cp-pick" disabled title="' + esc(canAdd.reason) + '">Chọn phòng</button>';
      }
    } else {
      var incDisabled = !canAdd.allowed;
      act =
        '<div class="cp-step" role="group" aria-label="Số lượng phòng ' + esc(t.name) + '">' +
        '<button data-act="dec" data-id="' + esc(t.id) + '" aria-label="Giảm số phòng">−</button>' +
        '<output aria-live="polite">' + qty + "</output>" +
        '<button data-act="inc" data-id="' + esc(t.id) + '" aria-label="Tăng số phòng"' +
        (incDisabled ? ' disabled title="' + esc(canAdd.reason) + '"' : '') + ">+</button></div>" +
        '<button class="cp-remove" data-act="rm" data-id="' + esc(t.id) + '">Bỏ chọn</button>';
    }

    var matchBadge = "";
    if (out) {
      matchBadge = "";
    } else if (qty > 0) {
      matchBadge = '<div class="cp-match-tag is-selected">✓ Đã chọn ' + qty + ' phòng (' + (qty * t.capacity) + ' chỗ)</div>';
    } else if (canAdd.allowed) {
      matchBadge = '<div class="cp-match-tag">✓ Phù hợp (tối đa ' + t.capacity + ' khách/phòng)</div>';
    } else {
      matchBadge = '<div class="cp-match-tag is-disabled">⚠️ ' + esc(canAdd.reason) + '</div>';
    }

    var left;
    if (out) {
      left = "Không còn phòng trống trong khoảng ngày này. Hãy thử đổi ngày.";
    } else if (qty > 0) {
      left = t.available + " phòng còn trống · Đã chọn " + qty + " phòng";
    } else if (!canAdd.allowed) {
      left = t.available + " phòng còn trống · " + canAdd.reason;
    } else {
      left = t.available + " phòng còn trống";
    }

    var isFav = window.SkyWishlist ? window.SkyWishlist.has(t.id) : false;
    var favBtn = isAdminViewer()
      ? ""
      : '<button class="cp-fav-toggle' + (isFav ? ' on' : '') + '" data-act="toggle-fav" data-id="' + esc(t.id) + '" type="button" aria-label="' + (isFav ? 'Bỏ lưu yêu thích' : 'Lưu vào yêu thích') + '" title="' + (isFav ? 'Đã lưu yêu thích' : 'Lưu vào yêu thích') + '">' + (isFav ? '♥' : '♡') + '</button>';

    return '<article class="cp-room' + (qty ? " is-sel" : "") + (out ? " is-out" : "") + '">' +
      '<a class="cp-img" href="#" data-act="detail" data-id="' + esc(t.id) + '" aria-label="Xem chi tiết hạng phòng ' + esc(t.name) + '">' +
      favBtn +
      '<span class="cp-status ' + st.cls + '">' + st.label + "</span>" +
      '<img src="' + esc(t.image) + '" alt="Phòng ' + esc(t.name) + ' tại SKY HOTEL" loading="lazy"></a>' +
      '<div class="cp-body"><h3><a href="#" data-act="detail" data-id="' + esc(t.id) + '">' + esc(t.name) + "</a></h3>" +
      '<div class="cp-view">' + esc(t.view || "") + "</div>" +
      (matchBadge ? '<div>' + matchBadge + '</div>' : '') +
      '<div class="cp-facts"><span>▭ <b>' + t.area + ' m²</b></span><span>☺ Tối đa <b>' + t.capacity + ' khách/phòng</b></span><span>🛏 ' + esc(t.bed) + "</span></div>" +
      '<p class="cp-desc">' + esc(t.desc || "") + "</p>" +
      '<div class="cp-am">' + am + "</div>" +
      '<div class="cp-foot"><div class="cp-price"><small>Giá mỗi đêm</small><strong>' + money(t.price) + "</strong><em>/ đêm</em>" +
      '<div class="cp-left">' + left + "</div></div>" +
      '<div class="cp-act"><button type="button" class="cp-detail-btn" data-act="detail" data-id="' + esc(t.id) + '">Chi tiết</button>' + act + "</div></div></div></article>";
  }

  function renderLoading() {
    var s = "";
    for (var i = 0; i < 3; i++) s += '<div class="cp-skel" aria-hidden="true"><div></div><div><i></i><i></i><i></i><i></i></div></div>';
    $("list").innerHTML = s;
    $("list").setAttribute("aria-busy", "true");
  }
  function StateBox(title, text, btn) {
    return '<div class="cp-state"><h3>' + esc(title) + "</h3><p>" + esc(text) + "</p>" +
      (btn ? '<button data-act="' + btn[0] + '">' + esc(btn[1]) + "</button>" : "") + "</div>";
  }
  function renderError(e) {
    $("list").removeAttribute("aria-busy");
    $("list").innerHTML = StateBox("Không tải được dữ liệu phòng",
      (e && e.message ? e.message + " " : "") + "Vui lòng kiểm tra kết nối và thử lại.", ["retry", "Thử lại"]);
  }
  function parsePriceInput(el) {
    if (!el) return null;
    var raw = String(el.value || "").trim();
    if (!raw) return null;
    var num = Number(raw);
    if (!isFinite(num) || num < 0) return null;
    return num;
  }

  function syncPricePresetButtons(pMin, pMax) {
    var btns = document.querySelectorAll("#filter-price-presets .cp-price-preset");
    btns.forEach(function (btn) {
      var bMinRaw = btn.getAttribute("data-min");
      var bMaxRaw = btn.getAttribute("data-max");
      var bMin = bMinRaw === "" ? null : Number(bMinRaw);
      var bMax = bMaxRaw === "" ? null : Number(bMaxRaw);
      var hasAny = pMin !== null || pMax !== null;
      var matched = hasAny &&
        ((pMin === null ? 0 : pMin) === (bMin === null ? 0 : bMin)) &&
        ((pMax === null ? null : pMax) === (bMax === null ? null : bMax));
      btn.classList.toggle("is-on", matched);
      btn.setAttribute("aria-pressed", matched ? "true" : "false");
    });
  }

  function readFilters() {
    state.filter.types = [].slice.call(document.querySelectorAll('input[name="f-type"]:checked')).map(function (el) { return el.value; });
    state.filter.amenities = [].slice.call(document.querySelectorAll('input[name="f-amenity"]:checked')).map(function (el) { return el.value; });
    state.filter.onlyFav = $("filter-fav") ? $("filter-fav").checked : false;
    state.filter.priceMin = parsePriceInput($("filter-price-min"));
    state.filter.priceMax = parsePriceInput($("filter-price-max"));
    syncPricePresetButtons(state.filter.priceMin, state.filter.priceMax);
    document.querySelectorAll("#quick-amenity-chips .cp-quickchip").forEach(function (chip) {
      var val = chip.getAttribute("data-amenity");
      var on = state.filter.amenities.indexOf(val) >= 0;
      chip.classList.toggle("is-on", on);
      chip.setAttribute("aria-pressed", on ? "true" : "false");
    });
    var hasPrice = state.filter.priceMin !== null || state.filter.priceMax !== null;
    var hasFilter = state.filter.types.length > 0 || state.filter.amenities.length > 0 || state.filter.onlyFav || hasPrice;
    var clearBtn = $("filter-clear");
    if (clearBtn) clearBtn.hidden = !hasFilter;
  }

  function resetFilters() {
    var hasPrice = state.filter.priceMin !== null || state.filter.priceMax !== null;
    var hadSidebar = state.filter.types.length > 0 || state.filter.amenities.length > 0 || state.filter.onlyFav || hasPrice;
    document.querySelectorAll('input[name="f-type"], input[name="f-amenity"]').forEach(function (el) {
      el.checked = false;
    });
    var favEl = $("filter-fav");
    if (favEl) favEl.checked = false;
    var pMinEl = $("filter-price-min");
    var pMaxEl = $("filter-price-max");
    if (pMinEl) pMinEl.value = "";
    if (pMaxEl) pMaxEl.value = "";
    readFilters();
    if (!hadSidebar) {
      state.occupancy = { adults: 2, children: 0, rooms: 1 };
      syncQueryOccupancy();
      state.sel = {};
      writeSearch();
      save();
      refresh();
      return;
    }
    renderList();
  }

  function renderList() {
    var L = $("list");
    L.removeAttribute("aria-busy");
    if (!state.data) {
      L.innerHTML = StateBox("Chọn ngày lưu trú", "Nhập ngày nhận phòng, ngày trả phòng và số khách để xem phòng còn trống.");
      return;
    }
    var types = state.data.types;
    if (!types.length) {
      L.innerHTML = StateBox("Không có kết quả", "Hiện chưa có hạng phòng nào phù hợp. Vui lòng thử ngày khác.", ["retry", "Tìm lại"]);
      return;
    }
    var ev = evaluateSelection(getSelectedRoomUnits());

    // Lọc theo bộ lọc loại phòng, tiện nghi, phòng yêu thích và điều kiện phòng thực tế
    var filteredTypes = types.filter(function (t) {
      var isSelected = (state.sel[t.id] || 0) > 0;

      // 1. Bộ lọc loại phòng, tiện nghi, phòng yêu thích và khoảng giá bên trái
      var matchType = !state.filter.types.length || state.filter.types.indexOf(t.id) > -1;
      var matchAmenity = !state.filter.amenities.length || state.filter.amenities.every(function (key) {
        var fn = AMENITY_MAP[key];
        return fn ? t.amenities.some(fn) : true;
      });
      var matchFav = !state.filter.onlyFav || (window.SkyWishlist && window.SkyWishlist.has(t.id));
      var pMin = state.filter.priceMin;
      var pMax = state.filter.priceMax;
      if (pMin !== null && pMax !== null && pMin > pMax) {
        var tmp = pMin; pMin = pMax; pMax = tmp;
      }
      var priceVal = Number(t.price) || 0;
      var matchPrice = (pMin === null || priceVal >= pMin) && (pMax === null || priceVal <= pMax);
      if (!matchType || !matchAmenity || !matchFav || !matchPrice) return false;

      // Nếu phòng đã được người dùng chọn thì giữ lại để tiếp tục quản lý số lượng
      if (isSelected) return true;

      // 2. Ẩn phòng đã hết phòng trống (không đáp ứng điều kiện còn phòng)
      if (t.available <= 0) return false;

      // 3. Nếu chỉ còn 1 phòng cần chọn, ẩn các hạng không đủ sức chứa cho số khách còn lại
      if (ev.remainingRooms === 1 && t.capacity < ev.remainingGuestsNeeded) return false;

      return true;
    });

    if (!filteredTypes.length) {
      L.innerHTML = StateBox(
        "Không có phòng phù hợp với điều kiện tìm kiếm",
        "Không tìm thấy phòng nào còn trống hoặc đáp ứng số lượng khách và bộ lọc bạn đã chọn. Vui lòng thử đổi ngày lưu trú hoặc điều chỉnh số lượng khách.",
        ["reset-filter", "Đặt lại bộ lọc"]
      );
      return;
    }
    L.innerHTML = filteredTypes.map(function (type) {
      return RoomCard(type);
    }).join("");
  }

  /* ---------------- Tóm tắt & tính tiền ---------------- */
  function selectedLines() {
    var out = [];
    if (!state.data) return out;
    state.data.types.forEach(function (t) {
      if (state.sel[t.id] > 0) out.push({ typeId: t.id, name: t.name, price: t.price, qty: state.sel[t.id], capacity: t.capacity, area: t.area });
    });
    return out;
  }
  function summaryInfo() {
    var lines = selectedLines(), nights = state.data ? state.data.nights : 0;
    var rooms = 0, cap = 0;
    lines.forEach(function (l) { rooms += l.qty; cap += l.qty * l.capacity; });
    var t = lines.length ? U.calcTotals(lines, nights, state.data.pricing) : null;

    var occ = window.SkyOccupancyPicker.toState(state.occupancy);
    var effectiveGuests = getEffectiveGuests(occ);
    var rawGuests = occ.adults + occ.children;
    var capOk = lines.length > 0 && rooms === occ.rooms && cap >= effectiveGuests;

    return {
      lines: lines, nights: nights, rooms: rooms, cap: cap, totals: t,
      capacityOk: cap >= effectiveGuests, roomsOk: rooms === occ.rooms,
      capOk: capOk, occ: occ, effectiveGuests: effectiveGuests, rawGuests: rawGuests
    };
  }

  function renderSummary() {
    var s = summaryInfo(), box = $("sum"), bar = $("bar");
    if (isAdminViewer()) {
      box.innerHTML = '<h3>Chế độ xem Quản trị viên</h3><div class="empty">Tài khoản Quản trị viên chỉ xem thông tin hạng phòng &amp; tình trạng phòng trống, không thực hiện đặt phòng trực tuyến.</div>';
      bar.hidden = true;
      return;
    }
    var h = "<h3>Phòng đã chọn</h3>";
    if (state.data) {
      var capSummary = s.effectiveGuests !== s.rawGuests ? (" · sức chứa " + s.effectiveGuests + " chỗ") : "";
      h += '<div class="dates">' + U.fmtDate(state.q.checkIn) + " – " + U.fmtDate(state.q.checkOut) + " · " + s.nights + " đêm · " + s.occ.adults + " người lớn, " + s.occ.children + " trẻ em (" + s.occ.rooms + " phòng" + capSummary + ")</div>";
    }
    if (!s.lines.length) {
      h += '<div class="empty">Bạn chưa chọn phòng nào.<br>Nhấn “Chọn phòng” theo đúng số phòng và tổng sức chứa bạn cần đặt.</div>';
      h += '<button class="cp-go" disabled>Thanh toán & Đặt phòng</button>';
      box.innerHTML = h; bar.hidden = true; return;
    }
    s.lines.forEach(function (l) {
      h += '<div class="cp-line"><b>' + esc(l.name) + '</b><span class="amt">' + money(l.lineTotal) + "</span>" +
        "<small>" + l.qty + " phòng × " + s.nights + " đêm × " + money(l.price) + "</small>" +
        '<button class="rm" data-act="rm" data-id="' + esc(l.typeId) + '">Bỏ chọn</button></div>';
    });

    var t = s.totals;
    h += '<div class="cp-rows">' +
      '<div class="cp-row"><span>Tiền phòng (' + s.rooms + " phòng)</span><span>" + money(t.subtotal) + "</span></div>" +
      (t.service > 0 ? '<div class="cp-row"><span>Phí dịch vụ (' + Math.round(t.serviceRate * 100) + '%)</span><span>' + money(t.service) + "</span></div>" : "") +
      '<div class="cp-row"><span>Thuế VAT phòng (' + Math.round(t.vatRate * 100) + '%)</span><span>+' + money(t.vat) + "</span></div>" +
      '<div class="cp-row tot"><span>Tổng tạm tính</span><strong>' + money(t.total) + "</strong></div></div>";

    if (s.capOk) {
      h += '<div class="cp-cap">✓ Đã chọn đủ ' + s.rooms + '/' + s.occ.rooms + ' phòng với tổng sức chứa ' + s.cap + ' chỗ (đủ cho ' + s.effectiveGuests + ' khách quy đổi)!</div>';
    } else if (s.rooms < s.occ.rooms) {
      h += '<div class="cp-cap bad">Chưa chọn đủ phòng: Đã chọn ' + s.rooms + '/' + s.occ.rooms + ' phòng. Vui lòng chọn thêm ' + (s.occ.rooms - s.rooms) + ' phòng.</div>';
    } else {
      h += '<div class="cp-cap bad">Chưa đủ sức chứa: Tổng sức chứa ' + s.cap + ' chỗ đang nhỏ hơn ' + s.effectiveGuests + ' khách quy đổi.</div>';
    }
    h += '<button class="cp-go" id="go"' + (s.capOk ? "" : " disabled") + ">Thanh toán & Đặt phòng →</button>" +
      '<p class="cp-fine">Chưa trừ khuyến mãi · Khai báo lưu trú & chọn mã ưu đãi ở bước tiếp theo</p>';
    box.innerHTML = h;
    bar.hidden = false;
    $("barN").textContent = s.rooms + " phòng · " + s.nights + " đêm";
    $("barT").textContent = money(t.total);
  }

  /* ---------------- Hành động ---------------- */
  function change(id, delta) {
    if (isAdminViewer()) return;
    var t = typeOf(id); if (!t) return;
    if (delta > 0) {
      var check = canAddRoom(id);
      if (!check.allowed) {
        showNotice([check.reason]);
        return;
      }
    }
    var current = state.sel[id] || 0;
    var q = current + delta;
    if (q > t.available) { showNotice(["Hạng " + t.name + " chỉ còn " + t.available + " phòng."]); q = t.available; }
    else showNotice([]);
    if (q <= 0) delete state.sel[id]; else state.sel[id] = q;
    renderDemand(); renderList(); renderSummary(); save();
  }

  function goBook() {
    if (isAdminViewer()) {
      if (parent !== window) parent.postMessage({ skyToast: "Tài khoản Quản trị viên không sử dụng chức năng đặt phòng." }, "*");
      return;
    }
    var s = summaryInfo(); if (!s.lines.length || !s.capOk) return;
    var btn = $("go"); if (btn) { btn.disabled = true; btn.textContent = "Đang kiểm tra phòng trống…"; }
    state.q.rooms = s.rooms;
    save();
    // Kiểm tra lại với dữ liệu mới nhất trước khi chuyển bước (tránh đặt trùng)
    API.availability(state.q).then(function (d) {
      state.data = d;
      var changes = reconcile();
      if (changes.length) {
        changes.push("Vui lòng xem lại lựa chọn rồi bấm “Tiếp tục đặt phòng” lần nữa.");
        showNotice(changes); renderDemand(); renderList(); renderSummary(); save();
        window.scrollTo({ top: 0, behavior: "smooth" });
        return;
      }
      if (parent !== window) parent.postMessage({ skyGo: "book" }, "*");
      else location.href = "dat-phong.html";
    }).catch(function (e) {
      showNotice(["Không thể kiểm tra phòng trống: " + e.message]);
      renderSummary();
    });
  }

  document.addEventListener("click", function (e) {
    if (e.target.id === "filter-clear") {
      resetFilters();
      return;
    }
    var b = e.target.closest("[data-act]");
    if (b) {
      var a = b.getAttribute("data-act"), id = b.getAttribute("data-id");
      if (a === "detail") {
        e.preventDefault();
        if (parent !== window) parent.postMessage({ skyGo: "detail:" + id }, "*");
        else location.href = "chi-tiet-phong.html#" + encodeURIComponent(id);
        return;
      }
      if (a === "toggle-fav") {
        e.preventDefault();
        e.stopPropagation();
        if (isAdminViewer()) return;
        if (window.SkyWishlist) {
          window.SkyWishlist.toggle(id);
          updateFavCount();
          renderList();
        }
        return;
      }
      if (a === "apply-combo") {
        if (isAdminViewer()) return;
        if (state.data && state.data.comboToiUu && Array.isArray(state.data.comboToiUu.chiTiet)) {
          state.sel = {};
          state.data.comboToiUu.chiTiet.forEach(function (item) {
            if (item && item.maLoai && item.soLuong > 0) {
              state.sel[item.maLoai] = item.soLuong;
            }
          });
          showNotice([]);
          renderDemand(); renderList(); renderSummary(); save();
        }
        return;
      }
      if (a === "pick" || a === "inc") change(id, 1);
      else if (a === "dec") change(id, -1);
      else if (a === "rm") { delete state.sel[id]; showNotice([]); renderDemand(); renderList(); renderSummary(); save(); }
      else if (a === "retry") refresh();
      else if (a === "reset-filter") resetFilters();
      else if (a === "focus") $("ci").focus();
      return;
    }
    if (e.target.id === "go") goBook();
    if (e.target.id === "barGo") {
      var sBar = summaryInfo();
      if (sBar && sBar.capOk) goBook();
      else $("sum").scrollIntoView({ behavior: "smooth", block: "start" });
    }
  });

  document.addEventListener("change", function (e) {
    if (e.target.name === "f-type" || e.target.name === "f-amenity" || e.target.name === "f-fav") {
      readFilters();
      renderList();
    }
  });
  var pricePresetsEl = $("filter-price-presets");
  if (pricePresetsEl) {
    pricePresetsEl.addEventListener("click", function (e) {
      var btn = e.target.closest(".cp-price-preset");
      if (!btn) return;
      var pMinEl = $("filter-price-min");
      var pMaxEl = $("filter-price-max");
      var wasOn = btn.classList.contains("is-on");
      if (wasOn) {
        if (pMinEl) pMinEl.value = "";
        if (pMaxEl) pMaxEl.value = "";
      } else {
        if (pMinEl) pMinEl.value = btn.getAttribute("data-min") || "";
        if (pMaxEl) pMaxEl.value = btn.getAttribute("data-max") || "";
      }
      readFilters();
      renderList();
    });
  }
  ["filter-price-min", "filter-price-max"].forEach(function (id) {
    var el = $(id);
    if (!el) return;
    el.addEventListener("input", function () {
      readFilters();
      renderList();
    });
    el.addEventListener("change", function () {
      readFilters();
      renderList();
    });
  });
  var quickChipsEl = $("quick-amenity-chips");
  if (quickChipsEl) {
    quickChipsEl.addEventListener("click", function (e) {
      var chip = e.target.closest(".cp-quickchip");
      if (!chip) return;
      var val = chip.getAttribute("data-amenity");
      var cb = document.querySelector('input[name="f-amenity"][value="' + val + '"]');
      if (cb) {
        cb.checked = !cb.checked;
        readFilters();
        renderList();
      }
    });
  }

  function updateFavCount() {
    var c = window.SkyWishlist ? window.SkyWishlist.count() : 0;
    var el = $("side-fav-count");
    if (el) el.textContent = c;
  }
  window.addEventListener("sky_wishlist_change", function () {
    updateFavCount();
    renderList();
  });
  updateFavCount();

  $("ci").addEventListener("change", function () {
    var ci = $("ci").value;
    if (U.parse(ci)) {
      $("co").min = U.addDays(ci, 1);
      if (!$("co").value || $("co").value <= ci) $("co").value = $("co").min;
    }
    scheduleRefresh();
  });
  $("co").addEventListener("change", scheduleRefresh);
  $("find").addEventListener("click", function () { clearTimeout(timer); refresh(); });

  initSearch();
  if (isAdminViewer()) {
    var favCheckbox = $("filter-fav");
    if (favCheckbox) {
      var favWrap = favCheckbox.closest(".cp-fcard, .cp-fgroup, label") || favCheckbox.parentElement;
      if (favWrap) favWrap.style.display = "none";
    }
    if (window.HotelAPI && window.HotelAPI.hideCustomerOnlyLinks) window.HotelAPI.hideCustomerOnlyLinks();
  }
  refresh();
})();
