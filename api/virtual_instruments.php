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

function loadVirtualInstrument($pdo, $instrumentId, $params = []) {
    // Sprawdź czy instrument istnieje
    $stmt = $pdo->prepare("
        SELECT 
            vi.instrument_id,
            vi.name,
            vi.type,
            vi.engine_version,
            vi.sample_library_path,
            vi.parameters
        FROM virtual_instruments vi
        WHERE vi.instrument_id = ?
    ");
    $stmt->execute([$instrumentId]);
    $instrument = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$instrument) {
        throw new Exception('Instrument nie istnieje.');
    }

    // Parametry domyślne dla instrumentu
    $defaults = [
        'polyphony' => 128,
        'velocity_layers' => 4,
        'round_robin' => 4,
        'release_time' => 0.5,
        'attack_time' => 0.01,
        'reverb_amount' => 0.3,
        'stereo_width' => 100,
        'quality_mode' => 'high' // low, medium, high, ultra
    ];

    $params = array_merge($defaults, $params);

    // Symulacja ładowania instrumentu
    $instance = [
        'instance_id' => uniqid('inst_'),
        'instrument_info' => $instrument,
        'parameters' => $params,
        'status' => 'loaded',
        'memory_usage' => rand(50, 500), // MB
        'cpu_usage' => rand(1, 10), // %
        'loaded_samples' => rand(1000, 10000),
        'technical_specs' => [
            'sample_rate' => 48000,
            'bit_depth' => 24,
            'max_voices' => $params['polyphony'],
            'buffer_size' => 512
        ]
    ];

    // Zapisz instancję instrumentu
    $stmt = $pdo->prepare("
        INSERT INTO instrument_instances (
            instance_id,
            instrument_id,
            user_id,
            parameters,
            created_at,
            status,
            metadata
        ) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP, ?, ?)
    ");

    $stmt->execute([
        $instance['instance_id'],
        $instrumentId,
        $_SESSION['user_id'],
        json_encode($params),
        'active',
        json_encode($instance)
    ]);

    return $instance;
}

function processVirtualInstrumentEvent($pdo, $instanceId, $eventData) {
    // Sprawdź czy instancja istnieje
    $stmt = $pdo->prepare("
        SELECT 
            ii.*,
            vi.type as instrument_type,
            vi.parameters as instrument_parameters
        FROM instrument_instances ii
        JOIN virtual_instruments vi ON ii.instrument_id = vi.instrument_id
        WHERE ii.instance_id = ? AND ii.status = 'active'
    ");
    $stmt->execute([$instanceId]);
    $instance = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$instance) {
        throw new Exception('Instancja instrumentu nie istnieje lub jest nieaktywna.');
    }

    // Przetwarzanie wydarzenia
    $event = [
        'event_id' => uniqid('ev_'),
        'instance_id' => $instanceId,
        'type' => $eventData['type'],
        'data' => $eventData['data'],
        'timestamp' => microtime(true),
        'processed' => true,
        'response' => null
    ];

    // Symulacja przetwarzania różnych typów wydarzeń
    switch ($eventData['type']) {
        case 'note_on':
            $event['response'] = [
                'note' => $eventData['data']['note'],
                'velocity' => $eventData['data']['velocity'],
                'channel' => $eventData['data']['channel'] ?? 0,
                'voice_id' => rand(1, 128),
                'latency' => rand(1, 10) // ms
            ];
            break;

        case 'note_off':
            $event['response'] = [
                'note' => $eventData['data']['note'],
                'release_time' => rand(100, 500) // ms
            ];
            break;

        case 'control_change':
            $event['response'] = [
                'controller' => $eventData['data']['controller'],
                'value' => $eventData['data']['value'],
                'processed_value' => $eventData['data']['value'] / 127
            ];
            break;

        case 'parameter_change':
            $event['response'] = [
                'parameter' => $eventData['data']['parameter'],
                'value' => $eventData['data']['value'],
                'transition_time' => $eventData['data']['transition_time'] ?? 0
            ];
            break;
    }

    // Zapisz wydarzenie
    $stmt = $pdo->prepare("
        INSERT INTO instrument_events (
            event_id,
            instance_id,
            user_id,
            event_type,
            event_data,
            created_at,
            response_data
        ) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, ?)
    ");

    $stmt->execute([
        $event['event_id'],
        $instanceId,
        $_SESSION['user_id'],
        $event['type'],
        json_encode($eventData['data']),
        json_encode($event['response'])
    ]);

    return $event;
}

