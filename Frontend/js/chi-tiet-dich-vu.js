/* Trang chi tiết dịch vụ SKY HOTEL: đọc mã dịch vụ trên địa chỉ (#nha-hang, #spa, #fitness, #hoi-nghi) rồi hiển thị nội dung */
(function () {
  var IMG = "../images/hotels/";
  var S = {
    "nha-hang": {
      icon: "⚜", name: "Nhà hàng & Bar", short: "Hương vị tinh hoa từ ẩm thực Á - Âu đặc sắc.",
      eyebrow: "NHÀ HÀNG & BAR", tagline: "Ẩm thực Á - Âu tinh tế bên khung cảnh toàn thành phố từ trên cao.",
      hero: "ChiNhanhThuDuc.jpg", thumb: "ChiNhanhThuDuc.jpg",
      introTitle: "Hành trình vị giác trên tầng cao",
      intro: "Nhà hàng Sky Dining nằm trên tầng cao của SKY HOTEL với những ô cửa kính từ sàn đến trần, nhìn ra dòng sông Sài Gòn và những tòa nhà biểu tượng. Thực đơn kết hợp tinh hoa ẩm thực Việt, châu Á và châu Âu do đội ngũ bếp trưởng nhiều năm kinh nghiệm xây dựng, ưu tiên nguyên liệu tươi theo mùa từ các nhà cung cấp địa phương. Khi đêm xuống, Skybar lên đèn với quầy cocktail thủ công và nhạc sống nhẹ nhàng.",
      info: [["Giờ phục vụ", "06:00 - 23:00"], ["Skybar", "17:00 - 01:00"], ["Vị trí", "Tầng 28 - 29"], ["Sức chứa", "120 khách"], ["Trang phục", "Lịch sự, gọn gàng"], ["Đặt bàn", "2800"]],
      hl: [
        ["🍽", "Thực đơn Á - Âu", "Hơn 80 món từ phở bò Wagyu, cá hồi nướng miso đến bít tết thượng hạng và pasta thủ công."],
        ["🌆", "Tầm nhìn 270°", "Ngắm toàn cảnh thành phố từ ô cửa kính lớn, đẹp nhất lúc hoàng hôn."],
        ["🍸", "Skybar & Cocktail", "Cocktail signature pha chế từ nguyên liệu Việt như bưởi, sả, cà phê và trà sen."],
        ["🥐", "Buffet sáng phong phú", "Quầy bánh Âu, món Việt nấu tại chỗ, nước ép tươi và quầy cà phê pha máy."],
        ["🍷", "Hầm rượu vang", "Hơn 200 nhãn rượu vang chọn lọc, có sommelier tư vấn kết hợp món."],
        ["🕯", "Bữa tối riêng tư", "Bàn đôi lãng mạn, thực đơn degustation theo yêu cầu cho dịp kỷ niệm."]
      ],
      gallery: ["ChiNhanhThuDuc.jpg", "login.jpg", "view.jpg"],
      menuEy: "THỰC ĐƠN NỔI BẬT", menuTitle: "Món được yêu thích",
      menu: [
        ["Bò Wagyu áp chảo", "Khoai tây nghiền, sốt vang đỏ", "890.000đ"],
        ["Cá hồi nướng miso", "Rau củ nướng, sốt yuzu", "490.000đ"],
        ["Phở bò Sky", "Nước dùng hầm 12 giờ, bò tái", "220.000đ"],
        ["Pasta tôm hùm", "Sốt kem cà chua, húng quế", "620.000đ"],
        ["Sky Sunset (cocktail)", "Rum, bưởi hồng, sả, tonic", "230.000đ"],
        ["Bánh flan cà phê", "Kem vani, caramel muối", "120.000đ"]
      ],
      cta: ["Đặt bàn cùng kỳ nghỉ của bạn", "Khách lưu trú được ưu đãi 15% thực đơn tối và miễn phí buffet sáng với gói phòng bao gồm bữa sáng."]
    },
    spa: {
      icon: "✿", name: "Spa & Wellness", short: "Thư giãn và tái tạo năng lượng cùng liệu trình chuyên nghiệp.",
      eyebrow: "SPA & WELLNESS", tagline: "Không gian yên tĩnh giữa lòng thành phố, nơi cơ thể và tâm trí được nghỉ ngơi trọn vẹn.",
      hero: "garden.jpg", thumb: "garden.jpg",
      introTitle: "Chốn dưỡng sinh giữa phố thị",
      intro: "Sky Spa là khu chăm sóc sức khỏe nằm trên sân thượng xanh mát của khách sạn, với phòng trị liệu riêng tư, bồn ngâm thảo dược và khu thư giãn ngoài trời. Các liệu trình lấy cảm hứng từ y học cổ truyền phương Đông kết hợp kỹ thuật trị liệu hiện đại, sử dụng tinh dầu thiên nhiên từ gừng, sả, hoa nhài và trà xanh. Mỗi khách đều được chuyên viên tư vấn riêng để chọn liệu trình phù hợp thể trạng.",
      info: [["Giờ mở cửa", "09:00 - 22:00"], ["Vị trí", "Tầng thượng"], ["Phòng trị liệu", "8 phòng, 2 phòng đôi"], ["Đặt lịch", "Trước ít nhất 2 giờ"], ["Độ tuổi", "Từ 16 tuổi"], ["Đặt bàn", "2900"]],
      hl: [
        ["💆", "Massage thảo dược", "Kết hợp bấm huyệt và túi chườm thảo dược ấm giúp giảm căng cơ, cải thiện giấc ngủ."],
        ["🌿", "Tinh dầu thiên nhiên", "Dùng tinh dầu sả, gừng, hoa nhài, không hương liệu nhân tạo."],
        ["🛁", "Bồn ngâm & xông hơi", "Bồn ngâm thảo dược, phòng xông hơi khô và ướt, miễn phí cho khách đặt liệu trình."],
        ["🌃", "Khu thư giãn sân thượng", "Ghế dài giữa vườn cây, ngắm đèn thành phố khi hoàng hôn."],
        ["💑", "Phòng trị liệu đôi", "Không gian riêng cho cặp đôi, kèm trà hoa và trái cây tươi."],
        ["🧖", "Chăm sóc da mặt", "Liệu trình làm sáng da, cấp ẩm sâu, phù hợp du khách bay đường dài."]
      ],
      gallery: ["garden.jpg", "ChiNhanhQ1.jpg", "ChiNhanhQ10.jpg"],
      menuEy: "LIỆU TRÌNH", menuTitle: "Gói chăm sóc phổ biến",
      menu: [
        ["Sky Signature Massage", "90 phút, tinh dầu và đá nóng", "1.450.000đ"],
        ["Thảo dược cổ truyền", "60 phút, túi chườm thảo dược", "980.000đ"],
        ["Chăm sóc da mặt", "75 phút, làm sạch và cấp ẩm", "1.150.000đ"],
        ["Tẩy tế bào chết toàn thân", "45 phút, muối biển và sả", "790.000đ"],
        ["Gói cặp đôi", "120 phút, massage và bồn ngâm", "3.200.000đ"],
        ["Gói giải tỏa jet lag", "60 phút, vai gáy và chân", "850.000đ"]
      ],
      cta: ["Thư giãn trọn vẹn trong kỳ nghỉ", "Khách lưu trú được giảm 10% liệu trình spa và ưu tiên đặt lịch."]
    },
    fitness: {
      icon: "❖", name: "Fitness & Gym", short: "Trang thiết bị hiện đại, không gian thoáng đãng.",
      eyebrow: "FITNESS & GYM", tagline: "Phòng tập thoáng đãng với tầm nhìn thành phố, mở cửa gần như suốt ngày.",
      hero: "view.jpg", thumb: "view.jpg",
      introTitle: "Giữ nhịp tập luyện mọi lúc",
      intro: "Sky Fitness trang bị máy tập cardio và tạ hiện đại từ các thương hiệu hàng đầu, bố trí quanh cửa kính lớn để bạn vừa tập vừa ngắm thành phố. Khu tập chức năng, phòng yoga và hồ bơi vô cực giúp bạn linh hoạt giữa nhiều hình thức vận động. Huấn luyện viên cá nhân có chứng chỉ quốc tế sẵn sàng thiết kế giáo án theo mục tiêu và thời gian lưu trú của bạn.",
      info: [["Giờ mở cửa", "05:00 - 23:00"], ["Vị trí", "Tầng 5"], ["Hồ bơi", "06:00 - 21:00"], ["Lớp yoga", "06:30 và 17:30 hằng ngày"], ["Khăn & nước", "Miễn phí"], ["Đặt bàn", "2950"]],
      hl: [
        ["🏋", "Thiết bị cao cấp", "Máy chạy bộ, xe đạp, khung tạ tự do và máy tập đa năng đời mới."],
        ["🧘", "Lớp yoga buổi sáng", "Lớp nhỏ tối đa 10 người, phù hợp mọi trình độ, có thảm sẵn."],
        ["🏊", "Hồ bơi vô cực", "Hồ bơi ngoài trời nhìn ra thành phố, nước ấm nhẹ vào buổi tối."],
        ["🥗", "Quầy nước ép & protein", "Smoothie, nước ép và bữa nhẹ giàu đạm sau buổi tập."],
        ["⏱", "Huấn luyện cá nhân", "Giáo án riêng 1 kèm 1, đo chỉ số cơ thể trước và sau."],
        ["🚿", "Phòng thay đồ & sauna", "Tủ khóa, vòi sen, sauna khô, đầy đủ đồ dùng cá nhân."]
      ],
      gallery: ["view.jpg", "skyline.jpg", "login.jpg"],
      menuEy: "GÓI & LỚP HỌC", menuTitle: "Lựa chọn cho kỳ nghỉ",
      menu: [
        ["Vào cửa phòng gym", "Miễn phí cho khách lưu trú", "Miễn phí"],
        ["Lớp yoga buổi sáng", "60 phút, mọi trình độ", "250.000đ"],
        ["Huấn luyện cá nhân", "60 phút, 1 kèm 1", "700.000đ"],
        ["Gói 3 buổi PT", "Giáo án theo mục tiêu", "1.900.000đ"],
        ["Khách vãng lai (ngày)", "Gym, hồ bơi, sauna", "450.000đ"],
        ["Lớp bơi cho trẻ", "45 phút, có huấn luyện viên", "400.000đ"]
      ],
      cta: ["Giữ thói quen tập luyện khi đi xa", "Khách lưu trú được sử dụng phòng gym, hồ bơi và sauna miễn phí."]
    },
    "hoi-nghi": {
      icon: "▤", name: "Hội nghị & Sự kiện", short: "Không gian linh hoạt cho mọi buổi họp và sự kiện.",
      eyebrow: "HỘI NGHỊ & SỰ KIỆN", tagline: "Từ buổi họp 10 người đến tiệc cưới 300 khách, mọi chi tiết đều được lo chu toàn.",
      hero: "lobby.jpg", thumb: "lobby.jpg",
      introTitle: "Nơi mọi sự kiện đáng nhớ",
      intro: "SKY HOTEL có hệ thống phòng đa năng với sảnh tiền sảnh rộng, trần cao và ánh sáng tự nhiên, có thể chia nhỏ hoặc ghép lớn tùy quy mô. Đội ngũ tổ chức sự kiện hỗ trợ từ ý tưởng, trang trí, âm thanh ánh sáng đến thực đơn riêng, giúp hội nghị, tiệc cưới, lễ ra mắt hay tiệc công ty diễn ra suôn sẻ. Khách lưu trú tham dự được ưu tiên giá phòng đặc biệt.",
      info: [["Sức chứa", "10 - 300 khách"], ["Số phòng", "5 phòng đa năng"], ["Diện tích lớn nhất", "420 m²"], ["Vị trí", "Tầng 3 và sảnh"], ["Liên hệ", "events@skyhotel.vn"], ["Đặt bàn", "3000"]],
      hl: [
        ["🎤", "Âm thanh ánh sáng chuẩn", "Màn hình LED, micro không dây, hệ thống ánh sáng sân khấu sẵn có."],
        ["📶", "Wi-Fi tốc độ cao", "Đường truyền riêng cho hội nghị, hỗ trợ họp trực tuyến và livestream."],
        ["🍰", "Tiệc trà & set menu", "Coffee break, buffet trưa, tiệc đứng và set menu theo ngân sách."],
        ["💍", "Tiệc cưới & sinh nhật", "Trang trí theo chủ đề, sân khấu và bàn tiệc bố trí linh hoạt."],
        ["🧑‍💼", "Điều phối viên riêng", "Một đầu mối phụ trách từ lúc báo giá đến khi kết thúc sự kiện."],
        ["🛏", "Ưu đãi phòng cho đoàn", "Giá phòng riêng cho khách tham dự, hỗ trợ đón tiễn sân bay."]
      ],
      gallery: ["lobby.jpg", "ChiNhanhQ10.jpg", "skyline.jpg"],
      menuEy: "PHÒNG & GÓI", menuTitle: "Không gian tổ chức",
      menu: [
        ["Sky Ballroom", "Tiệc, cưới, gala, đến 300 khách", "Từ 45.000.000đ"],
        ["Phòng Lotus", "Hội nghị, đến 120 khách", "Từ 18.000.000đ"],
        ["Phòng Orchid", "Họp, đào tạo, đến 50 khách", "Từ 9.000.000đ"],
        ["Phòng họp Jasmine", "Họp nhóm 10 - 20 người", "Từ 3.500.000đ"],
        ["Gói coffee break", "2 lần, trà, cà phê, bánh", "180.000đ/khách"],
        ["Gói buffet trưa", "Món Á - Âu, đồ uống", "450.000đ/khách"]
      ],
      cta: ["Lên kế hoạch cho sự kiện của bạn", "Gửi nhu cầu để nhận báo giá trong 24 giờ. Khách lưu trú được ưu đãi riêng."]
    }
  };
  var ORDER = ["nha-hang", "spa", "fitness", "hoi-nghi"];
  var $ = function (i) { return document.getElementById(i); };
  function esc(s) { return String(s).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;"); }
  /* Ảnh riêng đặt trong images/services/ (vd: spa-hero.jpg, spa-1.jpg). Chưa có thì tự dùng ảnh cũ trong images/hotels/ */
  function bg(f, key, n) {
    var own = key ? "url(../images/services/" + key + "-" + n + ".jpg), " : "";
    return own + "url(" + IMG + f + ")";
  }

  function render(key) {
    var v = S[key] || S[ORDER[0]];
    key = S[key] ? key : ORDER[0];
    document.title = "SKY HOTEL - " + v.name;
    $("hero-image").src = "../images/services/" + key + "-hero.jpg";
    $("hero-image").alt = "Không gian " + v.name + " tại SKY HOTEL";
    $("eyebrow").textContent = v.eyebrow;
    $("title").textContent = v.name;
    $("tagline").textContent = v.tagline;
    $("introTitle").textContent = v.introTitle;
    $("intro").textContent = v.intro;
    $("info").innerHTML = v.info.map(function (r) { return "<div><small>" + esc(r[0]) + "</small><b>" + esc(r[1]) + "</b></div>"; }).join("");
    $("highlights").innerHTML = v.hl.map(function (h) { return "<article><i>" + h[0] + "</i><h3>" + esc(h[1]) + "</h3><p>" + esc(h[2]) + "</p></article>"; }).join("");
    $("gallery").innerHTML = v.gallery.map(function (f, i) {
      var src = "../images/services/" + key + "-" + (i + 1) + ".jpg";
      return '<div><img src="' + esc(src) + '" alt="' + esc(v.name) + " tại SKY HOTEL, ảnh " + (i + 1) + '" loading="lazy"></div>';
    }).join("");
    $("menuEy").textContent = v.menuEy;
    $("menuTitle").textContent = v.menuTitle;
    $("menu").innerHTML = v.menu.map(function (m) { return "<article><div><h3>" + esc(m[0]) + "</h3><p>" + esc(m[1]) + "</p></div><b>" + esc(m[2]) + "</b></article>"; }).join("");
    $("ctaTitle").textContent = v.cta[0];
    $("ctaText").textContent = v.cta[1];
    $("more").innerHTML = ORDER.filter(function (k) { return k != key; }).map(function (k) {
      var o = S[k];
      return '<article data-svc="' + k + '"><div class="w"><div class="im"><img src="../images/services/' + esc(k) + '-hero.jpg" alt="' + esc(o.name) + ' tại SKY HOTEL" loading="lazy"></div></div><div class="bd"><h3>' + o.icon + " " + esc(o.name) + "</h3><small>" + esc(o.short) + "</small></div></article>";
    }).join("");
    window.scrollTo(0, 0);
  }

  function cur() { return (location.hash || "").replace("#", ""); }
  window.addEventListener("hashchange", function () { render(cur()); });
  document.addEventListener("click", function (e) {
    var m = e.target.closest("[data-svc]");
    if (m) { location.hash = m.dataset.svc; return; }
    if (e.target.closest("#back")) parent.postMessage({ skyGo: "home", skySec: "dich-vu" }, "*");
    var g = e.target.closest("[data-go]");
    if (g) parent.postMessage({ skyGo: g.dataset.go }, "*");
  });
  /* nhận mã dịch vụ từ khung website (index.html) */
  window.addEventListener("message", function (e) {
    var d = e.data || {};
    if (d.skyService && S[d.skyService]) { location.hash = d.skyService; render(d.skyService); }
  });
  render(cur());
})();
