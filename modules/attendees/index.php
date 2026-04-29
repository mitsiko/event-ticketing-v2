<?php
require_once __DIR__ . '/../../includes/header.php';

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $attendee_id = (int)$_GET['delete'];
    
    $check = mysqli_query($conn, "SELECT COUNT(*) as count FROM Ticket WHERE attendee_id = $attendee_id");
    if ($check) {
        $count = mysqli_fetch_assoc($check)['count'];
        
        if ($count > 0) {
            $_SESSION['error'] = "Cannot delete attendee: They have associated tickets.";
        } else {
            $sql = "DELETE FROM Attendee WHERE attendee_id = $attendee_id";
            if (mysqli_query($conn, $sql)) {
                $_SESSION['success'] = "Attendee deleted successfully.";
            } else {
                $_SESSION['error'] = "Error deleting attendee: " . mysqli_error($conn);
            }
        }
    }
    redirect('/event-ticketing-v2/modules/attendees/');
}

// Filter parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';

$query = "SELECT * FROM Attendee WHERE 1=1";

if (!empty($search)) {
    $search_escaped = mysqli_real_escape_string($conn, $search);
    $query .= " AND (first_name LIKE '%$search_escaped%' OR last_name LIKE '%$search_escaped%' OR email LIKE '%$search_escaped%')";
}

if (!empty($type_filter)) {
    $type_escaped = mysqli_real_escape_string($conn, $type_filter);
    $query .= " AND attendee_type = '$type_escaped'";
}

$query .= " ORDER BY registered_at DESC";
$attendees = mysqli_query($conn, $query);

if (!$attendees) {
    $_SESSION['error'] = "Database error: " . mysqli_error($conn);
    $attendees = false;
}
?>

<div class="page active">
    <div class="page-header">
        <div>
            <div class="page-title">Attendees</div>
            <div class="page-sub">Register and manage event attendees</div>
        </div>
        <a href="/event-ticketing-v2/modules/attendees/create.php" class="btn btn-primary">+ Register Attendee</a>
    </div>

    <?php echo displayMessage(); ?>

    <div class="search-row">
        <form method="GET" id="filter-form" style="display:flex;gap:8px;width:100%">
            <input type="text" name="search" placeholder="Search name or email..." value="<?php echo htmlspecialchars($search); ?>" style="flex:1;max-width:280px">
            <select name="type" id="type-select" style="width:140px">
                <option value="">All Types</option>
                <option value="student" <?php echo $type_filter == 'student' ? 'selected' : ''; ?>>Student</option>
                <option value="employee" <?php echo $type_filter == 'employee' ? 'selected' : ''; ?>>Employee</option>
                <option value="alumni" <?php echo $type_filter == 'alumni' ? 'selected' : ''; ?>>Alumni</option>
                <option value="guest" <?php echo $type_filter == 'guest' ? 'selected' : ''; ?>>Guest</option>
            </select>
            <button type="submit" class="btn">Filter</button>
            <a href="/event-ticketing-v2/modules/attendees/" class="btn">Reset</a>
        </form>
    </div>

    <div class="card-flush">
        <div class="tbl-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Email</th>
                        <th>ID / Details</th>
                        <th>Gender</th>
                        <th>Registered</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($attendees && mysqli_num_rows($attendees) > 0): ?>
                        <?php while ($att = mysqli_fetch_assoc($attendees)): 
                            $detail = '-';
                            if ($att['attendee_type'] == 'student') {
                                $detail = $att['student_id'] ? $att['student_id'] . ' · ' . $att['program'] : '-';
                            } elseif ($att['attendee_type'] == 'employee') {
                                $detail = $att['employee_id'] ? $att['employee_id'] . ' · ' . $att['job_title'] : '-';
                            } elseif ($att['attendee_type'] == 'alumni') {
                                $detail = $att['alumni_id'] ? $att['alumni_id'] . ' · ' . $att['graduation_year'] : '-';
                            } elseif ($att['attendee_type'] == 'guest') {
                                $detail = $att['guest_id'] ?? '-';
                            }
                        ?>
                            <tr>
                                <td style="font-weight:500"><?php echo htmlspecialchars($att['first_name'] . ' ' . $att['last_name']); ?></td>
                                <td><span class="badge <?php echo getAttendeeTypeBadge($att['attendee_type']); ?>"><?php echo htmlspecialchars($att['attendee_type']); ?></span></td>
                                <td style="font-size:12px"><?php echo htmlspecialchars($att['email']); ?></td>
                                <td style="font-size:11px;color:var(--color-text-secondary)"><?php echo htmlspecialchars($detail); ?></td>
                                <td style="font-size:12px"><?php echo str_replace('_', ' ', htmlspecialchars($att['gender'])); ?></td>
                                <td style="font-size:12px"><?php echo formatDate($att['registered_at']); ?></td>
                                <td>
                                    <div class="actions" style="display:flex;gap:4px;flex-wrap:wrap;">
                                        <a href="/event-ticketing-v2/modules/attendees/view.php?id=<?php echo $att['attendee_id']; ?>" class="btn btn-sm">View</a>
                                        <a href="/event-ticketing-v2/modules/attendees/edit.php?id=<?php echo $att['attendee_id']; ?>" class="btn btn-sm">Edit</a>
                                        <a href="/event-ticketing-v2/modules/attendees/?delete=<?php echo $att['attendee_id']; ?>" 
                                           class="btn btn-sm btn-danger" 
                                           onclick="return confirm('Are you sure you want to delete this attendee?')">Del</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" style="text-align:center;padding:2rem;color:var(--color-text-secondary)">No attendees found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>