<?php

namespace App\ThanhToan\Services;

use App\ThanhToan\Repositories\ThanhToanRepository;
use App\HoaDon\Repositories\HoaDonRepository;

/**
 * Service xử lý toàn bộ logic nghiệp vụ thanh toán, tạo mã VietQR Napas 247,
 * đối soát nghiêm ngặt Webhook và quy trình hoàn tiền (Mô hình 1).
 */
class ThanhToanService
{
    private ThanhToanRepository $repo;
    private HoaDonRepository $hoaDonRepo;

    // Cấu hình tài khoản Napas 247 của khách sạn (MBBank)
    public const BANK_ID = 'MB';
    public const BANK_NAME = 'MBBank (Ngân hàng Quân Đội)';
    public const ACCOUNT_NO = '0589822346';
    public const ACCOUNT_NAME = 'NGUYEN HOANG ANH';
    public const QR_EXPIRE_SECONDS = 900; // Thời hạn mã QR: 15 phút (900 giây)

    public function __construct(?ThanhToanRepository $repo = null, ?HoaDonRepository $hoaDonRepo = null)
    {
        $this->repo = $repo ?? new ThanhToanRepository();
        $this->hoaDonRepo = $hoaDonRepo ?? new HoaDonRepository();
    }

    /**
     * Tạo giao dịch thanh toán cho hóa đơn (Chuyển khoản VietQR hoặc Tiền mặt).
     */
    public function taoThanhToan(array $params): array
    {
        $maHD = trim($params['MaHD'] ?? '');
        $hinhThuc = $params['HinhThuc'] ?? 'ChuyenKhoan';

        if (empty($maHD)) {
            return ['success' => false, 'error' => 'Vui lòng cung cấp mã hóa đơn (MaHD).'];
        }

        if (!in_array($hinhThuc, ['ChuyenKhoan', 'TienMat'], true)) {
            return ['success' => false, 'error' => "Hình thức thanh toán phải là 'ChuyenKhoan' hoặc 'TienMat'."];
        }

        $hoaDon = $this->hoaDonRepo->findHoaDonById($maHD);
        if (!$hoaDon) {
            return ['success' => false, 'error' => "Không tìm thấy hóa đơn '{$maHD}'."];
        }

        $soTien = (float)$hoaDon['TongTienSauVAT'];
        if ($soTien <= 0) {
            return ['success' => false, 'error' => 'Số tiền hóa đơn không hợp lệ để thanh toán.'];
        }

        // Kiểm tra xem đã có giao dịch thanh toán cho hóa đơn này chưa
        $existing = $this->repo->findByMaHD($maHD);
        if ($existing && $existing['TrangThaiThanhToan'] === 'HoanTat') {
            return [
                'success' => true,
                'message' => "Hóa đơn '{$maHD}' đã được thanh toán hoàn tất trước đó.",
                'data' => $existing
            ];
        }

        $maTT = $existing ? $existing['MaThanhToan'] : $this->repo->generateNextMaTT();
        $now = date('Y-m-d H:i:s');
        $trangThai = ($hinhThuc === 'TienMat') ? 'HoanTat' : 'ChoThanhToan';

        if ($existing) {
            // Cập nhật giao dịch đang chờ
            $this->repo->updateStatus($maTT, $trangThai, $now);
        } else {
            // Tạo mới giao dịch
            $this->repo->create([
                'MaThanhToan' => $maTT,
                'SoTien' => $soTien,
                'ThoiGianThanhToan' => $now,
                'HinhThuc' => $hinhThuc,
                'TrangThaiThanhToan' => $trangThai,
                'MaHD' => $maHD,
            ]);
        }

        // Sinh link VietQR chuẩn Napas 247 nếu là Chuyển Khoản
        $vietQrUrl = null;
        if ($hinhThuc === 'ChuyenKhoan') {
            $encodedAccountName = urlencode(self::ACCOUNT_NAME);
            $vietQrUrl = "https://img.vietqr.io/image/" . self::BANK_ID . "-" . self::ACCOUNT_NO . "-compact2.png"
                . "?amount=" . (int)$soTien
                . "&addInfo=" . urlencode($maHD)
                . "&accountName=" . $encodedAccountName;
        }

        return [
            'success' => true,
            'message' => ($hinhThuc === 'TienMat') ? 'Thanh toán tiền mặt thành công!' : 'Đã tạo mã QR Napas 247 thanh toán.',
            'data' => [
                'MaThanhToan' => $maTT,
                'MaHD' => $maHD,
                'SoTien' => $soTien,
                'HinhThuc' => $hinhThuc,
                'TrangThaiThanhToan' => $trangThai,
                'ThoiGianKhoiTao' => $now,
                'ThoiHanGiay' => self::QR_EXPIRE_SECONDS,
                'TaiKhoanNhan' => [
                    'NganHang' => self::BANK_NAME,
                    'MaNganHang' => self::BANK_ID,
                    'SoTaiKhoan' => self::ACCOUNT_NO,
                    'ChuTaiKhoan' => self::ACCOUNT_NAME,
                    'NoiDungChuyenKhoan' => $maHD,
                ],
                'VietQrUrl' => $vietQrUrl,
            ]
        ];
    }

