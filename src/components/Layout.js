import { NavLink, Outlet } from 'react-router-dom';

const NAV_ITEMS = [
	{ path: '/', label: 'Dashboard', icon: 'dashicons-dashboard' },
	{ path: '/connect', label: 'Connect', icon: 'dashicons-admin-links' },
	{ path: '/audit', label: 'Audit', icon: 'dashicons-search' },
	{ path: '/setup', label: 'Fix Issues', icon: 'dashicons-admin-tools' },
	{ path: '/settings', label: 'Settings', icon: 'dashicons-admin-generic' },
];

export default function Layout() {
	return (
		<div className="adbot-admin">
			<div className="adbot-admin__header">
				<h1>
					<span className="adbot-admin__logo">Adbot</span>
				</h1>
			</div>
			<div className="adbot-admin__body">
				<nav className="adbot-admin__sidebar">
					<ul>
						{ NAV_ITEMS.map( ( item ) => (
							<li key={ item.path }>
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
							</li>
						) ) }
					</ul>
				</nav>
				<main className="adbot-admin__content">
					<Outlet />
				</main>
			</div>
		</div>
	);
}
