<?php
require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die(json_encode(['error' => 'Connection failed: ' . $conn->connect_error]));
}

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        if (isset($_GET['podcast_id'])) {
            // Pobierz szczegóły podcastu
            $podcastId = (int)$_GET['podcast_id'];
            
            $stmt = $conn->prepare("
                SELECT 
                    p.*,
                    COUNT(pe.episode_id) as episode_count,
                    SUM(pe.duration) as total_duration,
                    GROUP_CONCAT(
                        JSON_OBJECT(
                            'episode_id', pe.episode_id,
                            'title', pe.title,
                            'duration', pe.duration,
                            'release_date', pe.release_date,
                            'play_count', pe.play_count
                        )
                    ) as episodes
                FROM podcasts p
                LEFT JOIN podcast_episodes pe ON p.podcast_id = pe.podcast_id
                WHERE p.podcast_id = ?
                GROUP BY p.podcast_id
            ");
            
            $stmt->bind_param("i", $podcastId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($podcast = $result->fetch_assoc()) {
                $podcast['episodes'] = json_decode('[' . $podcast['episodes'] . ']', true);
                echo json_encode($podcast);
            } else {
                echo json_encode(['error' => 'Podcast not found']);
            }
        } else {
            // Lista podcastów z paginacją
            $page = isset($_GET['page']) ? max(0, (int)$_GET['page'] - 1) : 0;
            $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 50) : 20;
            $offset = $page * $limit;
            
            $stmt = $conn->prepare("
                SELECT 
                    p.*,
                    COUNT(pe.episode_id) as episode_count
                FROM podcasts p
                LEFT JOIN podcast_episodes pe ON p.podcast_id = pe.podcast_id
                GROUP BY p.podcast_id
                ORDER BY p.subscriber_count DESC
                LIMIT ?, ?
            ");
            
            $stmt->bind_param("ii", $offset, $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $podcasts = [];
            while ($row = $result->fetch_assoc()) {
                $podcasts[] = $row;
            }
            
            echo json_encode(['podcasts' => $podcasts]);
        }
        break;

    case 'POST':
        // Dodaj nowy podcast
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['title'], $data['creator_id'])) {
            echo json_encode(['error' => 'Missing required fields']);
            exit;
        }
        
        $stmt = $conn->prepare("
            INSERT INTO podcasts (
                title, creator_id, description, cover_art_url, 
                rss_feed_url, language, explicit_content
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param(
            "sissssi",
            $data['title'],
            $data['creator_id'],
            $data['description'] ?? null,
            $data['cover_art_url'] ?? null,
            $data['rss_feed_url'] ?? null,
            $data['language'] ?? 'en',
            $data['explicit_content'] ?? false
        );
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'podcast_id' => $conn->insert_id,
                'message' => 'Podcast created successfully'
            ]);
        } else {
            echo json_encode(['error' => 'Failed to create podcast']);
        }
        break;

    case 'PUT':
        // Aktualizuj podcast
        if (!isset($_GET['podcast_id'])) {
            echo json_encode(['error' => 'Missing podcast ID']);
            exit;
        }
        
        $podcastId = (int)$_GET['podcast_id'];
        $data = json_decode(file_get_contents('php://input'), true);
        
        $updateFields = [];
        $params = [];
        $types = "";
        
        if (isset($data['title'])) {
            $updateFields[] = "title = ?";
            $params[] = $data['title'];
            $types .= "s";
        }
        
        if (isset($data['description'])) {
            $updateFields[] = "description = ?";
            $params[] = $data['description'];
            $types .= "s";
        }
        
        if (isset($data['cover_art_url'])) {
            $updateFields[] = "cover_art_url = ?";
            $params[] = $data['cover_art_url'];
            $types .= "s";
        }
        
        if (empty($updateFields)) {
            echo json_encode(['error' => 'No fields to update']);
            exit;
        }
        
        $params[] = $podcastId;
        $types .= "i";
        
        $stmt = $conn->prepare("
            UPDATE podcasts 
            SET " . implode(", ", $updateFields) . "
            WHERE podcast_id = ?
        ");
        
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Podcast updated successfully'
            ]);
        } else {
            echo json_encode(['error' => 'Failed to update podcast']);
        }
        break;

    case 'DELETE':
        if (!isset($_GET['podcast_id'])) {
            echo json_encode(['error' => 'Missing podcast ID']);
            exit;
        }
        
        $podcastId = (int)$_GET['podcast_id'];
        
        $stmt = $conn->prepare("DELETE FROM podcasts WHERE podcast_id = ?");
        $stmt->bind_param("i", $podcastId);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Podcast deleted successfully'
            ]);
        } else {
            echo json_encode(['error' => 'Failed to delete podcast']);
        }
        break;
}

$conn->close();
?> 