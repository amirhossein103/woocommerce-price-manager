import { __, sprintf } from '@wordpress/i18n';
import { formatPrice } from './formatters';

/**
 * Validate a price field value.
 *
 * @param {string} field Field name ('regular_price' or 'sale_price').
 * @param {number|null} value Numeric value to validate.
 * @param {number|null} regularPrice Current regular price (when validating sale price).
 * @return {string|null} Error message or null if valid.
 */
export function validatePriceField( field, value, regularPrice = null ) {
    if ( value !== null && ( isNaN( value ) || value < 0 ) ) {
        return __( 'Price must be a valid non-negative number.', 'woo-price-manager' );
    }

    if ( field === 'sale_price' && value !== null ) {
        if ( regularPrice === null || regularPrice === 0 ) {
            return __( 'Cannot set sale price without a regular price.', 'woo-price-manager' );
        }
        if ( value >= regularPrice ) {
            return sprintf(
                __( 'Sale price must be strictly less than regular price (%s).', 'woo-price-manager' ),
                formatPrice( regularPrice )
            );
        }
    }

    return null;
}

/**
 * Validate stock quantity.
 *
 * @param {number|null} quantity Stock quantity.
 * @return {string|null} Error message or null if valid.
 */
export function validateStock( quantity ) {
    if ( quantity !== null && ( ! Number.isInteger( quantity ) || quantity < 0 ) ) {
        return __( 'Stock quantity must be a non-negative integer.', 'woo-price-manager' );
    }
    return null;
}
