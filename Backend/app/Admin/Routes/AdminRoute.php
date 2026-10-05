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
    $router->post('/admin/thongkedoanhthu', [AdminController::class, 'thongKeDoanhThu']);
    $router->post('/admin/thongkesoluongphong', [AdminController::class, 'thongKeSoLuongPhong']);
    $router->post('/admin/xemdsletan', [AdminController::class, 'xemDSLeTan']);
    $router->post('/admin/timkiemletan', [AdminController::class, 'timKiemLeTan']);
    $router->post('/admin/xemctletan', [AdminController::class, 'xemCTLeTan']);
    $router->post('/admin/capnhatthongtinletan', [AdminController::class, 'capNhatThongTinLeTan']);
    $router->post('/admin/themletan', [AdminController::class, 'themLeTan']);
    $router->post('/admin/motaikhoanletan', [AdminController::class, 'moTaiKhoanLeTan']);
    $router->post('/admin/khoataikhoanletan', [AdminController::class, 'khoaTaiKhoanLeTan']);
    }
}
