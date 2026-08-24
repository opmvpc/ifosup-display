---
id: IFO-017
titre: Médias de slides en 404 — storage:link cassé par le conteneur non-root
statut: terminé
priorité: haute
dépend-de: [IFO-015]
créé: 2026-08-24
mis-à-jour: 2026-08-24
---

## Contexte

Signalé par Thibault le 2026-08-24 : sur la production, les images de slides
répondent 404 (`/storage/screen-slides/images/…`) — aussi bien celle uploadée la
semaine précédente que celle uploadée le jour même. L'upload lui-même réussit
(POST `/slides` en 201), c'est le service du fichier qui échoue.

## Diagnostic

Régression introduite par le passage en conteneur non-root
([IFO-015](IFO-015-durcissements-differes.md)) :

- les médias sont stockés sur le disque `public` (`storage/app/public`, persisté
  par le volume `ifosup-storage`) et servis via le lien symbolique
  `public/storage` ;
- ce lien n'existe pas dans l'image (il n'est pas commité et le build ne le crée
  pas) : il était recréé à chaque démarrage par `php artisan storage:link --force`
  dans l'entrypoint ;
- depuis IFO-015, cette commande tourne en `www-data` alors que `/app/public`
  appartient à root : la création du lien échoue, et le `|| true` avalait
  l'erreur. Plus aucun `/storage/*` n'était servi.

Les fichiers eux-mêmes sont intacts sur le volume : seule la porte d'accès
manquait. La vérification d'IFO-015 (« écritures storage OK ») portait sur
l'écriture dans `storage/`, pas sur le service des médias via `public/storage` —
c'est par là que la régression est passée.

## Correctif

`docker-entrypoint.sh` : le lien est posé dans la phase root du démarrage
(`ln -sfn /app/storage/app/public /app/public/storage`), avant le `setpriv` vers
`www-data`. L'appel `artisan storage:link` de la phase www-data est retiré (il ne
peut pas écrire dans `/app/public`).

## Critères d'acceptation

- [ ] Après redéploiement, le lien `public/storage` existe dans le conteneur et
      pointe vers `storage/app/public`
- [ ] Les images de slides (anciennes comme nouvelles) sont servies en 200 via
      `/storage/screen-slides/...`
- [ ] Le serveur tourne toujours en `www-data` (le durcissement d'IFO-015 est
      conservé)
- [ ] À la première occasion : rejouer l'image sur la stack prod-like locale
      (Docker local indisponible le 2026-08-24, WSL2 en cours d'installation)

## Journal du ticket

- 2026-08-24 — création, diagnostic, correctif (lien posé au build dans le
  Dockerfile ET en phase root de l'entrypoint), push sur `main` (déploiement
  Coolify automatique). Vérification locale impossible (Docker/WSL2 en
  réinstallation).
- 2026-08-24 — **diagnostic confirmé en production** : Thibault a lancé
  `storage:link` dans le terminal du conteneur (exec = root, l'image n'a pas de
  directive `USER`) et les images s'affichent à nouveau. Ce lien manuel ne
  survivra pas au prochain redéploiement — d'où le correctif dans l'image.
