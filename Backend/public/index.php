<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

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
         * Khởi tạo Controller và gọi method
         */
        [$controllerClass, $method] = $handler;

        // DI Container đơn giản cho TaiKhoanController, KhachHangController, DatPhongController
        $controller = null;
        if ($controllerClass === \App\TaiKhoan\Controllers\TaiKhoanController::class) {
            $db = new \Database\Database();
            $repo = new \App\TaiKhoan\Repositories\TaiKhoanRepository($db);
            $service = new \App\TaiKhoan\Services\TaiKhoanService($repo);
            $quenMatKhauRepo = new \App\TaiKhoan\Repositories\QuenMatKhauRepository($db);
            $quenMatKhauService = new \App\TaiKhoan\Services\QuenMatKhauService($quenMatKhauRepo);
            $controller = new $controllerClass($service, $quenMatKhauService);
        } elseif ($controllerClass === \App\KhachHang\Controllers\KhachHangController::class) {
            $db = new \Database\Database();
            $khachHangRepo = new \App\KhachHang\Repositories\KhachHangRepository($db);
            $taiKhoanRepo = new \App\TaiKhoan\Repositories\TaiKhoanRepository($db);
            $service = new \App\KhachHang\Services\KhachHangService($khachHangRepo, $taiKhoanRepo);
            $controller = new $controllerClass($service);
        } elseif ($controllerClass === \App\DatPhong\Controllers\DatPhongController::class) {
            $db = new \Database\Database();
            $datPhongRepo = new \App\DatPhong\Repositories\DatPhongRepository($db);
            $phongRepo = new \App\Phong\Repositories\PhongRepository($db);
            $phongService = new \App\Phong\Services\PhongService($phongRepo);
            $service = new \App\DatPhong\Services\DatPhongService($datPhongRepo, $phongService);
            $khachHangRepo = new \App\KhachHang\Repositories\KhachHangRepository($db);
            $lichSuService = new \App\DatPhong\Services\LichSuDatPhongService($datPhongRepo, $khachHangRepo);
            $controller = new $controllerClass($service, $lichSuService);
        } elseif ($controllerClass === \App\DanhGia\Controllers\DanhGiaController::class) {
            $db = new \Database\Database();
            $danhGiaRepo = new \App\DanhGia\Repositories\DanhGiaRepository($db);
            $khachHangRepo = new \App\KhachHang\Repositories\KhachHangRepository($db);
            $service = new \App\DanhGia\Services\DanhGiaService($danhGiaRepo, $khachHangRepo);
            $controller = new $controllerClass($service);
        } elseif ($controllerClass === \App\DichVu\Controllers\DichVuController::class) {
            $service = new \App\DichVu\Services\DichVuService();
            $controller = new \App\DichVu\Controllers\DichVuController($service);
        } elseif ($controllerClass === \App\DichVu\Controllers\DatDoAnController::class || $controllerClass === 'App\DichVu\DatDoAn\DatDoAnController') {
            $db = new \Database\Database();
            $repo = new \App\DichVu\Repositories\DatDoAnRepository($db);
            $khachHangRepo = new \App\KhachHang\Repositories\KhachHangRepository($db);
            $service = new \App\DichVu\Services\DatDoAnService($repo, $khachHangRepo);
            $controller = new \App\DichVu\Controllers\DatDoAnController($service);
        } elseif ($controllerClass === \App\DichVu\Controllers\GiatUiController::class || $controllerClass === 'App\DichVu\GiatUi\GiatUiController') {
            $db = new \Database\Database();
            $repo = new \App\DichVu\Repositories\GiatUiRepository($db);
            $khachHangRepo = new \App\KhachHang\Repositories\KhachHangRepository($db);
            $service = new \App\DichVu\Services\GiatUiService($repo, $khachHangRepo);
            $controller = new \App\DichVu\Controllers\GiatUiController($service);
        } elseif ($controllerClass === \App\DichVu\Controllers\ThueXeMayController::class || $controllerClass === 'App\DichVu\ThueXeMay\ThueXeMayController') {
            $db = new \Database\Database();
            $repo = new \App\DichVu\Repositories\ThueXeMayRepository($db);
            $khachHangRepo = new \App\KhachHang\Repositories\KhachHangRepository($db);
            $service = new \App\DichVu\Services\ThueXeMayService($repo, $khachHangRepo);
            $controller = new \App\DichVu\Controllers\ThueXeMayController($service);
        } elseif ($controllerClass === \App\LeTan\Controllers\LeTanController::class) {
            $db = new \Database\Database();
            $leTanRepo = new \App\LeTan\Repositories\LeTanRepository($db);
            $hoaDonRepo = new \App\HoaDon\Repositories\HoaDonRepository($db);
            $thanhToanRepo = new \App\ThanhToan\Repositories\ThanhToanRepository($db);
            $service = new \App\LeTan\Services\LeTanService($leTanRepo, $hoaDonRepo, $thanhToanRepo);
            $controller = new $controllerClass($service);
        } elseif ($controllerClass === \App\ThanhToan\Controllers\ThanhToanController::class) {
            $db = new \Database\Database();
            $thanhToanRepo = new \App\ThanhToan\Repositories\ThanhToanRepository($db);
            $hoaDonRepo = new \App\HoaDon\Repositories\HoaDonRepository($db);
            $service = new \App\ThanhToan\Services\ThanhToanService($thanhToanRepo, $hoaDonRepo);
            $controller = new $controllerClass($service);
        } elseif ($controllerClass === \App\HoaDon\Controllers\HoaDonController::class) {
            $db = new \Database\Database();
            $hoaDonRepo = new \App\HoaDon\Repositories\HoaDonRepository($db);
            $service = new \App\HoaDon\Services\HoaDonService($hoaDonRepo);
            $controller = new $controllerClass($service);
        } elseif ($controllerClass === \App\Phong\Controllers\PhongController::class) {
            $db = new \Database\Database();
            $phongRepo = new \App\Phong\Repositories\PhongRepository($db);
            $service = new \App\Phong\Services\PhongService($phongRepo);
            $controller = new $controllerClass($service);
        } elseif ($controllerClass === \App\Admin\Controllers\AdminController::class) {
            $db = new \Database\Database();
            $repo = new \App\Admin\Repositories\AdminRepository($db->getConnection());
            $service = new \App\Admin\Services\AdminService($repo);
            $controller = new $controllerClass($service);
        } elseif ($controllerClass === \App\KhuyenMai\Controllers\KhuyenMaiController::class) {
            $repo = new \App\KhuyenMai\Repositories\KhuyenMaiRepository();
            $service = new \App\KhuyenMai\Services\KhuyenMaiService($repo);
            $controller = new $controllerClass($service);
        }

        // Tạo Request giả lập hỗ trợ JSON body, POST và GET
        $request = new class {
            public function all() {
                $raw = json_decode(file_get_contents('php://input'), true);
                if (is_array($raw)) {
                    return array_merge($_GET ?? [], $raw);
                }
                return array_merge($_GET ?? [], $_POST ?? []);
            }
        };

        if ($controller) {
            $controller->$method($request, null);
        } else {
            echo json_encode(['error' => 'Chưa cấu hình DI cho Controller này.']);
        }
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

\App\DichVu\Routes\DichVuRoute::register($router);

\App\LeTan\Routes\LeTanRoute::register($router);

\App\Phong\Routes\PhongRoute::register($router);

\App\ThanhToan\Routes\ThanhToanRoute::register($router);

\App\HoaDon\Routes\HoaDonRoute::register($router);

\App\Admin\Routes\AdminRoute::register($router);

\App\KhuyenMai\Routes\KhuyenMaiRoutes::register($router);


/*
|--------------------------------------------------------------------------
| Kết nối Database
|--------------------------------------------------------------------------
*/

$db = Database::getConnection();
// Bỏ echo "KẾT NỐI MYSQL THÀNH CÔNG!" và exit; để request có thể chạy tiếp xuống dưới.

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

// Xử lý URI khi chạy PHP Server từ thư mục gốc
$uri = str_replace('/Backend/public/index.php', '', $uri);
if ($uri === '' || $uri === '/') {
    $uri = '/';
}

$router->dispatch($method, $uri);