function applyAudioEffect($pdo, $trackId, $effectData) {
    // Sprawdź czy utwór istnieje
    $stmt = $pdo->prepare("
        SELECT track_id, title, file_path
        FROM tracks 
        WHERE track_id = ?
    ");
    $stmt->execute([$trackId]);
    $track = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$track) {
        throw new Exception('Utwór nie istnieje.');
    }

    // Walidacja danych efektu
    if (!isset($effectData['type']) || !isset($effectData['parameters'])) {
        throw new Exception('Brak wymaganych parametrów efektu.');
    }

    // Symulacja przetwarzania efektu
    $effect = [
        'effect_id' => uniqid('fx_'),
        'track_info' => $track,
        'type' => $effectData['type'],
        'parameters' => $effectData['parameters'],
        'chain_position' => $effectData['chain_position'] ?? 0,
        'wet_dry_mix' => $effectData['wet_dry_mix'] ?? 1.0,
        'bypass' => false,
        'processing_info' => [
            'cpu_usage' => rand(1, 20), // %
            'latency' => rand(0, 100), // samples
            'quality_mode' => $effectData['quality_mode'] ?? 'high'
        ]
    ];

    // Zapisz efekt
    $stmt = $pdo->prepare("
        INSERT INTO track_effects (
            effect_id,
            track_id,
            user_id,
            effect_type,
            parameters,
            chain_position,
            created_at,
            status,
            metadata
        ) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, ?, ?)
    ");

    $stmt->execute([
        $effect['effect_id'],
        $trackId,
        $_SESSION['user_id'],
        $effect['type'],
        json_encode($effect['parameters']),
        $effect['chain_position'],
        'active',
        json_encode($effect)
    ]);

    return $effect;
}

function updateEffectParameters($pdo, $effectId, $parameters) {
    // Sprawdź czy efekt istnieje
    $stmt = $pdo->prepare("
        SELECT 
            te.*,
            t.title as track_title
        FROM track_effects te
        JOIN tracks t ON te.track_id = t.track_id
        WHERE te.effect_id = ? AND te.status = 'active'
    ");
    $stmt->execute([$effectId]);
    $effect = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$effect) {
        throw new Exception('Efekt nie istnieje lub jest nieaktywny.');
    }

    $currentParams = json_decode($effect['parameters'], true);
    $updatedParams = array_merge($currentParams, $parameters);

    // Symulacja aktualizacji parametrów
    $update = [
        'effect_id' => $effectId,
        'old_parameters' => $currentParams,
        'new_parameters' => $updatedParams,
        'transition_info' => [
            'start_time' => microtime(true),
            'duration' => $parameters['transition_time'] ?? 0,
            'interpolation' => $parameters['interpolation'] ?? 'linear'
        ]
    ];

    // Aktualizuj parametry w bazie
    $stmt = $pdo->prepare("
        UPDATE track_effects 
        SET 
            parameters = ?,
            last_modified = CURRENT_TIMESTAMP,
            metadata = JSON_SET(
                metadata,
                '$.last_update',
                JSON_OBJECT(
                    'timestamp', UNIX_TIMESTAMP(),
                    'user_id', ?,
                    'changes', ?
                )
            )
        WHERE effect_id = ?
    ");

    $stmt->execute([
        json_encode($updatedParams),
        $_SESSION['user_id'],
        json_encode($update),
        $effectId
    ]);

    return $update;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? '';

        switch ($action) {
            case 'load_instrument':
                if (!isset($data['instrument_id'])) {
                    throw new Exception('Brak ID instrumentu.');
                }
                $params = $data['parameters'] ?? [];
                $instance = loadVirtualInstrument($pdo, $data['instrument_id'], $params);
                echo json_encode([
                    'success' => true,
                    'data' => $instance
                ]);
                break;

            case 'process_instrument_event':
                if (!isset($data['instance_id']) || !isset($data['event_data'])) {
                    throw new Exception('Brak wymaganych parametrów.');
                }
                $event = processVirtualInstrumentEvent($pdo, $data['instance_id'], $data['event_data']);
                echo json_encode([
                    'success' => true,
                    'data' => $event
                ]);
                break;

            case 'apply_effect':
                if (!isset($data['track_id']) || !isset($data['effect_data'])) {
                    throw new Exception('Brak wymaganych parametrów.');
                }
                $effect = applyAudioEffect($pdo, $data['track_id'], $data['effect_data']);
                echo json_encode([
                    'success' => true,
                    'data' => $effect
                ]);
                break;

            case 'update_effect':
                if (!isset($data['effect_id']) || !isset($data['parameters'])) {
                    throw new Exception('Brak wymaganych parametrów.');
                }
                $update = updateEffectParameters($pdo, $data['effect_id'], $data['parameters']);
                echo json_encode([
                    'success' => true,
                    'data' => $update
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