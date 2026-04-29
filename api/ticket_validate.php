<?php
require_once __DIR__ . '/../../includes/header.php';

$ticket_code = isset($_GET['code']) ? trim($_GET['code']) : '';
$validation_result = null;

if (!empty($ticket_code)) {
    $python_script = __DIR__ . '/../../python/validate_ticket.py';
    
    if (!file_exists($python_script)) {
        $validation_result = [
            'success' => false,
            'message' => 'Python validation script not found.'
        ];
    } else {
        $ticket_code_escaped = escapeshellarg($ticket_code);
        $command = "py \"$python_script\" $ticket_code_escaped 2>&1";
        $raw_output = shell_exec($command);
        
        // Cleann output - remove any lines that aren't JSON
        $lines = explode("\n", trim($raw_output));
        $json_line = '';
        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, '{') === 0 || strpos($line, '{"') === 0) {
                $json_line = $line;
                break;
            }
        }
        
        if (!empty($json_line)) {
            $validation_result = json_decode($json_line, true);
        }
        
        if ($validation_result === null) {
            $validation_result = [
                'success' => false,
                'message' => 'Error processing validation: Invalid response.',
                'debug_raw' => $raw_output
            ];
        }
    }
}

$recent_sql = "SELECT t.ticket_code, a.first_name, a.last_name, t.is_validated
               FROM Ticket t
               JOIN Attendee a ON t.attendee_id = a.attendee_id
               ORDER BY t.purchase_date DESC
               LIMIT 5";
$recent = mysqli_query($conn, $recent_sql);
?>

<div class="page active">
    <div class="page-header">
        <div>
            <div class="page-title">Ticket Validation</div>
            <div class="page-sub">Scan or enter ticket code to validate entry</div>
        </div>
    </div>

    <div style="max-width:600px">
        <div class="card">
            <div style="font-size:13px;font-weight:500;margin-bottom:12px">Enter ticket code</div>
            <form method="GET" style="display:flex;gap:8px">
                <input type="text" name="code" value="<?php echo h($ticket_code); ?>" placeholder="e.g. UUID ticket code..." style="flex:1" required>
                <button type="submit" class="btn btn-primary">Validate</button>
                <a href="/event-ticketing-v2/modules/tickets/validate.php" class="btn">Clear</a>
            </form>
            
            <?php if ($validation_result): ?>
                <?php if ($validation_result['success']): ?>
                    <div style="background:#EAF3DE;border:0.5px solid #639922;color:#27500A;padding:1rem;border-radius:6px;margin-top:16px">
                        <div style="font-weight:500;margin-bottom:6px">✓ <?php echo h($validation_result['message']); ?></div>
                        <div>
                            <?php if (isset($validation_result['ticket'])): ?>
                                <table style="font-size:13px;width:100%">
                                    <tr><td style="padding:2px 0;width:100px">Attendee</td><td><strong><?php echo h($validation_result['ticket']['attendee_name']); ?></strong></td></tr>
                                    <tr><td style="padding:2px 0">Type</td><td><?php echo h($validation_result['ticket']['attendee_type']); ?></td></tr>
                                    <tr><td style="padding:2px 0">Event</td><td><?php echo h($validation_result['ticket']['event_name']); ?></td></tr>
                                    <tr><td style="padding:2px 0">Category</td><td><?php echo h($validation_result['ticket']['category_name']); ?></td></tr>
                                    <tr><td style="padding:2px 0">Venue</td><td><?php echo h($validation_result['ticket']['venue_name']); ?></td></tr>
                                    <tr><td style="padding:2px 0">Validated at</td><td><?php echo h($validation_result['ticket']['validated_at']); ?></td></tr>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php elseif (isset($validation_result['already_validated']) && $validation_result['already_validated']): ?>
                    <div style="background:#FAEEDA;border:0.5px solid #BA7517;color:#633806;padding:1rem;border-radius:6px;margin-top:16px">
                        <div style="font-weight:500;margin-bottom:6px">⚠ Already Validated</div>
                        <div>
                            <p><?php echo h($validation_result['message']); ?></p>
                            <?php if (isset($validation_result['ticket'])): ?>
                                <table style="font-size:13px;width:100%;margin-top:10px">
                                    <tr><td style="padding:2px 0">Attendee</td><td><strong><?php echo h($validation_result['ticket']['attendee_name']); ?></strong></td></tr>
                                    <tr><td style="padding:2px 0">Event</td><td><?php echo h($validation_result['ticket']['event_name']); ?></td></tr>
                                    <tr><td style="padding:2px 0">Validated at</td><td><?php echo h($validation_result['validated_at']); ?></td></tr>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div style="background:#FCEBEB;border:0.5px solid #E24B4A;color:#791F1F;padding:1rem;border-radius:6px;margin-top:16px">
                        <div style="font-weight:500;margin-bottom:6px">✗ Validation Failed</div>
                        <div><?php echo h($validation_result['message']); ?></div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <div style="font-size:13px;font-weight:500;margin-bottom:12px">Recent Tickets (Quick Test)</div>
            <?php if ($recent && mysqli_num_rows($recent) > 0): ?>
                <?php while ($rec = mysqli_fetch_assoc($recent)): ?>
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:0.5px solid var(--color-border-tertiary)">
                        <div>
                            <span style="font-size:13px;font-weight:500"><?php echo h($rec['first_name'] . ' ' . $rec['last_name']); ?></span>
                            <br><code style="font-size:10px;color:var(--color-text-secondary)"><?php echo h($rec['ticket_code']); ?></code>
                        </div>
                        <div style="display:flex;gap:6px;align-items:center">
                            <span class="badge <?php echo $rec['is_validated'] ? 'b-green' : 'b-gray'; ?>">
                                <?php echo $rec['is_validated'] ? 'Validated' : 'Pending'; ?>
                            </span>
                            <?php if (!$rec['is_validated']): ?>
                                <a href="?code=<?php echo urlencode($rec['ticket_code']); ?>" class="btn btn-sm">Test</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="color:var(--color-text-secondary);font-size:13px">No tickets found.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>