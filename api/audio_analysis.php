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

function analyzeAudioFeatures($pdo, $trackId) {
    // Sprawdź czy utwór istnieje
    $stmt = $pdo->prepare("
        SELECT 
            t.track_id,
            t.title,
            t.file_path,
            t.duration,
            t.bpm,
            t.key_signature
        FROM tracks t
        WHERE t.track_id = ?
    ");
    $stmt->execute([$trackId]);
    $track = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$track) {
        throw new Exception('Utwór nie istnieje.');
    }

    // Symulacja analizy cech audio
    $analysis = [
        'track_info' => $track,
        'analysis_id' => uniqid('analysis_'),
        'tempo_analysis' => [
            'bpm' => $track['bpm'],
            'bpm_confidence' => rand(90, 100) / 100,
            'beat_positions' => generateBeatPositions($track['duration'], $track['bpm']),
            'tempo_stability' => rand(85, 100) / 100,
            'time_signature' => '4/4',
            'rhythm_complexity' => rand(1, 10) / 10
        ],
        'key_analysis' => [
            'key' => $track['key_signature'],
            'key_confidence' => rand(85, 100) / 100,
            'mode' => rand(0, 1) ? 'major' : 'minor',
            'key_strength' => rand(70, 100) / 100,
            'chord_progression' => generateChordProgression()
        ],
        'spectral_features' => [
            'spectral_centroid' => rand(1000, 5000),
            'spectral_rolloff' => rand(5000, 15000),
            'spectral_flux' => rand(50, 200) / 100,
            'spectral_flatness' => rand(1, 100) / 100,
            'spectral_bandwidth' => rand(2000, 8000)
        ],
        'dynamics' => [
            'rms_energy' => rand(60, 90) / 100,
            'peak_amplitude' => rand(85, 100) / 100,
            'crest_factor' => rand(10, 20),
            'dynamic_range' => rand(8, 14),
            'loudness_lufs' => -rand(10, 16)
        ]
    ];

    // Zapisz analizę
    $stmt = $pdo->prepare("
        INSERT INTO audio_analyses (
            track_id,
            user_id,
            analysis_id,
            created_at,
            tempo_data,
            key_data,
            spectral_data,
            dynamics_data
        ) VALUES (?, ?, ?, CURRENT_TIMESTAMP, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $trackId,
        $_SESSION['user_id'],
        $analysis['analysis_id'],
        json_encode($analysis['tempo_analysis']),
        json_encode($analysis['key_analysis']),
        json_encode($analysis['spectral_features']),
        json_encode($analysis['dynamics'])
    ]);

    return $analysis;
}

