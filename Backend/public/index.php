<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Database\Database;
use Shared\Request;

/*
|--------------------------------------------------------------------------
| Load file .env
|--------------------------------------------------------------------------
*/

if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

/*
|--------------------------------------------------------------------------
| Load cấu hình ứng dụng
|--------------------------------------------------------------------------
*/

$config = file_exists(__DIR__ . '/../config/App.php') ? require __DIR__ . '/../config/App.php' : ['timezone' => 'Asia/Ho_Chi_Minh'];
date_default_timezone_set($config['timezone'] ?? 'Asia/Ho_Chi_Minh');

/*
|--------------------------------------------------------------------------
| Router đơn giản có hỗ trợ Base Path XAMPP & Dependency Injection
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

    private array $container = [];

    public function resolve(string $className): object
    {
        if (isset($this->container[$className])) {
            return $this->container[$className];
        }

        if (!class_exists($className)) {
            throw new \RuntimeException("Class {$className} does not exist");
        }

        $ref = new \ReflectionClass($className);
        $ctor = $ref->getConstructor();

        if (!$ctor || $ctor->getNumberOfParameters() === 0) {
            $instance = new $className();
            $this->container[$className] = $instance;
            return $instance;
        }

        $dependencies = [];
        foreach ($ctor->getParameters() as $param) {
            $paramType = $param->getType();
            if ($paramType instanceof \ReflectionNamedType && !$paramType->isBuiltin()) {
                $dependencies[] = $this->resolve($paramType->getName());
            } elseif ($param->isDefaultValueAvailable()) {
                $dependencies[] = $param->getDefaultValue();
            } else {
                $dependencies[] = null;
            }
        }

        $instance = $ref->newInstanceArgs($dependencies);
        $this->container[$className] = $instance;
        return $instance;
    }

    public function dispatch(string $method, string $rawUri): void
    {
        // Loại bỏ base path nếu chạy trong thư mục con của XAMPP
        $uri = $rawUri;
        $scriptName = dirname($_SERVER['SCRIPT_NAME'] ?? '');
        if ($scriptName !== '/' && $scriptName !== '\\' && !empty($scriptName)) {
            $scriptName = str_replace('\\', '/', $scriptName);
            if (str_starts_with($uri, $scriptName)) {
                $uri = substr($uri, strlen($scriptName));
            }
        }
        $uri = '/' . ltrim($uri, '/');

        // Route trang chu kiem tra trang thai
        if ($uri === '/' || $uri === '/index.php') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'status' => 'online',
                'message' => 'Hotel Booking API Server is running!',
                'time' => date('Y-m-d H:i:s'),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            return;
        }

        $normalizedUri = rtrim($uri, '/') ?: '/';
        $handler = $this->routes[$method][$uri] ?? $this->routes[$method][$normalizedUri] ?? null;

        if (!$handler) {
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error' => "Route not found: [{$method}] {$uri}"
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            if (is_callable($handler)) {
                $handler();
                return;
            }

            if (is_array($handler) && count($handler) === 2) {
                [$controllerClass, $methodName] = $handler;

                if (!class_exists($controllerClass)) {
                    http_response_code(500);
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => false, 'error' => "Class {$controllerClass} not found"]);
                    return;
                }

                $controller = $this->resolve($controllerClass);
                if (!method_exists($controller, $methodName)) {
                    http_response_code(500);
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => false, 'error' => "Method {$methodName} not found in {$controllerClass}"]);
                    return;
                }

                $request = Request::capture();
                $response = null;

                $controller->$methodName($request, $response);
                return;
            }

            throw new \RuntimeException("Invalid route handler format");
        } catch (\Throwable $e) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
    }
};

/*
|--------------------------------------------------------------------------
| Đăng ký routes của các module
|--------------------------------------------------------------------------
*/

if (class_exists(\App\TaiKhoan\Routes\TaiKhoanRoute::class)) {
    \App\TaiKhoan\Routes\TaiKhoanRoute::register($router);
}

if (class_exists(\App\KhachHang\Routes\KhachHangRoute::class)) {
    \App\KhachHang\Routes\KhachHangRoute::register($router);
}

if (class_exists(\App\DatPhong\Routes\DatPhongRoute::class)) {
    \App\DatPhong\Routes\DatPhongRoute::register($router);
}

if (class_exists(\App\DanhGia\Routes\DanhGiaRoute::class)) {
    \App\DanhGia\Routes\DanhGiaRoute::register($router);
}

if (class_exists(\App\LeTan\Routes\LeTanRoute::class)) {
    \App\LeTan\Routes\LeTanRoute::register($router);
}

if (class_exists(\App\Phong\Routes\PhongRoute::class)) {
    \App\Phong\Routes\PhongRoute::register($router);
}

if (class_exists(\App\DichVu\Routes\DichVuRoutes::class)) {
    \App\DichVu\Routes\DichVuRoutes::register($router);
}

if (class_exists(\App\KhuyenMai\Routes\KhuyenMaiRoutes::class)) {
    \App\KhuyenMai\Routes\KhuyenMaiRoutes::register($router);
}

if (class_exists(\App\ThanhToan\Routes\ThanhToanRoute::class)) {
    \App\ThanhToan\Routes\ThanhToanRoute::register($router);
}

if (class_exists(\App\HoaDon\Routes\HoaDonRoute::class)) {
    \App\HoaDon\Routes\HoaDonRoute::register($router);
}

if (class_exists(\App\Admin\Routes\AdminRoute::class)) {
    \App\Admin\Routes\AdminRoute::register($router);
}

/*
|--------------------------------------------------------------------------
| Xử lý request
|--------------------------------------------------------------------------
*/

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

$router->dispatch($method, $uri);