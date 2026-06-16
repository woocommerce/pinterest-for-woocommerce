<?php
/**
 * Pinterest for WooCommerce — Feed Debug Tool
 *
 * URL: https://{site}/wp-content/plugins/pinterest-for-woocommerce/debug.php
 *
 * Shows feed generation state, product counts, Action Scheduler history, and
 * system info in one page. Everything needed to diagnose feed generation
 * problems without asking the merchant to run SQL queries or install extra
 * plugins.
 *
 * Access: requires an active WordPress administrator session. Visitors who are
 * not logged in are redirected to wp-login.php; non-administrators receive a
 * 403. The page is not linked from any admin menu — it is intentionally
 * accessed only by support staff who know the URL.
 *
 * @package Automattic\WooCommerce\Pinterest
 */

// ── Bootstrap WordPress ───────────────────────────────────────────────────────
$p4wc_wp_load = '';
$p4wc_dir     = __DIR__;
for ( $p4wc_i = 0; $p4wc_i < 8; $p4wc_i++ ) {
	if ( file_exists( $p4wc_dir . '/wp-load.php' ) ) {
		$p4wc_wp_load = $p4wc_dir . '/wp-load.php';
		break;
	}
	$p4wc_dir = dirname( $p4wc_dir );
}
if ( ! $p4wc_wp_load ) {
	http_response_code( 500 );
	exit( 'Could not locate wp-load.php. Place this file inside a WordPress installation.' );
}
require_once $p4wc_wp_load;
unset( $p4wc_wp_load, $p4wc_dir, $p4wc_i );

// ── Authentication ────────────────────────────────────────────────────────────
if ( ! is_user_logged_in() ) {
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
	wp_safe_redirect( wp_login_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) ) );
	exit;
}
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die(
		'<p>You do not have permission to access this page.</p>',
		'Access Denied',
		array( 'response' => 403 )
	);
}

// ── Guard: plugin must be active ──────────────────────────────────────────────
if ( ! defined( 'PINTEREST_FOR_WOOCOMMERCE_VERSION' ) ) {
	wp_die( '<p>Pinterest for WooCommerce is not active on this site.</p>', 'Plugin Not Active' );
}

// ── Data collection ───────────────────────────────────────────────────────────
global $wpdb;

// 1. System info.
$system = array(
	'Pinterest for WooCommerce' => PINTEREST_FOR_WOOCOMMERCE_VERSION,
	'WooCommerce'               => defined( 'WC_VERSION' ) ? WC_VERSION : 'n/a',
	'WordPress'                 => get_bloginfo( 'version' ),
	'PHP'                       => PHP_VERSION,
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	'MySQL'                     => $wpdb->get_var( 'SELECT VERSION()' ),
	'Site URL'                  => get_site_url(),
);

// 2. Product counts — same queries the feed uses.
$variable_like = $wpdb->esc_like( 'variable' ) . '%';

// All published product posts (simple + variable parents) — matches WC admin dashboard count.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$simple_count = (int) $wpdb->get_var(
	"SELECT COUNT(*)
	 FROM {$wpdb->posts}
	 WHERE post_type = 'product' AND post_status = 'publish'"
);

// Simple products only (not variable type).
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$simple_products = (int) $wpdb->get_var(
	$wpdb->prepare(
		"SELECT COUNT(*)
		 FROM {$wpdb->posts}
		 WHERE post_type = 'product' AND post_status = 'publish'
		   AND ID NOT IN (
		       SELECT object_id
		       FROM {$wpdb->term_relationships} tr
		       JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
		       JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
		       WHERE tt.taxonomy = 'product_type' AND t.slug LIKE %s
		   )",
		$variable_like
	)
);

