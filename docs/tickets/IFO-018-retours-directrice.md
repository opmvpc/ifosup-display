---
id: IFO-018
titre: Retours de la directrice (tri profs, année de cours, ratio des médias)
statut: en-cours
priorité: haute
dépend-de: []
créé: 2026-08-24
mis-à-jour: 2026-08-24
---

## Contexte

Première recette utilisateur par la directrice (2026-08-24). Quatre retours :

1. La liste des enseignants (`/teachers`) n'est pas triée alphabétiquement
   (`Teacher::all()`, ordre d'insertion).
2. Le select « Enseignant » des formulaires de cours souffre du même défaut.
3. Il manque la notion d'année (1ère, 2e, 3e) sur un cours, à afficher aussi
   dans le planning de l'écran TV.
4. Les images et vidéos des slides sont étirées (`object-cover`) au lieu de
   conserver leur ratio, tant sur l'écran TV que sur les aperçus de la page
   `/screen/slides`. Fond demandé : le bleu foncé du branding (`#1e2d55`).

Extensions demandées en cours de route par Thibault :

5. Trier aussi les sections et les cours (par nom) dans les index et les
   selects ; vérifier le tri naturel des locaux (nommés « 004 », « 104 »,
   « - 103 » pour les sous-sols).
6. Un seeder manuel pour remplir le catalogue de cours IFOSUP (œnologie
   regroupée en une section, cours déjà créés à la main exclus).

## Critères d'acceptation

- [x] `/teachers` liste les enseignants par ordre alphabétique
- [x] Les selects « Enseignant » (création/édition de cours) sont triés
- [x] Un cours peut porter une année (1, 2, 3) facultative, validée côté serveur
- [x] L'année apparaît dans le formulaire, la fiche cours et le planning TV
- [x] Slides image/vidéo de l'écran TV en `object-contain` sur fond `#1e2d55`
- [x] Aperçus de `/screen/slides` idem
- [x] Sections et cours triés par nom (index + selects), locaux en tri naturel
      y compris « - 103 » (bug `parseInt` corrigé, helper partagé `lib/rooms.ts`)
- [x] `CourseCatalogSeeder` idempotent, testé (4 tests), 219 cours créés en dev
- [x] Tests verts (`php artisan test` : 293 passés), Pint/ESLint/Prettier/vue-tsc OK

## Journal du ticket

- 2026-08-24 — création sur base du feedback de la directrice transmis par
  Thibault, implémentation sur la branche `feat/retours-directrice`.
- 2026-08-24 — périmètre étendu (tris sections/cours/locaux, seeder catalogue).
  Quatre commits sur la branche, suite complète verte dans le container dev,
  seeder vérifié en réel (20 sections, 219 cours). La sélection d'une option
  de Combobox au travers du navigateur piloté n'a pas pu être rejouée de bout
  en bout (pane non affichée → pas de clics fiables), mais le champ caché
  `year` et le POST sont couverts par les feature tests.
