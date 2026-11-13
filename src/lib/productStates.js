import { __ } from '@wordpress/i18n';

/**
 * One place that turns GET /status into each Google product's state, so the
 * Overview previews, the product page headers and Settings → Connections never
 * disagree. Tones follow the shared design system:
 * success = working, warning = needs attention, accent = ready to connect,
 * neutral = coming soon / not applicable.
 *
 * Each returns { tone, label, ready, hint, to }:
 * - ready: the product is set up enough to chart.
 * - hint:  one plain sentence for previews.
 * - to:    the page to manage it.
 */

const granted = ( status, set ) =>
	Array.isArray( status?.googleScopeSets ) &&
	status.googleScopeSets.includes( set );

export function ga4State( status ) {
	if ( status?.ga4PropertyId ) {
		return {
			tone: 'success',
			label: __( 'Reporting', 'adbot' ),
			ready: true,
			hint: status.ga4PropertyName || status.ga4MeasurementId || '',
			to: '/analytics',
		};
	}
	if ( status?.ga4MeasurementId ) {
		return {
			tone: 'warning',
			label: __( 'Choose property', 'adbot' ),
			ready: false,
			hint: __(
				'Choose your GA4 property again to turn on charts.',
				'adbot'
			),
			to: '/analytics',
		};
	}
	return {
		tone: 'accent',
		label: __( 'Ready to connect', 'adbot' ),
		ready: false,
		hint: __( 'Choose the GA4 property for this site.', 'adbot' ),
		to: '/analytics',
	};
}

export function gtmState( status ) {
	if ( status?.snippetActive ) {
		return {
			tone: 'success',
			label: __( 'Snippet active', 'adbot' ),
			ready: true,
			hint: status.snippetContainerId || '',
			to: '/tag-manager',
		};
	}
	if ( status?.snippetExternalDetected ) {
		return {
			tone: 'info',
			label: __( 'Installed elsewhere', 'adbot' ),
			ready: true,
			hint: status.snippetContainerId || '',
			to: '/tag-manager',
		};
	}
	return {
		tone: 'warning',
		label: __( 'Not installed', 'adbot' ),
		ready: false,
		hint: __( 'Choose a container to install the GTM snippet.', 'adbot' ),
		to: '/tag-manager',
	};
}

export function gscState( status ) {
	if ( status?.gscSitemapSubmittedAt ) {
		return {
			tone: 'success',
			label: __( 'Sitemap submitted', 'adbot' ),
			ready: true,
			hint: status.gscSiteUrl || '',
			to: '/search-console',
		};
	}
	if ( status?.gscSiteUrl ) {
		return {
			tone: 'warning',
			label: __( 'Sitemap not submitted', 'adbot' ),
			ready: true,
			hint: status.gscSiteUrl,
			to: '/search-console',
		};
	}
	if ( granted( status, 'gsc' ) ) {
		return {
			tone: 'warning',
			label: __( 'Choose a property', 'adbot' ),
			ready: false,
			hint: __(
				'Verify this site and choose its Search Console property.',
				'adbot'
			),
			to: '/search-console',
		};
	}
	return {
		tone: 'accent',
		label: __( 'Ready to connect', 'adbot' ),
		ready: false,
		hint: __(
			'Send your sitemap to Google and see how people find you in search.',
			'adbot'
		),
		to: '/search-console',
	};
}

export function gmcState( status ) {
	if ( ! status?.wooActive ) {
		return {
			tone: 'neutral',
			label: __( 'WooCommerce required', 'adbot' ),
			ready: false,
			hint: __(
				'Activate WooCommerce to publish products to Google Shopping.',
				'adbot'
			),
			to: '/merchant-center',
		};
	}
	if ( status?.gmcFeedRegisteredAt ) {
		return {
			tone: 'success',
			label: __( 'Feed live', 'adbot' ),
			ready: true,
			hint: status.gmcAccountLabel || '',
			to: '/merchant-center',
		};
	}
	if ( status?.gmcAccountName ) {
		return {
			tone: 'warning',
			label: __( 'Feed not registered', 'adbot' ),
			ready: true,
			hint: status.gmcAccountLabel || '',
			to: '/merchant-center',
		};
	}
	return {
		tone: 'accent',
		label: __( 'Ready to connect', 'adbot' ),
		ready: false,
		hint: __(
			'Publish your WooCommerce products to Google Shopping.',
			'adbot'
		),
		to: '/merchant-center',
	};
}

export function gbpState() {
	return {
		tone: 'neutral',
		label: __( 'Coming soon', 'adbot' ),
		ready: false,
		hint: __(
			'Manage your Google Business Profile from WordPress in a coming release.',
			'adbot'
		),
		to: '/business-profile',
	};
}
