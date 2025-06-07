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
        if (isset($_GET['lesson_id'])) {
            // Pobierz szczegóły lekcji
            $lessonId = (int)$_GET['lesson_id'];
            
            $stmt = $conn->prepare("
                SELECT 
                    ml.*,
                    u.username as instructor_name,
                    u.avatar_url as instructor_avatar
                FROM music_lessons ml
                JOIN users u ON ml.instructor_id = u.user_id
                WHERE ml.lesson_id = ?
            ");
            
            $stmt->bind_param("i", $lessonId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($lesson = $result->fetch_assoc()) {
                $lesson['tags'] = json_decode($lesson['tags'], true);
                echo json_encode($lesson);
            } else {
                echo json_encode(['error' => 'Lesson not found']);
            }
        } else {
            // Lista lekcji z filtrowaniem
            $query = "
                SELECT 
                    ml.*,
                    u.username as instructor_name,
                    u.avatar_url as instructor_avatar
                FROM music_lessons ml
                JOIN users u ON ml.instructor_id = u.user_id
                WHERE 1=1
            ";
            
            $params = [];
            $types = "";
            
            if (isset($_GET['difficulty'])) {
                $query .= " AND ml.difficulty_level = ?";
                $params[] = $_GET['difficulty'];
                $types .= "s";
            }
            
            if (isset($_GET['category'])) {
                $query .= " AND ml.category = ?";
                $params[] = $_GET['category'];
                $types .= "s";
            }
            
            if (isset($_GET['instructor_id'])) {
                $query .= " AND ml.instructor_id = ?";
                $params[] = (int)$_GET['instructor_id'];
                $types .= "i";
            }
            
            // Sortowanie
            $sort = isset($_GET['sort']) ? $_GET['sort'] : 'popular';
            switch ($sort) {
                case 'price_asc':
                    $query .= " ORDER BY ml.price ASC";
                    break;
                case 'price_desc':
                    $query .= " ORDER BY ml.price DESC";
                    break;
                case 'rating':
                    $query .= " ORDER BY ml.average_rating DESC";
                    break;
                default: // 'popular'
                    $query .= " ORDER BY ml.enrollment_count DESC";
            }
            
            $query .= " LIMIT 50";
            
            $stmt = $conn->prepare($query);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            $lessons = [];
            while ($row = $result->fetch_assoc()) {
                $row['tags'] = json_decode($row['tags'], true);
                $lessons[] = $row;
            }
            
            echo json_encode(['lessons' => $lessons]);
        }
        break;

    case 'POST':
        // Dodaj nową lekcję
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['title'], $data['instructor_id'], $data['difficulty_level'], $data['duration'])) {
            echo json_encode(['error' => 'Missing required fields']);
            exit;
        }
        
        $stmt = $conn->prepare("
            INSERT INTO music_lessons (
                title, instructor_id, description, difficulty_level,
                duration, video_url, price, category, tags
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $tags = isset($data['tags']) ? json_encode($data['tags']) : null;
        
        $stmt->bind_param(
            "sississss",
            $data['title'],
            $data['instructor_id'],
            $data['description'] ?? null,
            $data['difficulty_level'],
            $data['duration'],
            $data['video_url'] ?? null,
            $data['price'] ?? 0.00,
            $data['category'] ?? null,
            $tags
        );
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'lesson_id' => $conn->insert_id,
                'message' => 'Lesson created successfully'
            ]);
        } else {
            echo json_encode(['error' => 'Failed to create lesson']);
        }
        break;

    case 'PUT':
        // Aktualizuj lekcję
        if (!isset($_GET['lesson_id'])) {
            echo json_encode(['error' => 'Missing lesson ID']);
            exit;
        }
        
        $lessonId = (int)$_GET['lesson_id'];
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
        
        if (isset($data['difficulty_level'])) {
            $updateFields[] = "difficulty_level = ?";
            $params[] = $data['difficulty_level'];
            $types .= "s";
        }
        
        if (isset($data['price'])) {
            $updateFields[] = "price = ?";
            $params[] = $data['price'];
            $types .= "d";
        }
        
        if (isset($data['tags'])) {
            $updateFields[] = "tags = ?";
            $params[] = json_encode($data['tags']);
            $types .= "s";
        }
        
        if (empty($updateFields)) {
            echo json_encode(['error' => 'No fields to update']);
            exit;
        }
        
        $params[] = $lessonId;
        $types .= "i";
        
        $stmt = $conn->prepare("
            UPDATE music_lessons 
            SET " . implode(", ", $updateFields) . "
            WHERE lesson_id = ?
        ");
        
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Lesson updated successfully'
            ]);
        } else {
            echo json_encode(['error' => 'Failed to update lesson']);
        }
        break;

    case 'DELETE':
        if (!isset($_GET['lesson_id'])) {
            echo json_encode(['error' => 'Missing lesson ID']);
            exit;
        }
        
        $lessonId = (int)$_GET['lesson_id'];
        
        $stmt = $conn->prepare("DELETE FROM music_lessons WHERE lesson_id = ?");
        $stmt->bind_param("i", $lessonId);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Lesson deleted successfully'
            ]);
        } else {
            echo json_encode(['error' => 'Failed to delete lesson']);
        }
        break;
}

$conn->close();
?> 