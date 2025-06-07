let aktualnyUzytkownik = null;
let playlisty = {};
let strukturaPlaylist = {};
let aktualnaPlaylista = "AI";
let indeksAktualnegoUtworu = -1;
let odtwarzanie = false;
let audio = new Audio();
let trybLosowy = false;

function renderujFormularzLogowania() {
    const aplikacja = document.getElementById('aplikacja');
    aplikacja.innerHTML = `
        <div class="kontener-logowania">
            <form class="formularz-logowania" id="formularzLogowania">
                <h2>Zaloguj się</h2>
                <input type="text" id="login" placeholder="Nazwa użytkownika lub email" required>
                <input type="password" id="haslo" placeholder="Hasło" required>
                <button type="submit">Zaloguj</button>
                <p>Nie masz konta? <a href="#" onclick="renderujFormularzRejestracji()">Zarejestruj się</a></p>
                <p><a href="#" onclick="renderujFormularzResetowaniaHasla()">Zapomniałeś hasła?</a></p>
            </form>
        </div>
    `;

    document.getElementById('formularzLogowania').onsubmit = async (e) => {
        e.preventDefault();
        const login = document.getElementById('login').value;
        const haslo = document.getElementById('haslo').value;

        try {
            const response = await fetch('api/login.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    login: login,
                    password: haslo
                })
            });

            const data = await response.json();
            if (data.success) {
                aktualnyUzytkownik = data.user.username;
                wczytajDaneUzytkownika();
                renderujAplikacje();
            } else {
                alert(data.message);
            }
        } catch (error) {
            alert('Błąd podczas logowania');
            console.error('Błąd:', error);
        }
    };
}

function renderujFormularzRejestracji() {
    const aplikacja = document.getElementById('aplikacja');
    aplikacja.innerHTML = `
        <div class="kontener-logowania">
            <form class="formularz-logowania" id="formularzRejestracji">
                <h2>Zarejestruj się</h2>
                <input type="text" id="nazwaUzytkownika" placeholder="Nazwa użytkownika" required>
                <input type="email" id="email" placeholder="Email" required>
                <input type="date" id="dataUrodzenia" required>
                <input type="password" id="haslo" placeholder="Hasło" required>
                <input type="password" id="potwierdzHaslo" placeholder="Potwierdź hasło" required>
                <button type="submit">Zarejestruj</button>
                <p>Masz już konto? <a href="#" onclick="renderujFormularzLogowania()">Zaloguj się</a></p>
            </form>
        </div>
    `;

    document.getElementById('formularzRejestracji').onsubmit = async (e) => {
        e.preventDefault();
        const nazwaUzytkownika = document.getElementById('nazwaUzytkownika').value;
        const email = document.getElementById('email').value;
        const dataUrodzenia = document.getElementById('dataUrodzenia').value;
        const haslo = document.getElementById('haslo').value;
        const potwierdzHaslo = document.getElementById('potwierdzHaslo').value;

        try {
            const response = await fetch('api/register.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    username: nazwaUzytkownika,
                    email: email,
                    dateOfBirth: dataUrodzenia,
                    password: haslo,
                    confirmPassword: potwierdzHaslo
                })
            });

            const data = await response.json();
            if (data.success) {
                alert('Rejestracja zakończona pomyślnie. Możesz się teraz zalogować.');
                renderujFormularzLogowania();
            } else {
                alert(data.message);
            }
        } catch (error) {
            alert('Błąd podczas rejestracji');
            console.error('Błąd:', error);
        }
    };
}

function renderujFormularzResetowaniaHasla() {
    const aplikacja = document.getElementById('aplikacja');
    aplikacja.innerHTML = `
        <div class="kontener-logowania">
            <form class="formularz-logowania" id="formularzResetuHasla">
                <h2>Resetowanie hasła</h2>
                <p>Podaj adres email powiązany z Twoim kontem.</p>
                <input type="email" id="email" placeholder="Adres email" required>
                <button type="submit">Wyślij link resetujący</button>
                <p><a href="#" onclick="renderujFormularzLogowania()">Powrót do logowania</a></p>
            </form>
        </div>
    `;

    document.getElementById('formularzResetuHasla').onsubmit = async (e) => {
        e.preventDefault();
        const email = document.getElementById('email').value;

        try {
            const response = await fetch('api/request_reset.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ email: email })
            });

            const data = await response.json();
            alert(data.message);
            if (data.success) {
                renderujFormularzLogowania();
            }
        } catch (error) {
            alert('Wystąpił błąd podczas wysyłania żądania resetowania hasła');
            console.error('Błąd:', error);
        }
    };
}

