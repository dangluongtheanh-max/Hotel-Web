<?php

namespace App\KhachHang\Routes;

use App\KhachHang\Controllers\KhachHangController;

/**
 * Định nghĩa route cho module KhachHang (Khách Đặt Phòng & Dịch Vụ).
 */
class KhachHangRoute
{
    public static function register($router): void
    {
        // Giao diện Khách Hàng Thao Tác Trực Tuyến (Customer Portal)
        $router->get('/client', [KhachHangController::class, 'viewPortal']);
        $router->get('/khachhang', [KhachHangController::class, 'viewPortal']);
        $router->get('/guest', [KhachHangController::class, 'viewPortal']);
        $router->get('/booking', [KhachHangController::class, 'viewPortal']);

        // Quản lý khách hàng phía Admin
        $router->get('/admin/xemdskhachhang', [KhachHangController::class, 'xemDSKhachHang']);
        $router->post('/admin/xemdskhachhang', [KhachHangController::class, 'xemDSKhachHang']);
        $router->get('/admin/timkiemkhachhang', [KhachHangController::class, 'timKiemKhachHang']);
        $router->post('/admin/timkiemkhachhang', [KhachHangController::class, 'timKiemKhachHang']);
        $router->get('/admin/xemctkhachhang', [KhachHangController::class, 'xemCTKhachHang']);
        $router->post('/admin/xemctkhachhang', [KhachHangController::class, 'xemCTKhachHang']);
        $router->post('/admin/capnhatthongtinkhachhang', [KhachHangController::class, 'capNhatThongTinKhachHang']);
        $router->post('/admin/khoataikhoankhachhang', [KhachHangController::class, 'khoaTaiKhoanKhachHang']);
        $router->post('/admin/motaikhoankhachhang', [KhachHangController::class, 'moTaiKhoanKhachHang']);

        // Xem danh sách phòng
        $router->get('/khachhang/danhsachphong', [KhachHangController::class, 'xemDSPhong']);
        $router->post('/khachhang/danhsachphong', [KhachHangController::class, 'xemDSPhong']);
        $router->get('/khachhang/xemdsphong', [KhachHangController::class, 'xemDSPhong']);
        $router->post('/khachhang/xemdsphong', [KhachHangController::class, 'xemDSPhong']);

        // Khách hàng đặt phòng trực tuyến (Lưu CSDL thật)
        $router->post('/khachhang/datphong', [KhachHangController::class, 'datPhong']);

        // Tra cứu đơn đặt phòng & lịch sử
        $router->get('/khachhang/tracuu', [KhachHangController::class, 'traCuu']);
        $router->post('/khachhang/tracuu', [KhachHangController::class, 'traCuu']);
        $router->get('/khachhang/lichsu', [KhachHangController::class, 'xemLichSuDatPhong']);
        $router->post('/khachhang/xemlichsudatphong', [KhachHangController::class, 'xemLichSuDatPhong']);
    }
}
