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

function exportAudio($pdo, $trackId, $format, $params = []) {
    // Sprawdź czy utwór istnieje
    $stmt = $pdo->prepare("
        SELECT 
            t.track_id,
            t.title,
            t.file_path,
            t.duration,
            t.sample_rate,
            t.bit_depth,
            t.stems_available
        FROM tracks t
        WHERE t.track_id = ?
    ");
    $stmt->execute([$trackId]);
    $track = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$track) {
        throw new Exception('Utwór nie istnieje.');
    }

    // Parametry domyślne dla eksportu
    $defaults = [
        'quality' => 'high',
        'normalize' => true,
        'dither' => true,
        'metadata' => true,
        'include_stems' => false
    ];

    $params = array_merge($defaults, $params);

    // Konfiguracja formatu
    $formatConfigs = [
        'wav' => [
            'sample_rate' => 48000,
            'bit_depth' => 24,
            'compression' => 'none'
        ],
        'flac' => [
            'sample_rate' => 48000,
            'bit_depth' => 24,
            'compression_level' => 8
        ],
        'mp3' => [
            'bitrate' => 320,
            'vbr' => true,
            'quality' => 0
        ],
        'aac' => [
            'bitrate' => 256,
            'profile' => 'aac_he_v2'
        ],
        'ogg' => [
            'quality' => 9,
            'managed_bitrate' => true
        ]
    ];

    if (!isset($formatConfigs[$format])) {
        throw new Exception('Nieobsługiwany format eksportu.');
    }

    // Symulacja procesu eksportu
    $export = [
        'export_id' => uniqid('export_'),
        'track_info' => $track,
        'format' => $format,
        'format_settings' => $formatConfigs[$format],
        'parameters' => $params,
        'processing_steps' => [
            [
                'step' => 'prepare',
                'status' => 'completed',
                'duration' => rand(100, 500)
            ],
            [
                'step' => 'normalize',
                'status' => 'completed',
                'target_lufs' => -14,
                'peak_level' => -1.0
            ],
            [
                'step' => 'convert',
                'status' => 'completed',
                'duration' => rand(500, 2000)
            ]
        ],
        'output_info' => [
            'file_size' => rand(5000000, 50000000),
            'duration' => $track['duration'],
            'sample_rate' => $formatConfigs[$format]['sample_rate'] ?? $track['sample_rate'],
            'channels' => 2,
            'checksum' => md5(uniqid())
        ]
    ];

    // Zapisz informacje o eksporcie
    $stmt = $pdo->prepare("
        INSERT INTO audio_exports (
            track_id,
            user_id,
            export_id,
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
        $export['export_id'],
        $format,
        json_encode($params),
        'completed',
        json_encode($export)
    ]);

    return $export;
}

function batchConvert($pdo, $trackIds, $format, $params = []) {
    // Sprawdź czy utwory istnieją
    $placeholders = str_repeat('?,', count($trackIds) - 1) . '?';
    $stmt = $pdo->prepare("
        SELECT 
            track_id,
            title,
            file_path,
            duration
        FROM tracks 
        WHERE track_id IN ($placeholders)
    ");
    $stmt->execute($trackIds);
    $tracks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($tracks) !== count($trackIds)) {
        throw new Exception('Niektóre utwory nie istnieją.');
    }

    // Parametry domyślne dla konwersji wsadowej
    $defaults = [
        'parallel_processing' => true,
        'max_threads' => 4,
        'preserve_metadata' => true,
        'error_handling' => 'skip'
    ];

    $params = array_merge($defaults, $params);

    // Symulacja konwersji wsadowej
    $batch = [
        'batch_id' => uniqid('batch_'),
        'format' => $format,
        'parameters' => $params,
        'tracks_count' => count($tracks),
        'start_time' => microtime(true),
        'conversions' => []
    ];

    foreach ($tracks as $track) {
        $conversion = [
            'track_id' => $track['track_id'],
            'status' => 'completed',
            'duration' => rand(500, 2000),
            'output_size' => rand(5000000, 50000000),
            'error' => null
        ];
        $batch['conversions'][] = $conversion;
    }

    $batch['end_time'] = microtime(true);
    $batch['total_duration'] = $batch['end_time'] - $batch['start_time'];
    $batch['success_rate'] = 100;

    // Zapisz informacje o konwersji wsadowej
    $stmt = $pdo->prepare("
        INSERT INTO batch_conversions (
            user_id,
            batch_id,
            format,
            parameters,
            created_at,
            status,
            metadata
        ) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP, ?, ?)
    ");

    $stmt->execute([
        $_SESSION['user_id'],
        $batch['batch_id'],
        $format,
        json_encode($params),
        'completed',
        json_encode($batch)
    ]);

    return $batch;
}

