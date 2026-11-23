/* ==========================================================================
   SkyHotel — Nghiệp vụ Lễ Tân & Quản Lý Lưu Trú (dashboard-le-tan.js)
   Đồng bộ toàn bộ chức năng từ letan.html với giao diện chủ đề SkyHotel
   ========================================================================== */

// Dùng chung HotelAPI.base
const API_BASE = (window.HotelAPI && window.HotelAPI.base) ? window.HotelAPI.base : '/Backend/public/index.php';

/* ==========================================================================
   LỚP CHUYỂN ĐỔI (ADAPTER) GIỮA GIAO DIỆN NÀY VÀ BACKEND HIỆN TẠI
   --------------------------------------------------------------------------
   Giao diện được viết theo định dạng dữ liệu khác (MaPNP, TenKhachHang, tongQuan...)
   trong khi Backend trả về (MaPhieu, TenKH, kpi, loaiPhong...) và nhận tham số
   camelCase (maPhieu, maPhong, maLeTan...). Không sửa Backend -> chuyển đổi tại đây.
   ========================================================================== */
const LT_ROOM_ST = { DangLuuTru: 'DangLuuTru', DaDatPhong: 'DaDat', DaDat: 'DaDat', DaTraPhong: 'DaTraPhong', DaHuy: 'DaHuy', ThanhToanThatBai: 'DaHuy', ChoThanhToan: 'DaDat' };

function ltUnwrap(j) {
  if (window.HotelAPI && typeof window.HotelAPI.unwrap === 'function') {
    return window.HotelAPI.unwrap(j);
  }
  let data = j ? j.data : null;
  let message = (j && (j.message || j.error)) || '';
  let guard = 0;
  while (data && typeof data === 'object' && !Array.isArray(data) && 'success' in data && 'data' in data && guard++ < 4) {
    if (data.message) message = data.message;
    if (data.success === false) return { ok: false, data: null, message: message };
    data = data.data;
  }
  return { ok: !!(j && j.success !== false && !j.error), data: data, message: message };
}

function ltNum(v) { const n = Number(v); return isNaN(n) ? 0 : n; }

/* "P203:DangLuuTru: | P206:DaHuy:Lý do có thể chứa ' | '" -> [{MaPhong, TrangThai, LyDo}] */
function ltParseRoomStates(str) {
  if (!str) return [];
  const parts = String(str).split(' | ');
  const out = [];
  parts.forEach(seg => {
    const m = /^([A-Za-z0-9_-]+):([A-Za-z]+):?(.*)$/.exec(seg.trim());
    if (m) out.push({ MaPhong: m[1], TrangThai: m[2], LyDo: m[3] || '' });
    else if (out.length) out[out.length - 1].LyDo += ' | ' + seg;
  });
  return out;
}

function ltMapSlip(s) {
  const states = ltParseRoomStates(s.PhongVaTrangThai);
  const rooms = s.DanhSachPhong ? String(s.DanhSachPhong).split(',').map(x => x.trim()).filter(Boolean) : states.map(x => x.MaPhong);
  return Object.assign({}, s, {
    MaPNP: s.MaPhieu,
    NgayDat: s.NgayLap,
    TenKhachHang: s.TenKH,
    DanhSachPhong: rooms,
    ChiTietPhongTrangThai: states.map(x => ({ MaPhong: x.MaPhong, TrangThaiPhong: LT_ROOM_ST[x.TrangThai] || x.TrangThai, LyDoHuy: x.LyDo })),
    NgayNhanPhong: s.NgayNhan,
    NgayTraPhong: s.NgayTra,
    SoLuongKhachKhaiBao: ltNum(s.TongSoKhach)
  });
}

function ltMapGuest(g, maPhieu) {
  return Object.assign({}, g, { CCCD: g.SoGiayTo || '', SDT: g.SDT || '', MaPNP: maPhieu || g.MaPhieu });
}

function ltMapRoomMap(d) {
  const k = d.kpi || {};
  const rooms = d.danhSachPhong || [];
  const mapRoom = r => {
    let st;
    if (r.TrangThaiPhong === 'BaoTri') st = 'BaoTri';
    else if (r.TrangThaiLuuTru === 'DangLuuTru') st = 'DangO';
    else if (r.TrangThaiLuuTru === 'DaDatPhong') st = 'DaDat';
    else if (r.TrangThaiLuuTru === 'ChoThanhToan') st = 'ChoThanhToan';
    else st = ({ DangSuDung: 'DangO', DaDat: 'DaDat', ChoThanhToan: 'ChoThanhToan', Trong: 'Trong', BaoTri: 'BaoTri' })[r.TrangThaiPhong] || 'Trong';
    return Object.assign({}, r, {
      TrangThaiHienThi: st,
      LichDatSapToi: Array.isArray(r.lichDatSapToi) ? r.lichDatSapToi : [],
      PhieuHienTai: r.MaPhieu ? {
        MaPNP: r.MaPhieu,
        TenKhachHang: r.TenNguoiDat,
        SDT: r.SDTNguoiDat,
        NgayNhanPhong: r.ThoiGianNhanPhong,
        NgayTraPhong: r.ThoiGianTraPhong,
        DanhSachKhachO: (r.danhSachNguoiLuuTru || []).map(g => ltMapGuest(g, r.MaPhieu))
      } : null
    });
  };
  return {
    success: true,
    boLocNgay: d.boLocNgay || null,
    tongQuan: { TongSoPhong: k.tongSoPhong || rooms.length, Trong: k.soPhongTrong || 0, DangO: k.soPhongDangO || 0, DaDat: k.soPhongDaDat || 0, ChoThanhToan: k.soPhongChoThanhToan || 0, BaoTri: k.soPhongBaoTri || 0 },
    danhSachLoaiPhong: (d.loaiPhong || []).map(lp => Object.assign({}, lp, {
      ThongKe: { TongSoPhong: lp.tongPhong || 0, Trong: lp.soPhongTrong || 0, DangO: lp.soPhongDangO || 0, DaDat: lp.soPhongDaDat || 0, ChoThanhToan: lp.soPhongChoThanhToan || 0, BaoTri: lp.soPhongBaoTri || 0 },
      DanhSachPhong: rooms.filter(r => r.MaLoaiPhong === lp.MaLoaiPhong).map(mapRoom)
    }))
  };
}

function ltMapService(s) {
  const sl = ltNum(s.SoLuong || s.soLuong || 1);
  const gia = ltNum(s.DonGia || s.donGia || s.Gia);
  return Object.assign({}, s, {
    TenDV: s.TenCTDV || s.tenCTDV || s.TenDV || s.tenDV || s.MaCTDV || '',
    MaDV: s.MaCTDV || s.maCTDV || s.MaDV,
    DonGia: gia,
    SoLuong: sl,
    ThanhTien: ltNum(s.ThanhTien || s.thanhTien) || gia * sl,
    ThoiGianSuDung: s.ThoiGianSuDung || s.thoiGianSuDung || s.ThoiGianNhan || s.thoiGianNhan || s.NgaySuDung || null
  });
}

function ltMapDetail(d) {
  const p = d.phieu || {};
  const rooms = d.danhSachPhong || [];
  const times = (key) => rooms.map(r => r[key]).filter(Boolean).sort();
  const paid = (d.danhSachThanhToan || []).filter(t => t.TrangThaiThanhToan === 'HoanTat').reduce((a, t) => a + ltNum(t.SoTien), 0);
  const firstIn = rooms.find(r => r.TenLeTanCheckin) || {};
  const firstOut = rooms.find(r => r.TenLeTanCheckout) || {};
  return Object.assign({}, p, {
    MaPNP: p.MaPhieu,
    TenKhachHang: p.TenKH,
    TenLeTanDat: p.TenLeTan || null,
    NgayNhanPhong: times('ThoiGianNhanPhong')[0] || null,
    NgayTraPhong: times('ThoiGianTraPhong').slice(-1)[0] || null,
    TenLeTanCheckIn: firstIn.TenLeTanCheckin || null,
    MaLTCheckIn: firstIn.MaLeTanCheckin || null,
    TenLeTanCheckOut: firstOut.TenLeTanCheckout || null,
    MaLTCheckOut: firstOut.MaLeTanCheckout || null,
    DanhSachChiTiet: rooms.map(r => Object.assign({}, r, {
      MaCTPNP: `${p.MaPhieu}-${r.MaPhong}`,
      DonGia: ltNum(r.Gia),
      TrangThaiPhong: LT_ROOM_ST[r.TrangThai] || r.TrangThai,
      TenLeTanCheckInPhong: r.TenLeTanCheckin,
      MaLTCheckInPhong: r.MaLeTanCheckin,
      TenLeTanCheckOutPhong: r.TenLeTanCheckout,
      MaLTCheckOutPhong: r.MaLeTanCheckout
    })),
    DanhSachNguoiLuuTru: (d.danhSachNguoiLuuTru || []).map(g => ltMapGuest(g, p.MaPhieu)),
    DanhSachDichVu: (d.danhSachDichVu || []).map(ltMapService),
    TongTienPhieu: d.hoaDon ? ltNum(d.hoaDon.TongTienSauVAT) : 0,
    DaThanhToan: paid,
    SoPhongDangHoatDong: rooms.filter(r => !['DaHuy', 'ThanhToanThatBai', 'DaTraPhong'].includes(r.TrangThai)).length,
    TrangThaiHienTai: p.TrangThai
  });
}

function ltMapCheckoutBill(b) {
  const rooms = b.danhSachChiTietPhong || [];
  const late = b.chiTietTreGio || [];
  const notes = late.map(x => `${x.maPhong}: ${x.ghiChu}`).filter(Boolean);
  if (ltNum(b.daThanhToan) > 0) notes.push(`Đã thanh toán trước ${ltNum(b.daThanhToan).toLocaleString('vi-VN')}đ, tổng chi phí ${ltNum(b.tongChiPhiToanBo).toLocaleString('vi-VN')}đ`);
  const now = new Date();
  const pad = n => String(n).padStart(2, '0');
  return Object.assign({}, b, {
    MaPNP: b.maPhieu,
    TenKhachHang: b.tenKH,
    MaKM: b.maKM,
    SoDemLuuTru: rooms.reduce((a, r) => Math.max(a, ltNum(r.soDem)), 0),
    NgayNhanDuKien: rooms.map(r => r.thoiGianNhan).filter(Boolean).sort()[0] || null,
    ThoiGianCheckIn: null,
    ThoiDiemTinhToan: `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())} ${pad(now.getHours())}:${pad(now.getMinutes())}:00`,
    DanhSachPhong: rooms.map(r => ({ MaPhong: r.maPhong, MaLoaiPhong: r.maLoaiPhong, TenLoaiPhong: r.tenLoaiPhong, DonGia: r.donGia, SoDem: r.soDem, ThanhTienPhong: r.thanhTien })),
    DanhSachDichVu: (b.danhSachDichVu || []).map(ltMapService),
    TongTienPhong: ltNum(b.tongTienPhongSauVAT),
    TongTienDichVu: ltNum(b.tongTienDichVuSauVAT),
    TienKhuyenMai: ltNum(b.tienGiamKM),
    PhuThuTraMuon: ltNum(b.tongPhuPhiTreGio),
    SoGioTraMuon: late.reduce((a, x) => Math.max(a, ltNum(x.soGioTre)), 0),
    GhiChuPhuThu: notes.join('; ') || 'Không trễ giờ',
    TongThanhToan: ltNum(b.conLai)
  });
}

function ltMapRefund(r, detail) {
  return Object.assign({}, detail || {}, {
    SoTienHoan: ltNum(r.soTienHoan),
    TyLeHoan: ltNum(r.tiLeHoan),
    PhiHuy: ltNum(r.phiHuy),
    ChinhSachApDung: r.moTaChinhSach || '',
    TienPhongMucTieu: ltNum(r.tongTienHuy),
    SoGioTruocCheckIn: Math.round(ltNum(r.diffDays) * 24),
    NgayNhanPhong: (detail && detail.NgayNhanPhong) || r.ngayNhan
  });
}

function ltRespond(obj) {
  return { ok: obj.success !== false, status: obj.success === false ? 400 : 200, json: async () => obj, text: async () => JSON.stringify(obj) };
}

async function ltCall(endpoint, method, params) {
  let url = `${API_BASE}/letan/${endpoint}`;
  const opt = { method: method, headers: { Accept: 'application/json' } };
  if (method === 'GET') {
    const q = new URLSearchParams();
    Object.keys(params || {}).forEach(k => { if (params[k] !== undefined && params[k] !== null && params[k] !== '') q.append(k, params[k]); });
    if (q.toString()) url += '?' + q.toString();
  } else {
    opt.headers['Content-Type'] = 'application/json';
    opt.body = JSON.stringify(params || {});
  }
  const res = await fetch(url, opt);
  const text = await res.text();
  let j = null;
  try { j = JSON.parse(text); } catch (e) { const i = text.indexOf('{'); if (i > -1) { try { j = JSON.parse(text.slice(i)); } catch (e2) {} } }
  if (!j) throw new Error('Phản hồi không hợp lệ từ máy chủ (HTTP ' + res.status + ')');
  return ltUnwrap(j);
}

/* Thay cho fetch() trong file này: chuyển tham số -> gọi Backend -> đổi dữ liệu về dạng giao diện cần */
async function leTanFetch(url, options) {
  options = options || {};
  const u = new URL(url, window.location.href);
  const endpoint = u.pathname.split('/letan/')[1] || '';
  const method = (options.method || 'GET').toUpperCase();
  let p = {};
  u.searchParams.forEach((v, k) => { p[k] = v; });
  if (options.body) { try { Object.assign(p, JSON.parse(options.body)); } catch (e) {} }

  const maPhieu = p.MaPNP || p.MaPhieu || p.maPhieu;
  const base = {};
  if (maPhieu) base.maPhieu = maPhieu;
  if (p.MaPhong || p.maPhong) base.maPhong = p.MaPhong || p.maPhong;
  if (p.MaLT || p.maLeTan) base.maLeTan = p.MaLT || p.maLeTan;

  const fail = (r, fallback) => ltRespond({ success: false, error: r.message || fallback, message: r.message || fallback });

  try {
    switch (endpoint) {
      case 'xemdsphieu': {
        const r = await ltCall('xemdsphieu', 'GET', {});
        if (!r.ok) return fail(r, 'Không thể tải danh sách phiếu');
        const items = Array.isArray(r.data) ? r.data : ((r.data && r.data.items) || []);
        return ltRespond({ success: true, data: items.map(ltMapSlip) });
      }
      case 'trangthaiphong': {
        const qp = {};
        if (p.tuNgay) qp.tuNgay = p.tuNgay;
        if (p.denNgay) qp.denNgay = p.denNgay;
        if (p.maLoaiPhong) qp.maLoaiPhong = p.maLoaiPhong;
        const r = await ltCall('trangthaiphong', 'GET', qp);
        if (!r.ok) return fail(r, 'Không thể tải sơ đồ phòng');
        return ltRespond(ltMapRoomMap(r.data || {}));
      }
      case 'xemctphieu': {
        const r = await ltCall('xemctphieu', 'POST', { maPhieu: maPhieu });
        if (!r.ok || !r.data) return fail(r, 'Không tìm thấy phiếu');
        return ltRespond({ success: true, data: ltMapDetail(r.data) });
      }
      case 'tinhtiencheckout': {
        const r = await ltCall('tinhtiencheckout', 'POST', base);
        if (!r.ok || !r.data) return fail(r, 'Không thể tính tiền Check-out');
        return ltRespond({ success: true, data: ltMapCheckoutBill(r.data) });
      }
      case 'tinhtienhoan': {
        const [r, dt] = await Promise.all([
          ltCall('tinhtienhoan', 'POST', base),
          ltCall('xemctphieu', 'POST', { maPhieu: maPhieu }).catch(() => null)
        ]);
        if (!r.ok || !r.data) return fail(r, 'Không thể tính tiền hoàn');
        return ltRespond({ success: true, data: ltMapRefund(r.data, dt && dt.ok && dt.data ? ltMapDetail(dt.data) : null) });
      }
      case 'checkin': {
        const r = await ltCall('checkin', 'POST', base);
        return r.ok ? ltRespond({ success: true, message: r.message, data: r.data }) : fail(r, 'Check-in thất bại');
      }
      case 'checkout': {
        const r = await ltCall('checkout', 'POST', Object.assign({}, base, { hinhThuc: p.PhuongThucThanhToan || p.hinhThuc || 'TienMat' }));
        return r.ok ? ltRespond({ success: true, message: r.message, data: r.data }) : fail(r, 'Check-out thất bại');
      }
      case 'huyphieu': {
        const r = await ltCall('huyphieu', 'POST', Object.assign({}, base, { lyDo: p.LyDoHuy || p.lyDo || '' }));
        if (!r.ok) return fail(r, 'Hủy phiếu thất bại');
        const h = (r.data && r.data.hoanTien) || {};
        return ltRespond({ success: true, message: r.message, data: { SoTienHoan: ltNum(h.soTienHoan), TyLeHoan: ltNum(h.tiLeHoan) } });
      }
      case 'capnhatbaotri': {
        const st = p.TrangThaiBaoTri || p.trangThai;
        const r = await ltCall('capnhatbaotri', 'POST', { maPhong: p.MaPhong || p.maPhong, trangThai: st === 'BaoTri' ? 'BaoTri' : 'Trong' });
        return r.ok ? ltRespond({ success: true, message: r.message }) : fail(r, 'Không thể cập nhật bảo trì');
      }
      case 'capnhatkhach': {
        const r = await ltCall('capnhatkhach', 'POST', {
          maPhieu: maPhieu || currentEditingMaPNP,
          maNguoiLuuTru: p.MaNguoiLuuTru,
          hoTen: p.HoTen,
          ngaySinh: p.NgaySinh,
          loaiGiayTo: p.LoaiGiayTo,
          soGiayTo: p.CCCD || p.SoGiayTo || ''
        });
        return r.ok ? ltRespond({ success: true, message: r.message }) : fail(r, 'Cập nhật thất bại');
      }
      case 'danhsachdichvu': {
        const r = await ltCall('danhsachdichvu', 'GET', {});
        if (!r.ok) return fail(r, 'Không thể tải danh mục dịch vụ');
        const groups = Array.isArray(r.data) ? r.data : [];
        const flat = [];
        groups.forEach(g => {
          const code = String(g.maDV || g.MaDV || '');
          const loai = /giat/i.test(code) ? 'DVGiatUi' : (/xe/i.test(code) ? 'DVThueXe' : (/an/i.test(code) ? 'DVDoAn' : code));
          (g.danhSach || []).forEach(it => flat.push({ MaDV: it.maCTDV, TenDV: it.tenCTDV, DonGia: ltNum(it.donGia), LoaiDV: loai, MoTa: it.moTa, SoLuongTon: it.soLuongTon }));
        });
        return ltRespond({ success: true, data: flat });
      }
      case 'datdichvunhanh': {
        const items = Array.isArray(p.DanhSachDichVu) ? p.DanhSachDichVu : [];
        const msgs = [];
        for (const it of items) {
          const r = await ltCall('datdichvunhanh', 'POST', {
            maPhieu: maPhieu,
            maPhong: p.MaPhong,
            maCTDV: it.MaDV,
            soLuong: it.SoLuong,
            thoiGianNhan: it.ThoiGianSuDung || null
          });
          if (!r.ok) return fail(r, 'Lỗi khi đặt dịch vụ ' + it.MaDV);
          if (r.message) msgs.push(r.message);
        }
        return ltRespond({ success: true, message: msgs[msgs.length - 1] || `Đã thêm ${items.length} dịch vụ cho phòng ${p.MaPhong}` });
      }
      default: {
        const r = await ltCall(endpoint, method, Object.assign({}, p, base));
        return r.ok ? ltRespond({ success: true, message: r.message, data: r.data }) : fail(r, 'Yêu cầu thất bại');
      }
    }
  } catch (err) {
    console.error('[leTanFetch]', endpoint, err);
    throw err;
  }
}

