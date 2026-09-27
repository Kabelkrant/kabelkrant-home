'use strict';

/*
 * Beheerscherm: tabbladen (bookmarks, icons, vormgeving, instellingen, versie) op één pagina.
 * Alle data komt binnen via <script id="boot"> en wordt via ./?api=… opgeslagen.
 */

const boot = JSON.parse(document.getElementById('boot').textContent);
let tree = boot.tree;
let icons = boot.icons;
let settings = boot.settings;
const defaults = boot.defaults;

const $ = (sel, root = document) => root.querySelector(sel);
const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];

function el(tag, props = {}, children = []) {
    const node = Object.assign(document.createElement(tag), props);
    node.append(...children);
    return node;
}

/* ---------- API & status ---------- */

async function api(action, { json, form } = {}) {
    const options = { method: json || form ? 'POST' : 'GET', headers: { 'X-CSRF-Token': boot.csrf } };
    if (json) {
        options.headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(json);
    } else if (form) {
        options.body = form;
    }
    let data;
    try {
        const res = await fetch('./?api=' + encodeURIComponent(action), options);
        data = await res.json();
    } catch {
        throw new Error('netwerkfout of te groot bestand');
    }
    if (!data.ok) throw new Error(data.error || 'onbekende fout');
    return data;
}

let statusTimer = null;
function setStatus(text, { error = false, autoHide = false } = {}) {
    const status = $('#status');
    status.textContent = text;
    status.classList.toggle('error', error);
    status.classList.remove('hidden');
    clearTimeout(statusTimer);
    if (autoHide) statusTimer = setTimeout(() => status.classList.add('hidden'), 2500);
}

/* ---------- Tabs ---------- */

const TABS = ['bookmarks', 'icons', 'design', 'settings', 'version'];

function showTab(name) {
    if (!TABS.includes(name)) name = TABS[0];
    $$('.tabs [data-tab]').forEach(btn => btn.setAttribute('aria-selected', String(btn.dataset.tab === name)));
    TABS.forEach(tab => { $('#tab-' + tab).hidden = tab !== name; });
    if (name === 'icons') renderIconManager();
    if (name === 'design') renderBackgroundMode();
    if (name === 'version' && !updateChecked) checkUpdate();
}

$$('.tabs [data-tab]').forEach(btn => btn.addEventListener('click', () => {
    history.replaceState(null, '', '#' + btn.dataset.tab);
    showTab(btn.dataset.tab);
}));

/* ---------- Overlays ---------- */

function openOverlay(overlay) { overlay.classList.remove('hidden'); }
function closeOverlay(overlay) { overlay.classList.add('hidden'); }

$$('.overlay').forEach(overlay => {
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay || e.target.closest('[data-close]')) closeOverlay(overlay);
    });
});
document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    const open = $$('.overlay:not(.hidden)').pop();
    if (open) closeOverlay(open);
});

/* =====================================================================
 * Bookmarks
 * ===================================================================== */

const collapsed = new Set();
let draggedId = null;
let formState = null;

function uid() {
    if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
    return 'id-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 8);
}

/*
 * De items op het hoogste niveau staan in kolommen (zoals op de startpagina).
 * `columns` is tijdens het bewerken leidend; `tree` is de platte lijst die wordt
 * opgeslagen, met per item het kolomnummer in `column`.
 */
let columns = splitColumns();

function columnCount() {
    return Number(settings.layout.columns) || 1;
}

/** Verdeelt `tree` over de kolommen; items in een verdwenen kolom komen in de laatste. */
function splitColumns() {
    const count = columnCount();
    const result = Array.from({ length: count }, () => []);
    tree.forEach((node) => {
        const index = Math.min(Math.max(Number(node.column) || 1, 1), count) - 1;
        result[index].push(node);
    });
    return result;
}

/** Zet de kolommen terug in de platte `tree` (met kolomnummers). */
function syncTree() {
    tree = columns.flatMap((items, i) => {
        items.forEach((node) => { node.column = i + 1; });
        return items;
    });
}

function locate(id, nodes = null) {
    if (nodes === null) {
        for (const column of columns) {
            const found = locate(id, column);
            if (found) return found;
        }
        return null;
    }
    for (let i = 0; i < nodes.length; i++) {
        if (nodes[i].id === id) return { array: nodes, index: i, node: nodes[i] };
        if (nodes[i].type === 'category') {
            const found = locate(id, nodes[i].items);
            if (found) return found;
        }
    }
    return null;
}

