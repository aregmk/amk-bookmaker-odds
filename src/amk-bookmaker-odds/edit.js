/**
 * Retrieves the translation of text.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-i18n/
 */
import { __ } from '@wordpress/i18n';

/**
 * React hook that is used to mark the block wrapper element.
 * It provides all the necessary props like the class name.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/#useblockprops
 */
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, CheckboxControl, TextControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useEffect } from '@wordpress/element';
//import { useEntityRecords } from '@wordpress/core-data';
/**
 * Lets webpack process CSS, SASS or SCSS files referenced in JavaScript files.
 * Those files can contain any CSS code that gets applied to the editor.
 *
 * @see https://www.npmjs.com/package/@wordpress/scripts#using-css
 */
import './editor.scss';

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#edit
 *
 * @return {Element} Element to render.
 */
export default function Edit({ attributes, setAttributes, clientId }) {
	const { blockId, url, bookmakers } = attributes;

	const bookmaker_posts = useSelect(select => {
		const posts = select('core').getEntityRecords('postType', 'bookmaker', { per_page: -1, order: 'asc', order_by: 'menu_order title' });
		if(!posts) return [];
		return posts.map(item => ({
			label: item.title.rendered,
			value: item.id
		}));
	}, []);
	//const bookmaker_posts = useEntityRecords('postType', 'bookmaker', { per_page: -1 });

	useEffect(() => {
		// only set if clientId exists and blockId not
		if(clientId && !blockId) {
			setAttributes({ blockId: clientId });
		}
	}, [clientId, blockId, setAttributes]);

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __('Settings', 'amk_bookmaker_odds') }>
					<TextControl
						label={ __('URL', 'amk_bookmaker_odds') }
						value={ url }
						onChange={ url => setAttributes({ url }) }
					/>
					<h4>{ __('Choose Bookmakers', 'amk_bookmaker_odds') }</h4>
					{ bookmaker_posts.map(item => (
						<CheckboxControl
							key={item.value}
							label={item.label}
							checked={ bookmakers.includes(item.value) }
							onChange={ isChecked => {
								const updated_bookmakers = isChecked
									? [...bookmakers, item.value]
									: bookmakers.filter(option => option !== item.value);
								setAttributes({ bookmakers: updated_bookmakers });
							} }
						/>
					)) }
				</PanelBody>
			</InspectorControls>
			<p { ...useBlockProps() }>
				{ __('This block uses dynamic server-side rendering', 'amk_bookmaker_odds') }
			</p>
		</>
	);
}
