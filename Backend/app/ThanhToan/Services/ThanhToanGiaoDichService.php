<?php

declare(strict_types=1);

namespace App\ThanhToan\Services;

use App\ThanhToan\Repositories\ThanhToanGiaoDichRepository;
use App\HoaDon\Repositories\HoaDonRepository;
use App\KhachHang\Repositories\KhachHangRepository;
use App\LeTan\Services\LeTanPhieuService;
use Database\Database;
use PDO;
use Shared\ChinhSachHoanHuy;

/**
 * Service chuyên trách quản lý giao dịch thanh toán (VietQR Napas 247 MBBank),
 * tra cứu, hủy thanh toán chủ động và quy trình hoàn tiền.
 */
class ThanhToanGiaoDichService
{
    private ThanhToanGiaoDichRepository $repo;
    private HoaDonRepository $hoaDonRepo;
    private ?ThanhToanDoiSoatService $doiSoatService;
    private ?LeTanPhieuService $leTanPhieuService;

    // Cấu hình tài khoản MBBank chính thức của khách sạn
    public const BANK_ID = 'MB';
    public const BANK_NAME = 'MBBank (Ngân hàng Quân Đội)';
    public const ACCOUNT_NO = '0589822346';
    public const ACCOUNT_NAME = 'NGUYEN HOANG ANH';
    public const QR_EXPIRE_SECONDS = 1200; // Thời hạn mã QR: 20 phút (1200 giây)

    public function __construct(
        ?ThanhToanGiaoDichRepository $repo = null,
        ?HoaDonRepository $hoaDonRepo = null,
        ?ThanhToanDoiSoatService $doiSoatService = null,
        ?LeTanPhieuService $leTanPhieuService = null
    ) {
        $this->repo = $repo ?? new ThanhToanGiaoDichRepository();
        $this->hoaDonRepo = $hoaDonRepo ?? new HoaDonRepository();
        $this->doiSoatService = $doiSoatService;
        $this->leTanPhieuService = $leTanPhieuService;
    }

    public function setDoiSoatService(ThanhToanDoiSoatService $doiSoatService): void
    {
        $this->doiSoatService = $doiSoatService;
    }

