<?php

declare(strict_types=1);

namespace App\LeTan\Routes;

use App\LeTan\Controllers\LeTanController;

/**
 * Dinh nghia route cho module LeTan.
 * Duoc include boi public/index.php thong qua bo dinh tuyen chung.
 */
class LeTanRoute
{
    public static function register($router): void
    {
        $router->get('/letan/trangthaiphong', [LeTanController::class, 'xemTrangThaiPhong']);
        $router->post('/letan/trangthaiphong', [LeTanController::class, 'xemTrangThaiPhong']);
        $router->get('/letan/xemdsphong', [LeTanController::class, 'xemDSPhong']);
        $router->post('/letan/xemdsphong', [LeTanController::class, 'xemDSPhong']);
    $router->post('/letan/xemctphong', [LeTanController::class, 'xemCTPhong']);
    $router->post('/letan/timkiemphong', [LeTanController::class, 'timKiemPhong']);
    $router->get('/letan/xemdsphieu', [LeTanController::class, 'xemDSPhieu']);
    $router->post('/letan/xemdsphieu', [LeTanController::class, 'xemDSPhieu']);
    $router->get('/letan/xemctphieu', [LeTanController::class, 'xemCTPhieu']);
    $router->post('/letan/xemctphieu', [LeTanController::class, 'xemCTPhieu']);
    $router->post('/letan/timkiemphieu', [LeTanController::class, 'timKiemPhieu']);
    $router->get('/letan/tinhtienhoan', [LeTanController::class, 'tinhTienHoanHuy']);
    $router->post('/letan/tinhtienhoan', [LeTanController::class, 'tinhTienHoanHuy']);
    $router->post('/letan/checkin', [LeTanController::class, 'checkin']);
    $router->post('/letan/huyphieu', [LeTanController::class, 'huyPhieu']);
    $router->get('/letan/tinhtiencheckout', [LeTanController::class, 'tinhTienCheckout']);
    $router->post('/letan/tinhtiencheckout', [LeTanController::class, 'tinhTienCheckout']);
    $router->post('/letan/checkout', [LeTanController::class, 'checkout']);
    $router->post('/letan/huyphong', [LeTanController::class, 'huyPhieu']);
    $router->post('/letan/doiphongnguoiluutru', [LeTanController::class, 'doiPhongNguoiLuuTru']);
    $router->post('/letan/capnhatkhach', [LeTanController::class, 'capNhatThongTinKhach']);
    $router->post('/letan/capnhatbaotri', [LeTanController::class, 'capNhatBaoTri']);
    $router->get('/letan/danhsachdichvu', [LeTanController::class, 'layDanhSachDichVu']);
    $router->post('/letan/danhsachdichvu', [LeTanController::class, 'layDanhSachDichVu']);
    $router->post('/letan/datdichvunhanh', [LeTanController::class, 'datDichVuNhanh']);
    $router->post('/letan/timhoadon', [LeTanController::class, 'timHoaDon']);
    $router->post('/letan/xemhoadon', [LeTanController::class, 'xemHoaDon']);
    $router->post('/letan/xemcthd', [LeTanController::class, 'xemCTHD']);
    }
}
