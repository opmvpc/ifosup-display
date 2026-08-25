/**
 * Avatars SVG déterministes, générés localement (aucun appel réseau).
 *
 * Chaque fonction prend une graine (nom, matricule, e-mail…) et renvoie une
 * data URI directement utilisable dans `<img :src="avatar(name)">`.
 * Même graine ⇒ même image, sur tous les navigateurs.
 *
 * Gamme chromatique volontairement sourde (branding bleu nuit / ambre + pastels
 * désaturés) pour se marier au thème zinc de l'app, en clair comme en sombre.
 */

/* ------------------------------------------------------------------ */
/* Hash + PRNG                                                         */
/* ------------------------------------------------------------------ */

/** cyrb53 : hash 53 bits, rapide et bien distribué (deux graines proches divergent). */
function cyrb53(str: string, seed: number): number {
    let h1 = 0xdeadbeef ^ seed;
    let h2 = 0x41c6ce57 ^ seed;

    for (let i = 0; i < str.length; i++) {
        const ch = str.charCodeAt(i);
        h1 = Math.imul(h1 ^ ch, 2654435761);
        h2 = Math.imul(h2 ^ ch, 1597334677);
    }

    h1 =
        Math.imul(h1 ^ (h1 >>> 16), 2246822507) ^
        Math.imul(h2 ^ (h2 >>> 13), 3266489909);
    h2 =
        Math.imul(h2 ^ (h2 >>> 16), 2246822507) ^
        Math.imul(h1 ^ (h1 >>> 13), 3266489909);

    return 4294967296 * (2097151 & h2) + (h1 >>> 0);
}

type Rng = () => number;

/** mulberry32 : PRNG 32 bits, séquence reproductible. */
function mulberry32(a: number): Rng {
    let t = a >>> 0;

    return () => {
        t = (t + 0x6d2b79f5) | 0;
        let r = Math.imul(t ^ (t >>> 15), 1 | t);
        r = (r + Math.imul(r ^ (r >>> 7), 61 | r)) ^ r;
        return ((r ^ (r >>> 14)) >>> 0) / 4294967296;
    };
}

/** `ns` (namespace) évite que deux générateurs partagent la même séquence. */
function seeded(seed: string, ns: number): Rng {
    const h = cyrb53(seed || 'ifosup', ns);
    return mulberry32((h ^ Math.floor(h / 4294967296)) >>> 0);
}

/** Identifiant court et stable, pour les `id` des `<defs>`. */
function uid(seed: string, ns: number): string {
    return 'g' + (cyrb53(seed || 'ifosup', ns) % 1679616).toString(36);
}

function pick<T>(rng: Rng, list: readonly T[]): T {
    return list[Math.floor(rng() * list.length)] as T;
}

function range(rng: Rng, min: number, max: number): number {
    return min + rng() * (max - min);
}

function int(rng: Rng, min: number, max: number): number {
    return Math.floor(min + rng() * (max - min + 1));
}

function chance(rng: Rng, p: number): boolean {
    return rng() < p;
}

/** Nombre court pour les attributs SVG (évite « 12.340000000000002 »). */
function n(value: number): string {
    return value.toFixed(2).replace(/0+$/, '').replace(/\.$/, '');
}

/** Enveloppe le contenu dans un SVG 80×80 et encode la data URI (les `#` deviennent `%23`). */
function toDataUri(inner: string): string {
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 80 80" width="80" height="80">${inner}</svg>`;
    return `data:image/svg+xml;utf8,${encodeURIComponent(svg)}`;
}

/* ------------------------------------------------------------------ */
/* Palettes douces                                                     */
/* ------------------------------------------------------------------ */

interface Palette {
    /** Fond opaque, très clair. */
    bg: string;
    /** `[sombre, moyen, clair, très clair]` — toujours dans cet ordre. */
    tones: readonly [string, string, string, string];
    /** Couleur des traits (yeux, bouche). */
    ink: string;
}

