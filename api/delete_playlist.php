<?php
header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['path'])) {
    $playlistPath = './sagni/' . $_POST['path'];

    if (is_dir($playlistPath)) {
        if (deleteDirectory($playlistPath)) {
            $response['success'] = true;
            $response['message'] = 'Playlista usunięta pomyślnie';
        } else {
            $response['message'] = 'Nie udało się usunąć playlisty';
        }
    } else {
        $response['message'] = 'Playlista nie istnieje';
    }
} else {
    $response['message'] = 'Nieprawidłowe żądanie';
}

echo json_encode($response);

function deleteDirectory($dir) {
    if (!file_exists($dir)) {
        return true;
    }

    if (!is_dir($dir)) {
        return unlink($dir);
    }

    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') {
            continue;
        }

        if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
            return false;
        }
    }

    return rmdir($dir);
}
?>