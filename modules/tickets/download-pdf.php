<?php
// Database connection only
require_once __DIR__ . '/../../config/database.php';

$ticket_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$ticket_id) {
    die('Ticket ID not found');
}

$query = "SELECT t.*, 
          a.first_name, a.last_name, a.email, a.attendee_type,
          tc.category_name, tc.price,
          e.event_name, e.event_date, e.start_time, e.end_time,
          v.venue_name,
          o.org_name
          FROM Ticket t
          JOIN Attendee a ON t.attendee_id = a.attendee_id
          JOIN Ticket_Category tc ON t.category_id = tc.category_id
          JOIN Event e ON tc.event_id = e.event_id
          JOIN Venue v ON e.venue_id = v.venue_id
          JOIN Organization o ON e.org_id = o.org_id
          WHERE t.ticket_id = $ticket_id";

$result = mysqli_query($conn, $query);
$ticket = mysqli_fetch_assoc($result);

if (!$ticket) {
    die('Ticket not found');
}

// Download HTML that can be printed to PDF
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket - ' . htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']) . '</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .container {
            max-width: 700px;
            margin: 0 auto;
        }
        .ticket {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            overflow: hidden;
            page-break-after: always;
        }
        .ticket-header {
            background: linear-gradient(135deg, #185FA5 0%, #0F3A6B 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        .ticket-header h1 {
            font-size: 32px;
            margin-bottom: 12px;
            font-weight: 600;
            letter-spacing: -0.5px;
        }
        .ticket-header .event-details {
            font-size: 14px;
            opacity: 0.9;
            line-height: 1.8;
        }
        .ticket-content {
            padding: 40px 30px;
        }
        .attendee-section {
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 40px;
            margin-bottom: 30px;
        }
        .attendee-info {
            flex: 1;
        }
        .qr-section {
            text-align: center;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 15px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        .field {
            margin-bottom: 20px;
        }
        .field-label {
            font-size: 11px;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 6px;
            font-weight: 700;
        }
        .field-value {
            font-size: 16px;
            color: #333;
            font-weight: 500;
            line-height: 1.4;
        }
        .ticket-code {
            background: #fafafa;
            padding: 16px;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            margin-top: 25px;
        }
        .ticket-code .label {
            font-size: 10px;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 8px;
            font-weight: 700;
        }
        .ticket-code .code {
            font-family: "Courier New", "Courier", monospace;
            font-size: 13px;
            color: #333;
            word-break: break-all;
            line-height: 1.6;
            background: white;
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #e0e0e0;
        }
        .qr-image {
            width: 260px;
            height: 260px;
            margin-bottom: 12px;
            border-radius: 8px;
            display: block;
        }
        .qr-label {
            font-size: 11px;
            color: #666;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .ticket-code-extractable {
            font-family: "Courier New", "Courier", monospace;
            font-size: 10px;
            color: #999;
            margin-top: 8px;
            padding: 4px 8px;
            background: #f5f5f5;
            border-radius: 4px;
            word-break: break-all;
        }
        .ticket-footer {
            border-top: 1px dashed #ddd;
            margin-top: 35px;
            padding-top: 20px;
            text-align: center;
            font-size: 12px;
            color: #999;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        .price-badge {
            background: #EAF3DE;
            color: #3B6D11;
            padding: 8px 12px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            display: inline-block;
            margin-top: 6px;
        }
        @media print {
            body {
                background: white;
                padding: 0;
            }
            .ticket {
                box-shadow: none;
                margin: 0;
                border-radius: 0;
            }
            .container {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="ticket">
            <div class="ticket-header">
                <h1>' . htmlspecialchars($ticket['event_name']) . '</h1>
                <div class="event-details">
                    <div>' . date('F d, Y', strtotime($ticket['event_date'])) . '</div>
                    <div>' . date('h:i A', strtotime($ticket['start_time'])) . ' — ' . date('h:i A', strtotime($ticket['end_time'])) . '</div>
                    <div>' . htmlspecialchars($ticket['venue_name']) . '</div>
                </div>
            </div>
            
            <div class="ticket-content">
                <div class="attendee-section">
                    <div class="attendee-info">
                        <div class="field">
                            <div class="field-label">Attendee Name</div>
                            <div class="field-value">' . htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']) . '</div>
                        </div>
                        
                        <div class="info-grid">
                            <div class="field">
                                <div class="field-label">Attendee Type</div>
                                <div class="field-value">' . ucfirst(htmlspecialchars($ticket['attendee_type'])) . '</div>
                            </div>
                            
                            <div class="field">
                                <div class="field-label">Ticket Category</div>
                                <div class="field-value">' . htmlspecialchars($ticket['category_name']) . '</div>
                            </div>
                        </div>
                        
                        <div class="field">
                            <div class="field-label">Price</div>
                            <div class="price-badge">₱' . number_format($ticket['price'], 2) . '</div>
                        </div>
                        
                        <div class="info-grid">
                            <div class="field">
                                <div class="field-label">Issued Date</div>
                                <div class="field-value">' . date('M d, Y', strtotime($ticket['purchase_date'])) . '</div>
                            </div>
                            
                            <div class="field">
                                <div class="field-label">Status</div>
                                <div class="field-value" style="color: ' . ($ticket['is_validated'] ? '#3B6D11' : '#888780') . '">
                                    ' . ($ticket['is_validated'] ? '✓ Valid' : 'Pending') . '
                                </div>
                            </div>
                        </div>
                        
                        <div class="ticket-code">
                            <div class="label">Ticket Code</div>
                            <div class="code">' . htmlspecialchars($ticket['ticket_code']) . '</div>
                        </div>
                    </div>
                    
                    <div class="qr-section">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=' . urlencode($ticket['ticket_code']) . '&margin=2" alt="QR Code" class="qr-image">
                        <div class="qr-label">Scan to Validate</div>
                        <div class="ticket-code-extractable">' . htmlspecialchars($ticket['ticket_code']) . '</div>
                    </div>
                </div>
                
                <div class="ticket-footer">
                    ' . htmlspecialchars($ticket['org_name']) . ' · Issued by EventTicket University System
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Suggest download as PDF using browser print
        if (window.location.search.indexOf("print=1") !== -1) {
            window.print();
        }
    </script>
</body>
</html>
';

// Set filename with attendee name
$filename = 'Ticket_' . str_replace(' ', '_', $ticket['first_name']) . '_' . str_replace(' ', '_', $ticket['last_name']) . '.html';

// Output HTML file with download headers
header('Content-Type: text/html; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: public');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');

echo $html;
?>
