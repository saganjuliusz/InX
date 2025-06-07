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

function generateMusicFromPrompt($pdo, $prompt, $params = []) {
    // W rzeczywistej implementacji należałoby użyć modelu AI do generowania muzyki
    // Tutaj symulujemy proces generowania
    
    // Parametry domyślne
    $defaults = [
        'duration' => 180, // w sekundach
        'tempo' => 120,
        'key' => 'C',
        'scale' => 'major',
        'style' => 'modern',
        'instruments' => ['piano', 'strings', 'drums'],
        'mood' => 'energetic',
        'complexity' => 0.7
    ];

    $params = array_merge($defaults, $params);

    // Symulacja generowania utworu
    $generatedTrack = [
        'prompt' => $prompt,
        'parameters' => $params,
        'generation_id' => uniqid('gen_'),
        'status' => 'completed',
        'duration' => $params['duration'],
        'tempo' => $params['tempo'],
        'key' => $params['key'],
        'sections' => [
            [
                'type' => 'intro',
                'duration' => 20,
                'elements' => ['melody', 'harmony', 'rhythm']
            ],
            [
                'type' => 'verse',
                'duration' => 40,
                'elements' => ['melody', 'harmony', 'rhythm', 'bass']
            ],
            [
                'type' => 'chorus',
                'duration' => 30,
                'elements' => ['melody', 'harmony', 'rhythm', 'bass', 'effects']
            ]
        ]
    ];

    // Zapisz informacje o wygenerowanym utworze
    $stmt = $pdo->prepare("
        INSERT INTO ai_generated_tracks (
            user_id,
            prompt,
            parameters,
            generation_id,
            created_at,
            status,
            metadata
        ) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP, ?, ?)
    ");

    $stmt->execute([
        $_SESSION['user_id'],
        $prompt,
        json_encode($params),
        $generatedTrack['generation_id'],
        'completed',
        json_encode($generatedTrack)
    ]);

    return $generatedTrack;
}

function remixTrack($pdo, $trackId, $params = []) {
    // Sprawdź czy utwór istnieje
    $stmt = $pdo->prepare("
        SELECT 
            t.track_id,
            t.title,
            t.duration,
            t.bpm,
            t.key_signature,
            t.file_path,
            t.stems_available
        FROM tracks t
        WHERE t.track_id = ?
    ");
    $stmt->execute([$trackId]);
    $track = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$track) {
        throw new Exception('Utwór nie istnieje.');
    }

    // Parametry domyślne dla remixu
    $defaults = [
        'tempo_change' => 0, // zmiana BPM
        'key_shift' => 0, // zmiana tonacji
        'elements' => ['drums', 'bass', 'melody', 'harmony'],
        'effects' => [],
        'style_transfer' => null,
        'intensity' => 0.7
    ];

    $params = array_merge($defaults, $params);

    // Symulacja tworzenia remixu
    $remixData = [
        'original_track' => $track,
        'remix_id' => uniqid('remix_'),
        'parameters' => $params,
        'status' => 'completed',
        'duration' => $track['duration'],
        'new_bpm' => $track['bpm'] + $params['tempo_change'],
        'modifications' => [
            'tempo' => $params['tempo_change'] != 0,
            'key' => $params['key_shift'] != 0,
            'effects' => !empty($params['effects']),
            'style' => $params['style_transfer'] !== null
        ]
    ];

    // Zapisz informacje o remixie
    $stmt = $pdo->prepare("
        INSERT INTO track_remixes (
            original_track_id,
            user_id,
            remix_id,
            parameters,
            created_at,
            status,
            metadata
        ) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP, ?, ?)
    ");

    $stmt->execute([
        $trackId,
        $_SESSION['user_id'],
        $remixData['remix_id'],
        json_encode($params),
        'completed',
        json_encode($remixData)
    ]);

    return $remixData;
}