function isDescendantArray(categoryNode, targetArray) {
    if (categoryNode.items === targetArray) return true;
    return categoryNode.items.some(child => child.type === 'category' && isDescendantArray(child, targetArray));
}

function moveNode(id, targetArray, targetIndex) {
    const found = locate(id);
    if (!found) return;
    const { array: sourceArray, index: sourceIndex, node } = found;

    if (node.type === 'category' && isDescendantArray(node, targetArray)) {
        setStatus('Kan een categorie niet in zichzelf of een eigen subcategorie plaatsen.', { error: true });
        return;
    }

    sourceArray.splice(sourceIndex, 1);
    let insertAt = targetIndex;
    if (sourceArray === targetArray && sourceIndex < targetIndex) insertAt -= 1;
    insertAt = Math.max(0, Math.min(insertAt, targetArray.length));
    targetArray.splice(insertAt, 0, node);

    renderTree();
    saveTree();
}

function deleteNode(node, siblingsArray) {
    const what = node.type === 'category' ? `categorie "${node.label}" (met alle inhoud)` : `bookmark "${node.label}"`;
    if (!confirm(`Weet je zeker dat je ${what} wilt verwijderen?`)) return;
    const idx = siblingsArray.findIndex(n => n.id === node.id);
    if (idx === -1) return;
    siblingsArray.splice(idx, 1);
    renderTree();
    saveTree();
}

function clearDragOverStyles() {
    $$('.drop-before, .drop-after').forEach(n => n.classList.remove('drop-before', 'drop-after'));
    $$('.drop-into').forEach(n => n.classList.remove('drop-into'));
}

document.addEventListener('dragend', () => {
    draggedId = null;
    document.body.classList.remove('dragging');
    clearDragOverStyles();
    $$('li.dragging').forEach(n => n.classList.remove('dragging'));
});

function attachRowDnD(li, row, node, siblingsArray) {
    li.draggable = true;
    const isBefore = (e) => (e.clientY - row.getBoundingClientRect().top) < row.offsetHeight / 2;

    li.addEventListener('dragstart', (e) => {
        // dragstart bubbelt: zonder stopPropagation() overschrijft elke voorouder-categorie draggedId.
        e.stopPropagation();
        draggedId = node.id;
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', node.id);
        e.dataTransfer.setDragImage(row, 10, 10);
        // Lay-out pas ná dragstart wijzigen: verspringt die tijdens dragstart, dan breekt Chrome de drag direct af.
        setTimeout(() => {
            if (draggedId !== node.id) return;
            document.body.classList.add('dragging');
            li.classList.add('dragging');
        }, 0);
    });
    li.addEventListener('dragover', (e) => {
        e.stopPropagation();
        if (!draggedId || draggedId === node.id) return;
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        clearDragOverStyles();
        li.classList.add(isBefore(e) ? 'drop-before' : 'drop-after');
    });
    li.addEventListener('drop', (e) => {
        e.stopPropagation();
        e.preventDefault();
        if (!draggedId || draggedId === node.id) return;
        const idx = siblingsArray.findIndex(n => n.id === node.id);
        moveNode(draggedId, siblingsArray, isBefore(e) ? idx : idx + 1);
    });
}

function attachListDnD(ul, nodes) {
    ul.addEventListener('dragover', (e) => {
        // Niet laten doorbubbelen naar de omvattende categorie-li (die zou een eigen doel afdwingen).
        e.stopPropagation();
        if (!draggedId) return;
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        ul.classList.add('drop-into');
    });
    ul.addEventListener('dragleave', (e) => {
        if (e.target === ul) ul.classList.remove('drop-into');
    });
    ul.addEventListener('drop', (e) => {
        e.stopPropagation();
        e.preventDefault();
        if (!draggedId) return;
        moveNode(draggedId, nodes, nodes.length);
    });
}

const ICONS = {
    edit: '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
    delete: '<path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="m19 6-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/>',
};

function iconButton(kind, label, onClick, cls = '') {
    const btn = el('button', { type: 'button', className: ('icon-button ' + cls).trim(), title: label, onclick: onClick });
    btn.setAttribute('aria-label', label);
    btn.innerHTML = `<svg viewBox="0 0 24 24" aria-hidden="true">${ICONS[kind]}</svg>`;
    return btn;
}

function iconImg(src, className = '') {
    const img = el('img', { className, src: src || 'icons/map.svg', alt: '' });
    img.onerror = () => { img.style.visibility = 'hidden'; };
    return img;
}

function renderList(nodes) {
    const ul = el('ul', { className: 'tree-list' });
    attachListDnD(ul, nodes);
    nodes.forEach(node => ul.appendChild(renderNode(node, nodes)));
    return ul;
}