    /**
     * Xem trạng thái và thông tin thanh toán của một hóa đơn (hỗ trợ Polling).
     */
    public function xemThanhToan(array $params): array
    {
        $maHD = trim($params['MaHD'] ?? '');
        $maTT = trim($params['MaThanhToan'] ?? '');

        $tt = null;
        if (!empty($maTT)) {
            $tt = $this->repo->findById($maTT);
        } elseif (!empty($maHD)) {
            $tt = $this->repo->findByMaHD($maHD);
        }

        if (!$tt) {
            return ['success' => false, 'error' => 'Chưa có thông tin giao dịch thanh toán.'];
        }

        // Kiểm tra thời hạn 15 phút của mã QR
        $thoiGianTao = strtotime($tt['ThoiGianThanhToan']);
        $thoiGianHetHan = $thoiGianTao + self::QR_EXPIRE_SECONDS;
        $conLaiGiay = max(0, $thoiGianHetHan - time());
        $isExpired = ($conLaiGiay <= 0 && $tt['TrangThaiThanhToan'] === 'ChoThanhToan');

        $vietQrUrl = null;
        if ($tt['HinhThuc'] === 'ChuyenKhoan' && !$isExpired && $tt['TrangThaiThanhToan'] === 'ChoThanhToan') {
            $vietQrUrl = "https://img.vietqr.io/image/" . self::BANK_ID . "-" . self::ACCOUNT_NO . "-compact2.png"
                . "?amount=" . (int)$tt['SoTien']
                . "&addInfo=" . urlencode($tt['MaHD'])
                . "&accountName=" . urlencode(self::ACCOUNT_NAME);
        }

        return [
            'success' => true,
            'data' => array_merge($tt, [
                'SoTien' => (float)$tt['SoTien'],
                'SoTienHoan' => (float)($tt['SoTienHoan'] ?? 0),
                'ConLaiGiay' => $conLaiGiay,
                'DaHetHan' => $isExpired,
                'VietQrUrl' => $vietQrUrl,
                'TaiKhoanNhan' => [
                    'NganHang' => self::BANK_NAME,
                    'SoTaiKhoan' => self::ACCOUNT_NO,
                    'ChuTaiKhoan' => self::ACCOUNT_NAME,
                    'NoiDungChuyenKhoan' => $tt['MaHD'],
                ]
            ])
        ];
    }

