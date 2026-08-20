# Tests CRUD & diagnostic CI — IFOSUP Display

Rapport de recherche. Aucun test n'a été écrit, aucun fichier applicatif ni workflow n'a été modifié.
Méthode : lecture intégrale des contrôleurs/requêtes/modèles/migrations concernés, puis **exécution réelle**
dans le conteneur de dev (`docker-compose.dev.yml`, image `ifosup-display:dev`, PHP 8.4.24 / Node 22) des
outils de lint pour observer les échecs effectifs plutôt que de les supposer. `php artisan`/`pest` n'étaient
pas installés sur la machine hôte Windows ; Docker si.

---

## CRUD — règles et validations

Constat transverse : les routes `teachers`, `rooms`, `groups`, `courses` et `admin/users` sont **toutes**
sous le même groupe `Route::middleware(['auth', 'verified'])` (`routes/web.php`). **Il n'existe aucun
middleware ni gate de rôle admin** (`bootstrap/app.php` n'enregistre que `HandleAppearance`,
`HandleInertiaRequests`, `AddLinkHeadersForPreloadedAssets`). Conséquence : n'importe quel utilisateur
authentifié et vérifié — pas seulement un « admin » — peut aujourd'hui créer/modifier/supprimer des
utilisateurs via `admin/users/*`. Toutes les `FormRequest` ont `authorize(): bool { return true; }`. À
garder en tête pour écrire des tests qui décrivent le comportement **actuel** (pas de test « 403 pour
utilisateur non-admin », il n'y a pas de notion d'admin) plutôt que le comportement souhaité.

| Ressource | Champs / règles (`Store*`) | Règles `Update*` | Unicité | Relations / cascades |
|---|---|---|---|---|
| **Teacher** (`teachers`, fillable `name`) | `name` required\|string\|max:255 | idem | aucune | `hasMany(Course)`. Suppression d'un teacher → `courses.teacher_id` mis à `NULL` (migration `2026_05_12_145456` : `nullOnDelete()`, pas de cascade delete) |
| **Room** (`rooms`, fillable `name`) | `name` required\|string\|max:255 | idem | aucune | pas de relation |
| **Group** (`groups`, fillable `name`) | `name` required\|string\|max:255 | idem | aucune | `belongsToMany(Course)` via pivot `course_group`. `group_id` sur la pivot est `constrained()->onDelete('cascade')` → supprimer un groupe retire silencieusement les lignes de pivot (les cours restent) |
| **Course** (`courses`, fillable `code,name,teacher_id`) | `name` required\|string\|max:255 ; `code` required\|string\|max:50\|**unique:courses,code** ; `teacher_id` **nullable**\|exists:teachers,id ; `groups` array, `groups.*` exists:groups,id | même règles + unique ignore l'id courant (`unique:courses,code,{course->id}`) | `code` unique | `belongsTo(Teacher)`, `belongsToMany(Group)`. Le contrôleur fait `$course->groups()->sync($validated['groups'])` au store (seulement si `has('groups')`) et à l'update (sync **vide** si `groups` absent → détache tout) |
| **User (Admin)** (`users`, fillable `name,email,password`) | `name` required\|string\|max:255 ; `email` required\|string\|email\|max:255\|**unique:users,email** ; `password` required\|confirmed\|`Password::defaults()` | `email` unique ignore l'id courant ; `password` **nullable**\|confirmed\|Password::defaults() (vide = mot de passe inchangé, le contrôleur fait `unset($validated['password'])` si vide) | `email` unique | pas de relation |

Points de comportement contrôleur à tester spécifiquement :
- `CourseController@store` / `TeacherController@store` / `RoomController@store` / `GroupController@store` /
  `Admin\UserController@store` : si `_create_another=1` dans la requête → redirection vers `*.create` au lieu
  de `*.show`.
- `CourseController@update` : si la clé `groups` est absente du payload, le contrôleur fait
  `$course->groups()->sync([])` — **détache tous les groupes**, même sans intention explicite de le faire.
  Comportement à documenter par un test explicite (piège potentiel côté frontend).
