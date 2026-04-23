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

3. Import file [database.sql](/abs/path/c:/xampp/htdocs/badminton-manager/database.sql:1)

`database.sql` là file SQL hoàn chỉnh. Không cần chạy thêm file migration nào khác.

## 5. Cấu hình database

Mở [config/database.php](/abs/path/c:/xampp/htdocs/badminton-manager/config/database.php:1) và sửa lại cho đúng máy đích.

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

Dựa trên mẫu [config/email.php.example](/abs/path/c:/xampp/htdocs/badminton-manager/config/email.php.example:1)

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

Dựa trên mẫu [config/payment.php.example](/abs/path/c:/xampp/htdocs/badminton-manager/config/payment.php.example:1)

Ví dụ với `MB Bank`:

```php
<?php

return [
    'bank_id' => '970422',
    'account_no' => '0941473515',
    'account_name' => 'NGUYEN TRONG PHUC',
    'template' => 'compact2',
];
```

Lưu ý:

- `bank_id` là mã ngân hàng VietQR/BIN, không phải số tài khoản
- `account_no` là số tài khoản nhận tiền

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

Sau khi import [database.sql](/abs/path/c:/xampp/htdocs/badminton-manager/database.sql:1), tài khoản admin mặc định là:

- Tên đăng nhập thực tế: nhập `admin` ở ô email hoặc số điện thoại
- Email: `admin@badminton.local`
- Mật khẩu: `admin123`

Lưu ý: hệ thống hiện không có cột `username` riêng. Giá trị `admin` đang được dùng như thông tin đăng nhập trong ô `email/số điện thoại`.

## 10. Tạo lại admin khi cần

Project có sẵn file [make_admin.php](/abs/path/c:/xampp/htdocs/badminton-manager/make_admin.php:1).

Chạy:

```text
http://localhost/badminton-manager/make_admin.php
```

Sau đó đăng nhập bằng:

- Tên đăng nhập: `admin`
- Email: `admin@badminton.local`
- Mật khẩu: `admin123`

## 11. Chức năng chính

- Đăng ký tài khoản khách hàng
- Xác minh tài khoản qua email
- Đăng nhập bằng email hoặc số điện thoại
- Cập nhật hồ sơ khách hàng
- Đặt sân theo ngày và khung giờ
- Thanh toán bằng tiền mặt hoặc chuyển khoản QR
- Khách xác nhận đã chuyển khoản
- Admin xác nhận thanh toán thủ công
- Xem lịch sử đặt sân
- Quản lý khách hàng và báo cáo cơ bản

## 12. Lưu ý khi chuyển sang máy khác

- Kiểm tra lại [config/database.php](/abs/path/c:/xampp/htdocs/badminton-manager/config/database.php:1)
- Tạo lại `config/email.php` và `config/payment.php`
- Không cần chạy migration, chỉ cần import `database.sql`
- Không commit `config/email.php` và `config/payment.php`

Các file bí mật này đã được ignore trong [.gitignore](/abs/path/c:/xampp/htdocs/badminton-manager/.gitignore:1).
