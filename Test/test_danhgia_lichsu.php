<?php
require __DIR__ . '/../Backend/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../Backend');
$dotenv->load();

use App\DanhGia\Repositories\DanhGiaRepository;
use App\DanhGia\Services\DanhGiaService;
use App\DatPhong\Repositories\DatPhongRepository;
use App\DatPhong\Services\DatPhongService;
use App\KhachHang\Repositories\KhachHangRepository;
use App\Phong\Repositories\PhongRepository;
use App\Phong\Services\PhongService;
use Database\Database;

$db = Database::getConnection();

// 1. Khởi tạo các Service theo đúng kiến trúc
$danhGiaRepo = new DanhGiaRepository();
$khachHangRepo = new KhachHangRepository(new Database());
$danhGiaService = new DanhGiaService($danhGiaRepo, $khachHangRepo);

$phongRepo = new PhongRepository(new Database());
$phongService = new PhongService($phongRepo);
$datPhongRepo = new DatPhongRepository(new Database());
$datPhongService = new DatPhongService(
    $datPhongRepo,
    $phongService,
    null,
    null,
    null,
    $khachHangRepo,
    null,
    null,
    $danhGiaService
);

// Đảm bảo trạng thái ban đầu sạch trước khi test: P004 chưa đánh giá
$db->exec("DELETE FROM DanhGia WHERE MaPhieu = 'PP001' AND MaPhong = 'P004';");

echo "====================================================================\n";
echo "TEST 1: KIỂM TRA DỮ LIỆU ĐÁNH GIÁ TRONG XEM CHI TIẾT PHIẾU PP001\n";
echo "====================================================================\n";
$detail1 = $datPhongService->xemChiTietPhieu('PP001', 'TK003');
$phongList1 = $detail1['DanhSachPhong'];

echo "Phiếu PP001 có " . count($phongList1) . " phòng:\n";
foreach ($phongList1 as $p) {
    $daDG = $p['DaDanhGia'] ? 'TRUE (Đã đánh giá)' : 'FALSE (Chưa đánh giá)';
    $sao = $p['DanhGia'] ? $p['DanhGia']['SoSao'] . '★' : 'N/A';
    echo " - Phòng [{$p['MaPhong']}] Trạng thái: {$p['TrangThai']} | Đã đánh giá: {$daDG} | Số sao: {$sao}\n";
}

// Kiểm tra: P001 đã đánh giá, P004 chưa đánh giá
assert($phongList1[0]['MaPhong'] === 'P001' && $phongList1[0]['DaDanhGia'] === true, 'P001 phải có DaDanhGia = true');
assert($phongList1[1]['MaPhong'] === 'P004' && $phongList1[1]['DaDanhGia'] === false, 'P004 phải có DaDanhGia = false');
echo "=> KẾT QUẢ TEST 1: CHÍNH XÁC!\n\n";

echo "====================================================================\n";
echo "TEST 2: CHẶN ĐÁNH GIÁ PHÒNG CHƯA HOÀN TẤT CHECK-OUT (PP002 - DangLuuTru)\n";
echo "====================================================================\n";
$resChuaCheckout = $danhGiaService->themDanhGia([
    'maTK' => 'TK005', // KH002 sở hữu PP002
    'maPhieu' => 'PP002',
    'maPhong' => 'P002',
    'soSao' => 5,
    'noiDung' => 'Thử đánh giá khi đang lưu trú'
]);
echo "Kết quả: " . json_encode($resChuaCheckout, JSON_UNESCAPED_UNICODE) . "\n";
assert($resChuaCheckout['status'] === 'error' && $resChuaCheckout['code'] === 400, 'Lẽ ra phải chặn lỗi 400');
echo "=> KẾT QUẢ TEST 2: ĐÃ CHẶN ĐÚNG NGHIỆP VỤ CHECK-OUT!\n\n";

