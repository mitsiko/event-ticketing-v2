<?php
/**
 * PUBLIC Ticket Lookup Page
 * Shows ticket details and QR code without admin authentication
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$basePath = dirname($_SERVER['SCRIPT_NAME']);
$ticket_code = isset($_GET['code']) ? trim($_GET['code']) : '';

if (empty($ticket_code)) {
    header('Location: ' . $basePath . '/online-reg.php');
    exit;
}

// Look up the ticket
$ticket_code_esc = mysqli_real_escape_string($conn, $ticket_code);
$query = "
    SELECT t.*, 
           a.first_name, a.last_name, a.email, a.attendee_type,
           a.student_id, a.employee_id, a.alumni_id, a.guest_id,
           tc.category_name, tc.price, tc.eligible_type,
           e.event_name, e.event_date, e.start_time, e.end_time, e.audience_type,
           v.venue_name, v.building,
           o.org_name
    FROM Ticket t
    JOIN Attendee a ON t.attendee_id = a.attendee_id
    JOIN Ticket_Category tc ON t.category_id = tc.category_id
    JOIN Event e ON tc.event_id = e.event_id
    JOIN Venue v ON e.venue_id = v.venue_id
    JOIN Organization o ON e.org_id = o.org_id
    WHERE t.ticket_code = '$ticket_code_esc'
";

$result = mysqli_query($conn, $query);
$ticket = mysqli_fetch_assoc($result);

if (!$ticket) {
    $error = "Ticket not found. Please check your ticket code and try again.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Ticket — <?php echo isset($ticket) ? h($ticket['event_name']) : 'Not Found'; ?></title>
    <link rel="stylesheet" href="<?php echo $basePath; ?>/assets/css/user-reg.css">
    <link href="https://fonts.googleapis.com/css2?family=Anton&family=Manrope:wght@200..800&family=Michroma&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .ticket-page { min-height: 100vh; background: #f8f5f6; padding: 20px; }
        .ticket-container { max-width: 600px; margin: 0 auto; }
        .ticket-header-bar { background: #7e1416; color: white; padding: 14px 20px; border-radius: 6px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        .ticket-header-bar a { color: rgba(255,255,255,0.85); text-decoration: none; font-size: 13px; }
        .ticket-header-bar a:hover { color: #f9be1b; }
        .ticket-card { background: #fffdf5; border: 2px solid #f9be1b; border-radius: 8px; overflow: hidden; }
        .ticket-card-header { background: #7e1416; color: white; padding: 24px; text-align: center; }
        .ticket-card-header h2 { font-family: 'Inter', serif; font-size: 22px; margin: 0 0 6px; }
        .ticket-card-header p { margin: 2px 0; font-size: 13px; opacity: 0.9; }
        .ticket-card-body { padding: 24px; }
        .ticket-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #fef3c7; font-size: 13px; }
        .ticket-row-label { color: #737373; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .ticket-row-value { color: #1a1a1a; font-weight: 500; text-align: right; }
        .ticket-code-box { background: #7e1416; border-radius: 6px; padding: 18px; text-align: center; margin: 20px 0; }
        .ticket-code-label { color: rgba(255,255,255,0.8); font-size: 10px; font-weight: 700; text-transform: uppercase; margin-bottom: 6px; }
        .ticket-code-value { color: #f9be1b; font-size: 15px; font-family: 'Courier New', monospace; word-break: break-all; background: rgba(0,0,0,0.2); padding: 10px; border-radius: 4px; display: block; }
        .qr-container { text-align: center; padding: 16px; background: white; border-radius: 6px; border: 1px solid #e5e5e5; margin: 16px 0; }
        .actions-bar { display: flex; gap: 10px; margin-top: 20px; flex-wrap: wrap; }
        .btn-print { padding: 12px 24px; background: #7e1416; color: white; border: 1px solid #7e1416; border-radius: 4px; font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 6px; flex: 1; }
        .btn-print:hover { background: #5c0e10; }
        .btn-back { padding: 12px 24px; background: #fdfbfc; color: #525252; border: 1px solid #e5e5e5; border-radius: 4px; font-size: 14px; font-weight: 500; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; flex: 1; }
        .btn-back:hover { background: #f8f5f6; }
        .error-card { background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 40px; text-align: center; }
        .error-card h2 { color: #991b1b; font-size: 20px; margin: 0 0 8px; }
        .error-card p { color: #7f1d1d; margin: 0 0 20px; }
        .status-badge { display: inline-block; padding: 3px 10px; border-radius: 3px; font-size: 11px; font-weight: 700; }
        .status-validated { background: #f0fdf4; color: #166534; }
        .status-pending { background: #f5f5f5; color: #737373; }
        @media print { 
            body * { visibility: hidden; } 
            .ticket-card, .ticket-card * { visibility: visible; }
            .ticket-card { position: absolute; left: 0; top: 0; width: 100%; box-shadow: none; border: 2px solid #000; }
            .actions-bar, .ticket-header-bar { display: none !important; }
        }
    </style>
</head>
<body class="ticket-page">
    <div class="ticket-container">
        <div class="ticket-header-bar">
            <span>UPHSD Molino — Event Ticket</span>
            <a href="<?php echo $basePath; ?>/online-reg.php">← Back to Events</a>
        </div>

        <?php if (isset($ticket) && $ticket): ?>
            <div class="ticket-card">
                <div class="ticket-card-header">
                    <h2><?php echo h($ticket['event_name']); ?></h2>
                    <p><?php echo date('F d, Y', strtotime($ticket['event_date'])); ?> · <?php echo date('g:i A', strtotime($ticket['start_time'])); ?> - <?php echo date('g:i A', strtotime($ticket['end_time'])); ?></p>
                    <p><?php echo h($ticket['venue_name']); ?></p>
                </div>
                <div class="ticket-card-body">
                    <div class="ticket-row">
                        <span class="ticket-row-label">Attendee</span>
                        <span class="ticket-row-value"><?php echo h($ticket['first_name'] . ' ' . $ticket['last_name']); ?></span>
                    </div>
                    <div class="ticket-row">
                        <span class="ticket-row-label">Type</span>
                        <span class="ticket-row-value"><?php echo ucfirst(h($ticket['attendee_type'])); ?></span>
                    </div>
                    <div class="ticket-row">
                        <span class="ticket-row-label">Category</span>
                        <span class="ticket-row-value"><?php echo h($ticket['category_name']); ?></span>
                    </div>
                    <div class="ticket-row">
                        <span class="ticket-row-label">Price</span>
                        <span class="ticket-row-value"><?php echo $ticket['price'] > 0 ? '₱' . number_format($ticket['price'], 2) : 'FREE'; ?></span>
                    </div>
                    <div class="ticket-row">
                        <span class="ticket-row-label">Status</span>
                        <span class="ticket-row-value">
                            <?php if ($ticket['is_validated']): ?>
                                <span class="status-badge status-validated">✓ Validated</span>
                            <?php else: ?>
                                <span class="status-badge status-pending">Not Yet Validated</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="ticket-row">
                        <span class="ticket-row-label">Organizer</span>
                        <span class="ticket-row-value"><?php echo h($ticket['org_name']); ?></span>
                    </div>

                    <div class="ticket-code-box">
                        <p class="ticket-code-label">Ticket Code</p>
                        <code class="ticket-code-value"><?php echo h($ticket['ticket_code']); ?></code>
                    </div>

                    <div class="qr-container">
                        <div id="qrcode" style="display:inline-block;"></div>
                        <p style="font-size:11px;color:#737373;margin-top:8px;">Present this QR code at the event entrance</p>
                    </div>

                    <div class="actions-bar">
                        <button class="btn-print" onclick="window.print()">🖨 Print Ticket</button>
                        <a href="<?php echo $basePath; ?>/online-reg.php" class="btn-back">← Back to Events</a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="error-card">
                <div style="font-size:48px;margin-bottom:12px;">🔍</div>
                <h2>Ticket Not Found</h2>
                <p><?php echo isset($error) ? h($error) : 'Please check your ticket code and try again.'; ?></p>
                <a href="<?php echo $basePath; ?>/online-reg.php" class="btn-back" style="display:inline-flex;">← Back to Events</a>
            </div>
        <?php endif; ?>
    </div>

    <?php if (isset($ticket) && $ticket): ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        new QRCode(document.getElementById("qrcode"), {
            text: "<?php echo h($ticket['ticket_code']); ?>",
            width: 200,
            height: 200,
            colorDark: "#7e1416",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.H
        });
    </script>
    <?php endif; ?>
</body>
</html>