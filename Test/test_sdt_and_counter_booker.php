<?php

require_once __DIR__ . '/../Backend/vendor/autoload.php';
(Dotenv\Dotenv::createImmutable(__DIR__ . '/../Backend'))->load();

use Database\Database;
use App\KhachHang\Repositories\KhachHangRepository;
use App\KhachHang\Services\KhachHangService;
use App\DatPhong\Repositories\DatPhongRepository;
use App\DatPhong\Services\DatPhongService;
use App\Phong\Repositories\PhongRepository;
use App\Phong\Services\PhongService;

echo "========================================================\n";
echo "TEST: SDT ARCHITECTURE & COUNTER BOOKER FLOW\n";
echo "========================================================\n\n";

$db = Database::getConnection();
$database = new Database();
$khRepo = new KhachHangRepository($database);
$khService = new KhachHangService($khRepo);
$phongRepo = new PhongRepository($database);
$phongService = new PhongService($phongRepo);
$datPhongRepo = new DatPhongRepository();
$datPhongService = new DatPhongService($datPhongRepo, $phongService);

$clearTestData = function() use ($db) {
    $stmtFindPhieu = $db->query("SELECT MaPhieu FROM PhieuNhanPhong WHERE MaKH IN (SELECT MaKH FROM KhachHang WHERE SDT IN ('0987654321', '0933333333', '0944444444')) OR MaKH IN ('KH_TEST_BOOKER1', 'KH_TEST_BOOKER2')");
    $allTestPhieu = $stmtFindPhieu->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($allTestPhieu)) {
        $inClause = "'" . implode("','", $allTestPhieu) . "'";
        $db->exec("DELETE FROM ThanhToan WHERE MaHD IN (SELECT MaHD FROM HoaDon WHERE MaPhieu IN ($inClause))");
        $db->exec("DELETE FROM cthd WHERE MaHD IN (SELECT MaHD FROM HoaDon WHERE MaPhieu IN ($inClause))");
        $db->exec("DELETE FROM HoaDon WHERE MaPhieu IN ($inClause)");
        $db->exec("DELETE FROM NguoiLuuTru WHERE MaPhieu IN ($inClause)");
        $db->exec("DELETE FROM ChiTietPhieuNhanPhong WHERE MaPhieu IN ($inClause)");
        $db->exec("DELETE FROM PhieuNhanPhong WHERE MaPhieu IN ($inClause)");
    }
    $db->exec("DELETE FROM KhachHang WHERE SDT IN ('0987654321', '0933333333', '0944444444') OR MaKH IN ('KH_TEST_BOOKER1', 'KH_TEST_BOOKER2')");
    $db->exec("DELETE FROM TaiKhoan WHERE TenDangNhap IN ('testuser_sdt', 'testbooker') OR Email IN ('testuser_sdt@gmail.com')");
};
$clearTestData();

// ---------------------------------------------------------
// TEST 1: Đăng ký tài khoản khách hàng trực tuyến
// ---------------------------------------------------------
echo "[TEST 1] Đăng ký tài khoản khách hàng mới (SDT: 0987654321)...\n";
try {
    $resReg = $khService->dangKy([
        'hoTen' => 'Nguyễn Thành Viên Test',
        'sdt' => '0987654321',
        'email' => 'testuser_sdt@gmail.com',
        'tenDangNhap' => 'testuser_sdt',
        'matKhau' => '123456'
    ]);
    echo "✅ Đăng ký thành công!\n";
    $registeredMaTK = $resReg['data']['MaTK'];
    $registeredMaKH = $resReg['data']['MaKH'];

    // Kiểm tra DB: bảng TaiKhoan không có SDT
    $stmtTK = $db->prepare("SELECT * FROM TaiKhoan WHERE MaTK = ?");
    $stmtTK->execute([$registeredMaTK]);
    $rowTK = $stmtTK->fetch(PDO::FETCH_ASSOC);
    if (!isset($rowTK['SDT'])) {
        echo "   ✓ Xác nhận: Bảng TaiKhoan đã hoàn toàn loại bỏ cột SDT!\n";
    }

    // Kiểm tra DB: bảng KhachHang lưu đúng SDT
    $stmtKH = $db->prepare("SELECT * FROM KhachHang WHERE MaKH = ?");
    $stmtKH->execute([$registeredMaKH]);
    $rowKH = $stmtKH->fetch(PDO::FETCH_ASSOC);
    if ($rowKH['SDT'] === '0987654321') {
        echo "   ✓ Xác nhận: Bảng KhachHang lưu trữ chính xác SDT = '{$rowKH['SDT']}'!\n";
    } else {
        echo "   ❌ Thất bại: SDT trong KhachHang không khớp!\n";
    }
} catch (Exception $e) {
    echo "❌ Lỗi TEST 1: " . $e->getMessage() . "\n";
}

