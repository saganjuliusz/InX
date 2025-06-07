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
        if (isset($_GET['mix_id'])) {
            // Pobierz szczegóły mixu DJ-skiego
            $mixId = (int)$_GET['mix_id'];
            
            // Najpierw pobierz informacje o mixie
            $stmt = $conn->prepare("
                SELECT 
                    dm.*,
                    u.username as dj_name,
                    u.avatar_url as dj_avatar
                FROM dj_mixes dm
                JOIN users u ON dm.user_id = u.user_id
                WHERE dm.mix_id = ?
            ");
            
            $stmt->bind_param("i", $mixId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($mix = $result->fetch_assoc()) {
                // Pobierz tracklistę
                $stmt = $conn->prepare("
                    SELECT 
                        mt.*,
                        t.title as track_title,
                        a.name as artist_name
                    FROM mix_tracklist mt
                    JOIN tracks t ON mt.track_id = t.track_id
                    JOIN artists a ON t.artist_id = a.artist_id
                    WHERE mt.mix_id = ?
                    ORDER BY mt.position
                ");
                
                $stmt->bind_param("i", $mixId);
                $stmt->execute();
                $tracklist = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                
                $mix['tracklist'] = $tracklist;
                $mix['genre_tags'] = json_decode($mix['genre_tags'], true);
                $mix['waveform_data'] = json_decode($mix['waveform_data'], true);
                
                echo json_encode($mix);
            } else {
                echo json_encode(['error' => 'Mix not found']);
            }
        } else {
            // Lista mixów z filtrowaniem
            $query = "
                SELECT 
                    dm.*,
                    u.username as dj_name,
                    u.avatar_url as dj_avatar
                FROM dj_mixes dm
                JOIN users u ON dm.user_id = u.user_id
                WHERE 1=1
            ";
            
            $params = [];
            $types = "";
            
            if (isset($_GET['mix_type'])) {
                $query .= " AND dm.mix_type = ?";
                $params[] = $_GET['mix_type'];
                $types .= "s";
            }
            
            if (isset($_GET['user_id'])) {
                $query .= " AND dm.user_id = ?";
                $params[] = (int)$_GET['user_id'];
                $types .= "i";
            }
            
            // Sortowanie
            $sort = isset($_GET['sort']) ? $_GET['sort'] : 'recent';
            switch ($sort) {
                case 'popular':
                    $query .= " ORDER BY dm.play_count DESC";
                    break;
                case 'likes':
                    $query .= " ORDER BY dm.like_count DESC";
                    break;
                default: // 'recent'
                    $query .= " ORDER BY dm.created_at DESC";
            }
            
            $query .= " LIMIT 50";
            
            $stmt = $conn->prepare($query);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            $mixes = [];
            while ($row = $result->fetch_assoc()) {
                $row['genre_tags'] = json_decode($row['genre_tags'], true);
                $mixes[] = $row;
            }
            
            echo json_encode(['mixes' => $mixes]);
        }
        break;

    case 'POST':
        // Dodaj nowy mix
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['user_id'], $data['title'], $data['duration'], $data['mix_type'])) {
            echo json_encode(['error' => 'Missing required fields']);
            exit;
        }
        
        // Rozpocznij transakcję
        $conn->begin_transaction();
        
        try {
            // Najpierw dodaj mix
            $stmt = $conn->prepare("
                INSERT INTO dj_mixes (
                    user_id, title, description, duration,
                    bpm_range, mix_type, genre_tags, file_path,
                    waveform_data
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $genreTags = isset($data['genre_tags']) ? json_encode($data['genre_tags']) : null;
            $waveformData = isset($data['waveform_data']) ? json_encode($data['waveform_data']) : null;
            
            $stmt->bind_param(
                "ississsss",
                $data['user_id'],
                $data['title'],
                $data['description'] ?? null,
                $data['duration'],
                $data['bpm_range'] ?? null,
                $data['mix_type'],
                $genreTags,
                $data['file_path'] ?? null,
                $waveformData
            );
            
            $stmt->execute();
            $mixId = $conn->insert_id;
            
            // Następnie dodaj tracklistę
            if (isset($data['tracklist']) && is_array($data['tracklist'])) {
                $stmt = $conn->prepare("
                    INSERT INTO mix_tracklist (
                        mix_id, track_id, position,
                        start_time, end_time, transition_type, notes
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                
                foreach ($data['tracklist'] as $position => $track) {
                    $stmt->bind_param(
                        "iiiiiss",
                        $mixId,
                        $track['track_id'],
                        $position + 1,
                        $track['start_time'],
                        $track['end_time'],
                        $track['transition_type'] ?? null,
                        $track['notes'] ?? null
                    );
                    $stmt->execute();
                }
            }
            
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'mix_id' => $mixId,
                'message' => 'Mix created successfully'
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['error' => 'Failed to create mix: ' . $e->getMessage()]);
        }
        break;

    case 'PUT':
        // Aktualizuj mix
        if (!isset($_GET['mix_id'])) {
            echo json_encode(['error' => 'Missing mix ID']);
            exit;
        }
        
        $mixId = (int)$_GET['mix_id'];
        $data = json_decode(file_get_contents('php://input'), true);
        
        $conn->begin_transaction();
        
        try {
            // Aktualizuj podstawowe informacje o mixie
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
            
            if (isset($data['genre_tags'])) {
                $updateFields[] = "genre_tags = ?";
                $params[] = json_encode($data['genre_tags']);
                $types .= "s";
            }
            
            if (!empty($updateFields)) {
                $params[] = $mixId;
                $types .= "i";
                
                $stmt = $conn->prepare("
                    UPDATE dj_mixes 
                    SET " . implode(", ", $updateFields) . "
                    WHERE mix_id = ?
                ");
                
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
            }
            
            // Aktualizuj tracklistę jeśli została dostarczona
            if (isset($data['tracklist']) && is_array($data['tracklist'])) {
                // Usuń starą tracklistę
                $stmt = $conn->prepare("DELETE FROM mix_tracklist WHERE mix_id = ?");
                $stmt->bind_param("i", $mixId);
                $stmt->execute();
                
                // Dodaj nową tracklistę
                $stmt = $conn->prepare("
                    INSERT INTO mix_tracklist (
                        mix_id, track_id, position,
                        start_time, end_time, transition_type, notes
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                
                foreach ($data['tracklist'] as $position => $track) {
                    $stmt->bind_param(
                        "iiiiiss",
                        $mixId,
                        $track['track_id'],
                        $position + 1,
                        $track['start_time'],
                        $track['end_time'],
                        $track['transition_type'] ?? null,
                        $track['notes'] ?? null
                    );
                    $stmt->execute();
                }
            }
            
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Mix updated successfully'
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['error' => 'Failed to update mix: ' . $e->getMessage()]);
        }
        break;

    case 'DELETE':
        if (!isset($_GET['mix_id'])) {
            echo json_encode(['error' => 'Missing mix ID']);
            exit;
        }
        
        $mixId = (int)$_GET['mix_id'];
        
        $conn->begin_transaction();
        
        try {
            // Usuń tracklistę
            $stmt = $conn->prepare("DELETE FROM mix_tracklist WHERE mix_id = ?");
            $stmt->bind_param("i", $mixId);
            $stmt->execute();
            
            // Usuń mix
            $stmt = $conn->prepare("DELETE FROM dj_mixes WHERE mix_id = ?");
            $stmt->bind_param("i", $mixId);
            $stmt->execute();
            
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Mix deleted successfully'
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['error' => 'Failed to delete mix: ' . $e->getMessage()]);
        }
        break;
}

$conn->close();
?> 