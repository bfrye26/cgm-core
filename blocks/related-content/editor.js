(function () {
  'use strict';
  var el = wp.element.createElement;
  var registerBlockType = wp.blocks.registerBlockType;
  var InspectorControls = wp.blockEditor.InspectorControls;
  var PanelBody = wp.components.PanelBody;
  var TextControl = wp.components.TextControl;
  var RangeControl = wp.components.RangeControl;

  registerBlockType('cgm-core/related-content', {
    edit: function (props) {
      var attributes = props.attributes;
      var setAttributes = props.setAttributes;
      return el(
        wp.element.Fragment,
        null,
        el(
          InspectorControls,
          null,
          el(
            PanelBody,
            { title: 'Relationship settings', initialOpen: true },
            el(TextControl, { label: 'Relationship', value: attributes.relationship, onChange: function (v) { setAttributes({ relationship: v }); } }),
            el(TextControl, { label: 'Heading', value: attributes.heading, onChange: function (v) { setAttributes({ heading: v }); } }),
            el(RangeControl, { label: 'Limit', value: attributes.limit, min: 1, max: 50, onChange: function (v) { setAttributes({ limit: v }); } })
          )
        ),
        el('p', { className: 'components-placeholder' }, 'Related content for relationship "' + attributes.relationship + '" renders on the front end.')
      );
    },
    save: function () { return null; },
  });
})();
