<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$basePath = dirname($_SERVER['SCRIPT_NAME']);
$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;

if (!$event_id) {
    header('Location: ' . $basePath . '/online-reg.php');
    exit;
}

$eventQuery = "
    SELECT e.*, v.venue_name, v.building, v.venue_type, v.capacity,
           o.org_name, o.org_type, o.contact_email
    FROM Event e
    JOIN Venue v ON e.venue_id = v.venue_id
    JOIN Organization o ON e.org_id = o.org_id
    WHERE e.event_id = $event_id AND e.requires_ticket = 1
";
$eventResult = mysqli_query($conn, $eventQuery);
if (!$eventResult || mysqli_num_rows($eventResult) === 0) {
    header('Location: ' . $basePath . '/online-reg.php?error=event_not_found');
    exit;
}
$event = mysqli_fetch_assoc($eventResult);

if ($event['status'] !== 'upcoming' || strtotime($event['event_date']) < strtotime('today')) {
    header('Location: ' . $basePath . '/online-reg.php?error=event_closed');
    exit;
}

// Fetch ALL categories for this event (we'll filter client-side)
$categoriesQuery = "SELECT * FROM Ticket_Category WHERE event_id = $event_id AND slots_remaining > 0 ORDER BY price ASC";
$categories = mysqli_query($conn, $categoriesQuery);
$allCategories = [];
while ($cat = mysqli_fetch_assoc($categories)) {
    $allCategories[] = $cat;
}
$hasCategories = !empty($allCategories);

