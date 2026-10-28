<?php

declare(strict_types=1);

namespace App\LeTan\Services;

use App\LeTan\Repositories\LeTanPhieuRepository;
use App\LeTan\Repositories\LeTanPhongVaKhachRepository;
use Database\Database;
use Exception;
use PDO;

/**
 * Service chuyên trách quản lý Phiếu nhận phòng, Check-in tại quầy và Hủy phiếu.
 */
class LeTanPhieuService
{
    private LeTanPhieuRepository $phieuRepo;
    private LeTanPhongVaKhachRepository $phongVaKhachRepo;

    public function __construct(
        ?LeTanPhieuRepository $phieuRepo = null,
        ?LeTanPhongVaKhachRepository $phongVaKhachRepo = null
    ) {
        $this->phieuRepo = $phieuRepo ?? new LeTanPhieuRepository();
        $this->phongVaKhachRepo = $phongVaKhachRepo ?? new LeTanPhongVaKhachRepository(null, $this->phieuRepo);
    }

    /**
     * Lấy danh sách phiếu đặt phòng theo bộ lọc
     */
    public function xemDSPhieu(array $params = []): array
    {
        $filters = [
            'trangThai' => trim($params['trangThai'] ?? ($params['TrangThai'] ?? '')),
            'tuKhoa' => trim($params['tuKhoa'] ?? ($params['tu_khoa'] ?? ($params['q'] ?? '')))
        ];

        $items = $this->phieuRepo->getDanhSachPhieu($filters);
        return [
            'success' => true,
            'count' => count($items),
            'items' => $items
        ];
    }

    /**
     * Tra cứu chi tiết phiếu đặt phòng (bao gồm danh sách người lưu trú và dịch vụ)
     */
    public function xemCTPhieu(array $params = []): array
    {
        $maPhieu = trim($params['maPhieu'] ?? ($params['MaPhieu'] ?? ''));
        if (empty($maPhieu)) {
            throw new Exception("Vui lòng cung cấp mã phiếu nhận phòng (MaPhieu).", 400);
        }

        $detail = $this->phieuRepo->getChiTietPhieu($maPhieu);
        if (!$detail) {
            throw new Exception("Không tìm thấy thông tin phiếu nhận phòng '{$maPhieu}'.", 404);
        }

        return [
            'success' => true,
            'data' => $detail
        ];
    }

