<?php
require_once __DIR__ . '/../../includes/header.php';

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $ticket_id = (int)$_GET['delete'];
    
    mysqli_begin_transaction($conn);
    
    try {
        $ticket = getById($conn, 'Ticket', 'ticket_id', $ticket_id);
        if ($ticket) {
            mysqli_query($conn, "UPDATE Ticket_Category SET slots_remaining = slots_remaining + 1 WHERE category_id = {$ticket['category_id']}");
            mysqli_query($conn, "DELETE FROM Ticket WHERE ticket_id = $ticket_id");
            mysqli_commit($conn);
            $_SESSION['success'] = "Ticket deleted successfully.";
        }
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['error'] = "Error deleting ticket: " . $e->getMessage();
    }
    
    redirect('/event-ticketing-v2/modules/tickets/');
}

// Filter parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$payment_filter = isset($_GET['payment']) ? $_GET['payment'] : '';

$query = "SELECT t.*, a.first_name, a.last_name, a.attendee_type, 
          e.event_name, tc.category_name, tc.price
          FROM Ticket t
          JOIN Attendee a ON t.attendee_id = a.attendee_id
          JOIN Ticket_Category tc ON t.category_id = tc.category_id
          JOIN Event e ON tc.event_id = e.event_id
          WHERE 1=1";

if (!empty($search)) {
    $search_escaped = mysqli_real_escape_string($conn, $search);
    $query .= " AND (t.ticket_code LIKE '%$search_escaped%' OR CONCAT(a.first_name, ' ', a.last_name) LIKE '%$search_escaped%')";
}

if (!empty($payment_filter)) {
    $payment_escaped = mysqli_real_escape_string($conn, $payment_filter);
    $query .= " AND t.payment_status = '$payment_escaped'";
}

$query .= " ORDER BY t.purchase_date DESC";
$tickets = mysqli_query($conn, $query);

if (!$tickets) {
    $_SESSION['error'] = "Database error: " . mysqli_error($conn);
    $tickets = false;
}
?>

<div class="page active">
    <div class="page-header">
        <div>
            <div class="page-title">Ticket Generation</div>
            <div class="page-sub">Issue UUID-based tickets with QR codes</div>
        </div>
        <a href="/event-ticketing-v2/modules/tickets/generate.php" class="btn btn-primary">+ Generate Ticket</a>
    </div>

    <?php echo displayMessage(); ?>

    <div class="search-row">
        <form method="GET" id="filter-form" style="display:flex;gap:8px;width:100%">
            <input type="text" name="search" placeholder="Search code or attendee..." value="<?php echo htmlspecialchars($search); ?>" style="flex:1;max-width:280px">
            <select name="payment" id="payment-select" style="width:140px">
                <option value="">All Payment</option>
                <option value="free" <?php echo $payment_filter == 'free' ? 'selected' : ''; ?>>Free</option>
                <option value="paid" <?php echo $payment_filter == 'paid' ? 'selected' : ''; ?>>Paid</option>
                <option value="pending" <?php echo $payment_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
            </select>
            <button type="submit" class="btn">Filter</button>
            <a href="/event-ticketing-v2/modules/tickets/" class="btn">Reset</a>
        </form>
    </div>

    <div class="card-flush">
        <div class="tbl-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Ticket Code</th>
                        <th>Attendee</th>
                        <th>Event / Category</th>
                        <th>Payment</th>
                        <th>Validated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($tickets && mysqli_num_rows($tickets) > 0): ?>
                        <?php while ($ticket = mysqli_fetch_assoc($tickets)): ?>
                            <tr>
                                <td><code style="font-size:11px;word-break:break-all"><?php echo htmlspecialchars(substr($ticket['ticket_code'], 0, 30)); ?>...</code></td>
                                <td>
                                    <div style="font-weight:500"><?php echo htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']); ?></div>
                                    <div style="margin-top:2px">
                                        <span class="badge <?php echo getAttendeeTypeBadge($ticket['attendee_type']); ?>">
                                            <?php echo htmlspecialchars($ticket['attendee_type']); ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-size:12px"><?php echo htmlspecialchars($ticket['event_name']); ?></div>
                                    <div style="font-size:11px;color:var(--color-text-secondary)"><?php echo htmlspecialchars($ticket['category_name']); ?></div>
                                </td>
                                <td><span class="badge <?php echo getPaymentBadge($ticket['payment_status']); ?>"><?php echo ucfirst(htmlspecialchars($ticket['payment_status'])); ?></span></td>
                                <td>
                                    <?php if ($ticket['is_validated']): ?>
                                        <span class="badge b-green">Yes</span>
                                        <div style="font-size:10px;color:var(--color-text-secondary);margin-top:2px">
                                            <?php echo formatDateTime($ticket['validated_at']); ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="badge b-gray">No</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="actions" style="display:flex;gap:4px;flex-wrap:wrap;">
                                        <a href="/event-ticketing-v2/modules/tickets/view.php?id=<?php echo $ticket['ticket_id']; ?>" class="btn btn-sm">View</a>
                                        <a href="/event-ticketing-v2/modules/tickets/edit.php?id=<?php echo $ticket['ticket_id']; ?>" class="btn btn-sm">Edit</a>
                                        <?php if (!$ticket['is_validated']): ?>
                                            <a href="/event-ticketing-v2/modules/tickets/validate.php?code=<?php echo $ticket['ticket_code']; ?>" class="btn btn-sm btn-success">Validate</a>
                                        <?php endif; ?>
                                        <a href="/event-ticketing-2/modules/tickets/?delete=<?php echo $ticket['ticket_id']; ?>" 
                                           class="btn btn-sm btn-danger" 
                                           onclick="return confirm('Are you sure you want to delete this ticket? The slot will be restored.')">Del</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align:center;padding:2rem;color:var(--color-text-secondary)">No tickets found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>