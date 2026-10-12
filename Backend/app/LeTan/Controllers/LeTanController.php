<?php

namespace App\LeTan\Controllers;

use App\LeTan\Services\LeTanService;
use Shared\Response;

/**
 * Controller cho module LeTan.
 * Chi nhan request, goi Service xu ly, tra ve response - khong chua business logic.
 */
class LeTanController
{
    private LeTanService $service;

    public function __construct(LeTanService $service)
    {
        $this->service = $service;
    }

    public function xemDSPhong($request, $response)
    {
        return $this->respond($response, $this->service->xemDSPhong($request->all()));
    }

    public function xemCTPhong($request, $response)
    {
        return $this->respond($response, $this->service->xemCTPhong($request->all()));
    }

    public function timKiemPhong($request, $response)
    {
        return $this->respond($response, $this->service->timKiemPhong($request->all()));
    }

    public function kiemTraPhongTrong($request, $response)
    {
        return $this->respond($response, $this->service->kiemTraPhongTrong($request->all()));
    }

    public function xemDSPhieu($request, $response)
    {
        $result = $this->service->xemDSPhieu($request->all());
        return Response::json($response, $result);
    }

    public function xemCTPhieu($request, $response)
    {
        $result = $this->service->xemCTPhieu($request->all());
        return Response::json($response, $result);
    }

    public function timKiemPhieu($request, $response)
    {
        $result = $this->service->timKiemPhieu($request->all());
        return Response::json($response, $result);
    }

    public function checkin($request, $response)
    {
        $result = $this->service->checkin($request->all());
        return Response::json($response, $result);
    }

    public function checkout($request, $response)
    {
        $result = $this->service->checkout($request->all());
        return Response::json($response, $result);
    }

    public function timHoaDon($request, $response)
    {
        $result = $this->service->timHoaDon($request->all());
        return Response::json($response, $result);
    }

    public function xemHoaDon($request, $response)
    {
        $result = $this->service->xemHoaDon($request->all());
        return Response::json($response, $result);
    }

    public function xemCTHD($request, $response)
    {
        $result = $this->service->xemCTHD($request->all());
        return Response::json($response, $result);
    }

    private function respond($response, array $result)
    {
        if (!($result['success'] ?? false)) {
            return Response::error(
                $response,
                $result['error'] ?? 'Không thể xử lý yêu cầu.',
                $result['statusCode'] ?? 400
            );
        }

        return Response::json($response, $result);
    }
}
