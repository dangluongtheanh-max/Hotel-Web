<?php

declare(strict_types=1);

namespace App\Admin\Services;

use App\Admin\Repositories\AdminTraCuuRepository;
use App\Admin\Repositories\AdminRepository;

/**
 * Service tra cứu thông tin vận hành khách sạn (Khách hàng, Phiếu đặt, Hóa đơn).
 */
class AdminTraCuuService
{
    private $repository;

    public function __construct($repository = null)
    {
        if ($repository instanceof AdminTraCuuRepository) {
            $this->repository = $repository;
        } elseif ($repository instanceof AdminRepository) {
            $this->repository = $repository->getTraCuuRepo();
        } else {
            $this->repository = new AdminTraCuuRepository();
        }
    }

    public function xemDSKhachHang(array $params = []): array
    {
        $keyword = trim((string)($params['keyword'] ?? $params['TuKhoa'] ?? ''));
        $list = $this->repository->getAllKhachHang(
            $keyword === '' ? [] : ['keyword' => $keyword]
        );

        return [
            'status' => 'success',
            'keyword' => $keyword,
            'total' => count($list),
            'data' => $list,
        ];
    }

    public function xemDSPhieuNhanPhong(array $params = []): array
    {
        $keyword = trim((string)($params['keyword'] ?? $params['TuKhoa'] ?? ''));
        $list = $this->repository->getAllPhieuNhanPhong(
            $keyword === '' ? [] : ['keyword' => $keyword]
        );

        return [
            'status' => 'success',
            'keyword' => $keyword,
            'total' => count($list),
            'data' => $list,
        ];
    }

    public function xemDSHoaDon(array $params = []): array
    {
        $keyword = trim((string)($params['keyword'] ?? $params['TuKhoa'] ?? ''));
        $list = $this->repository->getAllHoaDon(
            $keyword === '' ? [] : ['keyword' => $keyword]
        );

        return [
            'status' => 'success',
            'keyword' => $keyword,
            'total' => count($list),
            'data' => $list,
        ];
    }
}