/** 14 palettes sourdes : déclinaisons du branding (#1e2d55 / #f2ae35) et pastels. */
const PALETTES: readonly Palette[] = [
    {
        bg: '#eef1f8',
        tones: ['#1e2d55', '#5b6b96', '#9fb0d4', '#cdd8ec'],
        ink: '#1e2d55',
    }, // bleu nuit
    {
        bg: '#fbf3e4',
        tones: ['#8a6220', '#c98a1f', '#e8c07a', '#f4e0b8'],
        ink: '#5c421a',
    }, // ambre doux
    {
        bg: '#f6f1e7',
        tones: ['#5d523f', '#8b7c62', '#c4b49a', '#e2d8c6'],
        ink: '#4a4133',
    }, // sable
    {
        bg: '#f2effb',
        tones: ['#544874', '#7b6bab', '#b3a7d6', '#d8d0ec'],
        ink: '#3f3560',
    }, // lavande
    {
        bg: '#e9f5ef',
        tones: ['#37624f', '#4f8f75', '#96c4ae', '#c6e0d4'],
        ink: '#2c4f40',
    }, // menthe
    {
        bg: '#fdefe8',
        tones: ['#8f5a41', '#cf8a6c', '#eab79c', '#f6d9c8'],
        ink: '#6d4430',
    }, // pêche
    {
        bg: '#faeef1',
        tones: ['#8a566a', '#c07f92', '#e0b0bd', '#f0d6dd'],
        ink: '#6b4152',
    }, // rose poudré
    {
        bg: '#e9f2fa',
        tones: ['#3d6485', '#5a8cb8', '#9dc0dc', '#c9dcee'],
        ink: '#2f4f6b',
    }, // bleu ciel
    {
        bg: '#eceef2',
        tones: ['#2d3748', '#5a6779', '#9aa5b5', '#c8cfd9'],
        ink: '#232b3a',
    }, // graphite
    {
        bg: '#f3f2e6',
        tones: ['#5f6334', '#8a8f4f', '#b8bc86', '#dadcbc'],
        ink: '#4a4d29',
    }, // olive
    {
        bg: '#f2f0ea',
        tones: ['#1e2d55', '#c98a1f', '#a9b8d8', '#eed9ae'],
        ink: '#1e2d55',
    }, // duo branding
    {
        bg: '#f7eee9',
        tones: ['#7d4a37', '#b5715a', '#d8a58e', '#eecdbc'],
        ink: '#63392a',
    }, // terracotta
    {
        bg: '#eef3ec',
        tones: ['#4d6349', '#6f8a6b', '#a8c2a4', '#cfe0cc'],
        ink: '#3c4f39',
    }, // sauge
    {
        bg: '#eff2f4',
        tones: ['#3d545f', '#5b7684', '#9ab3bf', '#c8d8df'],
        ink: '#2f4149',
    }, // brume
];

/** Dégradés sourds mais assez profonds pour du texte blanc. */
const GRADIENTS: readonly (readonly [string, string])[] = [
    ['#1e2d55', '#44578c'],
    ['#334155', '#5b6b82'],
    ['#5f4b8b', '#8a76b5'],
    ['#4f6f5c', '#789a85'],
    ['#8a5a3c', '#b5836a'],
    ['#8a6220', '#c1912f'],
    ['#3d6485', '#6b93b3'],
    ['#7a4a5c', '#a97b8b'],
    ['#4a5568', '#7c8798'],
    ['#5c6b33', '#8a9a55'],
    ['#1e2d55', '#8a6220'],
    ['#42566b', '#7f95a5'],
    ['#6b4f7a', '#9a7da6'],
    ['#2f5d55', '#5f8d84'],
];

/** Teintes de « visage » neutres : aucune connotation de carnation. */
const FACE_TONES: readonly string[] = [
    '#f8f3ea',
    '#f4eee3',
    '#eef1f6',
    '#f7f1f4',
    '#eff3ee',
    '#f3f0f7',
    '#f1f4f5',
    '#faf6ee',
];

