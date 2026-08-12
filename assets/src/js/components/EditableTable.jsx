import { useState, useEffect, memo } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button, CheckboxControl, Notice, SearchControl, SelectControl, Spinner } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import EditableCell from './EditableCell';
import HistoryDrawer from './HistoryDrawer';
import GlobalHistoryDrawer from './GlobalHistoryDrawer';
import BulkToolbar from './BulkToolbar';
import LiveRegion from './LiveRegion';
import SaleScheduleModal from './SaleScheduleModal';
import { formatPrice } from '../utils/formatters';
import api from '../api';

const VariationRow = memo( function VariationRow( { variation, parentId, parentManageStock, isSelected, onToggleSelect, onAnnouncement, onOpenSchedule } ) {
    const { updateProduct, setDefaultVariation } = useDispatch( 'wpm/products' );
    const isVarManaged = !!( variation.stock?.is_managed ?? variation.stock?.manage_stock );

    return (
        <tr className={`wpm-row--variation ${ variation.is_default ? 'wpm-row--is-default' : '' }`}>
            <td className="wpm-cell--checkbox">
                <CheckboxControl
                    checked={ isSelected }
                    onChange={ () => onToggleSelect( variation.id ) }
                    aria-label={ sprintf( __( 'Select variation #%d', 'woo-price-manager' ), variation.id ) }
                />
            </td>
            <td className="wpm-cell--image"></td>
            <td className="wpm-cell--name">
                <span className="wpm-variation-indent">↳</span>
                <span className="wpm-variation-attributes">
                    { Object.entries( variation.attributes || {} ).map( ( [ k, v ] ) => `${ k }: ${ v }` ).join( ', ' ) || `Variation #${ variation.id }` }
                </span>
            </td>
            <td className="wpm-cell--sku">{ variation.sku || '-' }</td>
            <td className="wpm-cell--type">
                <span className="wpm-badge wpm-badge--subtle">{ __( 'Var', 'woo-price-manager' ) }</span>
            </td>
            <td className="wpm-cell--manage-stock">
                <CheckboxControl
                    checked={ isVarManaged }
                    onChange={ ( val ) => {
                        const qty = variation.stock?.quantity ?? 0;
                        updateProduct( variation.id, val ? { is_managed: true, manage_stock: true, stock_quantity: qty } : { is_managed: false, manage_stock: false } );
                    } }
                    aria-label={ sprintf( __( 'Manage stock for variation #%d', 'woo-price-manager' ), variation.id ) }
                />
            </td>
            <td className="wpm-cell--stock">
                { !isVarManaged ? (
                    <span className="wpm-cell-readonly wpm-opacity-50">—</span>
                ) : (
                    <EditableCell
                        id={ variation.id }
                        field="stock_quantity"
                        value={ variation.stock?.quantity }
                        type="stock"
                        onSaveSuccess={ () => onAnnouncement?.( __( 'Variation stock updated', 'woo-price-manager' ), 'polite' ) }
                    />
                ) }
            </td>
            <td className="wpm-cell--stock-status">
                { isVarManaged ? (
                    <span className={ `wpm-badge ${ (variation.stock?.quantity > 0) ? 'wpm-badge--success' : 'wpm-badge--danger' }` }>
                        { (variation.stock?.quantity > 0) ? __( 'In Stock', 'woo-price-manager' ) : __( 'Out of Stock', 'woo-price-manager' ) }
                    </span>
                ) : (
                    <SelectControl
                        value={ variation.stock?.status || 'instock' }
                        options={[
                            { label: __( 'In Stock', 'woo-price-manager' ), value: 'instock' },
                            { label: __( 'Out of Stock', 'woo-price-manager' ), value: 'outofstock' },
                            { label: __( 'On Backorder', 'woo-price-manager' ), value: 'onbackorder' },
                        ]}
                        onChange={ ( val ) => {
                            updateProduct( variation.id, { stock_status: val, status: val } );
                        } }
                    />
                ) }
            </td>
            <td className="wpm-cell--regular-price">
                <EditableCell
                    id={ variation.id }
                    field="regular_price"
                    value={ variation.price?.regular_price }
                    type="price"
                    onSaveSuccess={ () => onAnnouncement?.( __( 'Variation regular price updated', 'woo-price-manager' ), 'polite' ) }
                />
            </td>
            <td className="wpm-cell--sale-price">
                <div className="wpm-flex-center-gap4">
                    <EditableCell
                        id={ variation.id }
                        field="sale_price"
                        value={ variation.price?.sale_price }
                        type="price"
                        regularPrice={ variation.price?.regular_price }
                        onSaveSuccess={ () => onAnnouncement?.( __( 'Variation sale price updated', 'woo-price-manager' ), 'polite' ) }
                    />
                    <Button
                        isSmall
                        variant="tertiary"
                        onClick={ () => onOpenSchedule?.( { ...variation, name: `Variation #${variation.id}` } ) }
                        title={ (variation.price?.date_on_sale_from || variation.price?.date_on_sale_to) ? sprintf( __( 'Scheduled: %s to %s', 'woo-price-manager' ), variation.price?.date_on_sale_from || 'Now', variation.price?.date_on_sale_to || 'Forever' ) : __( 'Schedule Sale Dates', 'woo-price-manager' ) }
                        aria-label={ __( 'Schedule Sale Dates', 'woo-price-manager' ) }
                        className={`wpm-schedule-btn wpm-icon-btn-small ${(variation.price?.date_on_sale_from || variation.price?.date_on_sale_to) ? 'is-scheduled' : ''}`}
                    >
                        📅
                    </Button>
                </div>
            </td>
            <td className="wpm-cell--actions">
                <Button
                    isSmall
                    variant="tertiary"
                    onClick={ () => {
                        if ( !variation.is_default && parentId ) {
                            setDefaultVariation( parentId, variation.id ).then( ( res ) => {
                                if ( res.success ) {
                                    onAnnouncement?.( __( 'Default variation updated', 'woo-price-manager' ), 'polite' );
                                } else {
                                    onAnnouncement?.( __( 'Failed to update default variation', 'woo-price-manager' ), 'assertive' );
                                }
                            } );
                        }
                    } }
                    disabled={ variation.is_default }
                    title={ variation.is_default ? __( 'Current default variation', 'woo-price-manager' ) : __( 'Set as default variation', 'woo-price-manager' ) }
                    aria-label={ variation.is_default ? __( 'Current default variation', 'woo-price-manager' ) : __( 'Set as default variation', 'woo-price-manager' ) }
                    className={`wpm-set-default-btn wpm-icon-btn-default ${ variation.is_default ? 'is-default' : '' }`}
                >
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false">
                        { variation.is_default ? (
                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path>
                        ) : (
                            <path d="M12 4.1l2.35 4.77 5.25.76-3.8 3.7.9 5.23L12 16.1l-4.7 2.47.9-5.23-3.8-3.7 5.25-.76L12 4.1m0-2.1L8.91 8.26 2 9.27l5 4.87-1.18 6.88L12 17.77l6.18 3.25L17 14.14l5-4.87-6.91-1.01L12 2z"></path>
                        )}
                    </svg>
                </Button>
            </td>
        </tr>
    );
} );

