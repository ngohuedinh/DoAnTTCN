<?php
session_start();
require_once 'database.php';

// Kiểm tra đăng nhập và quyền admin
if (!isset($_SESSION['TenDangNhap']) || $_SESSION['VaiTro'] !== 'admin') {
    die("<h3 style='color:red;text-align:center;'>⛔ Bạn không có quyền truy cập!</h3>");
}

$msg = "";
$page = $_GET['page'] ?? 'dashboard';

// LOAD CẤU HÌNH HỆ THỐNG
function getConfig($key) {
    global $conn;
    $result = $conn->query("SELECT value FROM cau_hinh WHERE key_name = '$key'");
    return $result && $result->num_rows > 0 ? $result->fetch_assoc()['value'] : null;
}

// XỬ LÝ CẬP NHẬT CẤU HÌNH
if (isset($_POST['update_config'])) {
    foreach ($_POST['config'] as $key => $value) {
        $stmt = $conn->prepare("UPDATE cau_hinh SET value = ? WHERE key_name = ?");
        $stmt->bind_param("ss", $value, $key);
        $stmt->execute();
    }
    $msg = "<div class='alert alert-success'>Đã cập nhật cấu hình!</div>";
}

// XỬ LÝ CẬP NHẬT TRẠNG THÁI LỊCH HẸN
if (isset($_POST['update_status'])) {
    $id = intval($_POST['MaDatLich']);
    $status = $_POST['TrangThai'];
    
    $update = $conn->prepare("UPDATE dat_lich SET TrangThai = ? WHERE MaDatLich = ?");
    $update->bind_param("si", $status, $id);
    $update->execute();
    
    $msg = "<div class='alert alert-success'>Đã cập nhật trạng thái!</div>";
}

// XỬ LÝ THÊM DỊCH VỤ
if (isset($_POST['add_service'])) {
    $ten = trim($_POST['TenDichVu']);
    $gia = floatval($_POST['Gia']);
    $loai = $_POST['LoaiDV'];
    $thoigian = intval($_POST['ThoiGian']);
    
    $insert = $conn->prepare("INSERT INTO dich_vu (TenDichVu, Gia, LoaiDV, ThoiGian, TrangThai) VALUES (?, ?, ?, ?, 'hoatdong')");
    $insert->bind_param("sdsi", $ten, $gia, $loai, $thoigian);
    $insert->execute();
    
    $msg = "<div class='alert alert-success'>Đã thêm dịch vụ!</div>";
}

// XỬ LÝ XÓA DỊCH VỤ
if (isset($_POST['delete_service'])) {
    $id = intval($_POST['MaDichVu']);
    $conn->query("DELETE FROM dich_vu WHERE MaDichVu = $id");
    $msg = "<div class='alert alert-success'>Đã xóa dịch vụ!</div>";
}

// XỬ LÝ ẨN/HIỆN ĐÁNH GIÁ
if (isset($_POST['toggle_review'])) {
    $id = intval($_POST['MaPhanHoi']);
    $status = $_POST['TrangThai'] == 'hienthi' ? 'an' : 'hienthi';
    
    $update = $conn->prepare("UPDATE phan_hoi SET TrangThai = ? WHERE MaPhanHoi = ?");
    $update->bind_param("si", $status, $id);
    $update->execute();
    
    $msg = "<div class='alert alert-success'>Đã cập nhật!</div>";
}

