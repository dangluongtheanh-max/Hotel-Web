<?php

declare(strict_types=1);

namespace App\KhuyenMai\Services;

use App\KhuyenMai\Repositories\KhuyenMaiXuLyRepository;

/**
 * Service thực thi toàn bộ Business Logic của module Khuyến Mãi.
 * Bao gồm 2 phần nghiệp vụ: Tra cứu & Động cơ áp dụng mã (Query/Engine) và Quản trị khuyến mãi (Command).
 */
class KhuyenMaiXuLyService
{
    private KhuyenMaiXuLyRepository $repo;

    public function __construct(?KhuyenMaiXuLyRepository $repo = null)
    {
        $this->repo = $repo ?? new KhuyenMaiXuLyRepository();
    }

    public function getRepository(): KhuyenMaiXuLyRepository
    {
        return $this->repo;
    }

    // =========================================================================
    // PHẦN 1: TRA CỨU, TÍNH TOÁN VÀ ĐỘNG CƠ ÁP DỤNG MÃ (READ / ENGINE)
    // =========================================================================

    /**
     * Lấy danh sách khuyến mãi cho Khách Hàng (Client):
     * - Các mã đang có hiệu lực ngay lúc này.
     * - Các sự kiện ưu đãi sắp diễn ra (Banner).
     */
    public function layDanhSachClient(array $params = []): array
    {
        $today = !empty($params['ngay']) ? $params['ngay'] : date('Y-m-d');
        $soDem = isset($params['soDem']) ? (int)$params['soDem'] : 1;
        $soNguoi = isset($params['soNguoi']) ? (int)$params['soNguoi'] : 1;
        $maKH = trim((string)($params['maKH'] ?? ($params['makh'] ?? ($params['MaKH'] ?? ''))));
        $maTK = trim((string)($params['maTK'] ?? ($params['matk'] ?? ($params['MaTK'] ?? ''))));
        $sdt = trim((string)($params['sdt'] ?? ($params['SDT'] ?? '')));

        // Phân giải MaKH từ MaTK hoặc SDT nếu MaKH trống
        $db = $this->repo->getConnection();
        if (empty($maKH) && !empty($maTK)) {
            $stmtKH = $db->prepare("SELECT MaKH FROM KhachHang WHERE MaTK = ?");
            $stmtKH->execute([$maTK]);
            $maKH = $stmtKH->fetchColumn() ?: '';
        }
        if (empty($maKH) && !empty($sdt)) {
            $stmtKH = $db->prepare("SELECT MaKH FROM KhachHang WHERE SDT = ?");
            $stmtKH->execute([$sdt]);
            $maKH = $stmtKH->fetchColumn() ?: '';
        }
        if (!empty($maKH) && str_starts_with(strtoupper($maKH), 'TK')) {
            $stmtKH = $db->prepare("SELECT MaKH FROM KhachHang WHERE MaTK = ?");
            $stmtKH->execute([$maKH]);
            $realMaKH = $stmtKH->fetchColumn();
            if ($realMaKH) $maKH = $realMaKH;
        }

        // Kiểm tra lịch sử đặt phòng của khách hàng
        $soPhieuNhanPhong = 0;
        $daTungDungMaKhachMoi = false;
        if (!empty($maKH)) {
            // 1. Số phiếu hợp lệ hoặc đang xử lý
            $stmtCount = $db->prepare("SELECT COUNT(*) FROM PhieuNhanPhong WHERE MaKH = ? AND TrangThai != 'DaHuy'");
            $stmtCount->execute([$maKH]);
            $soPhieuNhanPhong = (int)$stmtCount->fetchColumn();

            // 2. Kiểm tra xem khách đã từng sử dụng bất kỳ mã khách mới nào chưa
            $stmtUsed = $db->prepare(
                "SELECT COUNT(*) FROM PhieuNhanPhong p 
                 JOIN KhuyenMai km ON p.MaKM = km.MaKM 
                 WHERE p.MaKH = ? AND (
                     km.MaKM = 'KM001' 
                     OR km.TenKM LIKE '%khách mới%' 
                     OR km.TenKM LIKE '%khach hang moi%' 
                     OR km.TenKM LIKE '%khach moi%' 
                     OR km.TenKM LIKE '%lần đầu%'
                 )"
            );
            $stmtUsed->execute([$maKH]);
            $daTungDungMaKhachMoi = ((int)$stmtUsed->fetchColumn()) > 0;
        }

        $active = $this->repo->getActivePromotions($today);
        $upcoming = $this->repo->getUpcomingPromotions($today);

        // Biến mất hoàn toàn: Lọc bỏ triệt để mã khách mới nếu khách hàng đã từng đặt phòng hoặc đã từng dùng mã
        if ($soPhieuNhanPhong > 0 || $daTungDungMaKhachMoi) {
            $active = array_values(array_filter($active, function ($item) {
                $isNewCustomerPromo = (strtoupper($item['MaKM']) === 'KM001' 
                    || mb_stripos($item['TenKM'], 'khách mới') !== false 
                    || mb_stripos($item['TenKM'], 'khach hang moi') !== false 
                    || mb_stripos($item['TenKM'], 'khach moi') !== false 
                    || mb_stripos($item['TenKM'], 'lần đầu') !== false);
                return !$isNewCustomerPromo;
            }));
        }

        return [
            'status' => 'success',
            'ngay_hien_tai' => $today,
            'so_phieu_khach_hang' => $soPhieuNhanPhong,
            'total_active' => count($active),
            'total_upcoming' => count($upcoming),
            'active_vouchers' => array_map(function ($item) use ($soDem, $soNguoi) {
                $dieuKienArr = [];
                if ((int)$item['SoDemToiThieu'] > 0) {
                    $dieuKienArr[] = "Ở từ {$item['SoDemToiThieu']} đêm trở lên";
                }
                if ((int)($item['SoNguoiToiThieu'] ?? 0) > 0) {
                    $dieuKienArr[] = "Đoàn từ {$item['SoNguoiToiThieu']} người trở lên";
                }
                $dieuKienText = !empty($dieuKienArr) ? implode(' • ', $dieuKienArr) : 'Không giới hạn';

                $isEligible = ($soDem >= (int)$item['SoDemToiThieu']) && ($soNguoi >= (int)($item['SoNguoiToiThieu'] ?? 0));
                $soLuong = (int)($item['SoLuong'] ?? 100);
                $soLuongDaDung = (int)($item['SoLuongDaDung'] ?? 0);
                $soLuongConLai = max(0, $soLuong - $soLuongDaDung);
                $isNewCustomerPromo = (strtoupper($item['MaKM']) === 'KM001' 
                    || mb_stripos($item['TenKM'], 'khách mới') !== false 
                    || mb_stripos($item['TenKM'], 'khach hang moi') !== false 
                    || mb_stripos($item['TenKM'], 'khach moi') !== false);

                return [
                    'MaKM' => $item['MaKM'],
                    'TenKM' => $item['TenKM'],
                    'LoaiKM' => $item['LoaiKM'],
                    'GiaTriGiam' => (float)$item['GiaTriGiam'],
                    'GiaTriHienThi' => $item['LoaiKM'] === 'PhanTram' ? "{$item['GiaTriGiam']}%" : number_format((float)$item['GiaTriGiam'], 0, ',', '.') . ' đ',
                    'SoDemToiThieu' => (int)$item['SoDemToiThieu'],
                    'SoNguoiToiThieu' => (int)($item['SoNguoiToiThieu'] ?? 0),
                    'SoLuong' => $soLuong,
                    'SoLuongDaDung' => $soLuongDaDung,
                    'SoLuongConLai' => $soLuongConLai,
                    'LaKhachMoi' => $isNewCustomerPromo,
                    'DieuKien' => $dieuKienText,
                    'DuDieuKien' => $isEligible,
                    'NgayBatDau' => $item['NgayBatDau'],
                    'NgayKetThuc' => $item['NgayKetThuc'],
                ];
            }, $active),
            'upcoming_events' => array_map(function ($item) {
                return [
                    'MaKM' => $item['MaKM'],
                    'TenKM' => $item['TenKM'],
                    'LoaiKM' => $item['LoaiKM'],
                    'GiaTriGiam' => (float)$item['GiaTriGiam'],
                    'GiaTriHienThi' => $item['LoaiKM'] === 'PhanTram' ? "{$item['GiaTriGiam']}%" : number_format((float)$item['GiaTriGiam'], 0, ',', '.') . ' đ',
                    'NgayBatDau' => $item['NgayBatDau'],
                    'NgayKetThuc' => $item['NgayKetThuc'],
                    'ThongBao' => "Sự kiện bắt đầu từ {$item['NgayBatDau']}"
                ];
            }, $upcoming)
        ];
    }

