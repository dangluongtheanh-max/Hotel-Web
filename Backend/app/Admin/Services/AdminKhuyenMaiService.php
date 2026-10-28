<?php

declare(strict_types=1);

namespace App\Admin\Services;

use App\Admin\Repositories\AdminKhuyenMaiRepository;
use App\Admin\Repositories\AdminRepository;

/**
 * Service quản lý chương trình khuyến mãi.
 */
class AdminKhuyenMaiService
{
    private $repository;

    public function __construct($repository = null)
    {
        if ($repository instanceof AdminKhuyenMaiRepository) {
            $this->repository = $repository;
        } elseif ($repository instanceof AdminRepository) {
            $this->repository = $repository->getKhuyenMaiRepo();
        } else {
            $this->repository = new AdminKhuyenMaiRepository();
        }
    }

    public function xemDSKhuyenMai(array $params = []): array
    {
        $keyword = trim((string)($params['keyword'] ?? $params['TuKhoa'] ?? ''));
        $list = $this->repository->getAllKhuyenMai(
            $keyword === '' ? [] : ['keyword' => $keyword]
        );

        return [
            'status' => 'success',
            'keyword' => $keyword,
            'total' => count($list),
            'data' => $list,
        ];
    }
    /* =========================================================================
     * SERVICE: QUẢN LÝ KHUYẾN MÃI
     * ========================================================================= */

    public function sinhMaKhuyenMai(): array
    {
        return [
            'status' => 'success',
            'maKM' => $this->repository->generateMaKhuyenMai(),
        ];
    }

    public function themKhuyenMai(array $data): array
    {
        $tenKM = trim($data['TenKM'] ?? $data['tenKM'] ?? '');
        if (empty($tenKM)) {
            return ['status' => 'error', 'message' => 'Tên chương trình khuyến mãi không được để trống.'];
        }

        $normalized = [
            'MaKM' => $data['MaKM'] ?? $data['maKM'] ?? null,
            'TenKM' => $tenKM,
            'LoaiKM' => $data['LoaiKM'] ?? $data['loaiKM'] ?? 'PhanTram',
            'GiaTriGiam' => (float)($data['GiaTriGiam'] ?? $data['giaTriGiam'] ?? 0),
            'SoDemToiThieu' => (int)($data['SoDemToiThieu'] ?? $data['soDemToiThieu'] ?? 0),
            'NgayBatDau' => $data['NgayBatDau'] ?? $data['ngayBatDau'] ?? date('Y-m-d'),
            'NgayKetThuc' => $data['NgayKetThuc'] ?? $data['ngayKetThuc'] ?? date('Y-m-d', strtotime('+30 days')),
            'TrangThai' => $data['TrangThai'] ?? $data['trangThai'] ?? 'HoatDong',
        ];

        try {
            $created = $this->repository->createKhuyenMai($normalized);
            return ['status' => 'success', 'message' => 'Thêm khuyến mãi thành công.', 'data' => $created];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function suaKhuyenMai(string $maKM, array $data): array
    {
        if (empty($maKM)) {
            $maKM = $data['MaKM'] ?? $data['maKM'] ?? '';
        }
        if (empty($maKM)) {
            return ['status' => 'error', 'message' => 'Mã khuyến mãi không hợp lệ.'];
        }

        $normalized = [];
        if (isset($data['TenKM']) || isset($data['tenKM'])) {
            $normalized['TenKM'] = trim($data['TenKM'] ?? $data['tenKM']);
        }
        if (isset($data['LoaiKM']) || isset($data['loaiKM'])) {
            $normalized['LoaiKM'] = trim($data['LoaiKM'] ?? $data['loaiKM']);
        }
        if (isset($data['GiaTriGiam']) || isset($data['giaTriGiam'])) {
            $normalized['GiaTriGiam'] = (float)($data['GiaTriGiam'] ?? $data['giaTriGiam']);
        }
        if (isset($data['SoDemToiThieu']) || isset($data['soDemToiThieu'])) {
            $normalized['SoDemToiThieu'] = (int)($data['SoDemToiThieu'] ?? $data['soDemToiThieu']);
        }
        if (isset($data['NgayBatDau']) || isset($data['ngayBatDau'])) {
            $normalized['NgayBatDau'] = $data['NgayBatDau'] ?? $data['ngayBatDau'];
        }
        if (isset($data['NgayKetThuc']) || isset($data['ngayKetThuc'])) {
            $normalized['NgayKetThuc'] = $data['NgayKetThuc'] ?? $data['ngayKetThuc'];
        }
        if (isset($data['TrangThai']) || isset($data['trangThai'])) {
            $normalized['TrangThai'] = $data['TrangThai'] ?? $data['trangThai'];
        }

        try {
            $updated = $this->repository->updateKhuyenMai($maKM, $normalized);
            if (!$updated) {
                return ['status' => 'error', 'message' => 'Cập nhật không thành công hoặc không có dữ liệu thay đổi.'];
            }
            return [
                'status' => 'success',
                'message' => 'Cập nhật khuyến mãi thành công.',
                'data' => $this->repository->findKhuyenMaiById($maKM)
            ];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function doiTrangThaiKhuyenMai(string $maKM): array
    {
        if (empty($maKM)) {
            return ['status' => 'error', 'message' => 'Mã khuyến mãi không hợp lệ.'];
        }

        try {
            $item = $this->repository->toggleTrangThaiKhuyenMai($maKM);
            $msg = ($item['TrangThai'] === 'NgungApDung')
                ? 'Đã ngừng áp dụng chương trình khuyến mãi.'
                : 'Đã mở lại áp dụng chương trình khuyến mãi.';
            return ['status' => 'success', 'message' => $msg, 'data' => $item];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
