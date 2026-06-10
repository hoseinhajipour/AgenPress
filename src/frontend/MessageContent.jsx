import { formatMessage } from './formatMessage';

export default function MessageContent( { content, role } ) {
	if ( role === 'assistant' ) {
		return (
			<div className="ap-chat-msg-content ap-chat-md" dir="auto">
				{ formatMessage( content ) }
			</div>
		);
	}

	return (
		<div className="ap-chat-msg-content" dir="auto">
			{ content }
		</div>
	);
}