- `Admin\UserController@show/@edit` : n'exposent que `id,name,email(,created_at)` via `only()` — jamais le
  hash de mot de passe.
- `CourseController@show` charge `teacher:id,name` et `groups:id,name` ; `TeacherController@show` charge
  `courses:id,name,code,teacher_id` ; `GroupController@show` charge `courses:id,name,code`.
- Dates : aucune contrainte de format particulière, pas de champs date dans ces modèles.

---

## Plan de tests CRUD

Convention : `it('...')` en français, un fichier par ressource sous `tests/Feature/Resources/`, un fichier
dédié pour l'admin sous `tests/Feature/Admin/`. Pest 4 + `pest-plugin-laravel`, `RefreshDatabase` est déjà
appliqué automatiquement à tout `tests/Feature/**` via `tests/Pest.php`. Toutes les factories nécessaires
existent déjà (voir section suivante).

### `tests/Feature/Resources/TeacherCrudTest.php`

- `it('redirige les invités vers la connexion sur toutes les routes teachers')` — pour chaque verbe/URL du
  resource controller (`GET teachers`, `GET teachers/create`, `POST teachers`, `GET teachers/{t}`,
  `GET teachers/{t}/edit`, `PUT teachers/{t}`, `DELETE teachers/{t}`) sans `actingAs` → `assertRedirect(route('login'))`.
- `it('affiche la liste des enseignants à un utilisateur connecté')` — `actingAs(User::factory()->create())`,
  `get(route('teachers.index'))->assertOk()->assertInertia(fn ($page) => $page->component('resources/teachers/Index'))`.
- `it('affiche le formulaire de création')` — `assertInertia(...->component('resources/teachers/Create'))`.
- `it("crée un enseignant avec un nom valide")` — `post(route('teachers.store'), ['name' => 'Jean Dupont'])`
  → `assertRedirect(route('teachers.show', Teacher::first()))`, `assertDatabaseHas('teachers', [...])`.
- `it("refuse la création d'un enseignant sans nom")` — `post(..., ['name' => ''])` →
  `assertSessionHasErrors('name')`, `assertDatabaseCount('teachers', 0)`.
- `it("refuse la création d'un enseignant avec un nom trop long")` — `name` = 256 caractères →
  `assertSessionHasErrors('name')`.
- `it('redirige vers create quand _create_another est présent')` — `post(..., [..., '_create_another' => 1])`
  → `assertRedirect(route('teachers.create'))`.
- `it("affiche un enseignant avec ses cours")` — créer teacher + 2 courses liés, `get(route('teachers.show', $teacher))`
  → `assertInertia(fn ($page) => $page->has('teacher.courses', 2))`.
- `it("affiche une 404 pour un enseignant inexistant")` — `get('/teachers/999999')->assertNotFound()`.
- `it("affiche le formulaire d'édition pré-rempli")` — vérifie `page->where('teacher.name', ...)`.
- `it("met à jour le nom d'un enseignant")` — `put(route('teachers.update', $teacher), ['name' => 'Nouveau'])`
  → `assertDatabaseHas('teachers', ['id' => $teacher->id, 'name' => 'Nouveau'])`.
- `it("refuse la mise à jour sans nom")` — `assertSessionHasErrors('name')`.
- `it("supprime un enseignant")` — `delete(route('teachers.destroy', $teacher))` → `assertRedirect(route('teachers.index'))`,
  `assertDatabaseMissing('teachers', ['id' => $teacher->id])`.
- `it("met les cours d'un enseignant supprimé à NULL au lieu de les supprimer")` — créer teacher + course lié,
  `delete(...)`, puis `assertDatabaseHas('courses', ['id' => $course->id, 'teacher_id' => null])`
  (vérifie le `nullOnDelete()` de la migration `2026_05_12_145456`).

### `tests/Feature/Resources/RoomCrudTest.php`

