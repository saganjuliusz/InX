<?php
require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die(json_encode(['error' => 'Connection failed: ' . $conn->connect_error]));
}

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        if (isset($_GET['event_id'])) {
            // Pobierz szczegóły wydarzenia wirtualnego
            $eventId = (int)$_GET['event_id'];
            
            $stmt = $conn->prepare("
                SELECT 
                    ve.*,
                    a.name as artist_name,
                    a.avatar_url as artist_avatar
                FROM virtual_events ve
                JOIN artists a ON ve.artist_id = a.artist_id
                WHERE ve.virtual_event_id = ?
            ");
            
            $stmt->bind_param("i", $eventId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($event = $result->fetch_assoc()) {
                echo json_encode($event);
            } else {
                echo json_encode(['error' => 'Virtual event not found']);
            }
        } else {
            // Lista nadchodzących wydarzeń wirtualnych
            $stmt = $conn->prepare("
                SELECT 
                    ve.*,
                    a.name as artist_name,
                    a.avatar_url as artist_avatar
                FROM virtual_events ve
                JOIN artists a ON ve.artist_id = a.artist_id
                WHERE ve.start_time > NOW()
                ORDER BY ve.start_time ASC
                LIMIT 50
            ");
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            $events = [];
            while ($row = $result->fetch_assoc()) {
                $events[] = $row;
            }
            
            echo json_encode(['events' => $events]);
        }
        break;

    case 'POST':
        // Utwórz nowe wydarzenie wirtualne
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['title'], $data['artist_id'], $data['start_time'])) {
            echo json_encode(['error' => 'Missing required fields']);
            exit;
        }
        
        $stmt = $conn->prepare("
            INSERT INTO virtual_events (
                title, artist_id, event_type, start_time, 
                duration, stream_url, ticket_price, max_viewers,
                chat_enabled
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param(
            "sisssidib",
            $data['title'],
            $data['artist_id'],
            $data['event_type'] ?? 'live',
            $data['start_time'],
            $data['duration'] ?? null,
            $data['stream_url'] ?? null,
            $data['ticket_price'] ?? 0.00,
            $data['max_viewers'] ?? null,
            $data['chat_enabled'] ?? true
        );
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'event_id' => $conn->insert_id,
                'message' => 'Virtual event created successfully'
            ]);
        } else {
            echo json_encode(['error' => 'Failed to create virtual event']);
        }
        break;

    case 'PUT':
        // Aktualizuj wydarzenie wirtualne
        if (!isset($_GET['event_id'])) {
            echo json_encode(['error' => 'Missing event ID']);
            exit;
        }
        
        $eventId = (int)$_GET['event_id'];
        $data = json_decode(file_get_contents('php://input'), true);
        
        $updateFields = [];
        $params = [];
        $types = "";
        
        if (isset($data['title'])) {
            $updateFields[] = "title = ?";
            $params[] = $data['title'];
            $types .= "s";
        }
        
        if (isset($data['start_time'])) {
            $updateFields[] = "start_time = ?";
            $params[] = $data['start_time'];
            $types .= "s";
        }
        
        if (isset($data['stream_url'])) {
            $updateFields[] = "stream_url = ?";
            $params[] = $data['stream_url'];
            $types .= "s";
        }
        
        if (isset($data['ticket_price'])) {
            $updateFields[] = "ticket_price = ?";
            $params[] = $data['ticket_price'];
            $types .= "d";
        }
        
        if (empty($updateFields)) {
            echo json_encode(['error' => 'No fields to update']);
            exit;
        }
        
        $params[] = $eventId;
        $types .= "i";
        
        $stmt = $conn->prepare("
            UPDATE virtual_events 
            SET " . implode(", ", $updateFields) . "
            WHERE virtual_event_id = ?
        ");
        
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Virtual event updated successfully'
            ]);
        } else {
            echo json_encode(['error' => 'Failed to update virtual event']);
        }
        break;

    case 'DELETE':
        if (!isset($_GET['event_id'])) {
            echo json_encode(['error' => 'Missing event ID']);
            exit;
        }
        
        $eventId = (int)$_GET['event_id'];
        
        $stmt = $conn->prepare("DELETE FROM virtual_events WHERE virtual_event_id = ?");
        $stmt->bind_param("i", $eventId);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Virtual event deleted successfully'
            ]);
        } else {
            echo json_encode(['error' => 'Failed to delete virtual event']);
        }
        break;
}

$conn->close();
?> 