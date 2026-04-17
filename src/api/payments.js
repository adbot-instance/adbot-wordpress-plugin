import { apiPost } from './client';

export function initializePayment() {
	return apiPost( 'payments/initialize' );
}

export function verifyPayment( reference ) {
	return apiPost( 'payments/verify', { reference } );
}
