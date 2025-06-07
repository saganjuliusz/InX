<?php
// create_playlist.php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Użytkownik nie jest zalogowany.'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['name'])) {
            throw new Exception('Nazwa playlisty jest wymagana.');
        }

        $name = trim($data['name']);
        $description = trim($data['description'] ?? '');
        $isPublic = isset($data['is_public']) ? (bool)$data['is_public'] : false;
        $isCollaborative = isset($data['is_collaborative']) ? (bool)$data['is_collaborative'] : false;
        
        if (empty($name)) {
            throw new Exception('Nazwa playlisty nie może być pusta.');
        }

        if (strlen($name) > 200) {
            throw new Exception('Nazwa playlisty jest zbyt długa (max 200 znaków).');
        }

        // Tworzenie nowej playlisty
        $stmt = $pdo->prepare("
            INSERT INTO playlists (
                user_id,
                name,
                description,
                playlist_type,
                is_public,
                is_collaborative,
                total_tracks,
                total_duration,
                created_at,
                updated_at
            ) VALUES (
                ?, ?, ?, 
                'user',
                ?, ?,
                0, 0,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )
        ");

        $stmt->execute([
            $_SESSION['user_id'],
            $name,
            $description,
            $isPublic,
            $isCollaborative
        ]);

        $playlistId = $pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'message' => 'Playlista została utworzona.',
            'playlist_id' => $playlistId
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Nieprawidłowa metoda żądania. Wymagana metoda POST.'
    ]);
}
?>