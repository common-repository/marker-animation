<?php
/**
 * WP_Framework_Custom_Post Traits Custom Post
 *
 * @author Technote
 * @copyright Technote All Rights Reserved
 * @license http://www.opensource.org/licenses/gpl-2.0.php GNU General Public License, version 2
 * @link https://technote.space
 */

namespace WP_Framework_Custom_Post\Traits;

use Closure;
use WP_Error;
use WP_Framework;
use WP_Framework_Core\Traits\Helper\Data_Helper;
use WP_Framework_Core\Traits\Hook;
use WP_Framework_Core\Traits\Singleton;
use WP_Framework_Db\Classes\Models\Query\Builder;
use WP_Framework_Presenter\Traits\Presenter;
use WP_Post;
use WP_Post_Type;
use WP_Query;

if ( ! defined( 'WP_CONTENT_FRAMEWORK' ) ) {
	exit;
}

/**
 * Trait Custom_Post
 * @package WP_Framework_Custom_Post\Traits
 * @property WP_Framework $app
 * @mixin Data_Helper
 */
trait Custom_Post {

	use Singleton, Hook, Presenter, Package;

	/**
	 * @var string $_slug
	 */
	private $_slug;

	/**
	 * @var array $_related_data
	 */
	private $_related_data = [];

	/**
	 * @var WP_Post_Type|WP_Error $_post_type_obj
	 */
	private $_post_type_obj;

	/**
	 * initialized
	 */
	protected function initialized() {
		$this->register_post_type();
	}

	/**
	 * @return mixed
	 */
	private function apply_custom_post_filters() {
		$args    = func_get_args();
		$key     = $args[0];
		$args[0] = $this->get_post_type_slug() . '-' . $key;

		return $this->apply_filters( ...$args );
	}

	/**
	 * register post type
	 */
	private function register_post_type() {
		$post_type            = $this->get_post_type();
		$this->_post_type_obj = register_post_type( $post_type, $this->get_post_type_args() );
		if ( is_wp_error( $this->_post_type_obj ) ) {
			$this->app->log( $this->_post_type_obj );

			return;
		}

		if ( $this->app->input->request( 'post_type' ) === $post_type ) {
			add_filter( "views_edit-{$post_type}", function ( $views ) {
				return $this->view_edit( $views );
			} );
			add_filter( "bulk_actions-edit-{$post_type}", function ( $actions ) {
				return $this->bulk_actions( $actions );
			} );
			add_filter( "handle_bulk_actions-edit-{$post_type}", function ( $sendback, $doaction, $post_ids ) {
				return $this->handle_bulk_actions( $sendback, $doaction, (array) $post_ids );
			}, 10, 3 );
			add_filter( "manage_edit-{$post_type}_sortable_columns", function ( $sortable_columns ) {
				return $this->manage_posts_columns( $sortable_columns, true );
			} );
		}
	}

	/**
	 * @param string|null $as
	 *
	 * @return Builder
	 */
	protected function query( $as = null ) {
		return $this->table( $this->get_related_table_name(), $as );
	}

	/**
	 * @param array $data
	 * @param array $_data
	 * @param bool $convert_name
	 *
	 * @return array
	 */
	private function _set_post( array $data, array $_data, $convert_name ) {
		unset( $data['post_type'] );
		unset( $data['post_title'] );
		unset( $data['post_content'] );
		unset( $data['post_status'] );

		foreach ( $this->get_data_field_settings() as $k => $v ) {
			$name = $convert_name ? $this->get_post_field_name( $k ) : $k;
			$this->app->input->delete_post( $name );
		}
		foreach ( $data as $k => $v ) {
			$name           = $convert_name ? $this->get_post_field_name( $k ) : $k;
			$_data[ $name ] = $v;
			$this->app->input->set_post( $name, $v );
		}

		return $_data;
	}

	/**
	 * @param array $data
	 * @param bool $convert_name
	 * @param callable $callback
	 *
	 * @return mixed
	 */
	private function _insert( array $data, $convert_name, $callback ) {
		$_data                 = [];
		$_data['post_type']    = $this->get_post_type();
		$_data['post_title']   = $this->app->array->get( $data, 'post_title', '' );
		$_data['post_content'] = $this->app->array->get( $data, 'post_content', '' );
		$_data['post_excerpt'] = $this->app->array->get( $data, 'post_excerpt', '' );
		$_data['post_status']  = $this->app->array->get( $data, 'post_status', 'publish' );
		$_data                 = $this->_set_post( $data, $_data, $convert_name );

		return $callback( $_data );
	}

	/**
	 * @param array $data
	 * @param bool $convert_name
	 * @param bool $wp_error
	 *
	 * @return array|bool|int
	 */
	public function insert( array $data, $convert_name = true, $wp_error = false ) {
		return $this->_insert( $data, $convert_name, function ( $data ) use ( $wp_error ) {
			return wp_insert_post( $data, $wp_error );
		} );
	}

	/**
	 * @param array $data
	 * @param bool $convert_name
	 *
	 * @return array
	 */
	public function validate_insert( array $data, $convert_name = true ) {
		return $this->_insert( $data, $convert_name, function ( $data ) {
			return $this->validate_input( $data );
		} );
	}

	/**
	 * @param array $data
	 * @param array $where
	 * @param bool $convert_name
	 *
	 * @return array|bool|int
	 */
	public function update( array $data, array $where, $convert_name = true ) {
		if ( empty( $where['id'] ) && empty( $where['post_id'] ) ) {
			return false;
		}
		if ( ! empty( $where['id'] ) ) {
			$d = $this->get_data( $where['id'] );
		} else {
			$d = $this->get_related_data( $where['post_id'] );
		}
		if ( empty( $d ) ) {
			return false;
		}

		$_data              = [];
		$_data['ID']        = $d['post_id'];
		$_data['post_type'] = $this->get_post_type();
		! empty( $data['post_title'] ) and $_data['post_title'] = $data['post_title'];
		! empty( $data['post_content'] ) and $_data['post_content'] = $data['post_content'];
		! empty( $data['post_status'] ) and $_data['post_status'] = $data['post_status'];
		$_data = $this->_set_post( $data, $_data, $convert_name );

		return wp_update_post( $_data );
	}