function renderNode(node, siblingsArray) {
    const li = el('li', { className: 'node ' + node.type + (node.enabled === false ? ' disabled' : '') });
    const row = el('div', { className: 'node-row' });

    row.appendChild(el('span', { className: 'drag-handle', textContent: '⠿' }));

    if (node.type === 'category') {
        row.appendChild(el('button', {
            type: 'button',
            className: 'toggle',
            textContent: collapsed.has(node.id) ? '▸' : '▾',
            onclick: () => {
                collapsed.has(node.id) ? collapsed.delete(node.id) : collapsed.add(node.id);
                renderTree();
            },
        }));
    } else {
        row.appendChild(el('span', { className: 'toggle-spacer' }));
    }

    row.appendChild(iconImg(node.icon, 'node-icon'));

    const info = el('div', { className: 'node-info' }, [el('span', { className: 'node-label', textContent: node.label })]);
    if (node.type === 'link') info.appendChild(el('span', { className: 'node-url', textContent: node.url }));
    if (node.enabled === false) info.appendChild(el('span', { className: 'badge', textContent: 'uitgeschakeld' }));
    row.appendChild(info);

    const actions = el('div', { className: 'node-actions' }, [
        iconButton('edit', `${node.label} bewerken`, () => openNodeForm({ mode: 'edit', type: node.type, node })),
        iconButton('delete', `${node.label} verwijderen`, () => deleteNode(node, siblingsArray), 'danger'),
    ]);
    row.appendChild(actions);

    li.appendChild(row);
    attachRowDnD(li, row, node, siblingsArray);

    if (node.type === 'category' && !collapsed.has(node.id)) li.appendChild(renderList(node.items));
    return li;
}

function renderTree() {
    const wrap = el('div', { className: 'tree-columns' });
    wrap.style.setProperty('--columns', columns.length);
    columns.forEach((items, i) => {
        const column = el('div', { className: 'tree-column' });
        if (columns.length > 1) column.appendChild(el('h3', { className: 'tree-column-title', textContent: `Kolom ${i + 1}` }));
        column.appendChild(renderList(items));
        wrap.appendChild(column);
    });
    $('#tree-root').replaceChildren(wrap);
}

async function saveTree() {
    syncTree();
    setStatus('Opslaan…');
    try {
        await api('bookmarks.save', { json: { tree } });
        setStatus('Opgeslagen', { autoHide: true });
    } catch (err) {
        setStatus('Opslaan mislukt: ' + err.message, { error: true });
    }
}

/* Bewerkformulier */

const nodeOverlay = $('#node-overlay');

/** Vult de keuzelijst "Plaats in" met de kolommen (hoofdniveau) en alle (sub)categorieën. */
function fillParentSelect() {
    const select = $('#f-parent');
    select.replaceChildren();
    const walk = (nodes, depth) => nodes.forEach((n) => {
        if (n.type !== 'category') return;
        select.append(el('option', { value: n.id, textContent: '\u2003'.repeat(depth) + n.label }));
        walk(n.items, depth + 1);
    });
    columns.forEach((items, i) => {
        const label = columns.length > 1 ? `Kolom ${i + 1}` : 'Hoofdniveau';
        select.append(el('option', { value: 'column:' + i, textContent: label }));
        walk(items, 1);
    });
    select.value = 'column:0';
}

function openNodeForm({ mode, type, node }) {
    formState = { mode, type, node };
    $('#f-parent-wrap').style.display = mode === 'create' ? '' : 'none';
    if (mode === 'create') fillParentSelect();
    $('#modal-title').textContent = mode === 'edit'
        ? `Bewerken: ${node.label}`
        : (type === 'category' ? 'Nieuwe categorie' : 'Nieuwe bookmark');
    $('#f-label').value = node ? node.label : '';
    $('#f-url').value = node?.type === 'link' ? node.url : '';
    $('#f-url-wrap').style.display = type === 'link' ? '' : 'none';
    $('#f-icon').value = node ? (node.icon || '') : '';
    $('#f-enabled').checked = node ? node.enabled !== false : true;
    updateIconPreview();
    openOverlay(nodeOverlay);
    $('#f-label').focus();
}

function updateIconPreview() {
    const val = $('#f-icon').value.trim();
    const img = $('#f-icon-preview');
    img.src = val;
    img.style.visibility = val ? 'visible' : 'hidden';
}

$('#f-icon').addEventListener('input', updateIconPreview);
$('#f-icon-pick').addEventListener('click', () => openPicker((path) => {
    $('#f-icon').value = path;
    updateIconPreview();
}));

