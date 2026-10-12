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
cp .env.example .env  # neu co, hoac chinh sua truc tiep .env
php -S localhost:8000 -t public
```

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

## Ghi chu

- Cac module nghiep vu khac van con method **stub (TODO)**; API quan ly phong BE3
  da ket noi repository voi database.
- Router trong `public/index.php` la ban toi gian minh hoa; nen thay bang mot
  thu vien routing that (Slim, FastRoute, Laravel...) khi trien khai that.
