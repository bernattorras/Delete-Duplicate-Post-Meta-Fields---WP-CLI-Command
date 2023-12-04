<?php
/**
 * Plugin Name: Delete Post Duplicate Meta Fields - CLI
 * Description: A custom WP-CLI command to delete/export duplicate post meta fields.
 * Version: 1.0.0
 *
 * @package delete-duplicate-meta-cli
 */
class Delete_Duplicate_Meta_Command {
	protected $environment;

	public function __construct() {
		$this->post_id  = 0;
		$this->export   = false;
		$this->dry_run  = false;
		$this->meta_key = '';
	}

	/**
	 * Delete duplicate meta fields.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : If set, no duplicate meta fields will be deleted.
	 *
	 * [--post_id=<post_id>]
	 * : If set, only the duplicate meta fields for the given post will be checked.
	 *
	 * [--export=<export>]
	 * : If set, the duplicate meta fields will be exported to a CSV file.
	 *
	 * ## EXAMPLES
	 *
	 *     wp delete-duplicate-meta
	 *     wp delete-duplicate-meta --dry-run
	 *     wp delete-duplicate-meta --post_id=123
	 *     wp delete-duplicate-meta --export=keys
	 *     wp delete-duplicate-meta --export=values
	 *     wp delete-duplicate-meta --export=different_values[meta_key_name]
	 *
	 * @param array $args       Arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function __invoke( $args, $assoc_args ) {
		$assoc_args = wp_parse_args(
			$assoc_args,
			array(
				'dry-run' => false,
				'post_id' => 0,
				'export'  => 'count',
			)
		);

		$this->post_id = (int) $assoc_args['post_id'];
		$this->export  = $assoc_args['export'];
		$this->dry_run = (bool) $assoc_args['dry-run'];

		WP_CLI::log( '' );
		WP_CLI::log( '------ DELETING DUPLICATE POST META ------' );
		WP_CLI::log( '' );

		$query_args = array(
			'post_type'      => 'post',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);

		if ( 0 !== $assoc_args['post_id'] ) {
			WP_CLI::log( sprintf( 'Checking the duplicate meta fields for the post: #%s', $this->post_id ) );
			$query_args['p'] = $this->post_id;
		} else {
			WP_CLI::log( 'Checking ALL posts for duplicate meta.' );
		}

		$duplicated_meta = $this->get_duplicated_meta_keys( $this->post_id );

		if ( empty( $duplicated_meta ) ) {
			WP_CLI::success( 'No duplicate meta found.' );
			return;
		} else {
			WP_CLI::warning( sprintf( 'Found %s posts with duplicate meta fields.', count( $duplicated_meta ) ) );

			if ( ! $this->dry_run ) {
				WP_CLI::confirm( 'Are you sure you want to delete the duplicate meta fields?' );
				WP_CLI::log( 'Deleting duplicate meta fields...' );
				$this->delete_duplicated_meta( $this->post_id );
			}

			if ( $this->export ) {

				if ( 'keys' === $this->export ) {
					$remaining_duplicated_meta = $this->get_duplicated_meta_keys( $this->post_id );
					$this->export_to_csv( $remaining_duplicated_meta, 'keys' );
				}

				if ( 'values' === $this->export ) {
					$duplicated_values = $this->get_duplicate_values( $this->post_id );
					$this->export_to_csv( $duplicated_values, 'values' );
				}

				// Export different values for the same meta key.
				if ( false !== strpos( $this->export, 'different_values' ) ) {
					$meta_key         = str_replace( 'different_values[', '', $this->export );
					$meta_key         = str_replace( ']', '', $meta_key );
					$this->meta_key   = $meta_key;
					$different_values = $this->get_different_values_same_meta_key( $meta_key );
					$this->export_to_csv( $different_values, 'different_values' );
				}
			}
		}
	}

	/**
	 * Get duplicated meta keys.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	private function get_duplicated_meta_keys( $post_id = 0 ) {
		global $wpdb;

		$where = isset( $post_id ) && 0 !== $post_id ? 'post_id = ' . (int) $post_id . ' AND ' : '';
		$query =
			"SELECT post_id, meta_key, COUNT(*) AS duplicate_count
			FROM $wpdb->postmeta
			WHERE $where (post_id, meta_key) IN (
				SELECT post_id, meta_key
				FROM $wpdb->postmeta
				GROUP BY post_id, meta_key
				HAVING COUNT(*) > 1
			)
			GROUP BY post_id, meta_key
			ORDER BY post_id, meta_key, duplicate_count DESC;";

		$duplicated_meta = $wpdb->get_results( $query ); // phpcs:ignore

		return $duplicated_meta;
	}

	/**
	 * Get duplicated meta values.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	private function get_duplicate_values( $post_id = 0 ) {
		global $wpdb;
		$where = isset( $post_id ) && 0 !== $post_id ? 'post_id = ' . (int) $post_id . ' AND ' : '';
		$query =
		"SELECT meta_id, post_id, meta_key, meta_value
			FROM wp_postmeta
			WHERE $where (post_id, meta_key) IN (
				SELECT post_id, meta_key
				FROM wp_postmeta
				GROUP BY post_id, meta_key
				HAVING COUNT(*) > 1
			)
			ORDER BY post_id, meta_key, meta_id";

		$duplicated_meta = $wpdb->get_results( $query ); // phpcs:ignore
		return $duplicated_meta;
	}

	/**
	 * Get a list of posts with different values for the same meta key.
	 *
	 * @param string $meta_key Meta key.
	 * @return array
	 */
	private function get_different_values_same_meta_key( $meta_key ) {
		global $wpdb;
		$query =
		"SELECT meta_id, post_id, meta_key, meta_value
			FROM $wpdb->postmeta
			WHERE meta_key = '$meta_key'
			AND post_id IN (
				SELECT post_id
				FROM $wpdb->postmeta
				WHERE meta_key = '$meta_key'
				GROUP BY post_id
				HAVING COUNT(*) > 1
			)";

		$different_meta = $wpdb->get_results( $query ); // phpcs:ignore
		return $different_meta;
	}
	/**
	 * Delete duplicated meta.
	 *
	 * @param int $post_id Post ID.
	 */
	private function delete_duplicated_meta( $post_id = 0 ) {
		global $wpdb;
		$and_post_id = isset( $post_id ) && 0 !== $post_id ? $wpdb->prepare( 'AND p1.post_id = %d;', $post_id ) : '';
		$result      = $wpdb->query(
			"DELETE p1
			FROM $wpdb->postmeta p1
			JOIN $wpdb->postmeta p2 ON (p1.post_id = p2.post_id AND p1.meta_key = p2.meta_key AND p1.meta_value = p2.meta_value)
			WHERE p1.meta_id > p2.meta_id $and_post_id",
			$post_id
		);

		$this->log_query_result( $result, 'Duplicate meta fields have been deleted', 'success', true );
	}

