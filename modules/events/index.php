<?php
require_once __DIR__ . '/../../includes/header.php';

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $event_id = (int)$_GET['delete'];
    
    $check = mysqli_query($conn, "SELECT COUNT(*) as count FROM Ticket_Category WHERE event_id = $event_id");
    if ($check) {
        $count = mysqli_fetch_assoc($check)['count'];
        
        if ($count > 0) {
            $_SESSION['error'] = "Cannot delete event: It has associated ticket categories.";
        } else {
            $sql = "DELETE FROM Event WHERE event_id = $event_id";
            if (mysqli_query($conn, $sql)) {
                $_SESSION['success'] = "Event deleted successfully.";
            } else {
                $_SESSION['error'] = "Error deleting event: " . mysqli_error($conn);
            }
        }
    }
    redirect('/event-ticketing-v2/modules/events/');
}

// Filter parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$query = "SELECT e.*, v.venue_name, o.org_name 
          FROM Event e
          JOIN Venue v ON e.venue_id = v.venue_id
          JOIN Organization o ON e.org_id = o.org_id
          WHERE 1=1";

if (!empty($search)) {
    $search_escaped = mysqli_real_escape_string($conn, $search);
    $query .= " AND (e.event_name LIKE '%$search_escaped%' OR v.venue_name LIKE '%$search_escaped%' OR o.org_name LIKE '%$search_escaped%')";
}

if (!empty($status_filter)) {
    $status_escaped = mysqli_real_escape_string($conn, $status_filter);
    $query .= " AND e.status = '$status_escaped'";
}

$query .= " ORDER BY e.event_date DESC";

$events = mysqli_query($conn, $query);

if (!$events) {
    $_SESSION['error'] = "Database error: " . mysqli_error($conn);
    $events = false;
}
?>

<div class="page active">
    <div class="page-header">
        <div>
            <div class="page-title">Manage Events</div>
            <div class="page-sub">Create, edit and manage university events</div>
        </div>
        <a href="/event-ticketing-v2/modules/events/create.php" class="btn btn-primary">+ New Event</a>
    </div>

    <?php echo displayMessage(); ?>

    <div class="search-row">
        <form method="GET" action="" style="display:flex;gap:8px;width:100%">
            <input type="text" name="search" placeholder="Search events..." value="<?php echo htmlspecialchars($search); ?>" style="flex:1;max-width:280px">
            <select name="status" style="width:140px">
                <option value="">All Status</option>
                <option value="upcoming" <?php echo $status_filter == 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                <option value="ongoing" <?php echo $status_filter == 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
            </select>
            <button type="submit" class="btn">Filter</button>
            <a href="/event-ticketing-v2/modules/events/" class="btn">Reset</a>
        </form>
    </div>

    <div class="card-flush">
        <div class="tbl-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Type</th>
                        <th>Date</th>
                        <th>Venue</th>
                        <th>Audience</th>
                        <th>Ticket</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($events && mysqli_num_rows($events) > 0): ?>
                        <?php while ($event = mysqli_fetch_assoc($events)): ?>
                            <tr>
                                <td>
                                    <div style="font-weight:500"><?php echo htmlspecialchars($event['event_name']); ?></div>
                                    <div style="font-size:11px;color:var(--color-text-secondary)"><?php echo htmlspecialchars($event['org_name']); ?></div>
                                </td>
                                <td><span class="badge <?php echo getEventTypeBadge($event['event_type']); ?>"><?php echo htmlspecialchars(ucfirst($event['event_type'])); ?></span></td>
                                <td><?php echo formatDate($event['event_date']); ?></td>
                                <td><?php echo htmlspecialchars($event['venue_name']); ?></td>
                                <td><span class="badge <?php echo getAudienceBadge($event['audience_type']); ?>"><?php echo str_replace('_', ' ', htmlspecialchars($event['audience_type'])); ?></span></td>
                                <td>
                                    <span class="badge <?php echo $event['requires_ticket'] ? 'b-blue' : 'b-gray'; ?>">
                                        <?php echo $event['requires_ticket'] ? 'Ticketed' : 'Free entry'; ?>
                                    </span>
                                </td>
                                <td><span class="badge <?php echo getStatusBadge($event['status']); ?>"><?php echo ucfirst(htmlspecialchars($event['status'])); ?></span></td>
                                <td>
                                    <div class="actions" style="display:flex;gap:4px;flex-wrap:wrap;">
                                        <a href="/event-ticketing-v2/modules/events/view.php?id=<?php echo $event['event_id']; ?>" class="btn btn-sm">View</a>
                                        <a href="/event-ticketing-v2/modules/events/edit.php?id=<?php echo $event['event_id']; ?>" class="btn btn-sm">Edit</a>
                                        <a href="/event-ticketing-v2/modules/events/?delete=<?php echo $event['event_id']; ?>" 
                                           class="btn btn-sm btn-danger" 
                                           onclick="return confirm('Are you sure you want to delete this event?')">Del</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="8" style="text-align:center;padding:2rem;color:var(--color-text-secondary)">No events found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>