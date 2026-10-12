<?php

namespace App\Phong\Services;

use App\Phong\Models\Phong;
use App\Phong\Models\LoaiPhong;
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

        $merged = array_merge($current, $params);
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

    private function isValidStatus(string $status): bool
    {
        return in_array($status, self::TRANG_THAI_PHONG, true);
    }

    private function failure(string $message, int $statusCode = 400): array
    {
        return ['success' => false, 'error' => $message, 'statusCode' => $statusCode];
    }

}
