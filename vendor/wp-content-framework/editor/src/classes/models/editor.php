<?php
/**
 * WP_Framework_Editor Classes Models Editor
 *
 * @author Technote
 * @copyright Technote All Rights Reserved
 * @license http://www.opensource.org/licenses/gpl-2.0.php GNU General Public License, version 2
 * @link https://technote.space
 */

namespace WP_Framework_Editor\Classes\Models;

use WP_Framework_Core\Traits\Hook;
use WP_Framework_Core\Traits\Singleton;
use WP_Framework_Editor\Traits\Package;
use WP_Scripts;

if ( ! defined( 'WP_CONTENT_FRAMEWORK' ) ) {
	exit;
}

/**
 * Class Editor
 * @package WP_Framework_Editor\Classes\Models
 */
class Editor implements \WP_Framework_Core\Interfaces\Singleton, \WP_Framework_Core\Interfaces\Hook {

	use Singleton, Hook, Package;

	/**
	 * @var string $_cache_key
	 */
	private $_cache_key;

	/**
	 * @return bool
	 */
	protected static function is_shared_class() {
		return true;
	}

	/**
	 * @return bool
	 */
	public function is_valid_tinymce_color_picker() {
		return $this->compare_wp_version( '4.0.0', '>=' );
	}

	/**
	 * @return bool
	 */
	public function can_use_block_editor() {
		return $this->compare_wp_version( '5.0.0', '>=' );
	}

	/**
	 * @return bool
	 */
	public function is_block_editor() {
		if ( ! is_admin() ) {
			return false;
		}

		if ( $this->can_use_block_editor() ) {
			return get_current_screen()->is_block_editor();
		}

		/** @noinspection PhpDeprecationInspection */
		return function_exists( 'is_gutenberg_page' ) && is_gutenberg_page();
	}

	/**
	 * @return array
	 */
	public function get_editor_package_versions() {
		if ( ! $this->can_use_block_editor() ) {
			return [];
		}

		$cache = $this->cache_get( 'versions', null, $this->get_cache_key() );
		if ( isset( $cache ) ) {
			return $cache;
		}

		$versions = $this->get_gutenberg_package_versions();
		if ( false === $versions ) {
			$versions = $this->get_wp_editor_package_versions();
		}
		$this->cache_set( 'versions', $versions, $this->get_cache_key() );

		return $versions;
	}

	/**
	 * @param string $package
	 *
	 * @return string|false
	 */
	public function get_editor_package_version( $package ) {
		return $this->app->array->get( $this->get_editor_package_versions(), $package, false );
	}

	/**
	 * @return string
	 */
	private function get_cache_key() {
		if ( ! isset( $this->_cache_key ) ) {
			$this->_cache_key = sha1( json_encode( [
				$this->wp_version(),
				$this->get_gutenberg_version(),
			] ) );
		}

		return $this->_cache_key;
	}

	/**
	 * @return array
	 */
	public function get_wp_editor_package_versions() {
		$scripts = new WP_Scripts();
		wp_default_packages_scripts( $scripts );

		return $this->app->array->filter( $this->app->array->map( $scripts->registered, function ( $script ) {
			return $script->ver;
		} ), function ( $version, $key ) {
			return $version && $this->app->string->starts_with( $key, 'wp-' );
		} );
	}

	/**
	 * @return string
	 */
	private function get_gutenberg_file() {
		return 'gutenberg/gutenberg.php';
	}

	/**
	 * @return string
	 */
	private function get_gutenberg_absolute_path() {
		return WP_PLUGIN_DIR . DS . $this->get_gutenberg_file();
	}

	/**
	 * @return bool
	 */
	private function is_gutenberg_active() {
		return $this->app->utility->is_plugin_active( $this->get_gutenberg_file() );
	}

	/**
	 * @return string
	 */
	private function get_gutenberg_version() {
		return $this->is_gutenberg_active() ? $this->app->array->get( get_plugin_data( $this->get_gutenberg_absolute_path() ), 'Version' ) : '';
	}

	/**
	 * @return array|false
	 */
	public function get_gutenberg_package_versions() {
		if ( $this->is_gutenberg_active() ) {
			$dir = dirname( $this->get_gutenberg_absolute_path() ) . DS . 'packages';

			return $this->app->array->combine( $this->app->array->filter( $this->app->array->map( $this->app->file->dirlist( $dir ), function ( $data ) use ( $dir ) {
				$package = $dir . DS . $data['name'] . DS . 'package.json';
				if ( $this->app->file->is_readable( $package ) ) {
					return [
						'package' => "wp-{$data['name']}",
						'version' => $this->app->array->get( json_decode( $this->app->file->get_contents( $package ), true ), 'version' ),
					];
				}

				return false;
			} ), function ( $data ) {
				return false !== $data;
			} ), 'package', 'version' );
		}

		return false;
	}

	/**
	 * @param string $package
	 *
	 * @return bool
	 */
	public function is_support_editor_package( $package ) {
		return $this->app->array->exists( $this->get_editor_package_versions(), $package );
	}
}
