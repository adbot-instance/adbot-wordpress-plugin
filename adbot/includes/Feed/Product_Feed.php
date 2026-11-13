<?php

namespace Adbot\Feed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Streams a WooCommerce catalogue as a Google Merchant Center product feed.
 *
 * RSS 2.0 with Google's `g:` namespace — the format Merchant Center's scheduled
 * fetch expects.
 *
 * Streamed rather than assembled: a catalogue of any size has to fit in a
 * response, not in memory, so items are echoed as they are built and products
 * are paged in small batches with the object cache suspended. Nothing here
 * buffers the whole feed.
 */
class Product_Feed {

	/** Products per query. Small enough to bound peak memory per batch. */
	private const BATCH_SIZE = 200;

	/** Merchant Center accepts at most 10 additional images per item. */
	private const MAX_ADDITIONAL_IMAGES = 10;

	/** Google accepts an availability_date at most one year ahead. */
	private const MAX_AVAILABILITY_DAYS = 365;

	/** Product meta a merchant (or another plugin) can set to a restock date. */
	public const AVAILABILITY_DATE_META = '_adbot_availability_date';

	/**
	 * g:id values already written in this render, as a set (id => true).
	 *
	 * Google treats a repeated g:id as one item and drops or flags the rest, so
	 * a SKU that two products share must not be emitted twice. Per render, not
	 * persisted: the cost is one array key per item, which is negligible next
	 * to the product objects loaded alongside it.
	 *
	 * @var array<string, true>
	 */
	private $emitted_ids = [];

	public function render(): void {
		// A big catalogue legitimately takes a while; the alternative is a
		// truncated feed, which Merchant Center reads as mass item removal.
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		// Without this, every product loaded below is retained in the object
		// cache for the life of the request and memory grows with the catalogue.
		wp_suspend_cache_addition( true );

		$this->emitted_ids = [];

		$this->open();

		$page  = 1;
		$count = 0;
		do {
			$ids = $this->fetch_product_ids( $page );
			foreach ( $ids as $product_id ) {
				$count += $this->render_product( (int) $product_id );
			}

			$this->flush();
			++$page;
		} while ( count( $ids ) === self::BATCH_SIZE );

		$this->close( $count );

		wp_suspend_cache_addition( false );
	}

	/**
	 * @return int[] Parent product IDs for this page.
	 */
	private function fetch_product_ids( int $page ): array {
		$args = [
			'status'   => 'publish',
			'limit'    => self::BATCH_SIZE,
			'page'     => $page,
			'orderby'  => 'ID',
			'order'    => 'ASC',
			'return'   => 'ids',
			// No `type` filter on purpose. wc_get_products() queries the
			// `product` post type, and variations are `product_variation`, so
			// they are already excluded — they are reached through their parent
			// instead, which is what keeps each variable product one item
			// group. Passing every type explicitly would only add a pointless
			// tax_query to a paged query that runs across the whole catalogue.
		];

		/**
		 * Filter the product query behind the Merchant Center feed.
		 *
		 * @param array $args wc_get_products() arguments.
		 */
		$args = (array) apply_filters( 'adbot_product_feed_query_args', $args );

		$ids = wc_get_products( $args );
		return is_array( $ids ) ? $ids : [];
	}

	/**
	 * Emit every sellable item for one product.
	 *
	 * @return int Number of items written.
	 */
	private function render_product( int $product_id ): int {
		$product = wc_get_product( $product_id );
		if ( ! $product || ! $this->is_includable( $product ) ) {
			return 0;
		}

		$written = 0;

		if ( $product->is_type( 'variable' ) ) {
			foreach ( $product->get_children() as $variation_id ) {
				$variation = wc_get_product( $variation_id );
				if ( ! $variation || ! $this->is_includable( $variation ) ) {
					continue;
				}
				$written += $this->render_item( $variation, $product );
			}

			return $written;
		}

		return $this->render_item( $product, null );
	}

