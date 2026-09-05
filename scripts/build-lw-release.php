<?php
/** Build the validated public-file manifest consumed by LuxWrap Studio. */
declare(strict_types=1);

$root = dirname( __DIR__ );
$version = $argv[1] ?? '1.0.0';
if ( ! preg_match( '/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/', $version ) ) {
	fwrite( STDERR, "Use a semantic version, for example 1.0.0.\n" );
	exit( 1 );
}

$allowedExtensions = array( 'html', 'css', 'js', 'json', 'xml', 'txt', 'svg', 'png', 'jpg', 'jpeg', 'webp', 'gif', 'ico', 'woff', 'woff2', 'webmanifest', 'mp4', 'webm' );
$files = array( $root . '/index.html' );
foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/assets', FilesystemIterator::SKIP_DOTS ) ) as $item ) {
	if ( $item->isFile() ) {
		$files[] = $item->getPathname();
	}
}
foreach ( new DirectoryIterator( $root ) as $item ) {
	if ( $item->isFile() && in_array( strtolower( $item->getExtension() ), array( 'svg', 'png', 'jpg', 'jpeg', 'webp', 'gif', 'ico' ), true ) ) {
		$files[] = $item->getPathname();
	}
}

$checksums = array();
foreach ( array_unique( $files ) as $file ) {
	$extension = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
	if ( ! in_array( $extension, $allowedExtensions, true ) ) {
		continue;
	}
	$relative = str_replace( DIRECTORY_SEPARATOR, '/', substr( $file, strlen( $root ) + 1 ) );
	$checksums[ $relative ] = hash_file( 'sha256', $file );
}
ksort( $checksums );

$manifest = array(
	'contract_version' => 1,
	'site'             => 'luxwrap-website',
	'version'          => $version,
	'languages'        => array( 'en', 'es' ),
	'entries'          => array( 'en' => 'index.html', 'es' => 'index.html' ),
	'files_sha256'     => $checksums,
);
file_put_contents( $root . '/lw-release.json', json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . PHP_EOL );
printf( "Built lw-release.json %s with %d public files.\n", $version, count( $checksums ) );
