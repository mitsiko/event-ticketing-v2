<?php
require_once __DIR__ . '/../../includes/header.php';

$category_id = get('id');

if (!$category_id) {
    $_SESSION['error'] = "No category specified.";
    redirect('/event-ticketing-v2/modules/categories/');
}

// Check if category has tickets
$check = mysqli_query($conn, "SELECT COUNT(*) as count FROM Ticket WHERE category_id = " . (int)$category_id);
$count = mysqli_fetch_assoc($check)['count'];

if ($count > 0) {
    $_SESSION['error'] = "Cannot delete category: It has associated tickets.";
} else {
    $sql = "DELETE FROM Ticket_Category WHERE category_id = " . (int)$category_id;
    if (mysqli_query($conn, $sql)) {
        $_SESSION['success'] = "Category deleted successfully.";
    } else {
        $_SESSION['error'] = "Error deleting category: " . mysqli_error($conn);
    }
}

// Preserve filter if any
$event_filter = get('event_id');
$redirect = '/event-ticketing-v2/modules/categories/';
if ($event_filter) {
    $redirect .= '?event_id=' . (int)$event_filter;
}

redirect($redirect);
?>