
<?php
// FILE: config_helper.php
// Helper function để lấy cấu hình hệ thống

function getConfig($key, $default = null) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT value FROM cau_hinh WHERE key_name = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc()['value'];
    }
    
    return $default;
}

// Lấy tất cả cấu hình cùng lúc
function getAllConfig() {
    global $conn;
    
    $configs = [];
    $result = $conn->query("SELECT key_name, value FROM cau_hinh");
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $configs[$row['key_name']] = $row['value'];
        }
    }
    
    return $configs;
}

// Kiểm tra ngày có phải ngày nghỉ không
function isHoliday($date) {
    global $conn;
    
    // Lấy danh sách ngày nghỉ
    $ngayNghi = getConfig('ngay_nghi', '');
    $ngayLeNghi = getConfig('ngay_le_nghi', '');
    
    // Kiểm tra ngày trong tuần (VD: Chủ nhật)
    $dayName = date('l', strtotime($date));
    $dayNameVN = [
        'Monday' => 'Thứ hai',
        'Tuesday' => 'Thứ ba',
        'Wednesday' => 'Thứ tư',
        'Thursday' => 'Thứ năm',
        'Friday' => 'Thứ sáu',
        'Saturday' => 'Thứ bảy',
        'Sunday' => 'Chủ nhật'
    ];
    
    if (stripos($ngayNghi, $dayNameVN[$dayName]) !== false) {
        return true;
    }
    
    // Kiểm tra ngày lễ (VD: 01/01, 30/04)
    $dateFormat = date('d/m', strtotime($date));
    if (stripos($ngayLeNghi, $dateFormat) !== false) {
        return true;
    }
    
    return false;
}
?>