	/**
	 * Whether an item belongs in the feed at all.
	 *
	 * Hidden products and priceless ones (external/grouped parents, "call for
	 * price" setups) are dropped here rather than sent for Google to disapprove.
	 */
	private function is_includable( \WC_Product $product ): bool {
		if ( 'hidden' === $product->get_catalog_visibility() ) {
			return false;
		}
		if ( ! $product->is_visible() && ! $product->is_type( 'variation' ) ) {
			return false;
		}
		if ( '' === (string) $product->get_price() ) {
			return false;
		}

		/**
		 * Filter whether a product is included in the Merchant Center feed.
		 *
		 * @param bool       $include Whether to include it.
		 * @param WC_Product $product The product or variation.
		 */
		return (bool) apply_filters( 'adbot_product_feed_include', true, $product );
	}

	/**
	 * @return int 1 when an item was written, 0 otherwise.
	 */
	private function render_item( \WC_Product $product, ?\WC_Product $parent ): int {
		$item = $this->build_item( $product, $parent );

		/**
		 * Filter one feed item before it is written.
		 *
		 * Values are escaped on output, so filters supply plain text. Returning
		 * an empty array skips the item.
		 *
		 * @param array      $item    Tag name => value (scalar or array).
		 * @param WC_Product $product The product or variation.
		 * @param WC_Product|null $parent Parent product for a variation.
		 */
		$item = (array) apply_filters( 'adbot_product_feed_item', $item, $product, $parent );

		if ( empty( $item ) ) {
			return 0;
		}

		// Recorded after the filter so the set holds the id actually written.
		if ( isset( $item['g:id'] ) && is_scalar( $item['g:id'] ) ) {
			$this->emitted_ids[ (string) $item['g:id'] ] = true;
		}

		echo "\t\t<item>\n";
		foreach ( $item as $tag => $value ) {
			foreach ( is_array( $value ) ? $value : [ $value ] as $single ) {
				if ( '' === $single || null === $single ) {
					continue;
				}
				printf(
					"\t\t\t<%1\$s>%2\$s</%1\$s>\n",
					esc_html( $tag ),
					$this->cdata( (string) $single ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- cdata() escapes.
				);
			}
		}
		echo "\t\t</item>\n";

		return 1;
	}

	private function build_item( \WC_Product $product, ?\WC_Product $parent ): array {
		$display  = $parent ?? $product;
		$currency = get_woocommerce_currency();

		$availability = $this->availability( $product, $parent );

		$item = [
			'g:id'           => $this->item_id( $product ),
			'title'          => $this->title( $product, $parent ),
			'description'    => $this->description( $product, $display ),
			'link'           => $product->get_permalink(),
			'g:condition'    => $this->condition( $product, $parent ),
			'g:availability' => $availability['status'],
			'g:price'        => $this->format_price( $this->regular_price( $product ), $currency ),
		];

		if ( '' !== $availability['date'] ) {
			$item['g:availability_date'] = $availability['date'];
		}

		$sale = $this->sale( $product );
		if ( null !== $sale ) {
			$item['g:sale_price'] = $this->format_price( $sale['price'], $currency );
			if ( '' !== $sale['effective'] ) {
				$item['g:sale_price_effective_date'] = $sale['effective'];
			}
		}

		$image = $this->image_url( $product, $display );
		if ( '' !== $image ) {
			$item['g:image_link'] = $image;
		}

		$additional = $this->additional_image_urls( $display, $image );
		if ( ! empty( $additional ) ) {
			$item['g:additional_image_link'] = $additional;
		}

		if ( $parent ) {
			// Ties variations together so Google shows one product with
			// variants rather than N unrelated listings.
			$item['g:item_group_id'] = (string) $parent->get_id();
		}

		$brand = $this->brand( $display );
		if ( '' !== $brand ) {
			$item['g:brand'] = $brand;
		}

		$gtin = $this->gtin( $product, $display );
		if ( '' !== $gtin ) {
			$item['g:gtin'] = $gtin;
		}

		$mpn = $this->mpn( $product, $display );
		if ( '' !== $mpn ) {
			$item['g:mpn'] = $mpn;
		}

		// Google's qualifying identifiers for a new product are a GTIN, or a
		// brand + MPN pair. Without either, the spec asks for an explicit
		// identifier_exists=no (it is "recommended" rather than required, but
		// an item with no identifiers and no flag is limited in Shopping).
		// Google also warns when "no" is sent for a product it can find an
		// identifier for — e.g. a branded product sold without its GTIN — so
		// the decision is filterable per product.
		$identifier_exists = ! ( '' === $gtin && ( '' === $brand || '' === $mpn ) );

		/**
		 * Filter whether the product has unique product identifiers.
		 *
		 * Return false (or 'no') to send `g:identifier_exists` = no; anything
		 * else omits the attribute, which Google reads as "yes". Only send no
		 * when the product genuinely has no GTIN/MPN assigned (custom,
		 * handmade, vintage goods), or Merchant Center raises a warning.
		 *
		 * @param bool            $identifier_exists Adbot's default decision.
		 * @param WC_Product      $product           The product or variation.
		 * @param WC_Product|null $parent            Parent product for a variation.
		 */
		$identifier_exists = apply_filters( 'adbot_product_feed_identifier_exists', $identifier_exists, $product, $parent );
		if ( false === $identifier_exists || ( is_string( $identifier_exists ) && in_array( strtolower( trim( $identifier_exists ) ), [ 'no', 'false' ], true ) ) ) {
			$item['g:identifier_exists'] = 'no';
		}

		$product_type = $this->product_type( $display );
		if ( '' !== $product_type ) {
			$item['g:product_type'] = $product_type;
		}

		$weight = $this->shipping_weight( $product );
		if ( '' !== $weight ) {
			$item['g:shipping_weight'] = $weight;
		}

		foreach ( $this->variant_attributes( $product ) as $tag => $value ) {
			$item[ $tag ] = $value;
		}

		return $item;
	}

	/**
	 * Stable per-item identifier.
	 *
	 * SKU when there is one, because that is what the merchant recognises in
	 * Merchant Center. The `wc-` prefixed id is the fallback and is unique per
	 * post; a SKU is not, so a SKU already written earlier in this render falls
	 * back too (see $emitted_ids). The first product to carry a shared SKU
	 * keeps it, and products are walked in ID order, so which one that is
	 * stays stable from one fetch to the next.
	 */
	private function item_id( \WC_Product $product ): string {
		// A variation's get_sku() in the default 'view' context inherits the
		// PARENT's SKU when its own is empty, which would give every SKU-less
		// variation of a product the same g:id. 'edit' reads only its own.
		$sku = $product->is_type( 'variation' )
			? (string) $product->get_sku( 'edit' )
			: (string) $product->get_sku();
		$sku = trim( $sku );

		if ( '' !== $sku && ! isset( $this->emitted_ids[ $sku ] ) ) {
			return $sku;
		}

		return 'wc-' . $product->get_id();
	}

	private function title( \WC_Product $product, ?\WC_Product $parent ): string {
		if ( $parent ) {
			// get_name() on a variation already reads "Parent - Blue, Large".
			$name = $product->get_name();
			return '' !== $name ? $name : $parent->get_name();
		}
		return $product->get_name();
	}

	private function description( \WC_Product $product, \WC_Product $display ): string {
		$candidates = [
			$product->get_description(),
			$display->get_description(),
			$display->get_short_description(),
			$display->get_name(),
		];

		foreach ( $candidates as $candidate ) {
			$text = trim( wp_strip_all_tags( (string) $candidate, true ) );
			if ( '' !== $text ) {
				// Merchant Center truncates past 5000 characters anyway.
				return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 5000 ) : substr( $text, 0, 5000 );
			}
		}

		return '';
	}

