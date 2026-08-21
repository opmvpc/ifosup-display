---
id: IFO-005
titre: Persistance des fichiers uploadés entre deux déploiements
statut: terminé
priorité: normale
dépend-de: [IFO-002]
créé: 2026-08-18
mis-à-jour: 2026-08-18
---

## Contexte

Les imports de planning (`storage/app/private/*.xlsx`) et les médias des slides
sont écrits dans le système de fichiers du container. Sans volume persistant,
chaque redéploiement Coolify reconstruit l'image et efface ces fichiers.

## Critères d'acceptation

- [x] Volumes montés sur `/app/storage/app` et `/app/storage/logs` — en local dans
      `docker-compose.yml`, et documentés pour Coolify dans [`../deploiement-coolify.md`](../deploiement-coolify.md)
- [x] Un fichier importé survit à un redéploiement (vérifié : fichiers déposés dans
      `storage/app/private` et `storage/app/public`, puis `docker compose up --force-recreate app`,
      les deux sont toujours là)
- [x] Le lien `public/storage` est recréé au démarrage et pointe bien sur
      `/app/storage/app/public` après recréation du container

## Journal du ticket

- 2026-08-18 — création.
- 2026-08-18 — réglé côté local et documenté côté Coolify. Reste à monter
  effectivement les deux volumes dans l'interface Coolify au moment du déploiement
  ([[IFO-002-deploiement-coolify]]).
- 2026-08-20 — persistance rendue déclarative. `docker-compose.coolify.yml` versionné :
  les deux volumes vivent désormais dans le dépôt plutôt que dans un réglage d'interface
  qu'on oublie en recréant l'application. Le fichier refuse aussi de démarrer si une
  variable critique manque (`APP_KEY`, bloc `DB_*`).
- 2026-08-20 — piège écarté et vérifié : déclarer ces chemins avec l'instruction
  `VOLUME` du `Dockerfile` produit un volume **anonyme**, recréé vide à chaque
  conteneur. Test à l'appui — un fichier écrit au premier démarrage contient toujours
  une seule ligne au second, au lieu de deux — et les volumes orphelins s'accumulent.
  C'est l'inverse de l'effet recherché.

