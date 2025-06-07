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

function logListening($pdo, $trackId, $duration, $context = []) {
    // Oblicz procent ukończenia
    $stmt = $pdo->prepare("SELECT duration FROM tracks WHERE track_id = ?");
    $stmt->execute([$trackId]);
    $trackDuration = $stmt->fetchColumn();
    
    if (!$trackDuration) {
        throw new Exception('Nie znaleziono utworu.');
    }

    $completionPercentage = ($duration / $trackDuration) * 100;
    $wasSkipped = $completionPercentage < 30; // Uznajemy za pominięty jeśli odtworzono mniej niż 30%

    // Zapisz historię odtwarzania
    $stmt = $pdo->prepare("
        INSERT INTO listening_history (
            user_id,
            track_id,
            played_at,
            listening_duration,
            completion_percentage,
            platform,
            device_type,
            listening_context,
            source_playlist_id,
            source_album_id,
            was_skipped,
            skip_time,
            volume_level,
            audio_quality
        ) VALUES (
            ?, ?, CURRENT_TIMESTAMP, ?, ?, 
            ?, ?, ?, ?, ?, 
            ?, ?, ?, ?
        )
    ");

    $stmt->execute([
        $_SESSION['user_id'],
        $trackId,
        $duration,
        $completionPercentage,
        $context['platform'] ?? 'web',
        $context['device_type'] ?? null,
        $context['context'] ?? 'playlist',
        $context['playlist_id'] ?? null,
        $context['album_id'] ?? null,
        $wasSkipped,
        $wasSkipped ? $duration : null,
        $context['volume'] ?? 100,
        $context['quality'] ?? 'normal'
    ]);

    // Aktualizuj statystyki użytkownika
    $stmt = $pdo->prepare("
        UPDATE users 
        SET total_listening_time = total_listening_time + ?,
            total_songs_played = total_songs_played + 1,
            last_active_at = CURRENT_TIMESTAMP
        WHERE user_id = ?
    ");
    $stmt->execute([$duration, $_SESSION['user_id']]);

    // Aktualizuj statystyki utworu
    $stmt = $pdo->prepare("
        UPDATE tracks 
        SET play_count = play_count + 1,
            skip_count = skip_count + CASE WHEN ? THEN 1 ELSE 0 END
        WHERE track_id = ?
    ");
    $stmt->execute([$wasSkipped, $trackId]);

    return [
        'completion_percentage' => $completionPercentage,
        'was_skipped' => $wasSkipped
    ];
}

function getListeningHistory($pdo, $limit = 50, $offset = 0) {
    $stmt = $pdo->prepare("
        SELECT 
            h.history_id,
            h.played_at,
            h.listening_duration,
            h.completion_percentage,
            h.was_skipped,
            t.title as track_title,
            t.duration as track_duration,
            a.name as artist_name,
            al.title as album_title,
            p.name as playlist_name
        FROM listening_history h
        JOIN tracks t ON h.track_id = t.track_id
        JOIN artists a ON t.artist_id = a.artist_id
        LEFT JOIN albums al ON t.album_id = al.album_id
        LEFT JOIN playlists p ON h.source_playlist_id = p.playlist_id
        WHERE h.user_id = ?
        ORDER BY h.played_at DESC
        LIMIT ? OFFSET ?
    ");
    
    $stmt->execute([$_SESSION['user_id'], $limit, $offset]);
    return $stmt->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? '';

        switch ($action) {
            case 'log':
                if (!isset($data['track_id']) || !isset($data['duration'])) {
                    throw new Exception('Brak wymaganych danych.');
                }
                $result = logListening($pdo, $data['track_id'], $data['duration'], $data['context'] ?? []);
                echo json_encode([
                    'success' => true,
                    'message' => 'Odtworzenie zostało zarejestrowane.',
                    'data' => $result
                ]);
                break;

            case 'get_history':
                $limit = min(($data['limit'] ?? 50), 100); // Maksymalnie 100 wpisów
                $offset = max(($data['offset'] ?? 0), 0);
                $history = getListeningHistory($pdo, $limit, $offset);
                echo json_encode([
                    'success' => true,
                    'data' => $history
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