<?php
require_once __DIR__ . '/../../includes/header.php';

if (isPost()) {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $birth_date = trim($_POST['birth_date'] ?? '');
    $attendee_type = trim($_POST['attendee_type'] ?? '');
    
    // Subtype fields
    $student_id = trim($_POST['student_id'] ?? '');
    $program = trim($_POST['program'] ?? '');
    $year_level = trim($_POST['year_level'] ?? '');
    $department = trim($_POST['department'] ?? '');
    
    $employee_id = trim($_POST['employee_id'] ?? '');
    $job_title = trim($_POST['job_title'] ?? '');
    
    $alumni_id = trim($_POST['alumni_id'] ?? '');
    $graduation_year = trim($_POST['graduation_year'] ?? '');
    
    $guest_id = trim($_POST['guest_id'] ?? '');
    
    $errors = [];
    
    if (empty($first_name)) $errors[] = "First name is required.";
    if (empty($last_name)) $errors[] = "Last name is required.";
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    if (empty($birth_date)) $errors[] = "Birth date is required.";
    
    // Check if email exists
    if (empty($errors)) {
        $check_email = mysqli_query($conn, "SELECT attendee_id FROM Attendee WHERE email = '" . mysqli_real_escape_string($conn, $email) . "'");
        if (mysqli_num_rows($check_email) > 0) {
            $errors[] = "An attendee with this email already exists.";
        }
    }
    
    // Check unique IDs
    if (empty($errors) && $attendee_type == 'student' && !empty($student_id)) {
        $check = mysqli_query($conn, "SELECT attendee_id FROM Attendee WHERE student_id = '" . mysqli_real_escape_string($conn, $student_id) . "'");
        if (mysqli_num_rows($check) > 0) {
            $errors[] = "Student ID already exists.";
        }
    }
    if (empty($errors) && $attendee_type == 'employee' && !empty($employee_id)) {
        $check = mysqli_query($conn, "SELECT attendee_id FROM Attendee WHERE employee_id = '" . mysqli_real_escape_string($conn, $employee_id) . "'");
        if (mysqli_num_rows($check) > 0) {
            $errors[] = "Employee ID already exists.";
        }
    }
    if (empty($errors) && $attendee_type == 'alumni' && !empty($alumni_id)) {
        $check = mysqli_query($conn, "SELECT attendee_id FROM Attendee WHERE alumni_id = '" . mysqli_real_escape_string($conn, $alumni_id) . "'");
        if (mysqli_num_rows($check) > 0) {
            $errors[] = "Alumni ID already exists.";
        }
    }
    if (empty($errors) && $attendee_type == 'guest' && !empty($guest_id)) {
        $check = mysqli_query($conn, "SELECT attendee_id FROM Attendee WHERE guest_id = '" . mysqli_real_escape_string($conn, $guest_id) . "'");
        if (mysqli_num_rows($check) > 0) {
            $errors[] = "Guest ID already exists.";
        }
    }
    
    if (empty($errors)) {
        // Escape all values
        $first_name = mysqli_real_escape_string($conn, $first_name);
        $last_name = mysqli_real_escape_string($conn, $last_name);
        $email = mysqli_real_escape_string($conn, $email);
        $phone = !empty($phone) ? "'" . mysqli_real_escape_string($conn, $phone) . "'" : "NULL";
        $gender = mysqli_real_escape_string($conn, $gender);
        $birth_date = mysqli_real_escape_string($conn, $birth_date);
        $attendee_type = mysqli_real_escape_string($conn, $attendee_type);
        
        $student_id_sql = !empty($student_id) ? "'" . mysqli_real_escape_string($conn, $student_id) . "'" : "NULL";
        $program_sql = !empty($program) ? "'" . mysqli_real_escape_string($conn, $program) . "'" : "NULL";
        $year_level_sql = !empty($year_level) ? intval($year_level) : "NULL";
        $department_sql = !empty($department) ? "'" . mysqli_real_escape_string($conn, $department) . "'" : "NULL";
        
        $employee_id_sql = !empty($employee_id) ? "'" . mysqli_real_escape_string($conn, $employee_id) . "'" : "NULL";
        $job_title_sql = !empty($job_title) ? "'" . mysqli_real_escape_string($conn, $job_title) . "'" : "NULL";
        
        $alumni_id_sql = !empty($alumni_id) ? "'" . mysqli_real_escape_string($conn, $alumni_id) . "'" : "NULL";
        $graduation_year_sql = !empty($graduation_year) ? intval($graduation_year) : "NULL";
        
        $guest_id_sql = !empty($guest_id) ? "'" . mysqli_real_escape_string($conn, $guest_id) . "'" : "NULL";
        
        $sql = "INSERT INTO Attendee (
                    first_name, last_name, email, phone, gender, birth_date, attendee_type,
                    student_id, program, year_level, department,
                    employee_id, job_title,
                    alumni_id, graduation_year,
                    guest_id
                ) VALUES (
                    '$first_name', '$last_name', '$email', $phone, '$gender', '$birth_date', '$attendee_type',
                    $student_id_sql, $program_sql, $year_level_sql, $department_sql,
                    $employee_id_sql, $job_title_sql,
                    $alumni_id_sql, $graduation_year_sql,
                    $guest_id_sql
                )";
        
        if (mysqli_query($conn, $sql)) {
            $_SESSION['success'] = "Attendee registered successfully.";
            redirect('/event-ticketing-v2/modules/attendees/');
        } else {
            $errors[] = "Error registering attendee: " . mysqli_error($conn);
        }
    }
}

