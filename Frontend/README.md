> **Bản đã merge với Backend.** Xem hướng dẫn chạy ở `../README-MERGE.md`. Các phần về dữ liệu phòng/đặt phòng bên dưới vẫn là chế độ giả lập, sẽ được nối Backend ở các bước tiếp theo.

# SkyHotel - Website đặt phòng khách sạn (bản giao diện)

Website tĩnh gồm 12 trang, viết bằng HTML + CSS + JavaScript thuần, **mỗi loại nằm trong file riêng**.
Không cần cài đặt gì, không cần Node.js, không cần internet.

## Cấu trúc thư mục

```
skyhotel/
├── index.html              <- Khung website: mở file này để chạy
├── README.md
├── docs/
│   └── API-DAT-PHONG.md    <- Tài liệu API đặt phòng & thanh toán
├── css/
│   ├── shell.css           <- Giao diện khung: thanh menu khách hàng, menu quản trị
│   ├── theme.css           <- Bảng màu, typography và biến giao diện toàn hệ thống
│   ├── admin-common.css    <- Giao diện dùng chung cho các phân hệ quản trị
│   └── <tên-trang>.css     <- Giao diện riêng của từng trang
├── js/
│   ├── shell.js            <- Điều hướng, đăng nhập/đăng ký, phân quyền, lưu phiên
│   ├── bridge.js           <- Cầu nối chung: bấm nút trong trang -> chuyển trang
│   ├── sky-api.js          <- Quản trị dữ liệu phòng, lịch đặt, ưu đãi voucher và khách
│   └── <tên-trang>.js      <- Chức năng riêng của từng trang
├── pages/
│   ├── trang-chu.html            ├── dashboard-quan-ly.html
│   ├── chon-phong.html           ├── dashboard-le-tan.html
│   ├── chi-tiet-phong.html       ├── quan-ly-phong.html
│   ├── dat-phong.html            ├── quan-ly-loai-phong.html
│   ├── thanh-toan.html           ├── quan-ly-khach-hang.html
│   ├── lich-su-dat-phong.html    ├── quan-ly-hoa-don.html
│   ├── dang-nhap.html            ├── quan-ly-dich-vu.html
│   ├── chi-tiet-dich-vu.html     ├── quan-ly-khuyen-mai.html
│   ├── danh-sach-yeu-thich.html  ├── quan-ly-nhan-vien.html
│   └── thong-tin-khach-san.html
└── images/
    ├── hotels/             <- Ảnh khách sạn & khuôn viên
    ├── rooms/              <- Ảnh các hạng phòng (nạp động theo mã hạng)
    └── services/           <- Ảnh các dịch vụ tiện ích
```

Cách hoạt động: `index.html` là khung, nạp `css/shell.css` và `js/shell.js`. Khung hiển thị từng file trong `pages/` bên trong một khung nhúng (iframe). Mỗi trang trong `pages/` tự nạp CSS và JS cùng tên của nó.
Trang chủ kiểu khách sạn 5 sao (hero, giới thiệu, phòng, dịch vụ, ưu đãi, liên hệ).

## Cách 1: Mở trực tiếp

1. Giải nén file zip.
2. Nhấp đúp vào `index.html` (hoặc chuột phải > Open with > Chrome / Edge / Firefox).

## Cách 2: Chạy bằng máy chủ cục bộ (nếu Cách 1 bị lỗi)

Vì website nạp nhiều file, một số trình duyệt có thể chặn khi mở kiểu `file://`. Khi đó hãy mở terminal trong thư mục `skyhotel` và chạy:

```
python -m http.server 8000
```
(hoặc `python3 -m http.server 8000` trên macOS/Linux), rồi vào http://localhost:8000

Dùng VS Code: cài extension **Live Server**, chuột phải `index.html` > **Open with Live Server**.

## Cách dùng website

Mở lên là thấy giao diện khách hàng: thanh menu (Trang chủ, Khách sạn, Ưu đãi, Về chúng tôi) và nút **Đăng nhập / Đăng ký** ở góc phải. Đăng nhập xong, góc phải hiện tên kèm menu (Thông tin cá nhân, Danh sách yêu thích, Lịch sử đặt phòng, Đăng xuất).

Luồng đặt phòng:

1. Trang chủ: bấm "Tìm kiếm" hoặc chọn một phòng
2. **Chọn phòng** (`pages/chon-phong.html`): chọn ngày, số khách, số phòng; xem phòng còn trống của 5 hạng; bấm "Chọn phòng", tăng/giảm số lượng; bấm "Tiếp tục đặt phòng"
3. Đặt phòng (cần đăng nhập): nhập thông tin hợp lệ, nhập mã giảm giá (`SKYWELCOME`, `FLASH300K`, `SKY10`...). Bước này kiểm tra lại phòng trống và giữ phòng 30 phút
4. Thanh toán: xem chi tiết khấu trừ voucher, bấm "Thanh toán ngay", sau đó tự chuyển sang Lịch sử đặt phòng

