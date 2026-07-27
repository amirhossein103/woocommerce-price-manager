import { useEffect, useState } from '@wordpress/element';

export default function LiveRegion( { message, type = 'polite' } ) {
    const [ announcement, setAnnouncement ] = useState( '' );

    useEffect( () => {
        if ( ! message ) {
            return;
        }

        setAnnouncement( message );

        if ( window.wp && window.wp.a11y && window.wp.a11y.speak ) {
            window.wp.a11y.speak( message, type );
        }

        const timer = setTimeout( () => {
            setAnnouncement( '' );
        }, 5000 );

        return () => clearTimeout( timer );
    }, [ message, type ] );

    return (
        <div
            role="status"
            aria-live={ type }
            aria-atomic="true"
            className="wpm-sr-only"
            style={ {
                position: 'absolute',
                width: '1px',
                height: '1px',
                padding: '0',
                margin: '-1px',
                overflow: 'hidden',
                clip: 'rect(0, 0, 0, 0)',
                border: '0',
            } }
        >
            { announcement }
        </div>
    );
}
