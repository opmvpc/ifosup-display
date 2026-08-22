# Review croisée de la reprise — Codex GPT-5.6 Sol, 2026-08-22

_Audit externe en sandbox read-only, rapport capturé par l'orchestrateur. Les points 1-4
ont été contre-vérifiés dans le code par l'orchestrateur avant intégration : confirmés._

## Verdict global

La majorité des correctifs d’Opus corrige bien les défauts annoncés : suppression de `/debug-excel`, transaction d’import, nettoyage des médias, validation et remontée des erreurs. La reprise n’est toutefois pas entièrement sûre : elle introduit plusieurs régressions de migration/import et laisse deux défauts de sécurité Docker mineurs.

## Problèmes confirmés

1. **Migration renommée rejouée sur une base existante — important**  
   [database/migrations/2026_03_16_140900_create_recurring_assignments_table.php:14](/Users/business_epicure/repos/ifosup-display/database/migrations/2026_03_16_140900_create_recurring_assignments_table.php:14)  
   Le commit `073e159` renomme une migration déjà susceptible de figurer dans la table `migrations`. Laravel identifie les migrations par leur nom de fichier : après mise à niveau, le nouveau nom est donc considéré comme non exécuté. Comme `2026_05_11_135528_drop_recurring_assignments.php` a déjà supprimé cette table et ne sera pas rejoué, `artisan migrate` recrée définitivement la table métier pourtant abandonnée.  
   **Reproduction :** migrer une base avec `fdfc3f2`, passer à HEAD, exécuter `php artisan migrate`, puis constater que `recurring_assignments` réapparaît et que les deux noms de migration figurent dans la table `migrations`.

2. **La fusion de trois sections homonymes peut créer des pivots dupliqués — important**  
   [database/migrations/2026_08_20_100100_add_unique_constraint_to_groups_name.php:47](/Users/business_epicure/repos/ifosup-display/database/migrations/2026_08_20_100100_add_unique_constraint_to_groups_name.php:47)  
   Le nettoyage ne supprime que les liens déjà présents sur la section canonique. Si deux sections à fusionner, autres que la canonique, sont liées au même cours, leurs deux lignes sont réaffectées à la canonique. Or `course_group` n’a aucune unicité composite : deux lignes `(course_id, group_id)` identiques subsistent, contrairement au critère d’acceptation d’IFO-010.  
   **Reproduction :** créer trois groupes de même nom ; laisser le plus ancien sans cours ; lier les deux autres au même cours ; appliquer la migration. Charger ensuite `$course->groups` : le groupe canonique apparaît deux fois.

3. **Import d’un local avec une casse différente : faux “nouveau local”, puis erreur 500 — important**  
   [app/Http/Controllers/SchedulerImportController.php:122](/Users/business_epicure/repos/ifosup-display/app/Http/Controllers/SchedulerImportController.php:122), [app/Http/Controllers/SchedulerImportController.php:261](/Users/business_epicure/repos/ifosup-display/app/Http/Controllers/SchedulerImportController.php:261)  
   MySQL utilise `utf8mb4_unicode_ci`, donc `whereIn('name', ['SALLE A'])` trouve `Salle A`. Mais `pluck('id', 'name')` conserve la casse stockée et les recherches PHP suivantes sont sensibles à la casse. L’aperçu classe alors `SALLE A` comme nouveau ; l’exécution tente de le créer et la contrainte unique ajoutée par `12c96cd` provoque une exception, convertie en réponse 500.  
   **Reproduction :** sous MySQL, créer le local `Salle A`, importer un fichier contenant `SALLE A`, sélectionner le local et exécuter l’import.

4. **Même incohérence sur les codes de cours, avec lignes silencieusement ignorées — important**  
   [app/Http/Controllers/SchedulerImportController.php:123](/Users/business_epicure/repos/ifosup-display/app/Http/Controllers/SchedulerImportController.php:123), [app/Http/Controllers/SchedulerImportController.php:269](/Users/business_epicure/repos/ifosup-display/app/Http/Controllers/SchedulerImportController.php:269)  
   Un cours `ABC` est trouvé par MySQL pour le code Excel `abc`, mais les comparaisons et indexations PHP ne correspondent plus. L’aperçu peut présenter simultanément le cours trouvé et `abc` comme inconnu ; à l’exécution, `$courses[$entry['course']]` vaut `null` et toutes les lignes concernées sont ignorées, avec une réponse de succès. Ce défaut vient du code étudiant mais devient particulièrement visible avec MySQL choisi pour la production.  
   **Reproduction :** créer le cours `ABC`, importer des cellules portant `abc`, puis constater `imported: 0` malgré la correspondance SQL.

