/**
 * Strict retrieval of WooCommerce currency formatting rules from localized settings.
 *
 * @throws {Error} When WooCommerce currency settings are missing.
 */
export function getCurrencyConfig() {
    if ( ! window.wpmData || ! window.wpmData.currency ) {
        throw new Error( 'WooCommerce currency settings (wpmData.currency) must be read from WooCommerce without hardcoded defaults.' );
    }
    return window.wpmData.currency;
}

/**
 * Format numeric value as WooCommerce currency string.
 *
 * @param {number|null} value Price value.
 * @return {string} Formatted string (e.g., '₹65,000,000' or '$45.00').
 */
export function formatPrice( value ) {
    if ( value === null || value === undefined || value === '' ) {
        return '-';
    }

    const {
        symbol,
        position,
        decimal_separator: decimalSep,
        thousand_separator: thousandSep,
        decimals
    } = getCurrencyConfig();

    const num = parseFloat( value );
    if ( isNaN( num ) ) {
        return '-';
    }

    // Format parts
    const fixed = num.toFixed( decimals );
    const [ intPart, decPart ] = fixed.split( '.' );
    const formattedInt = intPart.replace( /\B(?=(\d{3})+(?!\d))/g, thousandSep );

    let formattedNum = formattedInt;
    if ( decimals > 0 && decPart ) {
        formattedNum += decimalSep + decPart;
    }

    switch ( position ) {
        case 'left':
            return `${ symbol }${ formattedNum }`;
        case 'right':
            return `${ formattedNum }${ symbol }`;
        case 'left_space':
            return `${ symbol } ${ formattedNum }`;
        case 'right_space':
            return `${ formattedNum } ${ symbol }`;
        default:
            return `${ symbol }${ formattedNum }`;
    }
}

/**
 * Parse user localized string input into clean float number.
 *
 * @param {string} input Localized string (e.g., "65,000.50").
 * @return {number|null} Clean float or null if empty.
 */
export function parsePrice( input ) {
    if ( ! input || typeof input !== 'string' ) {
        return null;
    }

    const { decimal_separator: decimalSep } = getCurrencyConfig();

    // Remove everything except digits, minus, and the designated decimal separator
    const regex = new RegExp( `[^0-9-${ decimalSep }]`, 'g' );
    let cleaned = input.replace( regex, '' );

    // Normalize decimal separator to standard dot for JavaScript parseFloat
    if ( decimalSep !== '.' ) {
        cleaned = cleaned.replace( decimalSep, '.' );
    }

    const parsed = parseFloat( cleaned );
    return isNaN( parsed ) ? null : parsed;
}
