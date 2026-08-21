---
id: ADR-002
titre: Deux stacks Docker distinctes — prod-like et développement
statut: accepté
date: 2026-08-18
---

## Contexte

Deux besoins qui ne se satisfont pas avec le même montage : valider avant Coolify
que l'image de production démarre réellement, et pouvoir reprendre le code de
l'étudiant confortablement (hot-reload).

## Décision

- `docker-compose.yml` + `Dockerfile` : image de production, assets Vite compilés,
  aucun bind mount du code. C'est l'image que Coolify construira. Port 8080.
- `docker-compose.dev.yml` + `Dockerfile.dev` : code monté en volume,
  `php artisan serve` sur 8000, serveur Vite sur 5173, Xdebug disponible.

## Alternatives écartées

- **Un seul compose avec un override dev** : plus compact, mais brouille justement
  la propriété recherchée — « ce que je teste est exactement ce qui sera déployé ».
- **Laravel Sail** : impose sa propre image et son `.env`, et s'éloigne de
  l'image FrankenPHP utilisée en production.

## Conséquences

- Deux images à construire, deux volumes MySQL séparés (ports hôte 33061 et 33062).
- Le `Dockerfile` de production doit rester autonome (Coolify ne lit pas le compose).