5. **Création concurrente possible de plusieurs slides par défaut via l’endpoint public — mineur**  
   [app/Models/ScreenSlide.php:64](/Users/business_epicure/repos/ifosup-display/app/Models/ScreenSlide.php:64), [app/Http/Controllers/ScreenController.php:35](/Users/business_epicure/repos/ifosup-display/app/Http/Controllers/ScreenController.php:35)  
   `ensureDefaultSlides()` effectue `exists()` puis deux insertions sans transaction, verrou ni contrainte d’unicité. Plusieurs appels anonymes simultanés à `/screen/data` sur une table vide peuvent tous passer le contrôle et créer plusieurs slides `welcome` et `schedule`.  
   **Reproduction :** vider `screen_slides`, lancer de nombreux `GET /screen/data` en parallèle, puis compter les slides par type.

6. **MySQL local publié sur toutes les interfaces avec des identifiants connus — mineur**  
   [docker-compose.yml:27](/Users/business_epicure/repos/ifosup-display/docker-compose.yml:27), [docker-compose.yml:29](/Users/business_epicure/repos/ifosup-display/docker-compose.yml:29), [docker-compose.dev.yml:51](/Users/business_epicure/repos/ifosup-display/docker-compose.dev.yml:51), [docker-compose.dev.yml:53](/Users/business_epicure/repos/ifosup-display/docker-compose.dev.yml:53)  
   Les syntaxes `33061:3306` et `${DB_HOST_PORT:-33063}:3306` écoutent par défaut sur `0.0.0.0`, tandis que les mots de passe sont `root`/`root` et `ifosup`/`secret`. Une machine du même réseau peut donc accéder à la base si aucun pare-feu hôte ne l’interdit.  
   **Reproduction :** démarrer l’une des stacks puis, depuis une autre machine, se connecter à l’adresse du poste sur le port publié avec `root`/`root`.

7. **Le secret root reste visible dans `docker inspect` malgré le correctif annoncé — mineur**  
   [docker-compose.coolify.yml:79](/Users/business_epicure/repos/ifosup-display/docker-compose.coolify.yml:79)  
   `0d72826` retire correctement le mot de passe de la commande de healthcheck, mais pas de `Config.Env`. La justification selon laquelle le secret n’apparaîtrait plus en clair dans `docker inspect` est donc incomplète.  
   **Reproduction :** déployer avec `DB_ROOT_PASSWORD`, puis examiner `docker inspect <mysql>` : `MYSQL_ROOT_PASSWORD=<valeur>` reste présent dans `Config.Env`.

## Doutes non confirmés

- [app/Services/SchedulerSheetParser.php:20](/Users/business_epicure/repos/ifosup-display/app/Services/SchedulerSheetParser.php:20) : la limite de 20 Mo porte sur le fichier Excel compressé, sans limite sur sa taille décompressée, ses dimensions ou son nombre de feuilles. Un classeur compressé spécialement construit pourrait épuiser les 512 Mo autorisés ; cela demande une mesure avec un fichier hostile.
- [app/Http/Controllers/ScreenController.php:44](/Users/business_epicure/repos/ifosup-display/app/Http/Controllers/ScreenController.php:44) : la troncature corrige bien 23:59:59,5, mais maintient la période précédente pendant toute la seconde 12:30:00.xxx et 17:30:00.xxx à cause des bornes inclusives. Les tests consacrent ce comportement aux secondes exactes ; l’intention métier sur les microsecondes de transition n’est pas documentée.

## Régressions introduites par la reprise

- `073e159` casse le chemin de mise à niveau des bases ayant déjà enregistré l’ancien nom de migration et ressuscite `recurring_assignments`.
- `12c96cd` ne satisfait pas complètement sa promesse de fusion sans doublon pour `course_group`.
- `12c96cd`, combiné aux comparaisons sensibles à la casse de l’import, transforme une variation de casse d’un local en erreur 500.
- `524179a` introduit les publications réseau MySQL avec mots de passe statiques dans les deux nouvelles stacks locales.
- `0d72826` retire le secret de la sonde, mais ne corrige pas l’exposition à `docker inspect` invoquée comme justification de sécurité.