Même structure que Teacher (pas de relation à tester en plus) :
- accès refusé aux invités sur les 7 routes.
- `it('affiche la liste des salles')`.
- `it('affiche le formulaire de création')`.
- `it("crée une salle avec un nom valide")`.
- `it("refuse la création d'une salle sans nom")`.
- `it("refuse la création d'une salle avec un nom de plus de 255 caractères")`.
- `it('redirige vers create quand _create_another est présent')`.
- `it("affiche une salle")` / `it("affiche une 404 pour une salle inexistante")`.
- `it("affiche le formulaire d'édition")`.
- `it("met à jour le nom d'une salle")` / `it("refuse la mise à jour sans nom")`.
- `it("supprime une salle")`.

### `tests/Feature/Resources/GroupCrudTest.php`

Structure identique à Room, plus :
- `it("affiche un groupe avec les cours qui lui sont rattachés")` — `group->courses()->attach($course)`,
  vérifie `page->has('group.courses', 1)`.
- `it("supprimer un groupe détache les cours sans les supprimer")` — attacher un groupe à un cours,
  `delete(route('groups.destroy', $group))`, puis `assertDatabaseMissing('course_group', [...])` **et**
  `assertDatabaseHas('courses', ['id' => $course->id])` (le cours doit survivre).

### `tests/Feature/Resources/CourseCrudTest.php`

- accès refusé aux invités sur les 7 routes.
- `it('affiche la liste des cours triée par code')`.
- `it("affiche le formulaire de création avec les enseignants et groupes disponibles")` —
  `assertInertia(fn ($page) => $page->has('teachers')->has('groups'))`.
- `it("crée un cours avec un enseignant et des groupes")` — `post(route('courses.store'), ['name' => ..., 'code' => 'X', 'teacher_id' => $teacher->id, 'groups' => [$group->id]])`
  → `assertDatabaseHas('courses', [...])`, `assertDatabaseHas('course_group', ['course_id' => ..., 'group_id' => $group->id])`.
- `it("crée un cours sans enseignant (teacher_id nullable)")` — omettre `teacher_id` → succès, `teacher_id` NULL en base.
- `it("refuse la création d'un cours sans nom")`.
- `it("refuse la création d'un cours sans code")`.
- `it("refuse la création d'un cours avec un code déjà utilisé")` — créer un `Course` via factory avec `code = 'ABC-1'`,
  poster le même code → `assertSessionHasErrors('code')`.
