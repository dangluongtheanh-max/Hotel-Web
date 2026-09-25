<?php

namespace App\KhachHang\Routes;

use App\KhachHang\Controllers\KhachHangController;

/**
 * Dinh nghia route cho module KhachHang.
 * Duoc include boi public/index.php thong qua bo dinh tuyen chung.
 */
class KhachHangRoute
{
    public static function register($router): void
    {
    $router->post('/khachhang/dangky', [KhachHangController::class, 'dangKy']);
    $router->post('/khachhang/xemdsphong', [KhachHangController::class, 'xemDSPhong']);
    $router->post('/khachhang/xemctphong', [KhachHangController::class, 'xemCTPhong']);
    $router->post('/khachhang/datphong', [KhachHangController::class, 'datPhong']);
    $router->post('/khachhang/thanhtoan', [KhachHangController::class, 'thanhToan']);
    $router->post('/khachhang/huydatphong', [KhachHangController::class, 'huyDatPhong']);
    $router->post('/khachhang/xemlichsudatphong', [KhachHangController::class, 'xemLichSuDatPhong']);
    $router->post('/khachhang/xemhoadon', [KhachHangController::class, 'xemHoaDon']);
    $router->post('/khachhang/xemcthd', [KhachHangController::class, 'xemCTHD']);
    $router->post('/khachhang/xemthongtincanhan', [KhachHangController::class, 'xemThongTinCaNhan']);
    $router->post('/khachhang/capnhatthongtincanhan', [KhachHangController::class, 'capNhatThongTinCaNhan']);
    $router->post('/khachhang/themdanhgia', [KhachHangController::class, 'themDanhGia']);
    $router->post('/khachhang/xoadanhgia', [KhachHangController::class, 'xoaDanhGia']);
    }
}