/* ================================================================== */
/* Personnages abstraits (enseignants + utilisateurs)                  */
/* ================================================================== */

/**
 * Sommet de la tête : uniquement des éléments franchement graphiques et détachés
 * du crâne (antenne, halo, plots…). Aucune forme n'enveloppe le visage, pour ne
 * jamais se lire comme une chevelure — donc aucune lecture genrée possible.
 */
function topShape(kind: number, color: string, accent: string): string {
    switch (kind) {
        case 0: // rien
            return '';
        case 1: // antenne
            return `<path d="M40 16 V11" stroke="${color}" stroke-width="2.4" stroke-linecap="round"/><circle cx="40" cy="8" r="3.5" fill="${accent}"/>`;
        case 2: // halo flottant
            return `<ellipse cx="40" cy="9" rx="14" ry="4" fill="none" stroke="${accent}" stroke-width="2.4"/>`;
        case 3: // boutons latéraux
            return `<circle cx="16" cy="36" r="4.5" fill="${color}"/><circle cx="64" cy="36" r="4.5" fill="${color}"/>`;
        case 4: // trois plots au-dessus
            return `<circle cx="30" cy="10" r="3.5" fill="${color}"/><circle cx="40" cy="7" r="4" fill="${accent}"/><circle cx="50" cy="10" r="3.5" fill="${color}"/>`;
        case 5: // arc flottant
            return `<path d="M24 13 Q40 4 56 13" fill="none" stroke="${color}" stroke-width="3.5" stroke-linecap="round"/>`;
        default: // losange flottant encadré de points
            return (
                `<path d="M40 3 L45 9 L40 15 L35 9 Z" fill="${accent}"/>` +
                `<circle cx="27" cy="12" r="2.4" fill="${color}"/><circle cx="53" cy="12" r="2.4" fill="${color}"/>`
            );
    }
}

/** Yeux abstraits (7 variantes) : points, anneaux, tirets, arcs, carrés. */
function eyesShape(kind: number, ink: string): string {
    switch (kind) {
        case 0:
            return `<circle cx="32" cy="38" r="3.2" fill="${ink}"/><circle cx="48" cy="38" r="3.2" fill="${ink}"/>`;
        case 1:
            return `<circle cx="32" cy="38" r="3.4" fill="none" stroke="${ink}" stroke-width="2"/><circle cx="48" cy="38" r="3.4" fill="none" stroke="${ink}" stroke-width="2"/>`;
        case 2: // tirets verticaux
            return `<path d="M32 34.5 v6 M48 34.5 v6" stroke="${ink}" stroke-width="2.6" stroke-linecap="round"/>`;
        case 3: // tirets horizontaux
            return `<path d="M28.5 38 h7 M44.5 38 h7" stroke="${ink}" stroke-width="2.6" stroke-linecap="round"/>`;
        case 4: // arcs vers le haut
            return `<path d="M28 40 Q32 34.5 36 40 M44 40 Q48 34.5 52 40" fill="none" stroke="${ink}" stroke-width="2.4" stroke-linecap="round"/>`;
        case 5: // arcs vers le bas
            return `<path d="M28 36 Q32 41.5 36 36 M44 36 Q48 41.5 52 36" fill="none" stroke="${ink}" stroke-width="2.4" stroke-linecap="round"/>`;
        default: // carrés arrondis
            return `<rect x="29" y="35" width="6" height="6" rx="1.6" fill="${ink}"/><rect x="45" y="35" width="6" height="6" rx="1.6" fill="${ink}"/>`;
    }
}

