import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { getSettings, updateSettings } from '../api';

const TABS = [
	{
		id: 'widget',
		label: __( 'Widget', 'agenpress' ),
		description: __( 'Appearance and placement on the storefront', 'agenpress' ),
		icon: '💬',
	},
	{
		id: 'behavior',
		label: __( 'AI Behavior', 'agenpress' ),
		description: __( 'Tone and sales rules for customer replies', 'agenpress' ),
		icon: '🤖',
	},
];

const TONE_OPTIONS = [
	{
		id: 'polite',
		label: __( 'Polite', 'agenpress' ),
		description: __( 'Courteous and respectful', 'agenpress' ),
	},
	{
		id: 'friendly',
		label: __( 'Friendly / Casual', 'agenpress' ),
		description: __( 'Warm and conversational', 'agenpress' ),
	},
	{
		id: 'professional',
		label: __( 'Professional', 'agenpress' ),
		description: __( 'Clear and business-like', 'agenpress' ),
	},
];

const SALES_CHAT_DEFAULTS = {
	sales_chat_enabled: false,
	sales_chat_title: '',
	sales_chat_position: 'bottom-right',
	sales_chat_color: '#2271b1',
	sales_chat_tone: 'polite',
	sales_chat_rules_dos: '',
	sales_chat_rules_donts: '',
};

function pickSalesSettings( data ) {
	return {
		sales_chat_enabled: !! data.sales_chat_enabled,
		sales_chat_title: data.sales_chat_title || '',
		sales_chat_position: data.sales_chat_position || 'bottom-right',
		sales_chat_color: data.sales_chat_color || '#2271b1',
		sales_chat_tone: data.sales_chat_tone || 'polite',
		sales_chat_rules_dos: data.sales_chat_rules_dos || '',
		sales_chat_rules_donts: data.sales_chat_rules_donts || '',
	};
}

function WidgetPreview( { title, color, position, enabled } ) {
	const isLeft = position === 'bottom-left';

	return (
		<div className="ap-sales-preview">
			<div className="ap-sales-preview-label">{ __( 'Preview', 'agenpress' ) }</div>
			<div className={ `ap-sales-preview-canvas ${ isLeft ? 'is-left' : 'is-right' }` }>
				<div className="ap-sales-preview-store">
					<div className="ap-sales-preview-bar" />
					<div className="ap-sales-preview-bar short" />
					<div className="ap-sales-preview-bar medium" />
				</div>
				{ enabled ? (
					<div className="ap-sales-preview-widget">
						<div className="ap-sales-preview-header" style={ { background: color } }>
							<span>{ title || __( 'Chat with us', 'agenpress' ) }</span>
						</div>
						<div className="ap-sales-preview-body">
							<div className="ap-sales-preview-bubble user" />
							<div className="ap-sales-preview-bubble bot" />
						</div>
					</div>
				) : (
					<div
						className="ap-sales-preview-fab"
						style={ { background: color } }
						title={ title || __( 'Chat with us', 'agenpress' ) }
					>
						💬
					</div>
				) }
			</div>
		</div>
	);
}

