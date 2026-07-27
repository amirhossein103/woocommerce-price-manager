import api from '../api';

export const actions = {
    setFilters( filters ) {
        return { type: 'SET_FILTERS', filters };
    },
    setPage( page ) {
        return { type: 'SET_PAGE', page };
    },
    toggleSelection( id ) {
        return { type: 'TOGGLE_SELECTION', id };
    },
    selectAll( ids ) {
        return { type: 'SELECT_ALL', ids };
    },
    clearSelection() {
        return { type: 'CLEAR_SELECTION' };
    },
    // Thunks (Async Actions)
    fetchProducts() {
        return async ( { dispatch, select } ) => {
            dispatch( { type: 'FETCH_START' } );
            try {
                const filters = select.getFilters();
                const response = await api.getProducts( filters );
                dispatch( { type: 'FETCH_SUCCESS', items: response.data || [], meta: response.meta || {} } );
            } catch ( error ) {
                dispatch( { type: 'FETCH_ERROR', error: error.message || 'Failed to fetch products' } );
            }
        };
    },
    updateProduct( id, changes ) {
        return async ( { dispatch } ) => {
            dispatch( { type: 'UPDATE_START', id, changes } );
            try {
                const response = await api.updateProduct( id, changes );
                dispatch( { type: 'UPDATE_SUCCESS', id, product: response.data || response } );
                return { success: true, data: response.data || response };
            } catch ( error ) {
                dispatch( { type: 'UPDATE_REVERT', id } );
                return { success: false, error };
            }
        };
    },
    fetchVariations( parentId ) {
        return async ( { dispatch } ) => {
            dispatch( { type: 'FETCH_VARIATIONS_START', parentId } );
            try {
                const response = await api.getVariations( parentId );
                dispatch( { type: 'FETCH_VARIATIONS_SUCCESS', parentId, variations: response.data || [] } );
            } catch ( error ) {
                dispatch( { type: 'FETCH_VARIATIONS_ERROR', parentId, error: error.message || 'Failed to fetch variations' } );
            }
        };
    },
    fetchHistory( productId ) {
        return async ( { dispatch } ) => {
            dispatch( { type: 'FETCH_HISTORY_START', productId } );
            try {
                const response = await api.getHistory( productId );
                dispatch( { type: 'FETCH_HISTORY_SUCCESS', productId, history: response.data || [] } );
            } catch ( error ) {
                dispatch( { type: 'FETCH_HISTORY_ERROR', productId, error: error.message || 'Failed to fetch history' } );
            }
        };
    },
    rollbackChange( productId, changeId ) {
        return async ( { dispatch } ) => {
            dispatch( { type: 'ROLLBACK_START', productId, changeId } );
            try {
                const response = await api.rollback( productId, changeId );
                dispatch( { type: 'ROLLBACK_SUCCESS', productId, result: response.data || response } );
                // Refetch product state to ensure consistency
                dispatch( actions.fetchProducts() );
                dispatch( actions.fetchVariations( productId ) );
                return { success: true, data: response.data || response };
            } catch ( error ) {
                dispatch( { type: 'ROLLBACK_ERROR', productId, error } );
                return { success: false, error };
            }
        };
    }
};
