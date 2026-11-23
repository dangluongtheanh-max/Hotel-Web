> **Lưu ý (bản merge):** tài liệu này mô tả API REST dự kiến của bản demo ban đầu. Backend thật (thư mục `Backend/`) dùng các endpoint dạng `/datphong/...`, `/thanhtoan/...`; phần đặt phòng sẽ được chuyển sang các endpoint đó ở Bước 2.

# Kết nối backend đặt phòng và thanh toán

Giao diện chỉ gọi `SkyAPI.*` (file `js/sky-api.js`). Khi có máy chủ thật, đặt **trước** thẻ nạp `sky-api.js`:

```html
<script>window.SKY_API_BASE = "https://domain-cua-ban/api";</script>
```

Khi đó mọi giá, số phòng trống, đơn đặt phòng đều đến từ máy chủ. Nếu không đặt, chương trình chỉ cho phép CSDL giả lập trong `localStorage` trên `file://` hoặc localhost để demo.

## Yêu cầu bảo mật bắt buộc

- Production phải được phục vụ qua HTTPS và `SKY_API_BASE` phải là URL HTTPS. HTTP chỉ được chấp nhận cho API localhost khi trang cũng chạy local để phát triển.
- Không triển khai production bằng chế độ mock `localStorage`; mock chỉ được phép trên `file://` hoặc `localhost`/loopback. Chế độ mock không được xác nhận thanh toán.
- Trang Sky Hotel không được thu thập, ghi log, hoặc lưu PAN, CVV, ngày hết hạn thẻ hay token thanh toán vào localStorage/sessionStorage.
- Chỉ backend giữ bí mật VNPay và tạo URL thanh toán có chữ ký. Trình duyệt nhận URL HTTPS và điều hướng sang VNPay Hosted Checkout; không tự ký yêu cầu tại frontend.
- Backend chỉ xác nhận đơn đã thanh toán sau khi xác minh chữ ký IPN/callback, mã đơn, số tiền, tiền tệ và trạng thái giao dịch. Không tin query string/trang return của trình duyệt làm bằng chứng thanh toán.
- Đến khi backend VNPay được cấu hình, nút thanh toán phải giữ trạng thái không khả dụng; không giả lập phản hồi thành công.

## Endpoint cần có

| Phương thức | Đường dẫn | Mô tả |
|---|---|---|
| GET | `/availability?checkIn=YYYY-MM-DD&checkOut=YYYY-MM-DD&guests=2&rooms=1` | Trả `{checkIn, checkOut, nights, pricing:{serviceRate, vatRate}, types:[{id,name,area,capacity,bed,price,image,view,desc,amenities[],available,totalActive}]}` |
| POST | `/bookings` | Body `{checkIn, checkOut, guests, promo, items:[{typeId,qty}], customer:{name,phone,email,idNumber,dob}}`. Kiểm tra lại + giữ phòng trong **một transaction**. Lỗi trùng lịch: HTTP 409 `{code:"SOLD_OUT", message, short:[{typeId,name,available,requested}]}` |
| GET | `/bookings/:code` | Chi tiết đơn (có `status`, `holdUntil`, `totals`, `items`) |
| POST | `/bookings/:code/payments/vnpay` | Tạo yêu cầu Hosted Checkout từ đơn đang chờ; trả `{redirectUrl}` dùng HTTPS. Không đánh dấu đơn đã thanh toán tại bước tạo URL. |
| POST | `/bookings/:code/cancel` | Hủy đơn, trả phòng về kho |
| GET | `/bookings?email=` | Lịch sử đặt phòng của khách (nên lấy theo phiên đăng nhập) |
| GET | `/admin/overview` | `{available, occupied, reserved, cancelled, recent[]}` cho Dashboard lễ tân (cần quyền admin) |

Mã lỗi giao diện đang xử lý: `SOLD_OUT`, `CAPACITY`, `HOLD_EXPIRED`, `BAD_*`, `NOT_FOUND`.
**Máy chủ phải tự kiểm tra lại mọi dữ liệu và tự tính tiền**, không tin số liệu từ trình duyệt.

`POST /bookings` phải xác thực lại `customer.name`, `phone`, `email`, `idNumber` và `dob` phía server. Validation JavaScript chỉ cải thiện trải nghiệm, không phải biện pháp kiểm soát bảo mật thay thế cho backend.

