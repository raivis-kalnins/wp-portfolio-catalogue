/* global wp */
/** Native dynamic catalogue block. Source is intentionally distributed unminified. */
(function (blocks, element, components, blockEditor, data, i18n, htmlEntities, serverSideRender) {
	'use strict';
	var el = element.createElement;
	var __ = i18n.__;
	var ServerSideRender = typeof serverSideRender === 'function' ? serverSideRender : serverSideRender.default;
	var attributes = {
		columns: { type: 'number', default: 3 }, perPage: { type: 'number', default: 6 },
		category: { type: 'string', default: '' }, tag: { type: 'string', default: '' },
		projectIds: { type: 'array', items: { type: 'number' }, default: [] },
		filters: { type: 'boolean', default: true }, search: { type: 'boolean', default: true },
		orderby: { type: 'string', default: 'date' }, order: { type: 'string', default: 'DESC' },
		excerptWords: { type: 'number', default: 22 }
	};

	function ProjectPicker(props) {
		var searchState = element.useState('');
		var selected = props.selected || [];
		var search = searchState[0];
		var records = data.useSelect(function (select) {
			return select('core').getEntityRecords('postType', 'project', {
				per_page: 50, status: 'publish', search: search, orderby: 'title', order: 'asc'
			});
		}, [search]);
		var chosen = data.useSelect(function (select) {
			return selected.length ? select('core').getEntityRecords('postType', 'project', {
				per_page: Math.min(selected.length, 100), include: selected, status: 'publish'
			}) : [];
		}, [selected.join(',')]);
		var labels = {};
		(chosen || []).concat(records || []).forEach(function (record) {
			labels[record.id] = htmlEntities.decodeEntities((record.title && record.title.rendered || '').replace(/<[^>]*>/g, ''));
		});
		return el(element.Fragment, {},
			el(components.SearchControl, {
				label: __('Find projects', 'wp-portfolio-catalogue'), value: search,
				onChange: searchState[1]
			}),
			el(components.SelectControl, {
				label: __('Add a project', 'wp-portfolio-catalogue'), value: '',
				options: [{ label: __('Choose a project…', 'wp-portfolio-catalogue'), value: '' }].concat(
					(records || []).filter(function (r) { return selected.indexOf(r.id) === -1; }).map(function (r) {
						return { label: labels[r.id] || __('Untitled project', 'wp-portfolio-catalogue'), value: String(r.id) };
					})
				),
				onChange: function (value) {
					var id = parseInt(value, 10);
					if (id && selected.indexOf(id) === -1 && selected.length < 100) { props.onChange(selected.concat([id])); }
				}
			}),
			el('div', { className: 'wppc-editor-selected' }, selected.map(function (id) {
				return el(components.Button, {
					key: id, variant: 'secondary', size: 'small', icon: 'no-alt',
					label: __('Remove project', 'wp-portfolio-catalogue') + ' ' + (labels[id] || id),
					onClick: function () { props.onChange(selected.filter(function (existing) { return existing !== id; })); }
				}, labels[id] || '#' + id);
			})),
			selected.length > 0 ? el(components.Button, { variant: 'link', onClick: function () { props.onChange([]); } }, props.legacy ? __('Clear selection', 'wp-portfolio-catalogue') : __('Show all projects', 'wp-portfolio-catalogue')) : null,
			el('p', { className: 'wppc-editor-help' }, props.legacy ? __('Choose the projects to show. An empty selection does not display a catalogue.', 'wp-portfolio-catalogue') : __('Leave the selection empty to show all published projects. Search to find more projects; selected projects are combined with category and tag restrictions.', 'wp-portfolio-catalogue'))
		);
	}

	function edit(props) {
		var a = props.attributes;
		var set = props.setAttributes;
		function update(key) { return function (value) { var patch = {}; patch[key] = value; set(patch); }; }
		return el('div', blockEditor.useBlockProps(),
			el(blockEditor.InspectorControls, {},
				el(components.PanelBody, { title: __('Layout', 'wp-portfolio-catalogue'), initialOpen: true },
					el(components.RangeControl, { label: __('Columns', 'wp-portfolio-catalogue'), value: a.columns, min: 1, max: 4, onChange: update('columns') }),
					el(components.RangeControl, { label: __('Projects per page', 'wp-portfolio-catalogue'), value: a.perPage, min: 1, max: 48, onChange: update('perPage') }),
					el(components.RangeControl, { label: __('Excerpt length (words)', 'wp-portfolio-catalogue'), value: a.excerptWords, min: 0, max: 80, onChange: update('excerptWords') }),
					el(components.ToggleControl, { label: __('Show category and tag filters', 'wp-portfolio-catalogue'), checked: a.filters, onChange: update('filters') }),
					el(components.ToggleControl, { label: __('Show search', 'wp-portfolio-catalogue'), checked: a.search, onChange: update('search') })
				),
				el(components.PanelBody, { title: __('Project selection', 'wp-portfolio-catalogue'), initialOpen: false },
					el(ProjectPicker, { selected: a.projectIds, onChange: update('projectIds') }),
					el(components.TextControl, { label: __('Category slugs', 'wp-portfolio-catalogue'), help: __('Optional comma-separated slugs, for example web-design,interiors.', 'wp-portfolio-catalogue'), value: a.category, onChange: update('category') }),
					el(components.TextControl, { label: __('Tag slugs', 'wp-portfolio-catalogue'), help: __('Optional comma-separated project tag slugs.', 'wp-portfolio-catalogue'), value: a.tag, onChange: update('tag') })
				),
				el(components.PanelBody, { title: __('Sort order', 'wp-portfolio-catalogue'), initialOpen: false },
					el(components.SelectControl, {
						label: __('Order by', 'wp-portfolio-catalogue'), value: a.orderby, onChange: update('orderby'), options: [
							{ label: __('Publish date', 'wp-portfolio-catalogue'), value: 'date' },
							{ label: __('Title', 'wp-portfolio-catalogue'), value: 'title' },
							{ label: __('Menu order', 'wp-portfolio-catalogue'), value: 'menu_order' }
						]
					}),
					el(components.SelectControl, { label: __('Direction', 'wp-portfolio-catalogue'), value: a.order, onChange: update('order'), options: [
						{ label: __('Descending', 'wp-portfolio-catalogue'), value: 'DESC' }, { label: __('Ascending', 'wp-portfolio-catalogue'), value: 'ASC' }
					] })
				)
			),
			el(ServerSideRender, { block: 'wp-portfolio-catalogue/catalogue', attributes: a, httpMethod: 'POST' })
		);
	}

	blocks.registerBlockType('wp-portfolio-catalogue/catalogue', {
		apiVersion: 3, title: __('Portfolio Catalogue', 'wp-portfolio-catalogue'),
		description: __('Display a filterable portfolio on any page.', 'wp-portfolio-catalogue'),
		category: 'widgets', icon: 'portfolio', attributes: attributes,
		supports: { html: false, align: ['wide', 'full'] }, edit: edit, save: function () { return null; }
	});

	blocks.registerBlockType('post-project-gallery/selected-projects', {
		apiVersion: 3, title: __('Selected Projects (legacy)', 'wp-portfolio-catalogue'), category: 'widgets', icon: 'portfolio',
		attributes: { projectIds: attributes.projectIds, columns: attributes.columns }, supports: { inserter: false, html: false },
		edit: function (props) {
			return el('div', blockEditor.useBlockProps(),
				el(blockEditor.InspectorControls, {}, el(components.PanelBody, { title: __('Project settings', 'wp-portfolio-catalogue') },
					el(ProjectPicker, { legacy: true, selected: props.attributes.projectIds, onChange: function (ids) { props.setAttributes({ projectIds: ids }); } }),
					el(components.RangeControl, { label: __('Columns', 'wp-portfolio-catalogue'), min: 1, max: 4, value: props.attributes.columns, onChange: function (columns) { props.setAttributes({ columns: columns }); } })
				)),
				props.attributes.projectIds.length ? el(ServerSideRender, { block: 'post-project-gallery/selected-projects', attributes: props.attributes }) : el('p', {}, __('Select projects from the block settings.', 'wp-portfolio-catalogue'))
			);
		},
		save: function () { return null; }
	});
})(wp.blocks, wp.element, wp.components, wp.blockEditor, wp.data, wp.i18n, wp.htmlEntities, wp.serverSideRender);
