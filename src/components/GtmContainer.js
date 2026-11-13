import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Notice, Modal, Button } from '@wordpress/components';
import { installSnippet, uninstallSnippet } from '../api/settings';
import ContainerSelector from './ContainerSelector';
import SnippetCode from './SnippetCode';

/**
 * Tag Manager → Container tab: pick the GTM container, see the exact snippet
 * Adbot added, or the existing one it found. (Moved from the old Connect page;
 * the Google account itself now lives under Settings → Connections.)
 *
 * @param {Object}   props
 * @param {Object}   props.status   GET /status payload.
 * @param {Function} props.onChange Reload status after an install/remove.
 */
export default function GtmContainer( { status, onChange } ) {
	const [ selectedContainer, setSelectedContainer ] = useState( null );
	const [ installing, setInstalling ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ confirmUninstall, setConfirmUninstall ] = useState( false );

	const snippetActive = status?.snippetActive;
	const snippetContainerId = status?.snippetContainerId;
	const showExisting =
		status?.snippetExternalDetected && !! snippetContainerId;

	const handleInstall = async ( container ) => {
		setInstalling( true );
		setError( '' );
		try {
			// The snippet uses the public GTM-XXXX id, not the numeric containerId.
			await installSnippet(
				container.publicId || container.containerId,
				container.path
			);
			setSelectedContainer( container );
			onChange();
		} catch ( err ) {
			setError( err?.message ? String( err.message ) : String( err ) );
		}
		setInstalling( false );
	};

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

			{ ! snippetActive && ! showExisting && (
				<div className="adbot-connect__section">
					<h3>{ __( 'Choose your GTM container', 'adbot' ) }</h3>
					<p>
						{ __(
							'Choose the GTM container to install on this WordPress site.',
							'adbot'
						) }
					</p>
					<ContainerSelector
						onSelect={ handleInstall }
						selectedPath={ selectedContainer?.path }
					/>
					{ installing && (
						<p>{ __( 'Installing snippet…', 'adbot' ) }</p>
					) }
				</div>
			) }

			{ snippetActive && (
				<div className="adbot-connect__section">
					<h3>{ __( 'GTM snippet installed', 'adbot' ) }</h3>
					<p>
						{ __( 'Container', 'adbot' ) }{ ' ' }
						<strong>{ snippetContainerId }</strong>{ ' ' }
						{ __(
							'is active on your site. This is the exact code Adbot added:',
							'adbot'
						) }
					</p>
					<SnippetCode
						head={ status.snippetHead }
						body={ status.snippetBody }
					/>
					<button
						type="button"
						className="button"
						onClick={ () => setConfirmUninstall( true ) }
					>
						{ __( 'Remove snippet', 'adbot' ) }
					</button>
				</div>
			) }

			{ showExisting && (
				<div className="adbot-connect__section">
					<h3>{ __( 'Existing GTM snippet detected', 'adbot' ) }</h3>
					<SnippetCode
						head={ status.snippetHead }
						body={ status.snippetBody }
						note={ sprintf(
							/* translators: %s: GTM container id, e.g. GTM-XXXXXXX. */
							__(
								'A GTM snippet for %s was already on your site, so Adbot did not add a duplicate (a second copy would make every tag fire twice). For reference, the standard snippet for this container is:',
								'adbot'
							),
							snippetContainerId
						) }
					/>
					<button
						type="button"
						className="button"
						onClick={ () => setConfirmUninstall( true ) }
					>
						{ __( 'Remove snippet', 'adbot' ) }
					</button>
				</div>
			) }

			{ confirmUninstall && (
				<Modal
					title={ __( 'Remove GTM snippet', 'adbot' ) }
					onRequestClose={ () => setConfirmUninstall( false ) }
				>
					<p>
						{ __(
							'Remove the GTM snippet from your site?',
							'adbot'
						) }
					</p>
					<div className="adbot-modal-actions">
						<Button
							variant="secondary"
							onClick={ () => setConfirmUninstall( false ) }
						>
							{ __( 'Cancel', 'adbot' ) }
						</Button>
						<Button
							variant="primary"
							onClick={ async () => {
								setConfirmUninstall( false );
								await uninstallSnippet();
								setSelectedContainer( null );
								onChange();
							} }
						>
							{ __( 'Remove', 'adbot' ) }
						</Button>
					</div>
				</Modal>
			) }
		</div>
	);
}
