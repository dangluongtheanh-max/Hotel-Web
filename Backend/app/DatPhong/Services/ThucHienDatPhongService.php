<?php

declare(strict_types=1);

namespace App\DatPhong\Services;

use App\DatPhong\Repositories\DatPhongRepository;
use App\HoaDon\Repositories\HoaDonRepository;
use App\KhachHang\Repositories\KhachHangRepository;
use App\Phong\Services\PhongService;
use App\ThanhToan\Repositories\ThanhToanRepository;
use Database\Database;
use Exception;
use PDO;

/**
 * Service chuyên trách xử lý nghiệp vụ Đặt phòng và Thanh toán (thực thi Transaction).
 */
class ThucHienDatPhongService
{
    private DatPhongRepository $repository;
    private PhongService $phongService;
    private ChiPhiDatPhongService $chiPhiService;
    private NguoiLuuTruValidator $validator;
    private KhachHangRepository $khachHangRepo;
    private HoaDonRepository $hoaDonRepo;
    private ThanhToanRepository $thanhToanRepo;

    public function __construct(
        DatPhongRepository $repository,
        PhongService $phongService,
        ?ChiPhiDatPhongService $chiPhiService = null,
        ?NguoiLuuTruValidator $validator = null,
        ?KhachHangRepository $khachHangRepo = null,
        ?HoaDonRepository $hoaDonRepo = null,
        ?ThanhToanRepository $thanhToanRepo = null
    ) {
        $this->repository = $repository;
        $this->phongService = $phongService;
        $this->chiPhiService = $chiPhiService ?? new ChiPhiDatPhongService($phongService);
        $this->validator = $validator ?? new NguoiLuuTruValidator();
        $this->khachHangRepo = $khachHangRepo ?? new KhachHangRepository(new Database());
        $this->hoaDonRepo = $hoaDonRepo ?? new HoaDonRepository();
        $this->thanhToanRepo = $thanhToanRepo ?? new ThanhToanRepository();
    }

