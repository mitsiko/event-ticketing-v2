<?php
require_once __DIR__ . '/../../includes/header.php';

$venues = mysqli_query($conn, "SELECT venue_id, venue_name FROM Venue ORDER BY venue_name");
$organizations = mysqli_query($conn, "SELECT org_id, org_name FROM Organization ORDER BY org_name");

if (isPost()) {
    $event_name = trim($_POST['event_name'] ?? '');
    $event_type = trim($_POST['event_type'] ?? '');
    $event_date = trim($_POST['event_date'] ?? '');
    $start_time = trim($_POST['start_time'] ?? '');
    $end_time = trim($_POST['end_time'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $audience_type = trim($_POST['audience_type'] ?? 'open_to_all');
    $requires_ticket = isset($_POST['requires_ticket']) ? (int)$_POST['requires_ticket'] : 1;
    $status = trim($_POST['status'] ?? 'upcoming');
    $venue_id = isset($_POST['venue_id']) ? (int)$_POST['venue_id'] : 0;
    $org_id = isset($_POST['org_id']) ? (int)$_POST['org_id'] : 0;
    
    $errors = [];
    
    if (empty($event_name)) $errors[] = "Event name is required.";
    if (empty($event_type)) $errors[] = "Event type is required.";
    if (empty($event_date)) $errors[] = "Event date is required.";
    if (empty($start_time)) $errors[] = "Start time is required.";
    if (empty($end_time)) $errors[] = "End time is required.";
    if ($start_time >= $end_time) $errors[] = "End time must be after start time.";
    if ($venue_id <= 0) $errors[] = "Venue is required.";
    if ($org_id <= 0) $errors[] = "Organization is required.";
    
    if (empty($errors)) {
        $event_name = mysqli_real_escape_string($conn, $event_name);
        $event_type = mysqli_real_escape_string($conn, $event_type);
        $event_date = mysqli_real_escape_string($conn, $event_date);
        $start_time = mysqli_real_escape_string($conn, $start_time);
        $end_time = mysqli_real_escape_string($conn, $end_time);
        $description = !empty($description) ? "'" . mysqli_real_escape_string($conn, $description) . "'" : "NULL";
        $audience_type = mysqli_real_escape_string($conn, $audience_type);
        $status = mysqli_real_escape_string($conn, $status);
        
        $sql = "INSERT INTO Event (event_name, event_type, event_date, start_time, end_time, description, 
                audience_type, requires_ticket, status, venue_id, org_id) 
                VALUES ('$event_name', '$event_type', '$event_date', '$start_time', '$end_time', $description,
                '$audience_type', $requires_ticket, '$status', $venue_id, $org_id)";
        
        if (mysqli_query($conn, $sql)) {
            $_SESSION['success'] = "Event added successfully.";
            redirect('/event-ticketing-v2/modules/events/');
        } else {
            $errors[] = "Error adding event: " . mysqli_error($conn);
        }
    }
}

$form_values = $_POST;
?>

<div class="page active">
    <div class="page-header">
        <div>
            <div class="page-title">New Event</div>
            <div class="page-sub">Create a new university event</div>
        </div>
        <a href="/event-ticketing-v2/modules/events/" class="btn">← Back to Events</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error" style="background:#FCEBEB;border:0.5px solid #E24B4A;color:#791F1F;padding:12px 16px;border-radius:6px;margin-bottom:16px">
            <ul style="margin:0;padding-left:20px">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo h($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card">
        <form method="POST">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                <div class="form-group" style="grid-column:1/-1">
                    <label class="form-label">Event name *</label>
                    <input type="text" name="event_name" value="<?php echo h($form_values['event_name'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Event type *</label>
                    <select name="event_type" required>
                        <option value="">Select...</option>
                        <option value="academic" <?php echo ($form_values['event_type'] ?? '') == 'academic' ? 'selected' : ''; ?>>Academic</option>
                        <option value="cultural" <?php echo ($form_values['event_type'] ?? '') == 'cultural' ? 'selected' : ''; ?>>Cultural</option>
                        <option value="sports" <?php echo ($form_values['event_type'] ?? '') == 'sports' ? 'selected' : ''; ?>>Sports</option>
                        <option value="concert" <?php echo ($form_values['event_type'] ?? '') == 'concert' ? 'selected' : ''; ?>>Concert</option>
                        <option value="seminar" <?php echo ($form_values['event_type'] ?? '') == 'seminar' ? 'selected' : ''; ?>>Seminar</option>
                        <option value="graduation" <?php echo ($form_values['event_type'] ?? '') == 'graduation' ? 'selected' : ''; ?>>Graduation</option>
                        <option value="orientation" <?php echo ($form_values['event_type'] ?? '') == 'orientation' ? 'selected' : ''; ?>>Orientation</option>
                        <option value="other" <?php echo ($form_values['event_type'] ?? '') == 'other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Date *</label>
                    <input type="date" name="event_date" value="<?php echo h($form_values['event_date'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Start time *</label>
                    <input type="time" name="start_time" value="<?php echo h($form_values['start_time'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">End time *</label>
                    <input type="time" name="end_time" value="<?php echo h($form_values['end_time'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Audience type</label>
                    <select name="audience_type">
                        <option value="open_to_all" <?php echo ($form_values['audience_type'] ?? 'open_to_all') == 'open_to_all' ? 'selected' : ''; ?>>Open to all</option>
                        <option value="student_only" <?php echo ($form_values['audience_type'] ?? '') == 'student_only' ? 'selected' : ''; ?>>Students only</option>
                        <option value="employee_only" <?php echo ($form_values['audience_type'] ?? '') == 'employee_only' ? 'selected' : ''; ?>>Employees only</option>
                        <option value="alumni_only" <?php echo ($form_values['audience_type'] ?? '') == 'alumni_only' ? 'selected' : ''; ?>>Alumni only</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Requires ticket</label>
                    <select name="requires_ticket">
                        <option value="1" <?php echo ($form_values['requires_ticket'] ?? '1') == '1' ? 'selected' : ''; ?>>Yes (ticketed)</option>
                        <option value="0" <?php echo ($form_values['requires_ticket'] ?? '') === '0' ? 'selected' : ''; ?>>No (free entry)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status">
                        <option value="upcoming" <?php echo ($form_values['status'] ?? 'upcoming') == 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                        <option value="ongoing" <?php echo ($form_values['status'] ?? '') == 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                        <option value="completed" <?php echo ($form_values['status'] ?? '') == 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo ($form_values['status'] ?? '') == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Venue *</label>
                    <select name="venue_id" required>
                        <option value="">Select venue</option>
                        <?php while ($venue = mysqli_fetch_assoc($venues)): ?>
                            <option value="<?php echo $venue['venue_id']; ?>" <?php echo ($form_values['venue_id'] ?? '') == $venue['venue_id'] ? 'selected' : ''; ?>>
                                <?php echo h($venue['venue_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Organization *</label>
                    <select name="org_id" required>
                        <option value="">Select organization</option>
                        <?php while ($org = mysqli_fetch_assoc($organizations)): ?>
                            <option value="<?php echo $org['org_id']; ?>" <?php echo ($form_values['org_id'] ?? '') == $org['org_id'] ? 'selected' : ''; ?>>
                                <?php echo h($org['org_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group" style="grid-column:1/-1">
                    <label class="form-label">Description</label>
                    <textarea name="description" rows="3"><?php echo h($form_values['description'] ?? ''); ?></textarea>
                </div>
            </div>
            <div style="display:flex;gap:8px;margin-top:16px;padding-top:16px;border-top:0.5px solid var(--color-border-tertiary)">
                <button type="submit" class="btn btn-primary">Save Event</button>
                <a href="<?php echo getBackUrl('/event-ticketing-v2/modules/events/'); ?>" class="btn">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>