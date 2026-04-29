<?php
/**
 * View Attendee Details
 * Shows comprehensive attendee profile with ticket history
 */
require_once __DIR__ . '/../../includes/header.php';

$attendee_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$attendee_id) {
    $_SESSION['error'] = "No attendee specified.";
    redirect('/event-ticketing-v2/modules/attendees/');
}

// Fetch attendee details
$result = mysqli_query($conn, "SELECT * FROM Attendee WHERE attendee_id = $attendee_id");
$attendee = mysqli_fetch_assoc($result);

if (!$attendee) {
    $_SESSION['error'] = "Attendee not found.";
    redirect('/event-ticketing-v2/modules/attendees/');
}

// Fetch attendee's tickets with event details
$ticketsQuery = "
    SELECT t.*, 
           tc.category_name, tc.price, tc.eligible_type,
           e.event_id, e.event_name, e.event_date, e.start_time, e.end_time, e.event_type, e.status as event_status,
           v.venue_name,
           o.org_name
    FROM Ticket t
    JOIN Ticket_Category tc ON t.category_id = tc.category_id
    JOIN Event e ON tc.event_id = e.event_id
    JOIN Venue v ON e.venue_id = v.venue_id
    JOIN Organization o ON e.org_id = o.org_id
    WHERE t.attendee_id = $attendee_id
    ORDER BY t.purchase_date DESC
";
$tickets = mysqli_query($conn, $ticketsQuery);
$totalTickets = $tickets ? mysqli_num_rows($tickets) : 0;

// Fetch ticket statistics
$statsQuery = "
    SELECT 
        COUNT(*) as total_tickets,
        SUM(CASE WHEN is_validated = 1 THEN 1 ELSE 0 END) as validated_tickets,
        SUM(CASE WHEN is_validated = 0 THEN 1 ELSE 0 END) as pending_tickets,
        SUM(CASE WHEN payment_status = 'paid' THEN tc.price ELSE 0 END) as total_spent,
        SUM(CASE WHEN payment_status = 'pending' THEN tc.price ELSE 0 END) as total_pending
    FROM Ticket t
    JOIN Ticket_Category tc ON t.category_id = tc.category_id
    WHERE t.attendee_id = $attendee_id
";
$statsResult = mysqli_query($conn, $statsQuery);
$stats = mysqli_fetch_assoc($statsResult);

// Build attendee detail string
$detailInfo = '';
$detailLabel = '';
switch ($attendee['attendee_type']) {
    case 'student':
        $detailLabel = 'Student ID';
        $detailInfo = $attendee['student_id'] ? $attendee['student_id'] : 'N/A';
        if ($attendee['program']) $detailInfo .= ' · ' . $attendee['program'];
        if ($attendee['year_level']) $detailInfo .= ' · Year ' . $attendee['year_level'];
        break;
    case 'employee':
        $detailLabel = 'Employee ID';
        $detailInfo = $attendee['employee_id'] ? $attendee['employee_id'] : 'N/A';
        if ($attendee['job_title']) $detailInfo .= ' · ' . $attendee['job_title'];
        if ($attendee['department']) $detailInfo .= ' · ' . $attendee['department'];
        break;
    case 'alumni':
        $detailLabel = 'Alumni ID';
        $detailInfo = $attendee['alumni_id'] ? $attendee['alumni_id'] : 'N/A';
        if ($attendee['graduation_year']) $detailInfo .= ' · Batch ' . $attendee['graduation_year'];
        break;
    case 'guest':
        $detailLabel = 'Guest ID';
        $detailInfo = $attendee['guest_id'] ? $attendee['guest_id'] : 'N/A';
        break;
}

// Calculate age
$birthDate = new DateTime($attendee['birth_date']);
$today = new DateTime();
$age = $today->diff($birthDate)->y;
?>

