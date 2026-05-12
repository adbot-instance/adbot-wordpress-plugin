import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Notice } from '@wordpress/components';
import { getSettings, updateSettings } from '../api/settings';

export default function Settings() {
	const [ settings, setSettings ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ saved, setSaved ] = useState( false );
	const [ error, setError ] = useState( '' );

	useEffect( () => {
		getSettings()
			.then( ( data ) => {
				setSettings( data );
				setLoading( false );
			} )
			.catch( () => setLoading( false ) );
	}, [] );

	const handleSave = async () => {
		setSaving( true );
		setSaved( false );
		setError( '' );
		try {
			const updated = await updateSettings( settings );
			setSettings( updated );
			setSaved( true );
			setTimeout( () => setSaved( false ), 3000 );
		} catch ( err ) {
			setError( err?.message ? String( err.message ) : String( err ) );
		}
		setSaving( false );
	};

	if ( loading ) {
		return <p>{ __( 'Loading settings…', 'adbot' ) }</p>;
	}

	return (
		<div className="adbot-settings">
			<h2>{ __( 'Settings', 'adbot' ) }</h2>

			{ error && (
				<Notice status="error" isDismissible onRemove={ () => setError( '' ) }>
					{ __( 'Failed to save:', 'adbot' ) } { error }
				</Notice>
			) }

			<Notice status="info" isDismissible={ false }>
				<strong>{ __( 'External services', 'adbot' ) }</strong>
				<p>
					{ __(
						'This plugin communicates only with the Adbot Tracking backend (adbot-tracking-platform.vercel.app). That service will move to tracking.adbot.co.za when DNS is ready; until then traffic uses the Vercel hostname. The backend relays authorized requests on your behalf to Google (OAuth, Tag Manager, Analytics, Ads, Search Console), Supabase (token storage), and Paystack (payments). Your WordPress site never talks to these providers directly, and no external request is made until the Consent toggle below is enabled AND you start a connection.',
						'adbot'
					) }
				</p>
			</Notice>

			<table className="form-table">
				<tbody>
					<tr>
						<th scope="row">{ __( 'Exclude admins', 'adbot' ) }</th>
						<td>
							<label>
								<input
									type="checkbox"
									checked={ settings?.exclude_admins ?? true }
									onChange={ ( e ) =>
										setSettings( { ...settings, exclude_admins: e.target.checked } )
									}
								/>
								{ __( "Don't inject the GTM snippet for logged-in administrators", 'adbot' ) }
							</label>
							<p className="description">
								{ __( 'Useful to avoid skewing analytics data during development.', 'adbot' ) }
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">{ __( 'Debug mode', 'adbot' ) }</th>
						<td>
							<label>
								<input
									type="checkbox"
									checked={ settings?.debug_mode ?? false }
									onChange={ ( e ) =>
										setSettings( { ...settings, debug_mode: e.target.checked } )
									}
								/>
								{ __( 'Enable debug logging in the browser console', 'adbot' ) }
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">{ __( 'Consent', 'adbot' ) }</th>
						<td>
							<label>
								<input
									type="checkbox"
									checked={ settings?.consent_given ?? false }
									onChange={ ( e ) =>
										setSettings( { ...settings, consent_given: e.target.checked } )
									}
								/>
								{ __( 'Allow Adbot to contact external services when you use connected features', 'adbot' ) }
							</label>
							<p className="description">
								{ __( 'You can revoke this at any time; connected features will stop working until re-enabled.', 'adbot' ) }
							</p>
						</td>
					</tr>
				</tbody>
			</table>

			<p className="submit">
				<button
					className="button button-primary"
					onClick={ handleSave }
					disabled={ saving }
				>
					{ saving ? __( 'Saving…', 'adbot' ) : __( 'Save settings', 'adbot' ) }
				</button>
				{ saved && <span className="adbot-saved"> { __( 'Settings saved.', 'adbot' ) }</span> }
			</p>
		</div>
	);
}
