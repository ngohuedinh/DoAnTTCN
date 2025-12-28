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

// Xử lý gửi đánh giá
if (isset($_POST['submit_review'])) {
    $maDatLich = intval($_POST['MaDatLich']);
    $rating = intval($_POST['DiemDanhGia']);
    $comment = trim($_POST['NoiDung']);

    if ($rating < 1 || $rating > 5) {
        $msg = "<div class='msg error'>Vui lòng chọn số sao từ 1-5!</div>";
    } elseif (strlen($comment) < 10) {
        $msg = "<div class='msg error'>Nội dung phải có ít nhất 10 ký tự!</div>";
    } else {
        // Kiểm tra lịch hợp lệ
        $checkBooking = $conn->prepare("
            SELECT MaDatLich FROM dat_lich 
            WHERE MaDatLich = ? AND MaNguoiDung = ? AND TrangThai = 'Đã hoàn thành'
        ");
        $checkBooking->bind_param("ii", $maDatLich, $userID);
        $checkBooking->execute();
        
        if ($checkBooking->get_result()->num_rows == 0) {
            $msg = "<div class='msg error'>Lịch hẹn không hợp lệ!</div>";
        } else {
            // Kiểm tra đã đánh giá chưa
            $checkExist = $conn->prepare("SELECT MaPhanHoi FROM phan_hoi WHERE MaDatLich = ?");
            $checkExist->bind_param("i", $maDatLich);
            $checkExist->execute();
            
            if ($checkExist->get_result()->num_rows > 0) {
                $msg = "<div class='msg error'>Bạn đã đánh giá lịch này rồi!</div>";
            } else {
                // Thêm đánh giá
                $insert = $conn->prepare("INSERT INTO phan_hoi (MaDatLich, DiemDanhGia, NoiDung) VALUES (?, ?, ?)");
                $insert->bind_param("iis", $maDatLich, $rating, $comment);
                
                if ($insert->execute()) {
                    $_SESSION['flash_msg'] = "<div class='msg success'>✓ Cảm ơn bạn đã đánh giá!</div>";
                    header("Location: danhgia.php");
                    exit();
                }
            }
        }
    }
}

// Hiển thị thông báo
if (isset($_SESSION['flash_msg'])) {
    $msg = $_SESSION['flash_msg'];
    unset($_SESSION['flash_msg']);
}

// Lấy danh sách lịch
$completedBookings = $conn->prepare("
    SELECT 
        dl.MaDatLich, dl.NgayDat, dl.GioDat, dl.LoaiXe, dl.TongTien,
        GROUP_CONCAT(dv.TenDichVu SEPARATOR ', ') AS DichVu,
        (SELECT COUNT(*) FROM phan_hoi WHERE MaDatLich = dl.MaDatLich) AS DaDanhGia
    FROM dat_lich dl
    LEFT JOIN chi_tiet_dat_lich ct ON dl.MaDatLich = ct.MaDatLich
    LEFT JOIN dich_vu dv ON ct.MaDichVu = dv.MaDichVu
    WHERE dl.MaNguoiDung = ? AND dl.TrangThai = 'Đã hoàn thành'
    GROUP BY dl.MaDatLich
    ORDER BY dl.NgayDat DESC
    LIMIT 20
");
$completedBookings->bind_param("i", $userID);
$completedBookings->execute();
$bookings = $completedBookings->get_result();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Đánh giá - D2AUTO</title>

<style>
body {
    background: #f4f6f9;
    font-family: "Segoe UI", Arial, sans-serif;
    margin: 0;
    padding: 20px;
}

.container {
    max-width: 800px;
    margin: 0 auto;
}

.card {
    background: white;
    padding: 25px;
    margin-bottom: 15px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

h3 {
    color: #2563eb;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #e5e7eb;
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
    margin-right: 10px;
}

.back-btn:hover {
    background: #f3f4f6;
}

.booking-item {
    border: 1px solid #e5e7eb;
    padding: 15px;
    margin-bottom: 15px;
    border-radius: 8px;
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

.badge-done {
    background: #d1fae5;
    color: #065f46;
}

.badge-pending {
    background: #fef3c7;
    color: #92400e;
}

.btn-review {
    background: #2563eb;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    margin-top: 10px;
}

.btn-review:hover {
    background: #1d4ed8;
}

.msg {
    padding: 12px;
    border-radius: 8px;
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

/* Modal */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
}

.modal.active {
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: white;
    padding: 30px;
    border-radius: 12px;
    max-width: 500px;
    width: 90%;
}

.modal-header {
    font-size: 20px;
    font-weight: bold;
    margin-bottom: 20px;
    color: #2563eb;
}

.star-rating {
    display: flex;
    justify-content: center;
    gap: 10px;
    font-size: 2.5rem;
    margin: 20px 0;
}

.star-rating input {
    display: none;
}

.star-rating label {
    cursor: pointer;
    color: #d1d5db;
}

.star-rating label:hover,
.star-rating label:hover ~ label,
.star-rating input:checked ~ label {
    color: #fbbf24;
}

textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-family: "Segoe UI", Arial;
    font-size: 14px;
    resize: vertical;
}

.modal-footer {
    margin-top: 20px;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

.btn-cancel {
    background: #6b7280;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    cursor: pointer;
}

.btn-cancel:hover {
    background: #4b5563;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #9ca3af;
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 20px;
}
</style>
</head>
<body>

<div class="container">
    <a href="index.php" class="back-btn">← Trang chủ</a>
    <a href="xemdanhgia.php" class="back-btn" style="background:#fef3c7;">⭐ Xem đánh giá khác</a>

    <?= $msg ?>

    <div class="card">
        <h3>⭐ Đánh giá dịch vụ</h3>

        <?php if ($bookings->num_rows > 0): ?>
            <?php while ($booking = $bookings->fetch_assoc()): ?>
            <div class="booking-item">
                <div class="booking-header">
                    <div>
                        <strong><?= date('d/m/Y', strtotime($booking['NgayDat'])) ?> - <?= date('H:i', strtotime($booking['GioDat'])) ?></strong>
                    </div>
                    <span class="status-badge <?= $booking['DaDanhGia'] > 0 ? 'badge-done' : 'badge-pending' ?>">
                        <?= $booking['DaDanhGia'] > 0 ? '✓ Đã đánh giá' : '⏳ Chưa đánh giá' ?>
                    </span>
                </div>
                <div class="booking-info">🚗 Xe: <?= $booking['LoaiXe'] ?></div>
                <div class="booking-info">🛠️ Dịch vụ: <?= $booking['DichVu'] ?></div>
                <div class="booking-info">💰 Tổng: <strong><?= number_format($booking['TongTien']) ?> đ</strong></div>
                
                <?php if ($booking['DaDanhGia'] == 0): ?>
                <button class="btn-review" onclick="openModal(<?= $booking['MaDatLich'] ?>)">
                    Đánh giá ngay
                </button>
                <?php endif; ?>
            </div>

            <!-- Modal -->
            <div id="modal<?= $booking['MaDatLich'] ?>" class="modal">
                <div class="modal-content">
                    <div class="modal-header">⭐ Đánh giá dịch vụ</div>
                    
                    <form method="POST">
                        <input type="hidden" name="MaDatLich" value="<?= $booking['MaDatLich'] ?>">
                        
                        <div style="text-align:center; margin-bottom:15px; color:#666; font-size:14px;">
                            <?= date('d/m/Y H:i', strtotime($booking['NgayDat'].' '.$booking['GioDat'])) ?> - <?= $booking['LoaiXe'] ?>
                        </div>

                        <div class="star-rating">
                            <input type="radio" name="DiemDanhGia" value="5" id="s5_<?= $booking['MaDatLich'] ?>" required>
                            <label for="s5_<?= $booking['MaDatLich'] ?>">★</label>
                            
                            <input type="radio" name="DiemDanhGia" value="4" id="s4_<?= $booking['MaDatLich'] ?>">
                            <label for="s4_<?= $booking['MaDatLich'] ?>">★</label>
                            
                            <input type="radio" name="DiemDanhGia" value="3" id="s3_<?= $booking['MaDatLich'] ?>">
                            <label for="s3_<?= $booking['MaDatLich'] ?>">★</label>
                            
                            <input type="radio" name="DiemDanhGia" value="2" id="s2_<?= $booking['MaDatLich'] ?>">
                            <label for="s2_<?= $booking['MaDatLich'] ?>">★</label>
                            
                            <input type="radio" name="DiemDanhGia" value="1" id="s1_<?= $booking['MaDatLich'] ?>">
                            <label for="s1_<?= $booking['MaDatLich'] ?>">★</label>
                        </div>

                        <label style="font-weight:600; margin-bottom:5px; display:block;">Chia sẻ trải nghiệm</label>
                        <textarea name="NoiDung" rows="4" placeholder="Dịch vụ tốt không? Nhân viên thế nào?..." required minlength="10"></textarea>
                        <small style="color:#6b7280;">Tối thiểu 10 ký tự</small>

                        <div class="modal-footer">
                            <button type="button" class="btn-cancel" onclick="closeModal(<?= $booking['MaDatLich'] ?>)">Hủy</button>
                            <button type="submit" name="submit_review" class="btn-review">Gửi đánh giá</button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <div style="font-size:4rem;">📭</div>
                <h4>Chưa có lịch nào hoàn thành</h4>
                <p>Hãy đặt lịch và sử dụng dịch vụ để đánh giá!</p>
                <a href="datlich.php" class="btn-review">Đặt lịch ngay</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function openModal(id) {
    document.getElementById('modal' + id).classList.add('active');
}

function closeModal(id) {
    document.getElementById('modal' + id).classList.remove('active');
}

// Close modal khi click outside
window.onclick = function(event) {
    if (event.target.className === 'modal active') {
        event.target.classList.remove('active');
    }
}
</script>

</body>
</html>