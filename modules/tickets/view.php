<?php 
require_once __DIR__ . '/../../includes/header.php';

$ticket_id = get('id');
$isPublic = get('public') === '1';

if (!$ticket_id) {
    redirect('/event-ticketing-v2/modules/tickets/');
}

// ... rest of existing query and code

// Then in the HTML, adjust the header for public view:
// After the <div class="page active">, add a conditional header

if ($isPublic): ?>
<div class="page active">
    <div class="page-header">
        <div>
            <div class="page-title">Your Ticket</div>
            <div class="page-sub">View your ticket details and QR code</div>
        </div>
        <div>
            <button class="btn btn-primary" onclick="window.print()">🖨 Print Ticket</button>
            <a href="/event-ticketing-v2/online-reg.php" class="btn">← Back to Events</a>
        </div>
    </div>
    <!-- Show a banner for public users -->
    <div style="background:#fffdf5;border:1px solid #fde68a;border-radius:4px;padding:10px 16px;margin-bottom:16px;font-size:12px;color:#92400e;">
        💡 <strong>Tip:</strong> Print this ticket or save the QR code. You'll need to present it at the event entrance for validation.
    </div>
<?php else: ?>
<div class="page active">
    <div class="page-header">
        <div>
            <div class="page-title">Ticket Details</div>
            <div class="page-sub">View ticket information and QR code</div>
        </div>
        <div>
            <a href="/event-ticketing-v2/modules/tickets/" class="btn">← Back to Tickets</a>
            <button class="btn btn-primary" onclick="window.print()">🖨 Print Ticket</button>
        </div>
    </div>
<?php endif; ?>


<?php require_once __DIR__ . '/../../includes/header.php';

$ticket_id = get('id');
if (!$ticket_id) {
    redirect('/event-ticketing-v2/modules/tickets/');
}

$query = "SELECT t.*, 
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
          WHERE t.ticket_id = $ticket_id";

$result = mysqli_query($conn, $query);
$ticket = mysqli_fetch_assoc($result);

if (!$ticket) {
    $_SESSION['error'] = "Ticket not found.";
    redirect('/event-ticketing-v2/modules/tickets/');
}

// Set page title for print
$printTitle = 'Ticket_' . str_replace(' ', '_', $ticket['first_name']) . '_' . str_replace(' ', '_', $ticket['last_name']);
?>

<script>
document.title = '<?php echo $printTitle; ?>';
</script>

<div class="page active">
    <div class="page-header">
        <div>
            <div class="page-title">Ticket Details</div>
            <div class="page-sub">View ticket information and QR code</div>
        </div>
        <div>
            <a href="/event-ticketing-v2/modules/tickets/" class="btn">← Back to Tickets</a>
            <button class="btn btn-primary" onclick="window.print()">🖨 Print Ticket</button>
        </div>
    </div>

    <div class="ticket-print" id="printable-ticket" style="max-width:500px;margin:0 auto">
        <div class="ticket-hd" style="background:#185FA5;color:#fff;border-radius:var(--border-radius-md);padding:1rem;margin-bottom:1.5rem;text-align:center">
            <div style="font-size:18px;font-weight:500"><?php echo h($ticket['event_name']); ?></div>
            <div style="font-size:13px;opacity:0.85;margin-top:5px">
                <?php echo formatDate($ticket['event_date']); ?> · 
                <?php echo formatTime($ticket['start_time']); ?> — <?php echo formatTime($ticket['end_time']); ?>
            </div>
            <div style="font-size:12px;opacity:0.8"><?php echo h($ticket['venue_name']); ?></div>
        </div>
        
        <div style="display:grid;grid-template-columns:1fr auto;gap:20px">
            <div>
                <div class="ticket-field" style="margin-bottom:12px">
                    <p style="font-size:11px;color:var(--color-text-secondary);margin:0">Attendee</p>
                    <p style="font-size:15px;font-weight:500;margin:2px 0"><?php echo h($ticket['first_name'] . ' ' . $ticket['last_name']); ?></p>
                </div>
                <div class="ticket-field" style="margin-bottom:12px">
                    <p style="font-size:11px;color:var(--color-text-secondary);margin:0">Attendee Type</p>
                    <p style="font-size:14px;margin:2px 0"><?php echo ucfirst(h($ticket['attendee_type'])); ?></p>
                </div>
                <div class="ticket-field" style="margin-bottom:12px">
                    <p style="font-size:11px;color:var(--color-text-secondary);margin:0">Ticket Category</p>
                    <p style="font-size:14px;margin:2px 0"><?php echo h($ticket['category_name']); ?></p>
                </div>
                <div class="ticket-field" style="margin-bottom:12px">
                    <p style="font-size:11px;color:var(--color-text-secondary);margin:0">Payment Status</p>
                    <p style="font-size:14px;margin:2px 0">
                        <span class="badge <?php echo getPaymentBadge($ticket['payment_status']); ?>"><?php echo ucfirst(h($ticket['payment_status'])); ?></span>
                        <?php if ($ticket['price'] > 0): ?>
                            (₱<?php echo number_format($ticket['price'], 2); ?>)
                        <?php endif; ?>
                    </p>
                </div>
                <div class="ticket-field" style="margin-bottom:12px">
                    <p style="font-size:11px;color:var(--color-text-secondary);margin:0">Issued</p>
                    <p style="font-size:13px;margin:2px 0"><?php echo formatDateTime($ticket['purchase_date']); ?></p>
                </div>
                <div class="ticket-field" style="margin-bottom:12px">
                    <p style="font-size:11px;color:var(--color-text-secondary);margin:0">Status</p>
                    <p style="font-size:14px;margin:2px 0">
                        <?php if ($ticket['is_validated']): ?>
                            <span style="color:#3B6D11;font-weight:500">✓ Validated</span>
                            <br><small style="font-size:11px"><?php echo formatDateTime($ticket['validated_at']); ?></small>
                        <?php else: ?>
                            <span style="color:#888780">Not yet validated</span>
                        <?php endif; ?>
                    </p>
                </div>
                <div style="margin-top:12px;padding:10px;background:var(--color-background-secondary);border-radius:6px">
                    <p style="font-size:10px;color:var(--color-text-secondary);margin:0 0 4px 0">Ticket Code (UUID)</p>
                    <code style="font-size:11px;word-break:break-all"><?php echo h($ticket['ticket_code']); ?></code>
                </div>
            </div>
            
            <div style="text-align:center">
                <div id="qrcode" style="padding:16px;background:#fff;display:inline-block;border-radius:4px"></div>
                <p style="font-size:10px;color:var(--color-text-secondary);margin-top:8px">Scan to validate entry</p>
            </div>
        </div>
        
        <div style="border-top:0.5px dashed var(--color-border-tertiary);margin-top:20px;padding-top:16px;font-size:11px;color:var(--color-text-secondary);text-align:center">
            <?php echo h($ticket['org_name']); ?> · Issued by EventTicket University System
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
new QRCode(document.getElementById("qrcode"), {
    text: "<?php echo h($ticket['ticket_code']); ?>",
    width: 300,
    height: 300,
    colorDark: "#000000",
    colorLight: "#ffffff",
    correctLevel: QRCode.CorrectLevel.H
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>