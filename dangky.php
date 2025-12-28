<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

include 'database.php';
$msg = "";

function clean($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// xu ly fom
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $hoten  = clean($_POST['HoVaTen']);
    $tendn  = clean($_POST['TenDangNhap']);
    $matkhau_raw = $_POST['MatKhau'];
    $sdt    = clean($_POST['SoDienThoai']);
    $email  = clean($_POST['Email']);
    $diachi = clean($_POST['DiaChi']);

    if (strlen($matkhau_raw) < 6) {
        $msg = "<div class='msg error'>Mật khẩu phải ≥ 6 ký tự</div>";
    }
    elseif (!preg_match('/^(0[3|5|7|8|9])[0-9]{8}$/', $sdt)) {
        $msg = "<div class='msg error'>Số điện thoại không hợp lệ!</div>";
    }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = "<div class='msg error'>Email không hợp lệ!</div>";
    }
    else {
        $check = $conn->prepare("SELECT MaNguoiDung FROM nguoi_dung 
                                WHERE TenDangNhap=? OR Email=? OR SoDienThoai=?");
        $check->bind_param("sss", $tendn, $email, $sdt);
        $check->execute();

        if ($check->get_result()->num_rows > 0) {
            $msg = "<div class='msg error'>Tên đăng nhập, Email hoặc SĐT đã tồn tại</div>";
        }
        else {
            $token  = bin2hex(random_bytes(16));
            $expire = date("Y-m-d H:i:s", time() + 600);
            $matkhau = password_hash($matkhau_raw, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("
                INSERT INTO nguoi_dung
                (HoVaTen, SoDienThoai, Email, TenDangNhap, MatKhau, DiaChi, VaiTro, verify_token, verify_expire, is_verified)
                VALUES (?, ?, ?, ?, ?, ?, 'customer', ?, ?, 0)
            ");
            $stmt->bind_param("ssssssss", $hoten, $sdt, $email, $tendn, $matkhau, $diachi, $token, $expire);

            if ($stmt->execute()) {

               $link = "https://ngohuedinh.id.vn/xacminh.php?token=$token";

                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'ngohuedinh@gmail.com';
                    $mail->Password = 'zpgcmuithmirqbnw';
                    $mail->SMTPSecure = 'tls';
                    $mail->Port = 587;
                    $mail->CharSet = 'UTF-8';

                    $mail->setFrom('ngohuedinh@gmail.com', 'Rửa Xe D2AUTO');
                    $mail->addAddress($email, $hoten);

                    $mail->isHTML(true);
                    $mail->Subject = "Xác Minh Tài Khoản Rửa Xe D2AUTO";
                    $mail->Body = "
                        <h3>Chào $hoten,</h3>
                        <p>Bấm vào link để kích hoạt tài khoản:</p>
                        <a href='$link'>$link</a>
                        <p> Link có hiệu lực 10 phút.</p>
                    ";

                    $mail->send();

                    $msg = "<div class='msg success'>✔ Kiểm tra email để xác minh tài khoản!</div>";

                } catch (Exception $e) {
                    $msg = "<div class='msg error'> Lỗi gửi email: {$mail->ErrorInfo}</div>";
                }
            }
            
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Đăng ký</title>

<style>
body {
    background:#f4f6f9;
    font-family: Segoe UI, sans-serif;
}

.container {
    width:400px;
    margin:60px auto;
    background:white;
    padding:25px;
    border-radius:12px;
    box-shadow:0 4px 20px #0002;
}

input, button {
    width:100%;
    padding:12px;
    margin-bottom:12px;
    border-radius:8px;
    border:1px solid #ddd;
}

button {
    background:#2563eb;
    color:white;
    border:none;
    cursor:pointer;
    font-weight:bold;
}
button:hover { background:#1d4ed8; }

.msg {
    padding:10px;
    border-radius:8px;
    text-align:center;
    margin-bottom:15px;
}
.msg.error { background:#fee2e2; color:#991b1b; }
.msg.success { background:#d1fae5; color:#065f46; }
</style>

</head>

<body>
<div class="container">

<h2 style="text-align:center;">Đăng ký</h2>

<?= $msg ?>

<form method="POST">
    <input type="text" name="HoVaTen" placeholder="Họ và tên" required>
    <input type="text" name="TenDangNhap" placeholder="Tên đăng nhập" required>
    <input type="password" name="MatKhau" placeholder="Mật khẩu" required>
    <input type="text" name="SoDienThoai" placeholder="Số điện thoại" required>
    <input type="email" name="Email" placeholder="Email" required>
    <input type="text" name="DiaChi" placeholder="Địa chỉ" required>
    <button>Đăng ký</button>
</form>

<div style="text-align:center;margin-top:10px;">
    <a href="dangnhap.php">← Quay lại đăng nhập</a>
</div>

</div>
</body>
</html>
