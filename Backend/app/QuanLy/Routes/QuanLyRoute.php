<?php

namespace App\QuanLy\Routes;

use App\QuanLy\Controllers\QuanLyController;

/**
 * Dinh nghia route cho module QuanLy.
 * Duoc include boi public/index.php thong qua bo dinh tuyen chung.
 */
class QuanLyRoute
{
    public static function register($router): void
    {
    $router->post('/quanly/xemdsletan', [QuanLyController::class, 'xemDSLeTan']);
    $router->post('/quanly/xemchitietletan', [QuanLyController::class, 'xemChiTietLeTan']);
    $router->post('/quanly/capnhatthongtinletan', [QuanLyController::class, 'capNhatThongTinLeTan']);
    $router->post('/quanly/timkiemletan', [QuanLyController::class, 'timKiemLeTan']);
    $router->post('/quanly/khoataikhoanletan', [QuanLyController::class, 'khoaTaiKhoanLeTan']);
    $router->post('/quanly/motaikhoanletan', [QuanLyController::class, 'moTaiKhoanLeTan']);
    $router->post('/quanly/thongkedoanhthu', [QuanLyController::class, 'thongKeDoanhThu']);
    $router->post('/quanly/thongkesldatphong', [QuanLyController::class, 'thongKeSLDatPhong']);
    $router->post('/quanly/thongkedanhgiakhachhang', [QuanLyController::class, 'thongKeDanhGiaKhachHang']);
    $router->post('/quanly/timhoadon', [QuanLyController::class, 'timHoaDon']);
    $router->post('/quanly/xemhoadon', [QuanLyController::class, 'xemHoaDon']);
    $router->post('/quanly/xemcthd', [QuanLyController::class, 'xemCTHD']);
    }
}
