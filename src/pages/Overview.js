import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import GoogleSignInButton from '../components/GoogleSignInButton';
import OnboardingChecklist from '../components/OnboardingChecklist';
import StatusBadge, { scoreTone } from '../components/StatusBadge';
import { productLogo } from '../components/ProductPage';
import Scorecards from '../components/charts/Scorecards';
import Sparkline from '../components/charts/Sparkline';
import { getInsights } from '../api/insights';
import { openOAuthPopup } from '../lib/oauthPopup';
import useStatus from '../hooks/useStatus';
import {
	ga4State,
	gtmState,
	gscState,
	gmcState,
	gbpState,
} from '../lib/productStates';

const SCORE_LABELS = {
	success: __( 'Healthy', 'adbot' ),
	warning: __( 'Needs attention', 'adbot' ),
	danger: __( 'Broken', 'adbot' ),
};

function readLastAudit() {
	try {
		const raw = localStorage.getItem( 'adbot_last_audit' );
		return raw ? JSON.parse( raw ) : null;
	} catch {
		return null;
	}
}

/**
 * One service's preview: status, the two headline numbers and a 28-day trend
 * when it is set up, otherwise what to do next. Each card loads on its own, so
 * a slow or failing service never holds up the others.
 *
 * @param {Object}  props
 * @param {string}  props.name      Service name.
 * @param {string}  props.logo      Logo URL.
 * @param {string}  props.icon      Dashicon class when there is no logo.
 * @param {Object}  props.state     From lib/productStates.
 * @param {string}  props.product   Insights product key, if it has charts.
 * @param {boolean} props.connected Google account connected.
 * @param {*}       props.children  Custom body instead of charts.
 */
function PreviewCard( {
	name,
	logo,
	icon,
	state,
	product,
	connected,
	children,
} ) {
	const [ data, setData ] = useState( null );

	useEffect( () => {
		if ( ! product || ! state.ready || ! connected ) {
			return;
		}
		let alive = true;
		getInsights( product, '28d' )
			.then( ( d ) => alive && setData( d ) )
			.catch( () => alive && setData( null ) );
		return () => {
			alive = false;
		};
	}, [ product, state.ready, connected ] );

	const live = data?.source === 'live';
	const trend = live ? data.charts?.[ 0 ]?.series?.[ 0 ]?.points : null;

	return (
		<article className="adbot-preview">
			<header className="adbot-preview__head">
				{ logo ? (
					<img src={ logo } alt="" width={ 28 } height={ 28 } />
				) : (
					<span
						className={ `dashicons ${ icon }` }
						aria-hidden="true"
					/>
				) }
				<h3 className="adbot-preview__title">{ name }</h3>
				<StatusBadge tone={ state.tone }>{ state.label }</StatusBadge>
			</header>

			<div className="adbot-preview__body">
				{ children }
				{ ! children && live ? (
					<>
						<Scorecards
							scorecards={ data.scorecards }
							currency={ data.currency }
							compareLabel={ data.compareLabel }
							limit={ 2 }
						/>
						<Sparkline points={ trend } />
					</>
				) : null }
				{ ! children && ! live ? (
					<p className="adbot-card__meta">
						{ data?.emptyReason && state.ready
							? data.emptyReason
							: state.hint }
					</p>
				) : null }
			</div>

			{ connected ? (
				<Link className="adbot-preview__link" to={ state.to }>
					{ sprintf(
						/* translators: %s: Google service name, e.g. Analytics. */
						__( 'Open %s', 'adbot' ),
						name
					) }
					<span aria-hidden="true"> →</span>
				</Link>
			) : null }
		</article>
	);
}

export default function Overview() {
	const { status, loading } = useStatus();
	const [ connecting, setConnecting ] = useState( false );
	const [ lastAudit, setLastAudit ] = useState( readLastAudit );

	useEffect( () => {
		const refresh = () => setLastAudit( readLastAudit() );
		window.addEventListener( 'adbot-last-audit', refresh );
		return () => window.removeEventListener( 'adbot-last-audit', refresh );
	}, [] );

	if ( loading && ! status ) {
		return <p>{ __( 'Loading…', 'adbot' ) }</p>;
	}

	const connected = !! status?.connected;
	const gtm = gtmState( status );

	const handleConnect = async () => {
		setConnecting( true );
		try {
			await openOAuthPopup();
		} catch {
			// The status refresh on window focus picks up the result.
		}
		setConnecting( false );
	};

	return (
		<div className="adbot-dashboard">
			<div className="adbot-dashboard__header">
				<div className="adbot-dashboard__intro">
					<h2 className="adbot-page-title">
						{ __( 'Overview', 'adbot' ) }
					</h2>
					<p className="adbot-page-lede">
						{ connected
							? sprintf(
									/* translators: %s: Google account email. */
									__(
										'Your Google marketing services at a glance · %s',
										'adbot'
									),
									status.account?.email || ''
							  )
							: __(
									'Connect your Google account to set up your Google marketing services.',
									'adbot'
							  ) }
					</p>
				</div>
				{ ! connected && (
					<div className="adbot-dashboard__header-actions">
						<GoogleSignInButton
							onClick={ handleConnect }
							loading={ connecting }
						/>
					</div>
				) }
			</div>

			<OnboardingChecklist />

			<div className="adbot-previews">
				<PreviewCard
					name={ __( 'Analytics', 'adbot' ) }
					logo={ productLogo( 'ga4' ) }
					state={ ga4State( status ) }
					product="ga4"
					connected={ connected }
				/>
				<PreviewCard
					name={ __( 'Tag Manager', 'adbot' ) }
					logo={ productLogo( 'gtm' ) }
					state={ gtm }
					connected={ connected }
				>
					<dl className="adbot-stats adbot-stats--compact">
						<div className="adbot-stat">
							<dt className="adbot-stat__label">
								{ __( 'Container', 'adbot' ) }
							</dt>
							<dd className="adbot-stat__value adbot-stat__value--text">
								{ status?.snippetContainerId || '—' }
							</dd>
						</div>
						<div className="adbot-stat">
							<dt className="adbot-stat__label">
								{ __( 'Last audit score', 'adbot' ) }
							</dt>
							<dd className="adbot-stat__value">
								{ lastAudit?.score ?? '—' }
								{ Number.isFinite( lastAudit?.score ) ? (
									<StatusBadge
										tone={ scoreTone( lastAudit.score ) }
										className="adbot-stat__badge"
									>
										{
											SCORE_LABELS[
												scoreTone( lastAudit.score )
											]
										}
									</StatusBadge>
								) : null }
							</dd>
						</div>
					</dl>
				</PreviewCard>
				<PreviewCard
					name={ __( 'Search Console', 'adbot' ) }
					logo={ productLogo( 'gsc' ) }
					state={ gscState( status ) }
					product="gsc"
					connected={ connected }
				/>
				<PreviewCard
					name={ __( 'Merchant Center', 'adbot' ) }
					logo={ productLogo( 'gmc' ) }
					state={ gmcState( status ) }
					product="gmc"
					connected={ connected }
				/>
				<PreviewCard
					name={ __( 'Business Profile', 'adbot' ) }
					icon="dashicons-store"
					state={ gbpState() }
					connected={ connected }
				/>
			</div>
		</div>
	);
}