	/**
	 * Availability, plus the restock date Google requires alongside backorder.
	 *
	 * Merchant Center rejects `backorder` without `availability_date`, and
	 * WooCommerce has no native restock date. So backorder is only claimed when
	 * a valid date is known; otherwise the item is sent as `out_of_stock`,
	 * which is accurate (it cannot ship now) and never disapproved.
	 *
	 * @return array{status: string, date: string} Date is '' when not sent.
	 */
	private function availability( \WC_Product $product, ?\WC_Product $parent ): array {
		if ( $product->is_on_backorder( 1 ) ) {
			$date = $this->availability_date( $product, $parent );
			if ( '' !== $date ) {
				return [
					'status' => 'backorder',
					'date'   => $date,
				];
			}
			return [
				'status' => 'out_of_stock',
				'date'   => '',
			];
		}

		return [
			'status' => $product->is_in_stock() ? 'in_stock' : 'out_of_stock',
			'date'   => '',
		];
	}

	/**
	 * Restock date for a backordered item, as ISO 8601 with offset, or '' when
	 * none is known or the one given is unusable.
	 *
	 * Read from the variation's own meta first, then the parent's, then passed
	 * through a filter so a stock/ERP plugin can supply it instead.
	 */
	private function availability_date( \WC_Product $product, ?\WC_Product $parent ): string {
		$raw = '';
		foreach ( array_filter( [ $product, $parent ] ) as $source ) {
			$raw = trim( (string) $source->get_meta( self::AVAILABILITY_DATE_META ) );
			if ( '' !== $raw ) {
				break;
			}
		}

		/**
		 * Filter the restock date sent with a backordered item.
		 *
		 * Any string DateTime can parse (e.g. "2026-12-01" or a full ISO 8601
		 * timestamp), a compact Ymd date ("20261201"), or a Unix timestamp; a
		 * bare date is read in the site's timezone. Return '' to
		 * send the item as out_of_stock instead. Dates in the past or more than
		 * a year ahead are ignored, since Google rejects them.
		 *
		 * @param string     $date    Date from `_adbot_availability_date`, or ''.
		 * @param WC_Product $product The product or variation.
		 */
		$raw = trim( (string) apply_filters( 'adbot_product_feed_availability_date', $raw, $product ) );
		if ( '' === $raw ) {
			return '';
		}

		$timezone = wp_timezone();
		try {
			if ( 8 === strlen( $raw ) && ctype_digit( $raw ) ) {
				// Compact Ymd ("20261201"), as ACF's date picker stores it. Must be
				// checked before the timestamp branch, which would otherwise read
				// it as a 1970 Unix time and silently drop the date.
				$date = \DateTimeImmutable::createFromFormat( '!Ymd', $raw, $timezone );
				if ( ! $date || $date->format( 'Ymd' ) !== $raw ) {
					return '';
				}
			} elseif ( ctype_digit( $raw ) ) {
				$date = ( new \DateTimeImmutable( '@' . $raw ) )->setTimezone( $timezone );
			} else {
				$date = new \DateTimeImmutable( $raw, $timezone );
			}
		} catch ( \Exception $e ) {
			return '';
		}

		$now    = new \DateTimeImmutable( 'now', $timezone );
		$latest = $now->modify( '+' . self::MAX_AVAILABILITY_DAYS . ' days' );
		if ( $date <= $now || $date > $latest ) {
			return '';
		}

		return $date->format( DATE_ATOM );
	}

