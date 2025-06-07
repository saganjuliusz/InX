<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Użytkownik nie jest zalogowany.'
    ]);
    exit;
}

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die(json_encode(['error' => 'Connection failed: ' . $conn->connect_error]));
}

function sanitizeInput($data) {
    return htmlspecialchars(strip_tags($data));
}

function addComment($pdo, $targetType, $targetId, $content, $timestampReference = null) {
    // Walidacja typu celu
    $validTypes = ['track', 'album', 'playlist', 'artist'];
    if (!in_array($targetType, $validTypes)) {
        throw new Exception('Nieprawidłowy typ celu komentarza.');
    }

    // Walidacja treści
    if (empty($content)) {
        throw new Exception('Treść komentarza nie może być pusta.');
    }

    // Sprawdź czy cel istnieje
    $tableMap = [
        'track' => 'tracks',
        'album' => 'albums',
        'playlist' => 'playlists',
        'artist' => 'artists'
    ];

    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM " . $tableMap[$targetType] . "
        WHERE " . $targetType . "_id = ?
    ");
    $stmt->execute([$targetId]);
    
    if ($stmt->fetchColumn() == 0) {
        throw new Exception('Cel komentarza nie istnieje.');
    }

    // Dodaj komentarz
    $stmt = $pdo->prepare("
        INSERT INTO comments (
            user_id,
            target_type,
            target_id,
            content,
            timestamp_reference,
            is_public,
            language,
            created_at
        ) VALUES (?, ?, ?, ?, ?, TRUE, ?, CURRENT_TIMESTAMP)
    ");

    $stmt->execute([
        $_SESSION['user_id'],
        $targetType,
        $targetId,
        $content,
        $timestampReference,
        substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en', 0, 2)
    ]);

    return $pdo->lastInsertId();
}

function addReply($pdo, $commentId, $content) {
    // Sprawdź czy komentarz istnieje i jest publiczny
    $stmt = $pdo->prepare("
        SELECT is_public 
        FROM comments 
        WHERE comment_id = ?
    ");
    $stmt->execute([$commentId]);
    $comment = $stmt->fetch();

    if (!$comment) {
        throw new Exception('Komentarz nie istnieje.');
    }

    if (!$comment['is_public']) {
        throw new Exception('Nie można odpowiedzieć na niepubliczny komentarz.');
    }

    // Walidacja treści
    if (empty($content)) {
        throw new Exception('Treść odpowiedzi nie może być pusta.');
    }

    // Dodaj odpowiedź
    $stmt = $pdo->prepare("
        INSERT INTO comment_replies (
            comment_id,
            user_id,
            content,
            created_at
        ) VALUES (?, ?, ?, CURRENT_TIMESTAMP)
    ");

    $stmt->execute([
        $commentId,
        $_SESSION['user_id'],
        $content
    ]);

    // Aktualizuj licznik odpowiedzi
    $stmt = $pdo->prepare("
        UPDATE comments 
        SET reply_count = reply_count + 1 
        WHERE comment_id = ?
    ");
    $stmt->execute([$commentId]);

    return $pdo->lastInsertId();
}

function getComments($pdo, $targetType, $targetId, $limit = 50, $offset = 0) {
    // Pobierz komentarze
    $stmt = $pdo->prepare("
        SELECT 
            c.comment_id,
            c.content,
            c.timestamp_reference,
            c.created_at,
            c.like_count,
            c.reply_count,
            u.user_id,
            u.username,
            u.display_name,
            u.avatar_url
        FROM comments c
        JOIN users u ON c.user_id = u.user_id
        WHERE c.target_type = ?
        AND c.target_id = ?
        AND c.is_public = TRUE
        AND c.moderation_status = 'approved'
        ORDER BY c.created_at DESC
        LIMIT ? OFFSET ?
    ");
    
    $stmt->execute([$targetType, $targetId, $limit, $offset]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Pobierz odpowiedzi dla komentarzy
    foreach ($comments as &$comment) {
        $stmt = $pdo->prepare("
            SELECT 
                r.reply_id,
                r.content,
                r.created_at,
                r.like_count,
                u.user_id,
                u.username,
                u.display_name,
                u.avatar_url
            FROM comment_replies r
            JOIN users u ON r.user_id = u.user_id
            WHERE r.comment_id = ?
            AND r.is_flagged = FALSE
            ORDER BY r.created_at ASC
        ");
        
        $stmt->execute([$comment['comment_id']]);
        $comment['replies'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    return $comments;
}

function deleteComment($pdo, $commentId) {
    // Sprawdź czy użytkownik jest właścicielem komentarza
    $stmt = $pdo->prepare("
        SELECT user_id 
        FROM comments 
        WHERE comment_id = ?
    ");
    $stmt->execute([$commentId]);
    $comment = $stmt->fetch();

    if (!$comment) {
        throw new Exception('Komentarz nie istnieje.');
    }

    if ($comment['user_id'] !== $_SESSION['user_id']) {
        throw new Exception('Nie masz uprawnień do usunięcia tego komentarza.');
    }

    // Usuń komentarz i wszystkie odpowiedzi
    $stmt = $pdo->prepare("DELETE FROM comments WHERE comment_id = ?");
    $stmt->execute([$commentId]);
}

function deleteReply($pdo, $replyId) {
    // Sprawdź czy użytkownik jest właścicielem odpowiedzi
    $stmt = $pdo->prepare("
        SELECT user_id, comment_id 
        FROM comment_replies 
        WHERE reply_id = ?
    ");
    $stmt->execute([$replyId]);
    $reply = $stmt->fetch();

    if (!$reply) {
        throw new Exception('Odpowiedź nie istnieje.');
    }

    if ($reply['user_id'] !== $_SESSION['user_id']) {
        throw new Exception('Nie masz uprawnień do usunięcia tej odpowiedzi.');
    }

    // Usuń odpowiedź
    $stmt = $pdo->prepare("DELETE FROM comment_replies WHERE reply_id = ?");
    $stmt->execute([$replyId]);

    // Aktualizuj licznik odpowiedzi
    $stmt = $pdo->prepare("
        UPDATE comments 
        SET reply_count = reply_count - 1 
        WHERE comment_id = ?
    ");
    $stmt->execute([$reply['comment_id']]);
}

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        // Pobierz komentarze dla określonego utworu
        $trackId = isset($_GET['track_id']) ? (int)$_GET['track_id'] : 0;
        
        if ($trackId <= 0) {
            echo json_encode(['error' => 'Invalid track ID']);
            exit;
        }

        $stmt = $conn->prepare("
            SELECT c.*, u.username, u.avatar_url 
            FROM comments c 
            JOIN users u ON c.user_id = u.user_id 
            WHERE c.target_type = 'track' 
            AND c.target_id = ? 
            AND c.is_public = 1 
            AND c.moderation_status = 'approved'
            ORDER BY c.created_at DESC
        ");
        
        $stmt->bind_param("i", $trackId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $comments = [];
        while ($row = $result->fetch_assoc()) {
            $comments[] = [
                'comment_id' => $row['comment_id'],
                'content' => $row['content'],
                'timestamp_reference' => $row['timestamp_reference'],
                'username' => $row['username'],
                'avatar_url' => $row['avatar_url'],
                'like_count' => $row['like_count'],
                'created_at' => $row['created_at']
            ];
        }
        
        echo json_encode($comments);
        break;

    case 'POST':
        // Dodaj nowy komentarz
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['user_id'], $data['track_id'], $data['content'])) {
            echo json_encode(['error' => 'Missing required fields']);
            exit;
        }

        $userId = (int)$data['user_id'];
        $trackId = (int)$data['track_id'];
        $content = sanitizeInput($data['content']);
        $timestampRef = isset($data['timestamp_reference']) ? (int)$data['timestamp_reference'] : null;

        $stmt = $conn->prepare("
            INSERT INTO comments (user_id, target_type, target_id, content, timestamp_reference)
            VALUES (?, 'track', ?, ?, ?)
        ");
        
        $stmt->bind_param("iisi", $userId, $trackId, $content, $timestampRef);
        
        if ($stmt->execute()) {
            $commentId = $conn->insert_id;
            echo json_encode([
                'success' => true,
                'comment_id' => $commentId,
                'message' => 'Comment added successfully'
            ]);
        } else {
            echo json_encode(['error' => 'Failed to add comment']);
        }
        break;

    case 'DELETE':
        // Usuń komentarz
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['comment_id'], $data['user_id'])) {
            echo json_encode(['error' => 'Missing required fields']);
            exit;
        }

        $commentId = (int)$data['comment_id'];
        $userId = (int)$data['user_id'];

        $stmt = $conn->prepare("
            DELETE FROM comments 
            WHERE comment_id = ? AND user_id = ?
        ");
        
        $stmt->bind_param("ii", $commentId, $userId);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Comment deleted successfully'
            ]);
        } else {
            echo json_encode(['error' => 'Failed to delete comment']);
        }
        break;
}

$conn->close();
?> 