<?php
require_once 'config.php';
require_once 'auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die(json_encode(['error' => 'Connection failed: ' . $conn->connect_error]));
}

// Verify JWT token and get user_id
$user_id = verify_token();
if (!$user_id) {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized']));
}

switch ($_SERVER['REQUEST_METHOD']) {
    case 'POST':
        // Create new project
        if (!isset($_GET['action'])) {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if ($stmt = $conn->prepare("
                INSERT INTO virtual_studio_projects (
                    creator_id, title, description, project_type,
                    bpm, key_signature, is_public
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ")) {
                $stmt->bind_param("isssisi", 
                    $user_id,
                    $data['title'],
                    $data['description'],
                    $data['project_type'],
                    $data['bpm'],
                    $data['key_signature'],
                    $data['is_public']
                );
                $stmt->execute();
                echo json_encode(['project_id' => $conn->insert_id]);
            }
        }
        // Add track to project
        else if ($_GET['action'] === 'add_track') {
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Verify user owns project
            if ($stmt = $conn->prepare("
                SELECT creator_id FROM virtual_studio_projects
                WHERE project_id = ? AND creator_id = ?
            ")) {
                $stmt->bind_param("ii", $data['project_id'], $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if (!$result->fetch_assoc()) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Not authorized to modify project']);
                    break;
                }
                
                // Add track
                if ($stmt = $conn->prepare("
                    INSERT INTO virtual_studio_tracks (
                        project_id, track_type, name, file_path,
                        instrument_preset, volume, pan
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)
                ")) {
                    $stmt->bind_param("issssdd", 
                        $data['project_id'],
                        $data['track_type'],
                        $data['name'],
                        $data['file_path'],
                        $data['instrument_preset'],
                        $data['volume'],
                        $data['pan']
                    );
                    $stmt->execute();
                    echo json_encode(['track_id' => $conn->insert_id]);
                }
            }
        }
        break;

    case 'GET':
        // Get project details
        if (isset($_GET['project_id'])) {
            $project_id = (int)$_GET['project_id'];
            
            if ($stmt = $conn->prepare("
                SELECT 
                    vsp.*,
                    u.username as creator_name,
                    u.avatar_url as creator_avatar
                FROM virtual_studio_projects vsp
                JOIN users u ON vsp.creator_id = u.user_id
                WHERE vsp.project_id = ?
                AND (vsp.creator_id = ? OR vsp.is_public = TRUE)
            ")) {
                $stmt->bind_param("ii", $project_id, $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($project = $result->fetch_assoc()) {
                    // Get tracks
                    $tracks_stmt = $conn->prepare("
                        SELECT * FROM virtual_studio_tracks
                        WHERE project_id = ?
                        ORDER BY track_type, name
                    ");
                    $tracks_stmt->bind_param("i", $project_id);
                    $tracks_stmt->execute();
                    $tracks = $tracks_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    
                    $project['tracks'] = $tracks;
                    echo json_encode($project);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Project not found']);
                }
            }
        }
        // List user's projects
        else {
            if ($stmt = $conn->prepare("
                SELECT 
                    vsp.*,
                    COUNT(vst.studio_track_id) as track_count
                FROM virtual_studio_projects vsp
                LEFT JOIN virtual_studio_tracks vst 
                    ON vsp.project_id = vst.project_id
                WHERE vsp.creator_id = ?
                GROUP BY vsp.project_id
                ORDER BY vsp.updated_at DESC
            ")) {
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                echo json_encode($result->fetch_all(MYSQLI_ASSOC));
            }
        }
        break;

    case 'PUT':
        if (isset($_GET['project_id'])) {
            $project_id = (int)$_GET['project_id'];
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Update project details
            if (!isset($_GET['action'])) {
                if ($stmt = $conn->prepare("
                    UPDATE virtual_studio_projects
                    SET title = ?,
                        description = ?,
                        project_type = ?,
                        bpm = ?,
                        key_signature = ?,
                        is_public = ?
                    WHERE project_id = ?
                    AND creator_id = ?
                ")) {
                    $stmt->bind_param("sssissii", 
                        $data['title'],
                        $data['description'],
                        $data['project_type'],
                        $data['bpm'],
                        $data['key_signature'],
                        $data['is_public'],
                        $project_id,
                        $user_id
                    );
                    $stmt->execute();
                    if ($conn->affected_rows > 0) {
                        echo json_encode(['success' => true]);
                    } else {
                        http_response_code(403);
                        echo json_encode(['error' => 'Not authorized to modify project']);
                    }
                }
            }
            // Update track settings
            else if ($_GET['action'] === 'update_track') {
                if ($stmt = $conn->prepare("
                    UPDATE virtual_studio_tracks
                    SET name = ?,
                        instrument_preset = ?,
                        volume = ?,
                        pan = ?,
                        muted = ?,
                        soloed = ?
                    WHERE studio_track_id = ?
                    AND project_id = ?
                    AND EXISTS (
                        SELECT 1 FROM virtual_studio_projects
                        WHERE project_id = ?
                        AND creator_id = ?
                    )
                ")) {
                    $stmt->bind_param("ssddiiiii", 
                        $data['name'],
                        $data['instrument_preset'],
                        $data['volume'],
                        $data['pan'],
                        $data['muted'],
                        $data['soloed'],
                        $data['track_id'],
                        $project_id,
                        $project_id,
                        $user_id
                    );
                    $stmt->execute();
                    if ($conn->affected_rows > 0) {
                        echo json_encode(['success' => true]);
                    } else {
                        http_response_code(403);
                        echo json_encode(['error' => 'Not authorized to modify track']);
                    }
                }
            }
        }
        break;

    case 'DELETE':
        if (isset($_GET['project_id'])) {
            $project_id = (int)$_GET['project_id'];
            
            // Delete track
            if (isset($_GET['track_id'])) {
                $track_id = (int)$_GET['track_id'];
                
                if ($stmt = $conn->prepare("
                    DELETE FROM virtual_studio_tracks
                    WHERE studio_track_id = ?
                    AND project_id = ?
                    AND EXISTS (
                        SELECT 1 FROM virtual_studio_projects
                        WHERE project_id = ?
                        AND creator_id = ?
                    )
                ")) {
                    $stmt->bind_param("iiii", 
                        $track_id,
                        $project_id,
                        $project_id,
                        $user_id
                    );
                    $stmt->execute();
                    if ($conn->affected_rows > 0) {
                        echo json_encode(['success' => true]);
                    } else {
                        http_response_code(403);
                        echo json_encode(['error' => 'Not authorized to delete track']);
                    }
                }
            }
            // Delete project
            else {
                if ($stmt = $conn->prepare("
                    DELETE FROM virtual_studio_projects
                    WHERE project_id = ?
                    AND creator_id = ?
                ")) {
                    $stmt->bind_param("ii", $project_id, $user_id);
                    $stmt->execute();
                    if ($conn->affected_rows > 0) {
                        echo json_encode(['success' => true]);
                    } else {
                        http_response_code(403);
                        echo json_encode(['error' => 'Not authorized to delete project']);
                    }
                }
            }
        }
        break;
} 