function renderujFormularzUstawianiaNowegoHasla(token) {
    const aplikacja = document.getElementById('aplikacja');
    aplikacja.innerHTML = `
        <div class="kontener-logowania">
            <form class="formularz-logowania" id="formularzNowegoHasla">
                <h2>Ustaw nowe hasło</h2>
                <input type="password" id="noweHaslo" placeholder="Nowe hasło" required 
                       minlength="8" pattern="(?=.*\\d)(?=.*[a-z])(?=.*[A-Z]).{8,}">
                <small>Hasło musi zawierać minimum 8 znaków, w tym cyfrę, małą i wielką literę.</small>
                <input type="password" id="potwierdzHaslo" placeholder="Potwierdź hasło" required>
                <button type="submit">Zmień hasło</button>
            </form>
        </div>
    `;

    document.getElementById('formularzNowegoHasla').onsubmit = async (e) => {
        e.preventDefault();
        const noweHaslo = document.getElementById('noweHaslo').value;
        const potwierdzHaslo = document.getElementById('potwierdzHaslo').value;

        if (noweHaslo !== potwierdzHaslo) {
            alert('Hasła nie są identyczne');
            return;
        }

        try {
            const response = await fetch('api/reset_password.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    token: token,
                    newPassword: noweHaslo
                })
            });

            const data = await response.json();
            alert(data.message);
            if (data.success) {
                renderujFormularzLogowania();
            }
        } catch (error) {
            alert('Wystąpił błąd podczas zmiany hasła');
            console.error('Błąd:', error);
        }
    };
}

function zaloguj(nazwaUzytkownika, haslo) {
    if (haslo === "password") {
        aktualnyUzytkownik = nazwaUzytkownika;
        wczytajDaneUzytkownika();
        renderujAplikacje();
        requestNotificationPermission();
    } else {
        alert("Niepoprawne hasło");
    }
}

function wczytajDaneUzytkownika() {
    playlisty = {
        "AI": []
    };
    pobierzUtworyIPlaylisty();
}

function utworzStruktureDrzewa(sciezki) {
    const drzewo = {};
    sciezki.forEach(sciezka => {
        let aktualnyPoziom = drzewo;
        const czesci = sciezka.split('/');
        czesci.forEach((czesc, indeks) => {
            if (!aktualnyPoziom[czesc]) {
                aktualnyPoziom[czesc] = indeks === czesci.length - 1 ? [] : {};
            }
            aktualnyPoziom = aktualnyPoziom[czesc];
        });
    });
    return drzewo;
}

function pobierzUtworyIPlaylisty() {
    fetch('./api/get_files.php')
        .then(response => response.json())
        .then(data => {
            const sciezkiPlaylist = [];

            function przetworzPliki(pliki, aktualnaSciezka = '') {
                Object.keys(pliki).forEach(klucz => {
                    const pelnaSciezka = aktualnaSciezka ? `${aktualnaSciezka}/${klucz}` : klucz;
                    if (Array.isArray(pliki[klucz])) {
                        sciezkiPlaylist.push(pelnaSciezka);
                        playlisty[pelnaSciezka] = pliki[klucz].map(plik => ({
                            nazwa: plik,
                            czas: 0,
                            sciezka: `./sagni/${pelnaSciezka}/${plik}`
                        }));
                    } else if (typeof pliki[klucz] === 'object') {
                        przetworzPliki(pliki[klucz], pelnaSciezka);
                    }
                });
            }

            przetworzPliki(data);
            strukturaPlaylist = utworzStruktureDrzewa(sciezkiPlaylist);
            renderujPlaylisty();
            renderujUtwory();
        })
        .catch(blad => {
            console.error('Błąd podczas pobierania plików:', blad);
            alert('Nie udało się pobrać listy utworów. Spróbuj odświeżyć stronę.');
        });
}

