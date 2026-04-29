<?php
require_once __DIR__ . '/../../includes/header.php';

$events = mysqli_query($conn, "
    SELECT DISTINCT e.event_id, e.event_name 
    FROM Event e
    JOIN Ticket_Category tc ON e.event_id = tc.event_id
    WHERE e.requires_ticket = 1 
    AND e.status != 'cancelled'
    AND tc.slots_remaining > 0
    ORDER BY e.event_date DESC
");

$attendees = mysqli_query($conn, "SELECT attendee_id, first_name, last_name, attendee_type FROM Attendee ORDER BY first_name");

if (isPost()) {
    $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
    $attendee_id = isset($_POST['attendee_id']) ? (int)$_POST['attendee_id'] : 0;
    
    $errors = [];
    
    if ($category_id <= 0) $errors[] = "Please select a ticket category.";
    if ($attendee_id <= 0) $errors[] = "Please select an attendee.";
    
    if (empty($errors)) {
        $cat_result = mysqli_query($conn, "SELECT * FROM Ticket_Category WHERE category_id = $category_id");
        $cat = mysqli_fetch_assoc($cat_result);
        
        $att_result = mysqli_query($conn, "SELECT * FROM Attendee WHERE attendee_id = $attendee_id");
        $att = mysqli_fetch_assoc($att_result);
        
        if ($cat['eligible_type'] != 'all' && $cat['eligible_type'] != $att['attendee_type']) {
            $errors[] = "Attendee type '{$att['attendee_type']}' is not eligible for this ticket category.";
        }
        
        if ($cat['slots_remaining'] <= 0) {
            $errors[] = "No slots remaining for this ticket category.";
        }
        
        $check = mysqli_query($conn, "SELECT ticket_id FROM Ticket WHERE category_id = $category_id AND attendee_id = $attendee_id");
        if (mysqli_num_rows($check) > 0) {
            $errors[] = "This attendee already has a ticket for this category.";
        }
        
        if (empty($errors)) {
            mysqli_begin_transaction($conn);
            
            try {
                // CALL PYTHON FOR UUID GENERATION USING 'py' COMMAND
                $python_script = __DIR__ . '/../../python/generate_uuid.py';
                $ticket_code = trim(shell_exec("py \"$python_script\" 2>&1"));
                
                if (empty($ticket_code) || strpos($ticket_code, 'ERROR') !== false) {
                    throw new Exception("Failed to generate ticket UUID: " . $ticket_code);
                }
                
                $payment_status = mysqli_real_escape_string($conn, $_POST['payment_status'] ?? ($cat['price'] > 0 ? 'paid' : 'free'));
                $payment_method = isset($_POST['payment_method']) ? mysqli_real_escape_string($conn, $_POST['payment_method']) : null;
                
                $ticket_code_escaped = mysqli_real_escape_string($conn, $ticket_code);
                
                $sql = "INSERT INTO Ticket (ticket_code, payment_status, payment_method, category_id, attendee_id) 
                        VALUES ('$ticket_code_escaped', '$payment_status', " . ($payment_method ? "'$payment_method'" : "NULL") . ", $category_id, $attendee_id)";
                mysqli_query($conn, $sql);
                $ticket_id = mysqli_insert_id($conn);
                
                mysqli_query($conn, "UPDATE Ticket_Category SET slots_remaining = slots_remaining - 1 WHERE category_id = $category_id");
                
                mysqli_commit($conn);
                
                $_SESSION['success'] = "Ticket generated successfully!";
                redirect('/event-ticketing-v2/modules/tickets/view.php?id=' . $ticket_id);
                
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $errors[] = "Error generating ticket: " . $e->getMessage();
            }
        }
    }
}

$form_values = $_POST;

$catQuery = mysqli_query($conn, "SELECT tc.*, e.event_id FROM Ticket_Category tc JOIN Event e ON tc.event_id = e.event_id WHERE tc.slots_remaining > 0");
$categoriesData = [];
while ($cat = mysqli_fetch_assoc($catQuery)) {
    $categoriesData[$cat['category_id']] = $cat;
}
?>

<div class="page active">
    <div class="page-header">
        <div>
            <div class="page-title">Generate Ticket</div>
            <div class="page-sub">Issue a new UUID-based ticket</div>
        </div>
        <a href="/event-ticketing-v2/modules/tickets/" class="btn">← Back to Tickets</a>
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
        <form method="POST" id="ticketForm">
            <div class="form-group">
                <label class="form-label">Event *</label>
                <select id="event_select" onchange="loadCategories()" required>
                    <option value="">-- Select event --</option>
                    <?php while ($event = mysqli_fetch_assoc($events)): ?>
                        <option value="<?php echo $event['event_id']; ?>"><?php echo h($event['event_name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Ticket category *</label>
                <select name="category_id" id="category_select" onchange="checkEligibility()" required>
                    <option value="">-- Select category --</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Attendee *</label>
                <select name="attendee_id" id="attendee_select" onchange="checkEligibility()" required>
                    <option value="">-- Select attendee --</option>
                    <?php while ($att = mysqli_fetch_assoc($attendees)): ?>
                        <option value="<?php echo $att['attendee_id']; ?>" data-type="<?php echo $att['attendee_type']; ?>">
                            <?php echo h($att['first_name'] . ' ' . $att['last_name'] . ' (' . $att['attendee_type'] . ')'); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Payment status *</label>
                <select name="payment_status" id="payment_status" onchange="togglePaymentMethod()" required>
                    <option value="">-- Select status --</option>
                    <option value="paid">Paid</option>
                    <option value="pending">Pending</option>
                    <option value="free">Free</option>
                    <option value="refunded">Refunded</option>
                </select>
            </div>
            <div class="form-group" id="payment_method_group" style="display:none">
                <label class="form-label">Payment method</label>
                <select name="payment_method" id="payment_method">
                    <option value="">-- Select method --</option>
                    <option value="cash">Cash</option>
                    <option value="online">Online Payment</option>
                </select>
            </div>
            <div id="eligibility_info" class="info-msg"></div>
            <div style="display:flex;gap:8px;margin-top:16px">
                <button type="submit" class="btn btn-primary">Generate & Issue Ticket</button>
                <a href="/event-ticketing-v2/modules/tickets/" class="btn">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
var categoriesData = <?php echo json_encode($categoriesData); ?>;

function loadCategories() {
    var eventId = document.getElementById('event_select').value;
    var catSelect = document.getElementById('category_select');
    catSelect.innerHTML = '<option value="">-- Select category --</option>';
    
    if (eventId) {
        for (var id in categoriesData) {
            var cat = categoriesData[id];
            if (cat.event_id == eventId) {
                var price = parseFloat(cat.price).toFixed(2);
                var option = document.createElement('option');
                option.value = id;
                option.textContent = cat.category_name + ' — ' + (cat.price > 0 ? '₱' + price : 'Free') + ' (' + cat.slots_remaining + ' left)';
                option.dataset.eligible = cat.eligible_type;
                catSelect.appendChild(option);
            }
        }
    }
    checkEligibility();
}

function checkEligibility() {
    var catSelect = document.getElementById('category_select');
    var attSelect = document.getElementById('attendee_select');
    var infoDiv = document.getElementById('eligibility_info');
    
    if (!catSelect.value || !attSelect.value) {
        infoDiv.innerHTML = '';
        return;
    }
    
    var selectedOption = catSelect.selectedOptions[0];
    var eligibleType = selectedOption.dataset.eligible;
    var attOption = attSelect.selectedOptions[0];
    var attendeeType = attOption.dataset.type;
    
    if (eligibleType === 'all' || eligibleType === attendeeType) {
        infoDiv.innerHTML = '<span style="color:#27500A">✓ Eligible for this category.</span>';
    } else {
        infoDiv.innerHTML = '<span style="color:#791F1F">⚠ Eligibility mismatch: category is for ' + eligibleType + 's, but this attendee is a ' + attendeeType + '.</span>';
    }
}

function togglePaymentMethod() {
    var paymentStatus = document.getElementById('payment_status').value;
    var methodGroup = document.getElementById('payment_method_group');
    var methodSelect = document.getElementById('payment_method');
    
    if (paymentStatus === 'paid') {
        methodGroup.style.display = 'block';
        methodSelect.required = true;
    } else {
        methodGroup.style.display = 'none';
        methodSelect.required = false;
        methodSelect.value = '';
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>