$('#node-form').addEventListener('submit', (e) => {
    e.preventDefault();
    const label = $('#f-label').value.trim();
    const url = $('#f-url').value.trim();
    const icon = $('#f-icon').value.trim();
    const enabled = $('#f-enabled').checked;
    if (!label) return;
    if (formState.type === 'link' && !url) {
        alert('Een bookmark heeft een URL nodig.');
        return;
    }

    if (formState.mode === 'edit') {
        const n = formState.node;
        n.label = label;
        n.icon = icon;
        if (n.type === 'link') n.url = url;
        if (enabled) delete n.enabled; else n.enabled = false;
    } else {
        const newNode = { id: uid(), type: formState.type, label, icon };
        if (formState.type === 'link') newNode.url = url; else newNode.items = [];
        if (!enabled) newNode.enabled = false;
        const target = $('#f-parent').value;
        if (target.startsWith('column:')) {
            columns[Number(target.slice(7))].push(newNode);
        } else {
            const parent = locate(target).node;
            parent.items.push(newNode);
            collapsed.delete(parent.id);
        }
    }

    closeOverlay(nodeOverlay);
    renderTree();
    saveTree();
});

$('#add-root-category').addEventListener('click', () => openNodeForm({ mode: 'create', type: 'category' }));
$('#add-root-link').addEventListener('click', () => openNodeForm({ mode: 'create', type: 'link' }));

$('#reload').addEventListener('click', async () => {
    try {
        tree = (await api('bookmarks')).tree;
        columns = splitColumns();
        collapsed.clear();
        renderTree();
        setStatus('Herladen', { autoHide: true });
    } catch (err) {
        setStatus('Herladen mislukt: ' + err.message, { error: true });
    }
});

/* =====================================================================
 * Icons
 * ===================================================================== */

let freshIcons = new Set();

/** Welke bookmarks en instellingen gebruiken elk icoonpad? */
function iconUsage() {
    const usage = new Map();
    const add = (path, what) => {
        if (!path) return;
        if (!usage.has(path)) usage.set(path, []);
        usage.get(path).push(what);
    };
    const walk = (nodes) => nodes.forEach(node => {
        add(node.icon, node.label);
        if (node.type === 'category') walk(node.items);
    });
    walk(tree);
    add(settings.logo, '(logo)');
    add(settings.favicon, '(favicon)');
    return usage;
}

function renderIconManager() {
    const q = $('#icons-search').value.trim().toLowerCase();
    const usage = iconUsage();
    const list = icons.filter(icon => icon.file.toLowerCase().includes(q));

    $('#icons-count').textContent = `${icons.length} icons.`;
    $('#icons-grid').replaceChildren(...list.map((icon) => {
        const users = usage.get(icon.path) || [];
        const item = el('div', { className: 'icon-item' + (freshIcons.has(icon.path) ? ' fresh' : ''), title: users.join(', ') }, [
            iconImg(icon.path),
            el('span', { className: 'name', textContent: icon.file }),
            el('span', {
                className: 'usage' + (users.length ? '' : ' unused'),
                textContent: users.length ? `${users.length}× gebruikt` : 'niet gebruikt',
            }),
            iconButton('delete', `${icon.file} verwijderen`, () => deleteIcon(icon, users), 'delete danger'),
        ]);
        return item;
    }));
}

async function deleteIcon(icon, users) {
    const message = users.length
        ? `"${icon.file}" wordt gebruikt door: ${users.join(', ')}.\n\nToch verwijderen? Deze bookmarks krijgen dan geen icoon meer.`
        : `"${icon.file}" verwijderen?`;
    if (!confirm(message)) return;
    try {
        const data = await api('icons.delete', { json: { file: icon.file } });
        icons = data.icons;
        tree = data.tree;
        columns = splitColumns();
        renderTree();
        renderIconManager();
        setStatus(`${icon.file} verwijderd`, { autoHide: true });
    } catch (err) {
        setStatus('Verwijderen mislukt: ' + err.message, { error: true });
    }
}

/** Uploadt een of meer icons; geeft de paden van de gelukte uploads terug. */
async function uploadIcons(fileList) {
    const files = [...fileList];
    if (!files.length) return [];
    const tooBig = files.filter(f => f.size > boot.limits.icon);
    if (tooBig.length) {
        setStatus('Te groot (max. 2 MB): ' + tooBig.map(f => f.name).join(', '), { error: true });
        return [];
    }

    const form = new FormData();
    files.forEach(f => form.append('files[]', f));
    setStatus('Uploaden…');
    try {
        const data = await api('icons.upload', { form });
        icons = data.icons;
        freshIcons = new Set(data.uploaded);
        renderIconManager();
        if (data.errors.length) {
            setStatus(data.errors.join(' '), { error: true });
        } else {
            setStatus(`${data.uploaded.length} icon(s) geüpload`, { autoHide: true });
        }
        return data.uploaded;
    } catch (err) {
        setStatus('Upload mislukt: ' + err.message, { error: true });
        return [];
    }
}

