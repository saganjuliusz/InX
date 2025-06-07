<?php
require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT');
header('Access-Control-Allow-Headers: Content-Type');

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die(json_encode(['error' => 'Connection failed: ' . $conn->connect_error]));
}

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        if (!isset($_GET['track_id'])) {
            echo json_encode(['error' => 'Missing track ID']);
            exit;
        }
        
        $trackId = (int)$_GET['track_id'];
        $language = isset($_GET['language']) ? $_GET['language'] : 'en';
        
        $stmt = $conn->prepare("
            SELECT 
                sl.*,
                u.username as contributor_name
            FROM synchronized_lyrics sl
            LEFT JOIN users u ON sl.contributor_id = u.user_id
            WHERE sl.track_id = ? AND sl.language = ?
            AND sl.is_verified = TRUE
            ORDER BY sl.created_at DESC
            LIMIT 1
        ");
        
        $stmt->bind_param("is", $trackId, $language);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($lyrics = $result->fetch_assoc()) {
            $lyrics['lyrics_json'] = json_decode($lyrics['lyrics_json'], true);
            echo json_encode($lyrics);
        } else {
            echo json_encode(['error' => 'Lyrics not found']);
        }
        break;

    case 'POST':
        // Dodaj nowe zsynchronizowane teksty
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['track_id'], $data['lyrics_json'], $data['contributor_id'])) {
            echo json_encode(['error' => 'Missing required fields']);
            exit;
        }
        
        // Walidacja formatu lyrics_json
        if (!is_array($data['lyrics_json'])) {
            echo json_encode(['error' => 'Invalid lyrics format']);
            exit;
        }
        
        foreach ($data['lyrics_json'] as $line) {
            if (!isset($line['time'], $line['text'])) {
                echo json_encode(['error' => 'Invalid lyrics line format']);
                exit;
            }
        }
        
        $stmt = $conn->prepare("
            INSERT INTO synchronized_lyrics (
                track_id, language, lyrics_json, 
                contributor_id, is_verified
            ) VALUES (?, ?, ?, ?, FALSE)
        ");
        
        $lyricsJson = json_encode($data['lyrics_json']);
        
        $stmt->bind_param(
            "issi",
            $data['track_id'],
            $data['language'] ?? 'en',
            $lyricsJson,
            $data['contributor_id']
        );
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'lyrics_id' => $conn->insert_id,
                'message' => 'Lyrics added successfully'
            ]);
        } else {
            echo json_encode(['error' => 'Failed to add lyrics']);
        }
        break;

    case 'PUT':
        // Aktualizuj status weryfikacji tekstów
        if (!isset($_GET['lyrics_id'])) {
            echo json_encode(['error' => 'Missing lyrics ID']);
            exit;
        }
        
        $lyricsId = (int)$_GET['lyrics_id'];
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['is_verified'])) {
            echo json_encode(['error' => 'Missing verification status']);
            exit;
        }
        
        $stmt = $conn->prepare("
            UPDATE synchronized_lyrics 
            SET is_verified = ?
            WHERE lyrics_id = ?
        ");
        
        $isVerified = (bool)$data['is_verified'];
        $stmt->bind_param("ii", $isVerified, $lyricsId);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Lyrics verification status updated successfully'
            ]);
        } else {
            echo json_encode(['error' => 'Failed to update lyrics verification status']);
        }
        break;
}

$conn->close();
?> 