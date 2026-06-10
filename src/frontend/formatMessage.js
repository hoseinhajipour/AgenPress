import { __ } from '@wordpress/i18n';

const URL_PATTERN = 'https?:\\/\\/[^\\s<>"\'\\]]+';
const LINK_LABELS = '(?:لینک محصول|مشاهده محصول|لینک|Product link|View product)';

/**
 * Extract the first URL from a line.
 *
 * @param {string} text Line text.
 * @return {string}
 */
function extractUrl( text ) {
	const match = text.match( new RegExp( URL_PATTERN, 'i' ) );
	return match ? match[ 0 ].replace( /[.,;:!?)]+$/, '' ) : '';
}

/**
 * Extract a product title from a list item main line.
 *
 * @param {string} mainLine Main bullet text.
 * @return {string}
 */
function extractProductTitle( mainLine ) {
	let text = mainLine.trim();
	const boldMatch = text.match( /^\*\*(.+?)\*\*/ );

	if ( boldMatch ) {
		text = boldMatch[ 1 ];
	}

	text = text.split( /\s+[—–-]\s+/ )[ 0 ].trim();

	return text;
}

/**
 * Pick a short label for a link button.
 *
 * @param {string} url URL.
 * @param {string} customLabel Optional custom label.
 * @return {string}
 */
function getLinkLabel( url, customLabel = '' ) {
	if ( customLabel?.trim() ) {
		return customLabel.trim();
	}

	if ( /\/product\//i.test( url ) ) {
		return __( 'View product', 'agenpress' );
	}

	return __( 'Open link', 'agenpress' );
}

/**
 * Render a product featured image from markdown.
 *
 * @param {string} url Image URL.
 * @param {string} alt Alt text.
 * @param {string} key React key.
 * @return {Object} React element.
 */
function renderProductImage( url, alt, key ) {
	return (
		<span key={ key } className="ap-chat-product-image">
			<img src={ url } alt={ alt || '' } loading="lazy" decoding="async" />
		</span>
	);
}

/**
 * Render a styled chat link button (never shows the raw URL as text).
 *
 * @param {string} url URL.
 * @param {string} label Button label.
 * @param {string} key React key.
 * @return {Object} React element.
 */
function renderLink( url, label, key ) {
	const displayLabel = getLinkLabel( url, label );

	return (
		<a
			key={ key }
			className="ap-chat-link-btn"
			href={ url }
			target="_blank"
			rel="noopener noreferrer"
			title={ url }
		>
			<span className="ap-chat-link-icon" aria-hidden="true">
				↗
			</span>
			<span className="ap-chat-link-text">{ displayLabel }</span>
		</a>
	);
}

/**
 * Parse a product link line into a button (no raw URL text).
 *
 * @param {string} line Line text.
 * @param {string} productTitle Product title for the button label.
 * @param {string} key React key.
 * @return {Object|null} React element or null.
 */
function parseProductLinkLine( line, productTitle, key ) {
	const trimmed = line.trim();
	const label = productTitle || '';

	const markdown = trimmed.match( /^\[([^\]]+)\]\(([^)]+)\)\s*$/ );
	if ( markdown ) {
		return renderLink( markdown[ 2 ], markdown[ 1 ], key );
	}

	const labeled = trimmed.match(
		new RegExp( `^(?:${ LINK_LABELS })\\s*:\\s*(${ URL_PATTERN })\\s*$`, 'i' )
	);
	if ( labeled ) {
		return renderLink( labeled[ 1 ], label, key );
	}

	if ( new RegExp( `^(?:${ LINK_LABELS })\\s*:?\\s*$`, 'i' ).test( trimmed ) ) {
		return null;
	}

	const urlOnly = trimmed.match( new RegExp( `^(${ URL_PATTERN })\\s*$`, 'i' ) );
	if ( urlOnly ) {
		return renderLink( urlOnly[ 1 ], label, key );
	}

	const url = extractUrl( trimmed );
	if ( url && isLinkOnlyLine( trimmed ) ) {
		return renderLink( url, label, key );
	}

	return null;
}

/**
 * Merge split product-link lines (label on one line, URL on the next).
 *
 * @param {Array<string>} extraLines Extra lines.
 * @return {Array<string>}
 */
