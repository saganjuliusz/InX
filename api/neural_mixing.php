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

function analyzeTrackWithNeuralNetwork($pdo, $trackId) {
    // Sprawdź czy utwór istnieje
    $stmt = $pdo->prepare("
        SELECT 
            t.track_id,
            t.title,
            t.file_path,
            t.duration,
            t.stems_available,
            t.bpm,
            t.key_signature,
            t.energy_level
        FROM tracks t
        WHERE t.track_id = ?
    ");
    $stmt->execute([$trackId]);
    $track = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$track) {
        throw new Exception('Utwór nie istnieje.');
    }

    // Symulacja analizy przez sieć neuronową
    $analysis = [
        'track_info' => $track,
        'analysis_id' => uniqid('neural_'),
        'spectral_analysis' => [
            'frequency_balance' => [
                'sub_bass' => rand(80, 100) / 100,
                'bass' => rand(85, 100) / 100,
                'low_mids' => rand(75, 95) / 100,
                'high_mids' => rand(70, 90) / 100,
                'presence' => rand(65, 85) / 100,
                'brilliance' => rand(60, 80) / 100
            ],
            'dynamic_range' => rand(8, 14), // dB
            'stereo_field' => [
                'width' => rand(80, 100) / 100,
                'correlation' => rand(85, 95) / 100,
                'phase_issues' => []
            ]
        ],
        'suggested_improvements' => [
            'eq_adjustments' => [
                ['frequency' => 100, 'gain' => -2, 'q' => 1.0],
                ['frequency' => 250, 'gain' => -1, 'q' => 0.7],
                ['frequency' => 2500, 'gain' => 1.5, 'q' => 0.5]
            ],
            'dynamics_processing' => [
                'compression' => [
                    'threshold' => -18,
                    'ratio' => 2.5,
                    'attack' => 10,
                    'release' => 100
                ],
                'limiting' => [
                    'threshold' => -1,
                    'release' => 50
                ]
            ],
            'stereo_enhancement' => [
                'width' => 1.1,
                'focus_frequencies' => [100, 1000, 5000]
            ]
        ],
        'reference_matching' => [
            'loudness_match' => -14, // LUFS
            'spectral_balance_match' => 0.85,
            'dynamic_range_match' => 0.9
        ]
    ];

    // Zapisz analizę
    $stmt = $pdo->prepare("
        INSERT INTO neural_analyses (
            track_id,
            user_id,
            analysis_id,
            created_at,
            analysis_data,
            suggestions
        ) VALUES (?, ?, ?, CURRENT_TIMESTAMP, ?, ?)
    ");

    $stmt->execute([
        $trackId,
        $_SESSION['user_id'],
        $analysis['analysis_id'],
        json_encode($analysis['spectral_analysis']),
        json_encode($analysis['suggested_improvements'])
    ]);

    return $analysis;
}

function applyNeuralMixing($pdo, $trackId, $params = []) {
    // Sprawdź czy utwór istnieje
    $stmt = $pdo->prepare("
        SELECT 
            t.track_id,
            t.title,
            t.stems_available,
            na.analysis_data,
            na.suggestions
        FROM tracks t
        LEFT JOIN neural_analyses na ON t.track_id = na.track_id
        WHERE t.track_id = ?
        ORDER BY na.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$trackId]);
    $track = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$track) {
        throw new Exception('Utwór nie istnieje.');
    }

    // Parametry domyślne dla miksowania
    $defaults = [
        'target_loudness' => -14, // LUFS
        'dynamic_range' => 8, // dB
        'style' => 'modern',
        'reference_track_id' => null,
        'preserve_transients' => true,
        'enhance_stereo' => true,
        'apply_limiting' => true
    ];

    $params = array_merge($defaults, $params);

    // Symulacja procesu miksowania
    $mixingResult = [
        'mix_id' => uniqid('mix_'),
        'track_info' => $track,
        'parameters' => $params,
        'processing_chain' => [
            [
                'type' => 'spectral_balance',
                'adjustments' => json_decode($track['suggestions'], true)['eq_adjustments']
            ],
            [
                'type' => 'dynamics',
                'compression' => json_decode($track['suggestions'], true)['dynamics_processing']['compression'],
                'limiting' => json_decode($track['suggestions'], true)['dynamics_processing']['limiting']
            ],
            [
                'type' => 'stereo_enhancement',
                'settings' => json_decode($track['suggestions'], true)['stereo_enhancement']
            ]
        ],
        'output_stats' => [
            'integrated_loudness' => $params['target_loudness'],
            'true_peak' => -1.0,
            'dynamic_range' => $params['dynamic_range'],
            'stereo_width' => rand(85, 100) / 100
        ]
    ];

    // Zapisz wynik miksowania
    $stmt = $pdo->prepare("
        INSERT INTO neural_mixes (
            track_id,
            user_id,
            mix_id,
            parameters,
            created_at,
            status,
            metadata
        ) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP, ?, ?)
    ");

    $stmt->execute([
        $trackId,
        $_SESSION['user_id'],
        $mixingResult['mix_id'],
        json_encode($params),
        'completed',
        json_encode($mixingResult)
    ]);

    return $mixingResult;
}

