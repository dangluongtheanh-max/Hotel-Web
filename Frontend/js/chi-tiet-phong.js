/* ==========================================================================
   SKY HOTEL - Chi tiết phòng nghỉ cao cấp
   ========================================================================== */

(function () {
  "use strict";

  if ("scrollRestoration" in history) {
    try {
      history.scrollRestoration = "manual";
    } catch (e) {}
  }

  var PHOTO_COUNT = { superior: 4, deluxe: 4, premier: 5, executive: 5, presidential: 5 };
  var SLIDE_MS = 4500;

  var TIER_LABELS = {
    LP001: "Phòng tiêu chuẩn",
    LP002: "Phòng cao cấp",
    LP003: "Phòng hạng sang",
    LP004: "Phòng Tiết Kiệm",
    LP005: "Phòng Gia Đình",
    LP006: "Phòng Giải Trí PS5",
    LP007: "Phòng Tổng Thống",
    superior: "Thanh Lịch & Tinh Tế",
    deluxe: "Sang Trọng & Bồn Tắm Riêng",
    premier: "Đặc Quyền Executive Lounge",
    executive: "Suite Thượng Lưu & Quản Gia",
    presidential: "Penthouse Độc Bản Xa Hoa"
  };

  var galleries = {};
  Object.keys(PHOTO_COUNT).forEach(function (id) {
    galleries[id] = [];
    for (var n = 1; n <= PHOTO_COUNT[id]; n++) {
      galleries[id].push("../images/rooms/" + id + "-" + n + ".jpg");
    }
  });
  galleries["LP001"] = galleries["superior"];
  galleries["LP002"] = galleries["deluxe"];
  galleries["LP003"] = galleries["premier"];
  galleries["LP004"] = ["../images/rooms/superior-2.jpg", "../images/rooms/superior-1.jpg", "../images/rooms/superior-3.jpg"];
  galleries["LP005"] = ["../images/rooms/deluxe-2.jpg", "../images/rooms/deluxe-1.jpg", "../images/rooms/premier-2.jpg"];
  galleries["LP006"] = galleries["executive"];
  galleries["LP007"] = galleries["presidential"];

  var detailError = document.getElementById("detail-error");
  var roomDetails = document.getElementById("room-details");

  function getRoomId() {
    var id = "";
    try {
      id = decodeURIComponent(location.hash.slice(1)).trim();
    } catch (e) {
      id = "";
    }
    return id || "LP001";
  }

  function fmt(n) {
    return Number(n).toLocaleString("vi-VN") + "đ";
  }

  function esc(s) {
    return String(s || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function scrollToTop() {
    var docEl = document.documentElement;
    var prevBehavior = docEl ? docEl.style.scrollBehavior : "";
    if (docEl) docEl.style.scrollBehavior = "auto";
    try {
      window.scrollTo({ top: 0, left: 0, behavior: "instant" });
    } catch (e) {
      window.scrollTo(0, 0);
    }
    if (docEl) docEl.scrollTop = 0;
    if (document.body) document.body.scrollTop = 0;
    setTimeout(function () {
      if (docEl) docEl.style.scrollBehavior = prevBehavior;
    }, 50);

    if (parent && parent !== window) {
      try {
        parent.window.scrollTo(0, 0);
        if (parent.document && parent.document.documentElement) {
          parent.document.documentElement.scrollTop = 0;
          parent.document.body.scrollTop = 0;
        }
      } catch (e) {}
    }
  }

  var currentRoom = null;

  // DOM Elements cache
  var crumbEl = document.getElementById("crumb-room-name");
  var tierBadge = document.getElementById("room-tier-badge");
  var roomNameEl = document.getElementById("room-name");
  var ratingBadge = document.getElementById("room-rating-badge");
  var taglineEl = document.getElementById("room-tagline");
  var viewEl = document.getElementById("room-view");
  var metaEl = document.getElementById("room-meta");
  var descEl = document.getElementById("room-description");
  var fullDescEl = document.getElementById("room-fulldesc");
  var highlightsEl = document.getElementById("room-highlights");
  var specsEl = document.getElementById("room-specs");
  var amenityGroupsEl = document.getElementById("room-amenity-groups");
  var perksEl = document.getElementById("room-perks");
  var policiesEl = document.getElementById("room-policies");
  var reviewsSummaryEl = document.getElementById("room-reviews-summary");
  var reviewsListEl = document.getElementById("room-reviews-list");
  var reviewScoreBadge = document.getElementById("review-score-badge");
  var otherRoomsEl = document.getElementById("other-rooms");
  var priceEl = document.getElementById("room-price");

  var API_BASE = (window.HotelAPI && window.HotelAPI.base) ? window.HotelAPI.base : "/Backend/public/index.php";

  function resolveRoomImgUrl(duongDan) {
    if (!duongDan) return "";
    if (/^https?:\/\//i.test(duongDan) || duongDan.indexOf("../") === 0) return duongDan;
    return "../../" + String(duongDan).replace(/^\/+/, "");
  }

  function applyBackendRoomDetail(room, raw, roomId) {
    if (!raw || typeof raw !== "object") return room;
    var r = room ? Object.assign({}, room) : {
      id: raw.MaLoaiPhong || roomId,
      name: "",
      price: 0,
      capacity: 2,
      area: "",
      floor: "",
      bed: "",
      view: "",
      desc: "",
      fullDesc: "",
      tagline: "",
      highlights: [],
      amenities: [],
      perks: []
    };

    if (raw.MaLoaiPhong) r.id = raw.MaLoaiPhong;
    if (raw.TenLoaiPhong) r.name = String(raw.TenLoaiPhong).replace(/^Phong\s+/i, "").replace(/^Phòng\s+/i, "").trim();
    if (raw.Gia != null) r.price = Number(raw.Gia);
    if (raw.SoNguoiToiDa != null) r.capacity = Number(raw.SoNguoiToiDa);
    if (raw.MoTa != null && String(raw.MoTa).trim() !== "") r.desc = raw.MoTa;
    if (raw.tom_tat != null && String(raw.tom_tat).trim() !== "") r.tagline = raw.tom_tat;

    if (raw.dien_tich) r.area = raw.dien_tich;
    if (raw.tang) r.floor = raw.tang;
    if (raw.bo_tri_giuong) r.bed = raw.bo_tri_giuong;
    if (raw.huong_view) r.view = raw.huong_view;

    var hasNewSpecs = !!(raw.dien_tich || raw.tang || raw.bo_tri_giuong || raw.huong_view || raw.tien_nghi_phong_tam || raw.quy_dinh_hut_thuoc);
    if (hasNewSpecs) {
      r.specs = {
        area: raw.dien_tich || (r.area ? r.area + " m²" : ""),
        floor: raw.tang || "",
        bed: raw.bo_tri_giuong || "",
        capacity: r.capacity ? r.capacity + " khách" : "",
        view: raw.huong_view || "",
        bathroom: raw.tien_nghi_phong_tam || "",
        smoking: raw.quy_dinh_hut_thuoc || ""
      };
    }

    if (Array.isArray(raw.diem_nhan_list) && raw.diem_nhan_list.length > 0) {
      r.highlights = raw.diem_nhan_list;
    } else if (!room) {
      r.highlights = [];
    }

    if (Array.isArray(raw.dac_quyen_list) && raw.dac_quyen_list.length > 0) {
      r.perks = raw.dac_quyen_list;
    } else if (!room) {
      r.perks = [];
    }

    if (raw.tien_nghi_nhom && typeof raw.tien_nghi_nhom === "object" && Object.keys(raw.tien_nghi_nhom).length > 0) {
      r.tien_nghi_nhom = raw.tien_nghi_nhom;
    } else if (raw.TienNghi && !room) {
      r.amenities = String(raw.TienNghi).split(",").map(function (s) { return s.trim(); }).filter(Boolean);
    }

    var hasNewPolicies = !!(raw.gio_nhan_phong || raw.gio_tra_phong || raw.chinh_sach_huy || raw.chinh_sach_tre_em || raw.phuong_thuc_coc_thanh_toan);
    if (hasNewPolicies) {
      r.policies = {
        checkIn: raw.gio_nhan_phong || "",
        checkOut: raw.gio_tra_phong || "",
        cancellation: raw.chinh_sach_huy || "",
        children: raw.chinh_sach_tre_em || "",
        deposit: raw.phuong_thuc_coc_thanh_toan || ""
      };
    }

    if (Array.isArray(raw.anh) && raw.anh.length > 0) {
      r.uploadedImages = raw.anh.map(function (a) { return resolveRoomImgUrl(a.duong_dan); }).filter(Boolean);
    }

    r._hasBackendDetail = true;
    return r;
  }

  function renderRoom(id) {
    var roomId = id || getRoomId();
    var localRoom = window.SkyAPI ? window.SkyAPI.getRoomType(roomId) : null;
    var allRooms = window.SkyAPI && window.SkyAPI.getRoomTypes ? window.SkyAPI.getRoomTypes() : [];

    // Gọi API công khai chi tiết loại phòng để lấy đủ ảnh, tiện nghi theo nhóm, thông số, đặc quyền, chính sách
    fetch(API_BASE + "/loaiphong/xemctloaiphong?MaLoaiPhong=" + encodeURIComponent(roomId))
      .then(function (res) { return res.json(); })
      .then(function (json) {
        var payload = json && json.data ? json.data : null;
        if (payload && payload.data && !payload.MaLoaiPhong) payload = payload.data;
        if (json && json.success !== false && payload && payload.MaLoaiPhong) {
          var merged = applyBackendRoomDetail(localRoom, payload, roomId);
          renderRoomUI(merged, roomId, allRooms);
        } else if (localRoom) {
          renderRoomUI(localRoom, roomId, allRooms);
        } else {
          showRoomNotFound(roomId);
        }
      })
      .catch(function () {
        if (localRoom) {
          renderRoomUI(localRoom, roomId, allRooms);
        } else {
          showRoomNotFound(roomId);
        }
      });

    if (localRoom) {
      renderRoomUI(localRoom, roomId, allRooms);
    }
  }

  function showRoomNotFound(roomId) {
    if (detailError) {
      detailError.textContent = "Không tìm thấy thông tin hạng phòng '" + roomId + "'. Vui lòng chọn hạng phòng khác.";
      detailError.hidden = false;
    }
    if (roomDetails) roomDetails.hidden = true;
    currentRoom = null;
  }

  function renderRoomUI(room, roomId, allRooms) {
    if (!room) {
      showRoomNotFound(roomId);
      return;
    }

    currentRoom = room;

    if (detailError) detailError.hidden = true;
    if (roomDetails) roomDetails.hidden = false;

    // Update Breadcrumb & Document Title
    document.title = "SkyHotel - Phòng " + room.name;
    if (crumbEl) crumbEl.textContent = "Phòng " + room.name;

    // Tier Badge
    if (tierBadge) {
      tierBadge.textContent = TIER_LABELS[room.id] || "5-Star Luxury";
    }

    // Header Details
    if (roomNameEl) roomNameEl.textContent = "Phòng " + room.name;

    if (ratingBadge) {
      var rScore = room.rating || 4.9;
      var rCnt = room.reviewCount || 120;
      ratingBadge.innerHTML = "★ " + rScore + " <span>(" + rCnt + " đánh giá từ khách)</span>";
    }

    if (taglineEl) {
      taglineEl.textContent = room.tagline || room.desc || "";
      taglineEl.hidden = !taglineEl.textContent;
    }

    if (viewEl) {
      if (room.view) {
        viewEl.innerHTML = "🧭 " + esc(room.view);
        viewEl.hidden = false;
      } else {
        viewEl.hidden = true;
      }
    }

    if (metaEl) {
      var metaParts = [];
      if (room.area) metaParts.push("<span>📐 Diện tích: <b>" + esc(String(room.area).replace(/\s*m²$/i, "")) + " m²</b></span>");
      if (room.floor) metaParts.push("<span>🏢 Vị trí: <b>" + esc(room.floor) + "</b></span>");
      if (room.capacity) metaParts.push("<span>👥 Sức chứa: <b>" + esc(room.capacity) + " khách</b></span>");
      if (room.bed) metaParts.push("<span>🛏️ Loại giường: <b>" + esc(room.bed) + "</b></span>");
      metaEl.innerHTML = metaParts.join("");
      metaEl.hidden = metaParts.length === 0;
    }

    // 2. Điểm nhấn không gian & Giới thiệu
    if (descEl) {
      descEl.textContent = room.desc || "";
      descEl.hidden = !room.desc;
    }
    if (fullDescEl) {
      fullDescEl.textContent = room.fullDesc || "";
      fullDescEl.hidden = !room.fullDesc;
    }
    if (highlightsEl) {
      if (room.highlights && room.highlights.length) {
        highlightsEl.innerHTML = room.highlights.map(function (hl) {
          return (
            '<div class="highlight-card">' +
            '<div class="highlight-icon">' + esc(hl.icon || "✨") + "</div>" +
            '<div class="highlight-content">' +
            "<b>" + esc(hl.title) + "</b>" +
            "<small>" + esc(hl.desc) + "</small>" +
            "</div>" +
            "</div>"
          );
        }).join("");
        highlightsEl.hidden = false;
      } else {
        highlightsEl.innerHTML = "";
        highlightsEl.hidden = true;
      }
    }
    var overviewSec = highlightsEl ? highlightsEl.closest("section") : null;
    if (overviewSec) {
      overviewSec.hidden = !(room.desc || room.fullDesc || (room.highlights && room.highlights.length));
    }

    // 3. Thông số & Bố cục phòng
    if (specsEl) {
      var sp = room.specs || {
        area: room.area ? String(room.area).replace(/\s*m²$/i, "") + " m²" : "",
        floor: room.floor || "",
        bed: room.bed || "",
        capacity: room.capacity ? room.capacity + " khách" : "",
        view: room.view || "",
        bathroom: "",
        smoking: ""
      };

      var specItems = [
        { label: "Diện tích không gian", val: sp.area },
        { label: "Vị trí tầng", val: sp.floor },
        { label: "Bố trí giường & Đệm", val: sp.bed },
        { label: "Sức chứa tối đa", val: sp.capacity },
        { label: "Tầm nhìn / Hướng view", val: sp.view },
        { label: "Tiện nghi phòng tắm", val: sp.bathroom },
        { label: "Quy định hút thuốc", val: sp.smoking }
      ].filter(function (s) { return s.val && String(s.val).trim() !== ""; });

      var specsSec = specsEl.closest("section");
      if (specItems.length > 0) {
        specsEl.innerHTML = specItems.map(function (s) {
          return (
            '<div class="spec-item">' +
            '<div class="spec-label">' + esc(s.label) + "</div>" +
            '<div class="spec-val">' + esc(s.val) + "</div>" +
            "</div>"
          );
        }).join("");
        if (specsSec) specsSec.hidden = false;
      } else {
        specsEl.innerHTML = "";
        if (specsSec) specsSec.hidden = true;
      }
    }

    // 4. Tiện nghi đẳng cấp 5 sao
    if (amenityGroupsEl) {
      var amenitiesSec = amenityGroupsEl.closest("section");
      var renderedAmenities = "";

      if (room.tien_nghi_nhom && Object.keys(room.tien_nghi_nhom).length > 0) {
        var groupIcons = {
          "Phòng ngủ & Thư giãn": "🛏️",
          "Phòng tắm & Chăm sóc cá nhân": "🚿",
          "Công nghệ & Giải trí": "📱",
          "Ẩm thực & Thức uống": "☕",
          "Tiện ích & Dịch vụ an toàn": "🛡️"
        };
        renderedAmenities = Object.keys(room.tien_nghi_nhom).map(function (gName) {
          var items = room.tien_nghi_nhom[gName] || [];
          if (!items.length) return "";
          var pills = items.map(function (it) {
            var icon = it.icon ? esc(it.icon) + " " : "";
            return '<span class="amenity-pill">' + icon + esc(it.ten) + "</span>";
          }).join("");
          return (
            '<div class="amenity-group">' +
            '<div class="amenity-group-title">' + (groupIcons[gName] || "✨") + " " + esc(gName) + "</div>" +
            '<div class="amenity-pills">' + pills + "</div>" +
            "</div>"
          );
        }).join("");
      } else if (room.amenityCategories) {
        var cats = room.amenityCategories;
        var catMeta = [
          { key: "bedroom", title: "🛏️ Phòng ngủ & Thư giãn" },
          { key: "bathroom", title: "🚿 Phòng tắm & Chăm sóc cá nhân" },
          { key: "technology", title: "📱 Công nghệ & Giải trí" },
          { key: "dining", title: "☕ Ẩm thực & Thức uống" },
          { key: "services", title: "🛡️ Tiện ích & Dịch vụ an toàn" }
        ];
        renderedAmenities = catMeta.map(function (c) {
          var items = cats[c.key] || [];
          if (!items.length) return "";
          var pills = items.map(function (item) {
            return '<span class="amenity-pill">' + esc(item) + "</span>";
          }).join("");
          return (
            '<div class="amenity-group">' +
            '<div class="amenity-group-title">' + esc(c.title) + "</div>" +
            '<div class="amenity-pills">' + pills + "</div>" +
            "</div>"
          );
        }).join("");
      } else if (room.amenities && room.amenities.length > 0) {
        renderedAmenities =
          '<div class="amenity-group">' +
          '<div class="amenity-group-title">✨ Tiện nghi đi kèm</div>' +
          '<div class="amenity-pills">' +
          room.amenities.map(function (a) { return '<span class="amenity-pill">' + esc(a) + "</span>"; }).join("") +
          "</div></div>";
      }

      amenityGroupsEl.innerHTML = renderedAmenities;
      if (amenitiesSec) amenitiesSec.hidden = !renderedAmenities;
    }

    // 5. Đặc quyền bao gồm
    if (perksEl) {
      var perksSec = perksEl.closest("section");
      if (room.perks && room.perks.length) {
        perksEl.innerHTML = room.perks.map(function (perk) {
          return (
            '<div class="perk-item">' +
            "<i>✓</i>" +
            "<span>" + esc(perk) + "</span>" +
            "</div>"
          );
        }).join("");
        if (perksSec) perksSec.hidden = false;
      } else {
        perksEl.innerHTML = "";
        if (perksSec) perksSec.hidden = true;
      }
    }

    // 6. Chính sách nhận/trả phòng & Lưu trú
    if (policiesEl) {
      var policiesSec = policiesEl.closest("section");
      var defaultCancelPolicy = (window.HotelAPI && window.HotelAPI.policy)
        ? window.HotelAPI.policy.getSummaryText()
        : "";
      var pol = room.policies || (room._hasBackendDetail ? {} : {
        checkIn: "Từ 14:00 (Hỗ trợ nhận phòng sớm theo tình trạng phòng)",
        checkOut: "Trước 12:00 trưa",
        cancellation: defaultCancelPolicy,
        children: "Miễn phí 01 trẻ em dưới 6 tuổi ngủ cùng bố mẹ.",
        deposit: "Thanh toán trực tuyến qua VietQR hoặc trực tiếp tại quầy."
      });

      var policyItems = [
        { icon: "🕒", title: "Giờ nhận phòng (Check-in)", text: pol.checkIn },
        { icon: "🕛", title: "Giờ trả phòng (Check-out)", text: pol.checkOut },
        { icon: "🔄", title: "Chính sách hủy phòng & Hoàn tiền", text: pol.cancellation },
        { icon: "👶", title: "Chính sách trẻ em & Giường phụ", text: pol.children },
        { icon: "💳", title: "Phương thức đặt cọc & Thanh toán", text: pol.deposit }
      ].filter(function (p) { return p.text && String(p.text).trim() !== ""; });

      if (policyItems.length > 0) {
        policiesEl.innerHTML = policyItems.map(function (p) {
          return (
            '<div class="policy-card">' +
            '<div class="policy-title"><span>' + p.icon + "</span> <b>" + esc(p.title) + "</b></div>" +
            '<div class="policy-text">' + esc(p.text) + "</div>" +
            "</div>"
          );
        }).join("");
        if (policiesSec) policiesSec.hidden = false;
      } else {
        policiesEl.innerHTML = "";
        if (policiesSec) policiesSec.hidden = true;
      }
    }

    // Reviews: chỉ hiển thị khi có dữ liệu thật từ Backend (chưa có API đánh giá theo loại phòng -> ẩn khối này)
    var reviewsSection = reviewsSummaryEl ? reviewsSummaryEl.closest("section") : null;
    var hasRealReviews = Array.isArray(room.reviews) && room.reviews.length > 0;
    if (reviewsSection) reviewsSection.hidden = !hasRealReviews;
    if (reviewScoreBadge) {
      reviewScoreBadge.hidden = !hasRealReviews;
      if (hasRealReviews && room.rating) reviewScoreBadge.textContent = "★ " + room.rating + " / 5";
    }
    if (reviewsSummaryEl) {
      reviewsSummaryEl.hidden = !hasRealReviews;
      reviewsSummaryEl.innerHTML = "";
    }
    if (reviewsListEl) {
      reviewsListEl.hidden = !hasRealReviews;
      if (hasRealReviews) {
        reviewsListEl.innerHTML = room.reviews.map(function (rev) {
          var firstLetter = (rev.name || "K").charAt(0).toUpperCase();
          return (
            '<div class="review-card">' +
            '<div class="review-card-head">' +
            '<div class="reviewer-info">' +
            '<div class="reviewer-av">' + firstLetter + "</div>" +
            '<div class="reviewer-name">' +
            "<b>" + esc(rev.name) + "</b>" +
            "<small>" + esc(rev.role) + " · " + esc(rev.date) + "</small>" +
            "</div>" +
            "</div>" +
            '<div class="review-stars">★ ★ ★ ★ ★</div>' +
            "</div>" +
            '<div class="review-comment">“' + esc(rev.comment) + '”</div>' +
            "</div>"
          );
        }).join("");
      } else {
        reviewsListEl.innerHTML = "";
      }
    }

    // Other Rooms Grid (exclude current room)
    if (otherRoomsEl && allRooms.length) {
      var others = allRooms.filter(function (r) { return r.id !== room.id; });
      otherRoomsEl.innerHTML = others.map(function (r) {
        return (
          '<a class="other-room-card" href="#' + encodeURIComponent(r.id) + '" data-room-id="' + esc(r.id) + '">' +
          '<div class="other-room-img">' +
          '<img src="' + esc(r.image) + '" alt="Phòng ' + esc(r.name) + '" loading="lazy">' +
          "</div>" +
          '<div class="other-room-body">' +
          '<div class="other-room-title">' + esc(r.name) + "</div>" +
          '<div class="other-room-meta">' + r.area + " m² · " + r.capacity + " khách</div>" +
          '<div class="other-room-price">Từ ' + fmt(r.price) + " / đêm</div>" +
          "</div>" +
          "</a>"
        );
      }).join("");
    }

    // Price in Booking Box
    if (priceEl) {
      priceEl.innerHTML = fmt(room.price) + "<small>/đêm</small>";
    }

    // Carousel init (ưu tiên ảnh upload từ Backend, fallback sang ảnh tĩnh nếu có)
    var imgs = (room.uploadedImages && room.uploadedImages.length)
      ? room.uploadedImages
      : (galleries[room.id] || (room.image ? [room.image] : []));
    var sliderBox = document.querySelector(".slider-container");
    if (sliderBox) sliderBox.hidden = imgs.length === 0;
    initCarousel(imgs, room.name);

    // Wishlist UI update
    updateWishlistUI();
  }

  // Wishlist Logic
  var saveBtn = document.getElementById("save");
  var saveIcon = document.getElementById("save-icon");
  var saveText = document.getElementById("save-text");
  var btnViewWishlist = document.getElementById("btn-view-wishlist");
  var favCountEls = document.querySelectorAll(".dt-fav-count, .foot-fav-count");

  function updateWishlistUI() {
    if (!currentRoom) return;
    var isFav = window.SkyWishlist ? window.SkyWishlist.has(currentRoom.id) : false;
    var count = window.SkyWishlist ? window.SkyWishlist.count() : 0;

    if (saveBtn) {
      saveBtn.classList.toggle("on", isFav);
      if (saveIcon) saveIcon.textContent = isFav ? "♥" : "♡";
      if (saveText) saveText.textContent = isFav ? "Đã lưu vào yêu thích" : "Lưu vào danh sách yêu thích";
    }

    favCountEls.forEach(function (el) {
      el.textContent = count;
    });
  }

  function isAdminViewer() {
    return !!(window.HotelAPI && window.HotelAPI.isAdminSession && window.HotelAPI.isAdminSession());
  }

  if (saveBtn) {
    saveBtn.addEventListener("click", function () {
      if (isAdminViewer()) return;
      if (window.SkyWishlist && currentRoom) {
        var added = window.SkyWishlist.toggle(currentRoom.id);
        updateWishlistUI();
        if (added) {
          if (parent && parent.postMessage) {
            parent.postMessage({ skyToast: "Đã lưu phòng " + currentRoom.name + " vào danh sách yêu thích!" }, "*");
          }
        } else {
          if (parent && parent.postMessage) {
            parent.postMessage({ skyToast: "Đã bỏ phòng " + currentRoom.name + " khỏi danh sách yêu thích." }, "*");
          }
        }
      }
    });
  }

  if (btnViewWishlist) {
    btnViewWishlist.addEventListener("click", function () {
      if (isAdminViewer()) return;
      if (window.SkyWishlist) {
        window.SkyWishlist.openDrawer();
      }
    });
  }

  var footFavLink = document.getElementById("foot-fav-link");
  if (footFavLink) {
    footFavLink.addEventListener("click", function (e) {
      e.preventDefault();
      if (isAdminViewer()) return;
      if (window.SkyWishlist) window.SkyWishlist.openDrawer();
    });
  }

  window.addEventListener("sky_wishlist_change", updateWishlistUI);

  // Booking button click
  var bookBtn = document.getElementById("btn-book");
  if (isAdminViewer()) {
    if (bookBtn) bookBtn.style.display = "none";
    var wlWrap = document.querySelector(".wishlist-action-wrap");
    if (wlWrap) wlWrap.style.display = "none";
    if (window.HotelAPI && window.HotelAPI.hideCustomerOnlyLinks) window.HotelAPI.hideCustomerOnlyLinks();
  }
  if (bookBtn) {
    bookBtn.addEventListener("click", function () {
      if (isAdminViewer()) return;
      if (parent !== window) {
        parent.postMessage({ skyGo: "rooms" }, "*");
      } else {
        location.href = "chon-phong.html";
      }
    });
  }

  // Back button click
  var backBtn = document.getElementById("back-btn");
  if (backBtn) {
    backBtn.addEventListener("click", function (e) {
      e.preventDefault();
      if (parent !== window) {
        parent.postMessage({ skyGo: "rooms" }, "*");
      } else {
        location.href = "chon-phong.html";
      }
    });
  }

  /* Banner ảnh tự động lướt */
  var carouselState = {
    cur: 0,
    timer: null,
    paused: false,
    hover: false,
    imgs: [],
    roomName: "",
    reduce: window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches
  };

  var slides = document.getElementById("slides"),
    count = document.getElementById("count"),
    bar = document.getElementById("bar"),
    pp = document.getElementById("pp"),
    prev = document.getElementById("prev"),
    next = document.getElementById("next"),
    mainSlider = document.getElementById("main"),
    th = document.getElementById("thumbs");

  function restartBar() {
    if (!bar) return;
    bar.style.transition = "none";
    bar.style.width = "0";
    void bar.offsetWidth;
    var many = carouselState.imgs.length > 1;
    if (!carouselState.paused && !carouselState.hover && many && !carouselState.reduce) {
      bar.style.transition = "width " + SLIDE_MS + "ms linear";
      bar.style.width = "100%";
    }
  }

  function scheduleSlide() {
    clearTimeout(carouselState.timer);
    restartBar();
    var many = carouselState.imgs.length > 1;
    if (carouselState.paused || carouselState.hover || !many || carouselState.reduce) return;
    carouselState.timer = setTimeout(function () {
      showSlide(carouselState.cur + 1);
    }, SLIDE_MS);
  }

  function showSlide(i, fromUser) {
    if (!carouselState.imgs.length) return;
    carouselState.cur = (i + carouselState.imgs.length) % carouselState.imgs.length;
    var cur = carouselState.cur;
    if (slides) {
      [].forEach.call(slides.children, function (e, j) {
        e.classList.toggle("on", j == cur);
        e.setAttribute("aria-hidden", j == cur ? "false" : "true");
      });
    }
    if (th) {
      [].forEach.call(th.children, function (e, j) {
        e.classList.toggle("on", j == cur);
        e.setAttribute("aria-pressed", j == cur ? "true" : "false");
        if (j == cur && fromUser !== undefined && th.scrollTo)
          th.scrollTo({ left: e.offsetLeft - 8, behavior: "smooth" });
      });
    }
    if (count) count.textContent = cur + 1 + " / " + carouselState.imgs.length;
    if (mainSlider) {
      mainSlider.setAttribute(
        "aria-label",
        "Ảnh phòng " + carouselState.roomName + ", ảnh " + (cur + 1) + " trên " + carouselState.imgs.length
      );
    }
    scheduleSlide();
  }

  function initCarousel(imgs, roomName) {
    clearTimeout(carouselState.timer);
    carouselState.imgs = imgs || [];
    carouselState.roomName = roomName || "";
    carouselState.cur = 0;
    carouselState.paused = false;
    carouselState.hover = false;

    if (pp) {
      pp.textContent = "❚❚";
      pp.setAttribute("aria-label", "Tạm dừng tự động lướt");
    }

    if (!slides || !th) return;

    slides.innerHTML = "";
    th.innerHTML = "";

    var many = carouselState.imgs.length > 1;

    carouselState.imgs.forEach(function (src, i) {
      var d = document.createElement("div");
      d.className = "slide";
      d.setAttribute("aria-hidden", "true");
      var image = document.createElement("img");
      image.src = src;
      image.alt = "Phòng " + carouselState.roomName + " tại SKY HOTEL, ảnh " + (i + 1);
      if (i > 0) image.loading = "lazy";
      image.addEventListener("click", function () {
        openLightbox(i);
      });
      d.appendChild(image);
      slides.appendChild(d);

      var b = document.createElement("button");
      b.type = "button";
      b.setAttribute("aria-label", "Xem ảnh " + (i + 1) + " của phòng " + carouselState.roomName);
      b.setAttribute("aria-pressed", "false");
      var thumbnail = document.createElement("img");
      thumbnail.src = src;
      thumbnail.alt = "";
      thumbnail.loading = "lazy";
      b.appendChild(thumbnail);
      b.addEventListener("click", function () {
        showSlide(i, true);
      });
      th.appendChild(b);
    });

    th.hidden = !many;
    [prev, next, pp, count, bar && bar.parentNode].forEach(function (e) {
      if (e) e.hidden = !many;
    });

    showSlide(0);
  }

  // Lightbox phóng to ảnh
  var lbEl = document.getElementById("dt-lightbox");
  var lbImg = document.getElementById("dt-lb-img");
  var lbCount = document.getElementById("dt-lb-count");
  var lbClose = document.getElementById("dt-lb-close");
  var lbPrev = document.getElementById("dt-lb-prev");
  var lbNext = document.getElementById("dt-lb-next");
  var lbIndex = 0;

  function updateLightbox() {
    if (!lbEl || !carouselState.imgs.length) return;
    lbIndex = (lbIndex + carouselState.imgs.length) % carouselState.imgs.length;
    if (lbImg) lbImg.src = carouselState.imgs[lbIndex];
    if (lbCount) lbCount.textContent = (lbIndex + 1) + " / " + carouselState.imgs.length;
    var many = carouselState.imgs.length > 1;
    if (lbPrev) lbPrev.hidden = !many;
    if (lbNext) lbNext.hidden = !many;
  }

  function openLightbox(idx) {
    if (!lbEl || !carouselState.imgs.length) return;
    lbIndex = idx || 0;
    updateLightbox();
    lbEl.hidden = false;
  }

  function closeLightbox() {
    if (lbEl) lbEl.hidden = true;
  }

  if (lbClose) lbClose.addEventListener("click", closeLightbox);
  if (lbPrev) lbPrev.addEventListener("click", function () { lbIndex--; updateLightbox(); });
  if (lbNext) lbNext.addEventListener("click", function () { lbIndex++; updateLightbox(); });
  if (lbEl) {
    lbEl.addEventListener("click", function (e) {
      if (e.target === lbEl) closeLightbox();
    });
  }
  document.addEventListener("keydown", function (e) {
    if (!lbEl || lbEl.hidden) return;
    if (e.key === "Escape") closeLightbox();
    else if (e.key === "ArrowLeft") { lbIndex--; updateLightbox(); }
    else if (e.key === "ArrowRight") { lbIndex++; updateLightbox(); }
  });

  if (prev) {
    prev.addEventListener("click", function () {
      showSlide(carouselState.cur - 1, true);
    });
  }
  if (next) {
    next.addEventListener("click", function () {
      showSlide(carouselState.cur + 1, true);
    });
  }
  if (pp) {
    pp.addEventListener("click", function () {
      carouselState.paused = !carouselState.paused;
      pp.textContent = carouselState.paused ? "▶" : "❚❚";
      pp.setAttribute("aria-label", carouselState.paused ? "Bật tự động lướt" : "Tạm dừng tự động lướt");
      scheduleSlide();
    });
  }

  if (mainSlider) {
    mainSlider.addEventListener("mouseenter", function () {
      carouselState.hover = true;
      scheduleSlide();
    });
    mainSlider.addEventListener("mouseleave", function () {
      carouselState.hover = false;
      scheduleSlide();
    });
    mainSlider.tabIndex = 0;
    mainSlider.addEventListener("keydown", function (e) {
      if (e.key == "ArrowLeft") showSlide(carouselState.cur - 1, true);
      if (e.key == "ArrowRight") showSlide(carouselState.cur + 1, true);
    });

    var x0 = null;
    mainSlider.addEventListener("touchstart", function (e) {
      x0 = e.touches[0].clientX;
    }, { passive: true });
    mainSlider.addEventListener("touchend", function (e) {
      if (x0 === null) return;
      var dx = e.changedTouches[0].clientX - x0;
      x0 = null;
      if (Math.abs(dx) > 40) showSlide(carouselState.cur + (dx < 0 ? 1 : -1), true);
    });
  }

  document.addEventListener("visibilitychange", function () {
    if (document.hidden) clearTimeout(carouselState.timer);
    else scheduleSlide();
  });

  // Event delegation for switching other rooms smoothly
  document.addEventListener("click", function (e) {
    var card = e.target.closest(".other-room-card");
    if (card) {
      e.preventDefault();
      var targetId = card.getAttribute("data-room-id") || (card.getAttribute("href") || "").replace(/^#/, "");
      if (targetId) {
        if (parent !== window) {
          parent.postMessage({ skyGo: "detail:" + targetId }, "*");
        } else {
          if (location.hash !== "#" + targetId) {
            location.hash = "#" + encodeURIComponent(targetId);
          } else {
            renderRoom(targetId);
          }
          scrollToTop();
        }
      }
    }
  });

  // Hashchange listener for smooth switching
  window.addEventListener("hashchange", function () {
    renderRoom(getRoomId());
    scrollToTop();
  });

  // Message listener from parent shell if loaded in iframe
  window.addEventListener("message", function (e) {
    var d = e.data || {};
    if (d.skyHash) {
      var hashUrl = "#" + encodeURIComponent(d.skyHash);
      try {
        history.replaceState(null, "", hashUrl);
      } catch (err) {
        if (location.hash !== hashUrl) location.hash = hashUrl;
      }
      renderRoom(d.skyHash);
      scrollToTop();
    }
  });

  // Initial render & scroll reset
  renderRoom(getRoomId());
  scrollToTop();
  window.addEventListener("load", scrollToTop);
})();