function normalizeProductExtras( extraLines ) {
	const normalized = [];

	for ( let i = 0; i < extraLines.length; i++ ) {
		const line = extraLines[ i ].trim();

		if ( new RegExp( `^(?:${ LINK_LABELS })\\s*:?\\s*$`, 'i' ).test( line ) ) {
			const next = extraLines[ i + 1 ]?.trim() || '';
			const url = extractUrl( next );

			if ( url ) {
				normalized.push( url );
				i++;
				continue;
			}
		}

		normalized.push( line );
	}

	return normalized;
}

/**
 * Parse inline markdown and auto-link bare URLs.
 *
 * @param {string} text Line text.
 * @param {Object} options Parser options.
 * @return {Array} React nodes.
 */
function parseInline( text, options = {} ) {
	const { linkLabel = '' } = options;
	const regex = new RegExp(
		`(!\\[([^\\]]*)\\]\\(([^)]+)\\)|\\*\\*(.+?)\\*\\*|\\*(.+?)\\*|\\[([^\\]]+)\\]\\(([^)]+)\\)|${ LINK_LABELS }\\s*:\\s*(${ URL_PATTERN })|(${ URL_PATTERN }))`,
		'gi'
	);

	const parts = [];
	let lastIndex = 0;
	let match;

	while ( ( match = regex.exec( text ) ) !== null ) {
		if ( match.index > lastIndex ) {
			const chunk = text.slice( lastIndex, match.index );
			if ( chunk.trim() && ! new RegExp( `^(?:${ LINK_LABELS })\\s*:?\\s*$`, 'i' ).test( chunk.trim() ) ) {
				parts.push( chunk );
			}
		}

		if ( match[ 0 ].startsWith( '![' ) ) {
			parts.push( renderProductImage( match[ 3 ], match[ 2 ], `${ match.index }-img` ) );
		} else if ( match[ 4 ] ) {
			parts.push( <strong key={ `${ match.index }-b` }>{ match[ 4 ] }</strong> );
		} else if ( match[ 5 ] ) {
			parts.push( <em key={ `${ match.index }-i` }>{ match[ 5 ] }</em> );
		} else if ( match[ 6 ] ) {
			parts.push( renderLink( match[ 7 ], match[ 6 ], `${ match.index }-a` ) );
		} else if ( match[ 8 ] ) {
			parts.push( renderLink( match[ 8 ], linkLabel, `${ match.index }-l` ) );
		} else if ( match[ 9 ] ) {
			parts.push( renderLink( match[ 9 ], linkLabel, `${ match.index }-u` ) );
		}

		lastIndex = match.index + match[ 0 ].length;
	}

	if ( lastIndex < text.length ) {
		const chunk = text.slice( lastIndex );
		if ( chunk.trim() && ! new RegExp( `^(?:${ LINK_LABELS })\\s*:?\\s*$`, 'i' ).test( chunk.trim() ) ) {
			parts.push( chunk );
		}
	}

	return parts.length ? parts : [ text ];
}

/**
 * Check if a line is only a product image.
 *
 * @param {string} line Line text.
 * @return {boolean}
 */
function isImageOnlyLine( line ) {
	return /^!\[[^\]]*\]\([^)]+\)\s*$/i.test( line.trim() );
}

/**
 * Check if a line is only a product/link reference.
 *
 * @param {string} line Line text.
 * @return {boolean}
 */
function isLinkOnlyLine( line ) {
	const trimmed = line.trim();

	if ( new RegExp( `^(?:${ LINK_LABELS })\\s*:?\\s*$`, 'i' ).test( trimmed ) ) {
		return true;
	}

	return (
		new RegExp( `^(?:${ LINK_LABELS })\\s*:\\s*${ URL_PATTERN }\\s*$`, 'i' ).test( trimmed )
		|| /^\[[^\]]+\]\([^)]+\)\s*$/.test( trimmed )
		|| new RegExp( `^${ URL_PATTERN }\\s*$`, 'i' ).test( trimmed )
	);
}

/**
 * Check if a list item should use product card styling.
 *
 * @param {Object} item List item.
 * @return {boolean}
 */
function isProductItem( item ) {
	return (
		item.extra.length > 0
		|| isLinkOnlyLine( item.main )
		|| item.extra.some( ( line ) => isLinkOnlyLine( line ) || isImageOnlyLine( line ) )
	);
}

