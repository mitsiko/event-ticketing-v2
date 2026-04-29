<?php
/**
 * AJAX Endpoint: Check if email is already registered
 * Returns JSON response
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once __DIR__ . '/../config/database.php';

$response = [
    'success' => false,
    'exists' => false,
    'message' => ''
];

$email = isset($_GET['email']) ? trim($_GET['email']) : '';

if (empty($email)) {
    $response['message'] = 'Email is required.';
    echo json_encode($response);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $response['message'] = 'Invalid email format.';
    echo json_encode($response);
    exit;
}

$emailEsc = mysqli_real_escape_string($conn, $email);
$result = mysqli_query($conn, "SELECT attendee_id, first_name, last_name, attendee_type FROM Attendee WHERE email = '$emailEsc'");

if ($result && mysqli_num_rows($result) > 0) {
    $attendee = mysqli_fetch_assoc($result);
    $response['success'] = true;
    $response['exists'] = true;
    $response['data'] = [
        'id' => $attendee['attendee_id'],
        'first_name' => $attendee['first_name'],
        'last_name' => $attendee['last_name'],
        'attendee_type' => $attendee['attendee_type']
    ];
    $response['message'] = 'Email found. Welcome back, ' . $attendee['first_name'] . '!';
} else {
    $response['success'] = true;
    $response['exists'] = false;
    $response['message'] = 'New email. You will be registered as a new attendee.';
}

echo json_encode($response);