	/**
	 * Log query result.
	 *
	 * @param mixed  $result           Query result.
	 * @param string $success_message  Success message.
	 * @param bool   $show_count       Show count.
	 */
	private function log_query_result( $result, $success_message = 'Query successfull', $log_type = 'log', $show_count = false ) {
		global $wpdb;
		if ( false === $result ) {
			// Query failed
			$wpdb_error = $wpdb->last_error;
			WP_CLI::error( $wpdb_error );
		} else {
			$count_msg = '';
			if ( $show_count ) {
				$deleted_entries_count = $wpdb->rows_affected;
				$count_msg             = " ($deleted_entries_count entries affected).";
			}
			$method = "WP_CLI::$log_type";
			$method( $success_message . $count_msg );
		}
	}
	/**
	 * Export to CSV.
	 *
	 * @param array  $duplicated_meta Duplicated meta.
	 * @param string $type Type.
	 */
	private function export_to_csv( $duplicated_meta, $type = 'keys' ) {
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}

		$success_message = "All the duplicate meta $type have been written to ";
		$header_fields   = array( 'post_id', 'meta_key', 'duplicate_keys_count' );

		if ( 'values' === $type || false !== strpos( $this->export, 'different_values' ) ) {
			$header_fields = array( 'meta_id', 'post_id', 'meta_key', 'meta_value' );
		}

		if ( false !== strpos( $this->export, 'different_values' ) ) {
			$type            = 'different_values_' . $this->meta_key;
			$success_message = "All the different values for the meta $type have been written to ";
		}

		$filename  = 'duplicate_meta_' . $type . '__' . gmdate( 'Y_m_d_H_i_s' ) . '.csv';
		$file_path = trailingslashit( wp_upload_dir()['basedir'] ) . 'export/' . $filename;
		$wp_filesystem->mkdir( trailingslashit( wp_upload_dir()['basedir'] ) . 'export/' );

		$csv_handle = fopen( $file_path, 'w' ); // phpcs:ignore

		if ( $csv_handle ) {
			fputcsv( $csv_handle, $header_fields );

			foreach ( $duplicated_meta as $entry ) {
				fputcsv( $csv_handle, (array) $entry );
			}
			fclose( $csv_handle ); // phpcs:ignore
		}

		WP_CLI::success( $success_message . $file_path );
	}
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	$instance = new Delete_Duplicate_Meta_Command();
	WP_CLI::add_command( 'delete-duplicate-meta', $instance );
}