/**
 * Convert assistant markdown into React elements for chat display.
 *
 * @param {string} content Message content.
 * @return {Array} Block elements.
 */
export function formatMessage( content ) {
	if ( ! content || typeof content !== 'string' ) {
		return [];
	}

	const lines = content.split( '\n' );
	const blocks = [];
	let listItems = [];
	let listType = null;

	const flushList = () => {
		if ( ! listItems.length ) {
			return;
		}

		const Tag = listType === 'ol' ? 'ol' : 'ul';
		blocks.push(
			<Tag key={ `list-${ blocks.length }` }>
				{ listItems.map( ( item, index ) => {
					const productTitle = extractProductTitle( item.main );
					const extras = normalizeProductExtras( item.extra );

					return (
						<li
							key={ index }
							className={ isProductItem( item ) ? 'ap-chat-product-item' : undefined }
						>
							<div className="ap-chat-li-main">{ parseInline( item.main ) }</div>
							{ extras.map( ( extraLine, extraIndex ) => {
								const linkNode = parseProductLinkLine(
									extraLine,
									productTitle,
									`link-${ index }-${ extraIndex }`
								);

								if ( linkNode ) {
									return (
										<div key={ extraIndex } className="ap-chat-li-link">
											{ linkNode }
										</div>
									);
								}

								if ( isImageOnlyLine( extraLine ) ) {
									return (
										<div key={ extraIndex } className="ap-chat-li-image">
											{ parseInline( extraLine ) }
										</div>
									);
								}

								return (
									<div key={ extraIndex } className="ap-chat-li-extra">
										{ parseInline( extraLine, { linkLabel: productTitle } ) }
									</div>
								);
							} ) }
						</li>
					);
				} ) }
			</Tag>
		);
		listItems = [];
		listType = null;
	};

	lines.forEach( ( line, index ) => {
		const trimmed = line.trim();

		if ( /^\s{2,}\S/.test( line ) && listItems.length && listType ) {
			listItems[ listItems.length - 1 ].extra.push( trimmed );
			return;
		}

		const ulMatch = trimmed.match( /^[-*•]\s+(.+)$/ );
		const olMatch = trimmed.match( /^\d+[.)]\s+(.+)$/ );

		if ( ulMatch ) {
			if ( listType && listType !== 'ul' ) {
				flushList();
			}
			listType = 'ul';
			listItems.push( { main: ulMatch[ 1 ], extra: [] } );
			return;
		}

		if ( olMatch ) {
			if ( listType && listType !== 'ol' ) {
				flushList();
			}
			listType = 'ol';
			listItems.push( { main: olMatch[ 1 ], extra: [] } );
			return;
		}

		flushList();

		if ( ! trimmed ) {
			return;
		}

		const h3Match = trimmed.match( /^###\s+(.+)$/ );
		const h2Match = trimmed.match( /^##\s+(.+)$/ );
		const h1Match = trimmed.match( /^#\s+(.+)$/ );

		if ( h3Match ) {
			blocks.push(
				<h4 key={ `h-${ index }` } className="ap-md-heading">
					{ parseInline( h3Match[ 1 ] ) }
				</h4>
			);
			return;
		}

		if ( h2Match ) {
			blocks.push(
				<h3 key={ `h-${ index }` } className="ap-md-heading">
					{ parseInline( h2Match[ 1 ] ) }
				</h3>
			);
			return;
		}

		if ( h1Match ) {
			blocks.push(
				<h3 key={ `h-${ index }` } className="ap-md-heading ap-md-heading-lg">
					{ parseInline( h1Match[ 1 ] ) }
				</h3>
			);
			return;
		}

		if ( isImageOnlyLine( trimmed ) ) {
			blocks.push(
				<div key={ `img-${ index }` } className="ap-chat-image-row">
					{ parseInline( trimmed ) }
				</div>
			);
			return;
		}

		const standaloneLink = parseProductLinkLine( trimmed, '', `standalone-${ index }` );
		if ( standaloneLink || isLinkOnlyLine( trimmed ) ) {
			blocks.push(
				<div key={ `link-${ index }` } className="ap-chat-link-row">
					{ standaloneLink || parseInline( trimmed ) }
				</div>
			);
			return;
		}

		blocks.push( <p key={ `p-${ index }` }>{ parseInline( trimmed ) }</p> );
	} );

	flushList();

	return blocks;
}
