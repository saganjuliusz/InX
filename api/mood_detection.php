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

function analyzeMoodParameters($pdo, $trackId) {
    // Pobierz parametry utworu
    $stmt = $pdo->prepare("
        SELECT 
            energy_level,
            valence,
            danceability,
            instrumentalness,
            acousticness,
            speechiness,
            loudness,
            bpm
        FROM tracks
        WHERE track_id = ?
    ");
    $stmt->execute([$trackId]);
    $track = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$track) {
        throw new Exception('Utwór nie istnieje.');
    }

    // Algorytm określania nastroju na podstawie parametrów
    $moods = [];

    // Energetyczny/Spokojny
    if ($track['energy_level'] > 0.8) {
        $moods[] = ['mood_id' => 3, 'intensity' => $track['energy_level'], 'name' => 'Energetic'];
    } elseif ($track['energy_level'] < 0.3) {
        $moods[] = ['mood_id' => 4, 'intensity' => 1 - $track['energy_level'], 'name' => 'Relaxed'];
    }

    // Szczęśliwy/Smutny
    if ($track['valence'] > 0.7) {
        $moods[] = ['mood_id' => 1, 'intensity' => $track['valence'], 'name' => 'Happy'];
    } elseif ($track['valence'] < 0.3) {
        $moods[] = ['mood_id' => 2, 'intensity' => 1 - $track['valence'], 'name' => 'Sad'];
    }

    // Taneczny
    if ($track['danceability'] > 0.7) {
        $moods[] = ['mood_id' => 8, 'intensity' => $track['danceability'], 'name' => 'Party'];
    }

    // Skupienie
    if ($track['instrumentalness'] > 0.7 && $track['energy_level'] < 0.6) {
        $moods[] = ['mood_id' => 9, 'intensity' => $track['instrumentalness'], 'name' => 'Focus'];
    }

    // Nostalgiczny
    if ($track['acousticness'] > 0.7 && $track['valence'] < 0.5) {
        $moods[] = ['mood_id' => 7, 'intensity' => $track['acousticness'], 'name' => 'Nostalgic'];
    }

    // Romantyczny
    if ($track['valence'] > 0.5 && $track['energy_level'] < 0.6 && $track['acousticness'] > 0.4) {
        $moods[] = ['mood_id' => 5, 'intensity' => ($track['valence'] + $track['acousticness']) / 2, 'name' => 'Romantic'];
    }

    return $moods;
}

function updateTrackMoods($pdo, $trackId, $moods) {
    // Usuń istniejące nastroje
    $stmt = $pdo->prepare("DELETE FROM track_moods WHERE track_id = ?");
    $stmt->execute([$trackId]);

    // Dodaj nowe nastroje
    $stmt = $pdo->prepare("
        INSERT INTO track_moods (track_id, mood_id, intensity)
        VALUES (?, ?, ?)
    ");

    foreach ($moods as $mood) {
        $stmt->execute([$trackId, $mood['mood_id'], $mood['intensity']]);
    }
}

function getMoodBasedRecommendations($pdo, $moodId, $limit = 20) {
    $stmt = $pdo->prepare("
        SELECT 
            t.track_id,
            t.title,
            t.duration,
            t.energy_level,
            t.valence,
            t.bpm,
            a.name as artist_name,
            a.artist_id,
            al.title as album_title,
            al.album_id,
            al.cover_art_url,
            tm.intensity as mood_intensity
        FROM tracks t
        JOIN track_moods tm ON t.track_id = tm.track_id
        JOIN artists a ON t.artist_id = a.artist_id
        LEFT JOIN albums al ON t.album_id = al.album_id
        WHERE tm.mood_id = ?
        AND tm.intensity > 0.7
        ORDER BY tm.intensity DESC, t.play_count DESC
        LIMIT ?
    ");
    
    $stmt->execute([$moodId, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getMoodTransition($pdo, $currentMoodId, $targetMoodId, $limit = 10) {
    // Znajdź utwory, które płynnie przeprowadzą ze jednego nastroju do drugiego
    $stmt = $pdo->prepare("
        WITH RECURSIVE mood_transition AS (
            -- Początkowe utwory w obecnym nastroju
            SELECT 
                t.track_id,
                t.title,
                t.energy_level,
                t.valence,
                tm1.intensity as current_mood_intensity,
                tm2.intensity as target_mood_intensity,
                1 as position
            FROM tracks t
            JOIN track_moods tm1 ON t.track_id = tm1.track_id AND tm1.mood_id = ?
            LEFT JOIN track_moods tm2 ON t.track_id = tm2.track_id AND tm2.mood_id = ?
            WHERE tm1.intensity > 0.5
            
            UNION ALL
            
            -- Znajdź kolejne utwory z płynnym przejściem
            SELECT 
                t2.track_id,
                t2.title,
                t2.energy_level,
                t2.valence,
                tm1.intensity,
                tm2.intensity,
                mt.position + 1
            FROM mood_transition mt
            JOIN tracks t2 ON ABS(t2.energy_level - mt.energy_level) < 0.2
                AND ABS(t2.valence - mt.valence) < 0.2
            JOIN track_moods tm1 ON t2.track_id = tm1.track_id AND tm1.mood_id = ?
            LEFT JOIN track_moods tm2 ON t2.track_id = tm2.track_id AND tm2.mood_id = ?
            WHERE t2.track_id != mt.track_id
            AND mt.position < ?
        )
        SELECT DISTINCT
            mt.track_id,
            mt.title,
            mt.energy_level,
            mt.valence,
            mt.current_mood_intensity,
            mt.target_mood_intensity,
            a.name as artist_name,
            a.artist_id,
            al.title as album_title,
            al.cover_art_url
        FROM mood_transition mt
        JOIN artists a ON a.artist_id = (
            SELECT artist_id FROM tracks WHERE track_id = mt.track_id
        )
        LEFT JOIN albums al ON al.album_id = (
            SELECT album_id FROM tracks WHERE track_id = mt.track_id
        )
        ORDER BY mt.position
        LIMIT ?
    ");

    $stmt->execute([
        $currentMoodId, 
        $targetMoodId, 
        $currentMoodId, 
        $targetMoodId, 
        $limit,
        $limit
    ]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? '';

        switch ($action) {
            case 'analyze_track':
                if (!isset($data['track_id'])) {
                    throw new Exception('Brak ID utworu.');
                }
                $moods = analyzeMoodParameters($pdo, $data['track_id']);
                updateTrackMoods($pdo, $data['track_id'], $moods);
                echo json_encode([
                    'success' => true,
                    'message' => 'Analiza nastroju została zakończona.',
                    'data' => $moods
                ]);
                break;

            case 'get_mood_recommendations':
                if (!isset($data['mood_id'])) {
                    throw new Exception('Brak ID nastroju.');
                }
                $limit = min(($data['limit'] ?? 20), 50);
                $recommendations = getMoodBasedRecommendations($pdo, $data['mood_id'], $limit);
                echo json_encode([
                    'success' => true,
                    'data' => $recommendations
                ]);
                break;

            case 'get_mood_transition':
                if (!isset($data['current_mood_id']) || !isset($data['target_mood_id'])) {
                    throw new Exception('Brak wymaganych ID nastrojów.');
                }
                $limit = min(($data['limit'] ?? 10), 20);
                $transition = getMoodTransition(
                    $pdo, 
                    $data['current_mood_id'], 
                    $data['target_mood_id'], 
                    $limit
                );
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