<?php

declare(strict_types=1);

namespace App\HoaDon\Controllers;

use App\HoaDon\Services\HoaDonService;
use Shared\Response;
use Exception;

/**
 * Controller cho module HoaDon.
 * Chỉ nhận request, gọi Service xử lý, trả về response - không chứa business logic.
 */
class HoaDonController
{
    private HoaDonService $service;

    public function __construct(HoaDonService $service)
    {
        $this->service = $service;
    }

    public function taoHoaDon($request, $response)
    {
        try {
            $result = $this->service->taoHoaDon($request->all());
            $data = (isset($result['data'])) ? $result['data'] : $result;
            return Response::json($response, $data);
        } catch (Exception $e) {
            return Response::error($response, $e->getMessage(), 400);
        }
    }

    public function xemHoaDon($request, $response)
    {
        try {
            $result = $this->service->xemHoaDon($request->all());
            $data = (isset($result['data'])) ? $result['data'] : $result;
            return Response::json($response, $data);
        } catch (Exception $e) {
            return Response::error($response, $e->getMessage(), 400);
        }
    }

    public function xemCTHD($request, $response)
    {
        try {
            $result = $this->service->xemCTHD($request->all());
            $data = (isset($result['data'])) ? $result['data'] : $result;
            return Response::json($response, $data);
        } catch (Exception $e) {
            return Response::error($response, $e->getMessage(), 400);
        }
    }

    public function timHoaDon($request, $response)
    {
        try {
            $result = $this->service->timHoaDon($request->all());
            $data = (isset($result['data'])) ? $result['data'] : $result;
            return Response::json($response, $data);
        } catch (Exception $e) {
            return Response::error($response, $e->getMessage(), 400);
        }
    }
}
