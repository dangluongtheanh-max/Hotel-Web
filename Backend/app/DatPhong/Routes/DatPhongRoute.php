<?php

namespace App\DatPhong\Routes;

use App\DatPhong\Controllers\DatPhongController;

/**
 * Dinh nghia route cho module DatPhong.
 * Duoc include boi public/index.php thong qua bo dinh tuyen chung.
 */
class DatPhongRoute
{
    public static function register($router): void
    {
    $router->post('/datphong/xemdsphieu', [DatPhongController::class, 'xemDSPhieu']);
    $router->post('/datphong/xemctphieu', [DatPhongController::class, 'xemCTPhieu']);
    $router->post('/datphong/timkiemphieu', [DatPhongController::class, 'timKiemPhieu']);
    $router->post('/datphong/checkin', [DatPhongController::class, 'checkin']);
    $router->post('/datphong/checkout', [DatPhongController::class, 'checkout']);
    $router->post('/datphong/taophieunhanphong', [DatPhongController::class, 'taoPhieuNhanPhong']);
    $router->post('/datphong/capnhatphieunhanphong', [DatPhongController::class, 'capNhatPhieuNhanPhong']);
    $router->post('/datphong/apdungkhuyenmai', [DatPhongController::class, 'apDungKhuyenMai']);
    }
}
