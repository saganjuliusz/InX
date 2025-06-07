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

function trackUserActivity($pdo, $userId, $activityType, $data = []) {
    // Walidacja typu aktywności
    $validTypes = [
        'playlist_create',
        'playlist_edit',
        'track_rate',
        'track_share',
        'track_favorite',
        'track_comment',
        'collection_update',
        'profile_update',
        'search_perform',
        'recommendation_interact'
    ];

    if (!in_array($activityType, $validTypes)) {
        throw new Exception('Nieprawidłowy typ aktywności.');
    }

    // Zapisz aktywność
    $stmt = $pdo->prepare("
        INSERT INTO user_activities (
            user_id,
            activity_type,
            activity_data,
            created_at,
            ip_address,
            user_agent
        ) VALUES (?, ?, ?, CURRENT_TIMESTAMP, ?, ?)
    ");

    $stmt->execute([
        $userId,
        $activityType,
        json_encode($data),
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);

    // Aktualizuj liczniki aktywności
    $stmt = $pdo->prepare("
        INSERT INTO activity_counters (
            user_id,
            activity_type,
            count,
            last_updated
        ) VALUES (?, ?, 1, CURRENT_TIMESTAMP)
        ON DUPLICATE KEY UPDATE
            count = count + 1,
            last_updated = CURRENT_TIMESTAMP
    ");

    $stmt->execute([
        $userId,
        $activityType
    ]);

    return [
        'activity_id' => $pdo->lastInsertId(),
        'type' => $activityType,
        'timestamp' => date('Y-m-d H:i:s'),
        'data' => $data
    ];
}

function calculateEngagementScore($pdo, $userId, $timeRange = '30 days') {
    // Pobierz wszystkie aktywności użytkownika
    $stmt = $pdo->prepare("
        SELECT 
            activity_type,
            COUNT(*) as count
        FROM user_activities
        WHERE user_id = ?
        AND created_at >= DATE_SUB(NOW(), INTERVAL ?)
        GROUP BY activity_type
    ");

    $stmt->execute([$userId, $timeRange]);
    $activities = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Wagi dla różnych typów aktywności
    $weights = [
        'playlist_create' => 10,
        'playlist_edit' => 5,
        'track_rate' => 3,
        'track_share' => 8,
        'track_favorite' => 4,
        'track_comment' => 6,
        'collection_update' => 7,
        'profile_update' => 2,
        'search_perform' => 1,
        'recommendation_interact' => 4
    ];

    // Oblicz bazowy wynik
    $baseScore = 0;
    foreach ($activities as $type => $count) {
        $baseScore += ($count * ($weights[$type] ?? 1));
    }

    // Pobierz statystyki odtwarzania
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT track_id) as unique_tracks,
            COUNT(*) as total_plays,
            SUM(TIMESTAMPDIFF(SECOND, started_at, ended_at)) as total_listen_time
        FROM playback_history
        WHERE user_id = ?
        AND played_at >= DATE_SUB(NOW(), INTERVAL ?)
    ");

    $stmt->execute([$userId, $timeRange]);
    $playbackStats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Dodaj punkty za odtwarzanie
    $playbackScore = 
        ($playbackStats['unique_tracks'] * 2) + 
        ($playbackStats['total_plays'] * 1) + 
        (floor($playbackStats['total_listen_time'] / 3600) * 5); // 5 punktów za każdą godzinę słuchania

    // Oblicz końcowy wynik
    $totalScore = $baseScore + $playbackScore;

    // Określ poziom zaangażowania
    $level = 'low';
    if ($totalScore >= 1000) {
        $level = 'very_high';
    } elseif ($totalScore >= 500) {
        $level = 'high';
    } elseif ($totalScore >= 200) {
        $level = 'medium';
    }

    $engagementData = [
        'score' => $totalScore,
        'level' => $level,
        'time_range' => $timeRange,
        'components' => [
            'activities' => [
                'score' => $baseScore,
                'breakdown' => $activities
            ],
            'playback' => [
                'score' => $playbackScore,
                'stats' => $playbackStats
            ]
        ]
    ];

    // Zapisz wynik zaangażowania
    $stmt = $pdo->prepare("
        INSERT INTO engagement_scores (
            user_id,
            time_range,
            score,
            level,
            created_at,
            score_data
        ) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP, ?)
    ");

    $stmt->execute([
        $userId,
        $timeRange,
        $totalScore,
        $level,
        json_encode($engagementData)
    ]);

    return $engagementData;
}

function generateUserInsights($pdo, $userId, $params = []) {
    // Parametry domyślne
    $defaults = [
        'time_range' => '30 days',
        'include_trends' => true,
        'min_confidence' => 0.7
    ];

    $params = array_merge($defaults, $params);

    $insights = [
        'generated_at' => date('Y-m-d H:i:s'),
        'time_range' => $params['time_range'],
        'insights' => []
    ];

    // Analiza wzorców słuchania
    $stmt = $pdo->prepare("
        SELECT 
            HOUR(played_at) as hour,
            COUNT(*) as play_count
        FROM playback_history
        WHERE user_id = ?
        AND played_at >= DATE_SUB(NOW(), INTERVAL ?)
        GROUP BY HOUR(played_at)
        ORDER BY play_count DESC
        LIMIT 3
    ");

    $stmt->execute([$userId, $params['time_range']]);
    $peakHours = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($peakHours)) {
        $insights['insights'][] = [
            'type' => 'listening_pattern',
            'title' => 'Szczytowe godziny słuchania',
            'description' => sprintf(
                'Najczęściej słuchasz muzyki o godzinie %s, %s i %s.',
                $peakHours[0]['hour'],
                $peakHours[1]['hour'],
                $peakHours[2]['hour']
            ),
            'confidence' => 0.9,
            'data' => $peakHours
        ];
    }

    // Analiza ulubionych gatunków
    $stmt = $pdo->prepare("
        SELECT 
            t.genre,
            COUNT(*) as play_count,
            COUNT(DISTINCT t.track_id) as unique_tracks
        FROM playback_history ph
        JOIN tracks t ON ph.track_id = t.track_id
        WHERE ph.user_id = ?
        AND ph.played_at >= DATE_SUB(NOW(), INTERVAL ?)
        GROUP BY t.genre
        HAVING play_count >= 10
        ORDER BY play_count DESC
        LIMIT 5
    ");

    $stmt->execute([$userId, $params['time_range']]);
    $topGenres = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($topGenres)) {
        $insights['insights'][] = [
            'type' => 'genre_preference',
            'title' => 'Ulubione gatunki',
            'description' => sprintf(
                'Twój ulubiony gatunek to %s, słuchałeś go %d razy w %d różnych utworach.',
                $topGenres[0]['genre'],
                $topGenres[0]['play_count'],
                $topGenres[0]['unique_tracks']
            ),
            'confidence' => 0.85,
            'data' => $topGenres
        ];
    }

    // Analiza trendów
    if ($params['include_trends']) {
        $stmt = $pdo->prepare("
            SELECT 
                DATE(played_at) as date,
                COUNT(*) as play_count
            FROM playback_history
            WHERE user_id = ?
            AND played_at >= DATE_SUB(NOW(), INTERVAL ?)
            GROUP BY DATE(played_at)
            ORDER BY date
        ");

        $stmt->execute([$userId, $params['time_range']]);
        $dailyTrends = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        if (!empty($dailyTrends)) {
            $trend = analyzeTrend(array_values($dailyTrends));
            $insights['insights'][] = [
                'type' => 'listening_trend',
                'title' => 'Trend słuchania',
                'description' => $trend['description'],
                'confidence' => $trend['confidence'],
                'data' => [
                    'trend' => $trend['type'],
                    'daily_data' => $dailyTrends
                ]
            ];
        }
    }

    // Zapisz wygenerowane insighty
    $stmt = $pdo->prepare("
        INSERT INTO user_insights (
            user_id,
            time_range,
            created_at,
            insights_data
        ) VALUES (?, ?, CURRENT_TIMESTAMP, ?)
    ");

    $stmt->execute([
        $userId,
        $params['time_range'],
        json_encode($insights)
    ]);

    return $insights;
}

function analyzeTrend($data) {
    $n = count($data);
    if ($n < 7) {
        return [
            'type' => 'insufficient_data',
            'description' => 'Za mało danych do analizy trendu.',
            'confidence' => 0.5
        ];
    }

    // Oblicz średnią ruchomą
    $movingAvg = [];
    $window = 3;
    for ($i = 0; $i <= $n - $window; $i++) {
        $sum = 0;
        for ($j = 0; $j < $window; $j++) {
            $sum += $data[$i + $j];
        }
        $movingAvg[] = $sum / $window;
    }

    // Oblicz trend
    $firstAvg = array_sum(array_slice($movingAvg, 0, 3)) / 3;
    $lastAvg = array_sum(array_slice($movingAvg, -3)) / 3;
    $change = $lastAvg - $firstAvg;
    $percentChange = ($firstAvg > 0) ? ($change / $firstAvg) * 100 : 0;

    if (abs($percentChange) < 10) {
        return [
            'type' => 'stable',
            'description' => 'Twój poziom aktywności jest stabilny.',
            'confidence' => 0.8
        ];
    } elseif ($percentChange > 0) {
        return [
            'type' => 'increasing',
            'description' => 'Twoja aktywność słuchania wzrasta.',
            'confidence' => 0.75
        ];
    } else {
        return [
            'type' => 'decreasing',
            'description' => 'Twoja aktywność słuchania maleje.',
            'confidence' => 0.75
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? '';

        switch ($action) {
            case 'track_activity':
                if (!isset($data['activity_type'])) {
                    throw new Exception('Brak typu aktywności.');
                }
                $activityData = $data['data'] ?? [];
                $result = trackUserActivity($pdo, $_SESSION['user_id'], $data['activity_type'], $activityData);
                echo json_encode([
                    'success' => true,
                    'data' => $result
                ]);
                break;

            case 'get_engagement_score':
                $timeRange = $data['time_range'] ?? '30 days';
                $score = calculateEngagementScore($pdo, $_SESSION['user_id'], $timeRange);
                echo json_encode([
                    'success' => true,
                    'data' => $score
                ]);
                break;

            case 'get_insights':
                $params = $data['parameters'] ?? [];
                $insights = generateUserInsights($pdo, $_SESSION['user_id'], $params);
                echo json_encode([
                    'success' => true,
                    'data' => $insights
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