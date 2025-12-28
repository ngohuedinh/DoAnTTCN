<?php
session_start();
require_once 'database.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

$msg = "";
$step = "request";

// --- BƯỚC 1: XỬ LÝ GỬI EMAIL ---
if (isset($_POST['send_reset'])) {
    $email = trim($_POST['Email']);

    if (empty($email)) {
        $msg = "<div class='msg error'>❌ Vui lòng nhập email!</div>";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = "<div class='msg error'>❌ Email không hợp lệ!</div>";
    } else {
        // Kiểm tra email có tồn tại không
        $stmt = $conn->prepare("SELECT * FROM nguoi_dung WHERE Email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 0) {
            $msg = "<div class='msg error'>❌ Email không tồn tại trong hệ thống!</div>";
        } else {
            $user = $result->fetch_assoc();

            // Tạo token reset
            $token = bin2hex(random_bytes(32));
            $expire = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Lưu token vào database
            $update = $conn->prepare("UPDATE nguoi_dung SET reset_token = ?, reset_expire = ? WHERE Email = ?");
            $update->bind_param("sss", $token, $expire, $email);
            $update->execute();

            // Tạo link reset
            $link = "https://ngohuedinh.id.vn/reset_password.php?token=" . $token;

            // --- GỬI EMAIL BẰNG PHPMailer ---
            $mail = new PHPMailer(true);

            try {
                // Cấu hình SMTP
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'ngohuedinh@gmail.com';
                $mail->Password   = 'zpgcmuithmirqbnw';     
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;
                $mail->CharSet    = 'UTF-8';

                // Người gửi
                $mail->setFrom('noreply@d2auto.com', 'D2AUTO');
                
                // Người nhận
                $mail->addAddress($email, $user['HoVaTen']);

                // Nội dung email
                $mail->isHTML(true);
                $mail->Subject = 'Đặt lại mật khẩu - D2AUTO';
                $mail->Body    = "
                <html>
                <body style='font-family: Arial, sans-serif;'>
                    <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                        <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                                   color: white; padding: 20px; text-align: center; border-radius: 8px;'>
                            <h2>🔑 Đặt lại mật khẩu</h2>
                        </div>
                        <div style='background: #f9fafb; padding: 30px; margin-top: 20px;'>
                            <p>Xin chào <strong>" . htmlspecialchars($user['HoVaTen']) . "</strong>,</p>
                            <p>Bạn nhận được email này vì đã yêu cầu đặt lại mật khẩu tại D2AUTO.</p>
                            <p style='text-align: center; margin: 30px 0;'>
                                <a href='" . $link . "' 
                                   style='display: inline-block; background: #667eea; color: white; 
                                          padding: 12px 30px; text-decoration: none; border-radius: 6px; 
                                          font-weight: 600;'>
                                    Đặt lại mật khẩu
                                </a>
                            </p>
                            <p>Hoặc copy link này:</p>
                            <p style='background: #e5e7eb; padding: 10px; border-radius: 4px; word-break: break-all; font-size: 12px;'>
                                " . $link . "
                            </p>
                            <p><strong>⏰ Link hết hạn sau 1 giờ.</strong></p>
                        </div>
                    </div>
                </body>
                </html>
                ";

                $mail->send();
                $msg = "<div class='msg success'>
                    ✅ Link đặt lại mật khẩu đã được gửi đến <strong>" . htmlspecialchars($email) . "</strong><br>
                    Vui lòng kiểm tra hộp thư.
                </div>";
            } catch (Exception $e) {
                $msg = "<div class='msg error'>❌ Không thể gửi email: " . $mail->ErrorInfo . "</div>";
            }
        }
    }
}

