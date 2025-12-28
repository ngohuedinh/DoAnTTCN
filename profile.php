<?php
session_start();
require_once 'database.php';

if (!isset($_SESSION['TenDangNhap'])) {
    header("Location: dangnhap.php");
    exit();
}

$username = $_SESSION['TenDangNhap'];
$msg = "";

// Xử lý thông báo từ session
if (isset($_SESSION['flash_msg'])) {
    $msg = $_SESSION['flash_msg'];
    unset($_SESSION['flash_msg']);
}

/* LẤY THÔNG TIN USER */
$stmt = $conn->prepare("SELECT * FROM nguoi_dung WHERE TenDangNhap = ? LIMIT 1");
$stmt->bind_param("s", $username);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    session_destroy();
    header("Location: dangnhap.php");
    exit();
}

/* CẬP NHẬT THÔNG TIN */
if (isset($_POST['update_info'])) {
    $hoten  = trim($_POST['HoVaTen']);
    $sdt    = trim($_POST['SoDienThoai']);
    $diachi = trim($_POST['DiaChi']);

    if (empty($hoten)) {
        $msg = "<div class='msg error'>Họ và tên không được để trống!</div>";
    } elseif (!preg_match('/^(0[3|5|7|8|9])[0-9]{8}$/', $sdt)) {
        $msg = "<div class='msg error'>Số điện thoại không hợp lệ!</div>";
    } else {
        $checkSdt = $conn->prepare("SELECT MaNguoiDung FROM nguoi_dung WHERE SoDienThoai = ? AND TenDangNhap != ?");
        $checkSdt->bind_param("ss", $sdt, $username);
        $checkSdt->execute();
        
        if ($checkSdt->get_result()->num_rows > 0) {
            $msg = "<div class='msg error'>Số điện thoại đã được sử dụng!</div>";
        } else {
            $up = $conn->prepare("UPDATE nguoi_dung SET HoVaTen = ?, SoDienThoai = ?, DiaChi = ? WHERE TenDangNhap = ?");
            $up->bind_param("ssss", $hoten, $sdt, $diachi, $username);
            $up->execute();

            $_SESSION['flash_msg'] = "<div class='msg success'>✓ Cập nhật thành công!</div>";
            header("Location: profile.php");
            exit();
        }
    }
}

/* ĐỔI MẬT KHẨU */
if (isset($_POST['change_password'])) {
    $oldPass = $_POST['MatKhauCu'];
    $newPass = trim($_POST['MatKhauMoi']);
    $confirmPass = trim($_POST['XacNhanMatKhau']);

    if (!password_verify($oldPass, $user['MatKhau'])) {
        $msg = "<div class='msg error'>Mật khẩu cũ không đúng!</div>";
    } elseif (strlen($newPass) < 8) {
        $msg = "<div class='msg error'>Mật khẩu mới phải có ít nhất 8 ký tự!</div>";
    } elseif ($newPass !== $confirmPass) {
        $msg = "<div class='msg error'>Mật khẩu xác nhận không khớp!</div>";
    } else {
        $hashedPass = password_hash($newPass, PASSWORD_BCRYPT);
        $upPass = $conn->prepare("UPDATE nguoi_dung SET MatKhau = ? WHERE TenDangNhap = ?");
        $upPass->bind_param("ss", $hashedPass, $username);
        $upPass->execute();

        $_SESSION['flash_msg'] = "<div class='msg success'>✓ Đổi mật khẩu thành công!</div>";
        header("Location: profile.php");
        exit();
    }
}

/* XÓA TÀI KHOẢN */
if (isset($_POST['delete_acc'])) {
    $del = $conn->prepare("DELETE FROM nguoi_dung WHERE TenDangNhap = ?");
    $del->bind_param("s", $username);
    $del->execute();

    session_destroy();
    header("Location: dangky.php?msg=deleted");
    exit();
}

// Đếm số lịch đã đặt
$countBookings = $conn->prepare("SELECT COUNT(*) as total FROM dat_lich WHERE MaNguoiDung = ?");
$countBookings->bind_param("i", $user['MaNguoiDung']);
$countBookings->execute();
$totalBookings = $countBookings->get_result()->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Trang cá nhân - D2AUTO</title>

<style>
body {
    background: #f4f6f9;
    font-family: "Segoe UI", Arial, sans-serif;
    margin: 0;
    padding: 20px;
}

.container {
    max-width: 700px;
    margin: 0 auto;
}

