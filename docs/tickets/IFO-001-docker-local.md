---
id: IFO-001
titre: Environnement Docker local (prod-like + dev)
statut: terminé
priorité: haute
dépend-de: []
créé: 2026-08-18
mis-à-jour: 2026-08-18
---

## Contexte

Le repo contenait un `Dockerfile` mono-usage pensé pour Railpack/Coolify, sans
compose, sans base de données et sans `.dockerignore`. Objectif : pouvoir lancer
le projet en local en une commande, sur l'image exacte qui partira en production
(validation du déploiement avant Coolify), et disposer d'une stack de dev
confortable pour reprendre le code.

Décisions associées : [[ADR-001-mysql-comme-base]].

## Critères d'acceptation

- [x] `.dockerignore` présent (pas de `vendor`/`node_modules`/`.env` dans le contexte)
- [x] `Dockerfile` build reproductible : `composer.lock` + `pnpm-lock.yaml` respectés
- [x] `docker-compose.yml` prod-like : app + MySQL 8.4 + volumes + healthcheck
- [x] `docker-compose.dev.yml` : code monté, Vite en hot-reload
- [x] Entrypoint : attente de la base, migrations, création de l'admin, `optimize`
- [x] `docker compose up -d --build` démarre sans erreur
- [x] `/` et `/screen` répondent 200
- [x] Connexion admin fonctionnelle sur `/login`
- [x] Un CRUD (salles ou enseignants) crée bien un enregistrement en base

## Journal du ticket

- 2026-08-18 — création. Dockerfile réécrit (pnpm + lockfiles), entrypoint durci,
  deux stacks compose ajoutées, `.env.docker` / `.env` générés avec `APP_KEY`.
- 2026-08-18 — validé. `/`, `/screen`, `/screen/data`, `/login` en 200 ; connexion
  admin OK ; toutes les pages protégées en 200 une fois authentifié ; création puis
  suppression d'une salle vérifiées directement en base MySQL. Le démarrage a révélé
  un bug de migrations corrigé sous [[IFO-006-ordre-migrations]].