	/**
	 * Prices are taken as displayed, so the feed matches what a shopper sees at
	 * checkout — Google compares the two and disapproves a mismatch.
	 */
	private function regular_price( \WC_Product $product ): float {
		$regular = (string) $product->get_regular_price();
		$base    = '' !== $regular ? $regular : (string) $product->get_price();
		return (float) wc_get_price_to_display( $product, [ 'price' => $base ] );
	}

	/**
	 * Sale price, with the window it applies in when that window is known.
	 *
	 * Merchant Center fetches the feed once a day, so a sale that ends (or
	 * starts) between fetches would otherwise leave the feed disagreeing with
	 * the checkout price until the next fetch — which Google disapproves as a
	 * price mismatch. `g:sale_price_effective_date` lets Google switch the
	 * price itself at the right moment.
	 *
	 * Dates are read from the item itself only. A variation carries its own
	 * sale schedule alongside its own sale price, and the variable parent has
	 * none, so pairing a parent's dates with a variation's price would only
	 * ever be wrong.
	 *
	 * Cases (Google requires both ends of the interval):
	 * - On sale now, end date known: "start/end", start being the scheduled
	 *   start or, when there is none, the start of today in the site timezone.
	 * - On sale now, no end date: sale price alone — it runs until removed,
	 *   and the next fetch picks up the change.
	 * - Scheduled to start later, with an end date: sent ahead of time with
	 *   its window, so Google starts the sale when WooCommerce does instead of
	 *   up to a day late. Google applies g:sale_price only inside the window.
	 * - Scheduled to start later, no end date: omitted. Without an interval an
	 *   undated sale price would be applied immediately, before the store
	 *   charges it.
	 *
	 * @return array{price: float, effective: string}|null Null when no sale price is sent.
	 */
	private function sale( \WC_Product $product ): ?array {
		$sale = (string) $product->get_sale_price();
		if ( '' === $sale ) {
			return null;
		}

		$from = $product->get_date_on_sale_from();
		$to   = $product->get_date_on_sale_to();
		$now  = time();

		$from_ts = $from ? $from->getTimestamp() : 0;
		$to_ts   = $to ? $to->getTimestamp() : 0;

		if ( $product->is_on_sale() ) {
			$effective = '';
			if ( $to_ts > $now ) {
				$start     = $from_ts > 0 && $from_ts <= $now
					? $from_ts
					: ( new \DateTimeImmutable( 'today', wp_timezone() ) )->getTimestamp();
				$effective = $this->sale_interval( $start, $to_ts );
			}
		} else {
			// Not on sale today: only a future, fully dated schedule qualifies.
			if ( $from_ts <= $now || $to_ts <= $from_ts ) {
				return null;
			}

			// is_on_sale() is what normally guarantees sale < regular; it has
			// returned false here for the date, so check the price directly.
			$regular = (string) $product->get_regular_price();
			if ( '' === $regular || (float) $sale >= (float) $regular ) {
				return null;
			}

			$effective = $this->sale_interval( $from_ts, $to_ts );
		}

		return [
			'price'     => (float) wc_get_price_to_display( $product, [ 'price' => $sale ] ),
			'effective' => $effective,
		];
	}

