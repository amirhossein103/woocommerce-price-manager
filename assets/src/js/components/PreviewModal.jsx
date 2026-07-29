import { __, sprintf } from '@wordpress/i18n';
import { Modal, Button, Spinner } from '@wordpress/components';
import { formatPrice } from '../utils/formatters';

const formatPreviewVal = ( val, operation ) => {
    if ( val === undefined || val === null || val === '' ) {
        return '-';
    }
    if ( operation && typeof operation === 'string' && operation.startsWith( 'stock_' ) ) {
        return val;
    }
    return formatPrice( val );
};

export default function PreviewModal( { isOpen, onClose, onConfirm, previewData, isExecuting, progress } ) {
    if ( ! isOpen ) {
        return null;
    }

    const { data = [], meta = {} } = previewData || {};
    const { total = 0, valid = 0, invalid = 0 } = meta;

    return (
        <Modal
            title={ __( 'Confirm Bulk Operation Preview', 'woo-price-manager' ) }
            onRequestClose={ isExecuting ? undefined : onClose }
            className="wpm-preview-modal"
        >
            <div className="wpm-preview-modal__content">
                <div className="wpm-preview-modal__summary">
                    <span className="wpm-badge wpm-badge--info">
                        { sprintf( __( 'Total: %d', 'woo-price-manager' ), total ) }
                    </span>
                    <span className="wpm-badge wpm-badge--success">
                        { sprintf( __( 'Valid: %d', 'woo-price-manager' ), valid ) }
                    </span>
                    { invalid > 0 && (
                        <span className="wpm-badge wpm-badge--danger">
                            { sprintf( __( 'Invalid: %d', 'woo-price-manager' ), invalid ) }
                        </span>
                    ) }
                </div>

                { isExecuting ? (
                    <div className="wpm-preview-modal__executing">
                        <Spinner />
                        <p>{ __( 'Executing bulk operation... Please do not close your browser tab.', 'woo-price-manager' ) }</p>
                        { progress && (
                            <div className="wpm-progress-bar">
                                <div
                                    className="wpm-progress-bar__fill"
                                    style={ { width: `${ progress.percent || 0 }%` } }
                                />
                            </div>
                        ) }
                    </div>
                ) : (
                    <>
                        <div className="wpm-preview-modal__table-container">
                            <table className="wpm-table wpm-preview-table">
                                <thead>
                                    <tr>
                                        <th>{ __( 'Product ID', 'woo-price-manager' ) }</th>
                                        <th>{ __( 'Current', 'woo-price-manager' ) }</th>
                                        <th>{ __( 'Calculated', 'woo-price-manager' ) }</th>
                                        <th>{ __( 'Status', 'woo-price-manager' ) }</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    { data.slice( 0, 100 ).map( ( item ) => (
                                        <tr key={ item.product_id } className={ ! item.is_valid ? 'wpm-row--error' : '' }>
                                            <td>#{ item.product_id }</td>
                                            <td>{ formatPreviewVal( item.original_price ?? item.current_price, item.operation_applied ) }</td>
                                            <td>{ formatPreviewVal( item.calculated_price, item.operation_applied ) }</td>
                                            <td>
                                                { item.is_valid ? (
                                                    <span className="wpm-status-icon wpm-status--valid">✓</span>
                                                ) : (
                                                    <span className="wpm-status-icon wpm-status--invalid">✗</span>
                                                ) }
                                            </td>
                                        </tr>
                                    ) ) }
                                </tbody>
                            </table>
                            { data.length > 100 && (
                                <p className="wpm-preview-modal__more">
                                    { sprintf( __( 'Showing first 100 of %d products', 'woo-price-manager' ), data.length ) }
                                </p>
                            ) }
                        </div>

                        <div className="wpm-preview-modal__actions">
                            <Button isSecondary onClick={ onClose }>
                                { __( 'Cancel', 'woo-price-manager' ) }
                            </Button>
                            <Button
                                isPrimary
                                onClick={ () => {
                                    const hasZeroPrice = data.some( ( item ) => ! item.operation_applied?.startsWith( 'stock_' ) && parseFloat( item.calculated_price ) === 0 );
                                    if ( hasZeroPrice ) {
                                        if ( ! window.confirm( __( 'Warning: You are attempting to set the price to 0 (free). Do you wish to proceed?', 'woo-price-manager' ) ) ) {
                                            return;
                                        }
                                    }
                                    onConfirm();
                                } }
                                disabled={ valid === 0 }
                            >
                                { sprintf( __( 'Apply to %d Products', 'woo-price-manager' ), valid ) }
                            </Button>
                        </div>
                    </>
                ) }
            </div>
        </Modal>
    );
}