## Lược đồ CSDL gợi ý (PostgreSQL)

```sql
CREATE TABLE room_types (
  id text PRIMARY KEY, name text NOT NULL, area_m2 int, capacity int NOT NULL,
  bed text, price_per_night int NOT NULL, image_url text, view text, description text,
  amenities text[] NOT NULL DEFAULT '{}'
);
CREATE TABLE rooms (
  id text PRIMARY KEY,                       -- ví dụ DEL-601
  type_id text NOT NULL REFERENCES room_types(id),
  status text NOT NULL DEFAULT 'active'      -- active | maintenance
);
CREATE TABLE bookings (
  code text PRIMARY KEY, status text NOT NULL,   -- pending | confirmed | paid | cancelled
  customer jsonb NOT NULL, check_in date NOT NULL, check_out date NOT NULL,
  guests int NOT NULL, hold_until timestamptz, total int NOT NULL,
  created_at timestamptz NOT NULL DEFAULT now(),
  CHECK (check_out > check_in)
);
CREATE TABLE booking_rooms (
  booking_code text REFERENCES bookings(code) ON DELETE CASCADE,
  room_id text REFERENCES rooms(id),
  stay daterange NOT NULL,                   -- [check_in, check_out)
  active boolean NOT NULL DEFAULT true,      -- false khi hủy / hết hạn giữ chỗ
  PRIMARY KEY (booking_code, room_id),
  -- Chặn trùng lịch ngay ở mức CSDL (cần: CREATE EXTENSION btree_gist)
  EXCLUDE USING gist (room_id WITH =, stay WITH &&) WHERE (active)
);
```

## Truy vấn phòng trống theo hạng

```sql
SELECT t.*, count(r.id) FILTER (WHERE r.status = 'active') AS total_active,
       count(r.id) FILTER (
         WHERE r.status = 'active' AND NOT EXISTS (
           SELECT 1 FROM booking_rooms br JOIN bookings b ON b.code = br.booking_code
           WHERE br.room_id = r.id AND br.active
             AND b.status <> 'cancelled'
             AND NOT (b.status = 'pending' AND b.hold_until < now())
             AND br.stay && daterange($1::date, $2::date)   -- [checkIn, checkOut)
         )) AS available
FROM room_types t LEFT JOIN rooms r ON r.type_id = t.id
GROUP BY t.id;
```

## Chống đặt trùng khi nhiều khách thao tác cùng lúc

Trong `POST /bookings`, mở transaction rồi:

1. `SELECT id FROM rooms WHERE type_id = $1 AND status='active' AND <không trùng lịch> ORDER BY id LIMIT $qty FOR UPDATE SKIP LOCKED;`
2. Nếu số dòng lấy được `< qty` → `ROLLBACK`, trả 409 `SOLD_OUT`.
3. `INSERT` vào `bookings` và `booking_rooms`. Ràng buộc `EXCLUDE` là lớp bảo vệ cuối cùng: nếu hai giao dịch vẫn đụng nhau, một bên sẽ lỗi và được trả về `SOLD_OUT`.
4. Một job định kỳ (hoặc truy vấn ở trên) bỏ qua các đơn `pending` quá `hold_until`.

## Xác thực độ tuổi (từ đủ 18 tuổi)

- Giao diện thu **ngày sinh**; `POST /bookings` nhận thêm `customer.dob` (YYYY-MM-DD). **Máy chủ phải tự kiểm tra lại** và trả HTTP 422 với `code`: `UNDERAGE`, `ID_MISMATCH`, `BAD_DOB`.
- Với CCCD 12 số, năm sinh nằm ở chữ số thứ 5-6 (chữ số thứ 4 cho biết thế kỷ), dùng để đối chiếu với ngày sinh khai báo. CMND 9 số không chứa năm sinh.
- Cổng dichvucong.dancuquocgia.gov.vn **không cung cấp API tra cứu công khai** theo số CCCD, nên website không thể tự tra từ đó. Muốn xác thực chính thức, tích hợp qua backend một dịch vụ eKYC/VNeID được cấp phép (VNPT eKYC, FPT.AI, VNeID...) và chỉ lưu kết quả cần thiết, có đồng ý của khách theo Nghị định 13/2023.
- Lễ tân nên đối chiếu CCCD gốc khi nhận phòng.