// THỐNG KÊ
$stats = [];
$stats['total_bookings'] = $conn->query("SELECT COUNT(*) as c FROM dat_lich")->fetch_assoc()['c'];
$stats['new_bookings'] = $conn->query("SELECT COUNT(*) as c FROM dat_lich WHERE TrangThai = 'Mới'")->fetch_assoc()['c'];
$stats['completed'] = $conn->query("SELECT COUNT(*) as c FROM dat_lich WHERE TrangThai = 'Đã hoàn thành'")->fetch_assoc()['c'];
$stats['total_users'] = $conn->query("SELECT COUNT(*) as c FROM nguoi_dung WHERE VaiTro = 'customer'")->fetch_assoc()['c'];
$stats['revenue'] = $conn->query("SELECT SUM(TongTien) as t FROM dat_lich WHERE TrangThai = 'Đã hoàn thành'")->fetch_assoc()['t'] ?? 0;
$stats['avg_rating'] = $conn->query("SELECT AVG(DiemDanhGia) as a FROM phan_hoi")->fetch_assoc()['a'] ?? 0;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin - D2AUTO</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
body { background: #f4f6f9; }
.sidebar {
    background: #2c3e50;
    min-height: 100vh;
    padding: 20px 0;
}
.sidebar a {
    color: #ecf0f1;
    padding: 12px 20px;
    display: block;
    text-decoration: none;
    transition: 0.3s;
}
.sidebar a:hover, .sidebar a.active {
    background: #34495e;
}
.header {
    background: white;
    padding: 15px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.stat-card {
    background: white;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.stat-number {
    font-size: 2rem;
    font-weight: bold;
    color: #2c3e50;
}
</style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <!-- SIDEBAR -->
        <div class="col-md-2 sidebar">
            <h4 class="text-white text-center mb-4">
                <i class="fas fa-car-wash"></i> ADMIN
            </h4>
            <a href="?page=dashboard" class="<?= $page=='dashboard'?'active':'' ?>">
                <i class="fas fa-chart-line"></i> Dashboard
            </a>
            <a href="?page=bookings" class="<?= $page=='bookings'?'active':'' ?>">
                <i class="fas fa-calendar-check"></i> Lịch hẹn
            </a>
            <a href="?page=services" class="<?= $page=='services'?'active':'' ?>">
                <i class="fas fa-tools"></i> Dịch vụ
            </a>
            <a href="?page=users" class="<?= $page=='users'?'active':'' ?>">
                <i class="fas fa-users"></i> Người dùng
            </a>
            <a href="?page=reviews" class="<?= $page=='reviews'?'active':'' ?>">
                <i class="fas fa-star"></i> Đánh giá
            </a>
            <a href="?page=revenue" class="<?= $page=='revenue'?'active':'' ?>">
                <i class="fas fa-chart-bar"></i> Doanh thu
            </a>
            <a href="?page=config" class="<?= $page=='config'?'active':'' ?>">
                <i class="fas fa-cog"></i> Cấu hình
            </a>
            <hr style="border-color: #7f8c8d;">
            <a href="index.php">
                <i class="fas fa-home"></i> Về trang chủ
            </a>
            <a href="logout.php">
                <i class="fas fa-sign-out-alt"></i> Đăng xuất
            </a>
        </div>

        <!-- MAIN CONTENT -->
        <div class="col-md-10">
            <div class="header">
                <h3><i class="fas fa-user-shield"></i> Chào Admin: <?= $_SESSION['HoVaTen'] ?? 'Admin' ?></h3>
            </div>

            <div class="container">
                <?= $msg ?>

                <?php if ($page == 'dashboard'): ?>
                <!-- DASHBOARD -->
                <h4 class="mb-4">Thống kê tổng quan</h4>
                <div class="row">
                    <div class="col-md-4">
                        <div class="stat-card">
                            <h6 class="text-muted">Tổng lịch hẹn</h6>
                            <div class="stat-number"><?= $stats['total_bookings'] ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card">
                            <h6 class="text-muted">Lịch mới (chờ xử lý)</h6>
                            <div class="stat-number text-warning"><?= $stats['new_bookings'] ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card">
                            <h6 class="text-muted">Đã hoàn thành</h6>
                            <div class="stat-number text-success"><?= $stats['completed'] ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card">
                            <h6 class="text-muted">Tổng khách hàng</h6>
                            <div class="stat-number"><?= $stats['total_users'] ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card">
                            <h6 class="text-muted">Doanh thu</h6>
                            <div class="stat-number text-primary"><?= number_format($stats['revenue']) ?> đ</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card">
                            <h6 class="text-muted">Đánh giá TB</h6>
                            <div class="stat-number text-warning"><?= number_format($stats['avg_rating'], 1) ?> ⭐</div>
                        </div>
                    </div>
                </div>

                <?php elseif ($page == 'config'): ?>
                <!-- CẤU HÌNH HỆ THỐNG -->
                <h4 class="mb-4"><i class="fas fa-cog"></i> Cấu hình hệ thống</h4>
                
                <div class="card">
                    <div class="card-body">
                        <form method="POST">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-car"></i> Số xe tối đa mỗi giờ
                                    </label>
                                    <input type="number" name="config[max_xe_moi_gio]" 
                                           class="form-control" 
                                           value="<?= getConfig('max_xe_moi_gio') ?>" 
                                           min="1" max="20" required>
                                    <small class="text-muted">Giới hạn số lượng xe được đặt trong cùng 1 khung giờ</small>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-clock"></i> Giờ mở cửa
                                    </label>
                                    <input type="time" name="config[gio_mo_cua]" 
                                           class="form-control" 
                                           value="<?= getConfig('gio_mo_cua') ?>" 
                                           required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-clock"></i> Giờ đóng cửa
                                    </label>
                                    <input type="time" name="config[gio_dong_cua]" 
                                           class="form-control" 
                                           value="<?= getConfig('gio_dong_cua') ?>" 
                                           required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-calendar-times"></i> Ngày nghỉ trong tuần
                                    </label>
                                    <input type="text" name="config[ngay_nghi]" 
                                           class="form-control" 
                                           value="<?= getConfig('ngay_nghi') ?>" 
                                           placeholder="VD: Chủ nhật, Thứ hai">
                                    <small class="text-muted">Ngăn cách bởi dấu phẩy</small>
                                </div>

                                <div class="col-md-12">
                                    <label class="form-label fw-bold">
                                        <i class="fas fa-gift"></i> Ngày lễ nghỉ
                                    </label>
                                    <input type="text" name="config[ngay_le_nghi]" 
                                           class="form-control" 
                                           value="<?= getConfig('ngay_le_nghi') ?>" 
                                           placeholder="VD: 01/01, 30/04, 01/05, 02/09">
                                    <small class="text-muted">Định dạng: DD/MM, ngăn cách bởi dấu phẩy</small>
                                </div>
                            </div>

                            <hr class="my-4">

                            <button type="submit" name="update_config" class="btn btn-primary btn-lg">
                                <i class="fas fa-save"></i> Lưu cấu hình
                            </button>
                        </form>

                        <hr class="my-4">
                    </div>
                </div>

                <?php elseif ($page == 'bookings'): ?>
                <!-- QUẢN LÝ LỊCH HẸN -->
                <h4 class="mb-4">Quản lý lịch hẹn</h4>
                <table class="table table-bordered bg-white">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Khách hàng</th>
                            <th>Ngày/Giờ</th>
                            <th>Xe</th>
                            <th>Dịch vụ</th>
                            <th>Tổng tiền</th>
                            <th>Trạng thái</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $bookings = $conn->query("
                        SELECT dl.*, nd.HoVaTen, nd.SoDienThoai,
                               GROUP_CONCAT(dv.TenDichVu SEPARATOR ', ') AS DichVu
                        FROM dat_lich dl
                        JOIN nguoi_dung nd ON dl.MaNguoiDung = nd.MaNguoiDung
                        LEFT JOIN chi_tiet_dat_lich ct ON dl.MaDatLich = ct.MaDatLich
                        LEFT JOIN dich_vu dv ON ct.MaDichVu = dv.MaDichVu
                        GROUP BY dl.MaDatLich
                        ORDER BY dl.NgayDat DESC, dl.GioDat DESC
                        LIMIT 50
                    ");
                    while ($row = $bookings->fetch_assoc()):
                    ?>
                    <tr>
                        <td><?= $row['MaDatLich'] ?></td>
                        <td>
                            <strong><?= $row['HoVaTen'] ?></strong><br>
                            <small><?= $row['SoDienThoai'] ?></small>
                        </td>
                        <td>
                            <?= date('d/m/Y', strtotime($row['NgayDat'])) ?><br>
                            <small><?= date('H:i', strtotime($row['GioDat'])) ?></small>
                        </td>
                        <td><?= $row['LoaiXe'] ?></td>
                        <td><small><?= $row['DichVu'] ?></small></td>
                        <td><strong><?= number_format($row['TongTien']) ?> đ</strong></td>
                        <td>
                            <span class="badge bg-<?= 
                                $row['TrangThai']=='Mới'?'warning':
                                ($row['TrangThai']=='Đã hoàn thành'?'success':'secondary') 
                            ?>">
                                <?= $row['TrangThai'] ?>
                            </span>
                        </td>
                        <td>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="MaDatLich" value="<?= $row['MaDatLich'] ?>">
                                <select name="TrangThai" class="form-select form-select-sm" onchange="this.form.submit()">
                                    <option value="Mới" <?= $row['TrangThai']=='Mới'?'selected':'' ?>>Mới</option>
                                    <option value="Đang xử lý" <?= $row['TrangThai']=='Đang xử lý'?'selected':'' ?>>Đang xử lý</option>
                                    <option value="Đã hoàn thành" <?= $row['TrangThai']=='Đã hoàn thành'?'selected':'' ?>>Hoàn thành</option>
                                    <option value="Đã hủy" <?= $row['TrangThai']=='Đã hủy'?'selected':'' ?>>Hủy</option>
                                </select>
                                <input type="hidden" name="update_status" value="1">
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>

                <?php elseif ($page == 'services'): ?>
                <!-- QUẢN LÝ DỊCH VỤ -->
                <h4 class="mb-4">Quản lý dịch vụ</h4>
                
                <!-- Form thêm dịch vụ -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <i class="fas fa-plus"></i> Thêm dịch vụ mới
                    </div>
                    <div class="card-body">
                        <form method="POST" class="row g-3">
                            <div class="col-md-4">
                                <input type="text" name="TenDichVu" class="form-control" placeholder="Tên dịch vụ" required>
                            </div>
                            <div class="col-md-2">
                                <input type="number" name="Gia" class="form-control" placeholder="Giá" required>
                            </div>
                            <div class="col-md-2">
                                <select name="LoaiDV" class="form-select" required>
                                    <option value="chinh">Dịch vụ chính</option>
                                    <option value="them">Dịch vụ thêm</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <input type="number" name="ThoiGian" class="form-control" placeholder="Thời gian (phút)" required>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" name="add_service" class="btn btn-primary w-100">
                                    <i class="fas fa-plus"></i> Thêm
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Danh sách dịch vụ -->
                <table class="table table-bordered bg-white">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Tên dịch vụ</th>
                            <th>Giá</th>
                            <th>Loại</th>
                            <th>Thời gian</th>
                            <th>Trạng thái</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $services = $conn->query("SELECT * FROM dich_vu ORDER BY LoaiDV DESC, MaDichVu ASC");
                    while ($row = $services->fetch_assoc()):
                    ?>
                    <tr>
                        <td><?= $row['MaDichVu'] ?></td>
                        <td><?= $row['TenDichVu'] ?></td>
                        <td><?= number_format($row['Gia']) ?> đ</td>
                        <td><span class="badge bg-<?= $row['LoaiDV']=='chinh'?'primary':'secondary' ?>">
                            <?= $row['LoaiDV']=='chinh'?'Chính':'Thêm' ?>
                        </span></td>
                        <td><?= $row['ThoiGian'] ?> phút</td>
                        <td><span class="badge bg-<?= $row['TrangThai']=='hoatdong'?'success':'danger' ?>">
                            <?= $row['TrangThai'] ?>
                        </span></td>
                        <td>
                            <form method="POST" onsubmit="return confirm('Xóa dịch vụ này?')">
                                <input type="hidden" name="MaDichVu" value="<?= $row['MaDichVu'] ?>">
                                <button type="submit" name="delete_service" class="btn btn-danger btn-sm">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>

                <?php elseif ($page == 'users'): ?>
                <!-- QUẢN LÝ NGƯỜI DÙNG -->
                <h4 class="mb-4">Quản lý người dùng</h4>
                
                <!-- Tìm kiếm -->
                <div class="card mb-3">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <input type="hidden" name="page" value="users">
                            <div class="col-md-10">
                                <input type="text" name="search" class="form-control" 
                                       placeholder="Tìm theo tên, email, SĐT..." 
                                       value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search"></i> Tìm
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php
                // Xử lý tìm kiếm
                $search = $_GET['search'] ?? '';
                $whereSearch = "";
                if (!empty($search)) {
                    $searchTerm = "%$search%";
                    $whereSearch = "WHERE HoVaTen LIKE ? OR Email LIKE ? OR SoDienThoai LIKE ?";
                }

                // Xử lý xóa user
                if (isset($_POST['delete_user'])) {
                    $userId = intval($_POST['user_id']);
                    
                    $checkSelf = $conn->prepare("SELECT TenDangNhap FROM nguoi_dung WHERE MaNguoiDung = ?");
                    $checkSelf->bind_param("i", $userId);
                    $checkSelf->execute();
                    $userToDelete = $checkSelf->get_result()->fetch_assoc();
                    
                    if ($userToDelete && $userToDelete['TenDangNhap'] === $_SESSION['TenDangNhap']) {
                        $msg = "<div class='alert alert-danger'>Không thể xóa chính mình!</div>";
                    } else {
                        $conn->query("DELETE FROM nguoi_dung WHERE MaNguoiDung = $userId");
                        $msg = "<div class='alert alert-success'>Đã xóa người dùng!</div>";
                    }
                }

                // Xử lý khóa/mở khóa
                if (isset($_POST['toggle_lock'])) {
                    $userId = intval($_POST['user_id']);
                    $currentStatus = intval($_POST['current_status']);
                    $newStatus = $currentStatus == 1 ? 0 : 1;
                    
                    $checkSelf = $conn->prepare("SELECT TenDangNhap FROM nguoi_dung WHERE MaNguoiDung = ?");
                    $checkSelf->bind_param("i", $userId);
                    $checkSelf->execute();
                    $userToLock = $checkSelf->get_result()->fetch_assoc();
                    
                    if ($userToLock && $userToLock['TenDangNhap'] === $_SESSION['TenDangNhap']) {
                        $msg = "<div class='alert alert-danger'>Không thể khóa chính mình!</div>";
                    } else {
                        $update = $conn->prepare("UPDATE nguoi_dung SET is_verified = ? WHERE MaNguoiDung = ?");
                        $update->bind_param("ii", $newStatus, $userId);
                        $update->execute();
                        $msg = "<div class='alert alert-success'>" . ($newStatus == 1 ? "Đã mở khóa" : "Đã khóa") . " tài khoản!</div>";
                    }
                }

                // Query users
                if (!empty($search)) {
                    $stmt = $conn->prepare("SELECT * FROM nguoi_dung $whereSearch ORDER BY MaNguoiDung DESC");
                    $stmt->bind_param("sss", $searchTerm, $searchTerm, $searchTerm);
                    $stmt->execute();
                    $users = $stmt->get_result();
                } else {
                    $users = $conn->query("SELECT * FROM nguoi_dung ORDER BY MaNguoiDung DESC");
                }
                ?>

                <table class="table table-bordered bg-white">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Họ tên</th>
                            <th>Email</th>
                            <th>SĐT</th>
                            <th>Vai trò</th>
                            <th>Trạng thái</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    if ($users->num_rows > 0):
                        $usersArray = [];
                        while ($row = $users->fetch_assoc()):
                            $usersArray[] = $row;
                    ?>
                    <tr>
                        <td><?= $row['MaNguoiDung'] ?></td>
                        <td>
                            <a href="#" data-bs-toggle="modal" data-bs-target="#userModal<?= $row['MaNguoiDung'] ?>" 
                               style="text-decoration:none; color:#2563eb; cursor:pointer;">
                                <strong><?= htmlspecialchars($row['HoVaTen']) ?></strong>
                            </a>
                            <?php if (!empty($row['DiaChi'])): ?>
                            <br><small class="text-muted"><?= htmlspecialchars($row['DiaChi']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($row['Email']) ?></td>
                        <td><?= htmlspecialchars($row['SoDienThoai']) ?></td>
                        <td>
                            <span class="badge bg-<?= $row['VaiTro']=='admin'?'danger':'primary' ?>">
                                <?= $row['VaiTro'] ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($row['is_verified']): ?>
                                <span class="badge bg-success">Hoạt động</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Đã khóa</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php 
                        endwhile;
                    else: 
                    ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted">
                            Không tìm thấy người dùng nào
                        </td>
                    </tr>
                    <?php endif; ?>
                    </tbody>
                </table>

                <!-- Modals -->
                <?php if (!empty($usersArray)): ?>
                    <?php foreach ($usersArray as $row): ?>
                    <div class="modal fade" id="userModal<?= $row['MaNguoiDung'] ?>" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">
                                        <i class="fas fa-user"></i> Chi tiết: <?= htmlspecialchars($row['HoVaTen']) ?>
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <h6>Thông tin cá nhân</h6>
                                    <table class="table table-sm">
                                        <tr>
                                            <td width="150"><strong>Username:</strong></td>
                                            <td><?= htmlspecialchars($row['TenDangNhap']) ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Email:</strong></td>
                                            <td><?= htmlspecialchars($row['Email']) ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>SĐT:</strong></td>
                                            <td><?= htmlspecialchars($row['SoDienThoai']) ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Địa chỉ:</strong></td>
                                            <td><?= htmlspecialchars($row['DiaChi']) ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Ngày tạo:</strong></td>
                                            <td><?= date('d/m/Y H:i', strtotime($row['NgayTao'] ?? 'now')) ?></td>
                                        </tr>
                                    </table>

                                    <hr>

                                    <h6>Lịch sử đặt lịch</h6>
                                    <?php
                                    $userId = $row['MaNguoiDung'];
                                    $bookingHistory = $conn->query("
                                        SELECT dl.*, 
                                               GROUP_CONCAT(dv.TenDichVu SEPARATOR ', ') AS DichVu
                                        FROM dat_lich dl
                                        LEFT JOIN chi_tiet_dat_lich ct ON dl.MaDatLich = ct.MaDatLich
                                        LEFT JOIN dich_vu dv ON ct.MaDichVu = dv.MaDichVu
                                        WHERE dl.MaNguoiDung = $userId
                                        GROUP BY dl.MaDatLich
                                        ORDER BY dl.NgayDat DESC, dl.GioDat DESC
                                        LIMIT 10
                                    ");
                                    ?>
                                    
                                    <?php if ($bookingHistory->num_rows > 0): ?>
                                    <table class="table table-sm table-bordered">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Ngày/Giờ</th>
                                                <th>Xe</th>
                                                <th>Dịch vụ</th>
                                                <th>Tiền</th>
                                                <th>TT</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php while ($booking = $bookingHistory->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <small>
                                                    <?= date('d/m/Y', strtotime($booking['NgayDat'])) ?><br>
                                                    <?= date('H:i', strtotime($booking['GioDat'])) ?>
                                                </small>
                                            </td>
                                            <td><small><?= $booking['LoaiXe'] ?></small></td>
                                            <td><small><?= $booking['DichVu'] ?></small></td>
                                            <td><small><?= number_format($booking['TongTien']) ?>đ</small></td>
                                            <td>
                                                <span class="badge bg-<?= 
                                                    $booking['TrangThai']=='Mới'?'warning':
                                                    ($booking['TrangThai']=='Đã hoàn thành'?'success':'secondary')
                                                ?>" style="font-size:10px;">
                                                    <?= $booking['TrangThai'] ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                    <?php else: ?>
                                    <p class="text-muted">Chưa có lịch hẹn nào</p>
                                    <?php endif; ?>
                                </div>
                                <div class="modal-footer">
                                    <?php if ($row['TenDangNhap'] !== $_SESSION['TenDangNhap']): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="user_id" value="<?= $row['MaNguoiDung'] ?>">
                                        <input type="hidden" name="current_status" value="<?= $row['is_verified'] ?>">
                                        <input type="hidden" name="page" value="users">
                                        <button type="submit" name="toggle_lock" class="btn btn-<?= $row['is_verified'] ? 'warning' : 'success' ?>">
                                            <i class="fas fa-<?= $row['is_verified'] ? 'lock' : 'unlock' ?>"></i> 
                                            <?= $row['is_verified'] ? 'Khóa TK' : 'Mở khóa' ?>
                                        </button>
                                    </form>
                                    <form method="POST" onsubmit="return confirm('XÓA tài khoản này?\nLịch sử sẽ mất hết!');" style="display:inline;">
                                        <input type="hidden" name="user_id" value="<?= $row['MaNguoiDung'] ?>">
                                        <input type="hidden" name="page" value="users">
                                        <button type="submit" name="delete_user" class="btn btn-danger">
                                            <i class="fas fa-trash"></i> Xóa TK
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php elseif ($page == 'revenue'): ?>
                <!-- THỐNG KÊ DOANH THU -->
                <h4 class="mb-4"><i class="fas fa-chart-bar"></i> Thống kê doanh thu</h4>

                <?php
                // Lấy tháng/năm từ GET hoặc mặc định tháng hiện tại
                $month = $_GET['month'] ?? date('m');
                $year = $_GET['year'] ?? date('Y');
                
                // Doanh thu theo ngày trong tháng
                $dailyRevenue = $conn->query("
                    SELECT 
                        DATE(NgayDat) as date,
                        COUNT(*) as total_orders,
                        SUM(TongTien) as revenue
                    FROM dat_lich
                    WHERE TrangThai = 'Đã hoàn thành'
                    AND MONTH(NgayDat) = $month
                    AND YEAR(NgayDat) = $year
                    GROUP BY DATE(NgayDat)
                    ORDER BY NgayDat ASC
                ");

                // Doanh thu theo tháng trong năm
                $monthlyRevenue = $conn->query("
                    SELECT 
                        MONTH(NgayDat) as month,
                        COUNT(*) as total_orders,
                        SUM(TongTien) as revenue
                    FROM dat_lich
                    WHERE TrangThai = 'Đã hoàn thành'
                    AND YEAR(NgayDat) = $year
                    GROUP BY MONTH(NgayDat)
                    ORDER BY MONTH(NgayDat) ASC
                ");

                // Tổng doanh thu tháng này
                $currentMonthTotal = $conn->query("
                    SELECT 
                        COUNT(*) as orders,
                        SUM(TongTien) as revenue
                    FROM dat_lich
                    WHERE TrangThai = 'Đã hoàn thành'
                    AND MONTH(NgayDat) = $month
                    AND YEAR(NgayDat) = $year
                ")->fetch_assoc();

                // Tổng doanh thu năm này
                $currentYearTotal = $conn->query("
                    SELECT 
                        COUNT(*) as orders,
                        SUM(TongTien) as revenue
                    FROM dat_lich
                    WHERE TrangThai = 'Đã hoàn thành'
                    AND YEAR(NgayDat) = $year
                ")->fetch_assoc();
                ?>

                <!-- Bộ lọc -->
                <div class="card mb-3">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <input type="hidden" name="page" value="revenue">
                            <div class="col-md-3">
                                <label class="form-label">Tháng</label>
                                <select name="month" class="form-select">
                                    <?php for($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?= $m ?>" <?= $m == $month ? 'selected' : '' ?>>
                                        Tháng <?= $m ?>
                                    </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Năm</label>
                                <select name="year" class="form-select">
                                    <?php for($y = date('Y'); $y >= 2020; $y--): ?>
                                    <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>>
                                        <?= $y ?>
                                    </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-filter"></i> Lọc
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tổng quan -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body text-center">
                                <h6 class="text-muted">Doanh thu tháng <?= $month ?>/<?= $year ?></h6>
                                <h2 class="text-primary mb-0">
                                    <?= number_format($currentMonthTotal['revenue'] ?? 0) ?> đ
                                </h2>
                                <small class="text-muted"><?= $currentMonthTotal['orders'] ?? 0 ?> đơn hoàn thành</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body text-center">
                                <h6 class="text-muted">Doanh thu năm <?= $year ?></h6>
                                <h2 class="text-success mb-0">
                                    <?= number_format($currentYearTotal['revenue'] ?? 0) ?> đ
                                </h2>
                                <small class="text-muted"><?= $currentYearTotal['orders'] ?? 0 ?> đơn hoàn thành</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Theo ngày -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <i class="fas fa-calendar-day"></i> Doanh thu theo ngày (Tháng <?= $month ?>/<?= $year ?>)
                    </div>
                    <div class="card-body">
                        <?php if ($dailyRevenue->num_rows > 0): ?>
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Ngày</th>
                                    <th>Số đơn</th>
                                    <th>Doanh thu</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php 
                            $totalDaily = 0;
                            while($row = $dailyRevenue->fetch_assoc()): 
                                $totalDaily += $row['revenue'];
                            ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($row['date'])) ?></td>
                                <td><?= $row['total_orders'] ?> đơn</td>
                                <td class="text-end"><strong><?= number_format($row['revenue']) ?> đ</strong></td>
                            </tr>
                            <?php endwhile; ?>
                            <tr class="table-warning">
                                <td colspan="2"><strong>Tổng cộng</strong></td>
                                <td class="text-end"><strong><?= number_format($totalDaily) ?> đ</strong></td>
                            </tr>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <p class="text-muted text-center">Chưa có doanh thu trong tháng này</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Theo tháng -->
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <i class="fas fa-calendar-alt"></i> Doanh thu theo tháng (Năm <?= $year ?>)
                    </div>
                    <div class="card-body">
                        <?php if ($monthlyRevenue->num_rows > 0): ?>
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Tháng</th>
                                    <th>Số đơn</th>
                                    <th>Doanh thu</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php 
                            $totalYearly = 0;
                            while($row = $monthlyRevenue->fetch_assoc()): 
                                $totalYearly += $row['revenue'];
                            ?>
                            <tr>
                                <td>Tháng <?= $row['month'] ?>/<?= $year ?></td>
                                <td><?= $row['total_orders'] ?> đơn</td>
                                <td class="text-end"><strong><?= number_format($row['revenue']) ?> đ</strong></td>
                            </tr>
                            <?php endwhile; ?>
                            <tr class="table-success">
                                <td colspan="2"><strong>Tổng cộng</strong></td>
                                <td class="text-end"><strong><?= number_format($totalYearly) ?> đ</strong></td>
                            </tr>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <p class="text-muted text-center">Chưa có doanh thu trong năm này</p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php elseif ($page == 'reviews'): ?>
                <!-- QUẢN LÝ ĐÁNH GIÁ -->
                <h4 class="mb-4">Quản lý đánh giá</h4>
                <table class="table table-bordered bg-white">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Khách hàng</th>
                            <th>Đánh giá</th>
                            <th>Nội dung</th>
                            <th>Ngày</th>
                            <th>Trạng thái</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $reviews = $conn->query("
                        SELECT ph.*, nd.HoVaTen
                        FROM phan_hoi ph
                        JOIN dat_lich dl ON ph.MaDatLich = dl.MaDatLich
                        JOIN nguoi_dung nd ON dl.MaNguoiDung = nd.MaNguoiDung
                        ORDER BY ph.NgayPhanHoi DESC
                    ");
                    while ($row = $reviews->fetch_assoc()):
                    ?>
                    <tr>
                        <td><?= $row['MaPhanHoi'] ?></td>
                        <td><?= $row['HoVaTen'] ?></td>
                        <td>
                            <?php for($i=1; $i<=5; $i++): ?>
                                <i class="fas fa-star <?= $i<=$row['DiemDanhGia']?'text-warning':'' ?>"></i>
                            <?php endfor; ?>
                        </td>
                        <td><?= substr($row['NoiDung'], 0, 50) ?>...</td>
                        <td><?= date('d/m/Y', strtotime($row['NgayPhanHoi'])) ?></td>
                        <td>
                            <span class="badge bg-<?= $row['TrangThai']=='hienthi'?'success':'danger' ?>">
                                <?= $row['TrangThai'] ?>
                            </span>
                        </td>
                        <td>
                            <form method="POST">
                                <input type="hidden" name="MaPhanHoi" value="<?= $row['MaPhanHoi'] ?>">
                                <input type="hidden" name="TrangThai" value="<?= $row['TrangThai'] ?>">
                                <button type="submit" name="toggle_review" class="btn btn-sm btn-<?= $row['TrangThai']=='hienthi'?'warning':'success' ?>">
                                    <?= $row['TrangThai']=='hienthi'?'Ẩn':'Hiện' ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>

                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>