$('#icons-search').addEventListener('input', renderIconManager);
$('#icons-upload').addEventListener('change', async (e) => {
    await uploadIcons(e.target.files);
    e.target.value = '';
});

const dropzone = $('#icons-drop');
dropzone.addEventListener('dragover', (e) => {
    if (!e.dataTransfer.types.includes('Files')) return;
    e.preventDefault();
    dropzone.classList.add('over');
});
dropzone.addEventListener('dragleave', (e) => {
    if (!dropzone.contains(e.relatedTarget)) dropzone.classList.remove('over');
});
dropzone.addEventListener('drop', (e) => {
    if (!e.dataTransfer.files.length) return;
    e.preventDefault();
    dropzone.classList.remove('over');
    uploadIcons(e.dataTransfer.files);
});

/* Icoonkiezer (gedeeld door bookmarkformulier en vormgeving) */

const pickerOverlay = $('#picker-overlay');
let onPick = null;

function openPicker(callback) {
    onPick = callback;
    $('#picker-search').value = '';
    renderPicker();
    openOverlay(pickerOverlay);
    $('#picker-search').focus();
}

function pick(path) {
    closeOverlay(pickerOverlay);
    onPick?.(path);
}

function renderPicker() {
    const q = $('#picker-search').value.trim().toLowerCase();
    $('#picker-grid').replaceChildren(...icons
        .filter(icon => icon.file.toLowerCase().includes(q))
        .map(icon => el('button', { type: 'button', className: 'icon-item', onclick: () => pick(icon.path) }, [
            iconImg(icon.path),
            el('span', { className: 'name', textContent: icon.file }),
        ])));
}

$('#picker-search').addEventListener('input', renderPicker);
$('#picker-upload').addEventListener('change', async (e) => {
    const uploaded = await uploadIcons(e.target.files);
    e.target.value = '';
    if (uploaded.length) pick(uploaded[0]);
});

/* =====================================================================
 * Instellingen en Vormgeving
 *
 * Beide tabbladen vullen samen één formulier (#design-form); de velden op het
 * tabblad Vormgeving horen er via het form-attribuut bij.
 * ===================================================================== */

const designForm = $('#design-form');
let draft = structuredClone(settings);
let designDirty = false;

function fillDesignForm() {
    draft = structuredClone(settings);
    designForm.elements.name.value = draft.name;
    designForm.elements.logo_animation.checked = draft.logo_animation;
    for (const [key, value] of Object.entries(draft.colors)) {
        designForm.elements['colors.' + key].value = value;
    }
    designForm.elements['layout.columns'].value = String(draft.layout.columns);
    designForm.elements['layout.logo_position'].value = draft.layout.logo_position;
    designForm.elements.background_mode.value = detectBackgroundMode(settings);
    $$('.asset-field').forEach(renderAssetField);
    renderBackgroundMode();
    applyThemePreview();
    designDirty = false;
}

function renderAssetField(field) {
    const img = $('.asset-preview img', field);
    const path = draft[field.dataset.field];
    if (path) img.src = path; else img.removeAttribute('src');
}

function setAsset(field, path) {
    draft[field.dataset.field] = path;
    renderAssetField(field);
    designDirty = true;
    if (field.dataset.field === 'background_image') {
        applyThemePreview();
        renderBgPreview();
    }
}

/** Laat kleuren en achtergrond direct zien op het beheerscherm zelf. */
function applyThemePreview() {
    const root = document.documentElement.style;
    for (const key of Object.keys(draft.colors)) {
        root.setProperty('--color-' + key.replace('_', '-'), designForm.elements['colors.' + key].value);
    }
    root.setProperty('--background-image', backgroundImageCss(1));
}

