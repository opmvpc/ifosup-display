---
id: IFO-002
titre: Déploiement sur le serveur Coolify de l'école
statut: ouvert
priorité: haute
dépend-de: [IFO-001]
créé: 2026-08-18
mis-à-jour: 2026-08-21
---

## Contexte

Mise en ligne sur l'instance Coolify de l'école une fois la stack locale validée.

Le déploiement passe par le build pack **Docker Compose** et le fichier versionné
`docker-compose.coolify.yml`, qui décrit l'application **et** sa base MySQL 8.4, avec
leurs trois volumes persistants. Rien à déclarer dans l'interface Coolify hormis les
variables d'environnement — la configuration de persistance vit dans le dépôt et ne peut
donc pas être oubliée en recréant l'application.

Décision du 2026-08-20 : la base est dans le compose plutôt qu'en ressource Coolify
managée. Les sauvegardes intégrées de Coolify ne s'appliquent donc pas et sont prises en
charge autrement (procédure de dump dans le guide).

## Critères d'acceptation

- [ ] Application Coolify créée : source = ce dépôt GitHub, build pack = Docker Compose
      sur `docker-compose.coolify.yml`
- [ ] Les trois variables obligatoires sont renseignées : `APP_KEY` (généré pour la
      production, **différent du local**), `DB_PASSWORD`, `DB_ROOT_PASSWORD`
- [ ] `ADMIN_EMAIL` et `ADMIN_PASSWORD` renseignés pour le **premier** déploiement,
      mot de passe conforme à la politique stricte de production
- [ ] Les trois volumes sont créés par le compose (`ifosup-storage`, `ifosup-logs`,
      `ifosup-mysql`) — rien à déclarer dans l'interface
- [ ] Une sauvegarde externe de la base et du volume `ifosup-storage` est planifiée
- [ ] Domaine + certificat HTTPS actifs
- [ ] Health check Coolify sur `/` en 200
- [ ] Connexion admin OK, `/screen` OK depuis une TV du réseau

## Journal du ticket

- 2026-08-18 — création, en attente de la validation locale (IFO-001).
- 2026-08-20 — procédure réécrite. La base rejoint le compose : plus de ressource MySQL
  séparée, plus de volumes à déclarer à la main. Le nombre de variables à saisir tombe à
  trois obligatoires (plus deux pour le premier démarrage), tout le reste ayant une
  valeur par défaut adaptée à la production.
- 2026-08-21 — **premier déploiement tenté, échoué sur `container mysql-... is
  unhealthy`.** Cause réelle : `DB_ROOT_PASSWORD` vide. MySQL refuse de s'initialiser,
  `restart: unless-stopped` le relance en boucle, et Compose résume cet état par
  « unhealthy ». Reproduit à l'identique en local. Le guide gagne une section
  « Dépannage » qui traduit le message, et la sonde MySQL perd son mot de passe —
  inutile (`mysqladmin ping` renvoie 0 même sur « Access denied ») et exposé dans
  `docker inspect`. Le compose complet a été rejoué en local après correction :
  base saine, application en 200. Reste à renseigner les variables côté Coolify et
  à relancer.

