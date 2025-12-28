<?php
session_start();
date_default_timezone_set('Asia/Ho_Chi_Minh');
require_once 'database.php'; 

// --- KIỂM TRA ĐĂNG NHẬP ---
if (!isset($_SESSION['TenDangNhap'])) {
    header("Location: dangnhap.php");
    exit();
}

$username = $_SESSION['TenDangNhap'];

// --- XỬ LÝ THÔNG BÁO TỪ SESSION (FIX LỖI F5) ---
$msg = "";
if (isset($_SESSION['flash_msg'])) {
    $msg = $_SESSION['flash_msg'];
    unset($_SESSION['flash_msg']);
}

// Lấy ID người dùng
$getUser = $conn->prepare("SELECT MaNguoiDung FROM nguoi_dung WHERE TenDangNhap = ? LIMIT 1");
$getUser->bind_param("s", $username);
$getUser->execute();
$resU = $getUser->get_result();
if (!$resU || $resU->num_rows === 0) {
    session_unset();
    session_destroy();
    header("Location: dangnhap.php");
    exit();
}
$UserID = $resU->fetch_assoc()['MaNguoiDung'];

// --- CẤU HÌNH ---
$MAX_PER_SLOT = 3;
$vehicle_types = ["Xe máy", "Ô tô 4 chỗ", "Ô tô 7 chỗ", "Ô tô 16 chỗ"];

// --- LẤY DỊCH VỤ (CHỈ LẤY DỊCH VỤ ĐANG HOẠT ĐỘNG) ---
$servicesMain = [];
$servicesExtra = [];
$sq = $conn->query("SELECT * FROM dich_vu WHERE TrangThai = 'hoatdong' ORDER BY LoaiDV DESC, MaDichVu ASC");
while ($r = $sq->fetch_assoc()) {
    if ($r['LoaiDV'] === 'chinh') $servicesMain[] = $r;
    else $servicesExtra[] = $r;
}

