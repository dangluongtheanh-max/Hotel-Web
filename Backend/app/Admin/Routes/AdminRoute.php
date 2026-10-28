<?php

declare(strict_types=1);

namespace App\Admin\Routes;

use App\Admin\Controllers\AdminController;

/**
 * Định nghĩa route cho module Admin & Báo Cáo Thống Kê (BE4).
 * Được nạp tự động bởi public/index.php.
 */
class AdminRoute
{
    public static function register($router): void
    {
        // Giao diện trực quan Dashboard Thống Kê & Báo Cáo (BE4)
        $router->get('/admin', [AdminController::class, 'viewDashboard']);
        $router->get('/admin/dashboard', [AdminController::class, 'viewDashboard']);
        $router->get('/gui', [AdminController::class, 'viewDashboard']);
        $router->get('/dashboard', [AdminController::class, 'viewDashboard']);

        // 1. Thống kê tổng quan KPI
        $router->get('/admin/thongketongquan', [AdminController::class, 'thongKeTongQuan']);
        $router->post('/admin/thongketongquan', [AdminController::class, 'thongKeTongQuan']);
        $router->get('/thongke/tongquan', [AdminController::class, 'thongKeTongQuan']);
        $router->post('/thongke/tongquan', [AdminController::class, 'thongKeTongQuan']);

        // 2. Thống kê doanh thu (Doanh thu theo thời gian, hình thức, cơ cấu phòng vs dịch vụ)
        $router->get('/admin/thongkedoanhthu', [AdminController::class, 'thongKeDoanhThu']);
        $router->post('/admin/thongkedoanhthu', [AdminController::class, 'thongKeDoanhThu']);
        $router->get('/thongke/doanhthu', [AdminController::class, 'thongKeDoanhThu']);
        $router->post('/thongke/doanhthu', [AdminController::class, 'thongKeDoanhThu']);

        // 2.1 Thống kê doanh thu loại phòng (Tab 1: Thống Kê Loại Phòng)
        $router->get('/admin/thongkedoanhthuloaiphong', [AdminController::class, 'thongKeDoanhThuLoaiPhong']);
        $router->post('/admin/thongkedoanhthuloaiphong', [AdminController::class, 'thongKeDoanhThuLoaiPhong']);

        // 2.2 Thống kê doanh thu dịch vụ (Tab 2: Thống Kê Dịch Vụ)
        $router->get('/admin/thongkedoanhthudichvu', [AdminController::class, 'thongKeDoanhThuDichVu']);
        $router->post('/admin/thongkedoanhthudichvu', [AdminController::class, 'thongKeDoanhThuDichVu']);

        // 2.3 Thống kê công suất phòng (UC21.2)
        $router->get('/admin/thongkecongsuatphong', [AdminController::class, 'thongKeCongSuatPhong']);
        $router->post('/admin/thongkecongsuatphong', [AdminController::class, 'thongKeCongSuatPhong']);
        $router->get('/thongke/congsuatphong', [AdminController::class, 'thongKeCongSuatPhong']);
        $router->post('/thongke/congsuatphong', [AdminController::class, 'thongKeCongSuatPhong']);

        // 2.4 Thống kê đánh giá (UC21.3)
        $router->get('/admin/thongkedanhgia', [AdminController::class, 'thongKeDanhGia']);
        $router->post('/admin/thongkedanhgia', [AdminController::class, 'thongKeDanhGia']);
        $router->get('/thongke/danhgia', [AdminController::class, 'thongKeDanhGia']);
        $router->post('/thongke/danhgia', [AdminController::class, 'thongKeDanhGia']);
        $router->get('/admin/chitietdanhgia', [AdminController::class, 'xemChiTietDanhGia']);
        $router->post('/admin/chitietdanhgia', [AdminController::class, 'xemChiTietDanhGia']);
        $router->get('/thongke/chitietdanhgia', [AdminController::class, 'xemChiTietDanhGia']);
        $router->post('/thongke/chitietdanhgia', [AdminController::class, 'xemChiTietDanhGia']);

        // 3. Thống kê số lượng / tình trạng phòng (Tỷ lệ lấp đầy, số phòng trống, đang ở, theo loại)
        $router->get('/admin/thongkesoluongphong', [AdminController::class, 'thongKeSoLuongPhong']);
        $router->post('/admin/thongkesoluongphong', [AdminController::class, 'thongKeSoLuongPhong']);
        $router->get('/thongke/phong', [AdminController::class, 'thongKeSoLuongPhong']);
        $router->post('/thongke/phong', [AdminController::class, 'thongKeSoLuongPhong']);

        // 4. Thống kê phiếu đặt phòng (Theo trạng thái, kênh trực tuyến vs trực tiếp)
        $router->get('/admin/thongkesoluongphieu', [AdminController::class, 'thongKeSoLuongPhieu']);
        $router->post('/admin/thongkesoluongphieu', [AdminController::class, 'thongKeSoLuongPhieu']);
        $router->get('/admin/thongkedatphong', [AdminController::class, 'thongKeSoLuongPhieu']);
        $router->post('/admin/thongkedatphong', [AdminController::class, 'thongKeSoLuongPhieu']);
        $router->get('/thongke/phieu', [AdminController::class, 'thongKeSoLuongPhieu']);
        $router->post('/thongke/phieu', [AdminController::class, 'thongKeSoLuongPhieu']);

        // 5. Thống kê dịch vụ sử dụng (Top dịch vụ, doanh thu dịch vụ, tồn kho)
        $router->get('/admin/thongkedichvu', [AdminController::class, 'thongKeDichVu']);
        $router->post('/admin/thongkedichvu', [AdminController::class, 'thongKeDichVu']);
        $router->get('/thongke/dichvu', [AdminController::class, 'thongKeDichVu']);
        $router->post('/thongke/dichvu', [AdminController::class, 'thongKeDichVu']);

        // 6. Thống kê nhân viên
        $router->get('/admin/thongkesoluongnhanvien', [AdminController::class, 'thongKeSoLuongNhanVien']);
        $router->post('/admin/thongkesoluongnhanvien', [AdminController::class, 'thongKeSoLuongNhanVien']);

        // 7. Quản lý nhân viên & Lễ tân (Admin Shared)
        $router->post('/admin/xemdsnhanvien', [AdminController::class, 'xemDSNhanVien']);
        $router->get('/admin/xemdsnhanvien', [AdminController::class, 'xemDSNhanVien']);
        $router->post('/admin/timkiemnhanvien', [AdminController::class, 'timKiemNhanVien']);
        $router->get('/admin/timkiemnhanvien', [AdminController::class, 'timKiemNhanVien']);
        $router->post('/admin/xemctnhanvien', [AdminController::class, 'xemCTNhanVien']);
        $router->get('/admin/xemctnhanvien', [AdminController::class, 'xemCTNhanVien']);
        $router->post('/admin/capnhatthongtinnhanvien', [AdminController::class, 'capNhatThongTinNhanVien']);
        $router->post('/admin/themnhanvien', [AdminController::class, 'themNhanVien']);
        $router->post('/admin/motaikhoannhanvien', [AdminController::class, 'moTaiKhoanNhanVien']);
        $router->post('/admin/khoataikhoannhanvien', [AdminController::class, 'khoaTaiKhoanNhanVien']);

        $router->post('/admin/xemdsletan', [AdminController::class, 'xemDSLeTan']);
        $router->get('/admin/xemdsletan', [AdminController::class, 'xemDSLeTan']);
        $router->post('/admin/timkiemletan', [AdminController::class, 'timKiemLeTan']);
        $router->get('/admin/timkiemletan', [AdminController::class, 'timKiemLeTan']);
        $router->post('/admin/xemctletan', [AdminController::class, 'xemCTLeTan']);
        $router->get('/admin/xemctletan', [AdminController::class, 'xemCTLeTan']);
        $router->post('/admin/capnhatthongtinletan', [AdminController::class, 'capNhatThongTinLeTan']);
        $router->post('/admin/themletan', [AdminController::class, 'themLeTan']);
        $router->get('/admin/sinhmaletan', [AdminController::class, 'sinhMaLeTan']);
        $router->post('/admin/sinhmaletan', [AdminController::class, 'sinhMaLeTan']);
        $router->post('/admin/doitrangthailetan', [AdminController::class, 'doiTrangThaiLeTan']);
        $router->post('/admin/motaikhoanletan', [AdminController::class, 'moTaiKhoanLeTan']);
        $router->post('/admin/khoataikhoanletan', [AdminController::class, 'khoaTaiKhoanLeTan']);

        // 8. Quản lý khách hàng
        $router->post('/admin/xemdskhachhang', [AdminController::class, 'xemDSKhachHang']);
        $router->get('/admin/xemdskhachhang', [AdminController::class, 'xemDSKhachHang']);

        // 9. Quản lý phiếu nhận phòng
        $router->post('/admin/danhsachphieunhanphong', [AdminController::class, 'xemDSPhieuNhanPhong']);
        $router->get('/admin/danhsachphieunhanphong', [AdminController::class, 'xemDSPhieuNhanPhong']);

        // 10. Quản lý hóa đơn
        $router->post('/admin/danhsachhoadon', [AdminController::class, 'xemDSHoaDon']);
        $router->get('/admin/danhsachhoadon', [AdminController::class, 'xemDSHoaDon']);

        // 11. Quản lý dịch vụ
        $router->post('/admin/danhsachdichvu', [AdminController::class, 'xemDSDichVu']);
        $router->get('/admin/danhsachdichvu', [AdminController::class, 'xemDSDichVu']);
        $router->get('/admin/sinhmachitietdichvu', [AdminController::class, 'sinhMaChiTietDichVu']);
        $router->post('/admin/sinhmachitietdichvu', [AdminController::class, 'sinhMaChiTietDichVu']);
        $router->post('/admin/themchitietdichvu', [AdminController::class, 'themChiTietDichVu']);
        $router->post('/admin/suachitietdichvu', [AdminController::class, 'suaChiTietDichVu']);
        $router->post('/admin/doitrangthaichitietdichvu', [AdminController::class, 'doiTrangThaiChiTietDichVu']);

        // 12. Quản lý khuyến mãi
        $router->post('/admin/danhsachkhuyenmai', [AdminController::class, 'xemDSKhuyenMai']);
        $router->get('/admin/danhsachkhuyenmai', [AdminController::class, 'xemDSKhuyenMai']);
        $router->get('/admin/sinhmakhuyenmai', [AdminController::class, 'sinhMaKhuyenMai']);
        $router->post('/admin/sinhmakhuyenmai', [AdminController::class, 'sinhMaKhuyenMai']);
        $router->post('/admin/themkhuyenmai', [AdminController::class, 'themKhuyenMai']);
        $router->post('/admin/suakhuyenmai', [AdminController::class, 'suaKhuyenMai']);
        $router->post('/admin/doitrangthaikhuyenmai', [AdminController::class, 'doiTrangThaiKhuyenMai']);

        // 13. Quản lý phòng & loại phòng
        $router->post('/admin/danhsachphongtheoloai', [AdminController::class, 'xemDSPhongTheoLoai']);
        $router->get('/admin/danhsachphongtheoloai', [AdminController::class, 'xemDSPhongTheoLoai']);
        $router->get('/admin/sinhmaloaiphong', [AdminController::class, 'sinhMaLoaiPhong']);
        $router->post('/admin/sinhmaloaiphong', [AdminController::class, 'sinhMaLoaiPhong']);
        $router->post('/admin/themloaiphong', [AdminController::class, 'themLoaiPhong']);
        $router->post('/admin/sualoaiphong', [AdminController::class, 'suaLoaiPhong']);
        $router->post('/admin/doitrangthailoaiphong', [AdminController::class, 'doiTrangThaiLoaiPhong']);
        $router->post('/admin/themphong', [AdminController::class, 'themPhong']);
        $router->post('/admin/suaphong', [AdminController::class, 'suaPhong']);
        $router->post('/admin/doitrangthaiphong', [AdminController::class, 'doiTrangThaiPhong']);
    }
}