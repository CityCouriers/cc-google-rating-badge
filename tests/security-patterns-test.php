<?php
/**
 * Testy skanu wzorcow bezpieczenstwa (scripts/security-patterns.php).
 *
 * Uruchomienie: php tests/security-patterns-test.php
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/scripts/security-patterns.php';

$passed = 0;

/**
 * Sprawdza warunek i zlicza test.
 *
 * @param bool   $condition Warunek.
 * @param string $name      Nazwa testu.
 */
function sp_assert( bool $condition, string $name ): void {
	global $passed;
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$name}\n" );
		exit( 1 );
	}
	++$passed;
}

/**
 * Nazwy znalezisk dla fragmentu kodu.
 *
 * @param string $code Kod PHP (bez znacznika otwarcia).
 * @return string[]
 */
function sp_names( string $code ): array {
	$names = array_map(
		static function ( array $finding ): string {
			return $finding['name'];
		},
		security_patterns_scan( "<?php\n" . $code, 'fixture.php' )
	);
	sort( $names );
	return $names;
}

// Zabronione funkcje i konstrukcje sa wykrywane (test pozytywny, kazda osobno).
$positive = array(
	'eval'              => 'eval($x);',
	'assert'            => 'assert($x);',
	'exec'              => 'exec($x);',
	'shell_exec'        => 'shell_exec($x);',
	'system'            => 'system($x);',
	'passthru'          => 'passthru($x);',
	'proc_open'         => 'proc_open($x, [], $p);',
	'popen'             => 'popen($x, "r");',
	'unserialize'       => 'unserialize($x);',
	'extract'           => 'extract($x);',
	'phpinfo'           => 'phpinfo();',
	'curl_init'         => 'curl_init($u);',
	'curl_exec'         => 'curl_exec($c);',
	'file_get_contents' => 'file_get_contents($u);',
	'fsockopen'         => 'fsockopen($h, 80);',
	'base64_decode'     => 'base64_decode($x);',
	'backtick'          => '$o = `ls`;',
	'$$'                => '$a = $$name;',
	'$_REQUEST'         => '$a = $_REQUEST["x"];',
	'include'           => 'include $file;',
);
foreach ( $positive as $name => $code ) {
	sp_assert( array( $name ) === sp_names( $code ), "wykrywa {$name}" );
}
sp_assert( array( 'exec' ) === sp_names( '\exec($x);' ), 'wykrywa wywolanie z prefiksem \\ (pelna nazwa)' );
sp_assert( array( 'exec' ) === sp_names( 'EXEC($x);' ), 'nazwa funkcji bez rozrozniania wielkosci liter' );
sp_assert( array( 'exec' ) === sp_names( 'exec /* uwaga */ ($x);' ), 'komentarz miedzy nazwa a nawiasem nie ukrywa wywolania' );

// Test negatywny: to samo slowo w innym znaczeniu nie jest znaleziskiem.
$negative = array(
	'metoda obiektu'           => '$o->exec($x);',
	'metoda statyczna'         => 'Foo::system($x);',
	'definicja funkcji'        => 'function exec($x) { return $x; }',
	'definicja metody'         => 'class A { public function system() {} }',
	'tworzenie obiektu'        => '$o = new exec();',
	'nazwa w tekscie'          => '$s = "exec(" . \'eval(\';',
	'nazwa w komentarzu'       => "// exec(\$x) i eval(\$y)\n/* unserialize(\$z) */",
	'stala o podobnej nazwie'  => '$a = EXECUTABLE;',
	'funkcja o podobnej nazwie' => 'wp_exec_thing($x); my_extract($y);',
	'zmienna o tej nazwie'     => '$exec = 1; $system = 2;',
	'dolar w tekscie'          => '$s = "$$";',
);
foreach ( $negative as $name => $code ) {
	sp_assert( array() === sp_names( $code ), "nie flaguje: {$name}" );
}

