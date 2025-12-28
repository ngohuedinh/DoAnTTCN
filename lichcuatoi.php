<?php
session_start();
require_once 'database.php';

if (!isset($_SESSION['TenDangNhap'])) {
    header("Location: dangnhap.php");
    exit();
}

$username = $_SESSION['TenDangNhap'];
$msg = "";

// Lấy thông tin user
$getUser = $conn->prepare("SELECT MaNguoiDung FROM nguoi_dung WHERE TenDangNhap = ?");
$getUser->bind_param("s", $username);
$getUser->execute();
$user = $getUser->get_result()->fetch_assoc();
$userID = $user['MaNguoiDung'];

// Hiển thị thông báo
if (isset($_SESSION['flash_msg'])) {
    $msg = $_SESSION['flash_msg'];
    unset($_SESSION['flash_msg']);
}

// Xử lý hủy lịch
if (isset($_POST['cancel_booking'])) {
    $bookingId = intval($_POST['MaDatLich']);
    
    $checkBooking = $conn->prepare("
        SELECT NgayDat, GioDat, TrangThai 
        FROM dat_lich 
        WHERE MaDatLich = ? AND MaNguoiDung = ?
    ");
    $checkBooking->bind_param("ii", $bookingId, $userID);
    $checkBooking->execute();
    $booking = $checkBooking->get_result()->fetch_assoc();
    
    if (!$booking) {
        $msg = "<div class='msg error'>Lịch hẹn không tồn tại!</div>";
    } elseif ($booking['TrangThai'] != 'Mới') {
        $msg = "<div class='msg error'>Chỉ có thể hủy lịch đang ở trạng thái 'Mới'!</div>";
    } else {
        $appointmentTime = strtotime($booking['NgayDat'] . ' ' . $booking['GioDat']);
        $timeUntilAppointment = $appointmentTime - time();
        
        if ($timeUntilAppointment < 7200) {
            $msg = "<div class='msg error'>Chỉ có thể hủy lịch trước 2 giờ!<br>Vui lòng liên hệ: 0123-456-789</div>";
        } else {
            $update = $conn->prepare("UPDATE dat_lich SET TrangThai = 'Đã hủy' WHERE MaDatLich = ?");
            $update->bind_param("i", $bookingId);
            
            if ($update->execute()) {
                $_SESSION['flash_msg'] = "<div class='msg success'>✓ Đã hủy lịch hẹn thành công!</div>";
                header("Location: lichcuatoi.php");
                exit();
            }
        }
    }
}

// Lấy danh sách lịch hẹn
$bookings = $conn->prepare("
    SELECT 
        dl.MaDatLich, dl.NgayDat, dl.GioDat, dl.LoaiXe, dl.TongTien, dl.TrangThai,
        GROUP_CONCAT(dv.TenDichVu SEPARATOR ', ') AS DichVu,
        TIMESTAMPDIFF(SECOND, NOW(), CONCAT(dl.NgayDat, ' ', dl.GioDat)) AS TimeLeft
    FROM dat_lich dl
    LEFT JOIN chi_tiet_dat_lich ct ON dl.MaDatLich = ct.MaDatLich
    LEFT JOIN dich_vu dv ON ct.MaDichVu = dv.MaDichVu
    WHERE dl.MaNguoiDung = ?
    GROUP BY dl.MaDatLich
    ORDER BY dl.NgayDat DESC, dl.GioDat DESC
    LIMIT 20
");
$bookings->bind_param("i", $userID);
$bookings->execute();
$result = $bookings->get_result();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Lịch hẹn của tôi - D2AUTO</title>

<style>
body {
    background: #f4f6f9;
    font-family: "Segoe UI", Arial, sans-serif;
    margin: 0;
    padding: 20px;
}

.container {
    max-width: 900px;
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
    margin-bottom: 20px;
}

.back-btn {
    display: inline-block;
    padding: 10px 20px;
    background: white;
    color: #333;
    text-decoration: none;
    border-radius: 8px;
    margin-bottom: 15px;
    margin-right: 10px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.back-btn:hover {
    background: #f3f4f6;
}

.booking-item {
    border: 1px solid #e5e7eb;
    padding: 20px;
    margin-bottom: 15px;
    border-radius: 10px;
    background: #fafafa;
}

.booking-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.booking-info {
    font-size: 14px;
    color: #666;
    margin: 5px 0;
}

.status-badge {
    padding: 5px 12px;
    border-radius: 15px;
    font-size: 13px;
    font-weight: 600;
}

.badge-new { background: #fef3c7; color: #92400e; }
.badge-processing { background: #dbeafe; color: #1e40af; }
.badge-completed { background: #d1fae5; color: #065f46; }
.badge-cancelled { background: #fee2e2; color: #991b1b; }

.btn-cancel {
    background: #ef4444;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    margin-top: 10px;
}

.btn-cancel:hover {
    background: #dc2626;
}

.btn-cancel:disabled {
    background: #d1d5db;
    cursor: not-allowed;
}

.msg {
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 15px;
}

.msg.error { background: #fee2e2; color: #991b1b; }
.msg.success { background: #d1fae5; color: #065f46; }

.time-left {
    color: #059669;
    font-weight: 600;
    font-size: 13px;
    margin-top: 5px;
}

.warning-text {
    color: #dc2626;
    font-size: 13px;
    margin-top: 5px;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #9ca3af;
}
</style>
</head>
<body>

<div class="container">
    <a href="index.php" class="back-btn">← Trang chủ</a>
    <a href="datlich.php" class="back-btn" style="background:#dbeafe;">📅 Đặt lịch mới</a>

    <?= $msg ?>

    <div class="card">
        <h3>📋 Lịch hẹn của tôi</h3>

        <?php if ($result->num_rows > 0): ?>
            <?php while ($booking = $result->fetch_assoc()): ?>
            <?php
            $canCancel = ($booking['TrangThai'] == 'Mới' && $booking['TimeLeft'] >= 7200);
            $hoursLeft = floor($booking['TimeLeft'] / 3600);
            $minutesLeft = floor(($booking['TimeLeft'] % 3600) / 60);
            ?>
            <div class="booking-item">
                <div class="booking-header">
                    <div>
                        <strong><?= date('d/m/Y', strtotime($booking['NgayDat'])) ?> - <?= date('H:i', strtotime($booking['GioDat'])) ?></strong>
                    </div>
                    <span class="status-badge badge-<?= 
                        $booking['TrangThai']=='Mới'?'new':
                        ($booking['TrangThai']=='Đang xử lý'?'processing':
                        ($booking['TrangThai']=='Đã hoàn thành'?'completed':'cancelled'))
                    ?>">
                        <?= $booking['TrangThai'] ?>
                    </span>
                </div>
                
                <div class="booking-info">🚗 <?= $booking['LoaiXe'] ?></div>
                <div class="booking-info">🛠️ <?= $booking['DichVu'] ?></div>
                <div class="booking-info">💰 <strong><?= number_format($booking['TongTien']) ?> đ</strong></div>
                
                <?php if ($booking['TrangThai'] == 'Mới'): ?>
                    <?php if ($booking['TimeLeft'] > 0): ?>
                        <div class="time-left">
                            ⏰ Còn <?= $hoursLeft ?>h <?= $minutesLeft ?>p
                        </div>
                        
                        <?php if ($canCancel): ?>
                            <form method="POST" onsubmit="return confirm('HỦY lịch hẹn này?');" style="display:inline;">
                                <input type="hidden" name="MaDatLich" value="<?= $booking['MaDatLich'] ?>">
                                <button type="submit" name="cancel_booking" class="btn-cancel">
                                    🚫 Hủy lịch
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="warning-text">
                                ⚠️ Hủy trước 2h
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="warning-text">⏱️ Lịch hẹn đã qua</div>
                    <?php endif; ?>
                <?php elseif ($booking['TrangThai'] == 'Đã hủy'): ?>
                    <div class="warning-text">❌ Đã hủy</div>
                <?php endif; ?>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <div style="font-size:4rem;">📭</div>
                <h4>Chưa có lịch hẹn nào</h4>
                <a href="datlich.php" class="back-btn">Đặt lịch ngay</a>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>