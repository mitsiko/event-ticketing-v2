<?php
/**
 * Edit Ticket
 * Allows editing of ticket payment details
 * With strict payment status transition rules
 */
require_once __DIR__ . '/../../includes/header.php';

$ticket_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$ticket_id) {
    $_SESSION['error'] = "No ticket specified.";
    redirect('/event-ticketing-v2/modules/tickets/');
}

// Fetch ticket with all related data
$query = "
    SELECT t.*, 
           a.first_name, a.last_name, a.email, a.attendee_type,
           a.student_id, a.employee_id, a.alumni_id, a.guest_id,
           tc.category_name, tc.price, tc.eligible_type, tc.event_id,
           e.event_name, e.event_date,
           v.venue_name
    FROM Ticket t
    JOIN Attendee a ON t.attendee_id = a.attendee_id
    JOIN Ticket_Category tc ON t.category_id = tc.category_id
    JOIN Event e ON tc.event_id = e.event_id
    JOIN Venue v ON e.venue_id = v.venue_id
    WHERE t.ticket_id = $ticket_id
";
$result = mysqli_query($conn, $query);
$ticket = mysqli_fetch_assoc($result);

if (!$ticket) {
    $_SESSION['error'] = "Ticket not found.";
    redirect('/event-ticketing-v2/modules/tickets/');
}

// Get the attendee's other tickets for context
$attendeeTicketsQuery = "
    SELECT t.*, e.event_name, tc.category_name, tc.price
    FROM Ticket t
    JOIN Ticket_Category tc ON t.category_id = tc.category_id
    JOIN Event e ON tc.event_id = e.event_id
    WHERE t.attendee_id = {$ticket['attendee_id']}
    ORDER BY t.purchase_date DESC
";
$attendeeTickets = mysqli_query($conn, $attendeeTicketsQuery);

$errors = [];
$isFreeEvent = $ticket['price'] == 0;
$currentStatus = $ticket['payment_status'];
$updateSuccess = false;

if (isPost()) {
    $payment_status = trim($_POST['payment_status'] ?? $ticket['payment_status']);
    $payment_method = trim($_POST['payment_method'] ?? '');
    
    // === STRICT PAYMENT STATUS TRANSITION RULES ===
    $allowedTransitions = [];
    
    if ($isFreeEvent) {
        $allowedTransitions = ['free'];
        if ($payment_status !== 'free') {
            $errors[] = "This is a free event. Tickets can only have 'Free' payment status.";
        }
    } else {
        switch ($currentStatus) {
            case 'pending':
                $allowedTransitions = ['pending', 'paid'];
                if ($payment_status === 'free') {
                    $errors[] = "Cannot change a pending paid ticket to 'Free'. Mark it as 'Paid' once payment is received.";
                }
                if ($payment_status === 'refunded') {
                    $errors[] = "Cannot refund a ticket that hasn't been paid yet. Mark it as 'Paid' first.";
                }
                break;
                
            case 'paid':
                $allowedTransitions = ['paid', 'refunded'];
                if ($payment_status === 'free') {
                    $errors[] = "Cannot change a paid ticket to 'Free'. You can mark it as 'Refunded' if needed.";
                }
                if ($payment_status === 'pending') {
                    $errors[] = "Cannot revert a paid ticket back to 'Pending'.";
                }
                break;
                
            case 'refunded':
                $allowedTransitions = ['refunded'];
                if ($payment_status !== 'refunded') {
                    $errors[] = "Refunded tickets cannot be changed to another status.";
                }
                break;
                
            case 'free':
                $allowedTransitions = ['free'];
                if ($payment_status !== 'free') {
                    $errors[] = "Free tickets cannot be changed to a paid status.";
                }
                break;
        }
    }
    
    if (!in_array($payment_status, $allowedTransitions)) {
        $errors[] = "Invalid payment status transition.";
    }
    
    if ($payment_status === 'paid' && empty($payment_method) && !$isFreeEvent) {
        $errors[] = "Payment method is required for paid tickets.";
    }
    
    if (empty($errors)) {
        $payment_status_esc = mysqli_real_escape_string($conn, $payment_status);
        $payment_method_sql = !empty($payment_method) ? "'" . mysqli_real_escape_string($conn, $payment_method) . "'" : "NULL";
        
        $sql = "UPDATE Ticket SET payment_status = '$payment_status_esc', payment_method = $payment_method_sql WHERE ticket_id = $ticket_id";
        
        if (mysqli_query($conn, $sql)) {
            // Refresh ticket data IMMEDIATELY
            $result = mysqli_query($conn, $query);
            $ticket = mysqli_fetch_assoc($result);
            $currentStatus = $ticket['payment_status'];
            
            // Refresh attendee tickets
            $attendeeTickets = mysqli_query($conn, $attendeeTicketsQuery);
            
            $_SESSION['success'] = "Ticket updated successfully.";
            $updateSuccess = true;
        } else {
            $errors[] = "Error updating ticket: " . mysqli_error($conn);
        }
    }
}

