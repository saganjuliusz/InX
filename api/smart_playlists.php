<?php
require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die(json_encode(['error' => 'Connection failed: ' . $conn->connect_error]));
}

switch ($_SERVER['REQUEST_METHOD']) {
    case 'POST':
        // Utwórz nową inteligentną playlistę
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['user_id'], $data['name'], $data['criteria'])) {
            echo json_encode(['error' => 'Missing required fields']);
            exit;
        }

        $userId = (int)$data['user_id'];
        $name = $data['name'];
        $criteria = json_encode($data['criteria']);

        // Wywołaj procedurę tworzenia inteligentnej playlisty
        $stmt = $conn->prepare("CALL create_smart_playlist(?, ?, ?)");
        $stmt->bind_param("iss", $userId, $name, $criteria);
        
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $playlistId = $result->fetch_assoc()['playlist_id'];
            $stmt->close();

            // Pobierz szczegóły utworzonej playlisty
            $stmt = $conn->prepare("
                SELECT 
                    p.*,
                    COUNT(pt.track_id) as track_count,
                    SUM(t.duration) as total_duration
                FROM playlists p
                LEFT JOIN playlist_tracks pt ON p.playlist_id = pt.playlist_id
                LEFT JOIN tracks t ON pt.track_id = t.track_id
                WHERE p.playlist_id = ?
                GROUP BY p.playlist_id
            ");
            
            $stmt->bind_param("i", $playlistId);
            $stmt->execute();
            $result = $stmt->get_result();
            $playlist = $result->fetch_assoc();

            echo json_encode([
                'success' => true,
                'message' => 'Smart playlist created successfully',
                'playlist' => [
                    'id' => $playlist['playlist_id'],
                    'name' => $playlist['name'],
                    'track_count' => (int)$playlist['track_count'],
                    'total_duration' => (int)$playlist['total_duration'],
                    'criteria' => json_decode($playlist['update_criteria'], true)
                ]
            ]);
        } else {
            echo json_encode(['error' => 'Failed to create smart playlist']);
        }
        break;

    case 'GET':
        if (isset($_GET['playlist_id'])) {
            // Pobierz szczegóły konkretnej playlisty
            $playlistId = (int)$_GET['playlist_id'];
            
            // Pobierz informacje o playliście
            $stmt = $conn->prepare("
                SELECT 
                    p.*,
                    COUNT(pt.track_id) as track_count,
                    SUM(t.duration) as total_duration
                FROM playlists p
                LEFT JOIN playlist_tracks pt ON p.playlist_id = pt.playlist_id
                LEFT JOIN tracks t ON pt.track_id = t.track_id
                WHERE p.playlist_id = ?
                AND p.playlist_type = 'algorithmic'
                GROUP BY p.playlist_id
            ");
            
            $stmt->bind_param("i", $playlistId);
            $stmt->execute();
            $result = $stmt->get_result();
            $playlist = $result->fetch_assoc();

            if (!$playlist) {
                echo json_encode(['error' => 'Smart playlist not found']);
                exit;
            }

            // Pobierz utwory z playlisty
            $stmt = $conn->prepare("
                SELECT 
                    t.track_id,
                    t.title,
                    t.duration,
                    t.energy_level,
                    t.valence,
                    a.name as artist_name,
                    a.artist_id,
                    al.title as album_title,
                    al.album_id,
                    al.cover_art_url,
                    pt.position
                FROM playlist_tracks pt
                JOIN tracks t ON pt.track_id = t.track_id
                JOIN artists a ON t.artist_id = a.artist_id
                LEFT JOIN albums al ON t.album_id = al.album_id
                WHERE pt.playlist_id = ?
                ORDER BY pt.position
            ");
            
            $stmt->bind_param("i", $playlistId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $tracks = [];
            while ($row = $result->fetch_assoc()) {
                $tracks[] = [
                    'id' => $row['track_id'],
                    'title' => $row['title'],
                    'duration' => (int)$row['duration'],
                    'position' => (int)$row['position'],
                    'energy_level' => (float)$row['energy_level'],
                    'valence' => (float)$row['valence'],
                    'artist' => [
                        'id' => $row['artist_id'],
                        'name' => $row['artist_name']
                    ],
                    'album' => [
                        'id' => $row['album_id'],
                        'title' => $row['album_title'],
                        'cover_art_url' => $row['cover_art_url']
                    ]
                ];
            }

            echo json_encode([
                'playlist' => [
                    'id' => $playlist['playlist_id'],
                    'name' => $playlist['name'],
                    'track_count' => (int)$playlist['track_count'],
                    'total_duration' => (int)$playlist['total_duration'],
                    'criteria' => json_decode($playlist['update_criteria'], true),
                    'tracks' => $tracks
                ]
            ]);
        } else if (isset($_GET['user_id'])) {
            // Pobierz wszystkie inteligentne playlisty użytkownika
            $userId = (int)$_GET['user_id'];
            
            $stmt = $conn->prepare("
                SELECT 
                    p.*,
                    COUNT(pt.track_id) as track_count,
                    SUM(t.duration) as total_duration
                FROM playlists p
                LEFT JOIN playlist_tracks pt ON p.playlist_id = pt.playlist_id
                LEFT JOIN tracks t ON pt.track_id = t.track_id
                WHERE p.user_id = ?
                AND p.playlist_type = 'algorithmic'
                GROUP BY p.playlist_id
                ORDER BY p.created_at DESC
            ");
            
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $playlists = [];
            while ($row = $result->fetch_assoc()) {
                $playlists[] = [
                    'id' => $row['playlist_id'],
                    'name' => $row['name'],
                    'track_count' => (int)$row['track_count'],
                    'total_duration' => (int)$row['total_duration'],
                    'criteria' => json_decode($row['update_criteria'], true),
                    'created_at' => $row['created_at']
                ];
            }

            echo json_encode(['playlists' => $playlists]);
        } else {
            echo json_encode(['error' => 'Missing user_id or playlist_id parameter']);
        }
        break;
}

$conn->close(); 