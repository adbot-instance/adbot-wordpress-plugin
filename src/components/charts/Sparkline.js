/**
 * Tiny trend line for Overview previews. Decorative: the scorecard beside it
 * carries the number, so it is hidden from assistive tech.
 *
 * @param {Object} props
 * @param {Array}  props.points [{ date, value }]
 */
export default function Sparkline( { points } ) {
	if ( ! points || points.length < 2 ) {
		return null;
	}
	const w = 120;
	const h = 32;
	const values = points.map( ( p ) => Number( p.value ) || 0 );
	const max = Math.max( ...values ) || 1;
	const min = Math.min( ...values, 0 );
	const span = max - min || 1;
	const d = values
		.map( ( v, i ) => {
			const x = ( i / ( values.length - 1 ) ) * ( w - 4 ) + 2;
			const y = h - 2 - ( ( v - min ) / span ) * ( h - 4 );
			return `${ i ? 'L' : 'M' }${ x.toFixed( 1 ) },${ y.toFixed( 1 ) }`;
		} )
		.join( ' ' );
	return (
		<svg
			className="adbot-sparkline"
			viewBox={ `0 0 ${ w } ${ h }` }
			aria-hidden="true"
			focusable="false"
		>
			<path
				d={ d }
				fill="none"
				stroke="var(--adbot-chart-1)"
				strokeWidth="2"
				strokeLinejoin="round"
				strokeLinecap="round"
			/>
		</svg>
	);
}
