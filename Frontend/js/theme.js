/* ==========================================================================
   SkyTheme - Fixed Light Mode Controller (Immune to Chrome/OS Dark Mode)
   ========================================================================== */
(function () {
  "use strict";

  var STORAGE_KEY = "skyhotel_theme";

  function applyToDoc(doc) {
    if (!doc || !doc.documentElement) return;
    var root = doc.documentElement;
    root.setAttribute("data-theme", "light");
    root.setAttribute("data-theme-mode", "light");
    root.style.colorScheme = "light only";
  }

  function syncIframe() {
    var fr = document.getElementById("fr");
    if (!fr) return;
    try {
      if (fr.contentDocument) applyToDoc(fr.contentDocument);
    } catch (e) {}
  }

  function apply() {
    try {
      localStorage.removeItem(STORAGE_KEY);
    } catch (e) {}
    applyToDoc(document);
    syncIframe();
    return "light";
  }

  // Apply immediately before first paint
  apply();

  document.addEventListener("DOMContentLoaded", function () {
    var fr = document.getElementById("fr");
    if (fr) {
      fr.addEventListener("load", syncIframe);
    }
  });

  function formatDisplayDate(val) {
    if (!val || typeof val !== "string") return "dd/mm/yyyy";
    var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(val.trim());
    if (!m) return "dd/mm/yyyy";
    return m[3] + "/" + m[2] + "/" + m[1];
  }

  function syncDateInput(el) {
    if (!el) return;
    var val = el.value || "";
    el.setAttribute("data-display-date", formatDisplayDate(val));
    el.classList.toggle("is-placeholder", !val);
  }

  function enhanceDateInput(el) {
    if (!el || el.tagName !== "INPUT" || el.type !== "date") return;
    if (el.dataset.skyDateEnhanced === "1") {
      syncDateInput(el);
      return;
    }
    if (el.classList.contains("g-dob-picker") || el.hasAttribute("data-native-date")) return;

    el.dataset.skyDateEnhanced = "1";
    try {
      var cs = window.getComputedStyle(el);
      if (cs.paddingLeft) el.style.setProperty("--sky-date-left", cs.paddingLeft);
      if (cs.color && cs.color !== "rgba(0, 0, 0, 0)" && cs.color !== "transparent") {
        el.style.setProperty("--sky-date-color", cs.color);
      }
    } catch (e) {}

    el.classList.add("sky-date-ddmmyyyy");

    var desc = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, "value");
    if (desc && desc.get && desc.set) {
      try {
        Object.defineProperty(el, "value", {
          configurable: true,
          get: function () {
            return desc.get.call(this);
          },
          set: function (v) {
            desc.set.call(this, v);
            syncDateInput(this);
          }
        });
      } catch (e) {}
    }

    el.addEventListener("input", function () { syncDateInput(el); });
    el.addEventListener("change", function () { syncDateInput(el); });
    el.addEventListener("click", function () {
      try {
        if (typeof el.showPicker === "function") el.showPicker();
      } catch (e) {}
    });

    syncDateInput(el);
  }

  function enhanceAllDateInputs(root) {
    var scope = root && root.querySelectorAll ? root : document;
    var list = scope.querySelectorAll('input[type="date"]');
    for (var i = 0; i < list.length; i++) {
      enhanceDateInput(list[i]);
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
      enhanceAllDateInputs(document);
    });
  } else {
    enhanceAllDateInputs(document);
  }

  if (typeof MutationObserver === "function") {
    var dateObserver = new MutationObserver(function (mutations) {
      for (var i = 0; i < mutations.length; i++) {
        var added = mutations[i].addedNodes;
        for (var j = 0; j < added.length; j++) {
          var node = added[j];
          if (!node || node.nodeType !== 1) continue;
          if (node.tagName === "INPUT" && node.type === "date") {
            enhanceDateInput(node);
          } else if (node.querySelectorAll) {
            enhanceAllDateInputs(node);
          }
        }
      }
    });
    var targetNode = document.documentElement || document;
    dateObserver.observe(targetNode, { childList: true, subtree: true });
  }

  window.SkyTheme = {
    STORAGE_KEY: STORAGE_KEY,
    getMode: function () { return "light"; },
    setMode: function () { return apply(); },
    getResolved: function () { return "light"; },
    cycle: function () { return "light"; },
    apply: apply,
    enhanceDateInputs: enhanceAllDateInputs
  };
})();
