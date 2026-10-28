<?php

declare(strict_types=1);

namespace App\DichVu\Controllers;

use App\DichVu\Services\DichVuService;
use Shared\Response;

/**
 * Facade Controller cho module DichVu.
 * Tiếp nhận request và ủy quyền cho DichVuService điều phối.
 */
class DichVuController
{
    private DichVuService $service;

    public function __construct(DichVuService $service)
    {
        $this->service = $service;
    }

    public function layTatCaDichVu($request, $response = null)
    {
        $result = $this->service->layTatCaDichVu();
        $httpCode = $result['code'] ?? 200;
        return Response::json($response, $result, $httpCode);
    }

    public function layPhongDangLuuTru($request, $response = null)
    {
        $params = $request->all();
        $result = $this->service->layPhongDangLuuTru($params['maTK'] ?? null, $params['maKH'] ?? null);
        $httpCode = $result['code'] ?? 200;
        return Response::json($response, $result, $httpCode);
    }

    public function layDanhSachMonAn($request, $response = null)
    {
        $result = $this->service->layDanhSachMonAn();
        $httpCode = $result['code'] ?? 200;
        return Response::json($response, $result, $httpCode);
    }

    public function datMonAn($request, $response = null)
    {
        $result = $this->service->datMonAn($request->all());
        $httpCode = $result['code'] ?? 200;
        return Response::json($response, $result, $httpCode);
    }

    public function layDanhSachGiatUi($request, $response = null)
    {
        $result = $this->service->layDanhSachGiatUi();
        $httpCode = $result['code'] ?? 200;
        return Response::json($response, $result, $httpCode);
    }

    public function datDichVuGiatUi($request, $response = null)
    {
        $result = $this->service->datDichVuGiatUi($request->all());
        $httpCode = $result['code'] ?? 200;
        return Response::json($response, $result, $httpCode);
    }

    public function layDanhSachXeMay($request, $response = null)
    {
        $result = $this->service->layDanhSachXeMay();
        $httpCode = $result['code'] ?? 200;
        return Response::json($response, $result, $httpCode);
    }

    public function datThueXeMay($request, $response = null)
    {
        $result = $this->service->datThueXeMay($request->all());
        $httpCode = $result['code'] ?? 200;
        return Response::json($response, $result, $httpCode);
    }
}
