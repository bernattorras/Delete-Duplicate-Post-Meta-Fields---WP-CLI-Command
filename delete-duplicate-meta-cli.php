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
		$this->post_id = 0;
		$this->export  = false;
		$this->dry_run = false;
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
	 * ---
	 * options:
	 *   - [none] or true (default): Exports the count of duplicate meta fields.
	 *   - values: Exports the duplicate meta values with their values.
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp post delete-duplicate-meta --dry-run
	 *     wp post delete-duplicate-meta --export=values --dry-run
	 *     wp post delete-duplicate-meta --post_id=123 --export
	 *     wp post delete-duplicate-meta --post_id=123 --export=values
	 *
	 * @param array $args       Arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function __invoke( $args, $assoc_args ) {
		$assoc_args = wp_parse_args(
			$assoc_args,
			array(
				'dry-run' => true,
				'post_id' => 0,
				'export'  => false,
			)
		);

		$this->post_id = (int) $assoc_args['post_id'];
		$this->export  = $assoc_args['export'];
		$this->dry_run = (bool) $assoc_args['dry_run'];

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
				WP_CLI::success( 'Duplicate meta fields have been deleted.' );
			}

			if ( $this->export ) {
				$this->export_to_csv( $duplicated_meta, 'count' );

				if ( 'values' === $this->export ) {
					$duplicated_values = $this->get_duplicate_values( $this->post_id );
					$this->export_to_csv( $duplicated_values, 'values' );
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
		$where = isset( $post_id ) && 0 !== $post_id ? $wpdb->prepare( 'WHERE post_id = %d', $post_id ) : '';

		$duplicated_meta = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, meta_key, COUNT(*)
				FROM $wpdb->postmeta
				$where 
				GROUP BY post_id, meta_key
				HAVING COUNT(*) > 1"
			)
		);

		$posts_with_duplicate_meta = array_reduce(
			$duplicated_meta,
			function( $result, $entry ) {
				$p_id = $entry->post_id;
				// Create a new array key if it doesn't exist
				if ( ! isset( $result[ $p_id ] ) ) {
					$result[ $p_id ] = array();
				}
				// Add the entry to the grouped array
				$result[ $p_id ][] = $entry;

				return $result;
			},
			array()
		);

		return $posts_with_duplicate_meta;
	}

	/**
	 * Get duplicated meta values.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	private function get_duplicate_values( $post_id = 0 ) {
		global $wpdb;
		$where = isset( $post_id ) && 0 !== $post_id ? $wpdb->prepare( 'WHERE pm1.post_id = %d', $post_id ) : '';

		$duplicated_meta = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
				pm1.post_id,
				pm1.meta_key,
				pm1.meta_value
			FROM
				wp_postmeta pm1
			JOIN
				wp_postmeta pm2 ON (
					pm1.post_id = pm2.post_id
					AND pm1.meta_key = pm2.meta_key
					AND pm1.meta_id <> pm2.meta_id
				)
			$where
			ORDER BY
				pm1.post_id, pm1.meta_key"
			)
		);

		$posts_with_duplicate_values = array_reduce(
			$duplicated_meta,
			function( $result, $entry ) {
				$p_id = $entry->post_id;
				// Create a new array key if it doesn't exist
				if ( ! isset( $result[ $p_id ] ) ) {
					$result[ $p_id ] = array();
				}
				// Add the entry to the grouped array
				$result[ $p_id ][] = $entry;

				return $result;
			},
			array()
		);
		return $posts_with_duplicate_values;
	}

	/**
	 * Delete duplicated meta.
	 *
	 * @param int $post_id Post ID.
	 */
	private function delete_duplicated_meta( $post_id = 0 ) {
		global $wpdb;
		$and_post_id = isset( $post_id ) && 0 !== $post_id ? $wpdb->prepare( 'AND p1.post_id = %d;', $post_id ) : '';
		$wpdb->query(
			"DELETE p1
			FROM $wpdb->postmeta p1
			JOIN $wpdb->postmeta p2 ON (p1.post_id = p2.post_id AND p1.meta_key = p2.meta_key AND p1.meta_value = p2.meta_value)
			WHERE p1.meta_id > p2.meta_id $and_post_id",
			$post_id
		);
	}

	/**
	 * Export to CSV.
	 *
	 * @param array  $duplicated_meta Duplicated meta.
	 * @param string $type Type.
	 */
	private function export_to_csv( $duplicated_meta, $type = 'count' ) {
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}

		$type      = 'count' === $type ? 'COUNT(*)' : 'meta_value';
		$filename  = 'duplicate_meta_' . $type . '__' . gmdate( 'Y_m_d_H_i_s' ) . '.csv';
		$file_path = trailingslashit( wp_upload_dir()['basedir'] ) . 'export/' . $filename;
		$wp_filesystem->mkdir( trailingslashit( wp_upload_dir()['basedir'] ) . 'export/' );

		$csv_handle = fopen( $file_path, 'w' ); // phpcs:ignore

		if ( $csv_handle ) {
			fputcsv( $csv_handle, array( 'post_id', 'meta_key', $type ) );

			foreach ( $duplicated_meta as $post_id => $entries ) {
				foreach ( $entries as $entry ) {
					$data = array(
						$entry->post_id,
						$entry->meta_key,
						$entry->{$type},
					);

					fputcsv( $csv_handle, $data );
				}
			}
			fclose( $csv_handle ); // phpcs:ignore
		}

		if ( 'meta_value' === $type ) {
			WP_CLI::success( "All the duplicate meta values have been written to $file_path" );
		} else {
			WP_CLI::success( "All the duplicate meta keys have been written to $file_path" );
		}
	}
}

$instance = new Delete_Duplicate_Meta_Command();

WP_CLI::add_command( 'post', $instance );
