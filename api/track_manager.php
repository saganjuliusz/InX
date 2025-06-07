<?php
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

function addTrackToPlaylist($pdo, $playlistId, $trackId, $position = null) {
    // Sprawdzenie czy użytkownik ma dostęp do playlisty
    $stmt = $pdo->prepare("
        SELECT user_id, is_collaborative 
        FROM playlists 
        WHERE playlist_id = ?
    ");
    $stmt->execute([$playlistId]);
    $playlist = $stmt->fetch();

    if (!$playlist || ($playlist['user_id'] !== $_SESSION['user_id'] && !$playlist['is_collaborative'])) {
        throw new Exception('Brak dostępu do playlisty.');
    }

    // Jeśli nie podano pozycji, dodaj na koniec
    if ($position === null) {
        $stmt = $pdo->prepare("
            SELECT COALESCE(MAX(position), 0) + 1 
            FROM playlist_tracks 
            WHERE playlist_id = ?
        ");
        $stmt->execute([$playlistId]);
        $position = $stmt->fetchColumn();
    }

    // Dodaj utwór do playlisty
    $stmt = $pdo->prepare("
        INSERT INTO playlist_tracks (
            playlist_id, 
            track_id, 
            position, 
            added_by_user_id,
            added_at
        ) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
    ");
    $stmt->execute([$playlistId, $trackId, $position, $_SESSION['user_id']]);

    // Aktualizuj statystyki playlisty
    updatePlaylistStats($pdo, $playlistId);
}

function removeTrackFromPlaylist($pdo, $playlistId, $trackId) {
    // Sprawdzenie uprawnień
    $stmt = $pdo->prepare("
        SELECT user_id, is_collaborative 
        FROM playlists 
        WHERE playlist_id = ?
    ");
    $stmt->execute([$playlistId]);
    $playlist = $stmt->fetch();

    if (!$playlist || ($playlist['user_id'] !== $_SESSION['user_id'] && !$playlist['is_collaborative'])) {
        throw new Exception('Brak dostępu do playlisty.');
    }

    // Usuń utwór
    $stmt = $pdo->prepare("
        DELETE FROM playlist_tracks 
        WHERE playlist_id = ? AND track_id = ?
    ");
    $stmt->execute([$playlistId, $trackId]);

    // Przenumeruj pozycje
    $stmt = $pdo->prepare("
        UPDATE playlist_tracks pt1
        JOIN (
            SELECT playlist_id, track_id, ROW_NUMBER() OVER (ORDER BY position) as new_position
            FROM playlist_tracks
            WHERE playlist_id = ?
        ) pt2 ON pt1.playlist_id = pt2.playlist_id AND pt1.track_id = pt2.track_id
        SET pt1.position = pt2.new_position
        WHERE pt1.playlist_id = ?
    ");
    $stmt->execute([$playlistId, $playlistId]);

    // Aktualizuj statystyki playlisty
    updatePlaylistStats($pdo, $playlistId);
}

function updatePlaylistStats($pdo, $playlistId) {
    $stmt = $pdo->prepare("
        UPDATE playlists p
        SET total_tracks = (
            SELECT COUNT(*) 
            FROM playlist_tracks 
            WHERE playlist_id = ?
        ),
        total_duration = (
            SELECT COALESCE(SUM(t.duration), 0)
            FROM playlist_tracks pt
            JOIN tracks t ON pt.track_id = t.track_id
            WHERE pt.playlist_id = ?
        ),
        updated_at = CURRENT_TIMESTAMP
        WHERE p.playlist_id = ?
    ");
    $stmt->execute([$playlistId, $playlistId, $playlistId]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? '';

        switch ($action) {
            case 'add':
                if (!isset($data['playlist_id']) || !isset($data['track_id'])) {
                    throw new Exception('Brak wymaganych danych.');
                }
                addTrackToPlaylist($pdo, $data['playlist_id'], $data['track_id'], $data['position'] ?? null);
                echo json_encode([
                    'success' => true,
                    'message' => 'Utwór został dodany do playlisty.'
                ]);
                break;

            case 'remove':
                if (!isset($data['playlist_id']) || !isset($data['track_id'])) {
                    throw new Exception('Brak wymaganych danych.');
                }
                removeTrackFromPlaylist($pdo, $data['playlist_id'], $data['track_id']);
                echo json_encode([
                    'success' => true,
                    'message' => 'Utwór został usunięty z playlisty.'
                ]);
                break;

            default:
                throw new Exception('Nieznana akcja.');
        }
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