// ---------------------------------------------------------
// TEST 2: Tra cứu khách hàng theo SĐT (findBySDT)
// ---------------------------------------------------------
echo "\n[TEST 2] Tra cứu khách hàng theo Số Điện Thoại...\n";

// Case 2A: Tra cứu khách thành viên vừa tạo
$foundMember = $khRepo->findBySDT('0987654321');
if ($foundMember && !empty($foundMember['MaTK'])) {
    echo "   ✓ Case 2A: Tìm thấy khách thành viên [{$foundMember['HoTen']}] - MaTK: {$foundMember['MaTK']}\n";
} else {
    echo "   ❌ Case 2A thất bại!\n";
}

// Case 2B: Tạo một khách vãng lai (không có MaTK) với SĐT 0933333333
$db->exec("INSERT INTO KhachHang (MaKH, HoTen, SDT, MaTK) VALUES ('KH_TEST_BOOKER1', 'Lê Vãng Lai Cũ', '0933333333', NULL)");
$foundWalkIn = $khRepo->findBySDT('0933333333');
if ($foundWalkIn && empty($foundWalkIn['MaTK'])) {
    echo "   ✓ Case 2B: Tìm thấy khách vãng lai cũ [{$foundWalkIn['HoTen']}] - MaTK: NULL (chính xác!)\n";
} else {
    echo "   ❌ Case 2B thất bại!\n";
}

// Case 2C: Tra cứu SĐT chưa từng tồn tại
$notFound = $khRepo->findBySDT('0999999999');
if ($notFound === null) {
    echo "   ✓ Case 2C: SĐT lạ trả về NULL (đúng như kỳ vọng để nhận diện khách mới)!\n";
} else {
    echo "   ❌ Case 2C thất bại!\n";
}

// ---------------------------------------------------------
// TEST 3: Đặt phòng tại quầy cho khách thành viên cũ (nhập SĐT 0987654321)
// ---------------------------------------------------------
echo "\n[TEST 3] Lễ tân đặt phòng tại quầy bằng SĐT của khách thành viên cũ (0987654321)...\n";
try {
    $today = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));

    $maPhieu1 = '';
    $resCounter1 = $datPhongService->datPhongVaThanhToan([
        'maTK' => 'TK002', // Lễ tân đặt
        'kenhDat' => 'TrucTiep',
        'maLeTan' => 'LT001',
        'sdtNguoiDat' => '0987654321', // SĐT của Nguyễn Thành Viên Test
        'tenNguoiDat' => 'Nguyễn Thành Viên Test',
        'ngayNhan' => $today,
        'ngayTra' => $tomorrow,
        'soNguoiLon' => 2,
        'soTreEm' => 0,
        'soPhong' => 1,
        'phongDaChon' => [
            ['maLoaiPhong' => 'LP001', 'soLuong' => 1]
        ],
        'nguoiLuuTruTheoPhong' => [
            [
                'maLoaiPhong' => 'LP001',
                'khach' => [
                    ['hoTen' => 'Nguyễn Thành Viên Test', 'ngaySinh' => '1995-05-15', 'quocTich' => 'Việt Nam', 'loaiGiayTo' => 'CCCD', 'soGiayTo' => '123456789012'],
                    ['hoTen' => 'Người Đi Cùng', 'ngaySinh' => '1998-08-20', 'quocTich' => 'Việt Nam', 'loaiGiayTo' => 'CCCD', 'soGiayTo' => '987654321098']
                ]
            ]
        ],
        'hinhThuc' => 'TienMat'
    ]);

    $maPhieu1 = $resCounter1['maPhieu'];
    $stmtCheckP1 = $db->prepare("SELECT MaKH FROM PhieuNhanPhong WHERE MaPhieu = ?");
    $stmtCheckP1->execute([$maPhieu1]);
    $maKHAttached1 = $stmtCheckP1->fetchColumn();

    if ($maKHAttached1 === $registeredMaKH) {
        echo "✅ THÀNH CÔNG: Phiếu đặt tại quầy đã tự động gắn đúng vào MaKH của khách thành viên ($registeredMaKH)!\n";
        echo "   -> Khách đăng nhập web sẽ thấy phiếu #$maPhieu1 trong Lịch sử đặt phòng của mình!\n";
    } else {
        echo "❌ THẤT BẠI: Gắn sai MaKH (kết quả: $maKHAttached1, kỳ vọng: $registeredMaKH)\n";
    }
} catch (Exception $e) {
    echo "❌ Lỗi TEST 3: " . $e->getMessage() . "\n";
}

