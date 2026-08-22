# Audit sécurité — état HEAD (2026-08-22, agent Fable)

Périmètre : code applicatif, config, Docker/CI à HEAD (`095edae`). Lecture seule.
Les points arbitrés (absence de rôles, purge_period, bulkStore, sync([])) ne sont pas re-signalés.

## Verdict global

Base saine pour un backoffice interne : pas de secret committé, mass assignment maîtrisé,
pas de SQL brut injectable, pas de XSS exploitable, uploads correctement validés, Fortify
réduit au strict minimum (login seul, throttlé). Restent deux points importants — le cookie
de session sans flag `Secure` en production et la possibilité de supprimer le dernier compte —
et une série de points mineurs (rate limiting de l'écran public, en-têtes HTTP, DoS import Excel).
Aucune vulnérabilité bloquante.

## Vulnérabilités confirmées

### Important

1. **Cookie de session sans flag `Secure` en production.**
   `config/session.php:172` lit `SESSION_SECURE_COOKIE`, jamais définie (ni dans
   `docker-compose.coolify.yml`, ni ailleurs). Aucun trusted proxy n'est configuré
   (`bootstrap/app.php`), donc derrière le Traefik de Coolify `$request->secure()` est faux
   et Laravel n'ajoutera jamais le flag de lui-même. Scénario : un attaquant en position
   réseau (wifi de l'école) force une requête `http://` vers le domaine (image traquée,
   redirection) ; le navigateur envoie le cookie de session en clair → vol de session du
   backoffice. Correctif : `SESSION_SECURE_COOKIE=true` dans le compose Coolify (+ idéalement
   `->trustProxies(at: '*')` dans `bootstrap/app.php` puisque l'app est toujours derrière Traefik).

2. **Suppression du dernier compte / de soi-même sans garde-fou.**
   `app/Http/Controllers/Admin/UserController.php:76-81` (`destroy`) supprime n'importe quel
   utilisateur, y compris l'utilisateur courant et le dernier compte existant, sans
   confirmation de mot de passe (contrairement à `ProfileController::destroy` qui l'exige).
   Registration, reset password et mail étant désactivés (`config/fortify.php` features `[]`),
   supprimer le dernier compte verrouille tout le backoffice ; la seule récupération est un
   redémarrage du conteneur (`app:create-admin-user` ne recrée un admin que si la table est
   vide, avec les variables `ADMIN_*` encore présentes). Un simple garde « impossible de
   supprimer son propre compte via /admin » + « impossible de supprimer le dernier compte »
   suffirait. (L'absence de rôles elle-même est arbitrée, pas ce cas limite.)

### Mineur

3. **`/screen/data` public, sans rate limiting.**
   `routes/web.php:16-17` : `screen` et `screen/data` sont hors middleware `auth`, sans
   `throttle`. Chaque hit exécute ~5 requêtes SQL avec eager loading
   (`ScreenController::data`, `app/Http/Controllers/ScreenController.php:35-137`) et passe
   par `ScreenSlide::ensureDefaultSlides()` (écriture potentielle). Un script trivial peut
   marteler l'endpoint depuis Internet (DoS applicatif modeste). Côté fuite : la réponse
   expose les modèles complets (Assignment + course.teacher + course.groups + room, avec ids
   et timestamps) et le `motd`. Les tables `teachers`/`groups` ne contiennent que des noms —
   affichés de toute façon sur les TV — donc pas de donnée personnelle sensible, mais un
   `->throttle` (ex. 60/min) et une sérialisation explicite (only name/code) seraient sains.

4. **Aucun en-tête de sécurité HTTP.** Ni middleware applicatif ni directive Caddy
   (`Caddyfile`) : pas de `X-Frame-Options`/CSP `frame-ancestors`, pas de
   `X-Content-Type-Options`, pas de HSTS. La page de login est notamment frameable
   (clickjacking). Deux lignes `header` dans le Caddyfile suffisent.

5. **Import Excel : DoS possible par fichier piégé, erreurs non gérées.**
   `app/Services/SchedulerSheetParser.php:21` fait `ini_set('memory_limit', '512M')` puis
   `IOFactory::load()` sans read filter : un `.xlsx` de 20 Mo très compressé (limite
   `SchedulerImportController.php:73`) peut consommer les 512 Mo et faire tomber le worker.
   `getCalculatedValue()` (`SchedulerSheetParser.php:151`) évalue les formules du fichier
   (moteur PhpSpreadsheet : DoS possible, pas d'exécution PHP). Une cellule date malformée
   fait lever `Carbon::createFromFormat` → 500 non catché dans `preview`/`executeImport`.
   Impact limité : routes authentifiées uniquement. XXE : non — PhpSpreadsheet 5.7.0
   (composer.lock), security scanner XML actif par défaut.

6. **Changement de mot de passe sans invalidation des autres sessions.**
   `Settings/PasswordController::update` et `Admin/UserController::update` changent le mot
   de passe sans déconnecter les sessions actives (middleware `AuthenticateSession` non
   activé). Un attaquant ayant volé une session la conserve après rotation du mot de passe.

## Points de vigilance non confirmés

- `ADMIN_PASSWORD`/`ADMIN_EMAIL` transitent en variables d'environnement du conteneur
  (`docker-compose.coolify.yml`) : visibles dans `docker inspect` / UI Coolify après le
  premier boot. Prévoir de vider ces champs une fois l'admin créé.
- `.env.docker.example` contient un mot de passe admin d'exemple (`ChangeMoi!2026`) et le
  compose local expose MySQL root/root sur le port hôte 33061 : acceptable en local, à ne
  jamais reproduire côté Coolify (le compose Coolify est propre sur ce point).
- `Password::defaults()` ne durcit qu'en production (`AppServiceProvider:56`) : en staging
  éventuel (`APP_ENV=staging`), aucune règle de complexité.
- Reliquats 2FA inoffensifs mais présents : trait `TwoFactorAuthenticatable` sur `User`,
  `Settings/TwoFactorAuthenticationController` + request + `TwoFactorSetupModal.vue`
  (seul `v-html` du projet, alimenté par le QR généré serveur). Non routés, features Fortify
  vides → code mort à nettoyer pour réduire la surface.

## Ce qui est sain (vérifié)

- **Secrets** : rien de committé (historique git vérifié) ; `.env`/`.env.docker` gitignorés ;
  compose Coolify en `${VAR}` sans défauts pour APP_KEY/DB_PASSWORD ; Caddy masque `.env*`/`.git`.
- **Auth** : login Fortify throttlé 5/min par email+IP ; `password.confirm` fonctionnel ;
  mot de passe ≥12 + `uncompromised()` en prod ; cast `hashed` ; `$hidden` correct ;
  registration/reset/verification désactivés (pas de routes fantômes).
- **Mass assignment** : `$fillable` strict sur les 7 modèles, contrôleurs sur `validated()`.
- **SQL** : unique `orderByRaw` statique (`ScreenController.php:63`), aucun raw avec entrée utilisateur.
- **XSS** : interpolation Vue partout (motd, cours importés d'Excel affichés échappés).
- **Uploads slides** : règle `image` (SVG exclu depuis Laravel 12, framework v12.53), vidéos
  validées par sniffing MIME, noms hachés sous `storage/app/public`, suppression propre
  (hooks modèle) ; fichiers Excel stockés sur le disque privé et supprimés après import/discard.
- **Config prod** : `APP_DEBUG=false` par défaut, HTTPS forcé hors local, CSRF actif,
  cookies chiffrés (sauf 2 cookies cosmétiques), `prohibitDestructiveCommands` en prod.
