<?php

declare(strict_types=1);

namespace App\DatPhong\Services;

use App\DatPhong\Repositories\DatPhongRepository;
use App\KhachHang\Repositories\KhachHangRepository;
use Database\Database;
use Exception;

/**
 * Service chuyên trách nghiệp vụ quản lý và cập nhật thông tin người lưu trú (NguoiLuuTru).
 */
class NguoiLuuTruService
{
    private DatPhongRepository $repository;
    private KhachHangRepository $khachHangRepo;
    private NguoiLuuTruValidator $validator;

    public function __construct(
        DatPhongRepository $repository,
        ?KhachHangRepository $khachHangRepo = null,
        ?NguoiLuuTruValidator $validator = null
    ) {
        $this->repository = $repository;
        $this->khachHangRepo = $khachHangRepo ?? new KhachHangRepository(new Database());
        $this->validator = $validator ?? new NguoiLuuTruValidator();
    }

    /**
     * Cập nhật thông tin người lưu trú trong lịch sử phiếu đặt phòng (khách hàng tự sửa khi phiếu chưa nhận phòng)
     */
    public function capNhatNguoiLuuTru(array $params, ?string $maTK = null): array
    {
        if (empty($maTK)) {
            throw new Exception("Bạn cần đăng nhập tài khoản khách hàng để thực hiện thao tác này.", 403);
        }

        $khachHang = $this->khachHangRepo->findByMaTK($maTK);
        if (!$khachHang) {
            throw new Exception("Tài khoản không hợp lệ hoặc chưa liên kết hồ sơ khách hàng.", 403);
        }

        $maNLT = trim($params['maNguoiLuuTru'] ?? $params['maNLT'] ?? '');
        if ($maNLT === '') {
            throw new Exception("Mã người lưu trú không được để trống.", 400);
        }

        $nlt = $this->repository->findNguoiLuuTruById($maNLT);
        if (!$nlt) {
            throw new Exception("Không tìm thấy thông tin người lưu trú có mã {$maNLT}.", 404);
        }

        // Kiểm tra quyền sở hữu phiếu
        if ($nlt['MaKH'] !== $khachHang['MaKH']) {
            throw new Exception("Bạn không có quyền chỉnh sửa thông tin người lưu trú của phiếu này.", 403);
        }

        // Kiểm tra trạng thái phiếu: chỉ cho phép khi chưa nhận phòng (DaDatPhong)
        $trangThaiPhieu = $nlt['TrangThaiPhieu'];
        $trangThaiChoPhep = ['DaDatPhong'];
        if (!in_array($trangThaiPhieu, $trangThaiChoPhep, true)) {
            $tenTrangThai = match($trangThaiPhieu) {
                'DaDatPhong' => 'Đã đặt phòng',
                'DangLuuTru' => 'Đang lưu trú',
                'DaTraPhong' => 'Đã trả phòng',
                'DaHuy' => 'Đã hủy',
                default => $trangThaiPhieu
            };
            throw new Exception("Phiếu đang ở trạng thái \"{$tenTrangThai}\", không thể chỉnh sửa thông tin người lưu trú.", 400);
        }

        // Validation dữ liệu đầu vào
        $hoTen = trim($params['hoTen'] ?? '');
        if ($hoTen === '') {
            throw new Exception("Vui lòng nhập họ và tên người lưu trú.", 400);
        }
        if (mb_strlen($hoTen) > 100) {
            throw new Exception("Họ và tên không được vượt quá 100 ký tự.", 400);
        }

        $ngaySinh = trim($params['ngaySinh'] ?? '');
        if ($ngaySinh === '') {
            throw new Exception("Vui lòng chọn ngày sinh.", 400);
        }
        try {
            $dob = new \DateTimeImmutable($ngaySinh);
            $today = new \DateTimeImmutable('today');
            if ($dob > $today) {
                throw new Exception("Ngày sinh không hợp lệ (không thể ở tương lai).", 400);
            }
            $ngaySinhFormat = $dob->format('Y-m-d');
        } catch (\Exception $e) {
            if ($e->getCode() === 400) throw $e;
            throw new Exception("Định dạng ngày sinh không hợp lệ.", 400);
        }

        $quocTich = trim($params['quocTich'] ?? '');
        if ($quocTich === '') {
            throw new Exception("Vui lòng nhập hoặc chọn quốc tịch.", 400);
        }

        $loaiGiayTo = trim($params['loaiGiayTo'] ?? '');
        if ($loaiGiayTo === '') {
            throw new Exception("Vui lòng chọn loại giấy tờ tùy thân.", 400);
        }

        $soGiayTo = trim($params['soGiayTo'] ?? '');
        if ($soGiayTo === '') {
            throw new Exception("Vui lòng nhập số giấy tờ tùy thân.", 400);
        }

        // Kiểm tra chi tiết định dạng và thừa/thiếu ký tự giấy tờ
        $this->validator->kiemTraGiayToTuyThan($loaiGiayTo, $soGiayTo, "Khách {$hoTen}");

        $ghiChu = isset($params['ghiChu']) ? trim($params['ghiChu']) : null;
        if ($ghiChu === '') {
            $ghiChu = null;
        }

        $success = $this->repository->capNhatNguoiLuuTru(
            $maNLT,
            $hoTen,
            $ngaySinhFormat,
            $quocTich,
            $loaiGiayTo,
            $soGiayTo,
            $ghiChu
        );

        if (!$success) {
            throw new Exception("Cập nhật thông tin người lưu trú thất bại, vui lòng thử lại sau.", 500);
        }

        return [
            'success' => true,
            'message' => 'Cập nhật thông tin người lưu trú thành công!',
            'data' => [
                'maNguoiLuuTru' => $maNLT,
                'hoTen' => $hoTen,
                'ngaySinh' => $ngaySinhFormat,
                'quocTich' => $quocTich,
                'loaiGiayTo' => $loaiGiayTo,
                'soGiayTo' => $soGiayTo,
                'ghiChu' => $ghiChu,
                'maPhong' => $nlt['MaPhong'],
                'maPhieu' => $nlt['MaPhieu'],
            ]
        ];
    }
}
