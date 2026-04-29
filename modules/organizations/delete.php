<?php
require_once __DIR__ . '/../../includes/header.php';

$org_id = get('id');

if (!$org_id) {
    $_SESSION['error'] = "No organization specified.";
    redirect('/event-ticketing-v2/modules/organizations/');
}

// Check if organization has events
$check = mysqli_query($conn, "SELECT COUNT(*) as count FROM Event WHERE org_id = " . (int)$org_id);
$count = mysqli_fetch_assoc($check)['count'];

if ($count > 0) {
    $_SESSION['error'] = "Cannot delete organization: It has associated events.";
} else {
    $sql = "DELETE FROM Organization WHERE org_id = " . (int)$org_id;
    if (mysqli_query($conn, $sql)) {
        $_SESSION['success'] = "Organization deleted successfully.";
    } else {
        $_SESSION['error'] = "Error deleting organization: " . mysqli_error($conn);
    }
}

redirect('/event-ticketing-v2/modules/organizations/');
?>