// --- BƯỚC 2: XỬ LÝ ĐỔI MẬT KHẨU ---
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = $_GET['token'];

    $stmt = $conn->prepare("SELECT * FROM nguoi_dung WHERE reset_token = ? LIMIT 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        $step = "error";
        $msg = "<div class='msg error'>❌ Token không tồn tại!</div>";
    } else {
        $user = $result->fetch_assoc();

        if (!empty($user['reset_expire']) && strtotime($user['reset_expire']) < time()) {
            $step = "error";
            $msg = "<div class='msg error'>❌ Link đã hết hạn!</div>";
        } else {
            $step = "reset";

            if (isset($_POST['change_password'])) {
                $newpass = trim($_POST['MatKhau']);
                $confirm = trim($_POST['XacNhanMatKhau']);

                if (empty($newpass)) {
                    $msg = "<div class='msg error'>❌ Vui lòng nhập mật khẩu!</div>";
                } elseif (strlen($newpass) < 6) {
                    $msg = "<div class='msg error'>❌ Mật khẩu phải ≥ 6 ký tự!</div>";
                } elseif ($newpass !== $confirm) {
                    $msg = "<div class='msg error'>❌ Mật khẩu xác nhận không khớp!</div>";
                } else {
                    $hashed = password_hash($newpass, PASSWORD_DEFAULT);

                    $update = $conn->prepare("
                        UPDATE nguoi_dung 
                        SET MatKhau = ?, reset_token = NULL, reset_expire = NULL 
                        WHERE reset_token = ?
                    ");
                    $update->bind_param("ss", $hashed, $token);
                    $update->execute();

                    if ($update->affected_rows > 0) {
                        $step = "success";
                        $msg = "<div class='msg success'>
                            ✅ Mật khẩu đã cập nhật thành công!<br>
                            <a href='dangnhap.php' style='color:#065f46;font-weight:600;'>Đăng nhập →</a>
                        </div>";
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $step == 'reset' ? 'Đặt lại mật khẩu' : 'Quên mật khẩu' ?> - D2AUTO</title>

<style>
* { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: "Segoe UI", Arial, sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.container { max-width: 450px; width: 100%; }

.card {
    background: white;
    padding: 40px;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
}

.icon {
    text-align: center;
    font-size: 64px;
    margin-bottom: 20px;
}

h2 {
    text-align: center;
    color: #1f2937;
    margin-bottom: 10px;
}

.subtitle {
    text-align: center;
    color: #6b7280;
    margin-bottom: 30px;
    font-size: 14px;
    line-height: 1.5;
}

.msg {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    text-align: center;
    line-height: 1.6;
}

.msg.error {
    background: #fee2e2;
    color: #991b1b;
}

.msg.success {
    background: #d1fae5;
    color: #065f46;
}

label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #374151;
}

input {
    width: 100%;
    padding: 12px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 15px;
    margin-bottom: 15px;
}

input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

button {
    width: 100%;
    padding: 14px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
}

button:hover { transform: translateY(-2px); }

.links {
    text-align: center;
    margin-top: 20px;
}

.links a {
    color: #667eea;
    text-decoration: none;
    font-weight: 600;
}

.info-box {
    background: #f3f4f6;
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 20px;
    font-size: 13px;
    color: #4b5563;
}
</style>
</head>
<body>

<div class="container">
    <div class="card">
        
        <?php if ($step == 'request'): ?>
        <div class="icon">🔐</div>
        <h2>Quên mật khẩu?</h2>
        <p class="subtitle">Nhập email đã đăng ký, chúng tôi sẽ gửi link đặt lại mật khẩu</p>

        <?= $msg ?>

        <form method="POST">
            <label>Email đăng ký</label>
            <input type="email" name="Email" placeholder="example@gmail.com" required autofocus>
            <button type="submit" name="send_reset">📧 Gửi link đặt lại mật khẩu</button>
        </form>

        <div class="links">
            <a href="dangnhap.php">← Quay lại đăng nhập</a>
        </div>

        <?php elseif ($step == 'reset'): ?>
        <div class="icon">🔑</div>
        <h2>Đặt lại mật khẩu</h2>
        <p class="subtitle">Nhập mật khẩu mới cho tài khoản</p>

        <?= $msg ?>

        <form method="POST">
            <label>Mật khẩu mới</label>
            <input type="password" name="MatKhau" required minlength="6" autofocus>

            <label>Xác nhận mật khẩu</label>
            <input type="password" name="XacNhanMatKhau" required minlength="6">

            <div class="info-box">
                <strong>Yêu cầu:</strong> Ít nhất 6 ký tự
            </div>

            <button type="submit" name="change_password">🔒 Cập nhật mật khẩu</button>
        </form>

        <div class="links">
            <a href="dangnhap.php">← Quay lại</a>
        </div>

        <?php elseif ($step == 'error'): ?>
        <div class="icon">⚠️</div>
        <h2>Có lỗi</h2>
        <?= $msg ?>
        <div class="links">
            <a href="reset_password.php">← Yêu cầu link mới</a>
        </div>

        <?php elseif ($step == 'success'): ?>
        <div class="icon">✅</div>
        <h2>Hoàn tất!</h2>
        <?= $msg ?>
        <?php endif; ?>

    </div>
</div>

</body>
</html>