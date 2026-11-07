# CC Google Rating Badge

Lekka wtyczka WordPress, która pokazuje wyłącznie agregat opinii Google:

- średnią ocenę, np. `4,8 / 5`,
- liczbę opinii, np. `82 opinie`,
- link „Zobacz opinie” i link „Zostaw opinię”,
- atrybucję Google w formie grafiki „Powered by Google”.

Wtyczka **nie pobiera i nie wyświetla** cytatów opinii, nazwisk, zdjęć ani karuzeli. Frontend nie ładuje skryptów Google: dane pobiera po stronie serwera WordPress (Google Places API) i trzyma w cache. Grafikę atrybucji także pobiera serwer i serwuje ją z tej samej domeny.

Wersja: `0.4.0`. Licencja: GPL-2.0-or-later (zob. [`LICENSE`](LICENSE)).

## Wymagania

- PHP 7.4 lub nowszy (zgodność sprawdza PHPCompatibilityWP w CI; testy uruchamiane są na PHP 8.0-8.4, składnię sprawdza `php -l` od 7.4).
- WordPress. Minimalna wersja WordPressa nie jest zweryfikowana testem integracyjnym; wtyczka używa tylko podstawowych funkcji (shortcode, opcje, transients, `wp_remote_get`).
- Do automatycznego pobierania danych: klucz Google Places API. Bez klucza wtyczka pokazuje ręcznie ustawiony wynik zapasowy.

## Instalacja

1. Pobierz plik `cc-google-rating-badge-<wersja>.zip` ze strony **Releases** tego repozytorium (plik zbudowany skryptem `scripts/build-zip.php`; obok leży suma SHA-256).
2. W panelu WordPress: **Wtyczki → Dodaj nową → Wyślij wtyczkę**, wskaż zip i aktywuj.

Katalog wewnątrz zipa nazywa się `cc-google-rating-badge`, tak jak wtyczka. Jeśli na stronie jest wcześniejsza instalacja tej wtyczki pod inną nazwą katalogu, najpierw ją dezaktywuj i usuń; dwie kopie tej samej wtyczki zgłaszają ten sam shortcode.

Instalacja z kodu źródłowego: skopiuj do `wp-content/plugins/cc-google-rating-badge/` pliki `cc-google-rating-badge.php` i `uninstall.php`.

## Konfiguracja

Panel: **Ustawienia → CC Google Rating**.

| Pole | Znaczenie |
| --- | --- |
| Google Places API key | klucz API; zapisywany w bazie WordPressa (opcja), nigdy w kodzie; pole puste nie zmienia zapisanego klucza |
| Google Place ID | identyfikator miejsca, którego ocenę pokazujemy |
| Link do profilu Google | adres przycisku „Zobacz opinie” |
| Link do wystawienia opinii | adres przycisku „Zostaw opinię” |
| Cache danych z Google | od 1 do 168 godzin (domyślnie 24) |
| Fallback | ocena i liczba opinii pokazywane, gdy API nie odpowiada i nie ma ostatniego poprawnego wyniku |

**Brak wartości domyślnych.** Kod nie zawiera żadnych domyślnych danych: Place ID, linki do profilu i do wystawienia opinii oraz fallback są puste. Dopóki nie ustawisz linku do profilu Google oraz danych do pokazania (klucz API z Place ID albo ręczny fallback z liczbą opinii większą od zera), shortcode **nie wyświetla niczego**, a panel ustawień pokazuje ostrzeżenie. Bez linku do wystawienia opinii przycisk „Zostaw opinię” jest ukryty.

Aktualizacja z wersji 0.3.x: wersja 0.3.x miała wpisane w kodzie dane firmy CityCouriers jako wartości domyślne. Jeśli strona działała na tych domyślnych (nie na zapisanych w ustawieniach), po wgraniu 0.4.0 badge zniknie, dopóki nie zapiszesz ustawień w panelu. Przed aktualizacją sprawdź w bazie opcję `cc_google_rating_badge_options` (np. `wp option get cc_google_rating_badge_options`): jeśli zawiera `place_id` i `profile_url`, aktualizacja jest bezpieczna. Panel wersji 0.3.x tego nie pokaże, bo wyświetla wartości domyślne razem z zapisanymi.

### Klucz API

Klucz leży w bazie WordPressa, więc musi być ograniczony w Google Cloud Console do Places API oraz do właściwego źródła użycia (adres IP serwera dla zapytań z serwera). Pełne adresy zawierające klucz nie powinny trafiać do logów, zgłoszeń ani narzędzi diagnostycznych. Klucz wklejony do publicznego zgłoszenia uznaj za ujawniony i unieważnij go.

## Shortcode