let allReceptions = [];
let currentFilterStatus = 'ALL';
let currentEditingMaPNP = null;
let currentCheckinMaPNP = null;
let currentCheckinMaPhong = null;
let currentCheckoutMaPNP = null;
let currentCheckoutMaPhong = null;
let currentCancelMaPNP = null;
let currentCancelMaPhong = null;
let currentCancelPreviewData = null;
let currentCancelDetailData = null;

// State cho Tab 2: Sơ đồ & Trạng thái loại phòng
let currentMainTab = 'bookings';
let roomStatusMapData = null;

window.startNewCounterBooking = function () {
  try {
    sessionStorage.removeItem('sky_booking_flow_state');
    localStorage.removeItem('sky_sel');
  } catch (e) {}
  if (window.parent && window.parent !== window) {
    window.parent.postMessage({ skyGo: 'rooms' }, (window.location.protocol === 'file:') ? '*' : window.location.origin);
  } else {
    window.location.href = 'chon-phong.html?mode=quay';
  }
};
let selectedRoomTypeCode = 'ALL';
let currentRoomSubFilter = 'ALL';
let roomFilterCheckIn = '';
let roomFilterCheckOut = '';

// State cho Đặt dịch vụ phòng nhanh tại quầy
let fastServiceCatalog = [];
let currentFastServiceCategory = 'DVDoAn';
let fastServiceCart = {}; // { MaDV: SoLuong }

let skyInitSessionUser = null;
let initialReceptionistUser = null;

window.addEventListener('message', function (e) {
  try {
    if (window.location.protocol !== 'file:' && e.origin !== window.location.origin) return;
    const d = e.data || {};
    if (d.skyInit && d.skyInit.user) {
      skyInitSessionUser = d.skyInit.user.be
        ? Object.assign({}, d.skyInit.user.be, { role: d.skyInit.user.role, MaLeTan: d.skyInit.user.maLeTan || d.skyInit.user.be.MaLeTan, MaTK: d.skyInit.user.maTK || d.skyInit.user.be.MaTK, HoTen: d.skyInit.user.name || d.skyInit.user.be.HoTen })
        : d.skyInit.user;
      const nameBadge = document.getElementById('receptionistNameText');
      if (nameBadge && nameBadge.innerText.includes('Chưa đăng nhập')) {
        nameBadge.innerText = 'Trực ca: ' + getLoggedReceptionistName();
      }
    }
  } catch (err) {}
});

function getLoggedReceptionistUser() {
  try {
    const liveUser = (window.HotelAPI && window.HotelAPI.session)
      ? window.HotelAPI.session.get()
      : JSON.parse(localStorage.getItem('hotel_user') || 'null');
    if (liveUser) return liveUser;
  } catch (e) {}

  try {
    if (window.parent && window.parent !== window && window.parent.user) {
      const pu = window.parent.user;
      return pu.be
        ? Object.assign({}, pu.be, { role: pu.role, MaLeTan: pu.maLeTan || pu.be.MaLeTan, MaTK: pu.maTK || pu.be.MaTK, HoTen: pu.name || pu.be.HoTen })
        : pu;
    }
  } catch (e) {}

  if (skyInitSessionUser) return skyInitSessionUser;
  return initialReceptionistUser;
}

// Lấy mã lễ tân đang đăng nhập (mặc định LT001 - Nguyễn Thị Phấn)
function getLoggedReceptionistMaLT() {
  if (window.SkyGuard) {
    var check = window.SkyGuard.checkAccess('rec');
    if (!check.allowed) {
      if (window.SkyUI) window.SkyUI.toast(check.message, 'error');
      if (window.parent && window.parent !== window) {
        window.parent.postMessage({ skyGo: 'login' }, window.location.origin);
      } else {
        window.location.href = 'dang-nhap.html';
      }
      return null;
    }
  }
  try {
    const u = getLoggedReceptionistUser();
    if (!u) return null;
    const role = String(u.VaiTro || u.vaiTro || u.role || '').trim().toLowerCase();
    if (role === 'letan' || role === 'lễ tân') {
      return u.MaLeTan || u.maLeTan || u.MaTK || u.maTK || 'LT001';
    }
    if (role === 'admin') {
      // Admin không có bản ghi riêng trong bảng LeTan; fallback 'LT001' để tránh lỗi khóa ngoại MaLeTanCheckin/Checkout
      return u.MaLeTan || u.maLeTan || 'LT001';
    }
    if ((u.MaLeTan || u.maLeTan) && role !== 'khachhang' && role !== 'customer') {
      return u.MaLeTan || u.maLeTan;
    }
  } catch (e) {}
  return null;
}

function getLoggedReceptionistName() {
  try {
    const u = getLoggedReceptionistUser();
    const name = u && (u.HoTen || u.name || u.TenDangNhap || u.username);
    const maTK = u && (u.MaTK || u.maTK);
    if (name) return `${name}${maTK ? ' (' + maTK + ')' : ''}`;
  } catch (e) {}
  return 'Chưa đăng nhập';
}

function formatMoney(val) {
  return Number(val || 0).toLocaleString('vi-VN') + ' đ';
}

function formatDateTime(str) {
  if (!str) return '<span style="color: var(--rec-muted);">Chưa ghi nhận</span>';
  const d = new Date(str.includes(' ') && !str.includes('T') ? str.replace(' ', 'T') : str);
  if (isNaN(d.getTime())) return str;
  const day = String(d.getDate()).padStart(2, '0');
  const month = String(d.getMonth() + 1).padStart(2, '0');
  const year = d.getFullYear();
  const hours = String(d.getHours()).padStart(2, '0');
  const minutes = String(d.getMinutes()).padStart(2, '0');
  return `${day}/${month}/${year} <strong style="color: var(--rec-gold-text);">${hours}:${minutes}</strong>`;
}

function showToast(msg, icon = '✅') {
  const box = document.getElementById('toastBox');
  if (!box) return;
  document.getElementById('toastIcon').innerText = icon;
  document.getElementById('toastText').innerText = msg;
  box.style.display = 'flex';
  clearTimeout(window._recToastTimer);
  window._recToastTimer = setTimeout(() => {
    box.style.display = 'none';
  }, 3800);
}

function closeModal(id) {
  const el = document.getElementById(id);
  if (el) el.classList.remove('show');
}

function closeModalOnOutside(e, id) {
  if (e.target && e.target.id === id) {
    closeModal(id);
  }
}

// =========================================================================
// CHUYỂN ĐỔI TAB CHÍNH & QUẢN LÝ SƠ ĐỒ PHÒNG (2 CẤP ĐỘ)
// =========================================================================
function switchMainTab(tabName) {
  currentMainTab = tabName;
  const tabBookings = document.getElementById('tabBookingsContent');
  const tabRooms = document.getElementById('tabRoomsContent');
  const btnBookings = document.getElementById('tabBtnBookings');
  const btnRooms = document.getElementById('tabBtnRooms');

  if (tabName === 'rooms') {
    tabBookings.style.display = 'none';
    tabRooms.style.display = 'block';
    btnBookings.classList.remove('active');
    btnRooms.classList.add('active');
    loadRoomStatusMap();
  } else {
    tabRooms.style.display = 'none';
    tabBookings.style.display = 'block';
    btnRooms.classList.remove('active');
    btnBookings.classList.add('active');
  }

  // Đồng bộ mục đang chọn ở thanh bên trái (thanh trong trang + thanh của khung index.html)
  const sideB = document.getElementById('sideTabBookings');
  const sideR = document.getElementById('sideTabRooms');
  if (sideB) sideB.classList.toggle('on', tabName !== 'rooms');
  if (sideR) sideR.classList.toggle('on', tabName === 'rooms');
  if (window.parent && window.parent !== window) {
    window.parent.postMessage({ skyRecTab: tabName === 'rooms' ? 'rooms' : 'bookings' }, '*');
  }
}

// Chọn tab từ thanh bên trái: qua #hash (khi mở trang) hoặc postMessage từ khung index.html
function applyMainTabFromHash() {
  const h = (window.location.hash || '').replace('#', '');
  if ((h === 'rooms' || h === 'bookings') && h !== currentMainTab) switchMainTab(h);
}
window.addEventListener('hashchange', applyMainTabFromHash);
window.addEventListener('message', (e) => {
  const d = e.data || {};
  const t = d.skyTab || d.skyHash;
  if ((t === 'rooms' || t === 'bookings') && t !== currentMainTab) switchMainTab(t);
});

function formatYMDLocal(d) {
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
}

function addDaysYMD(ymd, days) {
  const parts = String(ymd || '').split('-').map(Number);
  const dt = (parts.length === 3 && parts[0]) ? new Date(parts[0], parts[1] - 1, parts[2]) : new Date();
  dt.setDate(dt.getDate() + days);
  return formatYMDLocal(dt);
}

function diffNightsYMD(inStr, outStr) {
  const p1 = String(inStr || '').split('-').map(Number);
  const p2 = String(outStr || '').split('-').map(Number);
  if (p1.length !== 3 || p2.length !== 3) return 1;
  const d1 = new Date(p1[0], p1[1] - 1, p1[2]);
  const d2 = new Date(p2[0], p2[1] - 1, p2[2]);
  const diff = Math.round((d2.getTime() - d1.getTime()) / 86400000);
  return diff > 0 ? diff : 1;
}

function formatShortDateVi(str) {
  if (!str) return '--/--';
  const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(str));
  if (m) return `${m[3]}/${m[2]}`;
  const d = new Date(String(str).replace(' ', 'T'));
  if (isNaN(d.getTime())) return String(str);
  return `${String(d.getDate()).padStart(2, '0')}/${String(d.getMonth() + 1).padStart(2, '0')}`;
}

function formatShortPrice(val) {
  const n = Number(val || 0);
  if (n >= 1000000) {
    const tr = n / 1000000;
    return (Number.isInteger(tr) ? tr : tr.toFixed(1).replace(/\.0$/, '')) + 'tr';
  }
  if (n >= 1000) return Math.round(n / 1000) + 'k';
  return String(n);
}

function formatRoomTypeTitle(name) {
  return String(name || '').replace(/^Phong\b/i, 'Phòng');
}

function formatShortCategoryName(name) {
  return String(name || '').replace(/^(Phong|Phòng)\s+/i, '').trim() || name;
}

function initRoomDateFilters() {
  const inEl = document.getElementById('roomFilterCheckIn');
  const outEl = document.getElementById('roomFilterCheckOut');
  const today = formatYMDLocal(new Date());
  if (!roomFilterCheckIn) roomFilterCheckIn = (inEl && inEl.value) ? inEl.value : today;
  if (!roomFilterCheckOut || roomFilterCheckOut <= roomFilterCheckIn) {
    roomFilterCheckOut = (outEl && outEl.value && outEl.value > roomFilterCheckIn)
      ? outEl.value
      : addDaysYMD(roomFilterCheckIn, 1);
  }
  if (inEl) inEl.value = roomFilterCheckIn;
  if (outEl) outEl.value = roomFilterCheckOut;
  updateRoomDateSummaryAndPresets();
}

function updateRoomDateSummaryAndPresets() {
  const nights = diffNightsYMD(roomFilterCheckIn, roomFilterCheckOut);
  const badge = document.getElementById('roomDateSummaryBadge');
  if (badge) {
    badge.textContent = `${nights} đêm • ${formatShortDateVi(roomFilterCheckIn)} → ${formatShortDateVi(roomFilterCheckOut)}`;
  }
  const today = formatYMDLocal(new Date());
  document.querySelectorAll('.room-preset-btn').forEach(btn => {
    const n = Number(btn.getAttribute('data-nights') || 0);
    const isMatch = (roomFilterCheckIn === today && roomFilterCheckOut === addDaysYMD(today, n));
    btn.classList.toggle('active', isMatch);
  });
}

function onRoomDateInputChange() {
  const inEl = document.getElementById('roomFilterCheckIn');
  const outEl = document.getElementById('roomFilterCheckOut');
  const today = formatYMDLocal(new Date());
  roomFilterCheckIn = (inEl && inEl.value) ? inEl.value : today;
  let outVal = (outEl && outEl.value) ? outEl.value : addDaysYMD(roomFilterCheckIn, 1);
  if (outVal <= roomFilterCheckIn) {
    outVal = addDaysYMD(roomFilterCheckIn, 1);
    if (outEl) outEl.value = outVal;
  }
  roomFilterCheckOut = outVal;
  updateRoomDateSummaryAndPresets();
  loadRoomStatusMap();
}

function setRoomDatePreset(nights) {
  const today = formatYMDLocal(new Date());
  roomFilterCheckIn = today;
  roomFilterCheckOut = addDaysYMD(today, Math.max(1, Number(nights) || 1));
  const inEl = document.getElementById('roomFilterCheckIn');
  const outEl = document.getElementById('roomFilterCheckOut');
  if (inEl) inEl.value = roomFilterCheckIn;
  if (outEl) outEl.value = roomFilterCheckOut;
  updateRoomDateSummaryAndPresets();
  loadRoomStatusMap();
}

async function loadRoomStatusMap() {
  initRoomDateFilters();
  try {
    const qs = `tuNgay=${encodeURIComponent(roomFilterCheckIn)}&denNgay=${encodeURIComponent(roomFilterCheckOut)}`;
    const res = await leTanFetch(`${API_BASE}/letan/trangthaiphong?${qs}`);
    const json = await res.json();
    if (!json.success) {
      showToast(json.error || 'Không thể tải sơ đồ trạng thái phòng', '❌');
      return;
    }

    roomStatusMapData = json;

    // Cập nhật badge tổng số phòng trên nút Tab
    const badge = document.getElementById('tabRoomCountBadge');
    if (badge && json.tongQuan) {
      badge.innerText = `${json.tongQuan.TongSoPhong} phòng`;
    }

    renderRoomRack();
  } catch (err) {
    console.error(err);
    showToast('Lỗi kết nối máy chủ khi tải sơ đồ phòng', '❌');
  }
}

function selectRoomCategory(maLP) {
  selectedRoomTypeCode = maLP || 'ALL';
  renderRoomRack();
}

function setRoomSubFilter(statusKey, btnEl) {
  currentRoomSubFilter = statusKey;
  document.querySelectorAll('#roomSubFilterChips .filter-chip').forEach(b => b.classList.remove('active'));
  if (btnEl) btnEl.classList.add('active');
  renderRoomRack();
}

function updateRoomSubFilterChipCounts(tk, totalRooms) {
  const chipLabels = {
    ALL: `Tất cả (${totalRooms})`,
    Trong: `🟢 Trống (${tk.Trong || 0})`,
    DangO: `🔴 Đang ở (${tk.DangO || 0})`,
    DaDat: `🔵 Đã đặt (${tk.DaDat || 0})`,
    ChoThanhToan: `🟡 Chờ TT (${tk.ChoThanhToan || 0})`,
    BaoTri: `🛠️ Bảo trì (${tk.BaoTri || 0})`
  };
  document.querySelectorAll('#roomSubFilterChips .filter-chip').forEach(chip => {
    const key = chip.getAttribute('data-rstatus');
    if (chipLabels[key]) chip.textContent = chipLabels[key];
  });
}

