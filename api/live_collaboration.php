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

function createCollaborationSession($pdo, $trackId, $params = []) {
    // Sprawdź czy utwór istnieje
    $stmt = $pdo->prepare("
        SELECT 
            t.track_id,
            t.title,
            t.duration,
            t.stems_available,
            t.file_path
        FROM tracks t
        WHERE t.track_id = ?
    ");
    $stmt->execute([$trackId]);
    $track = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$track) {
        throw new Exception('Utwór nie istnieje.');
    }

    // Parametry domyślne dla sesji
    $defaults = [
        'max_participants' => 10,
        'roles' => ['producer', 'musician', 'vocalist', 'listener'],
        'features' => [
            'real_time_mixing' => true,
            'stem_editing' => true,
            'chat' => true,
            'video' => true,
            'annotations' => true
        ],
        'quality' => 'high', // low, medium, high, studio
        'latency_mode' => 'balanced' // minimal, balanced, quality
    ];

    $params = array_merge($defaults, $params);

    // Tworzenie sesji współpracy
    $session = [
        'session_id' => uniqid('collab_'),
        'track_info' => $track,
        'created_by' => $_SESSION['user_id'],
        'parameters' => $params,
        'status' => 'active',
        'participants' => [
            [
                'user_id' => $_SESSION['user_id'],
                'role' => 'producer',
                'permissions' => ['edit', 'mix', 'invite', 'kick', 'end_session']
            ]
        ],
        'features' => $params['features'],
        'technical_settings' => [
            'sample_rate' => 48000,
            'bit_depth' => 24,
            'max_latency' => 50, // ms
            'buffer_size' => 512
        ]
    ];

    // Zapisz sesję w bazie
    $stmt = $pdo->prepare("
        INSERT INTO collaboration_sessions (
            session_id,
            track_id,
            created_by,
            parameters,
            created_at,
            status,
            metadata
        ) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP, ?, ?)
    ");

    $stmt->execute([
        $session['session_id'],
        $trackId,
        $_SESSION['user_id'],
        json_encode($params),
        'active',
        json_encode($session)
    ]);

    return $session;
}

function joinCollaborationSession($pdo, $sessionId, $role = 'listener') {
    // Sprawdź czy sesja istnieje i jest aktywna
    $stmt = $pdo->prepare("
        SELECT 
            cs.*,
            t.title as track_title,
            t.duration,
            t.stems_available
        FROM collaboration_sessions cs
        JOIN tracks t ON cs.track_id = t.track_id
        WHERE cs.session_id = ? AND cs.status = 'active'
    ");
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        throw new Exception('Sesja nie istnieje lub jest nieaktywna.');
    }

    $metadata = json_decode($session['metadata'], true);
    
    // Sprawdź czy jest miejsce w sesji
    if (count($metadata['participants']) >= $metadata['parameters']['max_participants']) {
        throw new Exception('Sesja jest pełna.');
    }

    // Dodaj uczestnika
    $participant = [
        'user_id' => $_SESSION['user_id'],
        'role' => $role,
        'joined_at' => time(),
        'permissions' => getRolePermissions($role)
    ];

    $metadata['participants'][] = $participant;

    // Aktualizuj metadane sesji
    $stmt = $pdo->prepare("
        UPDATE collaboration_sessions 
        SET metadata = ?
        WHERE session_id = ?
    ");
    $stmt->execute([json_encode($metadata), $sessionId]);

    // Zapisz dołączenie do historii
    $stmt = $pdo->prepare("
        INSERT INTO session_participants (
            session_id,
            user_id,
            role,
            joined_at,
            status
        ) VALUES (?, ?, ?, CURRENT_TIMESTAMP, ?)
    ");
    $stmt->execute([$sessionId, $_SESSION['user_id'], $role, 'active']);

    return [
        'session_info' => $metadata,
        'participant_info' => $participant
    ];
}

function sendCollaborationEvent($pdo, $sessionId, $eventData) {
    // Sprawdź uprawnienia użytkownika w sesji
    $stmt = $pdo->prepare("
        SELECT metadata
        FROM collaboration_sessions
        WHERE session_id = ? AND status = 'active'
    ");
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch();

    if (!$session) {
        throw new Exception('Sesja nie istnieje lub jest nieaktywna.');
    }

    $metadata = json_decode($session['metadata'], true);
    $participant = null;

    foreach ($metadata['participants'] as $p) {
        if ($p['user_id'] === $_SESSION['user_id']) {
            $participant = $p;
            break;
        }
    }

    if (!$participant) {
        throw new Exception('Nie jesteś uczestnikiem tej sesji.');
    }

    // Walidacja i przetwarzanie wydarzenia
    $event = [
        'event_id' => uniqid('event_'),
        'session_id' => $sessionId,
        'user_id' => $_SESSION['user_id'],
        'type' => $eventData['type'],
        'data' => $eventData['data'],
        'timestamp' => microtime(true),
        'metadata' => [
            'user_role' => $participant['role'],
            'client_timestamp' => $eventData['client_timestamp'] ?? null,
            'target_users' => $eventData['target_users'] ?? null
        ]
    ];

    // Zapisz wydarzenie
    $stmt = $pdo->prepare("
        INSERT INTO collaboration_events (
            event_id,
            session_id,
            user_id,
            event_type,
            event_data,
            created_at,
            metadata
        ) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, ?)
    ");

    $stmt->execute([
        $event['event_id'],
        $sessionId,
        $_SESSION['user_id'],
        $event['type'],
        json_encode($event['data']),
        json_encode($event['metadata'])
    ]);

    return $event;
}

function getRolePermissions($role) {
    // Definicje uprawnień dla różnych ról
    $permissions = [
        'producer' => [
            'edit', 'mix', 'invite', 'kick', 'end_session',
            'add_stems', 'delete_stems', 'change_settings'
        ],
        'musician' => [
            'edit', 'mix', 'add_stems', 'add_annotations'
        ],
        'vocalist' => [
            'edit', 'mix', 'add_stems', 'add_annotations'
        ],
        'listener' => [
            'view', 'chat', 'add_annotations'
        ]
    ];

    return $permissions[$role] ?? ['view', 'chat'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? '';

        switch ($action) {
            case 'create_session':
                if (!isset($data['track_id'])) {
                    throw new Exception('Brak ID utworu.');
                }
                $params = $data['parameters'] ?? [];
                $session = createCollaborationSession($pdo, $data['track_id'], $params);
                echo json_encode([
                    'success' => true,
                    'data' => $session
                ]);
                break;

            case 'join_session':
                if (!isset($data['session_id'])) {
                    throw new Exception('Brak ID sesji.');
                }
                $role = $data['role'] ?? 'listener';
                $result = joinCollaborationSession($pdo, $data['session_id'], $role);
                echo json_encode([
                    'success' => true,
                    'data' => $result
                ]);
                break;

            case 'send_event':
                if (!isset($data['session_id']) || !isset($data['event_data'])) {
                    throw new Exception('Brak wymaganych parametrów.');
                }
                $event = sendCollaborationEvent($pdo, $data['session_id'], $data['event_data']);
                echo json_encode([
                    'success' => true,
                    'data' => $event
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