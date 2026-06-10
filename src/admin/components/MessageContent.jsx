import { formatMessage } from '../../frontend/formatMessage';

export default function MessageContent( { content, role } ) {
	if ( role === 'assistant' ) {
		return (
			<div className="ap-message-md" dir="auto">
				{ formatMessage( content ) }
			</div>
		);
	}

	return (
		<div dir="auto">
			{ content }
		</div>
	);
}
