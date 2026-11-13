<?php

namespace Adbot\Channels;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Store facts the Merchant Center data source needs.
 *
 * `contentLanguage` and `feedLabel` are required and immutable on a Google
 * file-input data source, so they are resolved from the store here rather than
 * guessed at by the backend — getting them wrong means creating a second data
 * source that can never be corrected in place.
 */
class Store {

	public static function is_woocommerce_active(): bool {
		return class_exists( 'WooCommerce' );
	}

	/** Two-letter ISO 639-1 language, e.g. `en` from `en_ZA`. */
	public static function content_language(): string {
		$locale = get_locale();
		$code   = strtolower( substr( (string) $locale, 0, 2 ) );
		return 1 === preg_match( '/^[a-z]{2}$/', $code ) ? $code : 'en';
	}

	/**
	 * Feed label — the store's base country as a CLDR territory code.
	 *
	 * Google allows up to 20 uppercase letters, digits and dashes, but the
	 * country code is the conventional value and is what makes the feed target
	 * the right Shopping destination.
	 */
	public static function feed_label(): string {
		$country = '';

		if ( function_exists( 'wc_get_base_location' ) ) {
			$base    = wc_get_base_location();
			$country = is_array( $base ) ? (string) ( $base['country'] ?? '' ) : '';
		}

		$country = strtoupper( trim( $country ) );
		return 1 === preg_match( '/^[A-Z]{2}$/', $country ) ? $country : '';
	}

	/**
	 * IANA time zone for the fetch schedule.
	 *
	 * `wp_timezone_string()` returns a raw UTC offset ("+02:00") on sites
	 * configured by offset rather than city. Google wants a CLDR zone name, so
	 * an offset falls back to UTC instead of being sent and rejected.
	 */
	public static function time_zone(): string {
		$zone = function_exists( 'wp_timezone_string' ) ? (string) wp_timezone_string() : 'UTC';
		return 1 === preg_match( '#^[A-Za-z]+/[A-Za-z_+\-/]+$#', $zone ) ? $zone : 'UTC';
	}

	/**
	 * Whether everything the feed registration needs is in place, with the
	 * reason when it is not so the card can say what to fix.
	 *
	 * `warnings` are non-blocking: the feed can still be registered, but
	 * Google is likely to disapprove items until they are dealt with.
	 *
	 * @return array{ready:bool, reason:string, warnings:string[]}
	 */
	public static function feed_readiness(): array {
		$warnings = self::feed_warnings();

		if ( ! self::is_woocommerce_active() ) {
			return [
				'ready'    => false,
				'reason'   => __( 'WooCommerce is not active, so there are no products to send.', 'adbot-tracking-platform' ),
				'warnings' => $warnings,
			];
		}

		if ( '' === self::feed_label() ) {
			return [
				'ready'    => false,
				'reason'   => __( 'Set your store address country in WooCommerce → Settings → General first.', 'adbot-tracking-platform' ),
				'warnings' => $warnings,
			];
		}

		// Only the scheme matters here: the requirement is that Google can fetch
		// the feed over HTTPS. wp_http_validate_url() also rejects localhost and
		// private hosts, which would have reported a local HTTPS dev site as
		// "not HTTPS" — a misleading message for a different problem.
		if ( 'https' !== strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_SCHEME ) ) ) {
			return [
				'ready'    => false,
				'reason'   => __( 'Your site must be served over HTTPS before Google can fetch the product feed.', 'adbot-tracking-platform' ),
				'warnings' => $warnings,
			];
		}

		return [
			'ready'    => true,
			'reason'   => '',
			'warnings' => $warnings,
		];
	}

	/**
	 * Markets where Google wants prices WITHOUT tax (sales tax is added at
	 * checkout). Everywhere else Google's price spec requires VAT/GST to be
	 * included — South Africa and India among them.
	 * Source: Merchant Center help, "Price [price]" attribute (answer 6324371).
	 */
	private const TAX_EXCLUSIVE_COUNTRIES = [ 'US', 'CA' ];

	/**
	 * Problems that will not stop the feed being registered but will get items
	 * flagged once Google reads it.
	 *
	 * @return string[]
	 */
	private static function feed_warnings(): array {
		$warnings = [];

		if ( ! self::is_woocommerce_active() ) {
			return $warnings;
		}

		// The feed prices items with wc_get_price_to_display(), which follows
		// "Display prices in the shop". Shown excluding tax in a VAT market,
		// the feed sends ex-VAT prices and Google flags every item as a price
		// mismatch against the tax-inclusive price it expects.
		$country = self::feed_label();
		if (
			'' !== $country
			&& function_exists( 'wc_tax_enabled' ) && wc_tax_enabled()
			&& 'excl' === get_option( 'woocommerce_tax_display_shop', 'excl' )
			&& ! in_array( $country, self::TAX_EXCLUSIVE_COUNTRIES, true )
		) {
			$warnings[] = __( 'Your shop shows prices excluding tax, so the product feed does too. Google requires prices including VAT/GST in your country — set WooCommerce → Settings → Tax → "Display prices in the shop" to "Including tax", or items may be disapproved for a price mismatch.', 'adbot-tracking-platform' );
		}

		return $warnings;
	}
}
