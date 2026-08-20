---
id: ADR-001
titre: MySQL 8.4 comme base de données, en local comme en production
statut: accepté
date: 2026-08-18
---

## Contexte

L'étudiant a développé sur SQLite (`DB_CONNECTION=sqlite` dans `.env.example`).
Le projet passe en exploitation réelle à l'école : plusieurs utilisateurs du
backoffice, imports Excel de plannings, écrans qui interrogent l'API en continu,
et un hébergement Coolify où les sauvegardes sont gérées par service.

## Décision

Le projet utilise MySQL 8.4 sur les deux environnements : un service `mysql` dans
les deux fichiers compose en local, un service MySQL managé côté Coolify en
production. Le `Dockerfile` installe déjà `pdo_mysql`.

## Alternatives écartées

- **SQLite avec volume persistant** : plus simple, suffisant en volumétrie, mais
  écritures concurrentes fragiles, sauvegarde et supervision moins bien outillées
  sur Coolify, et divergence local/prod si l'école bascule plus tard.
- **PostgreSQL 17** : équivalent techniquement, mais impose d'ajouter `pdo_pgsql`
  au `Dockerfile` sans bénéfice pour ce projet.

## Conséquences

- `.env.example` reste sur SQLite (il documente le mode « dev sans Docker » de
  l'étudiant) ; les fichiers `.env` / `.env.docker` de la stack Docker sont sur MySQL.
- L'entrypoint attend que la base réponde avant de migrer (`php artisan db:show`).
- Une migration des données SQLite existantes serait à traiter à part ; à ce jour
  il n'y a pas de données de production à reprendre.