$formValues = !empty($_POST) && !$updateSuccess ? $_POST : $ticket;

// Build allowed statuses for the dropdown
function getAllowedStatuses($isFreeEvent, $currentStatus) {
    if ($isFreeEvent) {
        return ['free' => 'Free'];
    }
    
    switch ($currentStatus) {
        case 'pending':
            return ['pending' => 'Pending', 'paid' => 'Paid'];
        case 'paid':
            return ['paid' => 'Paid', 'refunded' => 'Refunded'];
        case 'refunded':
            return ['refunded' => 'Refunded'];
        case 'free':
            return ['free' => 'Free'];
        default:
            return ['free' => 'Free', 'paid' => 'Paid', 'pending' => 'Pending', 'refunded' => 'Refunded'];
    }
}

$allowedStatuses = getAllowedStatuses($isFreeEvent, $currentStatus);

// Attendee detail display
$attendeeDetail = '';
if ($ticket['attendee_type'] === 'student') {
    $attendeeDetail = $ticket['student_id'] ? $ticket['student_id'] : 'N/A';
} elseif ($ticket['attendee_type'] === 'employee') {
    $attendeeDetail = $ticket['employee_id'] ? $ticket['employee_id'] : 'N/A';
} elseif ($ticket['attendee_type'] === 'alumni') {
    $attendeeDetail = $ticket['alumni_id'] ? $ticket['alumni_id'] : 'N/A';
} elseif ($ticket['attendee_type'] === 'guest') {
    $attendeeDetail = $ticket['guest_id'] ? $ticket['guest_id'] : 'N/A';
}
?>

