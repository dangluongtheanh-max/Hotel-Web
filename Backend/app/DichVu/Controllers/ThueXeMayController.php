<?php

declare(strict_types=1);

namespace App\DichVu\Controllers;

use App\DichVu\Services\ThueXeMayService;
use Shared\Response;

/**
 * Controller tiếp nhận yêu cầu API cho phân hệ Thuê xe máy (DichVu/Controllers/ThueXeMayController).
 */
class ThueXeMayController
{
    private ThueXeMayService $service;

    public function __construct(ThueXeMayService $service)
    {
        $this->service = $service;
    }

    /**
     * API: Lấy danh sách các dòng xe máy cho thuê (GET/POST /dichvu/xemay)
     */
    public function layDanhSachXeMay($request, $response = null)
    {
        $result = $this->service->layDanhSachXeMay();
        $httpCode = $result['code'] ?? 200;
        return Response::json($response, $result, $httpCode);
    }

    /**
     * API: Đăng ký đặt thuê xe máy (POST /dichvu/dat-thuexe)
     */
    public function datThueXeMay($request, $response = null)
    {
        $params = $request->all();
        $result = $this->service->datThueXeMay($params);
        $httpCode = $result['code'] ?? 200;
        return Response::json($response, $result, $httpCode);
    }
}
