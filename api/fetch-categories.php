<?php
/**
 * AJAX Endpoint: Fetch available ticket categories for an event
 * Returns JSON response with category details
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once __DIR__ . '/../config/database.php';

$response = [
    'success' => false,
    'categories' => [],
    'message' => ''
];

$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
$attendee_type = isset($_GET['attendee_type']) ? trim($_GET['attendee_type']) : '';

if ($event_id <= 0) {
    $response['message'] = 'Event ID is required.';
    echo json_encode($response);
    exit;
}

// Fetch categories with availability
$query = "
    SELECT 
        category_id,
        category_name,
        eligible_type,
        price,
        total_slots,
        slots_remaining,
        (total_slots - slots_remaining) as slots_sold,
        CASE 
            WHEN slots_remaining <= 5 THEN 'urgent'
            WHEN (total_slots - slots_remaining) / total_slots >= 0.8 THEN 'warning'
            ELSE 'available'
        END as availability_status
    FROM Ticket_Category 
    WHERE event_id = $event_id 
    AND slots_remaining > 0
    ORDER BY price ASC
";

$result = mysqli_query($conn, $query);

if (!$result) {
    $response['message'] = 'Database error: ' . mysqli_error($conn);
    echo json_encode($response);
    exit;
}

$categories = [];
while ($row = mysqli_fetch_assoc($result)) {
    // Determine eligibility
    $isEligible = true;
    $eligibilityNote = '';
    
    if (!empty($attendee_type) && $row['eligible_type'] !== 'all' && $row['eligible_type'] !== $attendee_type) {
        $isEligible = false;
        $eligibilityNote = 'Only available for ' . $row['eligible_type'] . 's';
    }
    
    $categories[] = [
        'category_id' => $row['category_id'],
        'category_name' => $row['category_name'],
        'eligible_type' => $row['eligible_type'],
        'price' => (float)$row['price'],
        'price_display' => $row['price'] > 0 ? '₱' . number_format($row['price'], 2) : 'FREE',
        'total_slots' => (int)$row['total_slots'],
        'slots_remaining' => (int)$row['slots_remaining'],
        'slots_sold' => (int)$row['slots_sold'],
        'fill_percentage' => $row['total_slots'] > 0 ? round(($row['slots_sold'] / $row['total_slots']) * 100) : 0,
        'availability_status' => $row['availability_status'],
        'is_eligible' => $isEligible,
        'eligibility_note' => $eligibilityNote
    ];
}

$response['success'] = true;
$response['categories'] = $categories;
$response['count'] = count($categories);

if (empty($categories)) {
    $response['message'] = 'No ticket categories currently available for this event.';
} else {
    $response['message'] = count($categories) . ' categories available.';
}

echo json_encode($response);