	/**
	 * @return string
	 */
	public function get_post_type_slug() {
		! isset( $this->_slug ) and $this->_slug = $this->get_file_slug();

		return $this->_slug;
	}

	/**
	 * @return string
	 */
	public function get_related_table_name() {
		return $this->apply_custom_post_filters( 'table_name', $this->get_post_type_slug() );
	}

	/**
	 * @return string
	 */
	public function get_post_type() {
		return $this->get_slug( 'post_type-' . $this->get_post_type_slug(), '-' . $this->get_post_type_slug() );
	}

	/**
	 * @return WP_Post_Type|WP_Error
	 */
	public function get_post_type_object() {
		return $this->_post_type_obj;
	}

	/**
	 * @param string $capability
	 *
	 * @return bool
	 */
	public function user_can( $capability ) {
		if ( ! ( $this->_post_type_obj instanceof WP_Post_Type ) ) {
			return false;
		}

		return ! empty( $this->_post_type_obj->cap->$capability );
	}

	/**
	 * @return array
	 */
	protected abstract function get_capabilities();

	/**
	 * @return string|false
	 */
	protected abstract function get_post_type_parent();

	/**
	 * @param array|null $capabilities
	 *
	 * @return array
	 */
	public function get_post_type_args( $capabilities = null ) {
		$save = false;
		if ( ! isset( $capabilities ) ) {
			$cache = $this->cache_get_common( 'post_type_args' );
			if ( is_array( $cache ) ) {
				return $cache;
			}
			$save         = true;
			$capabilities = $this->get_capabilities();
		}

		$args = $this->apply_custom_post_filters( 'args', [
			'labels'              => $this->get_post_type_labels(),
			'description'         => '',
			'public'              => false,
			'show_ui'             => true,
			'has_archive'         => false,
			'show_in_menu'        => $this->get_post_type_parent(),
			'exclude_from_search' => true,
			'capability_type'     => $this->get_post_type_capability_type(),
			'capabilities'        => $capabilities,
			'map_meta_cap'        => true,
			'hierarchical'        => false,
			'rewrite'             => [ 'slug' => $this->get_post_type_slug(), 'with_front' => false ],
			'query_var'           => true,
			'menu_icon'           => $this->get_post_type_menu_icon(),
			'supports'            => $this->get_post_type_supports(),
			'menu_position'       => $this->get_post_type_position(),
		] );

		if ( $save ) {
			$this->cache_set_common( 'post_type_args', $args );
		}

		return $args;
	}

	/**
	 * @return array
	 */
	public function get_post_type_labels() {
		$plural_name = $this->translate( $this->get_post_type_plural_name() );
		$single_name = $this->translate( $this->get_post_type_single_name() );

		return $this->apply_custom_post_filters( 'labels', [
			'name'               => $plural_name,
			'singular_name'      => $single_name,
			'menu_name'          => $plural_name,
			'all_items'          => sprintf( $this->translate( 'All %s' ), $plural_name ),
			'add_new'            => $this->translate( 'Add New' ),
			'add_new_item'       => sprintf( $this->translate( 'Add New %s' ), $single_name ),
			'edit_item'          => sprintf( $this->translate( 'Edit %s' ), $single_name ),
			'search_items'       => sprintf( $this->translate( 'Search %s' ), $plural_name ),
			'not_found'          => sprintf( $this->translate( 'No %s found.' ), $plural_name ),
			'not_found_in_trash' => sprintf( $this->translate( 'No %s found in Trash.' ), $plural_name ),
		] );
	}

	/**
	 * @return string
	 */
	public function get_post_type_single_name() {
		return $this->apply_custom_post_filters( 'single_name', ucwords( str_replace( '_', ' ', $this->get_post_type_slug() ) ) );
	}

	/**
	 * @return string
	 */
	public function get_post_type_plural_name() {
		return $this->apply_custom_post_filters( 'plural_name', $this->get_post_type_single_name() . 's' );
	}

	/**
	 * @return string|array
	 */
	public function get_post_type_capability_type() {
		return $this->apply_custom_post_filters( 'capability_type', $this->get_post_type_slug() );
	}

	/**
	 * @return array
	 */
	public function get_post_type_supports() {
		return $this->apply_custom_post_filters( 'supports', [
			'title',
		] );
	}

	/**
	 * @return string
	 */
	public function get_post_type_menu_icon() {
		return $this->apply_custom_post_filters( 'menu_icon', 'dashicons-admin-plugins' );
	}

	/**
	 * @return int|null
	 */
	public function get_post_type_position() {
		return $this->apply_custom_post_filters( 'position', 5 );
	}

