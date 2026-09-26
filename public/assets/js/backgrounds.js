'use strict';

/*
 * Generators voor de achtergrondpatronen in het beheer (tabblad Achtergrond).
 *
 * De patronen zijn afgeleid van de gratis set "Free SVG Backgrounds and Patterns"
 * van Matt Visiwig / SVGBackgrounds.com:
 * https://www.svgbackgrounds.com/set/free-svg-backgrounds-and-patterns/
 * Die licentie vraagt om naamsvermelding; die staat in de editor en als
 * opmerking in elk gegenereerd SVG-bestand.
 *
 * Elk type levert svg(params, tileScale): een complete SVG die het hele scherm
 * vult (background-size: cover). tileScale verkleint herhalende tegels in previews.
 */
(function () {
    const SET_URL = 'https://www.svgbackgrounds.com/set/free-svg-backgrounds-and-patterns/';

    const rgb = (c) => [1, 3, 5].map(i => parseInt(c.slice(i, i + 2), 16));
    const hex = (v) => '#' + v.map(x => Math.round(Math.min(255, Math.max(0, x))).toString(16).padStart(2, '0')).join('');
    /** Lineaire menging van twee #rrggbb-kleuren; t = 0 geeft a, t = 1 geeft b. */
    const mix = (a, b, t) => { const A = rgb(a), B = rgb(b); return hex(A.map((v, i) => v + (B[i] - v) * t)); };

    const credit = (name) => `<!-- "${name}" by Matt Visiwig, SVGBackgrounds.com: ${SET_URL} -->`;
    const open = (viewBox) => `<svg xmlns="http://www.w3.org/2000/svg" width="100%" height="100%" viewBox="${viewBox}" preserveAspectRatio="xMidYMid slice">`;

    const LIQUID = ["M486 705.8c-109.3-21.8-223.4-32.2-335.3-19.4C99.5 692.1 49 703 0 719.8V800h843.8c-115.9-33.2-230.8-68.1-347.6-92.2C492.8 707.1 489.4 706.5 486 705.8z", "M1600 0H0v719.8c49-16.8 99.5-27.8 150.7-33.5c111.9-12.7 226-2.4 335.3 19.4c3.4 0.7 6.8 1.4 10.2 2c116.8 24 231.7 59 347.6 92.2H1600V0z", "M478.4 581c3.2 0.8 6.4 1.7 9.5 2.5c196.2 52.5 388.7 133.5 593.5 176.6c174.2 36.6 349.5 29.2 518.6-10.2V0H0v574.9c52.3-17.6 106.5-27.7 161.1-30.9C268.4 537.4 375.7 554.2 478.4 581z", "M0 0v429.4c55.6-18.4 113.5-27.3 171.4-27.7c102.8-0.8 203.2 22.7 299.3 54.5c3 1 5.9 2 8.9 3c183.6 62 365.7 146.1 562.4 192.1c186.7 43.7 376.3 34.4 557.9-12.6V0H0z", "M181.8 259.4c98.2 6 191.9 35.2 281.3 72.1c2.8 1.1 5.5 2.3 8.3 3.4c171 71.6 342.7 158.5 531.3 207.7c198.8 51.8 403.4 40.8 597.3-14.8V0H0v283.2C59 263.6 120.6 255.7 181.8 259.4z", "M1600 0H0v136.3c62.3-20.9 127.7-27.5 192.2-19.2c93.6 12.1 180.5 47.7 263.3 89.6c2.6 1.3 5.1 2.6 7.7 3.9c158.4 81.1 319.7 170.9 500.3 223.2c210.5 61 430.8 49 636.6-16.6V0z", "M454.9 86.3C600.7 177 751.6 269.3 924.1 325c208.6 67.4 431.3 60.8 637.9-5.3c12.8-4.1 25.4-8.4 38.1-12.9V0H288.1c56 21.3 108.7 50.6 159.7 82C450.2 83.4 452.5 84.9 454.9 86.3z", "M1600 0H498c118.1 85.8 243.5 164.5 386.8 216.2c191.8 69.2 400 74.7 595 21.1c40.8-11.2 81.1-25.2 120.3-41.7V0z", "M1397.5 154.8c47.2-10.6 93.6-25.3 138.6-43.8c21.7-8.9 43-18.8 63.9-29.5V0H643.4c62.9 41.7 129.7 78.2 202.1 107.4C1020.4 178.1 1214.2 196.1 1397.5 154.8z", "M1315.3 72.4c75.3-12.6 148.9-37.1 216.8-72.4h-723C966.8 71 1144.7 101 1315.3 72.4z"];
    const BOXES = [["d1", "polygon", "points", "100 57.1 64 93.1 71.5 100.6 100 72.1"], ["d2", "polygon", "points", "100 57.1 100 72.1 128.6 100.6 136.1 93.1"], ["d1", "polygon", "points", "100 163.2 100 178.2 170.7 107.5 170.8 92.4"], ["d2", "polygon", "points", "100 163.2 29.2 92.5 29.2 107.5 100 178.2"], ["diamond", "path", "d", "M100 21.8L29.2 92.5l70.7 70.7l70.7-70.7L100 21.8z M100 127.9L64.6 92.5L100 57.1l35.4 35.4L100 127.9z"], ["c1", "polygon", "points", "0 157.1 0 172.1 28.6 200.6 36.1 193.1"], ["c2", "polygon", "points", "70.7 200 70.8 192.4 63.2 200"], ["corners", "polygon", "points", "27.8 200 63.2 200 70.7 192.5 0 121.8 0 157.2 35.3 192.5"], ["c2", "polygon", "points", "200 157.1 164 193.1 171.5 200.6 200 172.1"], ["c1", "polygon", "points", "136.7 200 129.2 192.5 129.2 200"], ["corners", "polygon", "points", "172.1 200 164.6 192.5 200 157.1 200 157.2 200 121.8 200 121.8 129.2 192.5 136.7 200"], ["c1", "polygon", "points", "129.2 0 129.2 7.5 200 78.2 200 63.2 136.7 0"], ["corners", "polygon", "points", "200 27.8 200 27.9 172.1 0 136.7 0 200 63.2 200 63.2"], ["c2", "polygon", "points", "63.2 0 0 63.2 0 78.2 70.7 7.5 70.7 0"], ["corners", "polygon", "points", "0 63.2 63.2 0 27.8 0 0 27.8"]];
    const RAY = "M998.7 439.2c1.7-26.5 1.7-52.7 0.1-78.5L401 399.9c0 0 0-0.1 0-0.1l587.6-116.9c-5.1-25.9-11.9-51.2-20.3-75.8L400.9 399.7c0 0 0-0.1 0-0.1l537.3-265c-11.6-23.5-24.8-46.2-39.3-67.9L400.8 399.5c0 0 0-0.1-0.1-0.1l450.4-395c-17.3-19.7-35.8-38.2-55.5-55.5l-395 450.4c0 0-0.1 0-0.1-0.1L733.4-99c-21.7-14.5-44.4-27.6-68-39.3l-265 537.4c0 0-0.1 0-0.1 0l192.6-567.4c-24.6-8.3-49.9-15.1-75.8-20.2L400.2 399c0 0-0.1 0-0.1 0l39.2-597.7c-26.5-1.7-52.7-1.7-78.5-0.1L399.9 399c0 0-0.1 0-0.1 0L282.9-188.6c-25.9 5.1-51.2 11.9-75.8 20.3l192.6 567.4c0 0-0.1 0-0.1 0l-265-537.3c-23.5 11.6-46.2 24.8-67.9 39.3l332.8 498.1c0 0-0.1 0-0.1 0.1L4.4-51.1C-15.3-33.9-33.8-15.3-51.1 4.4l450.4 395c0 0 0 0.1-0.1 0.1L-99 66.6c-14.5 21.7-27.6 44.4-39.3 68l537.4 265c0 0 0 0.1 0 0.1l-567.4-192.6c-8.3 24.6-15.1 49.9-20.2 75.8L399 399.8c0 0 0 0.1 0 0.1l-597.7-39.2c-1.7 26.5-1.7 52.7-0.1 78.5L399 400.1c0 0 0 0.1 0 0.1l-587.6 116.9c5.1 25.9 11.9 51.2 20.3 75.8l567.4-192.6c0 0 0 0.1 0 0.1l-537.3 265c11.6 23.5 24.8 46.2 39.3 67.9l498.1-332.8c0 0 0 0.1 0.1 0.1l-450.4 395c17.3 19.7 35.8 38.2 55.5 55.5l395-450.4c0 0 0.1 0 0.1 0.1L66.6 899c21.7 14.5 44.4 27.6 68 39.3l265-537.4c0 0 0.1 0 0.1 0L207.1 968.3c24.6 8.3 49.9 15.1 75.8 20.2L399.8 401c0 0 0.1 0 0.1 0l-39.2 597.7c26.5 1.7 52.7 1.7 78.5 0.1L400.1 401c0 0 0.1 0 0.1 0l116.9 587.6c25.9-5.1 51.2-11.9 75.8-20.3L400.3 400.9c0 0 0.1 0 0.1 0l265 537.3c23.5-11.6 46.2-24.8 67.9-39.3L400.5 400.8c0 0 0.1 0 0.1-0.1l395 450.4c19.7-17.3 38.2-35.8 55.5-55.5l-450.4-395c0 0 0-0.1 0.1-0.1L899 733.4c14.5-21.7 27.6-44.4 39.3-68l-537.4-265c0 0 0-0.1 0-0.1l567.4 192.6c8.3-24.6 15.1-49.9 20.2-75.8L401 400.2c0 0 0-0.1 0-0.1L998.7 439.2z";

    const types = [
        {
            id: 'liquid-cheese',
            name: 'Liquid Cheese',
            fields: [
                { key: 'from', label: 'Kleur onder', type: 'color' },
                { key: 'to', label: 'Kleur boven', type: 'color' },
                { key: 'flip', label: 'Spiegelen', type: 'check' },
            ],
            // Standaard: de groene golven die de site al had
            defaults: { from: '#e3ffc6', to: '#72df6e', flip: false },
            base: p => p.from,
            svg(p) {
                const paths = LIQUID.map((d, i) => `<path fill="${mix(p.from, p.to, (i + 1) / LIQUID.length)}" d="${d}"/>`).join('');
                const flip = p.flip ? ' transform="matrix(-1 0 0 1 1600 0)"' : '';
                return `${open('0 0 1600 800')}${credit(this.name)}<rect fill="${p.from}" width="1600" height="800"/><g${flip}>${paths}</g></svg>`;
            },
        },
        {
            id: 'bullseye-gradient',
            name: 'Bullseye Gradient',
            fields: [
                { key: 'outer', label: 'Kleur buiten', type: 'color' },
                { key: 'inner', label: 'Kleur midden', type: 'color' },
                { key: 'rings', label: 'Aantal ringen', type: 'range', min: 3, max: 16, step: 1 },
                { key: 'cx', label: 'Middelpunt horizontaal', type: 'range', min: 0, max: 100, step: 1, unit: '%' },
                { key: 'cy', label: 'Middelpunt verticaal', type: 'range', min: 0, max: 100, step: 1, unit: '%' },
            ],
            defaults: { outer: '#000000', inner: '#552277', rings: 6, cx: 50, cy: 50 },
            base: p => p.outer,
            svg(p) {
                const cx = p.cx * 8, cy = p.cy * 8, n = p.rings;
                // grootste ring reikt minstens tot de verste hoek, zodat er geen lege hoeken zijn
                const reach = Math.max(600, ...[[0, 0], [800, 0], [0, 800], [800, 800]].map(([x, y]) => Math.hypot(x - cx, y - cy)));
                let circles = '';
                for (let k = 0; k < n; k++) {
                    circles += `<circle fill="${mix(p.outer, p.inner, k / (n - 1))}" cx="${cx}" cy="${cy}" r="${(reach * (n - k) / n).toFixed(1)}"/>`;
                }
                return `${open('0 0 800 800')}${credit(this.name)}<rect fill="${p.outer}" width="800" height="800"/><g>${circles}</g></svg>`;
            },
        },
        {
            id: 'hollowed-boxes',
            name: 'Hollowed Boxes',
            fields: [
                { key: 'bg', label: 'Achtergrond', type: 'color' },
                { key: 'diamond', label: 'Kleur ruiten', type: 'color' },
                { key: 'corners', label: 'Kleur hoeken', type: 'color' },
                { key: 'scale', label: 'Tegelgrootte', type: 'range', min: 40, max: 400, step: 10, unit: 'px' },
            ],
            defaults: { bg: '#487346', diamond: '#89cc7c', corners: '#b6cc76', scale: 200 },
            base: p => p.bg,
            svg(p, tileScale = 1) {
                const shade = {
                    diamond: p.diamond, d2: mix(p.diamond, p.bg, 0.35), d1: mix(p.diamond, p.bg, 0.7),
                    corners: p.corners, c2: mix(p.corners, p.bg, 0.35), c1: mix(p.corners, p.bg, 0.7),
                };
                const s = Math.max(4, p.scale * tileScale);
                const shapes = BOXES.map(([color, tag, attr, value]) => `<${tag} fill="${shade[color]}" ${attr}="${value}"/>`).join('');
                return `<svg xmlns="http://www.w3.org/2000/svg" width="100%" height="100%">${credit(this.name)}`
                    + `<defs><pattern id="p" width="${s}" height="${s}" patternUnits="userSpaceOnUse">`
                    + `<g transform="scale(${s / 200})"><rect fill="${p.bg}" width="200" height="200"/>${shapes}</g></pattern></defs>`
                    + `<rect width="100%" height="100%" fill="url(#p)"/></svg>`;
            },
        },
        {
            id: 'quantum-gradient',
            name: 'Quantum Gradient',
            fields: [
                { key: 'tl', label: 'Linksboven', type: 'color' },
                { key: 'tr', label: 'Rechtsboven', type: 'color' },
                { key: 'bl', label: 'Linksonder', type: 'color' },
                { key: 'br', label: 'Rechtsonder', type: 'color' },
                { key: 'bands', label: 'Aantal banen', type: 'range', min: 4, max: 24, step: 1 },
            ],
            defaults: { tl: '#ff0000', tr: '#ee00ff', bl: '#ee00ff', br: '#000077', bands: 12 },
            base: p => p.tl,
            svg(p) {
                const n = p.bands, w = 1200 / n;
                let defs = '', rects = '';
                for (let k = 0; k < n; k++) {
                    const t = k / (n - 1), x = k * w;
                    defs += `<linearGradient id="g${k}" gradientUnits="userSpaceOnUse" x1="${x + 50}" y1="25" x2="${x + 50}" y2="777">`
                        + `<stop offset="0" stop-color="${mix(p.tl, p.tr, t)}"/><stop offset="1" stop-color="${mix(p.bl, p.br, t)}"/></linearGradient>`;
                    rects += `<rect fill="url(#g${k})" x="${x.toFixed(2)}" width="${(1200 - x).toFixed(2)}" height="800"/>`;
                }
                return `${open('0 0 1200 800')}${credit(this.name)}<rect fill="${p.tl}" width="1200" height="800"/><defs>${defs}</defs><g>${rects}</g></svg>`;
            },
        },
        {
            id: 'rose-petals',
            name: 'Rose Petals',
            fields: [
                { key: 'bg', label: 'Achtergrond', type: 'color' },
                { key: 'glow', label: 'Gloed', type: 'color' },
                { key: 'petals', label: 'Blaadjes', type: 'color' },
                { key: 'opacity', label: 'Zichtbaarheid blaadjes', type: 'range', min: 0, max: 100, step: 5, unit: '%' },
            ],
            defaults: { bg: '#330000', glow: '#dd1188', petals: '#ffaa33', opacity: 40 },
            base: p => p.bg,
            svg(p) {
                return `${open('0 0 800 400')}${credit(this.name)}<rect fill="${p.bg}" width="800" height="400"/><defs>`
                    + `<radialGradient id="a" cx="396" cy="281" r="514" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="${p.glow}"/><stop offset="1" stop-color="${p.bg}"/></radialGradient>`
                    + `<linearGradient id="b" gradientUnits="userSpaceOnUse" x1="400" y1="148" x2="400" y2="333"><stop offset="0" stop-color="${p.petals}" stop-opacity="0"/><stop offset="1" stop-color="${p.petals}" stop-opacity="0.5"/></linearGradient>`
                    + `</defs><rect fill="url(#a)" width="800" height="400"/><g fill-opacity="${p.opacity / 100}">`
                    + `<circle fill="url(#b)" cx="267.5" cy="61" r="300"/><circle fill="url(#b)" cx="532.5" cy="61" r="300"/><circle fill="url(#b)" cx="400" cy="30" r="300"/></g></svg>`;
            },
        },
        {
            id: 'wintery-sunburst',
            name: 'Wintery Sunburst',
            fields: [
                { key: 'center', label: 'Kleur midden', type: 'color' },
                { key: 'edge', label: 'Kleur rand', type: 'color' },
                { key: 'rays', label: 'Kleur stralen', type: 'color' },
                { key: 'opacity', label: 'Zichtbaarheid stralen', type: 'range', min: 0, max: 100, step: 5, unit: '%' },
            ],
            defaults: { center: '#ffffff', edge: '#00eeff', rays: '#00ffff', opacity: 80 },
            base: p => p.edge,
            svg(p) {
                return `${open('0 0 800 800')}${credit(this.name)}<rect fill="${p.center}" width="800" height="800"/><defs>`
                    + `<radialGradient id="a" cx="400" cy="400" r="50%" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="${p.center}"/><stop offset="1" stop-color="${p.edge}"/></radialGradient>`
                    + `<radialGradient id="b" cx="400" cy="400" r="70%" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="${p.center}"/><stop offset="1" stop-color="${p.rays}"/></radialGradient>`
                    + `</defs><rect fill="url(#a)" width="800" height="800"/><g fill-opacity="${p.opacity / 100}"><path fill="url(#b)" d="${RAY}"/></g></svg>`;
            },
        },
    ];

    window.Backgrounds = { types, SET_URL, find: id => types.find(t => t.id === id) };
})();
