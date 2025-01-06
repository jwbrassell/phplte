<?php
// Disable error display
ini_set('display_errors', 0);
error_reporting(0);

// Set JSON content type for all responses
header('Content-Type: application/json');

/**
 * API endpoints for on-call calendar operations
 */

require_once __DIR__ . '/../config.php';  // Get $APP variable first
require_once __DIR__ . '/../includes/init.php';  // This will handle session start
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/OnCallCalendar.php';

// Ensure user is authenticated
if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Initialize calendar manager
$calendar = new OnCallCalendar();

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $endpoint = $_GET['endpoint'] ?? '';

    // Handle different endpoints
    switch ($endpoint) {
        case 'teams':
            handleTeams($method, $calendar);
            break;
            
        case 'events':
            handleEvents($method, $calendar);
            break;
            
        case 'current':
            handleCurrentOnCall($calendar);
            break;
            
        case 'holidays':
            handleHolidays($method, $calendar);
            break;
            
        case 'upload':
            handleUpload($calendar);
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
    }
    
} catch (Exception $e) {
    error_log("OnCall Calendar Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleTeams($method, $calendar) {
    // Check admin privileges for modifications
    if ($method !== 'GET' && !isAdmin()) {
        http_response_code(403);
        echo json_encode(['error' => 'Admin privileges required']);
        return;
    }
    
    switch ($method) {
        case 'GET':
            $result = $calendar->getTeams();
            echo json_encode($result);
            break;
            
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!isset($data['name'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Team name is required']);
                return;
            }
            $result = $calendar->addTeam($data['name'], $data['color'] ?? 'primary');
            echo json_encode($result);
            break;
            
        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!isset($data['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Team ID is required']);
                return;
            }
            $result = $calendar->updateTeam(
                $data['id'],
                $data['name'] ?? null,
                $data['color'] ?? null
            );
            echo json_encode($result);
            break;
            
        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!isset($data['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Team ID is required']);
                return;
            }
            $result = $calendar->deleteTeam($data['id']);
            echo json_encode($result);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

function handleEvents($method, $calendar) {
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }
    
    $start = $_GET['start'] ?? null;
    $end = $_GET['end'] ?? null;
    
    if (!$start || !$end) {
        http_response_code(400);
        echo json_encode(['error' => 'Start and end dates are required']);
        return;
    }
    
    $teamId = isset($_GET['team']) ? intval($_GET['team']) : null;
    $result = $calendar->getEvents($start, $end, $teamId);
    echo json_encode($result);
}

function handleCurrentOnCall($calendar) {
    $teamId = isset($_GET['team']) ? intval($_GET['team']) : null;
    $result = $calendar->getCurrentOnCall($teamId);
    echo json_encode($result);
}

function handleHolidays($method, $calendar) {
    if ($method === 'GET') {
        $year = isset($_GET['year']) ? intval($_GET['year']) : null;
        $result = $calendar->getHolidays($year);
        echo json_encode($result);
        return;
    }
    
    if ($method === 'POST' && isAdmin()) {
        if (!isset($_FILES['file'])) {
            http_response_code(400);
            echo json_encode(['error' => 'No file provided']);
            return;
        }
        
        $result = $calendar->uploadHolidays($_FILES['file']);
        echo json_encode($result);
        return;
    }
    
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}

function handleUpload($calendar) {
    if (!isAdmin()) {
        http_response_code(403);
        echo json_encode(['error' => 'Admin privileges required']);
        return;
    }
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }
    
    if (!isset($_FILES['file'])) {
        http_response_code(400);
        echo json_encode(['error' => 'No file provided']);
        return;
    }
    
    $teamId = $_POST['team'] ?? null;
    $year = isset($_POST['year']) ? intval($_POST['year']) : null;
    $scheduleType = $_POST['schedule_type'] ?? 'manual';
    
    if (!$teamId) {
        http_response_code(400);
        echo json_encode(['error' => 'Team ID is required']);
        return;
    }
    
    if (!in_array($scheduleType, ['manual', 'auto', 'auto_weekly'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid schedule type']);
        return;
    }
    
    $result = $calendar->uploadSchedule(
        $_FILES['file'], 
        $teamId, 
        $year, 
        $scheduleType !== 'manual',  // autoGenerate flag
        $scheduleType === 'auto_weekly' // weeklyRotation flag
    );
    echo json_encode($result);
}

function isAdmin() {
    global $APP;
    return isset($_SESSION[$APP."_adom_groups"]) && 
           in_array('admin', array_map('trim', explode(',', $_SESSION[$APP."_adom_groups"])));
}
