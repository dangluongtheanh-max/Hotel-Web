<?php

declare(strict_types=1);

namespace App\ThanhToan\Services;

use App\ThanhToan\Repositories\ThanhToanGiaoDichRepository;
use App\ThanhToan\Repositories\ThanhToanDoiSoatRepository;
use Database\Database;
use PDO;

/**
 * Service chuyên trách đối soát ngân hàng thật MBBank Napas 247 qua SePay API / Webhook,
 * đồng bộ trạng thái đặt phòng thành công, tự động hủy và lazy cleanup khi quá hạn 20 phút.
 */
class ThanhToanDoiSoatService
{
    private ThanhToanGiaoDichRepository $giaoDichRepo;
    private ThanhToanDoiSoatRepository $doiSoatRepo;

    public function __construct(
        ?ThanhToanGiaoDichRepository $giaoDichRepo = null,
        ?ThanhToanDoiSoatRepository $doiSoatRepo = null
    ) {
        $this->giaoDichRepo = $giaoDichRepo ?? new ThanhToanGiaoDichRepository();
        $this->doiSoatRepo = $doiSoatRepo ?? new ThanhToanDoiSoatRepository();
    }

    /**
     * Đồng bộ biến động số dư Ngân Hàng Thật (MBBank qua SePay API):
     * Gọi trực tiếp lên ngân hàng thật để tìm giao dịch khớp đúng mã hóa đơn.
     * - Nếu chuyển sai nội dung: Bỏ qua (không có gì xảy ra).
     * - Nếu có tiền vào khớp đúng MaHD và đủ số tiền: LẬP TỨC CHUYỂN TRẠNG THÁI THÀNH CÔNG!
     */
    public function dongBoNganHangThat(string $maHD, ?string $customApiKey = null): array
    {
        $tt = $this->giaoDichRepo->findByMaHD($maHD);
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
            return [
                'success' => false,
                'is_paid' => false,
                'has_api_key' => false,
                'message' => 'Chưa cấu hình SePay API Key để kết nối biến động số dư ngân hàng thật.'
            ];
        }

        // Gọi API SePay kiểm tra danh sách giao dịch MBBank gần nhất
        $url = "https://my.sepay.vn/userapi/transactions/list?account_number=" . ThanhToanGiaoDichService::ACCOUNT_NO . "&limit=20";
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
            $this->giaoDichRepo->updateStatus($tt['MaThanhToan'], 'HoanTat', date('Y-m-d H:i:s'));
            $this->capNhatHoanTatDatPhong($maHD);
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
        $soTienChuyen = (float)($payload['transferAmount'] ?? ($payload['amount'] ?? ($payload['amount_in'] ?? 0)));

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
        $tt = $this->giaoDichRepo->findByMaHD($maHD);
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
        $this->giaoDichRepo->updateStatus($tt['MaThanhToan'], 'HoanTat', date('Y-m-d H:i:s'));
        $this->capNhatHoanTatDatPhong($maHD);

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
     * Cập nhật hoàn tất đặt phòng khi thanh toán thành công (Phiếu -> DaDatPhong, Phòng -> DaDat).
     */
    public function capNhatHoanTatDatPhong(string $maHD, ?PDO $db = null): void
    {
        $this->doiSoatRepo->capNhatHoanTatDatPhong($maHD, $db);
    }

    /**
     * Xử lý hết hạn hoặc hủy thanh toán cho một hóa đơn:
     * - Thanh toán -> DaHetHan
     * - Phiếu nhận phòng -> ThanhToanThatBai
     * - Chi tiết phiếu -> ThanhToanThatBai
     * - Phòng -> Trong (nhả lại trạng thái trống)
     */
    public function xuLyHetHanThanhToan(string $maHD, string $lyDo = 'Quá hạn thanh toán 20 phút', ?PDO $db = null): void
    {
        $this->doiSoatRepo->xuLyHetHanThanhToan($maHD, $lyDo, $db);
    }

    /**
     * Cơ chế Lazy Cleanup: Quét và giải phóng toàn bộ các phiếu ChoThanhToan đã quá 20 phút.
     */
    public function quetVaXuLyTatCaPhieuHetHan(?PDO $db = null): int
    {
        $db = $db ?? Database::getConnection();
        $rows = $this->doiSoatRepo->quetDanhSachPhieuQuaHan($db);
        $count = 0;
        foreach ($rows as $maHD) {
            $this->xuLyHetHanThanhToan($maHD, 'Quá hạn thanh toán 20 phút', $db);
            $count++;
        }
        return $count;
    }
}
