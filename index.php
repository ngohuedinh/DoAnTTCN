<?php
session_start();
date_default_timezone_set('Asia/Ho_Chi_Minh');
require_once 'database.php'; 
$currentDate = date('Y-m-d');
$currentTime = time(); 
$MAX_PER_SLOT = 3; 

$sqlToday = $conn->prepare("
    SELECT dl.GioDat, SUM(dv.ThoiGian) as TongThoiGian
    FROM dat_lich dl
    JOIN chi_tiet_dat_lich ct ON dl.MaDatLich = ct.MaDatLich
    JOIN dich_vu dv ON ct.MaDichVu = dv.MaDichVu
    WHERE dl.NgayDat = ? 
    AND dl.TrangThai NOT IN ('Đã hủy', 'Đã hoàn thành')
    AND dv.TrangThai = 'hoatdong'
    GROUP BY dl.MaDatLich
");
$sqlToday->bind_param("s", $currentDate);
$sqlToday->execute();
$resToday = $sqlToday->get_result();

$dangRua = 0;         
$bookedSlots = [];     

if ($resToday) {
    while ($row = $resToday->fetch_assoc()) {
        $gioDatStr = $row['GioDat']; 
        $thoiGianLam = intval($row['TongThoiGian']); 

        // --- A. TÍNH XE ĐANG RỬA ---
        $startTime = strtotime("$currentDate $gioDatStr"); 
        $endTime   = $startTime + ($thoiGianLam * 60);     

        
        if ($currentTime >= $startTime && $currentTime <= $endTime) {
            $dangRua++;
        }

      
        $slotKey = date('H:i', $startTime); 
        if (!isset($bookedSlots[$slotKey])) $bookedSlots[$slotKey] = 0;
        $bookedSlots[$slotKey]++;
    }
}


$suggestion = "";

$startCheck = ceil($currentTime / (30*60)) * (30*60); 
$endOfDay   = strtotime("$currentDate 18:30:00");

for ($i = $startCheck; $i < $endOfDay; $i += 1800) { 
    $slotCheck = date('H:i', $i);
    
   
    if (!isset($bookedSlots[$slotCheck]) || $bookedSlots[$slotCheck] < $MAX_PER_SLOT) {
        $suggestion = $slotCheck;
        break;
    }
}

$page = $_GET['page'] ?? 'home';
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Rửa Xe D2AUTO</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
:root {
    --primary: #2563eb;
    --danger: #ef4444;
}

body {
    font-family: "Segoe UI", Arial, sans-serif;
    color: #111;
    background: #f4f6f9;
}

/* VIDEO BACKGROUND */
#bg-video {
    position: fixed;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    z-index: -2;
}
#video-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.4); 
    z-index: -1;
}

