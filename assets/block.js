( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var be = wp.blockEditor;
	var c = wp.components;
	var ServerSideRender = wp.serverSideRender;

	function select( label, value, options, onChange ) {
		return el( c.SelectControl, {
			label: label,
			value: value,
			options: options,
			onChange: onChange,
			__next40pxDefaultSize: true,
			__nextHasNoMarginBottom: true,
		} );
	}

	function toggle( label, checked, onChange ) {
		return el( c.ToggleControl, {
			label: label,
			checked: checked,
			onChange: onChange,
			__nextHasNoMarginBottom: true,
		} );
	}

	wp.blocks.registerBlockType( 'rating-glance/ratings', {
		apiVersion: 3,
		title: __( 'Rating Glance', 'rating-glance' ),
		description: __( 'Google and Tripadvisor rating, number of reviews and link.', 'rating-glance' ),
		category: 'widgets',
		icon: 'star-filled',
		keywords: [ 'reviews', 'rating', 'google', 'tripadvisor', 'stars' ],

		edit: function ( props ) {
			var a = props.attributes;
			var set = props.setAttributes;
			var blockProps = be.useBlockProps();

			return el(
				wp.element.Fragment,
				null,
				el(
					be.InspectorControls,
					null,
					el(
						c.PanelBody,
						{ title: __( 'Sources', 'rating-glance' ) },
						el( 'div', { style: { display: 'grid', gap: '16px' } },
							toggle( 'Google', a.showGoogle, function ( v ) { set( { showGoogle: v } ); } ),
							toggle( 'Tripadvisor', a.showTripadvisor, function ( v ) { set( { showTripadvisor: v } ); } ),
							toggle( __( 'Show source name', 'rating-glance' ), a.showLabel, function ( v ) { set( { showLabel: v } ); } )
						)
					),
					el(
						c.PanelBody,
						{ title: __( 'Display', 'rating-glance' ) },
						el( 'div', { style: { display: 'grid', gap: '16px' } },
							select( __( 'Style', 'rating-glance' ), a.display, [
								{ value: 'stars', label: __( 'Five stars + score', 'rating-glance' ) },
								{ value: 'compact', label: __( 'One star + score', 'rating-glance' ) },
								{ value: 'text', label: __( 'Score only (4.6/5)', 'rating-glance' ) },
							], function ( v ) { set( { display: v } ); } ),
							select( __( 'Logo', 'rating-glance' ), a.icon, [
								{ value: 'mono', label: __( 'Logo in text color', 'rating-glance' ) },
								{ value: 'color', label: __( 'Logo in brand colors', 'rating-glance' ) },
								{ value: 'none', label: __( 'No logo', 'rating-glance' ) },
							], function ( v ) { set( { icon: v } ); } ),
							select( __( 'Review count', 'rating-glance' ), a.countStyle, [
								{ value: 'text', label: __( '1,234 reviews', 'rating-glance' ) },
								{ value: 'number', label: __( '(1,234)', 'rating-glance' ) },
								{ value: 'none', label: __( 'Hide review count', 'rating-glance' ) },
							], function ( v ) { set( { countStyle: v } ); } ),
							select( __( 'Layout', 'rating-glance' ), a.layout, [
								{ value: 'inline', label: __( 'Side by side', 'rating-glance' ) },
								{ value: 'stacked', label: __( 'Stacked', 'rating-glance' ) },
							], function ( v ) { set( { layout: v } ); } ),
							select( __( 'Alignment', 'rating-glance' ), a.align, [
								{ value: 'start', label: __( 'Left', 'rating-glance' ) },
								{ value: 'center', label: __( 'Center', 'rating-glance' ) },
								{ value: 'end', label: __( 'Right', 'rating-glance' ) },
							], function ( v ) { set( { align: v } ); } )
						)
					)
				),
				el(
					'div',
					blockProps,
					el( c.Disabled, null,
						el( ServerSideRender, {
							block: 'rating-glance/ratings',
							attributes: a,
							skipBlockSupportAttributes: true,
						} )
					)
				)
			);
		},

		save: function () {
			return null;
		},
	} );
} )( window.wp );
