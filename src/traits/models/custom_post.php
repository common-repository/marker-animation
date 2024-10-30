<?php
/**
 * @author Technote
 * @copyright Technote All Rights Reserved
 * @license http://www.opensource.org/licenses/gpl-2.0.php GNU General Public License, version 2
 * @link https://technote.space/
 */

namespace Marker_Animation\Traits\Models;

use WP_Framework_Core\Traits\Helper\Data_Helper;
use WP_Framework_Core\Traits\Helper\Validate;

// @codeCoverageIgnoreStart
if ( ! defined( 'MARKER_ANIMATION' ) ) {
	exit;
}
// @codeCoverageIgnoreEnd

/**
 * Trait Custom_Post
 * @package Marker_Animation\Traits\Models
 */
trait Custom_Post {

	use \WP_Framework_Custom_Post\Traits\Custom_Post, Data_Helper, Validate;

	/**
	 * @return string
	 */
	public function get_post_type() {
		return 'ma-' . $this->get_post_type_slug();
	}

	/**
	 * @return array
	 */
	protected function get_capabilities() {
		return (array) get_post_type_object( 'post' )->cap;
	}

	/**
	 * @return string|false
	 */
	protected function get_post_type_parent() {
		return $this->app->admin->get_menu_slug();
	}
}
