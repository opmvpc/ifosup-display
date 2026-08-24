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

- [ ] Route(s) `/docs` derrière `auth`, lien dans la sidebar de l'application
- [ ] Un chapitre par grande fonctionnalité (ressources, planning, import
      Excel, slides écran, écran TV, utilisateurs)
- [ ] Sidebar gauche (chapitres) + « Sur cette page » à droite (ancres)
- [ ] Langage non technique, français
- [ ] Captures d'écran des écrans principaux
- [ ] Diagrammes/infographies pour les flux clés (ex. du planning à la TV)
- [ ] Tests de fumée des routes docs, lint et types au vert

## Journal du ticket

- 2026-08-25 — création, conception en cours sur `feat/retours-directrice`
  (ou branche dédiée selon l'ampleur).
