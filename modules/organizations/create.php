<?php
require_once __DIR__ . '/../../includes/header.php';

if (isPost()) {
    $org_name = trim($_POST['org_name'] ?? '');
    $org_type = trim($_POST['org_type'] ?? '');
    $adviser_first_name = trim($_POST['adviser_first_name'] ?? '');
    $adviser_last_name = trim($_POST['adviser_last_name'] ?? '');
    $contact_email = trim($_POST['contact_email'] ?? '');
    $contact_phone = trim($_POST['contact_phone'] ?? '');
    $is_accredited = isset($_POST['is_accredited']) ? (int)$_POST['is_accredited'] : 1;
    
    $errors = [];
    
    if (empty($org_name)) $errors[] = "Organization name is required.";
    if (empty($contact_email)) {
        $errors[] = "Contact email is required.";
    } elseif (!filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    } else {
        $check = mysqli_query($conn, "SELECT org_id FROM Organization WHERE contact_email = '" . mysqli_real_escape_string($conn, $contact_email) . "'");
        if (mysqli_num_rows($check) > 0) {
            $errors[] = "An organization with this email already exists.";
        }
    }
    
    if (empty($errors)) {
        $org_name = mysqli_real_escape_string($conn, $org_name);
        $org_type = mysqli_real_escape_string($conn, $org_type);
        $adviser_first_name = !empty($adviser_first_name) ? "'" . mysqli_real_escape_string($conn, $adviser_first_name) . "'" : "NULL";
        $adviser_last_name = !empty($adviser_last_name) ? "'" . mysqli_real_escape_string($conn, $adviser_last_name) . "'" : "NULL";
        $contact_email = mysqli_real_escape_string($conn, $contact_email);
        $contact_phone = !empty($contact_phone) ? "'" . mysqli_real_escape_string($conn, $contact_phone) . "'" : "NULL";
        
        $sql = "INSERT INTO Organization (org_name, org_type, adviser_first_name, adviser_last_name, contact_email, contact_phone, is_accredited) 
                VALUES ('$org_name', '$org_type', $adviser_first_name, $adviser_last_name, '$contact_email', $contact_phone, $is_accredited)";
        
        if (mysqli_query($conn, $sql)) {
            $_SESSION['success'] = "Organization added successfully.";
            redirect('/event-ticketing-v2/modules/organizations/');
        } else {
            $errors[] = "Error adding organization: " . mysqli_error($conn);
        }
    }
}

$form_values = $_POST;
?>

<div class="page active">
    <div class="page-header">
        <div>
            <div class="page-title">Add Organization</div>
            <div class="page-sub">Register a new event organizer</div>
        </div>
        <a href="/event-ticketing-v2/modules/organizations/" class="btn">← Back to Organizations</a>
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
                <label class="form-label">Organization name *</label>
                <input type="text" name="org_name" value="<?php echo h($form_values['org_name'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">Type *</label>
                <select name="org_type" required>
                    <option value="student_org" <?php echo ($form_values['org_type'] ?? '') == 'student_org' ? 'selected' : ''; ?>>Student org</option>
                    <option value="alumni_org" <?php echo ($form_values['org_type'] ?? '') == 'alumni_org' ? 'selected' : ''; ?>>Alumni org</option>
                    <option value="external" <?php echo ($form_values['org_type'] ?? '') == 'external' ? 'selected' : ''; ?>>External</option>
                    <option value="university_office" <?php echo ($form_values['org_type'] ?? '') == 'university_office' ? 'selected' : ''; ?>>University office</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Accredited</label>
                <select name="is_accredited">
                    <option value="1" <?php echo ($form_values['is_accredited'] ?? '1') == '1' ? 'selected' : ''; ?>>Yes</option>
                    <option value="0" <?php echo ($form_values['is_accredited'] ?? '') === '0' ? 'selected' : ''; ?>>No</option>
                </select>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                <div class="form-group">
                    <label class="form-label">Adviser first name</label>
                    <input type="text" name="adviser_first_name" value="<?php echo h($form_values['adviser_first_name'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Adviser last name</label>
                    <input type="text" name="adviser_last_name" value="<?php echo h($form_values['adviser_last_name'] ?? ''); ?>">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Contact email *</label>
                <input type="email" name="contact_email" value="<?php echo h($form_values['contact_email'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">Contact phone</label>
                <input type="text" name="contact_phone" value="<?php echo h($form_values['contact_phone'] ?? ''); ?>">
            </div>
            <div style="display:flex;gap:8px;margin-top:16px">
                <button type="submit" class="btn btn-primary">Save Organization</button>
                <a href="<?php echo getBackUrl('/event-ticketing-v2/modules/organizations/'); ?>" class="btn">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>