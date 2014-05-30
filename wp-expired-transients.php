<?php
/*
Plugin Name: WP Expired Transients
Plugin URI: https://github.com/technosailor/wp-expire-transients
Author: Aaron Brazell
Author URI: http://technosailor.com
Description: WordPress does not have a very good garbage collection system for transients when WordPress is not using an external object cache. In fact, it doesn't at all. When a transient expires, it's left in the databasee. This plugin takes care of that for you.
Version: 1.0-alpha
License: MIT
License URI: https://github.com/technosailor/wp-expire-transients/blob/master/LICENSE
*/

class WP_Expired_Transients {

	private $transients;
	public $err;
	public $msg;

	public function __construct() {
		// Object caches handle expiration properly, so no need to garbage collect. Abort mission!
		if ( wp_using_ext_object_cache() ) 
			return false;

		$this->transients = false;
		$this->err = array();
		$this->msg = false;
		$this->hooks();
	}

	public function hooks() {

		add_action( 'init', array( $this, 'purge' ),1 );
		add_action( 'admin_notices', array( $this, 'notices' ) );
	}

	public function notices() {
		if( !empty( $this->err ) ) {
			echo '<div class="error">';
				echo '<ul>';
				foreach( $this->err as $err ) {
					echo '<li>' . $err . '</li>';
				}
				echo '</ul>';
			echo '</div>';
		}
		if( $this->msg ) {
			echo '<div class="updated">';
				echo '<p>' . $this->msg . '</p>';
			echo '</div>';
		}
	}

	public function purge() {
		// So we can reduce DB queries during this process, let's create an empty array to dump expireds into so we can just do one query instead of a potentially large number
		$expireds = array();

		// Get all existing transients
		$transients = $this->_get_transients();
		
		// Check each transient for expiration
		foreach( $transients as $transient ) {
			if( !strpos( $transient->option_name, 'transient_timeout' ) ) {
				// This is not a timeout, so it's a transient. Get it's key
				$key = str_replace( '_transient_', '', $transient->option_name );
				$expiry = $this->_get_transient_expiry( $key );
				
				// Check if the expiration date has passed and mark expired if so.
				if( $this->_is_expired( $expiry ) ) {
					$expireds[] = '"' . $transient->option_name . '"';

					// Include the timeout if it exists
					$expireds[] = '"_transient_timeout_' . $key . '"';
				}
			}
		}
		// Delete Expired
		if( $this->_delete_transient( $expireds ) ) {
			$this->msg = __( 'Expired transients removed' );
		}
		else {
			$this->err[] = __( 'Expired Transients Not Removed' );
		}
	}

	private function _get_transients() {
		global $wpdb;
		$this->transients = $transients = $wpdb->get_results( "SELECT option_name, option_value FROM $wpdb->options WHERE option_name LIKE '%transient%'" );
		return $transients;
	}

	private function _get_transient_expiry( $transient ) {
		global $wpdb;

		foreach( $this->transients as $key => $trans ) {
			if( $trans->option_name == '_transient_timeout_' . $transient ) {
				return (int) trim( $trans->option_value );
			}
		}

		return false;
	}

	private function _is_expired( $expiry ) {
		if( !$expiry || $expiry === 0 ) 
			return false;

		$now = new DateTime( date( 'Y-m-d g:i:s', time() ) );
		$exp = new DateTime( date( 'Y-m-d g:i:s', $expiry ) );
		//$diff = $now->diff( $exp );
		if( $now > $exp)
			return true;
				
		return false;
	}

	private function _delete_transient( $transients ) {
		global $wpdb;

		if( !is_array( $transients ) ) 
			$transients = (array) $transients;

		$tstring = implode( ',', $transients );
		$sql = sprintf( "DELETE FROM $wpdb->options WHERE option_name IN(%s)", $tstring ) ;
		$r = $wpdb->query( $sql );
		if( !is_wp_error( $r ) )
			return true;

		return false;
	}
}

new WP_Expired_Transients;