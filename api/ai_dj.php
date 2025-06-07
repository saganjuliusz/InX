<?php
require_once 'config.php';
require_once 'auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die(json_encode(['error' => 'Connection failed: ' . $conn->connect_error]));
}

// Verify JWT token and get user_id
$user_id = verify_token();
if (!$user_id) {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized']));
}

switch ($_SERVER['REQUEST_METHOD']) {
    case 'POST':
        // Start new AI DJ session
        if ($_GET['action'] === 'start_session') {
            $data = json_decode(file_get_contents('php://input'), true);
            $context = json_encode($data['context'] ?? []);
            
            if ($stmt = $conn->prepare("CALL start_ai_dj_session(?, ?)")) {
                $stmt->bind_param("is", $user_id, $context);
                $stmt->execute();
                $result = $stmt->get_result();
                $session = $result->fetch_assoc();
                echo json_encode(['session_id' => $session['session_id']]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to start session']);
            }
        }
        // Add track to queue
        else if ($_GET['action'] === 'queue_track') {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if ($stmt = $conn->prepare("CALL queue_ai_dj_track(?, ?, ?, ?)")) {
                $stmt->bind_param("iisd", 
                    $data['session_id'],
                    $data['track_id'],
                    $data['confidence'],
                    $data['reason']
                );
                $stmt->execute();
                echo json_encode(['success' => true]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to queue track']);
            }
        }
        // Process voice command
        else if ($_GET['action'] === 'voice_command') {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if ($stmt = $conn->prepare("
                INSERT INTO voice_commands (
                    user_id, session_id, command_text, command_type
                ) VALUES (?, ?, ?, ?)
            ")) {
                $stmt->bind_param("iiss", 
                    $user_id,
                    $data['session_id'],
                    $data['command'],
                    $data['type']
                );
                $stmt->execute();
                
                // Here you would integrate with your AI service to process the command
                // For now we'll just return a mock response
                echo json_encode([
                    'command_id' => $conn->insert_id,
                    'response' => 'Command received and being processed'
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to process command']);
            }
        }
        break;

    case 'GET':
        // Get session details
        if (isset($_GET['session_id'])) {
            $session_id = (int)$_GET['session_id'];
            
            // Verify user owns this session
            if ($stmt = $conn->prepare("
                SELECT * FROM ai_dj_sessions 
                WHERE session_id = ? AND user_id = ?
            ")) {
                $stmt->bind_param("ii", $session_id, $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($session = $result->fetch_assoc()) {
                    // Get current queue
                    $queue_stmt = $conn->prepare("
                        SELECT 
                            q.*,
                            t.title as track_title,
                            t.duration,
                            a.name as artist_name
                        FROM ai_dj_queue q
                        JOIN tracks t ON q.track_id = t.track_id
                        JOIN artists a ON t.artist_id = a.artist_id
                        WHERE q.session_id = ?
                        ORDER BY q.position
                    ");
                    $queue_stmt->bind_param("i", $session_id);
                    $queue_stmt->execute();
                    $queue = $queue_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    
                    $session['queue'] = $queue;
                    echo json_encode($session);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Session not found']);
                }
            }
        }
        // List user's sessions
        else {
            if ($stmt = $conn->prepare("
                SELECT * FROM ai_dj_sessions 
                WHERE user_id = ?
                ORDER BY start_time DESC
                LIMIT 10
            ")) {
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                echo json_encode($result->fetch_all(MYSQLI_ASSOC));
            }
        }
        break;

    case 'PUT':
        if (isset($_GET['session_id'])) {
            $session_id = (int)$_GET['session_id'];
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Update feedback for a queued track
            if ($_GET['action'] === 'feedback') {
                if ($stmt = $conn->prepare("
                    UPDATE ai_dj_queue 
                    SET user_feedback = ?
                    WHERE queue_id = ? AND session_id = ?
                ")) {
                    $stmt->bind_param("sii", 
                        $data['feedback'],
                        $data['queue_id'],
                        $session_id
                    );
                    $stmt->execute();
                    echo json_encode(['success' => true]);
                }
            }
        }
        break;

    case 'DELETE':
        if (isset($_GET['session_id'])) {
            $session_id = (int)$_GET['session_id'];
            
            // End session
            if ($stmt = $conn->prepare("
                UPDATE ai_dj_sessions 
                SET end_time = CURRENT_TIMESTAMP
                WHERE session_id = ? AND user_id = ?
            ")) {
                $stmt->bind_param("ii", $session_id, $user_id);
                $stmt->execute();
                echo json_encode(['success' => true]);
            }
        }
        break;
} 