	/**
	 * @param string $search
	 * @param WP_Query $wp_query
	 *
	 * @return string
	 */
	public function posts_search(
		/** @noinspection PhpUnusedParameterInspection */
		$search, WP_Query $wp_query
	) {
		if ( ! $wp_query->is_search() || ! $wp_query->is_main_query() || ! is_admin() || empty( $wp_query->query_vars['search_terms'] ) ) {
			return $search;
		}

		$fields = $this->get_search_fields();
		if ( empty( $fields ) ) {
			return $search;
		}

		$exclusion_prefix = apply_filters( 'wp_query_search_exclusion_prefix', '-' );
		$search           = '';
		$q                = $wp_query->query_vars;
		$n                = ! empty( $q['exact'] ) ? '' : '%';
		$table            = $this->app->db->get_table( $this->get_related_table_name() );

		foreach ( $q['search_terms'] as $term ) {
			$exclude = $exclusion_prefix && ( $exclusion_prefix === substr( $term, 0, 1 ) );
			if ( $exclude ) {
				$like_op  = 'NOT LIKE';
				$andor_op = 'AND';
				$term     = substr( $term, 1 );
			} else {
				$like_op  = 'LIKE';
				$andor_op = 'OR';
			}

			$conditions = [];
			$fields     = array_map( function ( $field ) use ( $table ) {
				return "{$table}.{$field}";
			}, $fields );
			$fields[]   = "{$this->get_wp_table('posts')}.post_title";
			$fields[]   = "{$this->get_wp_table('posts')}.post_excerpt";
			$fields[]   = "{$this->get_wp_table('posts')}.post_content";
			$like       = $n . $this->wpdb()->esc_like( $term ) . $n;
			foreach ( $fields as $field ) {
				$conditions[] = $this->wpdb()->prepare( "({$field} $like_op %s)", $like );
			}
			$conditions = implode( " {$andor_op} ", $conditions );
			$search     .= " AND ({$conditions})";
		}

		return $search;
	}

	/**
	 * @return array
	 */
	protected function get_search_fields() {
		return [];
	}

	/**
	 * @param string $join
	 * @param WP_Query|string $wp_query
	 *
	 * @return string
	 */
	public function posts_join(
		/** @noinspection PhpUnusedParameterInspection */
		$join, $wp_query
	) {
		$table = $this->app->db->get_table( $this->get_related_table_name() );
		$join  .= " INNER JOIN {$table} ON {$table}.post_id = {$this->get_wp_table('posts')}.ID ";

		return $join;
	}

	/**
	 * @param WP_Query $wp_query
	 */
	public function setup_posts_orderby( $wp_query ) {
		if ( method_exists( $this, 'pre_get_posts' ) ) {
			return;
		}

		$orderby = $wp_query->get( 'orderby' );
		$table   = $this->app->db->get_table( $this->get_related_table_name() );

		if ( empty( $orderby ) ) {
			$_orderby_list = [];
			foreach ( $this->get_manage_posts_columns() as $k => $v ) {
				if ( ! is_array( $v ) || empty( $v['sortable'] ) ) {
					continue;
				}
				if ( ! empty( $v['default_sort'] ) ) {
					$_priority                     = $this->app->array->get( $v, 'default_sort_priority', 10 );
					$_orderby                      = $this->app->array->get( $v, 'orderby', "{$table}.{$k}" );
					$_order                        = $this->app->array->get( $v, 'desc', false ) ? 'DESC' : 'ASC';
					$_orderby_list[ $_priority ][] = "{$_orderby} {$_order}";
				}
			}
			ksort( $_orderby_list );
			$_orderby_list   = $this->app->array->flatten( $_orderby_list );
			$_orderby_list[] = "{$table}.updated_at DESC";

			$func = function (
				/** @noinspection PhpUnusedParameterInspection */
				$orderby, $wp_query
			) use ( &$func, $_orderby_list ) {
				/** @var string $orderby */
				/** @var WP_Query $wp_query */
				remove_filter( 'posts_orderby', $func );

				$_orderby_list[] = $orderby;

				return implode( ', ', $_orderby_list );
			};
			add_filter( 'posts_orderby', $func, 10, 2 );
		} else {
			foreach ( $this->get_manage_posts_columns() as $k => $v ) {
				if ( ! is_array( $v ) || empty( $v['sortable'] ) ) {
					continue;
				}
				$key = $this->get_post_type() . '-' . $k;
				if ( $key === $orderby ) {
					$_order = $wp_query->get( 'order', 'ASC' );
					$func   = function (
						/** @noinspection PhpUnusedParameterInspection */
						$orderby, $wp_query
					) use ( &$func, $k, $v, $table, $_order ) {
						/** @var string $orderby */
						/** @var WP_Query $wp_query */
						remove_filter( 'posts_orderby', $func );
						$_orderby = $this->app->array->get( $v, 'orderby', "{$table}.{$k}" );

						return "{$_orderby} {$_order}, {$orderby}";
					};
					add_filter( 'posts_orderby', $func, 10, 2 );
					break;
				}
			}
		}
	}

	/**
	 * @return bool
	 */
	public function is_support_io() {
		return $this->compare_wp_version( '4.7', '>=' ) && ! empty( $this->app->get_config( 'io', $this->get_post_type_slug() ) ) && $this->user_can( 'edit_others_posts' ) && $this->is_valid_export_post_status();
	}

	/**
	 * @return bool
	 */
	private function is_valid_export_post_status() {
		return ! in_array( $this->app->input->request( 'post_status', 'all' ), $this->get_exclude_from_search_post_status() );
	}

	/**
	 * @param array $actions
	 * @param WP_Post $post
	 *
	 * @return array
	 */
	public function post_row_actions( array $actions, WP_Post $post ) {
		unset( $actions['inline hide-if-no-js'] );
		unset( $actions['edit'] );
		unset( $actions['clone'] );
		unset( $actions['edit_as_new_draft'] );
		if ( ! $this->user_can( 'delete_posts' ) ) {
			unset( $actions['trash'] );
		}

		$row_actions = $this->get_post_row_actions();
		if ( $this->is_support_io() ) {
			$row_actions['export'] = 'Export as JSON';
		}
		foreach ( $row_actions as $key => $value ) {
			$actions[ $key ] = $this->url( wp_nonce_url( add_query_arg( [
				'action'    => $key,
				'post_type' => $this->get_post_type(),
				'ids'       => $post->ID,
			], admin_url( 'edit.php' ) ), 'bulk-posts' ), $value, true, false, [], false );
		}

		return $this->filter_post_row_actions( $actions, $post );
	}

