<?php
/**
 * Handle frontend scripts
 *
 * @class       Pinterest_For_Woocommerce_Frontend_Scripts
 * @version     1.0.0
 * @package     Pinterest_For_Woocommerce/Classes/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pinterest_For_Woocommerce_Frontend_Scripts Class.
 */
abstract class Pinterest_For_Woocommerce_Assets {

	/**
	 * Contains an array of script handles registered by WC.
	 *
	 * @var array
	 */
	private $scripts = array();

	/**
	 * Contains an array of script handles registered by WC.
	 *
	 * @var array
	 */
	private $styles = array();

	/**
	 * Contains an array of script handles localized by WC.
	 *
	 * @var array
	 */
	private $wp_localize_scripts = array();

	/**
	 * Tryies to localize the minified version if required and exists, otherwise load the unminified version
	 *
	 * @param string $path The path of the asset to localize.
	 * @return string
	 */
	protected function localize_asset( $path ) {
		$assets_path     = Pinterest_For_Woocommerce()->plugin_path() . '/assets/';
		$assets_path_url = str_replace( array( 'http:', 'https:' ), '', Pinterest_For_Woocommerce()->plugin_url() ) . '/assets/';

		if ( ! ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ) {
			$ext_pos    = strrpos( $path, '.' );
			$clean_path = substr( $path, 0, $ext_pos );
			$ext        = substr( $path, $ext_pos );
			$min_path   = $clean_path . '.min' . $ext;
			if ( file_exists( $assets_path . $min_path ) ) {
				$path = $min_path;
			}
		}

		return $assets_path_url . $path;
	}

	/**
	 * Get styles for the frontend.
	 *
	 * @return array
	 */
	public function get_styles() {
		return array();
	}

	/**
	 * Get styles for the frontend.
	 *
	 * @return array
	 */
	public function get_scripts() {
		return array();
	}

	/**
	 * Register a script for use.
	 *
	 * @uses   wp_register_script()
	 *
	 * @param  string   $handle handle that will be passed to wp_register_script().
	 * @param  string   $path path that will be passed to wp_register_script().
	 * @param  string[] $deps deps that will be passed to wp_register_script().
	 * @param  string   $version version that will be passed to wp_register_script().
	 * @param  boolean  $in_footer in_footer that will be passed to wp_register_script().
	 */
	private function register_script( $handle, $path, $deps = array( 'jquery' ), $version = PINTEREST_FOR_WOOCOMMERCE_VERSION, $in_footer = true ) {
		$this->scripts[] = $handle;
		wp_register_script( $handle, $path, $deps, $version, $in_footer );
	}

	/**
	 * Register and enqueue a script for use.
	 *
	 * @uses   wp_enqueue_script()
	 *
	 * @param  string   $handle handle that will be passed to wp_register_script().
	 * @param  string   $path path that will be passed to wp_register_script().
	 * @param  string[] $deps deps that will be passed to wp_register_script().
	 * @param  string   $version version that will be passed to wp_register_script().
	 * @param  boolean  $in_footer in_footer that will be passed to wp_register_script().
	 */
	private function enqueue_script( $handle, $path = '', $deps = array( 'jquery' ), $version = PINTEREST_FOR_WOOCOMMERCE_VERSION, $in_footer = true ) {
		if ( ! in_array( $handle, $this->scripts, true ) && $path ) {
			$this->register_script( $handle, $path, $deps, $version, $in_footer );
		}
		wp_enqueue_script( $handle );
	}

	/**
	 * Register a style for use.
	 *
	 * @uses   wp_register_style()
	 *
	 * @param  string   $handle handle that will be passed to wp_register_style().
	 * @param  string   $path path that will be passed to wp_register_style().
	 * @param  string[] $deps deps that will be passed to wp_register_style().
	 * @param  string   $version version that will be passed to wp_register_style().
	 * @param  string   $media media that will be passed to wp_register_style().
	 */
	private function register_style( $handle, $path, $deps = array(), $version = PINTEREST_FOR_WOOCOMMERCE_VERSION, $media = 'all' ) {
		$this->styles[] = $handle;
		wp_register_style( $handle, $path, $deps, $version, $media );
	}

