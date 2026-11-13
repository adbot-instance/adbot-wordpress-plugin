import { NavLink } from 'react-router-dom';

/**
 * Tabs that are routes (platform `<UrlTabs>`): links, the back button and
 * bookmarks all land on the right tab. Render the active tab with <Outlet />.
 *
 * @param {Object} props
 * @param {Array}  props.tabs  [{ to, label, end }] — `end` for the index tab.
 * @param {string} props.label Accessible name for the tab row.
 */
export default function UrlTabs( { tabs, label } ) {
	return (
		<nav className="adbot-tabs" aria-label={ label }>
			{ tabs.map( ( tab ) => (
				<NavLink
					key={ tab.to }
					to={ tab.to }
					end={ !! tab.end }
					className={ ( { isActive } ) =>
						'adbot-tabs__item' + ( isActive ? ' is-active' : '' )
					}
				>
					{ tab.label }
				</NavLink>
			) ) }
		</nav>
	);
}
