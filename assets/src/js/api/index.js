import apiFetch from '@wordpress/api-fetch';

const NAMESPACE = '/woo-ops/v1';

const api = {
    /**
     * Get paginated products list with filters.
     * @param {Object} params Query parameters.
     * @return {Promise<Object>} API response with data and meta.
     */
    async getProducts( params = {} ) {
        const query = new URLSearchParams( params ).toString();
        const path = query ? `${ NAMESPACE }/products?${ query }` : `${ NAMESPACE }/products`;
        return apiFetch( { path } );
    },

    /**
     * Get categories list.
     * @return {Promise<Object>} API response.
     */
    async getCategories() {
        return apiFetch( { path: `${ NAMESPACE }/categories` } );
    },

    /**
     * Update single product or variation price/stock.
     * @param {number} id Product ID.
     * @param {Object} changes Key-value pairs to update.
     * @return {Promise<Object>} Updated product object.
     */
    async updateProduct( id, changes ) {
        return apiFetch( {
            path: `${ NAMESPACE }/products/${ id }`,
            method: 'PUT',
            data: changes,
        } );
    },

    /**
     * Get variations for a variable product.
     * @param {number} parentId Variable product ID.
     * @return {Promise<Object>} Variations list.
     */
    async getVariations( parentId ) {
        return apiFetch( { path: `${ NAMESPACE }/products/${ parentId }/variations` } );
    },

    /**
     * Preview bulk operation results.
     * @param {Object} payload Bulk operation specification.
     * @return {Promise<Object>} Calculation results.
     */
    async previewBulk( payload ) {
        return apiFetch( {
            path: `${ NAMESPACE }/products/bulk/preview`,
            method: 'POST',
            data: payload,
        } );
    },

    /**
     * Execute confirmed bulk operation.
     * @param {Object} payload Confirmed operation specification.
     * @return {Promise<Object>} Execution summary.
     */
    async executeBulk( payload ) {
        return apiFetch( {
            path: `${ NAMESPACE }/products/bulk/execute`,
            method: 'POST',
            data: payload,
        } );
    },

    /**
     * Get change log history for a product.
     * @param {number} productId Product ID.
     * @return {Promise<Object>} History records.
     */
    async getHistory( productId ) {
        return apiFetch( { path: `${ NAMESPACE }/products/${ productId }/history` } );
    },

    /**
     * Rollback a specific change record.
     * @param {number} productId Product ID.
     * @param {number} changeId Change record ID.
     * @return {Promise<Object>} Restored state and new record.
     */
    async rollback( productId, changeId ) {
        return apiFetch( {
            path: `${ NAMESPACE }/products/${ productId }/history/${ changeId }/rollback`,
            method: 'POST',
        } );
    },

    /**
     * Set a variation as the default for its parent product.
     * @param {number} parentId Product ID.
     * @param {number} variationId Variation ID.
     * @return {Promise<Object>} Response object.
     */
    async setDefaultVariation( parentId, variationId ) {
        return apiFetch( {
            path: `${ NAMESPACE }/products/${ parentId }/default-variation`,
            method: 'PUT',
            data: { variation_id: variationId },
        } );
    },

    /**
     * Get global bulk operation history.
     */
    async getGlobalHistory() {
        return apiFetch( { path: `${ NAMESPACE }/history/global` } );
    },

    /**
     * Roll back an entire bulk operation.
     */
    async rollbackBulk( bulkId ) {
        return apiFetch( {
            path: `${ NAMESPACE }/history/global/${ bulkId }/rollback`,
            method: 'POST',
        } );
    }
};

export default api;