/** Bouche neutre (5 variantes) : trait, arc discret, point, ou rien. */
function mouthShape(kind: number, ink: string): string {
    switch (kind) {
        case 0:
            return `<path d="M35 50 h10" stroke="${ink}" stroke-width="2.4" stroke-linecap="round"/>`;
        case 1:
            return `<path d="M34 49 Q40 53.5 46 49" fill="none" stroke="${ink}" stroke-width="2.4" stroke-linecap="round"/>`;
        case 2:
            return `<circle cx="40" cy="50" r="2.6" fill="${ink}"/>`;
        case 3:
            return `<rect x="35" y="48.5" width="10" height="4" rx="2" fill="${ink}" opacity="0.85"/>`;
        default:
            return '';
    }
}

/** Décor de fond abstrait (5 variantes), en retrait derrière le personnage. */
function backdropShape(kind: number, color: string): string {
    switch (kind) {
        case 0:
            return '';
        case 1: // grand disque décentré (jamais concentrique au visage)
            return `<circle cx="68" cy="14" r="26" fill="${color}" opacity="0.7"/>`;
        case 2:
            return `<g fill="${color}" opacity="0.75"><circle cx="11" cy="15" r="5"/><circle cx="69" cy="23" r="6.5"/><circle cx="15" cy="63" r="3.5"/></g>`;
        case 3:
            return `<path d="M0 54 L80 36 L80 80 L0 80 Z" fill="${color}" opacity="0.65"/>`;
        default: // anneau décalé en coin
            return `<circle cx="13" cy="17" r="17" fill="none" stroke="${color}" stroke-width="4.5" opacity="0.75"/>`;
    }
}

/**
 * Fabrique commune aux enseignants et aux utilisateurs : tête géométrique,
 * traits abstraits, décor neutre. `ns` change la séquence, `offset` décale la
 * palette pour donner deux ambiances de la même famille visuelle.
 */
function buildCharacter(
    seed: string,
    ns: number,
    paletteOffset: number,
    frame: boolean,
): string {
    const r = seeded(seed, ns);
    const palette = PALETTES[
        (Math.floor(r() * PALETTES.length) + paletteOffset) % PALETTES.length
    ] as Palette;
    const [dark, mid, light, pale] = palette.tones;

    const face = chance(r, 0.55) ? pick(r, FACE_TONES) : pale;
    const outline = chance(r, 0.5) ? dark : mid;
    const body = pick(r, [dark, mid] as const);
    const accent = pick(r, [dark, mid, light] as const);

    const headKind = int(r, 0, 4);
    const topKind = int(r, 0, 6);
    const eyeKind = int(r, 0, 6);
    const mouthKind = int(r, 0, 4);
    const accKind = int(r, 0, 3);
    // Décor de fond pondéré : le « rien » reste possible mais rare.
    const backKind = pick(r, [0, 1, 1, 2, 2, 3, 3, 4, 4] as const);
    const tilt = range(r, -8, 8);
    const shift = range(r, -2.5, 2.5);
    const scale = range(r, 0.9, 1.08);

    const stroke = `fill="${face}" stroke="${outline}" stroke-width="2.2"`;
    const heads = [
        `<circle cx="40" cy="38" r="21" ${stroke}/>`,
        `<rect x="19" y="17" width="42" height="42" rx="14" ${stroke}/>`,
        `<ellipse cx="40" cy="38" rx="19" ry="22" ${stroke}/>`,
        `<ellipse cx="40" cy="38" rx="22" ry="19" ${stroke}/>`,
        `<path d="M40 16 L58 26 L58 50 L40 60 L22 50 L22 26 Z" ${stroke} stroke-linejoin="round"/>`,
    ];

    const accessories = [
        '',
        // lunettes
        `<g fill="none" stroke="${palette.ink}" stroke-width="1.8" opacity="0.8"><circle cx="32" cy="38" r="7.5"/><circle cx="48" cy="38" r="7.5"/><path d="M39.5 38 h1"/></g>`,
        // formes flottantes
        `<g fill="${accent}" opacity="0.75"><rect x="8" y="44" width="7" height="7" rx="2" transform="rotate(18 11.5 47.5)"/><circle cx="70" cy="52" r="3.5"/></g>`,
        // pastilles neutres sur les joues
        `<g fill="${accent}" opacity="0.4"><circle cx="26" cy="46" r="3.2"/><circle cx="54" cy="46" r="3.2"/></g>`,
    ];

    const bust = `<path d="M13 80 Q13 60 40 60 Q67 60 67 80 Z" fill="${body}"/><path d="M34 60 Q40 68 46 60 Z" fill="${face}" opacity="0.8"/>`;

    const head =
        `<g transform="translate(${n(shift)} 0) rotate(${n(tilt)} 40 38) translate(40 38) scale(${n(scale)}) translate(-40 -38)">` +
        heads[headKind] +
        topShape(topKind, accent, light) +
        eyesShape(eyeKind, palette.ink) +
        mouthShape(mouthKind, palette.ink) +
        accessories[accKind] +
        `</g>`;

    const border = frame
        ? `<rect x="2" y="2" width="76" height="76" rx="18" fill="none" stroke="${dark}" stroke-width="2.5" opacity="0.35"/>`
        : '';

    return toDataUri(
        `<rect width="80" height="80" fill="${palette.bg}"/>${backdropShape(backKind, light)}${bust}${head}${border}`,
    );
}