function renderujAplikacje() {
    const aplikacja = document.getElementById('aplikacja');
    aplikacja.innerHTML = `
        <div class="kontener">
            <div id="pasekBoczny" class="pasek-boczny">
                <button id="przyciskToggle" class="przycisk-toggle">☰</button>
                <div class="info-uzytkownika">
                    Zalogowano jako: ${aktualnyUzytkownik}
                    <button onclick="renderujUstawieniaKonta()">Ustawienia konta</button>
                    <button onclick="wyloguj()">Wyloguj</button>
                </div>
                <h2>Playlisty</h2>
                <div id="listaPlaylist"></div>
                <form id="formularzNowejPlaylisty">
                    <input type="text" id="nazwaNowejPlaylisty" placeholder="Nazwa nowej playlisty">
                    <button type="submit">Utwórz playlistę</button>
                </form>
            </div>
            <div class="glowna-zawartosc">
                <h1>InX</h1>
                <div class="playlista">
                    <h3 id="nazwaAktualnejPlaylisty">AI</h3>
                    <div id="listaUtworow"></div>
                </div>
            </div>
        </div>
        <div class="odtwarzacz">
            <div id="terazOdtwarzane"></div>
            <div class="kontrolki-odtwarzacza">
                <button id="przyciskPoprzedni">⏮</button>
                <button id="przyciskOdtwarzaniaPauzy">▶</button>
                <button id="przyciskNastepny">⏭</button>
            </div>
            <div class="pasek-postepu-container">
                <div id="wyswietlaczCzasu">0:00 / 0:00</div>
                <div class="pasek-postepu">
                    <div class="postep" id="pasekPostepu"></div>
                </div>
            </div>
        </div>
        <div class="kontener-przesylania">
            <input type="file" id="wejsciePliku" accept=".mp3,.flac" style="display: none;">
            <button id="przyciskLosowegoUtworu">Losowy Utwór</button>
        </div>
    `;

    renderujPlaylisty();
    renderujUtwory();
    ustawNasluchiwaczeZdarzen();
    inicjalizujUkladStrony();
}

function renderujPlaylisty() {
    const listaPlaylist = document.getElementById('listaPlaylist');
    listaPlaylist.innerHTML = '';
    renderujPlaylistyRekurencyjnie(strukturaPlaylist, listaPlaylist);
}

function renderujPlaylistyRekurencyjnie(elementy, kontener, sciezka = '', poziom = 0) {
    Object.keys(elementy).forEach(klucz => {
        const pelnaSciezka = sciezka ? `${sciezka}/${klucz}` : klucz;
        const elementPlaylisty = document.createElement('div');
        elementPlaylisty.className = 'element-playlisty';
        elementPlaylisty.setAttribute('data-sciezka', pelnaSciezka);
        elementPlaylisty.style.paddingLeft = `${poziom * 20}px`;

        const spanStrzalki = document.createElement('span');
        spanStrzalki.className = 'strzalka-playlisty';
        elementPlaylisty.appendChild(spanStrzalki);

        const spanNazwy = document.createElement('span');
        spanNazwy.className = 'nazwa-playlisty';
        spanNazwy.textContent = klucz;
        elementPlaylisty.appendChild(spanNazwy);

        const przyciskiKontener = document.createElement('div');
        przyciskiKontener.className = 'przyciski-playlisty';

        const przyciskZmienNazwe = document.createElement('button');
        przyciskZmienNazwe.textContent = '✏️';
        przyciskZmienNazwe.onclick = (e) => {
            e.stopPropagation();
            zmienNazwePlisty(pelnaSciezka);
        };

        const przyciskUsun = document.createElement('button');
        przyciskUsun.textContent = '🗑️';
        przyciskUsun.onclick = (e) => {
            e.stopPropagation();
            usunPlayliste(pelnaSciezka);
        };

        przyciskiKontener.appendChild(przyciskZmienNazwe);
        przyciskiKontener.appendChild(przyciskUsun);
        elementPlaylisty.appendChild(przyciskiKontener);

        if (typeof elementy[klucz] === 'object' && !Array.isArray(elementy[klucz])) {
            spanStrzalki.textContent = '▼';
            elementPlaylisty.onclick = (e) => {
                e.stopPropagation();
                spanStrzalki.textContent = spanStrzalki.textContent === '▼' ? '▶' : '▼';
                const nastepnyElement = elementPlaylisty.nextElementSibling;
                if (nastepnyElement && nastepnyElement.classList.contains('zagniezdzony-kontener')) {
                    nastepnyElement.style.display = nastepnyElement.style.display === 'none' ? 'block' : 'none';
                }
            };

            kontener.appendChild(elementPlaylisty);

            const zagniezdzonyKontener = document.createElement('div');
            zagniezdzonyKontener.className = 'zagniezdzony-kontener';
            zagniezdzonyKontener.style.display = 'block';
            kontener.appendChild(zagniezdzonyKontener);

            renderujPlaylistyRekurencyjnie(elementy[klucz], zagniezdzonyKontener, pelnaSciezka, poziom + 1);
        } else {
            spanStrzalki.style.visibility = 'hidden';
            elementPlaylisty.onclick = (e) => {
                e.stopPropagation();
                wczytajPlayliste(pelnaSciezka);
            };
            kontener.appendChild(elementPlaylisty);
        }
    });
}

