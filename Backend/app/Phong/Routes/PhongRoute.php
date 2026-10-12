<?php

namespace App\Phong\Routes;

use App\Phong\Controllers\PhongController;

/**
 * Dinh nghia route cho module Phong.
 * Duoc include boi public/index.php thong qua bo dinh tuyen chung.
 */
class PhongRoute
{
    public static function register($router): void
    {
        $router->post('/phong/xemdsphong', [PhongController::class, 'xemDSPhong']);
        $router->post('/phong/xemctphong', [PhongController::class, 'xemCTPhong']);
        $router->post('/phong/themphong', [PhongController::class, 'themPhong']);
        $router->post('/phong/capnhatthongtinphong', [PhongController::class, 'capNhatThongTinPhong']);
        $router->post('/phong/timkiemphong', [PhongController::class, 'timKiemPhong']);
        $router->post('/phong/kiemtratrangthaiphong', [PhongController::class, 'kiemTraTrangThaiPhong']);
        $router->post('/phong/kiemtraphongtrong', [PhongController::class, 'kiemTraPhongTrong']);

        $router->post('/loaiphong/xemdsloaiphong', [PhongController::class, 'xemDSLoaiPhong']);
        $router->post('/loaiphong/xemctloaiphong', [PhongController::class, 'xemCTLoaiPhong']);
        $router->post('/loaiphong/themloaiphong', [PhongController::class, 'themLoaiPhong']);
        $router->post('/loaiphong/capnhatloaiphong', [PhongController::class, 'capNhatLoaiPhong']);
    }
}
