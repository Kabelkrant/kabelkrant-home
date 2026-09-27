'use strict';

/*
 * Achtergrondpatronen voor de live preview in het beheer (tabblad Vormgeving).
 *
 * Soorten, velden, standaardwaarden en vormen komen uit app/backgrounds.json
 * (via boot.backgrounds). De site zelf krijgt de SVG van de server (/?bg=…),
 * gemaakt door app/Backgrounds.php; de sjablonen hieronder moeten daarmee
 * gelijk blijven.
 *
 * De patronen zijn afgeleid van de gratis set "Free SVG Backgrounds and Patterns"
 * van Matt Visiwig / SVGBackgrounds.com; die licentie vraagt om naamsvermelding,
 * die in de editor en als opmerking in elke SVG staat.
 */
(function () {
    const rgb = (c) => [1, 3, 5].map(i => parseInt(c.slice(i, i + 2), 16));
    const hex = (v) => '#' + v.map(x => Math.round(Math.min(255, Math.max(0, x))).toString(16).padStart(2, '0')).join('');
    /** Lineaire menging van twee #rrggbb-kleuren; t = 0 geeft a, t = 1 geeft b. */
    const mix = (a, b, t) => { const A = rgb(a), B = rgb(b); return hex(A.map((v, i) => v + (B[i] - v) * t)); };
    /** Getal met hooguit twee decimalen, zonder overbodige nullen (zoals in Backgrounds::num). */
    const num = (n) => String(+n.toFixed(2));

    const open = (viewBox) => `<svg xmlns="http://www.w3.org/2000/svg" width="100%" height="100%" viewBox="${viewBox}" preserveAspectRatio="xMidYMid slice">`;

    const templates = {
        'liquid-cheese': {
            viewBox: '0 0 1600 800',
            body(p, type) {
                const n = type.paths.length;
                const paths = type.paths.map((d, i) => `<path fill="${mix(p.from, p.to, (i + 1) / n)}" d="${d}"/>`).join('');
                const flip = p.flip ? ' transform="matrix(-1 0 0 1 1600 0)"' : '';
                return `<rect fill="${p.from}" width="1600" height="800"/><g${flip}>${paths}</g>`;
            },
        },
        'quantum-gradient': {
            viewBox: '0 0 1200 800',
            body(p) {
                const n = p.bands, w = 1200 / n;
                let defs = '', rects = '';
                for (let k = 0; k < n; k++) {
                    const t = k / (n - 1), x = k * w;
                    defs += `<linearGradient id="g${k}" gradientUnits="userSpaceOnUse" x1="${num(x + 50)}" y1="25" x2="${num(x + 50)}" y2="777">`
                        + `<stop offset="0" stop-color="${mix(p.tl, p.tr, t)}"/><stop offset="1" stop-color="${mix(p.bl, p.br, t)}"/></linearGradient>`;
                    rects += `<rect fill="url(#g${k})" x="${num(x)}" width="${num(1200 - x)}" height="800"/>`;
                }
                return `<rect fill="${p.tl}" width="1200" height="800"/><defs>${defs}</defs><g>${rects}</g>`;
            },
        },
        'rose-petals': {
            viewBox: '0 0 800 400',
            body(p) {
                return `<rect fill="${p.bg}" width="800" height="400"/><defs>`
                    + `<radialGradient id="a" cx="396" cy="281" r="514" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="${p.glow}"/><stop offset="1" stop-color="${p.bg}"/></radialGradient>`
                    + `<linearGradient id="b" gradientUnits="userSpaceOnUse" x1="400" y1="148" x2="400" y2="333"><stop offset="0" stop-color="${p.petals}" stop-opacity="0"/><stop offset="1" stop-color="${p.petals}" stop-opacity="0.5"/></linearGradient>`
                    + `</defs><rect fill="url(#a)" width="800" height="400"/><g fill-opacity="${num(p.opacity / 100)}">`
                    + `<circle fill="url(#b)" cx="267.5" cy="61" r="300"/><circle fill="url(#b)" cx="532.5" cy="61" r="300"/><circle fill="url(#b)" cx="400" cy="30" r="300"/></g>`;
            },
        },
    };

    /**
     * Maakt de lijst patronen uit de definities van de server. Elk type krijgt
     * svg(params) (complete SVG) en base(params) (vulkleur onder de SVG).
     */
    window.Backgrounds = function (definitions) {
        const types = definitions.types.filter(t => templates[t.id]).map((type) => ({
            ...type,
            base: p => p[type.base],
            svg(p) {
                const credit = `<!-- "${type.name}" by Matt Visiwig, SVGBackgrounds.com: ${definitions.set_url} -->`;
                return open(templates[type.id].viewBox) + credit + templates[type.id].body(p, type) + '</svg>';
            },
        }));
        return { types, find: id => types.find(t => t.id === id) };
    };
})();
