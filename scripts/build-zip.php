<?php
/**
 * Buduje zip wtyczki do wgrania w wp-admin (Wtyczki -> Dodaj nowa -> Wyslij wtyczke).
 *
 * Uzycie: php scripts/build-zip.php [--out KATALOG]   (domyslnie dist/)
 *
 * Zasady:
 * - katalog wewnatrz zipa nazywa sie jak wtyczka (cc-google-rating-badge/), bo WordPress aktywuje
 *   wtyczke po nazwie katalogu; zip bez tego katalogu albo z zagniezdzonym katalogiem tworzy duplikat;
 * - do zipa trafia tylko jawna lista plikow (INCLUDED): nic z narzedzi, testow ani konfiguracji CI;
 * - wersja z naglowka wtyczki musi zgadzac sie ze stala wersja stylu w kodzie (wp_register_style),
 *   inaczej budowa konczy sie bledem: zip z dwiema roznymi wersjami nie jest wydaniem;
 * - zip jest powtarzalny: plik zapisuje ten skrypt, bez rozszerzenia zip i bez bibliotek (stala kolejnosc
 *   wpisow, stala data 1980-01-01 zapisana wprost w naglowkach, stale uprawnienia, brak kompresji).
 *   Wynik nie zalezy od strefy czasu, systemu ani wersji zlib: ten sam commit daje ten sam plik.
 *
 * Wymaga PHP 7.4+. Kod wyjscia: 0 = gotowe, 1 = blad (komunikat na STDERR).
 */

declare(strict_types=1);

const PLUGIN_SLUG = 'cc-google-rating-badge';
const INCLUDED = [
	'cc-google-rating-badge.php',
	'uninstall.php',
	'LICENSE',
	'README.md',
];
// Najwczesniejsza data zapisywalna w zipie, zapisana wprost w polach DOS: 1980-01-01 00:00:00.
const DOS_TIME = 0x0000;
const DOS_DATE = 0x0021; // (1980 - 1980) << 9 | 1 << 5 | 1
// Uprawnienia plikow: zwykly plik, rw-r--r-- (system Unix w "version made by").
const UNIX_FILE_ATTR = 0100644 << 16;

/**
 * Zatrzymuje budowe z komunikatem.
 *
 * @param string $message Komunikat bledu.
 */
function build_zip_fail( string $message ): void {
	fwrite( STDERR, 'build-zip: BLAD: ' . $message . "\n" );
	exit( 1 );
}

/**
 * Wersja z naglowka wtyczki ("Version: x.y.z").
 *
 * @param string $source Tresc pliku wtyczki.
 * @return string|null Wersja albo null, gdy naglowek jej nie ma.
 */
function build_zip_header_version( string $source ): ?string {
	return 1 === preg_match( '/^\s*\*\s*Version:\s*(\S+)\s*$/m', $source, $m ) ? $m[1] : null;
}

/**
 * Wersja przekazywana do wp_register_style w kodzie wtyczki.
 *
 * @param string $source Tresc pliku wtyczki.
 * @return string|null Wersja albo null, gdy wywolania nie ma.
 */
function build_zip_style_version( string $source ): ?string {
	$pattern = "/wp_register_style\(\s*'" . preg_quote( PLUGIN_SLUG, '/' ) . "'\s*,\s*false\s*,\s*\[\]\s*,\s*'([^']+)'\s*\)/";
	return 1 === preg_match( $pattern, $source, $m ) ? $m[1] : null;
}

/**
 * Sklada zip (metoda STORE) z podanych plikow, w podanej kolejnosci, pod katalogiem wtyczki.
 *
 * @param array<string, string> $contents Mapa nazwa pliku => tresc.
 * @return string Bajty zipa.
 */
