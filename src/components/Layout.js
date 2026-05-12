import { useEffect } from '@wordpress/element';
import { NavLink, Outlet, useNavigate, useLocation } from 'react-router-dom';
import { __ } from '@wordpress/i18n';
import { useGoogleConnection } from '../hooks/useGoogleConnection';

function getNavItems() {
	return [
		{ path: '/', label: __( 'Dashboard', 'adbot' ), icon: 'dashicons-dashboard' },
		{ path: '/connect', label: __( 'Connect', 'adbot' ), icon: 'dashicons-admin-links' },
		{ path: '/audit', label: __( 'Audit', 'adbot' ), icon: 'dashicons-search' },
		{ path: '/setup', label: __( 'Fix Issues', 'adbot' ), icon: 'dashicons-admin-tools' },
		{ path: '/settings', label: __( 'Settings', 'adbot' ), icon: 'dashicons-admin-generic' },
	];
}

const LOCKED_PATHS = [ '/connect', '/audit', '/setup', '/settings' ];

function getBrand() {
	if ( typeof window === 'undefined' || ! window.adbotAdmin?.brand ) {
		return { name: __( 'Tracking', 'adbot' ), logoUrl: '', tagline: '' };
	}
	return window.adbotAdmin.brand;
}

export default function Layout() {
	const { connected, loading } = useGoogleConnection();
	const navigate = useNavigate();
	const location = useLocation();
	const brand = getBrand();
	const navLocked = ! loading && ! connected;
	const navItems = getNavItems();

	useEffect( () => {
		if ( loading || connected ) {
			return;
		}
		const path = location.pathname;
		if ( LOCKED_PATHS.includes( path ) ) {
			navigate( '/', { replace: true } );
		}
	}, [ loading, connected, location.pathname, navigate ] );

	return (
		<div className="adbot-admin">
			<div className="adbot-admin__header">
				<div className="adbot-admin__identity">
					{ brand.logoUrl ? (
						<img
							src={ brand.logoUrl }
							alt=""
							className="adbot-admin__identity-logo"
							width={ 48 }
							height={ 48 }
						/>
					) : null }
					<div className="adbot-admin__identity-text">
						<h1 className="adbot-admin__title">{ brand.name }</h1>
						{ brand.tagline ? (
							<p className="adbot-admin__tagline">{ brand.tagline }</p>
						) : null }
					</div>
				</div>
			</div>
			<div className="adbot-admin__body">
				<nav className="adbot-admin__sidebar" aria-label={ __( 'Adbot plugin sections', 'adbot' ) }>
					<ul>
						{ navItems.map( ( item ) => {
							const isDashboard = item.path === '/';
							const isLocked = ! isDashboard && navLocked;
							return (
								<li key={ item.path }>
									{ isLocked ? (
										<span
											className="adbot-nav-item adbot-nav-item--disabled"
											aria-disabled="true"
											title={ __(
												'Connect your Google account from the Dashboard first.',
												'adbot'
											) }
										>
											<span className={ `dashicons ${ item.icon }` } />
											{ item.label }
										</span>
									) : (
										<NavLink
											to={ item.path }
											className={ ( { isActive } ) =>
												'adbot-nav-item' + ( isActive ? ' adbot-nav-item--active' : '' )
											}
											end={ item.path === '/' }
										>
											<span className={ `dashicons ${ item.icon }` } />
											{ item.label }
										</NavLink>
									) }
								</li>
							);
						} ) }
					</ul>
				</nav>
				<main className="adbot-admin__content">
					<Outlet />
				</main>
			</div>
		</div>
	);
}
