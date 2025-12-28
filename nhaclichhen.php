<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require_once 'database.php';

// Cấu hình múi giờ
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Log file (để tracking)
$logFile = __DIR__ . '/logs/cron_reminder_' . date('Y-m-d') . '.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

function writeLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
    echo "[$timestamp] $message<br>\n";
}

writeLog("=== CRON JOB BẮT ĐẦU ===");

// Tìm lịch hẹn sắp diễn ra trong 15 phút tới
$sql = "
    SELECT 
        dl.MaDatLich, 
        dl.NgayDat, 
        dl.GioDat, 
        dl.LoaiXe,
        dl.GhiChu,
        dl.TongTien,
        nd.Email, 
        nd.HoVaTen,
        nd.SoDienThoai,
        GROUP_CONCAT(dv.TenDichVu SEPARATOR ', ') AS DichVu
    FROM dat_lich dl
    JOIN nguoi_dung nd ON dl.MaNguoiDung = nd.MaNguoiDung
    LEFT JOIN chi_tiet_dat_lich ct ON dl.MaDatLich = ct.MaDatLich
    LEFT JOIN dich_vu dv ON ct.MaDichVu = dv.MaDichVu
    WHERE dl.TrangThai = 'Mới' 
    AND dl.TrangThaiThongBao = 0
    AND TIMESTAMP(CONCAT(dl.NgayDat, ' ', dl.GioDat)) <= DATE_ADD(NOW(), INTERVAL 15 MINUTE)
    AND TIMESTAMP(CONCAT(dl.NgayDat, ' ', dl.GioDat)) > NOW()
    GROUP BY dl.MaDatLich
";

$result = $conn->query($sql);

if (!$result) {
    writeLog("LỖI SQL: " . $conn->error);
    exit;
}

if ($result->num_rows > 0) {
    writeLog("Tìm thấy " . $result->num_rows . " lịch cần nhắc");

    $mail = new PHPMailer(true);
    $successCount = 0;
    $failCount = 0;

    try {
        // Cấu hình SMTP
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'ngohuedinhit@gmail.com';
        $mail->Password   = 'zpgcmuithmirqbnw'; // App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom('ngohuedinhit@gmail.com', 'D2AUTO - Rửa Xe');
        $mail->isHTML(true);

        while ($row = $result->fetch_assoc()) {
            try {
                $toEmail = $row['Email'];
                $name    = $row['HoVaTen'];
                $time    = date('H:i', strtotime($row['GioDat']));
                $date    = date('d/m/Y', strtotime($row['NgayDat']));
                $xe      = $row['LoaiXe'];
                $dichvu  = $row['DichVu'] ?? 'N/A';
                $tongTien = number_format($row['TongTien'], 0, ',', '.');
                $ghichu  = $row['GhiChu'] ?? '';

                // Tính thời gian còn lại
                $appointmentTime = strtotime($row['NgayDat'] . ' ' . $row['GioDat']);
                $minutesLeft = round(($appointmentTime - time()) / 60);

                // Email HTML Template
                $emailBody = "
                <!DOCTYPE html>
                <html lang='vi'>
                <head>
                    <meta charset='UTF-8'>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9; }
                        .header { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                        .content { background: white; padding: 30px; border-radius: 0 0 10px 10px; }
                        .alert { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
                        .info-box { background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0; }
                        .info-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee; }
                        .label { font-weight: bold; color: #666; }
                        .value { color: #333; }
                        .footer { text-align: center; margin-top: 20px; color: #999; font-size: 12px; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h2>🔔 NHẮC LỊCH RỬA XE</h2>
                            <p>Lịch hẹn của bạn sắp đến giờ!</p>
                        </div>
                        <div class='content'>
                            <p>Xin chào <strong>$name</strong>,</p>
                            
                            <div class='alert'>
                                ⏰ Lịch hẹn của bạn sẽ bắt đầu trong <strong>$minutesLeft phút</strong> nữa!
                            </div>
                            
                            <div class='info-box'>
                                <h3 style='margin-top: 0;'>Thông tin lịch hẹn:</h3>
                                <div class='info-row'>
                                    <span class='label'>📅 Ngày:</span>
                                    <span class='value'>$date</span>
                                </div>
                                <div class='info-row'>
                                    <span class='label'>🕐 Giờ:</span>
                                    <span class='value'>$time</span>
                                </div>
                                <div class='info-row'>
                                    <span class='label'>🚗 Loại xe:</span>
                                    <span class='value'>$xe</span>
                                </div>
                                <div class='info-row'>
                                    <span class='label'>🛠️ Dịch vụ:</span>
                                    <span class='value'>$dichvu</span>
                                </div>
                                <div class='info-row'>
                                    <span class='label'>💰 Tổng tiền:</span>
                                    <span class='value'><strong>$tongTien đ</strong></span>
                                </div>
                                " . ($ghichu ? "<div class='info-row'><span class='label'>📝 Ghi chú:</span><span class='value'>$ghichu</span></div>" : "") . "
                            </div>
                            
                            <p><strong>Lưu ý quan trọng:</strong></p>
                            <ul>
                                <li>Vui lòng đến <strong>đúng giờ</strong> để được phục vụ tốt nhất</li>
                                <li>Nếu đến muộn quá <strong>15 phút</strong>, lịch hẹn có thể bị hủy</li>
                                <li>Liên hệ cửa hàng nếu có thay đổi kế hoạch</li>
                            </ul>
                            
                            <p style='margin-top: 20px;'>
                                Cảm ơn bạn đã tin tưởng sử dụng dịch vụ của D2AUTO!
                            </p>
                            
                            <p style='margin-top: 30px;'>
                                Trân trọng,<br>
                                <strong>Đội ngũ D2AUTO</strong>
                            </p>
                        </div>
                        <div class='footer'>
                            <p>Email tự động - Vui lòng không trả lời email này</p>
                            <p>&copy; " . date('Y') . " D2AUTO - Hệ thống đặt lịch rửa xe</p>
                        </div>
                    </div>
                </body>
                </html>
                ";

                $mail->addAddress($toEmail, $name);
                $mail->Subject = '🔔 [NHẮC LỊCH] Lịch rửa xe của bạn sắp đến giờ - D2AUTO';
                $mail->Body    = $emailBody;
                $mail->AltBody = strip_tags($emailBody); // Plain text version

                // Gửi email
                if ($mail->send()) {
                    // Update trạng thái đã gửi
                    $update = $conn->prepare("UPDATE dat_lich SET TrangThaiThongBao = 1 WHERE MaDatLich = ?");
                    $update->bind_param("i", $row['MaDatLich']);
                    $update->execute();

                    writeLog("✓ Đã gửi email cho: $toEmail (ID: {$row['MaDatLich']})");
                    $successCount++;
                } else {
                    writeLog("✗ Không thể gửi email cho: $toEmail - " . $mail->ErrorInfo);
                    $failCount++;
                }

                $mail->clearAddresses();
                
                // Delay nhẹ tránh spam
                usleep(500000); // 0.5 second

            } catch (Exception $e) {
                writeLog("✗ LỖI gửi mail cho {$row['Email']}: {$e->getMessage()}");
                $failCount++;
            }
        }

        writeLog("=== KẾT QUẢ: Thành công: $successCount | Thất bại: $failCount ===");

    } catch (Exception $e) {
        writeLog("LỖI CẤU HÌNH SMTP: {$e->getMessage()}");
    }

} else {
    writeLog("Không có lịch nào cần nhắc lúc này");
}

writeLog("=== CRON JOB KẾT THÚC ===\n");
$conn->close();
?>