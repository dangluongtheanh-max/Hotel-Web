<?php

namespace App\Phong\Services;

use App\Phong\Models\Phong;
use App\Phong\Repositories\PhongRepository;

/**
 * Service (business logic) cho module Phong.
 * Cac module khac muon dung du lieu cua module nay PHAI goi qua Service nay,
 * khong duoc goi thang Repository/Model de giu tinh dong goi giua cac module.
 */
class PhongService
{
    private const TRANG_THAI_PHONG = ['Trong', 'DaDat', 'DangSuDung', 'BaoTri'];

    private PhongRepository $repository;

    public function __construct(PhongRepository $repository)
    {
        $this->repository = $repository;
    }

    public function xemDSPhong(array $params = []): array
    {
        $filters = $this->filtersFrom($params);
        $rooms = $this->repository->getAllPhong($filters);

        return ['success' => true, 'total' => count($rooms), 'data' => $rooms];
    }

    public function xemCTPhong(array $params = []): array
    {
        $id = $this->roomIdFrom($params);
        if ($id === '') {
            return $this->failure('Vui lòng cung cấp mã phòng (MaPhong).');
        }

        $room = $this->repository->findPhongById($id);
        if ($room === null) {
            return $this->failure("Không tìm thấy phòng '{$id}'.", 404);
        }

        return ['success' => true, 'data' => $room];
    }

    public function themPhong(array $params = []): array
    {
        $id = trim((string)($params['MaPhong'] ?? ''));
        $typeId = trim((string)($params['MaLoaiPhong'] ?? ''));
        $status = trim((string)($params['TrangThaiPhong'] ?? 'Trong'));

        if ($id === '' || $typeId === '') {
            return $this->failure('Vui lòng cung cấp MaPhong và MaLoaiPhong.');
        }
        if (strlen($id) > 50) {
            return $this->failure('MaPhong không được dài quá 50 ký tự.');
        }
        if (strlen($typeId) > 50) {
            return $this->failure('MaLoaiPhong không được dài quá 50 ký tự.');
        }
        if (!$this->isValidStatus($status)) {
            return $this->failure('Trạng thái phòng không hợp lệ.');
        }
        if (!$this->repository->existsLoaiPhong($typeId)) {
            return $this->failure("Không tìm thấy loại phòng '{$typeId}'.", 404);
        }
        if ($this->repository->findPhongById($id) !== null) {
            return $this->failure("Mã phòng '{$id}' đã tồn tại.");
        }

        $room = new Phong($id, $status, $typeId);
        if (!$this->repository->createPhong($room)) {
            return $this->failure('Không thể thêm phòng vào cơ sở dữ liệu.', 500);
        }

        return [
            'success' => true,
            'message' => 'Thêm phòng thành công.',
            'data' => $this->repository->findPhongById($id),
        ];
    }

    public function capNhatThongTinPhong(array $params = []): array
    {
        $id = $this->roomIdFrom($params);
        if ($id === '') {
            return $this->failure('Vui lòng cung cấp mã phòng (MaPhong).');
        }

        $current = $this->repository->findPhongById($id);
        if ($current === null) {
            return $this->failure("Không tìm thấy phòng '{$id}'.", 404);
        }

        $typeId = trim((string)($params['MaLoaiPhong'] ?? $current['MaLoaiPhong']));
        $status = trim((string)($params['TrangThaiPhong'] ?? $current['TrangThaiPhong']));
        if (array_key_exists('MaLoaiPhong', $params) && $typeId === '') {
            return $this->failure('MaLoaiPhong không được để trống.');
        }
        if (array_key_exists('TrangThaiPhong', $params) && !$this->isValidStatus($status)) {
            return $this->failure('Trạng thái phòng không hợp lệ.');
        }
        if (array_key_exists('MaLoaiPhong', $params) && !$this->repository->existsLoaiPhong($typeId)) {
            return $this->failure("Không tìm thấy loại phòng '{$typeId}'.", 404);
        }

        $room = new Phong($id, $status, $typeId);
        if (!$this->repository->updatePhong($room)) {
            return $this->failure('Không thể cập nhật phòng trong cơ sở dữ liệu.', 500);
        }

        return [
            'success' => true,
            'message' => 'Cập nhật thông tin phòng thành công.',
            'data' => $this->repository->findPhongById($id),
        ];
    }

    public function timKiemPhong(array $params = []): array
    {
        $filters = $this->filtersFrom($params);
        $keyword = trim((string)($params['keyword'] ?? $params['TuKhoa'] ?? ''));
        if ($keyword !== '') {
            $filters['keyword'] = $keyword;
        }

        $rooms = $this->repository->getAllPhong($filters);
        return ['success' => true, 'keyword' => $keyword, 'total' => count($rooms), 'data' => $rooms];
    }

    public function kiemTraTrangThaiPhong(array $params = []): array
    {
        $id = $this->roomIdFrom($params);
        if ($id === '') {
            return $this->failure('Vui lòng cung cấp mã phòng (MaPhong).');
        }

        $room = $this->repository->findPhongById($id);
        if ($room === null) {
            return $this->failure("Không tìm thấy phòng '{$id}'.", 404);
        }

        return [
            'success' => true,
            'MaPhong' => $id,
            'TrangThaiPhong' => $room['TrangThaiPhong'],
            'data' => $room,
        ];
    }

    public function kiemTraPhongTrong(array $params = []): array
    {
        $id = $this->roomIdFrom($params);
        if ($id !== '') {
            $room = $this->repository->findPhongById($id);
            if ($room === null) {
                return $this->failure("Không tìm thấy phòng '{$id}'.", 404);
            }

            return [
                'success' => true,
                'MaPhong' => $id,
                'available' => $room['TrangThaiPhong'] === 'Trong',
                'TrangThaiPhong' => $room['TrangThaiPhong'],
                'data' => $room,
            ];
        }

        $filters = $this->filtersFrom($params);
        $filters['TrangThaiPhong'] = 'Trong';
        $rooms = $this->repository->getAllPhong($filters);

        return ['success' => true, 'total' => count($rooms), 'data' => $rooms];
    }

    private function filtersFrom(array $params): array
    {
        $filters = [];
        foreach (['MaLoaiPhong', 'TrangThaiPhong', 'MaPhong'] as $key) {
            if (isset($params[$key]) && trim((string)$params[$key]) !== '') {
                $filters[$key] = trim((string)$params[$key]);
            }
        }

        return $filters;
    }

    private function roomIdFrom(array $params): string
    {
        return trim((string)($params['MaPhong'] ?? $params['id'] ?? ''));
    }

    private function isValidStatus(string $status): bool
    {
        return in_array($status, self::TRANG_THAI_PHONG, true);
    }

    private function failure(string $message, int $statusCode = 400): array
    {
        return ['success' => false, 'error' => $message, 'statusCode' => $statusCode];
    }

}
