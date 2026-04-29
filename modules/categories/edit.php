<?php
require_once __DIR__ . '/../../includes/header.php';

$category_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$category_id) {
    redirect('/event-ticketing-v2/modules/categories/');
}

$result = mysqli_query($conn, "SELECT * FROM Ticket_Category WHERE category_id = $category_id");
$category = mysqli_fetch_assoc($result);

if (!$category) {
    $_SESSION['error'] = "Category not found.";
    redirect('/event-ticketing-v2/modules/categories/');
}

$events = mysqli_query($conn, "SELECT event_id, event_name FROM Event WHERE requires_ticket = 1 ORDER BY event_date DESC");

// Get the proper back URL
$backUrl = getBackUrl('/event-ticketing-v2/modules/categories/');
$eventFilter = get('event_id');

if (isPost()) {
    $event_id = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;
    $category_name = trim($_POST['category_name'] ?? '');
    $eligible_type = trim($_POST['eligible_type'] ?? 'all');
    $price = trim($_POST['price'] ?? '0');
    $total_slots = trim($_POST['total_slots'] ?? '');
    
    $errors = [];
    
    if ($event_id <= 0) $errors[] = "Event is required.";
    if (empty($category_name)) $errors[] = "Category name is required.";
    if (!is_numeric($price) || $price < 0) $errors[] = "Price must be a non-negative number.";
    if (empty($total_slots) || !is_numeric($total_slots) || $total_slots <= 0) {
        $errors[] = "Total slots must be a positive number.";
    }
    
    $sold = $category['total_slots'] - $category['slots_remaining'];
    if ($total_slots < $sold) {
        $errors[] = "Total slots cannot be less than already sold tickets ($sold).";
    }
    
    if (empty($errors)) {
        $category_name = mysqli_real_escape_string($conn, $category_name);
        $eligible_type = mysqli_real_escape_string($conn, $eligible_type);
        $price = floatval($price);
        $total_slots = intval($total_slots);
        $new_remaining = max(0, $total_slots - $sold);
        
        $sql = "UPDATE Ticket_Category SET 
                event_id = $event_id,
                category_name = '$category_name',
                eligible_type = '$eligible_type',
                price = $price,
                total_slots = $total_slots,
                slots_remaining = $new_remaining
                WHERE category_id = $category_id";
        
        if (mysqli_query($conn, $sql)) {
            $_SESSION['success'] = "Category updated successfully.";
            redirect($backUrl);
        } else {
            $errors[] = "Error updating category: " . mysqli_error($conn);
        }
    }
}

$form_values = !empty($_POST) ? $_POST : $category;
?>

<div class="page active">
    <div class="page-header">
        <div>
            <div class="page-title">Edit Ticket Category</div>
            <div class="page-sub">Update ticket tier information</div>
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
                <label class="form-label">Event *</label>
                <select name="event_id" required>
                    <?php while ($event = mysqli_fetch_assoc($events)): ?>
                        <option value="<?php echo $event['event_id']; ?>" <?php echo ($form_values['event_id'] ?? '') == $event['event_id'] ? 'selected' : ''; ?>>
                            <?php echo h($event['event_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Category name *</label>
                <input type="text" name="category_name" value="<?php echo h($form_values['category_name'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">Eligible type *</label>
                <select name="eligible_type" required>
                    <option value="all" <?php echo ($form_values['eligible_type'] ?? '') == 'all' ? 'selected' : ''; ?>>All</option>
                    <option value="student" <?php echo ($form_values['eligible_type'] ?? '') == 'student' ? 'selected' : ''; ?>>Student</option>
                    <option value="employee" <?php echo ($form_values['eligible_type'] ?? '') == 'employee' ? 'selected' : ''; ?>>Employee</option>
                    <option value="alumni" <?php echo ($form_values['eligible_type'] ?? '') == 'alumni' ? 'selected' : ''; ?>>Alumni</option>
                    <option value="guest" <?php echo ($form_values['eligible_type'] ?? '') == 'guest' ? 'selected' : ''; ?>>Guest</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Price (PHP) *</label>
                <input type="number" name="price" value="<?php echo h($form_values['price'] ?? '0.00'); ?>" min="0" step="0.01" required>
            </div>
            <div class="form-group">
                <label class="form-label">Total slots *</label>
                <input type="number" name="total_slots" value="<?php echo h($form_values['total_slots'] ?? ''); ?>" min="1" required>
            </div>
            <div style="font-size:12px;color:var(--color-text-secondary);margin-bottom:12px;">
                Currently sold: <strong><?php echo $category['total_slots'] - $category['slots_remaining']; ?> tickets</strong>
            </div>
            <div style="display:flex;gap:8px;margin-top:16px">
                <button type="submit" class="btn btn-primary">Update Category</button>
                <a href="<?php echo $backUrl; ?>" class="btn">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>