$$('.asset-field').forEach((field) => {
    const key = field.dataset.field;

    $('input[type="file"]', field).addEventListener('change', async (e) => {
        const file = e.target.files[0];
        e.target.value = '';
        if (!file) return;
        if (file.size > boot.limits.branding) {
            setStatus('Bestand is te groot (max. 8 MB).', { error: true });
            return;
        }
        const form = new FormData();
        form.append('slot', field.dataset.slot);
        form.append('file', file);
        setStatus('Uploaden…');
        try {
            setAsset(field, (await api('branding.upload', { form })).path);
            setStatus('Geüpload — vergeet niet op te slaan', { autoHide: true });
        } catch (err) {
            setStatus('Upload mislukt: ' + err.message, { error: true });
        }
    });

    $('[data-pick-icon]', field)?.addEventListener('click', () => openPicker(path => setAsset(field, path)));
    $('[data-reset]', field)?.addEventListener('click', () => setAsset(field, defaults[key]));
    $('[data-clear]', field)?.addEventListener('click', () => setAsset(field, ''));
});

document.addEventListener('input', (e) => {
    if (e.target.form !== designForm) return;
    designDirty = true;
    if (e.target.type === 'color') {
        applyThemePreview();
        renderBgPreview();
    }
});

$('#reset-colors').addEventListener('click', () => {
    for (const [key, value] of Object.entries(defaults.colors)) {
        designForm.elements['colors.' + key].value = value;
    }
    designDirty = true;
    applyThemePreview();
    renderBgPreview();
});

designForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const f = designForm.elements;
    const payload = {
        name: f.name.value.trim(),
        logo_animation: f.logo_animation.checked,
        logo: draft.logo,
        favicon: draft.favicon,
        background_image: draft.background_image,
        colors: Object.fromEntries(Object.keys(defaults.colors).map(key => [key, f['colors.' + key].value])),
        layout: {
            columns: Number(f['layout.columns'].value),
            logo_position: f['layout.logo_position'].value,
        },
    };

    setStatus('Opslaan…');
    try {
        const mode = backgroundMode();
        if (mode === 'color') {
            payload.background_image = '';
        } else if (mode === 'image' && !payload.background_image) {
            payload.background_image = defaults.background_image;
        } else if (mode === 'pattern') {
            if (!patternIsActive()) {
                // Patroon genereren en opslaan; dat levert het bestand en de basiskleur
                const type = BG.find(bgType);
                const params = bgParams[bgType];
                settings = (await api('background.save', {
                    json: { type: bgType, params, svg: type.svg(params), base: type.base(params) },
                })).settings;
                bgDirty.delete(bgType);
            }
            payload.background_image = settings.background_image;
            payload.colors.background = settings.colors.background;
        }
        settings = (await api('settings.save', { json: payload })).settings;
        fillDesignForm();
        columns = splitColumns();
        renderTree();
        setStatus('Instellingen opgeslagen', { autoHide: true });
    } catch (err) {
        setStatus('Opslaan mislukt: ' + err.message, { error: true });
    }
});

/* Wachtwoord */

const passwordForm = $('#password-form');

passwordForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const f = passwordForm.elements;
    if (f.new.value !== f.repeat.value) {
        setStatus('De nieuwe wachtwoorden komen niet overeen.', { error: true });
        f.repeat.focus();
        return;
    }
    setStatus('Wachtwoord wijzigen…');
    try {
        const data = await api('password.change', {
            json: { current: f.current.value, new: f.new.value, repeat: f.repeat.value },
        });
        boot.csrf = data.csrf;
        passwordForm.reset();
        setStatus('Wachtwoord gewijzigd', { autoHide: true });
    } catch (err) {
        setStatus('Wijzigen mislukt: ' + err.message, { error: true });
    }
});

/* Software bijwerken */

let updateChecked = false;
const updateRun = $('#update-run');
const updateLog = $('#update-log');
const shortSha = (sha) => sha ? sha.slice(0, 7) : 'onbekend';
const version = (build, sha) => build ? `build ${build} (${shortSha(sha)})` : shortSha(sha);
const formatDate = (iso) => iso ? new Date(iso).toLocaleString('nl-NL', { dateStyle: 'medium', timeStyle: 'short' }) : '';

async function checkUpdate() {
    updateChecked = true;
    updateRun.disabled = true;
    $('#update-latest').textContent = 'controleren…';
    try {
        const s = await api('update.status');
        $('#update-installed').textContent = version(s.build, s.installed) + (s.current ? ' (actueel)' : '');
        $('#update-latest').textContent = `${version(s.latest.build, s.latest.sha)} · ${formatDate(s.latest.date)} · ${s.latest.message}`;
        $('#update-mode').textContent = s.mode === 'git'
            ? 'git pull (deze installatie is een git-clone)'
            : `download van github.com/${s.repo} (${s.branch})`;
        updateRun.disabled = s.current || !s.writable;
        updateRun.textContent = s.current ? 'Al bijgewerkt' : 'Bijwerken';
        if (!s.writable) setStatus('PHP mag de projectmap niet overschrijven (schrijfrechten).', { error: true });
    } catch (err) {
        $('#update-latest').textContent = 'onbekend';
        setStatus('Controleren mislukt: ' + err.message, { error: true });
    }
}

