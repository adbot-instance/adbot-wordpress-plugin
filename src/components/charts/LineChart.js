import { useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { formatDay, formatValue, SERIES_VARS } from './format';

// Drawn at the container's real pixel width (measured below), so axis text
// stays at its CSS size instead of scaling with the page.
const DEFAULT_WIDTH = 640;
const HEIGHT = 220;
const PAD = { top: 12, right: 16, bottom: 28, left: 52 };

function niceMax( max ) {
	if ( max <= 0 ) {
		return 1;
	}
	const pow = Math.pow( 10, Math.floor( Math.log10( max ) ) );
	const step = [ 1, 2, 2.5, 5, 10 ].find( ( s ) => s * pow * 4 >= max ) || 10;
	return step * pow * 4;
}

/**
 * Time-series line chart (TimeSeriesChart from the insights payload).
 *
 * Chart rules shared with the platform and the Shopify app: series colours are
 * chart-1…5 in order and follow the series, one y-axis only, 2px lines,
 * hairline grid, a legend whenever there are two or more series, a crosshair
 * tooltip on hover/focus, and a table view so nothing depends on colour.
 *
 * @param {Object} props
 * @param {Object} props.chart    { id, title, format, series: [{ id, label, points }] }
 * @param {string} props.currency
 */
export default function LineChart( { chart, currency } ) {
	const [ hover, setHover ] = useState( null );
	const [ showTable, setShowTable ] = useState( false );
	const svgRef = useRef( null );
	const plotRef = useRef( null );
	const [ WIDTH, setWidth ] = useState( DEFAULT_WIDTH );

	useEffect( () => {
		const el = plotRef.current;
		if ( ! el || typeof window.ResizeObserver === 'undefined' ) {
			return;
		}
		const observer = new window.ResizeObserver( ( entries ) => {
			const w = Math.round( entries[ 0 ].contentRect.width );
			if ( w > 0 ) {
				setWidth( w );
			}
		} );
		observer.observe( el );
		return () => observer.disconnect();
	}, [ showTable ] );

	const series = ( chart.series || [] ).slice( 0, SERIES_VARS.length );
	const dates = series[ 0 ]?.points?.map( ( p ) => p.date ) || [];

	const { max, ticks } = useMemo( () => {
		const all = series.flatMap( ( s ) => s.points.map( ( p ) => p.value ) );
		const m = niceMax( Math.max( 0, ...all ) );
		return { max: m, ticks: [ 0, m / 4, m / 2, ( 3 * m ) / 4, m ] };
	}, [ series ] );

	if ( ! dates.length ) {
		return null;
	}

	const innerW = WIDTH - PAD.left - PAD.right;
	const innerH = HEIGHT - PAD.top - PAD.bottom;
	const x = ( i ) =>
		PAD.left +
		( dates.length === 1
			? innerW / 2
			: ( i / ( dates.length - 1 ) ) * innerW );
	const y = ( v ) => PAD.top + innerH - ( v / max ) * innerH;

	const path = ( points ) =>
		points
			.map(
				( p, i ) =>
					`${ i ? 'L' : 'M' }${ x( i ).toFixed( 1 ) },${ y(
						p.value
					).toFixed( 1 ) }`
			)
			.join( ' ' );

	const tickAnchor = ( i ) => {
		if ( i === 0 ) {
			return 'start';
		}
		return i === dates.length - 1 ? 'end' : 'middle';
	};

	// About one date label per 90px, and never one crowding the last label.
	const labelEvery = Math.max(
		1,
		Math.ceil( dates.length / Math.max( 2, Math.floor( innerW / 90 ) ) )
	);
	const showTick = ( i ) =>
		i === dates.length - 1 ||
		( i % labelEvery === 0 && dates.length - 1 - i >= labelEvery * 0.75 );

	const onMove = ( event ) => {
		const rect = svgRef.current.getBoundingClientRect();
		const px = ( ( event.clientX - rect.left ) / rect.width ) * WIDTH;
		const i = Math.round(
			( ( px - PAD.left ) / innerW ) * ( dates.length - 1 )
		);
		setHover( Math.min( dates.length - 1, Math.max( 0, i ) ) );
	};

	const onKey = ( event ) => {
		if ( event.key === 'ArrowRight' || event.key === 'ArrowLeft' ) {
			event.preventDefault();
			const step = event.key === 'ArrowRight' ? 1 : -1;
			setHover( ( h ) =>
				Math.min(
					dates.length - 1,
					Math.max( 0, ( h ?? dates.length - 1 ) + step )
				)
			);
		}
	};

	const tipLeft = hover !== null ? ( x( hover ) / WIDTH ) * 100 : 0;

	return (
		<figure className="adbot-chart">
			<figcaption className="adbot-chart__head">
				<span className="adbot-chart__title">{ chart.title }</span>
				{ series.length > 1 ? (
					<ul className="adbot-chart__legend">
						{ series.map( ( s, i ) => (
							<li key={ s.id }>
								<span
									className="adbot-chart__key"
									style={ { background: SERIES_VARS[ i ] } }
									aria-hidden="true"
								/>
								{ s.label }
							</li>
						) ) }
					</ul>
				) : null }
				<button
					type="button"
					className="adbot-chart__toggle"
					onClick={ () => setShowTable( ( v ) => ! v ) }
					aria-pressed={ showTable }
				>
					{ showTable
						? __( 'Show chart', 'adbot' )
						: __( 'Show table', 'adbot' ) }
				</button>
			</figcaption>

			{ showTable ? (
				<div className="adbot-chart__table-wrap">
					<table className="adbot-table">
						<thead>
							<tr>
								<th scope="col">{ __( 'Date', 'adbot' ) }</th>
								{ series.map( ( s ) => (
									<th
										scope="col"
										key={ s.id }
										className="is-num"
									>
										{ s.label }
									</th>
								) ) }
							</tr>
						</thead>
						<tbody>
							{ dates.map( ( d, i ) => (
								<tr key={ d }>
									<td>{ formatDay( d ) }</td>
									{ series.map( ( s ) => (
										<td key={ s.id } className="is-num">
											{ formatValue(
												s.points[ i ]?.value,
												chart.format,
												currency
											) }
										</td>
									) ) }
								</tr>
							) ) }
						</tbody>
					</table>
				</div>
			) : (
				<div className="adbot-chart__plot" ref={ plotRef }>
					<svg
						ref={ svgRef }
						viewBox={ `0 0 ${ WIDTH } ${ HEIGHT }` }
						width={ WIDTH }
						height={ HEIGHT }
						role="img"
						aria-label={ chart.title }
						tabIndex={ 0 }
						onMouseMove={ onMove }
						onMouseLeave={ () => setHover( null ) }
						onFocus={ () => setHover( dates.length - 1 ) }
						onBlur={ () => setHover( null ) }
						onKeyDown={ onKey }
					>
						{ ticks.map( ( t ) => (
							<g key={ t }>
								<line
									className="adbot-chart__grid"
									x1={ PAD.left }
									x2={ WIDTH - PAD.right }
									y1={ y( t ) }
									y2={ y( t ) }
								/>
								<text
									className="adbot-chart__tick"
									x={ PAD.left - 8 }
									y={ y( t ) }
									textAnchor="end"
									dominantBaseline="middle"
								>
									{ formatValue(
										t,
										chart.format === 'percent'
											? 'percent'
											: 'number',
										currency
									) }
								</text>
							</g>
						) ) }
						{ dates.map( ( d, i ) =>
							showTick( i ) ? (
								<text
									key={ d }
									className="adbot-chart__tick"
									x={ x( i ) }
									y={ HEIGHT - 8 }
									textAnchor={ tickAnchor( i ) }
								>
									{ formatDay( d ) }
								</text>
							) : null
						) }
						{ series.map( ( s, si ) => (
							<path
								key={ s.id }
								d={ path( s.points ) }
								fill="none"
								stroke={ SERIES_VARS[ si ] }
								strokeWidth="2"
								strokeLinejoin="round"
								strokeLinecap="round"
							/>
						) ) }
						{ hover !== null ? (
							<g>
								<line
									className="adbot-chart__crosshair"
									x1={ x( hover ) }
									x2={ x( hover ) }
									y1={ PAD.top }
									y2={ PAD.top + innerH }
								/>
								{ series.map( ( s, si ) => (
									<circle
										key={ s.id }
										cx={ x( hover ) }
										cy={ y(
											s.points[ hover ]?.value || 0
										) }
										r="4"
										fill={ SERIES_VARS[ si ] }
										stroke="var(--adbot-white)"
										strokeWidth="2"
									/>
								) ) }
							</g>
						) : null }
					</svg>
					{ hover !== null ? (
						<div
							className="adbot-chart__tooltip"
							style={ {
								left: `${ tipLeft }%`,
								transform:
									tipLeft > 60
										? 'translateX(calc(-100% - 12px))'
										: 'translateX(12px)',
							} }
							role="status"
						>
							<strong>{ formatDay( dates[ hover ] ) }</strong>
							{ series.map( ( s, si ) => (
								<span
									key={ s.id }
									className="adbot-chart__tooltip-row"
								>
									<span
										className="adbot-chart__key"
										style={ {
											background: SERIES_VARS[ si ],
										} }
										aria-hidden="true"
									/>
									{ s.label }
									<b>
										{ formatValue(
											s.points[ hover ]?.value,
											chart.format,
											currency
										) }
									</b>
								</span>
							) ) }
						</div>
					) : null }
				</div>
			) }
		</figure>
	);
}
