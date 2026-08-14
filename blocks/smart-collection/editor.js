(function () {
  'use strict';
  var el = wp.element.createElement;
  var registerBlockType = wp.blocks.registerBlockType;
  var InspectorControls = wp.blockEditor.InspectorControls;
  var PanelBody = wp.components.PanelBody;
  var TextControl = wp.components.TextControl;
  var ToggleControl = wp.components.ToggleControl;
  var RangeControl = wp.components.RangeControl;

  registerBlockType('cgm-core/smart-collection', {
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
            { title: 'Collection settings', initialOpen: true },
            el(TextControl, { label: 'Saved query ID', value: attributes.queryId, onChange: function (v) { setAttributes({ queryId: v }); } }),
            el(ToggleControl, { label: 'Show exposed filters', checked: attributes.filters, onChange: function (v) { setAttributes({ filters: v }); } }),
            el(RangeControl, { label: 'Limit (0 = default)', value: attributes.limit, min: 0, max: 100, onChange: function (v) { setAttributes({ limit: v }); } })
          )
        ),
        el('p', { className: 'components-placeholder' }, 'Smart collection "' + attributes.queryId + '" renders on the front end.')
      );
    },
    save: function () { return null; },
  });
})();
