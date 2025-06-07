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

function convertToSpatialAudio($pdo, $trackId, $params = []) {
    // Sprawdź czy utwór istnieje
    $stmt = $pdo->prepare("
        SELECT 
            t.track_id,
            t.title,
            t.file_path,
            t.duration,
            t.stems_available,
            t.spatial_audio_available
        FROM tracks t
        WHERE t.track_id = ?
    ");
    $stmt->execute([$trackId]);
    $track = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$track) {
        throw new Exception('Utwór nie istnieje.');
    }

    // Parametry domyślne dla konwersji
    $defaults = [
        'format' => 'dolby_atmos', // dolby_atmos, sony_360ra, ambisonics
        'room_size' => 'medium', // small, medium, large
        'reverb_level' => 0.3,
        'height_channels' => true,
        'object_based' => true,
        'binaural_fallback' => true
    ];

    $params = array_merge($defaults, $params);

    // Symulacja konwersji do audio przestrzennego
    $spatialData = [
        'track_info' => $track,
        'spatial_id' => uniqid('spatial_'),
        'format' => $params['format'],
        'channels' => [
            'front_left' => true,
            'front_right' => true,
            'center' => true,
            'lfe' => true,
            'surround_left' => true,
            'surround_right' => true,
            'height_front_left' => $params['height_channels'],
            'height_front_right' => $params['height_channels']
        ],
        'objects' => [
            [
                'type' => 'vocal',
                'position' => ['x' => 0, 'y' => 0, 'z' => 0],
                'movement' => []
            ],
            [
                'type' => 'instrument',
                'position' => ['x' => -0.5, 'y' => 0, 'z' => 0.2],
                'movement' => [
                    ['time' => 0, 'x' => -0.5, 'y' => 0, 'z' => 0.2],
                    ['time' => 10, 'x' => 0.5, 'y' => 0, 'z' => 0.2]
                ]
            ]
        ]
    ];

    // Zapisz informacje o konwersji
    $stmt = $pdo->prepare("
        INSERT INTO spatial_audio_conversions (
            track_id,
            user_id,
            spatial_id,
            format,
            parameters,
            created_at,
            status,
            metadata
        ) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, ?, ?)
    ");

    $stmt->execute([
        $trackId,
        $_SESSION['user_id'],
        $spatialData['spatial_id'],
        $params['format'],
        json_encode($params),
        'completed',
        json_encode($spatialData)
    ]);

    return $spatialData;
}

function createImmersiveScene($pdo, $trackId, $sceneData) {
    // Sprawdź czy utwór istnieje i ma dostępne audio przestrzenne
    $stmt = $pdo->prepare("
        SELECT 
            t.track_id,
            t.title,
            t.spatial_audio_available,
            t.spatial_audio_format
        FROM tracks t
        WHERE t.track_id = ?
    ");
    $stmt->execute([$trackId]);
    $track = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$track) {
        throw new Exception('Utwór nie istnieje.');
    }

    if (!$track['spatial_audio_available']) {
        throw new Exception('Audio przestrzenne nie jest dostępne dla tego utworu.');
    }

    // Walidacja danych sceny
    if (!isset($sceneData['environment']) || !isset($sceneData['objects'])) {
        throw new Exception('Brak wymaganych parametrów sceny.');
    }

    // Symulacja tworzenia sceny immersyjnej
    $scene = [
        'track_info' => $track,
        'scene_id' => uniqid('scene_'),
        'environment' => $sceneData['environment'],
        'objects' => array_map(function($obj) {
            return [
                'id' => uniqid('obj_'),
                'type' => $obj['type'],
                'position' => $obj['position'],
                'rotation' => $obj['rotation'] ?? [0, 0, 0],
                'scale' => $obj['scale'] ?? [1, 1, 1],
                'movement' => $obj['movement'] ?? []
            ];
        }, $sceneData['objects']),
        'acoustics' => [
            'reverb' => $sceneData['environment']['reverb'] ?? 0.3,
            'reflection' => $sceneData['environment']['reflection'] ?? 0.5,
            'absorption' => $sceneData['environment']['absorption'] ?? 0.2
        ]
    ];

    // Zapisz informacje o scenie
    $stmt = $pdo->prepare("
        INSERT INTO immersive_scenes (
            track_id,
            user_id,
            scene_id,
            environment_data,
            created_at,
            status,
            metadata
        ) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP, ?, ?)
    ");

    $stmt->execute([
        $trackId,
        $_SESSION['user_id'],
        $scene['scene_id'],
        json_encode($sceneData['environment']),
        'active',
        json_encode($scene)
    ]);

    return $scene;
}

function getHeadTracking($pdo, $trackId, $timestamp, $headPosition) {
    // Sprawdź czy utwór istnieje i ma dostępne audio przestrzenne
    $stmt = $pdo->prepare("
        SELECT spatial_audio_available, spatial_audio_format
        FROM tracks 
        WHERE track_id = ?
    ");
    $stmt->execute([$trackId]);
    $track = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$track['spatial_audio_available']) {
        throw new Exception('Audio przestrzenne nie jest dostępne dla tego utworu.');
    }

    // Symulacja obliczeń dla śledzenia głowy
    $adjustments = [
        'position' => [
            'x' => $headPosition['x'],
            'y' => $headPosition['y'],
            'z' => $headPosition['z']
        ],
        'rotation' => [
            'pitch' => $headPosition['pitch'],
            'yaw' => $headPosition['yaw'],
            'roll' => $headPosition['roll']
        ],
        'audio_adjustments' => [
            'left_channel' => [
                'gain' => 1.0 + ($headPosition['yaw'] / 180),
                'delay' => abs($headPosition['x']) * 0.003
            ],
            'right_channel' => [
                'gain' => 1.0 - ($headPosition['yaw'] / 180),
                'delay' => abs($headPosition['x']) * 0.003
            ],
            'height_channels' => [
                'gain' => 1.0 + ($headPosition['pitch'] / 90)
            ]
        ]
    ];

    // Zapisz dane śledzenia
    $stmt = $pdo->prepare("
        INSERT INTO head_tracking_data (
            track_id,
            user_id,
            timestamp,
            head_position,
            adjustments,
            created_at
        ) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
    ");

    $stmt->execute([
        $trackId,
        $_SESSION['user_id'],
        $timestamp,
        json_encode($headPosition),
        json_encode($adjustments)
    ]);

    return $adjustments;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? '';

        switch ($action) {
            case 'convert_spatial':
                if (!isset($data['track_id'])) {
                    throw new Exception('Brak ID utworu.');
                }
                $params = $data['parameters'] ?? [];
                $result = convertToSpatialAudio($pdo, $data['track_id'], $params);
                echo json_encode([
                    'success' => true,
                    'data' => $result
                ]);
                break;

            case 'create_scene':
                if (!isset($data['track_id']) || !isset($data['scene_data'])) {
                    throw new Exception('Brak wymaganych parametrów.');
                }
                $scene = createImmersiveScene($pdo, $data['track_id'], $data['scene_data']);
                echo json_encode([
                    'success' => true,
                    'data' => $scene
                ]);
                break;

            case 'head_tracking':
                if (!isset($data['track_id']) || !isset($data['timestamp']) || !isset($data['head_position'])) {
                    throw new Exception('Brak wymaganych parametrów.');
                }
                $tracking = getHeadTracking($pdo, $data['track_id'], $data['timestamp'], $data['head_position']);
                echo json_encode([
                    'success' => true,
                    'data' => $tracking
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