---
id: IFO-014
titre: Import Excel — la casse des noms fait mentir l'aperçu et casse l'exécution
statut: ouvert
priorité: haute
dépend-de: []
créé: 2026-08-22
mis-à-jour: 2026-08-22
---

## Contexte

Trouvé par la review croisée Codex Sol (2026-08-22), confirmé par relecture du code.
MySQL compare les chaînes en `utf8mb4_unicode_ci` (insensible à la casse), mais les
tableaux PHP de `SchedulerImportController` sont sensibles à la casse :

- **Locaux** — aperçu ([SchedulerImportController.php:122](../../app/Http/Controllers/SchedulerImportController.php)) :
  `whereIn('name', ['SALLE A'])` trouve `Salle A`, mais `array_diff` classe `SALLE A`
  comme *nouveau* local. À l'exécution (`:261`), `Room::create(['name' => 'SALLE A'])`
  heurte la contrainte d'unicité (insensible à la casse sous MySQL) → exception →
  **réponse 500** (le rollback de la transaction IFO-009 protège les données).
- **Cours** — `$courses[$entry['course']] ?? null` vaut `null` pour `abc` vs `ABC` :
  les lignes concernées sont **silencieusement ignorées** avec une réponse de succès.

Bug d'origine étudiante, rendu visible par le passage à MySQL (ADR-001) et la
contrainte d'unicité (IFO-010). Sous SQLite (tests), `LIKE` est insensible mais `=` et
l'unicité sont sensibles à la casse : le comportement diffère de la production.

## Critères d'acceptation

- [ ] L'aperçu classe un local existant à casse différente comme *existant* (pas de
      création tentée à l'exécution, pas de 500)
- [ ] Les lignes d'un cours dont le code ne diffère que par la casse sont importées,
      pas ignorées
- [ ] Tests couvrant les deux cas (au minimum au niveau du contrôleur, mappings
      normalisés indépendamment du moteur SQL)
- [ ] Comportement identique MySQL / SQLite (normalisation faite en PHP)

## Piste

Normaliser les correspondances en PHP : indexer `roomsByName` / `coursesByCode` par
`mb_strtolower(...)` et faire toutes les recherches (`array_diff`, `isset`, `[$clé]`)
sur la forme normalisée, en conservant la casse d'origine pour l'affichage et la
création. Ne pas toucher à la base.

## Journal du ticket

- 2026-08-22 — création (audit croisé IFO-013).
