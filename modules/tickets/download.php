<?php
require_once __DIR__ . '/../../includes/header.php';

$ticket_id = get('id');
if (!$ticket_id) {
    exit('Ticket ID not found');
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
    exit('Ticket not found');
}

// Create simple HTML that can be printed as PDF
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Ticket - ' . htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']) . '</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .ticket {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
            page-break-after: always;
        }
        .ticket-header {
            background: linear-gradient(135deg, #185FA5 0%, #0F3A6B 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        .ticket-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
            font-weight: 600;
        }
        .ticket-header .event-details {
            font-size: 14px;
            opacity: 0.9;
            line-height: 1.6;
        }
        .ticket-content {
            padding: 30px 20px;
        }
        .attendee-section {
            margin-bottom: 30px;
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }
        .attendee-info {
            flex: 1;
        }
        .qr-section {
            text-align: center;
        }
        .field {
            margin-bottom: 20px;
        }
        .field-label {
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
            font-weight: 600;
        }
        .field-value {
            font-size: 16px;
            color: #333;
            font-weight: 500;
        }
        .ticket-code {
            background: #f5f5f5;
            padding: 12px 16px;
            border-radius: 6px;
            border: 1px solid #ddd;
            margin-top: 20px;
        }
        .ticket-code .label {
            font-size: 10px;
            color: #999;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        .ticket-code .code {
            font-family: "Courier New", monospace;
            font-size: 12px;
            color: #333;
            word-break: break-all;
            line-height: 1.4;
        }
        .qr-image {
            width: 280px;
            height: 280px;
            border: 2px solid #f0f0f0;
            border-radius: 8px;
            margin-bottom: 10px;
            display: inline-block;
        }
        .qr-label {
            font-size: 12px;
            color: #666;
        }
        .ticket-footer {
            border-top: 1px dashed #ddd;
            margin-top: 30px;
            padding-top: 20px;
            text-align: center;
            font-size: 12px;
            color: #999;
        }
        @media print {
            body {
                background: white;
                padding: 0;
            }
            .ticket {
                box-shadow: none;
                margin: 0;
            }
        }
    </style>
</head>
<body>
    <div class="ticket">
        <div class="ticket-header">
            <h1>' . htmlspecialchars($ticket['event_name']) . '</h1>
            <div class="event-details">
                <div>' . date('F d, Y', strtotime($ticket['event_date'])) . ' · ' . date('h:i A', strtotime($ticket['start_time'])) . ' - ' . date('h:i A', strtotime($ticket['end_time'])) . '</div>
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
                    
                    <div class="field">
                        <div class="field-label">Attendee Type</div>
                        <div class="field-value">' . ucfirst(htmlspecialchars($ticket['attendee_type'])) . '</div>
                    </div>
                    
                    <div class="field">
                        <div class="field-label">Ticket Category</div>
                        <div class="field-value">' . htmlspecialchars($ticket['category_name']) . '</div>
                    </div>
                    
                    <div class="field">
                        <div class="field-label">Price</div>
                        <div class="field-value">₱' . number_format($ticket['price'], 2) . '</div>
                    </div>
                    
                    <div class="field">
                        <div class="field-label">Issued Date</div>
                        <div class="field-value">' . date('M d, Y g:i A', strtotime($ticket['purchase_date'])) . '</div>
                    </div>
                    
                    <div class="field">
                        <div class="field-label">Status</div>
                        <div class="field-value" style="color: ' . ($ticket['is_validated'] ? '#3B6D11' : '#888780') . '">
                            ' . ($ticket['is_validated'] ? '✓ Validated' : 'Not Yet Validated') . '
                        </div>
                    </div>
                    
                    <div class="ticket-code">
                        <div class="label">Ticket Code (UUID)</div>
                        <div class="code">' . htmlspecialchars($ticket['ticket_code']) . '</div>
                    </div>
                </div>
                
                <div class="qr-section">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=280x280&data=' . urlencode($ticket['ticket_code']) . '" alt="QR Code" class="qr-image">
                    <div class="qr-label">Scan to validate entry</div>
                </div>
            </div>
            
            <div class="ticket-footer">
                ' . htmlspecialchars($ticket['org_name']) . ' · Issued by EventTicket University System
            </div>
        </div>
    </div>
    
    <script>
        // Auto-print or download as PDF
        window.onload = function() {
            // Uncomment to auto-print on load:
            // window.print();
        };
    </script>
</body>
</html>
';

// Set headers for PDF download
$filename = 'Ticket_' . str_replace(' ', '_', $ticket['first_name']) . '_' . str_replace(' ', '_', $ticket['last_name']) . '.html';

header('Content-Type: text/html; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

echo $html;
?>
