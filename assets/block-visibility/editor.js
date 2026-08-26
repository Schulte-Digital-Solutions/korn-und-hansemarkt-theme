/**
 * Sichtbarkeits-Optionen für alle Blöcke (Inspector-Panel "Sichtbarkeit").
 *
 * Fügt jedem Block das Attribut `kuhHiddenOn` hinzu und schreibt daraus die
 * Klassen `kuh-hide-mobile|tablet|desktop` in den Block-Wrapper.
 */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.hooks || ! wp.element || ! wp.blockEditor ) {
		return;
	}

	var addFilter = wp.hooks.addFilter;
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var ToggleControl = wp.components.ToggleControl;
	var createHigherOrderComponent = wp.compose.createHigherOrderComponent;

	var ATTRIBUTE = 'kuhHiddenOn';

	var BREAKPOINTS = [
		{ key: 'mobile', label: 'Auf Smartphones ausblenden (< 768 px)', className: 'kuh-hide-mobile' },
		{ key: 'tablet', label: 'Auf Tablets ausblenden (768–1023 px)', className: 'kuh-hide-tablet' },
		{ key: 'desktop', label: 'Auf Desktop ausblenden (≥ 1024 px)', className: 'kuh-hide-desktop' },
	];

	// Blöcke, die keinen eigenen Markup-Wrapper besitzen bzw. serverseitig verwaltet werden.
	var EXCLUDED = [ 'core/legacy-widget', 'core/widget-area', 'core/list-item' ];

	function isSupported( name ) {
		return typeof name === 'string' && EXCLUDED.indexOf( name ) === -1;
	}

	function getValues( attributes ) {
		var value = attributes && attributes[ ATTRIBUTE ];
		return Array.isArray( value ) ? value : [];
	}

	function getClassNames( values ) {
		return BREAKPOINTS.filter( function ( bp ) {
			return values.indexOf( bp.key ) !== -1;
		} ).map( function ( bp ) {
			return bp.className;
		} );
	}

	addFilter( 'blocks.registerBlockType', 'kuh/block-visibility/attribute', function ( settings, name ) {
		if ( ! isSupported( name ) || ! settings.attributes ) {
			return settings;
		}

		settings.attributes[ ATTRIBUTE ] = {
			type: 'array',
			items: { type: 'string' },
			default: [],
		};

		return settings;
	} );

	addFilter(
		'editor.BlockEdit',
		'kuh/block-visibility/controls',
		createHigherOrderComponent( function ( BlockEdit ) {
			return function ( props ) {
				if ( ! isSupported( props.name ) ) {
					return el( BlockEdit, props );
				}

				var values = getValues( props.attributes );

				var toggles = BREAKPOINTS.map( function ( bp ) {
					return el( ToggleControl, {
						key: bp.key,
						__nextHasNoMarginBottom: true,
						label: bp.label,
						checked: values.indexOf( bp.key ) !== -1,
						onChange: function ( checked ) {
							var next = values.filter( function ( item ) {
								return item !== bp.key;
							} );
							if ( checked ) {
								next.push( bp.key );
							}
							var update = {};
							update[ ATTRIBUTE ] = next;
							props.setAttributes( update );
						},
					} );
				} );

				return el(
					Fragment,
					null,
					el( BlockEdit, props ),
					el(
						InspectorControls,
						{ group: 'styles' },
						el(
							PanelBody,
							{ title: 'Sichtbarkeit', initialOpen: false },
							toggles
						)
					)
				);
			};
		}, 'kuhWithVisibilityControls' )
	);

	// Klassen in das gespeicherte Markup schreiben (statische Blöcke).
	addFilter( 'blocks.getSaveContent.extraProps', 'kuh/block-visibility/save', function ( extraProps, blockType, attributes ) {
		if ( ! isSupported( blockType && blockType.name ) ) {
			return extraProps;
		}

		var classNames = getClassNames( getValues( attributes ) );
		if ( ! classNames.length ) {
			return extraProps;
		}

		extraProps.className = [ extraProps.className, classNames.join( ' ' ) ]
			.filter( Boolean )
			.join( ' ' );

		return extraProps;
	} );

	// Im Editor nur markieren, nicht ausblenden.
	addFilter(
		'editor.BlockListBlock',
		'kuh/block-visibility/editor-class',
		createHigherOrderComponent( function ( BlockListBlock ) {
			return function ( props ) {
				var classNames = getClassNames( getValues( props.attributes ) );
				if ( ! classNames.length ) {
					return el( BlockListBlock, props );
				}

				var editorClasses = classNames.map( function ( name ) {
					return name.replace( 'kuh-hide-', 'kuh-editor-hidden-' );
				} );
				editorClasses.push( 'kuh-has-visibility-rules' );

				return el(
					BlockListBlock,
					Object.assign( {}, props, {
						className: [ props.className ].concat( editorClasses ).filter( Boolean ).join( ' ' ),
					} )
				);
			};
		}, 'kuhWithVisibilityEditorClass' )
	);
} )( window.wp );
