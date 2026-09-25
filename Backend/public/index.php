<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Database\Database;


/*
|--------------------------------------------------------------------------
| Load file .env
|--------------------------------------------------------------------------
*/

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();


/*
|--------------------------------------------------------------------------
| Load cấu hình ứng dụng
|--------------------------------------------------------------------------
*/

$config = require __DIR__ . '/../config/App.php';

date_default_timezone_set($config['timezone']);


/*
|--------------------------------------------------------------------------
| Router đơn giản
|--------------------------------------------------------------------------
*/

$router = new class {

    public array $routes = [];

    public function post(string $path, array $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function get(string $path, array $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function dispatch(string $method, string $uri): void
    {
        $handler = $this->routes[$method][$uri] ?? null;

        if (!$handler) {
            http_response_code(404);

            header('Content-Type: application/json; charset=utf-8');

            echo json_encode([
                'error' => 'Route not found'
            ]);

            return;
        }

        /*
         * TODO:
         * Resolve controller từ container DI
         * và gọi method tương ứng.
         */
    }
};


/*
|--------------------------------------------------------------------------
| Đăng ký routes của các module
|--------------------------------------------------------------------------
*/

\App\TaiKhoan\Routes\TaiKhoanRoute::register($router);

\App\KhachHang\Routes\KhachHangRoute::register($router);

\App\DatPhong\Routes\DatPhongRoute::register($router);

\App\DanhGia\Routes\DanhGiaRoute::register($router);

\App\LeTan\Routes\LeTanRoute::register($router);

\App\Phong\Routes\PhongRoute::register($router);

\App\ChiNhanh\Routes\ChiNhanhRoute::register($router);

\App\QuanLy\Routes\QuanLyRoute::register($router);

\App\ThanhToan\Routes\ThanhToanRoute::register($router);

\App\HoaDon\Routes\HoaDonRoute::register($router);

\App\Admin\Routes\AdminRoute::register($router);


/*
|--------------------------------------------------------------------------
| Kết nối Database
|--------------------------------------------------------------------------
*/

$db = Database::getConnection();
echo "KẾT NỐI MYSQL THÀNH CÔNG!";
exit;


/*
|--------------------------------------------------------------------------
| Xử lý request
|--------------------------------------------------------------------------
*/

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$uri = parse_url(
    $_SERVER['REQUEST_URI'] ?? '/',
    PHP_URL_PATH
);

$router->dispatch($method, $uri);