// Adresy: tylko https z listy dozwolonych domen.
sp_assert( array() === sp_names( '$u = "https://maps.googleapis.com/maps/api/place/details/json";' ), 'dozwolona domena https' );
sp_assert( array() === sp_names( '$u = \'https://maps.gstatic.com/x.png\';' ), 'dozwolona domena gstatic' );
sp_assert( array( 'host:maps.googleapis.com' ) === sp_names( '$u = "http://maps.googleapis.com/x";' ), 'http bez TLS jest znaleziskiem nawet dla dozwolonej domeny' );
sp_assert( array( 'host:evil.example' ) === sp_names( '$u = "https://evil.example/collect?k=1";' ), 'domena spoza listy' );
sp_assert( array( 'host:evil.example' ) === sp_names( "// wysylka na https://evil.example/x\n\$a = 1;" ), 'adres w komentarzu tez jest sprawdzany' );
sp_assert( array( 'host:maps.googleapis.com.evil.example' ) === sp_names( '$u = "https://maps.googleapis.com.evil.example/";' ), 'domena z dozwolonym prefiksem nie przechodzi' );
sp_assert( array( 'host:evil.example' ) === sp_names( '$u = "https://evil.example";' ), 'adres bez sciezki' );
sp_assert( array() === sp_names( '$u = "ftp://x";' . "\n" . '$s = "tekst bez adresu";' ), 'brak adresu http(s) nie jest znaleziskiem' );

// Wyjatki: komentarz z nazwa i powodem w tej samej linii albo linii powyzej.
sp_assert( array() === sp_names( '// security-patterns: allow base64_decode - dekodowanie naglowka testowego' . "\n" . 'base64_decode($x);' ), 'wyjatek w linii powyzej' );
sp_assert( array() === sp_names( 'base64_decode($x); // security-patterns: allow base64_decode - dekodowanie naglowka testowego' ), 'wyjatek w tej samej linii' );
sp_assert( array( 'base64_decode' ) === sp_names( '// security-patterns: allow base64_decode' . "\n" . 'base64_decode($x);' ), 'wyjatek bez powodu nie dziala' );
sp_assert( array( 'base64_decode' ) === sp_names( '// security-patterns: allow base64_decode -' . "\n" . 'base64_decode($x);' ), 'wyjatek z pustym powodem nie dziala' );
sp_assert( array( 'exec' ) === sp_names( '// security-patterns: allow system - powod' . "\n" . 'exec($x);' ), 'wyjatek dla innej nazwy nie dziala' );
sp_assert( array( 'exec' ) === sp_names( '// security-patterns: allow exec - powod' . "\n\n" . 'exec($x);' ), 'wyjatek nie siega dalej niz linia pod komentarzem' );
sp_assert( array() === sp_names( '// security-patterns: allow host:evil.example - testowy adres' . "\n" . '$u = "https://evil.example/";' ), 'wyjatek dla domeny' );

// Wiele znalezisk w jednym pliku ma poprawne numery linii.
$found = security_patterns_scan( "<?php\n\$a = 1;\neval(\$x);\n\n\$u = 'http://x.test/';\n", 'f.php' );
sp_assert( 2 === count( $found ) && 3 === $found[0]['line'] && 5 === $found[1]['line'], 'numery linii znalezisk' );

// Kod wtyczki z repozytorium jest czysty.
$root  = dirname( __DIR__ );
$files = security_patterns_default_files( $root );
sp_assert( count( $files ) >= 2, 'domyslna lista plikow obejmuje kod wtyczki' );
foreach ( $files as $path ) {
	$result = security_patterns_scan( (string) file_get_contents( $path ), basename( $path ) );
	sp_assert( array() === $result, 'kod wtyczki bez znalezisk: ' . basename( $path ) . ' ' . sp_json( $result ) );
}

/**
 * Krotki zapis znalezisk do komunikatu.
 *
 * @param array<int, array<string, mixed>> $findings Znaleziska.
 */
function sp_json( array $findings ): string {
	return (string) json_encode( $findings );
}

echo $passed . " tests passed\n";