	/**
	 * Register and enqueue a styles for use.
	 *
	 * @uses   wp_enqueue_style()
	 *
	 * @param  string   $handle handle that will be passed to wp_register_style().
	 * @param  string   $path path that will be passed to wp_register_style().
	 * @param  string[] $deps deps that will be passed to wp_register_style().
	 * @param  string   $version version that will be passed to wp_register_style().
	 * @param  string   $media media that will be passed to wp_register_style().
	 */
	private function enqueue_style( $handle, $path = '', $deps = array(), $version = PINTEREST_FOR_WOOCOMMERCE_VERSION, $media = 'all' ) {
		if ( ! in_array( $handle, $this->styles, true ) && $path ) {
			$this->register_style( $handle, $path, $deps, $version, $media );
		}
		wp_enqueue_style( $handle );
	}

	/**
	 * Register/queue frontend scripts.
	 */
	public function load_scripts() {
		global $post;

		if ( ! did_action( 'before_pinterest_for_woocommerce_init' ) ) {
			return;
		}

		$suffix               = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		$assets_path          = str_replace( array( 'http:', 'https:' ), '', Pinterest_For_Woocommerce()->plugin_url() ) . '/assets/';
		$frontend_script_path = $assets_path . 'js/frontend/';

		// JS Scripts.
		$enqueue_scripts = $this->get_scripts();
		if ( $enqueue_scripts ) {
			foreach ( $enqueue_scripts as $handle => $args ) {
				$args = wp_parse_args(
					$args,
					array(
						'src'       => '',
						'deps'      => array( 'jquery' ),
						'version'   => PINTEREST_FOR_WOOCOMMERCE_VERSION,
						'in_footer' => true,
					)
				);
				$this->enqueue_script( $handle, $args['src'], $args['deps'], $args['version'], $args['in_footer'] );
			}
		}

		// CSS Styles.
		$enqueue_styles = $this->get_styles();
		if ( $enqueue_styles ) {
			foreach ( $enqueue_styles as $handle => $args ) {
				$args = wp_parse_args(
					$args,
					array(
						'src'     => '',
						'deps'    => '',
						'version' => PINTEREST_FOR_WOOCOMMERCE_VERSION,
						'media'   => 'all',
					)
				);
				$this->enqueue_style( $handle, $args['src'], $args['deps'], $args['version'], $args['media'] );
			}
		}
	}

	/**
	 * Localize a WC script once.
	 *
	 * @since  1.0.0 this needs less wp_script_is() calls due to https://core.trac.wordpress.org/ticket/28404 being added in WP 4.0.
	 * @param  string $handle handle of the script to localize.
	 */
	private function localize_script( $handle ) {
		if ( ! in_array( $handle, $this->wp_localize_scripts, true ) && wp_script_is( $handle ) ) {
			$data = $this->get_script_data( $handle );
			if ( $data ) {
				$name                        = str_replace( '-', '_', $handle ) . '_params';
				$this->wp_localize_scripts[] = $handle;
				wp_localize_script( $handle, $name, apply_filters( $name, $data ) );
			}
		}
	}

	/**
	 * Return data for script handles.
	 *
	 * @param  string $handle handle of the script to get data for.
	 * @return array|bool
	 */
	private function get_script_data( $handle ) {
		global $wp;

		$scripts = $this->get_scripts();
		if ( isset( $scripts[ $handle ] ) && isset( $scripts[ $handle ]['data'] ) ) {
			$data = $scripts[ $handle ]['data'];
			if ( is_callable( $data ) ) {
				$data = call_user_func( $data );
			}
			return $data;
		}
		return false;
	}

	/**
	 * Localize scripts only when enqueued.
	 */
	public function localize_printed_scripts() {
		foreach ( $this->scripts as $handle ) {
			$this->localize_script( $handle );
		}
	}
}
