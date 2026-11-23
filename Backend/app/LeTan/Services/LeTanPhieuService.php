<?php

declare(strict_types=1);

namespace App\LeTan\Services;

use App\LeTan\Repositories\LeTanPhieuRepository;
use App\LeTan\Repositories\LeTanPhongVaKhachRepository;
use Database\Database;
use Exception;
use PDO;
use Shared\ChinhSachHoanHuy;

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

            // 3. Quy tắc giữ phòng cho khách đến trễ: chỉ hủy No-show khi quá giờ trả phòng dự kiến
            if (ChinhSachHoanHuy::daQuaHanTraPhong($p['ThoiGianTraPhong'], $currentTimestamp)) {
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
     * Xác thực vai trò/quyền sở hữu trước khi tính tiền hoàn hoặc hủy phiếu.
     */
    private function xacThucQuyenHuy(array $phieu, array $params): void
    {
        $actor = trim((string)($params['actor'] ?? ''));

        if ($actor === 'khachHang') {
            $maKH = trim((string)($params['maKH'] ?? ''));
            if (empty($maKH)) {
                throw new Exception("Vui lòng đăng nhập tài khoản khách hàng để thực hiện hủy phòng.", 401);
            }
            if (($phieu['MaKH'] ?? '') !== $maKH) {
                throw new Exception("Bạn không có quyền thao tác hủy trên phiếu đặt phòng này.", 403);
            }
            return;
        }

        // Nếu truyền maTK ở luồng Lễ tân/Admin, chặn tài khoản KhachHang gọi endpoint nội bộ
        $maTK = trim((string)($params['maTK'] ?? ($params['MaTK'] ?? '')));
        if (!empty($maTK)) {
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT VaiTro FROM TaiKhoan WHERE MaTK = ? LIMIT 1");
            $stmt->execute([$maTK]);
            $vaiTro = $stmt->fetchColumn();
            if ($vaiTro !== false && $vaiTro === 'KhachHang') {
                throw new Exception("Tài khoản khách hàng vui lòng sử dụng chức năng tự hủy phòng dành cho khách.", 403);
            }
        }
    }

    /**
     * Tính toán số tiền hoàn hủy phòng theo quy tắc chuẩn Shared\ChinhSachHoanHuy.
     */
    public function tinhTienHoanHuyPhong(array $params = []): array
    {
        $maPhieu = trim((string)($params['maPhieu'] ?? ($params['MaPhieu'] ?? ($params['MaPNP'] ?? ($params['maPNP'] ?? '')))));
        $maPhong = trim((string)($params['maPhong'] ?? ($params['MaPhong'] ?? '')));

        if (empty($maPhieu)) {
            throw new Exception("Vui lòng cung cấp mã phiếu đặt phòng.", 400);
        }

        $detail = $this->phieuRepo->getChiTietPhieu($maPhieu);
        if (!$detail) {
            throw new Exception("Không tìm thấy phiếu nhận phòng '{$maPhieu}'.", 404);
        }

        $phieu = $detail['phieu'];
        $this->xacThucQuyenHuy($phieu, $params);

        $trangThaiPhieu = $phieu['TrangThai'] ?? '';
        if ($trangThaiPhieu === 'DaTraPhong') {
            throw new Exception("Phiếu này đã hoàn tất trả phòng, không thể hủy.", 400);
        }
        if ($trangThaiPhieu === 'DaHuy') {
            throw new Exception("Phiếu đặt phòng '{$maPhieu}' đã bị hủy trước đó.", 400);
        }

        $chiTietPhong = $detail['danhSachPhong'] ?? ($detail['chiTietPhong'] ?? []);
        $isSingleRoom = !empty($maPhong) && $maPhong !== 'ALL';

        $tongGiaTriTatCaPhong = 0.0;
        $tongPhiHuyTruocDo = 0.0;
        $soPhongCoTheHuyConLai = 0;
        $phongTargetList = [];
        $targetRow = null;

        foreach ($chiTietPhong as $cp) {
            $giaPhong = (float)($cp['GiaPhong'] ?? ($cp['Gia'] ?? 0));
            $start = strtotime($cp['ThoiGianNhanPhong'] ?? $phieu['NgayNhan']);
            $end = strtotime($cp['ThoiGianTraPhong'] ?? $phieu['NgayTra']);
            $soDem = max(1, (int)round(($end - $start) / 86400));
            $thanhTienPhong = $giaPhong * $soDem;

            $tongGiaTriTatCaPhong += $thanhTienPhong;
            $cpTrangThai = $cp['TrangThai'] ?? '';

            if ($cpTrangThai === 'DaHuy') {
                $tongPhiHuyTruocDo += (float)($cp['PhiHuy'] ?? 0);
            }
            if (in_array($cpTrangThai, ['DaDatPhong', 'ChoThanhToan'], true)) {
                $soPhongCoTheHuyConLai++;
            }

            if ($isSingleRoom && ($cp['MaPhong'] ?? '') === $maPhong) {
                $targetRow = $cp;
            }
        }

        if ($isSingleRoom && !$targetRow) {
            throw new Exception("Phòng '{$maPhong}' không thuộc phiếu #{$maPhieu}.", 404);
        }
        if ($isSingleRoom) {
            $st = $targetRow['TrangThai'] ?? '';
            if ($st === 'DangLuuTru') {
                throw new Exception("Phòng {$maPhong} đang lưu trú (đã nhận phòng), không thể hủy trực tiếp; vui lòng thực hiện Check-out trả phòng sớm.", 400);
            }
            if ($st === 'DaHuy') {
                throw new Exception("Phòng {$maPhong} đã được hủy trước đó.", 400);
            }
            if ($st === 'DaTraPhong') {
                throw new Exception("Phòng {$maPhong} đã hoàn tất trả phòng, không thể hủy.", 400);
            }
        }

        $thoiGianNhan = null;
        $tongTienPhongHuy = 0.0;
        $hasDangLuuTru = false;

        foreach ($chiTietPhong as $cp) {
            $cpTrangThai = $cp['TrangThai'] ?? '';
            if ($cpTrangThai === 'DangLuuTru') {
                $hasDangLuuTru = true;
            }
            if (!in_array($cpTrangThai, ['DaDatPhong', 'ChoThanhToan'], true)) {
                continue;
            }
            if ($isSingleRoom && ($cp['MaPhong'] ?? '') !== $maPhong) {
                continue;
            }

            $giaPhong = (float)($cp['GiaPhong'] ?? ($cp['Gia'] ?? 0));
            $start = strtotime($cp['ThoiGianNhanPhong'] ?? $phieu['NgayNhan']);
            $end = strtotime($cp['ThoiGianTraPhong'] ?? $phieu['NgayTra']);
            $soDem = max(1, (int)round(($end - $start) / 86400));
            $thanhTienPhong = $giaPhong * $soDem;

            if ($thoiGianNhan === null || strtotime($cp['ThoiGianNhanPhong']) < strtotime($thoiGianNhan)) {
                $thoiGianNhan = $cp['ThoiGianNhanPhong'];
            }

            $tongTienPhongHuy += $thanhTienPhong;
            $phongTargetList[] = [
                'maPhong' => $cp['MaPhong'],
                'tenLoaiPhong' => $cp['TenLoaiPhong'] ?? '',
                'donGia' => $giaPhong,
                'soDem' => $soDem,
                'thanhTien' => $thanhTienPhong,
                'thoiGianNhanPhong' => $cp['ThoiGianNhanPhong'],
            ];
        }

        if (empty($phongTargetList)) {
            if ($hasDangLuuTru) {
                throw new Exception("Phiếu đang có phòng lưu trú (đã nhận phòng), vui lòng chọn hủy từng phòng chưa nhận hoặc thực hiện Check-out.", 400);
            }
            throw new Exception("Không còn phòng hợp lệ ở trạng thái có thể hủy trong phiếu #{$maPhieu}.", 400);
        }

        $thoiGianNhan = $thoiGianNhan ?? ($phieu['NgayNhan'] ?? date('Y-m-d 14:00:00'));

        // Kiểm tra số tiền thực tế đã thanh toán trên bảng ThanhToan
        $danhSachTT = $detail['danhSachThanhToan'] ?? [];
        $tongDaThu = 0.0;
        $tongDaHoanTruocDo = 0.0;
        foreach ($danhSachTT as $tt) {
            $ttStatus = $tt['TrangThaiThanhToan'] ?? '';
            if (in_array($ttStatus, [ChinhSachHoanHuy::TT_HOAN_TAT, ChinhSachHoanHuy::TT_HOAN_MOT_PHAN], true)) {
                $tongDaThu += (float)($tt['SoTien'] ?? 0);
                $tongDaHoanTruocDo += (float)($tt['SoTienHoan'] ?? 0);
            }
        }

        $daThanhToan = $tongDaThu > 0 && $trangThaiPhieu !== 'ChoThanhToan';
        $isCancelAllRemaining = !$isSingleRoom || (count($phongTargetList) >= $soPhongCoTheHuyConLai);

        $soTienCoSo = $daThanhToan
            ? ChinhSachHoanHuy::tinhTienCoSoPhanBo(
                $tongTienPhongHuy,
                $tongGiaTriTatCaPhong,
                $tongDaThu,
                $tongDaHoanTruocDo,
                $tongPhiHuyTruocDo,
                $isCancelAllRemaining
            )
            : 0.0;

        $isNoShow = !empty($params['isNoShow']);
        if (!$daThanhToan) {
            $nowTs = time();
            $diffDays = (strtotime($thoiGianNhan) - $nowTs) / 86400.0;
            $policyRes = [
                'maMoc' => 'UNPAID',
                'moTaChinhSach' => 'Đơn đặt phòng chưa thanh toán (Hủy giữ phòng - Hoàn 0 đ)',
                'diffDays' => round($diffDays, 2),
                'tiLeHoan' => 0.0,
                'tiLePhiHuy' => 0.0,
                'soTienHoan' => 0.0,
                'phiHuy' => 0.0,
            ];
        } else {
            $policyRes = ChinhSachHoanHuy::tinhHoanTheoThoiGian($thoiGianNhan, $soTienCoSo, null, $isNoShow);
        }

        // Phân bổ tiền hoàn & phí hủy xuống từng phòng để lưu vết & quyết toán Check-out chính xác
        $chiTietPhongKetQua = [];
        $tongTienHoan = (float)$policyRes['soTienHoan'];
        $tongPhiHuy = (float)$policyRes['phiHuy'];
        $countRooms = count($phongTargetList);
        $daChiaHoan = 0.0;
        $daChiaPhi = 0.0;

        foreach ($phongTargetList as $idx => $pt) {
            if ($idx === $countRooms - 1) {
                $rHoan = max(0.0, round($tongTienHoan - $daChiaHoan, 0));
                $rPhi = max(0.0, round($tongPhiHuy - $daChiaPhi, 0));
            } else {
                $tyTrong = ($tongTienPhongHuy > 0) ? ($pt['thanhTien'] / $tongTienPhongHuy) : (1 / $countRooms);
                $rHoan = round($tongTienHoan * $tyTrong, 0);
                $rPhi = round($tongPhiHuy * $tyTrong, 0);
                $daChiaHoan += $rHoan;
                $daChiaPhi += $rPhi;
            }
            $pt['tiLeHoan'] = $policyRes['tiLeHoan'];
            $pt['soTienHoan'] = $rHoan;
            $pt['phiHuy'] = $rPhi;
            $chiTietPhongKetQua[] = $pt;
        }

        $soGioConLai = $policyRes['soGioConLai'] ?? round((strtotime($thoiGianNhan) - time()) / 3600, 1);
        $soDemChinh = $phongTargetList[0]['soDem'] ?? 1;
        $tongTienPhieuGoc = (float)($phieu['TongTienSauVAT'] ?? ($detail['hoaDon']['TongTienSauVAT'] ?? $tongGiaTriTatCaPhong));

        return [
            // CamelCase chuẩn mới
            'maPhieu' => $maPhieu,
            'maPhong' => $maPhong ?: 'ALL',
            'ngayNhan' => $thoiGianNhan,
            'diffDays' => $policyRes['diffDays'],
            'daThanhToan' => $daThanhToan,
            'tongTienDaThanhToan' => $tongDaThu,
            'soTienCoSo' => $soTienCoSo,
            'tongTienHuy' => $daThanhToan ? $soTienCoSo : $tongTienPhongHuy,
            'tiLeHoan' => $policyRes['tiLeHoan'],
            'tiLePhiHuy' => $policyRes['tiLePhiHuy'],
            'soTienHoan' => $tongTienHoan,
            'phiHuy' => $tongPhiHuy,
            'maMoc' => $policyRes['maMoc'],
            'moTaChinhSach' => $policyRes['moTaChinhSach'],
            'canThongTinNganHang' => $tongTienHoan > 0,
            'danhSachPhongHuy' => $chiTietPhongKetQua,
            'quyTac' => ChinhSachHoanHuy::danhSachQuyTac(),

            // PascalCase tương thích giao diện Dashboard Lễ tân
            'MaPNP' => $maPhieu,
            'TrangThaiHienTai' => $trangThaiPhieu,
            'NgayNhanPhong' => $thoiGianNhan,
            'SoGioTruocCheckIn' => $soGioConLai,
            'SoDem' => $soDemChinh,
            'SoPhongDangHoatDong' => $soPhongCoTheHuyConLai,
            'TongTienPhieu' => $tongTienPhieuGoc,
            'TienPhongMucTieu' => $tongTienPhongHuy,
            'DaThanhToan' => $soTienCoSo,
            'TyLeHoan' => $policyRes['tiLeHoan'],
            'PhiHuy' => $tongPhiHuy,
            'SoTienHoan' => $tongTienHoan,
            'ChinhSachApDung' => $policyRes['moTaChinhSach'],
        ];
    }

    /**
     * Hủy phiếu đặt phòng / từng phòng (dùng chung cho Khách hàng tự hủy, Lễ tân và ThanhToan).
     */
    public function huyPhieu(array $params = []): array
    {
        $maPhieu = trim((string)($params['maPhieu'] ?? ($params['MaPhieu'] ?? ($params['MaPNP'] ?? ($params['maPNP'] ?? '')))));
        $maPhong = trim((string)($params['maPhong'] ?? ($params['MaPhong'] ?? '')));
        $lyDo = trim((string)($params['lyDo'] ?? ($params['lyDoHuy'] ?? ($params['ly_do'] ?? 'Khách hàng chủ động yêu cầu hủy phòng'))));
        $actor = trim((string)($params['actor'] ?? ''));

        $nganHangHoan = trim((string)($params['nganHangHoan'] ?? ($params['NganHangHoan'] ?? '')));
        $stkHoan = trim((string)($params['stkHoan'] ?? ($params['STKHoan'] ?? '')));
        $tenChuTKHoan = trim((string)($params['tenChuTKHoan'] ?? ($params['TenChuTKHoan'] ?? '')));

        $maLeTan = null;
        if ($actor !== 'khachHang') {
            $rawLeTan = trim((string)($params['maLeTan'] ?? ($params['MaLeTan'] ?? 'LT001')));
            $maLeTan = $this->resolveMaLeTan($rawLeTan);
        }

        if (empty($maPhieu)) {
            throw new Exception("Vui lòng cung cấp mã phiếu nhận phòng (MaPhieu).", 400);
        }

        // 1. Kiểm tra quyền & tính toán chính sách hoàn tiền qua quy tắc chuẩn duy nhất
        $hoanInfo = $this->tinhTienHoanHuyPhong($params);

        // Nếu khách tự hủy & có tiền hoàn > 0: bắt buộc cung cấp tài khoản ngân hàng nhận tiền hoàn
        if ($actor === 'khachHang' && $hoanInfo['soTienHoan'] > 0) {
            if (empty($nganHangHoan) || empty($stkHoan) || empty($tenChuTKHoan)) {
                throw new Exception("Vui lòng nhập đầy đủ Ngân hàng, Số tài khoản và Tên chủ tài khoản để nhận tiền hoàn.", 400);
            }
        }

        $prefixNguoiHuy = ($actor === 'khachHang') ? 'Khách tự hủy' : 'Lễ tân tiếp nhận hủy';
        $lyDoDayDu = "{$prefixNguoiHuy}: {$lyDo} [Chính sách: {$hoanInfo['moTaChinhSach']} | Hoàn tiền: "
            . number_format($hoanInfo['soTienHoan'], 0, ',', '.') . " đ ({$hoanInfo['tiLeHoan']}%), Phí hủy: "
            . number_format($hoanInfo['phiHuy'], 0, ',', '.') . " đ]";

        // 2. Cập nhật hủy trong CSDL, đồng bộ ThanhToan & giải phóng phòng an toàn
        $this->phieuRepo->capNhatHuy($maPhieu, $maLeTan, $maPhong ?: null, $lyDoDayDu, [
            'tiLeHoan' => $hoanInfo['tiLeHoan'],
            'soTienHoan' => $hoanInfo['soTienHoan'],
            'chiTietPhong' => $hoanInfo['danhSachPhongHuy'],
            'nganHangHoan' => $nganHangHoan,
            'stkHoan' => $stkHoan,
            'tenChuTKHoan' => $tenChuTKHoan,
        ]);

        $targetText = !empty($maPhong) && $maPhong !== 'ALL' ? "phòng {$maPhong} thuộc phiếu #{$maPhieu}" : "phiếu #{$maPhieu}";
        $msg = $hoanInfo['soTienHoan'] > 0
            ? "Đã hủy thành công {$targetText}. Số tiền hoàn dự kiến: " . number_format($hoanInfo['soTienHoan'], 0, ',', '.') . " đ ({$hoanInfo['tiLeHoan']}%)."
            : "Đã hủy thành công {$targetText}. ({$hoanInfo['moTaChinhSach']}).";

        return [
            'success' => true,
            'message' => $msg,
            'SoTienHoan' => $hoanInfo['soTienHoan'],
            'TyLeHoan' => $hoanInfo['tiLeHoan'],
            'PhiHuy' => $hoanInfo['phiHuy'],
            'data' => [
                'maPhieu' => $maPhieu,
                'maPhong' => (!empty($maPhong) && $maPhong !== 'ALL') ? $maPhong : null,
                'trangThai' => 'DaHuy',
                'lyDo' => $lyDoDayDu,
                'SoTienHoan' => $hoanInfo['soTienHoan'],
                'TyLeHoan' => $hoanInfo['tiLeHoan'],
                'PhiHuy' => $hoanInfo['phiHuy'],
                'hoanTien' => $hoanInfo,
            ],
        ];
    }
}
