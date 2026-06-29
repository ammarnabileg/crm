/*
 * HaHireAI — Workflow Builder canvas (vanilla JS, no dependencies).
 *
 * An infinite, pan/zoom canvas where users drag trigger/condition/action nodes
 * from a palette and connect them — a no-code automation editor. It reads the
 * node catalog and any existing graph from JSON <script> tags, renders nodes and
 * bezier edges, edits node config from each node's declared schema (no JSON, no
 * code), shows a live plain-language summary, validates, and serialises the graph
 * to a hidden form on Save. Re-runs cleanly after pjax swaps.
 */
(function () {
  'use strict';

  var NODE_W = 220, PORT_Y = 26, MIN_ZOOM = 0.3, MAX_ZOOM = 2;
  var CAT_COLOR = {
    Triggers: '#f59e0b', Actions: '#6366f1', Conditions: '#8b5cf6', AI: '#d946ef',
    Recruitment: '#3b82f6', Workspace: '#0ea5e9', Users: '#14b8a6', Notifications: '#10b981',
    Database: '#64748b', Files: '#0891b2', Time: '#f97316', Logic: '#8b5cf6',
    Variables: '#a855f7', Integrations: '#ec4899', Utilities: '#94a3b8'
  };

  function kind(type) {
    if (type.indexOf('trigger.') === 0) return 'trigger';
    if (type.indexOf('condition.') === 0 || type === 'logic.filter') return 'condition';
    return 'action';
  }
  function uid() { return 'n_' + Math.random().toString(36).slice(2, 9); }
  function el(tag, cls, html) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    if (html != null) e.innerHTML = html;
    return e;
  }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }

  function init() {
    var root = document.getElementById('wf-builder');
    if (!root || root.__wfInit) return;
    root.__wfInit = true;

    var catalog = JSON.parse(document.getElementById('wf-catalog').textContent || '[]');
    var categories = JSON.parse(document.getElementById('wf-categories').textContent || '[]');
    var initial = JSON.parse(document.getElementById('wf-initial').textContent || '{}');
    var byType = {};
    catalog.forEach(function (n) { byType[n.type] = n; });

    var canvas = root.querySelector('#wf-canvas');
    var layer = root.querySelector('#wf-layer');
    var nodesEl = root.querySelector('#wf-nodes');
    var edgesEl = root.querySelector('#wf-edges');
    var emptyEl = root.querySelector('#wf-empty');

    var state = {
      nodes: (initial.nodes || []).map(function (n) {
        return { id: String(n.id || uid()), type: n.type, x: +n.x || 80, y: +n.y || 80, config: n.config || {} };
      }),
      edges: (initial.edges || []).map(function (e) {
        return { id: String(e.id || uid()), from: String(e.from), to: String(e.to), branch: e.branch || '' };
      }),
      pan: { x: 40, y: 40 }, zoom: 1, sel: null
    };

    // ---- Palette --------------------------------------------------------------
    var paletteEl = root.querySelector('#wf-palette');
    function buildPalette(filter) {
      paletteEl.innerHTML = '';
      filter = (filter || '').toLowerCase();
      categories.forEach(function (cat) {
        var items = catalog.filter(function (n) {
          return n.category === cat && (!filter || (n.label + ' ' + n.description).toLowerCase().indexOf(filter) >= 0);
        });
        if (!items.length) return;
        var group = el('div', 'mb-3');
        group.appendChild(el('div', 'px-2 pb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400', esc(cat)));
        items.forEach(function (n) {
          var color = CAT_COLOR[n.category] || '#94a3b8';
          var item = el('div', 'group mb-1 flex cursor-grab items-center gap-2 rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-sm hover:border-indigo-300 hover:bg-indigo-50');
          item.setAttribute('draggable', 'true');
          item.innerHTML = '<span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background:' + color + '"></span>'
            + '<span class="truncate text-slate-700">' + esc(n.label) + '</span>';
          item.title = n.description;
          item.addEventListener('dragstart', function (ev) { ev.dataTransfer.setData('text/plain', n.type); });
          group.appendChild(item);
        });
        paletteEl.appendChild(group);
      });
    }
    buildPalette('');
    root.querySelector('#wf-search').addEventListener('input', function (e) { buildPalette(e.target.value); });

    // ---- Coordinate helpers ---------------------------------------------------
    function toLayer(clientX, clientY) {
      var r = canvas.getBoundingClientRect();
      return { x: (clientX - r.left - state.pan.x) / state.zoom, y: (clientY - r.top - state.pan.y) / state.zoom };
    }
    function applyTransform() {
      layer.style.transform = 'translate(' + state.pan.x + 'px,' + state.pan.y + 'px) scale(' + state.zoom + ')';
      root.querySelector('#wf-zoom').textContent = Math.round(state.zoom * 100) + '%';
    }

    // ---- Node / edge model ----------------------------------------------------
    function addNode(type, x, y) {
      var def = byType[type]; if (!def) return;
      var node = { id: uid(), type: type, x: Math.round(x), y: Math.round(y), config: {} };
      (def.config || []).forEach(function (f) { node.config[f.key] = ''; });
      var prev = state.sel;
      state.nodes.push(node);
      // Auto-connect from the selected node so a chain builds with a single drop.
      if (prev && prev !== node.id && kind(type) !== 'trigger' && nodeById(prev)
        && !state.edges.some(function (e) { return e.from === prev && e.to === node.id; })) {
        state.edges.push({ id: uid(), from: prev, to: node.id, branch: '' });
      }
      state.sel = node.id;
      render();
    }
    function removeNode(id) {
      state.nodes = state.nodes.filter(function (n) { return n.id !== id; });
      state.edges = state.edges.filter(function (e) { return e.from !== id && e.to !== id; });
      if (state.sel === id) state.sel = null;
      render();
    }
    function addEdge(from, to, branch) {
      if (from === to) return;
      if (state.edges.some(function (e) { return e.from === from && e.to === to; })) return;
      state.edges.push({ id: uid(), from: from, to: to, branch: branch || '' });
      render();
    }
    function removeEdge(id) {
      state.edges = state.edges.filter(function (e) { return e.id !== id; });
      render();
    }
    edgesEl.addEventListener('click', function (e) {
      var hit = e.target.closest && e.target.closest('[data-edge]');
      if (hit) removeEdge(hit.getAttribute('data-edge'));
    });
    function nodeById(id) { return state.nodes.find(function (n) { return n.id === id; }); }

    // ---- Rendering ------------------------------------------------------------
    function validate() {
      var issues = {}, triggers = 0, actions = 0;
      state.nodes.forEach(function (n) {
        var k = kind(n.type);
        if (k === 'trigger') triggers++;
        if (k === 'action') actions++;
        var def = byType[n.type] || {};
        (def.config || []).forEach(function (f) {
          if (!String(n.config[f.key] || '').trim()) issues[n.id] = 'Fill in “' + f.label + '”';
        });
        if (k !== 'trigger' && !state.edges.some(function (e) { return e.to === n.id; })) {
          issues[n.id] = issues[n.id] || 'Not connected';
        }
      });
      var ok = triggers === 1 && actions >= 1 && Object.keys(issues).length === 0;
      return { ok: ok, issues: issues, triggers: triggers, actions: actions };
    }

    function render() {
      emptyEl.style.display = state.nodes.length ? 'none' : 'flex';
      nodesEl.innerHTML = '';
      var v = validate();

      state.nodes.forEach(function (n) {
        var def = byType[n.type] || { label: n.type, category: 'Utilities', config: [] };
        var color = CAT_COLOR[def.category] || '#94a3b8';
        var k = kind(n.type);
        var bad = v.issues[n.id];
        var card = el('div', 'wf-node absolute select-none rounded-xl border bg-white shadow-sm'
          + (state.sel === n.id ? ' ring-2 ring-indigo-500' : '')
          + (bad ? ' border-rose-300' : ' border-slate-200'));
        card.style.left = n.x + 'px'; card.style.top = n.y + 'px'; card.style.width = NODE_W + 'px';
        card.dataset.id = n.id;

        var summary = configSummary(n, def);
        card.innerHTML =
          '<div class="wf-head flex cursor-move items-center gap-2 rounded-t-xl px-3 py-2" style="background:' + color + '14">'
          + '<span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background:' + color + '"></span>'
          + '<span class="truncate text-sm font-semibold text-slate-800">' + esc(def.label) + '</span>'
          + '<span class="ms-auto text-[10px] font-medium uppercase tracking-wide text-slate-400">' + esc(k) + '</span>'
          + '</div>'
          + '<div class="px-3 py-2 text-xs text-slate-500">' + (summary || '<span class="text-slate-300">No settings</span>') + '</div>';

        // Ports: input (left) unless trigger; output (right) unless pure end.
        if (k !== 'trigger') {
          var pin = el('div', 'wf-port wf-in absolute -left-2.5 h-4 w-4 rounded-full border-2 border-white bg-slate-400 transition hover:scale-125 hover:bg-indigo-500');
          pin.style.top = (PORT_Y - 8) + 'px'; pin.dataset.in = n.id; pin.title = 'Input — drop a connection here'; card.appendChild(pin);
        }
        var pout = el('div', 'wf-port wf-out absolute -right-2.5 h-4 w-4 cursor-crosshair rounded-full border-2 border-white transition hover:scale-125 hover:ring-2 hover:ring-indigo-300');
        pout.style.top = (PORT_Y - 8) + 'px'; pout.style.background = color; pout.dataset.out = n.id; pout.title = 'Drag from here to connect';
        card.appendChild(pout);

        nodesEl.appendChild(card);
      });

      renderEdges();
      renderMinimap(v);
      applyTransform();
      renderInspector();
      renderStatus(v);
    }

    function portPos(id, side) {
      var n = nodeById(id); if (!n) return { x: 0, y: 0 };
      return { x: n.x + (side === 'out' ? NODE_W : 0), y: n.y + PORT_Y };
    }
    function path(a, b) {
      var dx = Math.max(40, Math.abs(b.x - a.x) / 2);
      return 'M ' + a.x + ' ' + a.y + ' C ' + (a.x + dx) + ' ' + a.y + ', ' + (b.x - dx) + ' ' + b.y + ', ' + b.x + ' ' + b.y;
    }
    function renderEdges(temp) {
      var parts = '<defs><marker id="wf-arrow" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse"><path d="M0,0 L10,5 L0,10 z" fill="#94a3b8"/></marker></defs>';
      state.edges.forEach(function (e) {
        var a = portPos(e.from, 'out'), b = portPos(e.to, 'in'); var d = path(a, b);
        parts += '<path d="' + d + '" fill="none" stroke="#94a3b8" stroke-width="2" marker-end="url(#wf-arrow)"/>'
          + '<path d="' + d + '" fill="none" stroke="transparent" stroke-width="16" style="pointer-events:stroke;cursor:pointer" data-edge="' + e.id + '"><title>Click to remove this connection</title></path>'
          + (e.branch ? '<text x="' + ((a.x + b.x) / 2) + '" y="' + ((a.y + b.y) / 2 - 6) + '" fill="#64748b" font-size="11" text-anchor="middle" style="pointer-events:none">' + esc(e.branch) + '</text>' : '');
      });
      if (temp) parts += '<path d="' + path(temp.a, temp.b) + '" fill="none" stroke="#6366f1" stroke-width="2" stroke-dasharray="6 4" marker-end="url(#wf-arrow)"/>';
      edgesEl.innerHTML = parts;
    }

    function renderMinimap(v) {
      var mm = root.querySelector('#wf-minimap');
      if (!state.nodes.length) { mm.innerHTML = ''; return; }
      var minX = Math.min.apply(null, state.nodes.map(function (n) { return n.x; })) - 40;
      var minY = Math.min.apply(null, state.nodes.map(function (n) { return n.y; })) - 40;
      var maxX = Math.max.apply(null, state.nodes.map(function (n) { return n.x + NODE_W; })) + 40;
      var maxY = Math.max.apply(null, state.nodes.map(function (n) { return n.y + 80; })) + 40;
      mm.setAttribute('viewBox', minX + ' ' + minY + ' ' + (maxX - minX) + ' ' + (maxY - minY));
      var s = '';
      state.edges.forEach(function (e) { var a = portPos(e.from, 'out'), b = portPos(e.to, 'in'); s += '<line x1="' + a.x + '" y1="' + a.y + '" x2="' + b.x + '" y2="' + b.y + '" stroke="#cbd5e1" stroke-width="2"/>'; });
      state.nodes.forEach(function (n) { var c = CAT_COLOR[(byType[n.type] || {}).category] || '#94a3b8'; s += '<rect x="' + n.x + '" y="' + n.y + '" width="' + NODE_W + '" height="56" rx="8" fill="' + c + '"/>'; });
      mm.innerHTML = s;
    }

    function renderStatus(v) {
      var st = root.querySelector('#wf-status');
      if (!state.nodes.length) { st.textContent = ''; st.className = 'rounded-full px-2.5 py-0.5 text-xs font-medium'; }
      else if (v.ok) { st.textContent = '✓ Ready'; st.className = 'rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700'; }
      else { st.textContent = '• Needs attention'; st.className = 'rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700'; }
      root.querySelector('#wf-summary').textContent = summarize();
    }

    function configSummary(n, def) {
      var parts = [];
      (def.config || []).forEach(function (f) {
        var val = n.config[f.key];
        if (val) parts.push(esc(f.label) + ': ' + esc(val));
      });
      return parts.join(' · ');
    }

    // ---- Inspector ------------------------------------------------------------
    function availableVars() {
      var t = state.nodes.find(function (n) { return kind(n.type) === 'trigger'; });
      var outs = t && byType[t.type] ? (byType[t.type].outputs || []) : [];
      return outs.map(function (o) { return '{{' + o + '}}'; });
    }
    function renderInspector() {
      var box = root.querySelector('#wf-inspector');
      var n = state.sel ? nodeById(state.sel) : null;
      if (!n) {
        box.innerHTML = '<div class="space-y-3 text-sm text-slate-500">'
          + '<p class="font-medium text-slate-700">Build your automation</p>'
          + '<ol class="list-decimal space-y-1.5 ps-4 text-slate-500">'
          + '<li>Drag a <span class="font-medium text-amber-600">trigger</span> from the left onto the canvas.</li>'
          + '<li>Drop an action — it <span class="font-medium">auto-connects</span> to the selected node.</li>'
          + '<li>Or drag from a node’s right dot to a node’s left dot to connect manually.</li>'
          + '<li>Click an arrow to remove it; select a node and press Delete to remove it.</li>'
          + '</ol>'
          + '<p class="text-xs text-slate-400">Select any node to edit its settings here.</p>'
          + '</div>';
        return;
      }
      var def = byType[n.type] || { label: n.type, description: '', config: [] };
      var vars = availableVars();
      var html = '<div class="mb-3"><div class="text-sm font-semibold text-slate-800">' + esc(def.label) + '</div>'
        + '<div class="text-xs text-slate-500">' + esc(def.description) + '</div></div>';

      (def.config || []).forEach(function (f) {
        var val = n.config[f.key] == null ? '' : n.config[f.key];
        html += '<label class="mb-1 block text-xs font-medium text-slate-600">' + esc(f.label) + '</label>';
        if (f.type === 'select') {
          html += '<select data-k="' + esc(f.key) + '" class="wf-cfg mb-3 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">'
            + '<option value="">Choose…</option>'
            + (f.options || []).map(function (o) { return '<option' + (o === val ? ' selected' : '') + '>' + esc(o) + '</option>'; }).join('')
            + '</select>';
        } else if (f.type === 'variable') {
          var listId = 'dl_' + f.key;
          html += '<input data-k="' + esc(f.key) + '" value="' + esc(val) + '" list="' + listId + '" class="wf-cfg mb-3 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm font-mono" placeholder="{{field}} or a value">'
            + '<datalist id="' + listId + '">' + vars.map(function (v) { return '<option value="' + esc(v) + '">'; }).join('') + '</datalist>';
        } else {
          html += '<input data-k="' + esc(f.key) + '" value="' + esc(val) + '" class="wf-cfg mb-3 w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm" placeholder="' + esc(f.label) + '">';
        }
      });

      if (!(def.config || []).length) html += '<div class="mb-3 text-xs text-slate-400">This node has no settings.</div>';
      html += '<button id="wf-del" class="mt-2 w-full rounded-lg border border-rose-200 px-3 py-1.5 text-sm font-medium text-rose-600 hover:bg-rose-50">Delete node</button>';
      box.innerHTML = html;

      box.querySelectorAll('.wf-cfg').forEach(function (input) {
        input.addEventListener('input', function () { n.config[input.dataset.k] = input.value; renderStatus(validate()); refreshCard(n); });
      });
      box.querySelector('#wf-del').addEventListener('click', function () { removeNode(n.id); });
    }
    function refreshCard(n) {
      var card = nodesEl.querySelector('[data-id="' + n.id + '"]');
      if (!card) return;
      var def = byType[n.type] || { config: [] };
      var body = card.querySelector('div:nth-child(2)');
      if (body) body.innerHTML = configSummary(n, def) || '<span class="text-slate-300">No settings</span>';
    }

    function summarize() {
      if (!state.nodes.length) return 'This workflow is empty. Drag a trigger onto the canvas to begin.';
      var t = state.nodes.find(function (n) { return kind(n.type) === 'trigger'; });
      var trig = t && byType[t.type] ? byType[t.type].label.toLowerCase() : 'a trigger fires';
      var acts = state.nodes.filter(function (n) { return kind(n.type) === 'action'; })
        .map(function (n) { return (byType[n.type] || {}).label ? byType[n.type].label.toLowerCase() : n.type; });
      if (!acts.length) return 'When ' + trig + ', this workflow does nothing yet — connect an action.';
      var list = acts.length === 1 ? acts[0] : acts.slice(0, -1).join(', ') + ' and ' + acts[acts.length - 1];
      return 'When ' + trig + ', ' + list + '.';
    }

    // ---- Canvas interactions --------------------------------------------------
    canvas.addEventListener('dragover', function (e) { e.preventDefault(); });
    canvas.addEventListener('drop', function (e) {
      e.preventDefault();
      var type = e.dataTransfer.getData('text/plain'); if (!type) return;
      var p = toLayer(e.clientX, e.clientY);
      addNode(type, p.x - NODE_W / 2, p.y - 20);
    });

    var drag = null; // { mode, id, startX, startY, origX, origY }
    nodesEl.addEventListener('mousedown', function (e) {
      var port = e.target.closest('.wf-out');
      if (port) { drag = { mode: 'connect', id: port.dataset.out }; e.preventDefault(); return; }
      var inPort = e.target.closest('.wf-in');
      if (inPort) return;
      var card = e.target.closest('.wf-node'); if (!card) return;
      state.sel = card.dataset.id;
      if (e.target.closest('.wf-head')) {
        var n = nodeById(card.dataset.id);
        drag = { mode: 'move', id: n.id, sx: e.clientX, sy: e.clientY, ox: n.x, oy: n.y };
      }
      render();
    });

    canvas.addEventListener('mousedown', function (e) {
      if (e.target.closest('.wf-node') || (e.target.closest && e.target.closest('[data-edge]'))) return;
      if (e.button !== 0) return;
      state.sel = null;
      drag = { mode: 'pan', sx: e.clientX, sy: e.clientY, ox: state.pan.x, oy: state.pan.y };
      render();
    });

    window.addEventListener('mousemove', function (e) {
      if (!drag) return;
      if (drag.mode === 'move') {
        var n = nodeById(drag.id); if (!n) return;
        n.x = Math.round(drag.ox + (e.clientX - drag.sx) / state.zoom);
        n.y = Math.round(drag.oy + (e.clientY - drag.sy) / state.zoom);
        moveCard(n); renderEdges(); renderMinimap();
      } else if (drag.mode === 'pan') {
        state.pan.x = drag.ox + (e.clientX - drag.sx);
        state.pan.y = drag.oy + (e.clientY - drag.sy);
        applyTransform();
      } else if (drag.mode === 'connect') {
        var a = portPos(drag.id, 'out'); var b = toLayer(e.clientX, e.clientY);
        renderEdges({ a: a, b: b });
      }
    });
    window.addEventListener('mouseup', function (e) {
      if (drag && drag.mode === 'connect') {
        var target = document.elementFromPoint(e.clientX, e.clientY);
        var inPort = target && target.closest && target.closest('.wf-in');
        if (inPort) addEdge(drag.id, inPort.dataset.in, '');
        else renderEdges();
      }
      drag = null;
    });
    function moveCard(n) {
      var card = nodesEl.querySelector('[data-id="' + n.id + '"]');
      if (card) { card.style.left = n.x + 'px'; card.style.top = n.y + 'px'; }
    }

    canvas.addEventListener('wheel', function (e) {
      e.preventDefault();
      var before = toLayer(e.clientX, e.clientY);
      var z = state.zoom * (e.deltaY < 0 ? 1.1 : 0.9);
      state.zoom = Math.min(MAX_ZOOM, Math.max(MIN_ZOOM, z));
      var after = toLayer(e.clientX, e.clientY);
      state.pan.x += (after.x - before.x) * state.zoom;
      state.pan.y += (after.y - before.y) * state.zoom;
      applyTransform();
    }, { passive: false });

    root.querySelectorAll('[data-zoom]').forEach(function (b) {
      b.addEventListener('click', function () {
        var m = b.dataset.zoom;
        if (m === 'in') state.zoom = Math.min(MAX_ZOOM, state.zoom * 1.2);
        else if (m === 'out') state.zoom = Math.max(MIN_ZOOM, state.zoom / 1.2);
        else { state.zoom = 1; state.pan = { x: 40, y: 40 }; }
        applyTransform();
      });
    });

    // Delete selected node with the keyboard (when not typing in a field).
    canvas.setAttribute('tabindex', '0');
    root.addEventListener('keydown', function (e) {
      if ((e.key === 'Delete' || e.key === 'Backspace') && state.sel
        && ['INPUT', 'SELECT', 'TEXTAREA'].indexOf(document.activeElement.tagName) < 0) {
        e.preventDefault(); removeNode(state.sel);
      }
    });

    // ---- Save -----------------------------------------------------------------
    root.querySelector('#wf-save').addEventListener('click', function () {
      var graph = {
        nodes: state.nodes.map(function (n) { return { id: n.id, type: n.type, x: n.x, y: n.y, config: n.config }; }),
        edges: state.edges.map(function (e) { return { from: e.from, to: e.to, branch: e.branch }; })
      };
      root.querySelector('#wf-form-name').value = root.querySelector('#wf-name').value || 'Untitled workflow';
      root.querySelector('#wf-form-enabled').value = root.querySelector('#wf-enabled').checked ? '1' : '0';
      root.querySelector('#wf-form-graph').value = JSON.stringify(graph);
      root.querySelector('#wf-form').submit();
    });

    render();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
