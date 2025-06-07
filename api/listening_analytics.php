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

function trackPlaybackStats($pdo, $userId, $timeRange = '30 days') {
    // Pobierz statystyki odtwarzania
    $stmt = $pdo->prepare("
        SELECT 
            t.track_id,
            t.title,
            t.artist,
            t.genre,
            COUNT(*) as play_count,
            MIN(ph.played_at) as first_played,
            MAX(ph.played_at) as last_played,
            AVG(TIMESTAMPDIFF(SECOND, ph.started_at, ph.ended_at)) as avg_listen_time
        FROM playback_history ph
        JOIN tracks t ON ph.track_id = t.track_id
        WHERE ph.user_id = ?
        AND ph.played_at >= DATE_SUB(NOW(), INTERVAL ?)
        GROUP BY t.track_id
        ORDER BY play_count DESC
    ");
    
    $stmt->execute([$userId, $timeRange]);
    $trackStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Pobierz statystyki gatunków
    $stmt = $pdo->prepare("
        SELECT 
            t.genre,
            COUNT(DISTINCT t.track_id) as unique_tracks,
            COUNT(*) as total_plays,
            SUM(TIMESTAMPDIFF(SECOND, ph.started_at, ph.ended_at)) as total_listen_time
        FROM playback_history ph
        JOIN tracks t ON ph.track_id = t.track_id
        WHERE ph.user_id = ?
        AND ph.played_at >= DATE_SUB(NOW(), INTERVAL ?)
        GROUP BY t.genre
        ORDER BY total_plays DESC
    ");
    
    $stmt->execute([$userId, $timeRange]);
    $genreStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Pobierz statystyki dzienne
    $stmt = $pdo->prepare("
        SELECT 
            DATE(ph.played_at) as date,
            COUNT(DISTINCT t.track_id) as unique_tracks,
            COUNT(*) as total_plays,
            SUM(TIMESTAMPDIFF(SECOND, ph.started_at, ph.ended_at)) as listen_time
        FROM playback_history ph
        JOIN tracks t ON ph.track_id = t.track_id
        WHERE ph.user_id = ?
        AND ph.played_at >= DATE_SUB(NOW(), INTERVAL ?)
        GROUP BY DATE(ph.played_at)
        ORDER BY date DESC
    ");
    
    $stmt->execute([$userId, $timeRange]);
    $dailyStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stats = [
        'time_range' => $timeRange,
        'total_stats' => [
            'unique_tracks' => count($trackStats),
            'total_plays' => array_sum(array_column($trackStats, 'play_count')),
            'total_genres' => count($genreStats),
            'total_listen_time' => array_sum(array_column($dailyStats, 'listen_time'))
        ],
        'track_stats' => array_map(function($track) {
            return [
                'track_id' => $track['track_id'],
                'title' => $track['title'],
                'artist' => $track['artist'],
                'genre' => $track['genre'],
                'stats' => [
                    'play_count' => $track['play_count'],
                    'first_played' => $track['first_played'],
                    'last_played' => $track['last_played'],
                    'avg_listen_time' => round($track['avg_listen_time'])
                ]
            ];
        }, $trackStats),
        'genre_stats' => array_map(function($genre) {
            return [
                'genre' => $genre['genre'],
                'stats' => [
                    'unique_tracks' => $genre['unique_tracks'],
                    'total_plays' => $genre['total_plays'],
                    'total_listen_time' => $genre['total_listen_time'],
                    'percentage' => 0 // Zostanie zaktualizowane poniżej
                ]
            ];
        }, $genreStats),
        'daily_stats' => $dailyStats
    ];

    // Oblicz procenty dla gatunków
    $totalPlays = $stats['total_stats']['total_plays'];
    foreach ($stats['genre_stats'] as &$genre) {
        $genre['stats']['percentage'] = $totalPlays > 0 ? 
            round(($genre['stats']['total_plays'] / $totalPlays) * 100, 2) : 0;
    }

    // Zapisz statystyki
    $stmt = $pdo->prepare("
        INSERT INTO playback_statistics (
            user_id,
            time_range,
            created_at,
            total_stats,
            track_stats,
            genre_stats,
            daily_stats
        ) VALUES (?, ?, CURRENT_TIMESTAMP, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $userId,
        $timeRange,
        json_encode($stats['total_stats']),
        json_encode($stats['track_stats']),
        json_encode($stats['genre_stats']),
        json_encode($stats['daily_stats'])
    ]);

    return $stats;
}

function generateListeningHistory($pdo, $userId, $params = []) {
    // Parametry domyślne
    $defaults = [
        'limit' => 50,
        'offset' => 0,
        'sort' => 'desc',
        'include_details' => true,
        'group_by' => null // null, 'day', 'week', 'month'
    ];

    $params = array_merge($defaults, $params);

    // Podstawowe zapytanie
    $baseQuery = "
        SELECT 
            ph.history_id,
            ph.track_id,
            ph.played_at,
            ph.started_at,
            ph.ended_at,
            t.title,
            t.artist,
            t.album,
            t.genre,
            t.duration
        FROM playback_history ph
        JOIN tracks t ON ph.track_id = t.track_id
        WHERE ph.user_id = ?
    ";

    // Grupowanie (jeśli wymagane)
    if ($params['group_by']) {
        switch ($params['group_by']) {
            case 'day':
                $groupBy = 'DATE(ph.played_at)';
                break;
            case 'week':
                $groupBy = 'YEARWEEK(ph.played_at)';
                break;
            case 'month':
                $groupBy = 'DATE_FORMAT(ph.played_at, "%Y-%m")';
                break;
        }

        $query = "
            SELECT 
                $groupBy as period,
                COUNT(DISTINCT ph.track_id) as unique_tracks,
                COUNT(*) as total_plays,
                GROUP_CONCAT(DISTINCT t.genre) as genres,
                SUM(TIMESTAMPDIFF(SECOND, ph.started_at, ph.ended_at)) as total_listen_time
            FROM playback_history ph
            JOIN tracks t ON ph.track_id = t.track_id
            WHERE ph.user_id = ?
            GROUP BY $groupBy
            ORDER BY period " . ($params['sort'] === 'desc' ? 'DESC' : 'ASC') . "
            LIMIT ? OFFSET ?
        ";
    } else {
        $query = $baseQuery . "
            ORDER BY ph.played_at " . ($params['sort'] === 'desc' ? 'DESC' : 'ASC') . "
            LIMIT ? OFFSET ?
        ";
    }

    // Wykonaj zapytanie
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        $userId,
        $params['limit'],
        $params['offset']
    ]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Pobierz dodatkowe szczegóły dla każdego utworu (jeśli wymagane)
    if ($params['include_details'] && !$params['group_by']) {
        foreach ($history as &$entry) {
            // Pobierz tagi
            $stmt = $pdo->prepare("
                SELECT tag_name
                FROM track_tags
                WHERE track_id = ?
            ");
            $stmt->execute([$entry['track_id']]);
            $entry['tags'] = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Pobierz ocenę użytkownika
            $stmt = $pdo->prepare("
                SELECT rating
                FROM track_ratings
                WHERE track_id = ? AND user_id = ?
            ");
            $stmt->execute([$entry['track_id'], $userId]);
            $entry['user_rating'] = $stmt->fetchColumn();

            // Oblicz procent odsłuchania
            $listenDuration = strtotime($entry['ended_at']) - strtotime($entry['started_at']);
            $entry['listen_percentage'] = $entry['duration'] > 0 ? 
                round(($listenDuration / $entry['duration']) * 100, 2) : 0;
        }
    }

    // Przygotuj wynik
    $result = [
        'params' => $params,
        'total_entries' => $stmt->rowCount(),
        'history' => $history
    ];

    // Zapisz wywołanie historii
    $stmt = $pdo->prepare("
        INSERT INTO history_queries (
            user_id,
            query_params,
            created_at,
            results_count
        ) VALUES (?, ?, CURRENT_TIMESTAMP, ?)
    ");

    $stmt->execute([
        $userId,
        json_encode($params),
        count($history)
    ]);

    return $result;
}

function createActivityReports($pdo, $userId, $reportType, $params = []) {
    // Parametry domyślne
    $defaults = [
        'time_range' => '30 days',
        'include_graphs' => true,
        'format' => 'detailed', // summary, detailed
        'metrics' => ['plays', 'time', 'genres', 'artists']
    ];

    $params = array_merge($defaults, $params);

    $report = [
        'report_id' => uniqid('report_'),
        'type' => $reportType,
        'generated_at' => date('Y-m-d H:i:s'),
        'time_range' => $params['time_range'],
        'metrics' => []
    ];

    switch ($reportType) {
        case 'listening_habits':
            // Analiza nawyków słuchania
            $stmt = $pdo->prepare("
                SELECT 
                    HOUR(played_at) as hour,
                    COUNT(*) as play_count
                FROM playback_history
                WHERE user_id = ?
                AND played_at >= DATE_SUB(NOW(), INTERVAL ?)
                GROUP BY HOUR(played_at)
                ORDER BY hour
            ");
            
            $stmt->execute([$userId, $params['time_range']]);
            $report['metrics']['hourly_distribution'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            // Analiza dni tygodnia
            $stmt = $pdo->prepare("
                SELECT 
                    DAYNAME(played_at) as day,
                    COUNT(*) as play_count
                FROM playback_history
                WHERE user_id = ?
                AND played_at >= DATE_SUB(NOW(), INTERVAL ?)
                GROUP BY DAYNAME(played_at)
                ORDER BY DAYOFWEEK(played_at)
            ");
            
            $stmt->execute([$userId, $params['time_range']]);
            $report['metrics']['daily_distribution'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            // Długość sesji słuchania
            $stmt = $pdo->prepare("
                SELECT 
                    TIMESTAMPDIFF(MINUTE, started_at, ended_at) as session_length,
                    COUNT(*) as session_count
                FROM playback_history
                WHERE user_id = ?
                AND played_at >= DATE_SUB(NOW(), INTERVAL ?)
                GROUP BY session_length
                ORDER BY session_length
            ");
            
            $stmt->execute([$userId, $params['time_range']]);
            $report['metrics']['session_lengths'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            break;

        case 'genre_analysis':
            // Analiza gatunków
            $stmt = $pdo->prepare("
                SELECT 
                    t.genre,
                    COUNT(*) as play_count,
                    COUNT(DISTINCT t.track_id) as unique_tracks,
                    SUM(TIMESTAMPDIFF(SECOND, ph.started_at, ph.ended_at)) as total_time
                FROM playback_history ph
                JOIN tracks t ON ph.track_id = t.track_id
                WHERE ph.user_id = ?
                AND ph.played_at >= DATE_SUB(NOW(), INTERVAL ?)
                GROUP BY t.genre
                ORDER BY play_count DESC
            ");
            
            $stmt->execute([$userId, $params['time_range']]);
            $report['metrics']['genre_stats'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Korelacje między gatunkami
            $stmt = $pdo->prepare("
                SELECT 
                    t1.genre as genre1,
                    t2.genre as genre2,
                    COUNT(*) as correlation_strength
                FROM playback_history ph1
                JOIN tracks t1 ON ph1.track_id = t1.track_id
                JOIN playback_history ph2 ON ph1.user_id = ph2.user_id
                JOIN tracks t2 ON ph2.track_id = t2.track_id
                WHERE ph1.user_id = ?
                AND ph1.played_at >= DATE_SUB(NOW(), INTERVAL ?)
                AND ph2.played_at >= ph1.played_at
                AND ph2.played_at <= DATE_ADD(ph1.played_at, INTERVAL 1 HOUR)
                AND t1.genre != t2.genre
                GROUP BY t1.genre, t2.genre
                HAVING correlation_strength > 5
                ORDER BY correlation_strength DESC
            ");
            
            $stmt->execute([$userId, $params['time_range']]);
            $report['metrics']['genre_correlations'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'discovery_patterns':
            // Analiza odkrywania nowej muzyki
            $stmt = $pdo->prepare("
                SELECT 
                    DATE(first_played) as discovery_date,
                    COUNT(*) as new_tracks
                FROM (
                    SELECT 
                        track_id,
                        MIN(played_at) as first_played
                    FROM playback_history
                    WHERE user_id = ?
                    AND played_at >= DATE_SUB(NOW(), INTERVAL ?)
                    GROUP BY track_id
                ) first_plays
                GROUP BY DATE(first_played)
                ORDER BY discovery_date
            ");
            
            $stmt->execute([$userId, $params['time_range']]);
            $report['metrics']['discovery_timeline'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            // Źródła odkryć (np. rekomendacje, playlisty)
            $stmt = $pdo->prepare("
                SELECT 
                    discovery_source,
                    COUNT(*) as count
                FROM track_discoveries
                WHERE user_id = ?
                AND discovered_at >= DATE_SUB(NOW(), INTERVAL ?)
                GROUP BY discovery_source
                ORDER BY count DESC
            ");
            
            $stmt->execute([$userId, $params['time_range']]);
            $report['metrics']['discovery_sources'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            break;
    }

    // Zapisz raport
    $stmt = $pdo->prepare("
        INSERT INTO activity_reports (
            user_id,
            report_id,
            report_type,
            time_range,
            created_at,
            report_data
        ) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP, ?)
    ");

    $stmt->execute([
        $userId,
        $report['report_id'],
        $reportType,
        $params['time_range'],
        json_encode($report)
    ]);

    return $report;
}

function analyzePreferences($pdo, $userId, $params = []) {
    // Parametry domyślne
    $defaults = [
        'time_range' => '90 days',
        'min_plays' => 3,
        'include_features' => true
    ];

    $params = array_merge($defaults, $params);

    // Pobierz podstawowe preferencje
    $preferences = [
        'favorite_genres' => [],
        'favorite_artists' => [],
        'favorite_tracks' => [],
        'listening_patterns' => [],
        'mood_preferences' => [],
        'feature_preferences' => []
    ];

    // Analiza ulubionych gatunków
    $stmt = $pdo->prepare("
        SELECT 
            t.genre,
            COUNT(*) as play_count,
            AVG(COALESCE(tr.rating, 0)) as avg_rating
        FROM playback_history ph
        JOIN tracks t ON ph.track_id = t.track_id
        LEFT JOIN track_ratings tr ON t.track_id = tr.track_id AND tr.user_id = ph.user_id
        WHERE ph.user_id = ?
        AND ph.played_at >= DATE_SUB(NOW(), INTERVAL ?)
        GROUP BY t.genre
        HAVING play_count >= ?
        ORDER BY play_count DESC, avg_rating DESC
    ");
    
    $stmt->execute([$userId, $params['time_range'], $params['min_plays']]);
    $preferences['favorite_genres'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Analiza ulubionych artystów
    $stmt = $pdo->prepare("
        SELECT 
            t.artist,
            COUNT(*) as play_count,
            COUNT(DISTINCT t.track_id) as unique_tracks,
            AVG(COALESCE(tr.rating, 0)) as avg_rating
        FROM playback_history ph
        JOIN tracks t ON ph.track_id = t.track_id
        LEFT JOIN track_ratings tr ON t.track_id = tr.track_id AND tr.user_id = ph.user_id
        WHERE ph.user_id = ?
        AND ph.played_at >= DATE_SUB(NOW(), INTERVAL ?)
        GROUP BY t.artist
        HAVING play_count >= ?
        ORDER BY play_count DESC, avg_rating DESC
    ");
    
    $stmt->execute([$userId, $params['time_range'], $params['min_plays']]);
    $preferences['favorite_artists'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Analiza ulubionych utworów
    $stmt = $pdo->prepare("
        SELECT 
            t.track_id,
            t.title,
            t.artist,
            t.genre,
            COUNT(*) as play_count,
            MAX(tr.rating) as user_rating
        FROM playback_history ph
        JOIN tracks t ON ph.track_id = t.track_id
        LEFT JOIN track_ratings tr ON t.track_id = tr.track_id AND tr.user_id = ph.user_id
        WHERE ph.user_id = ?
        AND ph.played_at >= DATE_SUB(NOW(), INTERVAL ?)
        GROUP BY t.track_id
        HAVING play_count >= ?
        ORDER BY play_count DESC, user_rating DESC
    ");
    
    $stmt->execute([$userId, $params['time_range'], $params['min_plays']]);
    $preferences['favorite_tracks'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Analiza wzorców słuchania
    $stmt = $pdo->prepare("
        SELECT 
            HOUR(played_at) as hour,
            DAYNAME(played_at) as day,
            COUNT(*) as play_count
        FROM playback_history
        WHERE user_id = ?
        AND played_at >= DATE_SUB(NOW(), INTERVAL ?)
        GROUP BY HOUR(played_at), DAYNAME(played_at)
        ORDER BY play_count DESC
    ");
    
    $stmt->execute([$userId, $params['time_range']]);
    $preferences['listening_patterns'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Analiza preferencji nastroju
    $stmt = $pdo->prepare("
        SELECT 
            t.mood,
            COUNT(*) as play_count,
            AVG(COALESCE(tr.rating, 0)) as avg_rating
        FROM playback_history ph
        JOIN tracks t ON ph.track_id = t.track_id
        LEFT JOIN track_ratings tr ON t.track_id = tr.track_id AND tr.user_id = ph.user_id
        WHERE ph.user_id = ?
        AND ph.played_at >= DATE_SUB(NOW(), INTERVAL ?)
        GROUP BY t.mood
        ORDER BY play_count DESC
    ");
    
    $stmt->execute([$userId, $params['time_range']]);
    $preferences['mood_preferences'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($params['include_features']) {
        // Analiza preferencji cech audio
        $stmt = $pdo->prepare("
            SELECT 
                AVG(t.tempo) as avg_tempo,
                AVG(t.energy_level) as avg_energy,
                AVG(t.danceability) as avg_danceability,
                AVG(t.acousticness) as avg_acousticness,
                AVG(t.instrumentalness) as avg_instrumentalness,
                AVG(t.valence) as avg_valence
            FROM playback_history ph
            JOIN tracks t ON ph.track_id = t.track_id
            WHERE ph.user_id = ?
            AND ph.played_at >= DATE_SUB(NOW(), INTERVAL ?)
        ");
        
        $stmt->execute([$userId, $params['time_range']]);
        $preferences['feature_preferences'] = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Zapisz analizę preferencji
    $stmt = $pdo->prepare("
        INSERT INTO user_preferences (
            user_id,
            time_range,
            created_at,
            preferences_data
        ) VALUES (?, ?, CURRENT_TIMESTAMP, ?)
    ");

    $stmt->execute([
        $userId,
        $params['time_range'],
        json_encode($preferences)
    ]);

    return $preferences;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? '';

        switch ($action) {
            case 'track_stats':
                $timeRange = $data['time_range'] ?? '30 days';
                $stats = trackPlaybackStats($pdo, $_SESSION['user_id'], $timeRange);
                echo json_encode([
                    'success' => true,
                    'data' => $stats
                ]);
                break;

            case 'get_history':
                $params = $data['parameters'] ?? [];
                $history = generateListeningHistory($pdo, $_SESSION['user_id'], $params);
                echo json_encode([
                    'success' => true,
                    'data' => $history
                ]);
                break;

            case 'create_report':
                if (!isset($data['report_type'])) {
                    throw new Exception('Brak typu raportu.');
                }
                $params = $data['parameters'] ?? [];
                $report = createActivityReports($pdo, $_SESSION['user_id'], $data['report_type'], $params);
                echo json_encode([
                    'success' => true,
                    'data' => $report
                ]);
                break;

            case 'analyze_preferences':
                $params = $data['parameters'] ?? [];
                $preferences = analyzePreferences($pdo, $_SESSION['user_id'], $params);
                echo json_encode([
                    'success' => true,
                    'data' => $preferences
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