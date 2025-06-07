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

function createPlaylist($pdo, $userId, $data) {
    // Walidacja danych
    if (empty($data['name'])) {
        throw new Exception('Nazwa playlisty jest wymagana.');
    }

    // Sprawdź czy nazwa nie jest już zajęta
    $stmt = $pdo->prepare("
        SELECT playlist_id
        FROM playlists
        WHERE user_id = ? AND name = ?
    ");
    
    $stmt->execute([$userId, $data['name']]);
    if ($stmt->fetch()) {
        throw new Exception('Playlista o tej nazwie już istnieje.');
    }

    // Utwórz playlistę
    $stmt = $pdo->prepare("
        INSERT INTO playlists (
            user_id,
            name,
            description,
            visibility,
            created_at,
            updated_at,
            cover_image,
            tags,
            settings
        ) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, ?, ?, ?)
    ");

    $stmt->execute([
        $userId,
        $data['name'],
        $data['description'] ?? null,
        $data['visibility'] ?? 'private',
        $data['cover_image'] ?? null,
        json_encode($data['tags'] ?? []),
        json_encode($data['settings'] ?? [
            'allow_comments' => true,
            'allow_collaborative' => false,
            'sort_order' => 'custom'
        ])
    ]);

    $playlistId = $pdo->lastInsertId();

    // Dodaj utwory jeśli zostały podane
    if (!empty($data['tracks'])) {
        $position = 0;
        $stmt = $pdo->prepare("
            INSERT INTO playlist_tracks (
                playlist_id,
                track_id,
                position,
                added_at,
                added_by
            ) VALUES (?, ?, ?, CURRENT_TIMESTAMP, ?)
        ");

        foreach ($data['tracks'] as $trackId) {
            $stmt->execute([
                $playlistId,
                $trackId,
                $position++,
                $userId
            ]);
        }
    }

    // Pobierz utworzoną playlistę
    return getPlaylistDetails($pdo, $playlistId);
}

function updatePlaylist($pdo, $userId, $playlistId, $data) {
    // Sprawdź czy playlista istnieje i czy użytkownik ma do niej prawa
    $stmt = $pdo->prepare("
        SELECT *
        FROM playlists
        WHERE playlist_id = ? AND (user_id = ? OR visibility = 'collaborative')
    ");
    
    $stmt->execute([$playlistId, $userId]);
    $playlist = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$playlist) {
        throw new Exception('Playlista nie istnieje lub brak uprawnień do edycji.');
    }

    // Przygotuj dane do aktualizacji
    $updates = [];
    $params = [];

    if (isset($data['name'])) {
        $updates[] = 'name = ?';
        $params[] = $data['name'];
    }

    if (isset($data['description'])) {
        $updates[] = 'description = ?';
        $params[] = $data['description'];
    }

    if (isset($data['visibility'])) {
        $updates[] = 'visibility = ?';
        $params[] = $data['visibility'];
    }

    if (isset($data['cover_image'])) {
        $updates[] = 'cover_image = ?';
        $params[] = $data['cover_image'];
    }

    if (isset($data['tags'])) {
        $updates[] = 'tags = ?';
        $params[] = json_encode($data['tags']);
    }

    if (isset($data['settings'])) {
        $updates[] = 'settings = ?';
        $params[] = json_encode(array_merge(
            json_decode($playlist['settings'], true),
            $data['settings']
        ));
    }

    if (!empty($updates)) {
        $updates[] = 'updated_at = CURRENT_TIMESTAMP';
        $params[] = $playlistId;
        $params[] = $userId;

        $stmt = $pdo->prepare("
            UPDATE playlists
            SET " . implode(', ', $updates) . "
            WHERE playlist_id = ? AND (user_id = ? OR visibility = 'collaborative')
        ");

        $stmt->execute($params);
    }

    // Aktualizuj utwory jeśli zostały podane
    if (isset($data['tracks'])) {
        // Usuń istniejące utwory
        $stmt = $pdo->prepare("
            DELETE FROM playlist_tracks
            WHERE playlist_id = ?
        ");
        
        $stmt->execute([$playlistId]);

        // Dodaj nowe utwory
        if (!empty($data['tracks'])) {
            $position = 0;
            $stmt = $pdo->prepare("
                INSERT INTO playlist_tracks (
                    playlist_id,
                    track_id,
                    position,
                    added_at,
                    added_by
                ) VALUES (?, ?, ?, CURRENT_TIMESTAMP, ?)
            ");

            foreach ($data['tracks'] as $trackId) {
                $stmt->execute([
                    $playlistId,
                    $trackId,
                    $position++,
                    $userId
                ]);
            }
        }
    }

    // Pobierz zaktualizowaną playlistę
    return getPlaylistDetails($pdo, $playlistId);
}

function getPlaylistDetails($pdo, $playlistId) {
    // Pobierz podstawowe informacje o playliście
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            u.username as creator_name,
            COUNT(DISTINCT pt.track_id) as track_count,
            COUNT(DISTINCT pf.user_id) as follower_count
        FROM playlists p
        JOIN users u ON p.user_id = u.user_id
        LEFT JOIN playlist_tracks pt ON p.playlist_id = pt.playlist_id
        LEFT JOIN playlist_followers pf ON p.playlist_id = pf.playlist_id
        WHERE p.playlist_id = ?
        GROUP BY p.playlist_id
    ");
    
    $stmt->execute([$playlistId]);
    $playlist = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$playlist) {
        throw new Exception('Playlista nie istnieje.');
    }

    // Pobierz utwory
    $stmt = $pdo->prepare("
        SELECT 
            t.*,
            pt.position,
            pt.added_at,
            u.username as added_by_name
        FROM playlist_tracks pt
        JOIN tracks t ON pt.track_id = t.track_id
        JOIN users u ON pt.added_by = u.user_id
        WHERE pt.playlist_id = ?
        ORDER BY pt.position
    ");
    
    $stmt->execute([$playlistId]);
    $playlist['tracks'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Oblicz czas trwania
    $playlist['duration'] = array_sum(array_column($playlist['tracks'], 'duration'));

    // Pobierz tagi i ustawienia
    $playlist['tags'] = json_decode($playlist['tags'], true);
    $playlist['settings'] = json_decode($playlist['settings'], true);

    return $playlist;
}

function addTracksToPlaylist($pdo, $userId, $playlistId, $tracks) {
    // Sprawdź uprawnienia
    $stmt = $pdo->prepare("
        SELECT *
        FROM playlists
        WHERE playlist_id = ? AND (user_id = ? OR visibility = 'collaborative')
    ");
    
    $stmt->execute([$playlistId, $userId]);
    if (!$stmt->fetch()) {
        throw new Exception('Brak uprawnień do modyfikacji playlisty.');
    }

    // Pobierz aktualną najwyższą pozycję
    $stmt = $pdo->prepare("
        SELECT COALESCE(MAX(position), -1) as max_position
        FROM playlist_tracks
        WHERE playlist_id = ?
    ");
    
    $stmt->execute([$playlistId]);
    $position = $stmt->fetchColumn() + 1;

    // Dodaj nowe utwory
    $stmt = $pdo->prepare("
        INSERT INTO playlist_tracks (
            playlist_id,
            track_id,
            position,
            added_at,
            added_by
        ) VALUES (?, ?, ?, CURRENT_TIMESTAMP, ?)
    ");

    $addedTracks = [];
    foreach ($tracks as $trackId) {
        try {
            $stmt->execute([
                $playlistId,
                $trackId,
                $position++,
                $userId
            ]);
            $addedTracks[] = $trackId;
        } catch (PDOException $e) {
            // Ignoruj duplikaty
            if ($e->getCode() != 23000) {
                throw $e;
            }
        }
    }

    // Aktualizuj timestamp playlisty
    $stmt = $pdo->prepare("
        UPDATE playlists
        SET 
            updated_at = CURRENT_TIMESTAMP,
            track_count = (
                SELECT COUNT(*)
                FROM playlist_tracks
                WHERE playlist_id = ?
            )
        WHERE playlist_id = ?
    ");
    
    $stmt->execute([$playlistId, $playlistId]);

    return [
        'playlist_id' => $playlistId,
        'added_tracks' => $addedTracks,
        'new_position' => $position
    ];
}

function removeTracksFromPlaylist($pdo, $userId, $playlistId, $tracks) {
    // Sprawdź uprawnienia
    $stmt = $pdo->prepare("
        SELECT *
        FROM playlists
        WHERE playlist_id = ? AND (user_id = ? OR visibility = 'collaborative')
    ");
    
    $stmt->execute([$playlistId, $userId]);
    if (!$stmt->fetch()) {
        throw new Exception('Brak uprawnień do modyfikacji playlisty.');
    }

    // Usuń utwory
    $stmt = $pdo->prepare("
        DELETE FROM playlist_tracks
        WHERE playlist_id = ? AND track_id = ?
    ");

    $removedTracks = [];
    foreach ($tracks as $trackId) {
        $stmt->execute([$playlistId, $trackId]);
        if ($stmt->rowCount() > 0) {
            $removedTracks[] = $trackId;
        }
    }

    // Przenumeruj pozycje
    $stmt = $pdo->prepare("
        SET @position := -1;
        UPDATE playlist_tracks
        SET position = @position := @position + 1
        WHERE playlist_id = ?
        ORDER BY position;
    ");
    
    $stmt->execute([$playlistId]);

    // Aktualizuj timestamp i licznik utworów
    $stmt = $pdo->prepare("
        UPDATE playlists
        SET 
            updated_at = CURRENT_TIMESTAMP,
            track_count = (
                SELECT COUNT(*)
                FROM playlist_tracks
                WHERE playlist_id = ?
            )
        WHERE playlist_id = ?
    ");
    
    $stmt->execute([$playlistId, $playlistId]);

    return [
        'playlist_id' => $playlistId,
        'removed_tracks' => $removedTracks
    ];
}

function reorderPlaylistTracks($pdo, $userId, $playlistId, $trackOrder) {
    // Sprawdź uprawnienia
    $stmt = $pdo->prepare("
        SELECT *
        FROM playlists
        WHERE playlist_id = ? AND (user_id = ? OR visibility = 'collaborative')
    ");
    
    $stmt->execute([$playlistId, $userId]);
    if (!$stmt->fetch()) {
        throw new Exception('Brak uprawnień do modyfikacji playlisty.');
    }

    // Aktualizuj pozycje
    $stmt = $pdo->prepare("
        UPDATE playlist_tracks
        SET position = ?
        WHERE playlist_id = ? AND track_id = ?
    ");

    foreach ($trackOrder as $position => $trackId) {
        $stmt->execute([$position, $playlistId, $trackId]);
    }

    // Aktualizuj timestamp
    $stmt = $pdo->prepare("
        UPDATE playlists
        SET updated_at = CURRENT_TIMESTAMP
        WHERE playlist_id = ?
    ");
    
    $stmt->execute([$playlistId]);

    return [
        'playlist_id' => $playlistId,
        'new_order' => $trackOrder
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? '';

        switch ($action) {
            case 'create_playlist':
                $result = createPlaylist($pdo, $_SESSION['user_id'], $data);
                echo json_encode([
                    'success' => true,
                    'data' => $result
                ]);
                break;

            case 'update_playlist':
                if (!isset($data['playlist_id'])) {
                    throw new Exception('Brak ID playlisty.');
                }
                $result = updatePlaylist($pdo, $_SESSION['user_id'], $data['playlist_id'], $data);
                echo json_encode([
                    'success' => true,
                    'data' => $result
                ]);
                break;

            case 'get_playlist':
                if (!isset($data['playlist_id'])) {
                    throw new Exception('Brak ID playlisty.');
                }
                $result = getPlaylistDetails($pdo, $data['playlist_id']);
                echo json_encode([
                    'success' => true,
                    'data' => $result
                ]);
                break;

            case 'add_tracks':
                if (!isset($data['playlist_id']) || !isset($data['tracks'])) {
                    throw new Exception('Brak wymaganych parametrów.');
                }
                $result = addTracksToPlaylist($pdo, $_SESSION['user_id'], $data['playlist_id'], $data['tracks']);
                echo json_encode([
                    'success' => true,
                    'data' => $result
                ]);
                break;

            case 'remove_tracks':
                if (!isset($data['playlist_id']) || !isset($data['tracks'])) {
                    throw new Exception('Brak wymaganych parametrów.');
                }
                $result = removeTracksFromPlaylist($pdo, $_SESSION['user_id'], $data['playlist_id'], $data['tracks']);
                echo json_encode([
                    'success' => true,
                    'data' => $result
                ]);
                break;

            case 'reorder_tracks':
                if (!isset($data['playlist_id']) || !isset($data['track_order'])) {
                    throw new Exception('Brak wymaganych parametrów.');
                }
                $result = reorderPlaylistTracks($pdo, $_SESSION['user_id'], $data['playlist_id'], $data['track_order']);
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