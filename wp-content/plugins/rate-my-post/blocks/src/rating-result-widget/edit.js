import { __ } from '@wordpress/i18n';
import {
    TextControl,
    Panel,
    PanelBody,
    PanelRow,
    Spinner
} from '@wordpress/components';

import {
    useBlockProps,
    InspectorControls,
} from '@wordpress/block-editor';

import ServerSideRender from '@wordpress/server-side-render';

export default function Edit({ attributes, setAttributes }) {
    return (
        <div {...useBlockProps()}>
            <InspectorControls key="setting">
                <Panel>
                    <PanelBody initialOpen={true} title={__('Rating Result Widget', 'rate-my-post')}>
                        <PanelRow>
                            <TextControl
                                label={__('Post ID', 'rate-my-post')}
                                type={'number'}
                                help={__('Enter a post ID to display the rating result for a specific post. Leave it empty to show the result for the current post.', 'rate-my-post')}
                                onChange={(id) => setAttributes({ id })}
                                value={attributes.id}
                            />
                        </PanelRow>
                    </PanelBody>
                </Panel>
            </InspectorControls>
            <ServerSideRender
                LoadingResponsePlaceholder={Spinner}
                block="feedbackwp/rating-result-widget"
                attributes={attributes}
            />
        </div>
    );
}