// ---------------------------------------------------------
// TEST 4: Trường hợp ĐẶT DÙM (Người đặt khác hoàn toàn người lưu trú)
// ---------------------------------------------------------
echo "\n[TEST 4] Đặt phòng tại quầy theo diện ĐẶT DÙM (Người đặt: Trần Trưởng Đoàn - 0944444444, Người ở: Lê Nhân Viên)...\n";
try {
    $maPhieu2 = '';
    $resCounter2 = $datPhongService->datPhongVaThanhToan([
        'maTK' => 'TK002',
        'kenhDat' => 'TrucTiep',
        'maLeTan' => 'LT001',
        'sdtNguoiDat' => '0944444444',
        'tenNguoiDat' => 'Trần Trưởng Đoàn',
        'ngayNhan' => $today,
        'ngayTra' => $tomorrow,
        'soNguoiLon' => 1,
        'soTreEm' => 0,
        'soPhong' => 1,
        'phongDaChon' => [
            ['maLoaiPhong' => 'LP001', 'soLuong' => 1]
        ],
        'nguoiLuuTruTheoPhong' => [
            [
                'maLoaiPhong' => 'LP001',
                'khach' => [
                    ['hoTen' => 'Lê Nhân Viên', 'ngaySinh' => '1999-01-01', 'quocTich' => 'Việt Nam', 'loaiGiayTo' => 'CCCD', 'soGiayTo' => '111222333444']
                ]
            ]
        ],
        'hinhThuc' => 'TienMat'
    ]);

    $maPhieu2 = $resCounter2['maPhieu'];
    
    // Kiểm tra chủ phiếu
    $stmtCheckP2 = $db->prepare("SELECT pnp.MaKH, kh.HoTen, kh.SDT FROM PhieuNhanPhong pnp JOIN KhachHang kh ON pnp.MaKH = kh.MaKH WHERE pnp.MaPhieu = ?");
    $stmtCheckP2->execute([$maPhieu2]);
    $bookerInfo = $stmtCheckP2->fetch(PDO::FETCH_ASSOC);

    echo "   - Người đại diện đứng tên phiếu #$maPhieu2: {$bookerInfo['HoTen']} (SĐT: {$bookerInfo['SDT']})\n";

    // Kiểm tra danh sách người lưu trú thực tế của phiếu
    $stmtGuests = $db->prepare("SELECT HoTen FROM NguoiLuuTru WHERE MaPhieu = ?");
    $stmtGuests->execute([$maPhieu2]);
    $guestsList = $stmtGuests->fetchAll(PDO::FETCH_COLUMN);

    echo "   - Danh sách khách lưu trú thực tế: " . implode(', ', $guestsList) . "\n";

    if ($bookerInfo['HoTen'] === 'Trần Trưởng Đoàn' && $bookerInfo['SDT'] === '0944444444' 
        && in_array('Lê Nhân Viên', $guestsList) && !in_array('Trần Trưởng Đoàn', $guestsList)) {
        echo "✅ THÀNH CÔNG: Xử lý ĐẶT DÙM hoàn hảo! Người đặt là Trần Trưởng Đoàn, người ở là Lê Nhân Viên!\n";
    } else {
        echo "❌ THẤT BẠI trong việc phân tách người đặt và người ở!\n";
    }

} catch (Exception $e) {
    echo "❌ Lỗi TEST 4: " . $e->getMessage() . "\n";
}

// ---------------------------------------------------------
// CLEANUP
// ---------------------------------------------------------
$clearTestData();

echo "\n[CLEANUP] Đã dọn dẹp toàn bộ dữ liệu kiểm thử thành công.\n";
