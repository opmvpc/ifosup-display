# Plan consolidé — tests métier et CI au vert

_Rédigé le 2026-08-18. Consolide les trois analyses de `docs/research/` après
vérification indépendante des chiffres._

Tickets : [IFO-004](tickets/IFO-004-couverture-de-tests.md) (tests),
[IFO-007](tickets/IFO-007-ci-au-vert.md) (CI).

## Point de départ mesuré

| Mesure | Valeur constatée |
|---|---|
| `php artisan test` | **23 échecs, 7 ignorés, 11 succès** |
| Cause de la quasi-totalité des échecs | `Vite manifest not found at /app/public/build/manifest.json` |
| `pint --test` | 39 fichiers non conformes sur 110 |
| `prettier --check resources/` | 50 fichiers non conformes |
| `eslint .` | 181 erreurs, dont **29 non auto-fixables** |
| Détail des 29 | `@typescript-eslint/no-unused-vars` (17), `vue/no-unused-vars` (8), `vue/block-lang` (4) |
| Tests métier existants | **0** — `tests/` ne contient que le starter kit |

Le workflow `lint.yml` appelle aujourd'hui `composer lint`, `npm run format` et
`npm run lint`, c'est-à-dire les variantes **qui réécrivent les fichiers**. Pint et
Prettier ne peuvent donc jamais échouer, quel que soit l'état du code. ESLint, lui,
échoue malgré `--fix` à cause des 29 erreurs sans correcteur automatique : le job
« Lint Frontend » est rouge aujourd'hui.

## Ordre d'exécution

Chaque phase est livrable et vérifiable seule. L'ordre compte : la phase 1 conditionne
la lisibilité de tout ce qui suit, la phase 2 conditionne l'écriture des tests.

---

### Phase 1 — Mise à niveau du style (commit isolé) — _validée le 2026-08-18_

Objectif : partir d'une base conforme pour que les diffs suivants ne mélangent jamais
reformatage et logique.

1. `composer lint` (Pint réécrit les 39 fichiers).
2. `npm run format` (Prettier réécrit les 50 fichiers).
3. `npm run lint` (ESLint corrige les 152 erreurs auto-fixables).
4. Corriger **à la main** les 29 erreurs restantes : supprimer les imports et variables
   inutilisés (`HashIcon`, `UsersRoundIcon`, `ChevronRight`, `MoreVertical`, `Settings`,
   `processing`, `CalendarDays`, `MessageSquareText`…), ajouter `lang="ts"` sur les
   4 blocs `<script>` concernés.

Ces étapes ne changent aucun comportement. À livrer en un commit `style:` distinct,
sans rien d'autre dedans.

**Fini quand** : `composer lint:check`, `npm run format:check` et `npm run lint:check`
sortent tous les trois en succès.

---

### Phase 2 — Socle de test

Sans ce socle, aucun des tests des phases suivantes n'est écrivable proprement.

1. **Neutraliser Vite dans les tests** — c'est la cause des 23 échecs actuels. Ajouter
   `$this->withoutVite()` globalement dans `tests/Pest.php` (ou `TestCase::setUp`).
   Bénéfice direct : la suite PHP cesse de dépendre d'un `npm run build` préalable, en
   local comme en CI.
2. **`ScreenSlideFactory`** — manquante alors que le modèle déclare `HasFactory`.
   Bloquante pour presque tous les tests d'écran. States : défaut `schedule`,
   `welcome()`, `image()`, `video()`, `locked()`.
3. **States sur `AssignmentFactory`** : `planned()`, `cancelled()`, `late()`,
   `onDate()`, `inRoom()`, `inPeriod()`, `withoutRoom()`, et un raccourci
   `forSlot(Room, date, period)` très réutilisé par les tests de conflits.
4. **Helper d'authentification** dans `tests/Pest.php` — évite de répéter
   `actingAs(User::factory()->create())` dans ~150 tests.
