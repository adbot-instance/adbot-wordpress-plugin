import { __ } from '@wordpress/i18n';
import { Outlet } from 'react-router-dom';
import ProductPage from '../components/ProductPage';
import UrlTabs from '../components/UrlTabs';

/** Settings: Connections (Google account + services) and General plugin options. */
export default function SettingsPage() {
	return (
		<ProductPage
			icon="dashicons-admin-generic"
			title={ __( 'Settings', 'adbot' ) }
			lede={ __(
				'What is connected, and how the plugin behaves on this site.',
				'adbot'
			) }
		>
			<UrlTabs
				label={ __( 'Settings sections', 'adbot' ) }
				tabs={ [
					{
						to: '/settings',
						label: __( 'Connections', 'adbot' ),
						end: true,
					},
					{
						to: '/settings/general',
						label: __( 'General', 'adbot' ),
					},
				] }
			/>
			<div className="adbot-product__tab">
				<Outlet />
			</div>
		</ProductPage>
	);
}
