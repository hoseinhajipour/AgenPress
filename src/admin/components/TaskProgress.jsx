export default function TaskProgress( { progress = 0, status = 'pending' } ) {
	return (
		<div>
			<div style={ { display: 'flex', justifyContent: 'space-between', marginBottom: '4px', fontSize: '12px' } }>
				<span className={ `ap-badge ap-badge-${ status }` }>{ status }</span>
				<span>{ progress }%</span>
			</div>
			<div className="ap-progress-bar">
				<div
					className="ap-progress-fill"
					style={ { width: `${ progress }%` } }
				/>
			</div>
		</div>
	);
}
