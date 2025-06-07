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

function followUser($pdo, $followerId, $targetUserId) {
    // Sprawdź czy użytkownik nie próbuje obserwować samego siebie
    if ($followerId === $targetUserId) {
        throw new Exception('Nie możesz obserwować samego siebie.');
    }

    // Sprawdź czy użytkownik docelowy istnieje
    $stmt = $pdo->prepare("
        SELECT user_id
        FROM users
        WHERE user_id = ?
    ");
    
    $stmt->execute([$targetUserId]);
    if (!$stmt->fetch()) {
        throw new Exception('Użytkownik nie istnieje.');
    }

    // Sprawdź czy już nie obserwuje
    $stmt = $pdo->prepare("
        SELECT follow_id
        FROM user_follows
        WHERE follower_id = ? AND following_id = ?
    ");
    
    $stmt->execute([$followerId, $targetUserId]);
    if ($stmt->fetch()) {
        throw new Exception('Już obserwujesz tego użytkownika.');
    }

    // Dodaj obserwację
    $stmt = $pdo->prepare("
        INSERT INTO user_follows (
            follower_id,
            following_id,
            created_at
        ) VALUES (?, ?, CURRENT_TIMESTAMP)
    ");

    $stmt->execute([$followerId, $targetUserId]);

    // Aktualizuj liczniki
    $stmt = $pdo->prepare("
        UPDATE user_stats
        SET followers_count = followers_count + 1
        WHERE user_id = ?
    ");
    
    $stmt->execute([$targetUserId]);

    $stmt = $pdo->prepare("
        UPDATE user_stats
        SET following_count = following_count + 1
        WHERE user_id = ?
    ");
    
    $stmt->execute([$followerId]);

    // Dodaj powiadomienie
    $stmt = $pdo->prepare("
        INSERT INTO notifications (
            user_id,
            type,
            data,
            created_at
        ) VALUES (?, 'new_follower', ?, CURRENT_TIMESTAMP)
    ");

    $stmt->execute([
        $targetUserId,
        json_encode([
            'follower_id' => $followerId
        ])
    ]);

    return [
        'follow_id' => $pdo->lastInsertId(),
        'follower_id' => $followerId,
        'following_id' => $targetUserId,
        'created_at' => date('Y-m-d H:i:s')
    ];
}

function unfollowUser($pdo, $followerId, $targetUserId) {
    // Sprawdź czy obserwacja istnieje
    $stmt = $pdo->prepare("
        SELECT follow_id
        FROM user_follows
        WHERE follower_id = ? AND following_id = ?
    ");
    
    $stmt->execute([$followerId, $targetUserId]);
    if (!$stmt->fetch()) {
        throw new Exception('Nie obserwujesz tego użytkownika.');
    }

    // Usuń obserwację
    $stmt = $pdo->prepare("
        DELETE FROM user_follows
        WHERE follower_id = ? AND following_id = ?
    ");

    $stmt->execute([$followerId, $targetUserId]);

    // Aktualizuj liczniki
    $stmt = $pdo->prepare("
        UPDATE user_stats
        SET followers_count = followers_count - 1
        WHERE user_id = ?
    ");
    
    $stmt->execute([$targetUserId]);

    $stmt = $pdo->prepare("
        UPDATE user_stats
        SET following_count = following_count - 1
        WHERE user_id = ?
    ");
    
    $stmt->execute([$followerId]);

    return [
        'follower_id' => $followerId,
        'following_id' => $targetUserId,
        'unfollowed_at' => date('Y-m-d H:i:s')
    ];
}

function shareContent($pdo, $userId, $data) {
    // Walidacja danych
    if (!isset($data['content_type']) || !isset($data['content_id'])) {
        throw new Exception('Brak wymaganych parametrów.');
    }

    // Sprawdź czy treść istnieje
    switch ($data['content_type']) {
        case 'track':
            $stmt = $pdo->prepare("
                SELECT track_id, title, artist
                FROM tracks
                WHERE track_id = ?
            ");
            break;

        case 'playlist':
            $stmt = $pdo->prepare("
                SELECT playlist_id, name, user_id
                FROM playlists
                WHERE playlist_id = ?
            ");
            break;

        case 'album':
            $stmt = $pdo->prepare("
                SELECT album_id, title, artist
                FROM albums
                WHERE album_id = ?
            ");
            break;

        default:
            throw new Exception('Nieobsługiwany typ treści.');
    }

    $stmt->execute([$data['content_id']]);
    $content = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$content) {
        throw new Exception('Treść nie istnieje.');
    }

    // Utwórz udostępnienie
    $stmt = $pdo->prepare("
        INSERT INTO content_shares (
            user_id,
            content_type,
            content_id,
            message,
            visibility,
            created_at
        ) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
    ");

    $stmt->execute([
        $userId,
        $data['content_type'],
        $data['content_id'],
        $data['message'] ?? null,
        $data['visibility'] ?? 'public'
    ]);

    $shareId = $pdo->lastInsertId();

    // Dodaj tagi jeśli są
    if (!empty($data['tags'])) {
        $stmt = $pdo->prepare("
            INSERT INTO share_tags (
                share_id,
                tag
            ) VALUES (?, ?)
        ");

        foreach ($data['tags'] as $tag) {
            $stmt->execute([$shareId, $tag]);
        }
    }

    // Powiadom obserwujących
    $stmt = $pdo->prepare("
        INSERT INTO notifications (
            user_id,
            type,
            data,
            created_at
        )
        SELECT 
            uf.follower_id,
            'new_share',
            ?,
            CURRENT_TIMESTAMP
        FROM user_follows uf
        WHERE uf.following_id = ?
    ");

    $stmt->execute([
        json_encode([
            'share_id' => $shareId,
            'user_id' => $userId,
            'content_type' => $data['content_type'],
            'content_id' => $data['content_id']
        ]),
        $userId
    ]);

    return [
        'share_id' => $shareId,
        'content' => $content,
        'created_at' => date('Y-m-d H:i:s')
    ];
}

function addComment($pdo, $userId, $data) {
    // Walidacja danych
    if (!isset($data['content_type']) || !isset($data['content_id']) || !isset($data['text'])) {
        throw new Exception('Brak wymaganych parametrów.');
    }

    // Sprawdź czy treść istnieje
    switch ($data['content_type']) {
        case 'track':
            $stmt = $pdo->prepare("
                SELECT track_id
                FROM tracks
                WHERE track_id = ?
            ");
            break;

        case 'playlist':
            $stmt = $pdo->prepare("
                SELECT playlist_id
                FROM playlists
                WHERE playlist_id = ?
            ");
            break;

        case 'share':
            $stmt = $pdo->prepare("
                SELECT share_id
                FROM content_shares
                WHERE share_id = ?
            ");
            break;

        default:
            throw new Exception('Nieobsługiwany typ treści.');
    }

    $stmt->execute([$data['content_id']]);
    if (!$stmt->fetch()) {
        throw new Exception('Treść nie istnieje.');
    }

    // Dodaj komentarz
    $stmt = $pdo->prepare("
        INSERT INTO comments (
            user_id,
            content_type,
            content_id,
            parent_id,
            text,
            created_at
        ) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
    ");

    $stmt->execute([
        $userId,
        $data['content_type'],
        $data['content_id'],
        $data['parent_id'] ?? null,
        $data['text']
    ]);

    $commentId = $pdo->lastInsertId();

    // Powiadom właściciela treści
    $stmt = $pdo->prepare("
        INSERT INTO notifications (
            user_id,
            type,
            data,
            created_at
        )
        SELECT 
            CASE ?
                WHEN 'track' THEN t.artist_id
                WHEN 'playlist' THEN p.user_id
                WHEN 'share' THEN cs.user_id
            END,
            'new_comment',
            ?,
            CURRENT_TIMESTAMP
        FROM tracks t
        LEFT JOIN playlists p ON p.playlist_id = ?
        LEFT JOIN content_shares cs ON cs.share_id = ?
        WHERE t.track_id = ? OR p.playlist_id = ? OR cs.share_id = ?
    ");

    $stmt->execute([
        $data['content_type'],
        json_encode([
            'comment_id' => $commentId,
            'user_id' => $userId,
            'content_type' => $data['content_type'],
            'content_id' => $data['content_id']
        ]),
        $data['content_id'],
        $data['content_id'],
        $data['content_id'],
        $data['content_id'],
        $data['content_id']
    ]);

    // Jeśli to odpowiedź, powiadom autora komentarza nadrzędnego
    if (isset($data['parent_id'])) {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (
                user_id,
                type,
                data,
                created_at
            )
            SELECT 
                user_id,
                'comment_reply',
                ?,
                CURRENT_TIMESTAMP
            FROM comments
            WHERE comment_id = ?
        ");

        $stmt->execute([
            json_encode([
                'comment_id' => $commentId,
                'user_id' => $userId,
                'parent_id' => $data['parent_id']
            ]),
            $data['parent_id']
        ]);
    }

    return [
        'comment_id' => $commentId,
        'created_at' => date('Y-m-d H:i:s')
    ];
}

function getActivityFeed($pdo, $userId, $params = []) {
    // Parametry domyślne
    $defaults = [
        'limit' => 20,
        'offset' => 0,
        'types' => ['share', 'comment', 'follow', 'like'],
        'include_following' => true
    ];

    $params = array_merge($defaults, $params);

    // Przygotuj warunki dla typów aktywności
    $typeConditions = [];
    $queryParams = [$userId];

    if (in_array('share', $params['types'])) {
        $typeConditions[] = "
            SELECT 
                cs.share_id as activity_id,
                cs.user_id,
                'share' as type,
                cs.created_at,
                JSON_OBJECT(
                    'content_type', cs.content_type,
                    'content_id', cs.content_id,
                    'message', cs.message
                ) as data
            FROM content_shares cs
            WHERE cs.user_id = ? OR cs.user_id IN (
                SELECT following_id
                FROM user_follows
                WHERE follower_id = ?
            )
        ";
        $queryParams[] = $userId;
    }

    if (in_array('comment', $params['types'])) {
        $typeConditions[] = "
            SELECT 
                c.comment_id as activity_id,
                c.user_id,
                'comment' as type,
                c.created_at,
                JSON_OBJECT(
                    'content_type', c.content_type,
                    'content_id', c.content_id,
                    'text', c.text,
                    'parent_id', c.parent_id
                ) as data
            FROM comments c
            WHERE c.user_id = ? OR c.user_id IN (
                SELECT following_id
                FROM user_follows
                WHERE follower_id = ?
            )
        ";
        $queryParams[] = $userId;
        $queryParams[] = $userId;
    }

    if (in_array('follow', $params['types'])) {
        $typeConditions[] = "
            SELECT 
                uf.follow_id as activity_id,
                uf.follower_id as user_id,
                'follow' as type,
                uf.created_at,
                JSON_OBJECT(
                    'following_id', uf.following_id
                ) as data
            FROM user_follows uf
            WHERE uf.follower_id = ? OR uf.follower_id IN (
                SELECT following_id
                FROM user_follows
                WHERE follower_id = ?
            )
        ";
        $queryParams[] = $userId;
        $queryParams[] = $userId;
    }

    if (in_array('like', $params['types'])) {
        $typeConditions[] = "
            SELECT 
                cl.like_id as activity_id,
                cl.user_id,
                'like' as type,
                cl.created_at,
                JSON_OBJECT(
                    'content_type', cl.content_type,
                    'content_id', cl.content_id
                ) as data
            FROM content_likes cl
            WHERE cl.user_id = ? OR cl.user_id IN (
                SELECT following_id
                FROM user_follows
                WHERE follower_id = ?
            )
        ";
        $queryParams[] = $userId;
        $queryParams[] = $userId;
    }

    // Połącz wszystkie zapytania
    $query = implode(" UNION ALL ", $typeConditions) . "
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ";

    $queryParams[] = $params['limit'];
    $queryParams[] = $params['offset'];

    // Wykonaj zapytanie
    $stmt = $pdo->prepare($query);
    $stmt->execute($queryParams);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Pobierz dodatkowe informacje o użytkownikach
    $userIds = array_unique(array_column($activities, 'user_id'));
    if (!empty($userIds)) {
        $stmt = $pdo->prepare("
            SELECT 
                user_id,
                username,
                avatar_url
            FROM users
            WHERE user_id IN (" . implode(',', array_fill(0, count($userIds), '?')) . ")
        ");

        $stmt->execute($userIds);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $usersMap = array_column($users, null, 'user_id');

        // Dodaj informacje o użytkownikach do aktywności
        foreach ($activities as &$activity) {
            $activity['user'] = $usersMap[$activity['user_id']] ?? null;
            $activity['data'] = json_decode($activity['data'], true);
        }
    }

    return [
        'activities' => $activities,
        'params' => $params
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? '';

        switch ($action) {
            case 'follow_user':
                if (!isset($data['target_user_id'])) {
                    throw new Exception('Brak ID użytkownika do obserwowania.');
                }
                $result = followUser($pdo, $_SESSION['user_id'], $data['target_user_id']);
                echo json_encode([
                    'success' => true,
                    'data' => $result
                ]);
                break;

            case 'unfollow_user':
                if (!isset($data['target_user_id'])) {
                    throw new Exception('Brak ID użytkownika do zaprzestania obserwowania.');
                }
                $result = unfollowUser($pdo, $_SESSION['user_id'], $data['target_user_id']);
                echo json_encode([
                    'success' => true,
                    'data' => $result
                ]);
                break;

            case 'share_content':
                $result = shareContent($pdo, $_SESSION['user_id'], $data);
                echo json_encode([
                    'success' => true,
                    'data' => $result
                ]);
                break;

            case 'add_comment':
                $result = addComment($pdo, $_SESSION['user_id'], $data);
                echo json_encode([
                    'success' => true,
                    'data' => $result
                ]);
                break;

            case 'get_activity_feed':
                $params = $data['parameters'] ?? [];
                $result = getActivityFeed($pdo, $_SESSION['user_id'], $params);
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