function usunPlayliste(sciezka) {
    if (confirm(`Czy na pewno chcesz usunąć playlistę "${sciezka}"?`)) {
        fetch('api/delete_playlist.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `path=${encodeURIComponent(sciezka)}`
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    usunPlistZeStruktury(sciezka);
                    delete playlisty[sciezka];
                    renderujPlaylisty();
                    if (aktualnaPlaylista === sciezka) {
                        aktualnaPlaylista = "AI";
                        renderujUtwory();
                    }
                    alert(data.message);
                } else {
                    alert(data.message || "Nie udało się usunąć playlisty");
                }
            })
            .catch(error => {
                console.error('Błąd:', error);
                alert("Wystąpił błąd podczas usuwania playlisty");
            });
    }
}

function zmienNazwePlisty(sciezka) {
    const nowaNazwa = prompt(`Podaj nową nazwę dla playlisty "${sciezka}":`, sciezka.split('/').pop());
    if (nowaNazwa && nowaNazwa !== sciezka.split('/').pop()) {
        const czesciSciezki = sciezka.split('/');
        czesciSciezki.pop();
        const nowaSciezka = czesciSciezki.length > 0 ? `${czesciSciezki.join('/')}/${nowaNazwa}` : nowaNazwa;

        fetch('api/rename_playlist.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `oldPath=${encodeURIComponent(sciezka)}&newPath=${encodeURIComponent(nowaSciezka)}`
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    zmienNazwePlistyWStrukturze(sciezka, nowaSciezka);
                    if (playlisty[sciezka]) {
                        playlisty[nowaSciezka] = playlisty[sciezka];
                        delete playlisty[sciezka];
                    }
                    if (aktualnaPlaylista === sciezka) {
                        aktualnaPlaylista = nowaSciezka;
                    }
                    renderujPlaylisty();
                    renderujUtwory();
                    alert(data.message);
                } else {
                    alert(data.message || "Nie udało się zmienić nazwy playlisty");
                }
            })
            .catch(error => {
                console.error('Błąd:', error);
                alert("Wystąpił błąd podczas zmiany nazwy playlisty");
            });
    }
}

function usunPlistZeStruktury(sciezka) {
    const czesci = sciezka.split('/');
    let aktualnyPoziom = strukturaPlaylist;
    for (let i = 0; i < czesci.length - 1; i++) {
        aktualnyPoziom = aktualnyPoziom[czesci[i]];
    }
    delete aktualnyPoziom[czesci[czesci.length - 1]];
}

function zmienNazwePlistyWStrukturze(staraSciezka, nowaSciezka) {
    const stareczesci = staraSciezka.split('/');
    const noweczesci = nowaSciezka.split('/');
    let aktualnyPoziom = strukturaPlaylist;

    for (let i = 0; i < stareczesci.length - 1; i++) {
        aktualnyPoziom = aktualnyPoziom[stareczesci[i]];
    }

    const staraNazwa = stareczesci[stareczesci.length - 1];
    const nowaNazwa = noweczesci[noweczesci.length - 1];
    aktualnyPoziom[nowaNazwa] = aktualnyPoziom[staraNazwa];
    delete aktualnyPoziom[staraNazwa];
}

