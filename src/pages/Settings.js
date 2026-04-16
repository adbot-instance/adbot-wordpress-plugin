import { useState, useEffect } from '@wordpress/element';
import { getSettings, updateSettings } from '../api/settings';

export default function Settings() {
	const [ settings, setSettings ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ saved, setSaved ] = useState( false );

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
		try {
			const updated = await updateSettings( settings );
			setSettings( updated );
			setSaved( true );
			setTimeout( () => setSaved( false ), 3000 );
		} catch ( err ) {
			alert( 'Failed to save: ' + ( err.message || err ) );
		}
		setSaving( false );
	};

	if ( loading ) {
		return <p>Loading settings...</p>;
	}

	return (
		<div className="adbot-settings">
			<h2>Settings</h2>

			<table className="form-table">
				<tbody>
					<tr>
						<th scope="row">Exclude Admins</th>
						<td>
							<label>
								<input
									type="checkbox"
									checked={ settings?.exclude_admins ?? true }
									onChange={ ( e ) =>
										setSettings( { ...settings, exclude_admins: e.target.checked } )
									}
								/>
								Don't inject GTM snippet for logged-in administrators
							</label>
							<p className="description">
								Useful to avoid skewing analytics data during development.
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Debug Mode</th>
						<td>
							<label>
								<input
									type="checkbox"
									checked={ settings?.debug_mode ?? false }
									onChange={ ( e ) =>
										setSettings( { ...settings, debug_mode: e.target.checked } )
									}
								/>
								Enable debug logging in the browser console
							</label>
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
					{ saving ? 'Saving...' : 'Save Settings' }
				</button>
				{ saved && <span className="adbot-saved"> Settings saved.</span> }
			</p>
		</div>
	);
}