function generateStyleTransfer($pdo, $trackId, $referenceTrackId, $params = []) {
    // Sprawdź czy utwory istnieją
    $stmt = $pdo->prepare("
        SELECT track_id, title, file_path
        FROM tracks 
        WHERE track_id IN (?, ?)
    ");
    $stmt->execute([$trackId, $referenceTrackId]);
    $tracks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($tracks) !== 2) {
        throw new Exception('Jeden z utworów nie istnieje.');
    }

    // Parametry domyślne dla transferu stylu
    $defaults = [
        'intensity' => 0.7,
        'preserve_rhythm' => true,
        'preserve_melody' => true,
        'transfer_elements' => [
            'timbre' => true,
            'dynamics' => true,
            'effects' => true,
            'spatial' => true
        ]
    ];

    $params = array_merge($defaults, $params);

    // Symulacja transferu stylu
    $transferResult = [
        'transfer_id' => uniqid('transfer_'),
        'source_track' => $tracks[0],
        'reference_track' => $tracks[1],
        'parameters' => $params,
        'transfer_analysis' => [
            'timbre_match' => rand(75, 95) / 100,
            'dynamics_match' => rand(80, 95) / 100,
            'spatial_match' => rand(70, 90) / 100
        ],
        'processing_steps' => [
            [
                'type' => 'timbre_analysis',
                'confidence' => rand(85, 95) / 100
            ],
            [
                'type' => 'style_extraction',
                'elements_extracted' => array_keys(array_filter($params['transfer_elements']))
            ],
            [
                'type' => 'neural_resynthesis',
                'quality_score' => rand(85, 95) / 100
            ]
        ]
    ];

    // Zapisz wynik transferu
    $stmt = $pdo->prepare("
        INSERT INTO style_transfers (
            source_track_id,
            reference_track_id,
            user_id,
            transfer_id,
            parameters,
            created_at,
            status,
            metadata
        ) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, ?, ?)
    ");

    $stmt->execute([
        $trackId,
        $referenceTrackId,
        $_SESSION['user_id'],
        $transferResult['transfer_id'],
        json_encode($params),
        'completed',
        json_encode($transferResult)
    ]);

    return $transferResult;
}

function optimizeMixForPlatform($pdo, $mixId, $platform, $params = []) {
    // Sprawdź czy miks istnieje
    $stmt = $pdo->prepare("
        SELECT 
            nm.*,
            t.title,
            t.file_path
        FROM neural_mixes nm
        JOIN tracks t ON nm.track_id = t.track_id
        WHERE nm.mix_id = ?
    ");
    $stmt->execute([$mixId]);
    $mix = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$mix) {
        throw new Exception('Miks nie istnieje.');
    }

    // Parametry platformy
    $platformSpecs = [
        'spotify' => [
            'target_loudness' => -14,
            'true_peak' => -1,
            'format' => 'ogg',
            'bitrate' => 320
        ],
        'apple_music' => [
            'target_loudness' => -16,
            'true_peak' => -1,
            'format' => 'aac',
            'bitrate' => 256
        ],
        'youtube' => [
            'target_loudness' => -13,
            'true_peak' => -1,
            'format' => 'aac',
            'bitrate' => 192
        ]
    ];

    if (!isset($platformSpecs[$platform])) {
        throw new Exception('Nieobsługiwana platforma.');
    }

    // Symulacja optymalizacji
    $optimization = [
        'optimization_id' => uniqid('opt_'),
        'mix_info' => $mix,
        'platform' => $platform,
        'platform_specs' => $platformSpecs[$platform],
        'adjustments' => [
            'loudness_adjustment' => $platformSpecs[$platform]['target_loudness'] - json_decode($mix['metadata'], true)['output_stats']['integrated_loudness'],
            'format_conversion' => [
                'target_format' => $platformSpecs[$platform]['format'],
                'target_bitrate' => $platformSpecs[$platform]['bitrate']
            ],
            'streaming_optimization' => [
                'pre_emphasis' => true,
                'psychoacoustic_optimization' => true
            ]
        ],
        'quality_metrics' => [
            'loudness_compliance' => true,
            'codec_quality_score' => rand(90, 100) / 100,
            'streaming_efficiency' => rand(85, 95) / 100
        ]
    ];

    // Zapisz optymalizację
    $stmt = $pdo->prepare("
        INSERT INTO platform_optimizations (
            mix_id,
            user_id,
            platform,
            optimization_id,
            created_at,
            status,
            metadata
        ) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP, ?, ?)
    ");

    $stmt->execute([
        $mixId,
        $_SESSION['user_id'],
        $platform,
        $optimization['optimization_id'],
        'completed',
        json_encode($optimization)
    ]);

    return $optimization;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? '';

        switch ($action) {
            case 'analyze_track':
                if (!isset($data['track_id'])) {
                    throw new Exception('Brak ID utworu.');
                }
                $analysis = analyzeTrackWithNeuralNetwork($pdo, $data['track_id']);
                echo json_encode([
                    'success' => true,
                    'data' => $analysis
                ]);
                break;

            case 'apply_mixing':
                if (!isset($data['track_id'])) {
                    throw new Exception('Brak ID utworu.');
                }
                $params = $data['parameters'] ?? [];
                $result = applyNeuralMixing($pdo, $data['track_id'], $params);
                echo json_encode([
                    'success' => true,
                    'data' => $result
                ]);
                break;

            case 'style_transfer':
                if (!isset($data['track_id']) || !isset($data['reference_track_id'])) {
                    throw new Exception('Brak wymaganych ID utworów.');
                }
                $params = $data['parameters'] ?? [];
                $transfer = generateStyleTransfer($pdo, $data['track_id'], $data['reference_track_id'], $params);
                echo json_encode([
                    'success' => true,
                    'data' => $transfer
                ]);
                break;

            case 'optimize_for_platform':
                if (!isset($data['mix_id']) || !isset($data['platform'])) {
                    throw new Exception('Brak wymaganych parametrów.');
                }
                $params = $data['parameters'] ?? [];
                $optimization = optimizeMixForPlatform($pdo, $data['mix_id'], $data['platform'], $params);
                echo json_encode([
                    'success' => true,
                    'data' => $optimization
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