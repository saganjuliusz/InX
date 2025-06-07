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
        // Rejestracja nowego urządzenia
        if (!isset($_GET['action'])) {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if ($stmt = $conn->prepare("CALL register_device(?, ?, ?, ?, ?, ?, ?)")) {
                $stmt->bind_param("issssss", 
                    $user_id,
                    $data['device_name'],
                    $data['device_type'],
                    $data['device_model'],
                    $data['os_type'],
                    $data['os_version'],
                    $data['app_version']
                );
                $stmt->execute();
                $result = $stmt->get_result();
                $device = $result->fetch_assoc();
                echo json_encode(['device_id' => $device['device_id']]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to register device']);
            }
        }
        // Transfer odtwarzania
        else if ($_GET['action'] === 'transfer') {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if ($stmt = $conn->prepare("CALL transfer_playback(?, ?, ?)")) {
                $stmt->bind_param("iii", 
                    $user_id,
                    $data['from_device_id'],
                    $data['to_device_id']
                );
                $stmt->execute();
                echo json_encode(['success' => true]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to transfer playback']);
            }
        }
        // Aktualizacja stanu odtwarzania
        else if ($_GET['action'] === 'update_state') {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if ($stmt = $conn->prepare("
                INSERT INTO device_playback_states (
                    device_id, user_id, track_id, playlist_id,
                    queue_position, playback_position_ms,
                    is_playing, volume_level, repeat_mode, shuffle_mode
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    track_id = VALUES(track_id),
                    playlist_id = VALUES(playlist_id),
                    queue_position = VALUES(queue_position),
                    playback_position_ms = VALUES(playback_position_ms),
                    is_playing = VALUES(is_playing),
                    volume_level = VALUES(volume_level),
                    repeat_mode = VALUES(repeat_mode),
                    shuffle_mode = VALUES(shuffle_mode),
                    updated_at = CURRENT_TIMESTAMP
            ")) {
                $stmt->bind_param("iiiiiiisis", 
                    $data['device_id'],
                    $user_id,
                    $data['track_id'],
                    $data['playlist_id'],
                    $data['queue_position'],
                    $data['position_ms'],
                    $data['is_playing'],
                    $data['volume'],
                    $data['repeat_mode'],
                    $data['shuffle']
                );
                $stmt->execute();
                echo json_encode(['success' => true]);
            }
        }
        break;

    case 'GET':
        // Lista urządzeń użytkownika
        if (!isset($_GET['action'])) {
            if ($stmt = $conn->prepare("
                SELECT 
                    ud.*,
                    dps.track_id as current_track_id,
                    dps.playlist_id as current_playlist_id,
                    dps.playback_position_ms,
                    dps.is_playing,
                    t.title as current_track_title,
                    a.name as current_artist_name
                FROM user_devices ud
                LEFT JOIN device_playback_states dps ON ud.device_id = dps.device_id
                LEFT JOIN tracks t ON dps.track_id = t.track_id
                LEFT JOIN artists a ON t.artist_id = a.artist_id
                WHERE ud.user_id = ? AND ud.is_active = TRUE
                ORDER BY ud.last_active_at DESC
            ")) {
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                echo json_encode($result->fetch_all(MYSQLI_ASSOC));
            }
        }
        // Stan odtwarzania konkretnego urządzenia
        else if ($_GET['action'] === 'state' && isset($_GET['device_id'])) {
            $device_id = (int)$_GET['device_id'];
            
            if ($stmt = $conn->prepare("
                SELECT 
                    dps.*,
                    t.title as track_title,
                    t.duration,
                    a.name as artist_name,
                    al.title as album_title,
                    al.cover_art_url
                FROM device_playback_states dps
                LEFT JOIN tracks t ON dps.track_id = t.track_id
                LEFT JOIN artists a ON t.artist_id = a.artist_id
                LEFT JOIN albums al ON t.album_id = al.album_id
                WHERE dps.device_id = ? AND dps.user_id = ?
            ")) {
                $stmt->bind_param("ii", $device_id, $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($state = $result->fetch_assoc()) {
                    // Pobierz kolejkę odtwarzania
                    $queue_stmt = $conn->prepare("
                        SELECT 
                            dpq.*,
                            t.title as track_title,
                            t.duration,
                            a.name as artist_name
                        FROM device_playback_queue dpq
                        JOIN tracks t ON dpq.track_id = t.track_id
                        JOIN artists a ON t.artist_id = a.artist_id
                        WHERE dpq.device_id = ?
                        ORDER BY dpq.position
                    ");
                    $queue_stmt->bind_param("i", $device_id);
                    $queue_stmt->execute();
                    $queue = $queue_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    
                    $state['queue'] = $queue;
                    echo json_encode($state);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Device state not found']);
                }
            }
        }
        break;

    case 'PUT':
        if (isset($_GET['device_id'])) {
            $device_id = (int)$_GET['device_id'];
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Aktualizacja informacji o urządzeniu
            if ($stmt = $conn->prepare("
                UPDATE user_devices
                SET device_name = ?,
                    last_active_at = CURRENT_TIMESTAMP
                WHERE device_id = ? AND user_id = ?
            ")) {
                $stmt->bind_param("sii", 
                    $data['device_name'],
                    $device_id,
                    $user_id
                );
                $stmt->execute();
                echo json_encode(['success' => true]);
            }
        }
        break;

    case 'DELETE':
        if (isset($_GET['device_id'])) {
            $device_id = (int)$_GET['device_id'];
            
            // Dezaktywacja urządzenia
            if ($stmt = $conn->prepare("
                UPDATE user_devices
                SET is_active = FALSE
                WHERE device_id = ? AND user_id = ?
            ")) {
                $stmt->bind_param("ii", $device_id, $user_id);
                $stmt->execute();
                echo json_encode(['success' => true]);
            }
        }
        break;
} 