function renderRoomRack() {
  if (!roomStatusMapData) return;
  const tq = roomStatusMapData.tongQuan || {};
  const listLP = roomStatusMapData.danhSachLoaiPhong || [];

  if (selectedRoomTypeCode !== 'ALL' && !listLP.some(lp => lp.MaLoaiPhong === selectedRoomTypeCode)) {
    selectedRoomTypeCode = 'ALL';
  }

  // 1. Render thanh lọc nhanh Hạng phòng (Category Pills)
  const pillsWrap = document.getElementById('roomCategoryPills');
  if (pillsWrap) {
    const allPill = `
      <button type="button" class="room-cat-pill ${selectedRoomTypeCode === 'ALL' ? 'active' : ''}" onclick="selectRoomCategory('ALL')">
        <span>Tất cả hạng phòng</span>
        <span class="room-cat-count">${tq.TongSoPhong || 0}</span>
      </button>
    `;
    const catPills = listLP.map(lp => {
      const tk = lp.ThongKe || {};
      const activeCls = (selectedRoomTypeCode === lp.MaLoaiPhong) ? 'active' : '';
      return `
        <button type="button" class="room-cat-pill ${activeCls}" onclick="selectRoomCategory('${lp.MaLoaiPhong}')" title="${formatRoomTypeTitle(lp.TenLoaiPhong)} (${lp.MaLoaiPhong})">
          <span>${formatShortCategoryName(lp.TenLoaiPhong)} • ${formatShortPrice(lp.Gia)}</span>
          <span class="room-cat-count">${tk.Trong || 0}/${tk.TongSoPhong || 0}</span>
        </button>
      `;
    }).join('');
    pillsWrap.innerHTML = allPill + catPills;
  }

  // 2. Cập nhật bộ đếm lọc trạng thái tương ứng với hạng phòng đang chọn
  const activeLP = selectedRoomTypeCode === 'ALL' ? null : listLP.find(x => x.MaLoaiPhong === selectedRoomTypeCode);
  const scopeStats = activeLP ? (activeLP.ThongKe || {}) : tq;
  const scopeTotal = activeLP
    ? (Array.isArray(activeLP.DanhSachPhong) ? activeLP.DanhSachPhong.length : (scopeStats.TongSoPhong || 0))
    : (tq.TongSoPhong || 0);
  updateRoomSubFilterChipCounts(scopeStats, scopeTotal);

  // 3. Lọc danh sách các nhóm hạng phòng và phòng bên trong
  const kw = ((document.getElementById('roomSearchInput') || {}).value || '').trim().toLowerCase();
  const targetGroups = activeLP ? [activeLP] : listLP;

  const ROOM_STATUS_LABELS = {
    Trong: 'Trống',
    DangO: 'Đang ở',
    DaDat: 'Đã đặt',
    ChoThanhToan: 'Chờ TT',
    BaoTri: 'Bảo trì'
  };

  const renderCardHtml = (r) => {
    const st = r.TrangThaiHienThi;
    const statusLabel = ROOM_STATUS_LABELS[st] || r.NhanTrangThai || st || 'Không rõ';
    const p = r.PhieuHienTai;
    const allUpcoming = Array.isArray(r.LichDatSapToi) ? r.LichDatSapToi : [];
    const nextBookings = p
      ? allUpcoming.filter(u => u.MaPhieu !== p.MaPNP)
      : allUpcoming;

    let upcomingHintHtml = '';
    if (nextBookings.length > 0) {
      const u0 = nextBookings[0];
      const extraCount = nextBookings.length > 1 ? ` <span class="rack-hint-more">+${nextBookings.length - 1}</span>` : '';
      const prefix = p ? 'Kế tiếp:' : 'Đặt:';
      upcomingHintHtml = `
        <div class="rack-hint-row rack-hint-booked" title="Phòng có ${nextBookings.length} lịch đặt sắp tới">
          <span>📅 ${prefix} <strong>${formatShortDateVi(u0.ThoiGianNhanPhong)} → ${formatShortDateVi(u0.ThoiGianTraPhong)}</strong></span>${extraCount}
        </div>
      `;
    } else if (st === 'Trong') {
      upcomingHintHtml = `<div class="rack-hint-row rack-hint-free">✓ Trống lịch sắp tới</div>`;
    } else if (st === 'BaoTri') {
      upcomingHintHtml = `<div class="rack-hint-row rack-hint-muted">Tạm khóa kỹ thuật / dọn phòng</div>`;
    } else {
      upcomingHintHtml = `<div class="rack-hint-row rack-hint-muted">✓ Không có lịch kế tiếp</div>`;
    }

    let bodyHtml = '';
    let actionsHtml = '';

    if (st === 'Trong') {
      bodyHtml = `
        <div class="rack-body-main">
          <div class="rack-state-title ok">✨ Sẵn sàng đón khách</div>
          <div class="rack-state-sub">Trống trong kỳ ${formatShortDateVi(roomFilterCheckIn)} → ${formatShortDateVi(roomFilterCheckOut)}</div>
        </div>
        ${upcomingHintHtml}
      `;
      actionsHtml = `
        <div class="pr-actions-subgrid">
          <button type="button" class="btn btn-xs btn-outline" onclick="openRoomScheduleModal('${r.MaPhong}')" title="Xem các khoảng ngày đã đặt sắp tới của phòng ${r.MaPhong}">📅 Xem lịch phòng</button>
          <button type="button" class="btn btn-xs btn-outline" onclick="toggleRoomMaintenance('${r.MaPhong}', 'BaoTri')" title="Chuyển sang trạng thái bảo trì / chờ dọn">🛠️ Bảo trì</button>
        </div>
      `;
    } else if (st === 'BaoTri') {
      bodyHtml = `
        <div class="rack-body-main">
          <div class="rack-state-title maint">🛠️ Đang tạm khóa bảo trì / dọn dẹp</div>
          <div class="rack-state-sub">Tự động loại trừ khi tìm phòng trống</div>
        </div>
        ${upcomingHintHtml}
      `;
      actionsHtml = `
        <div class="pr-actions-subgrid">
          <button type="button" class="btn btn-xs btn-outline" onclick="openRoomScheduleModal('${r.MaPhong}')" title="Xem lịch đặt sắp tới của phòng ${r.MaPhong}">📅 Xem lịch phòng</button>
          <button type="button" class="btn btn-xs btn-emerald" onclick="toggleRoomMaintenance('${r.MaPhong}', 'SanSang')">🔓 Mở lại phòng</button>
        </div>
      `;
    } else if (p) {
      bodyHtml = `
        <div class="rack-body-main">
          <div class="rack-slip-row">
            <strong class="rack-slip-link" onclick="jumpToBookingFromRoom('${p.MaPNP}')" title="Nhấn để chuyển sang Tiếp đón & Quản lý phiếu">#${p.MaPNP}</strong>
            <span class="rack-stay-span">📅 ${formatShortDateVi(p.NgayNhanPhong)} → ${formatShortDateVi(p.NgayTraPhong)}</span>
          </div>
          <div class="rack-guest-name" title="${p.TenKhachHang || 'Khách hàng'}">👤 ${p.TenKhachHang || 'Khách hàng'}</div>
          <div class="rack-guest-phone">📞 ${p.SDT || 'Chưa cập nhật'}</div>
        </div>
        ${upcomingHintHtml}
      `;

      actionsHtml = `
        <div class="pr-actions-subgrid">
          <button type="button" class="btn btn-xs btn-outline" onclick="openRoomScheduleModal('${r.MaPhong}')" title="Xem lịch đặt phòng ${r.MaPhong}">📅 Xem lịch phòng</button>
          <button type="button" class="btn btn-xs btn-outline" onclick="jumpToBookingFromRoom('${p.MaPNP}')" title="Mở phiếu #${p.MaPNP} bên Tiếp đón & Quản lý phiếu">📋 Phiếu #${p.MaPNP}</button>
        </div>
      `;
    }

    return `
      <div class="phys-room-card rack-card pr-${st}">
        <div class="pr-top">
          <div class="pr-number">
            <span>🚪</span> ${r.MaPhong}
          </div>
          <span class="pr-status-pill pill-${st}">${statusLabel}</span>
        </div>
        <div class="pr-body rack-body">
          ${bodyHtml}
        </div>
        <div class="pr-actions rack-actions">
          ${actionsHtml}
        </div>
      </div>
    `;
  };

  let groupsHtml = '';
  targetGroups.forEach(lp => {
    const filteredRooms = (lp.DanhSachPhong || []).filter(r => {
      if (currentRoomSubFilter !== 'ALL' && r.TrangThaiHienThi !== currentRoomSubFilter) {
        return false;
      }
      if (kw) {
        const codeMatch = (r.MaPhong || '').toLowerCase().includes(kw);
        const guestMatch = r.PhieuHienTai && (
          (r.PhieuHienTai.TenKhachHang || '').toLowerCase().includes(kw) ||
          (r.PhieuHienTai.MaPNP || '').toLowerCase().includes(kw) ||
          (r.PhieuHienTai.SDT || '').toLowerCase().includes(kw)
        );
        const upcomingMatch = (r.LichDatSapToi || []).some(u =>
          (u.MaPhieu || '').toLowerCase().includes(kw) ||
          (u.TenNguoiDat || '').toLowerCase().includes(kw) ||
          (u.SDTNguoiDat || '').toLowerCase().includes(kw)
        );
        return codeMatch || guestMatch || upcomingMatch;
      }
      return true;
    });

    if (!filteredRooms.length) return;

    const tk = lp.ThongKe || {};
    groupsHtml += `
      <section class="room-rack-group">
        <div class="room-rack-group-header">
          <div class="room-rack-group-left">
            <span class="rt-code-badge">${lp.MaLoaiPhong}</span>
            <h3 class="room-rack-group-title">${formatRoomTypeTitle(lp.TenLoaiPhong)}</h3>
            <span class="room-rack-group-meta">
              Giá: <strong>${formatMoney(lp.Gia)}/đêm</strong> • Tối đa <strong>${lp.SoNguoiToiDa} người</strong> • Tiện nghi: ${lp.TienNghi || 'Tiêu chuẩn'}
            </span>
          </div>
          <div class="room-rack-group-right">
            <span class="room-rack-avail-pill">Trống ${tk.Trong || 0}/${tk.TongSoPhong || 0} phòng</span>
          </div>
        </div>
        <div class="individual-rooms-grid rack-grid">
          ${filteredRooms.map(renderCardHtml).join('')}
        </div>
      </section>
    `;
  });

  const container = document.getElementById('roomRackContainer');
  if (container) {
    container.innerHTML = groupsHtml || `<div class="empty-state">Không tìm thấy phòng nào khớp với bộ lọc hiện tại.</div>`;
  }
}

function openRoomScheduleModal(maPhong) {
  if (!roomStatusMapData) return;
  let foundRoom = null;
  let foundLP = null;
  (roomStatusMapData.danhSachLoaiPhong || []).forEach(lp => {
    const r = (lp.DanhSachPhong || []).find(x => x.MaPhong === maPhong);
    if (r) {
      foundRoom = r;
      foundLP = lp;
    }
  });
  if (!foundRoom) return;

  const titleEl = document.getElementById('roomScheduleTitle');
  const subEl = document.getElementById('roomScheduleSubtitle');
  const bodyEl = document.getElementById('roomScheduleModalBody');
  if (titleEl) titleEl.textContent = foundRoom.MaPhong;
  if (subEl && foundLP) {
    subEl.textContent = `${formatRoomTypeTitle(foundLP.TenLoaiPhong)} (${foundLP.MaLoaiPhong}) • ${formatMoney(foundLP.Gia)}/đêm`;
  }

  const list = Array.isArray(foundRoom.LichDatSapToi) ? foundRoom.LichDatSapToi : [];
  const SLIP_ST_LABEL = {
    DangLuuTru: '🔴 Đang lưu trú',
    DaDatPhong: '🔵 Đã đặt trước',
    ChoThanhToan: '🟡 Chờ thanh toán'
  };

  if (!bodyEl) return;
  if (!list.length) {
    bodyEl.innerHTML = `
      <div class="empty-state" style="padding: 28px 16px;">
        <div style="font-size: 28px; margin-bottom: 6px;">✨</div>
        <strong>Phòng ${foundRoom.MaPhong} hiện trống lịch sắp tới</strong>
        <div style="font-size: 12.5px; color: var(--rec-muted); margin-top: 4px;">Chưa có phiếu đặt phòng hoặc giữ chỗ nào sắp tới cho phòng này.</div>
      </div>
    `;
  } else {
    bodyEl.innerHTML = `
      <div class="room-schedule-list">
        ${list.map(it => `
          <div class="room-schedule-item">
            <div class="rsi-top">
              <div>
                <strong style="color: var(--rec-primary);">#${it.MaPhieu}</strong>
                <span class="rsi-status">${SLIP_ST_LABEL[it.TrangThaiLuuTru] || it.TrangThaiLuuTru}</span>
              </div>
              <div class="rsi-dates">📅 ${formatShortDateVi(it.ThoiGianNhanPhong)} → ${formatShortDateVi(it.ThoiGianTraPhong)}</div>
            </div>
            <div class="rsi-guest">👤 ${it.TenNguoiDat || 'Khách hàng'} • 📞 ${it.SDTNguoiDat || 'Chưa cập nhật'}</div>
            <div class="rsi-time-detail">Nhận: ${formatDateTime(it.ThoiGianNhanPhong)} | Trả: ${formatDateTime(it.ThoiGianTraPhong)}</div>
            <div class="rsi-actions">
              <button type="button" class="btn btn-xs btn-outline" onclick="closeModal('modalRoomSchedule'); jumpToBookingFromRoom('${it.MaPhieu}')">📋 Xem phiếu #${it.MaPhieu} ở Tiếp đón</button>
            </div>
          </div>
        `).join('')}
      </div>
    `;
  }

  document.getElementById('modalRoomSchedule').classList.add('show');
}

function jumpToBookingFromRoom(maPNP) {
  switchMainTab('bookings');
  setFilterStatus('ALL', document.querySelector('#filterChips .filter-chip[data-status="ALL"]'));
  const searchInput = document.getElementById('searchInput');
  if (searchInput) {
    searchInput.value = maPNP;
    renderReceptionTable();
  }
}

async function toggleRoomMaintenance(maPhong, newState) {
  const actionLabel = newState === 'BaoTri'
    ? `Xác nhận đưa phòng ${maPhong} vào trạng thái BẢO TRÌ / CHỜ DỌN?`
    : `Xác nhận mở khóa phòng ${maPhong} sang trạng thái SẴN SÀNG ĐÓN KHÁCH?`;

  if (!(window.SkyUI ? await window.SkyUI.confirm(actionLabel) : false)) return;

  try {
    const res = await leTanFetch(`${API_BASE}/letan/capnhatbaotri`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        MaPhong: maPhong,
        TrangThaiBaoTri: newState
      })
    });
    const json = await res.json();
    if (!json.success) {
      showToast(json.error || 'Không thể cập nhật trạng thái phòng', '❌');
      return;
    }

    showToast(json.message || `Đã cập nhật trạng thái phòng ${maPhong}`, '🛠️');
    loadRoomStatusMap();
  } catch (err) {
    console.error(err);
    showToast('Lỗi kết nối máy chủ khi cập nhật bảo trì', '❌');
  }
}

// =========================================================================
// ĐẶT DỊCH VỤ PHÒNG NHANH TẠI QUẦY LỄ TÂN (TỪ THẺ PHÒNG ĐANG Ở)
// =========================================================================
async function openFastServiceModal(maPhong, maPNP) {
  document.getElementById('fastServiceRoomVal').value = maPhong;
  document.getElementById('fastServiceMaPNPVal').value = maPNP;
  document.getElementById('fastServiceRoomTitle').innerText = maPhong;
  document.getElementById('fastServiceMaPNPBadge').innerText = maPNP;
  const noticePNP = document.getElementById('fastServiceNoticePNP');
  if (noticePNP) noticePNP.innerText = maPNP;

  // Mặc định thời gian hiện tại (YYYY-MM-DDTHH:mm)
  const now = new Date();
  const tzOffset = now.getTimezoneOffset() * 60000;
  const localISOTime = new Date(now - tzOffset).toISOString().slice(0, 16);
  document.getElementById('fastServiceTimeInput').value = localISOTime;

  fastServiceCart = {};
  currentFastServiceCategory = 'DVDoAn';
  updateFastServiceTotalSummary();

  document.getElementById('modalFastService').classList.add('show');

  if (!fastServiceCatalog.length) {
    await loadFastServiceCatalog();
  } else {
    switchFastServiceCategory('DVDoAn');
  }
}

async function loadFastServiceCatalog() {
  const listEl = document.getElementById('fastServiceCatalogList');
  listEl.innerHTML = `<div class="empty-state" style="grid-column: 1 / -1; padding: 30px;">⏳ Đang tải danh mục dịch vụ khách sạn...</div>`;

  try {
    const res = await leTanFetch(`${API_BASE}/letan/danhsachdichvu`);
    const json = await res.json();
    if (json.success && Array.isArray(json.data)) {
      fastServiceCatalog = json.data;
      switchFastServiceCategory(currentFastServiceCategory);
    } else {
      listEl.innerHTML = `<div class="empty-state" style="grid-column: 1 / -1; color: var(--rec-err);">Không thể tải danh mục dịch vụ.</div>`;
    }
  } catch (err) {
    console.error(err);
    listEl.innerHTML = `<div class="empty-state" style="grid-column: 1 / -1; color: var(--rec-err);">Lỗi kết nối máy chủ khi tải dịch vụ.</div>`;
  }
}

function switchFastServiceCategory(loaiDV) {
  currentFastServiceCategory = loaiDV;
  ['DVDoAn', 'DVGiatUi', 'DVThueXe'].forEach(cat => {
    const tab = document.getElementById(`fastSvcTab_${cat}`);
    if (tab) tab.classList.toggle('active', cat === loaiDV);
  });
  renderFastServiceItems();
}

function renderFastServiceItems() {
  const listEl = document.getElementById('fastServiceCatalogList');
  const filtered = fastServiceCatalog.filter(item => item.LoaiDV === currentFastServiceCategory);

  if (!filtered.length) {
    listEl.innerHTML = `<div class="empty-state" style="grid-column: 1 / -1; padding: 20px;">Chưa có dịch vụ nào trong nhóm này.</div>`;
    return;
  }

  const unitLabel = currentFastServiceCategory === 'DVDoAn' ? 'phần' : (currentFastServiceCategory === 'DVGiatUi' ? 'kg / lượt' : 'ngày / chiếc');

  listEl.innerHTML = filtered.map(item => {
    const qty = fastServiceCart[item.MaDV] || 0;
    const isSelected = qty > 0;
    return `
      <div style="background: ${isSelected ? '#F4FBF7' : '#FFFFFF'}; border: 1.5px solid ${isSelected ? 'var(--rec-ok)' : 'var(--rec-line)'}; border-radius: 10px; padding: 12px 14px; display: flex; justify-content: space-between; align-items: center; gap: 10px; transition: all 0.15s;">
        <div style="flex: 1;">
          <div style="font-weight: 700; color: var(--rec-navy); font-size: 13.5px;">${item.TenDV}</div>
          <div style="font-size: 12.5px; color: var(--rec-gold-text); font-weight: 700; margin-top: 2px;">
            ${formatMoney(item.DonGia)} <span style="font-size: 11px; color: var(--rec-muted); font-weight: 400;">/ ${unitLabel}</span>
          </div>
        </div>
        <div style="display: flex; align-items: center; gap: 8px;">
          <button type="button" onclick="changeFastSvcQty('${item.MaDV}', -1)" style="width: 28px; height: 28px; border-radius: 6px; border: 1px solid var(--rec-line); background: var(--rec-section); color: var(--rec-navy); font-weight: 800; cursor: pointer;">-</button>
          <span style="min-width: 22px; text-align: center; font-weight: 800; font-size: 14px; color: ${isSelected ? 'var(--rec-ok)' : 'var(--rec-muted)'};">${qty}</span>
          <button type="button" onclick="changeFastSvcQty('${item.MaDV}', 1)" style="width: 28px; height: 28px; border-radius: 6px; border: 1px solid var(--rec-primary); background: var(--rec-highlight); color: var(--rec-primary); font-weight: 800; cursor: pointer;">+</button>
        </div>
      </div>
    `;
  }).join('');
}

