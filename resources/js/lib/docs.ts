export interface DocsChapter {
    slug: string;
    title: string;
    href: string;
}

// L'ordre définit la navigation (sidebar et boutons précédent/suivant).
// Doit rester aligné avec DocsController::PAGES.
export const docsChapters: DocsChapter[] = [
    { slug: 'introduction', title: 'Bien démarrer', href: '/docs' },
    { slug: 'ressources', title: 'Les ressources', href: '/docs/ressources' },
    { slug: 'planning', title: 'Le planning', href: '/docs/planning' },
    {
        slug: 'import-excel',
        title: "L'import Excel",
        href: '/docs/import-excel',
    },
    { slug: 'slides', title: "Les slides de l'écran", href: '/docs/slides' },
    { slug: 'ecran-tv', title: 'La télévision', href: '/docs/ecran-tv' },
    {
        slug: 'utilisateurs',
        title: 'Les utilisateurs',
        href: '/docs/utilisateurs',
    },
];
