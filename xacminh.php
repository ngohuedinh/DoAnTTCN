<?php
require_once 'database.php';

$msg = "";
$success = false;


if (!isset($_GET['token']) || empty($_GET['token'])) {
    $msg = "Token không hợp lệ!";
} else {
    $token = $_GET['token'];

   
    $stmt = $conn->prepare("SELECT * FROM nguoi_dung WHERE verify_token = ? LIMIT 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        $msg = "Token không tồn tại hoặc đã được sử dụng!";
    } else {
        $user = $result->fetch_assoc();

       
        if ($user['is_verified'] == 1) {
            $msg = "Tài khoản này đã được xác minh trước đó!";
            $success = true; // Vẫn cho phép đăng nhập
        }
       
        elseif (!empty($user['verify_expire']) && strtotime($user['verify_expire']) < time()) {
            $msg = "Token đã hết hạn! Vui lòng đăng ký lại hoặc yêu cầu gửi lại email xác minh.";
        }
        
        else {
            $updateStmt = $conn->prepare("
                UPDATE nguoi_dung
                SET is_verified = 1, verify_token = NULL, verify_expire = NULL
                WHERE verify_token = ?
            ");
            $updateStmt->bind_param("s", $token);
            $updateStmt->execute();

            if ($updateStmt->affected_rows > 0) {
                $msg = "Tài khoản của bạn đã được xác minh thành công!";
                $success = true;
            } else {
                $msg = "Có lỗi xảy ra! Vui lòng thử lại.";
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
<title>Xác minh tài khoản - D2AUTO</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
body {
    font-family: 'Segoe UI', Arial, sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.verify-container {
    max-width: 500px;
    width: 100%;
    background: white;
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    padding: 50px 40px;
    text-align: center;
}
.icon-box {
    width: 100px;
    height: 100px;
    margin: 0 auto 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
}
.icon-success {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    color: white;
    animation: successPulse 1.5s ease-in-out;
}
.icon-error {
    background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
    color: white;
    animation: shake 0.5s;
}
.message-title {
    font-size: 1.75rem;
    font-weight: 700;
    margin-bottom: 15px;
}
.message-text {
    color: #666;
    margin-bottom: 30px;
    line-height: 1.6;
}
.btn-primary {
    background: linear-gradient(90deg, #667eea, #764ba2);
    border: none;
    padding: 12px 30px;
    border-radius: 50px;
    font-weight: 600;
    transition: transform 0.2s;
}
.btn-primary:hover {
    transform: translateY(-2px);
}
@keyframes successPulse {
    0% { transform: scale(0); opacity: 0; }
    50% { transform: scale(1.1); }
    100% { transform: scale(1); opacity: 1; }
}
@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-10px); }
    75% { transform: translateX(10px); }
}
</style>
</head>
<body>

<div class="verify-container">
    <?php if ($success): ?>
        <div class="icon-box icon-success">
            <i class="fas fa-check-circle"></i>
        </div>
        <h1 class="message-title text-success">Thành công!</h1>
        <p class="message-text"><?= htmlspecialchars($msg) ?></p>
        <a href="dangnhap.php" class="btn btn-primary">
            <i class="fas fa-sign-in-alt me-2"></i>Đăng nhập ngay
        </a>
    <?php else: ?>
        <div class="icon-box icon-error">
            <i class="fas fa-times-circle"></i>
        </div>
        <h1 class="message-title text-danger">Có lỗi xảy ra!</h1>
        <p class="message-text"><?= htmlspecialchars($msg) ?></p>
        <div class="d-flex gap-2 justify-content-center flex-wrap">
            <a href="dangky.php" class="btn btn-outline-primary">
                <i class="fas fa-user-plus me-2"></i>Đăng ký lại
            </a>
            <a href="dangnhap.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Về trang đăng nhập
            </a>
        </div>
    <?php endif; ?>

    <hr class="my-4">
    <small class="text-muted">
        <i class="fas fa-shield-alt me-1"></i>
        Hệ thống đặt lịch rửa xe D2AUTO
    </small>
</div>

</body>
</html>