function wczytajPlayliste(nazwaPlaylisty) {
    aktualnaPlaylista = nazwaPlaylisty;
    document.getElementById('nazwaAktualnejPlaylisty').textContent = nazwaPlaylisty;
    renderujUtwory();
}

function renderujUtwory() {
    const listaUtworow = document.getElementById('listaUtworow');
    listaUtworow.innerHTML = '';
    playlisty[aktualnaPlaylista].forEach((utwor, indeks) => {
        const elementUtworu = document.createElement('div');
        elementUtworu.className = 'utwor';
        if (indeks === indeksAktualnegoUtworu && aktualnaPlaylista === audio.dataset.playlista) {
            elementUtworu.classList.add('aktywny');
        }
        elementUtworu.innerHTML = `
            <span>${utwor.nazwa}</span>
            <button onclick="przeniesDoPlaylisty('${utwor.nazwa}')">➡️</button>
        `;
        elementUtworu.onclick = () => odtworzUtwor(indeks);
        listaUtworow.appendChild(elementUtworu);

        let tymczasoweAudio = new Audio(utwor.sciezka);
        tymczasoweAudio.onloadedmetadata = function () {
            utwor.czas = tymczasoweAudio.duration;
            elementUtworu.querySelector('span').textContent = `${utwor.nazwa} (${formatujCzas(utwor.czas)})`;
        };
        tymczasoweAudio.onerror = function () {
            elementUtworu.querySelector('span').textContent = `${utwor.nazwa} (Błąd ładowania)`;
        };
    });
}

function formatujCzas(sekundy) {
    const minuty = Math.floor(sekundy / 60);
    const pozostaleSekundy = Math.floor(sekundy % 60);
    return `${minuty}:${pozostaleSekundy.toString().padStart(2, '0')}`;
}

function przeniesDoPlaylisty(nazwaUtworu) {
    const celowaPlaylista = prompt("Do której playlisty przenieść utwór?");
    if (celowaPlaylista && playlisty[celowaPlaylista]) {
        const indeksUtworu = playlisty[aktualnaPlaylista].findIndex(utwor => utwor.nazwa === nazwaUtworu);
        if (indeksUtworu !== -1) {
            const utwor = playlisty[aktualnaPlaylista].splice(indeksUtworu, 1)[0];
            playlisty[celowaPlaylista].push(utwor);
            renderujUtwory();
            alert(`Przeniesiono "${nazwaUtworu}" do playlisty "${celowaPlaylista}"`);
        }
    } else if (celowaPlaylista) {
        alert("Taka playlista nie istnieje");
    }
}

function odtworzUtwor(indeks) {
    indeksAktualnegoUtworu = indeks;
    const utwor = playlisty[aktualnaPlaylista][indeksAktualnegoUtworu];
    audio.src = utwor.sciezka;
    audio.dataset.playlista = aktualnaPlaylista;
    audio.play();
    odtwarzanie = true;
    trybLosowy = false;
    aktualizujStanOdtwarzacza();
}

function aktualizujStanOdtwarzacza() {
    document.querySelectorAll('.utwor').forEach((el, idx) => {
        el.classList.toggle('aktywny', idx === indeksAktualnegoUtworu && aktualnaPlaylista === audio.dataset.playlista);
    });

    if (indeksAktualnegoUtworu !== -1) {
        const aktualneUtwory = playlisty[aktualnaPlaylista];
        const aktualnyUtwor = aktualneUtwory[indeksAktualnegoUtworu];
        document.getElementById('terazOdtwarzane').textContent = aktualnyUtwor.nazwa;
        document.getElementById('przyciskOdtwarzaniaPauzy').textContent = odtwarzanie ? '⏸' : '▶';
    }

    document.getElementById('nazwaAktualnejPlaylisty').textContent = aktualnaPlaylista;
}

