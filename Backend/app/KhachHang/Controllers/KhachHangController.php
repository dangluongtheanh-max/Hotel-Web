<?php

declare(strict_types=1);

namespace App\KhachHang\Controllers;

use App\KhachHang\Services\KhachHangService;
use Shared\Response;

/**
 * Controller cho module KhachHang.
 * Chi nhan request, goi Service xu ly, tra ve response - khong chua business logic.
 */
class KhachHangController
{
    private KhachHangService $service;

    public function __construct(KhachHangService $service)
    {
        $this->service = $service;
    }

    public function dangKy($request, $response)
    {
        try {
            $result = $this->service->dangKy($request->all());
            return Response::json($response, $result);
        } catch (\Exception $e) {
            return Response::error($response, $e->getMessage());
        }
    }

    public function xemDSPhong($request, $response)
    {
        $result = $this->service->xemDSPhong($request->all());
        return Response::json($response, $result);
    }

    public function xemCTPhong($request, $response)
    {
        $result = $this->service->xemCTPhong($request->all());
        return Response::json($response, $result);
    }

    public function datPhong($request, $response)
    {
        $result = $this->service->datPhong($request->all());
        return Response::json($response, $result);
    }

    public function thanhToan($request, $response)
    {
        $result = $this->service->thanhToan($request->all());
        return Response::json($response, $result);
    }

    public function huyDatPhong($request, $response)
    {
        $result = $this->service->huyDatPhong($request->all());
        return Response::json($response, $result);
    }

    public function xemLichSuDatPhong($request, $response)
    {
        $result = $this->service->xemLichSuDatPhong($request->all());
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

    public function xemThongTinCaNhan($request, $response)
    {
        try {
            $result = $this->service->xemThongTinCaNhan($request->all());
            return Response::json($response, $result);
        } catch (\Throwable $e) {
            $code = is_numeric($e->getCode()) && (int)$e->getCode() >= 400 && (int)$e->getCode() < 600 ? (int)$e->getCode() : 400;
            return Response::error($response, $e->getMessage(), $code);
        }
    }

    public function capNhatThongTinCaNhan($request, $response)
    {
        try {
            $result = $this->service->capNhatThongTinCaNhan($request->all());
            return Response::json($response, $result);
        } catch (\Throwable $e) {
            $code = is_numeric($e->getCode()) && (int)$e->getCode() >= 400 && (int)$e->getCode() < 600 ? (int)$e->getCode() : 400;
            return Response::error($response, $e->getMessage(), $code);
        }
    }

    public function doiMatKhau($request, $response)
    {
        try {
            $result = $this->service->doiMatKhau($request->all());
            return Response::json($response, $result);
        } catch (\Throwable $e) {
            $code = is_numeric($e->getCode()) && (int)$e->getCode() >= 400 && (int)$e->getCode() < 600 ? (int)$e->getCode() : 400;
            return Response::error($response, $e->getMessage(), $code);
        }
    }

    public function themDanhGia($request, $response)
    {
        $result = $this->service->themDanhGia($request->all());
        return Response::json($response, $result);
    }

    public function xoaDanhGia($request, $response)
    {
        $result = $this->service->xoaDanhGia($request->all());
        return Response::json($response, $result);
    }

    public function timTheoSDT($request, $response)
    {
        try {
            $params = $request->all();
            $sdt = $params['sdt'] ?? ($params['SDT'] ?? '');
            $khach = $this->service->timTheoSDT($sdt);
            if (!$khach) {
                return Response::json($response, [
                    'found' => false,
                    'message' => 'Không tìm thấy khách hàng với số điện thoại này.'
                ]);
            }
            return Response::json($response, [
                'found' => true,
                'data' => $khach
            ]);
        } catch (\Exception $e) {
            return Response::error($response, $e->getMessage(), 400);
        }
    }

}
