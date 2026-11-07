<?php
/**
 * Testy budowy zipa wydania (scripts/build-zip.php).
 *
 * Uruchomienie: php tests/build-zip-test.php   (do odczytu zipa uzywa rozszerzenia zip; sam skrypt budowy go nie wymaga)
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/scripts/build-zip.php';

$passed = 0;

/**
 * Sprawdza warunek i zlicza test.
 *
 * @param bool   $condition Warunek.
 * @param string $name      Nazwa testu.
 */
function bz_assert( bool $condition, string $name ): void {
	global $passed;
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$name}\n" );
		exit( 1 );
	}
	++$passed;
}

/**
 * Tworzy pusty katalog tymczasowy.
 *
 * @return string Sciezka.
 */
function bz_tmpdir(): string {
	$dir = sys_get_temp_dir() . '/cc-grb-' . bin2hex( random_bytes( 6 ) );
	mkdir( $dir, 0777, true );
	return $dir;
}

/**
 * Usuwa katalog z zawartoscia.
 *
 * @param string $dir Katalog.
 */
function bz_rmtree( string $dir ): void {
	if ( ! is_dir( $dir ) ) {
		return;
	}
	foreach ( scandir( $dir ) as $item ) {
		if ( '.' === $item || '..' === $item ) {
			continue;
		}
		$path = $dir . '/' . $item;
		is_dir( $path ) ? bz_rmtree( $path ) : unlink( $path );
	}
	rmdir( $dir );
}

/**
 * Kopiuje pliki zrodlowe wydania do katalogu tymczasowego z podmiana tresci wtyczki.
 *
 * @param string      $root    Katalog zrodlowy.
 * @param callable    $mutate  Funkcja zmieniajaca tresc pliku wtyczki.
 * @return string Katalog z kopia.
 */
function bz_copy_root( string $root, callable $mutate ): string {
	$copy = bz_tmpdir();
	foreach ( INCLUDED as $file ) {
		$data = (string) file_get_contents( $root . '/' . $file );
		if ( PLUGIN_SLUG . '.php' === $file ) {
			$data = $mutate( $data );
		}
		file_put_contents( $copy . '/' . $file, $data );
	}
	return $copy;
}

/**
 * Uruchamia budowe w osobnym procesie (blad konczy proces kodem 1).
 *
 * @param string $root Katalog zrodlowy.
 * @param string $out  Katalog wyjsciowy.
 * @return array{0: int, 1: string} Kod wyjscia i polaczone wyjscie.
 */
function bz_run( string $root, string $out ): array {
	$script = dirname( __DIR__ ) . '/scripts/build-zip.php';
	$code   = 'require ' . var_export( $script, true ) . '; build_zip(' . var_export( $root, true ) . ', ' . var_export( $out, true ) . ');';
	$cmd    = escapeshellarg( PHP_BINARY ) . ' -r ' . escapeshellarg( $code ) . ' 2>&1';
	$output = array();
	$rc     = 0;
	exec( $cmd, $output, $rc ); // phpcs:ignore -- tylko test, wejscie stale.
	return array( $rc, implode( "\n", $output ) );
}

$root = dirname( __DIR__ );
$tmp  = bz_tmpdir();

// Wersja z naglowka i ze stylu to ta sama wartosc, a funkcje odczytu dzialaja na prawdziwym pliku.
$plugin = (string) file_get_contents( $root . '/cc-google-rating-badge.php' );
bz_assert( null !== build_zip_header_version( $plugin ), 'naglowek ma wersje' );
bz_assert( build_zip_header_version( $plugin ) === build_zip_style_version( $plugin ), 'wersja naglowka = wersja stylu' );
bz_assert( null === build_zip_header_version( '<?php // bez naglowka' ), 'brak naglowka daje null' );
bz_assert( null === build_zip_style_version( '<?php // bez stylu' ), 'brak stylu daje null' );

// Budowa i zawartosc zipa.
$result = build_zip( $root, $tmp . '/a' );
bz_assert( is_file( $result['zip'] ), 'zip powstal' );
bz_assert( basename( $result['zip'] ) === 'cc-google-rating-badge-' . $result['version'] . '.zip', 'nazwa zipa z wersja' );
bz_assert( 1 === preg_match( '/^\d+\.\d+\.\d+$/', $result['version'] ), 'wersja x.y.z' );

$zip = new ZipArchive();
bz_assert( true === $zip->open( $result['zip'] ), 'zip da sie otworzyc' );
$names = array();
for ( $i = 0; $i < $zip->numFiles; $i++ ) {
	$stat    = $zip->statIndex( $i );
	$names[] = $stat['name'];
	bz_assert( 0 === strpos( $stat['name'], PLUGIN_SLUG . '/' ), 'wpis w katalogu wtyczki: ' . $stat['name'] );
	bz_assert( ZipArchive::CM_STORE === $stat['comp_method'], 'bez kompresji: ' . $stat['name'] );
	bz_assert( false === strpos( $stat['name'], '..' ) && false === strpos( $stat['name'], '\\' ), 'bezpieczna nazwa wpisu: ' . $stat['name'] );
	$zip->getExternalAttributesIndex( $i, $opsys, $attr );
	bz_assert( ZipArchive::OPSYS_UNIX === $opsys && 0100644 === ( $attr >> 16 ), 'uprawnienia 0644: ' . $stat['name'] );
	bz_assert( $zip->getFromIndex( $i ) === file_get_contents( $root . '/' . substr( $stat['name'], strlen( PLUGIN_SLUG ) + 1 ) ), 'tresc zgodna ze zrodlem: ' . $stat['name'] );
}
bz_assert(
	array( 'cc-google-rating-badge/cc-google-rating-badge.php', 'cc-google-rating-badge/uninstall.php', 'cc-google-rating-badge/LICENSE', 'cc-google-rating-badge/README.md' ) === $names,
	'dokladnie lista wpisow w stalej kolejnosci'
);
$zip->close();

