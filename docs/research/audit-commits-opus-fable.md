# Audit croisé de la reprise Opus (fdfc3f2..HEAD) — Fable, 2026-08-22

Méthode : lecture de chacun des 15 commits de code de la plage, vérification de chaque
fix revendiqué dans le code à HEAD, contrôle des workflows CI via `gh` (lecture seule).
Aucun fichier de code modifié, aucun test exécuté localement (pas de vendor/).

## Verdict global

**Le travail d'Opus est fiable.** Chaque bug revendiqué dans STATUS.md correspond à un
correctif réel et vérifiable dans le code à HEAD, presque toujours accompagné d'un test
qui le fige. La CI est réellement verte sur main à HEAD (runs 32470149598/32470149551 du
2026-08-21 : `tests` et `linter`, matrices PHP 8.4 et 8.5). Les deux régressions
introduites en cours de route (unicité sans règle applicative, garde-fou `:?` du compose)
ont été rattrapées et honnêtement documentées. Restent des écarts documentaires mineurs
et deux limites résiduelles non bloquantes, listés ci-dessous.

## Fixes vérifiés OK

- **Ordre des migrations** : `create_recurring_assignments` renommée en `2026_03_16_140900`,
  avant `create_assignments` (140937). Contenu inchangé.
- **`/debug-excel`** : route et `SchedulerParserController` (avec son `dd()`) supprimés ;
  aucun `debug-excel` dans `routes/web.php` à HEAD.
- **Écran vide à 23:59:59,5** : `ScreenController.php:44` tronque `now` via `startOfSecond()`
  avant `between()`. Correct (bornes `00:00:00`–`23:59:59` couvertes après troncature).
- **Médias orphelins** : hooks `deleting`/`updating` de `ScreenSlide` passés sur
  `Storage::disk('public')` ; `imageUrl()` volontairement inchangé (commenté).
- **`password.confirm` en 500** : `Fortify::confirmPasswordView()` enregistré.
- **Import atomique (IFO-009)** : purge + réinsertion dans `DB::transaction()` avec
  `catch (Throwable)` → 500 explicite ; suppression du fichier et nettoyage de session
  après commit seulement. Deux tests de rollback présents. `bulkStore` est aussi passé
  sous transaction.