**Khu vực quản trị** chỉ hiện khi đăng nhập bằng tài khoản Admin, với menu bên trái đầy đủ 9 phân hệ: Thống kê & Báo cáo, Nghiệp vụ lễ tân, Quản lý phòng, Loại phòng, Khách hàng, Hóa đơn & Thu chi, Quản lý dịch vụ, Khuyến mãi & Voucher, Quản lý tài khoản, cùng Thông tin khách sạn và Hệ thống email.

## Tài khoản (đã nối Backend)

Đăng nhập, đăng ký, quên mật khẩu (OTP qua email) gọi thẳng Backend PHP + MySQL qua `js/hotel-api.js`. Không còn tài khoản/mật khẩu mẫu trong code.

| Vai trò (Backend) | Vào được | Trang đầu sau đăng nhập |
|---|---|---|
| `KhachHang` | Trang khách hàng, đặt phòng, lịch sử | Trang chủ |
| `LeTan` | Lễ tân & Đặt phòng | Lễ tân |
| `Admin` | Toàn bộ khu quản trị | Thống kê & Báo cáo |

- Đăng nhập bằng **tên đăng nhập** (Backend không tra theo email).
- Phiên đăng nhập lưu ở `localStorage["hotel_user"]`, dùng chung với các trang trong `legacy/`.
- Nút Google / Facebook chưa được hỗ trợ.

## Dữ liệu phòng & kết nối backend

`js/sky-api.js` là lớp dữ liệu duy nhất cho phòng trống / đặt phòng. Chưa có máy chủ nên chế độ demo dùng CSDL giả lập trong localStorage và chỉ hoạt động trên `file://` hoặc localhost; không dùng chế độ này cho production. API thật phải dùng HTTPS. Thanh toán hiện bị vô hiệu hóa: không nhập thông tin thẻ trên website và chưa có giao dịch VNPay thật. Khi triển khai backend, làm theo `docs/API-DAT-PHONG.md` để thiết lập endpoint đặt phòng, VNPay Hosted Checkout và xác minh IPN/callback phía máy chủ.

## Chức năng đang hoạt động

- Danh sách phòng: lọc theo giá, hạng phòng, tiện nghi, đánh giá; sắp xếp
- Chi tiết phòng: bấm ảnh hoặc tên hạng phòng để xem thông tin và tiện nghi, đổi ảnh, lưu vào danh sách
- Đặt phòng: kiểm tra dữ liệu nhập, mã giảm giá
- Lịch sử đặt phòng: lọc theo trạng thái
- Dashboard: biểu đồ đường, cột, tròn (vẽ bằng SVG)
- Quản lý nhân viên: tìm kiếm, lọc vai trò, khóa/mở khóa, xóa, thêm, phân trang
- Thông tin khách sạn (một khách sạn duy nhất): sửa địa chỉ, hotline, email; chân trang của trang chủ tự cập nhật
- Hệ thống email: xem trước 4 mẫu email, bật/tắt từng loại
- Đăng nhập / đăng ký / quên mật khẩu, phân quyền theo vai trò

## Chỉnh sửa code

| Muốn sửa | Mở file |
|---|---|
| Màu sắc, bố cục một trang | `css/<tên-trang>.css` |
| Chức năng, dữ liệu mẫu một trang | `js/<tên-trang>.js` (dữ liệu mẫu nằm đầu file) |
| Nội dung chữ, cấu trúc một trang | `pages/<tên-trang>.html` |
| Thanh menu trên cùng, menu quản trị | `css/shell.css` và `js/shell.js` |
| Tài khoản, phân quyền | `js/shell.js` (mảng `SEED`, biến `RULES`) |
| Nút nào dẫn sang trang nào | `js/bridge.js` |

- Cuối mỗi file trong `css/` của các trang có đoạn "Hiển thị bên trong khung website": đây là phần ẩn thanh menu riêng của trang (vì khung `index.html` đã có menu chung). Xóa đoạn này nếu muốn mở trang đó độc lập với menu cũ.
- Ảnh nằm trong thư mục `images/` (hiện là ảnh minh họa SVG). Để dùng ảnh thật, chép ảnh vào `images/` rồi đổi đuôi `.svg` thành `.jpg`/`.webp` trong các file css/js/pages tương ứng.

## Lưu ý

- Dữ liệu là dữ liệu giả nằm trong code, tải lại trang sẽ về trạng thái ban đầu (trừ tài khoản đã đăng ký và phiên đăng nhập).
- Chưa có máy chủ, cơ sở dữ liệu, thanh toán hay gửi email thật.
- Giao diện tự chuyển sáng/tối theo hệ thống và dùng được trên điện thoại.
