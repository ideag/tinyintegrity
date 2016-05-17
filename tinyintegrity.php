<?php
/*
Plugin Name: tinyIntegrity
Plugin URI: http://arunas.co
Description: Monitor integrity of WordPress files
Author: Arūnas Liuiza
Author URI: http://arunas.co/
Version: 0.1.0
*/

add_action( 'plugins_loaded', array( 'tinyIntegrity', 'init' ) );
class tinyIntegrity {
	public static $skip = array(
		'wp-config.php',
		'wp-content',
	);
	public static $plugin_path = '';
	public static function init() {
		self::$plugin_path = plugin_dir_path( __FILE__ );
		if ( isset( $_REQUEST['scan'] ) ) {
			echo '<pre>';
			$old = self::load();
			$new = self::scan();
			if ( isset( $_REQUEST['store'] ) ) {
				self::store( $new );
			}
			$diff = self::_diff( $old, $new );
			var_dump($diff);
			if ( isset( $_REQUEST['fix'] ) ) {
				self::fix_changes( $diff );
			}
			die();
		}
	}
	public static function scan( $directory = ABSPATH ) {
		if ( ! is_dir( $directory ) ) {
			return array();
    }
		$files = array();
		$dir = dir($directory);
		while ( false !== ( $file = $dir->read() ) ) {
			if ($file != '.' and $file != '..') {
				$filename = $directory . $file;
				$rel_filename = str_replace( ABSPATH, '', $filename );
				if ( self::_skip( $rel_filename ) ) {
					// 	var_dump( 'skipping: '.$rel_filename );
					continue;
				}
				if ( is_dir( $filename ) ) {
					$files = array_merge( $files, self::scan( $filename.'/' ) );
				} else {
					$files[ $rel_filename ] = md5_file( $filename );
				}
			}
		}
    $dir->close();
		return $files;
	}
	public static function load( $slug = 'core' ) {
		$data = file_get_contents( self::$plugin_path . "{$slug}.md5.json" );
		$data = json_decode( $data, true );
		if ( !is_array( $data ) ) {
			$data = array();
		}
		return $data;
	}
	public static function store( $data, $slug = 'core' ) {
		return file_put_contents(  self::$plugin_path . "{$slug}.md5.json", json_encode( $data ) );
		// return set_transient( "tinyi_{$slug}", $data, 30 *  DAY_IN_SECONDS );
	}
	public static function fix_changes( $diff ) {
		$version = get_bloginfo( 'version' );
		$remote = "https://core.svn.wordpress.org/tags/{$version}/";
		foreach ( $diff['modified'] as $file ) {
			$response = wp_remote_get( $remote.$file );
			if ( 200 == $response['response']['code'] ) {
				$abs_file = ABSPATH.$file;
				file_put_contents( $abs_file,  $response['body'] );
			}
		}
		foreach ( $diff['added'] as $file ) {
			$abs_file = ABSPATH.$file;
			unlink( $abs_file );
		}
	}
	private static function _skip( $filename ) {
		foreach ( self::$skip as $value ) {
			if ( fnmatch( $value, $filename ) ) {
				return true;
			}
		}
		return false;
	}
	private static function _diff( $old, $new ) {
		$result = array(
			'removed' => array(),
			'added' => array(),
			'modified' => array(),
		);
		foreach( $old as $key => $val ) {
			if ( !isset( $new[ $key ] ) ) {
				$result['removed'][] = $key;
				unset( $old[ $key ] );
			} else if( $old[ $key ] !== $new[ $key ] ) {
				$result['modified'][] = $key;
				unset( $old[ $key ] );
				unset( $new[ $key ] );
			} else {
				unset( $old[ $key ] );
				unset( $new[ $key ] );
			}
		}
		$result['added'] = array_keys( $new );
		return $result;
	}
}
