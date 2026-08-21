---
id: IFO-006
titre: Ordre des migrations cassé sur MySQL (recurring_assignments)
statut: terminé
priorité: haute
dépend-de: []
créé: 2026-08-18
mis-à-jour: 2026-08-18
---

## Contexte

Au premier démarrage de la stack Docker sur MySQL, `php artisan migrate` échoue :

```
SQLSTATE[HY000]: General error: 1824 Failed to open the referenced table 'recurring_assignments'
alter table `assignments` add constraint `assignments_recurring_assignment_id_foreign` …
```

`2026_03_16_140937_create_assignments_table` déclare une clé étrangère vers
`recurring_assignments`, table créée dix jours plus tard par
`2026_03_26_131518_create_recurring_assignments_table`. Le développement s'étant
fait sur SQLite — qui n'applique pas les contraintes de clé étrangère lors des
migrations Laravel — l'erreur n'était jamais apparue. Elle est bloquante sur MySQL
comme sur PostgreSQL, donc sur toute installation neuve en production.

À noter : la fonctionnalité « attributions récurrentes » a été abandonnée depuis
(`2026_05_11_135528_drop_recurring_assignments`). Sur une base neuve, ces migrations
créent donc une table pour la supprimer trois migrations plus loin.

## Critères d'acceptation

- [x] `php artisan migrate` passe intégralement sur une base MySQL vierge
- [x] L'ordre reste cohérent pour `add_week_columns_to_recurring_assignments_table`
      (toujours exécutée après la création de la table)
- [x] `drop_recurring_assignments` s'exécute toujours en dernier et nettoie bien
      la contrainte puis les colonnes

## Journal du ticket

- 2026-08-18 — création et correction. `2026_03_26_131518_create_recurring_assignments_table.php`
  renommé en `2026_03_16_140900_create_recurring_assignments_table.php` (via `git mv`,
  contenu inchangé), soit juste avant `2026_03_16_140937_create_assignments_table`.
  Les 11 migrations passent ensuite sans erreur sur MySQL 8.4.
- 2026-08-18 — correction minimale retenue plutôt qu'un nettoyage complet des trois
  migrations `recurring_assignments` : elle ne touche à aucun contenu de migration et
  préserve l'historique du travail de l'étudiant. Un nettoyage reste possible plus tard
  si l'on veut alléger `migrate:fresh`.
