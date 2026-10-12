<?php

namespace App\Phong\Controllers;

use App\Phong\Services\PhongService;
use Shared\Response;

/**
 * Controller cho module Phong.
 * Chi nhan request, goi Service xu ly, tra ve response - khong chua business logic.
 */
class PhongController
{
    private PhongService $service;

    public function __construct(PhongService $service)
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

    public function themPhong($request, $response)
    {
        return $this->respond($response, $this->service->themPhong($request->all()));
    }

    public function capNhatThongTinPhong($request, $response)
    {
        return $this->respond($response, $this->service->capNhatThongTinPhong($request->all()));
    }

    public function timKiemPhong($request, $response)
    {
        return $this->respond($response, $this->service->timKiemPhong($request->all()));
    }

    public function kiemTraTrangThaiPhong($request, $response)
    {
        return $this->respond($response, $this->service->kiemTraTrangThaiPhong($request->all()));
    }

    public function kiemTraPhongTrong($request, $response)
    {
        return $this->respond($response, $this->service->kiemTraPhongTrong($request->all()));
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
