# Backend - Hotel Booking

Cay thu muc va code duoc sinh tu so do lop (class diagram), theo kien truc module
hoa: moi module nghiep vu co du 4 lop `Routes -> Controllers -> Services -> Repositories -> Models`.

## Danh sach module

- `TaiKhoan` (BE1) — model: TaiKhoan
- `KhachHang` (BE1) — model: KhachHang
- `DatPhong` (BE1) — entities: PhieuNhanPhong, ChiTietPhieuNhanPhong, KhuyenMai
- `DanhGia` (BE1) — model: DanhGia
- `LeTan` (BE2) — model: LeTan
- `Phong` (BE3) — model: Phong, LoaiPhong
- `DichVu` — catalog and service-booking controller; other layers remain unimplemented
- `ThanhToan` (BE4) — model: ThanhToan
- `HoaDon` (BE4) — model: HoaDon, CTHD
- `Admin` (BE4) — model: Admin

## Nguyen tac quan trong

1. **Repository** la lop DUY NHAT duoc phep dung `Database::getConnection()` de truy van DB.
2. **Service** chua toan bo business logic; neu can du lieu cua module khac, phai
   goi qua **Service** cua module do (khong duoc goi thang Repository/Model cheo module)
   de giu tinh dong goi giua cac module.
3. **Controller** chi nhan request/goi Service/tra response, khong chua logic nghiep vu.
4. Thứ tự tạo bảng theo phụ thuộc khóa ngoại: tài khoản và vai trò, khách hàng/nhân viên,
   loại phòng/phòng/khuyến mãi, phiếu và chi tiết phiếu, dịch vụ/hóa đơn, rồi đánh giá/thanh toán.
5. Admin quản lý tài khoản nhân viên lễ tân; hệ thống hiện mô hình hóa một khách sạn,
   không tách chi nhánh hoặc vai trò quản lý riêng.

## Cai dat

```bash
composer install   # (chua co vendor/, can chay composer de sinh autoload)
cp .env.example .env
php -S localhost:8000 -t public public/index.php
```

## Dang nhap va phan quyen

De don gian hoa demo tren lop, dashboard va cac API quan tri tai khoan, phong,
loai phong, thong ke, khuyen mai Admin khong yeu cau dang nhap/token. Cac API
nghiep vu nhu `/letan/*`, `/datphong/*`, `/hoadon/*`, `/thanhtoan/*`, va
`/danhgia/*` van yeu cau token va vai tro theo route. Khong dua cac API quan tri
mo nay len mang cong khai.

Sao chep `Backend/.env.example` thanh `Backend/.env`, dien cau hinh MySQL va
dat `AUTH_TOKEN_SECRET` thanh mot chuoi ngau nhien rieng, dai toi thieu 32 byte.
Co the tao secret bang lenh:

```bash
php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
```

API dang nhap tra ve Bearer access token co hieu luc 1 gio. Cac request duoc
bao ve gui token qua header `Authorization: Bearer <access_token>`. Moi request
se kiem tra lai trang thai va vai tro trong database, vi vay khoa tai khoan hoac
doi vai tro co hieu luc ngay; token het han sau toi da mot gio.

Tai khoan mau (sau khi nap `Database/data.sql`): `admin` / `123456`. Mat khau
plaintext mau se duoc nang cap sang hash sau lan dang nhap thanh cong dau tien;
khong su dung tai khoan/mat khau mau trong moi truong that.

| Endpoint | JSON/query | Chuc nang |
|---|---|---|
| `POST /taikhoan/dangnhap` | `TenDangNhap`, `MatKhau` | Dang nhap va nhan access token |
| `GET` hoac `POST /taikhoan/xemds` | tuy chon `keyword`, `VaiTro`, `TrangThaiTaiKhoan` | Admin xem/tim tai khoan |
| `GET` hoac `POST /taikhoan/xemct` | `MaTK` | Admin xem chi tiet tai khoan, khong tra mat khau |
| `POST /taikhoan/khoa` | `MaTK` | Admin khoa tai khoan |
| `POST /taikhoan/mo` | `MaTK` | Admin mo khoa tai khoan |
| `POST /taikhoan/capnhatvaitro` | `MaTK`, `VaiTro` | Admin doi vai tro; khong the ha cap Admin hoat dong cuoi cung |
| `GET /taikhoan/vaitroquyen` | — | Admin xem danh sach vai tro va quyen |

Vai tro Admin/LeTan van duoc kiem tra tren cac API nghiep vu co bao ve. Cac API
quan tri mo khong kiem tra vai tro, nen chi phu hop voi localhost/mang demo tin
cay; day khong phai cau hinh bao mat cho production.

## API quan ly phong (BE3)

Tat ca endpoint duoi day dung `POST`, nhan JSON. Cac trang thai hop le:
`Trong`, `DaDat`, `DangSuDung`, `BaoTri`.

| Endpoint | JSON body | Chuc nang |
|---|---|---|
| `/phong/xemdsphong` | `{}` hoac `MaLoaiPhong`, `TrangThaiPhong` | Xem danh sach va loc phong |
| `/phong/xemctphong` | `MaPhong` | Xem chi tiet phong kem thong tin loai phong |
| `/phong/themphong` | `MaPhong`, `MaLoaiPhong`, tuy chon `TrangThaiPhong` | Them phong; mac dinh la `Trong` |
| `/phong/capnhatthongtinphong` | `MaPhong`, tuy chon `MaLoaiPhong`, `TrangThaiPhong` | Cap nhat loai phong va/hoac trang thai |
| `/phong/timkiemphong` | `keyword` hoac `TuKhoa`; tuy chon bo loc | Tim theo ma phong, ma loai hoac ten loai |
| `/phong/kiemtratrangthaiphong` | `MaPhong` | Tra ve trang thai cua mot phong |
| `/phong/kiemtraphongtrong` | `{}` hoac `MaPhong`, tuy chon `MaLoaiPhong` | Liet ke phong `Trong`, hoac kiem tra mot phong |

