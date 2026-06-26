import { createContext, useContext, useEffect, useState, useCallback } from '@wordpress/element';
import { getOnboarding, updateOnboarding } from '../api/onboarding';

const OnboardingContext = createContext( null );

const STEP_ORDER = [ 'welcome', 'connect', 'property', 'ga4', 'audit', 'report', 'pay', 'apply', 'done' ];

// Steps the user is NOT allowed to rewind past (irreversible or in-flight).
const NO_BACK_FROM = [ 'welcome', 'apply', 'done' ];

export function OnboardingProvider( { children } ) {
	const [ state, setState ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ skippedLocal, setSkippedLocal ] = useState( false );

	const refresh = useCallback( () => {
		return getOnboarding()
			.then( ( data ) => {
				setState( data );
				setSkippedLocal( !! data.skipped );
				setLoading( false );
				return data;
			} )
			.catch( () => setLoading( false ) );
	}, [] );

	useEffect( () => {
		refresh();
	}, [ refresh ] );

	const advance = useCallback(
		async ( nextStep, extra = {} ) => {
			const payload = { step: nextStep, markCompleted: state?.step, ...extra };
			const updated = await updateOnboarding( payload );
			setState( updated );
			return updated;
		},
		[ state ]
	);

	const setAuditId = useCallback( async ( auditId ) => {
		const updated = await updateOnboarding( { auditId } );
		setState( updated );
		return updated;
	}, [] );

	const skip = useCallback( async () => {
		setSkippedLocal( true );
		const updated = await updateOnboarding( { skipped: true } );
		setState( updated );
	}, [] );

	const goTo = useCallback( async ( step ) => {
		const updated = await updateOnboarding( { step } );
		setState( updated );
		return updated;
	}, [] );

	const back = useCallback( async () => {
		const current = state?.step;
		if ( ! current ) return;
		const idx = STEP_ORDER.indexOf( current );
		if ( idx <= 0 ) return;
		const prev = STEP_ORDER[ idx - 1 ];
		if ( NO_BACK_FROM.includes( current ) ) return;
		const updated = await updateOnboarding( { step: prev } );
		setState( updated );
	}, [ state ] );

	const canGoBack = (() => {
		const current = state?.step;
		if ( ! current ) return false;
		if ( NO_BACK_FROM.includes( current ) ) return false;
		return STEP_ORDER.indexOf( current ) > 0;
	})();

	const resume = useCallback( async () => {
		setSkippedLocal( false );
		const updated = await updateOnboarding( { skipped: false } );
		setState( updated );
	}, [] );

	const value = {
		state,
		loading,
		skipped: skippedLocal,
		refresh,
		advance,
		back,
		canGoBack,
		goTo,
		setAuditId,
		skip,
		resume,
	};

	return <OnboardingContext.Provider value={ value }>{ children }</OnboardingContext.Provider>;
}

export function useOnboarding() {
	const ctx = useContext( OnboardingContext );
	if ( ! ctx ) {
		throw new Error( 'useOnboarding must be used within OnboardingProvider' );
	}
	return ctx;
}