    /**
     * Tự động tìm mã khuyến mãi có mức giảm giá cao nhất (Auto-Suggest / Auto-Apply)
     * dựa theo số đêm, số người và ngày áp dụng.
     */
    public function timKhuyenMaiTotNhat(int $soDem, int $soNguoi, float $tienPhong, ?string $ngayApDung = null): ?array
    {
        $today = $ngayApDung ?? date('Y-m-d');
        $active = $this->repo->getActivePromotions($today);
        $best = null;
        $maxGiam = 0.0;

        foreach ($active as $km) {
            if ($soDem < (int)$km['SoDemToiThieu']) continue;
            if ($soNguoi < (int)($km['SoNguoiToiThieu'] ?? 0)) continue;

            $giaTriGiam = (float)$km['GiaTriGiam'];
            $tienGiam = 0.0;
            if ($km['LoaiKM'] === 'PhanTram') {
                $tienGiam = round($tienPhong * ($giaTriGiam / 100));
            } else {
                $tienGiam = min($giaTriGiam, $tienPhong);
            }

            if ($tienGiam > $maxGiam) {
                $maxGiam = $tienGiam;
                $best = [
                    'MaKM' => $km['MaKM'],
                    'TenKM' => $km['TenKM'],
                    'LoaiKM' => $km['LoaiKM'],
                    'GiaTriGiam' => $giaTriGiam,
                    'TienGiam' => $tienGiam,
                    'TienPhongGoc' => $tienPhong,
                    'TienSauGiam' => max(0.0, $tienPhong - $tienGiam),
                    'SoDem' => $soDem,
                    'SoNguoi' => $soNguoi,
                    'MoTaGiam' => $km['LoaiKM'] === 'PhanTram' 
                        ? "Tự động giảm {$giaTriGiam}% (Tiết kiệm " . number_format($tienGiam, 0, ',', '.') . " đ)"
                        : "Tự động giảm " . number_format($tienGiam, 0, ',', '.') . " đ"
                ];
            }
        }

        return $best;
    }