function odtworzLosowyUtwor() {
    const wszystkieUtwory = Object.values(playlisty).flat();
    if (wszystkieUtwory.length > 0) {
        const losowyIndeks = Math.floor(Math.random() * wszystkieUtwory.length);
        const losowyUtwor = wszystkieUtwory[losowyIndeks];

        for (const [nazwaPlaylisty, utwory] of Object.entries(playlisty)) {
            const indeksUtworu = utwory.findIndex(u => u.sciezka === losowyUtwor.sciezka);
            if (indeksUtworu !== -1) {
                aktualnaPlaylista = nazwaPlaylisty;
                indeksAktualnegoUtworu = indeksUtworu;
                break;
            }
        }

        audio.src = losowyUtwor.sciezka;
        audio.dataset.playlista = aktualnaPlaylista;
        audio.play();
        odtwarzanie = true;
        trybLosowy = true;
        aktualizujStanOdtwarzacza();
        renderujUtwory();

        setTimeout(() => {
            const aktywnyUtwor = document.querySelector('.utwor.aktywny');
            if (aktywnyUtwor) {
                aktywnyUtwor.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }, 100);
    } else {
        alert("Brak dostępnych utworów do odtworzenia.");
    }
}

function ustawNasluchiwaczeZdarzen() {
    document.getElementById('przyciskOdtwarzaniaPauzy').onclick = () => {
        if (indeksAktualnegoUtworu !== -1) {
            if (odtwarzanie) {
                audio.pause();
            } else {
                audio.play();
            }
            odtwarzanie = !odtwarzanie;
            aktualizujStanOdtwarzacza();
        }
    };

    document.getElementById('przyciskLosowegoUtworu').onclick = odtworzLosowyUtwor;

    document.getElementById('przyciskToggle').onclick = togglePasekBoczny;

    document.getElementById('przyciskPoprzedni').onclick = () => {
        if (indeksAktualnegoUtworu > 0) {
            odtworzUtwor(indeksAktualnegoUtworu - 1);
        }
    };

    document.getElementById('przyciskNastepny').onclick = () => {
        if (indeksAktualnegoUtworu < playlisty[aktualnaPlaylista].length - 1) {
            odtworzUtwor(indeksAktualnegoUtworu + 1);
        }
    };

    document.getElementById('formularzNowejPlaylisty').onsubmit = (e) => {
        e.preventDefault();
        const nazwaNowejPlaylisty = document.getElementById('nazwaNowejPlaylisty').value;
        const wybranaPlaylista = document.querySelector('.element-playlisty.wybrana');
        let sciezkaRodzica = '';

        if (wybranaPlaylista) {
            sciezkaRodzica = wybranaPlaylista.getAttribute('data-sciezka');
        }

        if (nazwaNowejPlaylisty) {
            console.log(`Próba utworzenia playlisty: ${nazwaNowejPlaylisty} w ${sciezkaRodzica}`);
            utworzNowaPlayliste(nazwaNowejPlaylisty, sciezkaRodzica);
        } else {
            alert("Nazwa playlisty nie może być pusta");
        }
    };

    document.getElementById('wejsciePliku').onchange = (e) => {
        const plik = e.target.files[0];
        if (plik && (plik.name.endsWith('.mp3') || plik.name.endsWith('.flac'))) {
            const nowyUtwor = {
                nazwa: plik.name,
                czas: 0,
                sciezka: URL.createObjectURL(plik)
            };
            playlisty[aktualnaPlaylista].push(nowyUtwor);
            renderujUtwory();
            alert(`Dodano utwór "${plik.name}" do playlisty "${aktualnaPlaylista}"`);
        } else {
            alert("Proszę wybrać plik MP3 lub FLAC.");
        }
    };

    audio.ontimeupdate = () => {
        const pasekPostepu = document.getElementById('pasekPostepu');
        const wyswietlaczCzasu = document.getElementById('wyswietlaczCzasu');
        const postep = (audio.currentTime / audio.duration) * 100;
        pasekPostepu.style.width = `${postep}%`;

        const aktualnyCzas = formatujCzas(audio.currentTime);
        const calkowityCzas = formatujCzas(audio.duration);
        wyswietlaczCzasu.textContent = `${aktualnyCzas} / ${calkowityCzas}`;
    };

    audio.onended = () => {
        if (trybLosowy) {
            odtworzLosowyUtwor();
        } else if (indeksAktualnegoUtworu < playlisty[aktualnaPlaylista].length - 1) {
            odtworzUtwor(indeksAktualnegoUtworu + 1);
        } else {
            odtworzLosowyUtwor();
        }
    };

    document.querySelector('.pasek-postepu').onclick = (e) => {
        const pasekPostepu = document.querySelector('.pasek-postepu');
        const pozycjaKlikniecia = e.clientX - pasekPostepu.getBoundingClientRect().left;
        const procentKlikniecia = pozycjaKlikniecia / pasekPostepu.offsetWidth;
        audio.currentTime = procentKlikniecia * audio.duration;
    };
}

function wyloguj() {
    aktualnyUzytkownik = null;
    playlisty = {};
    aktualnaPlaylista = "AI";
    indeksAktualnegoUtworu = -1;
    odtwarzanie = false;
    audio.pause();
    audio.src = '';
    renderujFormularzLogowania();
}

function utworzNowaPlayliste(nazwa, sciezkaRodzica = '') {
    const sciezka = sciezkaRodzica ? `${sciezkaRodzica}/${nazwa}` : nazwa;

    fetch('api/create_playlist.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `name=${encodeURIComponent(sciezka)}`
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (!playlisty[sciezka]) {
                    playlisty[sciezka] = [];
                }
                aktualizujStruktureDrzewa(sciezka);
                renderujPlaylisty();
                document.getElementById('nazwaNowejPlaylisty').value = '';
                console.log(`Playlista "${sciezka}" została utworzona pomyślnie.`);
            } else {
                console.error(`Błąd tworzenia playlisty: ${data.message}`);
                alert(data.message || "Nie udało się utworzyć playlisty");
            }
        })
        .catch(error => {
            console.error('Błąd:', error);
            alert("Wystąpił błąd podczas tworzenia playlisty");
        });
}