function createArchive($pdo, $trackIds, $params = []) {
    // Sprawdź czy utwory istnieją
    $placeholders = str_repeat('?,', count($trackIds) - 1) . '?';
    $stmt = $pdo->prepare("
        SELECT 
            track_id,
            title,
            file_path,
            duration,
            stems_available
        FROM tracks 
        WHERE track_id IN ($placeholders)
    ");
    $stmt->execute($trackIds);
    $tracks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($tracks) !== count($trackIds)) {
        throw new Exception('Niektóre utwory nie istnieją.');
    }

    // Parametry domyślne dla archiwum
    $defaults = [
        'compression_level' => 9,
        'include_stems' => false,
        'include_metadata' => true,
        'split_by_folders' => true,
        'naming_template' => '{artist} - {title}'
    ];

    $params = array_merge($defaults, $params);

    // Symulacja tworzenia archiwum
    $archive = [
        'archive_id' => uniqid('arch_'),
        'parameters' => $params,
        'tracks_count' => count($tracks),
        'total_size' => 0,
        'files' => []
    ];

    foreach ($tracks as $track) {
        $fileEntry = [
            'track_id' => $track['track_id'],
            'original_name' => $track['title'],
            'archived_name' => generateArchivedName($track, $params['naming_template']),
            'size' => rand(5000000, 50000000),
            'checksum' => md5(uniqid())
        ];
        
        $archive['files'][] = $fileEntry;
        $archive['total_size'] += $fileEntry['size'];
    }

    $archive['compression_ratio'] = rand(40, 70) / 100;
    $archive['final_size'] = round($archive['total_size'] * $archive['compression_ratio']);

    // Zapisz informacje o archiwum
    $stmt = $pdo->prepare("
        INSERT INTO audio_archives (
            user_id,
            archive_id,
            parameters,
            created_at,
            status,
            metadata
        ) VALUES (?, ?, ?, CURRENT_TIMESTAMP, ?, ?)
    ");

    $stmt->execute([
        $_SESSION['user_id'],
        $archive['archive_id'],
        json_encode($params),
        'completed',
        json_encode($archive)
    ]);

    return $archive;
}

function generateArchivedName($track, $template) {
    // Prosta implementacja generowania nazwy pliku
    $replacements = [
        '{artist}' => 'Unknown Artist',
        '{title}' => $track['title'],
        '{track_id}' => $track['track_id']
    ];
    
    return str_replace(
        array_keys($replacements),
        array_values($replacements),
        $template
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? '';

        switch ($action) {
            case 'export':
                if (!isset($data['track_id']) || !isset($data['format'])) {
                    throw new Exception('Brak wymaganych parametrów.');
                }
                $params = $data['parameters'] ?? [];
                $export = exportAudio($pdo, $data['track_id'], $data['format'], $params);
                echo json_encode([
                    'success' => true,
                    'data' => $export
                ]);
                break;

            case 'batch_convert':
                if (!isset($data['track_ids']) || !isset($data['format'])) {
                    throw new Exception('Brak wymaganych parametrów.');
                }
                $params = $data['parameters'] ?? [];
                $batch = batchConvert($pdo, $data['track_ids'], $data['format'], $params);
                echo json_encode([
                    'success' => true,
                    'data' => $batch
                ]);
                break;

            case 'create_archive':
                if (!isset($data['track_ids'])) {
                    throw new Exception('Brak wymaganych parametrów.');
                }
                $params = $data['parameters'] ?? [];
                $archive = createArchive($pdo, $data['track_ids'], $params);
                echo json_encode([
                    'success' => true,
                    'data' => $archive
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