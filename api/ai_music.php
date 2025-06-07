<?php
require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die(json_encode(['error' => 'Connection failed: ' . $conn->connect_error]));
}

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        if (isset($_GET['track_id'])) {
            // Pobierz szczegóły wygenerowanego utworu
            $trackId = (int)$_GET['track_id'];
            
            $stmt = $conn->prepare("
                SELECT 
                    agt.*,
                    t.title as source_track_title,
                    t.artist_id as source_artist_id,
                    a.name as source_artist_name
                FROM ai_generated_tracks agt
                LEFT JOIN tracks t ON agt.source_track_id = t.track_id
                LEFT JOIN artists a ON t.artist_id = a.artist_id
                WHERE agt.track_id = ?
            ");
            
            $stmt->bind_param("i", $trackId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($track = $result->fetch_assoc()) {
                $track['generation_parameters'] = json_decode($track['generation_parameters'], true);
                echo json_encode($track);
            } else {
                echo json_encode(['error' => 'AI generated track not found']);
            }
        } else {
            // Lista wygenerowanych utworów
            $page = isset($_GET['page']) ? max(0, (int)$_GET['page'] - 1) : 0;
            $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 50) : 20;
            $offset = $page * $limit;
            
            $stmt = $conn->prepare("
                SELECT 
                    agt.*,
                    t.title as source_track_title,
                    a.name as source_artist_name
                FROM ai_generated_tracks agt
                LEFT JOIN tracks t ON agt.source_track_id = t.track_id
                LEFT JOIN artists a ON t.artist_id = a.artist_id
                ORDER BY agt.track_id DESC
                LIMIT ?, ?
            ");
            
            $stmt->bind_param("ii", $offset, $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $tracks = [];
            while ($row = $result->fetch_assoc()) {
                $row['generation_parameters'] = json_decode($row['generation_parameters'], true);
                $tracks[] = $row;
            }
            
            echo json_encode(['tracks' => $tracks]);
        }
        break;

    case 'POST':
        // Rozpocznij generowanie nowego utworu
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['prompt_text'])) {
            echo json_encode(['error' => 'Missing prompt text']);
            exit;
        }
        
        $stmt = $conn->prepare("
            INSERT INTO ai_generated_tracks (
                prompt_text, model_version, generation_parameters,
                source_track_id, generation_status
            ) VALUES (?, ?, ?, ?, 'pending')
        ");
        
        $generationParams = isset($data['parameters']) ? json_encode($data['parameters']) : null;
        $modelVersion = $data['model_version'] ?? 'v1.0';
        
        $stmt->bind_param(
            "sssi",
            $data['prompt_text'],
            $modelVersion,
            $generationParams,
            $data['source_track_id'] ?? null
        );
        
        if ($stmt->execute()) {
            $generationId = $conn->insert_id;
            
            // Rozpocznij asynchroniczne generowanie (w rzeczywistej implementacji)
            // Tutaj należałoby dodać kod do kolejkowania zadania generowania
            
            echo json_encode([
                'success' => true,
                'generation_id' => $generationId,
                'message' => 'AI music generation started',
                'status' => 'pending'
            ]);
        } else {
            echo json_encode(['error' => 'Failed to start AI music generation']);
        }
        break;
}

$conn->close();
?> 