5. **Helper de fixture Excel** — un trait qui génère un classeur avec PhpSpreadsheet à
   la volée plutôt qu'un `.xlsx` binaire versionné : le contenu reste lisible et
   auditable dans le diff. Format à respecter (déduit du parser) : ligne d'en-tête
   `Matin`/`Midi`/`Soir`, ligne des dates juste en dessous, données à
   `headerRow + 3`, salle en colonne `en-tête − 1`, fin de bloc sur deux lignes vides.

**Fini quand** : `php artisan test` repasse au vert sur les tests existants du starter kit.

---

### Phase 3 — Correction des bugs confirmés

Trois bugs sont assez nets pour être corrigés plutôt que simplement figés. Chacun part
avec son test de non-régression.

| # | Bug | Correction |
|---|---|---|
| B1 | `ScreenController::data()` — les trois plages s'arrêtent à `23:59:59` pile. À `23:59:59,5`, aucune ne correspond : `visiblePeriods` est vide et **l'écran n'affiche plus aucun horaire pendant ~1 seconde chaque nuit**. | Comparer sur l'heure seule sans microsecondes, ou porter la borne à `23:59:59.999999`. |
| B2 | `ScheduleController::bulkPreview()` — `room_id` est validé comme obligatoire mais n'est jamais utilisé pour filtrer la requête des attributions existantes. Le front reçoit des conflits portant sur des salles non concernées. | Ajouter le `where('room_id', …)` manquant. |
| B3 | `ScreenSlide` — les hooks `deleting`/`updating` appellent `Storage::delete()` sans disque, donc sur `local`, alors que `ScreenSlideController` écrit les médias sur le disque `public`. Les anciens fichiers ne sont jamais réellement supprimés. | Préciser `Storage::disk('public')` dans les hooks du modèle. |

---

### Phase 4 — Tests métier

**Décision du 2026-08-18 : les 216 tests proposés sont retenus**, couverture
exhaustive. Le noyau ci-dessous est traité en premier — il porte le risque réel
d'exploitation — puis les cas limites complètent chaque module.

#### Noyau (premier passage)

| Fichier | Tests | Ce qui est couvert |
|---|---|---|
| `tests/Feature/Screen/ScreenDataTest.php` | ~14 | Les 3 périodes avec `travelTo`, les frontières 12:30 / 17:30 / minuit (B1), le fuseau configurable, l'accès anonyme autorisé |
| `tests/Feature/Screen/ScreenSlideControllerTest.php` | ~16 | CRUD des slides, réordonnancement, slide verrouillé en 1re position, suppression des médias sur le bon disque (B3) |
| `tests/Feature/Scheduler/StoreAssignmentTest.php` | ~12 | Création, conflit de créneau, validations, refus aux anonymes |
| `tests/Feature/Scheduler/UpdateAssignmentTest.php` | ~9 | Déplacement, conflit, changement de salle et de période |
| `tests/Feature/Scheduler/UpdateStatusDestroyTest.php` | ~7 | Statuts `planned`/`cancelled`/`late`, suppression |
| `tests/Feature/Scheduler/BulkTest.php` | ~12 | `bulkPreview` (dont B2), `bulkStore`, écrasement de créneau, compteur `inserted` |
| `tests/Feature/Scheduler/ScheduleIndexTest.php` | ~8 | Validation des paramètres, repli sur les cookies, props Inertia |
| `tests/Feature/Scheduler/SchedulerImportTest.php` | ~12 | Upload, `preview`, `execute`, `discard`, portée réelle de `purge_period` |
| `tests/Unit/Services/SchedulerSheetParserTest.php` | ~10 | Blocs matin/midi/soir, dates déduites, multi-feuilles, cellules fusionnées |
| `tests/Feature/Resources/*CrudTest.php` (4 fichiers) | ~28 | Les 4 CRUD : accès anonyme refusé, création, édition, suppression, validations, relations `course_group` |
| `tests/Feature/Admin/UserCrudTest.php` | ~10 | CRUD utilisateurs, unicité de l'email, changement de mot de passe |
| `tests/Feature/RoutesTest.php` | 1 | `GET /debug-excel` renvoie bien 404 (non-régression IFO-003) |

