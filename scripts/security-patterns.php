<?php
/**
 * Skan wzorcow bezpieczenstwa w kodzie wtyczki (job `security` w CI).
 *
 * Uzycie: php scripts/security-patterns.php [plik.php ...]
 * Bez argumentow skanuje pliki *.php z katalogu glownego repozytorium (kod wtyczki; bez tests/, scripts/, vendor/).
 *
 * To NIE jest analiza przeplywu danych (CodeQL nie obsluguje PHP). To heurystyka na tokenach PHP, ktora
 * wylapuje znane grozne konstrukcje i nieoczekiwane adresy wychodzace:
 * - zabronione funkcje i konstrukcje (wykonywanie kodu i polecen powloki, deserializacja, bezposrednie
 *   klienty HTTP zamiast API HTTP WordPressa, zmienne zmiennych, $_REQUEST);
 * - adresy w kodzie: tylko https i tylko z listy dozwolonych domen (ALLOWED_HOSTS).
 *
 * Wyjatek dla jednego miejsca: komentarz w tej samej linii albo w linii powyzej
 *   // security-patterns: allow <nazwa> - <powod>
 * Powod jest obowiazkowy; wyjatek bez powodu nie dziala. <nazwa> to nazwa funkcji, `$$`, `$_REQUEST`,
 * `backtick`, `include` albo `host:<domena>`.
 *
 * Wymaga PHP 8.0+ (rozszerzenie tokenizer). Kod wyjscia: 0 = czysto, 1 = znaleziska, 2 = blad uzycia.
 */

declare(strict_types=1);

const SECURITY_PATTERNS_FORBIDDEN_FUNCTIONS = array(
	'eval'          => 'wykonywanie kodu z tekstu',
	'assert'        => 'assert() z tekstem wykonuje kod w starszych PHP',
	'create_function' => 'wykonywanie kodu z tekstu',
	'exec'          => 'wywolanie polecenia powloki',
	'shell_exec'    => 'wywolanie polecenia powloki',
	'system'        => 'wywolanie polecenia powloki',
	'passthru'      => 'wywolanie polecenia powloki',
	'proc_open'     => 'wywolanie polecenia powloki',
	'popen'         => 'wywolanie polecenia powloki',
	'pcntl_exec'    => 'wywolanie polecenia powloki',
	'unserialize'   => 'deserializacja obiektow z danych zewnetrznych (uzyj json_decode)',
	'extract'       => 'nadpisywanie zmiennych z tablicy',
	'phpinfo'       => 'ujawnianie konfiguracji serwera',
	'curl_init'     => 'bezposredni klient HTTP (uzyj wp_remote_get / wp_remote_post)',
	'curl_exec'     => 'bezposredni klient HTTP (uzyj wp_remote_get / wp_remote_post)',
	'file_get_contents' => 'czytanie adresow i plikow bez API WordPressa (dla HTTP uzyj wp_remote_get)',
	'fsockopen'     => 'bezposrednie gniazdo sieciowe',
	'base64_decode' => 'dekodowanie czesto ukrywa kod; uzasadnij wyjatkiem',
);

// Dozwolone domeny wychodzace i odnosniki w kodzie (zob. README, sekcja Kontrole CI).
const SECURITY_PATTERNS_ALLOWED_HOSTS = array(
	'maps.googleapis.com', // Google Places API (dane oceny).
	'maps.gstatic.com',    // grafika atrybucji "Powered by Google".
	'g.page',              // domyslne linki do profilu Google (zob. README, wartosci domyslne).
	'github.com',          // Plugin URI w naglowku.
	'www.gnu.org',         // License URI w naglowku.
);

/**
 * Wyjatki zadeklarowane w komentarzach: mapa linia => lista nazw.
 *
 * @param array<int, mixed> $tokens Tokeny z token_get_all().
 * @return array<int, string[]>
 */
function security_patterns_allowances( array $tokens ): array {
	$allowed = array();
	foreach ( $tokens as $token ) {
		if ( ! is_array( $token ) || ( T_COMMENT !== $token[0] && T_DOC_COMMENT !== $token[0] ) ) {
			continue;
		}
		if ( preg_match_all( '/security-patterns:\s*allow\s+(\S+)\s+-\s+\S/', $token[1], $matches ) > 0 ) {
			foreach ( $matches[1] as $name ) {
				// Wyjatek dotyczy linii komentarza i linii pod nim.
				$allowed[ $token[2] ][]     = strtolower( $name );
				$allowed[ $token[2] + 1 ][] = strtolower( $name );
			}
		}
	}
	return $allowed;
}

/**
 * Skanuje kod PHP i zwraca znaleziska.
 *
 * @param string $source Tresc pliku.
 * @param string $file   Nazwa pliku do raportu.
 * @return array<int, array{file: string, line: int, name: string, message: string}>
 */