function generateContinuation($pdo, $trackId, $duration = 60) {
    // Sprawdź czy utwór istnieje
    $stmt = $pdo->prepare("
        SELECT 
            t.track_id,
            t.title,
            t.duration,
            t.bpm,
            t.key_signature,
            t.energy_level,
            t.valence,
            t.file_path
        FROM tracks t
        WHERE t.track_id = ?
    ");
    $stmt->execute([$trackId]);
    $track = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$track) {
        throw new Exception('Utwór nie istnieje.');
    }

    // Symulacja generowania kontynuacji utworu
    $continuation = [
        'original_track' => $track,
        'continuation_id' => uniqid('cont_'),
        'duration' => $duration,
        'bpm' => $track['bpm'],
        'key_signature' => $track['key_signature'],
        'energy_level' => $track['energy_level'],
        'valence' => $track['valence'],
        'sections' => [
            [
                'type' => 'transition',
                'duration' => 5,
                'confidence' => 0.95
            ],
            [
                'type' => 'continuation',
                'duration' => $duration - 5,
                'confidence' => 0.88
            ]
        ]
    ];

    // Zapisz informacje o kontynuacji
    $stmt = $pdo->prepare("
        INSERT INTO track_continuations (
            original_track_id,
            user_id,
            continuation_id,
            duration,
            created_at,
            status,
            metadata
        ) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP, ?, ?)
    ");

    $stmt->execute([
        $trackId,
        $_SESSION['user_id'],
        $continuation['continuation_id'],
        $duration,
        'completed',
        json_encode($continuation)
    ]);

    return $continuation;
}

function generateStemVariations($pdo, $trackId, $stemType, $params = []) {
    // Sprawdź czy utwór istnieje i ma dostępne stemy
    $stmt = $pdo->prepare("
        SELECT 
            t.track_id,
            t.title,
            t.stems_available,
            t.stems_path
        FROM tracks t
        WHERE t.track_id = ?
    ");
    $stmt->execute([$trackId]);
    $track = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$track) {
        throw new Exception('Utwór nie istnieje.');
    }

    if (!$track['stems_available']) {
        throw new Exception('Stemy nie są dostępne dla tego utworu.');
    }

    // Parametry domyślne dla wariacji
    $defaults = [
        'variation_count' => 3,
        'complexity' => 0.7,
        'style' => 'original',
        'preserve_rhythm' => true,
        'preserve_harmony' => true
    ];

    $params = array_merge($defaults, $params);

    // Symulacja generowania wariacji stemów
    $variations = [];
    for ($i = 0; $i < $params['variation_count']; $i++) {
        $variations[] = [
            'variation_id' => uniqid('var_'),
            'stem_type' => $stemType,
            'confidence' => 0.85 + (rand(-10, 10) / 100),
            'complexity_score' => $params['complexity'] + (rand(-20, 20) / 100),
            'similarity_to_original' => 0.7 + (rand(-20, 20) / 100)
        ];
    }

    // Zapisz informacje o wariacjach
    $stmt = $pdo->prepare("
        INSERT INTO stem_variations (
            track_id,
            user_id,
            stem_type,
            parameters,
            created_at,
            status,
            metadata
        ) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP, ?, ?)
    ");

    $variationData = [
        'track_info' => $track,
        'parameters' => $params,
        'variations' => $variations
    ];

    $stmt->execute([
        $trackId,
        $_SESSION['user_id'],
        $stemType,
        json_encode($params),
        'completed',
        json_encode($variationData)
    ]);

    return $variationData;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? '';

        switch ($action) {
            case 'generate_music':
                if (!isset($data['prompt'])) {
                    throw new Exception('Brak wymaganego promptu.');
                }
                $params = $data['parameters'] ?? [];
                $result = generateMusicFromPrompt($pdo, $data['prompt'], $params);
                echo json_encode([
                    'success' => true,
                    'data' => $result
                ]);
                break;

            case 'remix_track':
                if (!isset($data['track_id'])) {
                    throw new Exception('Brak ID utworu.');
                }
                $params = $data['parameters'] ?? [];
                $remix = remixTrack($pdo, $data['track_id'], $params);
                echo json_encode([
                    'success' => true,
                    'data' => $remix
                ]);
                break;

            case 'generate_continuation':
                if (!isset($data['track_id'])) {
                    throw new Exception('Brak ID utworu.');
                }
                $duration = $data['duration'] ?? 60;
                $continuation = generateContinuation($pdo, $data['track_id'], $duration);
                echo json_encode([
                    'success' => true,
                    'data' => $continuation
                ]);
                break;

            case 'generate_stem_variations':
                if (!isset($data['track_id']) || !isset($data['stem_type'])) {
                    throw new Exception('Brak wymaganych parametrów.');
                }
                $params = $data['parameters'] ?? [];
                $variations = generateStemVariations($pdo, $data['track_id'], $data['stem_type'], $params);
                echo json_encode([
                    'success' => true,
                    'data' => $variations
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