function changeFastSvcQty(maDV, delta) {
  const cur = fastServiceCart[maDV] || 0;
  const next = Math.max(0, cur + delta);
  if (next === 0) {
    delete fastServiceCart[maDV];
  } else {
    fastServiceCart[maDV] = next;
  }
  renderFastServiceItems();
  updateFastServiceTotalSummary();
}

function updateFastServiceTotalSummary() {
  let totalCount = 0;
  let totalMoney = 0;

  Object.keys(fastServiceCart).forEach(maDV => {
    const qty = fastServiceCart[maDV];
    const item = fastServiceCatalog.find(x => x.MaDV === maDV);
    if (item && qty > 0) {
      totalCount += qty;
      totalMoney += Number(item.DonGia || 0) * qty;
    }
  });

  document.getElementById('fastServiceSelectedCount').innerText = totalCount;
  document.getElementById('fastServiceTotalMoney').innerText = formatMoney(totalMoney);
}

async function submitFastRoomService() {
  const maPhong = document.getElementById('fastServiceRoomVal').value;
  const maPNP = document.getElementById('fastServiceMaPNPVal').value;
  const rawTime = document.getElementById('fastServiceTimeInput').value;

  const itemsPayload = Object.keys(fastServiceCart).map(maDV => ({
    MaDV: maDV,
    SoLuong: fastServiceCart[maDV],
    ThoiGianSuDung: rawTime ? rawTime.replace('T', ' ') + ':00' : null
  })).filter(x => x.SoLuong > 0);

  if (!itemsPayload.length) {
    showToast('Vui lòng chọn ít nhất 1 dịch vụ trước khi xác nhận!', '⚠️');
    return;
  }

  const btn = document.getElementById('btnSubmitFastService');
  btn.disabled = true;
  btn.innerText = '⏳ Đang ghi nhận dịch vụ...';

  try {
    const res = await leTanFetch(`${API_BASE}/letan/datdichvunhanh`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        MaPNP: maPNP,
        MaPhong: maPhong,
        DanhSachDichVu: itemsPayload
      })
    });
    const json = await res.json();
    if (!json.success) {
      showToast(json.error || 'Lỗi khi đặt dịch vụ phòng', '❌');
      return;
    }

    showToast(json.message || `Đã thêm dịch vụ cho phòng ${maPhong}`, '🍽️');
    closeModal('modalFastService');
    loadReceptions();
  } catch (err) {
    console.error(err);
    showToast('Lỗi kết nối máy chủ khi đặt dịch vụ', '❌');
  } finally {
    btn.disabled = false;
    btn.innerText = '➕ XÁC NHẬN THÊM DỊCH VỤ VÀO PHÒNG';
  }
}

// =========================================================================
// 1. TẢI DANH SÁCH PHIẾU ĐẶT PHÒNG & RENDER BẢNG
// =========================================================================
async function loadReceptions() {
  try {
    const res = await leTanFetch(`${API_BASE}/letan/xemdsphieu`);
    const json = await res.json();
    if (!json.success) {
      showToast(json.error || 'Không thể tải danh sách phiếu', '❌');
      return;
    }

    allReceptions = json.data || [];
    updateKPIs();
    renderReceptionTable();
  } catch (err) {
    console.error(err);
    showToast('Lỗi kết nối máy chủ khi tải dữ liệu lễ tân', '❌');
  }
}

function updateKPIs() {
  const booked = allReceptions.filter(r => r.TrangThai === 'DaDatPhong').length;
  const staying = allReceptions.filter(r => r.TrangThai === 'DangLuuTru').length;
  const checkedOut = allReceptions.filter(r => r.TrangThai === 'DaTraPhong').length;
  const cancelled = allReceptions.filter(r => r.TrangThai === 'DaHuy' || r.TrangThai === 'ThanhToanThatBai').length;

  document.getElementById('kpiBooked').innerText = booked;
  document.getElementById('kpiStaying').innerText = staying;
  document.getElementById('kpiCheckedOut').innerText = checkedOut;
  document.getElementById('kpiCancelled').innerText = cancelled;
}

function syncKpiActiveState() {
  document.querySelectorAll('.kpi-card[data-kpi-status]').forEach(card => {
    card.classList.toggle('active', card.getAttribute('data-kpi-status') === currentFilterStatus);
  });
}

function setFilterStatus(status, btnEl) {
  currentFilterStatus = status;
  document.querySelectorAll('#filterChips .filter-chip').forEach(b => b.classList.remove('active'));
  if (btnEl) btnEl.classList.add('active');
  syncKpiActiveState();
  renderReceptionTable();
}

function filterByStatusFromKpi(status) {
  const targetBtn = document.querySelector(`#filterChips .filter-chip[data-status="${status}"]`);
  setFilterStatus(status, targetBtn);
  const tableEl = document.querySelector('.table-card');
  if (tableEl) tableEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function parseDateVal(str) {
  if (!str) return null;
  const d = new Date(str.includes(' ') && !str.includes('T') ? str.replace(' ', 'T') : str);
  return isNaN(d.getTime()) ? null : d;
}

function formatCompactBookDate(str) {
  const d = parseDateVal(str);
  if (!d) return str || '—';
  const day = String(d.getDate()).padStart(2, '0');
  const month = String(d.getMonth() + 1).padStart(2, '0');
  const year = d.getFullYear();
  const hours = String(d.getHours()).padStart(2, '0');
  const minutes = String(d.getMinutes()).padStart(2, '0');
  const currentYear = new Date().getFullYear();
  return year === currentYear
    ? `${day}/${month} ${hours}:${minutes}`
    : `${day}/${month}/${year} ${hours}:${minutes}`;
}

function formatStayEndpoint(str) {
  const d = parseDateVal(str);
  if (!d) return '—';
  const day = String(d.getDate()).padStart(2, '0');
  const month = String(d.getMonth() + 1).padStart(2, '0');
  const hours = String(d.getHours()).padStart(2, '0');
  const minutes = String(d.getMinutes()).padStart(2, '0');
  return `${day}/${month} <span class="rec-stay-time">(${hours}:${minutes})</span>`;
}

function formatActualStayLine(inStr, outStr) {
  if (!inStr && !outStr) {
    return `<div class="rec-actual-muted">Thực tế: Chưa ghi nhận</div>`;
  }
  const parts = [];
  if (inStr) parts.push(`In: ${formatCompactBookDate(inStr)}`);
  if (outStr) parts.push(`Out: ${formatCompactBookDate(outStr)}`);
  return `<div class="rec-stay-actual">${parts.join(' • ')}</div>`;
}

function closeAllRecActionMenus() {
  document.querySelectorAll('.rec-more-wrap.open').forEach(el => {
    el.classList.remove('open');
  });
}

function toggleRecActionMenu(event, btn) {
  event.stopPropagation();
  const wrap = btn.closest('.rec-more-wrap');
  if (!wrap) return;
  const wasOpen = wrap.classList.contains('open');
  closeAllRecActionMenus();
  if (wasOpen) return;

  wrap.classList.add('open');
  const menu = wrap.querySelector('.rec-more-menu');
  if (!menu) return;

  const rect = btn.getBoundingClientRect();
  const menuH = menu.offsetHeight || 120;
  const menuW = menu.offsetWidth || 160;
  const spaceBelow = window.innerHeight - rect.bottom;
  const top = spaceBelow >= menuH + 10 ? rect.bottom + 6 : Math.max(8, rect.top - menuH - 6);
  const left = Math.max(8, Math.min(window.innerWidth - menuW - 8, rect.right - menuW));

  menu.style.top = `${top}px`;
  menu.style.left = `${left}px`;
}

if (!window.__recActionMenuBound) {
  window.__recActionMenuBound = true;
  document.addEventListener('click', closeAllRecActionMenus);
  window.addEventListener('scroll', closeAllRecActionMenus, true);
}

function getStatusBadgeHtml(st) {
  const labels = {
    'ChoThanhToan': '⏳ Chờ thanh toán',
    'DaDatPhong': '📅 Chờ Check-in',
    'DangLuuTru': '🏨 Đang lưu trú',
    'DaTraPhong': '✅ Đã Check-out',
    'ThanhToanThatBai': '⚠️ Lỗi TT',
    'DaHuy': '🚫 Đã hủy'
  };
  return `<span class="status-badge st-${st}">${labels[st] || st}</span>`;
}

function buildRecMoreMenuHtml(moreItems) {
  if (!moreItems || !moreItems.length) return '';
  return `
    <div class="rec-more-wrap">
      <button type="button" class="rec-more-btn" onclick="toggleRecActionMenu(event, this)" title="Thao tác khác" aria-label="Thao tác khác">⋮ Khác</button>
      <div class="rec-more-menu">${moreItems.join('')}</div>
    </div>
  `;
}

function renderReceptionTable() {
  const tbody = document.getElementById('receptionTableBody');
  const keyword = (document.getElementById('searchInput').value || '').trim().toLowerCase();

  const filtered = allReceptions.filter(item => {
    if (currentFilterStatus !== 'ALL' && item.TrangThai !== currentFilterStatus) {
      return false;
    }
    if (!keyword) return true;
    const matchMa = (item.MaPNP || '').toLowerCase().includes(keyword);
    const matchTen = (item.TenKhachHang || '').toLowerCase().includes(keyword);
    const matchSDT = (item.SDT || '').toLowerCase().includes(keyword);
    const matchPhong = (item.DanhSachPhong || []).some(p => p.toLowerCase().includes(keyword));
    return matchMa || matchTen || matchSDT || matchPhong;
  });

  if (filtered.length === 0) {
    tbody.innerHTML = `<tr><td colspan="6" class="empty-state">Không tìm thấy phiếu nhận phòng nào khớp với bộ lọc hiện tại.</td></tr>`;
    return;
  }

  tbody.innerHTML = filtered.map(item => {
    const roomsDetail = item.ChiTietPhongTrangThai || [];
    const roomsHtml = roomsDetail.length > 0
      ? roomsDetail.map(rd => `<span class="room-tag rec-room-neutral">${rd.MaPhong}</span>`).join('')
      : `<span style="color: var(--rec-muted); font-size: 12px;">Chưa gán phòng</span>`;

    const hasWaitingCheckinRoom = roomsDetail.some(r => (r.TrangThaiPhong || 'DaDat') === 'DaDat') && !['DaHuy', 'ThanhToanThatBai', 'DaTraPhong'].includes(item.TrangThai);
    const hasStayingRoom = roomsDetail.some(r => (r.TrangThaiPhong || '') === 'DangLuuTru') || item.TrangThai === 'DangLuuTru';

    const canCheckin = item.TrangThai === 'DaDatPhong' || hasWaitingCheckinRoom;
    const canCheckout = hasStayingRoom;
    const canCancel = item.TrangThai === 'DaDatPhong' || item.TrangThai === 'ChoThanhToan';

    const channelText = item.KenhDat === 'TrucTiep' ? 'Tại quầy' : 'Online';

    // Xây dựng cột Tác Vụ Lễ Tân theo trạng thái dòng
    let actionsHtml = '';
    if (item.TrangThai === 'DaHuy') {
      actionsHtml = `
        <button type="button" class="btn btn-xs btn-danger" onclick="openCancelDetailModal('${item.MaPNP}')" title="Xem chi tiết biên bản hủy & hoàn tiền">
          📑 Chi tiết hủy
        </button>
      `;
    } else if (canCheckout) {
      const moreItems = [];
      moreItems.push(`<button type="button" class="rec-more-item" onclick="closeAllRecActionMenus(); openAuditModal('${item.MaPNP}')">🪪 Đối chiếu</button>`);
      if (canCheckin) {
        moreItems.push(`<button type="button" class="rec-more-item" onclick="closeAllRecActionMenus(); openCheckinModal('${item.MaPNP}')">🔑 Check-in</button>`);
      }
      if (canCancel) {
        moreItems.push(`<button type="button" class="rec-more-item is-danger" onclick="closeAllRecActionMenus(); openCancelSlipModal('${item.MaPNP}')">🚫 Hủy phiếu</button>`);
      }
      actionsHtml = `
        <button type="button" class="btn btn-xs btn-amber" onclick="openCheckoutModal('${item.MaPNP}')">
          🏁 Check-out
        </button>
        <button type="button" class="btn btn-xs btn-purple" onclick="openServicesModal('${item.MaPNP}')" title="Xem danh sách dịch vụ phát sinh">
          🍽️ Dịch vụ
        </button>
        ${buildRecMoreMenuHtml(moreItems)}
      `;
    } else if (canCheckin) {
      const moreItems = [
        `<button type="button" class="rec-more-item" onclick="closeAllRecActionMenus(); openAuditModal('${item.MaPNP}')">🪪 Đối chiếu</button>`,
        `<button type="button" class="rec-more-item" onclick="closeAllRecActionMenus(); openServicesModal('${item.MaPNP}')">🍽️ Dịch vụ</button>`
      ];
      if (canCancel) {
        moreItems.push(`<button type="button" class="rec-more-item is-danger" onclick="closeAllRecActionMenus(); openCancelSlipModal('${item.MaPNP}')">🚫 Hủy phiếu</button>`);
      }
      actionsHtml = `
        <button type="button" class="btn btn-xs btn-emerald" onclick="openCheckinModal('${item.MaPNP}')">
          🔑 Check-in
        </button>
        ${buildRecMoreMenuHtml(moreItems)}
      `;
    } else if (item.TrangThai === 'DaTraPhong') {
      const moreItems = [
        `<button type="button" class="rec-more-item" onclick="closeAllRecActionMenus(); openAuditModal('${item.MaPNP}')">🪪 Đối chiếu</button>`,
        `<button type="button" class="rec-more-item" onclick="closeAllRecActionMenus(); openServicesModal('${item.MaPNP}')">🍽️ Dịch vụ</button>`
      ];
      actionsHtml = `
        <button type="button" class="btn btn-xs btn-sky" onclick="openCheckoutModal('${item.MaPNP}', true)" title="Xem lại bảng quyết toán hóa đơn">
          🧾 Xem bill
        </button>
        ${buildRecMoreMenuHtml(moreItems)}
      `;
    } else {
      const moreItems = [
        `<button type="button" class="rec-more-item" onclick="closeAllRecActionMenus(); openServicesModal('${item.MaPNP}')">🍽️ Dịch vụ</button>`
      ];
      if (canCancel) {
        moreItems.push(`<button type="button" class="rec-more-item is-danger" onclick="closeAllRecActionMenus(); openCancelSlipModal('${item.MaPNP}')">🚫 Hủy phiếu</button>`);
      }
      actionsHtml = `
        <button type="button" class="btn btn-xs btn-outline" onclick="openAuditModal('${item.MaPNP}')" title="Kiểm tra & sửa giấy tờ khách lưu trú">
          🪪 Đối chiếu
        </button>
        ${buildRecMoreMenuHtml(moreItems)}
      `;
    }

    return `
      <tr>
        <td>
          <div class="rec-slip-head">
            <strong class="rec-slip-code">#${item.MaPNP}</strong>
            <span class="rec-dot-sep">•</span>
            <span class="rec-channel-text">${channelText}</span>
          </div>
          <div class="rec-book-date">Đặt: ${formatCompactBookDate(item.NgayDat)}</div>
        </td>
        <td>
          <div class="rec-cust-head">
            <span class="rec-cust-name">${item.TenKhachHang || 'Khách vãng lai'}</span>
            <span class="rec-cust-code">(${item.MaKH || '—'})</span>
          </div>
          <div class="rec-cust-phone">${item.SDT || 'Chưa cập nhật'}</div>
        </td>
        <td>
          <div class="room-tag-list">${roomsHtml}</div>
          <div class="rec-guest-count">👤 ${item.SoLuongKhachKhaiBao || 0} khách</div>
        </td>
        <td>
          <div class="rec-stay-plan">${formatStayEndpoint(item.NgayNhanPhong)} → ${formatStayEndpoint(item.NgayTraPhong)}</div>
          ${formatActualStayLine(item.ThoiGianCheckIn, item.ThoiGianCheckOut)}
        </td>
        <td>
          ${getStatusBadgeHtml(item.TrangThai)}
          ${item.TrangThai === 'DaHuy' && Number(item.SoTienHoan || 0) > 0 ? `
            <div style="font-size: 11px; color: var(--rec-ok); margin-top: 5px; font-weight: 700;">
              💸 Hoàn: ${formatMoney(item.SoTienHoan)} (${item.TyLeHoan || 0}%)
            </div>
          ` : ''}
          ${item.TrangThai === 'DaHuy' && item.LyDoHuy ? `
            <div style="font-size: 11px; color: var(--rec-muted); margin-top: 2px; max-width: 170px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="${item.LyDoHuy}">
              📝 ${item.LyDoHuy}
            </div>
          ` : ''}
        </td>
        <td class="rec-td-actions">
          <div class="rec-actions-row">${actionsHtml}</div>
        </td>
      </tr>
    `;
  }).join('');
}

// =========================================================================
// 2. MODAL ĐỐI CHIẾU THÔNG TIN & CHỈNH SỬA GIẤY TỜ KHÁCH LƯU TRÚ
// =========================================================================
async function openAuditModal(maPNP) {
  currentEditingMaPNP = maPNP;
  document.getElementById('auditMaPNP').innerText = maPNP;
  const body = document.getElementById('auditModalBody');
  body.innerHTML = `<div class="empty-state">⏳ Đang tải hồ sơ đối chiếu của phiếu ${maPNP}...</div>`;
  document.getElementById('modalAudit').classList.add('show');

  try {
    const res = await leTanFetch(`${API_BASE}/letan/xemctphieu`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ MaPNP: maPNP })
    });
    const json = await res.json();
    if (!json.success) {
      body.innerHTML = `<div class="empty-state" style="color: var(--rec-err);">${json.error || 'Lỗi tải chi tiết phiếu'}</div>`;
      return;
    }

    const d = json.data;
    const rooms = d.DanhSachChiTiet || [];
    const guests = d.DanhSachNguoiLuuTru || [];

    body.innerHTML = `
      <div class="info-grid">
        <div class="info-item">
          <label>Người Đặt Phòng</label>
          <div class="val">${d.TenKhachHang || 'N/A'} (${d.MaKH})</div>
        </div>
        <div class="info-item">
          <label>Số Điện Thoại &amp; Email</label>
          <div class="val">${d.SDT || 'Chưa có'}</div>
        </div>
        <div class="info-item">
          <label>Nhận Phòng Dự Kiến</label>
          <div class="val">${formatDateTime(d.NgayNhanPhong)}</div>
        </div>
        <div class="info-item">
          <label>Trả Phòng Dự Kiến</label>
          <div class="val">${formatDateTime(d.NgayTraPhong)}</div>
        </div>
        <div class="info-item">
          <label>Check-in Thực Tế</label>
          <div class="val">${formatDateTime(d.ThoiGianCheckIn)}</div>
          ${d.TenLeTanCheckIn ? `<div style="font-size:11.5px; color:var(--rec-ok); margin-top:3px;">👤 Lễ tân: ${d.TenLeTanCheckIn} (${d.MaLTCheckIn})</div>` : ''}
        </div>
        <div class="info-item">
          <label>Check-out Thực Tế</label>
          <div class="val">${formatDateTime(d.ThoiGianCheckOut)}</div>
          ${d.TenLeTanCheckOut ? `<div style="font-size:11.5px; color:var(--rec-primary); margin-top:3px;">👤 Lễ tân: ${d.TenLeTanCheckOut} (${d.MaLTCheckOut})</div>` : ''}
        </div>
        <div class="info-item">
          <label>Kênh Đặt Phòng</label>
          <div class="val">${d.KenhDat === 'TrucTiep' ? '🏢 Đặt trực tiếp tại quầy' : '🌐 Đặt trực tuyến (Online)'}</div>
          ${d.TenLeTanDat ? `<div style="font-size:11.5px; color:var(--rec-gold-text); margin-top:3px;">👤 Tạo bởi: ${d.TenLeTanDat}</div>` : ''}
        </div>
        <div class="info-item">
          <label>Trạng Thái Hiện Tại</label>
          <div class="val">${getStatusBadgeHtml(d.TrangThai)}</div>
        </div>
      </div>

      <div>
        <h4 style="margin: 0 0 10px 0; color: var(--rec-navy); font-size: 14px; text-transform: uppercase;">
          🛏️ Danh Sách Phòng Phân Bổ &amp; Trạng Thái Từng Phòng (${rooms.length} phòng)
        </h4>
        <div class="modal-table-wrap">
        <table class="sub-table">
          <thead>
            <tr>
              <th>Mã CTPNP</th>
              <th>Số Phòng</th>
              <th>Hạng Phòng</th>
              <th>Đơn Giá / Đêm</th>
              <th>Giờ Check-in / Check-out Phòng</th>
              <th>Lễ Tân Phụ Trách Phòng</th>
              <th>Trạng Thái Phòng</th>
              <th style="text-align: right;">Tác Vụ Riêng</th>
            </tr>
          </thead>
          <tbody>
            ${rooms.map(r => {
              const rSt = r.TrangThaiPhong || 'DaDat';
              let rBadge = `<span class="status-badge st-DaDatPhong">📅 Chờ nhận phòng</span>`;
              let rAction = '';

              if (rSt === 'DangLuuTru') {
                rBadge = `<span class="status-badge st-DangLuuTru">🏨 Đang lưu trú</span>`;
                rAction = `
                  <button type="button" class="btn btn-xs btn-amber" onclick="closeModal('modalAudit'); openCheckoutModal('${d.MaPNP}', false, '${r.MaPhong}');">
                    🏁 Trả phòng ${r.MaPhong}
                  </button>
                `;
              } else if (rSt === 'DaTraPhong') {
                rBadge = `<span class="status-badge st-DaTraPhong">✅ Đã trả phòng</span>`;
              } else if (rSt === 'DaHuy') {
                rBadge = `<span class="status-badge st-DaHuy">🚫 Đã hủy phòng</span>`;
              } else if (d.TrangThai !== 'DaHuy' && d.TrangThai !== 'ThanhToanThatBai') {
                rAction = `
                  <div style="display: inline-flex; gap: 6px; flex-wrap: wrap; justify-content: flex-end;">
                    <button type="button" class="btn btn-xs btn-emerald" onclick="closeModal('modalAudit'); openCheckinModal('${d.MaPNP}', '${r.MaPhong}');">
                      🔑 Nhận phòng ${r.MaPhong}
                    </button>
                    <button type="button" class="btn btn-xs btn-danger" onclick="closeModal('modalAudit'); openCancelSlipModal('${d.MaPNP}', '${r.MaPhong}');">
                      🚫 Hủy phòng ${r.MaPhong}
                    </button>
                  </div>
                `;
              }

              const ltCheckInRoom = r.TenLeTanCheckInPhong
                ? `<div style="font-size:11.5px; color:var(--rec-ok);">🔑 In: <strong>${r.TenLeTanCheckInPhong}</strong> <span style="color:var(--rec-muted);">(${r.MaLTCheckInPhong})</span></div>`
                : `<div style="font-size:11.5px; color:var(--rec-muted);">🔑 In: Chưa ghi nhận</div>`;
              const ltCheckOutRoom = r.TenLeTanCheckOutPhong
                ? `<div style="font-size:11.5px; color:var(--rec-primary); margin-top:3px;">🏁 Out: <strong>${r.TenLeTanCheckOutPhong}</strong> <span style="color:var(--rec-muted);">(${r.MaLTCheckOutPhong})</span></div>`
                : `<div style="font-size:11.5px; color:var(--rec-muted); margin-top:3px;">🏁 Out: Chưa ghi nhận</div>`;

              return `
                <tr>
                  <td><strong>${r.MaCTPNP}</strong></td>
                  <td><span class="room-tag">Phòng ${r.MaPhong}</span></td>
                  <td>${r.TenLoaiPhong || r.MaLoaiPhong}</td>
                  <td style="color: var(--rec-gold-text); font-weight: 700;">${formatMoney(r.DonGia)}</td>
                  <td>
                    <div style="font-size:11.5px;">🔑 In: ${formatDateTime(r.ThoiGianCheckInPhong)}</div>
                    <div style="font-size:11.5px; margin-top:3px;">🏁 Out: ${formatDateTime(r.ThoiGianCheckOutPhong)}</div>
                  </td>
                  <td>
                    ${ltCheckInRoom}
                    ${ltCheckOutRoom}
                  </td>
                  <td>${rBadge}</td>
                  <td style="text-align: right;">${rAction}</td>
                </tr>
              `;
            }).join('')}
          </tbody>
        </table>
        </div>
      </div>

      <div>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; flex-wrap: wrap; gap: 8px;">
          <h4 style="margin: 0; color: var(--rec-navy); font-size: 14px; text-transform: uppercase;">
            🪪 Danh Sách Người Lưu Trú &amp; Đối Chiếu Giấy Tờ (${guests.length} khách)
          </h4>
          <span style="font-size: 12px; color: var(--rec-muted);">💡 Nhấn "✏️ Sửa giấy tờ" nếu khách nhập sai CCCD/Họ tên/Ngày sinh</span>
        </div>

        ${guests.length === 0 ? `
          <div class="empty-state" style="padding: 20px;">Chưa có dữ liệu người lưu trú.</div>
        ` : `
          <div class="modal-table-wrap">
          <table class="sub-table">
            <thead>
              <tr>
                <th>Mã NLT</th>
                <th>Phòng Ở</th>
                <th>Họ Và Tên</th>
                <th>Ngày Sinh</th>
                <th>Loại Giấy Tờ</th>
                <th>Số Giấy Tờ (CCCD/Passport)</th>
                <th>SĐT</th>
                <th>Thao Tác</th>
              </tr>
            </thead>
            <tbody>
              ${guests.map(g => `
                <tr>
                  <td><strong>${g.MaNguoiLuuTru}</strong></td>
                  <td><span class="room-tag">${g.MaPhong || 'Chưa gán'}</span></td>
                  <td style="font-weight: 700; color: var(--rec-navy);">${g.HoTen}</td>
                  <td>${g.NgaySinh || '-'}</td>
                  <td><span style="color: var(--rec-primary); font-weight: 600;">${g.LoaiGiayTo || 'CCCD'}</span></td>
                  <td><strong style="color: var(--rec-gold-text); letter-spacing: 0.5px;">${g.CCCD || 'Trẻ em'}</strong></td>
                  <td>${g.SDT || '-'}</td>
                  <td>
                    <button type="button" class="btn btn-xs btn-sky" onclick='openSuaNLTModal(${JSON.stringify(g).replace(/'/g, "&#39;")})'>
                      ✏️ Sửa giấy tờ
                    </button>
                  </td>
                </tr>
              `).join('')}
            </tbody>
          </table>
          </div>
        `}
      </div>
    `;
  } catch (err) {
    console.error(err);
    body.innerHTML = `<div class="empty-state" style="color: var(--rec-err);">Lỗi kết nối máy chủ.</div>`;
  }
}

