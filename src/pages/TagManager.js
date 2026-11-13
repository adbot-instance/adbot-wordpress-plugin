import { __ } from '@wordpress/i18n';
import { Outlet } from 'react-router-dom';
import ProductPage, { productLogo } from '../components/ProductPage';
import UrlTabs from '../components/UrlTabs';
import GtmContainer from '../components/GtmContainer';
import useStatus from '../hooks/useStatus';
import { gtmState } from '../lib/productStates';

/**
 * Google Tag Manager: the container and its snippet, the tracking audit, and
 * the automated fixes, as URL tabs.
 */
export default function TagManager() {
	const { status } = useStatus();
	return (
		<ProductPage
			logoUrl={ productLogo( 'gtm' ) }
			title={ __( 'Tag Manager', 'adbot' ) }
			lede={ __(
				'Install your container, audit its tracking and fix what the audit finds.',
				'adbot'
			) }
			status={ status ? gtmState( status ) : null }
		>
			<UrlTabs
				label={ __( 'Tag Manager sections', 'adbot' ) }
				tabs={ [
					{
						to: '/tag-manager',
						label: __( 'Container', 'adbot' ),
						end: true,
					},
					{ to: '/tag-manager/audit', label: __( 'Audit', 'adbot' ) },
					{
						to: '/tag-manager/fixes',
						label: __( 'Fix issues', 'adbot' ),
					},
				] }
			/>
			<div className="adbot-product__tab">
				<Outlet />
			</div>
		</ProductPage>
	);
}

/** Index tab: container picker / installed snippet. */
export function TagManagerContainer() {
	const { status, loading, reload } = useStatus();
	if ( loading && ! status ) {
		return <p>{ __( 'Loading…', 'adbot' ) }</p>;
	}
	return <GtmContainer status={ status } onChange={ reload } />;
}
