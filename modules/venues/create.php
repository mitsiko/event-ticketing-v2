<?php
require_once __DIR__ . '/../../includes/header.php';

if (isPost()) {
    $venue_name = trim($_POST['venue_name'] ?? '');
    $venue_type = trim($_POST['venue_type'] ?? '');
    $building = trim($_POST['building'] ?? '');
    $floor_level = trim($_POST['floor_level'] ?? '');
    $capacity = trim($_POST['capacity'] ?? '');
    $has_av_system = isset($_POST['has_av_system']) ? (int)$_POST['has_av_system'] : 1;
    
    $errors = [];
    
    if (empty($venue_name)) $errors[] = "Venue name is required.";
    if (empty($capacity) || !is_numeric($capacity) || $capacity <= 0) {
        $errors[] = "Capacity must be a positive number.";
    }
    
    if (empty($errors)) {
        $venue_name = mysqli_real_escape_string($conn, $venue_name);
        $venue_type = mysqli_real_escape_string($conn, $venue_type);
        $building = !empty($building) ? "'" . mysqli_real_escape_string($conn, $building) . "'" : "NULL";
        $floor_level = !empty($floor_level) ? "'" . mysqli_real_escape_string($conn, $floor_level) . "'" : "NULL";
        $capacity = intval($capacity);
        
        $sql = "INSERT INTO Venue (venue_name, venue_type, building, floor_level, capacity, has_av_system) 
                VALUES ('$venue_name', '$venue_type', $building, $floor_level, $capacity, $has_av_system)";
        
        if (mysqli_query($conn, $sql)) {
            $_SESSION['success'] = "Venue added successfully.";
            redirect('/event-ticketing-v2/modules/venues/');
        } else {
            $errors[] = "Error adding venue: " . mysqli_error($conn);
        }
    }
}

$form_values = $_POST;
?>

<div class="page active">
    <div class="page-header">
        <div>
            <div class="page-title">Add Venue</div>
            <div class="page-sub">Create a new university facility</div>
        </div>
        <a href="/event-ticketing-v2/modules/venues/" class="btn">← Back to Venues</a>
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

    <div class="card" style="max-width:500px">
        <form method="POST">
            <div class="form-group">
                <label class="form-label">Venue name *</label>
                <input type="text" name="venue_name" value="<?php echo h($form_values['venue_name'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">Type *</label>
                <select name="venue_type" required>
                    <option value="gymnasium" <?php echo ($form_values['venue_type'] ?? '') == 'gymnasium' ? 'selected' : ''; ?>>Gymnasium</option>
                    <option value="auditorium" <?php echo ($form_values['venue_type'] ?? '') == 'auditorium' ? 'selected' : ''; ?>>Auditorium</option>
                    <option value="classroom" <?php echo ($form_values['venue_type'] ?? '') == 'classroom' ? 'selected' : ''; ?>>Classroom</option>
                    <option value="field" <?php echo ($form_values['venue_type'] ?? '') == 'field' ? 'selected' : ''; ?>>Field</option>
                    <option value="courtyard" <?php echo ($form_values['venue_type'] ?? '') == 'courtyard' ? 'selected' : ''; ?>>Courtyard</option>
                    <option value="amphitheater" <?php echo ($form_values['venue_type'] ?? '') == 'amphitheater' ? 'selected' : ''; ?>>Amphitheater</option>
                    <option value="other" <?php echo ($form_values['venue_type'] ?? '') == 'other' ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Capacity *</label>
                <input type="number" name="capacity" value="<?php echo h($form_values['capacity'] ?? '500'); ?>" min="1" required>
            </div>
            <div class="form-group">
                <label class="form-label">Building</label>
                <input type="text" name="building" value="<?php echo h($form_values['building'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Floor level</label>
                <input type="text" name="floor_level" value="<?php echo h($form_values['floor_level'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label class="form-label">AV system</label>
                <select name="has_av_system">
                    <option value="1" <?php echo ($form_values['has_av_system'] ?? '1') == '1' ? 'selected' : ''; ?>>Yes</option>
                    <option value="0" <?php echo ($form_values['has_av_system'] ?? '') === '0' ? 'selected' : ''; ?>>No</option>
                </select>
            </div>
            <div style="display:flex;gap:8px;margin-top:16px">
                <button type="submit" class="btn btn-primary">Save Venue</button>
                <a href="<?php echo getBackUrl('/event-ticketing-v2/modules/venues/'); ?>" class="btn">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>