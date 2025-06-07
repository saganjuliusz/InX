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
        // Pobierz ocenę utworu
        $trackId = isset($_GET['track_id']) ? (int)$_GET['track_id'] : 0;
        $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

        if ($trackId <= 0) {
            echo json_encode(['error' => 'Invalid track ID']);
            exit;
        }

        // Pobierz średnią ocenę
        $stmt = $conn->prepare("
            SELECT 
                AVG(rating) as average_rating,
                COUNT(*) as total_ratings
            FROM user_track_interactions
            WHERE track_id = ? AND rating IS NOT NULL
        ");
        
        $stmt->bind_param("i", $trackId);
        $stmt->execute();
        $result = $stmt->get_result();
        $avgRating = $result->fetch_assoc();

        // Jeśli podano user_id, pobierz też ocenę użytkownika
        $userRating = null;
        if ($userId > 0) {
            $stmt = $conn->prepare("
                SELECT rating
                FROM user_track_interactions
                WHERE track_id = ? AND user_id = ?
            ");
            
            $stmt->bind_param("ii", $trackId, $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $userRating = $row['rating'];
            }
        }

        echo json_encode([
            'average_rating' => round($avgRating['average_rating'], 1),
            'total_ratings' => (int)$avgRating['total_ratings'],
            'user_rating' => $userRating
        ]);
        break;

    case 'POST':
    case 'PUT':
        // Dodaj lub zaktualizuj ocenę
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['user_id'], $data['track_id'], $data['rating'])) {
            echo json_encode(['error' => 'Missing required fields']);
            exit;
        }

        $userId = (int)$data['user_id'];
        $trackId = (int)$data['track_id'];
        $rating = (float)$data['rating'];

        // Sprawdź poprawność oceny
        if ($rating < 1.0 || $rating > 5.0) {
            echo json_encode(['error' => 'Rating must be between 1.0 and 5.0']);
            exit;
        }

        // Dodaj lub zaktualizuj ocenę
        $stmt = $conn->prepare("
            INSERT INTO user_track_interactions 
                (user_id, track_id, rating, rated_at)
            VALUES 
                (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                rating = VALUES(rating),
                rated_at = NOW()
        ");
        
        $stmt->bind_param("iid", $userId, $trackId, $rating);
        
        if ($stmt->execute()) {
            // Pobierz zaktualizowaną średnią ocenę
            $stmt = $conn->prepare("
                SELECT AVG(rating) as average_rating
                FROM user_track_interactions
                WHERE track_id = ? AND rating IS NOT NULL
            ");
            
            $stmt->bind_param("i", $trackId);
            $stmt->execute();
            $result = $stmt->get_result();
            $avgRating = $result->fetch_assoc();

            echo json_encode([
                'success' => true,
                'message' => 'Rating updated successfully',
                'new_average_rating' => round($avgRating['average_rating'], 1)
            ]);
        } else {
            echo json_encode(['error' => 'Failed to update rating']);
        }
        break;
}

$conn->close(); 