    /**
     * Engine đối soát nghiêm ngặt khi ngân hàng bắn Webhook về:
     * - Sai nội dung: KHÔNG tiếp nhận.
     * - Chuyển thiếu tiền: KHÔNG tiếp nhận.
     * - Quá hạn 15 phút: Cảnh báo hết hạn.
     */
    public function xuLyWebhook(array $payload): array
    {
        $content = strtoupper(trim($payload['content'] ?? ($payload['description'] ?? '')));
        $soTienChuyen = (float)($payload['amount'] ?? 0);

        if (empty($content)) {
            return [
                'success' => false,
                'code' => 'INVALID_CONTENT',
                'error' => 'Nội dung chuyển khoản trống. Mọi trường hợp không có nội dung không được tiếp nhận!'
            ];
        }

        // 1. Đối soát Mã Hóa Đơn trong nội dung chuyển khoản
        if (!preg_match('/(HD\d+|HD_[A-Z0-9]+)/i', $content, $matches)) {
            return [
                'success' => false,
                'code' => 'WRONG_CONTENT',
                'error' => "Nội dung chuyển khoản '{$content}' không chứa mã hóa đơn hợp lệ. Từ chối tiếp nhận giao dịch!"
            ];
        }

        $maHD = strtoupper($matches[1]);
        $tt = $this->repo->findByMaHD($maHD);
        if (!$tt) {
            return [
                'success' => false,
                'code' => 'INVOICE_NOT_FOUND',
                'error' => "Mã hóa đơn '{$maHD}' không tồn tại trong hệ thống. Giao dịch chuyển sai mã, không tiếp nhận!"
            ];
        }

        if ($tt['TrangThaiThanhToan'] === 'HoanTat') {
            return [
                'success' => true,
                'message' => "Hóa đơn '{$maHD}' đã hoàn tất thanh toán từ trước."
            ];
        }

        // 2. Đối soát số tiền chuyển so với số tiền cần thu
        $soTienYeuCau = (float)$tt['SoTien'];
        if ($soTienChuyen < $soTienYeuCau) {
            return [
                'success' => false,
                'code' => 'INSUFFICIENT_AMOUNT',
                'error' => "Số tiền chuyển (" . number_format($soTienChuyen, 0, ',', '.') . " đ) ít hơn số tiền hóa đơn (" . number_format($soTienYeuCau, 0, ',', '.') . " đ). Từ chối tiếp nhận giao dịch!"
            ];
        }

        // 3. Đối soát thời hạn hiệu lực 15 phút
        $thoiGianTao = strtotime($tt['ThoiGianThanhToan']);
        if ((time() - $thoiGianTao) > self::QR_EXPIRE_SECONDS) {
            return [
                'success' => false,
                'code' => 'QR_EXPIRED',
                'error' => "Mã QR cho hóa đơn '{$maHD}' đã hết hạn 15 phút trước khi chuyển. Giao dịch cần chuyển sang đối soát thủ công tại quầy!"
            ];
        }

        // 4. Hợp lệ 100% -> Cập nhật sang Hoàn Tất
        $this->repo->updateStatus($tt['MaThanhToan'], 'HoanTat', date('Y-m-d H:i:s'));

        return [
            'success' => true,
            'message' => "Thanh toán thành công cho hóa đơn '{$maHD}' qua Napas 247 MBBank!",
            'data' => [
                'MaHD' => $maHD,
                'MaThanhToan' => $tt['MaThanhToan'],
                'SoTienDaNhan' => $soTienChuyen,
                'TrangThai' => 'HoanTat',
                'ThoiGianNhan' => date('Y-m-d H:i:s')
            ]
        ];
    }

