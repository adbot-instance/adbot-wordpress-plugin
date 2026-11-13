import StatusBadge from './StatusBadge';

/**
 * Shared frame for every Google product page: logo, title, one-line purpose,
 * a status pill, then the page body (setup card, tabs, charts).
 *
 * @param {Object} props
 * @param {string} props.logoUrl
 * @param {string} props.icon     Dashicon class when there is no logo.
 * @param {string} props.title
 * @param {string} props.lede
 * @param {Object} props.status   { tone, label } or null.
 * @param {*}      props.children
 */
export default function ProductPage( {
	logoUrl,
	icon,
	title,
	lede,
	status,
	children,
} ) {
	return (
		<div className="adbot-product">
			<header className="adbot-product__header">
				{ logoUrl ? (
					<img
						src={ logoUrl }
						alt=""
						className="adbot-product__logo"
						width={ 40 }
						height={ 40 }
					/>
				) : (
					<span
						className={ `adbot-product__icon dashicons ${
							icon || 'dashicons-admin-generic'
						}` }
						aria-hidden="true"
					/>
				) }
				<div className="adbot-product__titles">
					<h2 className="adbot-page-title">{ title }</h2>
					{ lede ? (
						<p className="adbot-page-lede">{ lede }</p>
					) : null }
				</div>
				{ status ? (
					<StatusBadge tone={ status.tone } dot>
						{ status.label }
					</StatusBadge>
				) : null }
			</header>
			<div className="adbot-product__body">{ children }</div>
		</div>
	);
}

/**
 * Product logos registered in Admin.php (window.adbotAdmin.images).
 *
 * @param {string} key ga4 | gtm | gsc | gmc | gads.
 * @return {string} Logo URL, or '' when unknown.
 */
export function productLogo( key ) {
	return (
		( typeof window !== 'undefined' &&
			window.adbotAdmin?.images?.[ key ] ) ||
		''
	);
}