/* ===============================
   XỬ LÝ ĐẶT LỊCH
================================ */
if (isset($_POST['book'])) {
    $ngay    = $_POST['NgayDat'] ?? '';
    $gio     = $_POST['GioDat'] ?? '';
    $loai    = $_POST['LoaiXe'] ?? '';
    $ghichu  = trim($_POST['GhiChu'] ?? '');
    $dvChinh = $_POST['DichVuChinh'] ?? null;
    $dvThem  = $_POST['DichVuThem'] ?? [];

    if (empty($ngay) || empty($gio)) {
        $msg = "<div class='alert alert-danger shadow-sm'>Vui lòng chọn ngày và giờ.</div>";
    } elseif (strtotime("$ngay $gio") < time()) {
        $msg = "<div class='alert alert-danger shadow-sm'>Thời gian không hợp lệ (Không thể ngược về quá khứ).</div>";
    } elseif (!in_array($loai, $vehicle_types)) {
        $msg = "<div class='alert alert-danger shadow-sm'>Loại xe không hợp lệ.</div>";
    } elseif (empty($dvChinh)) {
        $msg = "<div class='alert alert-danger shadow-sm'>Vui lòng chọn 1 dịch vụ chính.</div>";
    } else {
        $allServices = [];
        $allServices[] = intval($dvChinh);
        foreach ($dvThem as $s) {
            $s = intval($s);
            if ($s > 0) $allServices[] = $s;
        }

        $slot = $conn->prepare("
            SELECT COUNT(*) AS c FROM dat_lich 
            WHERE NgayDat = ? AND GioDat = ? 
            AND TrangThai NOT IN ('Đã hủy', 'Đã hoàn thành')
        ");
        $slot->bind_param("ss", $ngay, $gio);
        $slot->execute();
        $slotCount = $slot->get_result()->fetch_assoc()['c'];

        if ($slotCount >= $MAX_PER_SLOT) {
            $msg = "<div class='alert alert-warning shadow-sm'>Khung giờ này đã đầy xe!</div>";
        } else {
            // Tính tổng tiền
            $totalPrice = 0;
            $servicePrices = [];
            $getPrice = $conn->prepare("SELECT MaDichVu, Gia FROM dich_vu WHERE MaDichVu = ?");
            foreach ($allServices as $dv) {
                $getPrice->bind_param("i", $dv);
                $getPrice->execute();
                $priceResult = $getPrice->get_result();
                if ($row = $priceResult->fetch_assoc()) {
                    $totalPrice += $row['Gia'];
                    $servicePrices[$dv] = $row['Gia'];
                }
            }

            $conn->begin_transaction();
            try {
                $add = $conn->prepare("INSERT INTO dat_lich (MaNguoiDung, NgayDat, GioDat, LoaiXe, GhiChu, TongTien, TrangThai) VALUES (?, ?, ?, ?, ?, ?, 'Mới')");
                $add->bind_param("issssd", $UserID, $ngay, $gio, $loai, $ghichu, $totalPrice);
                $add->execute();
                $newID = $conn->insert_id;

                $ct = $conn->prepare("INSERT INTO chi_tiet_dat_lich (MaDatLich, MaDichVu, GiaDichVu) VALUES (?, ?, ?)");
                foreach ($allServices as $dv) {
                    $price = $servicePrices[$dv] ?? 0;
                    $ct->bind_param("iid", $newID, $dv, $price);
                    $ct->execute();
                }
                $conn->commit();
                
                $_SESSION['flash_msg'] = "<div class='alert alert-success shadow-sm'><i class='fas fa-check-circle'></i> Đặt lịch thành công!</div>";
                header("Location: datlich.php"); 
                exit(); 

            } catch (Exception $e) {
                $conn->rollback();
                $msg = "<div class='alert alert-danger shadow-sm'>Lỗi: {$e->getMessage()}</div>";
            }
        }
    }
}

/* ===============================
   XỬ LÝ HỦY LỊCH
================================ */
if (isset($_POST['cancel'])) {
    $id = intval($_POST['id']);
    
    $check = $conn->prepare("SELECT NgayDat, GioDat, TrangThai FROM dat_lich WHERE MaDatLich = ? AND MaNguoiDung = ?");
    $check->bind_param("ii", $id, $UserID);
    $check->execute();
    $resCheck = $check->get_result();

    if ($rowCheck = $resCheck->fetch_assoc()) {
        if ($rowCheck['TrangThai'] !== 'Mới') {
            $msg = "<div class='alert alert-warning shadow-sm'>Lịch này không thể hủy được.</div>";
        } else {
            $bookingTimestamp = strtotime($rowCheck['NgayDat'] . ' ' . $rowCheck['GioDat']);
            $timeLeft = $bookingTimestamp - time();

            if ($timeLeft > 1800) {
                $up = $conn->prepare("UPDATE dat_lich SET TrangThai='Đã hủy' WHERE MaDatLich=?");
                $up->bind_param("i", $id);
                $up->execute();
                
                $_SESSION['flash_msg'] = "<div class='alert alert-success shadow-sm'>Đã hủy lịch hẹn thành công.</div>";
                header("Location: datlich.php");
                exit();
            } else {
                $msg = "<div class='alert alert-danger shadow-sm'>Không thể hủy vì đã sát giờ hẹn rồi.</div>";
            }
        }
    }
}

/* ===============================
   LẤY DANH SÁCH LỊCH
================================ */
$myBookings = $conn->prepare("
    SELECT dl.*, GROUP_CONCAT(dv.TenDichVu SEPARATOR ', ') AS DichVu
    FROM dat_lich dl
    LEFT JOIN chi_tiet_dat_lich ct ON dl.MaDatLich = ct.MaDatLich
    LEFT JOIN dich_vu dv ON ct.MaDichVu = dv.MaDichVu
    WHERE dl.MaNguoiDung = ?
    GROUP BY dl.MaDatLich
    ORDER BY dl.NgayDat DESC, dl.GioDat DESC
");
$myBookings->bind_param("i", $UserID);
$myBookings->execute();
$list = $myBookings->get_result();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Đặt lịch rửa xe | D2AUTO</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<style>
    :root {
        --primary-color: #0d6efd;
        --bg-light: #f4f7f6;
    }
    body {
        font-family: 'Poppins', sans-serif;
        background-color: var(--bg-light);
        color: #333;
    }
    .main-container {
        max-width: 900px;
        margin: 0 auto;
    }
    
    /* Card Styles */
    .card-custom {
        border: none;
        border-radius: 16px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        background: white;
        overflow: hidden;
    }
    .card-header-custom {
        background: linear-gradient(135deg, #0d6efd, #0a58ca);
        color: white;
        padding: 2rem;
        text-align: center;
    }
    
    /* Form Elements */
    .form-control, .form-select {
        border-radius: 10px;
        padding: 0.75rem 1rem;
        border: 1px solid #e0e0e0;
        box-shadow: none;
    }
    .form-control:focus, .form-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 4px rgba(13, 110, 253, 0.1);
    }
    
    /* Custom Service Selection Cards */
    .service-option-card {
        cursor: pointer;
        transition: all 0.2s ease;
        border: 2px solid #f0f0f0;
        border-radius: 12px;
        padding: 1rem;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    .service-option-card:hover {
        border-color: #b3d7ff;
        background-color: #f8fbff;
    }
    .btn-check:checked + .service-option-card {
        border-color: var(--primary-color);
        background-color: #e7f1ff;
        box-shadow: 0 4px 10px rgba(13, 110, 253, 0.15);
    }
    .service-price {
        font-weight: 600;
        color: var(--primary-color);
        font-size: 1.1rem;
    }

    /* Buttons */
    .btn-submit {
        padding: 0.8rem 2rem;
        font-weight: 600;
        border-radius: 50px;
        background: linear-gradient(90deg, #0d6efd, #0099ff);
        border: none;
        box-shadow: 0 4px 15px rgba(13, 110, 253, 0.3);
        transition: transform 0.2s;
    }
    .btn-submit:hover {
        transform: translateY(-2px);
    }

    /* Table */
    .table-custom th {
        background-color: #f8f9fa;
        color: #666;
        font-weight: 600;
        border-bottom: 2px solid #eee;
    }
    .table-custom td {
        vertical-align: middle;
    }
    .status-badge {
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 500;
    }
</style>
</head>
<body>

<div class="container my-5 main-container">
    <div class="mb-4">
        <a href="index.php" class="text-decoration-none text-secondary">
            <i class="fas fa-arrow-left me-2"></i>Quay về trang chủ
        </a>
    </div>

    <div class="card card-custom mb-5">
        <div class="card-header-custom">
            <h2 class="mb-0 fw-bold"><i class="fas fa-car-wash me-2"></i>Đặt Lịch Rửa Xe</h2>
            <p class="mb-0 opacity-75 mt-1">Chọn dịch vụ và thời gian phù hợp nhất với bạn</p>
        </div>
        <div class="card-body p-4 p-md-5">
            <?= $msg ?>
            <form method="POST">
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label"><i class="far fa-calendar-alt me-1"></i> Ngày đặt</label>
                        <input id="ngaydat" name="NgayDat" class="form-control" placeholder="Chọn ngày..." required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><i class="far fa-clock me-1"></i> Giờ đặt</label>
                        <input id="giodat" name="GioDat" class="form-control" placeholder="Chọn giờ..." required>
                    </div>

                    <div class="col-12">
                        <label class="form-label"><i class="fas fa-car me-1"></i> Loại xe</label>
                        <select name="LoaiXe" class="form-select">
                            <?php foreach ($vehicle_types as $v): ?>
                                <option value="<?= $v ?>"><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label text-primary fw-bold mb-3">Dịch vụ chính</label>
                        <div class="row g-3">
                            <?php foreach ($servicesMain as $s): ?>
                            <div class="col-md-4 col-sm-6">
                                <input type="radio" class="btn-check service-input" 
                                       name="DichVuChinh" 
                                       id="main_svc_<?= $s['MaDichVu'] ?>" 
                                       value="<?= $s['MaDichVu'] ?>" 
                                       data-time="<?= $s['ThoiGian'] ?>" 
                                       data-price="<?= $s['Gia'] ?>" 
                                       required>
                                <label class="service-option-card" for="main_svc_<?= $s['MaDichVu'] ?>">
                                    <div class="fw-bold mb-2"><?= $s['TenDichVu'] ?></div>
                                    <div class="d-flex justify-content-between align-items-end">
                                        <span class="service-price"><?= number_format($s['Gia']) ?> đ</span>
                                        <span class="text-muted small"><i class="far fa-clock"></i> <?= $s['ThoiGian'] ?>p</span>
                                    </div>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label text-primary fw-bold mb-3">Dịch vụ thêm</label>
                        <div class="row g-3">
                            <?php foreach ($servicesExtra as $s): ?>
                            <div class="col-md-4 col-sm-6">
                                <input type="checkbox" class="btn-check service-input" 
                                       name="DichVuThem[]" 
                                       id="extra_svc_<?= $s['MaDichVu'] ?>" 
                                       value="<?= $s['MaDichVu'] ?>" 
                                       data-time="<?= $s['ThoiGian'] ?>" 
                                       data-price="<?= $s['Gia'] ?>">
                                <label class="service-option-card" for="extra_svc_<?= $s['MaDichVu'] ?>">
                                    <div class="mb-2"><?= $s['TenDichVu'] ?></div>
                                    <div class="d-flex justify-content-between align-items-end">
                                        <span class="service-price text-secondary" style="font-size: 1rem;"><?= number_format($s['Gia']) ?> đ</span>
                                        <span class="text-muted small"><i class="far fa-clock"></i> +<?= $s['ThoiGian'] ?>p</span>
                                    </div>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Ghi chú</label>
                        <textarea name="GhiChu" rows="2" class="form-control" placeholder="Ví dụ: Rửa kỹ cái gầm xe..."></textarea>
                    </div>

                    <div class="col-12 mt-4">
                        <div class="alert alert-info border-0 shadow-sm">
                            <div class="d-flex justify-content-between mb-2">
                                <span><i class="fas fa-coins me-2"></i>Tổng chi phí:</span>
                                <span class="fw-bold fs-5 text-primary" id="total_price">0 đ</span>
                            </div>
                            <div class="text-end small text-muted mt-1">
                                Tổng thời gian: <span id="total_duration">0</span> phút | Dự kiến xong: <span id="finish_time">--:--</span>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <button name="book" class="btn btn-primary btn-submit w-100 text-uppercase">
                            Xác nhận đặt lịch
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card card-custom">
        <div class="card-body p-4">
            <h4 class="fw-bold mb-4"><i class="fas fa-history me-2"></i>Lịch sử đặt chỗ</h4>
            <div class="table-responsive">
                <table class="table table-hover table-custom align-middle">
                    <thead>
                        <tr>
                            <th>Thời gian</th>
                            <th>Loại xe</th>
                            <th>Dịch vụ</th>
                            <th>Tổng tiền</th>
                            <th class="text-center">Trạng thái</th>
                            <th class="text-end">Hành động</th>
                            
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($list->num_rows > 0): ?>
                        <?php while ($r = $list->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div class="fw-bold"><?= date('d/m/Y', strtotime($r['NgayDat'])) ?></div>
                                <div class="small text-muted"><?= date('H:i', strtotime($r['GioDat'])) ?></div>
                            </td>
                            <td><?= $r['LoaiXe'] ?></td>
                            <td><small><?= $r['DichVu'] ?></small></td>
                            <td><span class="text-primary fw-bold"><?= number_format($r['TongTien']) ?> đ</span></td>
                            <td class="text-center">
                                <?php 
                                    $statusClass = 'bg-secondary';
                                    if ($r['TrangThai'] === 'Mới') $statusClass = 'bg-success';
                                    elseif ($r['TrangThai'] === 'Đã hủy') $statusClass = 'bg-danger';
                                    elseif ($r['TrangThai'] === 'Đã hoàn thành') $statusClass = 'bg-primary';
                                ?>
                                <span class="badge status-badge <?= $statusClass ?>"><?= $r['TrangThai'] ?></span>
                            </td>
                            <td class="text-end">
                                <?php if ($r['TrangThai'] === 'Mới'): ?>
                                    <?php 
                                        $bookingTime = strtotime($r['NgayDat'] . ' ' . $r['GioDat']);
                                        $timeLeft = $bookingTime - time();
                                    ?>
                                    <?php if ($timeLeft > 1800): ?>
                                        <form method="POST" onsubmit="return confirm('Bạn chắc chắn muốn hủy lịch này?');">
                                            <input type="hidden" name="id" value="<?= $r['MaDatLich'] ?>">
                                            <button class="btn btn-outline-danger btn-sm rounded-pill px-3" name="cancel">
                                                <i class="fas fa-times me-1"></i> Hủy
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-danger small">Đã sát giờ</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">Bạn chưa có lịch đặt nào.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/vn.js"></script>
<script>
    // Flatpickr
    const fpDate = flatpickr("#ngaydat", { 
        locale: "vn", dateFormat: "Y-m-d", minDate: "today", altInput: true, altFormat: "d/m/Y", disableMobile: "true", 
        onChange: calculateTotal 
    });
    const fpTime = flatpickr("#giodat", { 
        enableTime: true, noCalendar: true, dateFormat: "H:i", time_24hr: true, minTime: "07:00", maxTime: "18:30", minuteIncrement: 30, 
        onClose: calculateTotal 
    });

    // Calculate Function
    function calculateTotal() {
        let totalMoney = 0; 
        let totalMinutes = 0;
        document.querySelectorAll('.service-input:checked').forEach(input => {
            totalMoney += parseInt(input.getAttribute('data-price')) || 0;
            totalMinutes += parseInt(input.getAttribute('data-time')) || 0;
        });
        document.getElementById('total_price').innerText = new Intl.NumberFormat('vi-VN').format(totalMoney) + ' đ';
        document.getElementById('total_duration').innerText = totalMinutes;

        const timeValue = document.getElementById('giodat').value;
        if (timeValue) {
            let [hours, mins] = timeValue.split(':').map(Number);
            let dateObj = new Date(); 
            dateObj.setHours(hours); 
            dateObj.setMinutes(mins + totalMinutes);
            
            let endHour = dateObj.getHours().toString().padStart(2, '0');
            let endMin = dateObj.getMinutes().toString().padStart(2, '0');
            document.getElementById('finish_time').innerText = `${endHour}:${endMin}`;
        } else { 
            document.getElementById('finish_time').innerText = "--:--"; 
        }
    }

    // Listener
    document.querySelectorAll('.service-input').forEach(item => { item.addEventListener('change', calculateTotal); });

    // Auto-fill from URL
    const urlParams = new URLSearchParams(window.location.search);
    const paramDate = urlParams.get('date');
    const paramTime = urlParams.get('time');
    if (paramDate && paramTime) {
        if (typeof fpDate !== 'undefined') fpDate.setDate(paramDate, true);
        if (typeof fpTime !== 'undefined') fpTime.setDate(paramTime, true);
        setTimeout(calculateTotal, 100); 
    }
</script>
</body>
</html>