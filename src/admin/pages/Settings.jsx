import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { getSettings, updateSettings, getApiKeys, createApiKey, deleteApiKey } from '../api';

export default function Settings() {
	const [ settings, setSettings ] = useState( {
		default_provider: 'openai',
		default_model: 'gpt-4o-mini',
		rate_limit: 60,
		license_tier: 'basic',
		openai_api_key: '',
		claude_api_key: '',
		has_openai_key: false,
		has_claude_key: false,
	} );
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ success, setSuccess ] = useState( null );
	const [ apiKeys, setApiKeys ] = useState( [] );
	const [ newKeyName, setNewKeyName ] = useState( '' );
	const [ createdKey, setCreatedKey ] = useState( null );

	useEffect( () => {
		Promise.all( [ getSettings(), getApiKeys().catch( () => ( { keys: [] } ) ) ] )
			.then( ( [ settingsData, keysData ] ) => {
				setSettings( settingsData );
				setApiKeys( keysData.keys || [] );
			} )
			.catch( ( err ) => setError( err.message ) )
			.finally( () => setLoading( false ) );
	}, [] );

	const handleSave = async ( e ) => {
		e.preventDefault();
		setSaving( true );
		setError( null );
		setSuccess( null );

		try {
			const updated = await updateSettings( settings );
			setSettings( updated );
			setSuccess( __( 'Settings saved successfully.', 'agenpress' ) );
		} catch ( err ) {
			setError( err.message );
		} finally {
			setSaving( false );
		}
	};

	const handleChange = ( key, value ) => {
		setSettings( ( prev ) => ( { ...prev, [ key ]: value } ) );
	};

	if ( loading ) {
		return <p className="ap-empty-state">{ __( 'Loading settings...', 'agenpress' ) }</p>;
	}

	return (
		<div className="ap-card" style={ { maxWidth: '600px' } }>
			{ error && <div className="ap-alert ap-alert-error">{ error }</div> }
			{ success && <div className="ap-alert ap-alert-success">{ success }</div> }

			<form onSubmit={ handleSave }>
				<div className="ap-form-group">
					<label className="ap-form-label">{ __( 'Default AI Provider', 'agenpress' ) }</label>
					<select
						className="ap-form-select"
						value={ settings.default_provider }
						onChange={ ( e ) => handleChange( 'default_provider', e.target.value ) }
					>
						<option value="openai">OpenAI</option>
						<option value="claude">Claude (Anthropic)</option>
					</select>
				</div>

				<div className="ap-form-group">
					<label className="ap-form-label">{ __( 'Default Model', 'agenpress' ) }</label>
					<input
						className="ap-form-input"
						value={ settings.default_model }
						onChange={ ( e ) => handleChange( 'default_model', e.target.value ) }
						placeholder="gpt-4o-mini"
					/>
				</div>

				<div className="ap-form-group">
					<label className="ap-form-label">
						{ __( 'OpenAI API Key', 'agenpress' ) }
						{ settings.has_openai_key && (
							<span style={ { color: '#22c55e', marginLeft: '8px', fontSize: '12px' } }>
								{ __( 'Configured', 'agenpress' ) }
							</span>
						) }
					</label>
					<input
						className="ap-form-input"
						type="password"
						value={ settings.openai_api_key }
						onChange={ ( e ) => handleChange( 'openai_api_key', e.target.value ) }
						placeholder={ settings.has_openai_key ? settings.openai_api_key : 'sk-...' }
					/>
				</div>

				<div className="ap-form-group">
					<label className="ap-form-label">
						{ __( 'Claude API Key', 'agenpress' ) }
						{ settings.has_claude_key && (
							<span style={ { color: '#22c55e', marginLeft: '8px', fontSize: '12px' } }>
								{ __( 'Configured', 'agenpress' ) }
							</span>
						) }
					</label>
					<input
						className="ap-form-input"
						type="password"
						value={ settings.claude_api_key }
						onChange={ ( e ) => handleChange( 'claude_api_key', e.target.value ) }
						placeholder={ settings.has_claude_key ? settings.claude_api_key : 'sk-ant-...' }
					/>
				</div>

				{ window.agenpressData?.woocommerce && (
					<>
						<h3 style={ { margin: '24px 0 12px', fontSize: '16px' } }>
							{ __( 'Storefront Sales Chat', 'agenpress' ) }
						</h3>
						<div className="ap-form-group">
							<label style={ { display: 'flex', alignItems: 'center', gap: '8px' } }>
								<input
									type="checkbox"
									checked={ !! settings.sales_chat_enabled }
									onChange={ ( e ) => handleChange( 'sales_chat_enabled', e.target.checked ) }
								/>
								{ __( 'Enable floating chat widget on storefront', 'agenpress' ) }
							</label>
							<p style={ { margin: '4px 0 0', fontSize: '12px', color: '#646970' } }>
								{ __( 'Also available via shortcode: [agenpress_chat]', 'agenpress' ) }
							</p>
						</div>
						<div className="ap-form-group">
							<label className="ap-form-label">{ __( 'Widget Title', 'agenpress' ) }</label>
							<input
								className="ap-form-input"
								value={ settings.sales_chat_title || '' }
								onChange={ ( e ) => handleChange( 'sales_chat_title', e.target.value ) }
							/>
						</div>
						<div className="ap-form-group">
							<label className="ap-form-label">{ __( 'Widget Position', 'agenpress' ) }</label>
							<select
								className="ap-form-select"
								value={ settings.sales_chat_position || 'bottom-right' }
								onChange={ ( e ) => handleChange( 'sales_chat_position', e.target.value ) }
							>
								<option value="bottom-right">{ __( 'Bottom Right', 'agenpress' ) }</option>
								<option value="bottom-left">{ __( 'Bottom Left', 'agenpress' ) }</option>
							</select>
						</div>
						<div className="ap-form-group">
							<label className="ap-form-label">{ __( 'Widget Color', 'agenpress' ) }</label>
							<input
								className="ap-form-input"
								type="color"
								value={ settings.sales_chat_color || '#2271b1' }
								onChange={ ( e ) => handleChange( 'sales_chat_color', e.target.value ) }
							/>
						</div>
					</>
				) }

				<div className="ap-form-group">
					<label className="ap-form-label">{ __( 'License Tier', 'agenpress' ) }</label>
					<select
						className="ap-form-select"
						value={ settings.license_tier || 'basic' }
						onChange={ ( e ) => handleChange( 'license_tier', e.target.value ) }
					>
						<option value="basic">{ __( 'Basic', 'agenpress' ) }</option>
						<option value="pro">{ __( 'Pro', 'agenpress' ) }</option>
						<option value="enterprise">{ __( 'Enterprise', 'agenpress' ) }</option>
					</select>
				</div>

				{ settings.license_tier === 'enterprise' && (
					<div className="ap-card" style={ { marginBottom: '16px', padding: '16px', background: '#f8fafc' } }>
						<h3 style={ { margin: '0 0 12px', fontSize: '16px' } }>
							{ __( 'External API Keys', 'agenpress' ) }
						</h3>
						{ createdKey && (
							<div className="ap-alert ap-alert-success" style={ { wordBreak: 'break-all' } }>
								<strong>{ __( 'Copy your key now — it won\'t be shown again:', 'agenpress' ) }</strong>
								<br />{ createdKey }
							</div>
						) }
						<div style={ { display: 'flex', gap: '8px', marginBottom: '12px' } }>
							<input
								className="ap-form-input"
								value={ newKeyName }
								onChange={ ( e ) => setNewKeyName( e.target.value ) }
								placeholder={ __( 'Key name', 'agenpress' ) }
							/>
							<button
								type="button"
								className="ap-btn ap-btn-secondary"
								onClick={ async () => {
									const key = await createApiKey( newKeyName || 'API Key' );
									setCreatedKey( key.key );
									setApiKeys( await getApiKeys().then( ( d ) => d.keys || [] ) );
									setNewKeyName( '' );
								} }
							>
								{ __( 'Generate Key', 'agenpress' ) }
							</button>
						</div>
						{ apiKeys.map( ( key ) => (
							<div key={ key.id } style={ { display: 'flex', justifyContent: 'space-between', padding: '6px 0', fontSize: '13px' } }>
								<span>{ key.name } ({ key.key_hint })</span>
								<button type="button" className="ap-btn ap-btn-danger" style={ { padding: '2px 8px', fontSize: '11px' } } onClick={ async () => {
									await deleteApiKey( key.id );
									setApiKeys( await getApiKeys().then( ( d ) => d.keys || [] ) );
								} }>
									{ __( 'Revoke', 'agenpress' ) }
								</button>
							</div>
						) ) }
						<p style={ { fontSize: '12px', color: '#646970', marginTop: '8px' } }>
							{ __( 'Use Authorization: Bearer agp_... for /external/* and /mcp/* endpoints.', 'agenpress' ) }
						</p>
					</div>
				) }

				<div className="ap-form-group">
					<label className="ap-form-label">{ __( 'Rate Limit (requests/hour)', 'agenpress' ) }</label>
					<input
						className="ap-form-input"
						type="number"
						min="1"
						max="1000"
						value={ settings.rate_limit }
						onChange={ ( e ) => handleChange( 'rate_limit', parseInt( e.target.value, 10 ) ) }
					/>
				</div>

				<button className="ap-btn ap-btn-primary" type="submit" disabled={ saving }>
					{ saving ? __( 'Saving...', 'agenpress' ) : __( 'Save Settings', 'agenpress' ) }
				</button>
			</form>
		</div>
	);
}
