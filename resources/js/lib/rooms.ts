// Tri naturel des locaux : sous-sols (noms négatifs, ex. « - 103 ») d'abord,
// puis les étages par numéro croissant, puis les noms non numériques.
export function compareRoomNames(a: string, b: string): number {
    // Les sous-sols sont nommés avec une espace après le tiret (« - 103 ») :
    // on retire les espaces avant de tester le numérique.
    const ca = a.replace(/\s+/g, '');
    const cb = b.replace(/\s+/g, '');
    const isInt = (s: string) => /^-?\d+$/.test(s);
    const na = parseInt(ca, 10),
        nb = parseInt(cb, 10);
    const group = (s: string, n: number) => (!isInt(s) ? 2 : n < 0 ? 0 : 1);
    const ga = group(ca, na),
        gb = group(cb, nb);
    if (ga !== gb) return ga - gb;
    if (ga === 0) return Math.abs(na) - Math.abs(nb);
    if (ga === 1) return na - nb;
    return a.localeCompare(b);
}
