<?php
require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die(json_encode(['error' => 'Connection failed: ' . $conn->connect_error]));
}

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        if (isset($_GET['track_id'])) {
            // Pobierz nastroje dla konkretnego utworu
            $trackId = (int)$_GET['track_id'];
            
            $stmt = $conn->prepare("
                SELECT m.*, tm.intensity
                FROM track_moods tm
                JOIN moods m ON tm.mood_id = m.mood_id
                WHERE tm.track_id = ?
                ORDER BY tm.intensity DESC
            ");
            
            $stmt->bind_param("i", $trackId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $moods = [];
            while ($row = $result->fetch_assoc()) {
                $moods[] = [
                    'mood_id' => $row['mood_id'],
                    'name' => $row['name'],
                    'description' => $row['description'],
                    'color_code' => $row['color_code'],
                    'emoji' => $row['emoji'],
                    'intensity' => (float)$row['intensity']
                ];
            }
            
            echo json_encode($moods);
        } else {
            // Pobierz wszystkie dostępne nastroje
            $result = $conn->query("
                SELECT * FROM moods 
                ORDER BY name
            ");
            
            $moods = [];
            while ($row = $result->fetch_assoc()) {
                $moods[] = [
                    'mood_id' => $row['mood_id'],
                    'name' => $row['name'],
                    'description' => $row['description'],
                    'color_code' => $row['color_code'],
                    'emoji' => $row['emoji']
                ];
            }
            
            echo json_encode($moods);
        }
        break;

    case 'POST':
        // Dodaj nastrój do utworu
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['track_id'], $data['mood_id'], $data['intensity'])) {
            echo json_encode(['error' => 'Missing required fields']);
            exit;
        }

        $trackId = (int)$data['track_id'];
        $moodId = (int)$data['mood_id'];
        $intensity = (float)$data['intensity'];

        // Sprawdź poprawność intensywności
        if ($intensity < 0.00 || $intensity > 1.00) {
            echo json_encode(['error' => 'Intensity must be between 0.00 and 1.00']);
            exit;
        }

        $stmt = $conn->prepare("
            INSERT INTO track_moods (track_id, mood_id, intensity)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
            intensity = VALUES(intensity)
        ");
        
        $stmt->bind_param("iid", $trackId, $moodId, $intensity);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Mood added/updated successfully'
            ]);
        } else {
            echo json_encode(['error' => 'Failed to add/update mood']);
        }
        break;

    case 'DELETE':
        // Usuń nastrój z utworu
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['track_id'], $data['mood_id'])) {
            echo json_encode(['error' => 'Missing required fields']);
            exit;
        }

        $trackId = (int)$data['track_id'];
        $moodId = (int)$data['mood_id'];

        $stmt = $conn->prepare("
            DELETE FROM track_moods 
            WHERE track_id = ? AND mood_id = ?
        ");
        
        $stmt->bind_param("ii", $trackId, $moodId);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Mood removed successfully'
            ]);
        } else {
            echo json_encode(['error' => 'Failed to remove mood']);
        }
        break;
}

$conn->close(); 