	/**
	 * @return array
	 */
	protected function get_post_row_actions() {
		return [];
	}

	/**
	 * @param array $actions
	 * @param WP_Post $post
	 *
	 * @return array
	 */
	protected function filter_post_row_actions(
		/** @noinspection PhpUnusedParameterInspection */
		array $actions, WP_Post $post
	) {
		return $actions;
	}

	/**
	 * @param array $views
	 *
	 * @return array
	 */
	protected function view_edit( array $views ) {
		unset( $views['mine'] );
		unset( $views['publish'] );

		return $this->filter_view_edit( $views );
	}

	/**
	 * @param array $views
	 *
	 * @return array
	 */
	protected function filter_view_edit( array $views ) {
		return $views;
	}

	/**
	 * @param array $actions
	 *
	 * @return array
	 */
	protected function bulk_actions( array $actions ) {
		unset( $actions['edit'] );
		if ( $this->is_support_io() ) {
			$actions['export'] = $this->translate( 'Export as JSON' );
		}

		return $this->filter_bulk_actions( $actions );
	}

	/**
	 * @param array $actions
	 *
	 * @return array
	 */
	protected function filter_bulk_actions( array $actions ) {
		return $actions;
	}

	/**
	 * @param string $sendback
	 * @param string $doaction
	 * @param array $post_ids
	 *
	 * @return string
	 */
	protected function handle_bulk_actions(
		/** @noinspection PhpUnusedParameterInspection */
		$sendback, $doaction, array $post_ids
	) {
		if ( ! empty( $post_ids ) && 'export' === $doaction && $this->is_support_io() ) {
			$this->export( $post_ids );
		}

		return $this->filter_handle_bulk_actions( $sendback, $doaction, $post_ids );
	}

	/**
	 * @param string $sendback
	 * @param string $doaction
	 * @param array $post_ids
	 *
	 * @return string
	 */
	protected function filter_handle_bulk_actions(
		/** @noinspection PhpUnusedParameterInspection */
		$sendback, $doaction, array $post_ids
	) {
		return $sendback;
	}

	/**
	 * @param array $post_ids
	 */
	private function export( $post_ids ) {
		$export_data = [];
		$setting     = $this->app->get_config( 'io', $this->get_post_type_slug() );
		foreach (
			$this->app->array->get( $this->get_list_data( function ( $query ) use ( $post_ids ) {
				/** @var Builder $query */
				$query->where_in( 'p.ID', $post_ids );
			}, false ), 'data' ) as $d
		) {
			$data = [];
			if ( in_array( 'title', $this->get_post_type_supports() ) ) {
				$data['post_title'] = $d['post']->post_title;
			}
			if ( in_array( 'editor', $this->get_post_type_supports() ) ) {
				$data['post_content'] = $d['post']->post_content;
			}
			if ( in_array( 'excerpt', $this->get_post_type_supports() ) ) {
				$data['post_excerpt'] = $d['post']->post_excerpt;
			}
			foreach ( $setting as $k => $v ) {
				if ( ! is_array( $v ) ) {
					$k = $v;
					$v = [];
				}
				if ( ! array_key_exists( $k, $d ) ) {
					continue;
				}
				if ( isset( $v['export'] ) && $this->is_closure( $v['export'] ) ) {
					$data[ $k ] = $this->call_closure( $v['export'], $d[ $k ] );
				} else {
					$data[ $k ] = $d[ $k ];
				}
			}
			$export_data[] = $data;
		}

		$this->output_json_file( [
			'plugin' => $this->app->plugin_name,
			'table'  => $this->get_related_table_name(),
			'data'   => $export_data,
		] );
		exit;
	}

	/**
	 * @param array $data
	 */
	private function output_json_file( $data ) {
		$json = @json_encode( $data );
		header( 'Content-Type: application/json' );
		header( 'Content-Length: ' . strlen( $json ) );
		header( 'Content-Disposition: attachment; filename="' . $this->get_export_filename() . '"' );
		header( 'Pragma: no-cache' );
		header( 'Cache-Control: no-cache' );
		$this->h( $json, false, true, false );
	}

	/**
	 * @return string
	 */
	private function get_export_filename() {
		return $this->app->string->replace_time( $this->apply_filters( 'export_filename', 'export${Y}${m}${d}${H}${i}${s}' ) ) . '.json';
	}

	/**
	 * @param mixed $data
	 *
	 * @return array {
	 *  int $result
	 *  string $message
	 *  int $success
	 *  int $fail
	 * }
	 */
	public function import( $data ) {
		if ( ! $this->is_support_io() ) {
			return [ 0, $this->translate( 'JSON Import is not supported.' ), 0, 0 ];
		}
		if ( empty( $data ) ) {
			return [ 0, $this->translate( 'Invalid JSON file' ), 0, 0 ];
		}

		$json = @json_decode( $data, true );
		if ( empty( $json ) || empty( $json['plugin'] ) || empty( $json['table'] ) || ! isset( $json['data'] ) || ! is_array( $json['data'] ) ) {
			return [ 0, $this->translate( 'Invalid JSON file' ), 0, 0 ];
		}

		if ( $json['plugin'] !== $this->app->plugin_name || $json['table'] !== $this->get_related_table_name() ) {
			return [ 0, $this->translate( 'Invalid JSON file' ), 0, 0 ];
		}

		$success = 0;
		$failed  = [];
		foreach ( $json['data'] as $item ) {
			$validation = $this->validate_insert( $item );
			if ( empty( $validation ) ) {
				$this->insert( $item );
				$success++;
			} else {
				$failed[] = [ $item, $validation ];
			}
		}
		$fail  = count( $failed );
		$total = $success + $fail;

		$this->do_action( 'custom_post_imported', $this->get_post_type(), $total, $success, $fail );

		return [
			1,
			$this->get_import_result( [
				'total'   => $total,
				'success' => $success,
				'failed'  => $failed,
			] ),
			$success,
			$fail,
		];
	}

