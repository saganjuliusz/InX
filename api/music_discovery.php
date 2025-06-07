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

function generateRecommendations($pdo, $userId, $params = []) {
    // Parametry domyślne
    $defaults = [
        'limit' => 20,
        'based_on' => 'all', // all, recent, favorites
        'genres' => [],
        'mood' => null,
        'tempo_range' => null,
        'exclude_listened' => true
    ];

    $params = array_merge($defaults, $params);

    // Pobierz historię słuchania
    $stmt = $pdo->prepare("
        SELECT 
            t.track_id,
            t.title,
            t.artist,
            t.genre,
            t.mood,
            t.tempo,
            COUNT(*) as play_count,
            MAX(ph.played_at) as last_played
        FROM playback_history ph
        JOIN tracks t ON ph.track_id = t.track_id
        WHERE ph.user_id = ?
        GROUP BY t.track_id
        ORDER BY last_played DESC
        LIMIT 100
    ");
    $stmt->execute([$userId]);
    $listeningHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Symulacja generowania rekomendacji
    $recommendations = [
        'recommendation_id' => uniqid('rec_'),
        'timestamp' => time(),
        'based_on' => [
            'listening_history' => true,
            'user_preferences' => true,
            'genre_affinity' => true,
            'mood_analysis' => true
        ],
        'tracks' => []
    ];

    // Pobierz podobne utwory na podstawie historii
    $genres = array_unique(array_column($listeningHistory, 'genre'));
    $moods = array_unique(array_column($listeningHistory, 'mood'));

    $stmt = $pdo->prepare("
        SELECT 
            t.*,
            (
                CASE 
                    WHEN t.genre IN (" . implode(',', array_fill(0, count($genres), '?')) . ") THEN 2
                    ELSE 0
                END +
                CASE 
                    WHEN t.mood IN (" . implode(',', array_fill(0, count($moods), '?')) . ") THEN 1
                    ELSE 0
                END
            ) as relevance_score
        FROM tracks t
        WHERE t.track_id NOT IN (
            SELECT track_id FROM playback_history WHERE user_id = ?
        )
        HAVING relevance_score > 0
        ORDER BY relevance_score DESC, RAND()
        LIMIT ?
    ");

    $params = array_merge($genres, $moods);
    $params[] = $userId;
    $params[] = $params['limit'];
    $stmt->execute($params);
    $recommendedTracks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($recommendedTracks as $track) {
        $recommendations['tracks'][] = [
            'track_id' => $track['track_id'],
            'title' => $track['title'],
            'artist' => $track['artist'],
            'genre' => $track['genre'],
            'mood' => $track['mood'],
            'confidence_score' => rand(70, 100) / 100,
            'recommendation_factors' => [
                'genre_match' => rand(80, 100) / 100,
                'mood_match' => rand(75, 95) / 100,
                'style_similarity' => rand(70, 90) / 100
            ]
        ];
    }

    // Zapisz wygenerowane rekomendacje
    $stmt = $pdo->prepare("
        INSERT INTO recommendations (
            user_id,
            recommendation_id,
            created_at,
            parameters,
            recommendations_data
        ) VALUES (?, ?, CURRENT_TIMESTAMP, ?, ?)
    ");

    $stmt->execute([
        $userId,
        $recommendations['recommendation_id'],
        json_encode($params),
        json_encode($recommendations)
    ]);

    return $recommendations;
}

function findSimilarTracks($pdo, $trackId, $limit = 10) {
    // Sprawdź czy utwór istnieje
    $stmt = $pdo->prepare("
        SELECT 
            t.track_id,
            t.title,
            t.artist,
            t.genre,
            t.mood,
            t.tempo,
            t.key_signature,
            t.energy_level
        FROM tracks t
        WHERE t.track_id = ?
    ");
    $stmt->execute([$trackId]);
    $sourceTrack = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sourceTrack) {
        throw new Exception('Utwór nie istnieje.');
    }

    // Znajdź podobne utwory
    $stmt = $pdo->prepare("
        SELECT 
            t.*,
            (
                CASE WHEN t.genre = ? THEN 3 ELSE 0 END +
                CASE WHEN t.mood = ? THEN 2 ELSE 0 END +
                CASE WHEN ABS(t.tempo - ?) <= 10 THEN 1 ELSE 0 END +
                CASE WHEN t.key_signature = ? THEN 1 ELSE 0 END
            ) as similarity_score
        FROM tracks t
        WHERE t.track_id != ?
        HAVING similarity_score > 0
        ORDER BY similarity_score DESC, RAND()
        LIMIT ?
    ");

    $stmt->execute([
        $sourceTrack['genre'],
        $sourceTrack['mood'],
        $sourceTrack['tempo'],
        $sourceTrack['key_signature'],
        $trackId,
        $limit
    ]);
    $similarTracks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [
        'source_track' => $sourceTrack,
        'similar_tracks' => array_map(function($track) use ($sourceTrack) {
            return [
                'track_id' => $track['track_id'],
                'title' => $track['title'],
                'artist' => $track['artist'],
                'similarity_score' => $track['similarity_score'] / 7, // Normalizacja do 0-1
                'matching_factors' => [
                    'genre' => $track['genre'] === $sourceTrack['genre'],
                    'mood' => $track['mood'] === $sourceTrack['mood'],
                    'tempo' => abs($track['tempo'] - $sourceTrack['tempo']) <= 10,
                    'key' => $track['key_signature'] === $sourceTrack['key_signature']
                ]
            ];
        }, $similarTracks)
    ];

    return $result;
}

function analyzeListeningPatterns($pdo, $userId, $timeRange = '30 days') {
    // Pobierz historię słuchania
    $stmt = $pdo->prepare("
        SELECT 
            t.track_id,
            t.title,
            t.artist,
            t.genre,
            t.mood,
            t.tempo,
            ph.played_at,
            DAYNAME(ph.played_at) as day_of_week,
            HOUR(ph.played_at) as hour_of_day
        FROM playback_history ph
        JOIN tracks t ON ph.track_id = t.track_id
        WHERE ph.user_id = ?
        AND ph.played_at >= DATE_SUB(NOW(), INTERVAL ?)
        ORDER BY ph.played_at DESC
    ");
    
    $stmt->execute([$userId, $timeRange]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Analiza wzorców
    $patterns = [
        'total_tracks' => count($history),
        'unique_tracks' => count(array_unique(array_column($history, 'track_id'))),
        'genre_distribution' => [],
        'mood_distribution' => [],
        'hourly_activity' => array_fill(0, 24, 0),
        'daily_activity' => array_fill(0, 7, 0),
        'tempo_preferences' => [
            'slow' => 0,
            'medium' => 0,
            'fast' => 0
        ],
        'listening_sessions' => []
    ];

    foreach ($history as $entry) {
        // Analiza gatunków
        $patterns['genre_distribution'][$entry['genre']] = 
            ($patterns['genre_distribution'][$entry['genre']] ?? 0) + 1;

        // Analiza nastrojów
        $patterns['mood_distribution'][$entry['mood']] = 
            ($patterns['mood_distribution'][$entry['mood']] ?? 0) + 1;

        // Analiza aktywności
        $patterns['hourly_activity'][$entry['hour_of_day']]++;
        $patterns['daily_activity'][date('N', strtotime($entry['played_at'])) - 1]++;

        // Analiza tempa
        if ($entry['tempo'] < 90) $patterns['tempo_preferences']['slow']++;
        elseif ($entry['tempo'] < 120) $patterns['tempo_preferences']['medium']++;
        else $patterns['tempo_preferences']['fast']++;
    }

    // Normalizacja rozkładów
    $patterns['genre_distribution'] = array_map(function($count) use ($patterns) {
        return $count / $patterns['total_tracks'];
    }, $patterns['genre_distribution']);

    $patterns['mood_distribution'] = array_map(function($count) use ($patterns) {
        return $count / $patterns['total_tracks'];
    }, $patterns['mood_distribution']);

    // Wykrywanie sesji słuchania
    $currentSession = [];
    $lastTimestamp = null;
    foreach ($history as $entry) {
        $timestamp = strtotime($entry['played_at']);
        
        if ($lastTimestamp === null || $timestamp - $lastTimestamp > 3600) {
            if (!empty($currentSession)) {
                $patterns['listening_sessions'][] = $currentSession;
            }
            $currentSession = [];
        }
        
        $currentSession[] = [
            'track_id' => $entry['track_id'],
            'timestamp' => $timestamp
        ];
        
        $lastTimestamp = $timestamp;
    }

    if (!empty($currentSession)) {
        $patterns['listening_sessions'][] = $currentSession;
    }

    // Zapisz analizę
    $stmt = $pdo->prepare("
        INSERT INTO listening_patterns (
            user_id,
            time_range,
            created_at,
            patterns_data
        ) VALUES (?, ?, CURRENT_TIMESTAMP, ?)
    ");

    $stmt->execute([
        $userId,
        $timeRange,
        json_encode($patterns)
    ]);

    return $patterns;
}

function createPersonalizedPlaylist($pdo, $userId, $params = []) {
    // Parametry domyślne
    $defaults = [
        'name' => 'Spersonalizowana playlista',
        'description' => 'Automatycznie wygenerowana playlista',
        'target_length' => 60, // minuty
        'based_on' => 'all', // all, recent, favorites
        'include_new' => true,
        'mood_balance' => true
    ];

    $params = array_merge($defaults, $params);

    // Pobierz preferencje użytkownika
    $patterns = analyzeListeningPatterns($pdo, $userId);
    
    // Utwórz playlistę
    $playlist = [
        'playlist_id' => uniqid('pl_'),
        'name' => $params['name'],
        'description' => $params['description'],
        'created_at' => time(),
        'tracks' => []
    ];

    // Wybierz utwory na podstawie preferencji
    $topGenres = array_keys(array_slice($patterns['genre_distribution'], 0, 3, true));
    $topMoods = array_keys(array_slice($patterns['mood_distribution'], 0, 3, true));

    $stmt = $pdo->prepare("
        SELECT 
            t.*,
            CASE 
                WHEN t.genre IN (" . implode(',', array_fill(0, count($topGenres), '?')) . ") THEN 2
                WHEN t.mood IN (" . implode(',', array_fill(0, count($topMoods), '?')) . ") THEN 1
                ELSE 0
            END as relevance_score
        FROM tracks t
        WHERE t.duration > 0
        HAVING relevance_score > 0
        ORDER BY relevance_score DESC, RAND()
    ");

    $stmt->execute(array_merge($topGenres, $topMoods));
    $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalDuration = 0;
    $targetDuration = $params['target_length'] * 60; // konwersja na sekundy

    foreach ($candidates as $track) {
        if ($totalDuration + $track['duration'] <= $targetDuration) {
            $playlist['tracks'][] = [
                'track_id' => $track['track_id'],
                'title' => $track['title'],
                'artist' => $track['artist'],
                'duration' => $track['duration'],
                'genre' => $track['genre'],
                'mood' => $track['mood'],
                'position' => count($playlist['tracks']) + 1
            ];
            $totalDuration += $track['duration'];
        }
    }

    // Zapisz playlistę
    $stmt = $pdo->prepare("
        INSERT INTO playlists (
            playlist_id,
            user_id,
            name,
            description,
            created_at,
            is_personalized,
            metadata
        ) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP, true, ?)
    ");

    $stmt->execute([
        $playlist['playlist_id'],
        $userId,
        $playlist['name'],
        $playlist['description'],
        json_encode($playlist)
    ]);

    // Dodaj utwory do playlisty
    $stmt = $pdo->prepare("
        INSERT INTO playlist_tracks (
            playlist_id,
            track_id,
            position
        ) VALUES (?, ?, ?)
    ");

    foreach ($playlist['tracks'] as $track) {
        $stmt->execute([
            $playlist['playlist_id'],
            $track['track_id'],
            $track['position']
        ]);
    }

    return $playlist;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? '';

        switch ($action) {
            case 'generate_recommendations':
                $params = $data['parameters'] ?? [];
                $recommendations = generateRecommendations($pdo, $_SESSION['user_id'], $params);
                echo json_encode([
                    'success' => true,
                    'data' => $recommendations
                ]);
                break;

            case 'find_similar':
                if (!isset($data['track_id'])) {
                    throw new Exception('Brak ID utworu.');
                }
                $limit = $data['limit'] ?? 10;
                $similar = findSimilarTracks($pdo, $data['track_id'], $limit);
                echo json_encode([
                    'success' => true,
                    'data' => $similar
                ]);
                break;

            case 'analyze_patterns':
                $timeRange = $data['time_range'] ?? '30 days';
                $patterns = analyzeListeningPatterns($pdo, $_SESSION['user_id'], $timeRange);
                echo json_encode([
                    'success' => true,
                    'data' => $patterns
                ]);
                break;

            case 'create_playlist':
                $params = $data['parameters'] ?? [];
                $playlist = createPersonalizedPlaylist($pdo, $_SESSION['user_id'], $params);
                echo json_encode([
                    'success' => true,
                    'data' => $playlist
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