// Variable product parents only.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$variable_parents = (int) $wpdb->get_var(
	$wpdb->prepare(
		"SELECT COUNT(*)
		 FROM {$wpdb->posts} p
		 JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
		 JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
		 JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
		 WHERE p.post_type = 'product' AND p.post_status = 'publish'
		   AND tt.taxonomy = 'product_type' AND t.slug LIKE %s",
		$variable_like
	)
);

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$feed_total = (int) $wpdb->get_var(
	$wpdb->prepare(
		"SELECT COUNT( post.ID )
		 FROM {$wpdb->posts} AS post
		 LEFT JOIN {$wpdb->posts} AS parent ON post.post_parent = parent.ID
		 WHERE
		     (
		         ( post.post_type = 'product_variation'
		             AND parent.post_status = 'publish'
		             AND EXISTS (
		                 SELECT 1
		                 FROM {$wpdb->term_relationships} tr
		                 INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
		                 INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
		                 WHERE tr.object_id = parent.ID
		                   AND tt.taxonomy = 'product_type'
		                   AND t.slug LIKE %s
		             )
		         )
		     OR ( post.post_type = 'product' AND post.post_status = 'publish' )
		     )",
		$variable_like
	)
);

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$variation_count = (int) $wpdb->get_var(
	$wpdb->prepare(
		"SELECT COUNT( post.ID )
		 FROM {$wpdb->posts} AS post
		 INNER JOIN {$wpdb->posts} AS parent ON post.post_parent = parent.ID
		 WHERE post.post_type = 'product_variation'
		   AND parent.post_status = 'publish'
		   AND EXISTS (
		       SELECT 1
		       FROM {$wpdb->term_relationships} tr
		       INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
		       INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
		       WHERE tr.object_id = parent.ID
		         AND tt.taxonomy = 'product_type'
		         AND t.slug LIKE %s
		   )",
		$variable_like
	)
);

$product_posts  = $feed_total - $variation_count;
$batches_needed = $feed_total > 0 ? (int) ceil( $feed_total / 100 ) : 0;
$buffered       = $batches_needed > 0 ? (int) ceil( $batches_needed * 1.25 ) : 0;
$recommended    = $feed_total > 0 ? max( 500, (int) ( ceil( $buffered / 500 ) * 500 ) ) : 0;

// 3. Feed cursor and configuration.
/**
 * Filters the maximum number of batches per feed generation cycle.
 *
 * @since 1.4.0
 * @param int $limit Default batch limit.
 */
$active_limit = (int) apply_filters( 'pinterest_for_woocommerce_max_feed_batches_per_cycle', 1000 );

$cursor_dedicated = get_option( 'pinterest-for-woocommerce_feed_cursor', null );
$reset_flag       = get_option( 'pinterest-for-woocommerce_cursor_reset_flag', null );

$data_option     = get_option( 'pinterest_for_woocommerce_data', array() );
$cursor_legacy   = $data_option['feed_last_queued_item_id'] ?? 'not set';
$batch_size_ovr  = $data_option['feed_product_batch_size'] ?? null;
$batch_attempt   = $data_option['feed_product_batch_attempt'] ?? null;
$feed_dirty      = isset( $data_option['feed_dirty'] ) ? ( $data_option['feed_dirty'] ? 'yes' : 'no' ) : 'not set';
$feed_registered = $data_option['feed_registered'] ?? 'not set';
$merchant_id     = $data_option['merchant_id'] ?? 'not set';

// 4. ProductFeedStatus.
$feed_status = array();
if ( class_exists( '\Automattic\WooCommerce\Pinterest\ProductFeedStatus' ) ) {
	$feed_status = \Automattic\WooCommerce\Pinterest\ProductFeedStatus::get();
}

// 5. Action Scheduler — all Pinterest actions, last 48 h.
$as_actions = array();
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
if ( $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}actionscheduler_actions'" ) ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	$as_actions = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT a.action_id,
			        a.hook,
			        a.status,
			        a.args,
			        a.scheduled_date_gmt,
			        a.last_attempt_gmt,
			        l.message AS log_message
			 FROM {$wpdb->prefix}actionscheduler_actions a
			 INNER JOIN {$wpdb->prefix}actionscheduler_groups g ON g.group_id = a.group_id
			 LEFT JOIN (
			     SELECT action_id, message
			     FROM {$wpdb->prefix}actionscheduler_logs
			     WHERE log_id IN (
			         SELECT MAX(log_id)
			         FROM {$wpdb->prefix}actionscheduler_logs
			         GROUP BY action_id
			     )
			 ) l ON l.action_id = a.action_id
			 WHERE g.slug = %s
			   AND a.scheduled_date_gmt >= DATE_SUB( NOW(), INTERVAL 48 HOUR )
			 ORDER BY a.scheduled_date_gmt DESC
			 LIMIT 100",
			'pinterest-for-woocommerce'
		)
	);
}

