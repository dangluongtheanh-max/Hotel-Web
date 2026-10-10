<?php

namespace App\ThanhToan\Services;

use App\ThanhToan\Repositories\ThanhToanRepository;
use App\HoaDon\Repositories\HoaDonRepository;

/**
 * Service xử lý toàn bộ logic nghiệp vụ thanh toán, tích hợp kết nối Ngân Hàng Thật Napas 247 MBBank,
 * đối soát giao dịch tự động qua SePay API / Webhook, tự động hủy khi hết hạn và nút Hủy thanh toán.
 */
class ThanhToanService
{
    private ThanhToanRepository $repo;
    private HoaDonRepository $hoaDonRepo;

    // Cấu hình tài khoản MBBank chính thức của khách sạn
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
     * Tạo giao dịch thanh toán cho hóa đơn (sinh mã VietQR Napas 247).
     */
    public function taoThanhToan(array $params): array
    {
        $maHD = trim($params['MaHD'] ?? '');
        $hinhThuc = $params['HinhThuc'] ?? 'ChuyenKhoan';

        if (empty($maHD)) {
            return ['success' => false, 'error' => 'Vui lòng cung cấp mã hóa đơn (MaHD).'];
        }

        $hoaDon = $this->hoaDonRepo->findHoaDonById($maHD);
        if (!$hoaDon) {
            return ['success' => false, 'error' => "Không tìm thấy hóa đơn '{$maHD}'."];
        }

        $soTien = (float)$hoaDon['TongTienSauVAT'];
        if ($soTien <= 0) {
            return ['success' => false, 'error' => 'Số tiền hóa đơn không hợp lệ để thanh toán.'];
        }

        // Kiểm tra xem đã có giao dịch hoàn tất chưa
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
            $this->repo->updateStatus($maTT, $trangThai, $now);
        } else {
            $this->repo->create([
                'MaThanhToan' => $maTT,
                'SoTien' => $soTien,
                'ThoiGianThanhToan' => $now,
                'HinhThuc' => $hinhThuc,
                'TrangThaiThanhToan' => $trangThai,
                'MaHD' => $maHD,
            ]);
        }

        // Sinh link VietQR chuẩn Napas 247 MBBank
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
     * Tra cứu trạng thái giao dịch & Tự động kiểm tra hết hạn 15 phút.
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

        // Kiểm tra thời hạn 15 phút (900 giây)
        $thoiGianTao = strtotime($tt['ThoiGianThanhToan']);
        $thoiGianHetHan = $thoiGianTao + self::QR_EXPIRE_SECONDS;
        $conLaiGiay = max(0, $thoiGianHetHan - time());
        $isExpired = false;

        // Nếu quá 15 phút mà chưa thanh toán -> Tự động đánh dấu Hết Hạn
        if ($conLaiGiay <= 0 && $tt['TrangThaiThanhToan'] === 'ChoThanhToan') {
            $isExpired = true;
            $this->repo->updateStatus($tt['MaThanhToan'], 'DaHetHan');
            $tt['TrangThaiThanhToan'] = 'DaHetHan';
        }

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
     * Hủy giao dịch thanh toán (Nút Hủy theo yêu cầu người dùng).
     */
    public function huyThanhToan(array $params): array
    {
        $maHD = trim($params['MaHD'] ?? '');
        if (empty($maHD)) {
            return ['success' => false, 'error' => 'Vui lòng cung cấp mã hóa đơn (MaHD) cần hủy.'];
        }

        $tt = $this->repo->findByMaHD($maHD);
        if (!$tt) {
            return ['success' => false, 'error' => "Không tìm thấy giao dịch của hóa đơn '{$maHD}'."];
        }

        if ($tt['TrangThaiThanhToan'] === 'HoanTat') {
            return ['success' => false, 'error' => "Hóa đơn '{$maHD}' đã thanh toán hoàn tất, không thể hủy trực tiếp. Vui lòng dùng chức năng Hoàn Tiền!"];
        }

        $this->repo->updateStatus($tt['MaThanhToan'], 'DaHuy', date('Y-m-d H:i:s'));

        return [
            'success' => true,
            'message' => "Đã hủy giao dịch thanh toán của hóa đơn '{$maHD}' thành công!",
            'data' => [
                'MaHD' => $maHD,
                'MaThanhToan' => $tt['MaThanhToan'],
                'TrangThaiThanhToan' => 'DaHuy'
            ]
        ];
    }

