import { useCallback, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { getInsights } from '../../api/insights';
import StatusBadge from '../StatusBadge';
import Scorecards from '../charts/Scorecards';
import LineChart from '../charts/LineChart';
import Breakdown from '../charts/Breakdown';

export const RANGES = [
	{ key: '7d', label: __( 'Last 7 days', 'adbot' ) },
	{ key: '28d', label: __( 'Last 28 days', 'adbot' ) },
	{ key: '90d', label: __( 'Last 90 days', 'adbot' ) },
];

/**
 * Loads and renders one product's chart data: date-range filter, scorecards,
 * time series and breakdowns. Mirrors the Shopify app's product pages.
 *
 * Never shows invented numbers. When there is no live data the panel says why
 * (`emptyReason`), and a failed request is a quiet notice, not a broken page.
 *
 * @param {Object}  props
 * @param {string}  props.product ga4 | gsc | gmc | gbp
 * @param {boolean} props.enabled False while the product is not set up yet;
 *                                the panel then renders nothing.
 */
export default function InsightsPanel( { product, enabled = true } ) {
	const [ range, setRange ] = useState( '28d' );
	const [ data, setData ] = useState( null );
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ] = useState( '' );

	const load = useCallback(
		( refresh = false ) => {
			setLoading( true );
			setError( '' );
			getInsights( product, range, refresh )
				.then( setData )
				.catch( ( err ) =>
					setError(
						err?.message ||
							__(
								'Could not load the charts. Try again in a moment.',
								'adbot'
							)
					)
				)
				.finally( () => setLoading( false ) );
		},
		[ product, range ]
	);

	useEffect( () => {
		if ( enabled ) {
			load();
		}
	}, [ enabled, load ] );

	if ( ! enabled ) {
		return null;
	}

	const live = data?.source === 'live';

	return (
		<section className="adbot-insights" aria-busy={ loading }>
			<div className="adbot-insights__bar">
				<h3 className="adbot-section-title">
					{ __( 'Performance', 'adbot' ) }
				</h3>
				<div className="adbot-insights__filters">
					{ data?.accountLabel ? (
						<span className="adbot-insights__account">
							{ data.accountLabel }
						</span>
					) : null }
					<div
						className="adbot-segmented"
						role="group"
						aria-label={ __( 'Date range', 'adbot' ) }
					>
						{ RANGES.map( ( r ) => (
							<button
								key={ r.key }
								type="button"
								className={
									'adbot-segmented__item' +
									( r.key === range ? ' is-active' : '' )
								}
								aria-pressed={ r.key === range }
								onClick={ () => setRange( r.key ) }
								disabled={ loading }
							>
								{ r.label }
							</button>
						) ) }
					</div>
					<button
						type="button"
						className="button"
						onClick={ () => load( true ) }
						disabled={ loading }
					>
						{ __( 'Refresh', 'adbot' ) }
					</button>
				</div>
			</div>

			{ loading && ! data ? (
				<div className="adbot-progress-bar">
					<span />
				</div>
			) : null }

			{ error ? <p className="adbot-error">{ error }</p> : null }

			{ data && ! live ? (
				<div className="adbot-insights__empty">
					<StatusBadge tone={ data.comingSoon ? 'neutral' : 'info' }>
						{ data.comingSoon
							? __( 'Coming soon', 'adbot' )
							: __( 'No data yet', 'adbot' ) }
					</StatusBadge>
					<p>
						{ data.emptyReason ||
							( data.comingSoon
								? __(
										'Charts for this service arrive in a coming release.',
										'adbot'
								  )
								: __(
										'There is nothing to chart for this period yet.',
										'adbot'
								  ) ) }
					</p>
				</div>
			) : null }

			{ live ? (
				<div
					className={
						'adbot-insights__body' +
						( loading ? ' is-loading' : '' )
					}
				>
					<Scorecards
						scorecards={ data.scorecards }
						currency={ data.currency }
						compareLabel={ data.compareLabel }
					/>
					{ ( data.charts || [] ).map( ( chart ) => (
						<LineChart
							key={ chart.id }
							chart={ chart }
							currency={ data.currency }
						/>
					) ) }
					{ data.breakdowns?.length ? (
						<div className="adbot-insights__grid">
							{ data.breakdowns.map( ( b ) => (
								<Breakdown
									key={ b.id }
									breakdown={ b }
									currency={ data.currency }
								/>
							) ) }
						</div>
					) : null }
				</div>
			) : null }
		</section>
	);
}
