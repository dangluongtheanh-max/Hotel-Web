(function (w) {
  "use strict";

  function ensureToastHost() {
    var host = document.getElementById("sky-ui-toast-host");
    if (!host) {
      host = document.createElement("div");
      host.id = "sky-ui-toast-host";
      host.className = "sky-ui-toast-host";
      document.body.appendChild(host);
    }
    return host;
  }

  function toast(msg, type) {
    if (!msg) return;
    if (!document.body) {
      document.addEventListener("DOMContentLoaded", function () { toast(msg, type); });
      return;
    }
    var host = ensureToastHost();
    var el = document.createElement("div");
    el.className = "sky-ui-toast" + (type ? " sky-ui-toast-" + type : "");
    el.setAttribute("role", "status");
    el.textContent = String(msg);
    host.appendChild(el);
    requestAnimationFrame(function () { el.classList.add("show"); });
    setTimeout(function () {
      el.classList.remove("show");
      setTimeout(function () {
        if (el.parentNode) el.parentNode.removeChild(el);
      }, 240);
    }, 3200);
  }

  function openModal(opts) {
    return new Promise(function (resolve) {
      if (!document.body) {
        resolve(opts.mode === "confirm" ? false : null);
        return;
      }
      var backdrop = document.createElement("div");
      backdrop.className = "sky-ui-backdrop";

      var card = document.createElement("div");
      card.className = "sky-ui-modal";
      card.setAttribute("role", "dialog");
      card.setAttribute("aria-modal", "true");

      var title = document.createElement("div");
      title.className = "sky-ui-modal-title";
      title.textContent = opts.title || (opts.mode === "confirm" ? "Xác nhận" : opts.mode === "prompt" ? "Nhập thông tin" : "Thông báo");

      var body = document.createElement("div");
      body.className = "sky-ui-modal-body";
      body.textContent = String(opts.message || "");

      var inputEl = null;
      if (opts.mode === "prompt") {
        inputEl = document.createElement("input");
        inputEl.type = "text";
        inputEl.className = "sky-ui-modal-input";
        inputEl.value = opts.defaultValue != null ? String(opts.defaultValue) : "";
        body.appendChild(inputEl);
      }

      var footer = document.createElement("div");
      footer.className = "sky-ui-modal-footer";

      var closed = false;
      function close(val) {
        if (closed) return;
        closed = true;
        document.removeEventListener("keydown", onKey);
        backdrop.classList.remove("show");
        setTimeout(function () {
          if (backdrop.parentNode) backdrop.parentNode.removeChild(backdrop);
        }, 180);
        resolve(val);
      }

      if (opts.mode === "confirm" || opts.mode === "prompt") {
        var btnCancel = document.createElement("button");
        btnCancel.type = "button";
        btnCancel.className = "sky-ui-btn sky-ui-btn-cancel";
        btnCancel.textContent = opts.cancelText || "Hủy";
        btnCancel.onclick = function () { close(opts.mode === "confirm" ? false : null); };
        footer.appendChild(btnCancel);
      }

      var btnOk = document.createElement("button");
      btnOk.type = "button";
      btnOk.className = "sky-ui-btn sky-ui-btn-ok";
      btnOk.textContent = opts.okText || (opts.mode === "confirm" ? "Xác nhận" : "Đồng ý");
      btnOk.onclick = function () {
        if (opts.mode === "prompt") close(inputEl ? inputEl.value : "");
        else if (opts.mode === "confirm") close(true);
        else close();
      };
      footer.appendChild(btnOk);

      function onKey(e) {
        if (e.key === "Escape") {
          e.preventDefault();
          close(opts.mode === "confirm" ? false : null);
        } else if (e.key === "Enter" && opts.mode !== "confirm") {
          e.preventDefault();
          btnOk.click();
        }
      }
      document.addEventListener("keydown", onKey);

      backdrop.addEventListener("click", function (e) {
        if (e.target === backdrop) close(opts.mode === "confirm" ? false : null);
      });

      card.appendChild(title);
      card.appendChild(body);
      card.appendChild(footer);
      backdrop.appendChild(card);
      document.body.appendChild(backdrop);
      requestAnimationFrame(function () {
        backdrop.classList.add("show");
        if (inputEl) {
          inputEl.focus();
          inputEl.select();
        } else {
          btnOk.focus();
        }
      });
    });
  }

  w.SkyUI = {
    toast: toast,
    alert: function (msg, opts) {
      return openModal(Object.assign({ mode: "alert", message: msg }, opts || {}));
    },
    confirm: function (msg, opts) {
      return openModal(Object.assign({ mode: "confirm", message: msg }, opts || {}));
    },
    prompt: function (msg, defaultValue, opts) {
      return openModal(Object.assign({ mode: "prompt", message: msg, defaultValue: defaultValue }, opts || {}));
    }
  };
})(window);

