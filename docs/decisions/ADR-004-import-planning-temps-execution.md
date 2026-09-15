---
id: ADR-004
titre: Import du planning — relever le temps d'exécution maintenant, tâche de fond ensuite
statut: accepté
date: 2026-09-15
---

## Contexte

Le premier import d'un planning annuel complet (« LOCAUX XAVIER 26-27 V3 »,
quelques milliers d'attributions sur six feuilles) échoue avec « Maximum
execution time of 30 seconds exceeded » ([IFO-024](../tickets/IFO-024-import-planning-trop-long.md)).
La veille, quelques centaines de lignes passaient. Causes dans
`SchedulerImportController` : une requête SQL par ligne à l'aperçu (recherche
de conflit) et à l'import (SELECT puis INSERT/UPDATE), et le parsing
PhpSpreadsheet exécuté deux fois. Il n'y a pas de queue : tout tourne dans la
requête HTTP, sous la limite de php.ini-production.

Quatre options ont été mises sur la table : (1) relever la limite de temps,
(2) supprimer les requêtes par ligne (préchargement + `upsert` par paquets sur
l'index unique `(date, period, room_id)`), (3) mémoriser le résultat du
parsing entre aperçu et import, (4) import en tâche de fond avec suivi de
progression.

## Décision

- **Tout de suite (1)** : `max_execution_time = 300` dans `docker/php.ini` et
  `set_time_limit(300)` dans `preview()` et `executeImport()`. La rentrée est
  en cours, le planning doit être importable aujourd'hui.
- **Ensuite, quand on aura le temps (4)** : l'import passe en tâche de fond
  (queue `database` déjà configurée, worker à ajouter dans le conteneur,
  table de suivi, barre de progression côté Vue). Les optimisations (2) et (3)
  seront intégrées à ce chantier, elles restent utiles pour que le job soit
  court.

## Alternatives écartées

- **(2)+(3) seules, maintenant** : meilleur rapport effort/résultat sur le
  papier, mais demande une passe de tests sur un contrôleur sensible (purge
  définitive dans la transaction) — pas le jour d'une rentrée.
- **Réduire le fichier** (importer feuille par feuille) : reporte la charge
  sur la personne qui encode.

## Conséquences

- Un aperçu ou un import peut durer une minute sans retour visuel autre que le
  spinner. À surveiller : le proxy Coolify (Traefik) ne coupe pas les réponses
  par défaut ; si un timeout apparaît côté proxy, le régler là aussi.
- L'import reste atomique (transaction) : une coupure ne laisse rien à moitié.
- Ticket à ouvrir pour (4) au moment de s'y mettre ; IFO-024 ne couvre que (1).
