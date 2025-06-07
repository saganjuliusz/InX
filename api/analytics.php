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

function getUserStats($pdo, $userId, $days = 30) {
    // Pobierz statystyki użytkownika
    $stmt = $pdo->prepare("
        SELECT 
            date,
            total_listening_time,
            tracks_played,
            unique_tracks,
            unique_artists,
            skips,
            likes,
            playlist_additions,
            new_tracks_discovered,
            new_artists_discovered
        FROM daily_user_stats
        WHERE user_id = ?
        AND date >= DATE_SUB(CURRENT_DATE, INTERVAL ? DAY)
        ORDER BY date ASC
    ");
    $stmt->execute([$userId, $days]);
    $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Oblicz sumy i średnie
    $totals = [
        'total_listening_time' => 0,
        'tracks_played' => 0,
        'unique_tracks' => 0,
        'unique_artists' => 0,
        'skips' => 0,
        'likes' => 0,
        'playlist_additions' => 0,
        'new_discoveries' => 0
    ];

    foreach ($stats as $day) {
        $totals['total_listening_time'] += $day['total_listening_time'];
        $totals['tracks_played'] += $day['tracks_played'];
        $totals['unique_tracks'] = max($totals['unique_tracks'], $day['unique_tracks']);
        $totals['unique_artists'] = max($totals['unique_artists'], $day['unique_artists']);
        $totals['skips'] += $day['skips'];
        $totals['likes'] += $day['likes'];
        $totals['playlist_additions'] += $day['playlist_additions'];
        $totals['new_discoveries'] += ($day['new_tracks_discovered'] + $day['new_artists_discovered']);
    }

    // Pobierz ulubione gatunki
    $stmt = $pdo->prepare("
        SELECT 
            g.name as genre_name,
            COUNT(*) as play_count
        FROM listening_history lh
        JOIN tracks t ON lh.track_id = t.track_id
        JOIN track_genres tg ON t.track_id = tg.track_id
        JOIN genres g ON tg.genre_id = g.genre_id
        WHERE lh.user_id = ?
        AND lh.played_at >= DATE_SUB(CURRENT_DATE, INTERVAL ? DAY)
        GROUP BY g.genre_id
        ORDER BY play_count DESC
        LIMIT 5
    ");
    $stmt->execute([$userId, $days]);
    $topGenres = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Pobierz ulubione pory słuchania
    $stmt = $pdo->prepare("
        SELECT 
            HOUR(played_at) as hour,
            COUNT(*) as play_count
        FROM listening_history
        WHERE user_id = ?
        AND played_at >= DATE_SUB(CURRENT_DATE, INTERVAL ? DAY)
        GROUP BY HOUR(played_at)
        ORDER BY hour
    ");
    $stmt->execute([$userId, $days]);
    $listeningHours = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'daily_stats' => $stats,
        'totals' => $totals,
        'top_genres' => $topGenres,
        'listening_hours' => $listeningHours
    ];
}

function getTrackStats($pdo, $trackId, $days = 30) {
    // Pobierz statystyki utworu
    $stmt = $pdo->prepare("
        SELECT 
            date,
            play_count,
            unique_listeners,
            total_listening_time,
            like_count,
            share_count,
            playlist_additions,
            completion_rate,
            skip_rate
        FROM daily_track_stats
        WHERE track_id = ?
        AND date >= DATE_SUB(CURRENT_DATE, INTERVAL ? DAY)
        ORDER BY date ASC
    ");
    $stmt->execute([$trackId, $days]);
    $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Oblicz sumy i średnie
    $totals = [
        'total_plays' => 0,
        'unique_listeners' => 0,
        'total_listening_time' => 0,
        'total_likes' => 0,
        'total_shares' => 0,
        'total_playlist_adds' => 0,
        'avg_completion_rate' => 0,
        'avg_skip_rate' => 0
    ];

    $days_count = count($stats);
    if ($days_count > 0) {
        foreach ($stats as $day) {
            $totals['total_plays'] += $day['play_count'];
            $totals['unique_listeners'] = max($totals['unique_listeners'], $day['unique_listeners']);
            $totals['total_listening_time'] += $day['total_listening_time'];
            $totals['total_likes'] += $day['like_count'];
            $totals['total_shares'] += $day['share_count'];
            $totals['total_playlist_adds'] += $day['playlist_additions'];
            $totals['avg_completion_rate'] += $day['completion_rate'];
            $totals['avg_skip_rate'] += $day['skip_rate'];
        }

        $totals['avg_completion_rate'] /= $days_count;
        $totals['avg_skip_rate'] /= $days_count;
    }

    // Pobierz demografię słuchaczy
    $stmt = $pdo->prepare("
        SELECT 
            u.country_code,
            COUNT(DISTINCT lh.user_id) as listener_count
        FROM listening_history lh
        JOIN users u ON lh.user_id = u.user_id
        WHERE lh.track_id = ?
        AND lh.played_at >= DATE_SUB(CURRENT_DATE, INTERVAL ? DAY)
        GROUP BY u.country_code
        ORDER BY listener_count DESC
        LIMIT 10
    ");
    $stmt->execute([$trackId, $days]);
    $demographics = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Pobierz źródła odtworzeń
    $stmt = $pdo->prepare("
        SELECT 
            listening_context,
            COUNT(*) as play_count
        FROM listening_history
        WHERE track_id = ?
        AND played_at >= DATE_SUB(CURRENT_DATE, INTERVAL ? DAY)
        GROUP BY listening_context
    ");
    $stmt->execute([$trackId, $days]);
    $playSources = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'daily_stats' => $stats,
        'totals' => $totals,
        'demographics' => $demographics,
        'play_sources' => $playSources
    ];
}

function getArtistStats($pdo, $artistId, $days = 30) {
    // Pobierz podstawowe statystyki
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT lh.user_id) as unique_listeners,
            COUNT(DISTINCT lh.track_id) as tracks_played,
            SUM(lh.listening_duration) as total_listening_time,
            COUNT(DISTINCT CASE WHEN af.followed_at >= DATE_SUB(CURRENT_DATE, INTERVAL ? DAY) THEN af.user_id END) as new_followers
        FROM listening_history lh
        JOIN tracks t ON lh.track_id = t.track_id
        LEFT JOIN artist_follows af ON t.artist_id = af.artist_id
        WHERE t.artist_id = ?
        AND lh.played_at >= DATE_SUB(CURRENT_DATE, INTERVAL ? DAY)
    ");
    $stmt->execute([$days, $artistId, $days]);
    $basicStats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Pobierz najpopularniejsze utwory
    $stmt = $pdo->prepare("
        SELECT 
            t.track_id,
            t.title,
            COUNT(*) as play_count,
            COUNT(DISTINCT lh.user_id) as unique_listeners
        FROM listening_history lh
        JOIN tracks t ON lh.track_id = t.track_id
        WHERE t.artist_id = ?
        AND lh.played_at >= DATE_SUB(CURRENT_DATE, INTERVAL ? DAY)
        GROUP BY t.track_id
        ORDER BY play_count DESC
        LIMIT 10
    ");
    $stmt->execute([$artistId, $days]);
    $topTracks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Pobierz demografię słuchaczy
    $stmt = $pdo->prepare("
        SELECT 
            u.country_code,
            COUNT(DISTINCT lh.user_id) as listener_count
        FROM listening_history lh
        JOIN tracks t ON lh.track_id = t.track_id
        JOIN users u ON lh.user_id = u.user_id
        WHERE t.artist_id = ?
        AND lh.played_at >= DATE_SUB(CURRENT_DATE, INTERVAL ? DAY)
        GROUP BY u.country_code
        ORDER BY listener_count DESC
        LIMIT 10
    ");
    $stmt->execute([$artistId, $days]);
    $demographics = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'basic_stats' => $basicStats,
        'top_tracks' => $topTracks,
        'demographics' => $demographics
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? '';

        switch ($action) {
            case 'user_stats':
                $days = min(($data['days'] ?? 30), 365); // Maksymalnie rok wstecz
                $stats = getUserStats($pdo, $_SESSION['user_id'], $days);
                echo json_encode([
                    'success' => true,
                    'data' => $stats
                ]);
                break;

            case 'track_stats':
                if (!isset($data['track_id'])) {
                    throw new Exception('Brak ID utworu.');
                }
                $days = min(($data['days'] ?? 30), 365);
                $stats = getTrackStats($pdo, $data['track_id'], $days);
                echo json_encode([
                    'success' => true,
                    'data' => $stats
                ]);
                break;

            case 'artist_stats':
                if (!isset($data['artist_id'])) {
                    throw new Exception('Brak ID artysty.');
                }
                $days = min(($data['days'] ?? 30), 365);
                $stats = getArtistStats($pdo, $data['artist_id'], $days);
                echo json_encode([
                    'success' => true,
                    'data' => $stats
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