echo "====================================================================\n";
echo "TEST 3: CHẶN KHÁCH HÀNG A ĐÁNH GIÁ PHIẾU CỦA KHÁCH HÀNG B (BẢO MẬT)\n";
echo "====================================================================\n";
$resSaiChuSoHuu = $danhGiaService->themDanhGia([
    'maTK' => 'TK005', // TK005 cố đánh giá phiếu PP001 của TK003
    'maPhieu' => 'PP001',
    'maPhong' => 'P004',
    'soSao' => 5,
    'noiDung' => 'Hack đánh giá'
]);
echo "Kết quả: " . json_encode($resSaiChuSoHuu, JSON_UNESCAPED_UNICODE) . "\n";
assert($resSaiChuSoHuu['status'] === 'error' && $resSaiChuSoHuu['code'] === 403, 'Lẽ ra phải chặn lỗi 403');
echo "=> KẾT QUẢ TEST 3: ĐÃ CHẶN ĐÚNG BẢO MẬT PHÂN QUYỀN!\n\n";

echo "====================================================================\n";
echo "TEST 4: CHẶN ĐÁNH GIÁ TRÙNG LẶP (P001 CỦA PP001 ĐÃ ĐÁNH GIÁ TRƯỚC ĐÓ)\n";
echo "====================================================================\n";
$resDanhGiaTrung = $danhGiaService->themDanhGia([
    'maTK' => 'TK003',
    'maPhieu' => 'PP001',
    'maPhong' => 'P001',
    'soSao' => 5,
    'noiDung' => 'Đánh giá lần 2'
]);
echo "Kết quả: " . json_encode($resDanhGiaTrung, JSON_UNESCAPED_UNICODE) . "\n";
assert($resDanhGiaTrung['status'] === 'error' && $resDanhGiaTrung['code'] === 400, 'Lẽ ra phải chặn trùng lỗi 400');
echo "=> KẾT QUẢ TEST 4: ĐÃ CHẶN ĐÁNH GIÁ TRÙNG THÀNH CÔNG!\n\n";

echo "====================================================================\n";
echo "TEST 5: ĐÁNH GIÁ PHÒNG P004 HỢP LỆ (ĐÃ CHECK-OUT, CHƯA ĐÁNH GIÁ)\n";
echo "====================================================================\n";
$resHopLe = $danhGiaService->themDanhGia([
    'maTK' => 'TK003',
    'maPhieu' => 'PP001',
    'maPhong' => 'P004',
    'soSao' => 4,
    'noiDung' => 'Phòng P004 nhỏ nhưng ấm cúng, giường nệm êm ái, rất hài lòng!'
]);
echo "Kết quả: " . json_encode($resHopLe, JSON_UNESCAPED_UNICODE) . "\n";
assert($resHopLe['status'] === 'success', 'Lẽ ra phải gửi đánh giá thành công');
$maDG_VuaTao = $resHopLe['data']['MaDanhGia'];

// Kiểm tra lại chi tiết phiếu sau khi vừa đánh giá P004
$detailSau = $datPhongService->xemChiTietPhieu('PP001', 'TK003');
$p004 = $detailSau['DanhSachPhong'][1];
echo "Sau khi đánh giá, phòng P004: DaDanhGia = " . ($p004['DaDanhGia'] ? 'TRUE' : 'FALSE') . " | Số sao = {$p004['DanhGia']['SoSao']}★ | Nội dung: {$p004['DanhGia']['NoiDung']}\n";
assert($p004['DaDanhGia'] === true, 'P004 giờ phải có DaDanhGia = true');
echo "=> KẾT QUẢ TEST 5: ĐÁNH GIÁ THÀNH CÔNG VÀ ĐÃ ĐƯỢC ĐỒNG BỘ!\n\n";

// Dọn dẹp bản ghi test P004 để DB trở lại trạng thái mẫu
$danhGiaService->xoaDanhGia(['maDanhGia' => $maDG_VuaTao]);
echo "Đã dọn dẹp bản ghi test {$maDG_VuaTao}.\n\n";

echo "🎉 TẤT CẢ 5 BÀI TEST NGHIỆP VỤ ĐÁNH GIÁ TRONG LỊCH SỬ ĐÃ VƯỢT QUA 100%!\n";
