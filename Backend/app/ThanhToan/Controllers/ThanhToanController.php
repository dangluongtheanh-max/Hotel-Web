<?php

namespace App\ThanhToan\Controllers;

use App\ThanhToan\Services\ThanhToanService;
use Shared\Response;
use Throwable;

/**
 * Controller cho module ThanhToan.
 * Chi nhan request, goi Service xu ly, tra ve response - khong chua business logic.
 */
class ThanhToanController
{
    private ThanhToanService $service;

    public function __construct(ThanhToanService $service)
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

    public function taoThanhToan($request = null, $response = null)
    {
        try {
            $params = $this->getParams($request);
            $result = $this->service->taoThanhToan($params);
            return Response::json($response, $result, 201);
        } catch (Throwable $e) {
            return Response::error($response, $e->getMessage(), 400);
        }
    }

    public function xemThanhToan($request = null, $response = null)
    {
        try {
            $params = $this->getParams($request);
            $result = $this->service->xemThanhToan($params);
            return Response::json($response, $result);
        } catch (Throwable $e) {
            return Response::error($response, $e->getMessage(), 400);
        }
    }

    public function capNhatTrangThaiThanhToan($request = null, $response = null)
    {
        try {
            $params = $this->getParams($request);
            $result = $this->service->capNhatTrangThaiThanhToan($params);
            return Response::json($response, $result);
        } catch (Throwable $e) {
            return Response::error($response, $e->getMessage(), 400);
        }
    }
}
