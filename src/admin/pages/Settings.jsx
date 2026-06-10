import { useState, useEffect, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { getSettings, updateSettings, getApiKeys, createApiKey, deleteApiKey } from '../api';

const PROVIDER_DEFAULTS = {
	openai: { model: 'gpt-5.2', imageModel: 'dall-e-3' },
	claude: { model: 'claude-sonnet-4-6', imageModel: 'dall-e-3' },
	gapgpt: { model: 'gapgpt-qwen-3.6', imageModel: 'gapgpt/z-image' },
	custom: { model: 'gpt-5.2', imageModel: 'dall-e-3' },
};

const PROVIDERS = [
	{
		id: 'openai',
		label: 'OpenAI',
		description: __( 'GPT models via OpenAI API', 'agenpress' ),
		endpoint: 'https://api.openai.com/v1',
		keyField: 'openai_api_key',
		hasKeyField: 'has_openai_key',
		placeholder: 'sk-...',
		supportsImage: true,
	},
	{
		id: 'claude',
		label: __( 'Claude', 'agenpress' ),
		description: __( 'Anthropic Claude models', 'agenpress' ),
		endpoint: 'https://api.anthropic.com/v1',
		keyField: 'claude_api_key',
		hasKeyField: 'has_claude_key',
		placeholder: 'sk-ant-...',
		supportsImage: false,
	},
	{
		id: 'gapgpt',
		label: 'GapGPT',
		description: __( 'Multi-model gateway (OpenAI-compatible)', 'agenpress' ),
		endpoint: 'https://gapgpt.app/api/v1',
		keyField: 'gapgpt_api_key',
		hasKeyField: 'has_gapgpt_key',
		placeholder: 'gapgpt-...',
		supportsImage: true,
	},
	{
		id: 'custom',
		label: __( 'Custom AI Agent', 'agenpress' ),
		description: __( 'Any OpenAI-compatible endpoint', 'agenpress' ),
		keyField: 'custom_api_key',
		hasKeyField: 'has_custom_key',
		placeholder: __( 'Your API key', 'agenpress' ),
		supportsImage: true,
		customEndpoint: true,
		customModelInput: true,
	},
];

function filterModels( catalog, provider, type ) {
	if ( ! catalog || ! provider ) {
		return [];
	}

	const models = catalog[ type ] || [];

	return models.filter( ( model ) => model.providers?.includes( provider ) );
}

function ConfiguredBadge( { active } ) {
	if ( ! active ) {
		return null;
	}

	return (
		<span className="ap-badge ap-badge-configured">
			{ __( 'Configured', 'agenpress' ) }
		</span>
	);
}

function ModelField( { label, value, models, onChange, customInput, placeholder, datalistId } ) {
	return (
		<div className="ap-form-group">
			<label className="ap-form-label">{ label }</label>
			{ customInput ? (
				<input
					className="ap-form-input"
					value={ value }
					onChange={ ( e ) => onChange( e.target.value ) }
					placeholder={ placeholder }
					list={ datalistId }
				/>
			) : (
				<select
					className="ap-form-select"
					value={ value }
					onChange={ ( e ) => onChange( e.target.value ) }
				>
					{ models.map( ( model ) => (
						<option key={ model.id } value={ model.id }>{ model.label }</option>
					) ) }
				</select>
			) }
			{ customInput && (
				<datalist id={ datalistId }>
					{ models.map( ( model ) => (
						<option key={ model.id } value={ model.id }>{ model.label }</option>
					) ) }
				</datalist>
			) }
		</div>
	);
}

export default function Settings() {
	const [ settings, setSettings ] = useState( {
		default_provider: 'openai',
		default_model: 'gpt-4o-mini',
		default_image_model: 'dall-e-3',
		default_image_aspect: '1:1',
		image_aspect_catalog: [],
		custom_api_base_url: '',
		rate_limit: 60,
		license_tier: 'basic',
		ai_language: 'en',
		language_catalog: [],
		openai_api_key: '',
		claude_api_key: '',
		gapgpt_api_key: '',
		custom_api_key: '',
		has_openai_key: false,
		has_claude_key: false,
		has_gapgpt_key: false,
		has_custom_key: false,
		model_catalog: { text: [], image: [] },
	} );
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ success, setSuccess ] = useState( null );
	const [ apiKeys, setApiKeys ] = useState( [] );
	const [ newKeyName, setNewKeyName ] = useState( '' );
	const [ createdKey, setCreatedKey ] = useState( null );

	const activeProvider = useMemo(
		() => PROVIDERS.find( ( p ) => p.id === settings.default_provider ) || PROVIDERS[ 0 ],
		[ settings.default_provider ]
	);

	const textModels = useMemo(
		() => filterModels( settings.model_catalog, settings.default_provider, 'text' ),
		[ settings.model_catalog, settings.default_provider ]
	);

	const imageModels = useMemo(
		() => filterModels( settings.model_catalog, settings.default_provider, 'image' ),
		[ settings.model_catalog, settings.default_provider ]
	);

	const isProviderConfigured = useMemo( () => {
		if ( activeProvider.customEndpoint ) {
			return settings.has_custom_key && !! settings.custom_api_base_url;
		}

		return !! settings[ activeProvider.hasKeyField ];
	}, [ activeProvider, settings ] );

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

	const handleProviderChange = ( provider ) => {
		const defaults = PROVIDER_DEFAULTS[ provider ] || PROVIDER_DEFAULTS.openai;

		setSettings( ( prev ) => {
			const text = filterModels( prev.model_catalog, provider, 'text' );
			const image = filterModels( prev.model_catalog, provider, 'image' );
			const model = text.some( ( m ) => m.id === defaults.model )
				? defaults.model
				: ( text[ 0 ]?.id || prev.default_model );
			const imageModel = image.some( ( m ) => m.id === defaults.imageModel )
				? defaults.imageModel
				: ( image[ 0 ]?.id || prev.default_image_model );

			return {
				...prev,
				default_provider: provider,
				default_model: model,
				default_image_model: imageModel,
			};
		} );
	};

	if ( loading ) {
		return <p className="ap-empty-state">{ __( 'Loading settings...', 'agenpress' ) }</p>;
	}

	const useCustomModelInput = !! activeProvider.customModelInput;

	return (
		<div className="ap-card ap-settings-layout">
			{ error && <div className="ap-alert ap-alert-error">{ error }</div> }
			{ success && <div className="ap-alert ap-alert-success">{ success }</div> }

			<form onSubmit={ handleSave }>
				<h3 className="ap-settings-section-title">{ __( 'AI Provider', 'agenpress' ) }</h3>

				<div className="ap-provider-tabs" role="tablist">
					{ PROVIDERS.map( ( provider ) => (
						<button
							key={ provider.id }
							type="button"
							role="tab"
							aria-selected={ settings.default_provider === provider.id }
							className={ `ap-provider-tab ${ settings.default_provider === provider.id ? 'active' : '' }` }
							onClick={ () => handleProviderChange( provider.id ) }
						>
							<span className="ap-provider-tab-title">{ provider.label }</span>
							<span className="ap-provider-tab-desc">{ provider.description }</span>
						</button>
					) ) }
				</div>

				<div className="ap-provider-panel" role="tabpanel">
					<div className="ap-provider-panel-header">
						<div>
							<h4 className="ap-provider-panel-title">{ activeProvider.label }</h4>
							<p className="ap-provider-panel-desc">{ activeProvider.description }</p>
						</div>
						<ConfiguredBadge active={ isProviderConfigured } />
					</div>

					{ activeProvider.customEndpoint ? (
						<div className="ap-form-group">
							<label className="ap-form-label">{ __( 'API Base URL', 'agenpress' ) }</label>
							<input
								className="ap-form-input"
								value={ settings.custom_api_base_url }
								onChange={ ( e ) => handleChange( 'custom_api_base_url', e.target.value ) }
								placeholder="https://api.example.com/v1"
							/>
							<p className="ap-form-hint">
								{ __( 'OpenAI-compatible base URL including /v1 path.', 'agenpress' ) }
							</p>
						</div>
					) : (
						<div className="ap-form-group">
							<label className="ap-form-label">{ __( 'API Endpoint', 'agenpress' ) }</label>
							<code className="ap-endpoint-chip">{ activeProvider.endpoint }</code>
						</div>
					) }

					<div className="ap-form-group">
						<label className="ap-form-label">{ __( 'API Key', 'agenpress' ) }</label>
						<input
							className="ap-form-input"
							value={ settings[ activeProvider.keyField ] }
							onChange={ ( e ) => handleChange( activeProvider.keyField, e.target.value ) }
							placeholder={
								settings[ activeProvider.hasKeyField ]
									? settings[ activeProvider.keyField ]
									: activeProvider.placeholder
							}
							autoComplete="off"
							spellCheck="false"
						/>
						<p className="ap-form-hint">
							{ settings[ activeProvider.hasKeyField ]
								? __( 'Key is saved. Paste a new value to replace it.', 'agenpress' )
								: __( 'Paste your API key here.', 'agenpress' ) }
						</p>
					</div>

					<ModelField
						label={ __( 'Text Model', 'agenpress' ) }
						value={ settings.default_model }
						models={ textModels }
						customInput={ useCustomModelInput }
						placeholder="gpt-5.2"
						datalistId="ap-text-models"
						onChange={ ( value ) => handleChange( 'default_model', value ) }
					/>

					{ activeProvider.supportsImage && (
						<ModelField
							label={ __( 'Image Model', 'agenpress' ) }
							value={ settings.default_image_model }
							models={ imageModels }
							customInput={ useCustomModelInput }
							placeholder="dall-e-3"
							datalistId="ap-image-models"
							onChange={ ( value ) => handleChange( 'default_image_model', value ) }
						/>
					) }
				</div>

				<h3 className="ap-settings-section-title">{ __( 'Image Generator', 'agenpress' ) }</h3>

				<div className="ap-form-group">
					<label className="ap-form-label">{ __( 'Default Image Dimensions', 'agenpress' ) }</label>
					<select
						className="ap-form-select"
						value={ settings.default_image_aspect || '1:1' }
						onChange={ ( e ) => handleChange( 'default_image_aspect', e.target.value ) }
					>
						{ ( settings.image_aspect_catalog || [] ).map( ( aspect ) => (
							<option key={ aspect.id } value={ aspect.id }>
								{ aspect.label }
							</option>
						) ) }
					</select>
					<p className="ap-form-hint">
						{ __( 'Default aspect ratio used when generating AI images across the plugin.', 'agenpress' ) }
					</p>
				</div>

				<h3 className="ap-settings-section-title">{ __( 'General', 'agenpress' ) }</h3>

				<div className="ap-form-group">
					<label className="ap-form-label">{ __( 'AI Language', 'agenpress' ) }</label>
					<select
						className="ap-form-select"
						value={ settings.ai_language || 'en' }
						onChange={ ( e ) => handleChange( 'ai_language', e.target.value ) }
					>
						{ ( settings.language_catalog || [] ).map( ( language ) => (
							<option key={ language.id } value={ language.id }>
								{ language.label }
							</option>
						) ) }
					</select>
					<p className="ap-form-hint">
						{ __( 'Base language for AI responses and generated content.', 'agenpress' ) }
					</p>
				</div>

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
					<div className="ap-provider-panel" style={ { marginBottom: '16px' } }>
						<h4 className="ap-provider-panel-title" style={ { marginBottom: '12px' } }>
							{ __( 'External API Keys', 'agenpress' ) }
						</h4>
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
						<p className="ap-form-hint">
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