	/**
	 * ISO 8601 interval in the site timezone, in the form Google documents
	 * (YYYY-MM-DDThh:mm±hhmm/YYYY-MM-DDThh:mm±hhmm). Converted from the Unix
	 * timestamp rather than formatting the WC_DateTime as stored, so the
	 * offset always reflects the site's configured zone.
	 */
	private function sale_interval( int $start, int $end ): string {
		$timezone = wp_timezone();
		$format   = 'Y-m-d\TH:iO';

		return ( new \DateTimeImmutable( '@' . $start ) )->setTimezone( $timezone )->format( $format )
			. '/'
			. ( new \DateTimeImmutable( '@' . $end ) )->setTimezone( $timezone )->format( $format );
	}

	/**
	 * Item condition. WooCommerce has no condition field, so everything is
	 * `new` unless a merchant selling refurbished or second-hand stock says
	 * otherwise through the filter.
	 */
	private function condition( \WC_Product $product, ?\WC_Product $parent ): string {
		/**
		 * Filter the Merchant Center condition of a product.
		 *
		 * Google accepts only `new`, `refurbished` or `used`; any other value
		 * falls back to `new`.
		 *
		 * @param string          $condition Default 'new'.
		 * @param WC_Product      $product   The product or variation.
		 * @param WC_Product|null $parent    Parent product for a variation.
		 */
		$condition = strtolower( trim( (string) apply_filters( 'adbot_product_feed_condition', 'new', $product, $parent ) ) );

		return in_array( $condition, [ 'new', 'refurbished', 'used' ], true ) ? $condition : 'new';
	}

	private function format_price( float $amount, string $currency ): string {
		// Google wants a plain "12.34 ZAR", never a formatted HTML price.
		return sprintf( '%s %s', number_format( $amount, wc_get_price_decimals(), '.', '' ), $currency );
	}