export default function SalesChatSettings() {
	const hasWoo = !! window.agenpressData?.woocommerce;
	const [ tab, setTab ] = useState( 'widget' );
	const [ settings, setSettings ] = useState( SALES_CHAT_DEFAULTS );
	const [ allSettings, setAllSettings ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ success, setSuccess ] = useState( null );

	useEffect( () => {
		getSettings()
			.then( ( data ) => {
				setAllSettings( data );
				setSettings( pickSalesSettings( data ) );
			} )
			.catch( ( err ) => setError( err.message ) )
			.finally( () => setLoading( false ) );
	}, [] );

	const handleChange = ( key, value ) => {
		setSettings( ( prev ) => ( { ...prev, [ key ]: value } ) );
	};

	const handleSave = async ( e ) => {
		e.preventDefault();
		setSaving( true );
		setError( null );
		setSuccess( null );

		try {
			const updated = await updateSettings( { ...allSettings, ...settings } );
			setAllSettings( updated );
			setSettings( pickSalesSettings( updated ) );
			setSuccess( __( 'Settings saved successfully.', 'agenpress' ) );
		} catch ( err ) {
			setError( err.message );
		} finally {
			setSaving( false );
		}
	};

	if ( ! hasWoo ) {
		return (
			<div className="ap-card ap-sales-settings">
				<p className="ap-empty-state">{ __( 'WooCommerce is not active.', 'agenpress' ) }</p>
			</div>
		);
	}

	if ( loading ) {
		return <p className="ap-empty-state">{ __( 'Loading settings...', 'agenpress' ) }</p>;
	}

	const activeTab = TABS.find( ( item ) => item.id === tab ) || TABS[ 0 ];

	return (
		<div className="ap-sales-settings">
			{ error && <div className="ap-alert ap-alert-error">{ error }</div> }
			{ success && <div className="ap-alert ap-alert-success">{ success }</div> }

			<div className="ap-sales-settings-intro ap-card">
				<div>
					<h3 className="ap-sales-settings-title">{ __( 'Storefront Sales Chat', 'agenpress' ) }</h3>
					<p className="ap-sales-settings-desc">
						{ __( 'Configure the floating chat widget and how the sales assistant talks to customers on your store.', 'agenpress' ) }
					</p>
				</div>
				<label className={ `ap-toggle ${ settings.sales_chat_enabled ? 'is-on' : '' }` }>
					<input
						type="checkbox"
						checked={ !! settings.sales_chat_enabled }
						onChange={ ( e ) => handleChange( 'sales_chat_enabled', e.target.checked ) }
					/>
					<span className="ap-toggle-track" aria-hidden="true" />
					<span className="ap-toggle-label">
						{ settings.sales_chat_enabled
							? __( 'Widget enabled', 'agenpress' )
							: __( 'Widget disabled', 'agenpress' ) }
					</span>
				</label>
			</div>

			<div className="ap-settings-tabs" role="tablist">
				{ TABS.map( ( item ) => (
					<button
						key={ item.id }
						type="button"
						role="tab"
						aria-selected={ tab === item.id }
						className={ `ap-settings-tab ${ tab === item.id ? 'active' : '' }` }
						onClick={ () => setTab( item.id ) }
					>
						<span className="ap-settings-tab-icon">{ item.icon }</span>
						<span className="ap-settings-tab-title">{ item.label }</span>
						<span className="ap-settings-tab-desc">{ item.description }</span>
					</button>
				) ) }
			</div>

			<form onSubmit={ handleSave }>
				<div className="ap-settings-tab-panel ap-card" role="tabpanel">
					<div className="ap-settings-tab-panel-header">
						<h4>{ activeTab.label }</h4>
						<p>{ activeTab.description }</p>
					</div>

					{ tab === 'widget' && (
						<div className="ap-sales-widget-layout">
							<div className="ap-sales-widget-fields">
								<div className="ap-form-group">
									<label className="ap-form-label">{ __( 'Widget Title', 'agenpress' ) }</label>
									<input
										className="ap-form-input"
										value={ settings.sales_chat_title }
										onChange={ ( e ) => handleChange( 'sales_chat_title', e.target.value ) }
										placeholder={ __( 'Chat with us', 'agenpress' ) }
									/>
								</div>

								<div className="ap-form-group">
									<label className="ap-form-label">{ __( 'Widget Position', 'agenpress' ) }</label>
									<div className="ap-position-options">
										<label className={ `ap-position-option ${ settings.sales_chat_position === 'bottom-right' ? 'active' : '' }` }>
											<input
												type="radio"
												name="sales_chat_position"
												value="bottom-right"
												checked={ settings.sales_chat_position === 'bottom-right' }
												onChange={ ( e ) => handleChange( 'sales_chat_position', e.target.value ) }
											/>
											<span>{ __( 'Bottom Right', 'agenpress' ) }</span>
										</label>
										<label className={ `ap-position-option ${ settings.sales_chat_position === 'bottom-left' ? 'active' : '' }` }>
											<input
												type="radio"
												name="sales_chat_position"
												value="bottom-left"
												checked={ settings.sales_chat_position === 'bottom-left' }
												onChange={ ( e ) => handleChange( 'sales_chat_position', e.target.value ) }
											/>
											<span>{ __( 'Bottom Left', 'agenpress' ) }</span>
										</label>
									</div>
								</div>

								<div className="ap-form-group">
									<label className="ap-form-label">{ __( 'Widget Color', 'agenpress' ) }</label>
									<div className="ap-color-field">
										<input
											className="ap-form-input ap-color-input"
											type="color"
											value={ settings.sales_chat_color }
											onChange={ ( e ) => handleChange( 'sales_chat_color', e.target.value ) }
										/>
										<input
											className="ap-form-input"
											value={ settings.sales_chat_color }
											onChange={ ( e ) => handleChange( 'sales_chat_color', e.target.value ) }
											placeholder="#2271b1"
										/>
									</div>
								</div>

								<p className="ap-form-hint">
									{ __( 'Also available via shortcode: [agenpress_chat]', 'agenpress' ) }
								</p>
							</div>

							<WidgetPreview
								title={ settings.sales_chat_title }
								color={ settings.sales_chat_color }
								position={ settings.sales_chat_position }
								enabled={ false }
							/>
						</div>
					) }

					{ tab === 'behavior' && (
						<div className="ap-sales-behavior-layout">
							<div className="ap-form-group">
								<label className="ap-form-label">{ __( 'Conversation Tone', 'agenpress' ) }</label>
								<p className="ap-form-hint" style={ { marginTop: 0 } }>
									{ __( 'Controls how the sales assistant speaks to customers.', 'agenpress' ) }
								</p>
								<div className="ap-tone-grid">
									{ TONE_OPTIONS.map( ( tone ) => (
										<button
											key={ tone.id }
											type="button"
											className={ `ap-tone-card ${ settings.sales_chat_tone === tone.id ? 'active' : '' }` }
											onClick={ () => handleChange( 'sales_chat_tone', tone.id ) }
										>
											<span className="ap-tone-card-label">{ tone.label }</span>
											<span className="ap-tone-card-desc">{ tone.description }</span>
										</button>
									) ) }
								</div>
							</div>

							<div className="ap-rules-grid">
								<div className="ap-form-group ap-rules-do">
									<label className="ap-form-label">{ __( 'Sales Rules — Do', 'agenpress' ) }</label>
									<textarea
										className="ap-form-textarea"
										value={ settings.sales_chat_rules_dos }
										onChange={ ( e ) => handleChange( 'sales_chat_rules_dos', e.target.value ) }
										placeholder={ __( 'One rule per line, e.g. Always mention free shipping over $50', 'agenpress' ) }
										rows={ 7 }
									/>
									<p className="ap-form-hint">
										{ __( 'Things the assistant must do when helping customers.', 'agenpress' ) }
									</p>
								</div>

								<div className="ap-form-group ap-rules-dont">
									<label className="ap-form-label">{ __( 'Sales Rules — Do Not', 'agenpress' ) }</label>
									<textarea
										className="ap-form-textarea"
										value={ settings.sales_chat_rules_donts }
										onChange={ ( e ) => handleChange( 'sales_chat_rules_donts', e.target.value ) }
										placeholder={ __( 'One rule per line, e.g. Never promise delivery dates without checking', 'agenpress' ) }
										rows={ 7 }
									/>
									<p className="ap-form-hint">
										{ __( 'Things the assistant must avoid in responses.', 'agenpress' ) }
									</p>
								</div>
							</div>
						</div>
					) }
				</div>

				<div className="ap-sales-settings-actions">
					<button className="ap-btn ap-btn-primary" type="submit" disabled={ saving }>
						{ saving ? __( 'Saving...', 'agenpress' ) : __( 'Save Settings', 'agenpress' ) }
					</button>
				</div>
			</form>
		</div>
	);
}