function openSuaNLTModal(guest) {
  document.getElementById('editMaNLT').value = guest.MaNguoiLuuTru;
  document.getElementById('editHoTenNLT').value = guest.HoTen || '';
  document.getElementById('editNgaySinhNLT').value = guest.NgaySinh || '';
  document.getElementById('editLoaiGiayTo').value = guest.LoaiGiayTo || 'CCCD';
  document.getElementById('editCCCD').value = guest.CCCD || '';
  document.getElementById('editSDTNLT').value = guest.SDT || '';
  updateHintGiayTo();
  document.getElementById('modalSuaNLT').classList.add('show');
}

function updateHintGiayTo() {
  const loai = document.getElementById('editLoaiGiayTo').value;
  const hint = document.getElementById('hintGiayToText');
  if (loai === 'CCCD') {
    hint.innerText = '📌 CCCD công dân Việt Nam bắt buộc đúng 12 chữ số.';
  } else if (loai === 'CMND') {
    hint.innerText = '📌 CMND cũ bắt buộc đúng 9 chữ số.';
  } else if (loai === 'Passport') {
    hint.innerText = '📌 Hộ chiếu (Passport) gồm 7 - 9 ký tự chữ in hoa và số.';
  } else {
    hint.innerText = '📌 Giấy khai sinh / Định danh trẻ em gồm 6 - 15 ký tự chữ hoặc số.';
  }
}

async function submitSuaNLT() {
  const maNLT = document.getElementById('editMaNLT').value.trim();
  const hoTen = document.getElementById('editHoTenNLT').value.trim();
  const ngaySinh = document.getElementById('editNgaySinhNLT').value;
  const loaiGiayTo = document.getElementById('editLoaiGiayTo').value;
  const cccd = document.getElementById('editCCCD').value.trim().toUpperCase();
  const sdt = document.getElementById('editSDTNLT').value.trim();

  if (!hoTen || hoTen.length < 2) {
    showToast('Vui lòng nhập đầy đủ họ và tên khách lưu trú', '⚠️');
    return;
  }
  if (!ngaySinh) {
    showToast('Vui lòng chọn ngày sinh của khách lưu trú', '⚠️');
    return;
  }

  if (loaiGiayTo === 'CCCD' && !/^\d{12}$/.test(cccd)) {
    showToast('Số CCCD phải gồm đúng 12 chữ số!', '⚠️');
    return;
  }
  if (loaiGiayTo === 'CMND' && !/^\d{9}$/.test(cccd)) {
    showToast('Số CMND phải gồm đúng 9 chữ số!', '⚠️');
    return;
  }
  if (loaiGiayTo === 'Passport' && !/^[A-Z0-9]{7,9}$/.test(cccd)) {
    showToast('Số Hộ chiếu (Passport) phải gồm từ 7 đến 9 ký tự chữ và số!', '⚠️');
    return;
  }
  if (loaiGiayTo === 'GiayKhaiSinh' && cccd !== '' && !/^[A-Z0-9\-]{6,15}$/.test(cccd)) {
    showToast('Mã Giấy khai sinh / Định danh phải từ 6 đến 15 ký tự!', '⚠️');
    return;
  }

  if (sdt !== '' && !/^0\d{9}$/.test(sdt)) {
    showToast('Số điện thoại phải gồm đúng 10 chữ số và bắt đầu bằng số 0!', '⚠️');
    return;
  }

  const btn = document.getElementById('btnSaveSuaNLT');
  btn.disabled = true;
  btn.innerText = 'Đang lưu...';

  try {
    const res = await leTanFetch(`${API_BASE}/letan/capnhatkhach`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        MaNguoiLuuTru: maNLT,
        HoTen: hoTen,
        NgaySinh: ngaySinh,
        LoaiGiayTo: loaiGiayTo,
        CCCD: cccd,
        SDT: sdt
      })
    });
    const json = await res.json();
    if (!json.success) {
      showToast(json.error || 'Cập nhật thất bại', '❌');
      return;
    }

    showToast('Đã cập nhật thông tin giấy tờ khách lưu trú thành công!');
    closeModal('modalSuaNLT');
    if (currentEditingMaPNP && document.getElementById('modalAudit').classList.contains('show')) {
      openAuditModal(currentEditingMaPNP);
    }
    if (currentMainTab === 'rooms') {
      loadRoomStatusMap();
    }
  } catch (err) {
    console.error(err);
    showToast('Lỗi kết nối máy chủ khi lưu thông tin', '❌');
  } finally {
    btn.disabled = false;
    btn.innerText = '💾 Lưu Cập Nhật';
  }
}

// =========================================================================
// 3. THỦ TỤC CHECK-IN (NHẬN PHÒNG TOÀN PHIẾU HOẶC TỪNG PHÒNG RIÊNG LẺ)
// =========================================================================
async function openCheckinModal(maPNP, maPhongTarget = null) {
  currentCheckinMaPNP = maPNP;
  currentCheckinMaPhong = maPhongTarget;
  const item = allReceptions.find(r => r.MaPNP === maPNP);
  if (!item) return;

  const roomsDetail = item.ChiTietPhongTrangThai || [];
  const waitingRooms = roomsDetail.filter(r => (r.TrangThaiPhong || 'DaDat') === 'DaDat').map(r => r.MaPhong);
  const roomsToCheckin = maPhongTarget ? [maPhongTarget] : (waitingRooms.length > 0 ? waitingRooms : (item.DanhSachPhong || []));

  // Tải sơ đồ trạng thái vật lý hiện tại nếu chưa có để kiểm tra phòng sẵn sàng / bảo trì / đang có khách
  if (!roomStatusMapData) {
    try {
      const resMap = await leTanFetch(`${API_BASE}/letan/trangthaiphong`);
      const jsonMap = await resMap.json();
      if (jsonMap.success) roomStatusMapData = jsonMap;
    } catch (e) {}
  }

  let blockedByMaint = [];
  let blockedByOccupied = [];
  if (roomStatusMapData && Array.isArray(roomStatusMapData.danhSachLoaiPhong)) {
    roomStatusMapData.danhSachLoaiPhong.forEach(lp => {
      (lp.DanhSachPhong || []).forEach(phys => {
        if (roomsToCheckin.includes(phys.MaPhong)) {
          if (phys.TrangThaiHienThi === 'BaoTri') {
            blockedByMaint.push(phys.MaPhong);
          } else if (phys.TrangThaiHienThi === 'DangO' && phys.PhieuHienTai && phys.PhieuHienTai.MaPNP !== maPNP) {
            blockedByOccupied.push({ MaPhong: phys.MaPhong, MaPNP: phys.PhieuHienTai.MaPNP });
          }
        }
      });
    });
  }

  const now = new Date();
  const expectedIn = new Date((item.NgayNhanPhong || '').replace(' ', 'T'));
  const isEarlyCheckin = !isNaN(expectedIn.getTime()) && now < expectedIn;
  const hoursEarly = isEarlyCheckin ? Math.round(((expectedIn - now) / 3600000) * 10) / 10 : 0;

  const roomsList = roomsToCheckin.map(p => `<span class="room-tag">Phòng ${p}</span>`).join(' ');
  const scopeTitle = maPhongTarget
    ? `<span style="color: var(--rec-gold-text);">Nhận riêng Phòng ${maPhongTarget}</span> (Thuộc phiếu ${item.MaPNP})`
    : `Nhận toàn bộ phòng chờ nhận của phiếu <strong style="color: var(--rec-gold-text);">${item.MaPNP}</strong>`;

  let statusWarningHtml = '';
  const btnExec = document.getElementById('btnConfirmCheckinExec');

  if (blockedByMaint.length > 0) {
    statusWarningHtml = `
      <div style="background: #F5F3FF; border: 1.5px solid #C4B5FD; padding: 14px 16px; border-radius: 10px; font-size: 13px; color: #5B21B6;">
        🛠️ <strong>CẢNH BÁO PHÒNG ĐANG BẢO TRÌ / DỌN DẸP:</strong><br>
        Phòng <strong>${blockedByMaint.join(', ')}</strong> hiện đang ở trạng thái <strong>Bảo trì / Chờ dọn</strong>. Vui lòng mở khóa bảo trì trên Sơ đồ phòng trước khi giao chìa khóa cho khách!
      </div>
    `;
    if (btnExec) btnExec.disabled = true;
  } else if (blockedByOccupied.length > 0) {
    const occText = blockedByOccupied.map(o => `${o.MaPhong} (Phiếu ${o.MaPNP})`).join(', ');
    statusWarningHtml = `
      <div style="background: #FEF2F2; border: 1.5px solid #FECACA; padding: 14px 16px; border-radius: 10px; font-size: 13px; color: #B91C1C;">
        🚫 <strong>PHÒNG ĐANG CÓ KHÁCH LƯU TRÚ:</strong><br>
        Phòng <strong>${occText}</strong> chưa hoàn tất Check-out. Vui lòng làm thủ tục trả phòng cho khách cũ trước khi Check-in!
      </div>
    `;
    if (btnExec) btnExec.disabled = true;
  } else {
    if (btnExec) btnExec.disabled = false;
    if (isEarlyCheckin) {
      statusWarningHtml = `
        <div style="background: var(--rec-highlight); border: 1.5px solid #BBD6EC; padding: 14px 16px; border-radius: 10px; font-size: 13px; color: var(--rec-navy);">
          ⚡ <strong>NHẬN PHÒNG SỚM (${hoursEarly} giờ trước giờ dự kiến):</strong><br>
          Hệ thống đã kiểm tra: Phòng <strong>${roomsToCheckin.join(', ')}</strong> hiện đang <strong>TRỐNG &amp; SẴN SÀNG</strong>. Lễ tân có thể linh hoạt làm thủ tục Check-in sớm cho khách ngay bây giờ!
        </div>
      `;
    } else {
      statusWarningHtml = `
        <div style="background: #F4FBF7; border: 1px solid #BBF7D0; padding: 14px 16px; border-radius: 10px; font-size: 13px; color: #15803D;">
          ✅ <strong>Kiểm tra điều kiện phòng:</strong> Phòng <strong>${roomsToCheckin.join(', ')}</strong> đang sẵn sàng đón khách. Sau khi xác nhận, hệ thống sẽ ghi nhận thời điểm Check-in thực tế và kích hoạt lưu trú.
        </div>
      `;
    }
  }

  document.getElementById('checkinConfirmBody').innerHTML = `
    ${statusWarningHtml}

    <div class="info-grid">
      <div class="info-item">
        <label>Phạm Vi Check-in</label>
        <div class="val" style="font-size: 14px;">${scopeTitle}</div>
      </div>
      <div class="info-item">
        <label>Khách Hàng Đại Diện</label>
        <div class="val">${item.TenKhachHang || 'N/A'}</div>
      </div>
      <div class="info-item">
        <label>Số Điện Thoại</label>
        <div class="val">${item.SDT || 'Chưa cập nhật'}</div>
      </div>
      <div class="info-item">
        <label>Số Khách Đã Khai Báo</label>
        <div class="val">${item.SoLuongKhachKhaiBao || 0} khách</div>
      </div>
      <div class="info-item" style="grid-column: 1 / -1;">
        <label>Phòng Giao Chìa Khóa Đợt Này</label>
        <div class="val" style="margin-top: 6px; display: flex; gap: 6px; flex-wrap: wrap;">${roomsList}</div>
      </div>
    </div>
  `;

  document.getElementById('modalCheckin').classList.add('show');
}

