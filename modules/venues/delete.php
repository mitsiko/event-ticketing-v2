<?php
require_once __DIR__ . '/../../includes/header.php';

$venue_id = get('id');

if (!$venue_id) {
    $_SESSION['error'] = "No venue specified.";
    redirect('/event-ticketing-v2/modules/venues/');
}

// Check if venue has events
$check = mysqli_query($conn, "SELECT COUNT(*) as count FROM Event WHERE venue_id = " . (int)$venue_id);
$count = mysqli_fetch_assoc($check)['count'];

if ($count > 0) {
    $_SESSION['error'] = "Cannot delete venue: It has associated events.";
} else {
    $sql = "DELETE FROM Venue WHERE venue_id = " . (int)$venue_id;
    if (mysqli_query($conn, $sql)) {
        $_SESSION['success'] = "Venue deleted successfully.";
    } else {
        $_SESSION['error'] = "Error deleting venue: " . mysqli_error($conn);
    }
}

redirect('/event-ticketing-v2/modules/venues/');
?>