    /**
     * Lấy toàn bộ danh sách cho Quản trị viên (Admin).
     */
    public function layDanhSachAdmin(): array
    {
        $list = $this->repo->getAll();
        $today = date('Y-m-d');

        $decorated = array_map(function ($item) use ($today) {
            $tinhTrang = 'DangDienRa';
            $tinhTrangText = 'Đang diễn ra';

            if ($item['TrangThai'] !== 'HoatDong') {
                $tinhTrang = 'TamKhoa';
                $tinhTrangText = 'Đang tạm dừng';
            } elseif ($today < $item['NgayBatDau']) {
                $tinhTrang = 'SapDienRa';
                $tinhTrangText = 'Sắp diễn ra';
            } elseif ($today > $item['NgayKetThuc']) {
                $tinhTrang = 'DaKetThuc';
                $tinhTrangText = 'Đã kết thúc';
            }

            $dieuKienArr = [];
            if ((int)$item['SoDemToiThieu'] > 0) {
                $dieuKienArr[] = "≥ {$item['SoDemToiThieu']} đêm";
            }
            if ((int)($item['SoNguoiToiThieu'] ?? 0) > 0) {
                $dieuKienArr[] = "≥ {$item['SoNguoiToiThieu']} người";
            }
            $dieuKienText = !empty($dieuKienArr) ? implode(', ', $dieuKienArr) : 'Không';

            return array_merge($item, [
                'GiaTriGiam' => (float)$item['GiaTriGiam'],
                'SoDemToiThieu' => (int)$item['SoDemToiThieu'],
                'SoNguoiToiThieu' => (int)($item['SoNguoiToiThieu'] ?? 0),
                'DieuKienText' => $dieuKienText,
                'TinhTrangThucTe' => $tinhTrang,
                'TinhTrangText' => $tinhTrangText
            ]);
        }, $list);

        return [
            'status' => 'success',
            'total' => count($decorated),
            'promotions' => $decorated
        ];
    }

