<?php

declare(strict_types=1);

namespace App\Phong\Services;

use App\Phong\Models\LoaiPhong;
use App\Phong\Models\Phong;
use App\Phong\Repositories\PhongRepository;
use PDO;

/**
 * Service cho module Phong.
 * Chịu trách nhiệm toàn bộ nghiệp vụ kiểm tra, tra cứu, quản lý phòng vật lý,
 * đồng thời đóng vai trò Facade tích hợp LoaiPhongService cho các nghiệp vụ Loại phòng.
 */
class PhongService
{
    private const TRANG_THAI_PHONG = ['Trong', 'ChoThanhToan', 'DaDat', 'DangSuDung', 'BaoTri'];

    private PhongRepository $repository;
    private LoaiPhongService $loaiPhongService;

    public function __construct(
        ?PhongRepository $repository = null,
        ?LoaiPhongService $loaiPhongService = null
    ) {
        $this->repository = $repository ?? new PhongRepository();
        $this->loaiPhongService = $loaiPhongService ?? new LoaiPhongService($this->repository->getLoaiPhongRepo());
    }

    public function getRepository(): PhongRepository
    {
        return $this->repository;
    }

    public function getLoaiPhongService(): LoaiPhongService
    {
        return $this->loaiPhongService;
    }

    // =========================================================================
    // PHÂN HỆ PHÒNG VẬT LÝ (PHONG)
    // =========================================================================

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
        if (!empty($params['checkIn']) && !empty($params['checkOut'])) {
            return $this->repository->countAvailableRoomsByType($params['checkIn'], $params['checkOut']);
        }

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

    public function layPhongTrongNgauNhien(string $maLoaiPhong, int $soLuong, string $checkIn, string $checkOut): array
    {
        $danhSach = $this->repository->getAvailableRoomsByLoai($maLoaiPhong, $checkIn, $checkOut);
        if (count($danhSach) < $soLuong) {
            throw new \Exception("Loại phòng {$maLoaiPhong} không còn đủ {$soLuong} phòng trống trong khoảng thời gian này.");
        }
        shuffle($danhSach);
        return array_slice($danhSach, 0, $soLuong);
    }

    public function capNhatTrangThaiPhong(string $maPhong, string $trangThai, ?PDO $db = null): bool
    {
        if (!$this->isValidStatus($trangThai)) {
            return false;
        }
        return $this->repository->capNhatTrangThaiPhong($maPhong, $trangThai, $db);
    }

    // =========================================================================
    // FACADE DELEGATE SANG LOAIPHONG SERVICE
    // =========================================================================

    public function xemDSLoaiPhong(array $params = []): array
    {
        return $this->loaiPhongService->xemDSLoaiPhong($params);
    }

    public function xemCTLoaiPhong(array $params = []): array
    {
        return $this->loaiPhongService->xemCTLoaiPhong($params);
    }

    public function themLoaiPhong(array $params = []): array
    {
        return $this->loaiPhongService->themLoaiPhong($params);
    }

    public function capNhatLoaiPhong(array $params = []): array
    {
        return $this->loaiPhongService->capNhatLoaiPhong($params);
    }

    public function layDanhSachLoaiPhong(array $filters = []): array
    {
        return $this->loaiPhongService->layDanhSachLoaiPhong($filters);
    }

    public function layThongTinLoaiPhong(string $id): ?LoaiPhong
    {
        return $this->loaiPhongService->layThongTinLoaiPhong($id);
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

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