const ProductRow = memo( function ProductRow( { product, isSelected, selectedIds = [], onToggleSelect, onOpenHistory, onAnnouncement, onOpenSchedule } ) {
    const [ isExpanded, setIsExpanded ] = useState( false );
    const { variations } = useSelect( ( select ) => ( {
        variations: select( 'wpm/products' ).getVariations( product.id ),
    } ), [ product.id ] );
    const { fetchVariations, updateProduct, setSelection } = useDispatch( 'wpm/products' );

    const isVariable = product.type === 'variable';
    const isManaged = !!( product.stock?.is_managed ?? product.stock?.manage_stock );

    const handleToggleExpand = () => {
        if ( ! isExpanded && isVariable && ! variations ) {
            fetchVariations( product.id );
        }
        setIsExpanded( ! isExpanded );
    };

    const handleParentToggle = () => {
        onToggleSelect( product.id );
    };

    const handleVariationToggle = ( varId ) => {
        let newIds = [ ...selectedIds ];
        const varExists = newIds.includes( varId );
        
        if ( isSelected ) {
            // If parent was checked, and user toggles a variation, uncheck parent and check all OTHER loaded variations explicitly.
            newIds = newIds.filter( id => id !== product.id ); // Remove parent
            if ( variations ) {
                variations.forEach( v => {
                    if ( v.id !== varId && !newIds.includes( v.id ) ) {
                        newIds.push( v.id );
                    }
                } );
            }
        } else {
            // Standard toggle for variation
            if ( varExists ) {
                newIds = newIds.filter( id => id !== varId );
            } else {
                newIds.push( varId );
            }
        }
        
        if ( setSelection ) {
            setSelection( newIds );
        }
    };


    return (
        <>
            <tr className={ `wpm-row ${ isSelected ? 'wpm-row--selected' : '' }` }>
                <td className="wpm-cell--checkbox">
                    <div className="wpm-flex-center-gap4">
                        <CheckboxControl
                            checked={ isSelected }
                            onChange={ handleParentToggle }
                            aria-label={ sprintf( __( 'Select %s', 'woo-price-manager' ), product.name ) }
                        />
                        { isVariable && (
                            <span 
                                title={ __( 'Selecting this product will apply bulk edits to all its variations.', 'woo-price-manager' ) } 
                                className="wpm-info-icon"
                            >
                                ℹ️
                            </span>
                        ) }
                    </div>
                </td>
                <td className="wpm-cell--image">
                    { product.image_url ? (
                        <img src={ product.image_url } alt={ product.name } className="wpm-product-thumb" />
                    ) : (
                        <div className="wpm-product-thumb-placeholder">📷</div>
                    ) }
                </td>
                <td className="wpm-cell--name">
                    <div className="wpm-product-title">
                        <strong>{ product.name }</strong>
                        { isVariable && (
                            <Button
                                isSmall
                                isLink
                                onClick={ handleToggleExpand }
                                className="wpm-expand-toggle"
                                aria-expanded={ isExpanded }
                            >
                                { isExpanded ? __( '▲ Hide Variations', 'woo-price-manager' ) : __( '▼ Show Variations', 'woo-price-manager' ) }
                            </Button>
                        ) }
                    </div>
                </td>
                <td className="wpm-cell--sku">{ product.sku || '-' }</td>
                <td className="wpm-cell--type">
                    <span className="wpm-badge">
                        { product.type === 'variable' ? __( 'variable', 'woo-price-manager' ) : product.type === 'simple' ? __( 'simple', 'woo-price-manager' ) : product.type }
                    </span>
                </td>
                <td className="wpm-cell--manage-stock">
                    <CheckboxControl
                        checked={ isManaged }
                        onChange={ ( val ) => {
                            if ( val ) {
                                const qty = product.stock?.quantity ?? 0;
                                updateProduct( product.id, { is_managed: true, manage_stock: true, stock_quantity: qty } );
                            } else {
                                updateProduct( product.id, { is_managed: false, manage_stock: false } );
                            }
                        } }
                        aria-label={ sprintf( __( 'Manage stock for %s', 'woo-price-manager' ), product.name ) }
                    />
                </td>
                <td className="wpm-cell--stock">
                    { !isManaged ? (
                        <span className="wpm-cell-readonly wpm-opacity-50">—</span>
                    ) : (
                        <EditableCell
                            id={ product.id }
                            field="stock_quantity"
                            value={ product.stock?.quantity }
                            type="stock"
                            onSaveSuccess={ () => onAnnouncement?.( sprintf( __( 'Stock updated for %s', 'woo-price-manager' ), product.name ), 'polite' ) }
                        />
                    ) }
                </td>
                <td className="wpm-cell--stock-status">
                    { isManaged ? (
                        <span className={ `wpm-badge ${ (product.stock?.quantity > 0) ? 'wpm-badge--success' : 'wpm-badge--danger' }` }>
                            { (product.stock?.quantity > 0) ? __( 'In Stock', 'woo-price-manager' ) : __( 'Out of Stock', 'woo-price-manager' ) }
                        </span>
                    ) : (
                        <SelectControl
                            value={ product.stock?.status || 'instock' }
                            options={[
                                { label: __( 'In Stock', 'woo-price-manager' ), value: 'instock' },
                                { label: __( 'Out of Stock', 'woo-price-manager' ), value: 'outofstock' },
                                { label: __( 'On Backorder', 'woo-price-manager' ), value: 'onbackorder' },
                            ]}
                            onChange={ ( val ) => {
                                updateProduct( product.id, { stock_status: val, status: val } );
                            } }
                        />
                    ) }
                </td>
                <td className="wpm-cell--regular-price">
                    { isVariable ? (
                        <span className="wpm-cell-readonly">{ formatPrice( product.price?.regular_price ) }</span>
                    ) : (
                        <EditableCell
                            id={ product.id }
                            field="regular_price"
                            value={ product.price?.regular_price }
                            type="price"
                            onSaveSuccess={ () => onAnnouncement?.( sprintf( __( 'Regular price updated for %s', 'woo-price-manager' ), product.name ), 'polite' ) }
                        />
                    ) }
                </td>
                <td className="wpm-cell--sale-price">
                    { isVariable ? (
                        <span className="wpm-cell-readonly">{ formatPrice( product.price?.sale_price ) }</span>
                    ) : (
                        <div className="wpm-flex-center-gap4">
                            <EditableCell
                                id={ product.id }
                                field="sale_price"
                                value={ product.price?.sale_price }
                                type="price"
                                regularPrice={ product.price?.regular_price }
                                onSaveSuccess={ () => onAnnouncement?.( sprintf( __( 'Sale price updated for %s', 'woo-price-manager' ), product.name ), 'polite' ) }
                            />
                            <Button
                                isSmall
                                variant="tertiary"
                                onClick={ () => onOpenSchedule?.( product ) }
                                title={ (product.price?.date_on_sale_from || product.price?.date_on_sale_to) ? sprintf( __( 'Scheduled: %s to %s', 'woo-price-manager' ), product.price?.date_on_sale_from || 'Now', product.price?.date_on_sale_to || 'Forever' ) : __( 'Schedule Sale Dates', 'woo-price-manager' ) }
                                aria-label={ __( 'Schedule Sale Dates', 'woo-price-manager' ) }
                                className={`wpm-schedule-btn wpm-icon-btn-small ${(product.price?.date_on_sale_from || product.price?.date_on_sale_to) ? 'is-scheduled' : ''}`}
                            >
                                📅
                            </Button>
                        </div>
                    ) }
                </td>
                <td className="wpm-cell--actions">
                    <Button
                        isSmall
                        isSecondary
                        onClick={ () => onOpenHistory( product ) }
                        title={ __( 'View Change History', 'woo-price-manager' ) }
                    >
                        🕒
                    </Button>
                </td>
            </tr>
            { isExpanded && isVariable && variations && variations.map( ( varItem ) => (
                <VariationRow 
                    key={ varItem.id } 
                    variation={ varItem } 
                    parentId={ product.id } 
                    parentManageStock={ !!product.stock?.manage_stock } 
                    isSelected={ selectedIds.includes( varItem.id ) }
                    onToggleSelect={ handleVariationToggle }
                    onAnnouncement={ onAnnouncement } 
                    onOpenSchedule={ onOpenSchedule } 
                />
            ) ) }
        </>
    );
} );

