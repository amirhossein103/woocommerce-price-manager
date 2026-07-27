import { useState, useEffect } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Modal, Button } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';

export default function SaleScheduleModal( { isOpen, onClose, product, onSaveSuccess } ) {
    const [ dateFrom, setDateFrom ] = useState( '' );
    const [ dateTo, setDateTo ] = useState( '' );
    const [ isSaving, setIsSaving ] = useState( false );
    const [ error, setError ] = useState( null );
    const { updateProduct } = useDispatch( 'wpm/products' );

    useEffect( () => {
        if ( isOpen && product ) {
            setDateFrom( product.price?.date_on_sale_from || '' );
            setDateTo( product.price?.date_on_sale_to || '' );
            setError( null );
        }
    }, [ isOpen, product ] );

    if ( ! isOpen || ! product ) {
        return null;
    }

    const handleSave = async () => {
        if ( dateFrom && dateTo && dateTo < dateFrom ) {
            setError( __( 'End date cannot be earlier than start date.', 'woo-price-manager' ) );
            return;
        }
        setIsSaving( true );
        setError( null );
        const res = await updateProduct( product.id, {
            date_on_sale_from: dateFrom || '',
            date_on_sale_to: dateTo || ''
        } );
        setIsSaving( false );
        if ( res && res.success ) {
            onClose();
            onSaveSuccess?.();
        } else {
            setError( res?.error?.message || __( 'Failed to save sale schedule.', 'woo-price-manager' ) );
        }
    };

    const handleClear = async () => {
        setIsSaving( true );
        setError( null );
        const res = await updateProduct( product.id, {
            date_on_sale_from: '',
            date_on_sale_to: ''
        } );
        setIsSaving( false );
        if ( res && res.success ) {
            onClose();
            onSaveSuccess?.();
        } else {
            setError( res?.error?.message || __( 'Failed to clear sale schedule.', 'woo-price-manager' ) );
        }
    };

    return (
        <Modal
            title={ sprintf( __( 'Schedule Sale for "%s"', 'woo-price-manager' ), product.name || `ID #${product.id}` ) }
            onRequestClose={ isSaving ? undefined : onClose }
            className="wpm-schedule-modal"
        >
            <div className="wpm-schedule-modal__content">
                <p className="wpm-schedule-modal__description" style={{ marginBottom: '12px' }}>
                    { __( 'Set starting and ending dates for the sale price using standard WooCommerce date format (YYYY-MM-DD).', 'woo-price-manager' ) }
                </p>

                { error && (
                    <div className="wpm-notice wpm-notice--error" style={{ marginBottom: '16px', color: '#cc1818', background: '#ffebe8', padding: '8px 12px', borderRadius: '4px' }}>
                        { error }
                    </div>
                ) }

                <div className="wpm-schedule-modal__fields" style={{ display: 'flex', gap: '16px', marginBottom: '20px' }}>
                    <div className="wpm-field" style={{ flex: 1 }}>
                        <label htmlFor="wpm-date-from" style={{ display: 'block', fontWeight: 'bold', marginBottom: '6px' }}>
                            { __( 'Sale Start Date', 'woo-price-manager' ) }
                        </label>
                        <input
                            id="wpm-date-from"
                            type="date"
                            className="date-picker wpm-date-input"
                            value={ dateFrom }
                            onChange={ ( e ) => setDateFrom( e.target.value ) }
                            disabled={ isSaving }
                            placeholder="YYYY-MM-DD"
                            style={{ width: '100%', padding: '6px 8px', borderRadius: '4px', border: '1px solid #8c8f94' }}
                        />
                        <span className="wpm-field__hint" style={{ display: 'block', fontSize: '12px', color: '#646970', marginTop: '4px' }}>
                            { __( 'From 00:00:00 on this date.', 'woo-price-manager' ) }
                        </span>
                    </div>

                    <div className="wpm-field" style={{ flex: 1 }}>
                        <label htmlFor="wpm-date-to" style={{ display: 'block', fontWeight: 'bold', marginBottom: '6px' }}>
                            { __( 'Sale End Date', 'woo-price-manager' ) }
                        </label>
                        <input
                            id="wpm-date-to"
                            type="date"
                            className="date-picker wpm-date-input"
                            value={ dateTo }
                            onChange={ ( e ) => setDateTo( e.target.value ) }
                            disabled={ isSaving }
                            placeholder="YYYY-MM-DD"
                            style={{ width: '100%', padding: '6px 8px', borderRadius: '4px', border: '1px solid #8c8f94' }}
                        />
                        <span className="wpm-field__hint" style={{ display: 'block', fontSize: '12px', color: '#646970', marginTop: '4px' }}>
                            { __( 'Until 23:59:59 on this date.', 'woo-price-manager' ) }
                        </span>
                    </div>
                </div>

                <div className="wpm-schedule-modal__actions" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', borderTop: '1px solid #dcdcde', paddingTop: '16px' }}>
                    <div>
                        { ( product.price?.date_on_sale_from || product.price?.date_on_sale_to ) && (
                            <Button
                                isDestructive
                                variant="link"
                                onClick={ handleClear }
                                disabled={ isSaving }
                            >
                                { __( 'Clear Schedule', 'woo-price-manager' ) }
                            </Button>
                        ) }
                    </div>
                    <div style={{ display: 'flex', gap: '8px' }}>
                        <Button
                            isSecondary
                            onClick={ onClose }
                            disabled={ isSaving }
                        >
                            { __( 'Cancel', 'woo-price-manager' ) }
                        </Button>
                        <Button
                            isPrimary
                            onClick={ handleSave }
                            disabled={ isSaving }
                            isBusy={ isSaving }
                        >
                            { isSaving ? __( 'Saving...', 'woo-price-manager' ) : __( 'Save Schedule', 'woo-price-manager' ) }
                        </Button>
                    </div>
                </div>
            </div>
        </Modal>
    );
}
