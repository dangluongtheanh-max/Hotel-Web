<?php

declare(strict_types=1);

namespace App\DanhGia\Controllers;

use App\DanhGia\Services\DanhGiaService;
use Shared\Response;

/**
 * Controller cho module DanhGia.
 * Chi nhan request, goi Service xu ly, tra ve response - khong chua business logic.
 */
class DanhGiaController
{
    private DanhGiaService $service;

    public function __construct(DanhGiaService $service)
    {
        $this->service = $service;
    }

    public function themDanhGia($request, $response)
    {
        $params = $request->all();
        if (empty($params['maTK']) && empty($params['MaTK'])) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
            if (empty($authHeader) && function_exists('getallheaders')) {
                $headers = getallheaders();
                $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
            }
            if (!empty($authHeader)) {
                $params['maTK'] = trim(str_replace(['Bearer ', 'bearer '], '', $authHeader));
            }
        }

        $result = $this->service->themDanhGia($params);
        if (($result['status'] ?? '') === 'error') {
            $code = $result['code'] ?? 400;
            return Response::error($response, $result['message'] ?? 'Thao tác thất bại.', $code);
        }
        return Response::json($response, $result['data'] ?? $result, 200);
    }

    public function xoaDanhGia($request, $response)
    {
        $result = $this->service->xoaDanhGia($request->all());
        if (($result['status'] ?? '') === 'error') {
            return Response::error($response, $result['message'] ?? 'Thao tác thất bại.', 400);
        }
        return Response::json($response, $result);
    }

    public function xemDanhGiaTheoPhieu($request, $response)
    {
        $result = $this->service->xemDanhGiaTheoPhieu($request->all());
        if (($result['status'] ?? '') === 'error') {
            return Response::error($response, $result['message'] ?? 'Thao tác thất bại.', 400);
        }
        return Response::json($response, $result['data'] ?? $result);
    }

}
