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
                    <PanelBody initialOpen={true} title={__('Rating Widget', 'rate-my-post')}>
                        <PanelRow>
                            <TextControl
                                label={__('Post ID', 'rate-my-post')}
                                type={'number'}
                                help={__('To display a rating widget for a specific post, enter its ID. Leave the field empty to show the rating widget for the current post.', 'rate-my-post')}
                                onChange={(id) => setAttributes({ id })}
                                value={attributes.id}
                            />
                        </PanelRow>
                    </PanelBody>
                </Panel>
            </InspectorControls>
            <ServerSideRender
                LoadingResponsePlaceholder={Spinner}
                block="feedbackwp/rating-widget"
                attributes={attributes}
            />
        </div>
    );
}