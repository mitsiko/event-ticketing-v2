<?php
require_once __DIR__ . '/includes/header.php';

// Redirect to login if not authenticated
if (!isset($_SESSION['user'])) {
    // header.php already shows login screen, so we just exit
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Get statisticss
$stats = [];

// Total events
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM Event");
$stats['total_events'] = mysqli_fetch_assoc($result)['total'];

// Upcoming events
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM Event WHERE status = 'upcoming'");
$stats['upcoming_events'] = mysqli_fetch_assoc($result)['total'];

// Total attendees
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM Attendee");
$stats['total_attendees'] = mysqli_fetch_assoc($result)['total'];

// Total tickets
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM Ticket");
$stats['total_tickets'] = mysqli_fetch_assoc($result)['total'];

// Validated tickets
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM Ticket WHERE is_validated = 1");
$stats['validated_tickets'] = mysqli_fetch_assoc($result)['total'];

// Revenue
$result = mysqli_query($conn, "
    SELECT SUM(tc.price) as revenue 
    FROM Ticket t 
    JOIN Ticket_Category tc ON t.category_id = tc.category_id 
    WHERE t.payment_status = 'paid'
");
$stats['revenue'] = mysqli_fetch_assoc($result)['revenue'] ?? 0;

// Recent events
$recentEvents = mysqli_query($conn, "
    SELECT e.*, v.venue_name, o.org_name,
           (SELECT SUM(tc2.total_slots) FROM Ticket_Category tc2 WHERE tc2.event_id = e.event_id) as total_slots,
           (SELECT SUM(tc2.total_slots - tc2.slots_remaining) FROM Ticket_Category tc2 WHERE tc2.event_id = e.event_id) as sold
    FROM Event e
    JOIN Venue v ON e.venue_id = v.venue_id
    JOIN Organization o ON e.org_id = o.org_id
    ORDER BY e.event_date DESC
    LIMIT 5
");

// Recent tickets
$recentTickets = mysqli_query($conn, "
    SELECT t.*, a.first_name, a.last_name, e.event_name, tc.category_name
    FROM Ticket t
    JOIN Attendee a ON t.attendee_id = a.attendee_id
    JOIN Ticket_Category tc ON t.category_id = tc.category_id
    JOIN Event e ON tc.event_id = e.event_id
    ORDER BY t.purchase_date DESC
    LIMIT 5
");
?>

<div class="page active" id="page-dashboard">
    <div class="page-header">
        <div>
            <div class="page-title">Dashboard</div>
            <div class="page-sub">Overview of events, attendees, and tickets</div>
        </div>
        <div style="margin-top:10px;">
            <a href="online-reg.php" target="_blank"
               style="display:inline-block;padding:8px 12px;
               background:transparent;color:#4e4e4e;border-radius:6px;
               font-size:12px;text-decoration:underline;">
                View Audience Registration Page
            </a>
        </div>
    </div>

    <?php echo displayMessage(); ?>

    <div class="metrics">
        <div class="metric">
            <div class="metric-val"><?php echo $stats['total_events']; ?></div>
            <div class="metric-label">Total events</div>
        </div>
        <div class="metric">
            <div class="metric-val"><?php echo $stats['upcoming_events']; ?></div>
            <div class="metric-label">Upcoming</div>
        </div>
        <div class="metric">
            <div class="metric-val"><?php echo $stats['total_attendees']; ?></div>
            <div class="metric-label">Attendees</div>
        </div>
        <div class="metric">
            <div class="metric-val"><?php echo $stats['total_tickets']; ?></div>
            <div class="metric-label">Tickets issued</div>
        </div>
        <div class="metric">
            <div class="metric-val"><?php echo $stats['validated_tickets']; ?></div>
            <div class="metric-label">Validated</div>
        </div>
        <div class="metric">
            <div class="metric-val">₱<?php echo number_format($stats['revenue'], 2); ?></div>
            <div class="metric-label">Revenue</div>
        </div>
    </div>

    <div class="card-flush">
        <div style="padding:0.875rem 1rem;font-weight:500;font-size:13px;border-bottom:0.5px solid var(--color-border-tertiary)">Upcoming events</div>
        <div class="tbl-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Date</th>
                        <th>Venue</th>
                        <th>Audience</th>
                        <th>Status</th>
                        <th>Sold / Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($recentEvents) > 0): ?>
                        <?php while ($event = mysqli_fetch_assoc($recentEvents)): ?>
                            <tr>
                                <td><?php echo h($event['event_name']); ?></td>
                                <td><?php echo formatDate($event['event_date']); ?></td>
                                <td><?php echo h($event['venue_name']); ?></td>
                                <td>
                                    <span class="badge <?php echo getAudienceBadge($event['audience_type']); ?>">
                                        <?php echo str_replace('_', ' ', h($event['audience_type'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php echo getStatusBadge($event['status']); ?>">
                                        <?php echo ucfirst(h($event['status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo $event['sold'] ?? 0; ?> / <?php echo $event['total_slots'] ?? 0; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align:center;padding:1.5rem;color:var(--color-text-secondary)">No events found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card-flush">
        <div style="padding:0.875rem 1rem;font-weight:500;font-size:13px;border-bottom:0.5px solid var(--color-border-tertiary)">Recent tickets</div>
        <div class="tbl-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Attendee</th>
                        <th>Event</th>
                        <th>Payment</th>
                        <th>Validated</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($recentTickets) > 0): ?>
                        <?php while ($ticket = mysqli_fetch_assoc($recentTickets)): ?>
                            <tr>
                                <td><code style="font-size:11px"><?php echo h(substr($ticket['ticket_code'], 0, 20)); ?>...</code></td>
                                <td><?php echo h($ticket['first_name'] . ' ' . $ticket['last_name']); ?></td>
                                <td style="font-size:12px"><?php echo h($ticket['event_name']); ?></td>
                                <td>
                                    <span class="badge <?php echo getPaymentBadge($ticket['payment_status']); ?>">
                                        <?php echo ucfirst(h($ticket['payment_status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $ticket['is_validated'] ? 'b-green' : 'b-gray'; ?>">
                                        <?php echo $ticket['is_validated'] ? 'Yes' : 'No'; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align:center;padding:1.5rem;color:var(--color-text-secondary)">No tickets found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
