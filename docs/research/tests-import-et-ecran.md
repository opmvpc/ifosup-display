# Plan de tests — Import Excel & Écran public

Périmètre analysé : `SchedulerImportController`, `SchedulerParserController`, `SchedulerSheetParser`, `ScreenController`, `ScreenSlideController`, `ScreenSlide`, migration `screen_slides`, routes `screen*` et `scheduler/import*`.

## Ce que font ces modules

### Écran public (`ScreenController`)

- `GET /screen` → rend simplement le composant Inertia `Screen` (pas de données, pas d'auth).
- `GET /screen/data` (nommée `screen.data`, **hors middleware auth**, donc publique) :
  1. Appelle `ScreenSlide::ensureDefaultSlides()` (crée un slide `welcome` verrouillé + un slide `schedule` si la table est vide).
  2. Calcule l'heure courante dans le fuseau `config('app.screen_timezone')` (par défaut `Europe/Brussels`, configurable via `APP_SCREEN_TIMEZONE`).
  3. Détermine la « période courante » (`morning`/`afternoon`/`evening`) en comparant `$now` à des plages horaires fixes de la journée (voir tableau ci-dessous).
  4. En déduit les « périodes à afficher » via `PERIODS_OF_INTEREST` (pas juste la période courante : le matin on montre matin+après-midi, l'après-midi on montre après-midi+soir, le soir on montre seulement le soir).
  5. Charge les `Assignment` du jour (`date = today`) filtrés sur ces périodes, avec `course.groups`, `course.teacher`, `room`, triés par période (matin/après-midi/soir) puis groupés par période.
  6. Construit la liste des slides à diffuser en dépliant (`flatMap`) chaque `ScreenSlide` :
     - `welcome` → 1 entrée avec `motd` et une durée minimale de 5000 ms.
     - `schedule` → **une entrée par période visible** (0 à 3 entrées selon l'heure).
     - `image` → 1 entrée seulement si `imageUrl()` non nul (donc si `image_path` renseigné).
     - `video` → 1 entrée seulement si `videoUrl()` non nul.
  7. Répond en JSON `{ now, timezone, slides }`.

### Gestion des slides (`ScreenSlideController` + `ScreenSlide`)

- CRUD des slides configurés par l'admin (derrière `auth`+`verified`) : `index`, `store`, `update`, `reorder`, `destroy`.
- `ensureDefaultSlides()` : si la table est vide, crée un slide `welcome` (`position=0`, `is_locked=true`) et un slide `schedule` (`position=1`, `is_locked=false`). N'agit que si la table est **totalement vide** — comme le slide welcome est verrouillé et ne peut jamais être supprimé via l'API (`destroy` bloque `is_locked`), cette méthode ne recrée jamais rien après la première fois.
- `store` : type requis parmi `schedule|image|video` (pas `welcome`, il n'y en a qu'un et il est créé par défaut). Fichier `image` requis si `type=image`, fichier `video` requis si `type=video`. Stockage sur le disque **`public`** (`screen-slides/images` ou `screen-slides/videos`). Position = max+1.
- `update` : comportement différent selon `$screenSlide->type` (branches distinctes, avec validations différentes) :
  - `welcome` → seulement `motd` (nullable, max 280).
  - `image` → `duration` requis (1000–120000 ms), `image` optionnelle (remplace le fichier si fournie) ; erreur 422 si aucune image n'existe au final.
  - `video` → `duration` optionnelle, `video` optionnelle (remplace le fichier) ; erreur 422 si aucune vidéo au final.
  - `schedule` (ou tout autre cas) → **aucune branche ne matche**, retourne directement le slide inchangé en 200 (no-op silencieux).
- `reorder` : reçoit `slide_ids` (tableau complet, doit contenir **exactement** tous les ids existants, sans doublon) ; le slide verrouillé (welcome) doit rester en première position ; sinon 422. Réassigne les `position` en transaction.
- `destroy` : refuse si `is_locked` (422). Sinon supprime les fichiers associés (disque `public`) puis renumérote séquentiellement les positions des slides restants.
- Le modèle `ScreenSlide` a des hooks `deleting`/`updating` qui suppriment aussi les anciens fichiers physiques via `Storage::delete(...)` **sans préciser de disque** (donc disque par défaut `local`, alors que les fichiers ont été stockés sur `public` par le contrôleur — voir bugs).

### Import Excel (`SchedulerImportController` + `SchedulerSheetParser`)

- Workflow en session (une seule "session d'import" active par utilisateur, clé de session `scheduler_import_pending_file` / `scheduler_import_start_year`) :
  1. `GET scheduler/import` (`index`) → affiche la page, indique si un fichier est en attente.
  2. `POST scheduler/import/upload` → valide `file` (xlsx/xls, max 20 Mo) et `start_year` (2000–2100). Supprime l'éventuel fichier précédent en session, stocke le nouveau sur le disque **par défaut** (`local`, dossier `scheduler-imports`), enregistre chemin + année en session, flash `just_uploaded`.
  3. `POST scheduler/import/preview` → relit le fichier stocké, appelle `SchedulerSheetParser::parse()`, puis calcule : plage de dates, salles/cours déjà connus vs nouveaux, conflits (créneau déjà occupé par un autre cours), breakdown par (salle, cours), comptages bruts.
  4. `POST scheduler/import/execute` → valide `selected_rooms[]`, `selected_courses[]`, `purge_period` (bool). Reparse le fichier. Purge optionnelle de **toutes** les `Assignment` dans la plage de dates du fichier (indépendamment des salles/cours sélectionnés). Crée les salles sélectionnées manquantes. Pour chaque ligne parsée dont la salle ET le cours sont dans la sélection : `update` si un `Assignment` existe déjà sur ce créneau (date+period+room), sinon `create`. Supprime le fichier et la session à la fin.
  5. `DELETE scheduler/import/discard` → supprime le fichier en attente et la session.
- `SchedulerParserController::debug` est une route de debug (`/debug-excel`, hors périmètre de test unitaire — utilise `dd()`, pas testable proprement, à ignorer).

### Format attendu par `SchedulerSheetParser`

Le parser scanne chaque feuille ligne par ligne à la recherche d'une **ligne d'en-tête** : une cellule contenant (après normalisation minuscule + suppression des accents é/è/ê/ë uniquement) le mot `matin`, `midi` ou `soir` → mappé respectivement sur `morning`/`afternoon`/`evening`.

Une fois un en-tête trouvé en colonne `C` à la ligne `R` :
- La ligne des **dates** est `R+1`, en partant de la colonne `C` (même colonne que le mot-clé) jusqu'à la dernière colonne. Seule la **première cellule non vide** de cette ligne doit contenir une date exploitable au format texte `JJ/MM` (elle est concaténée avec `/{annee}` puis parsée avec le format Carbon `d/m/Y`). Les colonnes suivantes non vides **n'utilisent pas leur propre contenu** comme date : chaque colonne non vide reçoit simplement `date_précédente + 1 semaine`. Une colonne vide est ignorée (pas de date associée, pas de décalage de semaine supplémentaire pour la suite).
- Le bloc de données commence à `R+3` (donc une ligne est sautée entre la ligne des dates et le début des données).
- Dans le bloc de données, la colonne « local/salle » est celle juste à gauche de l'en-tête (`C-1`). Une cellule multi-lignes n'utilise que sa première ligne (`explode("\n")[0]`) comme nom de salle.
- Si la cellule locale est fusionnée sur plusieurs lignes (merge range), le parseur ne lit les cours que sur la **première ligne** de la fusion, puis saute directement à la ligne suivant la fusion (les lignes intermédiaires ne sont jamais lues, même si elles contiennent des données dans d'autres colonnes).
- Fin de bloc : dès que deux lignes consécutives ont une cellule "local" vide, le bloc s'arrête (`break`). Une seule ligne vide suivie d'une ligne non vide est simplement sautée (considérée comme un séparateur).
- Pour chaque cellule "cours" non vide dans une colonne qui a une date mappée, on émet une entrée `{date, period, local, course}`. Si la colonne n'a pas de date mappée (vide dans la ligne des dates), la cellule cours est ignorée silencieusement.
- Plusieurs blocs matin/midi/soir peuvent se succéder verticalement dans la même feuille, et toutes les feuilles du classeur sont fusionnées.

## Règles métier et cas limites

### Calcul des périodes (`ScreenController::data`) — code sensible au temps

| Période | Plage horaire (bornes incluses) | Périodes affichées (`PERIODS_OF_INTEREST`) |
|---|---|---|
| morning | 00:00:00 → 12:30:00 | morning, afternoon |
| afternoon | 12:30:00 → 17:30:00 | afternoon, evening |
| evening | 17:30:00 → 23:59:59 | evening (seul) |

Points à tester explicitement avec `$this->travelTo(...)` :
- Juste avant/après chaque frontière (12:29:59 / 12:30:00 / 12:30:01, 17:29:59 / 17:30:00 / 17:30:01).
- Aux bornes exactes 00:00:00 et 23:59:59.
- Le fuseau horaire configuré (`config(['app.screen_timezone' => ...])`) doit être respecté même si le serveur tourne en UTC — tester avec un `$now` UTC qui tombe dans une période différente une fois converti en `Europe/Brussels` (ex. décalage hiver/été).
- Asymétrie voulue : le matin on affiche matin+après-midi, l'après-midi on affiche après-midi+soir, mais le soir on n'affiche QUE le soir (pas le lendemain matin). À couvrir par un test dédié par créneau.
- Seules les `Assignment` du jour courant (`date = today`) sont prises en compte — un assignment de la veille ou du lendemain ne doit jamais apparaître.
- Le tri des lignes suit l'ordre matin→après-midi→soir via `orderByRaw`, indépendamment de l'ordre d'insertion.

### Slides

- `ensureDefaultSlides()` ne doit rien faire si au moins un slide existe déjà (peu importe lequel).
- Un slide `image` sans `image_path` n'apparaît pas du tout dans le flux (pas d'entrée vide). Idem pour `video`.
- Un slide `schedule` génère 0 entrée si aucune période n'est visible (créneau limite type 23:59:59 exact avec microsecondes, cf bug ci-dessous), 1 à 3 entrées sinon.
- `reorder` exige la liste **complète** des ids (pas un sous-ensemble), sans doublon, avec le slide verrouillé en première position.
- `destroy` : jamais sur `is_locked=true` ; renumérotation séquentielle 0..N-1 après suppression.
- `store`/`update` : bornes de `duration` (1000–120000 ms) à tester aux limites (999 refusé, 1000 accepté, 120000 accepté, 120001 refusé).

### Import Excel

- Un seul fichier "en attente" par session ; un nouvel upload écrase (supprime) le précédent.
- `preview`/`execute` sans fichier en attente (session vide ou fichier disparu du disque) → 422 `{error: "Aucun fichier en attente."}`.
- Conflit détecté uniquement si la salle existe déjà en base ET que le cours en base sur ce créneau diffère du cours importé (même cours = pas un conflit, c'est un "replace" identique).
- `purge_period` supprime **toutes** les `Assignment` de la plage de dates du fichier importé, y compris celles de salles/cours non sélectionnés — comportement large à valider explicitement.
- Une ligne dont la salle ou le cours sélectionné n'est finalement pas résolu en base (`$roomId`/`$courseId` null) est silencieusement ignorée (`continue`), sans compteur dédié (pas comptée dans `imported`, `replaced` ni `purged`).
- `existing_rooms`/`new_rooms` : une salle est "nouvelle" si son nom n'existe pas en base au moment du `preview`.
- `unknown_courses` : codes cours présents dans le fichier mais absents de la table `courses` — non créés automatiquement par `execute` (les cours doivent déjà exister ; contrairement aux salles).

## Bugs ou points douteux repérés

1. **Bug potentiel — trou d'une fraction de seconde dans le calcul de période.** `PERIOD_TIMES['evening']` se termine à `23:59:59` et `morning` recommence à `00:00:00`. Si `$now` a une composante microseconde (ex. `23:59:59.500000`), `Carbon::between()` (bornes incluses par défaut) échoue pour les trois périodes car la borne de comparaison `Carbon::createFromTimeString('23:59:59', ...)` n'a pas de microsecondes → `$now` lui est strictement supérieur. Résultat : `$currentPeriodKey` est `false`, `visiblePeriods = []`, **aucun horaire n'est affiché à l'écran pendant ~1 seconde chaque jour**. À couvrir par un test `travelTo('23:59:59.500000')`.
2. **Bug de disque incohérent — suppression de fichiers de slides.** `ScreenSlideController` stocke les images/vidéos sur le disque `public` (`->store(..., 'public')`), mais les hooks `deleting`/`updating` du modèle `ScreenSlide` appellent `Storage::delete($path)` **sans préciser de disque**, donc sur le disque par défaut (`local`, cf `config/filesystems.php` → `FILESYSTEM_DISK`, défaut `local`). Si `FILESYSTEM_DISK` ≠ `public`, les anciens fichiers physiques ne sont jamais réellement supprimés du disque `public` (ils restent orphelins), alors que le contrôleur, lui, appelle bien `Storage::disk('public')->delete(...)` dans `destroy()`. Seuls les hooks du modèle (déclenchés sur update d'image/vidéo, et sur delete via Eloquent direct hors du contrôleur) sont concernés. À tester : un `update` qui remplace une image doit vérifier que l'ancien fichier disparaît bien du disque `public` — ce test est susceptible d'échouer et de révéler le bug.
3. **Comportement silencieux sur `update` d'un slide `schedule`.** Aucune des trois branches (`welcome`/`image`/`video`) ne matche pour `type=schedule` : la route répond 200 avec le slide inchangé, sans erreur ni validation, quel que soit le payload envoyé. Peut surprendre côté frontend si un jour on tente d'ajouter des champs éditables au slide schedule. À figer par un test de non-régression.
4. **`purge_period` a une portée plus large que la sélection.** Il supprime tous les assignments de la période, y compris ceux de salles/cours non cochés dans l'import. Comportement potentiellement dangereux/surprenant à documenter et tester explicitement (ne pas supposer que c'est un bug sans confirmation produit, mais le couvrir par un test qui matérialise ce comportement).
5. **Le parser ignore le contenu réel des cellules de date après la première.** Seule la première date de chaque bloc matin/midi/soir est réellement lue ; toutes les suivantes sont *déduites* (`+1 semaine`) et leur contenu textuel n'est jamais vérifié. Si le fichier Excel contient une semaine sautée (vacances) représentée par une vraie date en colonne mais pas par `+1 semaine` exact, le parseur produira une date fausse silencieusement. Point à documenter comme limite connue, à figer par un test qui prouve ce comportement (plutôt qu'un bug à corriger dans ce périmètre).
6. **Cellules "local" fusionnées sur plusieurs lignes : les lignes intermédiaires ne sont jamais parcourues.** Si un fichier réel a une salle fusionnée sur 3 lignes avec des cours différents sur chaque ligne (plutôt que le même local répété), seules les données de la première ligne du merge seraient importées — les 2 lignes suivantes disparaissent silencieusement. À vérifier avec le métier si c'est le format réel ; en attendant, à couvrir par un test qui documente ce comportement actuel.
7. **`normalize()` ne supprime que les accents `é/è/ê/ë`.** Un en-tête écrit avec un `à` ou une majuscule accentuée différente ne casserait rien ici (aucun mot-clé n'en contient), mais c'est fragile si le vocabulaire du fichier source change.
8. **Aucune vérification d'autorisation par utilisateur sur le fichier en session d'import** au-delà de la session Laravel elle-même (comportement standard, pas un bug, mais à garder en tête si un jour l'admin utilise plusieurs onglets/navigateurs).
9. **Route `/debug-excel`** (`SchedulerParserController::debug`) est **non protégée par `auth`** (elle est déclarée après le groupe middleware, à la ligne 59 de `routes/web.php`) et lit un fichier fixe `storage/app/planning.xlsx` avec `dd()`. C'est une route de debug qui fuit potentiellement des données de planning sans authentification. Hors périmètre strict de test (ne fait pas partie de la liste demandée) mais mérite d'être signalé — recommandé de la retirer avant mise en prod, indépendamment du plan de tests.

## Plan de tests

### `tests/Feature/Screen/ScreenDataTest.php`

1. `it("crée les slides par défaut au premier appel de /screen/data")` — DB vide de `screen_slides`, appelle `GET /screen/data`, vérifie que 2 lignes existent (`welcome` position 0 verrouillé, `schedule` position 1) et que la réponse contient bien un slide `welcome` et au moins un `schedule`.
2. `it("n'ajoute pas de slides par défaut si des slides existent déjà")` — crée un slide `image` seul en base, appelle la route, vérifie que le nombre total de slides n'a pas changé (pas de recréation du welcome/schedule).
3. `it("n'affiche que les cours du matin et de l'après-midi le matin")` — `travelTo` 09:00 Europe/Brussels, crée des `Assignment::factory()` pour aujourd'hui sur les 3 périodes + un pour demain, appelle la route, vérifie que le JSON contient des slides `schedule` uniquement pour `morning`/`afternoon` (2 entrées schedule par slide `schedule` en base), et que les lignes "evening" et celles de demain sont absentes.
4. `it("n'affiche que les cours de l'après-midi et du soir l'après-midi")` — `travelTo` 14:00, mêmes assertions adaptées (afternoon+evening).
5. `it("n'affiche que les cours du soir après 17h30")` — `travelTo` 18:00, vérifie qu'un seul groupe "evening" est renvoyé, pas de morning/afternoon.
6. `it("respecte les bornes exactes des créneaux (12:30:00 et 17:30:00)")` — `travelTo('12:30:00')` doit être classé "morning" (première correspondance dans l'ordre du tableau) ; `travelTo('12:30:01')` doit être "afternoon" ; `travelTo('17:30:00')` doit être "afternoon" ; `travelTo('17:30:01')` doit être "evening".
7. `it("n'affiche aucun horaire pendant le creux de minuit avec microsecondes")` — `travelTo(Carbon::parse('today 23:59:59.500000'))`, vérifie que les slides `schedule` ne produisent aucune entrée (documente le bug n°1 ; si corrigé plus tard, ce test devra être mis à jour).
8. `it("respecte le fuseau horaire configuré pour l'écran")` — `config(['app.screen_timezone' => 'Pacific/Kiritimati'])` (ou tout fuseau très décalé d'UTC), `travelTo` une heure UTC qui, une fois convertie, tombe dans une période différente de celle qu'on aurait avec UTC ; vérifie que le calcul utilise bien le fuseau configuré (comparer avec le comportement sans override).
9. `it("ne montre que les assignments du jour courant")` — crée des assignments hier/aujourd'hui/demain à la même heure/période, vérifie que seuls ceux d'aujourd'hui apparaissent.
10. `it("trie les groupes de périodes dans l'ordre matin, après-midi, soir")` — à une heure où 2-3 périodes sont visibles, vérifie l'ordre des clés `key`/`title` dans le tableau `slides` (ou plus simplement que les groupes "schedule" apparaissent dans le bon ordre relatif).
11. `it("inclut course.teacher, course.groups et room dans les lignes de planning")` — crée un assignment avec teacher, groupes multiples et room, vérifie la présence des champs imbriqués dans le JSON.
12. `it("n'affiche pas un slide image sans fichier")` — crée un `ScreenSlide` type image avec `image_path = null`, vérifie qu'aucune entrée `type=image` n'apparaît dans `slides`.
13. `it("affiche un slide image avec sa durée et son url")` — `image_path` renseigné, `duration=8000`, vérifie l'entrée générée (`data.duration === 8000`, `data.src` non vide).
14. `it("n'affiche pas un slide video sans fichier")` — analogue au 12 pour vidéo.
15. `it("affiche le motd du slide welcome")` — vérifie que `data.motd` du slide welcome correspond à la valeur en base.
16. `it("l'écran /screen est accessible sans authentification")` — `GET /screen` sans `actingAs`, `assertOk()`.
17. `it("/screen/data est accessible sans authentification")` — idem, `assertOk()` + structure JSON de base (`now`, `timezone`, `slides`).

### `tests/Feature/Screen/ScreenSlideControllerTest.php`

18. `it("refuse l'accès aux routes de gestion des slides sans authentification")` — pour chaque route (`index`, `store`, `update`, `reorder`, `destroy`), vérifie une redirection vers login (ou 302/401 selon le middleware `auth`).
19. `it("liste les slides existants triés par position")` — crée plusieurs slides avec positions désordonnées en base, `actingAs`, `GET screen/slides`, vérifie l'ordre Inertia props.
20. `it("crée un slide image avec un fichier valide")` — `Storage::fake('public')`, `POST screen/slides` avec `UploadedFile::fake()->image(...)`, vérifie 201, fichier stocké, `duration` par défaut 5000 si non fourni.
21. `it("refuse la création d'un slide image sans fichier")` — 422 avec message "Une image est requise...".
22. `it("refuse la création d'un slide video sans fichier")` — 422 équivalent.
23. `it("refuse un type de slide invalide")` — `type=welcome` (ou `foo`) → 422 (règle `in:schedule,image,video`).
24. `it("refuse une durée hors bornes à la création")` — `duration=500` → 422 ; `duration=200000` → 422.
25. `it("valide le type mime et la taille de la vidéo")` — fichier `.exe` déguisé ou mimetype non autorisé → 422 ; fichier > 300 Mo → 422 (peut être simulé avec `UploadedFile::fake()->create('x.mp4', 307201)`).
26. `it("met à jour le motd du slide welcome")` — `PATCH screen/slides/{welcome}` avec `motd`, vérifie la persistance ; `motd` trop long (>280) → 422.
27. `it("met à jour la durée et remplace l'image d'un slide image, en supprimant l'ancien fichier")` — vérifie que `Storage::disk('public')->assertMissing(oldPath)` et `assertExists(newPath)` (ce test peut révéler le bug n°2 selon où la suppression a lieu — ici c'est le contrôleur qui supprime explicitement sur `public`, donc devrait passer ; à bien distinguer du test suivant).
28. `it("supprime l'ancien fichier du disque public quand le modèle déclenche le hook updating")` — scénario ciblé sur le hook Eloquent (`ScreenSlide::update()` appelé directement, hors contrôleur, ou via `update` sur un slide video) pour vérifier concrètement sur quel disque `Storage::delete` agit ; ce test est celui qui doit démontrer/confirmer le bug n°2.
29. `it("refuse de mettre à jour un slide image sans laisser au moins une image")` — cas impossible normalement (image_path déjà présent), mais vérifie la garde `if (!$nextImagePath)`.
30. `it("ne modifie rien sur un update de slide schedule (no-op silencieux)")` — `PATCH` avec un payload arbitraire sur le slide schedule, vérifie 200 et absence de changement (documente le bug/point douteux n°3).
31. `it("réordonne les slides en respectant la contrainte du slide verrouillé en premier")` — `PATCH screen/slides/order` avec un ordre valide (welcome en premier), vérifie les nouvelles positions.
32. `it("refuse un réordonnancement qui ne met pas le slide verrouillé en premier")` — 422 message dédié.
33. `it("refuse un réordonnancement avec une liste incomplète ou avec doublons")` — deux cas : liste partielle → 422 ; id dupliqué → 422.
34. `it("refuse de supprimer le slide verrouillé")` — `DELETE` sur welcome → 422, slide toujours présent.
35. `it("supprime un slide non verrouillé et renumérote les positions restantes")` — crée 4 slides, supprime le 2e, vérifie `position` séquentielle 0..2 sur les 3 restants.
36. `it("supprime les fichiers physiques associés lors de la suppression d'un slide image/video")` — `Storage::fake('public')`, vérifie `assertMissing`.

### `tests/Feature/Scheduler/SchedulerImportUploadTest.php`

37. `it("refuse l'accès aux routes d'import sans authentification")` — pour `index`, `upload`, `preview`, `execute`, `discard`.
38. `it("upload un fichier xlsx valide et le place en session")` — `Storage::fake()` (disque par défaut/local), `POST scheduler/import/upload` avec `UploadedFile::fake()->create('planning.xlsx', 100)` et `start_year=2025`, vérifie redirection, `session()->has(...)`, fichier stocké.
39. `it("refuse un fichier qui n'est pas xlsx/xls")` — `.pdf` ou `.csv` → 422 (redirection avec erreurs de validation Inertia/session).
40. `it("refuse un fichier de plus de 20 Mo")` — `UploadedFile::fake()->create('planning.xlsx', 20481)`.
41. `it("refuse une start_year hors bornes")` — `1999` et `2101` → erreurs de validation.
42. `it("remplace le fichier précédent lors d'un nouvel upload dans la même session")` — upload A puis upload B, vérifie que le fichier A a été supprimé du disque et que la session pointe vers B.
43. `it("indexe hasPendingFile correctement selon l'état de la session")` — sans upload → `false` ; après upload → `true` ; si le fichier a été supprimé manuellement du disque entre-temps → `false` (et la clé de session est nettoyée).

### `tests/Feature/Scheduler/SchedulerImportPreviewTest.php`

44. `it("retourne une erreur 422 si aucun fichier n'est en attente")` — appel direct de `preview` sans upload préalable.
45. `it("calcule correctement la plage de dates et les comptages à partir du fichier")` — upload d'une fixture connue (voir section fixture), `POST .../preview`, vérifie `total`, `date_from`, `date_to`, `room_counts`, `course_counts` attendus précisément (valeurs calculées à la main depuis la fixture).
46. `it("distingue les salles existantes des nouvelles salles")` — crée en base une `Room` dont le nom correspond à une salle de la fixture, vérifie qu'elle apparaît dans `existing_rooms` et pas dans `new_rooms`, et que les autres apparaissent dans `new_rooms`.
47. `it("distingue les cours connus des cours inconnus")` — crée un `Course` avec un `code` présent dans la fixture, vérifie `known_courses` vs `unknown_courses`.
48. `it("détecte un conflit quand le créneau est déjà occupé par un autre cours")` — crée une `Room` existante + un `Assignment` sur une date/période/salle présente dans la fixture avec un cours différent de celui du fichier, vérifie que l'entrée apparaît dans `conflicts` avec `course_current` et `course_new` corrects.
49. `it("ne signale pas de conflit si le cours en base est identique à celui importé")` — même setup que 48 mais avec le même code cours → `conflicts` vide pour ce créneau.
50. `it("ne signale jamais de conflit pour une salle qui n'existe pas encore en base")` — salle nouvelle → jamais dans `conflicts` même si un `Assignment` existe ailleurs.
51. `it("calcule le breakdown par salle et par cours avec le compte de conflits")` — vérifie la structure `breakdown` (room, course, count, conflict_count) sur un cas simple avec 1 conflit connu.

### `tests/Feature/Scheduler/SchedulerImportExecuteTest.php`

52. `it("retourne une erreur 422 si aucun fichier n'est en attente")`.
53. `it("crée les nouvelles salles sélectionnées et importe les assignments")` — fixture avec 1 salle inconnue + 1 cours connu, sélectionne tout, vérifie `Room::where('name', ...)->exists()`, `Assignment::count()` et la réponse `imported`.
54. `it("ignore silencieusement les lignes dont la salle ou le cours sélectionné n'existe pas en base")` — sélectionne un `selected_courses` avec un code qui n'est PAS dans `known_courses` (jamais créé côté cours), vérifie qu'aucun assignment n'est créé pour ces lignes et que ce n'est compté nulle part (ni imported ni erreur) — documente le point n°/comportement silencieux.
55. `it("remplace un assignment existant sur le même créneau (date+period+room) plutôt que d'en créer un doublon")` — pré-crée un `Assignment` sur un créneau présent dans la fixture avec un autre cours, exécute l'import, vérifie qu'il n'y a toujours qu'un seul assignment sur ce créneau, que son `course_id` a changé, `status` repassé à `planned`, et que le compteur `replaced` est incrémenté (pas `imported`).
56. `it("purge tous les assignments de la période importée quand purge_period est vrai, même hors sélection")` — pré-crée un assignment sur une salle/cours de la fixture NON sélectionné mais dans la plage de dates du fichier, `purge_period=true`, vérifie qu'il est supprimé quand même (documente le comportement n°4) et que `purged` reflète le nombre supprimé.
57. `it("ne purge rien quand purge_period est absent ou faux")` — assignments hors sélection dans la plage doivent survivre.
58. `it("supprime le fichier et nettoie la session après un import réussi")` — vérifie `Storage::assertMissing($path)` et `session()->missing(...)` après `execute`.
59. `it("ne recrée pas une salle déjà existante lors de l'exécution")` — pré-crée la `Room`, vérifie qu'aucun doublon n'est créé (toujours `Room::where('name', ...)->count() === 1`).

### `tests/Feature/Scheduler/SchedulerImportDiscardTest.php`

60. `it("supprime le fichier en attente et nettoie la session")` — upload puis `DELETE scheduler/import/discard`, vérifie `Storage::assertMissing`, session vidée, redirection vers la page d'import.
61. `it("ne plante pas si aucun fichier n'est en attente")` — appel direct sans upload préalable, doit rediriger sans erreur.

### `tests/Unit/Services/SchedulerSheetParserTest.php`

62. `it("parse un bloc simple matin/midi/soir avec les bonnes dates, salles et cours")` — sur la fixture minimale (voir section suivante), vérifie le tableau exact retourné par `parse()` (nombre d'entrées, valeurs `date`/`period`/`local`/`course` pour chaque ligne attendue).
63. `it("déduit les dates des semaines suivantes en ajoutant 7 jours à partir de la première date, indépendamment du contenu des autres cellules")` — fixture avec une 2e colonne de date contenant une valeur "fausse"/différente, vérifie que la date retenue est bien `première_date + 7 jours` et pas la valeur de la cellule.
64. `it("ignore une colonne de la ligne de dates si elle est vide, sans décaler les semaines suivantes")` — fixture avec un trou dans la ligne de dates (colonne vide entre deux dates), vérifie que la date de la colonne suivante est `date_précédente + 7 jours` (pas +14) et qu'aucune entrée n'est produite pour la colonne vide même si la ligne "cours" a une valeur à cette colonne.
65. `it("ne garde que la première ligne d'un nom de salle multi-lignes")` — cellule locale `"Salle 101\nAnnexe"`, vérifie que `local === "Salle 101"`.
66. `it("n'importe que la première ligne d'une cellule salle fusionnée sur plusieurs lignes et saute les lignes suivantes")` — fixture avec merge sur 2-3 lignes, cours différents sur chaque ligne du merge, vérifie que seules les valeurs de la première ligne apparaissent dans le résultat (documente le bug/point douteux n°6).
67. `it("arrête le bloc de données après deux lignes vides consécutives sur la colonne locale")` — vérifie que les données au-delà de la coupure ne sont pas incluses.
68. `it("saute une ligne locale vide isolée si la ligne suivante contient une valeur")` — vérifie que ce n'est pas traité comme fin de bloc.
69. `it("traite plusieurs blocs matin/midi/soir empilés dans la même feuille")` — fixture avec 2 ou 3 en-têtes verticalement, vérifie que toutes les entrées des différents blocs sont retournées avec le bon `period`.
70. `it("fusionne les données de plusieurs feuilles du classeur")` — fixture à 2 feuilles, vérifie que `parse()` retourne les entrées des deux.
71. `it("reconnaît les en-têtes insensibles à la casse et aux accents é/è/ê/ë")` — cellule `"Matin"`, `"MIDI"`, `"soir"`, `"Après-midi"` (si applicable — attention "après-midi" ne contient pas "midi" en tant que mot isolé mais contient bien la sous-chaîne "midi", donc devrait matcher ; à vérifier et documenter dans le test) → tous reconnus correctement.
72. `it("ignore une cellule cours non vide dans une colonne sans date mappée")` — vérifie qu'aucune entrée n'est produite pour cette colonne.
73. `it("retourne un tableau vide pour une feuille sans en-tête reconnu")`.

Total proposé : **73 tests** répartis sur 4 fichiers Feature (Screen data, Screen slides, Import upload/preview/execute/discard) + 1 fichier Unit (parser).

## Fixture Excel à créer

Créer `tests/Fixtures/scheduler/planning-minimal.xlsx` (nom court, versionné en binaire dans le repo). Le construire avec PhpSpreadsheet dans un script one-shot (ou dans un `beforeAll`/`beforeEach` de test qui génère un fichier temporaire — **préférable** pour rester 100% déclaratif et éviter de committer un binaire opaque, mais un vrai fichier `.xlsx` de fixture reste utile pour un test d'intégration "bout en bout" réaliste). Recommandation : privilégier une fixture **générée par du code de test** (helper `SchedulerSheetParserTest` ou un trait `CreatesSchedulerFixture`) pour que le contenu soit lisible et auditable dans le diff, plutôt qu'un `.xlsx` binaire versionné — mais si l'équipe préfère un vrai fichier, voici le contenu exact à y mettre :

**Feuille 1 (nom libre, ex. "Planning")** :

| Ligne | Col A (local, colonne C-1) | Col B (en-tête, colonne C) | Col C | Col D | Col E |
|---|---|---|---|---|---|
| 1 | *(vide)* | `Matin` | | | |
| 2 | *(vide)* | `08/09` | `15/09` | *(vide)* | `29/09` |
| 3 | *(vide, ligne sautée — dataStartRow = header+3)* | | | | |
| 4 | `Salle 101` | `MATH-1` | `MATH-1` | | `PHYS-2` |
| 5 | `Salle 102` | `INFO-3` | *(vide)* | | `INFO-3` |
| 6 | *(vide)* | | | | |
| 7 | *(vide — 2e ligne vide consécutive = fin du bloc)* | | | | |
| 8 | *(vide)* | `Midi` | | | |
| 9 | *(vide)* | `08/09` | `15/09` | | |
| 10 | *(vide)* | | | | |
| 11 | `Salle 101` | `ANGL-2` | `ANGL-2` | | |
| 12 | *(vide)* | | | | |
| 13 | *(vide)* | | | | |

Notes de construction précises :
- En-tête colonne = colonne C (index 3) pour le premier bloc, donc colonne locale = colonne B (index 2). Adapter si on préfère commencer en colonne B pour l'en-tête (colonne locale = A) — peu importe tant que c'est cohérent, ce que le test doit fixer.
- Ligne des dates = ligne juste après l'en-tête, mêmes colonnes. Colonne C (première date) = texte ou date formatée donnant `"08/09"` via `getCalculatedValue()` (le plus fiable en test est d'écrire une **chaîne texte** `"08/09"` plutôt qu'une vraie date Excel, pour ne pas dépendre du format d'affichage cellule). Colonne D = `"15/09"` (valeur ignorée par le parseur mais doit être non vide — mettre volontairement une valeur incohérente comme `"01/01"` dans un test dédié pour prouver qu'elle est ignorée). Colonne E = laissée vide dans un des scénarios pour tester le "trou", puis non vide dans un autre pour tester `+2 semaines` groupées correctement (prevDate+7 seulement, colonne vide non comptée).
- `dataStartRow = headerRow + 3` : bien laisser une ligne complètement vide entre la ligne des dates et la première ligne de données (sinon la première ligne de données serait mal alignée).
- Une salle fusionnée sur 2 lignes (ex. `Salle 103` fusionnée lignes 4-5 dans un scénario dédié) avec des cours différents à chaque ligne, pour matérialiser le bug n°6.
- Un second onglet minimal (2e feuille) avec un seul bloc "Soir" pour tester la fusion multi-feuilles.
- `start_year` de test = `2025`, donc les dates attendues sont `2025-09-08`, `2025-09-15`, `2025-09-29` (ISO), calculées via `Carbon::createFromFormat('d/m/Y', '08/09/2025')`.

Pour les tests Feature d'upload (`SchedulerImportUploadTest`), pas besoin d'un contenu Excel valide : `UploadedFile::fake()->create('planning.xlsx', 100)` suffit puisque seule la validation de type MIME/taille est testée (le parsing réel n'est déclenché qu'au `preview`/`execute`). Pour les tests `preview`/`execute`, il faut en revanche un vrai contenu XLSX valide — soit la fixture binaire ci-dessus, soit un helper qui génère le fichier à la volée avec PhpSpreadsheet dans un dossier temporaire (`storage_path('framework/testing/...')` ou le répertoire scratch du test) puis le pousse dans le disque fake avant l'appel HTTP.

## Factories à créer

- **`ScreenSlideFactory`** (`database/factories/ScreenSlideFactory.php`) — **manquante**, indispensable pour la quasi-totalité des tests Screen/ScreenSlide listés ci-dessus. Le modèle `ScreenSlide` utilise déjà `HasFactory` mais aucun fichier factory n'existe dans `database/factories/`. Prévoir des états (`Factory::state`) pratiques :
  - état par défaut `type=schedule`, `position` auto-incrémenté, `is_locked=false`.
  - `welcome()` → `type=welcome`, `is_locked=true`, `motd` généré.
  - `image()` → `type=image`, `image_path` factice (ou `UploadedFile::fake()` géré au niveau du test plutôt que de la factory), `duration=5000`.
  - `video()` → `type=video`, `video_path` factice, `duration` nullable.
  - `locked()` → force `is_locked=true` (utile pour tester la contrainte de position 1re place indépendamment du type).
- Les factories `AssignmentFactory`, `CourseFactory`, `RoomFactory`, `TeacherFactory`, `GroupFactory`, `UserFactory` existent déjà et couvrent les besoins du périmètre (Screen data notamment). Aucun ajout requis pour elles, mais noter pour les tests de "période courante" qu'il faudra forcer explicitly `date` et `period` sur `Assignment::factory()->create([...])` plutôt que de laisser les valeurs aléatoires par défaut.
- Pas de factory nécessaire pour `SchedulerSheetParser` (service, pas un modèle) — la donnée d'entrée est le fichier Excel, pas une factory Eloquent.
- Envisager un **helper/trait de test** (pas une factory Eloquent à proprement parler) pour générer une fixture XLSX à la volée avec PhpSpreadsheet, réutilisable entre `SchedulerSheetParserTest` et les tests Feature d'import — évite de dupliquer la construction du classeur dans chaque test.