/** Enseignants : personnage abstrait, sans cadre. */
export function teacherAvatar(seed: string): string {
    return buildCharacter(seed, 101, 0, false);
}

/** Utilisateurs : même famille de personnages, palette décalée et liseré. */
export function userAvatar(seed: string): string {
    return buildCharacter(seed, 505, 7, true);
}

/* ================================================================== */
/* Cours — initiales stylisées                                         */
/* ================================================================== */

/**
 * Libellé affiché : on privilégie le code complet pour que « 4CCBU » et « 4CCEN »
 * se distinguent au premier coup d'œil. Une ou deux lignes selon la forme du code.
 */
function labelOf(seed: string): readonly string[] {
    const clean = seed
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '')
        .toUpperCase();

    const words = clean.split(/[^A-Z0-9]+/).filter((w) => w.length > 0);

    if (words.length === 0) {
        return ['??'];
    }

    const first = words[0] as string;

    // Un seul bloc : « 4CCBU », « A1 », « 104 » tels quels ; sinon préfixe + dernière lettre.
    if (words.length === 1) {
        return first.length <= 6
            ? [first]
            : [first.slice(0, 4) + first.charAt(first.length - 1)];
    }

    const second = words[1] as string;
    const joined = words.join('');

    // « ANG-UE1 » ⇒ deux lignes « ANG » / « UE1 ».
    if (joined.length <= 6) {
        return words.length === 2 &&
            joined.length >= 5 &&
            first.length <= 4 &&
            second.length <= 4
            ? [first, second]
            : [joined];
    }

    // Libellés longs : deux lignes tronquées (« OENO-MONDE » ⇒ « OENO » / « MON »).
    return [
        first.slice(0, 4),
        second.length <= 3 ? second : second.slice(0, 3),
    ];
}

/** Corps de police par longueur de ligne, pour que le texte tienne dans les 80 px. */
function fontSizeFor(length: number, twoLines: boolean): number {
    if (twoLines) {
        return length <= 3 ? 21 : 17;
    }

    switch (length) {
        case 1:
            return 40;
        case 2:
            return 33;
        case 3:
            return 27;
        case 4:
            return 23;
        case 5:
            return 19;
        default:
            return 16;
    }
}

/** Largeur cible : uniformise le rendu et garantit que les codes longs ne débordent pas. */
function textLengthFor(length: number, twoLines: boolean): string {
    if (length < 4) {
        return '';
    }

    const target = twoLines ? 54 : Math.min(50 + (length - 4) * 6, 64);

    return ` textLength="${target}" lengthAdjust="spacingAndGlyphs"`;
}

