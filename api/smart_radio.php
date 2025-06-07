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

function generateRadioQueue($pdo, $seedType, $seedId, $limit = 50) {
    $queue = [];
    $usedTracks = [];
    $currentEnergy = null;
    $currentBPM = null;
    $currentKey = null;

    // Pobierz pierwszy utwór na podstawie seeda
    switch ($seedType) {
        case 'track':
            $stmt = $pdo->prepare("
                SELECT 
                    t.track_id,
                    t.title,
                    t.duration,
                    t.energy_level,
                    t.bpm,
                    t.key_signature,
                    t.valence,
                    a.name as artist_name,
                    a.artist_id,
                    al.title as album_title,
                    al.album_id,
                    al.cover_art_url
                FROM tracks t
                JOIN artists a ON t.artist_id = a.artist_id
                LEFT JOIN albums al ON t.album_id = al.album_id
                WHERE t.track_id = ?
            ");
            $stmt->execute([$seedId]);
            break;

        case 'artist':
            $stmt = $pdo->prepare("
                SELECT 
                    t.track_id,
                    t.title,
                    t.duration,
                    t.energy_level,
                    t.bpm,
                    t.key_signature,
                    t.valence,
                    a.name as artist_name,
                    a.artist_id,
                    al.title as album_title,
                    al.album_id,
                    al.cover_art_url
                FROM tracks t
                JOIN artists a ON t.artist_id = a.artist_id
                LEFT JOIN albums al ON t.album_id = al.album_id
                WHERE t.artist_id = ?
                ORDER BY t.play_count DESC
                LIMIT 1
            ");
            $stmt->execute([$seedId]);
            break;

        case 'genre':
            $stmt = $pdo->prepare("
                SELECT 
                    t.track_id,
                    t.title,
                    t.duration,
                    t.energy_level,
                    t.bpm,
                    t.key_signature,
                    t.valence,
                    a.name as artist_name,
                    a.artist_id,
                    al.title as album_title,
                    al.album_id,
                    al.cover_art_url
                FROM tracks t
                JOIN track_genres tg ON t.track_id = tg.track_id
                JOIN artists a ON t.artist_id = a.artist_id
                LEFT JOIN albums al ON t.album_id = al.album_id
                WHERE tg.genre_id = ?
                ORDER BY t.play_count DESC
                LIMIT 1
            ");
            $stmt->execute([$seedId]);
            break;

        case 'mood':
            $stmt = $pdo->prepare("
                SELECT 
                    t.track_id,
                    t.title,
                    t.duration,
                    t.energy_level,
                    t.bpm,
                    t.key_signature,
                    t.valence,
                    a.name as artist_name,
                    a.artist_id,
                    al.title as album_title,
                    al.album_id,
                    al.cover_art_url
                FROM tracks t
                JOIN track_moods tm ON t.track_id = tm.track_id
                JOIN artists a ON t.artist_id = a.artist_id
                LEFT JOIN albums al ON t.album_id = al.album_id
                WHERE tm.mood_id = ?
                ORDER BY tm.intensity DESC
                LIMIT 1
            ");
            $stmt->execute([$seedId]);
            break;

        default:
            throw new Exception('Nieprawidłowy typ seeda.');
    }

    $firstTrack = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$firstTrack) {
        throw new Exception('Nie znaleziono utworu początkowego.');
    }

    $queue[] = $firstTrack;
    $usedTracks[] = $firstTrack['track_id'];
    $currentEnergy = $firstTrack['energy_level'];
    $currentBPM = $firstTrack['bpm'];
    $currentKey = $firstTrack['key_signature'];

    // Generuj kolejne utwory z płynnym przejściem
    while (count($queue) < $limit) {
        $stmt = $pdo->prepare("
            SELECT 
                t.track_id,
                t.title,
                t.duration,
                t.energy_level,
                t.bpm,
                t.key_signature,
                t.valence,
                a.name as artist_name,
                a.artist_id,
                al.title as album_title,
                al.album_id,
                al.cover_art_url,
                (
                    CASE 
                        WHEN t.key_signature = ? THEN 1
                        WHEN t.key_signature IN (
                            SELECT compatible_key 
                            FROM key_compatibility 
                            WHERE base_key = ?
                        ) THEN 0.8
                        ELSE 0.4
                    END +
                    (1 - ABS(t.energy_level - ?) * 2) +
                    (1 - ABS(t.bpm - ?) / 40)
                ) as transition_score
            FROM tracks t
            JOIN artists a ON t.artist_id = a.artist_id
            LEFT JOIN albums al ON t.album_id = al.album_id
            WHERE t.track_id NOT IN (" . implode(',', array_fill(0, count($usedTracks), '?')) . ")
            AND ABS(t.energy_level - ?) <= 0.3
            AND ABS(t.bpm - ?) <= 30
            ORDER BY transition_score DESC, RAND()
            LIMIT 1
        ");

        $params = array_merge(
            [$currentKey, $currentKey, $currentEnergy, $currentBPM],
            $usedTracks,
            [$currentEnergy, $currentBPM]
        );
        $stmt->execute($params);
        $nextTrack = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$nextTrack) {
            // Jeśli nie znaleziono pasującego utworu, rozszerz kryteria
            $stmt = $pdo->prepare("
                SELECT 
                    t.track_id,
                    t.title,
                    t.duration,
                    t.energy_level,
                    t.bpm,
                    t.key_signature,
                    t.valence,
                    a.name as artist_name,
                    a.artist_id,
                    al.title as album_title,
                    al.album_id,
                    al.cover_art_url
                FROM tracks t
                JOIN artists a ON t.artist_id = a.artist_id
                LEFT JOIN albums al ON t.album_id = al.album_id
                WHERE t.track_id NOT IN (" . implode(',', array_fill(0, count($usedTracks), '?')) . ")
                ORDER BY RAND()
                LIMIT 1
            ");
            $stmt->execute($usedTracks);
            $nextTrack = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if ($nextTrack) {
            $queue[] = $nextTrack;
            $usedTracks[] = $nextTrack['track_id'];
            $currentEnergy = $nextTrack['energy_level'];
            $currentBPM = $nextTrack['bpm'];
            $currentKey = $nextTrack['key_signature'];
        } else {
            break; // Przerwij jeśli nie ma więcej utworów
        }
    }

    return $queue;
}

function getTransitionPoints($pdo, $track1Id, $track2Id) {
    // Pobierz informacje o utworach
    $stmt = $pdo->prepare("
        SELECT 
            track_id,
            duration,
            bpm,
            key_signature,
            energy_level
        FROM tracks
        WHERE track_id IN (?, ?)
    ");
    $stmt->execute([$track1Id, $track2Id]);
    $tracks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($tracks) !== 2) {
        throw new Exception('Nie znaleziono jednego z utworów.');
    }

    // Symulacja znalezienia punktów przejścia
    // W rzeczywistej implementacji należałoby użyć analizy audio
    return [
        'track1' => [
            'track_id' => $tracks[0]['track_id'],
            'fade_out_start' => $tracks[0]['duration'] - 10,
            'fade_out_duration' => 8
        ],
        'track2' => [
            'track_id' => $tracks[1]['track_id'],
            'fade_in_start' => 0,
            'fade_in_duration' => 8
        ],
        'overlap_duration' => 8,
        'recommended_crossfade' => min(
            10,
            max(
                4,
                abs($tracks[0]['bpm'] - $tracks[1]['bpm']) * 0.1
            )
        )
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? '';

        switch ($action) {
            case 'generate_queue':
                if (!isset($data['seed_type']) || !isset($data['seed_id'])) {
                    throw new Exception('Brak wymaganych parametrów.');
                }
                $limit = min(($data['limit'] ?? 50), 100);
                $queue = generateRadioQueue($pdo, $data['seed_type'], $data['seed_id'], $limit);
                echo json_encode([
                    'success' => true,
                    'data' => $queue
                ]);
                break;

            case 'get_transition':
                if (!isset($data['track1_id']) || !isset($data['track2_id'])) {
                    throw new Exception('Brak ID utworów.');
                }
                $transition = getTransitionPoints($pdo, $data['track1_id'], $data['track2_id']);
                echo json_encode([
                    'success' => true,
                    'data' => $transition
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