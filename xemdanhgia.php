<?php
require_once 'database.php';

// Lấy filter
$filter = $_GET['filter'] ?? 'all';
$whereStar = "";

if ($filter == '5') $whereStar = "AND ph.DiemDanhGia = 5";
elseif ($filter == '4') $whereStar = "AND ph.DiemDanhGia = 4";
elseif ($filter == '3') $whereStar = "AND ph.DiemDanhGia = 3";
elseif ($filter == '2') $whereStar = "AND ph.DiemDanhGia = 2";
elseif ($filter == '1') $whereStar = "AND ph.DiemDanhGia = 1";

// Lấy đánh giá
$reviews = $conn->query("
    SELECT 
        ph.DiemDanhGia, ph.NoiDung, ph.NgayPhanHoi,
        nd.HoVaTen, dl.LoaiXe, dl.NgayDat,
        GROUP_CONCAT(dv.TenDichVu SEPARATOR ', ') AS DichVu
    FROM phan_hoi ph
    JOIN dat_lich dl ON ph.MaDatLich = dl.MaDatLich
    JOIN nguoi_dung nd ON dl.MaNguoiDung = nd.MaNguoiDung
    LEFT JOIN chi_tiet_dat_lich ct ON dl.MaDatLich = ct.MaDatLich
    LEFT JOIN dich_vu dv ON ct.MaDichVu = dv.MaDichVu
    WHERE ph.TrangThai = 'hienthi'
    $whereStar
    GROUP BY ph.MaPhanHoi
    ORDER BY ph.NgayPhanHoi DESC
");

// Thống kê
$stats = $conn->query("
    SELECT 
        COUNT(*) as total,
        AVG(DiemDanhGia) as avg_rating,
        SUM(CASE WHEN DiemDanhGia = 5 THEN 1 ELSE 0 END) as star5,
        SUM(CASE WHEN DiemDanhGia = 4 THEN 1 ELSE 0 END) as star4,
        SUM(CASE WHEN DiemDanhGia = 3 THEN 1 ELSE 0 END) as star3,
        SUM(CASE WHEN DiemDanhGia = 2 THEN 1 ELSE 0 END) as star2,
        SUM(CASE WHEN DiemDanhGia = 1 THEN 1 ELSE 0 END) as star1
    FROM phan_hoi WHERE TrangThai = 'hienthi'
")->fetch_assoc();

$totalReviews = $stats['total'] ?? 0;
$avgRating = $stats['avg_rating'] ?? 0;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Đánh giá khách hàng - D2AUTO</title>

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

.stats-header {
    background: #2563eb;
    color: white;
    padding: 30px;
    border-radius: 12px 12px 0 0;
    text-align: center;
    margin: -25px -25px 20px -25px;
}

.avg-rating {
    font-size: 3.5rem;
    font-weight: bold;
}

.stars-display {
    font-size: 1.8rem;
    margin: 10px 0;
}

.stat-bars {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.stat-row {
    display: flex;
    align-items: center;
    margin-bottom: 10px;
}

.stat-label {
    width: 60px;
    font-size: 14px;
}

.stat-bar {
    flex: 1;
    height: 18px;
    background: #e5e7eb;
    border-radius: 10px;
    overflow: hidden;
    margin: 0 15px;
}

.stat-fill {
    height: 100%;
    background: #2563eb;
}

.stat-count {
    width: 40px;
    text-align: right;
    font-size: 14px;
    color: #6b7280;
}

.filter-bar {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    justify-content: center;
    margin-bottom: 20px;
}

.filter-btn {
    padding: 8px 16px;
    border: 2px solid #d1d5db;
    background: white;
    border-radius: 20px;
    text-decoration: none;
    color: #333;
    font-size: 14px;
}

.filter-btn:hover, .filter-btn.active {
    background: #2563eb;
    color: white;
    border-color: #2563eb;
}

.review-item {
    border: 1px solid #e5e7eb;
    padding: 20px;
    margin-bottom: 15px;
    border-radius: 10px;
    background: #fafafa;
}

.review-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 10px;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.user-avatar {
    width: 45px;
    height: 45px;
    background: #2563eb;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 18px;
}

.stars {
    color: #fbbf24;
    font-size: 18px;
}

.stars .empty {
    color: #d1d5db;
}

.review-date {
    color: #9ca3af;
    font-size: 13px;
}

.review-content {
    margin: 15px 0;
    line-height: 1.6;
    color: #374151;
}

.review-meta {
    font-size: 13px;
    color: #6b7280;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #9ca3af;
}

h3 {
    color: #2563eb;
    margin-bottom: 20px;
}
</style>
</head>
<body>

<div class="container">
    <a href="index.php" class="back-btn">← Trang chủ</a>
    <a href="danhgia.php" class="back-btn" style="background:#fef3c7;">✏️ Đánh giá của tôi</a>

    <div class="card">
        <div class="stats-header">
            <h2>⭐ Đánh giá từ khách hàng</h2>
            <?php if ($totalReviews > 0): ?>
                <div class="avg-rating"><?= number_format($avgRating, 1) ?></div>
                <div class="stars-display">
                    <?php 
                    $fullStars = floor($avgRating);
                    $halfStar = ($avgRating - $fullStars) >= 0.5;
                    for ($i = 1; $i <= 5; $i++) {
                        if ($i <= $fullStars) echo '★';
                        elseif ($i == $fullStars + 1 && $halfStar) echo '⭐';
                        else echo '☆';
                    }
                    ?>
                </div>
                <p style="margin:0; opacity:0.8;">Dựa trên <?= $totalReviews ?> đánh giá</p>
            <?php else: ?>
                <p>Chưa có đánh giá nào</p>
            <?php endif; ?>
        </div>

        <?php if ($totalReviews > 0): ?>
        <!-- Thống kê -->
        <div class="stat-bars">
            <?php for ($i = 5; $i >= 1; $i--): ?>
            <?php 
            $count = $stats['star'.$i] ?? 0;
            $percent = $totalReviews > 0 ? ($count / $totalReviews * 100) : 0;
            ?>
            <div class="stat-row">
                <div class="stat-label"><?= $i ?> ★</div>
                <div class="stat-bar">
                    <div class="stat-fill" style="width: <?= $percent ?>%"></div>
                </div>
                <div class="stat-count"><?= $count ?></div>
            </div>
            <?php endfor; ?>
        </div>

        <!-- Bộ lọc -->
        <div class="filter-bar">
            <a href="?" class="filter-btn <?= $filter == 'all' ? 'active' : '' ?>">
                Tất cả (<?= $totalReviews ?>)
            </a>
            <a href="?filter=5" class="filter-btn <?= $filter == '5' ? 'active' : '' ?>">
                5★ (<?= $stats['star5'] ?>)
            </a>
            <a href="?filter=4" class="filter-btn <?= $filter == '4' ? 'active' : '' ?>">
                4★ (<?= $stats['star4'] ?>)
            </a>
            <a href="?filter=3" class="filter-btn <?= $filter == '3' ? 'active' : '' ?>">
                3★ (<?= $stats['star3'] ?>)
            </a>
            <a href="?filter=2" class="filter-btn <?= $filter == '2' ? 'active' : '' ?>">
                2★ (<?= $stats['star2'] ?>)
            </a>
            <a href="?filter=1" class="filter-btn <?= $filter == '1' ? 'active' : '' ?>">
                1★ (<?= $stats['star1'] ?>)
            </a>
        </div>

        <!-- Danh sách đánh giá -->
        <h3>📝 Đánh giá</h3>
        <?php if ($reviews->num_rows > 0): ?>
            <?php while ($review = $reviews->fetch_assoc()): ?>
            <div class="review-item">
                <div class="review-header">
                    <div class="user-info">
                        <div class="user-avatar">
                            <?= strtoupper(mb_substr($review['HoVaTen'], 0, 1)) ?>
                        </div>
                        <div>
                            <strong><?= htmlspecialchars($review['HoVaTen']) ?></strong>
                            <div class="stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <span class="<?= $i > $review['DiemDanhGia'] ? 'empty' : '' ?>">★</span>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                    <div class="review-date">
                        🕐 <?= date('d/m/Y H:i', strtotime($review['NgayPhanHoi'])) ?>
                    </div>
                </div>

                <div class="review-content">
                    <?= nl2br(htmlspecialchars($review['NoiDung'])) ?>
                </div>

                <div class="review-meta">
                    🚗 <?= htmlspecialchars($review['LoaiXe']) ?> 
                    • 🛠️ <?= htmlspecialchars($review['DichVu']) ?> 
                    • 📅 <?= date('d/m/Y', strtotime($review['NgayDat'])) ?>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <div style="font-size:3rem;">🔍</div>
                <h4>Không có đánh giá nào</h4>
                <a href="?" class="back-btn">Xem tất cả</a>
            </div>
        <?php endif; ?>
        <?php else: ?>
        <div class="empty-state">
            <div style="font-size:4rem;">💬</div>
            <h4>Chưa có đánh giá nào</h4>
            <p>Hãy là người đầu tiên đánh giá!</p>
        </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>