    /**
     * Động cơ kiểm tra & Áp dụng mã khuyến mãi (Validation Engine):
     * Kiểm tra 5 điều kiện: Tồn tại -> Trạng thái mở -> Hạn sử dụng -> Số đêm tối thiểu -> Số người tối thiểu.
     */
    public function apDungKhuyenMai(array $data): array
    {
        $maKM = trim($data['MaKM'] ?? '');
        $soDem = (int)($data['SoDem'] ?? 1);
        $soNguoi = (int)($data['SoNguoi'] ?? 1);
        $tienPhong = (float)($data['TienPhong'] ?? 0);
        $ngayApDung = !empty($data['NgayApDung']) ? $data['NgayApDung'] : date('Y-m-d');

        if ($tienPhong <= 0) {
            return [
                'success' => false,
                'error' => 'Tiền phòng không hợp lệ để áp dụng khuyến mãi.'
            ];
        }

        if ($soDem <= 0) $soDem = 1;
        if ($soNguoi <= 0) $soNguoi = 1;

        // Nếu mã rỗng hoặc là AUTO -> Tự động tìm mã tốt nhất
        if (empty($maKM) || strtoupper($maKM) === 'AUTO') {
            $best = $this->timKhuyenMaiTotNhat($soDem, $soNguoi, $tienPhong, $ngayApDung);
            if ($best) {
                return [
                    'success' => true,
                    'message' => "Hệ thống tự động áp dụng mã tốt nhất '{$best['MaKM']}'!",
                    'data' => $best
                ];
            } else {
                return [
                    'success' => false,
                    'error' => "Không có mã khuyến mãi nào phù hợp với đơn {$soDem} đêm, {$soNguoi} người."
                ];
            }
        }

        // Bước 1: Kiểm tra mã có tồn tại không
        $km = $this->repo->findByMaKM($maKM);
        if (!$km) {
            return [
                'success' => false,
                'error' => "Mã khuyến mãi '{$maKM}' không tồn tại trong hệ thống."
            ];
        }

        // Bước 2: Kiểm tra trạng thái hoạt động
        if ($km['TrangThai'] !== 'HoatDong') {
            return [
                'success' => false,
                'error' => "Mã khuyến mãi '{$maKM}' hiện đang bị tạm khóa hoặc đã ngưng áp dụng."
            ];
        }

        // Bước 3: Kiểm tra ngày áp dụng (Hiệu lực thời gian)
        if ($ngayApDung < $km['NgayBatDau']) {
            return [
                'success' => false,
                'error' => "Mã khuyến mãi '{$maKM}' chưa đến thời gian áp dụng. Ngày bắt đầu: {$km['NgayBatDau']}."
            ];
        }

        if ($ngayApDung > $km['NgayKetThuc']) {
            return [
                'success' => false,
                'error' => "Mã khuyến mãi '{$maKM}' đã hết hạn sử dụng vào ngày {$km['NgayKetThuc']}."
            ];
        }

        // Bước 3.1: Kiểm tra số lượng giới hạn còn lại
        $soLuong = (int)($km['SoLuong'] ?? 100);
        $soLuongDaDung = (int)($km['SoLuongDaDung'] ?? 0);
        if ($soLuongDaDung >= $soLuong) {
            return [
                'success' => false,
                'error' => "Mã khuyến mãi '{$maKM}' đã hết lượt sử dụng (Đã dùng {$soLuongDaDung}/{$soLuong} lượt)."
            ];
        }

        // Bước 3.2: Kiểm tra khuyến mãi dành riêng cho khách hàng mới
        $maKH = trim($data['maKH'] ?? ($data['makh'] ?? ''));
        $maTK = trim($data['maTK'] ?? ($data['matk'] ?? ''));
        $sdt = trim($data['sdt'] ?? ($data['SDT'] ?? ''));

        $db = $this->repo->getConnection();
        if (empty($maKH) && !empty($maTK)) {
            $stmtKH = $db->prepare("SELECT MaKH FROM KhachHang WHERE MaTK = ?");
            $stmtKH->execute([$maTK]);
            $maKH = $stmtKH->fetchColumn() ?: '';
        }
        if (empty($maKH) && !empty($sdt)) {
            $stmtKH = $db->prepare("SELECT MaKH FROM KhachHang WHERE SDT = ?");
            $stmtKH->execute([$sdt]);
            $maKH = $stmtKH->fetchColumn() ?: '';
        }
        if (!empty($maKH) && str_starts_with(strtoupper($maKH), 'TK')) {
            $stmtKH = $db->prepare("SELECT MaKH FROM KhachHang WHERE MaTK = ?");
            $stmtKH->execute([$maKH]);
            $realMaKH = $stmtKH->fetchColumn();
            if ($realMaKH) $maKH = $realMaKH;
        }

        $isNewCustomerPromo = (strtoupper($km['MaKM']) === 'KM001' 
            || mb_stripos($km['TenKM'], 'khách mới') !== false 
            || mb_stripos($km['TenKM'], 'khach hang moi') !== false 
            || mb_stripos($km['TenKM'], 'khach moi') !== false 
            || mb_stripos($km['TenKM'], 'lần đầu') !== false);

        if ($isNewCustomerPromo) {
            if (empty($maKH)) {
                return [
                    'success' => false,
                    'error' => "Mã khuyến mãi '{$maKM}' chỉ áp dụng cho tài khoản khách hàng mới trong lần đặt phòng đầu tiên. Vui lòng đăng nhập để áp dụng."
                ];
            }

            $stmtCount = $db->prepare("SELECT COUNT(*) FROM PhieuNhanPhong WHERE MaKH = ? AND TrangThai != 'DaHuy'");
            $stmtCount->execute([$maKH]);
            $soPhieu = (int)$stmtCount->fetchColumn();

            $stmtUsed = $db->prepare(
                "SELECT COUNT(*) FROM PhieuNhanPhong p 
                 JOIN KhuyenMai k ON p.MaKM = k.MaKM 
                 WHERE p.MaKH = ? AND (
                     k.MaKM = 'KM001' 
                     OR k.TenKM LIKE '%khách mới%' 
                     OR k.TenKM LIKE '%khach hang moi%' 
                     OR k.TenKM LIKE '%khach moi%' 
                     OR k.TenKM LIKE '%lần đầu%'
                 )"
            );
            $stmtUsed->execute([$maKH]);
            $daTungDung = ((int)$stmtUsed->fetchColumn()) > 0;

            if ($soPhieu > 0 || $daTungDung) {
                return [
                    'success' => false,
                    'error' => "Mã khuyến mãi '{$maKM}' chỉ áp dụng 1 lần duy nhất cho khách hàng mới trong lần đặt phòng đầu tiên. Tài khoản của bạn đã từng đặt phòng nên không thể áp dụng mã này."
                ];
            }
        }

        // Bước 4: Kiểm tra điều kiện số đêm lưu trú tối thiểu
        $soDemToiThieu = (int)$km['SoDemToiThieu'];
        if ($soDem < $soDemToiThieu) {
            return [
                'success' => false,
                'error' => "Mã khuyến mãi '{$maKM}' yêu cầu lưu trú tối thiểu {$soDemToiThieu} đêm (Bạn đang đặt {$soDem} đêm)."
            ];
        }

        // Bước 5: Kiểm tra điều kiện số người lưu trú tối thiểu
        $soNguoiToiThieu = (int)($km['SoNguoiToiThieu'] ?? 0);
        if ($soNguoi < $soNguoiToiThieu) {
            return [
                'success' => false,
                'error' => "Mã khuyến mãi '{$maKM}' yêu cầu đoàn từ {$soNguoiToiThieu} người trở lên (Bạn đang chọn {$soNguoi} người)."
            ];
        }

        // Tính số tiền giảm giá
        $giaTriGiam = (float)$km['GiaTriGiam'];
        $tienGiam = 0.0;

        if ($km['LoaiKM'] === 'PhanTram') {
            $tienGiam = round($tienPhong * ($giaTriGiam / 100));
        } else {
            $tienGiam = min($giaTriGiam, $tienPhong);
        }

        $tienSauGiam = max(0.0, $tienPhong - $tienGiam);

        return [
            'success' => true,
            'message' => 'Áp dụng mã khuyến mãi thành công!',
            'data' => [
                'MaKM' => $km['MaKM'],
                'TenKM' => $km['TenKM'],
                'LoaiKM' => $km['LoaiKM'],
                'GiaTriGiam' => $giaTriGiam,
                'SoDem' => $soDem,
                'SoNguoi' => $soNguoi,
                'TienPhongGoc' => $tienPhong,
                'TienGiam' => $tienGiam,
                'TienSauGiam' => $tienSauGiam,
                'MoTaGiam' => $km['LoaiKM'] === 'PhanTram' 
                    ? "Giảm {$giaTriGiam}% (Tiết kiệm " . number_format($tienGiam, 0, ',', '.') . " đ)"
                    : "Giảm trực tiếp " . number_format($tienGiam, 0, ',', '.') . " đ"
            ]
        ];
    }

