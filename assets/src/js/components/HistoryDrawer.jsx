import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button, Spinner, Modal } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { formatPrice } from '../utils/formatters';

export default function HistoryDrawer( { productId, productName, isOpen, onClose, onAnnouncement } ) {
    const { history, isLoading } = useSelect( ( select ) => ( {
        history: select( 'wpm/products' ).getHistory( productId ),
        isLoading: select( 'wpm/products' ).isLoading(),
    } ), [ productId ] );

    const { fetchHistory, rollbackChange } = useDispatch( 'wpm/products' );
    const [ rollingBackId, setRollingBackId ] = useState( null );

    useEffect( () => {
        if ( isOpen && productId ) {
            fetchHistory( productId );
        }
    }, [ isOpen, productId ] );

    if ( ! isOpen ) {
        return null;
    }

    const handleRollback = async ( changeId ) => {
        setRollingBackId( changeId );
        onAnnouncement?.( __( 'Rolling back change...', 'woo-price-manager' ), 'polite' );
        const res = await rollbackChange( productId, changeId );
        setRollingBackId( null );
        if ( res && res.success ) {
            onAnnouncement?.( __( 'Successfully rolled back change.', 'woo-price-manager' ), 'polite' );
            fetchHistory( productId );
        } else {
            onAnnouncement?.( __( 'Failed to rollback change.', 'woo-price-manager' ), 'assertive' );
        }
    };

    return (
        <Modal
            title={ sprintf( __( 'Change History: %s', 'woo-price-manager' ), productName || `#${ productId }` ) }
            onRequestClose={ onClose }
            className="wpm-history-drawer"
        >
            <div className="wpm-history-drawer__content">
                { ! history && isLoading ? (
                    <div className="wpm-loading-state">
                        <Spinner />
                        <span>{ __( 'Loading history...', 'woo-price-manager' ) }</span>
                    </div>
                ) : ! history || history.length === 0 ? (
                    <p className="wpm-empty-state">{ __( 'No change history found for this product.', 'woo-price-manager' ) }</p>
                ) : (
                    <ul className="wpm-history-list">
                        { history.map( ( record ) => (
                            <li key={ record.id } className="wpm-history-item">
                                <div className="wpm-history-item__meta">
                                    <span className="wpm-badge wpm-badge--secondary">{ record.field }</span>
                                    <span className="wpm-history-item__type">{ record.operation_type }</span>
                                    { record.created_at && <time className="wpm-history-item__time">{ record.created_at }</time> }
                                </div>
                                <div className="wpm-history-item__values">
                                    <span className="wpm-history-item__old">
                                        { record.field.includes( 'price' ) ? formatPrice( record.old_value ) : ( record.old_value ?? '-' ) }
                                    </span>
                                    <span className="wpm-history-item__arrow">➔</span>
                                    <span className="wpm-history-item__new">
                                        { record.field.includes( 'price' ) ? formatPrice( record.new_value ) : ( record.new_value ?? '-' ) }
                                    </span>
                                </div>
                                { record.operation_type !== 'rollback' && (
                                    <div className="wpm-history-item__action">
                                        <Button
                                            isSmall
                                            isSecondary
                                            disabled={ rollingBackId === record.id }
                                            onClick={ () => handleRollback( record.id ) }
                                        >
                                            { rollingBackId === record.id ? __( 'Reverting...', 'woo-price-manager' ) : __( 'Rollback', 'woo-price-manager' ) }
                                        </Button>
                                    </div>
                                ) }
                            </li>
                        ) ) }
                    </ul>
                ) }
            </div>
        </Modal>
    );
}
