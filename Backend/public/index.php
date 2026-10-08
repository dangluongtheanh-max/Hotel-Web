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

date_default_timezone_set($config['timezone'] ?? 'Asia/Ho_Chi_Minh');

/*
|--------------------------------------------------------------------------
| Cấu hình CORS cho Frontend gọi API
|--------------------------------------------------------------------------
*/

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/*
|--------------------------------------------------------------------------
| Router & DI Container
|--------------------------------------------------------------------------
*/

$router = new class {

    public array $routes = [];
    private array $container = [];

    public function post(string $path, array|callable $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function get(string $path, array|callable $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    /**
     * Auto-wiring Dependency Injection container
     */
    public function resolve(string $className): object
    {
        if (isset($this->container[$className])) {
            return $this->container[$className];
        }

        if (!class_exists($className)) {
            throw new RuntimeException("Class {$className} does not exist");
        }

        $ref = new ReflectionClass($className);
        $ctor = $ref->getConstructor();

        if (!$ctor || $ctor->getNumberOfParameters() === 0) {
            $instance = new $className();
            $this->container[$className] = $instance;
            return $instance;
        }

        $dependencies = [];
        foreach ($ctor->getParameters() as $param) {
            $paramType = $param->getType();
            if ($paramType instanceof ReflectionNamedType && !$paramType->isBuiltin()) {
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

    public function dispatch(string $method, string $uri): void
    {
        $normalizedUri = rtrim($uri, '/') ?: '/';
        $handler = $this->routes[$method][$uri] ?? $this->routes[$method][$normalizedUri] ?? null;

        if (!$handler) {
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error' => "Route not found: {$method} {$uri}"
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            if (is_callable($handler)) {
                $handler();
                return;
            }

            if (is_array($handler) && count($handler) === 2) {
                [$controllerClass, $action] = $handler;
                $controller = $this->resolve($controllerClass);

                if (!method_exists($controller, $action)) {
                    throw new RuntimeException("Action '{$action}' does not exist in {$controllerClass}");
                }

                $controller->$action();
                return;
            }

            throw new RuntimeException("Invalid route handler format");
        } catch (Throwable $e) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
        }
    }
};

/*
|--------------------------------------------------------------------------
| Đăng ký routes của các module
|--------------------------------------------------------------------------
*/

$router->get('/', function () {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'message' => 'Hotel-Web Backend API is active',
        'time' => date('Y-m-d H:i:s'),
    ], JSON_UNESCAPED_UNICODE);
});

\App\TaiKhoan\Routes\TaiKhoanRoute::register($router);
\App\KhachHang\Routes\KhachHangRoute::register($router);
\App\DatPhong\Routes\DatPhongRoute::register($router);
\App\DanhGia\Routes\DanhGiaRoute::register($router);
\App\LeTan\Routes\LeTanRoute::register($router);
\App\Phong\Routes\PhongRoute::register($router);
\App\ThanhToan\Routes\ThanhToanRoute::register($router);
\App\HoaDon\Routes\HoaDonRoute::register($router);
\App\Admin\Routes\AdminRoute::register($router);

/*
|--------------------------------------------------------------------------
| Xử lý request
|--------------------------------------------------------------------------
*/

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

$router->dispatch($method, $uri);