$('#update-check').addEventListener('click', checkUpdate);

updateRun.addEventListener('click', async () => {
    if (!confirm('De code van deze site bijwerken naar de nieuwste versie van GitHub?')) return;
    updateRun.disabled = true;
    setStatus('Bijwerken…');
    try {
        const data = await api('update.run', { json: {} });
        updateLog.textContent = data.log;
        updateLog.hidden = false;
        setStatus('Bijgewerkt naar ' + shortSha(data.sha) + '; pagina wordt herladen…');
        setTimeout(() => location.reload(), 2500);
    } catch (err) {
        updateLog.textContent = err.message;
        updateLog.hidden = false;
        setStatus('Bijwerken mislukt', { error: true });
        updateRun.disabled = false;
    }
});

window.addEventListener('beforeunload', (e) => {
    if (designDirty || bgDirty.size) e.preventDefault();
});

/* =====================================================================
 * Vormgeving: achtergrond (eigen afbeelding, patroon of solide kleur)
 * ===================================================================== */

const BG = window.Backgrounds;
// Per patroon de huidige parameters, zodat wisselen tussen patronen geen wijzigingen weggooit.
const bgParams = Object.fromEntries(BG.types.map(t => [t.id, { ...t.defaults }]));
let bgType = 'liquid-cheese';
const bgDirty = new Set(); // patronen met niet-opgeslagen wijzigingen

if (settings.background_pattern && BG.find(settings.background_pattern.type)) {
    bgType = settings.background_pattern.type;
    Object.assign(bgParams[bgType], settings.background_pattern.params);
}

const svgUrl = (svg) => `url("data:image/svg+xml,${encodeURIComponent(svg)}")`;
const fileUrl = (path) => `url("${new URL(path, location.href).href}")`;
/** Herhalende tegels verkleinen in verhouding tot een echt scherm, zodat een preview klopt. */
const tileScaleFor = (element) => element.clientWidth / Math.max(screen.width, 1);
const backgroundMode = () => designForm.elements.background_mode.value;

function detectBackgroundMode(s) {
    if (!s.background_image) return 'color';
    return s.background_pattern?.file === s.background_image ? 'pattern' : 'image';
}

/** Is het patroon zoals het nu in de editor staat ook echt de achtergrond van de site? */
function patternIsActive() {
    const saved = settings.background_pattern;
    return !!saved && saved.type === bgType && saved.file === settings.background_image && !bgDirty.has(bgType);
}

/** CSS-waarde voor background-image volgens de gekozen soort (voor beheerscherm en preview). */
function backgroundImageCss(tileScale) {
    switch (backgroundMode()) {
        case 'pattern':
            return patternIsActive() ? fileUrl(settings.background_image) : svgUrl(BG.find(bgType).svg(bgParams[bgType], tileScale));
        case 'image':
            return draft.background_image ? fileUrl(draft.background_image) : 'none';
        default:
            return 'none';
    }
}

function renderBackgroundMode() {
    const mode = backgroundMode();
    $$('.bg-mode-panel').forEach((panel) => { panel.hidden = panel.dataset.mode !== mode; });
    if (mode === 'pattern') {
        renderBgVariants();
        renderBgControls();
    }
    renderBgPreviewPage();
    renderBgPreview();
}

$$('input[name="background_mode"]').forEach(radio => radio.addEventListener('change', () => {
    // Naar "eigen afbeelding" zonder eigen afbeelding: begin met de standaard
    if (backgroundMode() === 'image' && (!draft.background_image || draft.background_image === settings.background_pattern?.file)) {
        draft.background_image = defaults.background_image;
        $$('.asset-field').forEach(renderAssetField);
    }
    renderBackgroundMode();
    applyThemePreview();
}));

function renderBgVariants() {
    $('#bg-variants').replaceChildren(...BG.types.map((type) => {
        const btn = el('button', {
            type: 'button',
            className: 'bg-variant' + (type.id === bgType ? ' selected' : ''),
            onclick: () => { bgType = type.id; renderBackgroundMode(); applyThemePreview(); },
        }, [el('span', { className: 'bg-thumb' }), el('span', { className: 'bg-name', textContent: type.name })]);
        btn.setAttribute('role', 'radio');
        btn.setAttribute('aria-checked', String(type.id === bgType));
        btn.dataset.type = type.id;
        return btn;
    }));
    // Pas na het plaatsen is de breedte bekend (nodig voor de tegelschaal)
    $$('.bg-variant').forEach((btn) => {
        const type = BG.find(btn.dataset.type);
        const thumb = $('.bg-thumb', btn);
        thumb.style.backgroundImage = svgUrl(type.svg(bgParams[type.id], tileScaleFor(thumb)));
    });
}

