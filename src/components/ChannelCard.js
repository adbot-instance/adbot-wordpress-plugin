import { __ } from '@wordpress/i18n';

// Maps the card state to the status colour class the stylesheet already
// defines for StatusCard, so no new CSS is needed.
const STATE_CLASSES = {
	success: 'adbot-status--success',
	warning: 'adbot-status--warning',
	error: 'adbot-status--error',
	neutral: 'adbot-status--neutral',
};

// A connectable Google surface: logo, state badge, explanation, actions.
//
// StatusCard is the dashboard tile and has no logo or action slot, so this is a
// sibling rather than a fork of it. Styling reuses the existing
// `.adbot-connect__section` / `.adbot-status--*` classes.
//
// Props: title, logoUrl, state ('success' | 'warning' | 'error' | 'neutral'),
// stateLabel, description, children (body), actions (button row).
export default function ChannelCard( {
	title,
	logoUrl,
	state = 'neutral',
	stateLabel,
	description,
	children,
	actions,
} ) {
	const stateClass = STATE_CLASSES[ state ] || STATE_CLASSES.neutral;

	return (
		<section className={ `adbot-connect__section adbot-channel-card ${ stateClass }` }>
			<header className="adbot-channel-card__header">
				{ logoUrl ? (
					<img
						src={ logoUrl }
						alt=""
						className="adbot-channel-card__logo"
						width={ 32 }
						height={ 32 }
					/>
				) : null }
				<h3 className="adbot-channel-card__title">{ title }</h3>
				{ stateLabel ? (
					<span className="adbot-status-card__badge">{ stateLabel }</span>
				) : null }
			</header>

			{ description ? (
				<p className="adbot-channel-card__description">{ description }</p>
			) : null }

			{ children }

			{ actions ? (
				<div className="adbot-step__actions adbot-channel-card__actions">{ actions }</div>
			) : null }
		</section>
	);
}

// A labelled key/value row for the details a card reports back — last fetch,
// submitted sitemap, item counts. Renders nothing when the value is empty, so
// callers do not each have to guard.
export function ChannelDetail( { label, children } ) {
	if ( children === null || children === undefined || children === '' ) {
		return null;
	}

	return (
		<div className="adbot-channel-card__detail">
			<span className="adbot-channel-card__detail-label">{ label }</span>
			<span className="adbot-channel-card__detail-value">{ children }</span>
		</div>
	);
}

/**
 * Render an ISO timestamp in the admin's own locale, falling back to the raw
 * string if it is not parseable — a backend that changes format should not
 * blank the field.
 *
 * @param {string} iso An ISO 8601 timestamp.
 * @return {string} A localised date/time, or '' when there is nothing to show.
 */
export function formatWhen( iso ) {
	if ( ! iso ) {
		return '';
	}
	const date = new Date( iso );
	if ( Number.isNaN( date.getTime() ) ) {
		return String( iso );
	}
	return date.toLocaleString();
}

export function NotConnectedHint() {
	return (
		<p className="adbot-channel-card__description">
			{ __( 'Connect your Google account first.', 'adbot' ) }
		</p>
	);
}