#### Complément exhaustif (second passage, ~100 tests)

Cas limites fins du parser Excel, tests unitaires de modèles, variantes exhaustives de
validation champ par champ, tests de props Inertia détaillées. Le détail par test se
trouve dans les trois rapports de `docs/research/`.

#### Comportements à figer sans les corriger

Ces comportements sont surprenants mais relèvent d'un arbitrage métier, pas d'un bug
technique. Les tests les **documentent tels quels**, pour qu'une modification future
soit un choix explicite et non une dérive silencieuse :

- une attribution `cancelled` continue de bloquer son créneau ;
- `bulkStore` écrase les attributions existantes là où `store`/`update` refusent en 422 ;
- `purge_period` supprime toutes les attributions de la plage, y compris les salles et
  cours non sélectionnés dans l'import — **arbitré le 2026-08-18 : figé par test, non
  modifié** ; l'arbitrage métier reste à faire ;
- `update` sur un slide de type `schedule` ne fait rien, sans erreur ;
- le parser déduit les dates par `+7 jours` sans relire les cellules suivantes ;
- une cellule de salle fusionnée sur plusieurs lignes ignore les lignes intermédiaires.

---

### Phase 5 — CI au vert

`.github/workflows/lint.yml` :

1. Ajouter `actions/setup-node@v4` avec `node-version: '22'` (absent, contrairement à
   `tests.yml`).
2. Remplacer `npm install` par `pnpm/action-setup@v4` + `pnpm install --frozen-lockfile`.
   Le dépôt committe `pnpm-lock.yaml` et aucun `package-lock.json` : `npm install`
   ignore le lockfile et résout ses propres versions à chaque exécution. Les deux outils
   ne cohabitent pas — un `npm install` sur un `node_modules` peuplé par pnpm casse net.
3. Passer les trois commandes en mode vérification : `composer lint:check`,
   `npm run format:check`, `npm run lint:check`. À faire **après** la phase 1,
   sans quoi le job devient rouge immédiatement.
4. Générer Wayfinder avant le typecheck (`cp .env.example .env`,
   `php artisan key:generate`, `php artisan wayfinder:generate --with-form`) puis
   ajouter `npm run types:check` — jamais exécuté par aucun workflow aujourd'hui, et
   qui échoue actuellement avec ~29 `TS2307` sur les imports `@/actions` et `@/routes`.
5. Remplacer `permissions: contents: write` par `contents: read` : l'étape d'auto-commit
   qui le justifiait est commentée.

`.github/workflows/tests.yml` :

6. Expliciter `extensions: pdo_sqlite, sqlite3, mbstring, gd, zip` sur `setup-php`
   (`phpunit.xml` force SQLite en mémoire ; PhpSpreadsheet utilise gd et zip).
7. Aligner sur pnpm comme `lint.yml`.
8. Une fois `withoutVite()` en place (phase 2), l'étape `npm run build` n'est plus
   nécessaire aux tests PHP — à conserver seulement si l'on veut vérifier que le build
   front passe, mais dans un job distinct.

**Fini quand** : les deux workflows sont verts sur une branche de test, avec les
commandes en mode vérification.

---

## Ce qui reste hors périmètre

- `composer.lock` est désynchronisé de `composer.json` (avertissement au `composer install`).
  Sans effet sur la CI qui installe à partir de zéro, mais à traiter un jour.
- Les cookies `scheduler_from_date` / `scheduler_to_date` sont posés en `httpOnly=false`,
  donc lisibles en JavaScript. Ce sont des préférences d'affichage, pas des données
  sensibles ; à revoir si leur usage change.
- Aucune notion de rôle : `admin/users/*` n'est protégé que par `auth` + `verified`,
  comme le reste. **Tout utilisateur connecté peut créer et supprimer des comptes.**
  À arbitrer séparément — ce n'est pas un test à écrire, c'est une fonctionnalité à
  décider. Point laissé ouvert au 2026-08-18, sans ticket : à trancher avec l'école.
