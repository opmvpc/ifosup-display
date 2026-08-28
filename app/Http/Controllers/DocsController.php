<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class DocsController extends Controller
{
    /**
     * Chapitres publiés : slug d'URL => composant Inertia.
     *
     * @var array<string, string>
     */
    private const PAGES = [
        'introduction' => 'docs/Introduction',
        'ressources' => 'docs/Ressources',
        'planning' => 'docs/Planning',
        'import-excel' => 'docs/ImportExcel',
        'slides' => 'docs/Slides',
        'ecran-tv' => 'docs/EcranTv',
        'utilisateurs' => 'docs/Utilisateurs',
    ];

    /**
     * Display the requested documentation chapter.
     */
    public function show(string $page = 'introduction'): Response
    {
        abort_unless(array_key_exists($page, self::PAGES), 404);

        return Inertia::render(self::PAGES[$page]);
    }
}
