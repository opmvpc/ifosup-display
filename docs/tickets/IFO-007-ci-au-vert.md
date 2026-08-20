---
id: IFO-007
titre: Faire passer la CI GitHub Actions au vert
statut: terminé
priorité: haute
dépend-de: [IFO-004]
créé: 2026-08-18
mis-à-jour: 2026-08-18
---

## Contexte

Les deux workflows (`lint.yml`, `tests.yml`) ne remplissent pas leur rôle :

- `lint.yml` appelle `composer lint`, `npm run format` et `npm run lint`, c'est-à-dire
  les variantes **qui réécrivent les fichiers**. Pint et Prettier ne peuvent donc jamais
  échouer — et masquent aujourd'hui 39 fichiers PHP et 50 fichiers front non conformes.
- Le job « Lint Frontend » est en revanche bien rouge : `eslint --fix` laisse
  29 erreurs sans correcteur automatique (imports et variables inutilisés, `vue/block-lang`).
- `lint.yml` n'a pas d'étape `setup-node`, contrairement à `tests.yml`.
- Les deux workflows font `npm install` alors que le dépôt committe `pnpm-lock.yaml` et
  aucun `package-lock.json` : le lockfile est ignoré, les versions sont re-résolues à
  chaque exécution.
- `npm run types:check` n'est appelé nulle part ; il échoue actuellement avec ~29
  `TS2307` faute de génération Wayfinder.
- `composer ci:check` existe dans `composer.json` mais aucun workflow ne l'appelle.

Détail complet et ordre des corrections : [`../plan-tests-et-ci.md`](../plan-tests-et-ci.md),
phases 1 et 5. Diagnostic d'origine : [`../research/tests-crud-et-ci.md`](../research/tests-crud-et-ci.md).

## Critères d'acceptation

- [x] `composer lint:check` passe (39 fichiers Pint remis en forme au préalable)
- [x] `pnpm format:check` passe (50 fichiers Prettier)
- [x] `pnpm lint:check` passe (181 erreurs ESLint, dont 29 corrigées à la main)
- [x] `pnpm types:check` passe et fait partie du workflow — résolu sous
      [[IFO-008-typecheck-et-reliquats-2fa]] sans supprimer de fichier
- [x] `lint.yml` utilise `setup-node@v4` en Node 22 et `pnpm install --frozen-lockfile`
- [x] `tests.yml` déclare explicitement ses extensions PHP et utilise pnpm
- [x] `permissions` de `lint.yml` ramené à `contents: read`
- [x] Les commandes des deux workflows passent en local dans le container de dev
      (Pint 130 fichiers PASS, Prettier PASS, ESLint 0 erreur, Pest 253 verts)
- [x] Confirmation sur un vrai runner GitHub Actions : les trois jobs passent sur la
      PR #1 — `quality`, `ci (8.4)` et `ci (8.5)`

## Journal du ticket

- 2026-08-18 — création, à partir du diagnostic outillé (commandes réellement exécutées
  dans le container de dev, pas seulement lues).
- 2026-08-18 — corrigé. `lint.yml` : `setup-node@v4` en Node 22, `pnpm/action-setup@v4`
  avec `pnpm install --frozen-lockfile`, les trois commandes passées en mode
  vérification, `permissions: contents: read`, extensions PHP explicites.
  `tests.yml` : mêmes extensions, pnpm, `coverage: none` (xdebug était actif sans
  qu'aucun rapport de couverture ne soit produit).
- 2026-08-18 — **piège trouvé en simulant la CI** : le résultat d'ESLint dépend de la
  présence des fichiers générés par Wayfinder. `resources/js/{actions,routes}` est
  gitignoré, et la règle `import/order` classe `@/actions` / `@/routes` différemment
  selon que ces modules sont résolvables ou non — 37 erreurs d'écart entre les deux
  états. `lint.yml` génère donc Wayfinder avant les vérifications front, et le code a
  été figé dans l'état « Wayfinder présent », celui d'un poste de développement normal.
- 2026-08-20 — validé en conditions réelles. Les workflows n'écoutaient `push` que sur
  `develop`, `main`, `master` et `workos` : une branche de chantier ne les déclenche pas.
  C'est l'ouverture de la PR #1 qui a activé le trigger `pull_request`. Résultat :
  `quality` vert en 59 s, `ci (8.4)` en 38 s, `ci (8.5)` en 1 min 3 s.
