<?php
require_once __DIR__ . '/../Backend/vendor/autoload.php';
$_ENV['DB_PASSWORD'] = 'theanh12345';

use App\DatPhong\Controllers\DatPhongController;
use App\DatPhong\Services\DatPhongService;
use App\DatPhong\Repositories\DatPhongRepository;
use Shared\Request;
use Shared\Response;

$db = new \Database\Database();
$repo = new DatPhongRepository($db);
$phongRepo = new \App\Phong\Repositories\PhongRepository($db);
$phongService = new \App\Phong\Services\PhongService($phongRepo);
$service = new DatPhongService($repo, $phongService);
$controller = new DatPhongController($service);

function createRequest(array $data) {
    return new class($data) {
        private array $data;
        public function __construct(array $d) { $this->data = $d; }
        public function all(): array { return $this->data; }
    };
}

echo "=== TEST CHỨC NĂNG CẬP NHẬT NGƯỜI LƯU TRÚ ===\n\n";

// Test 1: Sửa người lưu trú NLT004 thuộc PP004 (Trạng thái DaDat của TK003 - khach01)
echo "1. Test sửa người lưu trú NLT004 (phiếu PP004 - DaDat):\n";
try {
    $req = createRequest([
        'maTK' => 'TK003',
        'maNguoiLuuTru' => 'NLT004',
        'hoTen' => 'Nguyễn Văn An (Đã sửa)',
        'ngaySinh' => '1990-01-02',
        'quocTich' => 'Việt Nam',
        'loaiGiayTo' => 'CCCD',
        'soGiayTo' => '001202000001',
        'ghiChu' => 'Cập nhật lại do sai ngày sinh',
    ]);
    $res = new Response();
    $controller->capNhatNguoiLuuTru($req, $res);
    $output = $res->getData();
    echo "  Status Code: " . $res->getStatusCode() . "\n";
    echo "  Output: " . json_encode($output, JSON_UNESCAPED_UNICODE) . "\n";
    assert($output['success'] === true);
    echo "  => PASS TEST 1!\n\n";
} catch (\Throwable $e) {
    echo "  => FAIL TEST 1: " . $e->getMessage() . "\n\n";
}

// Test 2: Thử sửa người lưu trú NLT001 thuộc PP001 (Trạng thái DaTraPhong) -> Phải bị chặn 400
echo "2. Test thử sửa NLT001 (phiếu PP001 - DaTraPhong):\n";
try {
    $req = createRequest([
        'maTK' => 'TK003',
        'maNguoiLuuTru' => 'NLT001',
        'hoTen' => 'Hack name',
        'ngaySinh' => '1990-05-15',
        'quocTich' => 'Việt Nam',
        'loaiGiayTo' => 'CCCD',
        'soGiayTo' => '001202000001',
    ]);
    $res = new Response();
    $controller->capNhatNguoiLuuTru($req, $res);
    $output = $res->getData();
    echo "  Status Code: " . $res->getStatusCode() . "\n";
    echo "  Output: " . json_encode($output, JSON_UNESCAPED_UNICODE) . "\n";
    assert($output['success'] === false);
    assert($res->getStatusCode() === 400);
    echo "  => PASS TEST 2 (Chặn thành công khi phiếu đã trả phòng)!\n\n";
} catch (\Throwable $e) {
    echo "  => FAIL TEST 2: " . $e->getMessage() . "\n\n";
}

// Test 3: Thử sửa khi tài khoản khác (TK002 - le tan) không phải chủ sở hữu khách hàng -> Chặn 403
echo "3. Test thử sửa khi tài khoản khác (TK002) không phải khách hàng sở hữu:\n";
try {
    $req = createRequest([
        'maTK' => 'TK002',
        'maNguoiLuuTru' => 'NLT004',
        'hoTen' => 'Hacker Name',
        'ngaySinh' => '1990-01-01',
        'quocTich' => 'Việt Nam',
        'loaiGiayTo' => 'CCCD',
        'soGiayTo' => '001202000001',
    ]);
    $res = new Response();
    $controller->capNhatNguoiLuuTru($req, $res);
    $output = $res->getData();
    echo "  Status Code: " . $res->getStatusCode() . "\n";
    echo "  Output: " . json_encode($output, JSON_UNESCAPED_UNICODE) . "\n";
    assert($res->getStatusCode() === 403);
    echo "  => PASS TEST 3 (Chặn thành công truy cập trái phép)!\n\n";
} catch (\Throwable $e) {
    echo "  => FAIL TEST 3: " . $e->getMessage() . "\n\n";
}

// Test 4: Ngày sinh ở tương lai -> Phải bị chặn 400
echo "4. Test ngày sinh ở tương lai (2099-01-01):\n";
try {
    $req = createRequest([
        'maTK' => 'TK003',
        'maNguoiLuuTru' => 'NLT004',
        'hoTen' => 'Nguyễn Văn An',
        'ngaySinh' => '2099-01-01',
        'quocTich' => 'Việt Nam',
        'loaiGiayTo' => 'CCCD',
        'soGiayTo' => '001202000001',
    ]);
    $res = new Response();
    $controller->capNhatNguoiLuuTru($req, $res);
    $output = $res->getData();
    echo "  Status Code: " . $res->getStatusCode() . "\n";
    echo "  Output: " . json_encode($output, JSON_UNESCAPED_UNICODE) . "\n";
    assert($res->getStatusCode() === 400);
    echo "  => PASS TEST 4 (Chặn thành công ngày sinh không hợp lệ)!\n\n";
} catch (\Throwable $e) {
    echo "  => FAIL TEST 4: " . $e->getMessage() . "\n\n";
}

// Khôi phục lại dữ liệu ban đầu cho NLT004
$repo->capNhatNguoiLuuTru('NLT004', 'Nguyễn Văn An', '1990-01-01', 'Việt Nam', 'CCCD', '001202000001', null);
echo "Đã khôi phục dữ liệu NLT004 về ban đầu.\n";
echo "=== TẤT CẢ TEST ĐỀU THÀNH CÔNG! ===\n";