- `it("refuse un teacher_id inexistant")` — `teacher_id => 999999` → `assertSessionHasErrors('teacher_id')`.
- `it("refuse un id de groupe inexistant dans groups.*")` — `groups => [999999]` → `assertSessionHasErrors('groups.0')`.
- `it('redirige vers create quand _create_another est présent')`.
- `it("affiche un cours avec son enseignant et ses groupes")` — vérifie `page->has('course.teacher')->has('course.groups', n)`.
- `it("affiche le formulaire d'édition avec le cours, les enseignants et les groupes")`.
- `it("met à jour le code d'un cours en conservant l'unicité (hors lui-même)")` — mettre à jour un cours avec
  **son propre** code actuel → doit passer (règle `unique:courses,code,{id}` doit s'auto-exclure).
- `it("refuse la mise à jour avec le code d'un autre cours")` — deux cours existants, tenter de donner à l'un
  le code de l'autre → `assertSessionHasErrors('code')`.
- `it("resynchronise les groupes d'un cours à la mise à jour")` — cours initialement lié à group A, update avec
  `groups => [group B->id]` → `assertDatabaseMissing('course_group', ['group_id' => $groupA->id])` et
  `assertDatabaseHas('course_group', ['group_id' => $groupB->id])`.
- `it("détache tous les groupes si la clé groups est absente à la mise à jour")` — documente le comportement
  actuel de `CourseController@update` (`sync([])` implicite) : cours lié à un groupe, `put(...)` sans clé
  `groups` du tout → `assertDatabaseMissing('course_group', ['course_id' => $course->id])`.
- `it("supprime un cours et ses liaisons de groupes")` — `delete(...)` → `assertDatabaseMissing('courses', [...])`
  et `assertDatabaseMissing('course_group', ['course_id' => ...])` (cascade `course_id` de la migration pivot).

### `tests/Feature/Admin/UserCrudTest.php`

- `it("redirige les invités vers la connexion sur toutes les routes admin/users")`.
- `it("affiche la liste des utilisateurs à un simple utilisateur connecté (pas de garde-fou de rôle)")` —
  documente explicitement l'absence de contrôle d'accès admin constatée plus haut : un `User::factory()->create()`
  quelconque peut `get(route('admin.users.index'))->assertOk()`.
- `it("affiche le formulaire de création")`.
- `it("crée un utilisateur avec mot de passe confirmé")` — `post(route('admin.users.store'), ['name'=>.., 'email'=>.., 'password'=>'Password123!', 'password_confirmation'=>'Password123!'])`
  → `assertDatabaseHas('users', ['email' => ...])`, et vérifier que le hash stocké n'est pas le mot de passe en clair.
- `it("refuse la création sans nom / sans email / avec email invalide")`.
- `it("refuse la création avec un email déjà utilisé")` — `User::factory()->create(['email' => 'x@x.com'])`
  puis poster le même email → `assertSessionHasErrors('email')`.
- `it("refuse la création si password et password_confirmation ne correspondent pas")`.
- `it("refuse la création sans mot de passe")` — `password` est `required` sur Store.
- `it('redirige vers create quand _create_another est présent')`.
- `it("affiche un utilisateur (sans exposer le mot de passe)")` — `assertInertia` sur les clés exposées
  uniquement (`id,name,email,created_at`), s'assurer qu'aucune clé `password` n'est présente dans la prop Inertia.
- `it("affiche le formulaire d'édition")`.
- `it("met à jour le nom et l'email sans changer le mot de passe")` — `put(..., ['name'=>.., 'email'=>.., 'password'=>null, 'password_confirmation'=>null])`
  → le hash de mot de passe original doit être inchangé (`assertTrue(Hash::check('password', $user->fresh()->password))`
  avec le mot de passe par défaut de `UserFactory`).
- `it("met à jour le mot de passe quand un nouveau est fourni")` — vérifie que le hash change bien.
- `it("permet de conserver son propre email à la mise à jour")` — update avec le même email que l'utilisateur
  courant → ne doit pas déclencher l'erreur d'unicité (`unique:users,email,{id}`).
- `it("refuse la mise à jour avec l'email d'un autre utilisateur")`.
- `it("supprime un utilisateur")` — `delete(route('admin.users.destroy', $user))` → `assertDatabaseMissing('users', [...])`.

---

## Factories à créer

**Aucune factory n'est manquante** pour le périmètre Volet A : `TeacherFactory`, `RoomFactory`,
`GroupFactory`, `CourseFactory` et `UserFactory` existent déjà et couvrent les attributs nécessaires
(`database/factories/*.php`). `CourseFactory::definition()` fournit même déjà un `teacher_id` via
`Teacher::factory()`, suffisant pour tous les tests ci-dessus.

Suggestions d'**états additionnels** (facultatifs, pas bloquants pour écrire les tests — on peut créer les
variantes à la volée avec `Course::factory()->create(['teacher_id' => null])`) :
- `CourseFactory::withoutTeacher()` → state mettant `teacher_id` à `null`, pour lisibilité des tests
  « cours sans enseignant ».
- `CourseFactory::withGroups(int $count = 1)` → wrap `afterCreating` qui attache `$count` groupes via
  `Group::factory()->count($count)`, pour éviter de dupliquer le `attach()` manuel dans chaque test de relation.

Pas de factory nécessaire à créer pour `ScreenSlide`/`Assignment` : hors périmètre Volet A.

---

## CI — diagnostic

Constats obtenus par **lecture** des workflows/`composer.json`/`package.json`, puis **vérifiés par exécution
réelle** dans le conteneur de dev (`docker compose -f docker-compose.dev.yml run --rm --no-deps app ...`,
avec `composer install` et `npm install`/`vue-tsc`/`eslint`/`pint`/`prettier` effectivement lancés).

### Faits vérifiés par exécution (preuves directes)

