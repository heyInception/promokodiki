import { __ } from '@wordpress/i18n';
import {
    TextControl,
    ToggleControl,
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
                    <PanelBody>
                        <PanelRow>
                            <TextControl
                                label={__('Number of Posts', 'rate-my-post')}
                                type="number"
                                value={attributes.number}
                                onChange={(number) => setAttributes({ number })}
                            />
                        </PanelRow>
                        <PanelRow>
                            <TextControl
                                label={__('Minimum Rating', 'rate-my-post')}
                                value={attributes.minimum_rating}
                                onChange={(minimum_rating) => setAttributes({ minimum_rating })}
                            />
                        </PanelRow>
                        <PanelRow>
                            <TextControl
                                label={__('Minimum Votes', 'rate-my-post')}
                                value={attributes.minimum_votes}
                                type="number"
                                onChange={(minimum_votes) => setAttributes({ minimum_votes })}
                            />
                        </PanelRow>
                        <PanelRow>
                            <ToggleControl
                                label={__('Show Featured Image', 'rate-my-post')}
                                checked={ attributes.show_featured_image }
                                onChange={(show_featured_image) => setAttributes({ show_featured_image })}
                            />
                        </PanelRow>
                        <PanelRow>
                            <ToggleControl
                                label={__('Show Star Rating', 'rate-my-post')}
                                checked={ attributes.show_star_rating }
                                onChange={(show_star_rating) => setAttributes({ show_star_rating })}
                            />
                        </PanelRow>
                    </PanelBody>
                </Panel>
            </InspectorControls>
            <ServerSideRender
                LoadingResponsePlaceholder={Spinner}
                block="feedbackwp/top-rated-posts"
                attributes={attributes}
            />
        </div>
    );
}