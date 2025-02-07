<?php
/**
 * WP_Framework Package View
 *
 * @version 0.0.3
 * @author Technote
 * @copyright Technote All Rights Reserved
 * @license http://www.opensource.org/licenses/gpl-2.0.php GNU General Public License, version 2
 * @link https://technote.space
 */

namespace WP_Framework;

if ( ! defined( 'WP_CONTENT_FRAMEWORK' ) ) {
	exit;
}

/**
 * Class Package_View
 * @package WP_Framework
 */
class Package_View extends Package_Base {

	/**
	 * @return int
	 */
	public function get_priority() {
		return 50;
	}

	/**
	 * @return array
	 */
	public function get_configs() {
		return [
			'dashicon',
		];
	}
}