    // =========================================================================
    // PHẦN 2: QUẢN TRỊ KHUYẾN MÃI (WRITE / ADMIN COMMAND)
    // =========================================================================

    /**
     * Thêm mới khuyến mãi (Admin).
     */
    public function themKhuyenMai(array $data): array
    {
        $maKM = strtoupper(trim($data['MaKM'] ?? ''));
        $tenKM = trim($data['TenKM'] ?? '');
        $loaiKM = $data['LoaiKM'] ?? 'PhanTram';
        $giaTriGiam = (float)($data['GiaTriGiam'] ?? 0);
        $soDemToiThieu = (int)($data['SoDemToiThieu'] ?? 0);
        $soNguoiToiThieu = (int)($data['SoNguoiToiThieu'] ?? 0);
        $ngayBatDau = $data['NgayBatDau'] ?? '';
        $ngayKetThuc = $data['NgayKetThuc'] ?? '';
        $trangThai = $data['TrangThai'] ?? 'HoatDong';

        if (empty($maKM) || empty($tenKM) || empty($ngayBatDau) || empty($ngayKetThuc)) {
            return ['success' => false, 'error' => 'Vui lòng điền đầy đủ các thông tin bắt buộc (Mã, Tên, Ngày bắt đầu, Ngày kết thúc).'];
        }

        if (!in_array($loaiKM, ['PhanTram', 'SoTien'], true)) {
            return ['success' => false, 'error' => "Loại khuyến mãi phải là 'PhanTram' hoặc 'SoTien'."];
        }

        if ($giaTriGiam <= 0) {
            return ['success' => false, 'error' => 'Giá trị giảm phải lớn hơn 0.'];
        }

        if ($loaiKM === 'PhanTram' && $giaTriGiam > 100) {
            return ['success' => false, 'error' => 'Khuyến mãi phần trăm không thể vượt quá 100%.'];
        }

        if ($ngayKetThuc < $ngayBatDau) {
            return ['success' => false, 'error' => 'Ngày kết thúc phải lớn hơn hoặc bằng ngày bắt đầu.'];
        }

        // Kiểm tra trùng mã
        if ($this->repo->findByMaKM($maKM)) {
            return ['success' => false, 'error' => "Mã khuyến mãi '{$maKM}' đã tồn tại trong hệ thống."];
        }

        $ok = $this->repo->create([
            'MaKM' => $maKM,
            'TenKM' => $tenKM,
            'LoaiKM' => $loaiKM,
            'GiaTriGiam' => $giaTriGiam,
            'SoDemToiThieu' => max(0, $soDemToiThieu),
            'SoNguoiToiThieu' => max(0, $soNguoiToiThieu),
            'NgayBatDau' => $ngayBatDau,
            'NgayKetThuc' => $ngayKetThuc,
            'TrangThai' => $trangThai
        ]);

        if (!$ok) {
            return ['success' => false, 'error' => 'Không thể lưu mã khuyến mãi vào cơ sở dữ liệu.'];
        }

        return [
            'success' => true,
            'message' => "Đã tạo mã khuyến mãi '{$maKM}' thành công!",
            'data' => $this->repo->findByMaKM($maKM)
        ];
    }

