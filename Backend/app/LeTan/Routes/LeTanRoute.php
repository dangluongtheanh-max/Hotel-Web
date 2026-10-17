<?php

namespace App\LeTan\Routes;

use App\LeTan\Controllers\LeTanController;

/**
 * Định nghĩa route cho module LeTan (BE2).
 * Được include bởi public/index.php thông qua bộ định tuyến chung.
 */
class LeTanRoute
{
    public static function register($router): void
    {
        // 11 API Endpoints theo đặc tả Use Case BE2
        $router->post('/letan/xemdsphong', [LeTanController::class, 'xemDSPhong']);
        $router->post('/letan/xemctphong', [LeTanController::class, 'xemCTPhong']);
        $router->post('/letan/timkiemphong', [LeTanController::class, 'timKiemPhong']);
        $router->post('/letan/xemdsphieu', [LeTanController::class, 'xemDSPhieu']);
        $router->post('/letan/xemctphieu', [LeTanController::class, 'xemCTPhieu']);
        $router->post('/letan/timkiemphieu', [LeTanController::class, 'timKiemPhieu']);
        $router->post('/letan/checkin', [LeTanController::class, 'checkin']);
        $router->post('/letan/checkout', [LeTanController::class, 'checkout']);
        $router->post('/letan/timhoadon', [LeTanController::class, 'timHoaDon']);
        $router->post('/letan/xemhoadon', [LeTanController::class, 'xemHoaDon']);
        $router->post('/letan/xemcthd', [LeTanController::class, 'xemCTHD']);
    }
}