Vi du:

```bash
curl -X POST http://localhost:8000/phong/xemdsphong \
  -H 'Content-Type: application/json' \
  -d '{}'
```

## API quan ly loai phong (BE3)

Tat ca endpoint dung `POST`, nhan JSON:

| Endpoint | JSON body | Chuc nang |
|---|---|---|
| `/loaiphong/xemdsloaiphong` | `{}`; tuy chon `keyword` hoac `TuKhoa` | Xem danh sach/tim kiem theo ma hoac ten loai |
| `/loaiphong/xemctloaiphong` | `MaLoaiPhong` | Xem chi tiet loai phong |
| `/loaiphong/themloaiphong` | `MaLoaiPhong`, `TenLoaiPhong`, `Gia`, `SoNguoiToiDa`; tuy chon `MoTa`, `TienNghi` | Them loai phong |
| `/loaiphong/capnhatloaiphong` | `MaLoaiPhong` va it nhat mot thuoc tinh can cap nhat | Cap nhat ten, gia, suc chua, mo ta hoac tien nghi |

## API quan ly le tan (BE3)

API quan tri le tan dung cac endpoint `/admin/*letan`. Danh sach, tim kiem va
chi tiet chap nhan `GET` hoac `POST`; them va cap nhat dung `POST` voi JSON body.
Tao moi yeu cau `HoTen`, `SDT`, `Email`, `TenDangNhap`, `MatKhau`; mat khau moi
phai co it nhat 8 ky tu va duoc hash truoc khi luu.

| Endpoint | JSON body | Chuc nang |
|---|---|---|
| `/admin/xemdsletan` | `{}` | Xem danh sach le tan |
| `/admin/timkiemletan` | `keyword` hoac `TuKhoa` | Tim theo ma, ho ten, SDT, email hoac ten dang nhap |
| `/admin/xemctletan` | `MaLeTan` | Xem thong tin le tan va trang thai tai khoan |
| `/admin/themletan` | `HoTen`, `SDT`, `Email`, `TenDangNhap`, `MatKhau` | Tao tai khoan vai tro `LeTan` va ho so trong cung transaction |
| `/admin/capnhatthongtinletan` | `MaLeTan` va it nhat mot trong `HoTen`, `SDT`, `Email` | Cap nhat thong tin; Email duoc dong bo vao tai khoan |

## API quan ly khach hang (BE3)

Danh sach, tim kiem va chi tiet chap nhan `GET` hoac `POST`; cap nhat va khoa/mo
tai khoan dung `POST` voi JSON body. Cac route quan tri bao gom:

| Endpoint | JSON body | Chuc nang |
|---|---|---|
| `/admin/xemdskhachhang` | `{}` | Xem danh sach khach hang va trang thai tai khoan neu co |
| `/admin/timkiemkhachhang` | `keyword` hoac `TuKhoa` | Tim theo ma, ho ten, SDT, email hoac ten dang nhap |
| `/admin/xemctkhachhang` | `MaKH` | Xem chi tiet khach hang |
| `/admin/capnhatthongtinkhachhang` | `MaKH` va it nhat mot trong `HoTen`, `SDT`, `Email` | Cap nhat thong tin; dong bo Email voi tai khoan dang nhap |
| `/admin/khoataikhoankhachhang` | `MaKH` | Khoa tai khoan `KhachHang` da lien ket |
| `/admin/motaikhoankhachhang` | `MaKH` | Mo lai tai khoan `KhachHang` da lien ket |

Khach hang duoc tao tu dat phong co the chua co tai khoan. Cac ban ghi nay van
hien trong danh sach va co the cap nhat ho so, nhung endpoint khoa/mo se tra loi
409 cho den khi tai khoan duoc lien ket.

## API phoi hop voi BE1/BE2 (Task 6)

BE1 va BE2 lay du lieu phong qua `PhongService`, cung mot nguon du lieu voi cac
API BE3. Cac route tiep nhan le tan `/letan/xemdsphong`,
`/letan/xemctphong`, `/letan/timkiemphong` va `/letan/kiemtraphongtrong`
duoc noi truc tiep toi service phong BE3 (chi Admin/LeTan duoc goi).
API khach hang `/khachhang/danhsachphong` va `/khachhang/xemdsphong` cung dung
service nay de tra danh sach va gia phong hien hanh.

Dat phong online tra 404 neu phong khong ton tai, 409 neu phong khong con trong,
va 400 neu so khach vuot suc chua loai phong. Khi ghi dat phong, trang thai
phong duoc chuyen `Trong` -> `DaDat` bang mot cap nhat co dieu kien trong cung
transaction voi phieu, hoa don va thanh toan; yeu cau canh tranh trung phong
chi co mot yeu cau thanh cong. Cac nghiep vu dat/check-in/check-out BE1/BE2
con TODO van nam ngoai pham vi Task 6.

## Ghi chu

- Cac module nghiep vu khac van con method **stub (TODO)**; phan xac thuc tai
  khoan va phan quyen route da duoc bat.
- Router trong `public/index.php` la ban toi gian minh hoa; nen thay bang mot
  thu vien routing that (Slim, FastRoute, Laravel...) khi trien khai that.
