---
id: IFO-019
titre: Documentation intégrée à l'application (utilisateurs non informaticiens)
statut: terminé
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
- [x] Captures d'écran des écrans principaux — prises via Edge headless
      (puppeteer-core) sur la stack dev avec un jeu de données de démo,
      publiées dans `public/docs-img/`
- [x] Structure du fichier d'import Excel documentée (tableau d'exemple) +
      modèle `modele-import-planning.xlsx` téléchargeable, généré avec
      PhpSpreadsheet et contre-vérifié par `SchedulerSheetParser`
- [x] Trois infographies SVG (déléguées à un agent Opus, relues — une durée
      corrigée de 10 s à 5 s) : flux planning→TV, boucle des slides, import
- [x] 11 tests de fumée (routes + présence du modèle) ; suite complète
      **304 tests verts**, types/lint/format OK

## Journal du ticket

- 2026-08-25 — création, implémentation sur `feat/retours-directrice`.
  Recherche des fonctionnalités déléguée à un agent Explore
  (`docs/research/ifo-019-fonctionnalites-ui.md`), diagrammes à un agent
  Opus. Rédaction, layout et intégration par l'orchestrateur. Vérifié en
  réel dans la stack dev (chapitres, TOC, diagrammes rendus).
- 2026-08-25 — captures d'écran prises (Edge headless + données de démo),
  section « structure du fichier » de l'import documentée d'après le code du
  parser, modèle xlsx généré et vérifié (31 attributions parsées). Piège
  d'environnement noté : Vite dans le container ne reçoit pas les événements
  de fichiers du montage Windows — redémarrer le service `vite` après avoir
  créé de nouveaux fichiers front. Ticket terminé.
