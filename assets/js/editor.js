(function () {
  'use strict';

  var cfg = window.CGMCoreEditor || { rest: '', nonce: '' };
  var base = (cfg.rest || '/wp-json/cgm-core/v1/').replace(/\/$/, '');

  var wp = window.wp || {};
  var el = wp.element.createElement;
  var useState = wp.element.useState;
  var useEffect = wp.element.useEffect;
  var Fragment = wp.element.Fragment;
  var apiFetch = wp.apiFetch;
  var registerPlugin = (wp.plugins && wp.plugins.registerPlugin) || function () {};
  var PluginPostStatusInfo = (wp.editPost && wp.editPost.PluginPostStatusInfo) || (wp.editor && wp.editor.PluginPostStatusInfo);
  var TextControl = wp.components.TextControl;
  var Button = wp.components.Button;
  var SelectControl = wp.components.SelectControl;
  var Spinner = wp.components.Spinner;
  var useSelect = wp.data && wp.data.useSelect;

  if (!PluginPostStatusInfo || !apiFetch) { return; }

  function headers() {
    return cfg.nonce ? { 'X-WP-Nonce': cfg.nonce } : {};
  }

  function fetchControls(postId) {
    return apiFetch({ path: base + '/editor/' + postId + '/controls', headers: headers() });
  }

  function searchControl(postId, controlId, search) {
    return apiFetch({ path: base + '/editor/' + postId + '/control/' + controlId + '?search=' + encodeURIComponent(search), headers: headers() });
  }

  function saveControl(postId, controlId, items) {
    return apiFetch({
      path: base + '/editor/' + postId + '/control/' + controlId,
      method: 'POST',
      headers: Object.assign({ 'Content-Type': 'application/json' }, headers()),
      body: JSON.stringify({ items: items }),
    });
  }

  function ControlRow(props) {
    var control = props.control;
    var postId = props.postId;
    var items = control.items || [];
    var schema = control.schema || {};
    var roles = schema.roles || [];
    var metadata = schema.metadata_schema || {};
    var canOrder = !!schema.ordered;
    var canPrimary = !!schema.primary;

    var searchState = useState('');
    var search = searchState[0];
    var setSearch = searchState[1];
    var resultsState = useState([]);
    var results = resultsState[0];
    var setResults = resultsState[1];
    var busyState = useState(false);
    var busy = busyState[0];
    var setBusy = busyState[1];

    useEffect(function () {
      if (!search) { setResults([]); return; }
      var t = setTimeout(function () {
        searchControl(postId, control.id, search).then(function (r) { setResults(r || []); });
      }, 250);
      return function () { clearTimeout(t); };
    }, [search]);

    function commit(next) {
      setBusy(true);
      saveControl(postId, control.id, next).then(function (r) {
        setBusy(false);
        if (r && r.success && r.items) { props.onChange(r.items); }
      }).catch(function () { setBusy(false); });
    }

    function addItem(item) {
      var next = items.slice();
      if (next.some(function (i) { return Number(i.id) === Number(item.id); })) { setSearch(''); setResults([]); return; }
      next.push({ id: Number(item.id), label: item.label || '', role: '', order: next.length, primary: false, meta: {} });
      commit(next);
      setSearch('');
      setResults([]);
    }

    function removeItem(id) {
      commit(items.filter(function (i) { return Number(i.id) !== Number(id); }));
    }

    function patchItem(id, patch) {
      commit(items.map(function (i) { return Number(i.id) === Number(id) ? Object.assign({}, i, patch) : i; }));
    }

    function move(id, delta) {
      var next = items.slice();
      var idx = next.findIndex(function (i) { return Number(i.id) === Number(id); });
      var to = idx + delta;
      if (idx < 0 || to < 0 || to >= next.length) { return; }
      var tmp = next[idx]; next[idx] = next[to]; next[to] = tmp;
      commit(next.map(function (i, n) { return Object.assign({}, i, { order: n }); }));
    }

    function makePrimary(id) {
      commit(items.map(function (i) { return Object.assign({}, i, { primary: Number(i.id) === Number(id) }); }));
    }

    return el('div', { className: 'cgm-core-control' },
      el('div', { className: 'cgm-core-control-head' },
        el('span', { className: 'cgm-core-control-label' }, control.label),
        busy ? el(Spinner) : null
      ),
      el('div', { className: 'cgm-core-selected' },
        items.map(function (item, idx) {
          return el('div', { className: 'cgm-core-selected-item', key: item.id },
            el('div', { className: 'cgm-core-chip-row' },
              el('span', { className: 'cgm-core-chip-label' }, item.label || ('#' + item.id)),
              roles.length ? el(SelectControl, { value: item.role || '', options: [{ value: '', label: '—' }].concat(roles.map(function (r) { return { value: r, label: r }; })), onChange: function (v) { patchItem(item.id, { role: v }); } }) : null,
              canPrimary ? el(Button, { isSmall: true, isPrimary: item.primary, onClick: function () { makePrimary(item.id); } }, item.primary ? 'Primary' : 'Make primary') : null,
              canOrder ? el(Button, { isSmall: true, disabled: idx === 0, onClick: function () { move(item.id, -1); } }, 'Move up') : null,
              canOrder ? el(Button, { isSmall: true, disabled: idx === items.length - 1, onClick: function () { move(item.id, 1); } }, 'Move down') : null,
              el(Button, { isSmall: true, isDestructive: true, onClick: function () { removeItem(item.id); } }, '✕')
            ),
            Object.keys(metadata).length ? el('div', { className: 'cgm-core-item-settings' },
              Object.keys(metadata).map(function (key) {
                var def = metadata[key] || {};
                return el(TextControl, { key: key, label: def.label || key, value: (item.meta && item.meta[key]) || '', onChange: function (v) { var m = Object.assign({}, item.meta, {}); m[key] = v; patchItem(item.id, { meta: m }); } });
              })
            ) : null
          );
        }),
        items.length === 0 ? el('small', { className: 'cgm-core-empty' }, 'Nothing selected') : null
      ),
      el(TextControl, { value: search, onChange: setSearch, placeholder: 'Search to add…' }),
      results.length ? el('div', { className: 'cgm-core-search-results' },
        results.map(function (r) {
          return el(Button, { key: r.id, onClick: function () { addItem(r); } },
            el(Fragment, null, r.label || ('#' + r.id), el('small', null, '#' + r.id))
          );
        })
      ) : null
    );
  }

  function CgmSummary() {
    var postId = useSelect(function (select) {
      return select('core/editor').getCurrentPostId();
    }, []);
    var state = useState(null);
    var controls = state[0];
    var setControls = state[1];

    useEffect(function () {
      if (!postId) { return; }
      fetchControls(postId).then(function (r) { setControls(r || []); });
    }, [postId]);

    if (!controls) { return el(PluginPostStatusInfo, null, el(Spinner)); }
    if (!controls.length) { return null; }

    return el(PluginPostStatusInfo, null,
      el('div', { className: 'cgm-core-summary-row' },
        controls.map(function (control) {
          return el(ControlRow, {
            key: control.id,
            control: control,
            postId: postId,
            onChange: function (items) {
              setControls(controls.map(function (c) { return c.id === control.id ? Object.assign({}, c, { items: items }) : c; }));
            },
          });
        })
      )
    );
  }

  registerPlugin('cgm-core', { render: CgmSummary });
})();
