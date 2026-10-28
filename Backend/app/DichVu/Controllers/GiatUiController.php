<?php

declare(strict_types=1);

namespace App\DichVu\Controllers;

use App\DichVu\Services\GiatUiService;
use Shared\Response;

/**
 * Controller tiếp nhận yêu cầu API cho phân hệ Giặt ủi (DichVu/Controllers/GiatUiController).
 */
class GiatUiController
{
    private GiatUiService $service;

    public function __construct(GiatUiService $service)
    {
        $this->service = $service;
    }

    /**
     * API: Lấy bảng giá cố định 8 loại đồ giặt ủi (GET/POST /dichvu/giatui)
     */
    public function layDanhSachGiatUi($request, $response = null)
    {
        $result = $this->service->layDanhSachGiatUi();
        $httpCode = $result['code'] ?? 200;
        return Response::json($response, $result, $httpCode);
    }

    /**
     * API: Đặt dịch vụ giặt ủi nhiều món (POST /dichvu/dat-giatui)
     */
    public function datDichVuGiatUi($request, $response = null)
    {
        $params = $request->all();
        $result = $this->service->datDichVuGiatUi($params);
        $httpCode = $result['code'] ?? 200;
        return Response::json($response, $result, $httpCode);
    }
}