$errors = [];
$success = false;
$ticketData = null;
$formValues = $_POST;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $birth_date = trim($_POST['birth_date'] ?? '');
    $attendee_type = trim($_POST['attendee_type'] ?? '');
    $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
    
    if (empty($first_name)) $errors[] = "First name is required.";
    if (empty($last_name)) $errors[] = "Last name is required.";
    if (empty($email)) { $errors[] = "Email address is required."; }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = "Please enter a valid email address."; }
    if (empty($birth_date)) $errors[] = "Birth date is required.";
    if (empty($gender)) $errors[] = "Please select your gender.";
    if (empty($attendee_type)) $errors[] = "Please select your affiliation.";
    
    if (!empty($birth_date)) {
        $age = (new DateTime())->diff(new DateTime($birth_date))->y;
        if ($age < 13) $errors[] = "You must be at least 13 years old to register.";
    }
    
    // Validate type-specific fields
    $student_id = $program = $year_level = $department = '';
    $employee_id = $job_title = $alumni_id = $graduation_year = $guest_id = '';
    
    if ($attendee_type === 'student') {
        $student_id = trim($_POST['student_id'] ?? '');
        $program = trim($_POST['program'] ?? '');
        $year_level = trim($_POST['year_level'] ?? '');
        if (empty($student_id)) $errors[] = "Student ID is required.";
        if (empty($program)) $errors[] = "Program is required.";
        if (empty($year_level)) $errors[] = "Year level is required.";
    } elseif ($attendee_type === 'employee') {
        $employee_id = trim($_POST['employee_id'] ?? '');
        $department = trim($_POST['department'] ?? '');
        if (empty($employee_id)) $errors[] = "Employee ID is required.";
        if (empty($department)) $errors[] = "Department is required.";
    } elseif ($attendee_type === 'alumni') {
        $graduation_year = trim($_POST['graduation_year'] ?? '');
        if (empty($graduation_year)) $errors[] = "Graduation year is required.";
    }
    
    // Validate ticket category
    if ($category_id <= 0) {
        $errors[] = "Please select a ticket category.";
    } else {
        // Verify category exists and is eligible
        $catCheck = mysqli_query($conn, "SELECT * FROM Ticket_Category WHERE category_id = $category_id AND event_id = $event_id AND slots_remaining > 0");
        if (!$catCheck || mysqli_num_rows($catCheck) === 0) {
            $errors[] = "Selected ticket category is no longer available.";
        } else {
            $cat = mysqli_fetch_assoc($catCheck);
            if ($cat['eligible_type'] !== 'all' && $cat['eligible_type'] !== $attendee_type) {
                $errors[] = "You are not eligible for this ticket category.";
            }
        }
    }
    
    if (empty($errors)) {
        mysqli_begin_transaction($conn);
        try {
            $emailEsc = mysqli_real_escape_string($conn, $email);
            $checkEmail = mysqli_query($conn, "SELECT attendee_id FROM Attendee WHERE email = '$emailEsc'");
            
            if (mysqli_num_rows($checkEmail) > 0) {
                $existing = mysqli_fetch_assoc($checkEmail);
                $attendee_id = $existing['attendee_id'];
                $dupCheck = mysqli_query($conn, "SELECT t.ticket_id FROM Ticket t JOIN Ticket_Category tc ON t.category_id = tc.category_id WHERE t.attendee_id = $attendee_id AND tc.event_id = $event_id");
                if (mysqli_num_rows($dupCheck) > 0) throw new Exception("You have already registered for this event.");
            } else {
                $fn = mysqli_real_escape_string($conn, $first_name);
                $ln = mysqli_real_escape_string($conn, $last_name);
                $ph = !empty($phone) ? "'" . mysqli_real_escape_string($conn, $phone) . "'" : "NULL";
                $gd = mysqli_real_escape_string($conn, $gender);
                $bd = mysqli_real_escape_string($conn, $birth_date);
                $tp = mysqli_real_escape_string($conn, $attendee_type);
                $sid = !empty($student_id) ? "'" . mysqli_real_escape_string($conn, $student_id) . "'" : "NULL";
                $pgm = !empty($program) ? "'" . mysqli_real_escape_string($conn, $program) . "'" : "NULL";
                $yl = !empty($year_level) ? (int)$year_level : "NULL";
                $dept = !empty($department) ? "'" . mysqli_real_escape_string($conn, $department) . "'" : "NULL";
                $eid = !empty($employee_id) ? "'" . mysqli_real_escape_string($conn, $employee_id) . "'" : "NULL";
                $jt = !empty($job_title) ? "'" . mysqli_real_escape_string($conn, $job_title) . "'" : "NULL";
                $aid = !empty($alumni_id) ? "'" . mysqli_real_escape_string($conn, $alumni_id) . "'" : "NULL";
                $gy = !empty($graduation_year) ? (int)$graduation_year : "NULL";
                $gid = "NULL";
                if ($attendee_type === 'guest') $gid = "'GST-" . strtoupper(substr(uniqid(), -6)) . "'";
                
                $sql = "INSERT INTO Attendee (first_name,last_name,email,phone,gender,birth_date,attendee_type,student_id,program,year_level,department,employee_id,job_title,alumni_id,graduation_year,guest_id) VALUES ('$fn','$ln','$emailEsc',$ph,'$gd','$bd','$tp',$sid,$pgm,$yl,$dept,$eid,$jt,$aid,$gy,$gid)";
                if (!mysqli_query($conn, $sql)) throw new Exception("Error registering: " . mysqli_error($conn));
                $attendee_id = mysqli_insert_id($conn);
            }
            
            $cat = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM Ticket_Category WHERE category_id = $category_id"));
            
            $ticket_code = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0x0fff)|0x4000, mt_rand(0,0x3fff)|0x8000, mt_rand(0,0xffff), mt_rand(0,0xffff), mt_rand(0,0xffff));
            $tc = mysqli_real_escape_string($conn, $ticket_code);
            $price = $cat['price'];
            $pstat = $price > 0 ? 'pending' : 'free';
            $pmeth = $price > 0 ? "'online'" : "NULL";
            
            mysqli_query($conn, "INSERT INTO Ticket (ticket_code,payment_status,payment_method,category_id,attendee_id) VALUES ('$tc','$pstat',$pmeth,$category_id,$attendee_id)");
            $ticket_id = mysqli_insert_id($conn);
            mysqli_query($conn, "UPDATE Ticket_Category SET slots_remaining = slots_remaining - 1 WHERE category_id = $category_id");
            mysqli_commit($conn);
            
            $tq = "SELECT t.*, a.first_name, a.last_name, a.email, a.attendee_type, tc.category_name, tc.price, e.event_name, e.event_date, e.start_time, e.end_time, v.venue_name, o.org_name FROM Ticket t JOIN Attendee a ON t.attendee_id=a.attendee_id JOIN Ticket_Category tc ON t.category_id=tc.category_id JOIN Event e ON tc.event_id=e.event_id JOIN Venue v ON e.venue_id=v.venue_id JOIN Organization o ON e.org_id=o.org_id WHERE t.ticket_id=$ticket_id";
            $tr = mysqli_query($conn, $tq);
            $ticketData = mysqli_fetch_assoc($tr);
            $success = true;
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $errors[] = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — <?php echo h($event['event_name']); ?></title>
    <link rel="stylesheet" href="<?php echo $basePath; ?>/assets/css/user-reg.css">
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .user-portal { font-family: 'Inter', sans-serif; background: #f8f5f6; min-height: 100vh; margin: 0; color: #1a1a1a; }
        .user-container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
        .user-header { background: #7e1416; color: white; padding: 12px 0; width: 100vw; margin-left: calc(-50vw + 50%); margin-right: calc(-50vw + 50%); }
        .header-content { max-width: 1200px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
        .back-link { color: rgba(255,255,255,0.85); text-decoration: none; font-size: 13px; font-weight: 500; }
        .back-link:hover { color: #f9be1b; }
        .btn-outline-light { padding: 5px 10px; background: rgba(255,255,255,0.08); color: white; border: 1px solid rgba(255,255,255,0.2); border-radius: 3px; text-decoration: none; font-size: 11px; }
        .registration-container { max-width: 720px; margin: 24px auto; padding: 0 20px; }
        .event-summary-card { background: #fdfbfc; border-radius: 6px; padding: 20px; margin-bottom: 18px; border: 1px solid #e5e5e5; }
        .event-badges { display: flex; gap: 5px; margin-bottom: 8px; flex-wrap: wrap; }
        .event-type-badge { padding: 3px 10px; border-radius: 3px; font-size: 10px; font-weight: 700; text-transform: uppercase; }
        .badge-academic{background:#eff6ff;color:#1e40af} .badge-cultural{background:#f0fdf4;color:#166534}
        .badge-sports{background:#fffbeb;color:#92400e} .badge-concert{background:#faf5ff;color:#6b21a8}
        .badge-seminar{background:#f0fdfa;color:#0f766e} .badge-graduation{background:#eef2ff;color:#3730a3}
        .badge-orientation{background:#fef2f2;color:#991b1b} .badge-other{background:#f5f5f5;color:#737373}
        .audience-badge { padding: 3px 10px; background: #fdf0f1; color: #7e1416; border-radius: 3px; font-size: 10px; font-weight: 600; }
        .event-summary-header h2 { font-family: 'Inter', serif; font-size: 20px; margin: 6px 0 0; color: #7e1416; }
        .event-summary-details { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 12px; padding-top: 12px; border-top: 1px solid #f0f0f0; }
        .detail-item { font-size: 13px; color: #525252; }
        .progress-steps { display: flex; align-items: center; justify-content: center; margin-bottom: 20px; padding: 16px; background: #fdfbfc; border-radius: 6px; border: 1px solid #e5e5e5; }
        .step { display: flex; flex-direction: column; align-items: center; gap: 6px; font-size: 11px; font-weight: 600; color: #737373; }
        .step.active { color: #7e1416; }
        .step.completed { color: #16a34a; }
        .step-number { width: 30px; height: 30px; border-radius: 50%; background: #f0f0f0; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px; }
        .step.active .step-number { background: #7e1416; color: white; }
        .step.completed .step-number { background: #16a34a; color: white; }
        .step-connector { width: 50px; height: 2px; background: #f0f0f0; margin: 0 6px; }
        .step-connector.active { background: #16a34a; }
        .registration-form { background: #fdfbfc; border-radius: 6px; padding: 24px; border: 1px solid #e5e5e5; }
        .form-section { margin-bottom: 24px; }
        .section-title { font-size: 15px; font-weight: 700; color: #7e1416; margin: 0 0 18px; padding-bottom: 10px; border-bottom: 2px solid #f9be1b; display: flex; align-items: center; gap: 8px; }
        .section-number { display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; background: #7e1416; color: white; border-radius: 50%; font-size: 12px; font-weight: 700; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .form-group { margin-bottom: 14px; }
        .form-label { display: block; font-size: 11px; font-weight: 700; color: #525252; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.3px; }
        .required { color: #dc2626; }
        .form-group input, .form-group select { width: 100%; padding: 9px 12px; border: 1px solid #e5e5e5; border-radius: 4px; font-size: 13px; font-family: 'Inter', sans-serif; background: #fdfbfc; box-sizing: border-box; transition: all 0.15s; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #7e1416; box-shadow: 0 0 0 2px rgba(126,20,22,0.08); }
        .affiliation-options { display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px; }
        .affiliation-option { display: flex; flex-direction: column; align-items: center; gap: 5px; padding: 14px 8px; border: 1px solid #e5e5e5; border-radius: 4px; cursor: pointer; transition: all 0.15s; text-align: center; background: #fdfbfc; }
        .affiliation-option:hover { border-color: #f9be1b; background: #fffdf5; }
        .affiliation-option.selected { border-color: #7e1416; background: #fdf0f1; }
        .affiliation-option input[type="radio"] { display: none; }
        .affiliation-icon { font-size: 24px; }
        .affiliation-option span:last-child { font-size: 12px; font-weight: 600; color: #7e1416; }
        .affiliation-fields { padding: 14px; background: #fdfbfc; border-radius: 4px; margin-top: 6px; border: 1px solid #f0f0f0; display: none; }
        .ticket-options { display: flex; flex-direction: column; gap: 10px; }
        .ticket-option { display: block; padding: 16px; border: 1px solid #e5e5e5; border-radius: 4px; cursor: pointer; transition: all 0.15s; background: #fdfbfc; }
        .ticket-option:hover:not(.disabled) { border-color: #f9be1b; }
        .ticket-option.selected { border-color: #7e1416; background: #fdf0f1; }
        .ticket-option.disabled { opacity: 0.35; cursor: not-allowed; pointer-events: none; }
        .ticket-option.hidden { display: none; }
        .ticket-option input[type="radio"] { display: none; }
        .ticket-option-content { pointer-events: none; }
        .ticket-option-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px; gap: 10px; }
        .ticket-option-name { font-weight: 700; font-size: 14px; color: #7e1416; display: block; }
        .ticket-option-eligible { font-size: 10px; color: #737373; display: block; margin-top: 1px; }
        .ticket-option-price { font-weight: 700; font-size: 15px; color: #7e1416; white-space: nowrap; }
        .ticket-option-meta { font-size: 11px; color: #737373; margin-bottom: 8px; }
        .slot-progress { height: 3px; background: #f0f0f0; border-radius: 2px; overflow: hidden; }
        .slot-bar { height: 100%; border-radius: 2px; background: #16a34a; }
        .slot-bar.warning { background: #d97706; }
        .form-actions { display: flex; gap: 10px; justify-content: flex-end; padding-top: 18px; border-top: 1px solid #f0f0f0; flex-wrap: wrap; }
        .btn-primary { padding: 11px 24px; background: #7e1416; color: white; border: 1px solid #7e1416; border-radius: 4px; font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 6px; }
        .btn-primary:hover { background: #5c0e10; }
        .btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-secondary { padding: 11px 22px; background: #fdfbfc; color: #525252; border: 1px solid #e5e5e5; border-radius: 4px; font-size: 14px; font-weight: 500; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; }
        .btn-secondary:hover { background: #f8f5f6; }
        .error-box { background: #fef2f2; border: 1px solid #fecaca; border-left: 3px solid #dc2626; border-radius: 4px; padding: 12px 16px; margin-bottom: 16px; color: #991b1b; }
        .error-box ul { margin: 6px 0 0 18px; }
        .warning-box { background: #fffbeb; border: 1px solid #fde68a; border-left: 3px solid #d97706; border-radius: 4px; padding: 10px 14px; color: #92400e; font-size: 12px; margin-top: 6px; display: none; }
        .no-tickets-warning { background: #fef2f2; border: 1px solid #fecaca; border-radius: 4px; padding: 14px 16px; color: #991b1b; font-size: 13px; text-align: center; display: none; }
        .success-screen { background: #fdfbfc; border-radius: 6px; padding: 32px; text-align: center; border: 1px solid #e5e5e5; }
        .success-icon { font-size: 60px; margin-bottom: 12px; }
        .success-screen h2 { font-family: 'Inter', serif; font-size: 24px; color: #16a34a; margin: 0 0 6px; }
        .ticket-preview { background: #fffdf5; border: 1px solid #f9be1b; border-radius: 6px; overflow: hidden; margin: 22px 0; text-align: left; }
        .ticket-preview-header { background: #7e1416; color: white; padding: 18px; text-align: center; }
        .ticket-preview-header h3 { margin: 0 0 4px; font-size: 17px; font-family: 'Inter', serif; }
        .ticket-preview-body { padding: 18px; }
        .ticket-info-row { display: flex; justify-content: space-between; padding: 7px 0; border-bottom: 1px solid #fef3c7; font-size: 13px; }
        .ticket-label { font-size: 10px; color: #737373; font-weight: 700; text-transform: uppercase; }
        .ticket-value { color: #7e1416; font-weight: 500; text-align: right; }
        .ticket-code-display { background: #7e1416; border-radius: 4px; padding: 16px; text-align: center; margin: 16px 0; }
        .code-label { color: rgba(255,255,255,0.8); font-size: 10px; font-weight: 700; text-transform: uppercase; margin: 0 0 6px; }
        .code-value { display: block; color: #f9be1b; font-size: 14px; font-family: 'Courier New', monospace; word-break: break-all; background: rgba(0,0,0,0.2); padding: 10px; border-radius: 4px; }
        .btn-copy { margin-top: 8px; padding: 6px 16px; background: rgba(255,255,255,0.12); color: white; border: 1px solid rgba(255,255,255,0.2); border-radius: 4px; font-size: 12px; cursor: pointer; }
        .success-actions { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; margin-bottom: 18px; }
        .user-footer { text-align: center; padding: 24px 0; color: #737373; font-size: 12px; border-top: 1px solid #e5e5e5; margin-top: 32px; }
        @media (max-width:768px) { .form-row { grid-template-columns:1fr; } .affiliation-options { grid-template-columns:repeat(2,1fr); } .event-summary-details { grid-template-columns:1fr; } .registration-form { padding:18px; } }
        @media print { body * { visibility:hidden; } .ticket-preview,.ticket-preview * { visibility:visible; } .ticket-preview { position:absolute; left:0; top:0; width:100%; box-shadow:none; border:1px solid #000; } .success-actions,.user-header,.user-footer,.btn-copy { display:none!important; } }
    </style>
</head>
<body class="user-portal">
    <header class="user-header">
        <div class="header-content">
            <div>
                <img src="<?php echo $basePath; ?>/uphsd-logo.png" alt="UPHSD" style="width:32px;height:auto;vertical-align:middle;margin-right:8px;">
                <a href="<?php echo $basePath; ?>/online-reg.php" class="back-link">← Back to Events</a>
                <span style="color:rgba(255,255,255,0.25);margin:0 8px;">|</span>
                <span style="color:rgba(255,255,255,0.9);font-size:13px;"><?php echo h($event['event_name']); ?></span>
            </div>
        </div>
    </header>

    <div class="registration-container">
        <?php if ($success && $ticketData): ?>
        <div class="success-screen">
            <h2>Registration Successful!</h2>
            <p style="color:#525252;margin-bottom:22px;">You have registered for <strong><?php echo h($ticketData['event_name']); ?></strong>.</p>
            <div class="ticket-preview">
                <div class="ticket-preview-header">
                    <h3><?php echo h($ticketData['event_name']); ?></h3>
                    <p><?php echo date('F d, Y', strtotime($ticketData['event_date'])); ?> · <?php echo date('g:i A', strtotime($ticketData['start_time'])); ?></p>
                    <p><?php echo h($ticketData['venue_name']); ?></p>
                </div>
                <div class="ticket-preview-body">
                    <div class="ticket-info-row"><span class="ticket-label">Attendee</span><span class="ticket-value"><?php echo h($ticketData['first_name'].' '.$ticketData['last_name']); ?></span></div>
                    <div class="ticket-info-row"><span class="ticket-label">Type</span><span class="ticket-value"><?php echo ucfirst(h($ticketData['attendee_type'])); ?></span></div>
                    <div class="ticket-info-row"><span class="ticket-label">Category</span><span class="ticket-value"><?php echo h($ticketData['category_name']); ?></span></div>
                    <div class="ticket-info-row"><span class="ticket-label">Price</span><span class="ticket-value"><?php echo $ticketData['price']>0?'₱'.number_format($ticketData['price'],2):'FREE'; ?></span></div>
                    <div class="ticket-code-display">
                        <p class="code-label">Your Ticket Code</p>
                        <code class="code-value" id="ticketCode"><?php echo h($ticketData['ticket_code']); ?></code>
                        <button class="btn-copy" onclick="copyCode()">📋 Copy Code</button>
                    </div>
                    <div style="text-align:center;margin-top:16px;">
                        <div id="qrcode" style="display:inline-block;"></div>
                        <p style="font-size:10px;color:#737373;margin-top:8px;">Scan at event entrance</p>
                    </div>
                </div>
            </div>
            <div class="success-actions">
                <button class="btn-primary" onclick="window.print()">🖨 Print Ticket</button>
                <a href="<?php echo $basePath; ?>/online-reg.php" class="btn-secondary">Register for Another Event</a>
                <a href="<?php echo $basePath; ?>/ticket-lookup.php?code=<?php echo urlencode($ticketData['ticket_code']); ?>" class="btn-secondary">View Ticket Page</a>
            </div>
        </div>
        <?php else: ?>
        <div class="event-summary-card">
            <div class="event-badges">
                <span class="event-type-badge badge-<?php echo $event['event_type']; ?>"><?php echo ucfirst($event['event_type']); ?></span>
                <span class="audience-badge"><?php echo str_replace('_',' ',ucfirst($event['audience_type'])); ?></span>
            </div>
            <h2><?php echo h($event['event_name']); ?></h2>
            <div class="event-summary-details">
                <div class="detail-item"><span>📅</span> <strong>Date:</strong> <?php echo date('F d, Y', strtotime($event['event_date'])); ?></div>
                <div class="detail-item"><span>🕐</span> <strong>Time:</strong> <?php echo date('g:i A', strtotime($event['start_time'])); ?> - <?php echo date('g:i A', strtotime($event['end_time'])); ?></div>
                <div class="detail-item"><span>📍</span> <strong>Venue:</strong> <?php echo h($event['venue_name']); ?></div>
                <div class="detail-item"><span>🏛️</span> <strong>Organizer:</strong> <?php echo h($event['org_name']); ?></div>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="error-box"><strong>⚠️ Please correct:</strong><ul><?php foreach($errors as $e): ?><li><?php echo h($e); ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <div class="progress-steps">
            <div class="step active"><div class="step-number">1</div><span>Personal Info</span></div>
            <div class="step-connector"></div>
            <div class="step"><div class="step-number">2</div><span>Ticket Selection</span></div>
            <div class="step-connector"></div>
            <div class="step"><div class="step-number">3</div><span>Confirmation</span></div>
        </div>

        <?php if ($hasCategories): ?>
        <form method="POST" class="registration-form" id="regForm" novalidate>
            <div class="form-section">
                <h3 class="section-title"><span class="section-number">1</span> Personal Information</h3>
                <div class="form-row">
                    <div class="form-group"><label class="form-label">First Name <span class="required">*</span></label><input type="text" name="first_name" value="<?php echo h($formValues['first_name']??''); ?>" required></div>
                    <div class="form-group"><label class="form-label">Last Name <span class="required">*</span></label><input type="text" name="last_name" value="<?php echo h($formValues['last_name']??''); ?>" required></div>
                </div>
                <div class="form-group"><label class="form-label">Email <span class="required">*</span></label><input type="email" name="email" value="<?php echo h($formValues['email']??''); ?>" required></div>
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Phone</label><input type="tel" name="phone" value="<?php echo h($formValues['phone']??''); ?>"></div>
                    <div class="form-group"><label class="form-label">Birth Date <span class="required">*</span></label><input type="date" name="birth_date" value="<?php echo h($formValues['birth_date']??''); ?>" required></div>
                </div>
                <div class="form-group"><label class="form-label">Gender <span class="required">*</span></label>
                    <select name="gender" required>
                        <option value="">Select</option>
                        <option value="male" <?php echo ($formValues['gender']??'')==='male'?'selected':''; ?>>Male</option>
                        <option value="female" <?php echo ($formValues['gender']??'')==='female'?'selected':''; ?>>Female</option>
                        <option value="non_binary" <?php echo ($formValues['gender']??'')==='non_binary'?'selected':''; ?>>Non-binary</option>
                        <option value="prefer_not_to_say" <?php echo ($formValues['gender']??'')==='prefer_not_to_say'?'selected':''; ?>>Prefer not to say</option>
                    </select>
                </div>
                <div class="form-group"><label class="form-label">I am a... <span class="required">*</span></label>
                    <div class="affiliation-options" id="affiliationOptions">
                        <label class="affiliation-option <?php echo ($formValues['attendee_type']??'')==='student'?'selected':''; ?>" data-type="student" onclick="selectAffiliation('student')">
                            <input type="radio" name="attendee_type" value="student" <?php echo ($formValues['attendee_type']??'')==='student'?'checked':''; ?>><span class="affiliation-icon">🎓</span><span>Student</span>
                        </label>
                        <label class="affiliation-option <?php echo ($formValues['attendee_type']??'')==='employee'?'selected':''; ?>" data-type="employee" onclick="selectAffiliation('employee')">
                            <input type="radio" name="attendee_type" value="employee" <?php echo ($formValues['attendee_type']??'')==='employee'?'checked':''; ?>><span class="affiliation-icon">💼</span><span>Employee</span>
                        </label>
                        <label class="affiliation-option <?php echo ($formValues['attendee_type']??'')==='alumni'?'selected':''; ?>" data-type="alumni" onclick="selectAffiliation('alumni')">
                            <input type="radio" name="attendee_type" value="alumni" <?php echo ($formValues['attendee_type']??'')==='alumni'?'checked':''; ?>><span class="affiliation-icon">🏆</span><span>Alumni</span>
                        </label>
                        <label class="affiliation-option <?php echo ($formValues['attendee_type']??'')==='guest'?'selected':''; ?>" data-type="guest" onclick="selectAffiliation('guest')">
                            <input type="radio" name="attendee_type" value="guest" <?php echo ($formValues['attendee_type']??'')==='guest'?'checked':''; ?>><span class="affiliation-icon">🎫</span><span>Guest</span>
                        </label>
                    </div>
                </div>
                <div id="student-fields" class="affiliation-fields">
                    <div class="form-row"><div class="form-group"><label class="form-label">Student ID <span class="required">*</span></label><input type="text" name="student_id" value="<?php echo h($formValues['student_id']??''); ?>"></div><div class="form-group"><label class="form-label">Year Level <span class="required">*</span></label><select name="year_level"><option value="">Select</option><?php for($i=1;$i<=5;$i++): ?><option value="<?php echo $i; ?>" <?php echo ($formValues['year_level']??'')==$i?'selected':''; ?>>Year <?php echo $i; ?></option><?php endfor; ?></select></div></div>
                    <div class="form-row"><div class="form-group"><label class="form-label">Program <span class="required">*</span></label><select name="program"><option value="">Select</option><option value="BSCS">BS Computer Science</option><option value="BSIT">BS Information Technology</option><option value="BSBA">BS Business Administration</option></select></div><div class="form-group"><label class="form-label">Department</label><input type="text" name="department" value="<?php echo h($formValues['department']??''); ?>"></div></div>
                </div>
                <div id="employee-fields" class="affiliation-fields">
                    <div class="form-row"><div class="form-group"><label class="form-label">Employee ID <span class="required">*</span></label><input type="text" name="employee_id" value="<?php echo h($formValues['employee_id']??''); ?>"></div><div class="form-group"><label class="form-label">Department <span class="required">*</span></label><input type="text" name="department" value="<?php echo h($formValues['department']??''); ?>"></div></div>
                    <div class="form-group"><label class="form-label">Job Title</label><input type="text" name="job_title" value="<?php echo h($formValues['job_title']??''); ?>"></div>
                </div>
                <div id="alumni-fields" class="affiliation-fields">
                    <div class="form-group"><label class="form-label">Graduation Year <span class="required">*</span></label><select name="graduation_year"><option value="">Select</option><?php for($y=date('Y');$y>=1960;$y--): ?><option value="<?php echo $y; ?>"><?php echo $y; ?></option><?php endfor; ?></select></div>
                </div>
                <div id="guest-fields" class="affiliation-fields"><p style="color:#525252;font-size:13px;padding:8px;background:#fffbeb;border-radius:4px;">ℹ️ Guest registration is open to all visitors.</p></div>
            </div>
            
            <div class="form-section">
                <h3 class="section-title"><span class="section-number">2</span> Select Your Ticket</h3>
                <div class="ticket-options" id="ticketOptions">
                    <?php foreach($allCategories as $cat): $sp=$cat['total_slots']>0?round((($cat['total_slots']-$cat['slots_remaining'])/$cat['total_slots'])*100):0; ?>
                    <label class="ticket-option" data-eligible="<?php echo $cat['eligible_type']; ?>" data-catid="<?php echo $cat['category_id']; ?>">
                        <input type="radio" name="category_id" value="<?php echo $cat['category_id']; ?>" data-eligible="<?php echo $cat['eligible_type']; ?>">
                        <div class="ticket-option-content">
                            <div class="ticket-option-header">
                                <div><span class="ticket-option-name"><?php echo h($cat['category_name']); ?></span><span class="ticket-option-eligible">For <?php echo $cat['eligible_type']==='all'?'Everyone':ucfirst($cat['eligible_type']).'s'; ?></span></div>
                                <span class="ticket-option-price"><?php echo $cat['price']>0?'₱'.number_format($cat['price'],2):'FREE'; ?></span>
                            </div>
                            <div class="ticket-option-meta"><span><?php echo $cat['slots_remaining']; ?> of <?php echo $cat['total_slots']; ?> remaining</span></div>
                            <div class="slot-progress"><div class="slot-bar <?php echo $sp>=80?'warning':''; ?>" style="width:<?php echo $sp; ?>%"></div></div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
                <div id="eligibility-warning" class="warning-box"></div>
                <div id="no-tickets-message" class="no-tickets-warning">
                    <strong>⚠️ No Available Tickets</strong><br>
                    There are no ticket categories available for your attendee type. Please select a different affiliation or contact the event organizer.
                </div>
            </div>
            
            <div class="form-actions">
                <a href="<?php echo $basePath; ?>/online-reg.php" class="btn-secondary">Cancel</a>
                <button type="submit" class="btn-primary" id="submitBtn">Complete Registration →</button>
            </div>
        </form>
        <?php else: ?>
            <div class="error-box"><strong>⚠️ No ticket categories available for this event.</strong> Please check back later.</div>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <footer class="user-footer">
        <p>University of Perpetual Help System DALTA — Molino Campus</p>
        <p style="font-size:11px;opacity:0.8;">© <?php echo date('Y'); ?> Event Management & Ticketing System</p>
    </footer>

    <?php if ($success && $ticketData): ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        new QRCode(document.getElementById("qrcode"),{text:"<?php echo h($ticketData['ticket_code']); ?>",width:160,height:160,colorDark:"#7e1416",colorLight:"#ffffff",correctLevel:QRCode.CorrectLevel.H});
        function copyCode(){var c=document.getElementById('ticketCode').textContent;navigator.clipboard.writeText(c).then(function(){var b=document.querySelector('.btn-copy');b.textContent='✅ Copied!';setTimeout(function(){b.textContent='📋 Copy Code';},2000);}).catch(function(){alert('Please copy manually.');});}
    </script>
    <?php endif; ?>

    <script>
        var allCategories = <?php echo json_encode($allCategories); ?>;
        
        // Store which categories each type has
        var categoriesByType = {};
        allCategories.forEach(function(cat) {
            if (!categoriesByType[cat.eligible_type]) categoriesByType[cat.eligible_type] = [];
            categoriesByType[cat.eligible_type].push(cat.category_id);
        });
        
        function selectAffiliation(type) {
            // Update radio button
            var radio = document.querySelector('input[name="attendee_type"][value="' + type + '"]');
            if (radio) radio.checked = true;
            
            // Update visual selection
            document.querySelectorAll('.affiliation-option').forEach(function(el) { el.classList.remove('selected'); });
            var option = document.querySelector('.affiliation-option[data-type="' + type + '"]');
            if (option) option.classList.add('selected');
            
            // Show/hide fields
            document.getElementById('student-fields').style.display = type === 'student' ? 'block' : 'none';
            document.getElementById('employee-fields').style.display = type === 'employee' ? 'block' : 'none';
            document.getElementById('alumni-fields').style.display = type === 'alumni' ? 'block' : 'none';
            document.getElementById('guest-fields').style.display = type === 'guest' ? 'block' : 'none';
            
            // Filter ticket options
            filterTicketOptions(type);
        }
        
        function filterTicketOptions(attendeeType) {
            var ticketOptions = document.querySelectorAll('.ticket-option');
            var warningBox = document.getElementById('eligibility-warning');
            var noTicketsMsg = document.getElementById('no-tickets-message');
            var submitBtn = document.getElementById('submitBtn');
            var visibleCount = 0;
            var firstVisible = null;
            
            // Uncheck all first
            document.querySelectorAll('input[name="category_id"]').forEach(function(r) { r.checked = false; });
            document.querySelectorAll('.ticket-option').forEach(function(o) { o.classList.remove('selected'); });
            
            ticketOptions.forEach(function(option) {
                var eligibleType = option.getAttribute('data-eligible');
                var isEligible = eligibleType === 'all' || eligibleType === attendeeType;
                
                if (isEligible) {
                    option.classList.remove('hidden', 'disabled');
                    option.style.display = 'block';
                    visibleCount++;
                    if (!firstVisible) firstVisible = option;
                } else {
                    option.classList.add('hidden');
                    option.style.display = 'none';
                }
            });
            
            // Auto-select first eligible
            if (firstVisible) {
                var radio = firstVisible.querySelector('input[type="radio"]');
                if (radio) {
                    radio.checked = true;
                    firstVisible.classList.add('selected');
                }
            }
            
            // Show/hide warnings
            if (visibleCount === 0) {
                noTicketsMsg.style.display = 'block';
                warningBox.style.display = 'none';
                submitBtn.disabled = true;
            } else {
                noTicketsMsg.style.display = 'none';
                warningBox.style.display = 'none';
                submitBtn.disabled = false;
            }
        }
        
        // Click handlers for ticket options
        document.querySelectorAll('.ticket-option').forEach(function(option) {
            option.addEventListener('click', function(e) {
                if (this.classList.contains('hidden') || this.classList.contains('disabled')) return;
                var radio = this.querySelector('input[type="radio"]');
                if (radio && e.target !== radio) {
                    radio.checked = true;
                    document.querySelectorAll('.ticket-option').forEach(function(o) { o.classList.remove('selected'); });
                    this.classList.add('selected');
                }
            });
        });
        
        // Form validation
        document.getElementById('regForm').addEventListener('submit', function(e) {
            var selectedTicket = document.querySelector('input[name="category_id"]:checked');
            if (!selectedTicket) {
                e.preventDefault();
                alert('Please select a ticket category before submitting.');
                return false;
            }
        });
        
        // Initialize on load
        document.addEventListener('DOMContentLoaded', function() {
            var initialType = '<?php echo $formValues['attendee_type'] ?? 'student'; ?>';
            selectAffiliation(initialType);
        });
    </script>
</body>
</html>