---
id: IFO-024
titre: Import du planning annuel — « Maximum execution time of 30 seconds exceeded »
statut: terminé
priorité: haute
dépend-de: [IFO-021]
créé: 2026-09-15
mis-à-jour: 2026-09-15
---

## Contexte

Signalé par Thibault le 2026-09-15 : l'import de « LOCAUX XAVIER 26-27 V3.xlsx »
(fichier déposé dans `docs/data/`, dossier ignoré par Git) échoue avec
« Maximum execution time of 30 seconds exceeded ». La veille, quelques centaines
de lignes passaient. Analyse et décision :
[ADR-004](../decisions/ADR-004-import-planning-temps-execution.md).

Mesure du fichier (openpyxl) : six feuilles, environ 6 500 cellules remplies,
soit quelques milliers d'attributions. Le contrôleur fait deux requêtes SQL par
ligne à l'aperçu comme à l'import, et parse le classeur deux fois.

## Critères d'acceptation

- [x] `max_execution_time = 300` dans `docker/php.ini` (documenté) et
      `set_time_limit(300)` dans `preview()` et `executeImport()`.
- [ ] Vérifié en prod par Thibault : aperçu puis import du fichier V3 sans
      erreur.
- [ ] Chantier suivant ouvert le moment venu : import en tâche de fond (ADR-004).

## Journal du ticket

- 2026-09-15 — création, analyse (N+1 à l'aperçu et à l'import, double
  parsing), quatre options proposées, Thibault choisit la (1) tout de suite et
  la (4) plus tard. Correctif poussé sur `main`.
