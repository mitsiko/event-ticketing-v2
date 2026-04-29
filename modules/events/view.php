<?php
/**
 * View Event Details
 * Shows comprehensive event information with ticket sales and attendee list
 */
require_once __DIR__ . '/../../includes/header.php';

$event_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$event_id) {
    $_SESSION['error'] = "No event specified.";
    redirect('/event-ticketing-v2/modules/events/');
}

// Fetch event with venue and organizer details
$eventQuery = "
    SELECT e.*, 
           v.venue_name, v.venue_type, v.building, v.floor_level, v.capacity as venue_capacity, v.has_av_system,
           o.org_name, o.org_type, o.contact_email, o.contact_phone, o.is_accredited
    FROM Event e
    JOIN Venue v ON e.venue_id = v.venue_id
    JOIN Organization o ON e.org_id = o.org_id
    WHERE e.event_id = $event_id
";
$eventResult = mysqli_query($conn, $eventQuery);

if (!$eventResult || mysqli_num_rows($eventResult) === 0) {
    $_SESSION['error'] = "Event not found.";
    redirect('/event-ticketing-v2/modules/events/');
}

$event = mysqli_fetch_assoc($eventResult);

// Fetch ticket categories with sales data
$categoriesQuery = "
    SELECT tc.*,
           COUNT(t.ticket_id) as tickets_sold,
           SUM(CASE WHEN t.is_validated = 1 THEN 1 ELSE 0 END) as tickets_validated,
           SUM(CASE WHEN t.payment_status = 'paid' THEN tc.price ELSE 0 END) as revenue
    FROM Ticket_Category tc
    LEFT JOIN Ticket t ON tc.category_id = t.category_id
    WHERE tc.event_id = $event_id
    GROUP BY tc.category_id
    ORDER BY tc.price ASC
";
$categories = mysqli_query($conn, $categoriesQuery);

// Calculate totals
$totalSlots = 0;
$totalSold = 0;
$totalRemaining = 0;
$totalRevenue = 0;
$totalValidated = 0;
$categoriesData = [];

if ($categories && mysqli_num_rows($categories) > 0) {
    mysqli_data_seek($categories, 0);
    while ($cat = mysqli_fetch_assoc($categories)) {
        $totalSlots += $cat['total_slots'];
        $totalSold += $cat['tickets_sold'];
        $totalRemaining += $cat['slots_remaining'];
        $totalRevenue += $cat['revenue'];
        $totalValidated += $cat['tickets_validated'];
        $categoriesData[] = $cat;
    }
}

// Fetch recent tickets for this event
$ticketsQuery = "
    SELECT t.*, 
           a.first_name, a.last_name, a.email, a.attendee_type,
           tc.category_name, tc.price
    FROM Ticket t
    JOIN Attendee a ON t.attendee_id = a.attendee_id
    JOIN Ticket_Category tc ON t.category_id = tc.category_id
    WHERE tc.event_id = $event_id
    ORDER BY t.purchase_date DESC
    LIMIT 10