/** Motif décoratif blanc translucide (7 variantes), appliqué sur tout le fond. */
function coursePattern(kind: number, id: string, rot: number): string {
    const tile = (content: string, size: number): string =>
        `<defs><pattern id="${id}" width="${size}" height="${size}" patternUnits="userSpaceOnUse" patternTransform="rotate(${n(rot)})">${content}</pattern></defs>` +
        `<rect width="80" height="80" fill="url(#${id})"/>`;

    switch (kind) {
        case 0:
            return '';
        case 1: // pois
            return tile(
                '<circle cx="5" cy="5" r="2" fill="#ffffff" opacity="0.13"/>',
                10,
            );
        case 2: // rayures
            return tile(
                '<rect width="6" height="12" fill="#ffffff" opacity="0.09"/>',
                12,
            );
        case 3: // anneaux
            return tile(
                '<circle cx="8" cy="8" r="5.5" fill="none" stroke="#ffffff" stroke-width="1.6" opacity="0.13"/>',
                16,
            );
        case 4: // grille
            return tile(
                '<path d="M0 0 H12 M0 0 V12" stroke="#ffffff" stroke-width="1.2" opacity="0.11" fill="none"/>',
                12,
            );
        case 5: // chevrons
            return tile(
                '<path d="M0 10 L7 3 L14 10" fill="none" stroke="#ffffff" stroke-width="1.6" opacity="0.12"/>',
                14,
            );
        default: // confettis
            return tile(
                '<rect x="2" y="2" width="4" height="4" rx="1" fill="#ffffff" opacity="0.12"/><circle cx="12" cy="12" r="1.8" fill="#ffffff" opacity="0.1"/>',
                16,
            );
    }
}

export function courseAvatar(seed: string): string {
    const r = seeded(seed, 202);
    const id = uid(seed, 202);
    const [from, to] = pick(r, GRADIENTS);
    const angle = pick(r, [0, 45, 90, 135] as const);
    const patternKind = int(r, 0, 6);
    const patternRot = pick(r, [0, 30, 45, 60, 90] as const);
    const backdrop = int(r, 0, 2);
    const withCorner = chance(r, 0.5);

    const rad = (angle * Math.PI) / 180;
    const x2 = n(0.5 + Math.cos(rad) / 2);
    const y2 = n(0.5 + Math.sin(rad) / 2);
    const x1 = n(0.5 - Math.cos(rad) / 2);
    const y1 = n(0.5 - Math.sin(rad) / 2);

    const backdrops = [
        '',
        `<circle cx="40" cy="40" r="26" fill="#ffffff" opacity="0.1"/>`,
        `<path d="M0 56 L80 26 L80 54 L0 80 Z" fill="#ffffff" opacity="0.08"/>`,
    ];

    const corner = withCorner
        ? `<path d="M80 0 L80 22 L58 0 Z" fill="#ffffff" opacity="0.13"/>`
        : '';

    const lines = labelOf(seed);
    const twoLines = lines.length > 1;
    const longest = Math.max(...lines.map((l) => l.length));
    const size = fontSizeFor(longest, twoLines);
    const font =
        `font-size="${size}" font-weight="600" letter-spacing="${longest >= 5 ? 0 : 1}" ` +
        `font-family="system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif"`;
    const fit = textLengthFor(longest, twoLines);

    const label = twoLines
        ? lines
              .map(
                  (line, i) =>
                      `<text x="40" y="${i === 0 ? 30 : 52}" dy="0.35em" text-anchor="middle" fill="#ffffff" ${font}${line.length >= 4 ? fit : ''}>${line}</text>`,
              )
              .join('')
        : `<text x="40" y="40" dy="0.35em" text-anchor="middle" fill="#ffffff" ${font}${fit}>${lines[0] as string}</text>`;

    return toDataUri(
        `<defs><linearGradient id="${id}b" x1="${x1}" y1="${y1}" x2="${x2}" y2="${y2}">` +
            `<stop offset="0" stop-color="${from}"/><stop offset="1" stop-color="${to}"/></linearGradient></defs>` +
            `<rect width="80" height="80" fill="url(#${id}b)"/>` +
            coursePattern(patternKind, `${id}p`, patternRot) +
            backdrops[backdrop] +
            corner +
            label,
    );
}

