<?php

namespace App\Admin\Routes;

use App\Admin\Controllers\AdminController;

/**
 * Dinh nghia route cho module Admin.
 * Duoc include boi public/index.php thong qua bo dinh tuyen chung.
 */
class AdminRoute
{
    public static function register($router): void
    {
    $router->post('/admin/xemdsnhanvien', [AdminController::class, 'xemDSNhanVien']);
    $router->post('/admin/timkiemnhanvien', [AdminController::class, 'timKiemNhanVien']);
    $router->post('/admin/xemctnhanvien', [AdminController::class, 'xemCTNhanVien']);
    $router->post('/admin/capnhatthongtinnhanvien', [AdminController::class, 'capNhatThongTinNhanVien']);
    $router->post('/admin/themnhanvien', [AdminController::class, 'themNhanVien']);
    $router->post('/admin/motaikhoannhanvien', [AdminController::class, 'moTaiKhoanNhanVien']);
    $router->post('/admin/khoataikhoannhanvien', [AdminController::class, 'khoaTaiKhoanNhanVien']);
    $router->post('/admin/thongkesoluongnhanvien', [AdminController::class, 'thongKeSoLuongNhanVien']);
    $router->post('/admin/thongkedoanhthuchinhanh', [AdminController::class, 'thongKeDoanhThuChiNhanh']);
    $router->post('/admin/thongkesoluongphong', [AdminController::class, 'thongKeSoLuongPhong']);
    }
}
