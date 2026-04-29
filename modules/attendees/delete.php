<?php
require_once __DIR__ . '/../../includes/header.php';

$attendee_id = get('id');

if (!$attendee_id) {
    $_SESSION['error'] = "No attendee specified.";
    redirect('/event-ticketing-v2/modules/attendees/');
}

// Check if attendee has tickets
$check = mysqli_query($conn, "SELECT COUNT(*) as count FROM Ticket WHERE attendee_id = " . (int)$attendee_id);
$count = mysqli_fetch_assoc($check)['count'];

if ($count > 0) {
    $_SESSION['error'] = "Cannot delete attendee: They have associated tickets.";
} else {
    $sql = "DELETE FROM Attendee WHERE attendee_id = " . (int)$attendee_id;
    if (mysqli_query($conn, $sql)) {
        $_SESSION['success'] = "Attendee deleted successfully.";
    } else {
        $_SESSION['error'] = "Error deleting attendee: " . mysqli_error($conn);
    }
}

redirect('/event-ticketing-v2/modules/attendees/');
?>