function build_zip_bytes( array $contents ): string {
	$local   = '';
	$central = '';
	$count   = 0;
	foreach ( $contents as $file => $data ) {
		$name   = PLUGIN_SLUG . '/' . $file;
		$crc    = crc32( $data );
		$size   = strlen( $data );
		$offset = strlen( $local );

		// Naglowek lokalny: sygnatura, wersja 2.0, flagi 0, metoda 0 (STORE), data DOS, CRC, rozmiary, nazwa.
		$local .= pack( 'VvvvvvVVVvv', 0x04034b50, 20, 0, 0, DOS_TIME, DOS_DATE, $crc, $size, $size, strlen( $name ), 0 ) . $name . $data;

		// Wpis katalogu centralnego: wersja 0x0314 (Unix, 2.0), te same pola, atrybuty zewnetrzne (uprawnienia).
		$central .= pack( 'VvvvvvvVVVvvvvvVV', 0x02014b50, 0x0314, 20, 0, 0, DOS_TIME, DOS_DATE, $crc, $size, $size, strlen( $name ), 0, 0, 0, 0, UNIX_FILE_ATTR, $offset ) . $name;
		++$count;
	}

	$end = pack( 'VvvvvVVv', 0x06054b50, 0, 0, $count, $count, strlen( $central ), strlen( $local ), 0 );
	return $local . $central . $end;
}

/**
 * Buduje zip i plik sumy SHA-256 w katalogu wyjsciowym.
 *
 * @param string $root Katalog glowny repozytorium.
 * @param string $out  Katalog wyjsciowy (tworzony, gdy go brak).
 * @return array{zip: string, sha256: string, version: string}
 */
function build_zip( string $root, string $out ): array {
	$contents = array();
	foreach ( INCLUDED as $file ) {
		$path = $root . '/' . $file;
		if ( ! is_file( $path ) ) {
			build_zip_fail( 'brak pliku ' . $file . ' w ' . $root );
		}
		$data = (string) file_get_contents( $path );
		if ( false !== strpos( $data, "\r" ) ) {
			build_zip_fail( $file . ' zawiera CR (CRLF); repozytorium wymaga LF (.gitattributes), inaczej zip zalezy od systemu.' );
		}
		$contents[ $file ] = $data;
	}

	$plugin_source = $contents[ PLUGIN_SLUG . '.php' ];
	$version       = build_zip_header_version( $plugin_source );
	if ( null === $version ) {
		build_zip_fail( 'naglowek wtyczki nie ma pola Version.' );
	}
	$style_version = build_zip_style_version( $plugin_source );
	if ( $style_version !== $version ) {
		build_zip_fail( sprintf( 'wersja w naglowku (%s) rozni sie od wersji stylu w kodzie (%s).', $version, (string) $style_version ) );
	}
	if ( 1 !== preg_match( '/^\d+\.\d+\.\d+$/', $version ) ) {
		build_zip_fail( 'wersja nie ma ksztaltu x.y.z: ' . $version );
	}

	if ( ! is_dir( $out ) && ! mkdir( $out, 0777, true ) && ! is_dir( $out ) ) {
		build_zip_fail( 'nie mozna utworzyc katalogu ' . $out );
	}
	$zip_path = rtrim( $out, '/\\' ) . '/' . PLUGIN_SLUG . '-' . $version . '.zip';
	if ( is_file( $zip_path ) && ! unlink( $zip_path ) ) {
		build_zip_fail( 'nie mozna nadpisac ' . $zip_path );
	}

	if ( false === file_put_contents( $zip_path, build_zip_bytes( $contents ) ) ) {
		build_zip_fail( 'nie mozna zapisac ' . $zip_path );
	}

	$sha  = hash_file( 'sha256', $zip_path );
	$line = $sha . '  ' . basename( $zip_path ) . "\n";
	file_put_contents( $zip_path . '.sha256', $line );

	return array(
		'zip'     => $zip_path,
		'sha256'  => (string) $sha,
		'version' => $version,
	);
}

if ( PHP_SAPI === 'cli' && isset( $argv[0] ) && realpath( $argv[0] ) === __FILE__ ) {
	$out_dir = dirname( __DIR__ ) . '/dist';
	$args    = array_slice( $argv, 1 );
	for ( $i = 0; $i < count( $args ); $i++ ) {
		if ( '--out' === $args[ $i ] && isset( $args[ $i + 1 ] ) ) {
			$out_dir = $args[ ++$i ];
			continue;
		}
		build_zip_fail( 'nieznany argument: ' . $args[ $i ] . ' (uzycie: --out KATALOG)' );
	}
	$result = build_zip( dirname( __DIR__ ), $out_dir );
	fwrite( STDOUT, sprintf( "build-zip: %s\nbuild-zip: wersja %s\nbuild-zip: sha256 %s\n", $result['zip'], $result['version'], $result['sha256'] ) );
}