- **Unicité (IFO-010)** : 4 migrations avec dédoublonnage préalable, `down()` fonctionnels,
  mécanismes portables MySQL 8.4/SQLite (query builder pur, `dropIndex`/`unique` Laravel,
  NULLs distincts dans l'index — exact sur les deux moteurs). La fusion des groupes évite
  bien les doublons de pivot ; les migrations rooms (100000) passent avant le dédoublonnage
  de créneaux (100300), donc les créneaux fusionnés en collision sont rattrapés.
- **Régression unicité rattrapée** : règle `unique` dans les 6 FormRequests (avec exclusion
  de l'enregistrement courant en update), `UniqueNameValidationTest`, factories passées en
  `fake()->unique()` (RoomFactory.php:24, GroupFactory.php:21, TeacherFactory.php:21).
- **IFO-011** : `lang/fr/` et `lang/en/` présents (validation, auth, passwords, pagination) ;
  `extractErrorMessage()` (errors → error → message → fallback) vérifié dans
  `Scheduler.vue:514`, `ScreenSlides.vue:136`, `SchedulerImport.vue:150` ; `gte:start_week`
  remplacé par une closure de comparaison lexicographique (`ScheduleController.php:144-157`)
  — le diagnostic « gte compare mb_strlen sur des chaînes » est exact.
- **Erreurs invisibles dans les CRUD** : `ResourceFormLayout.vue:67-76` ne met à jour
  `formKey` qu'en l'absence d'erreurs, et fusionne `pageErrors` avec celles du `<Form>`.
  Logique correcte, y compris après un échec suivi d'un succès.
- **Healthcheck Docker** : `Dockerfile:62` interroge `http://127.0.0.1:${PORT}/` au lieu de
  l'API admin de Caddy désactivée par `admin off`. Diagnostic exact.
- **Compose Coolify** : volumes nommés déclarés (storage/logs/mysql), pas d'instruction
  `VOLUME` dans le Dockerfile, `${VAR}` sans `:?`, sonde MySQL sans mot de passe —
  l'affirmation « `mysqladmin ping` renvoie 0 même sur Access denied » est conforme au
  comportement documenté de mysqladmin.
- **CI** : `lint.yml` génère Wayfinder puis exécute `lint:check`/`format:check`/`types:check` ;
  `tests.yml` tourne sur SQLite (extensions déclarées) en PHP 8.4 et 8.5. Vert à HEAD.

## Problèmes confirmés

1. **Course résiduelle sur les créneaux** — `ScheduleController.php:81` et `:98` : `store`
   et `update` s'appuient sur `slotIsOccupied()` (lire-puis-écrire) sans rattraper
   `UniqueConstraintViolationException`. Deux écritures strictement concurrentes donnent
   un 500 JSON brut au lieu d'un 422 « créneau occupé ». Gravité : faible (la contrainte
   fait son travail, le double-affichage TV est bien empêché ; seul le message est laid).
   Le fix 1f5ae46 n'a couvert que les noms, pas les créneaux.
2. **Dédoublonnage destructif au migrate** — `2026_08_20_100300...:44-64` : les doublons de
   créneau sont supprimés en gardant **le plus ancien** id, sans journalisation des ids
   supprimés ni sauvegarde préalable exigée par le guide. Choix documenté et défendable,
   mais si le doublon « récent » était la version corrigée d'un planning, elle est perdue
   silencieusement. Gravité : faible (STATUS indique une vérification sur base réelle).

## Régressions introduites

- **Aucune régression non rattrapée détectée.** Les deux régressions de la reprise
  (500 sur doublon de nom ; `:?` devenu mot de passe par défaut dans Coolify) ont été
  corrigées par 1f5ae46/3e616e0 et f8cb307, et sont consignées sans fard.
- Transaction d'import : pas de risque de verrou pour les écrans (lectures MVCC, jamais
  bloquées par InnoDB) ; seul un import concurrent d'un autre admin attendrait. Un
  dépassement de temps PHP en cours d'import provoque un rollback propre — c'est le but.
- Refactor vue-tsc (3bbfd14) et style (e1ffb84) : le gros du churn Vue vient de Prettier ;
  les fixes fonctionnels sur `Scheduler.vue`/`ScreenSlides.vue`/`SchedulerImport.vue` sont
  petits et localisés (6d2f34e : 36/24/23 lignes). Rien de comportemental détecté dans les
  fichiers sondés ; couvert par types:check + 274 tests verts.

## Écarts docs/réalité

- **Compteurs de tests périmés d'une unité** — `docs/STATUS.md:12` et `:74` annoncent
  « 273 passés, 1080 assertions » ; la CI à HEAD affiche **274 passés, 1094 assertions**
  (le test ajouté par 3e616e0 n'a pas été recompté). Les 7 ignorés (2FA via
  `markTestSkipped`) sont exacts.
- **« Treize bugs » vs 14 lignes** — le tableau « Bugs corrigés » de STATUS.md compte
  14 entrées. L'écart tient sans doute à la ligne « sonde MySQL » (hygiène de secret plus
  que bug fonctionnel), mais le texte et le tableau ne concordent pas.
- Détail cosmétique : le titre du commit baa9c6b dit « 238 tests », son corps « 253 passés ».
- Sondages exacts par ailleurs : factories `unique()`, `SlotUniquenessTest.php` présent,
  `lang/fr` présent, six FormRequests modifiées comme annoncé, entrypoints prod/dev
  conformes aux descriptions, réserve homonymes consignée dans la migration teachers.