function detectSections($pdo, $trackId) {
    // Sprawdź czy utwór istnieje
    $stmt = $pdo->prepare("
        SELECT 
            t.track_id,
            t.title,
            t.duration,
            t.bpm
        FROM tracks t
        WHERE t.track_id = ?
    ");
    $stmt->execute([$trackId]);
    $track = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$track) {
        throw new Exception('Utwór nie istnieje.');
    }

    // Symulacja detekcji sekcji
    $sections = [
        'track_info' => $track,
        'sections' => [
            [
                'type' => 'intro',
                'start_time' => 0,
                'duration' => 15.5,
                'confidence' => 0.92,
                'energy' => 0.65
            ],
            [
                'type' => 'verse',
                'start_time' => 15.5,
                'duration' => 32.0,
                'confidence' => 0.88,
                'energy' => 0.75
            ],
            [
                'type' => 'chorus',
                'start_time' => 47.5,
                'duration' => 28.0,
                'confidence' => 0.95,
                'energy' => 0.90
            ]
        ],
        'section_transitions' => [
            [
                'from' => 'intro',
                'to' => 'verse',
                'time' => 15.5,
                'smoothness' => 0.85
            ],
            [
                'from' => 'verse',
                'to' => 'chorus',
                'time' => 47.5,
                'smoothness' => 0.92
            ]
        ],
        'structure_analysis' => [
            'form' => 'ABABCB',
            'symmetry_score' => 0.85,
            'complexity_score' => 0.72,
            'repetition_patterns' => [
                'chorus' => 3,
                'verse' => 2,
                'bridge' => 1
            ]
        ]
    ];

    // Zapisz analizę sekcji
    $stmt = $pdo->prepare("
        INSERT INTO section_analyses (
            track_id,
            user_id,
            created_at,
            sections_data,
            transitions_data,
            structure_data
        ) VALUES (?, ?, CURRENT_TIMESTAMP, ?, ?, ?)
    ");

    $stmt->execute([
        $trackId,
        $_SESSION['user_id'],
        json_encode($sections['sections']),
        json_encode($sections['section_transitions']),
        json_encode($sections['structure_analysis'])
    ]);

    return $sections;
}

function analyzeHarmony($pdo, $trackId) {
    // Sprawdź czy utwór istnieje
    $stmt = $pdo->prepare("
        SELECT 
            t.track_id,
            t.title,
            t.key_signature,
            t.duration
        FROM tracks t
        WHERE t.track_id = ?
    ");
    $stmt->execute([$trackId]);
    $track = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$track) {
        throw new Exception('Utwór nie istnieje.');
    }

    // Symulacja analizy harmonicznej
    $harmony = [
        'track_info' => $track,
        'key_analysis' => [
            'main_key' => $track['key_signature'],
            'secondary_keys' => [
                ['key' => 'Am', 'start_time' => 45.2, 'duration' => 15.8],
                ['key' => 'F', 'start_time' => 89.5, 'duration' => 22.3]
            ],
            'modulations' => [
                [
                    'from' => $track['key_signature'],
                    'to' => 'Am',
                    'time' => 45.2,
                    'smoothness' => 0.88
                ]
            ]
        ],
        'chord_analysis' => [
            'progression_patterns' => [
                ['I-IV-V-I', 'confidence' => 0.92],
                ['ii-V-I', 'confidence' => 0.85]
            ],
            'chord_complexity' => 0.65,
            'extended_chords_ratio' => 0.25,
            'chord_rhythm_correlation' => 0.78
        ],
        'voice_leading' => [
            'smoothness' => 0.82,
            'voice_crossing_instances' => 3,
            'parallel_motion_ratio' => 0.15,
            'voice_ranges' => [
                'soprano' => ['min' => 'C4', 'max' => 'A5'],
                'alto' => ['min' => 'G3', 'max' => 'D5'],
                'tenor' => ['min' => 'C3', 'max' => 'G4'],
                'bass' => ['min' => 'F2', 'max' => 'C4']
            ]
        ],
        'tension_analysis' => [
            'overall_tension_curve' => generateTensionCurve($track['duration']),
            'tension_peaks' => [
                ['time' => 65.3, 'value' => 0.85],
                ['time' => 128.7, 'value' => 0.92]
            ],
            'resolution_points' => [
                ['time' => 72.1, 'strength' => 0.78],
                ['time' => 135.2, 'strength' => 0.88]
            ]
        ]
    ];

    // Zapisz analizę harmonii
    $stmt = $pdo->prepare("
        INSERT INTO harmony_analyses (
            track_id,
            user_id,
            created_at,
            key_data,
            chord_data,
            voice_leading_data,
            tension_data
        ) VALUES (?, ?, CURRENT_TIMESTAMP, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $trackId,
        $_SESSION['user_id'],
        json_encode($harmony['key_analysis']),
        json_encode($harmony['chord_analysis']),
        json_encode($harmony['voice_leading']),
        json_encode($harmony['tension_analysis'])
    ]);

    return $harmony;
}

function generateBeatPositions($duration, $bpm) {
    $beatInterval = 60 / $bpm;
    $positions = [];
    $currentTime = 0;
    
    while ($currentTime < $duration) {
        $positions[] = round($currentTime, 3);
        $currentTime += $beatInterval;
    }
    
    return $positions;
}

function generateChordProgression() {
    $commonProgressions = [
        ['I', 'IV', 'V', 'I'],
        ['I', 'vi', 'IV', 'V'],
        ['ii', 'V', 'I'],
        ['I', 'V', 'vi', 'IV']
    ];
    
    return $commonProgressions[array_rand($commonProgressions)];
}

function generateTensionCurve($duration) {
    $points = [];
    $numPoints = min(100, $duration);
    $timeStep = $duration / $numPoints;
    
    for ($i = 0; $i < $numPoints; $i++) {
        $points[] = [
            'time' => round($i * $timeStep, 2),
            'tension' => rand(30, 90) / 100
        ];
    }
    
    return $points;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? '';

        switch ($action) {
            case 'analyze_features':
                if (!isset($data['track_id'])) {
                    throw new Exception('Brak ID utworu.');
                }
                $analysis = analyzeAudioFeatures($pdo, $data['track_id']);
                echo json_encode([
                    'success' => true,
                    'data' => $analysis
                ]);
                break;

            case 'detect_sections':
                if (!isset($data['track_id'])) {
                    throw new Exception('Brak ID utworu.');
                }
                $sections = detectSections($pdo, $data['track_id']);
                echo json_encode([
                    'success' => true,
                    'data' => $sections
                ]);
                break;

            case 'analyze_harmony':
                if (!isset($data['track_id'])) {
                    throw new Exception('Brak ID utworu.');
                }
                $harmony = analyzeHarmony($pdo, $data['track_id']);
                echo json_encode([
                    'success' => true,
                    'data' => $harmony
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