    /**
     * Đồng bộ biến động số dư Ngân Hàng Thật (MBBank qua SePay API):
     * Gọi trực tiếp lên ngân hàng thật để tìm giao dịch khớp đúng mã hóa đơn.
     * - Nếu chuyển sai nội dung: Bỏ qua (không có gì xảy ra).
     * - Nếu có tiền vào khớp đúng MaHD và đủ số tiền: LẬP TỨC CHUYỂN TRẠNG THÁI THÀNH CÔNG!
     */
    public function dongBoNganHangThat(string $maHD, ?string $customApiKey = null): array
    {
        $tt = $this->repo->findByMaHD($maHD);
        if (!$tt) {
            return ['success' => false, 'error' => "Không tìm thấy giao dịch của hóa đơn '{$maHD}'."];
        }

        if ($tt['TrangThaiThanhToan'] === 'HoanTat') {
            return [
                'success' => true,
                'is_paid' => true,
                'message' => "Hóa đơn '{$maHD}' đã được xác nhận thanh toán thành công!"
            ];
        }

        if ($tt['TrangThaiThanhToan'] === 'DaHuy' || $tt['TrangThaiThanhToan'] === 'DaHetHan') {
            return [
                'success' => false,
                'is_paid' => false,
                'error' => "Giao dịch này đã bị hủy hoặc hết hạn."
            ];
        }

        $apiKey = $customApiKey ?: ($_ENV['SEPAY_API_KEY'] ?? '');
        if (empty($apiKey)) {
            // Chưa có API Key ngân hàng thật -> trả về hướng dẫn kết nối
            return [
                'success' => false,
                'is_paid' => false,
                'has_api_key' => false,
                'message' => 'Chưa cấu hình SePay API Key để kết nối biến động số dư ngân hàng thật.'
            ];
        }

        // Gọi API SePay kiểm tra danh sách giao dịch MBBank gần nhất
        $url = "https://my.sepay.vn/userapi/transactions/list?account_number=" . self::ACCOUNT_NO . "&limit=20";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$apiKey}",
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$res) {
            return [
                'success' => false,
                'is_paid' => false,
                'has_api_key' => true,
                'error' => 'Không thể kết nối đến cổng ngân hàng SePay. Vui lòng kiểm tra lại API Key.'
            ];
        }

        $json = json_decode($res, true);
        $transactions = $json['messages'] ?? ($json['transactions'] ?? []);

        $soTienCanThu = (float)$tt['SoTien'];
        $found = false;
        $matchedTx = null;

        // Quét từng giao dịch ngân hàng thật
        foreach ($transactions as $tx) {
            $content = strtoupper($tx['transaction_content'] ?? ($tx['description'] ?? ''));
            $amountIn = (float)($tx['amount_in'] ?? ($tx['amount'] ?? 0));

            // Kiểm tra: Nội dung chuyển khoản phải chứa đúng mã hóa đơn
            if (strpos($content, strtoupper($maHD)) !== false) {
                // Kiểm tra: Số tiền phải >= số tiền hóa đơn
                if ($amountIn >= $soTienCanThu) {
                    $found = true;
                    $matchedTx = $tx;
                    break;
                }
            }
        }

        if ($found) {
            // Khớp tiền thật 100% -> Lập tức cập nhật Hoàn Tất!
            $this->repo->updateStatus($tt['MaThanhToan'], 'HoanTat', date('Y-m-d H:i:s'));
            return [
                'success' => true,
                'is_paid' => true,
                'message' => "🎉 Ngân hàng MBBank xác nhận đã nhận đủ " . number_format($matchedTx['amount_in'], 0, ',', '.') . " đ cho hóa đơn '{$maHD}'!",
                'transaction' => $matchedTx
            ];
        }

        // Nếu chuyển sai nội dung hoặc chưa có tiền -> Không có gì xảy ra
        return [
            'success' => true,
            'is_paid' => false,
            'has_api_key' => true,
            'message' => 'Đang chờ khách chuyển tiền vào tài khoản MBBank...'
        ];
    }

    /**
     * Webhook đối soát nghiêm ngặt từ Cổng Ngân Hàng Napas 247:
     * - Sai nội dung: Bỏ qua / Từ chối (không có gì xảy ra).
     * - Chuyển thiếu tiền: Từ chối.
     */
    public function xuLyWebhook(array $payload): array
    {
        $content = strtoupper(trim($payload['content'] ?? ($payload['description'] ?? ($payload['transaction_content'] ?? ''))));
        $soTienChuyen = (float)($payload['amount'] ?? ($payload['amount_in'] ?? 0));

        if (empty($content)) {
            return [
                'success' => false,
                'error' => 'Nội dung chuyển khoản trống. Không tiếp nhận giao dịch!'
            ];
        }

        // 1. Phải chứa mã hóa đơn
        if (!preg_match('/(HD\d+|HD_[A-Z0-9]+)/i', $content, $matches)) {
            return [
                'success' => false,
                'error' => "Nội dung chuyển khoản '{$content}' không chứa mã hóa đơn. Không có gì xảy ra!"
            ];
        }

        $maHD = strtoupper($matches[1]);
        $tt = $this->repo->findByMaHD($maHD);
        if (!$tt) {
            return [
                'success' => false,
                'error' => "Không tìm thấy hóa đơn '{$maHD}' trong hệ thống."
            ];
        }

        if ($tt['TrangThaiThanhToan'] === 'HoanTat') {
            return ['success' => true, 'message' => "Hóa đơn '{$maHD}' đã thanh toán hoàn tất trước đó."];
        }

        if ($tt['TrangThaiThanhToan'] === 'DaHuy' || $tt['TrangThaiThanhToan'] === 'DaHetHan') {
            return [
                'success' => false,
                'error' => "Hóa đơn '{$maHD}' đã bị hủy hoặc hết hạn thanh toán!"
            ];
        }

        // 2. Kiểm tra số tiền
        $soTienYeuCau = (float)$tt['SoTien'];
        if ($soTienChuyen < $soTienYeuCau) {
            return [
                'success' => false,
                'error' => "Số tiền chuyển (" . number_format($soTienChuyen, 0, ',', '.') . " đ) ít hơn số tiền hóa đơn (" . number_format($soTienYeuCau, 0, ',', '.') . " đ). Không tiếp nhận!"
            ];
        }

        // 3. Khớp chuẩn xác -> Hoàn tất!
        $this->repo->updateStatus($tt['MaThanhToan'], 'HoanTat', date('Y-m-d H:i:s'));

        return [
            'success' => true,
            'message' => "Xác nhận thanh toán thành công cho hóa đơn '{$maHD}' qua MBBank!",
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
     * Quy trình Hoàn Tiền (Mô hình 1: Điền Form STK nhận lại tiền).
     */
    public function yeuCauHoanTien(array $params): array
    {
        $maHD = trim($params['MaHD'] ?? '');
        $nganHangHoan = trim($params['NganHangHoan'] ?? '');
        $stkHoan = trim($params['STKHoan'] ?? '');
        $tenChuTKHoan = strtoupper(trim($params['TenChuTKHoan'] ?? ''));
        $lyDoHoan = trim($params['LyDoHoan'] ?? 'Khách yêu cầu hủy phòng');

        if (empty($maHD) || empty($nganHangHoan) || empty($stkHoan) || empty($tenChuTKHoan)) {
            return ['success' => false, 'error' => 'Vui lòng điền đầy đủ Ngân hàng, Số tài khoản và Tên chủ tài khoản nhận tiền hoàn.'];
        }

        $tt = $this->repo->findByMaHD($maHD);
        if (!$tt) {
            return ['success' => false, 'error' => "Không tìm thấy giao dịch của hóa đơn '{$maHD}'."];
        }

        if ($tt['TrangThaiThanhToan'] !== 'HoanTat') {
            return ['success' => false, 'error' => "Hóa đơn '{$maHD}' chưa thanh toán hoàn tất, không thể hoàn tiền."];
        }

        $soTienGoc = (float)$tt['SoTien'];
        $tiLeHoan = 1.0;
        $chinhSachGiaiThich = 'Hủy trước 24h: Hoàn 100% tiền';

        $hoaDon = $this->hoaDonRepo->findHoaDonById($maHD);
        $pnp = $this->hoaDonRepo->getPhieuNhanPhongFull($hoaDon['MaPhieu']);
        if ($pnp && !empty($pnp['DanhSachPhong'][0]['ThoiGianNhanPhong'])) {
            $gioNhanPhong = strtotime($pnp['DanhSachPhong'][0]['ThoiGianNhanPhong']);
            $soGioConLai = ($gioNhanPhong - time()) / 3600;

            if ($soGioConLai < 24 && $soGioConLai > 0) {
                $tiLeHoan = 0.70;
                $chinhSachGiaiThich = 'Hủy trong vòng 24h trước giờ nhận phòng: Khấu trừ 30% phí hủy';
            } elseif ($soGioConLai <= 0) {
                $tiLeHoan = 0.50;
                $chinhSachGiaiThich = 'Hủy sau giờ nhận phòng quy định: Khấu trừ 50% phí hủy';
            }
        }

        $soTienHoan = round($soTienGoc * $tiLeHoan);
        $phiHuy = $soTienGoc - $soTienHoan;

        $this->repo->saveRefund($tt['MaThanhToan'], [
            'SoTienHoan' => $soTienHoan,
            'NganHangHoan' => $nganHangHoan,
            'STKHoan' => $stkHoan,
            'TenChuTKHoan' => $tenChuTKHoan,
            'LyDoHoan' => $lyDoHoan . " ({$chinhSachGiaiThich})",
            'ThoiGianHoan' => date('Y-m-d H:i:s'),
        ]);

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