function security_patterns_scan( string $source, string $file ): array {
	$tokens   = token_get_all( $source );
	$allowed  = security_patterns_allowances( $tokens );
	$findings = array();
	$count    = count( $tokens );

	$add = static function ( int $line, string $name, string $message ) use ( &$findings, $allowed, $file ): void {
		if ( isset( $allowed[ $line ] ) && in_array( strtolower( $name ), $allowed[ $line ], true ) ) {
			return;
		}
		$findings[] = array(
			'file'    => $file,
			'line'    => $line,
			'name'    => $name,
			'message' => $message,
		);
	};

	$line        = 1;
	$in_backtick = false;
	for ( $i = 0; $i < $count; $i++ ) {
		$token = $tokens[ $i ];

		if ( ! is_array( $token ) ) {
			if ( '`' === $token ) {
				// Zglaszamy tylko otwarcie pary (zamkniecie przelacza stan z powrotem).
				if ( ! $in_backtick ) {
					$add( $line, 'backtick', 'operator odwrotnego apostrofu wywoluje polecenie powloki' );
				}
				$in_backtick = ! $in_backtick;
			} elseif ( '$' === $token && isset( $tokens[ $i + 1 ] ) && is_array( $tokens[ $i + 1 ] ) && T_VARIABLE === $tokens[ $i + 1 ][0] ) {
				$add( $line, '$$', 'zmienna zmiennych' );
			}
			continue;
		}

		list( $id, $text, $line ) = $token;

		switch ( $id ) {
			case T_EVAL:
				$add( $line, 'eval', SECURITY_PATTERNS_FORBIDDEN_FUNCTIONS['eval'] );
				break;
			case T_INCLUDE:
			case T_INCLUDE_ONCE:
			case T_REQUIRE:
			case T_REQUIRE_ONCE:
				$add( $line, 'include', 'wlaczanie plikow w kodzie wtyczki wymaga uzasadnienia wyjatkiem' );
				break;
			case T_VARIABLE:
				if ( '$_REQUEST' === $text ) {
					$add( $line, '$_REQUEST', '$_REQUEST miesza GET, POST i COOKIE (uzyj $_POST albo $_GET)' );
				}
				break;
			case T_COMMENT:
			case T_DOC_COMMENT:
			case T_CONSTANT_ENCAPSED_STRING:
			case T_ENCAPSED_AND_WHITESPACE:
			case T_INLINE_HTML:
				$found_urls = preg_match_all( '~(?<![\w])(https?)://([^\s/\'"\\\\)>?#]+)~i', $text, $urls, PREG_SET_ORDER );
				if ( false === $found_urls ) {
					// Blad wyrazenia nie moze dac "czysto": przerywamy.
					throw new RuntimeException( 'security-patterns: blad wyrazenia regularnego adresow: ' . preg_last_error_msg() );
				}
				if ( $found_urls > 0 ) {
					foreach ( $urls as $url ) {
						$host = strtolower( $url[2] );
						if ( 'https' !== strtolower( $url[1] ) ) {
							$add( $line, 'host:' . $host, 'adres bez TLS (http://): ' . $url[0] );
						} elseif ( ! in_array( $host, SECURITY_PATTERNS_ALLOWED_HOSTS, true ) ) {
							$add( $line, 'host:' . $host, 'domena spoza listy dozwolonych: ' . $host );
						}
					}
				}
				break;
			case T_STRING:
			case T_NAME_FULLY_QUALIFIED:
				$name = strtolower( ltrim( $text, '\\' ) );
				if ( ! isset( SECURITY_PATTERNS_FORBIDDEN_FUNCTIONS[ $name ] ) ) {
					break;
				}
				// Wywolanie funkcji: nastepny znaczacy token to "(", a poprzedni nie jest -> :: function new.
				$next = $i + 1;
				while ( $next < $count && is_array( $tokens[ $next ] ) && in_array( $tokens[ $next ][0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
					$next++;
				}
				$prev = $i - 1;
				while ( $prev >= 0 && is_array( $tokens[ $prev ] ) && T_WHITESPACE === $tokens[ $prev ][0] ) {
					$prev--;
				}
				$is_call   = isset( $tokens[ $next ] ) && '(' === $tokens[ $next ];
				$is_member = $prev >= 0 && is_array( $tokens[ $prev ] ) && in_array( $tokens[ $prev ][0], array( T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW ), true );
				if ( $is_call && ! $is_member ) {
					$add( $line, $name, SECURITY_PATTERNS_FORBIDDEN_FUNCTIONS[ $name ] );
				}
				break;
		}
	}

	return $findings;
}

/**
 * Domyslna lista plikow: *.php z katalogu glownego repozytorium.
 *
 * @param string $root Katalog glowny repozytorium.
 * @return string[]
 */
function security_patterns_default_files( string $root ): array {
	$files = glob( $root . '/*.php' );
	$files = false === $files ? array() : $files;
	sort( $files );
	return $files;
}

if ( PHP_SAPI === 'cli' && isset( $argv[0] ) && realpath( $argv[0] ) === __FILE__ ) {
	$root  = dirname( __DIR__ );
	$files = array_slice( $argv, 1 );
	if ( array() === $files ) {
		$files = security_patterns_default_files( $root );
	}
	if ( array() === $files ) {
		fwrite( STDERR, "security-patterns: BLAD: brak plikow do skanowania w {$root}\n" );
		exit( 2 );
	}

	$all = array();
	foreach ( $files as $path ) {
		if ( ! is_file( $path ) ) {
			fwrite( STDERR, "security-patterns: BLAD: brak pliku {$path}\n" );
			exit( 2 );
		}
		$all = array_merge( $all, security_patterns_scan( (string) file_get_contents( $path ), basename( $path ) ) );
	}

	foreach ( $all as $f ) {
		fwrite( STDOUT, sprintf( "%s:%d: [%s] %s\n", $f['file'], $f['line'], $f['name'], $f['message'] ) );
	}
	fwrite( STDOUT, sprintf( "security-patterns: przeskanowano %d plik(i), znalezisk: %d\n", count( $files ), count( $all ) ) );
	exit( array() === $all ? 0 : 1 );
}
