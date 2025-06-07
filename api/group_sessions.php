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
        // Create new group session
        if (!isset($_GET['action'])) {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if ($stmt = $conn->prepare("CALL create_group_session(?, ?, ?, ?)")) {
                $stmt->bind_param("issi", 
                    $user_id,
                    $data['name'],
                    $data['session_type'],
                    $data['max_participants']
                );
                $stmt->execute();
                $result = $stmt->get_result();
                $session = $result->fetch_assoc();
                echo json_encode(['session_id' => $session['session_id']]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to create session']);
            }
        }
        // Join session
        else if ($_GET['action'] === 'join') {
            $data = json_decode(file_get_contents('php://input'), true);
            $session_id = (int)$data['session_id'];
            
            // Check if session exists and has space
            if ($stmt = $conn->prepare("
                SELECT 
                    gs.*,
                    COUNT(gsp.user_id) as current_participants
                FROM group_sessions gs
                LEFT JOIN group_session_participants gsp 
                    ON gs.group_session_id = gsp.group_session_id
                WHERE gs.group_session_id = ?
                GROUP BY gs.group_session_id
            ")) {
                $stmt->bind_param("i", $session_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $session = $result->fetch_assoc();
                
                if (!$session) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Session not found']);
                    break;
                }
                
                if ($session['current_participants'] >= $session['max_participants']) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Session is full']);
                    break;
                }
                
                // Add user to session
                if ($stmt = $conn->prepare("
                    INSERT INTO group_session_participants (
                        group_session_id, user_id, role
                    ) VALUES (?, ?, 'participant')
                ")) {
                    $stmt->bind_param("ii", $session_id, $user_id);
                    $stmt->execute();
                    echo json_encode(['success' => true]);
                }
            }
        }
        // Send chat message
        else if ($_GET['action'] === 'chat') {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if ($stmt = $conn->prepare("
                INSERT INTO group_session_chat (
                    group_session_id, user_id, message_text, message_type
                ) VALUES (?, ?, ?, ?)
            ")) {
                $stmt->bind_param("iiss", 
                    $data['session_id'],
                    $user_id,
                    $data['message'],
                    $data['type'] ?? 'text'
                );
                $stmt->execute();
                echo json_encode([
                    'message_id' => $conn->insert_id,
                    'sent_at' => date('Y-m-d H:i:s')
                ]);
            }
        }
        break;

    case 'GET':
        // Get session details
        if (isset($_GET['session_id'])) {
            $session_id = (int)$_GET['session_id'];
            
            // Get session info with participants
            if ($stmt = $conn->prepare("
                SELECT 
                    gs.*,
                    t.title as current_track_title,
                    t.duration as current_track_duration,
                    a.name as current_track_artist
                FROM group_sessions gs
                LEFT JOIN tracks t ON gs.current_track_id = t.track_id
                LEFT JOIN artists a ON t.artist_id = a.artist_id
                WHERE gs.group_session_id = ?
            ")) {
                $stmt->bind_param("i", $session_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($session = $result->fetch_assoc()) {
                    // Get participants
                    $part_stmt = $conn->prepare("
                        SELECT 
                            gsp.*,
                            u.username,
                            u.avatar_url
                        FROM group_session_participants gsp
                        JOIN users u ON gsp.user_id = u.user_id
                        WHERE gsp.group_session_id = ?
                    ");
                    $part_stmt->bind_param("i", $session_id);
                    $part_stmt->execute();
                    $participants = $part_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    
                    // Get recent chat messages
                    $chat_stmt = $conn->prepare("
                        SELECT 
                            gsc.*,
                            u.username,
                            u.avatar_url
                        FROM group_session_chat gsc
                        JOIN users u ON gsc.user_id = u.user_id
                        WHERE gsc.group_session_id = ?
                        ORDER BY gsc.sent_at DESC
                        LIMIT 50
                    ");
                    $chat_stmt->bind_param("i", $session_id);
                    $chat_stmt->execute();
                    $chat = $chat_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    
                    $session['participants'] = $participants;
                    $session['chat'] = array_reverse($chat); // Show oldest first
                    
                    echo json_encode($session);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Session not found']);
                }
            }
        }
        // List available sessions
        else {
            $query = "
                SELECT 
                    gs.*,
                    COUNT(gsp.user_id) as current_participants,
                    u.username as host_username,
                    u.avatar_url as host_avatar
                FROM group_sessions gs
                JOIN users u ON gs.host_user_id = u.user_id
                LEFT JOIN group_session_participants gsp 
                    ON gs.group_session_id = gsp.group_session_id
                WHERE gs.is_active = TRUE
                AND gs.session_type IN ('public'";
            
            // Add friends_only if we implement friend system
            // if (has_friends) {
            //     $query .= ", 'friends_only'";
            // }
            
            $query .= ")
                GROUP BY gs.group_session_id
                HAVING current_participants < gs.max_participants
                ORDER BY gs.created_at DESC
                LIMIT 20
            ";
            
            if ($result = $conn->query($query)) {
                echo json_encode($result->fetch_all(MYSQLI_ASSOC));
            }
        }
        break;

    case 'PUT':
        if (isset($_GET['session_id'])) {
            $session_id = (int)$_GET['session_id'];
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Update current track
            if ($_GET['action'] === 'update_track') {
                // Verify user can control playback
                if ($stmt = $conn->prepare("
                    SELECT can_control_playback 
                    FROM group_session_participants
                    WHERE group_session_id = ? AND user_id = ?
                ")) {
                    $stmt->bind_param("ii", $session_id, $user_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $participant = $result->fetch_assoc();
                    
                    if (!$participant || !$participant['can_control_playback']) {
                        http_response_code(403);
                        echo json_encode(['error' => 'Not authorized to control playback']);
                        break;
                    }
                    
                    // Update track
                    if ($stmt = $conn->prepare("
                        UPDATE group_sessions 
                        SET current_track_id = ?,
                            current_position = ?
                        WHERE group_session_id = ?
                    ")) {
                        $stmt->bind_param("iii", 
                            $data['track_id'],
                            $data['position'] ?? 0,
                            $session_id
                        );
                        $stmt->execute();
                        echo json_encode(['success' => true]);
                    }
                }
            }
        }
        break;

    case 'DELETE':
        if (isset($_GET['session_id'])) {
            $session_id = (int)$_GET['session_id'];
            
            // Leave session
            if ($_GET['action'] === 'leave') {
                if ($stmt = $conn->prepare("
                    DELETE FROM group_session_participants
                    WHERE group_session_id = ? AND user_id = ?
                ")) {
                    $stmt->bind_param("ii", $session_id, $user_id);
                    $stmt->execute();
                    echo json_encode(['success' => true]);
                }
            }
            // End session (host only)
            else {
                if ($stmt = $conn->prepare("
                    UPDATE group_sessions 
                    SET is_active = FALSE
                    WHERE group_session_id = ? 
                    AND host_user_id = ?
                ")) {
                    $stmt->bind_param("ii", $session_id, $user_id);
                    $stmt->execute();
                    if ($conn->affected_rows > 0) {
                        echo json_encode(['success' => true]);
                    } else {
                        http_response_code(403);
                        echo json_encode(['error' => 'Not authorized to end session']);
                    }
                }
            }
        }
        break;
} 