<div class="page active">
    <div class="page-header">
        <div>
            <div class="page-title">Attendee Details</div>
            <div class="page-sub">View attendee profile and ticket history</div>
        </div>
        <div style="display:flex;gap:8px;">
            <a href="/event-ticketing-v2/modules/attendees/edit.php?id=<?php echo $attendee_id; ?>" class="btn">Edit Attendee</a>
            <a href="/event-ticketing-v2/modules/attendees/" class="btn">← Back to Attendees</a>
        </div>
    </div>

    <?php echo displayMessage(); ?>

    <!-- Stats Row -->
    <div class="metrics" style="grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));">
        <div class="metric">
            <div class="metric-val"><?php echo $totalTickets; ?></div>
            <div class="metric-label">Total Tickets</div>
        </div>
        <div class="metric">
            <div class="metric-val"><?php echo $stats['validated_tickets'] ?? 0; ?></div>
            <div class="metric-label">Validated</div>
        </div>
        <div class="metric">
            <div class="metric-val"><?php echo $stats['pending_tickets'] ?? 0; ?></div>
            <div class="metric-label">Pending</div>
        </div>
        <div class="metric">
            <div class="metric-val">₱<?php echo number_format($stats['total_spent'] ?? 0, 2); ?></div>
            <div class="metric-label">Total Spent</div>
        </div>
        <div class="metric">
            <div class="metric-val">₱<?php echo number_format($stats['total_pending'] ?? 0, 2); ?></div>
            <div class="metric-label">Pending Payment</div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 380px;gap:20px;align-items:start;">

        <!-- Left Column: Ticket History -->
        <div>
            <div class="card-flush">
                <div style="padding:0.75rem 1rem;font-weight:600;font-size:13px;color:var(--color-primary);border-bottom:1px solid var(--color-border);display:flex;justify-content:space-between;align-items:center;">
                    <span>Ticket History</span>
                    <a href="/event-ticketing-v2/modules/tickets/generate.php" class="btn btn-sm btn-primary">+ Generate New Ticket</a>
                </div>
                <div class="tbl-wrap">
                    <?php if ($tickets && mysqli_num_rows($tickets) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Event</th>
                                    <th>Category</th>
                                    <th>Date</th>
                                    <th>Payment</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($ticket = mysqli_fetch_assoc($tickets)): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight:500;font-size:13px;"><?php echo h($ticket['event_name']); ?></div>
                                            <div style="font-size:11px;color:var(--color-text-secondary);">
                                                <?php echo h($ticket['venue_name']); ?> · 
                                                <span class="badge <?php echo getEventTypeBadge($ticket['event_type']); ?>" style="font-size:10px;">
                                                    <?php echo ucfirst($ticket['event_type']); ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-size:12px;font-weight:500;"><?php echo h($ticket['category_name']); ?></div>
                                            <div style="font-size:11px;color:var(--color-text-secondary);">
                                                <?php echo $ticket['price'] > 0 ? '₱' . number_format($ticket['price'], 2) : 'Free'; ?>
                                            </div>
                                        </td>
                                        <td style="font-size:12px;">
                                            <?php echo formatDate($ticket['event_date']); ?>
                                            <div style="font-size:10px;color:var(--color-text-secondary);">
                                                <?php echo date('g:i A', strtotime($ticket['start_time'])); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo getPaymentBadge($ticket['payment_status']); ?>">
                                                <?php echo ucfirst($ticket['payment_status']); ?>
                                            </span>
                                            <?php if ($ticket['payment_method']): ?>
                                                <div style="font-size:10px;color:var(--color-text-secondary);margin-top:2px;">
                                                    <?php echo ucfirst(str_replace('_', ' ', $ticket['payment_method'])); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($ticket['is_validated']): ?>
                                                <span class="badge b-green">✓ Validated</span>
                                                <div style="font-size:10px;color:var(--color-text-secondary);margin-top:2px;">
                                                    <?php echo formatDateTime($ticket['validated_at']); ?>
                                                </div>
                                            <?php else: ?>
                                                <?php if ($ticket['event_status'] === 'completed'): ?>
                                                    <span class="badge b-gray">Missed</span>
                                                <?php elseif ($ticket['event_status'] === 'cancelled'): ?>
                                                    <span class="badge b-red">Cancelled</span>
                                                <?php else: ?>
                                                    <span class="badge b-amber">Pending</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="actions" style="display:flex;gap:4px;flex-wrap:wrap;">
                                                <a href="/event-ticketing-v2/modules/tickets/view.php?id=<?php echo $ticket['ticket_id']; ?>" 
                                                   class="btn btn-sm" title="View Ticket">View</a>
                                                <a href="/event-ticketing-v2/modules/tickets/edit.php?id=<?php echo $ticket['ticket_id']; ?>" 
                                                   class="btn btn-sm" title="Edit Ticket">Edit</a>
                                                <?php if (!$ticket['is_validated'] && $ticket['event_status'] === 'upcoming'): ?>
                                                    <a href="/event-ticketing-v2/modules/tickets/validate.php?code=<?php echo $ticket['ticket_code']; ?>" 
                                                       class="btn btn-sm btn-success" title="Validate Entry">✓</a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div style="text-align:center;padding:3rem;color:var(--color-text-secondary);">
                            <div style="font-size:2.5rem;margin-bottom:0.75rem;">🎫</div>
                            <p style="margin:0;font-size:14px;font-weight:500;">No tickets found</p>
                            <p style="margin:4px 0 0;font-size:12px;">This attendee hasn't registered for any events yet.</p>
                            <a href="/event-ticketing-v2/modules/tickets/generate.php" class="btn btn-primary" style="margin-top:12px;">
                                Generate First Ticket
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Column: Attendee Profile -->
        <div>
            <!-- Profile Card -->
            <div class="card">
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
                    <div style="width:48px;height:48px;border-radius:50%;background:var(--color-primary);color:white;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:20px;flex-shrink:0;">
                        <?php echo strtoupper(substr($attendee['first_name'], 0, 1)); ?>
                    </div>
                    <div style="min-width:0;">
                        <div style="font-weight:600;font-size:15px;color:var(--color-text-primary);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                            <?php echo h($attendee['first_name'] . ' ' . $attendee['last_name']); ?>
                        </div>
                        <div style="margin-top:4px;">
                            <span class="badge <?php echo getAttendeeTypeBadge($attendee['attendee_type']); ?>">
                                <?php echo ucfirst($attendee['attendee_type']); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div style="display:flex;flex-direction:column;gap:8px;font-size:13px;">
                    <div class="profile-field">
                        <span class="profile-label">Email</span>
                        <span class="profile-value"><?php echo h($attendee['email']); ?></span>
                    </div>
                    <div class="profile-field">
                        <span class="profile-label">Phone</span>
                        <span class="profile-value"><?php echo h($attendee['phone'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="profile-field">
                        <span class="profile-label">Gender</span>
                        <span class="profile-value"><?php echo ucfirst(str_replace('_', ' ', $attendee['gender'])); ?></span>
                    </div>
                    <div class="profile-field">
                        <span class="profile-label">Birth Date</span>
                        <span class="profile-value"><?php echo formatDate($attendee['birth_date']); ?> (Age: <?php echo $age; ?>)</span>
                    </div>
                    <div class="profile-field">
                        <span class="profile-label"><?php echo $detailLabel; ?></span>
                        <span class="profile-value"><?php echo h($detailInfo); ?></span>
                    </div>
                    <?php if ($attendee['department']): ?>
                    <div class="profile-field">
                        <span class="profile-label">Department</span>
                        <span class="profile-value"><?php echo h($attendee['department']); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="profile-field">
                        <span class="profile-label">Registered</span>
                        <span class="profile-value"><?php echo formatDateTime($attendee['registered_at']); ?></span>
                    </div>
                </div>

                <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--color-border);display:flex;gap:8px;">
                    <a href="/event-ticketing-v2/modules/attendees/edit.php?id=<?php echo $attendee_id; ?>" 
                       class="btn btn-sm" style="flex:1;">Edit Profile</a>
                    <a href="/event-ticketing-v2/modules/tickets/generate.php" 
                       class="btn btn-sm btn-primary" style="flex:1;">New Ticket</a>
                </div>
            </div>

            <!-- Event Participation Summary -->
            <?php if ($totalTickets > 0): ?>
            <div class="card">
                <div style="font-size:12px;font-weight:700;color:var(--color-primary);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:12px;">
                    Participation Summary
                </div>
                
                <?php
                // Reset pointer and calculate summaries
                mysqli_data_seek($tickets, 0);
                $eventTypes = [];
                $upcomingCount = 0;
                $pastCount = 0;
                $totalSpent = 0;
                
                while ($t = mysqli_fetch_assoc($tickets)) {
                    $etype = $t['event_type'];
                    if (!isset($eventTypes[$etype])) $eventTypes[$etype] = 0;
                    $eventTypes[$etype]++;
                    
                    if (strtotime($t['event_date']) >= strtotime('today') && $t['event_status'] === 'upcoming') {
                        $upcomingCount++;
                    } else {
                        $pastCount++;
                    }
                    
                    if ($t['payment_status'] === 'paid') {
                        $totalSpent += $t['price'];
                    }
                }
                ?>
                
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:12px;">
                    <div style="background:var(--color-info-bg);padding:10px;border-radius:var(--radius-sm);text-align:center;">
                        <div style="font-size:20px;font-weight:700;color:#1e40af;"><?php echo $upcomingCount; ?></div>
                        <div style="font-size:10px;color:#1e40af;font-weight:600;text-transform:uppercase;">Upcoming</div>
                    </div>
                    <div style="background:var(--color-bg-tertiary);padding:10px;border-radius:var(--radius-sm);text-align:center;">
                        <div style="font-size:20px;font-weight:700;color:var(--color-text-secondary);"><?php echo $pastCount; ?></div>
                        <div style="font-size:10px;color:var(--color-text-secondary);font-weight:600;text-transform:uppercase;">Past Events</div>
                    </div>
                </div>

                <?php if (!empty($eventTypes)): ?>
                <div style="display:flex;flex-direction:column;gap:6px;">
                    <?php 
                    $typeColors = [
                        'academic' => ['bg' => '#eff6ff', 'color' => '#1e40af'],
                        'cultural' => ['bg' => '#f0fdf4', 'color' => '#166534'],
                        'sports' => ['bg' => '#fffbeb', 'color' => '#92400e'],
                        'concert' => ['bg' => '#faf5ff', 'color' => '#6b21a8'],
                        'seminar' => ['bg' => '#f0fdfa', 'color' => '#0f766e'],
                        'graduation' => ['bg' => '#eef2ff', 'color' => '#3730a3'],
                        'orientation' => ['bg' => '#fef2f2', 'color' => '#991b1b'],
                        'other' => ['bg' => 'var(--color-bg-tertiary)', 'color' => 'var(--color-text-secondary)'],
                    ];
                    foreach ($eventTypes as $etype => $count): 
                        $colors = $typeColors[$etype] ?? $typeColors['other'];
                    ?>
                        <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 8px;background:<?php echo $colors['bg']; ?>;border-radius:var(--radius-xs);">
                            <span style="font-size:12px;font-weight:500;color:<?php echo $colors['color']; ?>;">
                                <?php echo ucfirst($etype); ?>
                            </span>
                            <span style="font-size:12px;font-weight:700;color:<?php echo $colors['color']; ?>;">
                                <?php echo $count; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if ($totalSpent > 0): ?>
                <div style="margin-top:12px;padding-top:12px;border-top:1px solid var(--color-border);text-align:center;">
                    <div style="font-size:11px;color:var(--color-text-secondary);text-transform:uppercase;font-weight:600;">Total Amount Spent</div>
                    <div style="font-size:20px;font-weight:700;color:var(--color-primary);">₱<?php echo number_format($totalSpent, 2); ?></div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Quick Actions -->
            <div class="card">
                <div style="font-size:12px;font-weight:700;color:var(--color-primary);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:10px;">
                    Quick Actions
                </div>
                <div style="display:flex;flex-direction:column;gap:6px;">
                    <a href="/event-ticketing-v2/modules/tickets/generate.php" class="btn btn-sm btn-primary" style="width:100%;">
                        🎫 Generate New Ticket
                    </a>
                    <a href="/event-ticketing-v2/modules/tickets/validate.php" class="btn btn-sm" style="width:100%;">
                        ✓ Validate Ticket Entry
                    </a>
                    <a href="/event-ticketing-v2/modules/attendees/edit.php?id=<?php echo $attendee_id; ?>" class="btn btn-sm" style="width:100%;">
                        ✏️ Edit Attendee Info
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Profile Field Styles */
.profile-field {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 6px 0;
    border-bottom: 1px solid var(--color-border-light);
    gap: 12px;
}
.profile-field:last-child {
    border-bottom: none;
}
.profile-label {
    font-size: 11px;
    color: var(--color-text-secondary);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    flex-shrink: 0;
    min-width: 80px;
}
.profile-value {
    font-size: 13px;
    color: var(--color-text-primary);
    font-weight: 500;
    text-align: right;
    word-break: break-word;
}
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>