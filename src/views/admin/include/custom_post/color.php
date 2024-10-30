<?php
/**
 * @author Technote
 * @copyright Technote All Rights Reserved
 * @license http://www.opensource.org/licenses/gpl-2.0.php GNU General Public License, version 2
 * @link https://technote.space
 */

use WP_Framework_Presenter\Traits\Presenter;

if ( ! defined( 'MARKER_ANIMATION' ) ) {
	return;
}
/** @var Presenter $instance */
/** @var array $data */
/** @var array $column */
/** @var string $name */
/** @var string $prefix */
$attr  = $instance->app->array->get( $column, 'attributes', [] );
$_data = [];
if ( ! empty( $data[ $name ] ) ) {
	$_data[ $name ] = $data[ $name ];
}
$instance->form( 'color', [
	'name'       => $prefix . $name,
	'id'         => $prefix . $name,
	'value'      => $instance->old( $prefix . $name, $_data, $name, $instance->app->array->get( $column, 'default' ) ),
	'attributes' => $attr,
], $instance->app->array->get( $column, 'args', [] ) );
