<?php

declare(strict_types=1);

namespace App\DichVu\Controllers;

use App\DichVu\Services\DatDoAnService;
use Shared\Response;

/**
 * Controller tiếp nhận yêu cầu API cho phân hệ Đặt đồ ăn (DichVu/Controllers/DatDoAnController).
 */
class DatDoAnController
{
    private DatDoAnService $service;

    public function __construct(DatDoAnService $service)
    {
        $this->service = $service;
    }

    /**
     * API: Lấy danh sách tất cả các dịch vụ trong khách sạn (GET/POST /dichvu/tatca)
     */
    public function layTatCaDichVu($request, $response = null)
    {
        $result = $this->service->layTatCaDichVu();
        $httpCode = $result['code'] ?? 200;
        return Response::json($response, $result, $httpCode);
    }

    /**
     * API: Lấy thực đơn món ăn phục vụ tại phòng (GET/POST /dichvu/monan)
     */
    public function layDanhSachMonAn($request, $response = null)
    {
        $result = $this->service->layDanhSachMonAn();
        $httpCode = $result['code'] ?? 200;
        return Response::json($response, $result, $httpCode);
    }

    /**
     * API: Lấy thông tin phòng đang lưu trú của khách (GET/POST /dichvu/phong-dang-luu-tru)
     */
    public function layPhongDangLuuTru($request, $response = null)
    {
        $params = $request->all();
        $maTK = $params['maTK'] ?? null;
        $maKH = $params['maKH'] ?? null;
        $result = $this->service->layPhongDangLuuTru($maTK, $maKH);
        $httpCode = $result['code'] ?? 200;
        return Response::json($response, $result, $httpCode);
    }

    /**
     * API: Đặt món ăn phục vụ tận phòng (POST /dichvu/datmon)
     */
    public function datMonAn($request, $response = null)
    {
        $params = $request->all();
        $result = $this->service->datMonAn($params);
        $httpCode = $result['code'] ?? 200;
        return Response::json($response, $result, $httpCode);
    }
}
