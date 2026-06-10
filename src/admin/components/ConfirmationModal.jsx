import { __ } from '@wordpress/i18n';

export default function ConfirmationModal( { pending, onConfirm, onCancel, loading } ) {
	if ( ! pending ) {
		return null;
	}

	return (
		<div
			style={ {
				position: 'fixed',
				inset: 0,
				background: 'rgba(0,0,0,0.5)',
				display: 'flex',
				alignItems: 'center',
				justifyContent: 'center',
				zIndex: 100000,
			} }
		>
			<div className="ap-card" style={ { maxWidth: '480px', width: '90%', margin: 0 } }>
				<h3 style={ { margin: '0 0 12px', fontSize: '16px' } }>
					{ __( 'Confirm Action', 'agenpress' ) }
				</h3>
				<p style={ { margin: '0 0 8px', fontSize: '14px', color: '#334155' } }>
					{ pending.message }
				</p>
				<p style={ { margin: '0 0 16px', fontSize: '12px', color: '#94a3b8' } }>
					{ __( 'Tool:', 'agenpress' ) } <code>{ pending.tool }</code>
				</p>
				<div style={ { display: 'flex', gap: '8px', justifyContent: 'flex-end' } }>
					<button
						className="ap-btn ap-btn-secondary"
						onClick={ onCancel }
						disabled={ loading }
					>
						{ __( 'Cancel', 'agenpress' ) }
					</button>
					<button
						className="ap-btn ap-btn-danger"
						onClick={ onConfirm }
						disabled={ loading }
					>
						{ loading ? __( 'Executing...', 'agenpress' ) : __( 'Confirm', 'agenpress' ) }
					</button>
				</div>
			</div>
		</div>
	);
}
