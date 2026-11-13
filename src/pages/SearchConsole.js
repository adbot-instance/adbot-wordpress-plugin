import { __ } from '@wordpress/i18n';
import ProductPage, { productLogo } from '../components/ProductPage';
import SearchConsoleChannel from '../channels/SearchConsoleChannel';
import InsightsPanel from '../components/insights/InsightsPanel';
import useStatus from '../hooks/useStatus';
import { gscState } from '../lib/productStates';

/**
 * Google Search Console: ownership, sitemap, and how the site performs in
 * Google Search (clicks, impressions, CTR, position, top queries and pages).
 */
export default function SearchConsole() {
	const { status, loading, reload } = useStatus();
	if ( loading && ! status ) {
		return <p>{ __( 'Loading…', 'adbot' ) }</p>;
	}
	const state = gscState( status );
	return (
		<ProductPage
			logoUrl={ productLogo( 'gsc' ) }
			title={ __( 'Search Console', 'adbot' ) }
			lede={ __(
				'How your site shows up in Google Search, and keeping Google up to date with your pages.',
				'adbot'
			) }
			status={ state }
		>
			<SearchConsoleChannel
				status={ status }
				onStatusChange={ reload }
				embedded
			/>
			<InsightsPanel product="gsc" enabled={ state.ready } />
		</ProductPage>
	);
}
