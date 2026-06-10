import { useState, useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { searchInternalLinks } from '../api';

const TYPE_OPTIONS = [
	{ id: 'all', label: __( 'All', 'agenpress' ) },
	{ id: 'post', label: __( 'Posts', 'agenpress' ) },
	{ id: 'page', label: __( 'Pages', 'agenpress' ) },
	{ id: 'product', label: __( 'Products', 'agenpress' ) },
];

const TYPE_LABELS = {
	post: __( 'Post', 'agenpress' ),
	page: __( 'Page', 'agenpress' ),
	product: __( 'Product', 'agenpress' ),
};

export default function LinkPickerModal( { open, onClose, onSelect, existingIds = [] } ) {
	const [ search, setSearch ] = useState( '' );
	const [ type, setType ] = useState( 'all' );
	const [ results, setResults ] = useState( [] );
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ] = useState( null );
	const inputRef = useRef( null );

	useEffect( () => {
		if ( ! open ) {
			return;
		}

		setSearch( '' );
		setType( 'all' );
		setResults( [] );
		setError( null );

		const timer = setTimeout( () => {
			inputRef.current?.focus();
		}, 50 );

		return () => clearTimeout( timer );
	}, [ open ] );

	useEffect( () => {
		if ( ! open ) {
			return undefined;
		}

		let cancelled = false;
		const timer = setTimeout( async () => {
			setLoading( true );
			setError( null );

			try {
				const items = await searchInternalLinks( search, type );
				if ( ! cancelled ) {
					setResults( items );
				}
			} catch ( err ) {
				if ( ! cancelled ) {
					setError( err.message || __( 'Search failed.', 'agenpress' ) );
					setResults( [] );
				}
			} finally {
				if ( ! cancelled ) {
					setLoading( false );
				}
			}
		}, 280 );

		return () => {
			cancelled = true;
			clearTimeout( timer );
		};
	}, [ open, search, type ] );

	if ( ! open ) {
		return null;
	}

	const handleSelect = ( item ) => {
		onSelect( {
			kind: 'link',
			id: item.post_id,
			post_id: item.post_id,
			post_type: item.post_type,
			name: item.title,
			title: item.title,
			url: item.url,
			type: `link/${ item.post_type }`,
			status: item.status,
			excerpt: item.excerpt || '',
		} );
		onClose();
	};

	return (
		<div
			className="ap-link-picker-overlay"
			onClick={ onClose }
			onKeyDown={ ( e ) => {
				if ( e.key === 'Escape' ) {
					onClose();
				}
			} }
			role="presentation"
		>
			<div
				className="ap-card ap-link-picker-modal"
				onClick={ ( e ) => e.stopPropagation() }
				role="dialog"
				aria-modal="true"
				aria-label={ __( 'Attach internal link', 'agenpress' ) }
			>
				<div className="ap-link-picker-header">
					<h3>{ __( 'Attach internal link', 'agenpress' ) }</h3>
					<button
						type="button"
						className="ap-link-picker-close"
						onClick={ onClose }
						aria-label={ __( 'Close', 'agenpress' ) }
					>
						×
					</button>
				</div>

				<div className="ap-link-picker-filters">
					<input
						ref={ inputRef }
						type="search"
						className="ap-form-input"
						value={ search }
						onChange={ ( e ) => setSearch( e.target.value ) }
						placeholder={ __( 'Search posts, pages, products...', 'agenpress' ) }
					/>
					<div className="ap-link-picker-types">
						{ TYPE_OPTIONS.map( ( option ) => (
							<button
								key={ option.id }
								type="button"
								className={ `ap-link-picker-type${ type === option.id ? ' is-active' : '' }` }
								onClick={ () => setType( option.id ) }
							>
								{ option.label }
							</button>
						) ) }
					</div>
				</div>

				{ error && (
					<div className="ap-alert ap-alert-error" style={ { margin: '0 0 12px' } }>
						{ error }
					</div>
				) }

				<div className="ap-link-picker-results">
					{ loading && (
						<p className="ap-link-picker-empty">{ __( 'Searching...', 'agenpress' ) }</p>
					) }

					{ ! loading && results.length === 0 && (
						<p className="ap-link-picker-empty">
							{ search
								? __( 'No results found.', 'agenpress' )
								: __( 'Type to search or browse recent items.', 'agenpress' ) }
						</p>
					) }

					{ ! loading && results.map( ( item ) => {
						const alreadyAttached = existingIds.includes( item.post_id );

						return (
							<button
								key={ item.post_id }
								type="button"
								className="ap-link-picker-item"
								onClick={ () => handleSelect( item ) }
								disabled={ alreadyAttached }
							>
								<span className="ap-link-picker-item-title">{ item.title }</span>
								<span className="ap-link-picker-item-meta">
									{ TYPE_LABELS[ item.post_type ] || item.post_type }
									{ item.status && item.status !== 'publish' ? ` · ${ item.status }` : '' }
								</span>
								{ item.excerpt && (
									<span className="ap-link-picker-item-excerpt">{ item.excerpt }</span>
								) }
								{ alreadyAttached && (
									<span className="ap-link-picker-item-attached">
										{ __( 'Already attached', 'agenpress' ) }
									</span>
								) }
							</button>
						);
					} ) }
				</div>
			</div>
		</div>
	);
}
