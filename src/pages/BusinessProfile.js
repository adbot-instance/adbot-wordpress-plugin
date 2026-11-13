import { __ } from '@wordpress/i18n';
import ProductPage from '../components/ProductPage';
import { gbpState } from '../lib/productStates';

/**
 * Google Business Profile — placeholder until the integration ships. No
 * Google calls are made from here: the Business Profile APIs need Google's
 * approval for API access before Adbot can read or manage a profile.
 */
export default function BusinessProfile() {
	return (
		<ProductPage
			icon="dashicons-store"
			title={ __( 'Business Profile', 'adbot' ) }
			lede={ __( 'Your business on Google Search and Maps.', 'adbot' ) }
			status={ gbpState() }
		>
			<section className="adbot-connect__section adbot-card">
				<h3>{ __( 'Coming in a future release', 'adbot' ) }</h3>
				<p>
					{ __(
						'Adbot will connect your Google Business Profile so you can see how people find your business on Search and Maps — calls, direction requests and website clicks — alongside your other Google services.',
						'adbot'
					) }
				</p>
				<p className="adbot-card__meta">
					{ __(
						'Nothing to set up yet. This page will update when the integration is available.',
						'adbot'
					) }
				</p>
			</section>
		</ProductPage>
	);
}