1. **`./vendor/bin/pint --test` (= `composer lint:check`) échoue réellement aujourd'hui** : `FAIL 110 files,
   39 style issues` sur 39 fichiers (`app/Http/Controllers/*`, toutes les `Store*Request`/`Update*Request` du
   Volet A, `app/Models/ScreenSlide.php`, plusieurs migrations, plusieurs fichiers de `tests/`, etc.). Or
   `.github/workflows/lint.yml` appelle `composer lint` (= `pint --parallel`, qui **réécrit** les fichiers et
   sort toujours en succès) et non `composer lint:check`. Le job Pint ne peut donc jamais être rouge en l'état,
   même si tout le code est mal formaté — ce qui est le cas.
2. **`npx prettier --check resources/` (= `npm run format:check`) échoue réellement aujourd'hui** : 50
   fichiers `.vue`/`.ts` signalés `Code style issues found`. Or `lint.yml` appelle `npm run format` =
   `prettier --write` (réécrit silencieusement, sort toujours en succès), pas `format:check`.
3. **`npx eslint .` (= `npm run lint:check`, sans `--fix`) échoue réellement aujourd'hui** : 181 erreurs sur
   plusieurs dizaines de fichiers. Sur ces 181, une majorité (`import/order`,
   `@typescript-eslint/consistent-type-imports`, `import/consistent-type-specifier-style`) sont
   auto-fixables, **mais pas toutes** : les erreurs `@typescript-eslint/no-unused-vars` (ex. `HashIcon`,
   `UsersRoundIcon`, `processing`, `ChevronRight`, `MoreVertical`, `Settings`, `aInt`/`bInt`, `Input`,
   `ResourceListItemData`, `CalendarDays`, `DateRangeFieldInput`, `DateRangeFieldRoot`, `MessageSquareText`…),
   `vue/no-unused-vars` (`processing` dans les formulaires create/edit de rooms/teachers) et `vue/block-lang`
   (4 occurrences) **n'ont pas de fixer automatique**. Résultat concret : `npm run lint` (= `eslint . --fix`,
   ce que `lint.yml` exécute réellement) **laisse des erreurs résiduelles non corrigibles et se termine donc
   en échec** malgré le `--fix`. Autrement dit, contrairement à l'hypothèse initiale (« les scripts en mode
   écriture masquent tout »), le job **« Lint Frontend » du workflow `lint.yml` est très probablement rouge
   en l'état actuel du dépôt**, à cause de vraies erreurs de code (imports/variables inutilisés), pas d'un
   problème de configuration CI en soi.
4. **`npx vue-tsc --noEmit` (= `npm run types:check`) échoue avec ~29 erreurs `TS2307: Cannot find module`**
   sur tous les imports `@/actions/...` et `@/routes/...` (pages `admin/users/*`, `resources/{courses,groups,rooms,teachers}/*`,
   `auth/Login`, `settings/*`). Confirme que ces modules générés par Wayfinder (gitignorés :
   `resources/js/{actions,routes,wayfinder}`) sont indispensables au typecheck. Ni `lint.yml` ni `tests.yml`
   n'appellent `npm run types:check` aujourd'hui — ce gap n'est donc **pas actif** dans l'état actuel des
   workflows (le typecheck n'est simplement jamais exécuté), mais deviendrait bloquant dès qu'on l'ajouterait
   sans générer Wayfinder au préalable.
5. `composer install` (sans `--no-scripts`, tel que fait manuellement pour le test ci-dessus) affiche :
   `Warning: The lock file is not up to date with the latest changes in composer.json.` — signal mineur, à
   surveiller (`composer.lock` désynchronisé de `composer.json`), sans lien direct avec les workflows qui font
   un install propre à partir de zéro à chaque run.
6. `eslint .` **résout correctement l'ordre des imports même sans les fichiers Wayfinder générés** (aucune
   erreur `import/no-unresolved` observée : cette règle n'est pas activée dans `eslint.config.js`, seules
   `import/order` et `import/consistent-type-specifier-style` le sont). Le gap Wayfinder casse donc le
   **typecheck**, pas l'**eslint** actuel.

### Faits déduits par lecture de code (haute confiance, non ré-exécutés)

7. **`lint.yml` n'a pas d'étape `actions/setup-node`**, contrairement à `tests.yml` qui pin explicitement
   `node-version: '22'`. Le job lint utilise donc la version de Node préinstallée par défaut sur l'image
   `ubuntu-latest` du moment (non garantie, non alignée avec `tests.yml`).
8. **Mismatch de gestionnaire de paquets JS** : le dépôt committe `pnpm-lock.yaml` (utilisé par
   `docker/dev-entrypoint.sh` via `pnpm install --frozen-lockfile`) mais **aucun `package-lock.json`** n'est
   committé, alors que `lint.yml` et `tests.yml` font tous les deux `npm install`/`npm i`. `npm install` sans
   lockfile committé ignore totalement `pnpm-lock.yaml`, résout ses propres versions à chaque run (non
   reproductible), et ne produit aucun lockfile committable. Constaté empiriquement en creusant le sujet :
   lancer `npm install` sur un `node_modules` déjà peuplé par `pnpm install` (comme fait par
   `dev-entrypoint.sh` avant que je ne lance ma propre commande) casse immédiatement avec
   `npm error Cannot read properties of null (reading 'matches')` — preuve que les deux outils ne
   cohabitent pas proprement sur ce projet.
9. **`composer.json` définit un script `ci:check`** (`npm run lint:check && npm run format:check && npm run
   types:check && @test`) qui n'est **appelé par aucun des deux workflows**. Les workflows dupliquent une
   logique différente (et plus permissive) à la main. Script orphelin = drift garanti entre l'intention
   (`ci:check`) et la réalité (`lint.yml`/`tests.yml`).
10. **`tests.yml`** : pas de clé `extensions:` sur l'étape `shivammathur/setup-php`, donc les extensions PHP
    installées (dont `pdo_sqlite`, requis par `phpunit.xml` qui force `DB_CONNECTION=sqlite`/`DB_DATABASE=:memory:`)
    dépendent du jeu par défaut de l'action pour PHP 8.4/8.5 sur `ubuntu-latest` — généralement présent en
    pratique, mais non garanti explicitement dans le workflow. Risque faible, non confirmé par exécution
    (pas de runner GitHub Actions disponible ici).
11. **`lint.yml`** déclare `permissions: contents: write` alors que l'unique étape qui en aurait besoin
    (commit automatique des fixes) est commentée. Permission inutilisée superflue, pas une cause d'échec.
12. `tests.yml` copie `.env.example` → `.env` puis `php artisan key:generate` puis `npm run build` (déclenche
    la génération Wayfinder via le plugin Vite `@laravel/vite-plugin-wayfinder`, donc dans le bon ordre :
    après `composer install`, après la clé d'app) : cette séquence est correcte et explique pourquoi le
    typecheck n'y est simplement jamais appelé plutôt que d'y planter — `vite build` n'échoue pas sur les
    imports manquants puisqu'il génère lui-même les fichiers avant le bundling.
13. Deux points signalés par le mainteneur comme déjà résolus pendant cette analyse (à ne pas re-traiter) :
    la route `/debug-excel` et `SchedulerParserController` ont été supprimés ; la migration
    `create_recurring_assignments_table` a été renommée `2026_03_16_140900_...` pour précéder
    `2026_03_16_140937_create_assignments_table` (ordre cassé sur MySQL corrigé).

---

## CI — corrections à apporter

Ordre recommandé (du plus impactant / plus sûr au plus optionnel) :

1. **`.github/workflows/lint.yml`** — remplacer les 3 commandes en mode écriture par leurs équivalents
   « check » pour que le job échoue réellement sur du code mal formaté/mal linté (aujourd'hui il échoue déjà
   à cause d'erreurs eslint non fixables, mais de façon peu lisible et incomplète — Pint et Prettier, eux,
   passent artificiellement) :
   - `composer lint` → `composer lint:check`
   - `npm run format` → `npm run format:check`
   - `npm run lint` → `npm run lint:check`
   Conséquence immédiate si appliqué tel quel : le job passera au rouge tant que les 39 fichiers Pint + 50
   fichiers Prettier + 181 erreurs ESLint n'auront pas été corrigés en amont (par ex. via `composer lint &&
   npm run format && npm run lint` lancés localement/en une PR de nettoyage avant d'activer ces checks stricts).
2. **`.github/workflows/lint.yml`** — ajouter une étape `actions/setup-node@v4` avec `node-version: '22'`
   juste après le checkout, pour aligner avec `tests.yml` et fixer la version de Node utilisée.
3. **`.github/workflows/lint.yml`** — avant l'étape « Lint Frontend », générer les fichiers Wayfinder
   consommés par les imports `@/actions`, `@/routes` (42 fichiers `resources/js/**` en dépendent), nécessaire
   dès qu'un `npm run types:check` sera ajouté (point 4) et par prudence générale :
   ```
   - name: Copy Environment File
     run: cp .env.example .env
   - name: Generate Application Key
     run: php artisan key:generate
   - name: Generate Wayfinder
     run: php artisan wayfinder:generate --with-form
   ```
   (mêmes flags que `docker/dev-entrypoint.sh`). Nécessite que l'étape composer install ait tourné avant
   (déjà le cas dans l'ordre actuel du workflow).
4. **`.github/workflows/lint.yml`** — ajouter une étape `npm run types:check` (vue-tsc) après la génération
   Wayfinder, pour combler le point 4/10 du diagnostic (le typecheck n'est aujourd'hui jamais exécuté par
   aucun des deux workflows).
5. **`package.json` / `.github/workflows/*.yml`** — trancher le mismatch pnpm vs npm (point 8) : soit (a)
   committer un `package-lock.json` et passer les deux workflows en `npm ci` (au lieu de `npm install`/`npm i`),
   soit (b) — recommandé, car cohérent avec `docker/dev-entrypoint.sh` qui utilise déjà pnpm — ajouter
   `pnpm/action-setup@v4` dans les deux workflows et remplacer `npm install`/`npm i` par
   `pnpm install --frozen-lockfile` (le lockfile `pnpm-lock.yaml` existe déjà et est à jour). Choisir une
   seule option pour tout le repo.
6. **`.github/workflows/tests.yml`** — ajouter explicitement `extensions: pdo_sqlite, sqlite3, mbstring, gd,
   zip` sur l'étape `shivammathur/setup-php` pour sécuriser la disponibilité du driver sqlite utilisé par
   `phpunit.xml` (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`) et des extensions utilisées par
   `phpoffice/phpspreadsheet` (gd/zip), plutôt que de dépendre du jeu d'extensions par défaut du runner.
7. **`.github/workflows/lint.yml`** — retirer `permissions: contents: write` (remplacer par `contents: read`
   ou l'omettre) puisque l'étape d'auto-commit est commentée et inutilisée ; à réintroduire seulement si cette
   étape est un jour réactivée.
8. **`composer.json` / workflows** — envisager de faire appeler `composer ci:check` directement par
   `lint.yml`/`tests.yml` (ou au minimum aligner leur contenu) plutôt que de dupliquer une liste de commandes
   différente dans le YAML : réduit le risque de re-divergence future entre le script « officiel » et ce que
   la CI exécute réellement.
9. **`composer.lock`** — lancer un `composer update` (ou `composer update <package>` ciblé) pour faire
   disparaître l'avertissement « lock file is not up to date with composer.json » observé pendant les tests
   locaux ; sans impact sur la CI actuelle (qui fait un install propre) mais source de confusion en dev.

Points **non confirmés par exécution** (pas de runner GitHub Actions disponible ici), à valider lors de la
première exécution réelle des workflows après corrections : le comportement précis de
`shivammathur/setup-php` sans `extensions:` explicite sur `ubuntu-latest` pour PHP 8.4 **et** 8.5, et le
comportement de `pnpm/action-setup` si l'option (b) du point 5 est retenue.
