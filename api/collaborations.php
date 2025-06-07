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
        if (isset($_GET['project_id'])) {
            // Pobierz szczegóły projektu kolaboracji
            $projectId = (int)$_GET['project_id'];
            
            $stmt = $conn->prepare("
                SELECT 
                    cp.*,
                    u.username as creator_name,
                    t.title as source_track_title,
                    a.name as source_artist_name,
                    GROUP_CONCAT(
                        JSON_OBJECT(
                            'contribution_id', pc.contribution_id,
                            'user_id', pc.user_id,
                            'username', cu.username,
                            'type', pc.contribution_type,
                            'status', pc.status,
                            'created_at', pc.created_at
                        )
                    ) as contributions
                FROM collaboration_projects cp
                LEFT JOIN users u ON cp.creator_id = u.user_id
                LEFT JOIN tracks t ON cp.source_track_id = t.track_id
                LEFT JOIN artists a ON t.artist_id = a.artist_id
                LEFT JOIN project_contributions pc ON cp.project_id = pc.project_id
                LEFT JOIN users cu ON pc.user_id = cu.user_id
                WHERE cp.project_id = ?
                GROUP BY cp.project_id
            ");
            
            $stmt->bind_param("i", $projectId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($project = $result->fetch_assoc()) {
                $project['contributions'] = json_decode('[' . $project['contributions'] . ']', true);
                echo json_encode($project);
            } else {
                echo json_encode(['error' => 'Project not found']);
            }
        } else {
            // Lista projektów kolaboracji
            $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
            $status = isset($_GET['status']) ? $_GET['status'] : null;
            
            $query = "
                SELECT 
                    cp.*,
                    u.username as creator_name,
                    COUNT(pc.contribution_id) as contribution_count
                FROM collaboration_projects cp
                LEFT JOIN users u ON cp.creator_id = u.user_id
                LEFT JOIN project_contributions pc ON cp.project_id = pc.project_id
            ";
            
            $whereConditions = [];
            $params = [];
            $types = "";
            
            if ($userId) {
                $whereConditions[] = "cp.creator_id = ?";
                $params[] = $userId;
                $types .= "i";
            }
            
            if ($status) {
                $whereConditions[] = "cp.status = ?";
                $params[] = $status;
                $types .= "s";
            }
            
            if (!empty($whereConditions)) {
                $query .= " WHERE " . implode(" AND ", $whereConditions);
            }
            
            $query .= " GROUP BY cp.project_id ORDER BY cp.created_at DESC";
            
            $stmt = $conn->prepare($query);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            $projects = [];
            while ($row = $result->fetch_assoc()) {
                $projects[] = $row;
            }
            
            echo json_encode(['projects' => $projects]);
        }
        break;

    case 'POST':
        if (isset($_GET['project_id']) && isset($_GET['action']) && $_GET['action'] === 'contribute') {
            // Dodaj nową kontrybucję do projektu
            $projectId = (int)$_GET['project_id'];
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['user_id'], $data['contribution_type'], $data['file_path'])) {
                echo json_encode(['error' => 'Missing required fields']);
                exit;
            }
            
            $stmt = $conn->prepare("
                INSERT INTO project_contributions (
                    project_id, user_id, contribution_type,
                    file_path, notes, status
                ) VALUES (?, ?, ?, ?, ?, 'submitted')
            ");
            
            $stmt->bind_param(
                "iisss",
                $projectId,
                $data['user_id'],
                $data['contribution_type'],
                $data['file_path'],
                $data['notes'] ?? null
            );
            
            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'contribution_id' => $conn->insert_id,
                    'message' => 'Contribution added successfully'
                ]);
            } else {
                echo json_encode(['error' => 'Failed to add contribution']);
            }
        } else {
            // Utwórz nowy projekt kolaboracji
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['title'], $data['creator_id'], $data['project_type'])) {
                echo json_encode(['error' => 'Missing required fields']);
                exit;
            }
            
            $stmt = $conn->prepare("
                INSERT INTO collaboration_projects (
                    title, creator_id, project_type, status,
                    deadline, description, source_track_id
                ) VALUES (?, ?, ?, 'open', ?, ?, ?)
            ");
            
            $stmt->bind_param(
                "sissis",
                $data['title'],
                $data['creator_id'],
                $data['project_type'],
                $data['deadline'] ?? null,
                $data['description'] ?? null,
                $data['source_track_id'] ?? null
            );
            
            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'project_id' => $conn->insert_id,
                    'message' => 'Project created successfully'
                ]);
            } else {
                echo json_encode(['error' => 'Failed to create project']);
            }
        }
        break;

    case 'PUT':
        if (!isset($_GET['project_id'])) {
            echo json_encode(['error' => 'Missing project ID']);
            exit;
        }
        
        $projectId = (int)$_GET['project_id'];
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (isset($_GET['contribution_id'])) {
            // Aktualizuj status kontrybucji
            $contributionId = (int)$_GET['contribution_id'];
            
            if (!isset($data['status'])) {
                echo json_encode(['error' => 'Missing status']);
                exit;
            }
            
            $stmt = $conn->prepare("
                UPDATE project_contributions 
                SET status = ?
                WHERE contribution_id = ? AND project_id = ?
            ");
            
            $stmt->bind_param("sii", $data['status'], $contributionId, $projectId);
        } else {
            // Aktualizuj projekt
            $updateFields = [];
            $params = [];
            $types = "";
            
            if (isset($data['status'])) {
                $updateFields[] = "status = ?";
                $params[] = $data['status'];
                $types .= "s";
            }
            
            if (isset($data['deadline'])) {
                $updateFields[] = "deadline = ?";
                $params[] = $data['deadline'];
                $types .= "s";
            }
            
            if (isset($data['description'])) {
                $updateFields[] = "description = ?";
                $params[] = $data['description'];
                $types .= "s";
            }
            
            if (empty($updateFields)) {
                echo json_encode(['error' => 'No fields to update']);
                exit;
            }
            
            $params[] = $projectId;
            $types .= "i";
            
            $stmt = $conn->prepare("
                UPDATE collaboration_projects 
                SET " . implode(", ", $updateFields) . "
                WHERE project_id = ?
            ");
            
            $stmt->bind_param($types, ...$params);
        }
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Update successful'
            ]);
        } else {
            echo json_encode(['error' => 'Failed to update']);
        }
        break;

    case 'DELETE':
        if (!isset($_GET['project_id'])) {
            echo json_encode(['error' => 'Missing project ID']);
            exit;
        }
        
        $projectId = (int)$_GET['project_id'];
        
        if (isset($_GET['contribution_id'])) {
            // Usuń kontrybucję
            $contributionId = (int)$_GET['contribution_id'];
            $stmt = $conn->prepare("
                DELETE FROM project_contributions 
                WHERE contribution_id = ? AND project_id = ?
            ");
            $stmt->bind_param("ii", $contributionId, $projectId);
        } else {
            // Usuń cały projekt
            $stmt = $conn->prepare("DELETE FROM collaboration_projects WHERE project_id = ?");
            $stmt->bind_param("i", $projectId);
        }
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Deleted successfully'
            ]);
        } else {
            echo json_encode(['error' => 'Failed to delete']);
        }
        break;
}

$conn->close();
?> 