/** Nagebootste startpagina (logo + bookmarks in de themakleuren) bovenop de achtergrond. */
function renderBgPreviewPage() {
    const page = $('.bg-preview-page');
    page.dataset.logoPosition = designForm.elements['layout.logo_position'].value || settings.layout.logo_position;
    $('.bg-preview-logo').src = draft.logo;
    $('#bg-preview-bookmarks').replaceChildren(...columns.map((items) => el('div', { className: 'bg-preview-column' },
        items.filter(n => n.enabled !== false).slice(0, 5).map((node) => {
            const row = (n, cls) => el('div', { className: cls }, [iconImg(n.icon || (n.type === 'category' ? 'icons/map.svg' : ''), ''), el('span', { textContent: n.label })]);
            if (node.type !== 'category') return row(node, 'bg-preview-link bg-preview-root');
            return el('div', { className: 'bg-preview-category' }, [
                row(node, 'bg-preview-link bg-preview-summary'),
                ...node.items.filter(n => n.enabled !== false).slice(0, 3).map(n => row(n, 'bg-preview-link bg-preview-child')),
            ]);
        }))));
}

function renderBgPreview() {
    const preview = $('#bg-preview');
    const pattern = backgroundMode() === 'pattern';
    preview.style.backgroundColor = pattern ? BG.find(bgType).base(bgParams[bgType]) : designForm.elements['colors.background'].value;
    preview.style.backgroundImage = backgroundImageCss(tileScaleFor(preview));
    if (pattern) {
        const thumb = $(`.bg-variant[data-type="${bgType}"] .bg-thumb`);
        if (thumb) thumb.style.backgroundImage = svgUrl(BG.find(bgType).svg(bgParams[bgType], tileScaleFor(thumb)));
        renderBgState();
    }
}

function renderBgState() {
    const state = $('#bg-state');
    const dirty = bgDirty.has(bgType);
    state.textContent = patternIsActive() ? 'Actief op de site' : (dirty ? 'Niet opgeslagen' : 'Wordt actief na opslaan');
    state.className = 'bg-state' + (patternIsActive() ? ' active' : dirty ? ' dirty' : '');
}

function renderBgControls() {
    const type = BG.find(bgType);
    const params = bgParams[bgType];
    const changed = () => { bgDirty.add(bgType); renderBgPreview(); applyThemePreview(); };

    $('#bg-title').textContent = 'Patroon: ' + type.name;
    $('#bg-controls').replaceChildren(...type.fields.map((field) => {
        if (field.type === 'color') {
            const input = el('input', { type: 'color', value: params[field.key] });
            input.addEventListener('input', () => { params[field.key] = input.value; changed(); });
            return el('label', { className: 'bg-field bg-field--color' }, [input, el('span', { textContent: field.label })]);
        }
        if (field.type === 'check') {
            const input = el('input', { type: 'checkbox', checked: !!params[field.key] });
            input.addEventListener('change', () => { params[field.key] = input.checked; changed(); });
            return el('label', { className: 'bg-field bg-field--check' }, [input, el('span', { textContent: field.label })]);
        }
        const output = el('output', { textContent: params[field.key] + (field.unit || '') });
        const input = el('input', { type: 'range', min: field.min, max: field.max, step: field.step, value: params[field.key] });
        input.addEventListener('input', () => {
            params[field.key] = Number(input.value);
            output.textContent = input.value + (field.unit || '');
            changed();
        });
        return el('label', { className: 'bg-field bg-field--range' }, [
            el('span', { className: 'bg-field-label' }, [el('span', { textContent: field.label }), output]),
            input,
        ]);
    }));
}

$('#bg-reset').addEventListener('click', () => {
    bgParams[bgType] = { ...BG.find(bgType).defaults };
    bgDirty.add(bgType);
    renderBackgroundMode();
    applyThemePreview();
});

// Indeling of logo gewijzigd: preview bijwerken
$$('input[name="layout.logo_position"]').forEach(r => r.addEventListener('change', renderBgPreviewPage));

/* ---------- Start ---------- */

renderTree();
fillDesignForm();
showTab(location.hash.slice(1));
