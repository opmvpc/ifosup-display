---
id: IFO-019
titre: Documentation intégrée à l'application (utilisateurs non informaticiens)
statut: en-cours
priorité: normale
dépend-de: []
créé: 2026-08-25
mis-à-jour: 2026-08-25
---

## Contexte

Demande de Thibault (2026-08-24) : une documentation accessible aux personnes
connectées, expliquant les menus et fonctionnalités pour un public non
informaticien. Exigences :

- navigation type « documentation moderne » : sidebar de chapitres à gauche,
  « Sur cette page » à droite ;
- captures d'écran de l'application ;
- diagrammes / infographies (délégués à un agent Opus) pour synthétiser
  les explications textuelles.

## Critères d'acceptation

- [x] Route `/docs/{page?}` derrière `auth`, lien « Documentation » dans le
      pied de la sidebar
- [x] Sept chapitres : Bien démarrer, Ressources, Planning, Import Excel,
      Slides, Télévision, Utilisateurs
- [x] Sidebar gauche (chapitres) + « Sur cette page » à droite (ancres h2,
      surlignage au scroll via IntersectionObserver), précédent/suivant
- [x] Langage non technique, français ; avertissements appuyés sur les
      opérations destructives (suppressions sans corbeille, remplacements en
      masse, purge d'import)
- [ ] Captures d'écran des écrans principaux — **différé** : la capture exige
      la pane navigateur affichée côté client ; le composant `DocsImage` est
      prêt à les accueillir
- [x] Trois infographies SVG (déléguées à un agent Opus, relues — une durée
      corrigée de 10 s à 5 s) : flux planning→TV, boucle des slides, import
- [x] 10 tests de fumée des routes docs ; suite complète **303 tests verts**,
      types/lint/format OK

## Journal du ticket

- 2026-08-25 — création, implémentation sur `feat/retours-directrice`.
  Recherche des fonctionnalités déléguée à un agent Explore
  (`docs/research/ifo-019-fonctionnalites-ui.md`), diagrammes à un agent
  Opus. Rédaction, layout et intégration par l'orchestrateur. Vérifié en
  réel dans la stack dev (chapitres, TOC, diagrammes rendus). Restent les
  captures d'écran.
