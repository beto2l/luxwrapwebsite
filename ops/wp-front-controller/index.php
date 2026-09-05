<?php
/**
 * Route this hosted domain through the OPIN X WordPress Multisite installation.
 */

$network_root = dirname( __DIR__ ) . '/intranet.opin-x.com';
$bootstrap    = $network_root . '/wp-blog-header.php';

if ( ! is_file( $bootstrap ) ) {
	http_response_code( 503 );
	header( 'Content-Type: text/plain; charset=UTF-8' );
	echo 'Website temporarily unavailable.';
	exit;
}

define( 'WP_USE_THEMES', true );
require $bootstrap;
