import { createReduxStore, register } from '@wordpress/data';
import { actions } from './actions';

const DEFAULT_STATE = {
    items: [],
    meta: { total: 0, pages: 0, page: 1, per_page: 20 },
    filters: { category: null, stock_status: null, status: null, search: '', orderby: 'name', order: 'asc', page: 1 },
    selectedIds: [],
    loading: false,
    savingIds: {},
    variations: {},
    history: {},
    error: null,
};

const selectors = {
    getProducts: ( state ) => state.items,
    getMeta: ( state ) => state.meta,
    getFilters: ( state ) => state.filters,
    getSelectedIds: ( state ) => state.selectedIds,
    isLoading: ( state ) => state.loading,
    isSaving: ( state, id ) => !! state.savingIds[ id ],
    getVariations: ( state, parentId ) => state.variations[ parentId ] || null,
    getHistory: ( state, productId ) => state.history[ productId ] || null,
    getError: ( state ) => state.error,
};

const reducer = ( state = DEFAULT_STATE, action ) => {
    switch ( action.type ) {
        case 'FETCH_START':
            return { ...state, loading: true, error: null };
        case 'FETCH_SUCCESS':
            return { ...state, loading: false, items: action.items, meta: action.meta };
        case 'FETCH_ERROR':
            return { ...state, loading: false, error: action.error };
        case 'SET_FILTERS':
            return { ...state, filters: { ...state.filters, ...action.filters, page: 1 } };
        case 'SET_PAGE':
            return { ...state, filters: { ...state.filters, page: action.page } };
        case 'TOGGLE_SELECTION':
            const exists = state.selectedIds.includes( action.id );
            return {
                ...state,
                selectedIds: exists
                    ? state.selectedIds.filter( id => id !== action.id )
                    : [ ...state.selectedIds, action.id ],
            };
        case 'SELECT_ALL':
            return { ...state, selectedIds: [ ...action.ids ] };
        case 'CLEAR_SELECTION':
            return { ...state, selectedIds: [] };
        case 'UPDATE_START': {
            const updateItemWithChanges = ( item ) => {
                if ( item.id !== action.id ) return item;
                const updatedStock = item.stock ? { ...item.stock } : {};
                if ( action.changes.manage_stock !== undefined || action.changes.is_managed !== undefined ) {
                    const val = action.changes.is_managed !== undefined ? action.changes.is_managed : action.changes.manage_stock;
                    updatedStock.is_managed = val;
                    updatedStock.manage_stock = val;
                }
                if ( action.changes.stock_quantity !== undefined ) {
                    updatedStock.quantity = action.changes.stock_quantity;
                }
                if ( action.changes.stock_status !== undefined || action.changes.status !== undefined ) {
                    const val = action.changes.stock_status !== undefined ? action.changes.stock_status : action.changes.status;
                    updatedStock.status = val;
                    updatedStock.stock_status = val;
                }
                return { ...item, ...action.changes, stock: updatedStock };
            };
            const updatedVariations = {};
            Object.keys( state.variations ).forEach( parentId => {
                updatedVariations[ parentId ] = state.variations[ parentId ].map( updateItemWithChanges );
            } );
            return {
                ...state,
                savingIds: { ...state.savingIds, [ action.id ]: true },
                items: state.items.map( updateItemWithChanges ),
                variations: updatedVariations,
            };
        }
        case 'UPDATE_SUCCESS': {
            const newSavingIds = { ...state.savingIds };
            delete newSavingIds[ action.id ];
            const successVariations = {};
            Object.keys( state.variations ).forEach( parentId => {
                successVariations[ parentId ] = state.variations[ parentId ].map( item =>
                    item.id === action.id ? action.product : item
                );
            } );
            return {
                ...state,
                savingIds: newSavingIds,
                items: state.items.map( item => item.id === action.id ? action.product : item ),
                variations: successVariations,
            };
        }
        case 'UPDATE_REVERT': {
            const revertSavingIds = { ...state.savingIds };
            delete revertSavingIds[ action.id ];
            return { ...state, savingIds: revertSavingIds };
        }
        case 'FETCH_VARIATIONS_SUCCESS':
            return {
                ...state,
                variations: { ...state.variations, [ action.parentId ]: action.variations }
            };
        case 'FETCH_HISTORY_SUCCESS':
            return {
                ...state,
                history: { ...state.history, [ action.productId ]: action.history }
            };
        default:
            return state;
    }
};

const store = createReduxStore( 'wpm/products', {
    reducer,
    actions,
    selectors,
} );

register( store );

export default store;
export { actions, selectors };
