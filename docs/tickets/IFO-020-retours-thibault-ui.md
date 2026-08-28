---
id: IFO-020
titre: Retours UI de Thibault (avatars locaux, sticky doc, tri, drag & drop slides)
statut: terminé
priorité: normale
dépend-de: []
créé: 2026-08-25
mis-à-jour: 2026-08-25
---

## Contexte

Retours de Thibault après revue d'IFO-018/019 (2026-08-25) :

1. Les avatars des ressources passaient par l'API dicebear (rate-limitée en
   local, dépendance réseau inutile) → générer en front, à la volée.
2. Les sidebars de la doc n'étaient pas sticky.
3. Revenir au tri des cours par code (le tri par nom testé en IFO-018 ne
   convient pas).
4. Le lien « Documentation » ouvrait un nouvel onglet.
5. Réordonnancement des slides par glisser-déposer (boutons conservés).

## Critères d'acceptation

- [x] `resources/js/lib/avatars.ts` : cinq générateurs SVG déterministes
      (data URI, hash + PRNG seedé, zéro réseau), délégués à un agent Opus en
      trois itérations : personnages géométriques **non genrés** partagés
      enseignants/utilisateurs, code complet lisible pour les cours,
      identicon symétrique pour les sections, formes pour les locaux,
      palettes sourdes branding/pastel. 0 collision sur 3000 graines/style.
- [x] Toutes les pages (9) basculées, plus aucune référence dicebear.
- [x] Sticky réparé : `overflow-x-hidden` → `overflow-x-clip` sur AppContent
      (un ancêtre overflow≠visible neutralisait les position:sticky).
- [x] Cours triés par code (liste, bibliothèque du planning), tests remis.
- [x] « Documentation » navigue en Link Inertia ; seul « Écran TV » garde
      `target="_blank"` (drapeau `external` sur NavItem).
- [x] Drag & drop des slides (agent Opus) : poignée au survol, armement au
      pointerdown, barre d'insertion, refus visuel devant Bienvenue,
      optimiste + rollback, FLIP ; flèches conservées ; doc mise à jour.
      Vérifié en réel : drag synthétique → PATCH 200, ordre changé en base,
      welcome resté en tête.
- [x] Suite 304 tests verts, Pint/ESLint/Prettier/vue-tsc OK.

## Journal du ticket

- 2026-08-25 — création et livraison complète sur `feat/retours-directrice`
  (4 commits). Contrôles visuels par captures Edge headless sur la stack dev.
