<?php

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
        $router->post('/admin/motaikhoanletan', [AdminController::class, 'moTaiKhoanLeTan']);
        $router->post('/admin/khoataikhoanletan', [AdminController::class, 'khoaTaiKhoanLeTan']);
    }
}