	private function image_url( \WC_Product $product, \WC_Product $display ): string {
		foreach ( [ $product->get_image_id(), $display->get_image_id() ] as $image_id ) {
			if ( ! $image_id ) {
				continue;
			}
			$url = wp_get_attachment_url( (int) $image_id );
			if ( $url ) {
				return $url;
			}
		}
		return '';
	}

	private function additional_image_urls( \WC_Product $display, string $exclude ): array {
		$urls = [];
		foreach ( $display->get_gallery_image_ids() as $image_id ) {
			if ( count( $urls ) >= self::MAX_ADDITIONAL_IMAGES ) {
				break;
			}
			$url = wp_get_attachment_url( (int) $image_id );
			if ( $url && $url !== $exclude ) {
				$urls[] = $url;
			}
		}
		return $urls;
	}

	/**
	 * Brand, preferring WooCommerce's own `product_brand` taxonomy (core since
	 * WooCommerce 9.4) and falling back to the conventional meta keys older
	 * feed plugins established.
	 */
	private function brand( \WC_Product $display ): string {
		$terms = get_the_terms( $display->get_id(), 'product_brand' );
		if ( is_array( $terms ) && ! empty( $terms ) ) {
			return (string) $terms[0]->name;
		}

		foreach ( [ '_brand', 'brand' ] as $key ) {
			$value = trim( (string) $display->get_meta( $key ) );
			if ( '' !== $value ) {
				return $value;
			}
		}

		$attribute = trim( (string) $display->get_attribute( 'brand' ) );
		return '' !== $attribute ? $attribute : '';
	}

	/**
	 * GTIN, preferring WooCommerce's native global unique id field (9.2+) over
	 * the ad-hoc meta keys in circulation.
	 */
	private function gtin( \WC_Product $product, \WC_Product $display ): string {
		foreach ( [ $product, $display ] as $source ) {
			if ( method_exists( $source, 'get_global_unique_id' ) ) {
				$value = trim( (string) $source->get_global_unique_id() );
				if ( '' !== $value ) {
					return $value;
				}
			}
			foreach ( [ '_gtin', '_ean', '_upc', '_isbn' ] as $key ) {
				$value = trim( (string) $source->get_meta( $key ) );
				if ( '' !== $value ) {
					return $value;
				}
			}
		}
		return '';
	}

	private function mpn( \WC_Product $product, \WC_Product $display ): string {
		foreach ( [ $product, $display ] as $source ) {
			foreach ( [ '_mpn', 'mpn' ] as $key ) {
				$value = trim( (string) $source->get_meta( $key ) );
				if ( '' !== $value ) {
					return $value;
				}
			}
		}
		return '';
	}

	/** The merchant's own category path, e.g. "Clothing > Shirts". */
	private function product_type( \WC_Product $display ): string {
		$terms = get_the_terms( $display->get_id(), 'product_cat' );
		if ( ! is_array( $terms ) || empty( $terms ) ) {
			return '';
		}

		$term   = $terms[0];
		$names  = [ $term->name ];
		$parent = (int) $term->parent;
		$guard  = 0;

		// Walk up to the root, with a guard against a corrupted term hierarchy
		// looping forever inside a streaming response.
		while ( $parent > 0 && $guard < 10 ) {
			$ancestor = get_term( $parent, 'product_cat' );
			if ( ! $ancestor || is_wp_error( $ancestor ) ) {
				break;
			}
			array_unshift( $names, $ancestor->name );
			$parent = (int) $ancestor->parent;
			++$guard;
		}

		return implode( ' > ', $names );
	}

	private function shipping_weight( \WC_Product $product ): string {
		$weight = trim( (string) $product->get_weight() );
		if ( '' === $weight ) {
			return '';
		}

		$unit = strtolower( (string) get_option( 'woocommerce_weight_unit', 'kg' ) );
		// Google accepts lb/oz/g/kg only; anything else is left off rather than
		// sent with a unit that gets the item disapproved.
		if ( ! in_array( $unit, [ 'lb', 'oz', 'g', 'kg' ], true ) ) {
			return '';
		}

		return $weight . ' ' . $unit;
	}

