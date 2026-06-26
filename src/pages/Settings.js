import { useState, useEffect } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Notice } from '@wordpress/components';
import {
	getSettings,
	updateSettings,
	getSnippetStatus,
	rescanSnippet,
} from '../api/settings';

function snippetStatusLabel( snippet ) {
	if ( ! snippet || ! snippet.containerId ) {
		return __(
			'No container selected yet. Finish setup to install tracking.',
			'adbot'
		);
	}
	if ( snippet.externalDetected ) {
		return sprintf(
			/* translators: %s: GTM container ID. */
			__(
				'Existing %s snippet detected on your site — Adbot is not injecting a duplicate.',
				'adbot'
			),
			snippet.containerId
		);
	}
	if ( snippet.active ) {
		return sprintf(
			/* translators: %s: GTM container ID. */
			__(
				"Adbot is injecting %s into your site's <head> and <body>.",
				'adbot'
			),
			snippet.containerId
		);
	}
	return sprintf(
		/* translators: %s: GTM container ID. */
		__( '%s is selected but the snippet is not active.', 'adbot' ),
		snippet.containerId
	);
}

export default function Settings() {
	const [ settings, setSettings ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ saved, setSaved ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ snippet, setSnippet ] = useState( null );
	const [ rescanning, setRescanning ] = useState( false );

	useEffect( () => {
		getSettings()
			.then( ( data ) => {
				setSettings( data );
				setLoading( false );
			} )
			.catch( () => setLoading( false ) );
		getSnippetStatus()
			.then( setSnippet )
			.catch( () => {} );
	}, [] );

	const handleRescan = async () => {
		setRescanning( true );
		try {
			setSnippet( await rescanSnippet() );
		} catch {
			// Non-fatal — leave the last known status in place.
		}
		setRescanning( false );
	};

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
						<th scope="row">{ __( 'GTM snippet', 'adbot' ) }</th>
						<td>
							<p style={ { margin: '0 0 8px' } }>
								{ snippetStatusLabel( snippet ) }
							</p>
							{ snippet?.detection?.evidence && (
								<p className="description">{ snippet.detection.evidence }</p>
							) }
							{ snippet?.containerId && (
								<button
									type="button"
									className="button"
									onClick={ handleRescan }
									disabled={ rescanning }
								>
									{ rescanning
										? __( 'Re-scanning…', 'adbot' )
										: __( 'Re-scan site', 'adbot' ) }
								</button>
							) }
						</td>
					</tr>
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