function aktualizujStruktureDrzewa(sciezka) {
    let aktualnyPoziom = strukturaPlaylist;
    const czesci = sciezka.split('/');
    czesci.forEach((czesc, indeks) => {
        if (!aktualnyPoziom[czesc]) {
            aktualnyPoziom[czesc] = indeks === czesci.length - 1 ? [] : {};
        }
        aktualnyPoziom = aktualnyPoziom[czesc];
    });
    console.log('Zaktualizowana struktura drzewa:', strukturaPlaylist);
}

function togglePasekBoczny() {
    const pasekBoczny = document.getElementById('pasekBoczny');
    pasekBoczny.classList.toggle('zwiniety');

    const przyciskToggle = document.getElementById('przyciskToggle');
    przyciskToggle.textContent = pasekBoczny.classList.contains('zwiniety') ? '☰' : '✖';

    const glownaZawartosc = document.querySelector('.glowna-zawartosc');
    glownaZawartosc.style.width = pasekBoczny.classList.contains('zwiniety') ? 'calc(100% - 60px)' : 'calc(100% - 250px)';
}

function inicjalizujUkladStrony() {
    const pasekBoczny = document.getElementById('pasekBoczny');
    const glownaZawartosc = document.querySelector('.glowna-zawartosc');

    if (pasekBoczny && glownaZawartosc) {
        glownaZawartosc.style.width = pasekBoczny.classList.contains('zwiniety') ? 'calc(100% - 60px)' : 'calc(100% - 250px)';
    }
}

document.addEventListener('keydown', (e) => {
    if (e.code === 'Space') {
        e.preventDefault();
        document.getElementById('przyciskOdtwarzaniaPauzy').click();
    } else if (e.code === 'ArrowRight') {
        document.getElementById('przyciskNastepny').click();
    } else if (e.code === 'ArrowLeft') {
        document.getElementById('przyciskPoprzedni').click();
    }
});

function requestNotificationPermission() {
    if ('Notification' in window) {
        Notification.requestPermission().then(function (permission) {
            if (permission === "granted") {
                subscribeToPushNotifications();
            }
        });
    }
}

function subscribeToPushNotifications() {
    navigator.serviceWorker.ready.then(function (registration) {
        registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array('TwójKluczPublicznyVAPID')
        }).then(function (subscription) {
            // Tutaj wysyłamy subskrypcję do serwera
            console.log('Subskrypcja push:', JSON.stringify(subscription));
        }).catch(function (error) {
            console.error('Błąd subskrypcji push:', error);
        });
    });
}

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding)
        .replace(/\-/g, '+')
        .replace(/_/g, '/');

    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);

    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