	/**
	 * @param array $data
	 *
	 * @return string
	 */
	private function get_import_result( array $data ) {
		return $this->get_view( 'admin/import_custom_post', $data );
	}

	/**
	 * @param array $columns
	 * @param bool $sortable
	 *
	 * @return array
	 */
	public function manage_posts_columns( array $columns, $sortable = false ) {
		if ( ! $sortable ) {
			$title = $this->get_post_column_title();
			! isset( $title ) and isset( $columns['title'] ) and $title = $columns['title'];
			$date = isset( $columns['date'] ) ? $columns['date'] : null;
			unset( $columns['date'] );
		}

		$columns = $this->pre_filter_posts_columns( $columns, $sortable );
		$columns = $this->post_filter_posts_columns( $columns, $sortable );

		if ( ! $sortable ) {
			isset( $title ) and $columns['title'] = $title;
			isset( $date ) and $columns['date'] = $date;
		}

		return $columns;
	}

	/**
	 * @return null|string
	 */
	protected function get_post_column_title() {
		return null;
	}

	/**
	 * @param array $columns
	 * @param bool $sortable
	 *
	 * @return array
	 */
	protected function pre_filter_posts_columns( array $columns, $sortable = false ) {
		// for cocoon
		unset( $columns['thumbnail'] );
		unset( $columns['memo'] );
		unset( $columns['word-count'] );

		$_columns = $this->app->db->get_columns( $this->get_related_table_name() );
		foreach ( $this->get_manage_posts_columns() as $k => $v ) {
			if ( $sortable && ( ! is_array( $v ) || empty( $v['sortable'] ) ) ) {
				continue;
			}
			if ( is_array( $v ) && ! empty( $v['hide'] ) ) {
				continue;
			}

			$key = $this->get_post_type() . '-' . $k;
			if ( $sortable ) {
				$order = ! empty( $v['desc'] );
				! empty( $v['default_sort'] ) and $order = ! $order;
				$columns[ $key ] = [ $key, $order ];
				continue;
			}

			$name = isset( $_columns[ $k ] ) ? $this->table_column_to_name( $k, $_columns ) : '';
			if ( is_array( $v ) ) {
				if ( isset( $v['name'] ) ) {
					$name = $v['name'];
				}
				$columns[ $key ] = $name;
			} elseif ( ! $sortable ) {
				$columns[ $key ] = empty( $name ) ? $k : $name;
			}
		}

		return $columns;
	}

	/**
	 * @param array $columns
	 * @param bool $sortable
	 *
	 * @return array
	 */
	protected function post_filter_posts_columns(
		/** @noinspection PhpUnusedParameterInspection */
		array $columns, $sortable = false
	) {
		return $columns;
	}

	/**
	 * @return array
	 */
	protected function get_manage_posts_columns() {
		return [];
	}

	/**
	 * @param string $column_name
	 * @param WP_Post $post
	 */
	public function manage_posts_custom_column(
		/** @noinspection PhpUnusedParameterInspection */
		$column_name, WP_Post $post
	) {
		$data = $this->get_related_data( $post->ID );
		if ( empty( $data ) ) {
			return;
		}
		$this->manage_posts_column( $column_name, $post, $data );
	}

	/**
	 * @param string $column_name
	 * @param WP_Post $post
	 * @param array $data
	 */
	protected function manage_posts_column(
		/** @noinspection PhpUnusedParameterInspection */
		$column_name, WP_Post $post, array $data
	) {
		foreach ( $this->get_manage_posts_columns() as $k => $v ) {
			$key = $this->get_post_type() . '-' . $k;
			if ( $column_name === $key ) {
				$value  = isset( $data[ $k ] ) ? $data[ $k ] : '';
				$escape = true;

				if ( is_array( $v ) ) {
					if ( isset( $v['callback'] ) && $this->is_closure( $v['callback'] ) ) {
						$value = call_user_func( $v['callback'], $value, $data, $post );
					} elseif ( isset( $v['value'] ) ) {
						$value .= $v['value'];
					}
					if ( isset( $v['unescape'] ) ) {
						$escape = false;
					}
				} elseif ( $this->is_closure( $v ) ) {
					$value = $this->call_closure( $v, $value, $data, $post );
				} else {
					$value .= $v;
				}
				$this->h( $value, false, true, $escape );

				break;
			}
		}
	}

	/**
	 * @param array $data
	 * @param WP_Post $post
	 *
	 * @return array
	 */
	protected function set_post_data( array $data, WP_Post $post ) {
		$data['post_title'] = $post->post_title;
		$data['post']       = $post;
		if ( $this->user_can( 'read_post' ) ) {
			$data['edit_link'] = get_edit_post_link( $post->ID );
		}

		foreach ( $this->get_data_field_settings() as $k => $v ) {
			$data[ $k ] = $this->sanitize_input( $this->app->array->get( $data, $k ), $v['type'], false, $v['nullable'] );
		}

		return $data;
	}

	/**
	 * @param int $post_id
	 *
	 * @return array|false
	 */
	public function get_related_data( $post_id ) {
		if ( ! isset( $this->_related_data[ $post_id ] ) ) {
			$data = $this->query()->where( 'post_id', $post_id )->row();
			if ( empty( $data ) ) {
				$data = false;
			} else {
				$post = get_post( $post_id );
				if ( empty( $post ) || $post->post_type != $this->get_post_type() ) {
					$data = false;
				} else {
					$data = $this->filter_item( $this->set_post_data( $data, $post ) );
				}
			}

			$this->_related_data[ $post_id ] = $data;
		}

		return $this->_related_data[ $post_id ];
	}