";
$recentTickets = mysqli_query($conn, $ticketsQuery);
$totalTicketsCount = mysqli_num_rows(mysqli_query($conn, "
    SELECT t.ticket_id FROM Ticket t 
    JOIN Ticket_Category tc ON t.category_id = tc.category_id 
    WHERE tc.event_id = $event_id
"));

// Fetch attendee type distribution
$attendeeDistributionQuery = "
    SELECT a.attendee_type, COUNT(*) as count
    FROM Ticket t
    JOIN Attendee a ON t.attendee_id = a.attendee_id
    JOIN Ticket_Category tc ON t.category_id = tc.category_id
    WHERE tc.event_id = $event_id
    GROUP BY a.attendee_type
    ORDER BY count DESC
";
$attendeeDistribution = mysqli_query($conn, $attendeeDistributionQuery);

$fillPercentage = $totalSlots > 0 ? round(($totalSold / $totalSlots) * 100) : 0;
$validationPercentage = $totalSold > 0 ? round(($totalValidated / $totalSold) * 100) : 0;
?>

<div class="page active">
    <div class="page-header">
        <div>
            <div class="page-title">Event Details</div>
            <div class="page-sub">View event information, ticket sales, and attendees</div>
        </div>
        <div style="display:flex;gap:8px;">
            <a href="/event-ticketing-v2/modules/events/edit.php?id=<?php echo $event_id; ?>" class="btn">Edit Event</a>
            <a href="/event-ticketing-v2/modules/events/" class="btn">← Back to Events</a>
        </div>
    </div>

    <?php echo displayMessage(); ?>

    <!-- Stats Row -->
    <div class="metrics" style="grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));">
        <div class="metric">
            <div class="metric-val"><?php echo $totalSlots; ?></div>
            <div class="metric-label">Total Slots</div>
        </div>
        <div class="metric">
            <div class="metric-val"><?php echo $totalSold; ?></div>
            <div class="metric-label">Tickets Sold</div>
        </div>
        <div class="metric">
            <div class="metric-val"><?php echo $totalRemaining; ?></div>
            <div class="metric-label">Remaining</div>
        </div>
        <div class="metric">
            <div class="metric-val"><?php echo $fillPercentage; ?>%</div>
            <div class="metric-label">Fill Rate</div>
        </div>
        <div class="metric">
            <div class="metric-val">₱<?php echo number_format($totalRevenue, 2); ?></div>
            <div class="metric-label">Revenue</div>
        </div>
        <div class="metric">
            <div class="metric-val"><?php echo $totalValidated; ?>/<?php echo $totalSold; ?></div>
            <div class="metric-label">Validated</div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 380px;gap:20px;align-items:start;">

        <!-- Left Column: Main Content -->
        <div>
            <!-- Event Information Card -->
            <div class="card">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
                    <div>
                        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:8px;">
                            <span class="badge <?php echo getEventTypeBadge($event['event_type']); ?>">
                                <?php echo ucfirst($event['event_type']); ?>
                            </span>
                            <span class="badge <?php echo getAudienceBadge($event['audience_type']); ?>">
                                <?php echo str_replace('_', ' ', ucfirst($event['audience_type'])); ?>
                            </span>
                            <span class="badge <?php echo getStatusBadge($event['status']); ?>">
                                <?php echo ucfirst($event['status']); ?>
                            </span>
                            <span class="badge <?php echo $event['requires_ticket'] ? 'b-blue' : 'b-gray'; ?>">
                                <?php echo $event['requires_ticket'] ? 'Ticketed' : 'Free Entry'; ?>
                            </span>
                        </div>
                        <h2 style="font-family:'Playfair Display',serif;font-size:22px;color:var(--color-primary);margin:0;">
                            <?php echo h($event['event_name']); ?>
                        </h2>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
                    <div class="info-row">
                        <span class="info-label">Date</span>
                        <span class="info-value"><?php echo formatDate($event['event_date']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Time</span>
                        <span class="info-value"><?php echo formatTime($event['start_time']); ?> - <?php echo formatTime($event['end_time']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Venue</span>
                        <span class="info-value"><?php echo h($event['venue_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Venue Capacity</span>
                        <span class="info-value"><?php echo number_format($event['venue_capacity']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Organizer</span>
                        <span class="info-value"><?php echo h($event['org_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Organizer Type</span>
                        <span class="info-value"><?php echo str_replace('_', ' ', ucfirst($event['org_type'])); ?></span>
                    </div>
                </div>

                <?php if (!empty($event['description'])): ?>
                    <div style="padding-top:16px;border-top:1px solid var(--color-border);">
                        <div style="font-size:12px;font-weight:700;color:var(--color-primary);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px;">
                            Description
                        </div>
                        <p style="font-size:13px;color:var(--color-text-secondary);line-height:1.6;margin:0;">
                            <?php echo nl2br(h($event['description'])); ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Ticket Categories Card -->
            <div class="card">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                    <div style="font-size:14px;font-weight:700;color:var(--color-primary);text-transform:uppercase;letter-spacing:0.5px;">
                        Ticket Categories
                    </div>
                    <a href="/event-ticketing-v2/modules/categories/create.php" class="btn btn-sm btn-primary">+ Add Category</a>
                </div>

                <?php if (!empty($categoriesData)): ?>
                    <div style="display:flex;flex-direction:column;gap:12px;">
                        <?php foreach ($categoriesData as $cat): 
                            $catFillPct = $cat['total_slots'] > 0 ? round(($cat['tickets_sold'] / $cat['total_slots']) * 100) : 0;
                            $catValidationPct = $cat['tickets_sold'] > 0 ? round(($cat['tickets_validated'] / $cat['tickets_sold']) * 100) : 0;
                        ?>
                            <div style="border:1px solid var(--color-border);border-radius:var(--radius-sm);padding:14px;transition:all 0.15s ease;">
                                <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px;flex-wrap:wrap;gap:8px;">
                                    <div>
                                        <div style="font-weight:600;font-size:14px;color:var(--color-text-primary);">
                                            <?php echo h($cat['category_name']); ?>
                                        </div>
                                        <div style="font-size:11px;color:var(--color-text-secondary);margin-top:2px;">
                                            Eligible: 
                                            <span class="badge <?php echo getAttendeeTypeBadge($cat['eligible_type']); ?>" style="font-size:10px;">
                                                <?php echo $cat['eligible_type'] === 'all' ? 'Everyone' : ucfirst($cat['eligible_type']) . 's'; ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div style="text-align:right;">
                                        <div style="font-weight:700;font-size:16px;color:var(--color-primary);">
                                            <?php echo $cat['price'] > 0 ? '₱' . number_format($cat['price'], 2) : 'FREE'; ?>
                                        </div>
                                    </div>
                                </div>

                                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:10px;">
                                    <div style="text-align:center;padding:6px;background:var(--color-bg-tertiary);border-radius:var(--radius-xs);">
                                        <div style="font-size:18px;font-weight:700;color:var(--color-text-primary);"><?php echo $cat['tickets_sold']; ?></div>
                                        <div style="font-size:10px;color:var(--color-text-secondary);font-weight:600;">Sold</div>
                                    </div>
                                    <div style="text-align:center;padding:6px;background:var(--color-bg-tertiary);border-radius:var(--radius-xs);">
                                        <div style="font-size:18px;font-weight:700;color:var(--color-text-primary);"><?php echo $cat['slots_remaining']; ?></div>
                                        <div style="font-size:10px;color:var(--color-text-secondary);font-weight:600;">Remaining</div>
                                    </div>
                                    <div style="text-align:center;padding:6px;background:var(--color-bg-tertiary);border-radius:var(--radius-xs);">
                                        <div style="font-size:18px;font-weight:700;color:var(--color-text-primary);">₱<?php echo number_format($cat['revenue'], 2); ?></div>
                                        <div style="font-size:10px;color:var(--color-text-secondary);font-weight:600;">Revenue</div>
                                    </div>
                                </div>

                                <div style="margin-bottom:6px;">
                                    <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--color-text-secondary);margin-bottom:4px;">
                                        <span>Sold: <?php echo $catFillPct; ?>%</span>
                                        <span>Validated: <?php echo $catValidationPct; ?>%</span>
                                    </div>
                                    <div style="height:6px;background:var(--color-bg-tertiary);border-radius:3px;overflow:hidden;display:flex;">
                                        <div style="height:100%;background:<?php echo $catFillPct >= 80 ? 'var(--color-warning)' : 'var(--color-success)'; ?>;width:<?php echo $catFillPct; ?>%;border-radius:3px;transition:width 0.5s ease;"></div>
                                    </div>
                                </div>

                                <div style="display:flex;gap:6px;">
                                    <a href="/event-ticketing-v2/modules/categories/edit.php?id=<?php echo $cat['category_id']; ?>" 
                                       class="btn btn-sm" style="flex:1;">Edit Category</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div style="text-align:center;padding:2rem;color:var(--color-text-secondary);">
                        <p style="margin:0;">No ticket categories created yet.</p>
                        <a href="/event-ticketing-v2/modules/categories/create.php" class="btn btn-primary" style="margin-top:10px;">
                            Add First Category
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Recent Tickets Card -->
            <div class="card-flush">
                <div style="padding:0.75rem 1rem;font-weight:600;font-size:13px;color:var(--color-primary);border-bottom:1px solid var(--color-border);display:flex;justify-content:space-between;align-items:center;">
                    <span>Recent Tickets (<?php echo $totalTicketsCount; ?> total)</span>
                    <a href="/event-ticketing-v2/modules/tickets/" class="btn btn-sm">View All</a>
                </div>
                <div class="tbl-wrap">
                    <?php if ($recentTickets && mysqli_num_rows($recentTickets) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Attendee</th>
                                    <th>Type</th>
                                    <th>Category</th>
                                    <th>Payment</th>
                                    <th>Validated</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($ticket = mysqli_fetch_assoc($recentTickets)): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight:500;font-size:13px;"><?php echo h($ticket['first_name'] . ' ' . $ticket['last_name']); ?></div>
                                            <div style="font-size:11px;color:var(--color-text-secondary);"><?php echo h($ticket['email']); ?></div>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo getAttendeeTypeBadge($ticket['attendee_type']); ?>" style="font-size:10px;">
                                                <?php echo h($ticket['attendee_type']); ?>
                                            </span>
                                        </td>
                                        <td style="font-size:12px;">
                                            <?php echo h($ticket['category_name']); ?>
                                            <div style="font-size:11px;color:var(--color-text-secondary);">
                                                <?php echo $ticket['price'] > 0 ? '₱' . number_format($ticket['price'], 2) : 'Free'; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo getPaymentBadge($ticket['payment_status']); ?>" style="font-size:10px;">
                                                <?php echo ucfirst($ticket['payment_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($ticket['is_validated']): ?>
                                                <span class="badge b-green" style="font-size:10px;">✓ Yes</span>
                                            <?php else: ?>
                                                <span class="badge b-gray" style="font-size:10px;">No</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="actions" style="display:flex;gap:4px;">
                                                <a href="/event-ticketing-v2/modules/tickets/view.php?id=<?php echo $ticket['ticket_id']; ?>" class="btn btn-sm">View</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div style="text-align:center;padding:2rem;color:var(--color-text-secondary);">
                            <p style="margin:0;">No tickets sold yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Column: Sidebar -->
        <div>
            <!-- Venue Details Card -->
            <div class="card">
                <div style="font-size:12px;font-weight:700;color:var(--color-primary);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:12px;">
                    Venue Details
                </div>
                <div style="display:flex;flex-direction:column;gap:8px;">
                    <div class="sidebar-row">
                        <span class="sidebar-label">Name</span>
                        <span class="sidebar-value"><?php echo h($event['venue_name']); ?></span>
                    </div>
                    <div class="sidebar-row">
                        <span class="sidebar-label">Type</span>
                        <span class="sidebar-value"><?php echo ucfirst($event['venue_type']); ?></span>
                    </div>
                    <div class="sidebar-row">
                        <span class="sidebar-label">Building</span>
                        <span class="sidebar-value"><?php echo h($event['building'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="sidebar-row">
                        <span class="sidebar-label">Floor</span>
                        <span class="sidebar-value"><?php echo h($event['floor_level'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="sidebar-row">
                        <span class="sidebar-label">Capacity</span>
                        <span class="sidebar-value"><?php echo number_format($event['venue_capacity']); ?></span>
                    </div>
                    <div class="sidebar-row">
                        <span class="sidebar-label">AV System</span>
                        <span class="sidebar-value">
                            <span class="badge <?php echo $event['has_av_system'] ? 'b-green' : 'b-gray'; ?>" style="font-size:10px;">
                                <?php echo $event['has_av_system'] ? 'Yes' : 'No'; ?>
                            </span>
                        </span>
                    </div>
                </div>
                <a href="/event-ticketing-v2/modules/venues/edit.php?id=<?php echo $event['venue_id']; ?>" 
                   class="btn btn-sm" style="width:100%;margin-top:12px;">Edit Venue</a>
            </div>

            <!-- Organizer Details Card -->
            <div class="card">
                <div style="font-size:12px;font-weight:700;color:var(--color-primary);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:12px;">
                    Organizer Details
                </div>
                <div style="display:flex;flex-direction:column;gap:8px;">
                    <div class="sidebar-row">
                        <span class="sidebar-label">Name</span>
                        <span class="sidebar-value"><?php echo h($event['org_name']); ?></span>
                    </div>
                    <div class="sidebar-row">
                        <span class="sidebar-label">Type</span>
                        <span class="sidebar-value"><?php echo str_replace('_', ' ', ucfirst($event['org_type'])); ?></span>
                    </div>
                    <div class="sidebar-row">
                        <span class="sidebar-label">Email</span>
                        <span class="sidebar-value" style="font-size:12px;"><?php echo h($event['contact_email']); ?></span>
                    </div>
                    <div class="sidebar-row">
                        <span class="sidebar-label">Phone</span>
                        <span class="sidebar-value"><?php echo h($event['contact_phone'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="sidebar-row">
                        <span class="sidebar-label">Accredited</span>
                        <span class="sidebar-value">
                            <span class="badge <?php echo $event['is_accredited'] ? 'b-green' : 'b-red'; ?>" style="font-size:10px;">
                                <?php echo $event['is_accredited'] ? 'Yes' : 'No'; ?>
                            </span>
                        </span>
                    </div>
                </div>
                <a href="/event-ticketing-v2/modules/organizations/edit.php?id=<?php echo $event['org_id']; ?>" 
                   class="btn btn-sm" style="width:100%;margin-top:12px;">Edit Organization</a>
            </div>

            <!-- Sales Analytics Card -->
            <?php if ($totalSold > 0): ?>
            <div class="card">
                <div style="font-size:12px;font-weight:700;color:var(--color-primary);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:12px;">
                    Sales Analytics
                </div>

                <!-- Fill Rate Bar -->
                <div style="margin-bottom:12px;">
                    <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--color-text-secondary);margin-bottom:4px;">
                        <span>Fill Rate</span>
                        <span><?php echo $fillPercentage; ?>% (<?php echo $totalSold; ?>/<?php echo $totalSlots; ?>)</span>
                    </div>
                    <div style="height:8px;background:var(--color-bg-tertiary);border-radius:4px;overflow:hidden;">
                        <div style="height:100%;background:<?php echo $fillPercentage >= 80 ? '#d97706' : ($fillPercentage >= 50 ? '#16a34a' : '#2563eb'); ?>;width:<?php echo $fillPercentage; ?>%;border-radius:4px;transition:width 0.5s ease;"></div>
                    </div>
                </div>

                <!-- Validation Rate Bar -->
                <div style="margin-bottom:12px;">
                    <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--color-text-secondary);margin-bottom:4px;">
                        <span>Validation Rate</span>
                        <span><?php echo $validationPercentage; ?>% (<?php echo $totalValidated; ?>/<?php echo $totalSold; ?>)</span>
                    </div>
                    <div style="height:8px;background:var(--color-bg-tertiary);border-radius:4px;overflow:hidden;">
                        <div style="height:100%;background:<?php echo $validationPercentage >= 80 ? '#16a34a' : ($validationPercentage >= 50 ? '#d97706' : '#dc2626'); ?>;width:<?php echo $validationPercentage; ?>%;border-radius:4px;transition:width 0.5s ease;"></div>
                    </div>
                </div>

                <!-- Revenue -->
                <div style="padding:10px;background:var(--color-primary-bg);border-radius:var(--radius-sm);text-align:center;margin-bottom:12px;">
                    <div style="font-size:10px;color:var(--color-text-secondary);text-transform:uppercase;font-weight:600;">Total Revenue</div>
                    <div style="font-size:22px;font-weight:700;color:var(--color-primary);">₱<?php echo number_format($totalRevenue, 2); ?></div>
                </div>

                <!-- Attendee Distribution -->
                <?php if ($attendeeDistribution && mysqli_num_rows($attendeeDistribution) > 0): ?>
                <div>
                    <div style="font-size:11px;font-weight:600;color:var(--color-text-secondary);margin-bottom:6px;">Attendee Distribution</div>
                    <?php while ($dist = mysqli_fetch_assoc($attendeeDistribution)): 
                        $distPct = $totalSold > 0 ? round(($dist['count'] / $totalSold) * 100) : 0;
                    ?>
                        <div style="margin-bottom:6px;">
                            <div style="display:flex;justify-content:space-between;font-size:11px;margin-bottom:2px;">
                                <span style="color:var(--color-text-secondary);"><?php echo ucfirst($dist['attendee_type']); ?></span>
                                <span style="font-weight:600;"><?php echo $dist['count']; ?> (<?php echo $distPct; ?>%)</span>
                            </div>
                            <div style="height:5px;background:var(--color-bg-tertiary);border-radius:3px;overflow:hidden;">
                                <div style="height:100%;background:var(--color-primary-light);width:<?php echo $distPct; ?>%;border-radius:3px;"></div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Quick Actions Card -->
            <div class="card">
                <div style="font-size:12px;font-weight:700;color:var(--color-primary);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:10px;">
                    Quick Actions
                </div>
                <div style="display:flex;flex-direction:column;gap:6px;">
                    <a href="/event-ticketing-v2/modules/events/edit.php?id=<?php echo $event_id; ?>" class="btn btn-sm" style="width:100%;">
                        ✏️ Edit Event
                    </a>
                    <a href="/event-ticketing-v2/modules/categories/create.php" class="btn btn-sm" style="width:100%;">
                        🎫 Add Ticket Category
                    </a>
                    <a href="/event-ticketing-v2/modules/tickets/generate.php" class="btn btn-sm btn-primary" style="width:100%;">
                        🎟️ Generate Ticket
                    </a>
                    <?php if ($event['requires_ticket']): ?>
                    <a href="<?php echo dirname($_SERVER['SCRIPT_NAME']); ?>/online-register.php?event_id=<?php echo $event_id; ?>" 
                       class="btn btn-sm" style="width:100%;" target="_blank">
                        👥 View Registration Page
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Info Row Styles */
.info-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 6px 0;
    border-bottom: 1px solid var(--color-border-light);
    gap: 12px;
}
.info-label {
    font-size: 11px;
    color: var(--color-text-secondary);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    flex-shrink: 0;
    min-width: 100px;
}
.info-value {
    font-size: 13px;
    color: var(--color-text-primary);
    font-weight: 500;
    text-align: right;
}

/* Sidebar Row Styles */
.sidebar-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 5px 0;
    border-bottom: 1px solid var(--color-border-light);
    gap: 10px;
}
.sidebar-row:last-child {
    border-bottom: none;
}
.sidebar-label {
    font-size: 10px;
    color: var(--color-text-secondary);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    flex-shrink: 0;
}
.sidebar-value {
    font-size: 12px;
    color: var(--color-text-primary);
    font-weight: 500;
    text-align: right;
    word-break: break-word;
}

@media (max-width: 768px) {
    .info-label { min-width: 80px; }
}
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>