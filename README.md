# Badminton Manager

Ứng dụng quản lý sân cầu lông viết bằng PHP thuần và MySQL, chạy trên XAMPP.

## 1. Yêu cầu môi trường

- Windows
- XAMPP
- Apache
- MySQL hoặc MariaDB
- PHP 8.0 trở lên

Khuyến nghị cài tại:

```text
C:\xampp
```

## 2. Chép source code

Đặt project vào:

```text
C:\xampp\htdocs\badminton-manager
```

## 3. Bật dịch vụ

Mở XAMPP Control Panel và start:

- `Apache`
- `MySQL`

## 4. Tạo database

1. Mở `http://localhost/phpmyadmin`
2. Tạo database:

```sql
badminton_manager
```

3. Import file `database.sql`

`database.sql` là file SQL hoàn chỉnh. Không cần chạy thêm file migration nào khác.

## 5. Cấu hình database

Mở `config/database.php` và sửa lại cho đúng máy đích.

Ví dụ:

```php
<?php
$host = "localhost";
$username = "root";
$password = "";
$database = "badminton_manager";

$conn = mysqli_connect("127.0.0.1:3306", $username, $password, $database);

if (!$conn) {
    die("Kết nối thất bại: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");
```

## 6. Cấu hình email xác minh

Tạo file:

```text
config/email.php
```

Dựa trên mẫu `config/email.php.example`

Ví dụ:

```php
<?php

return [
    'transport' => 'smtp',
    'host' => 'smtp.gmail.com',
    'port' => 587,
    'encryption' => 'tls',
    'username' => 'your_email@gmail.com',
    'password' => 'your_app_password',
    'from_email' => 'your_email@gmail.com',
    'from_name' => 'Badminton Manager',
    'timeout' => 20,
];
```

Nếu email gửi lỗi, hệ thống sẽ ghi link xác minh vào:

```text
storage/email_verification.log
```

## 7. Cấu hình QR chuyển khoản

Tạo file:

```text
config/payment.php
```

Dựa trên mẫu `config/payment.php.example`

Ví dụ:

```php
<?php

return [
    'bank_id' => '970416',
    'account_no' => '0123456789',
    'account_name' => 'NGUYEN VAN A',
    'template' => 'compact2',
    'webhook_api_key' => 'SEPAY_WEBHOOK_KEY_123456',
];
```

Lưu ý:

- `bank_id` là mã ngân hàng VietQR/BIN, không phải số tài khoản
- `account_no` là số tài khoản nhận tiền
- `account_name` nên ghi in hoa, không dấu
- `webhook_api_key` là khóa dùng để xác thực webhook SePay

## 8. Chạy project

Mở:

```text
http://localhost/badminton-manager
```

Trang đăng nhập:

```text
http://localhost/badminton-manager/auth/login.php
```

## 9. Tài khoản admin mặc định

Sau khi import `database.sql`, tài khoản admin mặc định là:

- Tên đăng nhập thực tế: nhập `admin` ở ô email hoặc số điện thoại
- Email: `admin@badminton.local`
- Mật khẩu: `admin123`

Lưu ý: hệ thống hiện không có cột `username` riêng. Giá trị `admin` đang được dùng như thông tin đăng nhập trong ô `email/số điện thoại`.

## 10. Tạo lại admin khi cần

Project có sẵn file `make_admin.php`.

Chạy:

```text
http://localhost/badminton-manager/make_admin.php
```

Sau đó đăng nhập bằng:

- Tên đăng nhập: `admin`
- Email: `admin@badminton.local`
- Mật khẩu: `admin123`

## 11. Hướng dẫn cấu hình SePay để tự xác nhận thanh toán

### 11.1. Luồng hoạt động

Khi khách hàng chọn `Chuyển khoản` và bấm `Đặt sân`, hệ thống sẽ:

1. Tạo booking mới
2. Sinh mã thanh toán dạng:

```text
BK000001
```

