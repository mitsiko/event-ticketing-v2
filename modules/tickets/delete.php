<?php
require_once __DIR__ . '/../../includes/header.php';

$ticket_id = get('id');

if (!$ticket_id) {
    $_SESSION['error'] = "No ticket specified.";
    redirect('/event-ticketing-v2/modules/tickets/');
}

mysqli_begin_transaction($conn);

try {
    // Get ticket info to restore slot
    $ticket = getById($conn, 'Ticket', 'ticket_id', $ticket_id);
    
    if (!$ticket) {
        throw new Exception("Ticket not found.");
    }
    
    // Restore slot to category
    $update = "UPDATE Ticket_Category SET slots_remaining = slots_remaining + 1 WHERE category_id = " . $ticket['category_id'];
    if (!mysqli_query($conn, $update)) {
        throw new Exception("Failed to restore ticket slot: " . mysqli_error($conn));
    }
    
    // Delete the ticket
    $delete = "DELETE FROM Ticket WHERE ticket_id = $ticket_id";
    if (!mysqli_query($conn, $delete)) {
        throw new Exception("Failed to delete ticket: " . mysqli_error($conn));
    }
    
    mysqli_commit($conn);
    $_SESSION['success'] = "Ticket deleted successfully. Slot has been restored.";
    
} catch (Exception $e) {
    mysqli_rollback($conn);
    $_SESSION['error'] = "Error: " . $e->getMessage();
}

redirect('/event-ticketing-v2/modules/tickets/');
?>