    /**
     * Quy trình Hoàn Tiền (Mô hình 1: Khách điền STK nhận lại tiền + Chính sách hoàn tiền).
     */
    public function yeuCauHoanTien(array $params): array
    {
        $maHD = trim($params['MaHD'] ?? '');
        $nganHangHoan = trim($params['NganHangHoan'] ?? '');
        $stkHoan = trim($params['STKHoan'] ?? '');
        $tenChuTKHoan = strtoupper(trim($params['TenChuTKHoan'] ?? ''));
        $lyDoHoan = trim($params['LyDoHoan'] ?? 'Khách yêu cầu hủy phòng');

        if (empty($maHD) || empty($nganHangHoan) || empty($stkHoan) || empty($tenChuTKHoan)) {
            return [
                'success' => false,
                'error' => 'Vui lòng điền đầy đủ thông tin nhận tiền hoàn (Ngân hàng, Số tài khoản, Tên chủ tài khoản).'
            ];
        }

        $tt = $this->repo->findByMaHD($maHD);
        if (!$tt) {
            return ['success' => false, 'error' => "Không tìm thấy giao dịch thanh toán của hóa đơn '{$maHD}'."];
        }

        if ($tt['TrangThaiThanhToan'] !== 'HoanTat') {
            return ['success' => false, 'error' => "Hóa đơn '{$maHD}' chưa thanh toán hoàn tất, không thể hoàn tiền."];
        }

        // Tính chính sách hoàn tiền theo thời gian hủy
        $hoaDon = $this->hoaDonRepo->findHoaDonById($maHD);
        $soTienGoc = (float)$tt['SoTien'];
        $tiLeHoan = 1.0; // Mặc định hoàn 100%
        $phiHuy = 0.0;
        $chinhSachGiaiThich = 'Hủy trước 24h: Hoàn 100% tiền';

        // Lấy thông tin ngày nhận phòng từ phiếu
        $pnp = $this->hoaDonRepo->getPhieuNhanPhongFull($hoaDon['MaPhieu']);
        if ($pnp && !empty($pnp['DanhSachPhong'][0]['ThoiGianNhanPhong'])) {
            $gioNhanPhong = strtotime($pnp['DanhSachPhong'][0]['ThoiGianNhanPhong']);
            $soGioConLai = ($gioNhanPhong - time()) / 3600;

            if ($soGioConLai < 24 && $soGioConLai > 0) {
                // Hủy sát giờ (< 24h): Khấu trừ 30% phí giữ phòng
                $tiLeHoan = 0.70;
                $chinhSachGiaiThich = 'Hủy trong vòng 24h trước giờ nhận phòng: Khấu trừ 30% phí hủy';
            } elseif ($soGioConLai <= 0) {
                // Đã quá giờ nhận phòng: Khấu trừ 50%
                $tiLeHoan = 0.50;
                $chinhSachGiaiThich = 'Hủy sau giờ nhận phòng quy định: Khấu trừ 50% phí hủy';
            }
        }

        $soTienHoan = round($soTienGoc * $tiLeHoan);
        $phiHuy = $soTienGoc - $soTienHoan;

        // Lưu thông tin hoàn tiền vào CSDL
        $this->repo->saveRefund($tt['MaThanhToan'], [
            'SoTienHoan' => $soTienHoan,
            'NganHangHoan' => $nganHangHoan,
            'STKHoan' => $stkHoan,
            'TenChuTKHoan' => $tenChuTKHoan,
            'LyDoHoan' => $lyDoHoan . " ({$chinhSachGiaiThich})",
            'ThoiGianHoan' => date('Y-m-d H:i:s'),
        ]);

        // Sinh mã VietQR chuyển trả cho khách
        $vietQrHoanUrl = "https://img.vietqr.io/image/{$nganHangHoan}-{$stkHoan}-compact2.png"
            . "?amount=" . (int)$soTienHoan
            . "&addInfo=" . urlencode("HOAN TIEN " . $maHD)
            . "&accountName=" . urlencode($tenChuTKHoan);

        return [
            'success' => true,
            'message' => "Đã duyệt hoàn tiền cho hóa đơn '{$maHD}' thành công!",
            'data' => [
                'MaHD' => $maHD,
                'MaThanhToan' => $tt['MaThanhToan'],
                'SoTienGoc' => $soTienGoc,
                'PhiHuy' => $phiHuy,
                'SoTienHoan' => $soTienHoan,
                'ChinhSach' => $chinhSachGiaiThich,
                'TaiKhoanNhanHoan' => [
                    'NganHang' => $nganHangHoan,
                    'SoTaiKhoan' => $stkHoan,
                    'ChuTaiKhoan' => $tenChuTKHoan,
                ],
                'VietQrHoanUrl' => $vietQrHoanUrl,
                'ThoiGianHoan' => date('Y-m-d H:i:s')
            ]
        ];
    }

    /**
     * Lấy danh sách toàn bộ các giao dịch thanh toán.
     */
    public function layDanhSachThanhToan(): array
    {
        $list = $this->repo->getAll();
        return [
            'success' => true,
            'total' => count($list),
            'data' => $list
        ];
    }
}