<div class="page active">
    <div class="page-header">
        <div>
            <div class="page-title">Edit Ticket</div>
            <div class="page-sub">Update ticket payment details</div>
        </div>
        <div style="display:flex;gap:8px;">
            <a href="/event-ticketing-v2/modules/tickets/view.php?id=<?php echo $ticket_id; ?>" class="btn">View Ticket</a>
            <a href="/event-ticketing-v2/modules/tickets/" class="btn">← Back to Tickets</a>
        </div>
    </div>

    <?php echo displayMessage(); ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <ul style="margin:0;padding-left:20px;">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo h($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Payment Rules Info -->
    <div style="background:var(--color-info-bg);border:1px solid #bfdbfe;border-radius:var(--radius-sm);padding:12px 16px;margin-bottom:16px;">
        <div style="font-size:12px;font-weight:600;color:#1e40af;margin-bottom:4px;">Payment Status Rules</div>
        <ul style="margin:0;padding-left:18px;font-size:11px;color:#1e40af;">
            <?php if ($isFreeEvent): ?>
                <li>This is a <strong>free event</strong>. Only 'Free' status is allowed.</li>
            <?php else: ?>
                <?php if ($currentStatus === 'pending'): ?>
                    <li>Pending tickets can only be changed to <strong>Paid</strong>.</li>
                <?php elseif ($currentStatus === 'paid'): ?>
                    <li>Paid tickets can only be changed to <strong>Refunded</strong>.</li>
                <?php elseif ($currentStatus === 'refunded'): ?>
                    <li>Refunded tickets <strong>cannot</strong> be changed to another status.</li>
                <?php endif; ?>
            <?php endif; ?>
        </ul>
    </div>

    <div style="display:grid;grid-template-columns:1fr 380px;gap:20px;align-items:start;">

        <!-- Edit Form -->
        <div>
            <div class="card">
                <form method="POST">
                    <!-- Ticket Information (Read-only) -->
                    <div style="margin-bottom:24px;padding-bottom:16px;border-bottom:1px solid var(--color-border);">
                        <h3 style="font-size:14px;font-weight:700;color:var(--color-primary);margin:0 0 12px 0;text-transform:uppercase;letter-spacing:0.5px;">
                            Ticket Information
                        </h3>
                        
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                            <div>
                                <div style="font-size:11px;color:var(--color-text-secondary);font-weight:600;text-transform:uppercase;margin-bottom:2px;">Ticket Code</div>
                                <code style="font-size:11px;word-break:break-all;"><?php echo h($ticket['ticket_code']); ?></code>
                            </div>
                            <div>
                                <div style="font-size:11px;color:var(--color-text-secondary);font-weight:600;text-transform:uppercase;margin-bottom:2px;">Event</div>
                                <div style="font-size:13px;font-weight:500;"><?php echo h($ticket['event_name']); ?></div>
                            </div>
                            <div>
                                <div style="font-size:11px;color:var(--color-text-secondary);font-weight:600;text-transform:uppercase;margin-bottom:2px;">Category</div>
                                <div style="font-size:13px;"><?php echo h($ticket['category_name']); ?></div>
                            </div>
                            <div>
                                <div style="font-size:11px;color:var(--color-text-secondary);font-weight:600;text-transform:uppercase;margin-bottom:2px;">Price</div>
                                <div style="font-size:13px;font-weight:600;">
                                    <?php echo $isFreeEvent ? 'FREE' : '₱' . number_format($ticket['price'], 2); ?>
                                </div>
                            </div>
                            <div>
                                <div style="font-size:11px;color:var(--color-text-secondary);font-weight:600;text-transform:uppercase;margin-bottom:2px;">Current Status</div>
                                <div>
                                    <span class="badge <?php echo getPaymentBadge($currentStatus); ?>"><?php echo ucfirst($currentStatus); ?></span>
                                </div>
                            </div>
                            <div>
                                <div style="font-size:11px;color:var(--color-text-secondary);font-weight:600;text-transform:uppercase;margin-bottom:2px;">Validation</div>
                                <div style="font-size:13px;">
                                    <?php if ($ticket['is_validated']): ?>
                                        <span style="color:#166534;font-weight:600;">✓ Validated</span>
                                    <?php else: ?>
                                        <span style="color:var(--color-text-muted);">Not validated</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Editable Fields -->
                    <div style="margin-bottom:24px;">
                        <h3 style="font-size:14px;font-weight:700;color:var(--color-primary);margin:0 0 12px 0;text-transform:uppercase;letter-spacing:0.5px;">
                            Update Payment Status
                        </h3>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Payment Status *</label>
                                <select name="payment_status" id="payment_status" onchange="togglePaymentFields()" required>
                                    <?php foreach ($allowedStatuses as $value => $label): ?>
                                        <option value="<?php echo $value; ?>" <?php echo ($formValues['payment_status'] ?? '') === $value ? 'selected' : ''; ?>>
                                            <?php echo $label; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group" id="payment_method_group" style="<?php echo ($formValues['payment_status'] ?? '') === 'paid' && !$isFreeEvent ? '' : 'display:none;'; ?>">
                                <label class="form-label">Payment Method *</label>
                                <select name="payment_method" id="payment_method">
                                    <option value="">Select method</option>
                                    <option value="cash" <?php echo ($formValues['payment_method'] ?? '') === 'cash' ? 'selected' : ''; ?>>Cash</option>
                                    <option value="online" <?php echo ($formValues['payment_method'] ?? '') === 'online' ? 'selected' : ''; ?>>Online Payment</option>
                                    <option value="bank_transfer" <?php echo ($formValues['payment_method'] ?? '') === 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                                    <option value="gcash" <?php echo ($formValues['payment_method'] ?? '') === 'gcash' ? 'selected' : ''; ?>>GCash</option>
                                    <option value="maya" <?php echo ($formValues['payment_method'] ?? '') === 'maya' ? 'selected' : ''; ?>>Maya</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div style="display:flex;gap:8px;padding-top:16px;border-top:1px solid var(--color-border);">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                        <a href="/event-ticketing-v2/modules/tickets/" class="btn">Cancel</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Sidebar -->
        <div>
            <!-- Attendee Card -->
            <div class="card">
                <div style="font-size:12px;font-weight:700;color:var(--color-primary);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:12px;">
                    Attendee Information
                </div>
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
                    <div style="width:40px;height:40px;border-radius:50%;background:var(--color-primary);color:white;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:16px;">
                        <?php echo strtoupper(substr($ticket['first_name'], 0, 1)); ?>
                    </div>
                    <div>
                        <div style="font-weight:600;font-size:14px;"><?php echo h($ticket['first_name'] . ' ' . $ticket['last_name']); ?></div>
                        <div style="font-size:12px;color:var(--color-text-secondary);"><?php echo h($ticket['email']); ?></div>
                    </div>
                </div>
                <div style="display:flex;flex-direction:column;gap:6px;font-size:12px;">
                    <div style="display:flex;justify-content:space-between;">
                        <span style="color:var(--color-text-secondary);">Type:</span>
                        <span class="badge <?php echo getAttendeeTypeBadge($ticket['attendee_type']); ?>"><?php echo h($ticket['attendee_type']); ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;">
                        <span style="color:var(--color-text-secondary);">ID:</span>
                        <span style="font-weight:500;"><?php echo h($attendeeDetail); ?></span>
                    </div>
                </div>
            </div>

            <!-- Other Tickets -->
            <?php if ($attendeeTickets && mysqli_num_rows($attendeeTickets) > 1): ?>
            <div class="card">
                <div style="font-size:12px;font-weight:700;color:var(--color-primary);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:10px;">
                    Other Tickets (<?php echo mysqli_num_rows($attendeeTickets); ?>)
                </div>
                <?php 
                mysqli_data_seek($attendeeTickets, 0);
                while ($at = mysqli_fetch_assoc($attendeeTickets)): 
                ?>
                    <div style="padding:8px 0;border-bottom:1px solid var(--color-border-light);<?php echo $at['ticket_id'] == $ticket_id ? 'background:var(--color-accent-bg);margin:0 -12px;padding:8px 12px;border-radius:4px;' : ''; ?>">
                        <div style="font-size:12px;font-weight:500;<?php echo $at['ticket_id'] == $ticket_id ? 'color:var(--color-primary);' : ''; ?>">
                            <?php echo h($at['event_name']); ?>
                            <?php if ($at['ticket_id'] == $ticket_id): ?>
                                <span style="font-size:10px;color:var(--color-accent-dark);">(current)</span>
                            <?php endif; ?>
                        </div>
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:4px;">
                            <span style="font-size:11px;color:var(--color-text-secondary);">
                                <?php echo h($at['category_name']); ?>
                                <?php if ($at['price'] > 0): ?>
                                    · ₱<?php echo number_format($at['price'], 2); ?>
                                <?php endif; ?>
                            </span>
                            <span class="badge <?php echo getPaymentBadge($at['payment_status']); ?>" style="font-size:10px;">
                                <?php echo ucfirst($at['payment_status']); ?>
                            </span>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function togglePaymentFields() {
    var status = document.getElementById('payment_status').value;
    var methodGroup = document.getElementById('payment_method_group');
    var methodSelect = document.getElementById('payment_method');
    
    if (status === 'paid' && !<?php echo $isFreeEvent ? 'true' : 'false'; ?>) {
        methodGroup.style.display = 'block';
        methodSelect.required = true;
    } else {
        methodGroup.style.display = 'none';
        methodSelect.required = false;
        methodSelect.value = '';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    togglePaymentFields();
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>