async function executeCheckin() {
  if (!currentCheckinMaPNP) return;
  const maLT = getLoggedReceptionistMaLT();
  if (!maLT) {
    showToast('Vui lòng đăng nhập tài khoản Lễ tân để thực hiện check-in!', '⚠️');
    return;
  }
  const btn = document.getElementById('btnConfirmCheckinExec');
  if (btn) {
    btn.disabled = true;
    btn.innerText = 'Đang xử lý...';
  }

  try {
    const payload = {
      MaPNP: currentCheckinMaPNP,
      MaLT: maLT
    };
    if (currentCheckinMaPhong) {
      payload.MaPhong = currentCheckinMaPhong;
    }

    const res = await leTanFetch(`${API_BASE}/letan/checkin`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const json = await res.json();
    if (!json.success) {
      showToast(json.error || 'Check-in thất bại', '❌');
      return;
    }

    showToast(json.message || `Check-in thành công!`, '🔑');
    closeModal('modalCheckin');
    loadReceptions();
    if (currentMainTab === 'rooms') {
      loadRoomStatusMap();
    }
  } catch (err) {
    console.error(err);
    showToast('Lỗi kết nối máy chủ khi thực hiện Check-in', '❌');
  } finally {
    btn.disabled = false;
    btn.innerText = '✅ XÁC NHẬN CHECK-IN NGAY';
  }
}

// =========================================================================
// 4. THỦ TỤC CHECK-OUT & TỔNG KẾT HÓA ĐƠN (HỖ TRỢ TỪNG PHÒNG HOẶC TOÀN PHIẾU)
// =========================================================================
let selectedCheckoutPaymentMethod = 'TienMat';
let currentCheckoutBillSnapshot = null;

async function openCheckoutModal(maPNP, viewOnly = false, maPhongTarget = null) {
  currentCheckoutMaPNP = maPNP;
  currentCheckoutMaPhong = maPhongTarget;
  selectedCheckoutPaymentMethod = 'TienMat';
  const body = document.getElementById('checkoutConfirmBody');
  const btnExec = document.getElementById('btnConfirmCheckoutExec');

  btnExec.style.display = viewOnly ? 'none' : 'inline-flex';
  if (!viewOnly) {
    btnExec.innerText = maPhongTarget
      ? `🏁 XÁC NHẬN TRẢ PHÒNG ${maPhongTarget} & THU TIỀN`
      : `🏁 HOÀN TẤT CHECK-OUT & XUẤT BILL`;
  }
  body.innerHTML = `<div class="empty-state">⏳ Đang tính toán bảng quyết toán hóa đơn cho phiếu ${maPNP}...</div>`;
  document.getElementById('modalCheckout').classList.add('show');

  try {
    const payload = { MaPNP: maPNP };
    if (maPhongTarget) payload.MaPhong = maPhongTarget;

    const res = await leTanFetch(`${API_BASE}/letan/tinhtiencheckout`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const json = await res.json();
    if (!json.success) {
      body.innerHTML = `<div class="empty-state" style="color: var(--rec-err);">${json.error || 'Không thể tính tiền Check-out'}</div>`;
      return;
    }

    const b = json.data;
    currentCheckoutBillSnapshot = b;
    const rooms = b.DanhSachPhong || [];
    const services = b.DanhSachDichVu || [];

    const scopeBanner = maPhongTarget ? `
      <div style="background: var(--rec-gold-soft); border: 1.5px solid var(--rec-gold); padding: 12px 16px; border-radius: 10px; font-size: 13px; color: var(--rec-navy);">
        📌 <strong>CHẾ ĐỘ CHECK-OUT RIÊNG TỪNG PHÒNG:</strong> Bạn đang làm thủ tục trả phòng riêng cho <strong>Phòng ${maPhongTarget}</strong> thuộc phiếu <strong>${b.MaPNP}</strong>. Các phòng khác đang ở trong cùng phiếu vẫn tiếp tục lưu trú bình thường.
      </div>
    ` : '';

    const qrAmount = Math.max(0, Math.round(Number(b.TongThanhToan || 0)));
    const qrMemo = maPhongTarget ? `CHECKOUT ${b.MaPNP} ${maPhongTarget}` : `CHECKOUT ${b.MaPNP}`;
    const vietQrUrl = `https://img.vietqr.io/image/MB-0589822346-compact2.png?amount=${qrAmount}&addInfo=${encodeURIComponent(qrMemo)}&accountName=${encodeURIComponent('NGUYEN HOANG ANH')}`;

    body.innerHTML = `
      ${scopeBanner}
      <div class="info-grid">
        <div class="info-item">
          <label>Mã Phiếu &amp; Khách Hàng</label>
          <div class="val">${b.MaPNP} — ${b.TenKhachHang || 'Khách hàng'}</div>
        </div>
        <div class="info-item">
          <label>Số Đêm Lưu Trú Tính Toán</label>
          <div class="val" style="color: var(--rec-gold-text);">${b.SoDemLuuTru} đêm</div>
        </div>
        <div class="info-item">
          <label>Giờ Nhận Phòng</label>
          <div class="val">${formatDateTime(b.ThoiGianCheckIn || b.NgayNhanDuKien)}</div>
        </div>
        <div class="info-item">
          <label>Thời Điểm Quyết Toán</label>
          <div class="val">${formatDateTime(b.ThoiDiemTinhToan)}</div>
        </div>
      </div>

      <div>
        <h4 style="margin: 0 0 8px 0; color: var(--rec-navy); font-size: 13.5px; text-transform: uppercase;">
          1. Chi Tiết Tiền Phòng (${rooms.length} phòng)
        </h4>
        <table class="sub-table">
          <thead>
            <tr>
              <th>Phòng</th>
              <th>Loại Phòng</th>
              <th>Đơn Giá / Đêm</th>
              <th>Số Đêm</th>
              <th style="text-align: right;">Thành Tiền</th>
            </tr>
          </thead>
          <tbody>
            ${rooms.map(r => `
              <tr>
                <td><span class="room-tag">Phòng ${r.MaPhong}</span></td>
                <td>${r.TenLoaiPhong || r.MaLoaiPhong}</td>
                <td>${formatMoney(r.DonGia)}</td>
                <td>${r.SoDem} đêm</td>
                <td style="text-align: right; font-weight: 700;">${formatMoney(r.ThanhTienPhong)}</td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      </div>

      <div>
        <h4 style="margin: 0 0 8px 0; color: var(--rec-navy); font-size: 13.5px; text-transform: uppercase;">
          2. Dịch Vụ Phát Sinh Đã Sử Dụng (${services.length} dịch vụ)
        </h4>
        ${services.length === 0 ? `
          <div style="background: var(--rec-section); padding: 12px 16px; border-radius: 8px; font-size: 13px; color: var(--rec-muted); border: 1px solid var(--rec-line);">
            Không có dịch vụ phát sinh thêm.
          </div>
        ` : `
          <table class="sub-table">
            <thead>
              <tr>
                <th>Tên Dịch Vụ</th>
                <th>Thời Gian</th>
                <th>Đơn Giá</th>
                <th>Số Lượng</th>
                <th style="text-align: right;">Thành Tiền</th>
              </tr>
            </thead>
            <tbody>
              ${services.map(s => `
                <tr>
                  <td><strong>${s.TenDV}</strong></td>
                  <td>${formatDateTime(s.ThoiGianSuDung)}</td>
                  <td>${formatMoney(s.DonGia)}</td>
                  <td>${s.SoLuong}</td>
                  <td style="text-align: right; font-weight: 700;">${formatMoney(s.ThanhTien)}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        `}
      </div>

      <div style="background: var(--rec-section); border: 1.5px solid var(--rec-gold); border-radius: 12px; padding: 18px 20px;">
        <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 13.5px;">
          <span style="color: var(--rec-muted);">Tổng tiền phòng (${b.SoDemLuuTru} đêm):</span>
          <strong>${formatMoney(b.TongTienPhong)}</strong>
        </div>
        <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 13.5px;">
          <span style="color: var(--rec-muted);">Tổng tiền dịch vụ phát sinh:</span>
          <strong>${formatMoney(b.TongTienDichVu)}</strong>
        </div>
        <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 13.5px;">
          <span style="color: var(--rec-muted);">Phụ thu trả phòng muộn (${b.SoGioTraMuon} giờ):</span>
          <strong style="color: ${b.PhuThuTraMuon > 0 ? 'var(--rec-warn)' : 'var(--rec-text)'};">+${formatMoney(b.PhuThuTraMuon)} (${b.GhiChuPhuThu})</strong>
        </div>
        ${b.TienKhuyenMai > 0 ? `
          <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 13.5px; color: var(--rec-ok);">
            <span>Khuyến mãi áp dụng (${b.MaKM}):</span>
            <strong>-${formatMoney(b.TienKhuyenMai)}</strong>
          </div>
        ` : ''}
        <div style="border-top: 1px solid var(--rec-line); margin-top: 10px; padding-top: 12px; display: flex; justify-content: space-between; align-items: center;">
          <span style="font-size: 15px; font-weight: 800; color: var(--rec-navy); text-transform: uppercase;">TỔNG QUYẾT TOÁN HÓA ĐƠN:</span>
          <span style="font-size: 22px; font-weight: 800; color: var(--rec-gold-text);">${formatMoney(b.TongThanhToan)}</span>
        </div>
      </div>

      ${!viewOnly ? `
        <div style="background: #FFFFFF; border: 1px solid var(--rec-line); border-radius: 12px; padding: 16px 20px;">
          <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 12px;">
            <div style="font-size: 13.5px; font-weight: 800; color: var(--rec-navy); text-transform: uppercase;">
              💳 Phương thức thanh toán quyết toán tại quầy
            </div>
            <button type="button" class="btn btn-xs btn-sky" onclick="printCheckoutInvoice()">
              🖨️ Xem &amp; In Hóa Đơn A4
            </button>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
            <label id="chkPayLabel_TienMat" onclick="selectCheckoutPayMethod('TienMat')" style="display: flex; align-items: center; gap: 10px; padding: 12px 16px; border-radius: 10px; border: 2px solid var(--rec-ok); background: #F4FBF7; cursor: pointer; transition: all 0.2s;">
              <input type="radio" name="checkoutPayMethod" value="TienMat" checked style="accent-color: var(--rec-ok); width: 17px; height: 17px;">
              <div>
                <div style="font-weight: 800; color: var(--rec-navy); font-size: 13.5px;">💵 Thu Tiền Mặt Tại Quầy</div>
                <div style="font-size: 11.5px; color: var(--rec-muted);">Nhận tiền mặt trực tiếp &amp; xuất hóa đơn ngay</div>
              </div>
            </label>

            <label id="chkPayLabel_ChuyenKhoan" onclick="selectCheckoutPayMethod('ChuyenKhoan')" style="display: flex; align-items: center; gap: 10px; padding: 12px 16px; border-radius: 10px; border: 1px solid var(--rec-line); background: var(--rec-section); cursor: pointer; transition: all 0.2s;">
              <input type="radio" name="checkoutPayMethod" value="ChuyenKhoan" style="accent-color: var(--rec-primary); width: 17px; height: 17px;">
              <div>
                <div style="font-weight: 800; color: var(--rec-navy); font-size: 13.5px;">📲 Chuyển Khoản VietQR MBBank</div>
                <div style="font-size: 11.5px; color: var(--rec-muted);">Hiển thị mã QR động đúng số tiền quyết toán</div>
              </div>
            </label>
          </div>

          <div id="checkoutVietQRBox" style="display: none; margin-top: 16px; padding-top: 16px; border-top: 1px dashed var(--rec-line);">
            <div style="display: grid; grid-template-columns: 200px 1fr; gap: 18px; align-items: center;">
              <div style="text-align: center; background: #fff; padding: 10px; border-radius: 10px; border: 1px solid var(--rec-line);">
                <img src="${vietQrUrl}" alt="VietQR MBBank" style="width: 180px; height: 180px; display: block; margin: 0 auto; border-radius: 6px;">
                <div style="font-size: 11px; color: var(--rec-navy); font-weight: 700; margin-top: 6px;">Quét mã Napas 247</div>
              </div>
              <div style="display: flex; flex-direction: column; gap: 8px; font-size: 13px;">
                <div style="background: var(--rec-section); padding: 9px 12px; border-radius: 8px; border: 1px solid var(--rec-line);">
                  <span style="color: var(--rec-muted); font-size: 11px; display: block;">NGÂN HÀNG THỤ HƯỞNG</span>
                  <strong style="color: var(--rec-primary); font-size: 14px;">MBBank (Ngân hàng Quân Đội)</strong>
                </div>
                <div style="background: var(--rec-section); padding: 9px 12px; border-radius: 8px; border: 1px solid var(--rec-line); display: flex; justify-content: space-between; align-items: center;">
                  <div>
                    <span style="color: var(--rec-muted); font-size: 11px; display: block;">SỐ TÀI KHOẢN &amp; CHỦ TK</span>
                    <strong style="color: var(--rec-gold-text); font-size: 14.5px;">0589822346 — NGUYEN HOANG ANH</strong>
                  </div>
                  <button type="button" class="btn btn-xs btn-outline" onclick="copyCheckoutText('0589822346', this)">📋 Copy STK</button>
                </div>
                <div style="background: var(--rec-section); padding: 9px 12px; border-radius: 8px; border: 1px solid var(--rec-line); display: flex; justify-content: space-between; align-items: center;">
                  <div>
                    <span style="color: var(--rec-muted); font-size: 11px; display: block;">SỐ TIỀN &amp; NỘI DUNG CK</span>
                    <strong style="color: var(--rec-ok); font-size: 14.5px;">${formatMoney(qrAmount)}</strong> • <strong style="color: var(--rec-navy);">${qrMemo}</strong>
                  </div>
                  <button type="button" class="btn btn-xs btn-outline" onclick="copyCheckoutText('${qrMemo}', this)">📋 Copy ND</button>
                </div>
              </div>
            </div>
          </div>
        </div>
      ` : `
        <div style="display: flex; justify-content: flex-end;">
          <button type="button" class="btn btn-sky" onclick="printCheckoutInvoice()">
            🖨️ In Hóa Đơn Quyết Toán (A4)
          </button>
        </div>
      `}
    `;
  } catch (err) {
    console.error(err);
    body.innerHTML = `<div class="empty-state" style="color: var(--rec-err);">Lỗi kết nối máy chủ.</div>`;
  }
}

function selectCheckoutPayMethod(method) {
  selectedCheckoutPaymentMethod = method;
  const lblTM = document.getElementById('chkPayLabel_TienMat');
  const lblCK = document.getElementById('chkPayLabel_ChuyenKhoan');
  const qrBox = document.getElementById('checkoutVietQRBox');

  if (method === 'ChuyenKhoan') {
    if (lblCK) {
      lblCK.style.border = '2px solid var(--rec-primary)';
      lblCK.style.background = 'var(--rec-highlight)';
    }
    if (lblTM) {
      lblTM.style.border = '1px solid var(--rec-line)';
      lblTM.style.background = 'var(--rec-section)';
    }
    if (qrBox) qrBox.style.display = 'block';
  } else {
    if (lblTM) {
      lblTM.style.border = '2px solid var(--rec-ok)';
      lblTM.style.background = '#F4FBF7';
    }
    if (lblCK) {
      lblCK.style.border = '1px solid var(--rec-line)';
      lblCK.style.background = 'var(--rec-section)';
    }
    if (qrBox) qrBox.style.display = 'none';
  }
}

function copyCheckoutText(text, btnEl) {
  navigator.clipboard.writeText(text).then(() => {
    const old = btnEl.innerText;
    btnEl.innerText = '✅ Đã copy';
    setTimeout(() => { btnEl.innerText = old; }, 1500);
  });
}

function printCheckoutInvoice() {
  if (!currentCheckoutBillSnapshot) return;
  const b = currentCheckoutBillSnapshot;
  const rooms = b.DanhSachPhong || [];
  const services = b.DanhSachDichVu || [];
  const payMethodText = selectedCheckoutPaymentMethod === 'ChuyenKhoan' ? 'Chuyển khoản VietQR MBBank' : 'Tiền mặt tại quầy';

  const win = window.open('', '_blank', 'width=850,height=900');
  if (!win) {
    showToast('Vui lòng cho phép mở cửa sổ bật lên (Popup) để in hóa đơn!', '⚠️');
    return;
  }

  win.document.write(`
    <!DOCTYPE html>
    <html lang="vi">
    <head>
      <meta charset="UTF-8">
      <title>Hóa Đơn Quyết Toán - ${b.MaPNP}</title>
      <style>
        body { font-family: 'Segoe UI', Tahoma, sans-serif; color: #172B3A; padding: 30px; max-width: 800px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #C6A15B; padding-bottom: 16px; margin-bottom: 20px; }
        .brand { font-size: 22px; font-weight: 800; color: #172B3A; }
        .sub { font-size: 12px; color: #6B7280; margin-top: 4px; }
        h2 { text-align: center; text-transform: uppercase; margin: 16px 0; color: #172B3A; }
        .meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; background: #FAF9F6; padding: 14px; border-radius: 8px; border: 1px solid #E3DED2; font-size: 13px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 18px; font-size: 13px; }
        th, td { border: 1px solid #E3DED2; padding: 9px 12px; text-align: left; }
        th { background: #F7FAFC; font-weight: 700; color: #172B3A; }
        .summary { margin-left: auto; width: 340px; border: 1.5px solid #172B3A; border-radius: 8px; padding: 14px; font-size: 13.5px; }
        .sum-row { display: flex; justify-content: space-between; margin-bottom: 6px; }
        .sum-total { border-top: 1px solid #E3DED2; padding-top: 8px; margin-top: 8px; font-weight: 800; font-size: 16px; color: #806223; display: flex; justify-content: space-between; }
        .sign-row { display: flex; justify-content: space-between; margin-top: 40px; text-align: center; font-size: 13px; }
        @media print { .no-print { display: none; } }
      </style>
    </head>
    <body>
      <div class="no-print" style="text-align: right; margin-bottom: 15px;">
        <button onclick="window.print()" style="padding: 8px 18px; background: #172B3A; color: #fff; border: none; border-radius: 6px; font-weight: 700; cursor: pointer;">🖨️ In Hóa Đơn Ngay</button>
      </div>
      <div class="header">
        <div>
          <div class="brand">☁ SKY HOTEL</div>
          <div class="sub">Địa chỉ: 123 Thanh Thái, Quận 10, TP. Hồ Chí Minh</div>
          <div class="sub">Hotline: 028 3930 8888 • Email: contact@skyhotel.vn</div>
        </div>
        <div style="text-align: right;">
          <div style="font-weight: 700;">MÃ PHIẾU: ${b.MaPNP}</div>
          <div class="sub">Ngày in: ${new Date().toLocaleString('vi-VN')}</div>
          <div class="sub">Hình thức TT: <strong>${payMethodText}</strong></div>
        </div>
      </div>

      <h2>HÓA ĐƠN QUYẾT TOÁN DỊCH VỤ LƯU TRÚ</h2>

      <div class="meta-grid">
        <div><strong>Khách hàng:</strong> ${b.TenKhachHang || 'Khách hàng'}</div>
        <div><strong>Số đêm tính phí:</strong> ${b.SoDemLuuTru} đêm</div>
        <div><strong>Giờ nhận phòng:</strong> ${b.ThoiGianCheckIn || b.NgayNhanDuKien}</div>
        <div><strong>Giờ trả phòng:</strong> ${b.ThoiDiemTinhToan}</div>
      </div>

      <h4>1. Chi Tiết Tiền Phòng</h4>
      <table>
        <thead>
          <tr>
            <th>Số Phòng</th>
            <th>Hạng Phòng</th>
            <th>Đơn Giá / Đêm</th>
            <th>Số Đêm</th>
            <th style="text-align: right;">Thành Tiền</th>
          </tr>
        </thead>
        <tbody>
          ${rooms.map(r => `
            <tr>
              <td>Phòng ${r.MaPhong}</td>
              <td>${r.TenLoaiPhong || r.MaLoaiPhong}</td>
              <td>${formatMoney(r.DonGia)}</td>
              <td>${r.SoDem} đêm</td>
              <td style="text-align: right; font-weight: 700;">${formatMoney(r.ThanhTienPhong)}</td>
            </tr>
          `).join('')}
        </tbody>
      </table>

      ${services.length > 0 ? `
        <h4>2. Dịch Vụ Phát Sinh</h4>
        <table>
          <thead>
            <tr>
              <th>Tên Dịch Vụ</th>
              <th>Thời Gian</th>
              <th>Đơn Giá</th>
              <th>Số Lượng</th>
              <th style="text-align: right;">Thành Tiền</th>
            </tr>
          </thead>
          <tbody>
            ${services.map(s => `
              <tr>
                <td>${s.TenDV}</td>
                <td>${s.ThoiGianSuDung || '-'}</td>
                <td>${formatMoney(s.DonGia)}</td>
                <td>${s.SoLuong}</td>
                <td style="text-align: right; font-weight: 700;">${formatMoney(s.ThanhTien)}</td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      ` : ''}

      <div class="summary">
        <div class="sum-row"><span>Tổng tiền phòng:</span><strong>${formatMoney(b.TongTienPhong)}</strong></div>
        <div class="sum-row"><span>Dịch vụ phát sinh:</span><strong>${formatMoney(b.TongTienDichVu)}</strong></div>
        <div class="sum-row"><span>Phụ thu trả muộn (${b.SoGioTraMuon}h):</span><strong>+${formatMoney(b.PhuThuTraMuon)}</strong></div>
        ${b.TienKhuyenMai > 0 ? `<div class="sum-row"><span>Khuyến mãi (${b.MaKM}):</span><strong>-${formatMoney(b.TienKhuyenMai)}</strong></div>` : ''}
        <div class="sum-total"><span>TỔNG THANH TOÁN:</span><span>${formatMoney(b.TongThanhToan)}</span></div>
      </div>

      <div class="sign-row">
        <div style="width: 45%;">
          <strong>Khách hàng ký tên</strong>
          <div style="font-size: 11px; color: #6B7280; margin-top: 4px;">(Ký và ghi rõ họ tên)</div>
        </div>
        <div style="width: 45%;">
          <strong>Lễ tân thu ngân</strong>
          <div style="font-size: 11px; color: #6B7280; margin-top: 4px;">(Ký và đóng dấu xác nhận)</div>
        </div>
      </div>
    </body>
    </html>
  `);
  win.document.close();
}

async function executeCheckout() {
  if (!currentCheckoutMaPNP) return;
  const maLT = getLoggedReceptionistMaLT();
  if (!maLT) {
    showToast('Vui lòng đăng nhập tài khoản Lễ tân để thực hiện check-out!', '⚠️');
    return;
  }
  const btn = document.getElementById('btnConfirmCheckoutExec');
  if (btn) {
    btn.disabled = true;
    btn.innerText = 'Đang quyết toán...';
  }

  try {
    const payload = {
      MaPNP: currentCheckoutMaPNP,
      PhuongThucThanhToan: selectedCheckoutPaymentMethod,
      MaLT: maLT
    };
    if (currentCheckoutMaPhong) {
      payload.MaPhong = currentCheckoutMaPhong;
    }

    const res = await leTanFetch(`${API_BASE}/letan/checkout`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const json = await res.json();
    if (!json.success) {
      showToast(json.error || 'Check-out thất bại', '❌');
      return;
    }

    showToast(json.message || `Đã hoàn tất Check-out!`, '🏁');
    closeModal('modalCheckout');
    loadReceptions();
    if (currentMainTab === 'rooms') {
      loadRoomStatusMap();
    }
  } catch (err) {
    console.error(err);
    showToast('Lỗi kết nối máy chủ khi thực hiện Check-out', '❌');
  } finally {
    btn.disabled = false;
    btn.innerText = '🏁 HOÀN TẤT CHECK-OUT & XUẤT BILL';
  }
}

// =========================================================================
// 5. XEM DỊCH VỤ PHÁT SINH CỦA PHIẾU
// =========================================================================
async function openServicesModal(maPNP) {
  document.getElementById('serviceModalMaPNP').innerText = maPNP;
  const body = document.getElementById('servicesModalBody');
  body.innerHTML = `<div class="empty-state">⏳ Đang tải danh sách dịch vụ...</div>`;
  document.getElementById('modalServices').classList.add('show');

  try {
    const res = await leTanFetch(`${API_BASE}/letan/xemctphieu`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ MaPNP: maPNP })
    });
    const json = await res.json();
    if (!json.success) {
      body.innerHTML = `<div class="empty-state" style="color: var(--rec-err);">${json.error || 'Không thể tải dịch vụ'}</div>`;
      return;
    }

    const services = (json.data && json.data.DanhSachDichVu) || [];
    if (services.length === 0) {
      body.innerHTML = `<div class="empty-state">Phiếu ${maPNP} hiện chưa đặt dịch vụ bổ sung nào (Thuê xe, Ẩm thực, Giặt ủi).</div>`;
      return;
    }

    const totalSvc = services.reduce((sum, s) => sum + Number(s.ThanhTien || 0), 0);

    body.innerHTML = `
      <table class="sub-table">
        <thead>
          <tr>
            <th>Mã DV</th>
            <th>Tên Dịch Vụ</th>
            <th>Thời Gian Sử Dụng</th>
            <th>Đơn Giá</th>
            <th>Số Lượng</th>
            <th style="text-align: right;">Thành Tiền</th>
          </tr>
        </thead>
        <tbody>
          ${services.map(s => `
            <tr>
              <td><strong>${s.MaDV}</strong></td>
              <td style="font-weight: 700; color: var(--rec-navy);">${s.TenDV}</td>
              <td>${formatDateTime(s.ThoiGianSuDung)}</td>
              <td>${formatMoney(s.DonGia)}</td>
              <td>${s.SoLuong}</td>
              <td style="text-align: right; color: var(--rec-gold-text); font-weight: 700;">${formatMoney(s.ThanhTien)}</td>
            </tr>
          `).join('')}
        </tbody>
      </table>
      <div style="text-align: right; font-size: 16px; font-weight: 800; color: var(--rec-gold-text);">
        Tổng chi phí dịch vụ: ${formatMoney(totalSvc)}
      </div>
    `;
  } catch (err) {
    console.error(err);
    body.innerHTML = `<div class="empty-state" style="color: var(--rec-err);">Lỗi kết nối máy chủ.</div>`;
  }
}

// =========================================================================
// 6. THỦ TỤC HỦY PHIẾU ĐẶT PHÒNG HOẶC HỦY RIÊNG TỪNG PHÒNG (LỄ TÂN)
// =========================================================================
async function openCancelSlipModal(maPNP, maPhongTarget = null) {
  currentCancelMaPNP = maPNP;
  currentCancelMaPhong = maPhongTarget;
  currentCancelPreviewData = null;

  const titleEl = document.getElementById('cancelSlipModalTitle');
  const subEl = document.getElementById('cancelSlipSubtitle');
  const body = document.getElementById('cancelSlipModalBody');
  const btnExec = document.getElementById('btnConfirmCancelSlipExec');

  if (titleEl) {
    titleEl.innerText = maPhongTarget
      ? `Xác Nhận Hủy Riêng Phòng ${maPhongTarget} (Phiếu ${maPNP})`
      : `Xác Nhận Hủy Toàn Bộ Phiếu ${maPNP}`;
  }
  if (subEl) {
    subEl.innerText = maPhongTarget
      ? `Giải phóng riêng Phòng ${maPhongTarget} về trạng thái trống & tính tiền hoàn cho phòng này`
      : `Giải phóng toàn bộ phòng trong phiếu & tính toán tiền hoàn tự động theo chính sách`;
  }

  btnExec.disabled = true;
  btnExec.innerText = maPhongTarget
    ? `🚫 XÁC NHẬN HỦY PHÒNG ${maPhongTarget}`
    : `🚫 XÁC NHẬN HỦY PHIẾU & HOÀN TIỀN`;

  body.innerHTML = `<div class="empty-state">⏳ Đang tính toán chính sách hoàn tiền cho ${maPhongTarget ? 'Phòng ' + maPhongTarget : 'phiếu ' + maPNP}...</div>`;
  document.getElementById('modalCancelSlip').classList.add('show');

  try {
    const queryUrl = maPhongTarget
      ? `${API_BASE}/letan/tinhtienhoan?MaPNP=${encodeURIComponent(maPNP)}&MaPhong=${encodeURIComponent(maPhongTarget)}`
      : `${API_BASE}/letan/tinhtienhoan?MaPNP=${encodeURIComponent(maPNP)}`;
    const res = await leTanFetch(queryUrl);
    const json = await res.json();

    if (!json.success) {
      body.innerHTML = `<div class="empty-state" style="color: var(--rec-err);">${json.error || 'Không thể tính toán tiền hoàn cho phiếu này'}</div>`;
      return;
    }

    const d = json.data;
    currentCancelPreviewData = d;
    btnExec.disabled = false;

    const item = allReceptions.find(r => r.MaPNP === maPNP) || {};
    const roomsList = maPhongTarget
      ? `<span class="room-tag">Phòng ${maPhongTarget}</span>`
      : (item.DanhSachPhong || []).map(p => `<span class="room-tag">Phòng ${p}</span>`).join(' ');

    let badgeColor = '#15803D';
    let badgeBg = '#F4FBF7';
    let badgeBorder = '#BBF7D0';
    if (d.TyLeHoan === 50) {
      badgeColor = '#B45309';
      badgeBg = '#FFFBEB';
      badgeBorder = '#FDE68A';
    } else if (d.TyLeHoan === 0) {
      badgeColor = '#B91C1C';
      badgeBg = '#FEF2F2';
      badgeBorder = '#FECACA';
    }

    const scopeNoticeHtml = maPhongTarget ? `
      <div style="background: #FEF2F2; border: 1.5px solid #FECACA; border-radius: 10px; padding: 12px 16px; font-size: 13px; color: #B91C1C;">
        📌 <strong>CHẾ ĐỘ HỦY RIÊNG TỪNG PHÒNG:</strong> Bạn đang thực hiện hủy riêng <strong>Phòng ${maPhongTarget}</strong> thuộc phiếu <strong>${d.MaPNP}</strong> (Đang có ${d.SoPhongDangHoatDong || 1} phòng hoạt động). Các phòng còn lại trong phiếu vẫn được giữ nguyên!
      </div>
    ` : '';

    body.innerHTML = `
      ${scopeNoticeHtml}
      <div class="info-grid">
        <div class="info-item">
          <label>Mã Phiếu &amp; Khách Hàng</label>
          <div class="val">${d.MaPNP} — ${item.TenKhachHang || 'Khách hàng'}</div>
        </div>
        <div class="info-item">
          <label>Trạng Thái Hiện Tại</label>
          <div class="val">${getStatusBadgeHtml(d.TrangThaiHienTai)}</div>
        </div>
        <div class="info-item">
          <label>Nhận Phòng Dự Kiến</label>
          <div class="val">${formatDateTime(d.NgayNhanPhong)}</div>
        </div>
        <div class="info-item">
          <label>Thời Gian Còn Lại Trước Check-in</label>
          <div class="val" style="color: var(--rec-gold-text);">${d.SoGioTruocCheckIn} giờ (${d.SoDem} đêm lưu trú)</div>
        </div>
        <div class="info-item" style="grid-column: 1 / -1;">
          <label>${maPhongTarget ? 'Phòng Sẽ Được Giải Phóng Đợt Này' : 'Toàn Bộ Phòng Sẽ Được Giải Phóng Về Trạng Thái Trống'}</label>
          <div class="val" style="margin-top: 6px; display: flex; gap: 6px; flex-wrap: wrap;">${roomsList || 'Chưa gán phòng'}</div>
        </div>
      </div>

      <!-- Bảng tính toán tiền hoàn -->
      <div style="background: var(--rec-section); border: 1.5px solid var(--rec-line); border-radius: 12px; padding: 18px 20px;">
        <div style="font-size: 12px; font-weight: 800; text-transform: uppercase; color: var(--rec-navy); margin-bottom: 12px; letter-spacing: 0.5px;">
          📊 Bảng Tính Toán Hoàn Tiền (${maPhongTarget ? 'Riêng Phòng ' + maPhongTarget : 'Toàn Phiếu'})
        </div>

        <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 13.5px;">
          <span style="color: var(--rec-muted);">Tổng giá trị toàn phiếu ban đầu:</span>
          <strong>${formatMoney(d.TongTienPhieu)}</strong>
        </div>

        ${maPhongTarget ? `
          <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 13.5px;">
            <span style="color: var(--rec-muted);">Giá trị tiền Phòng ${maPhongTarget} (${d.SoDem} đêm):</span>
            <strong style="color: var(--rec-gold-text);">${formatMoney(d.TienPhongMucTieu)}</strong>
          </div>
        ` : ''}

        <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 13.5px;">
          <span style="color: var(--rec-muted);">Số tiền đã thanh toán áp dụng cho phạm vi hủy:</span>
          <strong style="color: var(--rec-primary);">${formatMoney(d.DaThanhToan)}</strong>
        </div>

        <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 13.5px;">
          <span style="color: var(--rec-muted);">Tỷ lệ hoàn tiền áp dụng:</span>
          <strong style="color: ${badgeColor};">${d.TyLeHoan}%</strong>
        </div>

        <div style="display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 13.5px;">
          <span style="color: var(--rec-muted);">Phí hủy / Khấu trừ:</span>
          <strong style="color: var(--rec-err);">${formatMoney(d.PhiHuy)}</strong>
        </div>

        <div style="border-top: 1px solid var(--rec-line); padding-top: 12px; display: flex; justify-content: space-between; align-items: center;">
          <span style="font-size: 14.5px; font-weight: 800; color: var(--rec-navy); text-transform: uppercase;">SỐ TIỀN HOÀN LẠI CHO KHÁCH:</span>
          <span style="font-size: 22px; font-weight: 800; color: var(--rec-ok);">${formatMoney(d.SoTienHoan)}</span>
        </div>

        <div style="margin-top: 12px; padding: 10px 14px; border-radius: 8px; background: ${badgeBg}; border: 1px solid ${badgeBorder}; color: ${badgeColor}; font-size: 12.5px; font-weight: 600;">
          📌 ${d.ChinhSachApDung}
        </div>
      </div>

      <!-- Quy định chính sách tham chiếu (nguồn chuẩn dùng chung) -->
      <div style="background: var(--rec-section); border: 1px dashed var(--rec-line); border-radius: 10px; padding: 12px 16px; font-size: 12px; color: var(--rec-muted);">
        <strong style="color: var(--rec-navy); display: block; margin-bottom: 4px;">📋 Quy định hoàn tiền chuẩn của Sky Hotel:</strong>
        ${(window.HotelAPI && window.HotelAPI.policy) ? window.HotelAPI.policy.renderBulletListHtml() : ''}
      </div>

      <!-- Nhập lý do hủy -->
      <div class="form-group" style="margin-bottom: 0;">
        <label class="form-label">Lý do hủy đặt phòng * (Ghi nhận vào biên bản hệ thống)</label>
        <div style="display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 8px;">
          <button type="button" class="btn btn-xs btn-outline" onclick="fillPresetCancelReason('Khách hàng gọi điện báo hủy đổi lịch trình')">📞 Khách báo đổi lịch</button>
          <button type="button" class="btn btn-xs btn-outline" onclick="fillPresetCancelReason('Khách không đến nhận phòng (No-show)')">🚫 Khách No-show</button>
          <button type="button" class="btn btn-xs btn-outline" onclick="fillPresetCancelReason('Khách yêu cầu hủy tại quầy để đặt lại hạng phòng khác')">🔄 Đổi hạng phòng khác</button>
          <button type="button" class="btn btn-xs btn-outline" onclick="fillPresetCancelReason('Hết thời gian giữ chỗ chờ thanh toán tại quầy')">⏳ Quá hạn thanh toán</button>
        </div>
        <textarea id="cancelReasonInput" class="form-input" rows="2" placeholder="Nhập lý do hủy cụ thể (VD: Khách hàng gọi báo hủy đổi lịch trình công tác)...">Khách hàng yêu cầu hủy tại quầy lễ tân</textarea>
      </div>
    `;
  } catch (err) {
    console.error(err);
    body.innerHTML = `<div class="empty-state" style="color: var(--rec-err);">Lỗi kết nối máy chủ khi tính toán hoàn tiền.</div>`;
  }
}

function fillPresetCancelReason(reasonText) {
  const input = document.getElementById('cancelReasonInput');
  if (input) input.value = reasonText;
}

async function executeCancelSlip() {
  if (!currentCancelMaPNP) return;
  const reasonEl = document.getElementById('cancelReasonInput');
  const lyDo = reasonEl ? reasonEl.value.trim() : 'Hủy bởi lễ tân tại quầy';

  if (!lyDo) {
    showToast('Vui lòng nhập lý do hủy phiếu đặt phòng!', '⚠️');
    if (reasonEl) reasonEl.focus();
    return;
  }

  const btn = document.getElementById('btnConfirmCancelSlipExec');
  if (btn) {
    btn.disabled = true;
    btn.innerText = 'Đang xử lý hủy...';
  }

  try {
    const payload = {
      MaPNP: currentCancelMaPNP,
      LyDoHuy: lyDo
    };
    if (currentCancelMaPhong) {
      payload.MaPhong = currentCancelMaPhong;
    }

    const res = await leTanFetch(`${API_BASE}/letan/huyphieu`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const json = await res.json();
    if (!json.success) {
      showToast(json.message || json.error || 'Hủy đặt phòng thất bại', '❌');
      return;
    }

    const resData = json.data || {};
    const nestedHoan = (resData.data && resData.data.hoanTien) || resData.hoanTien || {};
    const refundAmount = resData.SoTienHoan != null ? resData.SoTienHoan : (nestedHoan.soTienHoan || 0);
    const refundRate = resData.TyLeHoan != null ? resData.TyLeHoan : (nestedHoan.tiLeHoan || 0);
    const targetLabel = currentCancelMaPhong ? `Phòng ${currentCancelMaPhong} (Phiếu ${currentCancelMaPNP})` : `phiếu ${currentCancelMaPNP}`;
    const refundMsg = refundAmount > 0
      ? `Đã hủy ${targetLabel}! Hoàn khách: ${formatMoney(refundAmount)} (${refundRate}%)`
      : `Đã hủy ${targetLabel} thành công!`;

    showToast(refundMsg, '🚫');
    closeModal('modalCancelSlip');
    loadReceptions();
    if (currentMainTab === 'rooms') {
      loadRoomStatusMap();
    }
  } catch (err) {
    console.error(err);
    showToast('Lỗi kết nối máy chủ khi hủy phiếu', '❌');
  } finally {
    btn.disabled = false;
    btn.innerText = '🚫 XÁC NHẬN HỦY & HOÀN TIỀN';
  }
}

// =========================================================================
// 7. XEM CHI TIẾT HỒ SƠ HỦY ĐẶT PHÒNG & IN BIÊN BẢN HOÀN TIỀN
// =========================================================================
async function openCancelDetailModal(maPNP) {
  document.getElementById('cancelDetailMaPNP').innerText = maPNP;
  const body = document.getElementById('cancelDetailModalBody');
  body.innerHTML = `<div class="empty-state">⏳ Đang tải hồ sơ hủy đặt phòng của phiếu ${maPNP}...</div>`;
  document.getElementById('modalCancelDetail').classList.add('show');

  try {
    const res = await leTanFetch(`${API_BASE}/letan/xemctphieu`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ MaPNP: maPNP })
    });
    const json = await res.json();
    if (!json.success || !json.data) {
      body.innerHTML = `<div class="empty-state" style="color: var(--rec-err);">${json.error || 'Không thể tải hồ sơ hủy'}</div>`;
      return;
    }

    const d = json.data;
    currentCancelDetailData = d;
    const rooms = d.DanhSachChiTiet || [];

    const checkInDate = new Date((d.NgayNhanPhong || '').replace(' ', 'T'));
    const checkOutDate = new Date((d.NgayTraPhong || '').replace(' ', 'T'));
    const nights = Math.max(1, Math.round((checkOutDate - checkInDate) / 86400000) || 1);
    const totalRoomVal = rooms.reduce((s, r) => s + Number(r.DonGia || 0) * nights, 0);

    const soTienHoan = Number(d.SoTienHoan || 0);
    const tyLeHoan = Number(d.TyLeHoan || 0);
    const phiHuy = Math.max(0, totalRoomVal - soTienHoan);

    body.innerHTML = `
      <div style="background: #FEF2F2; border: 1.5px solid #FECACA; border-radius: 12px; padding: 14px 18px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <div>
          <div style="font-size: 11.5px; color: #B91C1C; text-transform: uppercase; font-weight: 800;">Trạng thái hồ sơ</div>
          <div style="font-size: 15px; font-weight: 800; color: var(--rec-navy); margin-top: 2px;">🚫 PHIẾU ĐẶT PHÒNG ĐÃ HỦY</div>
        </div>
        <div style="text-align: right;">
          <div style="font-size: 11.5px; color: var(--rec-muted);">Thời điểm ghi nhận hủy</div>
          <div style="font-size: 13.5px; font-weight: 700; color: var(--rec-gold-text);">${formatDateTime(d.ThoiGianHuy)}</div>
        </div>
      </div>

      <div class="info-grid">
        <div class="info-item">
          <label>Mã Phiếu &amp; Kênh Đặt</label>
          <div class="val">${d.MaPNP} (${d.KenhDat === 'TrucTiep' ? 'Tại quầy' : 'Online'})</div>
        </div>
        <div class="info-item">
          <label>Khách Hàng Đặt Phòng</label>
          <div class="val">${d.TenKhachHang || 'Khách hàng'} (${d.MaKH})</div>
        </div>
        <div class="info-item">
          <label>Số Điện Thoại</label>
          <div class="val">${d.SDT || 'Chưa cập nhật'}</div>
        </div>
        <div class="info-item">
          <label>Lịch Dự Kiến Ban Đầu (${nights} đêm)</label>
          <div class="val">${formatDateTime(d.NgayNhanPhong)} → ${formatDateTime(d.NgayTraPhong)}</div>
        </div>
        <div class="info-item" style="grid-column: 1 / -1;">
          <label>📝 Lý Do Hủy Đặt Phòng Đã Ghi Nhận</label>
          <div class="val" style="color: #B91C1C; background: #FEF2F2; padding: 10px 14px; border-radius: 8px; border: 1px dashed #FECACA; margin-top: 4px;">
            ${d.LyDoHuy || 'Khách hàng / Lễ tân hủy phiếu đặt phòng'}
          </div>
        </div>
      </div>

      <div>
        <h4 style="margin: 0 0 8px 0; color: var(--rec-navy); font-size: 13.5px; text-transform: uppercase;">
          🛏️ Các Phòng Đã Được Giải Phóng (${rooms.length} phòng)
        </h4>
        <table class="sub-table">
          <thead>
            <tr>
              <th>Mã CTPNP</th>
              <th>Số Phòng</th>
              <th>Hạng Phòng</th>
              <th>Đơn Giá / Đêm</th>
              <th style="text-align: right;">Thành Tiền (${nights} đêm)</th>
            </tr>
          </thead>
          <tbody>
            ${rooms.map(r => `
              <tr>
                <td><strong>${r.MaCTPNP}</strong></td>
                <td><span class="room-tag">Phòng ${r.MaPhong}</span></td>
                <td>${r.TenLoaiPhong || r.MaLoaiPhong}</td>
                <td>${formatMoney(r.DonGia)}</td>
                <td style="text-align: right; font-weight: 700;">${formatMoney(Number(r.DonGia || 0) * nights)}</td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      </div>

      <div style="background: var(--rec-section); border: 1.5px solid var(--rec-line); border-radius: 12px; padding: 18px 20px;">
        <div style="font-size: 12px; font-weight: 800; text-transform: uppercase; color: var(--rec-navy); margin-bottom: 12px;">
          💸 Quyết Toán Hoàn Tiền Hủy Đặt Phòng
        </div>
        <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 13.5px;">
          <span style="color: var(--rec-muted);">Tổng giá trị đặt phòng ban đầu:</span>
          <strong>${formatMoney(totalRoomVal)}</strong>
        </div>
        <div style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 13.5px;">
          <span style="color: var(--rec-muted);">Tỷ lệ hoàn tiền theo chính sách:</span>
          <strong style="color: var(--rec-gold-text);">${tyLeHoan}%</strong>
        </div>
        <div style="display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 13.5px;">
          <span style="color: var(--rec-muted);">Phí hủy / Khấu trừ dịch vụ:</span>
          <strong style="color: var(--rec-err);">${formatMoney(phiHuy)}</strong>
        </div>
        <div style="border-top: 1px solid var(--rec-line); padding-top: 12px; display: flex; justify-content: space-between; align-items: center;">
          <span style="font-size: 15px; font-weight: 800; color: var(--rec-navy); text-transform: uppercase;">SỐ TIỀN HOÀN TRẢ KHÁCH HÀNG:</span>
          <span style="font-size: 22px; font-weight: 800; color: var(--rec-ok);">${formatMoney(soTienHoan)}</span>
        </div>
      </div>
    `;
  } catch (err) {
    console.error(err);
    body.innerHTML = `<div class="empty-state" style="color: var(--rec-err);">Lỗi kết nối máy chủ khi tải hồ sơ hủy.</div>`;
  }
}

function printCancelDetailSlip() {
  if (!currentCancelDetailData) return;
  const d = currentCancelDetailData;
  const rooms = d.DanhSachChiTiet || [];
  const checkInDate = new Date((d.NgayNhanPhong || '').replace(' ', 'T'));
  const checkOutDate = new Date((d.NgayTraPhong || '').replace(' ', 'T'));
  const nights = Math.max(1, Math.round((checkOutDate - checkInDate) / 86400000) || 1);
  const totalRoomVal = rooms.reduce((s, r) => s + Number(r.DonGia || 0) * nights, 0);
  const soTienHoan = Number(d.SoTienHoan || 0);
  const tyLeHoan = Number(d.TyLeHoan || 0);

  const win = window.open('', '_blank', 'width=820,height=860');
  if (!win) {
    showToast('Vui lòng cho phép mở cửa sổ Popup để in biên bản!', '⚠️');
    return;
  }

  win.document.write(`
    <!DOCTYPE html>
    <html lang="vi">
    <head>
      <meta charset="UTF-8">
      <title>Biên Bản Hủy Đặt Phòng - ${d.MaPNP}</title>
      <style>
        body { font-family: 'Segoe UI', Tahoma, sans-serif; color: #172B3A; padding: 30px; max-width: 780px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #C0392B; padding-bottom: 14px; margin-bottom: 20px; }
        .brand { font-size: 20px; font-weight: 800; color: #172B3A; }
        h2 { text-align: center; color: #C0392B; text-transform: uppercase; margin: 16px 0; }
        .box { background: #FAF9F6; border: 1px solid #E3DED2; border-radius: 8px; padding: 14px; margin-bottom: 18px; font-size: 13.5px; line-height: 1.6; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 18px; font-size: 13px; }
        th, td { border: 1px solid #E3DED2; padding: 9px 12px; text-align: left; }
        th { background: #F7FAFC; }
        .refund-box { border: 2px solid #2F8F5B; background: #F4FBF7; padding: 14px 18px; border-radius: 8px; font-size: 15px; font-weight: 800; display: flex; justify-content: space-between; margin-top: 12px; }
        .sign { display: flex; justify-content: space-between; margin-top: 45px; text-align: center; font-size: 13px; }
        @media print { .no-print { display: none; } }
      </style>
    </head>
    <body>
      <div class="no-print" style="text-align: right; margin-bottom: 12px;">
        <button onclick="window.print()" style="padding: 8px 16px; background: #C0392B; color: #fff; border: none; border-radius: 6px; font-weight: 700; cursor: pointer;">🖨️ In Biên Bản Ngay</button>
      </div>
      <div class="header">
        <div>
          <div class="brand">☁ SKY HOTEL</div>
          <div style="font-size: 12px; color: #6B7280;">Bộ phận Lễ Tân &amp; Quản Lý Lưu Trú</div>
        </div>
        <div style="text-align: right; font-size: 12.5px;">
          <div><strong>Mã phiếu:</strong> ${d.MaPNP}</div>
          <div><strong>Thời gian hủy:</strong> ${d.ThoiGianHuy || '-'}</div>
        </div>
      </div>

      <h2>BIÊN BẢN XÁC NHẬN HỦY ĐẶT PHÒNG &amp; HOÀN TIỀN</h2>

      <div class="box">
        <div><strong>Khách hàng:</strong> ${d.TenKhachHang || 'Khách hàng'} (${d.MaKH}) — <strong>SĐT:</strong> ${d.SDT || '-'}</div>
        <div><strong>Lịch nhận - trả dự kiến:</strong> ${d.NgayNhanPhong} đến ${d.NgayTraPhong} (${nights} đêm)</div>
        <div><strong>Lý do hủy:</strong> ${d.LyDoHuy || 'Khách hàng yêu cầu hủy đặt phòng'}</div>
      </div>

      <table>
        <thead>
          <tr>
            <th>Mã CTPNP</th>
            <th>Số Phòng</th>
            <th>Hạng Phòng</th>
            <th>Đơn Giá / Đêm</th>
            <th style="text-align: right;">Thành Tiền (${nights} đêm)</th>
          </tr>
        </thead>
        <tbody>
          ${rooms.map(r => `
            <tr>
              <td>${r.MaCTPNP}</td>
              <td>Phòng ${r.MaPhong}</td>
              <td>${r.TenLoaiPhong || r.MaLoaiPhong}</td>
              <td>${formatMoney(r.DonGia)}</td>
              <td style="text-align: right; font-weight: 700;">${formatMoney(Number(r.DonGia || 0) * nights)}</td>
            </tr>
          `).join('')}
        </tbody>
      </table>

      <div class="box">
        <div style="display:flex; justify-content:space-between;"><span>Tổng giá trị phiếu ban đầu:</span><strong>${formatMoney(totalRoomVal)}</strong></div>
        <div style="display:flex; justify-content:space-between;"><span>Tỷ lệ hoàn tiền theo chính sách:</span><strong>${tyLeHoan}%</strong></div>
      </div>

      <div class="refund-box">
        <span>SỐ TIỀN HOÀN TRẢ KHÁCH HÀNG:</span>
        <span style="color: #2F8F5B;">${formatMoney(soTienHoan)}</span>
      </div>

      <div class="sign">
        <div style="width: 45%;"><strong>Khách hàng xác nhận</strong><br><small>(Ký và ghi rõ họ tên)</small></div>
        <div style="width: 45%;"><strong>Đại diện Lễ tân khách sạn</strong><br><small>(Ký và ghi rõ họ tên)</small></div>
      </div>
    </body>
    </html>
  `);
  win.document.close();
}

// Khởi chạy khi mở trang
document.addEventListener('DOMContentLoaded', () => {
  const initialUser = getLoggedReceptionistUser();
  if (initialUser) {
    const role = String(initialUser.VaiTro || initialUser.vaiTro || initialUser.role || '').trim().toLowerCase();
    if (role === 'letan' || role === 'lễ tân' || role === 'admin') {
      initialReceptionistUser = initialUser;
    }
  }
  const nameBadge = document.getElementById('receptionistNameText');
  if (nameBadge) {
    nameBadge.innerText = 'Trực ca: ' + getLoggedReceptionistName();
  }
  loadReceptions();
  loadRoomStatusMap();
  applyMainTabFromHash(); // mở thẳng tab "Sơ đồ & Trạng thái loại phòng" nếu URL có #rooms
});
