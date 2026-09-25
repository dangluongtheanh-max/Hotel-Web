<?php

namespace App\TaiKhoan\Routes;

use App\TaiKhoan\Controllers\TaiKhoanController;

/**
 * Dinh nghia route cho module TaiKhoan.
 * Duoc include boi public/index.php thong qua bo dinh tuyen chung.
 */
class TaiKhoanRoute
{
    public static function register($router): void
    {
    $router->post('/taikhoan/dangnhap', [TaiKhoanController::class, 'dangNhap']);
    $router->post('/taikhoan/quenmatkhau', [TaiKhoanController::class, 'quenMatKhau']);
    }
}
