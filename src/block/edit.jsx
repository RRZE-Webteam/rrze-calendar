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
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';

/**
 * Lets webpack process CSS, SASS or SCSS files referenced in JavaScript files.
 * Those files can contain any CSS code that gets applied to the editor.
 *
 * @see https://www.npmjs.com/package/@wordpress/scripts#using-css
 */
import './editor.scss';

import ServerSideRender from "@wordpress/server-side-render";
import { PanelBody, ComboboxControl, TextControl, ToggleControl, SelectControl } from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import {select, useSelect} from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';


/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#edit
 *
 * @return {Element} Element to render.
 */

export default function Edit({ attributes, setAttributes }) {
	const blockProps = useBlockProps();
	const [layout, setLayout] = useState( attributes.layout || 'calendar-full' );
	const { abonnementLink} = attributes;
	const { pageLink } = attributes;
	const { pageLinkLabel } = attributes;
	const { numEvents } = attributes;
	const [selectedCategories, setSelectedCategories] = useState(attributes.selectedCategories || []);
	const [selectedTags, setSelectedTags] = useState(attributes.selectedTags || []);
	const { includeEvents } = attributes;
	const { excludeEvents } = attributes;


	const onChangeLayout = (value) => {
		setLayout( value );
		setAttributes({layout: value});
	};

	// Page Link Settings
	const pages = useSelect((select) => {
		return select(coreStore).getEntityRecords('postType', 'page', {
			per_page: -1,
		});
	}, []);
	const pageOptions = pages ? pages.map((page) => ({
			label: page.title.rendered || __('(No title)', 'rrze-calendar'),
			value: page.id,
		})) : [];

	const onChangePageLink = (value) => {
		setAttributes({pageLink: value});
	};

	const onChangePageLinkLabel = (value) => {
		setAttributes({ pageLinkLabel: value });
	};

	// Category Settings
	const categories = useSelect(select => {
		return select('core').getEntityRecords('taxonomy', 'rrze-calendar-category', { per_page: -1 }) || [];
	}, []);

	const onAddCategory = (categoryId) => {
		if (!selectedCategories.includes(categoryId)) {
			const newCategories = [...selectedCategories, categoryId];
			setSelectedCategories(newCategories);
			setAttributes({ selectedCategories: newCategories });
		}
	};

	const onRemoveCategory = (categoryId) => {
		const newCategories = selectedCategories.filter(id => id !== categoryId);
		setSelectedCategories(newCategories);
		setAttributes({ selectedCategories: newCategories });
	};

	const categoryOptions = categories ? categories.map(category => ({
		label: category.name,
		value: category.slug
	})) : [];

	// Tag Settings
	const tags = useSelect(select => {
		return select('core').getEntityRecords('taxonomy', 'rrze-calendar-tag', { per_page: -1 }) || [];
	}, []);

	const onAddTag = (tagId) => {
		if (!selectedTags.includes(tagId)) {
			const newTags = [...selectedTags, tagId];
			setSelectedTags(newTags);
			setAttributes({ selectedTags: newTags });
		}
	};

	const onRemoveTag = (tagId) => {
		const newTags = selectedTags.filter(id => id !== tagId);
		setSelectedTags(newTags);
		setAttributes({ selectedTags: newTags });
	};

	const tagOptions = tags ? tags.map(tag => ({
		label: tag.name,
		value: tag.slug
	})) : [];

	const onChangeIncludeEvents = (value) => {
		setAttributes({ includeEvents: value });
	};

	const onChangeExcludeEvents = (value) => {
		setAttributes({ excludeEvents: value });
	};

	// Number Settings
	const onChangeNumber = (value) => {
		// Sicherstellen, dass nur Zahlen gespeichert werden
		const newNumber = parseInt(value, 10);
		if (!isNaN(newNumber) && newNumber >= -1) {
			setAttributes({ numEvents: newNumber });
		} else {
			setAttributes({ numEvents: '' });
		}
	};

	return (
		<div {...blockProps}>
			<InspectorControls>
				<PanelBody title={__('Layout', 'rrze-calendar')}>
					<SelectControl
						label={__('Layout', 'rrze-calendar')}
							value={layout}
						options={[
							{label: __('Event List', 'rrze-calendar'), value: 'teaser'},
							{label: __('Event List Short', 'rrze-calendar'), value: 'list'},
							{label: __('Calendar Full', 'rrze-calendar'), value: 'full'},
							{label: __('Calendar Mini', 'rrze-calendar'), value: 'mini'}
						]}
						onChange={onChangeLayout}
					/>
					{(layout === "teaser" || layout === "list") && (
						<TextControl
							label={__('Count', 'rrze-calendar')}
							type="number"
							min={0}
							step={1}
							value={numEvents}
							onChange={onChangeNumber}
							help={__('How many events do you want to show? Enter 0 or leave empty for all events.', 'rrze-calendar')}
						/>
					)}
					<ToggleControl
						__nextHasNoMarginBottom
						checked={!!abonnementLink}
						label={__('ICS Link', 'rrze-calendar')}
						help={__('Show link to ICS file', 'rrze-calendar')}
						onChange={() =>
							setAttributes({
								abonnementLink: !abonnementLink,
							})
						}
					/>
					<ComboboxControl
						label={__('Page Link', 'rrze-calendar')}
						options={pageOptions}
						onChange={onChangePageLink}
						help={__('Link to all events page', 'rrze-calendar')}
					/>
					{pageLink !== "" && (
						<TextControl
						label={__('Page Link Label', 'rrze-calendar')}
						type="text"
						value={pageLinkLabel}
						onChange={onChangePageLinkLabel}
						help={__('Link text', 'rrze-calendar')}
						/>
					)}
				</PanelBody>
				<PanelBody title={__('Select Events', 'rrze-calendar')}>
					<ComboboxControl
						label={__('Categories', 'rrze-calendar')}
						options={categoryOptions}
						onChange={onAddCategory}
					/>
					<div style={{marginTop: '10px'}}>
						{__('Selected Categories', 'rrze-calendar')}:
						<ul>
							{selectedCategories.map(categorySlug => {
								const category = categories.find(t => t.slug === categorySlug);
								return (
									<li key={categorySlug}>
										{category?.name}
										<button onClick={() => onRemoveCategory(categorySlug)} style={{marginLeft: '5px'}}>
											{__('Remove', 'rrze-calendar')}
										</button>
									</li>
								);
							})}
						</ul>
					</div>
					<hr/>
					<ComboboxControl
						label={__('Tags', 'rrze-calendar')}
						options={tagOptions}
						onChange={onAddTag}
					/>
					<div style={{marginTop: '10px'}}>
						{__('Selected Tags', 'rrze-calendar')}:
						<ul>
							{selectedTags.map(tagSlug => {
								const tag = tags.find(t => t.slug === tagSlug);
								return (
									<li key={tagSlug}>
										{tag?.name}
										<button onClick={() => onRemoveTag(tagSlug)} style={{marginLeft: '5px'}}>
											{__('Remove', 'rrze-calendar')}
										</button>
									</li>
								);
							})}
						</ul>
					</div>
					<hr />
					<TextControl
						label={__('Include Events', 'rrze-calendar')}
						type="text"
						value={includeEvents}
						onChange={onChangeIncludeEvents}
						help={__('Only show events having these words in the title.', 'rrze-calendar')}
					/>
					<TextControl
						label={__('Exclude Events', 'rrze-calendar')}
						type="text"
						value={excludeEvents}
						onChange={onChangeExcludeEvents}
						help={__('Hide events having these words in the title.', 'rrze-calendar')}
					/>
				</PanelBody>
			</InspectorControls>
			<ServerSideRender
				block="rrze-calendar/calendar"
				attributes={attributes}
			/>
		</div>
	);
}
