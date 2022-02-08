<?php
/**
 * Generate Google Product Categories helper script.
 *
 * Run with the command:
 * `php GenerateCategories.php taxonomy-with-ids.en-US.txt`
 *
 * Input file can be downloaded from: https://www.google.com/basepages/producttype/taxonomy-with-ids.en-US.txt
 *
 * @package Automattic\WooCommerce\Pinterest
 */

/**
 * Generate Categories class.
 *
 * @since 1.0.2
 */
class GenerateCategories {
	const CATEGORIES_FILE_NAME = 'GoogleProductTaxonomy.php';
	const NAMESPACE            = 'Automattic\WooCommerce\Pinterest\Product';

	/**
	 * Prepare categories file. It can be later required in a script.
	 *
	 * @param string $file Path to file.
	 */
	public function prepare_categories( $file ) {
		$categories_data = $this->load_categories( $file );
		$categories      = $this->parse_categories( $categories_data );

		// File content START.
		$export_string =
		'<?php' . PHP_EOL .
		'// phpcs:ignoreFile' . PHP_EOL .
		'// This file was generated using GenerateCategories.php, do not modify it manually' . PHP_EOL .
		'// php GenerateCategories.php taxonomy-with-ids.en-US.txt' . PHP_EOL .
		'namespace ' . $this::NAMESPACE . ';' . PHP_EOL .
		'class GoogleProductTaxonomy {' . PHP_EOL .
		'	const TAXONOMY = %s' . PHP_EOL .
		';}' . PHP_EOL;

		$export = sprintf(
			$export_string,
			preg_replace(
				array(
					'/[\n\r]/',
					'/\s\s+/',
				),
				'',
				var_export( $categories, true ) // phpcs:ignore
			)
		);
		// File content END.

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
		file_put_contents( $this::CATEGORIES_FILE_NAME, $export );
	}

	/**
	 * Load categories from a file.
	 *
	 * @param string $file Path to file.
	 */
	protected function load_categories( $file ) {
		// phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$category_file_contents = @file_get_contents( $file );
		$category_file_lines    = explode( "\n", $category_file_contents );
		$raw_categories         = array();
		foreach ( $category_file_lines as $category_line ) {

			if ( strpos( $category_line, ' - ' ) === false ) {
				// Not a category, skip it.
				continue;
			}

			list( $category_id, $category_name ) = explode( ' - ', $category_line );

			$raw_categories[ (string) trim( $category_id ) ] = trim( $category_name );
		}
		return $raw_categories;
	}

	/**
	 * Parse categories file lines and combine them to create an array of relations between categories.
	 *
	 * @param array $raw_categories Path to file.
	 */
	protected function parse_categories( $raw_categories ) {
		$categories = array();
		foreach ( $raw_categories as $category_id => $category_tree ) {

			$category_tree  = explode( ' > ', $category_tree );
			$category_label = end( $category_tree );
			$category       = array(
				'label'   => $category_label,
				'options' => array(),
			);

			if ( $category_label === $category_tree[0] ) {
				// This is a top-level category.
				$category['parent'] = '';
			} else {
				$parent_label = $category_tree[ count( $category_tree ) - 2 ];

				$parent_category = array_search(
					$parent_label,
					array_map(
						function ( $item ) {
							return $item['label'];
						},
						$categories
					),
					true
				);

				$category['parent'] = (string) $parent_category;

				// Add category label to the parent's list of options.
				$categories[ $parent_category ]['options'][ $category_id ] = $category_label;
			}

			$categories[ (string) $category_id ] = $category;
		}

		return $categories;
	}

}

if ( ! is_file( $argv[1] ) ) {
	echo 'Not a file!';
	exit;
}

$generator = new GenerateCategories();
$generator->prepare_categories( $argv[1] );
