<?php
/*
Plugin Name: Output Chain Manager
Plugin URI: http://webdogs.com/
Description: Use this Dashboard Widget to output and manage CSV reports. (Output customized for ShipStation.)
Version: 1.0
Author: WEBDOGS JVC
Author URI: http://webdogs.com/
License: WDLv1
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Required functions
 */
if ( ! function_exists( 'woothemes_queue_update' ) )
	require_once( 'woo-includes/woo-functions.php' );

/**
 * Plugin updates
 */
woothemes_queue_update( plugin_basename( __FILE__ ), '', '' );

if ( is_woocommerce_active() ) {

	// Load plugin class files
	require_once( 'includes/class-output-chain-manager.php' 	);
	require_once( 'includes/class-output-chain-reporter.php' 	);
	require_once( 'includes/class-output-chain-row-columns.php' );

	// Load plugin libraries
	require_once( 'includes/lib/class-output-chain-post-type.php'  );
	require_once( 'includes/lib/class-output-chain-list-table.php' );

	/**
	 * Returns the main instance of Output_Chain to prevent the need to use globals.
	 *
	 * @since  1.0.0
	 * @return object Output_Chain
	 */
	function Output_Chiain_Manager() { 
	    $instance = Output_Chiain_Manager::instance( __FILE__, '1.0.0' );

	    if( is_null( $instance->reporter ) ) {
	        $instance->reporter = Output_Chiain_Reporter::instance( $instance );
	    }

	    return $instance;
	}


	Output_Chiain_Manager();

	// Register custom post types
	Output_Chiain_Manager()->register_post_types();
}
