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

function followArtist($pdo, $artistId) {
    // Sprawdź czy artysta istnieje
    $stmt = $pdo->prepare("
        SELECT artist_id, name 
        FROM artists 
        WHERE artist_id = ? AND is_active = TRUE
    ");
    $stmt->execute([$artistId]);
    $artist = $stmt->fetch();

    if (!$artist) {
        throw new Exception('Artysta nie istnieje lub jest nieaktywny.');
    }

    // Sprawdź czy już nie obserwuje
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM artist_follows 
        WHERE user_id = ? AND artist_id = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $artistId]);
    
    if ($stmt->fetchColumn() > 0) {
        throw new Exception('Już obserwujesz tego artystę.');
    }

    // Dodaj obserwowanie
    $stmt = $pdo->prepare("
        INSERT INTO artist_follows (
            user_id, 
            artist_id, 
            followed_at,
            notification_enabled
        ) VALUES (?, ?, CURRENT_TIMESTAMP, TRUE)
    ");
    $stmt->execute([$_SESSION['user_id'], $artistId]);

    return $artist;
}

function unfollowArtist($pdo, $artistId) {
    $stmt = $pdo->prepare("
        DELETE FROM artist_follows 
        WHERE user_id = ? AND artist_id = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $artistId]);

    if ($stmt->rowCount() === 0) {
        throw new Exception('Nie obserwujesz tego artysty.');
    }
}

function getArtistDetails($pdo, $artistId) {
    $stmt = $pdo->prepare("
        SELECT 
            a.*,
            COUNT(DISTINCT af.user_id) as current_followers,
            COUNT(DISTINCT t.track_id) as total_tracks,
            SUM(t.play_count) as total_plays,
            EXISTS(
                SELECT 1 
                FROM artist_follows 
                WHERE user_id = ? AND artist_id = ?
            ) as is_followed
        FROM artists a
        LEFT JOIN artist_follows af ON a.artist_id = af.artist_id
        LEFT JOIN tracks t ON a.artist_id = t.artist_id
        WHERE a.artist_id = ?
        GROUP BY a.artist_id
    ");
    $stmt->execute([$_SESSION['user_id'], $artistId, $artistId]);
    $artist = $stmt->fetch();

    if (!$artist) {
        throw new Exception('Artysta nie istnieje.');
    }

    // Pobierz popularne utwory
    $stmt = $pdo->prepare("
        SELECT 
            t.track_id,
            t.title,
            t.duration,
            t.play_count,
            al.title as album_title,
            al.release_date
        FROM tracks t
        LEFT JOIN albums al ON t.album_id = al.album_id
        WHERE t.artist_id = ?
        ORDER BY t.play_count DESC
        LIMIT 10
    ");
    $stmt->execute([$artistId]);
    $artist['top_tracks'] = $stmt->fetchAll();

    // Pobierz albumy
    $stmt = $pdo->prepare("
        SELECT 
            album_id,
            title,
            release_date,
            album_type,
            cover_art_url,
            total_tracks,
            total_plays
        FROM albums
        WHERE artist_id = ?
        ORDER BY release_date DESC
    ");
    $stmt->execute([$artistId]);
    $artist['albums'] = $stmt->fetchAll();

    return $artist;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? '';

        switch ($action) {
            case 'follow':
                if (!isset($data['artist_id'])) {
                    throw new Exception('Brak ID artysty.');
                }
                $artist = followArtist($pdo, $data['artist_id']);
                echo json_encode([
                    'success' => true,
                    'message' => 'Zacząłeś obserwować artystę: ' . $artist['name'],
                    'artist' => $artist
                ]);
                break;

            case 'unfollow':
                if (!isset($data['artist_id'])) {
                    throw new Exception('Brak ID artysty.');
                }
                unfollowArtist($pdo, $data['artist_id']);
                echo json_encode([
                    'success' => true,
                    'message' => 'Przestałeś obserwować artystę.'
                ]);
                break;

            case 'get_details':
                if (!isset($data['artist_id'])) {
                    throw new Exception('Brak ID artysty.');
                }
                $details = getArtistDetails($pdo, $data['artist_id']);
                echo json_encode([
                    'success' => true,
                    'data' => $details
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