```text
[cc_google_rating_badge]
```

Wariant zaufania dla stron sprzedażowych, bez przycisku wystawiania opinii:

```text
[cc_google_rating_badge mode="trust"]
```

Wariant kompaktowy:

```text
[cc_google_rating_badge variant="compact"]
```

Pokazanie daty aktualizacji:

```text
[cc_google_rating_badge show_updated="true"]
```

## Dane, prywatność i zgodność

- Dane odwiedzających stronę nie są wysyłane do Google przed kliknięciem linku.
- Wtyczka nie renderuje danych osobowych recenzentów.
- Publiczny adres grafiki atrybucji pobiera tylko jeden, wpisany w kodzie obraz z `maps.gstatic.com`, sprawdza sygnaturę PNG i rozmiar przed zapisem do cache oraz nie ujawnia klucza API.
- Gdy dane pochodzą z Google Places API albo z ostatniego poprawnego wyniku API, przy badge'u zostaje pokazana grafika „Powered by Google”. Gdy wtyczka pokazuje ręczny fallback, nie pokazuje atrybucji ani komunikatu technicznego.
- Wtyczka nie dodaje automatycznie znaczników schema `AggregateRating`: opinie zewnętrzne o własnej firmie mogą dać nieprawidłowy rich snippet.
- Odinstalowanie wtyczki usuwa jej opcje i transients (`uninstall.php`).
- Korzystanie z Google Places API podlega warunkom Google i kosztom konta Google Cloud właściciela klucza.

### Fallback i ponawianie

Gdy API nie działa, wtyczka pokazuje ostatni poprawny wynik, a gdy go nie ma, skonfigurowany fallback. W ścieżce błędu czas ponowienia jest stały i wynosi 1 godzinę, niezależnie od ustawienia cache, żeby po awarii szybciej wrócić do danych z API.

## Rozwój

Wymagane: PHP 8.0+ (testy czytają zip rozszerzeniem `zip`; sam skrypt budowy go nie wymaga), Composer 2, Node 20+ (tylko do testów skryptów procesu).

```bash
composer install
composer test      # testy wtyczki (atrapa WordPressa) i skryptów
composer phpcs     # WordPress Coding Standards
composer phpstan   # analiza statyczna
composer phpcompat # PHPCompatibilityWP (kod wtyczki od PHP 7.4)
composer security  # audyt zależności i skan wzorców kodu
composer zip       # zip do dist/ (php scripts/build-zip.php)
npm test           # testy skryptów procesu repozytorium
```

## Kontrole CI i bezpieczeństwo

Workflow `CI` uruchamia kontrole poniżej, a job zbiorczy `quality-gate` jest jedyną kontrolą wymaganą przez ochronę `main` (razem z `pr-title`).

| Job | Co sprawdza |
| --- | --- |
| `phpcs` | standard kodowania WordPress (`WordPress`: Core, Extra, Docs), w tym reguły `WordPress.Security` (nonce, sanityzacja wejścia, escapowanie wyjścia) |
| `phpcompat` | PHPCompatibilityWP: kod wtyczki od PHP 7.4, testy i skrypty od PHP 8.0 |
| `phpstan` | analiza statyczna z rozszerzeniem WordPress (poziom podany w `phpstan.neon.dist`) |
| `lint` | `php -l` na PHP 7.4-8.4 |
| `tests` | testy z `tests/` na PHP 8.0-8.4 |
| `security` | `composer audit --locked` (znane podatności zależności deweloperskich) i `scripts/security-patterns.php` (zob. niżej) |
| `release-zip` | buduje zip dwa razy i wymaga identycznej sumy SHA-256; zip jest artefaktem przebiegu |
| `process-tests` | testy skryptów procesu: bramka audytu zgłoszeń, etykiety, `quality-gate` |
| `secret-scan` | gitleaks na pełnej historii (push) albo na zakresie zmian PR |

### Czym jest kontrola bezpieczeństwa

**CodeQL nie obsługuje PHP**, więc nie ma tu analizy przepływu danych z narzędzia klasy CodeQL i nie wolno jej zakładać. Kontrola bezpieczeństwa składa się z czterech uzupełniających się, prostszych warstw:

1. **Reguły `WordPress.Security` w PHPCS**: brak weryfikacji nonce, niesanityzowane wejście z `$_GET`/`$_POST`, nieescapowane wyjście. Wykrywają typowe błędy wtyczek WordPress, ale nie śledzą danych między funkcjami.
2. **Skan wzorców** (`scripts/security-patterns.php`): lista zabronionych funkcji (`eval`, `unserialize`, wywołania powłoki, `curl_*` i `file_get_contents` zamiast API HTTP WordPressa i inne) oraz lista dozwolonych domen wychodzących. Jest heurystyką na tokenach PHP: łapie znane konstrukcje, nie dowodzi bezpieczeństwa.
3. **Audyt zależności** (`composer audit --locked`): wtyczka nie ma zależności uruchomieniowych, więc audyt obejmuje tylko narzędzia deweloperskie z `composer.lock`.
4. **Skan sekretów** (gitleaks): klucze i tokeny w historii.

