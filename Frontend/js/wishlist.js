/* ==========================================================================
   SKY HOTEL - Quản lý Danh sách phòng yêu thích (Wishlist)
   Lưu trữ trong localStorage ("sky_favorites") & đồng bộ qua postMessage / storage event
   ========================================================================== */
(function () {
  "use strict";

  var KEY = "sky_favorites";

  function getList() {
    try {
      var raw = localStorage.getItem(KEY);
      var arr = JSON.parse(raw || "[]");
      return Array.isArray(arr) ? arr : [];
    } catch (e) {
      return [];
    }
  }

  function setList(arr) {
    try {
      localStorage.setItem(KEY, JSON.stringify(arr));
    } catch (e) {}
    notify(arr);
  }

  function notify(arr) {
    var c = arr.length;
    // Dispatch local custom event
    try {
      window.dispatchEvent(new CustomEvent("sky_wishlist_change", { detail: { favorites: arr, count: c } }));
    } catch (e) {}

    // Post message to parent (if inside iframe)
    if (window.parent && window.parent !== window) {
      window.parent.postMessage({ skyFavChange: c, favorites: arr }, "*");
    }
  }

  var SkyWishlist = {
    getAll: function () {
      return getList();
    },

    has: function (id) {
      if (!id) return false;
      return getList().indexOf(String(id).toLowerCase()) > -1;
    },

    add: function (id) {
      if (!id) return;
      var list = getList();
      id = String(id).toLowerCase();
      if (list.indexOf(id) === -1) {
        list.push(id);
        setList(list);
      }
      return list;
    },

    remove: function (id) {
      if (!id) return;
      var list = getList();
      id = String(id).toLowerCase();
      var idx = list.indexOf(id);
      if (idx > -1) {
        list.splice(idx, 1);
        setList(list);
      }
      return list;
    },

    toggle: function (id) {
      if (!id) return false;
      id = String(id).toLowerCase();
      var list = getList();
      var idx = list.indexOf(id);
      var isSaved = false;
      if (idx > -1) {
        list.splice(idx, 1);
        isSaved = false;
      } else {
        list.push(id);
        isSaved = true;
      }
      setList(list);
      return isSaved;
    },

    count: function () {
      return getList().length;
    },

    clear: function () {
      setList([]);
    },

    openDrawer: function () {
      if (window.parent && window.parent !== window) {
        window.parent.postMessage({ skyOpenWishlist: 1 }, "*");
      } else if (typeof window.openWishlistDrawer === "function") {
        window.openWishlistDrawer();
      }
    }
  };

  // Listen to cross-tab storage changes
  window.addEventListener("storage", function (e) {
    if (e.key === KEY) {
      notify(getList());
    }
  });

  window.SkyWishlist = SkyWishlist;
})();
