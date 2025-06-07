<!--Copyright by Juliusz Sagan. The right to copy is strictly prohibited! It is forbidden to use resources inappropriately!-->
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>InX</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
    <link rel="manifest" href="js/manifest.json">
    <meta name="theme-color" content="#1DB954">
    <link rel="icon" type="image/png" href="media/img/ico.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <link rel="apple-touch-icon" href="icon-192x192.png">
</head>
<body>
    DB
    <div id="aplikacja">
        <!-- Tutaj umieść zawartość swojej aplikacji -->
        <h1>Witaj w InX</h1>
        <p>To jest przykładowa zawartość aplikacji.</p>
    </div>
    <button id="installPWA" style="display: none;">Zainstaluj aplikację</button>

    <script src="js/script.js"></script>
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('/js/service-worker.js', { scope: '/js/' })
                    .then(function(registration) {
                        console.log('Service Worker zarejestrowany pomyślnie:', registration);
                    })
                    .catch(function(error) {
                        console.log('Błąd rejestracji Service Workera:', error);
                    });
            });
        }

        let deferredPrompt;
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            document.getElementById('installPWA').style.display = 'block';
        });

        document.getElementById('installPWA').addEventListener('click', () => {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then((choiceResult) => {
                    if (choiceResult.outcome === 'accepted') {
                        console.log('Użytkownik zaakceptował instalację PWA');
                    }
                    deferredPrompt = null;
                });
            }
        });
    </script>
</body>
</html>
<!--Copyright by Juliusz Sagan. The right to copy is strictly prohibited! It is forbidden to use resources inappropriately!-->