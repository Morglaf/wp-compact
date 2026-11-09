<?php
/**
 * Exact URL search/replace after WebP migration.
 *
 * @package WPSquoosh
 */

namespace WPSquoosh;

defined( 'ABSPATH' ) || exit;

/**
 * Builds URL maps and rewrites DB references.
 */
class Url_Rewriter {

	/**
	 * Snapshot URLs/paths before migration.
	 *
	 * @param int         $attachment_id Attachment ID.
	 * @param string      $main_path Absolute main path.
	 * @param array       $meta Metadata.
	 * @param string      $rel_file Relative attached file.
	 * @param string|false $url Main URL.
	 * @return array{main_url: string, main_rel: string, sizes: array<string, array{url: string, file: string}>}
	 */
	public static function snapshot_attachment_urls( $attachment_id, $main_path, $meta, $rel_file, $url ) {
		$sizes = array();

		if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $name => $size ) {
				if ( empty( $size['file'] ) ) {
					continue;
				}
				$size_url = wp_get_attachment_image_url( $attachment_id, $name );
				$sizes[ $name ] = array(
					'url'  => $size_url ? $size_url : '',
					'file' => (string) $size['file'],
					'rel'  => self::join_rel_dir( $rel_file, (string) $size['file'] ),
				);
			}
		}

		return array(
			'main_url' => $url ? (string) $url : '',
			'main_rel' => (string) $rel_file,
			'sizes'    => $sizes,
		);
	}

	/**
	 * Relative path for a size file next to main relative file.
	 *
	 * @param string $main_rel Main relative path.
	 * @param string $size_file Basename of size.
	 * @return string
	 */
	private static function join_rel_dir( $main_rel, $size_file ) {
		$dir = dirname( str_replace( '\\', '/', $main_rel ) );
		if ( '.' === $dir || '' === $dir ) {
			return ltrim( $size_file, '/' );
		}
		return trailingslashit( $dir ) . ltrim( $size_file, '/' );
	}

	/**
	 * Build old→new map after WebP migration + metadata regen.
	 *
	 * @param int   $attachment_id Attachment ID.
	 * @param array $before Snapshot from snapshot_attachment_urls().
	 * @return array<string, string> Map old => new (longest keys first later).
	 */
	public static function build_map_after_migration( $attachment_id, $before ) {
		$map = array();

		$new_url = wp_get_attachment_url( $attachment_id );
		$new_rel = get_post_meta( $attachment_id, '_wp_attached_file', true );
		$new_meta = wp_get_attachment_metadata( $attachment_id );
		if ( ! is_array( $new_meta ) ) {
			$new_meta = array();
		}

		if ( ! empty( $before['main_url'] ) && $new_url && $before['main_url'] !== $new_url ) {
			$map[ $before['main_url'] ] = $new_url;
			// Protocol-relative / http twin.
			self::add_protocol_variants( $map, $before['main_url'], $new_url );
		}
		if ( ! empty( $before['main_rel'] ) && $new_rel && $before['main_rel'] !== $new_rel ) {
			$map[ $before['main_rel'] ] = $new_rel;
		}

		// Same stem extension swap (handles hard-coded thumbs even if size keys differ).
		if ( ! empty( $before['main_url'] ) && $new_url ) {
			$old_ext = pathinfo( wp_parse_url( $before['main_url'], PHP_URL_PATH ), PATHINFO_EXTENSION );
			$new_ext = pathinfo( wp_parse_url( $new_url, PHP_URL_PATH ), PATHINFO_EXTENSION );
			if ( $old_ext && $new_ext && strtolower( $old_ext ) !== strtolower( $new_ext ) ) {
				foreach ( $before['sizes'] as $name => $old_size ) {
					if ( empty( $old_size['url'] ) ) {
						continue;
					}
					$candidate = self::swap_extension( $old_size['url'], $new_ext );
					// Prefer mapped size URL if available.
					$mapped = wp_get_attachment_image_url( $attachment_id, $name );
					if ( $mapped ) {
						$map[ $old_size['url'] ] = $mapped;
						self::add_protocol_variants( $map, $old_size['url'], $mapped );
					} elseif ( $candidate && $candidate !== $old_size['url'] ) {
						$map[ $old_size['url'] ] = $candidate;
						self::add_protocol_variants( $map, $old_size['url'], $candidate );
					}
					if ( ! empty( $old_size['rel'] ) ) {
						$new_size_rel = '';
						if ( ! empty( $new_meta['sizes'][ $name ]['file'] ) ) {
							$new_size_rel = self::join_rel_dir( $new_rel, $new_meta['sizes'][ $name ]['file'] );
						} else {
							$new_size_rel = self::swap_extension( $old_size['rel'], $new_ext );
						}
						if ( $new_size_rel && $new_size_rel !== $old_size['rel'] ) {
							$map[ $old_size['rel'] ] = $new_size_rel;
						}
					}
				}
			}
		}

		// Drop identity pairs.
		$map = array_filter(
			$map,
			static function ( $new, $old ) {
				return is_string( $old ) && is_string( $new ) && $old !== '' && $new !== '' && $old !== $new;
			},
			ARRAY_FILTER_USE_BOTH
		);

		return $map;
	}

	/**
	 * @param array  $map Map.
	 * @param string $old Old URL.
	 * @param string $new New URL.
	 */
	private static function add_protocol_variants( &$map, $old, $new ) {
		$variants = array(
			array( 'http://', 'https://' ),
			array( 'https://', 'http://' ),
		);
		foreach ( $variants as $pair ) {
			if ( 0 === strpos( $old, $pair[0] ) ) {
				$old_alt = $pair[1] . substr( $old, strlen( $pair[0] ) );
				$new_alt = $new;
				if ( 0 === strpos( $new, $pair[0] ) ) {
					$new_alt = $pair[1] . substr( $new, strlen( $pair[0] ) );
				} elseif ( 0 === strpos( $new, $pair[1] ) ) {
					$new_alt = $new;
				}
				if ( $old_alt !== $new_alt ) {
					$map[ $old_alt ] = $new_alt;
				}
			}
		}
	}

	/**
	 * @param string $path Path or URL.
	 * @param string $new_ext New extension without dot.
	 * @return string
	 */
	private static function swap_extension( $path, $new_ext ) {
		$new_ext = ltrim( $new_ext, '.' );
		return (string) preg_replace( '/\.[a-zA-Z0-9]+$/', '.' . $new_ext, $path );
	}

	/**
	 * Apply map across posts, postmeta, options.
	 *
	 * @param array<string, string> $map Old => new.
	 * @return array{replacements: int, rows: int, warnings: string[]}
	 */
	public static function apply_map( $map ) {
		if ( empty( $map ) ) {
			return array(
				'replacements' => 0,
				'rows'         => 0,
				'warnings'     => array(),
			);
		}

		uksort(
			$map,
			static function ( $a, $b ) {
				return strlen( $b ) - strlen( $a );
			}
		);

		$total = 0;
		$rows  = 0;
		$warn  = array();

		$r = self::replace_in_posts( $map );
		$total += $r['replacements'];
		$rows  += $r['rows'];
		$warn   = array_merge( $warn, $r['warnings'] );

		$r = self::replace_in_postmeta( $map );
		$total += $r['replacements'];
		$rows  += $r['rows'];
		$warn   = array_merge( $warn, $r['warnings'] );

		$r = self::replace_in_options( $map );
		$total += $r['replacements'];
		$rows  += $r['rows'];
		$warn   = array_merge( $warn, $r['warnings'] );

		return array(
			'replacements' => $total,
			'rows'         => $rows,
			'warnings'     => $warn,
		);
	}

	/**
	 * @param array<string, string> $map Map.
	 * @return array{replacements: int, rows: int, warnings: string[]}
	 */
	private static function replace_in_posts( $map ) {
		global $wpdb;
		$replacements = 0;
		$rows         = 0;
		$warnings     = array();

		foreach ( $map as $old => $new ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$found = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT ID, post_content, guid FROM {$wpdb->posts} WHERE post_content LIKE %s OR guid LIKE %s",
					'%' . $wpdb->esc_like( $old ) . '%',
					'%' . $wpdb->esc_like( $old ) . '%'
				),
				ARRAY_A
			);
			if ( ! $found ) {
				continue;
			}
			foreach ( $found as $row ) {
				$content = $row['post_content'];
				$guid    = $row['guid'];
				$c_new   = self::replace_value( $content, $old, $new, $warnings );
				$g_new   = self::replace_value( $guid, $old, $new, $warnings );
				$changed = false;
				$data    = array();
				if ( $c_new !== $content ) {
					$data['post_content'] = $c_new;
					$replacements        += substr_count( $content, $old );
					$changed              = true;
				}
				if ( $g_new !== $guid ) {
					$data['guid']  = $g_new;
					$replacements += substr_count( $guid, $old );
					$changed       = true;
				}
				if ( $changed ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->update( $wpdb->posts, $data, array( 'ID' => (int) $row['ID'] ) );
					clean_post_cache( (int) $row['ID'] );
					++$rows;
				}
			}
		}

		return compact( 'replacements', 'rows', 'warnings' );
	}

	/**
	 * @param array<string, string> $map Map.
	 * @return array{replacements: int, rows: int, warnings: string[]}
	 */
	private static function replace_in_postmeta( $map ) {
		global $wpdb;
		$replacements = 0;
		$rows         = 0;
		$warnings     = array();

		foreach ( $map as $old => $new ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$found = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT meta_id, meta_value FROM {$wpdb->postmeta} WHERE meta_value LIKE %s",
					'%' . $wpdb->esc_like( $old ) . '%'
				),
				ARRAY_A
			);
			if ( ! $found ) {
				continue;
			}
			foreach ( $found as $row ) {
				$val     = $row['meta_value'];
				$new_val = self::replace_value( $val, $old, $new, $warnings );
				if ( $new_val === $val ) {
					continue;
				}
				$count = substr_count( is_string( $val ) ? $val : '', $old );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update(
					$wpdb->postmeta,
					array( 'meta_value' => $new_val ),
					array( 'meta_id' => (int) $row['meta_id'] )
				);
				$replacements += max( 1, $count );
				++$rows;
			}
		}

		return compact( 'replacements', 'rows', 'warnings' );
	}

	/**
	 * @param array<string, string> $map Map.
	 * @return array{replacements: int, rows: int, warnings: string[]}
	 */
	private static function replace_in_options( $map ) {
		global $wpdb;
		$replacements = 0;
		$rows         = 0;
		$warnings     = array();

		foreach ( $map as $old => $new ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$found = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT option_id, option_name, option_value FROM {$wpdb->options} WHERE option_value LIKE %s",
					'%' . $wpdb->esc_like( $old ) . '%'
				),
				ARRAY_A
			);
			if ( ! $found ) {
				continue;
			}
			foreach ( $found as $row ) {
				$val     = $row['option_value'];
				$new_val = self::replace_value( $val, $old, $new, $warnings );
				if ( $new_val === $val ) {
					continue;
				}
				$count = substr_count( is_string( $val ) ? $val : '', $old );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update(
					$wpdb->options,
					array( 'option_value' => $new_val ),
					array( 'option_id' => (int) $row['option_id'] )
				);
				wp_cache_delete( $row['option_name'], 'options' );
				$replacements += max( 1, $count );
				++$rows;
			}
		}

		return compact( 'replacements', 'rows', 'warnings' );
	}

	/**
	 * Replace in plain or serialized PHP string values.
	 *
	 * @param mixed    $value Value.
	 * @param string   $old Old needle.
	 * @param string   $new New needle.
	 * @param string[] $warnings Warnings collector.
	 * @return mixed
	 */
	public static function replace_value( $value, $old, $new, &$warnings ) {
		if ( ! is_string( $value ) || $value === '' || false === strpos( $value, $old ) ) {
			return $value;
		}

		if ( self::is_serialized( $value ) ) {
			$un = @unserialize( $value ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize, WordPress.PHP.NoSilencedErrors.Discouraged
			if ( false === $un && 'b:0;' !== $value ) {
				$warnings[] = 'serialized_unreadable';
				return str_replace( $old, $new, $value );
			}
			if ( is_object( $un ) ) {
				$warnings[] = 'serialized_object_skipped';
				return $value;
			}
			$replaced = self::replace_deep( $un, $old, $new );
			return serialize( $replaced ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		}

		return str_replace( $old, $new, $value );
	}

	/**
	 * @param mixed  $data Data.
	 * @param string $old Old.
	 * @param string $new New.
	 * @return mixed
	 */
	private static function replace_deep( $data, $old, $new ) {
		if ( is_string( $data ) ) {
			return str_replace( $old, $new, $data );
		}
		if ( is_array( $data ) ) {
			foreach ( $data as $k => $v ) {
				$data[ $k ] = self::replace_deep( $v, $old, $new );
			}
		}
		return $data;
	}

	/**
	 * @param string $data Data.
	 * @return bool
	 */
	private static function is_serialized( $data ) {
		if ( ! is_string( $data ) ) {
			return false;
		}
		$data = trim( $data );
		if ( 'N;' === $data ) {
			return true;
		}
		if ( strlen( $data ) < 4 || ':' !== $data[1] ) {
			return false;
		}
		$last = substr( $data, -1 );
		if ( ';' !== $last && '}' !== $last ) {
			return false;
		}
		$token = $data[0];
		return in_array( $token, array( 'a', 'O', 's', 'b', 'i', 'd' ), true );
	}
}