3. Hiển thị mã QR để khách quét
4. Khách chuyển khoản đúng số tiền và đúng nội dung
5. SePay gửi webhook về hệ thống
6. Hệ thống tự cập nhật `payment_status = paid`
7. Giao diện tự đổi từ QR sang trạng thái `Đã nhận thanh toán`

### 11.2. Chạy ngrok để tạo public URL

Vì SePay không gọi được trực tiếp vào `localhost`, khi test local cần chạy ngrok.

Ví dụ:

```bash
ngrok http 80
```

Sau khi chạy, ngrok sẽ tạo một URL public, ví dụ:

```text
https://abc123.ngrok-free.app
```

Giữ cửa sổ ngrok luôn mở trong suốt thời gian test webhook.

### 11.3. Cấu hình nhận diện mã thanh toán trên SePay

Trong SePay, vào phần cấu hình công ty và bật nhận diện mã thanh toán.

Thiết lập cấu trúc mã thanh toán như sau:

- Tiền tố: `BK`
- Hậu tố: `6 ký tự`
- Kiểu: `Số nguyên`

Ví dụ mã hợp lệ:

```text
BK000007
```

### 11.4. Tạo webhook trên SePay

Tạo một webhook mới với cấu hình:

- Sự kiện: `Có tiền vào`
- Bỏ qua nếu nội dung giao dịch không có code thanh toán: `Có`
- Là webhook xác thực thanh toán: `Đúng`
- Kiểu chứng thực: `API Key`
- Request content type: `application/json`
- Trạng thái: `Kích hoạt`

### 11.5. URL webhook

Điền URL:

```text
https://YOUR_NGROK_URL/badminton-manager/sepay_webhook.php
```

Ví dụ:

```text
https://abc123.ngrok-free.app/badminton-manager/sepay_webhook.php
```

### 11.6. API Key webhook

Giá trị API Key trong SePay phải giống hệt giá trị:

```php
'webhook_api_key' => 'SEPAY_WEBHOOK_KEY_123456'
```

trong file `config/payment.php`.

### 11.7. File liên quan đến SePay

Các file chính của luồng SePay:

- `customer/booking.php`
- `customer/check_payment_status.php`
- `sepay_webhook.php`
- `config/payment.php`
- `config/payment.php.example`

## 12. Cách test SePay trên local

1. Chạy XAMPP
2. Chạy ngrok
3. Mở project local
4. Tạo booking mới bằng hình thức chuyển khoản
5. Quét QR và chuyển khoản đúng số tiền
6. Chờ SePay gửi webhook
7. Giao diện trang đặt sân sẽ tự đổi sang `Đã nhận thanh toán`
8. Vào `customer/my_bookings.php` để kiểm tra trạng thái booking

Nếu webhook không chạy, kiểm tra lại:

- Cửa sổ ngrok có request `POST` vào `sepay_webhook.php` hay không
- `config/payment.php`
- API key trong SePay có khớp với `config/payment.php` hay không
- Nội dung chuyển khoản có đúng dạng `BK000xxx` hay không

## 13. Chức năng chính

- Đăng ký tài khoản khách hàng
- Xác minh tài khoản qua email
- Đăng nhập bằng email hoặc số điện thoại
- Cập nhật hồ sơ khách hàng
- Đặt sân theo ngày và khung giờ
- Thanh toán bằng tiền mặt hoặc chuyển khoản QR
- Tự xác nhận thanh toán qua SePay webhook
- Xem lịch sử đặt sân
- Quản lý khách hàng và báo cáo cơ bản

## 14. Lưu ý khi chuyển sang máy khác

- Kiểm tra lại `config/database.php`
- Tạo lại `config/email.php` và `config/payment.php`
- Không cần chạy migration, chỉ cần import `database.sql`
- Nếu test webhook local, phải chạy lại ngrok và cập nhật URL webhook trong SePay
- Không commit `config/email.php` và `config/payment.php`

Các file bí mật này đã được ignore trong `.gitignore`.