// Daty zapisane wprost w naglowkach lokalnych (1980-01-01 00:00:00), niezaleznie od strefy czasu i systemu.
$raw    = (string) file_get_contents( $result['zip'] );
$offset = 0;
$seen   = 0;
while ( 0x04034b50 === unpack( 'Vsig', $raw, $offset )['sig'] ) {
	$h = unpack( 'Vsig/vver/vflags/vmethod/vtime/vdate/Vcrc/Vcsize/Vsize/vnlen/velen', $raw, $offset );
	bz_assert( 0 === $h['time'] && 0x0021 === $h['date'], 'stala data DOS w naglowku lokalnym' );
	bz_assert( 0 === $h['method'] && $h['csize'] === $h['size'], 'metoda STORE, rozmiar bez zmian' );
	$offset += 30 + $h['nlen'] + $h['elen'] + $h['csize'];
	++$seen;
}
bz_assert( count( $names ) === $seen, 'liczba naglowkow lokalnych = liczba wpisow' );

// Nic spoza listy (testy, narzedzia, konfiguracja CI) nie trafia do zipa.
foreach ( array( 'tests/', 'scripts/', '.github/', 'composer.json', 'vendor/', 'phpcs.xml.dist' ) as $forbidden ) {
	foreach ( $names as $name ) {
		bz_assert( false === strpos( $name, $forbidden ), "zip nie zawiera {$forbidden}" );
	}
}

// Suma SHA-256 w pliku obok zipa zgadza sie z plikiem.
$sha_line = (string) file_get_contents( $result['zip'] . '.sha256' );
bz_assert( hash_file( 'sha256', $result['zip'] ) . '  ' . basename( $result['zip'] ) . "\n" === $sha_line, 'plik .sha256 zgodny z zipem' );

// Powtarzalnosc: drugi przebieg i osobny proces dają identyczny plik (data jest w naglowkach, nie z zegara).
$second = build_zip( $root, $tmp . '/b' );
bz_assert( hash_file( 'sha256', $result['zip'] ) === hash_file( 'sha256', $second['zip'] ), 'dwa przebiegi: ta sama suma' );
list( $rc, $proc_out ) = bz_run( $root, $tmp . '/c' );
bz_assert( 0 === $rc && hash_file( 'sha256', $result['zip'] ) === hash_file( 'sha256', $tmp . '/c/' . basename( $result['zip'] ) ), 'osobny proces: ta sama suma (rc=' . $rc . ' ' . $proc_out . ')' );

// Odmowy (test negatywny): kazda zla wersja zrodel konczy sie kodem 1 i komunikatem, bez zipa.
$cases = array(
	'rozbieznosc wersji stylu'   => array(
		static function ( string $d ): string {
			return preg_replace( "/(wp_register_style\('cc-google-rating-badge', false, \[\], ')[^']+/", '${1}9.9.9', $d );
		},
		'rozni sie od wersji stylu',
	),
	'brak pola Version'          => array(
		static function ( string $d ): string {
			return (string) preg_replace( '/^ \* Version:.*\n/m', '', $d );
		},
		'nie ma pola Version',
	),
	'wersja o zlym ksztalcie'    => array(
		static function ( string $d ): string {
			$d = (string) preg_replace( '/^ \* Version: \d+\.\d+\.\d+$/m', ' * Version: 0.3', $d );
			return (string) preg_replace( "/(wp_register_style\('cc-google-rating-badge', false, \[\], ')[^']+/", '${1}0.3', $d );
		},
		'nie ma ksztaltu x.y.z',
	),
	'konce linii CRLF w pliku'   => array(
		static function ( string $d ): string {
			return str_replace( "\n", "\r\n", $d );
		},
		'zawiera CR',
	),
);
foreach ( $cases as $name => $case ) {
	$copy = bz_copy_root( $root, $case[0] );
	list( $rc, $out ) = bz_run( $copy, $copy . '/dist' );
	bz_assert( 1 === $rc, "odmowa (kod 1): {$name}" );
	bz_assert( false !== strpos( $out, 'build-zip: BLAD' ) && false !== strpos( $out, $case[1] ), "komunikat odmowy: {$name} => {$out}" );
	bz_assert( array() === ( glob( $copy . '/dist/*.zip' ) ?: array() ), "brak zipa po odmowie: {$name}" );
	bz_rmtree( $copy );
}

// Brak pliku z listy to blad, nie zip bez niego.
$copy = bz_copy_root(
	$root,
	static function ( string $d ): string {
		return $d;
	}
);
unlink( $copy . '/LICENSE' );
list( $rc, $out ) = bz_run( $copy, $copy . '/dist' );
bz_assert( 1 === $rc && false !== strpos( $out, 'brak pliku LICENSE' ), 'brak pliku z listy konczy sie bledem' );
bz_rmtree( $copy );

bz_rmtree( $tmp );
echo $passed . " tests passed\n";