// 6. Integrity ratio.
$integrity_ok = null;
if ( $feed_total > 0 && isset( $feed_status['product_count'] ) ) {
	$written      = (int) $feed_status['product_count'];
	$integrity_ok = $written <= (int) ceil( $feed_total * 1.1 );
}

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Return a coloured status badge span.
 *
 * @param string $status Action Scheduler status string.
 * @return string HTML span.
 */
function p4wc_debug_badge( string $status ): string {
	$map                = array(
		'complete'    => array( '#d4edda', '#155724' ),
		'pending'     => array( '#cce5ff', '#004085' ),
		'in-progress' => array( '#fff3cd', '#856404' ),
		'failed'      => array( '#f8d7da', '#721c24' ),
		'canceled'    => array( '#e2e3e5', '#383d41' ),
	);
	list( $bg, $color ) = isset( $map[ $status ] ) ? $map[ $status ] : array( '#e2e3e5', '#383d41' );
	return sprintf(
		'<span style="background:%s;color:%s;padding:2px 7px;border-radius:3px;font-size:11px;font-weight:600">%s</span>',
		esc_attr( $bg ),
		esc_attr( $color ),
		esc_html( strtoupper( $status ) )
	);
}

/**
 * Return a two-cell table row with label and value.
 *
 * @param string $label Row label.
 * @param mixed  $value Row value (arrays are rendered as a pre block).
 * @param string $note  Optional explanatory note shown in muted text.
 * @return string HTML tr.
 */
function p4wc_debug_row( string $label, $value, string $note = '' ): string {
	if ( is_array( $value ) || is_object( $value ) ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
		$val = '<pre style="margin:0">' . esc_html( var_export( $value, true ) ) . '</pre>';
	} else {
		$val = esc_html( (string) $value );
	}
	$n = $note ? ' <span style="color:#6c757d;font-size:11px">&mdash; ' . esc_html( $note ) . '</span>' : '';
	return '<tr><td style="padding:5px 10px;color:#6c757d;white-space:nowrap;vertical-align:top">'
		. esc_html( $label )
		. '</td><td style="padding:5px 10px">'
		. $val . $n
		. '</td></tr>';
}

/**
 * Return an h2 section heading.
 *
 * @param string $title Section title.
 * @return string HTML h2.
 */
function p4wc_debug_section( string $title ): string {
	return '<h2 style="margin:30px 0 8px;font-size:15px;border-bottom:2px solid #dee2e6;padding-bottom:6px;color:#343a40">'
		. esc_html( $title )
		. '</h2>';
}

