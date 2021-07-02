/**
 * External dependencies
 */
import { useDispatch } from '@wordpress/data';
import { useEffect } from '@wordpress/element';

export const useCreateNotice = (error) => {
	const { createNotice } = useDispatch('core/notices');

	useEffect(() => {
		if (error) {
			createNotice('error', error);
		}
	}, [error, createNotice]);
};
