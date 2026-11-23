var TARGET_ORIGIN = (window.location.protocol === "file:") ? "*" : window.location.origin;
var $ = function (i) {
    return document.getElementById(i);
  },
  send = function (o) {
    parent.postMessage(o, TARGET_ORIGIN);
  };
var RE = {
  email: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
  phone: /^0[35789]\d{8}$/,
  user: /^[A-Za-z0-9._-]{3,30}$/,
  name: /^[\p{L}\s]{2,100}$/u,
};
function normalizeName(s) {
  return String(s || "").replace(/\s+/g, " ").trim();
}
function tab(t) {
  ["login", "register", "forgot"].forEach(function (k) {
    $("f-" + k).className = k == t ? "on" : "";
  });
  $("tabs").style.display = t == "forgot" ? "none" : "flex";
  [].forEach.call($("tabs").children, function (b) {
    var active = b.dataset.t == t;
    b.className = active ? "on" : "";
    b.setAttribute("aria-selected", active ? "true" : "false");
  });
  [].forEach.call(document.querySelectorAll(".msg"), function (m) {
    m.textContent = "";
    m.className = "msg";
  });
}
function fld(id, bad, txt) {
  $(id).className = bad ? "bad" : "";
  var e = $("e-" + id);
  if (e) e.textContent = bad ? txt : "";
  return !bad;
}
function msg(op, ok, t) {
  var m = $("m-" + op);
  m.className = "msg " + (ok ? "o" : "e");
  m.textContent = t;
}
document.addEventListener("click", function (e) {
  var tEl = e.target.closest("[data-t]");
  if (tEl && tEl.dataset.t) tab(tEl.dataset.t);
  var eyeEl = e.target.closest("[data-eye]");
  if (eyeEl && eyeEl.dataset.eye) {
    var i = $(eyeEl.dataset.eye);
    if (i) {
      var show = i.type == "password";
      i.type = show ? "text" : "password";
      eyeEl.setAttribute("aria-pressed", show ? "true" : "false");
      eyeEl.classList.toggle("is-open", show);
      var svg = eyeEl.querySelector("svg");
      if (svg) {
        svg.innerHTML = show
          ? '<path d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" stroke-linecap="round" stroke-linejoin="round" />'
          : '<path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" stroke-linecap="round" stroke-linejoin="round" /><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" stroke-linecap="round" stroke-linejoin="round" />';
      }
    }
  }
  var socEl = e.target.closest("[data-soc]");
  if (socEl && socEl.dataset.soc) {
    msg("login", false, "Đăng nhập bằng " + socEl.dataset.soc + " chưa được hỗ trợ. Vui lòng dùng tên đăng nhập và mật khẩu.");
  }
  var quickEl = e.target.closest("[data-quick-user]");
  if (quickEl && quickEl.dataset.quickUser) {
    if ($("l-email")) $("l-email").value = quickEl.dataset.quickUser;
    if ($("l-pw")) $("l-pw").value = quickEl.dataset.quickPass || "";
    fld("l-email", false, "");
    fld("l-pw", false, "");
    var loginForm = $("f-login");
    if (loginForm) {
      if (typeof loginForm.requestSubmit === "function") loginForm.requestSubmit();
      else loginForm.dispatchEvent(new Event("submit", { cancelable: true }));
    }
  }
  if (e.target.closest("#home")) {
    if (window.parent && window.parent !== window) send({ skyGo: "home" });
    else window.location.href = "trang-chu.html";
  }
});
$("f-login").onsubmit = function (e) {
  e.preventDefault();
  var em = $("l-email").value.trim(),
    pw = $("l-pw").value;
  var a = fld(
      "l-email",
      em.length < 3,
      "Vui lòng nhập tên đăng nhập",
    ),
    b = fld("l-pw", pw.length < 1, "Vui lòng nhập mật khẩu");
  if (a && b)
    send({
      skyAuth: { op: "login", email: em, pw: pw, remember: $("l-rem").checked },
    });
};
$("r-pw").oninput = function () {
  var p = this.value,
    s = 0;
  if (p.length >= 6) s++;
  if (p.length >= 10) s++;
  if (/[A-Z]/.test(p) && /[a-z]/.test(p)) s++;
  if (/\d/.test(p) && /[^A-Za-z0-9]/.test(p)) s++;
  var L = ["", "Yếu", "Trung bình", "Khá", "Mạnh"],
    C = ["", "#d93a4a", "#d9b87a", "#b08a4f", "#1a9a5a"];
  if (!p) s = 0;
  $("sb").parentElement.classList.toggle("on", Boolean(p));
  $("sb").style.width = s * 25 + "%";
  $("sb").style.background = C[s];
  $("sl").textContent = p ? "Độ mạnh: " + L[s] : "";
};
$("f-register").onsubmit = function (e) {
  e.preventDefault();
  var n = normalizeName($("r-name").value),
    un = $("r-user").value.trim(),
    em = $("r-email").value.trim(),
    ph = $("r-phone").value.trim(),
    p1 = $("r-pw").value,
    p2 = $("r-pw2").value,
    tm = $("r-tm").checked;
  var nameBad = !n || n.length < 2 || !RE.name.test(n);
  var nameMsg = n.length < 2
    ? "Vui lòng nhập họ và tên"
    : "Họ và tên chỉ được chứa chữ cái và khoảng trắng, không chứa số hoặc ký tự đặc biệt";
  var ok = [
    fld("r-name", nameBad, nameMsg),
    fld(
      "r-user",
      !RE.user.test(un),
      "Tên đăng nhập 3–30 ký tự: chữ, số, dấu . _ - (không dấu cách)",
    ),
    fld("r-email", !RE.email.test(em), "Email không hợp lệ"),
    fld(
      "r-phone",
      !RE.phone.test(ph),
      "Số điện thoại gồm 10 số, đầu số 03, 05, 07, 08 hoặc 09",
    ),
    fld("r-pw", p1.length < 6, "Mật khẩu tối thiểu 6 ký tự"),
    fld("r-pw2", p1 !== p2, "Mật khẩu nhập lại không khớp"),
  ];
  $("e-r-tm").textContent = tm ? "" : "Bạn cần đồng ý với điều khoản";
  if (ok.every(Boolean) && tm)
    send({
      skyAuth: { op: "register", name: n, username: un, email: em, phone: ph, pw: p1 },
    });
};
/* ---- Quên mật khẩu: gửi OTP -> xác nhận OTP -> đặt mật khẩu mới ---- */
var FG = { step: 1, email: "", token: "", timer: null };
function fgStep(n) {
  FG.step = n;
  [1, 2, 3].forEach(function (i) {
    $("fg-" + i).style.display = i == n ? "block" : "none";
  });
  $("fg-sub").textContent =
    n == 1
      ? "Nhập email đã đăng ký, chúng tôi sẽ gửi mã xác nhận (OTP) qua email."
      : n == 2
        ? "Mã OTP đã được gửi tới " + FG.email + ". Nhập mã để tiếp tục."
        : "Xác minh thành công. Hãy đặt mật khẩu mới cho tài khoản của bạn.";
}
function fgBusy(id, on, txt) {
  var b = $(id);
  if (!b) return;
  if (on) {
    b.dataset.t0 = b.textContent;
    b.textContent = txt;
  } else if (b.dataset.t0) b.textContent = b.dataset.t0;
  b.disabled = on;
}
function fgCooldown(sec) {
  var a = $("fg-resend");
  clearInterval(FG.timer);
  a.style.pointerEvents = "none";
  a.style.opacity = ".55";
  a.textContent = "Gửi lại mã (" + sec + "s)";
  FG.timer = setInterval(function () {
    sec--;
    if (sec <= 0) {
      clearInterval(FG.timer);
      a.style.pointerEvents = "";
      a.style.opacity = "";
      a.textContent = "Gửi lại mã";
    } else a.textContent = "Gửi lại mã (" + sec + "s)";
  }, 1000);
}
function fgSend(btnId) {
  msg("forgot", true, "Đang gửi mã xác nhận, vui lòng chờ...");
  fgBusy(btnId, true, "Đang gửi...");
  HotelAPI.forgotSendOtp(FG.email)
    .then(function (r) {
      fgBusy(btnId, false);
      msg("forgot", true, r.message || "Đã gửi mã xác nhận tới email của bạn.");
      fgStep(2);
      fgCooldown(60);
    })
    .catch(function (e) {
      fgBusy(btnId, false);
      msg("forgot", false, e.message);
    });
}
$("fg-resend").onclick = function () {
  if (FG.email) fgSend("fg-btn2");
};
$("f-forgot").onsubmit = function (e) {
  e.preventDefault();
  if (FG.step == 1) {
    var em = $("f-email").value.trim();
    if (!fld("f-email", !RE.email.test(em), "Email không hợp lệ")) return;
    FG.email = em;
    fgSend("fg-btn1");
  } else if (FG.step == 2) {
    var otp = $("f-otp").value.trim();
    if (!fld("f-otp", !/^\d{6}$/.test(otp), "Mã OTP gồm đúng 6 chữ số")) return;
    msg("forgot", true, "Đang xác minh mã OTP...");
    fgBusy("fg-btn2", true, "Đang kiểm tra...");
    HotelAPI.forgotVerifyOtp(FG.email, otp)
      .then(function (r) {
        fgBusy("fg-btn2", false);
        FG.token = r.resetToken;
        msg("forgot", true, r.message || "Xác minh thành công.");
        fgStep(3);
      })
      .catch(function (er) {
        fgBusy("fg-btn2", false);
        msg("forgot", false, er.message);
      });
  } else {
    var p1 = $("f-pw").value,
      p2 = $("f-pw2").value;
    var ok = [
      fld("f-pw", p1.length < 6, "Mật khẩu tối thiểu 6 ký tự"),
      fld("f-pw2", p1 !== p2, "Mật khẩu nhập lại không khớp"),
    ];
    if (!ok.every(Boolean)) return;
    fgBusy("fg-btn3", true, "Đang lưu...");
    HotelAPI.forgotReset(FG.email, FG.token, p1, p2)
      .then(function (r) {
        fgBusy("fg-btn3", false);
        FG = { step: 1, email: "", token: "", timer: FG.timer };
        $("f-forgot").reset();
        fgStep(1);
        tab("login");
        msg("login", true, (r && r.message) || "Đổi mật khẩu thành công. Hãy đăng nhập bằng mật khẩu mới.");
      })
      .catch(function (er) {
        fgBusy("fg-btn3", false);
        msg("forgot", false, er.message);
      });
  }
};
window.addEventListener("message", function (e) {
  if (window.location.protocol !== "file:" && e.origin !== window.location.origin) return;
  if (e.source !== parent) return;
  var d = e.data || {};
  if (d.skyAuthRes)
    msg(
      d.skyAuthRes.op == "social" ? "login" : d.skyAuthRes.op,
      d.skyAuthRes.ok,
      d.skyAuthRes.msg,
    );
  if (d.skyTab) tab(d.skyTab);
  if (d.skyInit && d.skyInit.tab) tab(d.skyInit.tab);
  if (d.skyInit && d.skyInit.notice) {
    var n = $("notice");
    n.textContent = d.skyInit.notice;
    n.style.display = "block";
  }
});