$generated_at = current_time( 'Y-m-d H:i:s' ) . ' (server: ' . gmdate( 'Y-m-d H:i:s' ) . ' UTC)';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Pinterest for WooCommerce &mdash; Feed Debug</title>
<style>
*, *::before, *::after { box-sizing: border-box }
body  { font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; font-size:13px; color:#212529; background:#f8f9fa; margin:0; padding:20px }
.wrap { max-width:1200px; margin:0 auto; background:#fff; border:1px solid #dee2e6; border-radius:6px; padding:24px }
h1    { font-size:20px; margin:0 0 4px; color:#343a40 }
.meta { color:#6c757d; font-size:12px; margin-bottom:20px }
table { width:100%; border-collapse:collapse; margin-bottom:6px }
table tr:nth-child(even) td { background:#f8f9fa }
.as-table th { background:#343a40; color:#fff; padding:6px 10px; text-align:left; font-size:11px; font-weight:600; white-space:nowrap }
.as-table td { padding:5px 10px; border-bottom:1px solid #dee2e6; vertical-align:top }
.as-table tr:hover td { background:#f0f4ff }
.warn { background:#fff3cd; border:1px solid #ffc107; border-radius:4px; padding:10px 14px; margin:8px 0; color:#856404 }
.ok   { background:#d4edda; border:1px solid #28a745; border-radius:4px; padding:10px 14px; margin:8px 0; color:#155724 }
.err  { background:#f8d7da; border:1px solid #dc3545; border-radius:4px; padding:10px 14px; margin:8px 0; color:#721c24 }
pre   { font-size:11px; white-space:pre-wrap; word-break:break-all }
.hook { font-size:11px; color:#495057 }
.nodata { color:#6c757d; font-style:italic; padding:10px 0 }
</style>
</head>
<body>
<div class="wrap">

<h1>Pinterest for WooCommerce &mdash; Feed Debug</h1>
<p class="meta">
	Generated: <?php echo esc_html( $generated_at ); ?>
	&nbsp;|&nbsp;
	User: <?php echo esc_html( wp_get_current_user()->user_login ); ?>
</p>

<?php
// ── Section 1: System Info ────────────────────────────────────────────────────
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo p4wc_debug_section( '1. System Info' );
echo '<table>';
foreach ( $system as $k => $v ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo p4wc_debug_row( $k, $v );
}
echo '</table>';

// ── Section 2: Product Counts ─────────────────────────────────────────────────
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo p4wc_debug_section( '2. Product Counts' );

if ( $simple_count !== $feed_total ) {
	echo '<div class="warn">'
		. '&#9888; WC dashboard count (' . esc_html( number_format( $simple_count ) ) . ') differs from feed query total (' . esc_html( number_format( $feed_total ) ) . '). '
		. 'The feed iterates more rows than the admin shows &mdash; check for extra published product posts (e.g. from incomplete imports or leftover variations).'
		. '</div>';
}

echo '<table>';
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo p4wc_debug_row( 'WC dashboard count', number_format( $simple_count ), 'all published products (simple + variable parents)' );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo p4wc_debug_row( '  -> simple products', number_format( $simple_products ), 'non-variable product type' );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo p4wc_debug_row( '  -> variable products (parents)', number_format( $variable_parents ), 'variable product type; do NOT appear in feed directly' );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo p4wc_debug_row( 'Feed query total rows', number_format( $feed_total ), 'rows the feed iterates: simple products + variations (not variable parents)' );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo p4wc_debug_row( '  -> simple products in feed', number_format( $product_posts - $variable_parents ), 'simple products counted by feed query' );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo p4wc_debug_row( '  -> variation posts in feed', number_format( $variation_count ), 'published variations with published variable parent' );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo p4wc_debug_row( 'Batches needed @ 100/batch', number_format( $batches_needed ) );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo p4wc_debug_row( 'Recommended circuit-breaker filter', number_format( $recommended ), 'ceil(total/100) x 1.25 rounded to nearest 500' );
echo '</table>';

// ── Section 3: Feed Configuration & Cursor ────────────────────────────────────
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo p4wc_debug_section( '3. Feed Configuration & Cursor' );

$limit_ok = $active_limit >= $batches_needed;
if ( $limit_ok ) {
	echo '<div class="ok">'
		. '&#10004; Circuit breaker limit (' . esc_html( number_format( $active_limit ) ) . ') is sufficient for ' . esc_html( number_format( $batches_needed ) ) . ' needed batches.'
		. '</div>';
} else {
	echo '<div class="err">'
		. '&#10008; Circuit breaker limit (' . esc_html( number_format( $active_limit ) ) . ') is TOO LOW &mdash; feed needs ' . esc_html( number_format( $batches_needed ) ) . ' batches. '
		. 'Add: <code>add_filter( \'pinterest_for_woocommerce_max_feed_batches_per_cycle\', fn() =&gt; ' . esc_html( number_format( $recommended ) ) . ' );</code>'
		. '</div>';
}

echo '<table>';
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo p4wc_debug_row( 'Active circuit breaker limit', number_format( $active_limit ), 'pinterest_for_woocommerce_max_feed_batches_per_cycle filter' );

if ( null !== $cursor_dedicated ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo p4wc_debug_row( 'Cursor (dedicated option)', number_format( (int) $cursor_dedicated ), 'pinterest-for-woocommerce_feed_cursor - authoritative after plugin fix' );
} else {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo p4wc_debug_row( 'Cursor (dedicated option)', 'NOT SET', 'plugin fix not yet active - cursor stored in legacy shared option' );
}
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo p4wc_debug_row( 'Cursor (legacy shared option)', $cursor_legacy, 'feed_last_queued_item_id inside pinterest_for_woocommerce_data' );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo p4wc_debug_row( 'Cursor reset flag', null !== $reset_flag ? (int) $reset_flag : 'not set', 'set by workaround snippet when batch 1 is about to start' );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo p4wc_debug_row( 'Batch size override', null !== $batch_size_ovr ? $batch_size_ovr : 'not set (using default 100)', 'set during timeout retries; cleared after success' );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo p4wc_debug_row( 'Batch attempt', null !== $batch_attempt ? $batch_attempt : 'not set', 'retry counter within current batch; cleared after success' );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo p4wc_debug_row( 'Feed dirty', $feed_dirty );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo p4wc_debug_row( 'Feed registered (feed ID)', $feed_registered );
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo p4wc_debug_row( 'Merchant ID', $merchant_id );
echo '</table>';

// ── Section 4: ProductFeedStatus ─────────────────────────────────────────────
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo p4wc_debug_section( '4. ProductFeedStatus' );
if ( empty( $feed_status ) ) {
	echo '<p class="nodata">ProductFeedStatus unavailable.</p>';
} else {
	$written        = (int) ( $feed_status['product_count'] ?? 0 );
	$wall_time_secs = (int) ( $feed_status['feed_generation_wall_time'] ?? 0 );
	$wall_human     = $wall_time_secs > 0 ? gmdate( 'H:i:s', $wall_time_secs ) : ( -1 === $wall_time_secs ? 'failed' : '—' );

	if ( false === $integrity_ok ) {
		echo '<div class="err">'
			. '&#10008; Content integrity: ' . esc_html( number_format( $written ) ) . ' entries written vs ' . esc_html( number_format( $feed_total ) ) . ' published products '
			. '(' . esc_html( (string) round( $written / max( $feed_total, 1 ), 2 ) ) . 'x). '
			. 'Ratio exceeds 1.1 &mdash; cursor was likely reset mid-cycle causing duplicates.'
			. '</div>';
	} elseif ( true === $integrity_ok ) {
		echo '<div class="ok">'
			. '&#10004; Content integrity: ' . esc_html( number_format( $written ) ) . ' entries for ' . esc_html( number_format( $feed_total ) ) . ' products '
			. '(' . esc_html( (string) round( $written / max( $feed_total, 1 ), 2 ) ) . 'x).'
			. '</div>';
	}

	echo '<table>';
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo p4wc_debug_row( 'Status', $feed_status['status'] ?? '—' );
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo p4wc_debug_row( 'Products written this cycle', number_format( $written ) );
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo p4wc_debug_row( 'Recent product count (last completed)', number_format( (int) ( $feed_status['feed_generation_recent_product_count'] ?? 0 ) ) );
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo p4wc_debug_row( 'Last generation wall time', $wall_human );
	if ( ! empty( $feed_status['error_message'] ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo p4wc_debug_row( 'Last error', $feed_status['error_message'] );
	}
	echo '</table>';
}

// ── Section 5: Action Scheduler (last 48 h) ───────────────────────────────────
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo p4wc_debug_section( '5. Action Scheduler — Last 48 h (pinterest-for-woocommerce group)' );

if ( empty( $as_actions ) ) {
	echo '<p class="nodata">No actions found in the last 48 hours.</p>';
} else {
	$batch_hook = 'pinterest/jobs/generate_feed/chain_batch';
	$start_hook = 'pinterest-for-woocommerce-start-feed-generation';

	// Count completed batches since the most recent start-feed-generation run.
	$last_start_ran    = null;
	$completed_batches = 0;
	foreach ( $as_actions as $row ) {
		if ( $row->hook === $start_hook && 'complete' === $row->status && '0000-00-00 00:00:00' !== $row->last_attempt_gmt ) {
			$last_start_ran = $last_start_ran ?? $row->last_attempt_gmt;
		}
	}
	foreach ( $as_actions as $row ) {
		if ( $row->hook === $batch_hook && 'complete' === $row->status ) {
			if ( ! $last_start_ran || $row->scheduled_date_gmt >= $last_start_ran ) {
				++$completed_batches;
			}
		}
	}

	if ( $completed_batches > (int) ( $batches_needed * 1.1 ) ) {
		echo '<div class="warn">'
			. '&#9888; ' . esc_html( number_format( $completed_batches ) ) . ' chain_batch actions completed since last start but only ' . esc_html( number_format( $batches_needed ) ) . ' expected. '
			. 'Cursor may have been reset mid-cycle.'
			. '</div>';
	} elseif ( $completed_batches > 0 ) {
		echo '<div class="ok">'
			. '&#10004; ' . esc_html( number_format( $completed_batches ) ) . ' batches completed this cycle (expected &asymp; ' . esc_html( number_format( $batches_needed ) ) . ').'
			. '</div>';
	}

	echo '<table class="as-table">';
	echo '<tr><th>ID</th><th>Hook</th><th>Status</th><th>Batch #</th><th>Scheduled (UTC)</th><th>Last attempt (UTC)</th><th>Last log</th></tr>';
	foreach ( $as_actions as $row ) {
		$args         = json_decode( $row->args ?? '[]', true );
		$batch_num    = ( is_array( $args ) && isset( $args[0] ) && is_int( $args[0] ) ) ? $args[0] : '—';
		$short_hook   = str_replace( 'pinterest-for-woocommerce/', '', $row->hook );
		$short_hook   = str_replace( 'pinterest-for-woocommerce', 'p4wc', $short_hook );
		$last_attempt = ( $row->last_attempt_gmt && '0000-00-00 00:00:00' !== $row->last_attempt_gmt )
			? $row->last_attempt_gmt : '—';
		printf(
			'<tr><td>%s</td><td class="hook">%s</td><td>%s</td><td style="text-align:right">%s</td><td style="white-space:nowrap">%s</td><td style="white-space:nowrap">%s</td><td><small>%s</small></td></tr>',
			esc_html( $row->action_id ),
			esc_html( $short_hook ),
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			p4wc_debug_badge( $row->status ),
			esc_html( (string) $batch_num ),
			esc_html( $row->scheduled_date_gmt ),
			esc_html( $last_attempt ),
			esc_html( mb_substr( $row->log_message ?? '', 0, 140 ) )
		);
	}
	echo '</table>';
	if ( 100 === count( $as_actions ) ) {
		echo '<p class="nodata">Showing most recent 100 actions. Older entries truncated.</p>';
	}
}

// ── Section 6: Diagnosis Summary ─────────────────────────────────────────────
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo p4wc_debug_section( '6. Diagnosis Summary' );

$issues = array();
if ( $active_limit < $batches_needed ) {
	$issues[] = 'Circuit breaker limit too low &mdash; feed truncated after ' . number_format( $active_limit ) . ' batches; needs ' . number_format( $batches_needed ) . '. Apply the filter: <code>add_filter( \'pinterest_for_woocommerce_max_feed_batches_per_cycle\', fn() =&gt; ' . number_format( $recommended ) . ' );</code>';
}
if ( $simple_count !== $feed_total ) {
	$issues[] = 'Product count mismatch: WC admin shows ' . number_format( $simple_count ) . ' but feed iterates ' . number_format( $feed_total ) . ' rows. Extra posts with post_type=product may exist in the database.';
}
if ( false === $integrity_ok ) {
	$issues[] = 'Feed content integrity breach: written product count (' . number_format( (int) ( $feed_status['product_count'] ?? 0 ) ) . ') is more than 1.1x the published product count. Cursor was reset mid-cycle &mdash; duplicates were written to the feed.';
}
if ( null === $cursor_dedicated ) {
	$issues[] = 'Dedicated cursor option not found. The cursor race-condition fix is not yet active on this site. Apply the workaround snippet or update the plugin.';
}
if ( ! empty( $feed_status['error_message'] ) ) {
	$issues[] = 'Last feed error: ' . esc_html( $feed_status['error_message'] );
}

if ( empty( $issues ) ) {
	echo '<div class="ok">&#10004; No issues detected. Feed configuration looks correct.</div>';
} else {
	foreach ( $issues as $issue ) {
		echo '<div class="err">&#10008; ' . wp_kses( $issue, array( 'code' => array() ) ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
?>

</div><!-- .wrap -->
</body>
</html>