	/**
	 * @param int $id
	 * @param bool $is_valid
	 *
	 * @return array|false
	 */
	public function get_data( $id, $is_valid = true ) {
		$data = $this->query()->find( $id );
		if ( empty( $data ) ) {
			return false;
		}
		$post_id = $data['post_id'];
		$post    = get_post( $post_id );
		if ( empty( $post ) || $post->post_type != $this->get_post_type() || ( $is_valid && $post->post_status !== 'publish' ) ) {
			return false;
		}

		return $this->filter_item( $this->set_post_data( $data, $post ) );
	}

	/**
	 * @param Closure|null $callback
	 * @param bool $is_valid
	 * @param int|null $per_page
	 * @param int $page
	 *
	 * @return array
	 */
	public function get_list_data( $callback = null, $is_valid = true, $per_page = null, $page = 1 ) {
		$page  = max( 1, $page );
		$query = $this->query( 't' )->alias_join_wp( 'posts', 'p', 'p.ID', 't.post_id' );
		if ( $is_valid ) {
			$query->where( 'p.post_status', 'publish' );
		}
		$exclude = $this->get_exclude_from_search_post_status();
		if ( ! empty( $exclude ) ) {
			$query->where_not_in( 'p.post_status', $exclude );
		}
		$this->call_if_closure( $callback, $query );
		$total      = $query->count();
		$per_page   = isset( $per_page ) && $per_page > 0 ? (int) $per_page : 0;
		$total_page = $per_page > 0 ? ceil( $total / $per_page ) : 1;
		$page       = min( $total_page, $page );
		$offset     = $per_page > 0 ? $per_page * ( $page - 1 ) : 0;
		if ( $offset > 0 ) {
			$query->offset( $offset );
		}
		if ( $per_page > 0 ) {
			$query->limit( $per_page );
		}
		$list = $query->get();
		if ( empty( $list ) ) {
			return [
				'total'      => 0,
				'total_page' => 0,
				'page'       => $page,
				'data'       => [],
			];
		}

		$post_ids = $this->app->array->pluck( $list, 'post_id' );
		$posts    = get_posts( [
			'include'     => $post_ids,
			'post_type'   => $this->get_post_type(),
			'post_status' => 'any',
		] );
		$posts    = $this->app->array->combine( $posts, 'ID' );

		return [
			'total'      => $total,
			'total_page' => $total_page,
			'page'       => $page,
			'data'       => array_map( function ( $d ) use ( $posts ) {
				return $this->filter_item( $this->set_post_data( $d, $posts[ $d['post_id'] ] ) );
			}, $list ),
		];
	}

	/**
	 * @param int $per_page
	 * @param int $page
	 * @param Closure|null $callback
	 * @param bool $is_valid
	 *
	 * @return array
	 */
	public function pagination( $per_page, $page, $callback = null, $is_valid = true ) {
		return $this->get_list_data( $callback, $is_valid, $per_page, $page );
	}

	/**
	 * @param bool $only_publish
	 *
	 * @return int
	 */
	public function count( $only_publish = false ) {
		$query = $this->query( 't' )->alias_join_wp( 'posts', 'p', 'p.ID', 't.post_id' );
		if ( $only_publish ) {
			$query->where( 'p.post_status', 'publish' );
		}

		return $query->count();
	}

	/**
	 * @param bool $only_publish
	 *
	 * @return bool
	 */
	public function is_empty( $only_publish = false ) {
		$query = $this->query( 't' )->alias_join_wp( 'posts', 'p', 'p.ID', 't.post_id' );
		if ( $only_publish ) {
			$query->where( 'p.post_status', 'publish' );
		}

		return ! $query->exists();
	}

	/**
	 * @param array $d
	 *
	 * @return array
	 */
	protected function filter_item( array $d ) {
		return $d;
	}

	/**
	 * @param array $params
	 * @param array $where
	 * @param WP_Post $post
	 * @param bool $update
	 *
	 * @return int|false
	 */
	public function update_data( array $params, array $where, WP_Post $post, $update ) {
		$params = array_merge( $params, $this->get_update_data_params( $post, $update ) );
		list( $params, $where ) = $this->update_misc( $params, $where, $post, $update );

		return $this->query()->update_or_insert( $where, $params );
	}

	/**
	 * @param int $post_id
	 * @param WP_Post $post
	 * @param array $old
	 * @param array $new
	 */
	public function data_updated( $post_id, WP_Post $post, array $old, array $new ) {

	}

	/**
	 * @param int $post_id
	 * @param WP_Post $post
	 * @param array $data
	 */
	public function data_inserted( $post_id, WP_Post $post, array $data ) {

	}

	/**
	 * @param array $params
	 * @param array $where
	 * @param WP_Post $post
	 * @param bool $update
	 *
	 * @return array
	 */
	protected function update_misc(
		/** @noinspection PhpUnusedParameterInspection */
		array $params, array $where, WP_Post $post, $update
	) {
		return [ $params, $where ];
	}

	/**
	 * @param WP_Post $post
	 * @param bool $update
	 *
	 * @return array
	 */
	public function get_update_data_params(
		/** @noinspection PhpUnusedParameterInspection */
		WP_Post $post, $update
	) {
		$params = [];
		foreach ( $this->get_data_field_settings() as $k => $v ) {
			$is_bool  = 'bool' === $v['type'];
			$not_sent = null === $this->app->input->post( $this->get_post_field_name( $k ) );
			if ( $is_bool && $not_sent ) {
				if ( $v['nullable'] ) {
					if ( $update ) {
						continue;
					}
					$params[ $k ] = null;
				} else {
					$params[ $k ] = 0;
				}
			} else {
				$params[ $k ] = $this->get_post_field( $k, $update || ! $v['required'] ? null : $v['default'], null, $v, true, $update );
				$params[ $k ] = $this->sanitize_input( $params[ $k ], $v['type'], ! $update && $v['unset_if_null'], $v['nullable'], $update );

				if ( ! isset( $params[ $k ] ) ) {
					if ( ! $not_sent ) {
						if ( $v['unset_if_null'] ) {
							$params[ $k ] = $v['default'];
						} else {
							$params[ $k ] = null;
						}
					} else {
						if ( $update ) {
							unset( $params[ $k ] );
						} else {
							if ( $v['unset_if_null'] ) {
								unset( $params[ $k ] );
							}
						}
					}
				}
			}
		}

		return $params;
	}