    /**
     * Xác nhận Đặt phòng và Thanh toán (Hoàn tất giao dịch trong Database Transaction)
     */
    public function datPhongVaThanhToan(array $params = []): array
    {
        $maTK = trim($params['maTK'] ?? '');
        $ngayNhan = trim($params['ngayNhan'] ?? '');
        $ngayTra = trim($params['ngayTra'] ?? '');
        $soNguoiLon = (int) ($params['soNguoiLon'] ?? 1);
        $soTreEm = (int) ($params['soTreEm'] ?? 0);
        $soPhong = (int) ($params['soPhong'] ?? 1);
        $phongDaChon = $params['phongDaChon'] ?? [];
        $nguoiLuuTruTheoPhong = $params['nguoiLuuTruTheoPhong'] ?? [];
        $maKM = trim($params['maKM'] ?? '');
        $dichVuDaChon = $params['dichVuDaChon'] ?? [];
        $kenhDat = trim($params['kenhDat'] ?? 'TrucTuyen');
        $maLeTan = !empty($params['maLeTan']) ? trim($params['maLeTan']) : null;
        $hinhThuc = trim($params['hinhThuc'] ?? 'ChuyenKhoan');
        $sdtNguoiDat = trim($params['sdtNguoiDat'] ?? ($params['sdt'] ?? ($params['SDT'] ?? '')));
        $tenNguoiDat = trim($params['tenNguoiDat'] ?? ($params['hoTenNguoiDat'] ?? ''));
        $maKHParam = trim($params['maKH'] ?? ($params['MaKH'] ?? ''));

        // 1. Kiểm tra tài khoản khách hàng hoặc nhận diện Lễ tân đặt phòng tại quầy
        $khachHang = null;
        $maKH = null;
        $tenKH = '';

        if ($maTK !== '') {
            $khachHang = $this->khachHangRepo->findByMaTK($maTK);
            if ($khachHang) {
                $maKH = $khachHang['MaKH'];
                $tenKH = $khachHang['HoTen'];
            } else {
                // Kiểm tra xem đây có phải tài khoản của Lễ tân không
                $dbConn = Database::getConnection();
                $stmtLT = $dbConn->prepare("SELECT MaLeTan, HoTen FROM LeTan WHERE MaTK = ?");
                $stmtLT->execute([$maTK]);
                $rowLT = $stmtLT->fetch(PDO::FETCH_ASSOC);
                if ($rowLT) {
                    $kenhDat = 'TrucTiep';
                    $maLeTan = $rowLT['MaLeTan'];
                }
            }
        }

        // Nếu là đặt phòng tại quầy (hoặc tài khoản Lễ tân đặt hộ)
        if ($kenhDat === 'TrucTiep' || !empty($maLeTan)) {
            $kenhDat = 'TrucTiep';
            if (empty($maLeTan)) {
                $maLeTan = 'LT001';
            }

            // 1.1 Nếu lễ tân chỉ định trực tiếp MaKH (đã tra cứu trước đó)
            if (!empty($maKHParam)) {
                $khExisted = $this->khachHangRepo->findById($maKHParam);
                if ($khExisted) {
                    $maKH = $khExisted['MaKH'];
                    $tenKH = $khExisted['HoTen'];
                }
            }

            // 1.2 Nếu có số điện thoại người đặt, kiểm tra tính hợp lệ của SĐT
            if (!empty($sdtNguoiDat)) {
                $this->validator->kiemTraSoDienThoai($sdtNguoiDat, "Người đặt phòng");
            }

            // 1.3 Nếu chưa có $maKH nhưng có số điện thoại người đặt -> Tra cứu theo SDT
            if (!$maKH && !empty($sdtNguoiDat)) {
                $khByPhone = $this->khachHangRepo->findBySDT($sdtNguoiDat);
                if ($khByPhone) {
                    $maKH = $khByPhone['MaKH'];
                    $tenKH = $khByPhone['HoTen'];
                } else {
                    // Khách hàng mới với SĐT này -> Tạo hồ sơ KhachHang mới
                    $tenNguoiDaiDien = !empty($tenNguoiDat) ? $tenNguoiDat : 'Khách đặt tại quầy';
                    if ($tenNguoiDaiDien === 'Khách đặt tại quầy' && !empty($nguoiLuuTruTheoPhong) && !empty($nguoiLuuTruTheoPhong[0]['khach']) && !empty($nguoiLuuTruTheoPhong[0]['khach'][0]['hoTen'])) {
                        $tenNguoiDaiDien = trim($nguoiLuuTruTheoPhong[0]['khach'][0]['hoTen']);
                    }

                    $newMaKH = $this->khachHangRepo->generateMaKH();
                    $this->khachHangRepo->themKhachHang($newMaKH, $tenNguoiDaiDien, $sdtNguoiDat, null);
                    $maKH = $newMaKH;
                    $tenKH = $tenNguoiDaiDien;
                }
            }

            // 1.4 Nếu vẫn chưa có $maKH (không nhập SĐT) -> Tạo hồ sơ khách vãng lai mới
            if (!$maKH) {
                $tenNguoiDaiDien = !empty($tenNguoiDat) ? $tenNguoiDat : 'Khách đặt tại quầy';
                if ($tenNguoiDaiDien === 'Khách đặt tại quầy' && !empty($nguoiLuuTruTheoPhong) && !empty($nguoiLuuTruTheoPhong[0]['khach']) && !empty($nguoiLuuTruTheoPhong[0]['khach'][0]['hoTen'])) {
                    $tenNguoiDaiDien = trim($nguoiLuuTruTheoPhong[0]['khach'][0]['hoTen']);
                }

                $newMaKH = $this->khachHangRepo->generateMaKH();
                $this->khachHangRepo->themKhachHang($newMaKH, $tenNguoiDaiDien, null, null);
                $maKH = $newMaKH;
                $tenKH = $tenNguoiDaiDien;
            }
        } else {
            // Đặt phòng trực tuyến (KenhDat = 'TrucTuyen')
            if (!$khachHang) {
                throw new Exception("Bạn cần đăng nhập tài khoản khách hàng để thực hiện đặt phòng trực tuyến.");
            }
            $maLeTan = null;
        }

        // 2. Tính toán lại chi phí chính xác qua ChiPhiDatPhongService
        $bangChiPhi = $this->chiPhiService->tinhChiPhiTamTinh([
            'ngayNhan' => $ngayNhan,
            'ngayTra' => $ngayTra,
            'soNguoiLon' => $soNguoiLon,
            'soTreEm' => $soTreEm,
            'soPhong' => $soPhong,
            'phongDaChon' => $phongDaChon,
            'maKM' => $maKM,
            'maKH' => $maKH,
            'maTK' => $maTK,
            'sdt' => $sdtNguoiDat,
            'dichVuDaChon' => $dichVuDaChon,
        ]);

        // 3. Phân bổ phòng và kiểm tra người lưu trú qua NguoiLuuTruValidator
        $danhSachPhongGan = $this->validator->kiemTraVaPhanBoPhong(
            $phongDaChon,
            $nguoiLuuTruTheoPhong,
            $ngayNhan,
            $ngayTra,
            $soNguoiLon,
            $soTreEm,
            $this->phongService
        );

        // 4. Thực thi Database Transaction qua Repository
        try {
            return $this->repository->transaction(function (PDO $db) use (
                $bangChiPhi,
                $danhSachPhongGan,
                $tenKH,
                $maKH,
                $ngayNhan,
                $ngayTra,
                $maKM,
                $hinhThuc,
                $kenhDat,
                $maLeTan
            ) {
                $ngayLap = date('Y-m-d');
                $maPhieu = $this->repository->generateMaPhieu($db);
                $maKMToSave = ($bangChiPhi['tienGiamKM'] > 0) ? $maKM : null;

                // Kiểm tra bảo mật khuyến mãi khách mới: chỉ áp dụng 1 lần duy nhất trong lần đặt phòng đầu tiên
                if (!empty($maKMToSave)) {
                    $isNewCustomerPromo = (strtoupper($maKMToSave) === 'KM001');
                    if (!$isNewCustomerPromo) {
                        $stmtCheckName = $db->prepare("SELECT TenKM FROM KhuyenMai WHERE MaKM = ?");
                        $stmtCheckName->execute([$maKMToSave]);
                        $kmName = (string)$stmtCheckName->fetchColumn();
                        $isNewCustomerPromo = (mb_stripos($kmName, 'khách mới') !== false 
                            || mb_stripos($kmName, 'khach hang moi') !== false 
                            || mb_stripos($kmName, 'khach moi') !== false
                            || mb_stripos($kmName, 'lần đầu') !== false);
                    }

                    if ($isNewCustomerPromo && !empty($maKH)) {
                        $stmtCheckFirst = $db->prepare("SELECT COUNT(*) FROM PhieuNhanPhong WHERE MaKH = ? AND TrangThai != 'DaHuy'");
                        $stmtCheckFirst->execute([$maKH]);
                        $priorBookings = (int)$stmtCheckFirst->fetchColumn();

                        $stmtCheckUsed = $db->prepare(
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
                        $stmtCheckUsed->execute([$maKH]);
                        $priorUsed = (int)$stmtCheckUsed->fetchColumn();

                        if ($priorBookings > 0 || $priorUsed > 0) {
                            throw new Exception("Mã khuyến mãi '{$maKMToSave}' chỉ áp dụng 1 lần duy nhất cho khách hàng mới trong lần đặt phòng đầu tiên. Tài khoản của bạn không thể sử dụng mã này.");
                        }
                    }
                }

                $kenhDatToSave = ($kenhDat === 'TrucTiep') ? 'TrucTiep' : 'TrucTuyen';
                $maLeTanToSave = ($kenhDatToSave === 'TrucTiep') ? ($maLeTan ?: 'LT001') : null;

                $hinhThucTT = ($hinhThuc === 'TienMat') ? 'TienMat' : 'ChuyenKhoan';
                $isCash = ($hinhThucTT === 'TienMat');

                // Nếu tiền mặt: hoàn tất ngay DaDatPhong. Nếu chuyển khoản: giữ phòng 20 phút ChoThanhToan
                $trangThaiPhieu = $isCash ? 'DaDatPhong' : 'ChoThanhToan';
                $trangThaiTT = $isCash ? 'HoanTat' : 'ChoThanhToan';
                $trangThaiPhong = $isCash ? 'DaDat' : 'ChoThanhToan';
                $thoiGianHetHan = $isCash ? null : date('Y-m-d H:i:s', time() + 1200);

                // Lưu Phiếu nhận phòng
                $this->repository->luuPhieuNhanPhong(
                    $maPhieu,
                    $ngayLap,
                    $kenhDatToSave,
                    $trangThaiPhieu,
                    $maKH,
                    $maLeTanToSave,
                    $maKMToSave,
                    $thoiGianHetHan,
                    $db
                );

                // Tăng số lượng đã dùng của khuyến mãi
                if (!empty($maKMToSave)) {
                    $stmtTangKM = $db->prepare("UPDATE KhuyenMai SET SoLuongDaDung = SoLuongDaDung + 1 WHERE MaKM = ? AND SoLuongDaDung < SoLuong");
                    $stmtTangKM->execute([$maKMToSave]);
                }

                $thoiGianNhanPhong = (strpos($ngayNhan, ' ') !== false) ? $ngayNhan : ($ngayNhan . ' 14:00:00');
                $thoiGianTraPhong = (strpos($ngayTra, ' ') !== false) ? $ngayTra : ($ngayTra . ' 12:00:00');

                // Lưu Chi tiết phiếu nhận phòng & Người lưu trú & Khóa trạng thái phòng
                $danhSachNLTDaLuu = [];
                foreach ($danhSachPhongGan as $p) {
                    $this->repository->luuChiTietPhieuNhanPhong(
                        $maPhieu,
                        $p['maPhong'],
                        $thoiGianNhanPhong,
                        $thoiGianTraPhong,
                        $p['soNguoi'],
                        $trangThaiPhieu,
                        $db
                    );

                    // Cập nhật trạng thái phòng sang ChoThanhToan (hoặc DaDat nếu trả tiền mặt)
                    $this->phongService->capNhatTrangThaiPhong($p['maPhong'], $trangThaiPhong, $db);

                    foreach ($p['khach'] as $g) {
                        $maNLT = $this->repository->generateMaNguoiLuuTru($db);
                        $hoTen = trim($g['hoTen'] ?? 'Khách lưu trú');
                        $ngaySinh = trim($g['ngaySinh'] ?? '2000-01-01');
                        $quocTich = trim($g['quocTich'] ?? 'Việt Nam');
                        $loaiGiayTo = trim($g['loaiGiayTo'] ?? 'CCCD');
                        $soGiayTo = trim($g['soGiayTo'] ?? '000000000000');
                        $ghiChu = isset($g['ghiChu']) ? trim($g['ghiChu']) : null;
                        if ($ghiChu === '') {
                            $ghiChu = null;
                        }

                        $this->repository->luuNguoiLuuTru(
                            $maNLT,
                            $hoTen,
                            $ngaySinh,
                            $quocTich,
                            $loaiGiayTo,
                            $soGiayTo,
                            $ghiChu,
                            $maPhieu,
                            $p['maPhong'],
                            $db
                        );

                        $calculatedAge = $this->validator->tinhTuoi($ngaySinh, $ngayNhan);
                        $calculatedGroup = ($calculatedAge <= 11) ? 'Trẻ em' : 'Người lớn';

                        $danhSachNLTDaLuu[] = [
                            'maNguoiLuuTru' => $maNLT,
                            'hoTen' => $hoTen,
                            'ngaySinh' => $ngaySinh,
                            'tuoi' => $calculatedAge,
                            'nhomTuoi' => $calculatedGroup,
                            'quocTich' => $quocTich,
                            'loaiGiayTo' => $loaiGiayTo,
                            'soGiayTo' => $soGiayTo,
                            'ghiChu' => $ghiChu,
                            'maPhong' => $p['maPhong'],
                            'tenLoaiPhong' => $p['tenLoaiPhong'],
                        ];
                    }
                }

                // Tự động lưu ngược thông tin định danh mới vào bảng KhachHang nếu trước đó tài khoản chưa có
                if (!empty($maKH) && !empty($danhSachNLTDaLuu)) {
                    $matchedGuest = null;
                    foreach ($danhSachNLTDaLuu as $g) {
                        if (!empty($g['hoTen']) && !empty($tenKH) && mb_strtolower($g['hoTen'], 'UTF-8') === mb_strtolower($tenKH, 'UTF-8')) {
                            $matchedGuest = $g;
                            break;
                        }
                    }
                    if (!$matchedGuest && count($danhSachNLTDaLuu) > 0) {
                        $matchedGuest = $danhSachNLTDaLuu[0];
                    }

                    if ($matchedGuest && (!empty($matchedGuest['soGiayTo']) || !empty($matchedGuest['ngaySinh']))) {
                        $this->khachHangRepo->capNhatThongTinDinhDanh(
                            $maKH,
                            $matchedGuest['ngaySinh'] ?? null,
                            $matchedGuest['quocTich'] ?? 'Việt Nam',
                            $matchedGuest['loaiGiayTo'] ?? 'CCCD',
                            $matchedGuest['soGiayTo'] ?? null,
                            $db
                        );
                    }
                }

                // Lưu Dịch vụ sử dụng nếu có
                if (!empty($bangChiPhi['chiTietDichVu'])) {
                    $firstRoom = $danhSachPhongGan[0]['maPhong'];
                    foreach ($bangChiPhi['chiTietDichVu'] as $dv) {
                        $this->repository->luuCTSuDungDichVu(
                            $dv['maCTDV'],
                            $maPhieu,
                            $firstRoom,
                            (float) $dv['donGia'],
                            $dv['tenCTDV'] ?? '',
                            (int) $dv['soLuong'],
                            $db
                        );
                    }
                }

                // Lưu Hóa đơn đặt phòng vào bảng HoaDon & CTHD
                $maHD = $this->hoaDonRepo->generateMaHoaDon($db);
                $this->hoaDonRepo->luuHoaDon($maHD, $maPhieu, (float)$bangChiPhi['tongTienSauVAT'], $db);

                foreach ($danhSachPhongGan as $p) {
                    $this->hoaDonRepo->luuCTHD(
                        $maHD,
                        $maPhieu,
                        $p['maPhong'],
                        null,
                        (int)$bangChiPhi['soDem'],
                        1,
                        (float)($p['donGia'] ?? 0),
                        $db
                    );
                }

                // Lưu các dòng dịch vụ đi kèm vào CTHD của Hóa đơn (nếu có)
                if (!empty($bangChiPhi['chiTietDichVu'])) {
                    foreach ($bangChiPhi['chiTietDichVu'] as $dv) {
                        $this->hoaDonRepo->luuCTHD(
                            $maHD,
                            $maPhieu,
                            null,
                            $dv['maCTDV'],
                            0,
                            (int)$dv['soLuong'],
                            (float)$dv['donGia'],
                            $db
                        );
                    }
                }

                // Lưu bản ghi thanh toán
                $maTT = $this->thanhToanRepo->generateMaThanhToan($db);
                $this->thanhToanRepo->luuThanhToan(
                    $maTT,
                    (float)$bangChiPhi['tongTienSauVAT'],
                    date('Y-m-d H:i:s'),
                    $hinhThucTT,
                    $trangThaiTT,
                    $maHD,
                    $db
                );

                $noiDungCK = "THANHTOAN " . $maHD;
                $qrUrl = "https://img.vietqr.io/image/MB-0589822346-compact2.png"
                    . "?amount=" . rawurlencode((string)((int)$bangChiPhi['tongTienSauVAT']))
                    . "&addInfo=" . rawurlencode($noiDungCK)
                    . "&accountName=" . rawurlencode("NGUYEN HOANG ANH");

                $responseData = [
                    'maPhieu' => $maPhieu,
                    'maHD' => $maHD,
                    'maThanhToan' => $maTT,
                    'trangThaiPhieu' => $trangThaiPhieu,
                    'thoiGianHetHan' => $thoiGianHetHan,
                    'thoiHanGiay' => $isCash ? 0 : 1200,
                    'kenhDat' => $kenhDatToSave,
                    'maLeTan' => $maLeTanToSave,
                    'khachHang' => $tenKH,
                    'ngayNhan' => $ngayNhan,
                    'ngayTra' => $ngayTra,
                    'soDem' => $bangChiPhi['soDem'],
                    'maKM' => $maKMToSave,
                    'tongTien' => $bangChiPhi['tongTienSauVAT'],
                    'thanhToan' => [
                        'maThanhToan' => $maTT,
                        'hinhThuc' => $hinhThucTT,
                        'soTien' => $bangChiPhi['tongTienSauVAT'],
                        'trangThai' => $trangThaiTT,
                        'bankId' => 'MB',
                        'bankName' => 'MBBank (Ngân hàng Quân Đội)',
                        'accountNo' => '0589822346',
                        'accountName' => 'NGUYEN HOANG ANH',
                        'noiDung' => $noiDungCK,
                        'qrUrl' => ($hinhThucTT === 'ChuyenKhoan') ? $qrUrl : null,
                        'thoiHanGiay' => $isCash ? 0 : 1200
                    ],
                    'chiPhiDuKien' => [
                        'tienPhong' => $bangChiPhi['tienPhong'],
                        'tienGiamKM' => $bangChiPhi['tienGiamKM'],
                        'tienDichVu' => $bangChiPhi['tienDichVu'],
                        'tienTruocVAT' => $bangChiPhi['tienTruocVAT'],
                        'vat' => $bangChiPhi['vat'],
                        'tongTienSauVAT' => $bangChiPhi['tongTienSauVAT'],
                    ],
                    'danhSachPhong' => $danhSachPhongGan,
                    'danhSachNguoiLuuTru' => $danhSachNLTDaLuu,
                ];

                $msg = ($hinhThucTT === 'TienMat')
                    ? 'Đặt phòng và thanh toán tiền mặt thành công! Mã phiếu: #' . $maPhieu
                    : 'Đặt phòng và thanh toán chuyển khoản thành công! Mã phiếu: #' . $maPhieu;

                return array_merge($responseData, [
                    'success' => true,
                    'message' => $msg,
                    'data' => $responseData,
                ]);
            });
        } catch (Exception $e) {
            throw new Exception("Thao tác đặt phòng thất bại: " . $e->getMessage());
        }
    }
}
