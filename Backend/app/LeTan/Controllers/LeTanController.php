<?php

namespace App\LeTan\Controllers;

use App\LeTan\Services\LeTanService;
use Shared\Response;
use Shared\Request;

/**
 * Controller cho module LeTan (BE2).
 * Chỉ nhận request, điều phối Service xử lý, trả về JSON hoặc HTML Dashboard - không chứa business logic.
 */
class LeTanController
{
    private LeTanService $service;

    public function __construct(?LeTanService $service = null)
    {
        $this->service = $service ?? new LeTanService();
    }

    private function getParams($request): array
    {
        if ($request instanceof Request) {
            return $request->all();
        }
        if (is_array($request)) {
            return $request;
        }
        if (is_object($request) && method_exists($request, 'all')) {
            return $request->all();
        }
        return Request::capture()->all();
    }

    /**
     * Use case 1: Xem danh sách phòng
     * POST /letan/xemdsphong
     */
    public function xemDSPhong($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->xemDSPhong($params);
        $status = ($result['success'] ?? false) ? 200 : 400;
        return Response::json($response, $result, $status);
    }

    /**
     * Use case 2: Xem chi tiết phòng
     * POST /letan/xemctphong
     */
    public function xemCTPhong($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->xemCTPhong($params);
        $status = ($result['success'] ?? false) ? 200 : 404;
        return Response::json($response, $result, $status);
    }

    /**
     * Use case 3: Tìm kiếm phòng
     * POST /letan/timkiemphong
     */
    public function timKiemPhong($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->timKiemPhong($params);
        $status = ($result['success'] ?? false) ? 200 : 400;
        return Response::json($response, $result, $status);
    }

    /**
     * Use case 4: Xem danh sách phiếu
     * POST /letan/xemdsphieu
     */
    public function xemDSPhieu($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->xemDSPhieu($params);
        $status = ($result['success'] ?? false) ? 200 : 400;
        return Response::json($response, $result, $status);
    }

    /**
     * Use case 5: Xem chi tiết phiếu
     * POST /letan/xemctphieu
     */
    public function xemCTPhieu($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->xemCTPhieu($params);
        $status = ($result['success'] ?? false) ? 200 : 404;
        return Response::json($response, $result, $status);
    }

    /**
     * Use case 6: Tìm kiếm phiếu
     * POST /letan/timkiemphieu
     */
    public function timKiemPhieu($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->timKiemPhieu($params);
        $status = ($result['success'] ?? false) ? 200 : 400;
        return Response::json($response, $result, $status);
    }

    /**
     * Use case 7: Check-in
     * POST /letan/checkin
     */
    public function checkin($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->checkin($params);
        $status = ($result['success'] ?? false) ? 200 : 400;
        return Response::json($response, $result, $status);
    }

    /**
     * Use case 8: Check-out
     * POST /letan/checkout
     */
    public function checkout($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->checkout($params);
        $status = ($result['success'] ?? false) ? 200 : 400;
        return Response::json($response, $result, $status);
    }

    /**
     * Use case 9: Tìm hóa đơn
     * POST /letan/timhoadon
     */
    public function timHoaDon($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->timHoaDon($params);
        $status = ($result['success'] ?? false) ? 200 : 400;
        return Response::json($response, $result, $status);
    }

    /**
     * Use case 10: Xem hóa đơn
     * POST /letan/xemhoadon
     */
    public function xemHoaDon($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->xemHoaDon($params);
        $status = ($result['success'] ?? false) ? 200 : 404;
        return Response::json($response, $result, $status);
    }

    /**
     * Use case 11: Xem chi tiết hóa đơn
     * POST /letan/xemcthd
     */
    public function xemCTHD($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->xemCTHD($params);
        $status = ($result['success'] ?? false) ? 200 : 404;
        return Response::json($response, $result, $status);
    }
}
