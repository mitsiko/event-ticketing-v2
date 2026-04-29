<?php
/**
 * Helper Functions
 * Event Management & Ticketing System
 */

function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

function executeQuery($conn, $sql) {
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        die("Query failed: " . mysqli_error($conn));
    }
    return $result;
}

function escapeString($conn, $str) {
    if ($str === null) {
        return '';
    }
    return mysqli_real_escape_string($conn, $str);
}

function getById($conn, $table, $idColumn, $id) {
    $id = (int)$id;
    $sql = "SELECT * FROM $table WHERE $idColumn = $id";
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        return null;
    }
    return mysqli_fetch_assoc($result);
}

function recordExists($conn, $table, $column, $value, $excludeId = null, $idColumn = null) {
    $value = mysqli_real_escape_string($conn, $value);
    $sql = "SELECT COUNT(*) as count FROM $table WHERE $column = '$value'";
    if ($excludeId !== null && $idColumn !== null) {
        $excludeId = (int)$excludeId;
        $sql .= " AND $idColumn != $excludeId";
    }
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    return $row['count'] > 0;
}

/**
 * Get report from Python using 'py' command
 */
function getPythonReport($report_type, $event_id = null) {
    $python_script = __DIR__ . '/../python/generate_reports.py';
    $command = "py \"$python_script\" $report_type";
    if ($event_id) {
        $command .= " " . escapeshellarg($event_id);
    }
    $output = shell_exec($command . " 2>&1");
    return json_decode($output, true);
}

function getEventTypeBadge($type) {
    $badges = [
        'academic' => 'b-blue', 'cultural' => 'b-green', 'sports' => 'b-amber',
        'concert' => 'b-purple', 'seminar' => 'b-teal', 'graduation' => 'b-blue',
        'orientation' => 'b-green', 'other' => 'b-gray'
    ];
    return $badges[$type] ?? 'b-gray';
}

function getAudienceBadge($type) {
    $badges = [
        'open_to_all' => 'b-teal', 'student_only' => 'b-blue',
        'employee_only' => 'b-amber', 'alumni_only' => 'b-purple'
    ];
    return $badges[$type] ?? 'b-gray';
}

function getStatusBadge($status) {
    $badges = [
        'upcoming' => 'b-blue', 'ongoing' => 'b-green',
        'completed' => 'b-gray', 'cancelled' => 'b-red'
    ];
    return $badges[$status] ?? 'b-gray';
}

function getAttendeeTypeBadge($type) {
    $badges = [
        'student' => 'b-blue', 'employee' => 'b-amber',
        'alumni' => 'b-purple', 'guest' => 'b-gray', 'all' => 'b-teal'
    ];
    return $badges[$type] ?? 'b-gray';
}

function getPaymentBadge($status) {
    $badges = [
        'free' => 'b-green', 'paid' => 'b-blue',
        'pending' => 'b-amber', 'refunded' => 'b-red'
    ];
    return $badges[$status] ?? 'b-gray';
}

function formatDate($date) {
    if (empty($date)) return 'N/A';
    return date('M d, Y', strtotime($date));
}

function formatTime($time) {
    if (empty($time)) return 'N/A';
    return date('h:i A', strtotime($time));
}

function formatDateTime($datetime) {
    if (empty($datetime)) return 'N/A';
    return date('M d, Y h:i A', strtotime($datetime));
}

function h($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function getVenueName($conn, $venue_id) {
    $venue = getById($conn, 'Venue', 'venue_id', $venue_id);
    return $venue ? $venue['venue_name'] : 'N/A';
}

function getOrgName($conn, $org_id) {
    $org = getById($conn, 'Organization', 'org_id', $org_id);
    return $org ? $org['org_name'] : 'N/A';
}

function getEventName($conn, $event_id) {
    $event = getById($conn, 'Event', 'event_id', $event_id);
    return $event ? $event['event_name'] : 'N/A';
}

function getCategoryName($conn, $category_id) {
    $category = getById($conn, 'Ticket_Category', 'category_id', $category_id);
    return $category ? $category['category_name'] : 'N/A';
}

function getAttendeeName($conn, $attendee_id) {
    $attendee = getById($conn, 'Attendee', 'attendee_id', $attendee_id);
    return $attendee ? $attendee['first_name'] . ' ' . $attendee['last_name'] : 'N/A';
}

function displayMessage() {
    $output = '';
    if (isset($_SESSION['success'])) {
        $output .= '<div class="alert alert-success" style="background:#EAF3DE;border:0.5px solid #639922;color:#27500A;padding:12px 16px;border-radius:6px;margin-bottom:16px">';
        $output .= '✓ ' . h($_SESSION['success']) . '</div>';
        unset($_SESSION['success']);
    }
    if (isset($_SESSION['error'])) {
        $output .= '<div class="alert alert-error" style="background:#FCEBEB;border:0.5px solid #E24B4A;color:#791F1F;padding:12px 16px;border-radius:6px;margin-bottom:16px">';
        $output .= '✗ ' . h($_SESSION['error']) . '</div>';
        unset($_SESSION['error']);
    }
    return $output;
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function isPost() {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function post($key, $default = '') {
    return isset($_POST[$key]) ? trim($_POST[$key]) : $default;
}

function get($key, $default = '') {
    return isset($_GET[$key]) ? trim($_GET[$key]) : $default;
}


function getBackUrl($defaultUrl) {
    if (isset($_SERVER['HTTP_REFERER'])) {
        $referrer = $_SERVER['HTTP_REFERER'];
        $currentHost = $_SERVER['HTTP_HOST'];
        
        // Only use referrer if it's from the same site
        if (strpos($referrer, $currentHost) !== false) {
            // Don't go back to create/edit pages (avoid loops)
            if (strpos($referrer, 'create.php') === false && 
                strpos($referrer, 'edit.php') === false &&
                strpos($referrer, 'delete.php') === false) {
                return $referrer;
            }
        }
    }
    return $defaultUrl;
}



?>