/* HEADER */
.header {
    position: sticky;
    top: 0;
    z-index: 100;
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(10px);
    padding: 12px 22px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.header-left { 
    display: flex; 
    gap: 10px; 
    flex-wrap: wrap;
}

.btn-custom {
    padding: 8px 14px;
    border-radius: 8px;
    background: white;
    border: 1px solid #ddd;
    font-weight: 600;
    text-decoration: none;
    color: #111;
    transition: 0.2s;
}
.btn-custom:hover { 
    background: #f0f0f0; 
    color: var(--primary); 
}

.btn-red {
    background: var(--danger);
    color: #fff;
    border: none;
    padding: 8px 16px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
}
.btn-red:hover { opacity: 0.9; }

/* CONTENT BOX */
.main-container {
    max-width: 900px;
    margin: 60px auto;
    padding: 0 15px;
}

.box {
    background: rgba(211, 210, 210, 0.8);
    padding: 30px;
    border-radius: 16px;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
}
</style>
</head>
<body>

<video autoplay muted loop id="bg-video" playsinline>
    <source src="video/background.mp4" type="video/mp4">
</video>
<div id="video-overlay"></div>

<div class="header">
    <div class="header-left">
        <a class="btn-custom" href="index.php?page=home"><i class="fas fa-home"></i> Trang chủ</a>
        
        <?php if (isset($_SESSION['TenDangNhap'])): ?>
            <!-- Menu cho người đã đăng nhập -->
            <a class="btn-custom" href="datlich.php"><i class="fas fa-calendar-plus"></i> Đặt lịch</a>
            <a href="danhgia.php" class="btn-custom"><i class="fas fa-star"></i> Đánh giá</a>
            <a href="lichcuatoi.php" class="btn-custom"><i class="fas fa-list"></i> Lịch của tôi</a>
            <a class="btn-custom" href="profile.php"><i class="fas fa-user"></i> Hồ sơ</a>
            <span class="btn-custom bg-light border-0">Xin chào, <?= htmlspecialchars($_SESSION['TenDangNhap']) ?> 👋</span>
        <?php else: ?>
            <!-- Menu cho khách chưa đăng nhập -->
            <a href="xemdanhgia.php" class="btn-custom"><i class="fas fa-star"></i> Đánh giá</a>
            <a class="btn-custom" href="dangnhap.php"><i class="fas fa-sign-in-alt"></i> Đăng nhập</a>
            <a class="btn-custom" href="dangky.php"><i class="fas fa-user-plus"></i> Đăng ký</a>
        <?php endif; ?>
    </div>
    
    <div>
        <?php if (isset($_SESSION['TenDangNhap'])): ?>
            <form method="POST" action="logout.php" style="display:inline">
                <button class="btn-red" type="submit"><i class="fas fa-sign-out-alt"></i> Đăng xuất</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="main-container">
    <div class="box">

        <?php if ($page === 'home'): ?>

            <div class="text-center mb-4">
                <h2 class="fw-bold text-primary">Hệ thống Rửa Xe D2AUTO</h2>
                <p class="text-muted">Theo dõi trạng thái và đặt lịch nhanh chóng</p>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="card bg-white text-dark h-100 shadow-sm border-0 rounded-4">
                        <div class="card-body d-flex align-items-center justify-content-between p-4">
                            <div>
                                <h6 class="text-uppercase opacity-75 small fw-bold">Hiện đang rửa</h6>
                                <span class="display-3 fw-bold"><?= $dangRua ?></span> <span class="fs-5">xe</span>
                            </div>
                            <div class="display-3 opacity-25"><i class="fas fa-car-side"></i></div>
                        </div>
                        <div class="card-footer bg-secondary border-0 pt-0 pb-3 ps-4 rounded-bottom-4 text-white">
                            <small class="opacity-75">
                                <?php if($dangRua >= 3): ?>
                                    <i class="fas fa-exclamation-triangle"></i> Cửa hàng đang rất đông!
                                <?php elseif($dangRua > 0): ?>
                                    <i class="fas fa-sync fa-spin"></i> Đang còng lưng rửa xe..
                                <?php else: ?>
                                    <i class="fas fa-check-circle"></i> Tiệm đang rảnh
                                <?php endif; ?>
                            </small>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card bg-white text-dark h-100 shadow-sm border-0 rounded-4">
                        <div class="card-body d-flex align-items-center justify-content-between p-4">
                            <div>
                                <h6 class="text-uppercase opacity-75 small fw-bold">Giờ trống gần nhất</h6>
                                <?php if ($suggestion): ?>
                                    <span class="display-4 fw-bold"><?= $suggestion ?></span>
                                    <div class="mt-1 small opacity-75">Hôm nay</div>
                                <?php else: ?>
                                    <span class="fs-4">Hiện hết chỗ hoặc quá giờ làm việc</span>
                                <?php endif; ?>
                            </div>
                            <div class="display-3 opacity-25"><i class="fas fa-clock"></i></div>
                        </div>
                        
                        <?php if ($suggestion): ?>
                        <div class="card-footer bg-success border-0 pt-0 pb-3 text-end pe-4 rounded-bottom-4">
                            <button type="button" onclick="applySuggestion('<?= $suggestion ?>')" class="btn btn-light text-success fw-bold rounded-pill shadow-sm px-4">
                                Chọn giờ này <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if (!isset($_SESSION['TenDangNhap'])): ?>
            
            <div class="alert alert-warning text-center mb-4">
                <i class="fas fa-info-circle"></i> 
                Bạn cần <a href="dangnhap.php" class="alert-link fw-bold">đăng nhập</a> hoặc 
                <a href="dangky.php" class="alert-link fw-bold">đăng ký</a> để đặt lịch
            </div>
            <?php endif; ?>

            <div class="d-flex justify-content-center gap-3 mt-4">
                <?php if (isset($_SESSION['TenDangNhap'])): ?>
                    <a class="btn btn-primary btn-lg rounded-pill px-5" href="datlich.php">
                        <i class="fas fa-calendar-check me-2"></i> Đặt lịch Ngay
                    </a>
                    <a class="btn btn-outline-secondary btn-lg rounded-pill px-5" href="profile.php">
                        <i class="fas fa-user-cog me-2"></i> Tài khoản
                    </a>
                <?php else: ?>
                    <a class="btn btn-primary btn-lg rounded-pill px-5" href="dangnhap.php?redirect=datlich.php">
                        <i class="fas fa-calendar-check me-2"></i> Đặt lịch Ngay
                    </a>
                    <a class="btn btn-outline-secondary btn-lg rounded-pill px-5" href="dangky.php">
                        <i class="fas fa-user-plus me-2"></i> Đăng ký ngay
                    </a>
                <?php endif; ?>
            </div>

        <?php endif; ?>

        <div class="text-center mt-5 text-muted small">
            <hr>
            &copy; <?= date('Y') ?> D2AUTO Đang Chờ Bạn Rửa Cái Xe<br>
            Hôm nay: <?= date('d/m/Y') ?>
        </div>

    </div>
</div>

<script>
    function applySuggestion(timeStr) {
        <?php if (isset($_SESSION['TenDangNhap'])): ?>
            // Đã đăng nhập - Chuyển đến trang đặt lịch với thời gian gợi ý
            const today = new Date();
            const yyyy = today.getFullYear();
            let mm = today.getMonth() + 1;
            let dd = today.getDate();

            if (dd < 10) dd = '0' + dd;
            if (mm < 10) mm = '0' + mm;

            const formattedDate = yyyy + '-' + mm + '-' + dd;
            window.location.href = `datlich.php?date=${formattedDate}&time=${timeStr}`;
        <?php else: ?>
            alert('Bạn cần đăng nhập để đặt lịch!');
            window.location.href = 'dangnhap.php?redirect=datlich.php';
        <?php endif; ?>
    }
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
