import { __ } from '@wordpress/i18n';

/**
 * Read-only display of the exact GTM snippet Adbot injects, sourced from the
 * backend (Snippet_Injector::head_snippet / body_snippet) so it is byte-for-byte
 * what is on the site. No copy button by design.
 *
 * @param {Object} props
 * @param {string} props.head Exact `<head>` snippet HTML.
 * @param {string} props.body Exact `<body>` (noscript) snippet HTML.
 * @param {string} [props.note] Optional context line shown above the blocks.
 */
export default function SnippetCode( { head, body, note } ) {
	if ( ! head && ! body ) {
		return null;
	}

	return (
		<div className="adbot-snippet-code">
			{ note && <p className="adbot-snippet-code__note">{ note }</p> }

			{ head && (
				<div className="adbot-snippet-code__block">
					<div className="adbot-snippet-code__label">
						{ __( 'In the page <head>', 'adbot' ) }
					</div>
					<pre className="adbot-snippet-code__pre">
						<code>{ head }</code>
					</pre>
				</div>
			) }

			{ body && (
				<div className="adbot-snippet-code__block">
					<div className="adbot-snippet-code__label">
						{ __( 'Immediately after the opening <body> tag', 'adbot' ) }
					</div>
					<pre className="adbot-snippet-code__pre">
						<code>{ body }</code>
					</pre>
				</div>
			) }
		</div>
	);
}
