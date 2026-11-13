import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import ProductPage, { productLogo } from '../components/ProductPage';
import PropertySelector from '../components/PropertySelector';
import InsightsPanel from '../components/insights/InsightsPanel';
import { selectGA4Property } from '../api/ga4';
import useStatus from '../hooks/useStatus';
import { ga4State } from '../lib/productStates';

/**
 * Google Analytics 4: which property this site reports to, and its
 * performance (sessions, users, key events, traffic sources, top pages).
 */
export default function Analytics() {
	const { status, loading, reload } = useStatus();
	const [ picking, setPicking ] = useState( false );
	const [ saving, setSaving ] = useState( false );
	const [ error, setError ] = useState( '' );

	if ( loading && ! status ) {
		return <p>{ __( 'Loading…', 'adbot' ) }</p>;
	}

	const state = ga4State( status );
	const showPicker = picking || ! state.ready;

	const choose = async ( property ) => {
		setSaving( true );
		setError( '' );
		try {
			await selectGA4Property( property );
			await reload();
			setPicking( false );
		} catch ( err ) {
			setError(
				err?.message ||
					__( 'Could not save the GA4 property.', 'adbot' )
			);
		}
		setSaving( false );
	};

	return (
		<ProductPage
			logoUrl={ productLogo( 'ga4' ) }
			title={ __( 'Analytics', 'adbot' ) }
			lede={ __(
				'How many people visit, where they come from and what they do.',
				'adbot'
			) }
			status={ state }
		>
			<section className="adbot-connect__section adbot-card">
				<div className="adbot-card__row">
					<div>
						<h3>{ __( 'GA4 property', 'adbot' ) }</h3>
						<p className="adbot-card__meta">
							{ state.ready
								? `${
										status.ga4PropertyName ||
										__( 'Property', 'adbot' )
								  } · ${ status.ga4MeasurementId }`
								: state.hint }
						</p>
					</div>
					{ state.ready && ! picking ? (
						<button
							type="button"
							className="button"
							onClick={ () => setPicking( true ) }
						>
							{ __( 'Change property', 'adbot' ) }
						</button>
					) : null }
					{ state.ready && picking ? (
						<button
							type="button"
							className="button"
							onClick={ () => setPicking( false ) }
						>
							{ __( 'Cancel', 'adbot' ) }
						</button>
					) : null }
				</div>
				{ error ? <p className="adbot-error">{ error }</p> : null }
				{ showPicker ? (
					<PropertySelector
						onSelect={ choose }
						selectedPath={ status?.ga4MeasurementId }
						busy={ saving }
					/>
				) : null }
			</section>

			<InsightsPanel product="ga4" enabled={ state.ready && ! picking } />
		</ProductPage>
	);
}
