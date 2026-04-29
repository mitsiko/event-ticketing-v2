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

$categoriesQuery = "SELECT * FROM Ticket_Category WHERE event_id = $event_id AND slots_remaining > 0 ORDER BY price ASC";
$categories = mysqli_query($conn, $categoriesQuery);
$hasCategories = $categories && mysqli_num_rows($categories) > 0;

$errors = [];
$success = false;
$ticketData = null;

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
    
    if ($category_id <= 0) $errors[] = "Please select a ticket category.";
    
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
            $att = mysqli_fetch_assoc(mysqli_query($conn, "SELECT attendee_type FROM Attendee WHERE attendee_id = $attendee_id"));
            if ($cat['eligible_type'] !== 'all' && $cat['eligible_type'] !== $att['attendee_type']) throw new Exception("Not eligible for this ticket category.");
            
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

$formValues = $_POST;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — <?php echo h($event['event_name']); ?></title>
    <link rel="stylesheet" href="<?php echo $basePath; ?>/assets/css/user-reg.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="user-portal">

    <!-- ===== HEADER (Full Width) ===== -->
    <header class="user-header compact">
        <div class="header-content">
            <div class="header-brand">
                <a href="<?php echo $basePath; ?>/online-reg.php" class="back-link">← Back to Events</a>
                <span class="header-divider">|</span>
                <span class="header-event-name"><?php echo h($event['event_name']); ?></span>
            </div>
            <div class="header-actions">
                <a href="<?php echo $basePath; ?>/modules/tickets/validate.php" class="btn-outline-light btn-sm">🎟️ Validate</a>
            </div>
        </div>
    </header>

    <div class="registration-container">

        <?php if ($success && $ticketData): ?>
        <!-- ===== SUCCESS SCREEN ===== -->
        <div class="success-screen">
            <div class="success-icon">✅</div>
            <h2>Registration Successful!</h2>
            <p class="success-message">You have registered for <strong><?php echo h($ticketData['event_name']); ?></strong>. Save your ticket code below.</p>
            
            <div class="ticket-preview">
                <div class="ticket-preview-header">
                    <h3><?php echo h($ticketData['event_name']); ?></h3>
                    <p><?php echo date('F d, Y', strtotime($ticketData['event_date'])); ?> · <?php echo date('g:i A', strtotime($ticketData['start_time'])); ?> - <?php echo date('g:i A', strtotime($ticketData['end_time'])); ?></p>
                    <p><?php echo h($ticketData['venue_name']); ?></p>
                </div>
                <div class="ticket-preview-body">
                    <div class="ticket-info-row"><span class="ticket-label">Attendee</span><span class="ticket-value"><?php echo h($ticketData['first_name'].' '.$ticketData['last_name']); ?></span></div>
                    <div class="ticket-info-row"><span class="ticket-label">Type</span><span class="ticket-value"><?php echo ucfirst(h($ticketData['attendee_type'])); ?></span></div>
                    <div class="ticket-info-row"><span class="ticket-label">Category</span><span class="ticket-value"><?php echo h($ticketData['category_name']); ?></span></div>
                    <div class="ticket-info-row"><span class="ticket-label">Price</span><span class="ticket-value"><?php echo $ticketData['price']>0?'₱'.number_format($ticketData['price'],2):'FREE'; ?></span></div>
                    <div class="ticket-info-row"><span class="ticket-label">Status</span><span class="ticket-value"><?php echo $ticketData['is_validated']?'<span style="color:#166534;">✓ Validated</span>':'<span style="color:var(--user-text-muted);">Not Yet Validated</span>'; ?></span></div>
                    
                    <div class="ticket-code-display">
                        <p class="code-label">Your Ticket Code</p>
                        <code class="code-value" id="ticketCode"><?php echo h($ticketData['ticket_code']); ?></code>
                        <button class="btn-copy" onclick="copyCode()">📋 Copy Code</button>
                    </div>
                    
                    <div class="qr-code-container">
                        <div id="qrcode" style="display:inline-block;"></div>
                        <p class="qr-label">Present this QR code at the event entrance</p>
                    </div>
                </div>
            </div>
            
            <div class="success-actions">
                <button class="btn-primary" onclick="window.print()">🖨 Print Ticket</button>
                <a href="<?php echo $basePath; ?>/online-reg.php" class="btn-secondary">Register for Another Event</a>
            </div>
            
            <div class="success-note">
                <div class="note-item"><span class="note-icon">📧</span><p>Confirmation details shown above</p></div>
                <div class="note-item"><span class="note-icon">💡</span><p>Keep your ticket code safe. You'll need it to enter the event.</p></div>
                <div class="note-item"><span class="note-icon">⚠️</span><p>Each ticket code can only be validated once at the entrance.</p></div>
            </div>
        </div>

        <?php else: ?>
        <!-- ===== REGISTRATION FORM ===== -->
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
            <?php if (!empty($event['description'])): ?>
                <div class="event-description-box"><p><?php echo nl2br(h($event['description'])); ?></p></div>
            <?php endif; ?>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="error-box"><strong>⚠️ Please correct the following:</strong><ul><?php foreach($errors as $e): ?><li><?php echo h($e); ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <?php if (!$hasCategories && empty($errors)): ?>
            <div class="warning-box"><p>⚠️ No ticket categories are currently available for this event. Please check back later.</p></div>
        <?php endif; ?>

        <div class="progress-steps">
            <div class="step active"><div class="step-number">1</div><span>Personal Info</span></div>
            <div class="step-connector"></div>
            <div class="step"><div class="step-number">2</div><span>Ticket Selection</span></div>
            <div class="step-connector"></div>
            <div class="step"><div class="step-number">3</div><span>Confirmation</span></div>
        </div>

        <?php if ($hasCategories): ?>
        <form method="POST" class="registration-form" novalidate>
            <div class="form-section">
                <h3 class="section-title"><span class="section-number">1</span> Personal Information</h3>
                
                <div class="form-row">
                    <div class="form-group"><label class="form-label">First Name <span class="required">*</span></label><input type="text" name="first_name" value="<?php echo h($formValues['first_name']??''); ?>" required></div>
                    <div class="form-group"><label class="form-label">Last Name <span class="required">*</span></label><input type="text" name="last_name" value="<?php echo h($formValues['last_name']??''); ?>" required></div>
                </div>
                
                <div class="form-group"><label class="form-label">Email Address <span class="required">*</span></label><input type="email" name="email" value="<?php echo h($formValues['email']??''); ?>" required></div>
                
                <div class="form-row">
                    <div class="form-group"><label class="form-label">Phone Number</label><input type="tel" name="phone" value="<?php echo h($formValues['phone']??''); ?>"></div>
                    <div class="form-group"><label class="form-label">Birth Date <span class="required">*</span></label><input type="date" name="birth_date" value="<?php echo h($formValues['birth_date']??''); ?>" required></div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Gender <span class="required">*</span></label>
                    <select name="gender" required>
                        <option value="">Select gender</option>
                        <option value="male" <?php echo ($formValues['gender']??'')==='male'?'selected':''; ?>>Male</option>
                        <option value="female" <?php echo ($formValues['gender']??'')==='female'?'selected':''; ?>>Female</option>
                        <option value="non_binary" <?php echo ($formValues['gender']??'')==='non_binary'?'selected':''; ?>>Non-binary</option>
                        <option value="prefer_not_to_say" <?php echo ($formValues['gender']??'')==='prefer_not_to_say'?'selected':''; ?>>Prefer not to say</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">I am a... <span class="required">*</span></label>
                    <div class="affiliation-options">
                        <label class="affiliation-option <?php echo ($formValues['attendee_type']??'')==='student'?'selected':''; ?>"><input type="radio" name="attendee_type" value="student" <?php echo ($formValues['attendee_type']??'')==='student'?'checked':''; ?>><span class="affiliation-icon">🎓</span><span>Student</span></label>
                        <label class="affiliation-option <?php echo ($formValues['attendee_type']??'')==='employee'?'selected':''; ?>"><input type="radio" name="attendee_type" value="employee" <?php echo ($formValues['attendee_type']??'')==='employee'?'checked':''; ?>><span class="affiliation-icon">💼</span><span>Employee</span></label>
                        <label class="affiliation-option <?php echo ($formValues['attendee_type']??'')==='alumni'?'selected':''; ?>"><input type="radio" name="attendee_type" value="alumni" <?php echo ($formValues['attendee_type']??'')==='alumni'?'checked':''; ?>><span class="affiliation-icon">🏆</span><span>Alumni</span></label>
                        <label class="affiliation-option <?php echo ($formValues['attendee_type']??'')==='guest'?'selected':''; ?>"><input type="radio" name="attendee_type" value="guest" <?php echo ($formValues['attendee_type']??'')==='guest'?'checked':''; ?>><span class="affiliation-icon">🎫</span><span>Guest</span></label>
                    </div>
                </div>
                
                <div id="student-fields" class="affiliation-fields">
                    <div class="form-row"><div class="form-group"><label class="form-label">Student ID <span class="required">*</span></label><input type="text" name="student_id" value="<?php echo h($formValues['student_id']??''); ?>"></div><div class="form-group"><label class="form-label">Year Level <span class="required">*</span></label><select name="year_level"><option value="">Select</option><?php for($i=1;$i<=5;$i++): ?><option value="<?php echo $i; ?>" <?php echo ($formValues['year_level']??'')==$i?'selected':''; ?>><?php echo $i; ?><?php echo $i==1?'st':($i==2?'nd':($i==3?'rd':'th')); ?> Year</option><?php endfor; ?></select></div></div>
                    <div class="form-row"><div class="form-group"><label class="form-label">Program <span class="required">*</span></label><select name="program"><option value="">Select</option><option value="BSCS" <?php echo ($formValues['program']??'')==='BSCS'?'selected':''; ?>>BS Computer Science</option><option value="BSIT" <?php echo ($formValues['program']??'')==='BSIT'?'selected':''; ?>>BS Information Technology</option><option value="BSBA" <?php echo ($formValues['program']??'')==='BSBA'?'selected':''; ?>>BS Business Administration</option></select></div><div class="form-group"><label class="form-label">Department</label><input type="text" name="department" value="<?php echo h($formValues['department']??''); ?>"></div></div>
                </div>
                
                <div id="employee-fields" class="affiliation-fields">
                    <div class="form-row"><div class="form-group"><label class="form-label">Employee ID <span class="required">*</span></label><input type="text" name="employee_id" value="<?php echo h($formValues['employee_id']??''); ?>"></div><div class="form-group"><label class="form-label">Department <span class="required">*</span></label><input type="text" name="department" value="<?php echo h($formValues['department']??''); ?>"></div></div>
                    <div class="form-group"><label class="form-label">Job Title</label><input type="text" name="job_title" value="<?php echo h($formValues['job_title']??''); ?>"></div>
                </div>
                
                <div id="alumni-fields" class="affiliation-fields">
                    <div class="form-group"><label class="form-label">Graduation Year <span class="required">*</span></label><select name="graduation_year"><option value="">Select</option><?php for($y=date('Y');$y>=1960;$y--): ?><option value="<?php echo $y; ?>" <?php echo ($formValues['graduation_year']??'')==$y?'selected':''; ?>><?php echo $y; ?></option><?php endfor; ?></select></div>
                </div>
                
                <div id="guest-fields" class="affiliation-fields"><p class="affiliation-note">ℹ️ Guest registration is open to all visitors.</p></div>
            </div>
            
            <div class="form-section">
                <h3 class="section-title"><span class="section-number">2</span> Select Your Ticket</h3>
                <div class="ticket-options">
                    <?php mysqli_data_seek($categories,0); $ci=0; while($cat=mysqli_fetch_assoc($categories)): $sp=$cat['total_slots']>0?round((($cat['total_slots']-$cat['slots_remaining'])/$cat['total_slots'])*100):0; ?>
                    <label class="ticket-option <?php echo ($formValues['category_id']??'')==$cat['category_id']||($ci===0&&empty($formValues['category_id']))?'selected':''; ?>">
                        <input type="radio" name="category_id" value="<?php echo $cat['category_id']; ?>" data-eligible="<?php echo $cat['eligible_type']; ?>" <?php echo ($formValues['category_id']??'')==$cat['category_id']||($ci===0&&empty($formValues['category_id']))?'checked':''; ?>>
                        <div class="ticket-option-content">
                            <div class="ticket-option-header">
                                <div><span class="ticket-option-name"><?php echo h($cat['category_name']); ?></span><span class="ticket-option-eligible">For <?php echo $cat['eligible_type']==='all'?'Everyone':ucfirst($cat['eligible_type']).'s'; ?></span></div>
                                <span class="ticket-option-price"><?php echo $cat['price']>0?'₱'.number_format($cat['price'],2):'FREE'; ?></span>
                            </div>
                            <div class="ticket-option-meta"><span><?php echo $cat['slots_remaining']; ?> of <?php echo $cat['total_slots']; ?> remaining</span></div>
                            <div class="slot-progress"><div class="slot-bar <?php echo $sp>=80?'warning':''; ?>" style="width:<?php echo $sp; ?>%"></div></div>
                        </div>
                    </label>
                    <?php $ci++; endwhile; ?>
                </div>
                <div id="eligibility-warning" class="warning-box" style="display:none;"></div>
            </div>
            
            <div class="form-actions">
                <a href="<?php echo $basePath; ?>/online-reg.php" class="btn-secondary">Cancel</a>
                <button type="submit" class="btn-primary">Complete Registration →</button>
            </div>
        </form>
        <?php endif; ?>

        <?php endif; ?>
    </div>

    <footer class="user-footer">
        <p>University Event Management & Ticketing System</p>
        <p class="footer-sub">© <?php echo date('Y'); ?> University Events</p>
    </footer>

    <?php if ($success && $ticketData): ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        new QRCode(document.getElementById("qrcode"),{text:"<?php echo h($ticketData['ticket_code']); ?>",width:160,height:160,colorDark:"#7e1416",colorLight:"#ffffff",correctLevel:QRCode.CorrectLevel.H});
        function copyCode(){var c=document.getElementById('ticketCode').textContent;navigator.clipboard.writeText(c).then(function(){var b=document.querySelector('.btn-copy');b.textContent='✅ Copied!';setTimeout(function(){b.textContent='📋 Copy Code';},2000);}).catch(function(){alert('Please copy manually.');});}
    </script>
    <?php endif; ?>

    <script>
        document.addEventListener('DOMContentLoaded',function(){
            var radios=document.querySelectorAll('input[name="attendee_type"]');
            var fields={student:'student-fields',employee:'employee-fields',alumni:'alumni-fields',guest:'guest-fields'};
            function toggle(){
                var sel=document.querySelector('input[name="attendee_type"]:checked');
                var t=sel?sel.value:'';
                Object.values(fields).forEach(function(id){var el=document.getElementById(id);if(el)el.style.display='none';});
                document.querySelectorAll('.affiliation-option').forEach(function(el){el.classList.remove('selected');});
                if(t&&fields[t]){var el=document.getElementById(fields[t]);if(el)el.style.display='block';}
                if(sel){var p=sel.closest('.affiliation-option');if(p)p.classList.add('selected');}
                updateTickets(t);
            }
            function updateTickets(at){
                document.querySelectorAll('.ticket-option').forEach(function(opt){
                    var r=opt.querySelector('input[type="radio"]');if(!r)return;
                    var et=r.dataset.eligible;var ok=et==='all'||et===at||!at;
                    opt.style.opacity=ok||!at?'1':'0.45';opt.style.pointerEvents=ok||!at?'auto':'none';
                    if(!ok&&at&&r.checked){r.checked=false;opt.classList.remove('selected');}
                });
                var sel=document.querySelector('input[name="category_id"]:checked');
                if(!sel){var fe=document.querySelector('.ticket-option[style*="opacity: 1"] input[type="radio"]');if(fe){fe.checked=true;fe.closest('.ticket-option').classList.add('selected');}}
                var wb=document.getElementById('eligibility-warning');
                if(wb){var st=document.querySelector('input[name="category_id"]:checked');if(st&&at){var et=st.dataset.eligible;if(et!=='all'&&et!==at){wb.style.display='block';wb.innerHTML='⚠️ This ticket is only for <strong>'+et+'s</strong>.';}else{wb.style.display='none';}}else{wb.style.display='none';}}
            }
            radios.forEach(function(r){r.addEventListener('change',toggle);});
            document.querySelectorAll('.affiliation-option').forEach(function(o){o.addEventListener('click',function(e){var r=this.querySelector('input[type="radio"]');if(r&&e.target!==r){r.checked=true;r.dispatchEvent(new Event('change'));}});});
            document.querySelectorAll('.ticket-option').forEach(function(o){o.addEventListener('click',function(e){if(this.style.pointerEvents==='none')return;var r=this.querySelector('input[type="radio"]');if(r&&e.target!==r){r.checked=true;document.querySelectorAll('.ticket-option').forEach(function(x){x.classList.remove('selected');});this.classList.add('selected');var at=document.querySelector('input[name="attendee_type"]:checked');if(at)updateTickets(at.value);}});});
            toggle();
        });
    </script>
</body>
</html>