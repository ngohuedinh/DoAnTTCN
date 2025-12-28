<?php
session_start();
require_once 'database.php';

// Nếu đã đăng nhập, chuyển hướng
if (isset($_SESSION['TenDangNhap'])) {
    $checkRole = $conn->prepare("SELECT VaiTro FROM nguoi_dung WHERE TenDangNhap = ?");
    $checkRole->bind_param("s", $_SESSION['TenDangNhap']);
    $checkRole->execute();
    $roleResult = $checkRole->get_result();
    
    if ($roleResult && $roleResult->num_rows > 0) {
        $role = $roleResult->fetch_assoc()['VaiTro'];
        if ($role === 'admin') {
            header("Location: admin.php");
        } else {
            header("Location: index.php");
        }
        exit();
    }
}

$msg = "";

// Xử lý đăng nhập
if (isset($_POST['login'])) {
    $username = trim($_POST['TenDangNhap']);
    $password = $_POST['MatKhau'];

    if (empty($username) || empty($password)) {
        $msg = "<div class='msg error'>Vui lòng nhập đầy đủ thông tin!</div>";
    } else {
        $stmt = $conn->prepare("SELECT * FROM nguoi_dung WHERE TenDangNhap = ? OR Email = ?");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();

            // KIỂM TRA TÀI KHOẢN BỊ KHÓA
            if ($user['is_verified'] == 0) {
                $msg = "<div class='msg error'>⚠️ Tài khoản đã bị khóa!<br>Vui lòng liên hệ admin để được hỗ trợ.</div>";
            } elseif (password_verify($password, $user['MatKhau'])) {
                // Đăng nhập thành công
                $_SESSION['TenDangNhap'] = $user['TenDangNhap'];
                $_SESSION['HoVaTen'] = $user['HoVaTen'];
                $_SESSION['VaiTro'] = $user['VaiTro'];
                $_SESSION['LAST_ACTIVITY'] = time(); // Session timeout

                // Chuyển hướng theo vai trò
                if ($user['VaiTro'] === 'admin') {
                    header("Location: admin.php");
                } else {
                    header("Location: index.php");
                }
                exit();
            } else {
                $msg = "<div class='msg error'>Mật khẩu không đúng!</div>";
            }
        } else {
            $msg = "<div class='msg error'>Tài khoản không tồn tại!</div>";
        }
    }
}

// Hiển thị thông báo từ URL
if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'logout') {
        $msg = "<div class='msg success'>Đã đăng xuất thành công!</div>";
    } elseif ($_GET['msg'] == 'timeout') {
        $msg = "<div class='msg error'>Phiên đăng nhập đã hết hạn!</div>";
    } elseif ($_GET['msg'] == 'verify') {
        $msg = "<div class='msg success'>Xác minh email thành công! Vui lòng đăng nhập.</div>";
    } elseif ($_GET['msg'] == 'reset') {
        $msg = "<div class='msg success'>Đổi mật khẩu thành công! Vui lòng đăng nhập.</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Đăng nhập - D2AUTO</title>

<style>
body {
    background: #f4f6f9;
    font-family: "Segoe UI", Arial, sans-serif;
    margin: 0;
    padding: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
}

.container {
    max-width: 400px;
    width: 100%;
}

.card {
    background: white;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

h2 {
    color: #2563eb;
    margin-bottom: 10px;
    text-align: center;
}

.subtitle {
    text-align: center;
    color: #6b7280;
    margin-bottom: 25px;
}

label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #374151;
}

input {
    width: 100%;
    padding: 12px;
    margin-bottom: 15px;
    border-radius: 8px;
    border: 1px solid #d1d5db;
    font-size: 15px;
    box-sizing: border-box;
}

input:focus {
    outline: none;
    border-color: #2563eb;
}

button {
    width: 100%;
    padding: 12px;
    background: #2563eb;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
}

button:hover {
    background: #1d4ed8;
}

.msg {
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 15px;
    text-align: center;
}

.msg.error {
    background: #fee2e2;
    color: #991b1b;
}

.msg.success {
    background: #d1fae5;
    color: #065f46;
}

.links {
    text-align: center;
    margin-top: 20px;
}

.links a {
    color: #2563eb;
    text-decoration: none;
    margin: 0 10px;
}

.links a:hover {
    text-decoration: underline;
}
</style>
</head>
<body>

<div class="container">
    <div class="card">
        <h2>🚗 D2AUTO</h2>
        <p class="subtitle">Đăng nhập hệ thống</p>

        <?= $msg ?>

        <form method="POST">
            <label>Tên đăng nhập hoặc Email</label>
            <input type="text" name="TenDangNhap" required autofocus>

            <label>Mật khẩu</label>
            <input type="password" name="MatKhau" required>

            <button type="submit" name="login">Đăng nhập</button>
        </form>

        <div class="links">
            <a href="dangky.php">Đăng ký</a> •
            <a href="reset_password.php">Quên mật khẩu?</a>
        </div>
    </div>
</div>

</body>
</html>