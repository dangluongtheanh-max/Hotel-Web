<?php

namespace App\ChiNhanh\Routes;

use App\ChiNhanh\Controllers\ChiNhanhController;

/**
 * Dinh nghia route cho module ChiNhanh.
 * Duoc include boi public/index.php thong qua bo dinh tuyen chung.
 */
class ChiNhanhRoute
{
    public static function register($router): void
    {
    $router->post('/chinhanh/xemdschinhanh', [ChiNhanhController::class, 'xemDSChiNhanh']);
    $router->post('/chinhanh/xemchitietchinhanh', [ChiNhanhController::class, 'xemChiTietChiNhanh']);
    $router->post('/chinhanh/themchinhanh', [ChiNhanhController::class, 'themChiNhanh']);
    $router->post('/chinhanh/capnhatthongtinchinhanh', [ChiNhanhController::class, 'capNhatThongTinChiNhanh']);
    $router->post('/chinhanh/timkiemchinhanh', [ChiNhanhController::class, 'timKiemChiNhanh']);
    }
}
