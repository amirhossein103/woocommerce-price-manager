import { render } from '@wordpress/element';
import './store';
import EditableTable from './components/EditableTable';

document.addEventListener( 'DOMContentLoaded', () => {
    const rootElement = document.getElementById( 'wpm-root' ) || document.getElementById( 'wpm-app-root' ) || document.getElementById( 'woo-price-manager-root' ) || document.getElementById( 'woo-ops-root' );
    if ( rootElement ) {
        render( <EditableTable />, rootElement );
    }
} );
