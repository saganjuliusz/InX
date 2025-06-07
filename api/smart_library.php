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

function createSmartPlaylist($pdo, $userId, $rules) {
    // Walidacja reguł
    if (empty($rules) || !isset($rules['name'])) {
        throw new Exception('Nieprawidłowe reguły playlisty.');
    }

    // Przygotuj warunki SQL na podstawie reguł
    $conditions = [];
    $params = [];
    
    foreach ($rules['conditions'] ?? [] as $condition) {
        switch ($condition['type']) {
            case 'genre':
                $conditions[] = "t.genre = ?";
                $params[] = $condition['value'];
                break;
            case 'artist':
                $conditions[] = "t.artist = ?";
                $params[] = $condition['value'];
                break;
            case 'year':
                $conditions[] = "t.year " . $condition['operator'] . " ?";
                $params[] = $condition['value'];
                break;
            case 'rating':
                $conditions[] = "tr.rating " . $condition['operator'] . " ?";
                $params[] = $condition['value'];
                break;
            case 'play_count':
                $conditions[] = "(SELECT COUNT(*) FROM playback_history ph WHERE ph.track_id = t.track_id) " . 
                              $condition['operator'] . " ?";
                $params[] = $condition['value'];
                break;
            case 'last_played':
                $conditions[] = "(SELECT MAX(played_at) FROM playback_history ph WHERE ph.track_id = t.track_id) " .
                              $condition['operator'] . " DATE_SUB(NOW(), INTERVAL ? DAY)";
                $params[] = $condition['value'];
                break;
            case 'mood':
                $conditions[] = "t.mood = ?";
                $params[] = $condition['value'];
                break;
            case 'tempo':
                $conditions[] = "t.tempo " . $condition['operator'] . " ?";
                $params[] = $condition['value'];
                break;
        }
    }

    // Utwórz zapytanie SQL
    $sql = "
        SELECT DISTINCT
            t.*,
            tr.rating,
            (SELECT COUNT(*) FROM playback_history ph WHERE ph.track_id = t.track_id) as play_count,
            (SELECT MAX(played_at) FROM playback_history ph WHERE ph.track_id = t.track_id) as last_played
        FROM tracks t
        LEFT JOIN track_ratings tr ON t.track_id = tr.track_id AND tr.user_id = ?
        WHERE " . implode(' AND ', $conditions);

    array_unshift($params, $userId);

    // Wykonaj zapytanie
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tracks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Utwórz playlistę
    $playlist = [
        'playlist_id' => uniqid('smart_'),
        'name' => $rules['name'],
        'description' => $rules['description'] ?? 'Inteligentna playlista',
        'rules' => $rules,
        'created_at' => time(),
        'track_count' => count($tracks),
        'tracks' => $tracks
    ];

    // Zapisz playlistę
    $stmt = $pdo->prepare("
        INSERT INTO smart_playlists (
            playlist_id,
            user_id,
            name,
            description,
            rules,
            created_at,
            last_updated,
            is_active
        ) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, true)
    ");

    $stmt->execute([
        $playlist['playlist_id'],
        $userId,
        $playlist['name'],
        $playlist['description'],
        json_encode($rules)
    ]);

    // Dodaj utwory do playlisty
    $stmt = $pdo->prepare("
        INSERT INTO playlist_tracks (
            playlist_id,
            track_id,
            position
        ) VALUES (?, ?, ?)
    ");

    foreach ($tracks as $index => $track) {
        $stmt->execute([
            $playlist['playlist_id'],
            $track['track_id'],
            $index + 1
        ]);
    }

    return $playlist;
}

function autoTagTracks($pdo, $trackIds) {
    if (empty($trackIds)) {
        throw new Exception('Nie podano utworów do tagowania.');
    }

    $results = [];
    
    // Pobierz informacje o utworach
    $placeholders = str_repeat('?,', count($trackIds) - 1) . '?';
    $stmt = $pdo->prepare("
        SELECT 
            t.*,
            (SELECT GROUP_CONCAT(tag_name) FROM track_tags tt WHERE tt.track_id = t.track_id) as existing_tags
        FROM tracks t
        WHERE t.track_id IN ($placeholders)
    ");
    $stmt->execute($trackIds);
    $tracks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($tracks as $track) {
        $tags = [];
        
        // Analiza gatunku
        if (!empty($track['genre'])) {
            $tags[] = strtolower($track['genre']);
            // Dodaj podgatunki
            $subgenres = explode('/', $track['genre']);
            $tags = array_merge($tags, array_map('strtolower', $subgenres));
        }

        // Analiza tempa
        if (!empty($track['tempo'])) {
            if ($track['tempo'] < 90) $tags[] = 'slow';
            elseif ($track['tempo'] < 120) $tags[] = 'medium_tempo';
            else $tags[] = 'fast';
        }

        // Analiza nastroju
        if (!empty($track['mood'])) {
            $tags[] = strtolower($track['mood']);
        }

        // Analiza energii
        if (isset($track['energy_level'])) {
            if ($track['energy_level'] < 0.3) $tags[] = 'calm';
            elseif ($track['energy_level'] < 0.7) $tags[] = 'moderate';
            else $tags[] = 'energetic';
        }

        // Analiza pory dnia
        $playbackTimes = $pdo->prepare("
            SELECT HOUR(played_at) as hour
            FROM playback_history
            WHERE track_id = ?
        ");
        $playbackTimes->execute([$track['track_id']]);
        $hours = $playbackTimes->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($hours)) {
            $avgHour = array_sum($hours) / count($hours);
            if ($avgHour >= 5 && $avgHour < 12) $tags[] = 'morning';
            elseif ($avgHour >= 12 && $avgHour < 17) $tags[] = 'afternoon';
            elseif ($avgHour >= 17 && $avgHour < 22) $tags[] = 'evening';
            else $tags[] = 'night';
        }

        // Usuń duplikaty i zapisz tagi
        $tags = array_unique($tags);
        $existingTags = !empty($track['existing_tags']) ? 
            explode(',', $track['existing_tags']) : [];
        $newTags = array_diff($tags, $existingTags);

        if (!empty($newTags)) {
            $stmt = $pdo->prepare("
                INSERT INTO track_tags (
                    track_id,
                    tag_name,
                    created_at,
                    source
                ) VALUES (?, ?, CURRENT_TIMESTAMP, 'auto')
            ");

            foreach ($newTags as $tag) {
                $stmt->execute([$track['track_id'], $tag]);
            }
        }

        $results[$track['track_id']] = [
            'track_title' => $track['title'],
            'existing_tags' => $existingTags,
            'new_tags' => $newTags,
            'total_tags' => count($existingTags) + count($newTags)
        ];
    }

    return $results;
}

function advancedSearch($pdo, $userId, $query) {
    // Walidacja parametrów wyszukiwania
    if (empty($query)) {
        throw new Exception('Brak parametrów wyszukiwania.');
    }

    $conditions = [];
    $params = [];
    $joins = [];
    
    // Podstawowe wyszukiwanie
    if (isset($query['text'])) {
        $conditions[] = "(
            t.title LIKE ? OR 
            t.artist LIKE ? OR 
            t.album LIKE ? OR 
            t.genre LIKE ?
        )";
        $searchTerm = "%{$query['text']}%";
        array_push($params, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
    }

    // Wyszukiwanie po tagach
    if (!empty($query['tags'])) {
        $tagCount = count($query['tags']);
        $tagPlaceholders = str_repeat('?,', $tagCount - 1) . '?';
        $conditions[] = "
            t.track_id IN (
                SELECT track_id 
                FROM track_tags 
                WHERE tag_name IN ($tagPlaceholders)
                GROUP BY track_id
                HAVING COUNT(DISTINCT tag_name) = ?
            )
        ";
        array_push($params, ...$query['tags'], $tagCount);
    }

    // Wyszukiwanie po ocenach
    if (isset($query['min_rating'])) {
        $joins['ratings'] = "LEFT JOIN track_ratings tr ON t.track_id = tr.track_id AND tr.user_id = ?";
        $conditions[] = "tr.rating >= ?";
        array_push($params, $userId, $query['min_rating']);
    }

    // Wyszukiwanie po metadanych audio
    if (isset($query['tempo_range'])) {
        $conditions[] = "t.tempo BETWEEN ? AND ?";
        array_push($params, $query['tempo_range'][0], $query['tempo_range'][1]);
    }

    if (isset($query['key'])) {
        $conditions[] = "t.key_signature = ?";
        $params[] = $query['key'];
    }

    if (isset($query['mood'])) {
        $conditions[] = "t.mood = ?";
        $params[] = $query['mood'];
    }

    // Wyszukiwanie po historii odtwarzania
    if (isset($query['played_in_last_days'])) {
        $joins['history'] = "
            LEFT JOIN (
                SELECT 
                    track_id,
                    MAX(played_at) as last_played,
                    COUNT(*) as play_count
                FROM playback_history
                WHERE user_id = ?
                AND played_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY track_id
            ) ph ON t.track_id = ph.track_id
        ";
        array_push($params, $userId, $query['played_in_last_days']);
    }

    // Budowa zapytania SQL
    $sql = "
        SELECT DISTINCT
            t.*,
            tr.rating,
            ph.play_count,
            ph.last_played,
            GROUP_CONCAT(DISTINCT tt.tag_name) as tags
        FROM tracks t
        LEFT JOIN track_tags tt ON t.track_id = tt.track_id
        " . implode("\n", $joins) . "
        " . (!empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "") . "
        GROUP BY t.track_id
    ";

    if (isset($query['sort'])) {
        $sql .= " ORDER BY " . $query['sort']['field'] . " " . ($query['sort']['desc'] ? 'DESC' : 'ASC');
    }

    if (isset($query['limit'])) {
        $sql .= " LIMIT " . (int)$query['limit'];
    }

    // Wykonaj wyszukiwanie
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Przygotuj wyniki
    $searchResults = [
        'query' => $query,
        'total_results' => count($results),
        'tracks' => array_map(function($track) {
            $track['tags'] = !empty($track['tags']) ? explode(',', $track['tags']) : [];
            return $track;
        }, $results)
    ];

    // Zapisz historię wyszukiwania
    $stmt = $pdo->prepare("
        INSERT INTO search_history (
            user_id,
            query_params,
            results_count,
            created_at
        ) VALUES (?, ?, ?, CURRENT_TIMESTAMP)
    ");

    $stmt->execute([
        $userId,
        json_encode($query),
        count($results)
    ]);

    return $searchResults;
}

function manageRatings($pdo, $userId, $action, $data) {
    switch ($action) {
        case 'rate':
            if (!isset($data['track_id']) || !isset($data['rating'])) {
                throw new Exception('Brak wymaganych parametrów.');
            }

            $rating = max(1, min(5, $data['rating'])); // Ograniczenie do zakresu 1-5

            $stmt = $pdo->prepare("
                INSERT INTO track_ratings (
                    user_id,
                    track_id,
                    rating,
                    created_at,
                    updated_at
                ) VALUES (?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                ON DUPLICATE KEY UPDATE
                    rating = ?,
                    updated_at = CURRENT_TIMESTAMP
            ");

            $stmt->execute([
                $userId,
                $data['track_id'],
                $rating,
                $rating
            ]);

            // Aktualizuj średnią ocenę utworu
            $stmt = $pdo->prepare("
                UPDATE tracks
                SET average_rating = (
                    SELECT AVG(rating)
                    FROM track_ratings
                    WHERE track_id = ?
                )
                WHERE track_id = ?
            ");

            $stmt->execute([
                $data['track_id'],
                $data['track_id']
            ]);

            return [
                'track_id' => $data['track_id'],
                'rating' => $rating,
                'timestamp' => time()
            ];

        case 'get_user_ratings':
            $stmt = $pdo->prepare("
                SELECT 
                    tr.track_id,
                    t.title,
                    t.artist,
                    tr.rating,
                    tr.created_at,
                    tr.updated_at
                FROM track_ratings tr
                JOIN tracks t ON tr.track_id = t.track_id
                WHERE tr.user_id = ?
                ORDER BY tr.updated_at DESC
            ");

            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        case 'get_track_ratings':
            if (!isset($data['track_id'])) {
                throw new Exception('Brak ID utworu.');
            }

            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_ratings,
                    AVG(rating) as average_rating,
                    MIN(rating) as min_rating,
                    MAX(rating) as max_rating,
                    (
                        SELECT rating
                        FROM track_ratings
                        WHERE track_id = ?
                        AND user_id = ?
                    ) as user_rating
                FROM track_ratings
                WHERE track_id = ?
            ");

            $stmt->execute([
                $data['track_id'],
                $userId,
                $data['track_id']
            ]);

            $ratings = $stmt->fetch(PDO::FETCH_ASSOC);

            // Dodaj rozkład ocen
            $stmt = $pdo->prepare("
                SELECT 
                    rating,
                    COUNT(*) as count
                FROM track_ratings
                WHERE track_id = ?
                GROUP BY rating
                ORDER BY rating
            ");

            $stmt->execute([$data['track_id']]);
            $ratings['distribution'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            return $ratings;

        default:
            throw new Exception('Nieznana akcja.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? '';

        switch ($action) {
            case 'create_smart_playlist':
                if (!isset($data['rules'])) {
                    throw new Exception('Brak reguł playlisty.');
                }
                $playlist = createSmartPlaylist($pdo, $_SESSION['user_id'], $data['rules']);
                echo json_encode([
                    'success' => true,
                    'data' => $playlist
                ]);
                break;

            case 'auto_tag':
                if (!isset($data['track_ids']) || !is_array($data['track_ids'])) {
                    throw new Exception('Brak ID utworów do tagowania.');
                }
                $tags = autoTagTracks($pdo, $data['track_ids']);
                echo json_encode([
                    'success' => true,
                    'data' => $tags
                ]);
                break;

            case 'search':
                if (!isset($data['query'])) {
                    throw new Exception('Brak parametrów wyszukiwania.');
                }
                $results = advancedSearch($pdo, $_SESSION['user_id'], $data['query']);
                echo json_encode([
                    'success' => true,
                    'data' => $results
                ]);
                break;

            case 'manage_ratings':
                if (!isset($data['rating_action']) || !isset($data['rating_data'])) {
                    throw new Exception('Brak parametrów zarządzania ocenami.');
                }
                $result = manageRatings($pdo, $_SESSION['user_id'], $data['rating_action'], $data['rating_data']);
                echo json_encode([
                    'success' => true,
                    'data' => $result
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