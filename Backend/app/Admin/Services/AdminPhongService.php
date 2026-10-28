<?php

declare(strict_types=1);

namespace App\Admin\Services;

use App\Admin\Repositories\AdminPhongRepository;
use App\Admin\Repositories\AdminRepository;

/**
 * Service quản lý phòng vật lý và loại phòng.
 */
class AdminPhongService
{
    private $repository;

    public function __construct($repository = null)
    {
        if ($repository instanceof AdminPhongRepository) {
            $this->repository = $repository;
        } elseif ($repository instanceof AdminRepository) {
            $this->repository = $repository->getPhongRepo();
        } else {
            $this->repository = new AdminPhongRepository();
        }
    }

    public function xemDSPhongTheoLoai(array $params = []): array
    {
        $list = $this->repository->getPhongTheoLoai();

        return [
            'status' => 'success',
            'total_types' => count($list),
            'data' => $list,
        ];
    }
    /* =========================================================================
     * SERVICE: QUẢN LÝ LOẠI PHÒNG
     * ========================================================================= */

    public function sinhMaLoaiPhong(): array
    {
        return [
            'status' => 'success',
            'maLoaiPhong' => $this->repository->generateMaLoaiPhong(),
        ];
    }

    public function themLoaiPhong(array $data): array
    {
        $tenLoai = trim($data['TenLoaiPhong'] ?? $data['tenLoaiPhong'] ?? '');
        if (empty($tenLoai)) {
            return ['status' => 'error', 'message' => 'Tên loại phòng không được để trống.'];
        }

        $normalized = [
            'MaLoaiPhong' => $data['MaLoaiPhong'] ?? $data['maLoaiPhong'] ?? null,
            'TenLoaiPhong' => $tenLoai,
            'Gia' => (float)($data['Gia'] ?? $data['gia'] ?? 0),
            'SoNguoiToiDa' => (int)($data['SoNguoiToiDa'] ?? $data['soNguoiToiDa'] ?? 2),
            'MoTa' => $data['MoTa'] ?? $data['moTa'] ?? null,
            'TienNghi' => $data['TienNghi'] ?? $data['tienNghi'] ?? null,
            'TinhTrang' => $data['TinhTrang'] ?? $data['tinhTrang'] ?? 'ConPhong',
        ];

        try {
            $created = $this->repository->createLoaiPhong($normalized);
            return ['status' => 'success', 'message' => 'Thêm loại phòng thành công.', 'data' => $created];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function suaLoaiPhong(string $maLoaiPhong, array $data): array
    {
        if (empty($maLoaiPhong)) {
            $maLoaiPhong = $data['MaLoaiPhong'] ?? $data['maLoaiPhong'] ?? '';
        }
        if (empty($maLoaiPhong)) {
            return ['status' => 'error', 'message' => 'Mã loại phòng không hợp lệ.'];
        }

        $normalized = [];
        if (isset($data['TenLoaiPhong']) || isset($data['tenLoaiPhong'])) {
            $normalized['TenLoaiPhong'] = trim($data['TenLoaiPhong'] ?? $data['tenLoaiPhong']);
        }
        if (isset($data['Gia']) || isset($data['gia'])) {
            $normalized['Gia'] = (float)($data['Gia'] ?? $data['gia']);
        }
        if (isset($data['SoNguoiToiDa']) || isset($data['soNguoiToiDa'])) {
            $normalized['SoNguoiToiDa'] = (int)($data['SoNguoiToiDa'] ?? $data['soNguoiToiDa']);
        }
        if (isset($data['MoTa']) || isset($data['moTa'])) {
            $normalized['MoTa'] = $data['MoTa'] ?? $data['moTa'];
        }
        if (isset($data['TienNghi']) || isset($data['tienNghi'])) {
            $normalized['TienNghi'] = $data['TienNghi'] ?? $data['tienNghi'];
        }
        if (isset($data['TinhTrang']) || isset($data['tinhTrang'])) {
            $normalized['TinhTrang'] = $data['TinhTrang'] ?? $data['tinhTrang'];
        }

        try {
            $updated = $this->repository->updateLoaiPhong($maLoaiPhong, $normalized);
            if (!$updated) {
                return ['status' => 'error', 'message' => 'Cập nhật không thành công hoặc không có dữ liệu thay đổi.'];
            }
            return [
                'status' => 'success',
                'message' => 'Cập nhật loại phòng thành công.',
                'data' => $this->repository->findLoaiPhongById($maLoaiPhong)
            ];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function doiTrangThaiLoaiPhong(string $maLoaiPhong): array
    {
        if (empty($maLoaiPhong)) {
            return ['status' => 'error', 'message' => 'Mã loại phòng không hợp lệ.'];
        }

        try {
            $item = $this->repository->toggleTrangThaiLoaiPhong($maLoaiPhong);
            $msg = ($item['TinhTrang'] === 'NgungKinhDoanh')
                ? 'Đã chuyển loại phòng sang trạng thái ngừng kinh doanh.'
                : 'Đã mở loại phòng hoạt động kinh doanh trở lại.';
            return ['status' => 'success', 'message' => $msg, 'data' => $item];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /* =========================================================================
     * SERVICE: QUẢN LÝ PHÒNG CỤ THỂ
     * ========================================================================= */

    public function themPhong(array $data): array
    {
        $maPhong = trim($data['MaPhong'] ?? $data['maPhong'] ?? '');
        $maLoai = trim($data['MaLoaiPhong'] ?? $data['maLoaiPhong'] ?? '');

        if (empty($maPhong) || empty($maLoai)) {
            return ['status' => 'error', 'message' => 'Vui lòng cung cấp mã phòng và loại phòng.'];
        }

        $normalized = [
            'MaPhong' => $maPhong,
            'MaLoaiPhong' => $maLoai,
            'TrangThaiPhong' => $data['TrangThaiPhong'] ?? $data['trangThaiPhong'] ?? 'Trong',
        ];

        try {
            $created = $this->repository->createPhong($normalized);
            return ['status' => 'success', 'message' => 'Thêm phòng mới thành công.', 'data' => $created];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function suaPhong(string $maPhong, array $data): array
    {
        if (empty($maPhong)) {
            $maPhong = $data['MaPhong'] ?? $data['maPhong'] ?? '';
        }
        if (empty($maPhong)) {
            return ['status' => 'error', 'message' => 'Mã phòng không hợp lệ.'];
        }

        $normalized = [];
        if (isset($data['MaLoaiPhong']) || isset($data['maLoaiPhong'])) {
            $normalized['MaLoaiPhong'] = trim($data['MaLoaiPhong'] ?? $data['maLoaiPhong']);
        }
        if (isset($data['TrangThaiPhong']) || isset($data['trangThaiPhong'])) {
            $normalized['TrangThaiPhong'] = trim($data['TrangThaiPhong'] ?? $data['trangThaiPhong']);
        }

        try {
            $updated = $this->repository->updatePhong($maPhong, $normalized);
            if (!$updated) {
                return ['status' => 'error', 'message' => 'Cập nhật không thành công hoặc không có dữ liệu thay đổi.'];
            }
            return [
                'status' => 'success',
                'message' => 'Cập nhật phòng thành công.',
                'data' => $this->repository->findPhongById($maPhong)
            ];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function doiTrangThaiPhong(string $maPhong): array
    {
        if (empty($maPhong)) {
            return ['status' => 'error', 'message' => 'Mã phòng không hợp lệ.'];
        }

        try {
            $item = $this->repository->toggleTrangThaiPhong($maPhong);
            $msg = ($item['TrangThaiPhong'] === 'BaoTri')
                ? 'Đã chuyển phòng sang trạng thái bảo trì / ngừng hoạt động.'
                : 'Đã mở phòng sẵn sàng đón khách trở lại.';
            return ['status' => 'success', 'message' => $msg, 'data' => $item];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

}
