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

function trackSystemPerformance($pdo, $metrics = []) {
    // Domyślne metryki do śledzenia
    $defaultMetrics = [
        'cpu_usage' => true,
        'memory_usage' => true,
        'disk_io' => true,
        'network_latency' => true,
        'audio_buffer_health' => true,
        'processing_queue' => true
    ];

    $metrics = array_merge($defaultMetrics, $metrics);

    // Symulacja zbierania metryk systemowych
    $performance = [
        'timestamp' => microtime(true),
        'session_id' => session_id(),
        'system_metrics' => [
            'cpu' => [
                'total_usage' => rand(20, 80),
                'process_usage' => rand(5, 30),
                'thread_count' => rand(10, 50),
                'core_distribution' => generateCoreDistribution()
            ],
            'memory' => [
                'total_used' => rand(2000, 8000), // MB
                'process_used' => rand(500, 2000), // MB
                'peak_usage' => rand(2500, 9000), // MB
                'swap_usage' => rand(0, 500) // MB
            ],
            'disk' => [
                'read_speed' => rand(50, 200), // MB/s
                'write_speed' => rand(40, 180), // MB/s
                'iops' => rand(1000, 5000),
                'queue_depth' => rand(1, 10)
            ],
            'network' => [
                'latency' => rand(5, 50), // ms
                'bandwidth_usage' => rand(1, 50), // MB/s
                'packet_loss' => rand(0, 100) / 1000, // percentage
                'connection_count' => rand(1, 20)
            ]
        ],
        'audio_metrics' => [
            'buffer_size' => 512,
            'sample_rate' => 48000,
            'buffer_underruns' => rand(0, 5),
            'processing_load' => rand(30, 90) / 100,
            'latency' => [
                'input' => rand(2, 10), // ms
                'output' => rand(2, 10), // ms
                'total' => rand(5, 20) // ms
            ]
        ],
        'processing_queue' => [
            'pending_tasks' => rand(0, 20),
            'completed_tasks' => rand(50, 200),
            'average_processing_time' => rand(10, 100), // ms
            'queue_health' => rand(85, 100) / 100
        ]
    ];

    // Zapisz metryki wydajności
    $stmt = $pdo->prepare("
        INSERT INTO performance_logs (
            session_id,
            user_id,
            timestamp,
            system_metrics,
            audio_metrics,
            queue_metrics
        ) VALUES (?, ?, CURRENT_TIMESTAMP, ?, ?, ?)
    ");

    $stmt->execute([
        session_id(),
        $_SESSION['user_id'],
        json_encode($performance['system_metrics']),
        json_encode($performance['audio_metrics']),
        json_encode($performance['processing_queue'])
    ]);

    return $performance;
}

function analyzePerformanceHistory($pdo, $timeRange = '1 hour') {
    // Pobierz historyczne dane wydajności
    $stmt = $pdo->prepare("
        SELECT 
            system_metrics,
            audio_metrics,
            queue_metrics,
            timestamp
        FROM performance_logs
        WHERE user_id = ?
        AND timestamp >= NOW() - INTERVAL ?
        ORDER BY timestamp ASC
    ");
    
    $stmt->execute([$_SESSION['user_id'], $timeRange]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Analiza trendów i wzorców
    $analysis = [
        'time_range' => $timeRange,
        'data_points' => count($logs),
        'trends' => [
            'cpu_usage' => analyzeTrend(array_column($logs, 'system_metrics', 'cpu', 'total_usage')),
            'memory_usage' => analyzeTrend(array_column($logs, 'system_metrics', 'memory', 'total_used')),
            'audio_latency' => analyzeTrend(array_column($logs, 'audio_metrics', 'latency', 'total'))
        ],
        'anomalies' => detectAnomalies($logs),
        'performance_score' => calculatePerformanceScore($logs),
        'recommendations' => generateOptimizationRecommendations($logs)
    ];

    // Zapisz analizę
    $stmt = $pdo->prepare("
        INSERT INTO performance_analyses (
            user_id,
            time_range,
            created_at,
            trends_data,
            anomalies_data,
            recommendations
        ) VALUES (?, ?, CURRENT_TIMESTAMP, ?, ?, ?)
    ");

    $stmt->execute([
        $_SESSION['user_id'],
        $timeRange,
        json_encode($analysis['trends']),
        json_encode($analysis['anomalies']),
        json_encode($analysis['recommendations'])
    ]);

    return $analysis;
}

function optimizeSystemResources($pdo, $targetMetrics = []) {
    // Domyślne cele optymalizacji
    $defaults = [
        'cpu_target' => 70, // max percentage
        'memory_target' => 80, // max percentage
        'latency_target' => 10, // ms
        'buffer_size_target' => 512 // samples
    ];

    $targetMetrics = array_merge($defaults, $targetMetrics);

    // Symulacja procesu optymalizacji
    $optimization = [
        'timestamp' => microtime(true),
        'initial_state' => [
            'cpu_usage' => rand(75, 95),
            'memory_usage' => rand(85, 95),
            'audio_latency' => rand(15, 25)
        ],
        'optimization_steps' => [
            [
                'type' => 'process_priority',
                'action' => 'adjust_thread_priority',
                'impact' => rand(5, 15)
            ],
            [
                'type' => 'memory_management',
                'action' => 'cleanup_unused_buffers',
                'impact' => rand(10, 20)
            ],
            [
                'type' => 'audio_buffer',
                'action' => 'optimize_buffer_size',
                'impact' => rand(3, 8)
            ]
        ],
        'final_state' => [
            'cpu_usage' => rand(50, 70),
            'memory_usage' => rand(60, 75),
            'audio_latency' => rand(5, 10)
        ],
        'improvements' => [
            'cpu_reduction' => rand(15, 30),
            'memory_freed' => rand(500, 2000), // MB
            'latency_improvement' => rand(30, 60) // percentage
        ]
    ];

    // Zapisz wyniki optymalizacji
    $stmt = $pdo->prepare("
        INSERT INTO resource_optimizations (
            user_id,
            timestamp,
            target_metrics,
            initial_state,
            optimization_steps,
            final_state,
            improvements
        ) VALUES (?, CURRENT_TIMESTAMP, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $_SESSION['user_id'],
        json_encode($targetMetrics),
        json_encode($optimization['initial_state']),
        json_encode($optimization['optimization_steps']),
        json_encode($optimization['final_state']),
        json_encode($optimization['improvements'])
    ]);

    return $optimization;
}

function generateCoreDistribution() {
    $cores = rand(4, 16);
    $distribution = [];
    
    for ($i = 0; $i < $cores; $i++) {
        $distribution["core_$i"] = rand(10, 100);
    }
    
    return $distribution;
}

function analyzeTrend($data) {
    // Prosta symulacja analizy trendu
    return [
        'direction' => rand(0, 1) ? 'increasing' : 'decreasing',
        'magnitude' => rand(1, 100) / 100,
        'stability' => rand(1, 100) / 100,
        'correlation' => rand(70, 100) / 100
    ];
}

function detectAnomalies($logs) {
    // Symulacja detekcji anomalii
    return [
        'cpu_spikes' => [
            ['timestamp' => time() - 3600, 'value' => 95, 'duration' => 30],
            ['timestamp' => time() - 1800, 'value' => 88, 'duration' => 45]
        ],
        'memory_leaks' => [
            ['start_time' => time() - 7200, 'growth_rate' => 50, 'duration' => 300]
        ],
        'audio_glitches' => [
            ['timestamp' => time() - 900, 'type' => 'buffer_underrun', 'severity' => 0.8]
        ]
    ];
}

function calculatePerformanceScore($logs) {
    // Symulacja obliczania ogólnego wyniku wydajności
    return [
        'overall_score' => rand(70, 100),
        'components' => [
            'cpu_efficiency' => rand(75, 100),
            'memory_management' => rand(70, 100),
            'audio_performance' => rand(80, 100),
            'resource_utilization' => rand(75, 95)
        ]
    ];
}

function generateOptimizationRecommendations($logs) {
    // Symulacja generowania rekomendacji
    return [
        [
            'type' => 'cpu',
            'priority' => 'high',
            'action' => 'Zoptymalizuj wykorzystanie wątków',
            'expected_impact' => rand(10, 30)
        ],
        [
            'type' => 'memory',
            'priority' => 'medium',
            'action' => 'Zwiększ rozmiar bufora audio',
            'expected_impact' => rand(5, 15)
        ],
        [
            'type' => 'audio',
            'priority' => 'low',
            'action' => 'Dostosuj rozmiar bloku DMA',
            'expected_impact' => rand(3, 8)
        ]
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $action = $data['action'] ?? '';

        switch ($action) {
            case 'track_performance':
                $metrics = $data['metrics'] ?? [];
                $performance = trackSystemPerformance($pdo, $metrics);
                echo json_encode([
                    'success' => true,
                    'data' => $performance
                ]);
                break;

            case 'analyze_history':
                $timeRange = $data['time_range'] ?? '1 hour';
                $analysis = analyzePerformanceHistory($pdo, $timeRange);
                echo json_encode([
                    'success' => true,
                    'data' => $analysis
                ]);
                break;

            case 'optimize_resources':
                $targetMetrics = $data['target_metrics'] ?? [];
                $optimization = optimizeSystemResources($pdo, $targetMetrics);
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