// Get current values for form
$form_values = $_POST;
?>

<div class="page active">
    <div class="page-header">
        <div>
            <div class="page-title">Register Attendee</div>
            <div class="page-sub">Add a new event attendee</div>
        </div>
        <a href="/event-ticketing-v2/modules/attendees/" class="btn">← Back to Attendees</a>
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
        <form method="POST" id="attendeeForm">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                <div class="form-group">
                    <label class="form-label">First name *</label>
                    <input type="text" name="first_name" value="<?php echo h($form_values['first_name'] ?? ''); ?>" placeholder="Enter first name" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Last name *</label>
                    <input type="text" name="last_name" value="<?php echo h($form_values['last_name'] ?? ''); ?>" placeholder="Enter last name" required>
                </div>
                <div class="form-group" style="grid-column:1/-1">
                    <label class="form-label">Email *</label>
                    <input type="email" name="email" value="<?php echo h($form_values['email'] ?? ''); ?>" placeholder="Enter email address" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" value="<?php echo h($form_values['phone'] ?? ''); ?>" placeholder="Enter phone number">
                </div>
                <div class="form-group">
                    <label class="form-label">Birth date *</label>
                    <input type="date" name="birth_date" value="<?php echo h($form_values['birth_date'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Gender *</label>
                    <select name="gender" required>
                        <option value="male" <?php echo ($form_values['gender'] ?? '') == 'male' ? 'selected' : ''; ?>>Male</option>
                        <option value="female" <?php echo ($form_values['gender'] ?? '') == 'female' ? 'selected' : ''; ?>>Female</option>
                        <option value="non_binary" <?php echo ($form_values['gender'] ?? '') == 'non_binary' ? 'selected' : ''; ?>>Non-binary</option>
                        <option value="prefer_not_to_say" <?php echo ($form_values['gender'] ?? '') == 'prefer_not_to_say' ? 'selected' : ''; ?>>Prefer not to say</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Attendee type *</label>
                    <select name="attendee_type" id="attendee_type" required onchange="toggleSubtypeFields()">
                        <option value="student" <?php echo ($form_values['attendee_type'] ?? 'student') == 'student' ? 'selected' : ''; ?>>Student</option>
                        <option value="employee" <?php echo ($form_values['attendee_type'] ?? '') == 'employee' ? 'selected' : ''; ?>>Employee</option>
                        <option value="alumni" <?php echo ($form_values['attendee_type'] ?? '') == 'alumni' ? 'selected' : ''; ?>>Alumni</option>
                        <option value="guest" <?php echo ($form_values['attendee_type'] ?? '') == 'guest' ? 'selected' : ''; ?>>Guest</option>
                    </select>
                </div>
            </div>
            
            <div id="student_fields" style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:8px">
                <div class="form-group">
                    <label class="form-label">Student ID</label>
                    <input type="text" name="student_id" value="<?php echo h($form_values['student_id'] ?? ''); ?>" placeholder="Enter student ID (e.g.: 2020-00000)">
                </div>
                <div class="form-group">
                    <label class="form-label">Year level</label>
                    <input type="number" name="year_level" min="1" max="5" value="<?php echo h($form_values['year_level'] ?? ''); ?>" placeholder="Enter year level (1-5)">
                </div>
                <div class="form-group">
                    <label class="form-label">Program</label>
                    <select name="program">
                        <option value="">Select program</option>
                        <option value="BAComm" <?php echo ($form_values['program'] ?? '') == 'BAComm' ? 'selected' : ''; ?>>BAComm</option>
                        <option value="BSAccountancy" <?php echo ($form_values['program'] ?? '') == 'BSAccountancy' ? 'selected' : ''; ?>>BSAccountancy</option>
                        <option value="BSArchi" <?php echo ($form_values['program'] ?? '') == 'BSArchi' ? 'selected' : ''; ?>>BSArchi</option>
                        <option value="BSBA" <?php echo ($form_values['program'] ?? '') == 'BSBA' ? 'selected' : ''; ?>>BSBA</option>
                        <option value="BSCrim" <?php echo ($form_values['program'] ?? '') == 'BSCrim' ? 'selected' : ''; ?>>BSCrim</option>
                        <option value="BSCS" <?php echo ($form_values['program'] ?? '') == 'BSCS' ? 'selected' : ''; ?>>BSCS</option>
                        <option value="BSEntrep" <?php echo ($form_values['program'] ?? '') == 'BSEntrep' ? 'selected' : ''; ?>>BSEntrep</option>
                        <option value="BSEduc" <?php echo ($form_values['program'] ?? '') == 'BSEduc' ? 'selected' : ''; ?>>BSEduc</option>
                        <option value="BSIT" <?php echo ($form_values['program'] ?? '') == 'BSIT' ? 'selected' : ''; ?>>BSIT</option>
                        <option value="BSMedTech" <?php echo ($form_values['program'] ?? '') == 'BSMedTech' ? 'selected' : ''; ?>>BSMedTech</option>
                        <option value="BSMM" <?php echo ($form_values['program'] ?? '') == 'BSMM' ? 'selected' : ''; ?>>BSMM</option>
                        <option value="BSRadTech" <?php echo ($form_values['program'] ?? '') == 'BSRadTech' ? 'selected' : ''; ?>>BSRadTech</option>
                        <option value="BSPT" <?php echo ($form_values['program'] ?? '') == 'BSPT' ? 'selected' : ''; ?>>BSPT</option>
                        <option value="BSN" <?php echo ($form_values['program'] ?? '') == 'BSN' ? 'selected' : ''; ?>>BSN</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Department</label>
                    <input type="text" name="department" value="<?php echo h($form_values['department'] ?? ''); ?>" placeholder="Enter department">
                </div>
            </div>
            
            <div id="employee_fields" style="display:none;grid-template-columns:1fr 1fr;gap:10px;margin-top:8px">
                <div class="form-group">
                    <label class="form-label">Employee ID</label>
                    <input type="text" name="employee_id" value="<?php echo h($form_values['employee_id'] ?? ''); ?>" placeholder="Enter employee ID (e.g.: EMP-0000)">
                </div>
                <div class="form-group">
                    <label class="form-label">Job title</label>
                    <input type="text" name="job_title" value="<?php echo h($form_values['job_title'] ?? ''); ?>" placeholder="Enter job title">
                </div>
            </div>
            
            <div id="alumni_fields" style="display:none;grid-template-columns:1fr 1fr;gap:10px;margin-top:8px">
                <div class="form-group">
                    <label class="form-label">Alumni ID</label>
                    <input type="text" name="alumni_id" value="<?php echo h($form_values['alumni_id'] ?? ''); ?>" placeholder="Enter alumni ID (e.g.: ALM-0000)">
                </div>
                <div class="form-group">
                    <label class="form-label">Graduation year</label>
                    <select name="graduation_year">
                        <option value="">Select year</option>
                        <?php $currentYear = date('Y'); for ($y = $currentYear; $y >= $currentYear - 50; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php echo ($form_values['graduation_year'] ?? '') == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
            
            <div id="guest_fields" style="display:none;margin-top:8px">
                <div class="form-group">
                    <label class="form-label">Guest ID</label>
                    <input type="text" name="guest_id" value="<?php echo h($form_values['guest_id'] ?? ''); ?>" placeholder="Enter guest ID (e.g.: GST-0000)">
                </div>
            </div>
            
            <div style="display:flex;gap:8px;margin-top:16px;padding-top:16px;border-top:0.5px solid var(--color-border-tertiary)">
                <button type="submit" class="btn btn-primary">Register Attendee</button>
                <a href="<?php echo getBackUrl('/event-ticketing-v2/modules/attendees/'); ?>" class="btn">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
function toggleSubtypeFields() {
    var type = document.getElementById('attendee_type').value;
    document.getElementById('student_fields').style.display = type === 'student' ? 'grid' : 'none';
    document.getElementById('employee_fields').style.display = type === 'employee' ? 'grid' : 'none';
    document.getElementById('alumni_fields').style.display = type === 'alumni' ? 'grid' : 'none';
    document.getElementById('guest_fields').style.display = type === 'guest' ? 'block' : 'none';
}
toggleSubtypeFields();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>