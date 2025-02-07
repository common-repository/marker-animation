<?php
/**
 * WP_Framework_Admin Views Admin Style Button
 *
 * @version 0.0.32
 * @author Technote
 * @copyright Technote All Rights Reserved
 * @license http://www.opensource.org/licenses/gpl-2.0.php GNU General Public License, version 2
 * @link https://technote.space
 */

use WP_Framework_Presenter\Interfaces\Presenter;

if ( ! defined( 'WP_CONTENT_FRAMEWORK' ) ) {
	return;
}
/** @var Presenter $instance */
?>
<style>
    #<?php $instance->id();?>-main-contents .button-primary {
        margin-top: 5px;
        min-width: 80px;
    }

    #<?php $instance->id();?>-main-contents .button-primary.right {
        float: right;
    }

    #<?php $instance->id();?>-main-contents .button-primary.small {
        min-width: 40px;
    }

    #<?php $instance->id();?>-main-contents .button-primary.large {
        min-width: 120px;
    }
</style>
