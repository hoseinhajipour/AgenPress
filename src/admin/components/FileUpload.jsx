import FileUpload from '../../shared/FileUpload';
import { uploadFile } from '../api';

export default function AdminFileUpload( props ) {
	return <FileUpload { ...props } uploadFile={ uploadFile } />;
}
