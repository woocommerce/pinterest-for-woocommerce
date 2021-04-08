<?php defined( 'ABSPATH' ) || exit;

$args = array(
	'token'             => Pinterest_For_Woocommerce()::get_token(),
	'settings'          => Pinterest_For_Woocommerce()::get_settings(),
	'service_login_url' => add_query_arg(
		array(
			'page' => PINTEREST_FOR_WOOCOMMERCE_PREFIX,
			PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_go_to_service_login' => '1',
		),
		admin_url( 'admin.php' )
	),
);

?>
<h2><?php echo esc_html__( 'Connection', 'pinterest-for-woocommerce' ); ?></h2>

<div class="pin4wc-login-wrapper">

	<?php if ( empty( $args['token']['access_token'] ) ) : ?>

		<p><?php echo esc_html__( 'Login to connect with Pinterest APP.', 'pinterest-for-woocommerce' ); ?></p>
		<a href="<?php echo esc_url( $args['service_login_url'] ); ?>" class="button">
			<span class="dashicons dashicons-admin-network"></span>
			<?php echo esc_html__( 'Login to Pinterest', 'pinterest-for-woocommerce' ); ?>
		</a>

	<?php else : ?>

		<p class="pin4wc-success-text">
			<strong>
				<span class="dashicons dashicons-saved"></span>
				<?php esc_html_e( 'Connected to Pinterest', 'pinterest-for-woocommerce' ); ?>
			</strong>
		</p>

		<a href="<?php echo esc_url( $args['service_login_url'] ); ?>" class="button">
			<span class="dashicons dashicons-admin-network"></span>
			<?php echo esc_html__( 'Refresh Token', 'pinterest-for-woocommerce' ); ?>
		</a>

	<?php endif; ?>

	<hr/>

</div>

<?php
submit_button( esc_html__( 'Save changes', 'pinterest-for-woocommerce' ) );
