<?php
header('Content-Type: application/json');

function scanDirectory($dir) {
    $result = [];
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            $result[$file] = scanDirectory($path);
        } else if (in_array(pathinfo($file, PATHINFO_EXTENSION), ['mp3', 'flac', 'wav'])) {
            // Changed to include both MP3 and FLAC files
            $result[] = $file;
        }
    }
    return $result;
}

$dir = '../sagni/';
$files = scanDirectory($dir);

// Add all MP3 and FLAC files from the main directory to the BEST playlist
$best_playlist = [];
foreach ($files as $key => $value) {
    if (is_string($value) && in_array(pathinfo($value, PATHINFO_EXTENSION), ['mp3', 'flac', 'wav'])) {
        $best_playlist[] = $value;
        unset($files[$key]);
    }
}

// Add the BEST playlist to the beginning of the $files array
if (!empty($best_playlist)) {
    $files = array_merge(['AI' => $best_playlist], $files);
}

echo json_encode($files);
?>