Poza CI zostają: przegląd logiki (audyt zgłoszeń, zob. Proces), test z prawdziwym kluczem i prawdziwym WordPressem oraz ocena ryzyka dla konkretnego wdrożenia. Zielone CI nie oznacza „wtyczka jest bezpieczna”.

### Wyjątki tymczasowe

`phpcs.xml.dist` i `phpstan.neon.dist` zawierają **tymczasowe wyjątki**: dług z pierwszego audytu narzędziami (formatowanie, dokumentacja kodu, styl warunków, trzy reguły bezpieczeństwa, poziom PHPStan). Każdy ma zgłoszenie z etykietą `audit:*`; poprawka usuwa wyjątek razem z kodem, który go wymagał. Nowych wyjątków nie dodajemy bez zgłoszenia.

## Proces

### Zgłoszenia i etykiety

Prace prowadzimy w zgłoszeniach (Issues) z szablonu `Zadanie lub znalezisko`. Etykiety opisuje [`.github/LABELS.md`](.github/LABELS.md); manifest `.github/labels.json` synchronizuje workflow `Sync GitHub labels`. Priorytet (`P0`-`P4`) i ryzyko (`T1`-`T3`) mają być na każdym otwartym zgłoszeniu.

### Audyt zgłoszeń

Zgłoszenie w zakresie bramki (`P0`-`P2` albo `T2`/`T3`) ma stan audytu: `audit:pending`, `audit:ok` albo `audit:changes`. Nie zaczynamy pracy nad zgłoszeniem w stanie `audit:pending` ani `audit:changes`. Werdykt wydaje strona przeciwna autorowi treści komentarzem `/audit-ok` albo `/audit-changes`; edycja treści cofa stan do `audit:pending`. Autora i recenzenta wyznaczają podpis ostatniej linii (`_— Claude Code (agent lokalny)_` albo `_— ChatGPT_`), etykieta `by:agent` i aplikacja, przez którą powstał wpis. Bramka jest konwencją, nie mechanicznym zamkiem: GitHub nie potrafi zablokować pracy nad zgłoszeniem.

Znaleziska z audytu narzędziami (PHPCS, PHPStan, kontrola wzorców) są zgłoszeniami w stanie `audit:pending`; poprawki idą w osobnych PR z linkiem `Fixes:`.

### Pull requesty

- Tytuł w konwencji Conventional Commits (`feat:`, `fix:`, `docs:`, `chore:`, `ci:`, `test:`, `refactor:`), temat małą literą; pilnuje go `pr-title`.
- Opis według szablonu `.github/PULL_REQUEST_TEMPLATE.md`, z linkiem `Fixes:` albo `Refs:`.
- Do `main` wchodzi się przez PR; wymagane kontrole: `quality-gate` i `pr-title`.

### Wydanie

1. Podnieś wersję w nagłówku wtyczki **i** w wywołaniu `wp_register_style` (skrypt budowy odmawia zipa przy rozbieżności).
2. PR do `main` z zielonym `quality-gate`.
3. `php scripts/build-zip.php`: powstaje `dist/cc-google-rating-badge-<wersja>.zip` i plik `.sha256`.
4. Tag `v<wersja>` na commicie z `main` i wydanie GitHub z zipem oraz sumą jako plikami wydania.

Wdrożenie na stronę to osobny krok właściciela strony (wgranie zipa w panelu); to repozytorium niczego nie instaluje.

## Pochodzenie wspólnych elementów

Etykiety, bramka audytu zgłoszeń (`scripts/quality/issue-audit-*.js` i ich testy), job zbiorczy `quality-gate`, workflow `pr-title` i `Sync GitHub labels` pochodzą z repozytorium [`CityCouriers/wordpress-automation`](https://github.com/CityCouriers/wordpress-automation) (commit `09dd882`; ten sam autor, tam na licencji MIT) i zostały dostosowane: inna lista obszarów, inna lista jobów, własna data startu bramki. Numery `#NNN` w komentarzach skopiowanych skryptów oznaczają zgłoszenia i PR z tamtego repozytorium.

## Licencja

GPL-2.0-or-later. Pełny tekst w [`LICENSE`](LICENSE), nagłówek `License:` w pliku wtyczki.