    /**
     * Cập nhật thông tin khuyến mãi (Admin).
     */
    public function capNhatKhuyenMai(string $maKM, array $data): array
    {
        $existing = $this->repo->findByMaKM($maKM);
        if (!$existing) {
            return ['success' => false, 'error' => "Không tìm thấy mã khuyến mãi '{$maKM}'."];
        }

        $tenKM = trim($data['TenKM'] ?? $existing['TenKM']);
        $loaiKM = $data['LoaiKM'] ?? $existing['LoaiKM'];
        $giaTriGiam = isset($data['GiaTriGiam']) ? (float)$data['GiaTriGiam'] : (float)$existing['GiaTriGiam'];
        $soDemToiThieu = isset($data['SoDemToiThieu']) ? (int)$data['SoDemToiThieu'] : (int)$existing['SoDemToiThieu'];
        $soNguoiToiThieu = isset($data['SoNguoiToiThieu']) ? (int)$data['SoNguoiToiThieu'] : (int)($existing['SoNguoiToiThieu'] ?? 0);
        $ngayBatDau = $data['NgayBatDau'] ?? $existing['NgayBatDau'];
        $ngayKetThuc = $data['NgayKetThuc'] ?? $existing['NgayKetThuc'];
        $trangThai = $data['TrangThai'] ?? $existing['TrangThai'];

        if ($ngayKetThuc < $ngayBatDau) {
            return ['success' => false, 'error' => 'Ngày kết thúc phải lớn hơn hoặc bằng ngày bắt đầu.'];
        }

        $ok = $this->repo->update($maKM, [
            'TenKM' => $tenKM,
            'LoaiKM' => $loaiKM,
            'GiaTriGiam' => $giaTriGiam,
            'SoDemToiThieu' => $soDemToiThieu,
            'SoNguoiToiThieu' => $soNguoiToiThieu,
            'NgayBatDau' => $ngayBatDau,
            'NgayKetThuc' => $ngayKetThuc,
            'TrangThai' => $trangThai
        ]);

        return [
            'success' => $ok,
            'message' => $ok ? "Cập nhật mã '{$maKM}' thành công!" : "Cập nhật thất bại."
        ];
    }

