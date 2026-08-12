import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button, Spinner, Modal } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';

export default function GlobalHistoryDrawer( { isOpen, onClose, onAnnouncement } ) {
    const { history, isLoading } = useSelect( ( select ) => ( {
        history: select( 'wpm/products' ).getGlobalHistory(),
        isLoading: select( 'wpm/products' ).isLoading(),
    } ), [] );

    const { fetchGlobalHistory, rollbackBulkJob } = useDispatch( 'wpm/products' );
    const [ rollingBackId, setRollingBackId ] = useState( null );

    useEffect( () => {
        if ( isOpen ) {
            fetchGlobalHistory();
        }
    }, [ isOpen ] );

    if ( ! isOpen ) {
        return null;
    }

    const handleRollback = async ( bulkId ) => {
        if ( ! window.confirm( __( 'Are you sure you want to revert all changes made during this bulk operation?', 'woo-price-manager' ) ) ) {
            return;
        }

        setRollingBackId( bulkId );
        onAnnouncement?.( __( 'Rolling back bulk operation...', 'woo-price-manager' ), 'polite' );
        const res = await rollbackBulkJob( bulkId );
        setRollingBackId( null );
        
        if ( res && res.success ) {
            onAnnouncement?.( sprintf( __( 'Successfully rolled back %d items.', 'woo-price-manager' ), res.data.succeeded || 0 ), 'polite' );
            fetchGlobalHistory();
        } else {
            onAnnouncement?.( __( 'Failed to rollback bulk operation.', 'woo-price-manager' ), 'assertive' );
        }
    };

    return (
        <Modal
            title={ __( 'Global Job History', 'woo-price-manager' ) }
            onRequestClose={ onClose }
            className="wpm-history-drawer"
        >
            <div className="wpm-history-drawer__content">
                <p className="wpm-schedule-modal__description" style={{ marginBottom: '16px' }}>
                    { __( 'View and revert recent bulk operations.', 'woo-price-manager' ) }
                </p>

                { ! history && isLoading ? (
                    <div className="wpm-loading-state">
                        <Spinner />
                        <span>{ __( 'Loading jobs...', 'woo-price-manager' ) }</span>
                    </div>
                ) : ! history || history.length === 0 ? (
                    <p className="wpm-empty-state">{ __( 'No bulk operations found.', 'woo-price-manager' ) }</p>
                ) : (
                    <ul className="wpm-history-list">
                        { history.map( ( job ) => (
                            <li key={ job.bulk_operation_id } className="wpm-history-item" style={{ flexDirection: 'column', alignItems: 'flex-start', gap: '8px' }}>
                                <div className="wpm-history-item__meta" style={{ width: '100%', justifyContent: 'space-between' }}>
                                    <div style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
                                        <span className="wpm-badge wpm-badge--info">{ job.operation_type }</span>
                                        <span className="wpm-history-item__type">{ sprintf( __( '%s items', 'woo-price-manager' ), job.affected_items ) }</span>
                                    </div>
                                    <time className="wpm-history-item__time">{ job.job_date }</time>
                                </div>
                                <div style={{ display: 'flex', justifyContent: 'space-between', width: '100%', alignItems: 'center' }}>
                                    <span style={{ fontSize: '12px', color: '#646970' }}>
                                        { sprintf( __( 'Field: %s | User: %s', 'woo-price-manager' ), job.field, job.user_name ) }
                                    </span>
                                    <Button
                                        isSmall
                                        isSecondary
                                        disabled={ rollingBackId === job.bulk_operation_id }
                                        onClick={ () => handleRollback( job.bulk_operation_id ) }
                                    >
                                        { rollingBackId === job.bulk_operation_id ? __( 'Reverting...', 'woo-price-manager' ) : __( 'Rollback All', 'woo-price-manager' ) }
                                    </Button>
                                </div>
                            </li>
                        ) ) }
                    </ul>
                ) }
            </div>
        </Modal>
    );
}
