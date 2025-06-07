<?php
// rename_playlist.php
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
        
        if (!isset($data['playlist_id']) || !isset($data['new_name'])) {
            throw new Exception('Brak wymaganych danych.');
        }

        $playlistId = (int)$data['playlist_id'];
        $newName = trim($data['new_name']);
        
        if (empty($newName)) {
            throw new Exception('Nazwa playlisty nie może być pusta.');
        }

        if (strlen($newName) > 200) {
            throw new Exception('Nazwa playlisty jest zbyt długa (max 200 znaków).');
        }

        // Sprawdzenie czy playlista należy do użytkownika
        $stmt = $pdo->prepare("
            SELECT user_id 
            FROM playlists 
            WHERE playlist_id = ? AND (user_id = ? OR is_collaborative = TRUE)
        ");
        $stmt->execute([$playlistId, $_SESSION['user_id']]);
        
        if (!$stmt->fetch()) {
            throw new Exception('Nie masz uprawnień do modyfikacji tej playlisty.');
        }

        // Aktualizacja nazwy playlisty
        $stmt = $pdo->prepare("
            UPDATE playlists 
            SET name = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE playlist_id = ?
        ");
        $stmt->execute([$newName, $playlistId]);

        echo json_encode([
            'success' => true,
            'message' => 'Nazwa playlisty została zmieniona.'
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