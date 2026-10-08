<?php

namespace App\HoaDon\Controllers;

use App\HoaDon\Services\HoaDonService;
use Shared\Response;
use Throwable;

/**
 * Controller cho module HoaDon.
 * Chi nhan request, goi Service xu ly, tra ve response - khong chua business logic.
 */
class HoaDonController
{
    private HoaDonService $service;

    public function __construct(HoaDonService $service)
    {
        $this->service = $service;
    }

    private function getParams($request): array
    {
        if (is_array($request)) {
            return $request;
        }
        if (is_object($request) && method_exists($request, 'all')) {
            return $request->all();
        }
        $body = file_get_contents('php://input');
        $json = json_decode($body, true);
        if (is_array($json)) {
            return array_merge($_GET, $_POST, $json);
        }
        return array_merge($_GET, $_POST);
    }

    public function taoHoaDon($request = null, $response = null)
    {
        try {
            $params = $this->getParams($request);
            $result = $this->service->taoHoaDon($params);
            return Response::json($response, $result, 201);
        } catch (Throwable $e) {
            return Response::error($response, $e->getMessage(), 400);
        }
    }

    public function xemHoaDon($request = null, $response = null)
    {
        try {
            $params = $this->getParams($request);
            $result = $this->service->xemHoaDon($params);
            if ($result === null) {
                return Response::error($response, 'Không tìm thấy hóa đơn.', 404);
            }
            return Response::json($response, $result);
        } catch (Throwable $e) {
            return Response::error($response, $e->getMessage(), 400);
        }
    }

    public function xemCTHD($request = null, $response = null)
    {
        try {
            $params = $this->getParams($request);
            $result = $this->service->xemCTHD($params);
            return Response::json($response, $result);
        } catch (Throwable $e) {
            return Response::error($response, $e->getMessage(), 400);
        }
    }

    public function timHoaDon($request = null, $response = null)
    {
        try {
            $params = $this->getParams($request);
            $result = $this->service->timHoaDon($params);
            return Response::json($response, $result);
        } catch (Throwable $e) {
            return Response::error($response, $e->getMessage(), 400);
        }
    }
}
