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
        if (isset($_GET['sheet_id'])) {
            // Pobierz szczegóły partytury
            $sheetId = (int)$_GET['sheet_id'];
            
            $stmt = $conn->prepare("
                SELECT 
                    sm.*,
                    t.title as track_title,
                    a.name as artist_name
                FROM sheet_music sm
                JOIN tracks t ON sm.track_id = t.track_id
                JOIN artists a ON t.artist_id = a.artist_id
                WHERE sm.sheet_id = ?
            ");
            
            $stmt->bind_param("i", $sheetId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($sheet = $result->fetch_assoc()) {
                echo json_encode($sheet);
            } else {
                echo json_encode(['error' => 'Sheet music not found']);
            }
        } else {
            // Lista partytur z filtrowaniem
            $query = "
                SELECT 
                    sm.*,
                    t.title as track_title,
                    a.name as artist_name
                FROM sheet_music sm
                JOIN tracks t ON sm.track_id = t.track_id
                JOIN artists a ON t.artist_id = a.artist_id
                WHERE 1=1
            ";
            
            $params = [];
            $types = "";
            
            if (isset($_GET['difficulty'])) {
                $query .= " AND sm.difficulty_level = ?";
                $params[] = $_GET['difficulty'];
                $types .= "s";
            }
            
            if (isset($_GET['instrument'])) {
                $query .= " AND sm.instrument_type = ?";
                $params[] = $_GET['instrument'];
                $types .= "s";
            }
            
            $query .= " ORDER BY sm.download_count DESC LIMIT 50";
            
            $stmt = $conn->prepare($query);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            $sheets = [];
            while ($row = $result->fetch_assoc()) {
                $sheets[] = $row;
            }
            
            echo json_encode(['sheets' => $sheets]);
        }
        break;

    case 'POST':
        // Dodaj nową partyturę
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['track_id'], $data['title'], $data['difficulty_level'])) {
            echo json_encode(['error' => 'Missing required fields']);
            exit;
        }
        
        $stmt = $conn->prepare("
            INSERT INTO sheet_music (
                track_id, title, composer_notes, difficulty_level,
                instrument_type, file_path, preview_image_url, price
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param(
            "issssssd",
            $data['track_id'],
            $data['title'],
            $data['composer_notes'] ?? null,
            $data['difficulty_level'],
            $data['instrument_type'] ?? null,
            $data['file_path'] ?? null,
            $data['preview_image_url'] ?? null,
            $data['price'] ?? 0.00
        );
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'sheet_id' => $conn->insert_id,
                'message' => 'Sheet music added successfully'
            ]);
        } else {
            echo json_encode(['error' => 'Failed to add sheet music']);
        }
        break;

    case 'PUT':
        // Aktualizuj partyturę
        if (!isset($_GET['sheet_id'])) {
            echo json_encode(['error' => 'Missing sheet ID']);
            exit;
        }
        
        $sheetId = (int)$_GET['sheet_id'];
        $data = json_decode(file_get_contents('php://input'), true);
        
        $updateFields = [];
        $params = [];
        $types = "";
        
        if (isset($data['title'])) {
            $updateFields[] = "title = ?";
            $params[] = $data['title'];
            $types .= "s";
        }
        
        if (isset($data['difficulty_level'])) {
            $updateFields[] = "difficulty_level = ?";
            $params[] = $data['difficulty_level'];
            $types .= "s";
        }
        
        if (isset($data['price'])) {
            $updateFields[] = "price = ?";
            $params[] = $data['price'];
            $types .= "d";
        }
        
        if (isset($data['is_verified'])) {
            $updateFields[] = "is_verified = ?";
            $params[] = $data['is_verified'];
            $types .= "i";
        }
        
        if (empty($updateFields)) {
            echo json_encode(['error' => 'No fields to update']);
            exit;
        }
        
        $params[] = $sheetId;
        $types .= "i";
        
        $stmt = $conn->prepare("
            UPDATE sheet_music 
            SET " . implode(", ", $updateFields) . "
            WHERE sheet_id = ?
        ");
        
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Sheet music updated successfully'
            ]);
        } else {
            echo json_encode(['error' => 'Failed to update sheet music']);
        }
        break;

    case 'DELETE':
        if (!isset($_GET['sheet_id'])) {
            echo json_encode(['error' => 'Missing sheet ID']);
            exit;
        }
        
        $sheetId = (int)$_GET['sheet_id'];
        
        $stmt = $conn->prepare("DELETE FROM sheet_music WHERE sheet_id = ?");
        $stmt->bind_param("i", $sheetId);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Sheet music deleted successfully'
            ]);
        } else {
            echo json_encode(['error' => 'Failed to delete sheet music']);
        }
        break;
}

$conn->close();
?> 