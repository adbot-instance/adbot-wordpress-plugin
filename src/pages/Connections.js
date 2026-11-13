import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import { Notice, Modal, Button } from '@wordpress/components';
import { disconnect } from '../api/auth';
import { openOAuthPopup } from '../lib/oauthPopup';
import GoogleSignInButton from '../components/GoogleSignInButton';
import StatusBadge from '../components/StatusBadge';
import { productLogo } from '../components/ProductPage';
import useStatus from '../hooks/useStatus';
import {
	ga4State,
	gtmState,
	gscState,
	gmcState,
	gbpState,
} from '../lib/productStates';

/**
 * Settings → Connections: the Google account Adbot uses, and what is
 * connected for each Google service, with a link to manage each one.
 */
export default function Connections() {
	const { status, loading, reload } = useStatus();
	const [ connecting, setConnecting ] = useState( false );
	const [ confirmDisconnect, setConfirmDisconnect ] = useState( false );
	const [ error, setError ] = useState( '' );

	if ( loading && ! status ) {
		return <p>{ __( 'Loading…', 'adbot' ) }</p>;
	}

	const connected = !! status?.connected;

	const handleConnect = async () => {
		setConnecting( true );
		setError( '' );
		try {
			await openOAuthPopup();
			await reload();
		} catch ( err ) {
			setError( err?.message ? String( err.message ) : String( err ) );
		}
		setConnecting( false );
	};

	const services = [
		{
			key: 'ga4',
			name: __( 'Analytics', 'adbot' ),
			logo: productLogo( 'ga4' ),
			state: ga4State( status ),
		},
		{
			key: 'gtm',
			name: __( 'Tag Manager', 'adbot' ),
			logo: productLogo( 'gtm' ),
			state: gtmState( status ),
		},
		{
			key: 'gsc',
			name: __( 'Search Console', 'adbot' ),
			logo: productLogo( 'gsc' ),
			state: gscState( status ),
		},
		{
			key: 'gmc',
			name: __( 'Merchant Center', 'adbot' ),
			logo: productLogo( 'gmc' ),
			state: gmcState( status ),
		},
		{
			key: 'gbp',
			name: __( 'Business Profile', 'adbot' ),
			icon: 'dashicons-store',
			state: gbpState(),
		},
	];

	return (
		<div className="adbot-connect">
			{ error && (
				<Notice
					status="error"
					isDismissible
					onRemove={ () => setError( '' ) }
				>
					{ error }
				</Notice>
			) }

			<section className="adbot-connect__section adbot-card">
				<h3>{ __( 'Google account', 'adbot' ) }</h3>
				{ connected ? (
					<div className="adbot-connect__account">
						{ status.account?.picture && (
							<img
								src={ status.account.picture }
								alt=""
								className="adbot-connect__avatar"
							/>
						) }
						<div>
							<strong>
								{ status.account?.name ||
									__( 'Google account', 'adbot' ) }
							</strong>
							<br />
							<span>{ status.account?.email }</span>
						</div>
						<button
							type="button"
							className="button"
							onClick={ () => setConfirmDisconnect( true ) }
						>
							{ __( 'Disconnect', 'adbot' ) }
						</button>
					</div>
				) : (
					<>
						<p>
							{ __(
								'Connect the Google account that manages this site. Each Google service then asks for its own access when you set it up.',
								'adbot'
							) }
						</p>
						<GoogleSignInButton
							variant="continue"
							onClick={ handleConnect }
							loading={ connecting }
							size="large"
						/>
					</>
				) }
			</section>

			<section className="adbot-connect__section adbot-card">
				<h3>{ __( 'Google services', 'adbot' ) }</h3>
				<ul className="adbot-service-list">
					{ services.map( ( s ) => (
						<li key={ s.key } className="adbot-service-list__item">
							{ s.logo ? (
								<img
									src={ s.logo }
									alt=""
									width={ 24 }
									height={ 24 }
								/>
							) : (
								<span
									className={ `dashicons ${ s.icon }` }
									aria-hidden="true"
								/>
							) }
							<span className="adbot-service-list__name">
								<strong>{ s.name }</strong>
								{ s.state.hint ? (
									<span className="adbot-card__meta">
										{ s.state.hint }
									</span>
								) : null }
							</span>
							<StatusBadge tone={ s.state.tone }>
								{ s.state.label }
							</StatusBadge>
							{ connected ? (
								<Link className="button" to={ s.state.to }>
									{ __( 'Manage', 'adbot' ) }
								</Link>
							) : null }
						</li>
					) ) }
				</ul>
			</section>

			{ confirmDisconnect && (
				<Modal
					title={ __( 'Disconnect Google account', 'adbot' ) }
					onRequestClose={ () => setConfirmDisconnect( false ) }
				>
					<p>
						{ __(
							'This revokes Adbot’s access to every Google service above and removes the Search Console and Merchant Center selections and the verification tag from this site.',
							'adbot'
						) }
					</p>
					<div className="adbot-modal-actions">
						<Button
							variant="secondary"
							onClick={ () => setConfirmDisconnect( false ) }
						>
							{ __( 'Cancel', 'adbot' ) }
						</Button>
						<Button
							variant="primary"
							isDestructive
							onClick={ async () => {
								setConfirmDisconnect( false );
								await disconnect();
								reload();
							} }
						>
							{ __( 'Disconnect', 'adbot' ) }
						</Button>
					</div>
				</Modal>
			) }
		</div>
	);
}
