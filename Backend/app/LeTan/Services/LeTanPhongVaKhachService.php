<?php

declare(strict_types=1);

namespace App\LeTan\Services;

use App\LeTan\Repositories\LeTanPhongVaKhachRepository;
use App\LeTan\Repositories\LeTanPhieuRepository;
use Database\Database;
use Exception;
use PDO;

/**
 * Service chuyên trách quản lý Sơ đồ trạng thái phòng, Bảo trì,
 * Người lưu trú và Đặt dịch vụ nhanh.
 */
class LeTanPhongVaKhachService
{
    private LeTanPhongVaKhachRepository $phongVaKhachRepo;
    private LeTanPhieuRepository $phieuRepo;

    public function __construct(
        ?LeTanPhongVaKhachRepository $phongVaKhachRepo = null,
        ?LeTanPhieuRepository $phieuRepo = null
    ) {
        $this->phieuRepo = $phieuRepo ?? new LeTanPhieuRepository();
        $this->phongVaKhachRepo = $phongVaKhachRepo ?? new LeTanPhongVaKhachRepository(null, $this->phieuRepo);
    }

    /**
     * Phân giải mã định danh (MaLeTan hoặc MaTK) sang MaLeTan hợp lệ
     */
    public function resolveMaLeTan(string $identifier = 'LT001'): string
    {
        if (empty($identifier)) return 'LT001';
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT MaLeTan FROM LeTan WHERE MaLeTan = ? OR MaTK = ? LIMIT 1");
        $stmt->execute([$identifier, $identifier]);
        $val = $stmt->fetchColumn();
        return $val ?: ($identifier ?: 'LT001');
    }

    /**
     * Lễ tân hỗ trợ khách chuyển người lưu trú sang phòng khác trong cùng phiếu đặt phòng
     */
    public function doiPhongNguoiLuuTru(array $params = []): array
    {
        $maPhieu = trim($params['maPhieu'] ?? ($params['MaPhieu'] ?? ''));
        $maNLT = trim($params['maNguoiLuuTru'] ?? ($params['maNLT'] ?? ''));
        $maPhongMoi = trim($params['maPhongMoi'] ?? ($params['MaPhongMoi'] ?? ''));
        $rawLeTan = trim($params['maLeTan'] ?? ($params['MaLeTan'] ?? 'LT001'));
        $maLeTan = $this->resolveMaLeTan($rawLeTan);

        if (empty($maPhieu) || empty($maNLT) || empty($maPhongMoi)) {
            throw new Exception("Vui lòng cung cấp đầy đủ: Mã phiếu, Mã người lưu trú và Mã phòng mới.", 400);
        }

        $detail = $this->phieuRepo->getChiTietPhieu($maPhieu);
        if (!$detail) {
            throw new Exception("Không tìm thấy phiếu nhận phòng #{$maPhieu}.", 404);
        }

        // Kiểm tra phòng mới có thuộc phiếu không
        $targetPhong = null;
        foreach ($detail['danhSachPhong'] as $p) {
            if ($p['MaPhong'] === $maPhongMoi) {
                $targetPhong = $p;
                break;
            }
        }
        if (!$targetPhong) {
            throw new Exception("Phòng {$maPhongMoi} không thuộc phiếu nhận phòng #{$maPhieu}.", 400);
        }
        if ($targetPhong['TrangThai'] === 'DaHuy') {
            throw new Exception("Phòng {$maPhongMoi} đã bị hủy, không thể chuyển khách vào phòng này.", 400);
        }

        // Tìm khách trong danh sách
        $targetNLT = null;
        $danhSachNLT = $detail['danhSachNLT'] ?? $detail['danhSachNguoiLuuTru'] ?? [];
        foreach ($danhSachNLT as $nlt) {
            if ($nlt['MaNguoiLuuTru'] === $maNLT) {
                $targetNLT = $nlt;
                break;
            }
        }
        if (!$targetNLT) {
            throw new Exception("Không tìm thấy người lưu trú có mã '{$maNLT}' trong phiếu #{$maPhieu}.", 404);
        }

        if ($targetNLT['MaPhong'] === $maPhongMoi) {
            throw new Exception("Khách {$targetNLT['HoTen']} hiện đã ở phòng {$maPhongMoi}.", 400);
        }

        // Đếm số người hiện tại trong phòng mới
        $soKhachHienTai = 0;
        foreach ($danhSachNLT as $nlt) {
            if ($nlt['MaPhong'] === $maPhongMoi) {
                $soKhachHienTai++;
            }
        }

        $maxKhach = (int)$targetPhong['SoNguoiToiDa'];
        if ($soKhachHienTai + 1 > $maxKhach + 1) { // Cho phép tối đa 1 trẻ em phụ thu
            throw new Exception("Phòng {$maPhongMoi} ({$targetPhong['TenLoaiPhong']}) đã đủ sức chứa ({$soKhachHienTai}/{$maxKhach} người). Không thể chuyển thêm khách vào phòng này.", 400);
        }

        // Cập nhật đổi phòng trong CSDL
        $this->phongVaKhachRepo->capNhatPhongNguoiLuuTru($maPhieu, $maNLT, $maPhongMoi);

        return [
            'success' => true,
            'message' => "Đã chuyển khách {$targetNLT['HoTen']} từ phòng {$targetNLT['MaPhong']} sang phòng {$maPhongMoi} thành công!",
            'data' => [
                'maPhieu' => $maPhieu,
                'maNLT' => $maNLT,
                'hoTen' => $targetNLT['HoTen'],
                'phongCu' => $targetNLT['MaPhong'],
                'phongMoi' => $maPhongMoi,
                'maLeTan' => $maLeTan
            ]
        ];
    }