/* ================================================================== */
/* Sections — identicon symétrique                                     */
/* ================================================================== */

export function groupAvatar(seed: string): string {
    const r = seeded(seed, 303);
    const id = uid(seed, 303);
    const palette = pick(r, PALETTES);
    const [dark, mid, , pale] = palette.tones;

    const grid = chance(r, 0.5) ? 5 : 6;
    const half = Math.ceil(grid / 2);
    const cellKind = int(r, 0, 3);
    const density = range(r, 0.42, 0.6);
    const inset = range(r, 8, 12);
    const withRing = chance(r, 0.5);

    const usable = 80 - inset * 2;
    const cell = usable / grid;
    const gap = cell * pick(r, [0, 0.08, 0.16] as const);
    const fill = `url(#${id}g)`;

    let cells = '';
    let filled = 0;

    for (let col = 0; col < half; col++) {
        for (let row = 0; row < grid; row++) {
            if (!chance(r, density)) {
                continue;
            }

            filled++;

            // Colonne centrale d'une grille impaire : pas de miroir.
            for (const c of col === grid - 1 - col
                ? [col]
                : [col, grid - 1 - col]) {
                const x = inset + c * cell + gap / 2;
                const y = inset + row * cell + gap / 2;
                const s = cell - gap;
                const cx = x + s / 2;
                const cy = y + s / 2;

                switch (cellKind) {
                    case 0:
                        cells += `<rect x="${n(x)}" y="${n(y)}" width="${n(s)}" height="${n(s)}" fill="${fill}"/>`;
                        break;
                    case 1:
                        cells += `<rect x="${n(x)}" y="${n(y)}" width="${n(s)}" height="${n(s)}" rx="${n(s * 0.3)}" fill="${fill}"/>`;
                        break;
                    case 2:
                        cells += `<circle cx="${n(cx)}" cy="${n(cy)}" r="${n(s / 2)}" fill="${fill}"/>`;
                        break;
                    default:
                        cells +=
                            `<path d="M${n(cx)} ${n(cy - s / 2)} L${n(cx + s / 2)} ${n(cy)} L${n(cx)} ${n(cy + s / 2)} L${n(cx - s / 2)} ${n(cy)} Z" ` +
                            `fill="${fill}"/>`;
                        break;
                }
            }
        }
    }

    // Garde-fou : une grille vide donnerait un avatar muet.
    if (filled === 0) {
        cells = `<circle cx="40" cy="40" r="${n(cell)}" fill="${fill}"/>`;
    }

    const ring = withRing
        ? `<circle cx="40" cy="40" r="36" fill="none" stroke="${mid}" stroke-width="2" opacity="0.3"/>`
        : '';

    return toDataUri(
        `<defs><linearGradient id="${id}g" x1="0" y1="0" x2="1" y2="1">` +
            `<stop offset="0" stop-color="${dark}"/><stop offset="1" stop-color="${mid}"/></linearGradient></defs>` +
            `<rect width="80" height="80" fill="${palette.bg}"/>` +
            `<rect width="80" height="80" fill="${pale}" opacity="0.5"/>` +
            ring +
            cells,
    );
}

/* ================================================================== */
/* Locaux — compositions de formes géométriques                        */
/* ================================================================== */

