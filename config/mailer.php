<?php
/**
 * BD Bus Track — Email Configuration
 * PHPMailer দিয়ে Gmail SMTP ব্যবহার করে email পাঠানো হয়
 *
 * ✏️  শুধু নিচের দুটো line পরিবর্তন করুন:
 */
define('EMAIL_USER', 'your_gmail@gmail.com');    // আপনার Gmail address
define('EMAIL_PASS', 'xxxx xxxx xxxx xxxx');     // Gmail App Password (16 digit)
// =====================================================
// বাকি কিছু পরিবর্তন করতে হবে না
// =====================================================
define('EMAIL_FROM_NAME', 'BD Bus Track');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../libs/phpmailer/Exception.php';
require_once __DIR__ . '/../libs/phpmailer/PHPMailer.php';
require_once __DIR__ . '/../libs/phpmailer/SMTP.php';

/**
 * Core send function using PHPMailer
 */
function sendSmtpEmail($to_email, $to_name, $subject, $html_body) {
    if (empty($to_email)) return false;

    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = EMAIL_USER;
        $mail->Password   = EMAIL_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        // From
        $mail->setFrom(EMAIL_USER, EMAIL_FROM_NAME);
        $mail->addAddress($to_email, $to_name);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html_body;
        $mail->AltBody = strip_tags(str_replace(['<br>','<br/>','</p>','</div>'], "\n", $html_body));

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("BD Bus Track Mailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Ticket Confirmed Email
 */
function sendTicketEmailSMTP($to_email, $passenger_name, $booking_id, $from_city, $to_city, $journey_date, $seats, $fare, $bus_name, $payment_method = 'cash') {
    if (empty($to_email)) return false;

    $subject        = "🎟️ Ticket Confirmed #$booking_id — BD Bus Track";
    $date_formatted = date('l, d F Y', strtotime($journey_date));
    $fare_fmt       = number_format($fare);
    $pay            = ucfirst($payment_method);

    $html = "
<!DOCTYPE html><html><head><meta charset='UTF-8'></head>
<body style='margin:0;padding:0;background:#f4f4f4;font-family:Arial,sans-serif;'>
<div style='max-width:600px;margin:30px auto;background:white;border-radius:12px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.1);'>
  <div style='background:linear-gradient(135deg,#198754,#0d6efd);padding:30px;text-align:center;'>
    <h1 style='color:white;margin:0;font-size:26px;'>🚌 BD Bus Track</h1>
    <p style='color:rgba(255,255,255,.85);margin:8px 0 0;font-size:15px;'>Your ticket is confirmed!</p>
  </div>
  <div style='background:#f8f9fa;padding:20px;text-align:center;border-bottom:2px dashed #dee2e6;'>
    <p style='margin:0;color:#6c757d;font-size:12px;text-transform:uppercase;letter-spacing:1px;'>Booking ID</p>
    <div style='font-size:42px;font-weight:900;color:#0d6efd;'>#$booking_id</div>
    <span style='background:#198754;color:white;padding:5px 16px;border-radius:20px;font-size:13px;font-weight:bold;'>✓ CONFIRMED</span>
  </div>
  <div style='padding:25px 30px;'>
    <table style='width:100%;border-collapse:collapse;'>
      <tr style='border-bottom:1px solid #f0f0f0;'><td style='padding:10px 0;color:#6c757d;width:40%;'>👤 Passenger</td><td style='padding:10px 0;font-weight:bold;'>$passenger_name</td></tr>
      <tr style='border-bottom:1px solid #f0f0f0;'><td style='padding:10px 0;color:#6c757d;'>🛣️ Route</td><td style='padding:10px 0;font-weight:bold;'>$from_city → $to_city</td></tr>
      <tr style='border-bottom:1px solid #f0f0f0;'><td style='padding:10px 0;color:#6c757d;'>🚌 Bus</td><td style='padding:10px 0;font-weight:bold;'>$bus_name</td></tr>
      <tr style='border-bottom:1px solid #f0f0f0;'><td style='padding:10px 0;color:#6c757d;'>📅 Journey Date</td><td style='padding:10px 0;font-weight:bold;'>$date_formatted</td></tr>
      <tr style='border-bottom:1px solid #f0f0f0;'><td style='padding:10px 0;color:#6c757d;'>💺 Seats</td><td style='padding:10px 0;font-weight:bold;color:#198754;'>$seats</td></tr>
      <tr style='border-bottom:1px solid #f0f0f0;'><td style='padding:10px 0;color:#6c757d;'>💳 Payment</td><td style='padding:10px 0;font-weight:bold;'>$pay</td></tr>
      <tr><td style='padding:12px 0;color:#6c757d;'>💰 Total Fare</td><td style='padding:12px 0;font-size:22px;font-weight:900;color:#198754;'>৳$fare_fmt</td></tr>
    </table>
  </div>
  <div style='background:#fff3cd;margin:0 30px 25px;padding:15px;border-radius:8px;border-left:4px solid #ffc107;'>
    <p style='margin:0;font-size:14px;color:#856404;'>
      <strong>⚠️ Need to cancel?</strong><br>
      Use Booking ID <strong>#$booking_id</strong> to cancel.<br>
      24+ hours before: <strong>80% refund</strong> | Within 24 hours: <strong>50% refund</strong>
    </p>
  </div>
  <div style='background:#212529;padding:18px;text-align:center;'>
    <p style='color:rgba(255,255,255,.6);margin:0;font-size:13px;'>© 2026 BD Bus Track | Have a safe journey! 🙏</p>
  </div>
</div></body></html>";

    return sendSmtpEmail($to_email, $passenger_name, $subject, $html);
}

/**
 * Ticket Cancelled / Refund Email
 */
function sendCancelEmailSMTP($to_email, $passenger_name, $booking_id, $from_city, $to_city, $journey_date, $seats, $refund_amount, $cancel_reason = '') {
    if (empty($to_email)) return false;

    $subject        = "❌ Booking #$booking_id Cancelled — BD Bus Track";
    $date_formatted = date('l, d F Y', strtotime($journey_date));
    $refund_fmt     = number_format($refund_amount);

    $html = "
<!DOCTYPE html><html><head><meta charset='UTF-8'></head>
<body style='margin:0;padding:0;background:#f4f4f4;font-family:Arial,sans-serif;'>
<div style='max-width:600px;margin:30px auto;background:white;border-radius:12px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.1);'>
  <div style='background:linear-gradient(135deg,#dc3545,#c82333);padding:30px;text-align:center;'>
    <h1 style='color:white;margin:0;font-size:26px;'>🚌 BD Bus Track</h1>
    <p style='color:rgba(255,255,255,.85);margin:8px 0 0;font-size:15px;'>Your booking has been cancelled</p>
  </div>
  <div style='background:#f8f9fa;padding:20px;text-align:center;border-bottom:2px dashed #dee2e6;'>
    <p style='margin:0;color:#6c757d;font-size:12px;text-transform:uppercase;letter-spacing:1px;'>Booking ID</p>
    <div style='font-size:42px;font-weight:900;color:#dc3545;'>#$booking_id</div>
    <span style='background:#dc3545;color:white;padding:5px 16px;border-radius:20px;font-size:13px;font-weight:bold;'>✗ CANCELLED</span>
  </div>
  <div style='padding:25px 30px;'>
    <table style='width:100%;border-collapse:collapse;'>
      <tr style='border-bottom:1px solid #f0f0f0;'><td style='padding:10px 0;color:#6c757d;width:40%;'>👤 Passenger</td><td style='padding:10px 0;font-weight:bold;'>$passenger_name</td></tr>
      <tr style='border-bottom:1px solid #f0f0f0;'><td style='padding:10px 0;color:#6c757d;'>🛣️ Route</td><td style='padding:10px 0;font-weight:bold;'>$from_city → $to_city</td></tr>
      <tr style='border-bottom:1px solid #f0f0f0;'><td style='padding:10px 0;color:#6c757d;'>📅 Journey Date</td><td style='padding:10px 0;font-weight:bold;'>$date_formatted</td></tr>
      <tr style='border-bottom:1px solid #f0f0f0;'><td style='padding:10px 0;color:#6c757d;'>💺 Seats</td><td style='padding:10px 0;font-weight:bold;'>$seats</td></tr>
      " . ($cancel_reason ? "<tr style='border-bottom:1px solid #f0f0f0;'><td style='padding:10px 0;color:#6c757d;'>📝 Reason</td><td style='padding:10px 0;font-weight:bold;'>$cancel_reason</td></tr>" : "") . "
      <tr><td style='padding:12px 0;color:#6c757d;'>💰 Refund Amount</td><td style='padding:12px 0;font-size:22px;font-weight:900;color:#198754;'>৳$refund_fmt</td></tr>
    </table>
  </div>
  <div style='background:#d1ecf1;margin:0 30px 25px;padding:15px;border-radius:8px;border-left:4px solid #17a2b8;'>
    <p style='margin:0;font-size:14px;color:#0c5460;'>
      <strong>💳 Refund Info:</strong><br>
      Your refund of <strong>৳$refund_fmt</strong> will be processed within <strong>3–5 business days</strong> to your original payment method.
    </p>
  </div>
  <div style='background:#212529;padding:18px;text-align:center;'>
    <p style='color:rgba(255,255,255,.6);margin:0;font-size:13px;'>© 2026 BD Bus Track | We hope to see you again! 🙏</p>
  </div>
</div></body></html>";

    return sendSmtpEmail($to_email, $passenger_name, $subject, $html);
}
?>
