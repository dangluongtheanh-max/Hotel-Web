<?php

declare(strict_types=1);

namespace App\DatPhong\Routes;

use App\DatPhong\Controllers\DatPhongController;

/**
 * Định nghĩa route cho module DatPhong.
 * Được include bởi public/index.php thông qua bộ định tuyến chung.
 */
class DatPhongRoute
{
    public static function register($router): void
    {
        // Các route phục vụ quy trình đặt phòng trực tuyến
        $router->post('/datphong/timkiem', [DatPhongController::class, 'timKiemVaGoiY']);
        $router->get('/datphong/dichvu', [DatPhongController::class, 'layDanhSachDichVu']);
        $router->post('/datphong/dichvu', [DatPhongController::class, 'layDanhSachDichVu']);
        $router->post('/datphong/kiemtrakhuyenmai', [DatPhongController::class, 'kiemTraKhuyenMai']);
        $router->post('/datphong/tinhchiphi', [DatPhongController::class, 'tinhChiPhi']);
        $router->post('/datphong/datphong', [DatPhongController::class, 'datPhongVaThanhToan']);
        $router->post('/datphong/datphongvathanhtoan', [DatPhongController::class, 'datPhongVaThanhToan']);
        $router->get('/datphong/xemctphieu', [DatPhongController::class, 'xemCTPhieu']);
        $router->post('/datphong/xemctphieu', [DatPhongController::class, 'xemCTPhieu']);
        $router->get('/datphong/chitietphieu', [DatPhongController::class, 'xemCTPhieu']);
        $router->post('/datphong/chitietphieu', [DatPhongController::class, 'xemCTPhieu']);
        // New route: lịch sử phiếu đặt phòng của khách hiện tại
        $router->get('/datphong/lichsu', [DatPhongController::class, 'lichSuDatPhong']);
        $router->post('/datphong/lichsu', [DatPhongController::class, 'lichSuDatPhong']);
        // New route: cập nhật thông tin người lưu trú
        $router->post('/datphong/capnhatnguoiluutru', [DatPhongController::class, 'capNhatNguoiLuuTru']);

        // Các route cũ theo class diagram
        $router->post('/datphong/xemdsphieu', [DatPhongController::class, 'xemDSPhieu']);
        $router->post('/datphong/timkiemphieu', [DatPhongController::class, 'timKiemPhieu']);
        $router->post('/datphong/checkin', [DatPhongController::class, 'checkin']);
        $router->post('/datphong/checkout', [DatPhongController::class, 'checkout']);
        $router->post('/datphong/taophieunhanphong', [DatPhongController::class, 'taoPhieuNhanPhong']);
        $router->post('/datphong/capnhatphieunhanphong', [DatPhongController::class, 'capNhatPhieuNhanPhong']);
        $router->post('/datphong/apdungkhuyenmai', [DatPhongController::class, 'apDungKhuyenMai']);
    }
}