    /**
     * Tạo giao dịch thanh toán cho hóa đơn (sinh mã VietQR Napas 247).
     */
    public function taoThanhToan(array $params): array
    {
        $maHD = trim($params['MaHD'] ?? ($params['maHD'] ?? ($params['ma_hd'] ?? '')));
        $hinhThuc = $params['HinhThuc'] ?? ($params['hinhThuc'] ?? 'ChuyenKhoan');

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
     * Tra cứu trạng thái giao dịch & Tự động kiểm tra hết hạn 20 phút.
     */
    public function xemThanhToan(array $params): array
    {
        $maHD = trim($params['MaHD'] ?? ($params['maHD'] ?? ($params['ma_hd'] ?? '')));
        $maTT = trim($params['MaThanhToan'] ?? ($params['maThanhToan'] ?? ($params['maTT'] ?? '')));

        $tt = null;
        if (!empty($maTT)) {
            $tt = $this->repo->findById($maTT);
        } elseif (!empty($maHD)) {
            $tt = $this->repo->findByMaHD($maHD);
        }

        if (!$tt) {
            return ['success' => false, 'error' => 'Chưa có thông tin giao dịch thanh toán.'];
        }

        // Kiểm tra thời hạn 20 phút (1200 giây)
        $thoiGianTao = strtotime($tt['ThoiGianThanhToan']);
        $thoiGianHetHan = $thoiGianTao + self::QR_EXPIRE_SECONDS;
        $conLaiGiay = max(0, $thoiGianHetHan - time());
        $isExpired = false;

        // Nếu quá 20 phút mà chưa thanh toán -> Tự động đánh dấu Hết Hạn & Nhả phòng về Trống
        if ($conLaiGiay <= 0 && $tt['TrangThaiThanhToan'] === 'ChoThanhToan') {
            $isExpired = true;
            if ($this->doiSoatService) {
                $this->doiSoatService->xuLyHetHanThanhToan($tt['MaHD'], 'Quá hạn thanh toán 20 phút');
            }
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
        $maHD = trim($params['MaHD'] ?? ($params['maHD'] ?? ($params['ma_hd'] ?? '')));
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

        // Hủy phiếu, chi tiết phiếu và nhả phòng về Trống
        if ($this->doiSoatService) {
            $this->doiSoatService->xuLyHetHanThanhToan($maHD, 'Khách hủy thanh toán');
        }
        $this->repo->updateStatus($tt['MaThanhToan'], 'DaHuy', date('Y-m-d H:i:s'));

        return [
            'success' => true,
            'message' => "Đã hủy giao dịch thanh toán của hóa đơn '{$maHD}' và nhả phòng về trạng thái Trống thành công!",
            'data' => [
                'MaHD' => $maHD,
                'MaThanhToan' => $tt['MaThanhToan'],
                'TrangThaiThanhToan' => 'DaHuy'
            ]
        ];
    }

    /**
     * Quy trình Hoàn Tiền & Hủy phòng hợp nhất (gọi chung quy tắc Shared\ChinhSachHoanHuy qua LeTanPhieuService).
     */
    public function yeuCauHoanTien(array $params): array
    {
        $maHD = trim((string)($params['MaHD'] ?? ($params['maHD'] ?? ($params['ma_hd'] ?? ''))));
        $maPhong = trim((string)($params['MaPhong'] ?? ($params['maPhong'] ?? '')));
        $nganHangHoan = trim((string)($params['NganHangHoan'] ?? ($params['nganHangHoan'] ?? '')));
        $stkHoan = trim((string)($params['STKHoan'] ?? ($params['stkHoan'] ?? '')));
        $tenChuTKHoan = strtoupper(trim((string)($params['TenChuTKHoan'] ?? ($params['tenChuTKHoan'] ?? ''))));
        $lyDoHoan = trim((string)($params['LyDoHoan'] ?? ($params['lyDoHoan'] ?? 'Khách yêu cầu hủy phòng')));

        if (empty($maHD) || empty($nganHangHoan) || empty($stkHoan) || empty($tenChuTKHoan)) {
            return ['success' => false, 'error' => 'Vui lòng điền đầy đủ Ngân hàng, Số tài khoản và Tên chủ tài khoản nhận tiền hoàn.'];
        }

        $tt = $this->repo->findByMaHD($maHD);
        if (!$tt) {
            return ['success' => false, 'error' => "Không tìm thấy giao dịch của hóa đơn '{$maHD}'."];
        }

        if (!in_array($tt['TrangThaiThanhToan'], [ChinhSachHoanHuy::TT_HOAN_TAT, ChinhSachHoanHuy::TT_HOAN_MOT_PHAN], true)) {
            return ['success' => false, 'error' => "Hóa đơn '{$maHD}' chưa thanh toán hoàn tất hoặc đã hoàn đủ, không thể hoàn tiền."];
        }

        $hoaDon = $this->hoaDonRepo->findHoaDonById($maHD);
        if (!$hoaDon || empty($hoaDon['MaPhieu'])) {
            return ['success' => false, 'error' => "Không tìm thấy phiếu đặt phòng gắn với hóa đơn '{$maHD}'."];
        }

        $cancelParams = [
            'maPhieu' => $hoaDon['MaPhieu'],
            'maPhong' => $maPhong,
            'lyDo' => $lyDoHoan,
            'nganHangHoan' => $nganHangHoan,
            'stkHoan' => $stkHoan,
            'tenChuTKHoan' => $tenChuTKHoan,
        ];

        $maTK = trim((string)($params['maTK'] ?? ($params['MaTK'] ?? '')));
        if ($maTK !== '') {
            $khach = (new KhachHangRepository(new Database()))->findByMaTK($maTK);
            if ($khach && !empty($khach['MaKH'])) {
                $cancelParams['actor'] = 'khachHang';
                $cancelParams['maKH'] = $khach['MaKH'];
            } else {
                $cancelParams['maTK'] = $maTK;
            }
        }
        if (!empty($params['maLeTan']) || !empty($params['MaLeTan'])) {
            $cancelParams['maLeTan'] = $params['maLeTan'] ?? $params['MaLeTan'];
        }

        try {
            $phieuService = $this->leTanPhieuService ?? new LeTanPhieuService();
            $cancelRes = $phieuService->huyPhieu($cancelParams);
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }

        $hoanInfo = $cancelRes['data']['hoanTien'] ?? [];
        $soTienGoc = (float)$tt['SoTien'];
        $soTienHoan = (float)($hoanInfo['soTienHoan'] ?? 0);
        $phiHuy = (float)($hoanInfo['phiHuy'] ?? 0);
        $chinhSachGiaiThich = $hoanInfo['moTaChinhSach'] ?? '';

        $vietQrHoanUrl = null;
        if ($soTienHoan > 0) {
            $vietQrHoanUrl = "https://img.vietqr.io/image/{$nganHangHoan}-{$stkHoan}-compact2.png"
                . "?amount=" . (int)$soTienHoan
                . "&addInfo=" . urlencode("HOAN TIEN " . $maHD)
                . "&accountName=" . urlencode($tenChuTKHoan);
        }

        return [
            'success' => true,
            'message' => $cancelRes['message'] ?? "Đã xử lý hủy & hoàn tiền cho hóa đơn '{$maHD}' thành công!",
            'data' => [
                'MaHD' => $maHD,
                'MaPhieu' => $hoaDon['MaPhieu'],
                'MaThanhToan' => $tt['MaThanhToan'],
                'SoTienGoc' => $soTienGoc,
                'PhiHuy' => $phiHuy,
                'SoTienHoan' => $soTienHoan,
                'TiLeHoan' => $hoanInfo['tiLeHoan'] ?? 0,
                'ChinhSach' => $chinhSachGiaiThich,
                'TaiKhoanNhanHoan' => [
                    'NganHang' => $nganHangHoan,
                    'SoTaiKhoan' => $stkHoan,
                    'ChuTaiKhoan' => $tenChuTKHoan,
                ],
                'VietQrHoanUrl' => $vietQrHoanUrl,
                'ThoiGianHoan' => date('Y-m-d H:i:s'),
                'HoanTienChiTiet' => $hoanInfo,
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