/** Une forme franche parmi 10, centrée en (cx, cy), demi-taille `s`. */
function geoShape(
    kind: number,
    cx: number,
    cy: number,
    s: number,
    fill: string,
    rot: number,
): string {
    const t = `transform="rotate(${n(rot)} ${n(cx)} ${n(cy)})"`;

    switch (kind) {
        case 0:
            return `<circle cx="${n(cx)}" cy="${n(cy)}" r="${n(s)}" fill="${fill}"/>`;
        case 1:
            return `<rect x="${n(cx - s)}" y="${n(cy - s)}" width="${n(s * 2)}" height="${n(s * 2)}" fill="${fill}" ${t}/>`;
        case 2:
            return `<path d="M${n(cx)} ${n(cy - s)} L${n(cx + s)} ${n(cy + s)} L${n(cx - s)} ${n(cy + s)} Z" fill="${fill}" ${t}/>`;
        case 3:
            return `<path d="M${n(cx)} ${n(cy - s)} L${n(cx + s)} ${n(cy)} L${n(cx)} ${n(cy + s)} L${n(cx - s)} ${n(cy)} Z" fill="${fill}" ${t}/>`;
        case 4: // demi-disque
            return `<path d="M${n(cx - s)} ${n(cy)} A${n(s)} ${n(s)} 0 0 1 ${n(cx + s)} ${n(cy)} Z" fill="${fill}" ${t}/>`;
        case 5: // quart de disque
            return `<path d="M${n(cx - s)} ${n(cy + s)} L${n(cx - s)} ${n(cy - s)} A${n(s * 2)} ${n(s * 2)} 0 0 1 ${n(cx + s)} ${n(cy + s)} Z" fill="${fill}" ${t}/>`;
        case 6: // anneau
            return `<circle cx="${n(cx)}" cy="${n(cy)}" r="${n(s * 0.72)}" fill="none" stroke="${fill}" stroke-width="${n(s * 0.55)}"/>`;
        case 7: // rectangle arrondi
            return `<rect x="${n(cx - s)}" y="${n(cy - s * 0.62)}" width="${n(s * 2)}" height="${n(s * 1.24)}" rx="${n(s * 0.45)}" fill="${fill}" ${t}/>`;
        case 8: // croix
            return (
                `<g ${t}><rect x="${n(cx - s)}" y="${n(cy - s * 0.32)}" width="${n(s * 2)}" height="${n(s * 0.64)}" fill="${fill}"/>` +
                `<rect x="${n(cx - s * 0.32)}" y="${n(cy - s)}" width="${n(s * 0.64)}" height="${n(s * 2)}" fill="${fill}"/></g>`
            );
        default: // gélule
            return `<rect x="${n(cx - s)}" y="${n(cy - s * 0.45)}" width="${n(s * 2)}" height="${n(s * 0.9)}" rx="${n(s * 0.45)}" fill="${fill}" ${t}/>`;
    }
}

export function roomAvatar(seed: string): string {
    const r = seeded(seed, 404);
    const palette = pick(r, PALETTES);
    const [dark, mid, light, pale] = palette.tones;

    // Deux ambiances : fond clair / formes sourdes, ou fond sourd / formes claires.
    const inverted = chance(r, 0.4);
    const bg = inverted ? dark : palette.bg;
    const pool: readonly string[] = inverted
        ? [pale, light, mid]
        : [dark, mid, pale];

    const sizes = [range(r, 24, 31), range(r, 15, 21), range(r, 8, 13)];
    let layers = '';
    let previous = '';

    for (let i = 0; i < 3; i++) {
        const kind = int(r, 0, 9);
        // La grande forme prend une couleur contrastée ; on évite deux couches identiques.
        const choices = i === 0 ? pool.slice(0, 2) : pool;
        let index = int(r, 0, choices.length - 1);
        if (choices[index] === previous) {
            index = (index + 1) % choices.length;
        }
        const fill = choices[index] as string;
        previous = fill;
        const s = sizes[i] as number;
        const cx = range(r, 22, 58);
        const cy = range(r, 22, 58);
        const rot = int(r, 0, 11) * 30;

        layers += geoShape(kind, cx, cy, s, fill, rot);
    }

    // Liseré discret pour asseoir la composition.
    const frame = chance(r, 0.4)
        ? `<rect x="3" y="3" width="74" height="74" rx="10" fill="none" stroke="${inverted ? light : mid}" stroke-width="2" opacity="0.45"/>`
        : '';

    return toDataUri(
        `<rect width="80" height="80" fill="${bg}"/>${layers}${frame}`,
    );
}
