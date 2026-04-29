<?php
require_once __DIR__ . '/../../includes/header.php';

$venue_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$venue_id) {
    redirect('/event-ticketing-v2/modules/venues/');
}

$result = mysqli_query($conn, "SELECT * FROM Venue WHERE venue_id = $venue_id");
$venue = mysqli_fetch_assoc($result);

if (!$venue) {
    $_SESSION['error'] = "Venue not found.";
    redirect('/event-ticketing-v2/modules/venues/');
}

// Get proper back URL
$backUrl = getBackUrl('/event-ticketing-v2/modules/venues/');

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
        $building_sql = !empty($building) ? "'" . mysqli_real_escape_string($conn, $building) . "'" : "NULL";
        $floor_level_sql = !empty($floor_level) ? "'" . mysqli_real_escape_string($conn, $floor_level) . "'" : "NULL";
        $capacity = intval($capacity);
        
        $sql = "UPDATE Venue SET 
                venue_name = '$venue_name',
                venue_type = '$venue_type',
                building = $building_sql,
                floor_level = $floor_level_sql,
                capacity = $capacity,
                has_av_system = $has_av_system
                WHERE venue_id = $venue_id";
        
        if (mysqli_query($conn, $sql)) {
            $_SESSION['success'] = "Venue updated successfully.";
            redirect('/event-ticketing-v2/modules/venues/');
        } else {
            $errors[] = "Error updating venue: " . mysqli_error($conn);
        }
    }
}

$form_values = !empty($_POST) ? $_POST : $venue;
?>

<div class="page active">
    <div class="page-header">
        <div>
            <div class="page-title">Edit Venue</div>
            <div class="page-sub">Update facility information</div>
        </div>
        <a href="<?php echo $backUrl; ?>" class="btn">← Back</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
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
                    <?php
                    $types = ['gymnasium', 'auditorium', 'classroom', 'field', 'courtyard', 'amphitheater', 'other'];
                    foreach ($types as $type):
                        $selected = ($form_values['venue_type'] ?? '') == $type ? 'selected' : '';
                        echo "<option value=\"$type\" $selected>" . ucfirst($type) . "</option>";
                    endforeach;
                    ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Capacity *</label>
                <input type="number" name="capacity" value="<?php echo h($form_values['capacity'] ?? ''); ?>" min="1" required>
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
                    <option value="1" <?php echo ($form_values['has_av_system'] ?? '') == '1' ? 'selected' : ''; ?>>Yes</option>
                    <option value="0" <?php echo ($form_values['has_av_system'] ?? '') === '0' ? 'selected' : ''; ?>>No</option>
                </select>
            </div>
            <div style="display:flex;gap:8px;margin-top:16px">
                <button type="submit" class="btn btn-primary">Update Venue</button>
                <a href="<?php echo $backUrl; ?>" class="btn">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>