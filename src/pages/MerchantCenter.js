import { __ } from '@wordpress/i18n';
import ProductPage, { productLogo } from '../components/ProductPage';
import MerchantCenterChannel from '../channels/MerchantCenterChannel';
import InsightsPanel from '../components/insights/InsightsPanel';
import useStatus from '../hooks/useStatus';
import { gmcState } from '../lib/productStates';

/**
 * Google Merchant Center: the WooCommerce product feed, and how the catalogue
 * is doing in Google (product statuses and, where available, performance).
 */
export default function MerchantCenter() {
	const { status, loading, reload } = useStatus();
	if ( loading && ! status ) {
		return <p>{ __( 'Loading…', 'adbot' ) }</p>;
	}
	const state = gmcState( status );
	return (
		<ProductPage
			logoUrl={ productLogo( 'gmc' ) }
			title={ __( 'Merchant Center', 'adbot' ) }
			lede={ __(
				'Your WooCommerce products on Google Shopping.',
				'adbot'
			) }
			status={ state }
		>
			<MerchantCenterChannel
				status={ status }
				onStatusChange={ reload }
				embedded
			/>
			<InsightsPanel product="gmc" enabled={ state.ready } />
		</ProductPage>
	);
}
