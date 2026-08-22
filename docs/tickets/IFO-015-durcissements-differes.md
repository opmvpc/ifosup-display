---
id: IFO-015
titre: Durcissements différés issus de l'audit croisé
statut: ouvert
priorité: normale
dépend-de: []
créé: 2026-08-22
mis-à-jour: 2026-08-22
---

## Contexte

L'audit croisé du 2026-08-22 ([IFO-013](IFO-013-audit-croise-reprise.md)) a relevé,
au-delà des correctifs appliqués immédiatement, une série de points réels mais non
urgents. Consignés ici pour ne pas les perdre ; aucun ne bloque la production.

## Points, par ordre d'intérêt

- [ ] **Conteneur en root** : aucune directive `USER` dans le Dockerfile,
      `frankenphp run` s'exécute en root (le `chown www-data` est sans effet). Passer
      à un utilisateur non privilégié demande de régler les permissions des volumes
      et les capacités de bind : à faire posément, avec test de déploiement.
- [ ] **En-têtes de sécurité HTTP** : ni CSP `frame-ancestors`/`X-Frame-Options`
      (login frameable), ni `X-Content-Type-Options`, ni HSTS. Deux lignes `header`
      dans le `Caddyfile` suffisent.
- [ ] **`/screen` et `/screen/data` sans rate limiting** : endpoints publics,
      ~5 requêtes SQL par hit. Ajouter un `throttle` généreux (les TV interrogent en
      boucle ; avec `trustProxies` posé, la limite s'applique par IP réelle).
      En profiter pour sérialiser explicitement la réponse (noms/codes seulement,
      pas les modèles entiers avec timestamps).
- [ ] **Sessions non invalidées au changement de mot de passe**
      (`Settings/PasswordController`, `Admin/UserController::update`) : une session
      volée survit à la rotation du mot de passe. Activer
      `AuthenticateSession` ou déconnecter les autres sessions à la main.
- [ ] **`ensureDefaultSlides()` non atomique** (`ScreenSlide.php`) : plusieurs
      requêtes anonymes simultanées sur une table vide peuvent créer les slides par
      défaut en double. Verrou, transaction + contrainte, ou tolérance assumée.
- [ ] **500 au lieu de 422 sur course de créneau** (`ScheduleController::store/update`) :
      rattraper `UniqueConstraintViolationException` et répondre « créneau occupé ».
- [ ] **Import Excel : DoS par fichier piégé** (authentifié) : 20 Mo compressés
      peuvent dépasser les 512 Mo de mémoire ; `getCalculatedValue()` évalue les
      formules ; une date malformée fait un 500 non rattrapé. Read filter, taille
      décompressée bornée, try/catch autour du parsing.
- [ ] **Reliquats 2FA** : trait `TwoFactorAuthenticatable`, contrôleur, composables et
      composants Vue non montés (dont le seul `v-html` du projet). À supprimer pour
      réduire la surface (suite d'IFO-008).
- [ ] **CI sans MySQL ni build d'image** : les tests ne tournent que sur SQLite et
      aucun workflow ne construit l'image Docker — la classe de bug IFO-006 reste
      invisible. Ajouter un job `mysql:8.4` (service container) et un `docker build`.
- [ ] **`node_modules` dans une couche de l'image** : `COPY --from=builder /app` puis
      `rm -rf` laisse ~centaines de Mo dans la couche copiée. Exclure au `COPY` ou
      supprimer dans le stage builder.
- [ ] **Stack dev sur SQLite pendant que son MySQL tourne à vide**
      (`docker-compose.dev.yml` n'injecte aucun `DB_*`, le `.env` généré vaut
      `sqlite`) : contraire à ADR-001, c'est le scénario qui a masqué IFO-006.
      Injecter les `DB_*` MySQL dans le service `app` de la stack dev.
- [ ] **Hygiène Coolify** : vider `ADMIN_EMAIL`/`ADMIN_PASSWORD` dans l'interface une
      fois le compte créé (ils restent lisibles dans l'environnement du conteneur) ;
      noter que `MYSQL_ROOT_PASSWORD` reste de toute façon visible dans
      `docker inspect` via `Config.Env` — la disparition de la sonde (2026-08-21)
      n'y change rien.

## Journal du ticket

- 2026-08-22 — création (audit croisé IFO-013).