.card {
    background: white;
    padding: 25px;
    margin-bottom: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

h3 {
    color: #2563eb;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #e5e7eb;
}

.info-row {
    display: flex;
    padding: 10px 0;
    border-bottom: 1px solid #f3f4f6;
}

.info-row label {
    font-weight: 600;
    color: #666;
    width: 150px;
}

.info-row span {
    color: #333;
}

input, button {
    width: 100%;
    padding: 12px;
    margin-bottom: 12px;
    border-radius: 8px;
    border: 1px solid #d1d5db;
    font-size: 15px;
}

input:focus {
    outline: none;
    border-color: #2563eb;
}

button {
    background: #2563eb;
    color: white;
    border: none;
    cursor: pointer;
    font-weight: 600;
}

button:hover {
    background: #1d4ed8;
}

.btn-warning {
    background: #f59e0b;
}

.btn-warning:hover {
    background: #d97706;
}

.btn-danger {
    background: #ef4444;
}

.btn-danger:hover {
    background: #dc2626;
}

.msg {
    padding: 12px;
    border-radius: 8px;
    text-align: center;
    margin-bottom: 15px;
}

.msg.error {
    background: #fee2e2;
    color: #991b1b;
}

.msg.success {
    background: #d1fae5;
    color: #065f46;
}

.stats {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
}

.stat-box {
    flex: 1;
    background: #f3f4f6;
    padding: 20px;
    border-radius: 10px;
    text-align: center;
}

.stat-number {
    font-size: 2rem;
    font-weight: bold;
    color: #2563eb;
}

.stat-label {
    color: #6b7280;
    font-size: 14px;
}

.back-btn {
    display: inline-block;
    padding: 10px 20px;
    background: white;
    color: #333;
    text-decoration: none;
    border-radius: 8px;
    margin-bottom: 15px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.back-btn:hover {
    background: #f3f4f6;
}

label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #374151;
}
</style>
</head>
<body>

<div class="container">
    <a href="index.php" class="back-btn">← Quay về trang chủ</a>

    <?= $msg ?>

    <!-- THÔNG TIN CƠ BẢN -->
    <div class="card">
        <h3>👤 Thông tin tài khoản</h3>
        
        <div class="stats">
            <div class="stat-box">
                <div class="stat-number"><?= $totalBookings ?></div>
                <div class="stat-label">Lịch đã đặt</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?= $user['is_verified'] ? '✓' : '✗' ?></div>
                <div class="stat-label"><?= $user['is_verified'] ? 'Đã xác minh' : 'Chưa xác minh' ?></div>
            </div>
        </div>

        <div class="info-row">
            <label>Tên đăng nhập:</label>
            <span><?= htmlspecialchars($user['TenDangNhap']) ?></span>
        </div>
        <div class="info-row">
            <label>Email:</label>
            <span><?= htmlspecialchars($user['Email']) ?></span>
        </div>
        <div class="info-row">
            <label>Vai trò:</label>
            <span><?= $user['VaiTro'] === 'admin' ? 'Quản trị viên' : 'Khách hàng' ?></span>
        </div>
    </div>

    <!-- CẬP NHẬT THÔNG TIN -->
    <div class="card">
        <h3>✏️ Cập nhật thông tin</h3>
        <form method="POST">
            <label>Họ và tên</label>
            <input type="text" name="HoVaTen" value="<?= htmlspecialchars($user['HoVaTen']) ?>" required>

            <label>Số điện thoại</label>
            <input type="text" name="SoDienThoai" value="<?= htmlspecialchars($user['SoDienThoai']) ?>" 
                   pattern="^(0[3|5|7|8|9])[0-9]{8}$" required>

            <label>Địa chỉ</label>
            <input type="text" name="DiaChi" value="<?= htmlspecialchars($user['DiaChi']) ?>" required>

            <button name="update_info">Cập nhật thông tin</button>
        </form>
    </div>

    <!-- ĐỔI MẬT KHẨU -->
    <div class="card">
        <h3>🔑 Đổi mật khẩu</h3>
        <form method="POST">
            <label>Mật khẩu cũ</label>
            <input type="password" name="MatKhauCu" required>

            <label>Mật khẩu mới</label>
            <input type="password" name="MatKhauMoi" minlength="8" required>

            <label>Xác nhận mật khẩu mới</label>
            <input type="password" name="XacNhanMatKhau" minlength="8" required>

            <button name="change_password" class="btn-warning">Đổi mật khẩu</button>
        </form>
    </div>

    <!-- XÓA TÀI KHOẢN -->
    <div class="card">
        <h3>⚠️ Xóa tài khoản</h3>
        <form method="POST" onsubmit="return confirm('BẠN CHẮC CHẮN MUỐN XÓA TÀI KHOẢN?');">
            <button name="delete_acc" class="btn-danger">Xóa tài khoản vĩnh viễn</button>
        </form>
    </div>
</div>

</body>
</html>