const buildCategoryOptions = ( cats = [] ) => {
    const options = [ { label: __( 'All Categories', 'woo-price-manager' ), value: '' } ];
    if ( ! Array.isArray( cats ) || ! cats.length ) {
        return options;
    }
    const byId = {};
    const roots = [];
    const children = {};

    cats.forEach( ( c ) => {
        byId[ c.id ] = c;
        if ( ! c.parent_id || ( ! byId[ c.parent_id ] && ! cats.some( p => p.id === c.parent_id ) ) ) {
            roots.push( c );
        } else {
            if ( ! children[ c.parent_id ] ) children[ c.parent_id ] = [];
            children[ c.parent_id ].push( c );
        }
    } );

    const addCat = ( cat, depth = 0 ) => {
        const prefix = depth > 0 ? '— '.repeat( depth ) : '';
        options.push( {
            label: `${ prefix }${ cat.name } (${ cat.count })`,
            value: String( cat.id ),
        } );
        if ( children[ cat.id ] ) {
            children[ cat.id ].forEach( ( child ) => addCat( child, depth + 1 ) );
        }
    };

    roots.forEach( ( root ) => addCat( root, 0 ) );
    return options;
};

export default function EditableTable() {
    const { products, meta, filters, selectedIds, isLoading, categories } = useSelect( ( select ) => ( {
        products: select( 'wpm/products' ).getProducts(),
        meta: select( 'wpm/products' ).getMeta(),
        filters: select( 'wpm/products' ).getFilters(),
        selectedIds: select( 'wpm/products' ).getSelectedIds(),
        isLoading: select( 'wpm/products' ).isLoading(),
        categories: select( 'wpm/products' ).getCategories(),
    } ) );

    const { fetchProducts, setFilters, setPage, toggleSelection, selectAll, clearSelection, setSelection, fetchCategories } = useDispatch( 'wpm/products' );
    const [ historyProduct, setHistoryProduct ] = useState( null );
    const [ isGlobalHistoryOpen, setIsGlobalHistoryOpen ] = useState( false );
    const [ scheduleItem, setScheduleItem ] = useState( null );
    const [ announcement, setAnnouncement ] = useState( '' );
    const [ announceType, setAnnounceType ] = useState( 'polite' );

    useEffect( () => {
        if ( ! categories.length ) {
            fetchCategories();
        }
    }, [] );

    useEffect( () => {
        fetchProducts();
    }, [ filters ] );

    useEffect( () => {
        if ( ! announcement ) {
            return;
        }
        const timer = setTimeout( () => {
            setAnnouncement( '' );
        }, 7000 );
        return () => clearTimeout( timer );
    }, [ announcement ] );

    const handleAnnouncement = ( msg, type = 'polite' ) => {
        setAnnouncement( msg );
        setAnnounceType( type );
    };

    const isAllSelected = products.length > 0 && products.every( ( p ) => selectedIds.includes( p.id ) );

    const handleToggleSelectAll = () => {
        if ( isAllSelected ) {
            clearSelection();
        } else {
            selectAll( products.map( ( p ) => p.id ) );
        }
    };

    return (
        <div className="wpm-app-container">
            <LiveRegion message={ announcement } type={ announceType } />

            <header className="wpm-header">
                <div>
                    <h1>{ __( 'WooCommerce Price & Stock Manager', 'woo-price-manager' ) }</h1>
                    <div className="wpm-header__stats">
                        <span>{ sprintf( __( 'Total Products: %d', 'woo-price-manager' ), meta.total || 0 ) }</span>
                        { isLoading && (
                            <span className="wpm-loading-stats">
                                <Spinner /> { __( 'Updating catalog...', 'woo-price-manager' ) }
                            </span>
                        ) }
                    </div>
                </div>
                <div style={{ display: 'flex', gap: '8px' }}>
                    <Button 
                        isSecondary 
                        onClick={ () => setIsGlobalHistoryOpen( true ) }
                    >
                        { __( 'Global Jobs History', 'woo-price-manager' ) }
                    </Button>
                    <Button 
                        isPrimary 
                        onClick={ fetchProducts } 
                        disabled={ isLoading }
                    >
                        { isLoading ? __( 'Refreshing...', 'woo-price-manager' ) : __( 'Refresh Data', 'woo-price-manager' ) }
                    </Button>
                </div>
            </header>

            { announcement && (
                <div className="wpm-visible-notice wpm-mb-16">
                    <Notice
                        status={ announceType === 'assertive' ? 'error' : 'success' }
                        isDismissible
                        onRemove={ () => setAnnouncement( '' ) }
                    >
                        { announcement }
                    </Notice>
                </div>
            ) }

            <div className="wpm-filter-bar">
                <SearchControl
                    label={ __( 'Search Products', 'woo-price-manager' ) }
                    value={ filters.search || '' }
                    onChange={ ( val ) => setFilters( { search: val } ) }
                    placeholder={ __( 'Search by name or SKU...', 'woo-price-manager' ) }
                />
                <SelectControl
                    label={ __( 'Category', 'woo-price-manager' ) }
                    value={ filters.category || '' }
                    options={ buildCategoryOptions( categories ) }
                    onChange={ ( val ) => setFilters( { category: val || null } ) }
                />
                <SelectControl
                    label={ __( 'Stock Status', 'woo-price-manager' ) }
                    value={ filters.stock_status || '' }
                    options={ [
                        { label: __( 'All Stock Statuses', 'woo-price-manager' ), value: '' },
                        { label: __( 'In Stock', 'woo-price-manager' ), value: 'instock' },
                        { label: __( 'Out of Stock', 'woo-price-manager' ), value: 'outofstock' },
                    ] }
                    onChange={ ( val ) => setFilters( { stock_status: val || null } ) }
                />
            </div>

            <div className="wpm-table-container">
                { isLoading && ! products.length ? (
                    <div className="wpm-loading-state">
                        <Spinner />
                        <span>{ __( 'Loading catalog...', 'woo-price-manager' ) }</span>
                    </div>
                ) : (
                    <table className="wpm-table">
                        <thead>
                            <tr>
                                <th className="wpm-cell--checkbox">
                                    <CheckboxControl
                                        checked={ isAllSelected }
                                        onChange={ handleToggleSelectAll }
                                        aria-label={ __( 'Select all products on page', 'woo-price-manager' ) }
                                    />
                                </th>
                                <th className="wpm-cell--image">{ __( 'Image', 'woo-price-manager' ) }</th>
                                <th className="wpm-cell--name">{ __( 'Product Name', 'woo-price-manager' ) }</th>
                                <th className="wpm-cell--sku">{ __( 'SKU', 'woo-price-manager' ) }</th>
                                <th className="wpm-cell--type">{ __( 'Type', 'woo-price-manager' ) }</th>
                                <th className="wpm-cell--manage-stock">{ __( 'Manage Stock', 'woo-price-manager' ) }</th>
                                <th className="wpm-cell--stock">{ __( 'Stock', 'woo-price-manager' ) }</th>
                                <th className="wpm-cell--stock-status">{ __( 'Stock Status', 'woo-price-manager' ) }</th>
                                <th className="wpm-cell--regular-price">{ __( 'Regular Price', 'woo-price-manager' ) }</th>
                                <th className="wpm-cell--sale-price">{ __( 'Sale Price', 'woo-price-manager' ) }</th>
                                <th className="wpm-cell--actions">{ __( 'History', 'woo-price-manager' ) }</th>
                            </tr>
                        </thead>
                        <tbody>
                            { products.length === 0 ? (
                                <tr>
                                    <td colSpan="11" className="wpm-empty-row">
                                        { __( 'No products found matching your filters.', 'woo-price-manager' ) }
                                    </td>
                                </tr>
                            ) : (
                                products.map( ( p ) => (
                                    <ProductRow
                                        key={ p.id }
                                        product={ p }
                                        isSelected={ selectedIds.includes( p.id ) }
                                        selectedIds={ selectedIds }
                                        onToggleSelect={ toggleSelection }
                                        onOpenHistory={ ( prod ) => setHistoryProduct( prod ) }
                                        onAnnouncement={ handleAnnouncement }
                                        onOpenSchedule={ ( item ) => setScheduleItem( item ) }
                                    />
                                ) )
                            ) }
                        </tbody>
                    </table>
                ) }
            </div>

            { meta.pages > 1 && (
                <div className="wpm-pagination">
                    <Button
                        isSecondary
                        disabled={ meta.page <= 1 || isLoading }
                        onClick={ () => setPage( meta.page - 1 ) }
                    >
                        { __( '← Previous', 'woo-price-manager' ) }
                    </Button>
                    <span className="wpm-pagination__info">
                        { sprintf( __( 'Page %d of %d', 'woo-price-manager' ), meta.page, meta.pages ) }
                    </span>
                    <Button
                        isSecondary
                        disabled={ meta.page >= meta.pages || isLoading }
                        onClick={ () => setPage( meta.page + 1 ) }
                    >
                        { __( 'Next →', 'woo-price-manager' ) }
                    </Button>
                </div>
            ) }

            <BulkToolbar onAnnouncement={ handleAnnouncement } />

            <HistoryDrawer
                productId={ historyProduct?.id }
                productName={ historyProduct?.name }
                isOpen={ !! historyProduct }
                onClose={ () => setHistoryProduct( null ) }
                onAnnouncement={ handleAnnouncement }
            />

            <GlobalHistoryDrawer
                isOpen={ isGlobalHistoryOpen }
                onClose={ () => setIsGlobalHistoryOpen( false ) }
                onAnnouncement={ handleAnnouncement }
            />

            <SaleScheduleModal
                isOpen={ !! scheduleItem }
                onClose={ () => setScheduleItem( null ) }
                product={ scheduleItem }
                onSaveSuccess={ () => handleAnnouncement( sprintf( __( 'Sale schedule updated for %s', 'woo-price-manager' ), scheduleItem?.name || `ID #${scheduleItem?.id}` ), 'polite' ) }
            />
        </div>
    );
}
