<?php
require_once __DIR__ . '/../../includes/header.php';

$event_id = get('id');

if (!$event_id) {
    $_SESSION['error'] = "No event specified.";
    redirect('/event-ticketing-v2/modules/events/');
}

// Check if event has ticket categories
$check = mysqli_query($conn, "SELECT COUNT(*) as count FROM Ticket_Category WHERE event_id = " . (int)$event_id);
$count = mysqli_fetch_assoc($check)['count'];

if ($count > 0) {
    $_SESSION['error'] = "Cannot delete event: It has associated ticket categories. Delete categories first.";
} else {
    $sql = "DELETE FROM Event WHERE event_id = " . (int)$event_id;
    if (mysqli_query($conn, $sql)) {
        $_SESSION['success'] = "Event deleted successfully.";
    } else {
        $_SESSION['error'] = "Error deleting event: " . mysqli_error($conn);
    }
}

redirect('/event-ticketing-v2/modules/events/');
?>