	/**
	 * @param int $post_id
	 * @param WP_Post $post
	 */
	public function untrash_post( $post_id, WP_Post $post ) {

	}

	/**
	 * @param int $post_id
	 */
	public function trash_post( $post_id ) {

	}

	/**
	 * @param int $post_id
	 *
	 * @return int|false
	 */
	public function delete_data( $post_id ) {
		$this->delete_misc( $post_id );

		return $this->query()->where( 'post_id', $post_id )->delete();
	}

	/**
	 * @param int $post_id
	 */
	protected function delete_misc( $post_id ) {

	}

	/**
	 * @return string
	 */
	public function get_post_field_name_prefix() {
		return $this->apply_custom_post_filters( 'post_field_name_prefix', $this->get_slug( 'post_field_name_prefix' ) );
	}

	/**
	 * @param string $key
	 *
	 * @return string
	 */
	public function get_post_field_name( $key ) {
		return $this->get_post_field_name_prefix() . $key;
	}

	/**
	 * @param string $name
	 *
	 * @return string
	 */
	public function get_original_field_name( $name ) {
		return preg_replace( '/\A' . preg_quote( $this->get_post_field_name_prefix(), '/' ) . '/', '', $name );
	}

	/**
	 * @param string $key
	 * @param array $post_array
	 *
	 * @return mixed
	 */
	protected function get_validation_var( $key, array $post_array ) {
		return $this->app->array->get( $post_array, $this->get_post_field_name( $key ) );
	}

	/**
	 * @param string $key
	 * @param mixed $default
	 * @param array|null $post_array
	 * @param array $setting
	 * @param bool $filter
	 * @param bool $update
	 *
	 * @return mixed
	 */
	protected function get_post_field( $key, $default = null, $post_array = null, array $setting = [], $filter = true, $update = false ) {
		if ( isset( $post_array ) ) {
			$value = $this->app->array->get( $post_array, $this->get_post_field_name( $key ), $default );
		} else {
			$value = $this->app->input->post( $this->get_post_field_name( $key ), $default );
		}
		if ( ! $update && 'bool' === $this->app->array->get( $setting, 'type' ) && (string) $value === '' ) {
			$value = null;
		}

		if ( ! $filter ) {
			return $value;
		}

		return $this->filter_post_field( $key, $value, $default, $post_array );
	}

	/**
	 * @param string $key
	 * @param mixed $value
	 * @param mixed $default
	 * @param array|null $post_array
	 *
	 * @return mixed
	 */
	protected function filter_post_field(
		/** @noinspection PhpUnusedParameterInspection */
		$key, $value, $default, $post_array
	) {
		return $value;
	}

	/**
	 * @return array
	 */
	public function get_data_field_settings() {
		$columns = $this->app->db->get_columns( $this->get_related_table_name() );
		unset( $columns['id'] );
		$columns = $this->app->array->combine( $columns, 'name' );
		unset( $columns['post_id'] );
		unset( $columns['created_at'] );
		unset( $columns['created_by'] );
		unset( $columns['updated_at'] );
		unset( $columns['updated_by'] );
		unset( $columns['deleted_at'] );
		unset( $columns['deleted_by'] );
		$prior_default = $this->app->get_config( 'config', 'prior_default' );
		foreach ( $columns as $k => $v ) {
			$type                           = $this->app->utility->parse_db_type( $v['type'], true );
			$columns[ $k ]['default']       = isset( $v['default'] ) ? $v['default'] : ( 'string' === $type || 'text' === $type ? '' : 0 );
			$columns[ $k ]['type']          = $type;
			$columns[ $k ]['nullable']      = ! isset( $v['null'] ) || ! empty( $v['null'] );
			$columns[ $k ]['required']      = ! isset( $v['default'] ) && ! $columns[ $k ]['nullable'] && 'bool' !== $type;
			$columns[ $k ]['unset_if_null'] = ! $columns[ $k ]['nullable'] && isset( $v['default'] );
			if ( $columns[ $k ]['nullable'] && isset( $v['default'] ) and ( $prior_default || ! empty( $v['prior_default'] ) ) ) {
				$columns[ $k ]['unset_if_null'] = true;
			}
		}

		return $this->filter_data_field_settings( $columns );
	}

	/**
	 * @param array $columns
	 *
	 * @return array
	 */
	protected function filter_data_field_settings( array $columns ) {
		return $columns;
	}

	/**
	 * @param WP_Post $post
	 */
	public function output_edit_form( WP_Post $post ) {
		$params = $this->get_edit_form_params( $post );
		$this->before_output_edit_form( $post, $params );
		$this->add_style_view( 'admin/style/custom_post', $params );
		$this->add_style_view( 'admin/style/custom_post/' . $this->get_post_type_slug(), $params );
		$this->add_script_view( 'admin/script/custom_post', $params );
		$this->add_script_view( 'admin/script/custom_post/' . $this->get_post_type_slug(), $params );
		if ( ! $this->get_view( 'admin/custom_post/' . $this->get_post_type_slug(), $params, true, false ) ) {
			$columns = $this->app->array->pluck( $params['columns'], 'is_user_defined' );
			unset( $columns['post_id'] );
			if ( ! empty( array_filter( $columns ) ) ) {
				$this->get_view( 'admin/custom_post', $params, true, false );
			}
		}
		$this->after_output_edit_form( $post, $params );
	}

