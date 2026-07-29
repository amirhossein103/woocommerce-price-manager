import { useState, useRef, useEffect } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { KEYCODE_ENTER, KEYCODE_ESCAPE, KEYCODE_TAB } from '@wordpress/keycodes';
import { useDispatch } from '@wordpress/data';
// Minimal Spinner component to avoid heavy dependency in tests
const Spinner = () => <span className="spinner" />;
import { formatPrice, parsePrice } from '../utils/formatters';
import { validatePriceField, validateStock } from '../utils/validators';

export default function EditableCell( { id, field, value, type = 'price', regularPrice = null, onSaveSuccess, onSaveError } ) {
    const [ isEditing, setIsEditing ] = useState( false );
    const [ isSaving, setIsSaving ] = useState( false );
    const [ showSuccess, setShowSuccess ] = useState( false );
    const [ editValue, setEditValue ] = useState( '' );
    const [ error, setError ] = useState( null );
    const inputRef = useRef( null );
    const { updateProduct } = useDispatch( 'wpm/products' );

    useEffect( () => {
        if ( isEditing && inputRef.current ) {
            inputRef.current.focus();
            inputRef.current.select();
        }
    }, [ isEditing ] );

    const handleStartEdit = () => {
        if ( isSaving ) {
            return;
        }
        setEditValue( value === null || value === undefined ? '' : value.toString() );
        setError( null );
        setIsEditing( true );
    };

    const handleCancel = () => {
        setIsEditing( false );
        setError( null );
    };

    const handleSave = async () => {
        const parsed = type === 'price' ? parsePrice( editValue ) : ( editValue === '' ? null : parseInt( editValue, 10 ) );

        // Client-side validation
        const validationError = type === 'price'
            ? validatePriceField( field, parsed, regularPrice )
            : validateStock( parsed );

        if ( validationError ) {
            setError( validationError );
            return;
        }

        if ( parsed === value ) {
            setIsEditing( false );
            return;
        }

        if ( type === 'price' && parsed === 0 ) {
            if ( ! window.confirm( __( 'Warning: You are attempting to set the price to 0 (free). Do you wish to proceed?', 'woo-price-manager' ) ) ) {
                return;
            }
        }

        setIsEditing( false );
        setIsSaving( true );
        const result = await updateProduct( id, { [ field ]: parsed } );
        setIsSaving( false );

        if ( result && result.success ) {
            setShowSuccess( true );
            setTimeout( () => setShowSuccess( false ), 1500 );
            onSaveSuccess?.();
        } else if ( result && result.error ) {
            onSaveError?.( result.error.message || 'Error updating product' );
        }
    };

    const handleKeyDown = ( e ) => {
        if ( e.keyCode === KEYCODE_ENTER || e.key === 'Enter' || e.keyCode === 13 ) {
            e.preventDefault();
            handleSave();
        } else if ( e.keyCode === KEYCODE_ESCAPE || e.key === 'Escape' || e.keyCode === 27 ) {
            e.preventDefault();
            handleCancel();
        } else if ( e.keyCode === KEYCODE_TAB || e.key === 'Tab' || e.keyCode === 9 ) {
            handleSave();
        }
    };

    if ( isSaving ) {
        return (
            <div className="wpm-cell--saving">
                <Spinner />
                <span>{ __( 'Saving...', 'woo-price-manager' ) }</span>
            </div>
        );
    }

    if ( showSuccess ) {
        return (
            <div className="wpm-cell--success">
                <span className="wpm-cell__success-icon">✓</span>
                <span>{ type === 'price' ? formatPrice( value ) : ( value ?? '-' ) }</span>
            </div>
        );
    }

    if ( isEditing ) {
        return (
            <div className={`wpm-cell--editing ${ error ? 'wpm-cell--has-error' : '' }`}>
                <input
                    ref={ inputRef }
                    type={ type === 'price' ? 'text' : 'number' }
                    value={ editValue }
                    onChange={ ( e ) => setEditValue( e.target.value ) }
                    onKeyDown={ handleKeyDown }
                    onBlur={ handleSave }
                    className="wpm-cell__input"
                    aria-label={ sprintf( __( 'Editing %s', 'woo-price-manager' ), field ) }
                />
                <div className="wpm-cell__hint">{ __( '↵ Enter to save • Esc to cancel', 'woo-price-manager' ) }</div>
                { error && <span className="wpm-cell__error-tooltip">{ error }</span> }
            </div>
        );
    }

    return (
        <div
            className="wpm-cell--editable"
            onClick={ handleStartEdit }
            tabIndex={ 0 }
            onKeyDown={ ( e ) => e.keyCode === KEYCODE_ENTER && handleStartEdit() }
            role="button"
            title={ __( 'Click to edit value', 'woo-price-manager' ) }
            aria-label={ sprintf( __( 'Edit %s', 'woo-price-manager' ), field ) }
        >
            <span className="wpm-cell__value">
                { type === 'price' ? formatPrice( value ) : ( value ?? '-' ) }
            </span>
            <span className="wpm-cell__edit-icon">✎</span>
        </div>
    );
}
