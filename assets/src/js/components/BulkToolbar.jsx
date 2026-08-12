import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button, SelectControl, TextControl } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import api from '../api';
import PreviewModal from './PreviewModal';
import { getCurrencyConfig } from '../utils/formatters';

export default function BulkToolbar( { onAnnouncement } ) {
    const { selectedIds } = useSelect( ( select ) => ( {
        selectedIds: select( 'wpm/products' ).getSelectedIds(),
    } ) );
    const { clearSelection, fetchProducts } = useDispatch( 'wpm/products' );

    const [ operationType, setOperationType ] = useState( 'price_percentage_increase' );
    const [ parameter, setParameter ] = useState( '10' );
    const [ targetField, setTargetField ] = useState( 'regular_price' );
    const [ dateFrom, setDateFrom ] = useState( '' );
    const [ dateTo, setDateTo ] = useState( '' );
    const [ isPreviewOpen, setIsPreviewOpen ] = useState( false );
    const [ previewData, setPreviewData ] = useState( null );
    const [ isExecuting, setIsExecuting ] = useState( false );
    const [ error, setError ] = useState( null );

    if ( ! selectedIds || selectedIds.length === 0 ) {
        return null;
    }

    let currencySymbol = '';
    try {
        currencySymbol = getCurrencyConfig().symbol || '';
    } catch ( e ) {
        currencySymbol = '';
    }
    const fixedIncreaseLabel = currencySymbol
        ? sprintf( __( 'Increase Price (%s)', 'woo-price-manager' ), currencySymbol )
        : __( 'Increase Price (Fixed)', 'woo-price-manager' );
    const fixedDecreaseLabel = currencySymbol
        ? sprintf( __( 'Decrease Price (%s)', 'woo-price-manager' ), currencySymbol )
        : __( 'Decrease Price (Fixed)', 'woo-price-manager' );
    const fixedSetLabel = currencySymbol
        ? sprintf( __( 'Set Price (%s)', 'woo-price-manager' ), currencySymbol )
        : __( 'Set Price (Fixed)', 'woo-price-manager' );

    const handlePreview = async () => {
        if ( ! operationType.startsWith( 'stock_' ) ) {
            const val = parseFloat( parameter );
            if ( ( operationType === 'price_fixed_set' && val === 0 ) || ( operationType === 'price_percentage_decrease' && val === 100 ) ) {
                if ( ! window.confirm( __( 'Warning: You are attempting to set the price to 0 (free). Do you wish to proceed?', 'woo-price-manager' ) ) ) {
                    return;
                }
            }
        }
        setError( null );
        try {
            const payload = {
                operation_type: operationType,
                target_field: targetField,
                parameter: parseFloat( parameter ),
                product_ids: selectedIds,
                date_from: targetField === 'sale_price' ? (dateFrom || null) : null,
                date_to: targetField === 'sale_price' ? (dateTo || null) : null,
            };
            const res = await api.previewBulk( payload );
            setPreviewData( res );
            setIsPreviewOpen( true );
        } catch ( err ) {
            setError( err.message || __( 'Failed to generate preview', 'woo-price-manager' ) );
            onAnnouncement?.( __( 'Error generating bulk preview', 'woo-price-manager' ), 'assertive' );
        }
    };

    const handleConfirmExecute = async () => {
        setIsExecuting( true );
        onAnnouncement?.( __( 'Bulk operation started', 'woo-price-manager' ), 'polite' );
        try {
            const payload = {
                operation_type: operationType,
                target_field: targetField,
                parameter: parseFloat( parameter ),
                product_ids: selectedIds,
                date_from: targetField === 'sale_price' ? (dateFrom || null) : null,
                date_to: targetField === 'sale_price' ? (dateTo || null) : null,
            };
            const res = await api.executeBulk( payload );
            setIsExecuting( false );
            setIsPreviewOpen( false );
            clearSelection();
            fetchProducts();
            onAnnouncement?.(
                sprintf( __( 'Bulk operation completed. Successfully updated %d products.', 'woo-price-manager' ), res.data?.succeeded || 0 ),
                'polite'
            );
        } catch ( err ) {
            setIsExecuting( false );
            setError( err.message || __( 'Bulk execution failed', 'woo-price-manager' ) );
            onAnnouncement?.( __( 'Error executing bulk operation', 'woo-price-manager' ), 'assertive' );
        }
    };

    return (
        <div className="wpm-bulk-toolbar">
            <div className="wpm-bulk-toolbar__summary">
                <span>{ sprintf( __( '%d items selected', 'woo-price-manager' ), selectedIds.length ) }</span>
                <Button isLink onClick={ clearSelection } className="wpm-bulk-toolbar__clear">
                    { __( 'Clear', 'woo-price-manager' ) }
                </Button>
            </div>

            <div className="wpm-bulk-toolbar__controls">
                <SelectControl
                    label={ __( 'Operation', 'woo-price-manager' ) }
                    hideLabelFromVision
                    value={ operationType }
                    options={ [
                        { label: __( 'Increase Price (%)', 'woo-price-manager' ), value: 'price_percentage_increase' },
                        { label: __( 'Decrease Price (%)', 'woo-price-manager' ), value: 'price_percentage_decrease' },
                        { label: fixedIncreaseLabel, value: 'price_fixed_increase' },
                        { label: fixedDecreaseLabel, value: 'price_fixed_decrease' },
                        { label: fixedSetLabel, value: 'price_fixed_set' },
                        { label: __( 'Set Stock Quantity', 'woo-price-manager' ), value: 'stock_set' },
                        { label: __( 'Increase Stock Quantity', 'woo-price-manager' ), value: 'stock_increase' },
                        { label: __( 'Clear Stock Management', 'woo-price-manager' ), value: 'stock_clear' },
                    ] }
                    onChange={ ( val ) => {
                        setOperationType( val );
                        if ( val.startsWith( 'stock_' ) ) {
                            setTargetField( 'stock_quantity' );
                        } else {
                            setTargetField( 'regular_price' );
                        }
                    } }
                />

                { ! operationType.startsWith( 'stock_' ) && (
                    <SelectControl
                        label={ __( 'Field', 'woo-price-manager' ) }
                        hideLabelFromVision
                        value={ targetField }
                        options={ [
                            { label: __( 'Regular Price', 'woo-price-manager' ), value: 'regular_price' },
                            { label: __( 'Sale Price', 'woo-price-manager' ), value: 'sale_price' },
                        ] }
                        onChange={ ( val ) => setTargetField( val ) }
                    />
                ) }

                <TextControl
                    label={ __( 'Value', 'woo-price-manager' ) }
                    hideLabelFromVision
                    type="number"
                    value={ parameter }
                    onChange={ ( val ) => setParameter( val ) }
                    className="wpm-bulk-toolbar__input"
                />

                { targetField === 'sale_price' && ! operationType.startsWith( 'stock_' ) && (
                    <div style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
                        <span style={{ color: '#8c8f94', fontSize: '12px' }}>{ __( 'Schedule:', 'woo-price-manager' ) }</span>
                        <input
                            type="date"
                            value={ dateFrom }
                            onChange={ ( e ) => setDateFrom( e.target.value ) }
                            title={ __( 'Sale Start Date', 'woo-price-manager' ) }
                            style={{ padding: '0 8px', height: '30px', fontSize: '13px', borderRadius: '4px', border: '1px solid #8c8f94', background: '#fff', color: '#3c434a' }}
                        />
                        <span style={{ color: '#8c8f94' }}>-</span>
                        <input
                            type="date"
                            value={ dateTo }
                            onChange={ ( e ) => setDateTo( e.target.value ) }
                            title={ __( 'Sale End Date', 'woo-price-manager' ) }
                            style={{ padding: '0 8px', height: '30px', fontSize: '13px', borderRadius: '4px', border: '1px solid #8c8f94', background: '#fff', color: '#3c434a' }}
                        />
                    </div>
                ) }

                <Button isPrimary onClick={ handlePreview }>
                    { __( 'Preview Changes', 'woo-price-manager' ) }
                </Button>
            </div>

            { error && <span className="wpm-bulk-toolbar__error">{ error }</span> }

            <PreviewModal
                isOpen={ isPreviewOpen }
                onClose={ () => setIsPreviewOpen( false ) }
                onConfirm={ handleConfirmExecute }
                previewData={ previewData }
                isExecuting={ isExecuting }
            />
        </div>
    );
}