    /**
     * Lễ tân cập nhật thông tin giấy tờ, họ tên của người lưu trú tại quầy
     */
    public function capNhatThongTinKhach(array $params = []): array
    {
        $maPhieu = trim($params['maPhieu'] ?? ($params['MaPhieu'] ?? ''));
        $maNLT = trim($params['maNguoiLuuTru'] ?? ($params['maNLT'] ?? ''));
        $hoTen = trim($params['hoTen'] ?? ($params['HoTen'] ?? ''));
        $ngaySinh = trim($params['ngaySinh'] ?? ($params['NgaySinh'] ?? ''));
        $quocTich = trim($params['quocTich'] ?? ($params['QuocTich'] ?? 'Việt Nam'));
        $loaiGiayTo = trim($params['loaiGiayTo'] ?? ($params['LoaiGiayTo'] ?? 'CCCD'));
        $soGiayTo = trim($params['soGiayTo'] ?? ($params['SoGiayTo'] ?? ''));
        $ghiChu = trim($params['ghiChu'] ?? ($params['GhiChu'] ?? ''));

        if (empty($maPhieu) || empty($maNLT) || empty($hoTen)) {
            throw new Exception("Vui lòng cung cấp đầy đủ: Mã phiếu, Mã khách và Họ tên.", 400);
        }

        if (!empty($soGiayTo)) {
            $validator = new \App\DatPhong\Services\NguoiLuuTruValidator();
            $validator->kiemTraGiayToTuyThan($loaiGiayTo, $soGiayTo, "Khách {$hoTen}");
        }

        $this->phongVaKhachRepo->capNhatThongTinNLT($maPhieu, $maNLT, [
            'hoTen' => $hoTen,
            'ngaySinh' => $ngaySinh,
            'quocTich' => $quocTich,
            'loaiGiayTo' => $loaiGiayTo,
            'soGiayTo' => $soGiayTo,
            'ghiChu' => $ghiChu
        ]);

        return [
            'success' => true,
            'message' => "Đã cập nhật thông tin khách {$hoTen} thành công!",
            'data' => [
                'maPhieu' => $maPhieu,
                'maNLT' => $maNLT,
                'hoTen' => $hoTen
            ]
        ];
    }

    /**
     * Tra cứu thống kê sơ đồ trạng thái Loại phòng và Phòng
     */
    public function xemTrangThaiLoaiPhongVaPhong(array $params = []): array
    {
        $maLoaiPhong = trim($params['maLoaiPhong'] ?? ($params['MaLoaiPhong'] ?? ''));
        return $this->phongVaKhachRepo->getThongKeLoaiPhongVaPhong(!empty($maLoaiPhong) ? $maLoaiPhong : null);
    }

    // Các hàm tương thích với sơ đồ lớp và RESTful API
    public function xemDSPhong(array $params = []): array
    {
        return $this->xemTrangThaiLoaiPhongVaPhong($params);
    }

    public function xemCTPhong(array $params = []): ?array
    {
        $maPhong = trim($params['maPhong'] ?? ($params['MaPhong'] ?? ''));
        if (empty($maPhong)) {
            return null;
        }
        $data = $this->phongVaKhachRepo->getThongKeLoaiPhongVaPhong();
        foreach ($data['danhSachPhong'] as $p) {
            if (strcasecmp($p['MaPhong'], $maPhong) === 0) {
                return $p;
            }
        }
        return null;
    }

    public function timKiemPhong(array $params = []): array
    {
        return $this->xemTrangThaiLoaiPhongVaPhong($params);
    }

    public function timHoaDon(array $params = []) { return null; }
    public function xemHoaDon(array $params = []) { return null; }
    public function xemCTHD(array $params = []) { return null; }

    /**
     * Cập nhật trạng thái chuyển đổi giữa 'Trong' và 'BaoTri' cho phòng
     */
    public function capNhatBaoTriPhong(array $params = []): array
    {
        $maPhong = trim($params['maPhong'] ?? ($params['MaPhong'] ?? ''));
        $trangThaiMoi = trim($params['trangThai'] ?? ($params['TrangThai'] ?? ''));

        if (empty($maPhong)) {
            throw new Exception("Vui lòng cung cấp mã phòng cần cập nhật trạng thái.", 400);
        }
        if (empty($trangThaiMoi)) {
            throw new Exception("Vui lòng cung cấp trạng thái mới ('Trong' hoặc 'BaoTri').", 400);
        }

        $res = $this->phongVaKhachRepo->capNhatTrangThaiBaoTri($maPhong, $trangThaiMoi);
        return [
            'success' => true,
            'message' => $res['message'],
            'data' => $res
        ];
    }

    /**
     * Lấy danh sách dịch vụ khả dụng trong khách sạn (Đồ ăn, giặt ủi, thuê xe...)
     */
    public function layDanhSachDichVu(array $params = []): array
    {
        $data = $this->phongVaKhachRepo->layDanhSachDichVuKhaDung();
        return [
            'success' => true,
            'data' => $data
        ];
    }

    /**
     * Tiếp nhận đặt dịch vụ nhanh cho phòng đang lưu trú
     */
    public function datDichVuNhanh(array $params = []): array
    {
        $maPhong = trim($params['maPhong'] ?? ($params['MaPhong'] ?? ''));
        $maCTDV = trim($params['maCTDV'] ?? ($params['MaCTDV'] ?? ''));

        if (empty($maPhong)) {
            throw new Exception("Vui lòng chọn phòng cần đặt dịch vụ.", 400);
        }
        if (empty($maCTDV)) {
            throw new Exception("Vui lòng chọn món / dịch vụ chi tiết.", 400);
        }

        $res = $this->phongVaKhachRepo->datDichVuNhanhChoPhong($params);
        return [
            'success' => true,
            'message' => $res['message'],
            'data' => $res
        ];
    }
}
