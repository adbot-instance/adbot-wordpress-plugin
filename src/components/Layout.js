import { useEffect } from '@wordpress/element';
import { NavLink, Outlet, useNavigate, useLocation } from 'react-router-dom';
import { __ } from '@wordpress/i18n';
import { useGoogleConnection } from '../hooks/useGoogleConnection';

function logo( key ) {
	return window.adbotAdmin?.images?.[ key ] || '';
}

// Overview, then one item per Google service, then Settings — the same order
// as the Shopify app's rail. Business Profile has no bundled logo yet.
function getNavItems() {
	return [
		{
			path: '/',
			label: __( 'Overview', 'adbot' ),
			icon: 'dashicons-dashboard',
		},
		{
			path: '/analytics',
			label: __( 'Analytics', 'adbot' ),
			logo: logo( 'ga4' ),
		},
		{
			path: '/tag-manager',
			label: __( 'Tag Manager', 'adbot' ),
			logo: logo( 'gtm' ),
		},
		{
			path: '/search-console',
			label: __( 'Search Console', 'adbot' ),
			logo: logo( 'gsc' ),
		},
		{
			path: '/merchant-center',
			label: __( 'Merchant Center', 'adbot' ),
			logo: logo( 'gmc' ),
		},
		{
			path: '/business-profile',
			label: __( 'Business Profile', 'adbot' ),
			icon: 'dashicons-store',
		},
		{
			path: '/settings',
			label: __( 'Settings', 'adbot' ),
			icon: 'dashicons-admin-generic',
			separated: true,
		},
	];
}

// Without a Google connection only Overview and Settings (where you connect)
// are open. A prefix check so nested tabs and legacy paths are covered too.
function isOpenPath( path ) {
	return (
		path === '/' ||
		path === '' ||
		path === '/settings' ||
		path.startsWith( '/settings/' )
	);
}

function NavIcon( { item } ) {
	return item.logo ? (
		<img
			className="adbot-nav-item__logo"
			src={ item.logo }
			alt=""
			width={ 18 }
			height={ 18 }
		/>
	) : (
		<span className={ `dashicons ${ item.icon }` } aria-hidden="true" />
	);
}

function getBrand() {
	if ( typeof window === 'undefined' || ! window.adbotAdmin?.brand ) {
		return { name: __( 'Adbot', 'adbot' ), logoUrl: '', tagline: '' };
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
		if ( ! isOpenPath( path ) ) {
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
							<p className="adbot-admin__tagline">
								{ brand.tagline }
							</p>
						) : null }
					</div>
				</div>
			</div>
			<div className="adbot-admin__body">
				<nav
					className="adbot-admin__sidebar"
					aria-label={ __( 'Adbot plugin sections', 'adbot' ) }
				>
					<ul>
						{ navItems.map( ( item ) => {
							const isLocked =
								navLocked && ! isOpenPath( item.path );
							return (
								<li
									key={ item.path }
									className={
										item.separated
											? 'adbot-admin__nav-sep'
											: undefined
									}
								>
									{ isLocked ? (
										<span
											className="adbot-nav-item adbot-nav-item--disabled"
											aria-disabled="true"
											title={ __(
												'Connect your Google account first (Overview or Settings).',
												'adbot'
											) }
										>
											<NavIcon item={ item } />
											{ item.label }
										</span>
									) : (
										<NavLink
											to={ item.path }
											className={ ( { isActive } ) =>
												'adbot-nav-item' +
												( isActive
													? ' adbot-nav-item--active'
													: '' )
											}
											end={ item.path === '/' }
										>
											<NavIcon item={ item } />
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
