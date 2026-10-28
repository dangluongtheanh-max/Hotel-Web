<?php

declare(strict_types=1);

namespace App\Phong\Services;

use App\Phong\Models\LoaiPhong;
use App\Phong\Repositories\LoaiPhongRepository;

/**
 * Service chuyên trách quản lý nghiệp vụ Loại phòng (LoaiPhong).
 */
class LoaiPhongService
{
    private LoaiPhongRepository $repository;

    public function __construct(?LoaiPhongRepository $repository = null)
    {
        $this->repository = $repository ?? new LoaiPhongRepository();
    }

    public function getRepository(): LoaiPhongRepository
    {
        return $this->repository;
    }

    public function xemDSLoaiPhong(array $params = []): array
    {
        $keyword = trim((string)($params['keyword'] ?? $params['TuKhoa'] ?? ''));
        $roomTypes = $this->repository->getAllLoaiPhong(
            $keyword === '' ? [] : ['keyword' => $keyword]
        );

        return [
            'success' => true,
            'total' => count($roomTypes),
            'data' => $roomTypes,
        ];
    }

    public function xemCTLoaiPhong(array $params = []): array
    {
        $id = $this->roomTypeIdFrom($params);
        if ($id === '') {
            return $this->failure('Vui lòng cung cấp mã loại phòng (MaLoaiPhong).');
        }

        $roomType = $this->repository->findLoaiPhongById($id);
        if ($roomType === null) {
            return $this->failure("Không tìm thấy loại phòng '{$id}'.", 404);
        }

        return ['success' => true, 'data' => $roomType];
    }

    public function themLoaiPhong(array $params = []): array
    {
        $roomType = $this->roomTypeFrom($params);
        $validationError = $this->validateRoomType($roomType);
        if ($validationError !== null) {
            return $this->failure($validationError);
        }

        if ($this->repository->existsLoaiPhong($roomType->getMaLoaiPhong())) {
            return $this->failure("Mã loại phòng '{$roomType->getMaLoaiPhong()}' đã tồn tại.");
        }
        if (!$this->repository->createLoaiPhong($roomType)) {
            return $this->failure('Không thể thêm loại phòng vào cơ sở dữ liệu.', 500);
        }

        return [
            'success' => true,
            'message' => 'Thêm loại phòng thành công.',
            'data' => $this->repository->findLoaiPhongById($roomType->getMaLoaiPhong()),
        ];
    }

    public function capNhatLoaiPhong(array $params = []): array
    {
        $id = $this->roomTypeIdFrom($params);
        if ($id === '') {
            return $this->failure('Vui lòng cung cấp mã loại phòng (MaLoaiPhong).');
        }
        if (array_intersect(
            ['TenLoaiPhong', 'Gia', 'SoNguoiToiDa', 'MoTa', 'TienNghi'],
            array_keys($params)
        ) === []) {
            return $this->failure('Vui lòng cung cấp ít nhất một thuộc tính cần cập nhật.');
        }

        $current = $this->repository->findLoaiPhongById($id);
        if ($current === null) {
            return $this->failure("Không tìm thấy loại phòng '{$id}'.", 404);
        }

        $merged = array_merge($current->toArray(), $params);
        $merged['MaLoaiPhong'] = $id;
        $roomType = $this->roomTypeFrom($merged);
        $validationError = $this->validateRoomType($roomType);
        if ($validationError !== null) {
            return $this->failure($validationError);
        }
        if (!$this->repository->updateLoaiPhong($roomType)) {
            return $this->failure('Không thể cập nhật loại phòng trong cơ sở dữ liệu.', 500);
        }

        return [
            'success' => true,
            'message' => 'Cập nhật loại phòng thành công.',
            'data' => $this->repository->findLoaiPhongById($id),
        ];
    }

    public function layDanhSachLoaiPhong(array $filters = []): array
    {
        return $this->repository->getAllLoaiPhong($filters);
    }

    public function layThongTinLoaiPhong(string $id): ?LoaiPhong
    {
        return $this->repository->findLoaiPhongById($id);
    }

    private function roomTypeIdFrom(array $params): string
    {
        return trim((string)($params['MaLoaiPhong'] ?? $params['id'] ?? ''));
    }

    private function roomTypeFrom(array $params): LoaiPhong
    {
        return new LoaiPhong(
            trim((string)($params['MaLoaiPhong'] ?? '')),
            trim((string)($params['TenLoaiPhong'] ?? '')),
            is_numeric($params['Gia'] ?? null) ? (float)$params['Gia'] : NAN,
            filter_var($params['SoNguoiToiDa'] ?? null, FILTER_VALIDATE_INT) !== false
                ? (int)$params['SoNguoiToiDa']
                : 0,
            trim((string)($params['MoTa'] ?? '')),
            trim((string)($params['TienNghi'] ?? ''))
        );
    }

    private function validateRoomType(LoaiPhong $roomType): ?string
    {
        if ($roomType->getMaLoaiPhong() === '' || strlen($roomType->getMaLoaiPhong()) > 50) {
            return 'MaLoaiPhong là bắt buộc và không được dài quá 50 ký tự.';
        }
        if ($roomType->getTenLoaiPhong() === '' || strlen($roomType->getTenLoaiPhong()) > 100) {
            return 'TenLoaiPhong là bắt buộc và không được dài quá 100 ký tự.';
        }
        if (!is_finite($roomType->getGia()) || $roomType->getGia() < 0) {
            return 'Gia phải là số hợp lệ lớn hơn hoặc bằng 0.';
        }
        if ($roomType->getSoNguoiToiDa() < 1) {
            return 'SoNguoiToiDa phải là số nguyên lớn hơn 0.';
        }

        return null;
    }

    private function failure(string $message, int $statusCode = 400): array
    {
        return ['success' => false, 'error' => $message, 'statusCode' => $statusCode];
    }
}