    public function timKiemPhieu(array $params = []): array
    {
        return $this->xemDSPhieu($params);
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
     * Check-in nhận phòng tại quầy lễ tân:
     * Kiểm tra điều kiện:
     * - Phiếu phải đang ở trạng thái DaDatPhong
     * - Số người lưu trú không vượt quá sức chứa tối đa của từng phòng
     * - Cập nhật trạng thái phiếu và các phòng sang DangLuuTru
     */
    public function checkin(array $params = []): array
    {
        $maPhieu = trim($params['maPhieu'] ?? ($params['MaPhieu'] ?? ''));
        $rawLeTan = trim($params['maLeTan'] ?? ($params['MaLeTan'] ?? 'LT001'));
        $maLeTan = $this->resolveMaLeTan($rawLeTan);
        $maPhong = trim($params['maPhong'] ?? ($params['MaPhong'] ?? ''));

        if (empty($maPhieu)) {
            throw new Exception("Vui lòng cung cấp mã phiếu nhận phòng (MaPhieu).", 400);
        }

        $detail = $this->phieuRepo->getChiTietPhieu($maPhieu);
        if (!$detail) {
            throw new Exception("Không tìm thấy phiếu nhận phòng '{$maPhieu}'.", 404);
        }

        $phieu = $detail['phieu'];
        $trangThaiHienTai = $phieu['TrangThai'];

        if ($trangThaiHienTai === 'DaTraPhong') {
            throw new Exception("Phiếu này đã hoàn tất trả phòng (Check-out), không thể check-in lại.", 400);
        }
        if ($trangThaiHienTai === 'DaHuy') {
            throw new Exception("Phiếu này đã bị hủy, không thể làm thủ tục nhận phòng.", 400);
        }

        // Kiểm tra sức chứa từng phòng
        $danhSachPhong = $detail['danhSachPhong'];
        $danhSachNLT = $detail['danhSachNLT'] ?? $detail['danhSachNguoiLuuTru'] ?? [];

        // Đếm số khách thực tế ở từng phòng
        $khachTheoPhong = [];
        foreach ($danhSachNLT as $nlt) {
            $pId = $nlt['MaPhong'];
            $khachTheoPhong[$pId] = ($khachTheoPhong[$pId] ?? 0) + 1;
        }

        // Nếu check-in phòng cụ thể, kiểm tra phòng đó
        if (!empty($maPhong)) {
            $targetPhong = null;
            foreach ($danhSachPhong as $p) {
                if ($p['MaPhong'] === $maPhong) {
                    $targetPhong = $p;
                    break;
                }
            }
            if (!$targetPhong) {
                throw new Exception("Phòng '{$maPhong}' không thuộc phiếu nhận phòng #{$maPhieu}.", 404);
            }
            if ($targetPhong['TrangThai'] === 'DangLuuTru') {
                throw new Exception("Phòng {$maPhong} đã được check-in trước đó.", 400);
            }
            if ($targetPhong['TrangThai'] === 'DaTraPhong') {
                throw new Exception("Phòng {$maPhong} đã trả phòng.", 400);
            }
        } else {
            // Check-in toàn bộ phiếu
            if ($trangThaiHienTai === 'DangLuuTru') {
                throw new Exception("Phiếu này đã được check-in trước đó (Đang lưu trú).", 400);
            }
        }

        foreach ($danhSachPhong as $p) {
            $pId = $p['MaPhong'];
            if (!empty($maPhong) && $pId !== $maPhong) {
                continue;
            }

            if ($p['TrangThai'] === 'DaHuy') {
                throw new Exception("Phòng {$pId} đã bị hủy trước đó. Lý do: " . ($p['LyDoHuy'] ?? 'Không rõ'), 400);
            }
            if ($p['TrangThai'] === 'DangLuuTru') {
                throw new Exception("Phòng {$pId} đã check-in trước đó.", 400);
            }
            if ($p['TrangThai'] === 'DaTraPhong') {
                throw new Exception("Phòng {$pId} đã trả phòng.", 400);
            }

            // Kiểm tra thời gian nhận phòng & trạng thái phòng:
            $nhanPhongTimestamp = strtotime($p['ThoiGianNhanPhong']);
            $currentTimestamp = time();
            $todayDate = date('Y-m-d', $currentTimestamp);
            $nhanDate = date('Y-m-d', $nhanPhongTimestamp);

            // 1. Kiểm tra ngày nhận phòng: Không được check-in trước ngày đăng ký nhận phòng
            if ($todayDate < $nhanDate) {
                $ngayNhanFmt = date('d/m/Y', $nhanPhongTimestamp);
                throw new Exception("Chưa đến ngày nhận phòng dự kiến cho phòng {$pId} (Ngày nhận: {$ngayNhanFmt}). Quý khách không thể làm thủ tục check-in trước ngày này.", 400);
            }

            // 2. Kiểm tra quy định giờ nhận phòng tiêu chuẩn (14:00) & Ngoại lệ phòng trống:
            $isRoomEmpty = $this->phongVaKhachRepo->kiemTraPhongCoTrong($pId);

            if ($currentTimestamp < $nhanPhongTimestamp) {
                // Khách đến trước 14:00 (Check-in sớm): Chỉ cho phép nếu phòng đang ở Trạng thái trống
                if (!$isRoomEmpty) {
                    $gioNhanFmt = date('H:i d/m/Y', $nhanPhongTimestamp);
                    throw new Exception("Chưa đến giờ nhận phòng tiêu chuẩn (14:00) và phòng {$pId} hiện chưa trống (đang có khách sử dụng). Khách sạn chỉ cho phép nhận phòng sớm khi phòng đang ở trạng thái Trống. Quý khách vui lòng chờ đến 14:00 hoặc khi phòng được trả và dọn dẹp xong.", 400);
                }
            } else {
                // Đã đến hoặc sau 14:00: Nếu phòng vẫn đang có khách lưu trú chưa trả
                if (!$isRoomEmpty) {
                    throw new Exception("Phòng {$pId} hiện vẫn đang có khách lưu trú chưa hoàn tất trả phòng. Vui lòng kiểm tra lại thủ tục trả phòng của khách trước!", 400);
                }
            }

            // 3. Quy tắc giữ phòng cho khách đến trễ:
            $traPhongTimestamp = strtotime($p['ThoiGianTraPhong']);
            if ($currentTimestamp > $traPhongTimestamp) {
                $lyDoQuaHan = "Đã quá thời gian trả phòng dự kiến ({$p['ThoiGianTraPhong']}) mà khách không đến nhận phòng";
                $this->phieuRepo->capNhatHuy($maPhieu, $maLeTan, $pId, $lyDoQuaHan);
                throw new Exception("Check-in thất bại: Đã quá thời gian trả phòng dự kiến ({$p['ThoiGianTraPhong']}) mà khách không đến nhận. Phòng {$pId} đã được chuyển sang trạng thái 'Đã hủy'.", 400);
            }

            $maxKhach = (int)$p['SoNguoiToiDa'];
            $soKhachKhaiBao = $khachTheoPhong[$pId] ?? (int)$p['SoNguoi'];

            if ($soKhachKhaiBao > $maxKhach + 1) { // Cho phép tối đa 1 trẻ em phụ thu theo chính sách
                throw new Exception("Phòng {$pId} ({$p['TenLoaiPhong']}) có {$soKhachKhaiBao} khách, vượt quá sức chứa tối đa ({$maxKhach} người). Vui lòng kiểm tra lại!", 400);
            }
        }

        // Thực hiện cập nhật check-in
        $this->phieuRepo->capNhatCheckin($maPhieu, $maLeTan, !empty($maPhong) ? $maPhong : null);

        $msg = !empty($maPhong)
            ? "Check-in thành công cho phòng {$maPhong} (Phiếu #{$maPhieu})! Trạng thái phòng đã chuyển sang 'Đang lưu trú'."
            : "Check-in thành công cho toàn bộ phiếu #{$maPhieu}! Trạng thái đã chuyển sang 'Đang lưu trú'.";

        return [
            'success' => true,
            'message' => $msg,
            'data' => [
                'maPhieu' => $maPhieu,
                'maPhong' => $maPhong ?: null,
                'trangThai' => 'DangLuuTru',
                'maLeTan' => $maLeTan,
                'soPhong' => !empty($maPhong) ? 1 : count($danhSachPhong)
            ]
        ];
    }

    /**
     * Tính toán số tiền hoàn hủy phòng theo chính sách khách sạn:
     * - Trước ngày nhận phòng từ 7 ngày trở lên: Hoàn 100%, Phí hủy 0%
     * - Trước ngày nhận phòng từ 3 đến dưới 7 ngày: Hoàn 50%, Phí hủy 50%
     * - Trước ngày nhận phòng dưới 3 ngày: Hoàn 0%, Phí hủy 100%
     * - Không đến nhận phòng, không báo hủy (hoặc sau thời gian nhận): Hoàn 0%, Phí hủy 100%
     */
    public function tinhTienHoanHuyPhong(array $params = []): array
    {
        $maPhieu = trim($params['maPhieu'] ?? ($params['MaPhieu'] ?? ''));
        $maPhong = trim($params['maPhong'] ?? ($params['MaPhong'] ?? ''));

        if (empty($maPhieu)) {
            throw new Exception("Vui lòng cung cấp mã phiếu đặt phòng.", 400);
        }

        $detail = $this->phieuRepo->getChiTietPhieu($maPhieu);
        if (!$detail) {
            throw new Exception("Không tìm thấy phiếu nhận phòng '{$maPhieu}'.", 404);
        }

        $phieu = $detail['phieu'];
        $chiTietPhong = $detail['danhSachPhong'] ?? ($detail['chiTietPhong'] ?? []);

        // Lấy ngày nhận phòng dự kiến từ danh sách phòng hoặc phiếu
        $thoiGianNhan = !empty($chiTietPhong[0]['ThoiGianNhanPhong']) 
            ? $chiTietPhong[0]['ThoiGianNhanPhong'] 
            : ($phieu['NgayNhan'] ?? date('Y-m-d 14:00:00'));

        // Tính tổng tiền của các phòng được chọn để hủy
        $tongTienHuy = 0.0;
        $phongHuyList = [];

        foreach ($chiTietPhong as $cp) {
            if ($cp['TrangThai'] !== 'DaDatPhong') {
                continue;
            }

            if (!empty($maPhong) && $maPhong !== 'ALL' && $cp['MaPhong'] !== $maPhong) {
                continue;
            }

            $giaPhong = (float)($cp['GiaPhong'] ?? ($cp['Gia'] ?? 0));
            $start = strtotime($cp['ThoiGianNhanPhong'] ?? $phieu['NgayNhan']);
            $end = strtotime($cp['ThoiGianTraPhong'] ?? $phieu['NgayTra']);
            $soDem = max(1, (int)round(($end - $start) / 86400));
            $thanhTienPhong = $giaPhong * $soDem;

            $tongTienHuy += $thanhTienPhong;
            $phongHuyList[] = [
                'maPhong' => $cp['MaPhong'],
                'tenLoaiPhong' => $cp['TenLoaiPhong'] ?? '',
                'donGia' => $giaPhong,
                'soDem' => $soDem,
                'thanhTien' => $thanhTienPhong
            ];
        }

        if ($tongTienHuy <= 0) {
            $tongTienHuy = (float)($phieu['TongTienSauVAT'] ?? ($phieu['TongTien'] ?? 0));
        }

        // Tính khoảng cách thời gian theo ngày từ thời điểm hiện tại đến thời gian nhận phòng
        $nowTs = time();
        $nhanTs = strtotime($thoiGianNhan);
        $diffSeconds = $nhanTs - $nowTs;
        $diffDays = $diffSeconds / 86400.0;

        $tiLeHoan = 0.0;
        $tiLePhiHuy = 100.0;
        $moTaChinhSach = "Trước ngày nhận phòng dưới 3 ngày";

        if ($diffDays >= 7.0) {
            $tiLeHoan = 100.0;
            $tiLePhiHuy = 0.0;
            $moTaChinhSach = "Trước ngày nhận từ 7 ngày trở lên (Hoàn 100% tiền phòng)";
        } elseif ($diffDays >= 3.0) {
            $tiLeHoan = 50.0;
            $tiLePhiHuy = 50.0;
            $moTaChinhSach = "Trước ngày nhận từ 3 đến dưới 7 ngày (Hoàn 50% tiền phòng, Phí hủy 50%)";
        } else {
            $tiLeHoan = 0.0;
            $tiLePhiHuy = 100.0;
            $moTaChinhSach = $diffDays < 0 
                ? "Đã qua thời gian nhận phòng (Không hoàn tiền - Phí hủy 100%)" 
                : "Trước ngày nhận phòng dưới 3 ngày (Không hoàn tiền - Phí hủy 100%)";
        }

        $soTienHoan = round(($tongTienHuy * $tiLeHoan) / 100);
        $phiHuy = round(($tongTienHuy * $tiLePhiHuy) / 100);

        return [
            'maPhieu' => $maPhieu,
            'maPhong' => $maPhong ?: 'ALL',
            'ngayNhan' => $thoiGianNhan,
            'diffDays' => round($diffDays, 1),
            'tongTienHuy' => $tongTienHuy,
            'tiLeHoan' => $tiLeHoan,
            'tiLePhiHuy' => $tiLePhiHuy,
            'soTienHoan' => $soTienHoan,
            'phiHuy' => $phiHuy,
            'moTaChinhSach' => $moTaChinhSach,
            'danhSachPhongHuy' => $phongHuyList
        ];
    }

    /**
     * Hủy phiếu đặt phòng theo yêu cầu chủ động của khách
     * Hỗ trợ hủy toàn bộ phiếu hoặc hủy riêng từng phòng lẻ, tự động tính tiền hoàn theo chính sách
     */
    public function huyPhieu(array $params = []): array
    {
        $maPhieu = trim($params['maPhieu'] ?? ($params['MaPhieu'] ?? ''));
        $maPhong = trim($params['maPhong'] ?? ($params['MaPhong'] ?? ''));
        $lyDo = trim($params['lyDo'] ?? ($params['ly_do'] ?? 'Khách hàng chủ động yêu cầu hủy phòng'));
        $rawLeTan = trim($params['maLeTan'] ?? ($params['MaLeTan'] ?? 'LT001'));
        $maLeTan = $this->resolveMaLeTan($rawLeTan);

        if (empty($maPhieu)) {
            throw new Exception("Vui lòng cung cấp mã phiếu nhận phòng (MaPhieu).", 400);
        }

        $detail = $this->phieuRepo->getChiTietPhieu($maPhieu);
        if (!$detail) {
            throw new Exception("Không tìm thấy phiếu nhận phòng '{$maPhieu}'.", 404);
        }

        $phieu = $detail['phieu'];
        $trangThai = $phieu['TrangThai'];
        if ($trangThai === 'DaTraPhong') {
            throw new Exception("Phiếu đã hoàn tất trả phòng, không thể hủy.", 400);
        }

        // 1. Tính toán chính sách hoàn tiền
        $hoanInfo = $this->tinhTienHoanHuyPhong([
            'maPhieu' => $maPhieu,
            'maPhong' => $maPhong
        ]);

        $lyDoDayDu = "Khách yêu cầu hủy: {$lyDo} [Chính sách: {$hoanInfo['moTaChinhSach']} | Hoàn tiền: " . number_format($hoanInfo['soTienHoan'], 0, ',', '.') . " đ ({$hoanInfo['tiLeHoan']}%), Phí hủy: " . number_format($hoanInfo['phiHuy'], 0, ',', '.') . " đ]";

        // 2. Cập nhật hủy trong cơ sở dữ liệu và giải phóng phòng về 'Trong'
        $this->phieuRepo->capNhatHuy($maPhieu, $maLeTan, $maPhong ?: null, $lyDoDayDu);

        $targetText = !empty($maPhong) && $maPhong !== 'ALL' ? "phòng {$maPhong} thuộc phiếu #{$maPhieu}" : "phiếu #{$maPhieu}";
        return [
            'success' => true,
            'message' => "Đã tiếp nhận hủy thành công {$targetText}. Số tiền hoàn lại: " . number_format($hoanInfo['soTienHoan'], 0, ',', '.') . " đ ({$hoanInfo['tiLeHoan']}%)",
            'data' => [
                'maPhieu' => $maPhieu,
                'maPhong' => $maPhong ?: null,
                'trangThai' => 'DaHuy',
                'lyDo' => $lyDoDayDu,
                'hoanTien' => $hoanInfo
            ]
        ];
    }
}