function renderujUstawieniaKonta() {
    const aplikacja = document.getElementById('aplikacja');
    aplikacja.innerHTML = `
        <div class="kontener">
            <div id="pasekBoczny" class="pasek-boczny">
                <button id="przyciskToggle" class="przycisk-toggle">☰</button>
                <div class="info-uzytkownika">
                    Zalogowano jako: ${aktualnyUzytkownik}
                    <button onclick="powrotDoGlownej()" class="przycisk-powrotu">Powrót</button>
                    <button onclick="wyloguj()">Wyloguj</button>
                </div>
            </div>
            <div class="glowna-zawartosc">
                <div class="ustawienia-konta">
                    <h2>Ustawienia konta</h2>
                    
                    <div class="sekcja-ustawien">
                        <h3>Zmiana hasła</h3>
                        <form id="formularzZmianyHasla">
                            <input type="password" id="stareHaslo" placeholder="Aktualne hasło" required>
                            <input type="password" id="noweHaslo" placeholder="Nowe hasło" required>
                            <input type="password" id="potwierdzNoweHaslo" placeholder="Potwierdź nowe hasło" required>
                            <button type="submit">Zmień hasło</button>
                        </form>
                    </div>
                    
                    <div class="sekcja-ustawien">
                        <h3>Weryfikacja dwuetapowa</h3>
                        <div class="przelacznik-2fa">
                            <label>
                                <input type="checkbox" id="wlacz2FA"> 
                                Włącz weryfikację dwuetapową
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="odtwarzacz">
            <div id="terazOdtwarzane"></div>
            <div class="kontrolki-odtwarzacza">
                <button id="przyciskPoprzedni">⏮</button>
                <button id="przyciskOdtwarzaniaPauzy">▶</button>
                <button id="przyciskNastepny">⏭</button>
            </div>
            <div class="pasek-postepu-container">
                <div id="wyswietlaczCzasu">0:00 / 0:00</div>
                <div class="pasek-postepu">
                    <div class="postep" id="pasekPostepu"></div>
                </div>
            </div>
        </div>
    `;

    // Zachowujemy pasek boczny w tym samym stanie
    const pasekBoczny = document.getElementById('pasekBoczny');
    if (pasekBoczny.classList.contains('zwiniety')) {
        togglePasekBoczny();
    }

    // Obsługa formularza zmiany hasła
    document.getElementById('formularzZmianyHasla').onsubmit = async (e) => {
        e.preventDefault();
        const stareHaslo = document.getElementById('stareHaslo').value;
        const noweHaslo = document.getElementById('noweHaslo').value;
        const potwierdzNoweHaslo = document.getElementById('potwierdzNoweHaslo').value;

        if (noweHaslo !== potwierdzNoweHaslo) {
            alert('Nowe hasło i potwierdzenie hasła nie są identyczne');
            return;
        }

        try {
            const response = await fetch('api/change_password.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    oldPassword: stareHaslo,
                    newPassword: noweHaslo
                })
            });

            const data = await response.json();
            alert(data.message);
            if (data.success) {
                powrotDoGlownej();
            }
        } catch (error) {
            alert('Wystąpił błąd podczas zmiany hasła');
            console.error('Błąd:', error);
        }
    };


    // Obsługa 2FA
    document.getElementById('wlacz2FA').onchange = async (e) => {
        try {
            const response = await fetch('api/enable_2fa.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    enable: e.target.checked
                })
            });

            const data = await response.json();
            alert(data.message);
        } catch (error) {
            alert('Wystąpił błąd podczas aktualizacji ustawień 2FA');
            console.error('Błąd:', error);
            e.target.checked = !e.target.checked;
        }
    };

    // Przywracamy nasłuchiwacze zdarzeń dla odtwarzacza
    ustawNasluchiwaczeZdarzen();
    inicjalizujUkladStrony();
}

function powrotDoGlownej() {
    renderujAplikacje();
}

renderujFormularzLogowania();