	/**
	 * Map a variation's attributes onto Google's dedicated variant fields.
	 * Anything outside this set is already covered by the title.
	 */
	private function variant_attributes( \WC_Product $product ): array {
		if ( ! $product->is_type( 'variation' ) || ! method_exists( $product, 'get_variation_attributes' ) ) {
			return [];
		}

		$supported = [
			'color'    => 'g:color',
			'colour'   => 'g:color',
			'size'     => 'g:size',
			'material' => 'g:material',
			'pattern'  => 'g:pattern',
		];

		$out = [];
		foreach ( (array) $product->get_variation_attributes() as $name => $value ) {
			$value = trim( (string) $value );
			if ( '' === $value ) {
				continue;
			}

			// Keys arrive as attribute_pa_color (a global attribute, backed by
			// a `pa_*` taxonomy) or attribute_size (a per-product one, whose
			// value is already the label).
			$raw      = (string) $name;
			$taxonomy = 0 === strpos( $raw, 'attribute_pa_' ) ? substr( $raw, strlen( 'attribute_' ) ) : '';
			$slug     = strtolower( preg_replace( '/^attribute_(pa_)?/', '', $raw ) );

			if ( isset( $supported[ $slug ] ) && ! isset( $out[ $supported[ $slug ] ] ) ) {
				$out[ $supported[ $slug ] ] = $this->attribute_label( $value, $taxonomy );
			}
		}

		return $out;
	}

	/**
	 * Turn a term slug back into its human label.
	 *
	 * The taxonomy has to come from the attribute itself — looking every value
	 * up against one hardcoded taxonomy meant a size slug like `extra-large`
	 * was title-cased to "Extra Large" instead of returning the merchant's
	 * actual label ("XL"), which is what Google matches variants on.
	 */
	private function attribute_label( string $value, string $taxonomy ): string {
		if ( '' !== $taxonomy && taxonomy_exists( $taxonomy ) ) {
			$term = get_term_by( 'slug', $value, $taxonomy );
			if ( $term && ! is_wp_error( $term ) ) {
				return (string) $term->name;
			}
		}

		// Per-product attributes store the label directly, so only a slug-like
		// value needs prettifying.
		return ucwords( str_replace( [ '-', '_' ], ' ', $value ) );
	}

	private function open(): void {
		$title = get_bloginfo( 'name' );
		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">' . "\n";
		echo "\t<channel>\n";
		printf( "\t\t<title>%s</title>\n", $this->cdata( (string) $title ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- cdata() escapes.
		printf( "\t\t<link>%s</link>\n", esc_url( home_url( '/' ) ) );
		printf(
			"\t\t<description>%s</description>\n",
			$this->cdata( __( 'Product feed generated by Adbot for Google Merchant Center.', 'adbot-tracking-platform' ) ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- cdata() escapes.
		);
	}

	private function close( int $count ): void {
		// A comment, not an element: Merchant Center ignores it, and it makes a
		// truncated feed obvious when debugging a fetch.
		printf( "\t\t<!-- adbot:items=%d -->\n", (int) $count );
		echo "\t</channel>\n";
		echo "</rss>\n";
		$this->flush();
	}

	/**
	 * CDATA-wrap a value. Product titles and descriptions routinely contain
	 * ampersands and quotes, and `]]>` inside one would otherwise end the
	 * section early and corrupt the document.
	 */
	private function cdata( string $value ): string {
		$value = str_replace( ']]>', ']]]]><![CDATA[>', $value );
		// Strip control characters XML 1.0 forbids outright.
		$value = preg_replace( '/[^\x09\x0A\x0D\x20-\x{10FFFF}]/u', '', $value );
		return '<![CDATA[' . $value . ']]>';
	}

	/**
	 * Push what we have to the client so the response streams instead of
	 * buffering the whole catalogue. Guarded because a host may have no
	 * buffering active, or have output buffering disabled entirely.
	 */
	private function flush(): void {
		if ( ob_get_level() > 0 ) {
			@ob_flush(); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		flush();
	}
}