	/**
	 * @param WP_Post $post
	 * @param array $params
	 */
	protected function before_output_edit_form( WP_Post $post, array $params ) {

	}

	/**
	 * @param WP_Post $post
	 * @param array $params
	 */
	protected function after_output_edit_form( WP_Post $post, array $params ) {

	}

	/**
	 * @param WP_Post $post
	 */
	public function output_after_editor( WP_Post $post ) {

	}

	/**
	 * @param WP_Post $post
	 *
	 * @return array
	 */
	protected function get_edit_form_params( WP_Post $post ) {
		return $this->filter_edit_form_params( [
			'post'    => $post,
			'data'    => $this->get_related_data( $post->ID ),
			'prefix'  => $this->get_post_field_name_prefix(),
			'columns' => $this->filter_table_columns( $this->get_table_columns() ),
		], $post );
	}

	/**
	 * @return array
	 */
	private function get_table_columns() {
		return $this->app->array->map( $this->app->db->get_columns( $this->get_related_table_name() ), function ( $d ) {
			$d['form_type'] = $this->get_form_by_type( $d['type'] );
			$d['required']  = ! isset( $d['default'] ) && isset( $d['null'] ) && empty( $d['null'] );

			return $d;
		} );
	}

	/**
	 * @param array $columns
	 *
	 * @return array
	 */
	protected function filter_table_columns( array $columns ) {
		return $columns;
	}

	/**
	 * @param array $params
	 * @param WP_Post $post
	 *
	 * @return array
	 */
	protected function filter_edit_form_params(
		/** @noinspection PhpUnusedParameterInspection */
		array $params, WP_Post $post
	) {
		return $params;
	}

	/**
	 * @param array|null $post_array
	 *
	 * @return array
	 */
	public function validate_input( $post_array = null ) {
		! isset( $post_array ) and $post_array = $this->app->input->post();
		$errors = [];
		foreach ( $this->get_data_field_settings() as $k => $v ) {
			$param    = $this->get_post_field( $k, null, $post_array, $v );
			$param    = $this->sanitize_input( $param, $v['type'], $v['unset_if_null'], $v['nullable'] );
			$validate = $this->validate( $param, $v );
			if ( $validate instanceof WP_Error ) {
				$errors[ $k ][] = $validate->get_error_message();
			}
		}

		if ( $this->validate_post_title() && in_array( 'title', $this->get_post_type_supports() ) ) {
			if ( ! isset( $post_array['post_title'] ) || '' === trim( $post_array['post_title'] ) ) {
				$errors['post_title'][] = $this->translate( 'Value is required.' );
			}
		}

		return $this->filter_validate_input( $errors, $post_array );
	}

	/**
	 * @return bool
	 */
	protected function validate_post_title() {
		return true;
	}

	/**
	 * @param array $errors
	 * @param array $post_array
	 *
	 * @return array
	 */
	protected function filter_validate_input(
		/** @noinspection PhpUnusedParameterInspection */
		array $errors, array $post_array
	) {
		return $errors;
	}

	/**
	 * @param array $data
	 * @param array $post_array
	 *
	 * @return array
	 */
	public function filter_post_data(
		/** @noinspection PhpUnusedParameterInspection */
		array $data, array $post_array
	) {
		return $data;
	}

	/**
	 * setup list
	 */
	public function setup_list() {

	}

	/**
	 * setup page
	 */
	public function setup_page() {

	}

	/**
	 * @param string $key
	 * @param array $errors
	 *
	 * @return array
	 */
	public function get_error_messages(
		/** @noinspection PhpUnusedParameterInspection */
		$key, array $errors
	) {
		$columns = $this->app->db->get_columns( $this->get_related_table_name() );
		unset( $columns['id'] );
		$columns = $this->app->array->combine( $columns, 'name' );

		return array_map( function ( $d ) use ( $key, $columns ) {
			$key = $this->table_column_to_name( $key, $columns );

			return "$d: [{$key}]";
		}, $errors );
	}

	/**
	 * @param string $key
	 * @param array $columns
	 *
	 * @return string
	 */
	protected function table_column_to_name( $key, array $columns ) {
		$name = $this->get_table_column_name( $key );
		if ( isset( $name ) ) {
			return $name;
		}

		return isset( $columns[ $key ]['comment'] ) ? $columns[ $key ]['comment'] : $key;
	}

	/**
	 * @param string $key
	 *
	 * @return null|string
	 */
	protected function get_table_column_name(
		/** @noinspection PhpUnusedParameterInspection */
		$key
	) {
		return null;
	}

	/**
	 * @param string $str
	 *
	 * @return string
	 */
	protected function replace_set_param_variable( $str ) {
		return preg_replace( '#\$\{([\.\w]+)\}#', '<span class="set_param" data-set_param="${1}"/>', $str );
	}

	/**
	 * @return string
	 */
	public function get_post_type_link() {
		return admin_url( 'edit.php?post_type=' . $this->get_post_type() );
	}

	/**
	 * @param int $post_id
	 *
	 * @return string
	 */
	public function get_edit_post_link( $post_id ) {
		if ( ! $post = get_post( $post_id ) ) {
			return $this->get_post_type_link();
		}
		$post_type_object = get_post_type_object( $post->post_type );
		if ( ! $post_type_object ) {
			return $this->get_post_type_link();
		}
		$action = '&action=edit';

		return admin_url( sprintf( $post_type_object->_edit_link . $action, $post->ID ) );
	}

	/**
	 * @return array
	 */
	public function get_exclude_from_search_post_status() {
		return get_post_stati( [ 'exclude_from_search' => true ] );
	}

	/**
	 * @return int
	 */
	public function get_load_priority() {
		return 10;
	}
}
