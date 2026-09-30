import { apiGet, apiPost } from './client';

export function initializePayment() {
	return apiPost( 'payments/initialize' );
}

export function verifyPayment( reference ) {
	return apiPost( 'payments/verify', { reference } );
}

export function paymentStatus() {
	return apiGet( 'payments/status' );
}
