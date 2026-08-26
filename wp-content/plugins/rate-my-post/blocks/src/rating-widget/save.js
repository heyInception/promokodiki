import { useBlockProps } from '@wordpress/block-editor';

export default function save({attributes}) {
    const {id} = attributes;
    return (
        <div { ...useBlockProps.save() }>
            { typeof id === 'undefined' ? '[ratemypost]' : `[ratemypost id=${id}]`}
        </div>
    );
}