<?php

declare(strict_types=1);

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
        try {
            $result = $this->service->xemDSPhong($request->all());
            return Response::json($response, $result);
        } catch (\Exception $e) {
            return Response::error($response, $e->getMessage(), 400);
        }
    }

    public function xemCTPhong($request, $response)
    {
        try {
            $result = $this->service->xemCTPhong($request->all());
            return Response::json($response, $result);
        } catch (\Exception $e) {
            return Response::error($response, $e->getMessage(), 400);
        }
    }

    public function timKiemPhong($request, $response)
    {
        try {
            $result = $this->service->timKiemPhong($request->all());
            return Response::json($response, $result);
        } catch (\Exception $e) {
            return Response::error($response, $e->getMessage(), 400);
        }
    }

    public function xemTrangThaiPhong($request, $response)
    {
        try {
            $result = $this->service->xemTrangThaiLoaiPhongVaPhong($request->all());
            return Response::json($response, $result);
        } catch (\Exception $e) {
            return Response::error($response, $e->getMessage(), 400);
        }
    }

    public function xemDSPhieu($request, $response)
    {
        try {
            $result = $this->service->xemDSPhieu($request->all());
            return Response::json($response, $result);
        } catch (\Exception $e) {
            return Response::error($response, $e->getMessage(), 400);
        }
    }

    public function xemCTPhieu($request, $response)
    {
        try {
            $result = $this->service->xemCTPhieu($request->all());
            return Response::json($response, $result);
        } catch (\Exception $e) {
            return Response::error($response, $e->getMessage(), 400);
        }
    }

    public function timKiemPhieu($request, $response)
    {
        try {
            $result = $this->service->timKiemPhieu($request->all());
            return Response::json($response, $result);
        } catch (\Exception $e) {
            return Response::error($response, $e->getMessage(), 400);
        }
    }

    public function checkin($request, $response)
    {
        try {
            $result = $this->service->checkin($request->all());
            return Response::json($response, $result);
        } catch (\Exception $e) {
            return Response::error($response, $e->getMessage(), 400);
        }
    }

    public function tinhTienHoanHuy($request, $response)
    {
        try {
            $result = $this->service->tinhTienHoanHuyPhong($request->all());
            return Response::json($response, $result);
        } catch (\Exception $e) {
            return Response::error($response, $e->getMessage(), 400);
        }
    }

    public function huyPhieu($request, $response)
    {
        try {
            $result = $this->service->huyPhieu($request->all());
            return Response::json($response, $result);
        } catch (\Exception $e) {
            return Response::error($response, $e->getMessage(), 400);
        }
    }

    public function tinhTienCheckout($request, $response)
    {
        try {
            $result = $this->service->tinhTienCheckout($request->all());
            return Response::json($response, $result);
        } catch (\Exception $e) {
            return Response::error($response, $e->getMessage(), 400);
        }
    }

    public function checkout($request, $response)
    {
        try {
            $result = $this->service->checkout($request->all());
            return Response::json($response, $result);
        } catch (\Exception $e) {
            return Response::error($response, $e->getMessage(), 400);
        }
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

    public function doiPhongNguoiLuuTru($request, $response)
    {
        try {
            $result = $this->service->doiPhongNguoiLuuTru($request->all());
            return Response::json($response, $result);
        } catch (\Exception $e) {
            return Response::error($response, $e->getMessage(), 400);
        }
    }

    public function capNhatThongTinKhach($request, $response)
    {
        try {
            $result = $this->service->capNhatThongTinKhach($request->all());
            return Response::json($response, $result);
        } catch (\Exception $e) {
            return Response::error($response, $e->getMessage(), 400);
        }
    }

    public function capNhatBaoTri($request, $response)
    {
        try {
            $result = $this->service->capNhatBaoTriPhong($request->all());
            return Response::json($response, $result);
        } catch (\Exception $e) {
            return Response::error($response, $e->getMessage(), 400);
        }
    }

    public function layDanhSachDichVu($request, $response)
    {
        try {
            $result = $this->service->layDanhSachDichVu($request->all());
            return Response::json($response, $result);
        } catch (\Exception $e) {
            return Response::error($response, $e->getMessage(), 400);
        }
    }

    public function datDichVuNhanh($request, $response)
    {
        try {
            $result = $this->service->datDichVuNhanh($request->all());
            return Response::json($response, $result);
        } catch (\Exception $e) {
            return Response::error($response, $e->getMessage(), 400);
        }
    }
}