    /**
     * Bật/Tắt trạng thái khuyến mãi (Admin).
     */
    public function doiTrangThai(string $maKM, string $trangThai): array
    {
        if (!in_array($trangThai, ['HoatDong', 'NgungHoatDong'], true)) {
            return ['success' => false, 'error' => 'Trạng thái không hợp lệ.'];
        }

        $existing = $this->repo->findByMaKM($maKM);
        if (!$existing) {
            return ['success' => false, 'error' => "Không tìm thấy mã khuyến mãi '{$maKM}'."];
        }

        $ok = $this->repo->updateStatus($maKM, $trangThai);
        return [
            'success' => $ok,
            'message' => $ok ? "Đã chuyển trạng thái mã '{$maKM}' sang {$trangThai}." : 'Thao tác thất bại.'
        ];
    }

    /**
     * Xóa khuyến mãi (Admin).
     */
    public function xoaKhuyenMai(string $maKM): array
    {
        $existing = $this->repo->findByMaKM($maKM);
        if (!$existing) {
            return ['success' => false, 'error' => "Không tìm thấy mã khuyến mãi '{$maKM}'."];
        }

        try {
            $ok = $this->repo->delete($maKM);
            return [
                'success' => $ok,
                'message' => $ok ? "Đã xóa mã khuyến mãi '{$maKM}'." : 'Xóa thất bại.'
            ];
        } catch (\PDOException $e) {
            return [
                'success' => false,
                'error' => "Không thể xóa mã khuyến mãi này vì đã có phiếu đặt phòng sử dụng. Bạn có thể chọn 'Tắt hoạt động' thay thế."
            ];
        }
    }
}
