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

function adaptiveQualityControl($pdo, $trackId, $params = []) {
    // Sprawdź czy utwór istnieje
    $stmt = $pdo->prepare("
        SELECT 
            t.track_id,
            t.title,
            t.file_path,
            t.sample_rate,
            t.bit_depth,
            t.format,
            t.file_size
        FROM tracks t
        WHERE t.track_id = ?
    ");
    $stmt->execute([$trackId]);
    $track = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$track) {
        throw new Exception('Utwór nie istnieje.');
    }

    // Parametry domyślne
    $defaults = [
        'target_bitrate' => 320, // kbps
        'min_bitrate' => 128,
        'max_bitrate' => 320,
        'quality_preference' => 'balanced', // quality, balanced, efficiency
        'buffer_size' => 2048,
        'network_condition' => 'auto' // auto, good, medium, poor
    ];

    $params = array_merge($defaults, $params);

    // Symulacja adaptacyjnej kontroli jakości
    $adaptation = [
        'track_info' => $track,
        'original_quality' => [
            'sample_rate' => $track['sample_rate'],
            'bit_depth' => $track['bit_depth'],
            'format' => $track['format'],
            'file_size' => $track['file_size']
        ],
        'network_analysis' => [
            'bandwidth' => rand(1, 100),
            'latency' => rand(10, 200),
            'jitter' => rand(1, 50),
            'packet_loss' => rand(0, 5) / 100
        ],
        'quality_adjustments' => [
            'target_bitrate' => calculateTargetBitrate(
                $params['network_condition'] === 'auto' ? 
                detectNetworkCondition() : 
                $params['network_condition'],
                $params['quality_preference'],
                $params['min_bitrate'],
                $params['max_bitrate']
            ),
            'buffer_size' => $params['buffer_size'],
            'resampling_quality' => 'high',
            'dithering_applied' => true
        ],
        'estimated_quality' => [
            'perceived_quality' => rand(85, 100) / 100,
            'compression_ratio' => rand(10, 20) / 10,
            'expected_file_size' => round($track['file_size'] * rand(60, 90) / 100)
        ]
    ];

    // Zapisz konfigurację adaptacyjną
    $stmt = $pdo->prepare("
        INSERT INTO quality_adaptations (
            track_id,
            user_id,
            created_at,
            network_conditions,
            quality_settings,
            adaptation_results
        ) VALUES (?, ?, CURRENT_TIMESTAMP, ?, ?, ?)
    ");

    $stmt->execute([
        $trackId,
        $_SESSION['user_id'],
        json_encode($adaptation['network_analysis']),
        json_encode($adaptation['quality_adjustments']),
        json_encode($adaptation['estimated_quality'])
    ]);

    return $adaptation;
}

function formatConversion($pdo, $trackId, $targetFormat, $params = []) {
    // Sprawdź czy utwór istnieje
    $stmt = $pdo->prepare("
        SELECT 
            t.track_id,
            t.title,
            t.file_path,
            t.format,
            t.sample_rate,
            t.bit_depth,
            t.channels
        FROM tracks t
        WHERE t.track_id = ?
    ");
    $stmt->execute([$trackId]);
    $track = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$track) {
        throw new Exception('Utwór nie istnieje.');
    }

    // Parametry domyślne dla różnych formatów
    $formatConfigs = [
        'wav' => [
            'sample_rate' => 48000,
            'bit_depth' => 24,
            'compression' => 'none'
        ],
        'flac' => [
            'compression_level' => 8,
            'sample_rate' => 48000,
            'bit_depth' => 24
        ],
        'aac' => [
            'bitrate' => 256,
            'vbr' => true,
            'quality' => 'high'
        ],
        'mp3' => [
            'bitrate' => 320,
            'vbr' => true,
            'quality' => 0
        ],
        'ogg' => [
            'quality' => 9,
            'vbr' => true
        ]
    ];

    if (!isset($formatConfigs[$targetFormat])) {
        throw new Exception('Nieobsługiwany format docelowy.');
    }

    $params = array_merge($formatConfigs[$targetFormat], $params);

    // Symulacja konwersji
    $conversion = [
        'track_info' => $track,
        'target_format' => $targetFormat,
        'conversion_params' => $params,
        'source_format' => [
            'format' => $track['format'],
            'sample_rate' => $track['sample_rate'],
            'bit_depth' => $track['bit_depth'],
            'channels' => $track['channels']
        ],
        'conversion_steps' => [
            [
                'step' => 'decode',
                'status' => 'completed',
                'duration' => rand(100, 500)
            ],
            [
                'step' => 'resample',
                'status' => 'completed',
                'duration' => rand(200, 800)
            ],
            [
                'step' => 'encode',
                'status' => 'completed',
                'duration' => rand(300, 1000)
            ]
        ],
        'quality_metrics' => [
            'snr' => rand(60, 90),
            'compression_ratio' => rand(10, 50) / 10,
            'spectral_analysis' => [
                'frequency_response' => 'flat',
                'aliasing' => 'none',
                'artifacts' => 'minimal'
            ]
        ]
    ];

    // Zapisz informacje o konwersji
    $stmt = $pdo->prepare("
        INSERT INTO format_conversions (
            track_id,
            user_id,
            source_format,
            target_format,
            conversion_params,
            created_at,
            quality_metrics
        ) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, ?)
    ");

    $stmt->execute([
        $trackId,
        $_SESSION['user_id'],
        json_encode($conversion['source_format']),
        $targetFormat,
        json_encode($params),
        json_encode($conversion['quality_metrics'])
    ]);

    return $conversion;
}

function loudnessNormalization($pdo, $trackId, $params = []) {
    // Sprawdź czy utwór istnieje
    $stmt = $pdo->prepare("
        SELECT 
            t.track_id,
            t.title,
            t.file_path,
            t.peak_amplitude,
            t.integrated_loudness,
            t.loudness_range
        FROM tracks t
        WHERE t.track_id = ?
    ");
    $stmt->execute([$trackId]);
    $track = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$track) {
        throw new Exception('Utwór nie istnieje.');
    }

    // Parametry domyślne
    $defaults = [
        'target_lufs' => -14.0,
        'true_peak' => -1.0,
        'preserve_dynamics' => true,
        'analysis_window' => 3.0, // sekundy
        'look_ahead' => 0.5 // sekundy
    ];

    $params = array_merge($defaults, $params);

    // Symulacja normalizacji głośności
    $normalization = [
        'track_info' => $track,
        'original_measurements' => [
            'integrated_loudness' => $track['integrated_loudness'],
            'peak_amplitude' => $track['peak_amplitude'],
            'loudness_range' => $track['loudness_range']
        ],
        'target_settings' => $params,
        'processing_results' => [
            'gain_adjustment' => $params['target_lufs'] - $track['integrated_loudness'],
            'peak_limiting_applied' => false,
            'dynamics_preserved' => true
        ],
        'final_measurements' => [
            'integrated_loudness' => $params['target_lufs'],
            'true_peak' => $params['true_peak'],
            'loudness_range' => $track['loudness_range'] * 0.9, // Symulacja lekkiej kompresji
            'short_term_max' => $params['target_lufs'] + 3,
            'momentary_max' => $params['target_lufs'] + 5
        ]
    ];

    // Zapisz wyniki normalizacji
    $stmt = $pdo->prepare("
        INSERT INTO loudness_normalizations (
            track_id,
            user_id,
            original_measurements,
            target_settings,
            processing_results,
            final_measurements,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
    ");

    $stmt->execute([
        $trackId,
        $_SESSION['user_id'],
        json_encode($normalization['original_measurements']),
        json_encode($params),
        json_encode($normalization['processing_results']),
        json_encode($normalization['final_measurements'])
    ]);

    return $normalization;
}

function audioEnhancement($pdo, $trackId, $params = []) {
    // Sprawdź czy utwór istnieje
    $stmt = $pdo->prepare("
        SELECT 
            t.track_id,
            t.title,
            t.file_path,
            t.format,
            t.sample_rate,
            t.bit_depth
        FROM tracks t
        WHERE t.track_id = ?
    ");
    $stmt->execute([$trackId]);
    $track = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$track) {
        throw new Exception('Utwór nie istnieje.');
    }

    // Parametry domyślne
    $defaults = [
        'clarity' => 0.5,
        'warmth' => 0.5,
        'space' => 0.5,
        'presence' => 0.5,
        'dynamics' => 0.5,
        'noise_reduction' => true,
        'stereo_enhancement' => true,
        'harmonic_enhancement' => true
    ];

    $params = array_merge($defaults, $params);

    // Symulacja procesu ulepszania
    $enhancement = [
        'track_info' => $track,
        'enhancement_params' => $params,
        'processing_chain' => [
            [
                'type' => 'spectral_enhancement',
                'settings' => [
                    'clarity' => $params['clarity'],
                    'presence' => $params['presence']
                ],
                'impact' => rand(70, 90) / 100
            ],
            [
                'type' => 'harmonic_processing',
                'settings' => [
                    'warmth' => $params['warmth'],
                    'harmonics' => $params['harmonic_enhancement']
                ],
                'impact' => rand(75, 95) / 100
            ],
            [
                'type' => 'spatial_processing',
                'settings' => [
                    'space' => $params['space'],
                    'stereo_width' => $params['stereo_enhancement']
                ],
                'impact' => rand(80, 100) / 100
            ],
            [
                'type' => 'dynamic_processing',
                'settings' => [
                    'dynamics' => $params['dynamics'],
                    'noise_floor' => $params['noise_reduction']
                ],
                'impact' => rand(85, 95) / 100
            ]
        ],
        'quality_assessment' => [
            'clarity_improvement' => rand(10, 30) / 100,
            'noise_reduction' => rand(40, 60) / 100,
            'stereo_image' => rand(20, 40) / 100,
            'overall_quality' => rand(85, 95) / 100
        ]
    ];

    // Zapisz wyniki ulepszania
    $stmt = $pdo->prepare("
        INSERT INTO audio_enhancements (
            track_id,
            user_id,
            enhancement_params,
            processing_chain,
            quality_assessment,
            created_at
        ) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
    ");

    $stmt->execute([
        $trackId,
        $_SESSION['user_id'],
        json_encode($params),
        json_encode($enhancement['processing_chain']),
        json_encode($enhancement['quality_assessment'])
    ]);

    return $enhancement;
}

function calculateTargetBitrate($networkCondition, $qualityPreference, $minBitrate, $maxBitrate) {
    $networkScores = [
        'poor' => 0.3,
        'medium' => 0.6,
        'good' => 1.0
    ];

    $qualityScores = [
        'efficiency' => 0.7,
        'balanced' => 0.85,
        'quality' => 1.0
    ];

    $networkScore = $networkScores[$networkCondition] ?? 0.6;
    $qualityScore = $qualityScores[$qualityPreference] ?? 0.85;

    $targetBitrate = round(
        $minBitrate + 
        ($maxBitrate - $minBitrate) * $networkScore * $qualityScore
    );

    return max($minBitrate, min($maxBitrate, $targetBitrate));
}

function detectNetworkCondition() {
    // Symulacja detekcji stanu sieci
    $bandwidth = rand(1, 100);
    $latency = rand(10, 200);
    $jitter = rand(1, 50);
    $packetLoss = rand(0, 500) / 100;

    if ($bandwidth >= 50 && $latency < 50 && $jitter < 20 && $packetLoss < 1) {
        return 'good';
    } elseif ($bandwidth >= 20 && $latency < 100 && $jitter < 35 && $packetLoss < 3) {
        return 'medium';
    } else {
        return 'poor';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? '';

        switch ($action) {
            case 'adaptive_quality':
                if (!isset($data['track_id'])) {
                    throw new Exception('Brak ID utworu.');
                }
                $params = $data['parameters'] ?? [];
                $result = adaptiveQualityControl($pdo, $data['track_id'], $params);
                echo json_encode([
                    'success' => true,
                    'data' => $result
                ]);
                break;

            case 'convert_format':
                if (!isset($data['track_id']) || !isset($data['target_format'])) {
                    throw new Exception('Brak wymaganych parametrów.');
                }
                $params = $data['parameters'] ?? [];
                $result = formatConversion($pdo, $data['track_id'], $data['target_format'], $params);
                echo json_encode([
                    'success' => true,
                    'data' => $result
                ]);
                break;

            case 'normalize_loudness':
                if (!isset($data['track_id'])) {
                    throw new Exception('Brak ID utworu.');
                }
                $params = $data['parameters'] ?? [];
                $result = loudnessNormalization($pdo, $data['track_id'], $params);
                echo json_encode([
                    'success' => true,
                    'data' => $result
                ]);
                break;

            case 'enhance_audio':
                if (!isset($data['track_id'])) {
                    throw new Exception('Brak ID utworu.');
                }
                $params = $data['parameters'] ?? [];
                $result = audioEnhancement($pdo, $data['track_id'], $params);
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