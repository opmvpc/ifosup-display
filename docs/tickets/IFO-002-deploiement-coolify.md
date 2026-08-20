---
id: IFO-002
titre: Déploiement sur le serveur Coolify de l'école
statut: ouvert
priorité: haute
dépend-de: [IFO-001]
créé: 2026-08-18
mis-à-jour: 2026-08-18
---

## Contexte

Mise en ligne sur l'instance Coolify de l'école une fois la stack locale validée.
Coolify construit à partir du `Dockerfile` du repo et injecte les variables
d'environnement ; la base MySQL est un service Coolify séparé.

## Critères d'acceptation

- [ ] Application Coolify créée, source = ce dépôt GitHub, build = Dockerfile
- [ ] Service MySQL 8.4 provisionné et relié à l'app
- [ ] Variables d'environnement renseignées (`APP_KEY` généré et **différent** du local,
      `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://…`, bloc `DB_*`,
      `ADMIN_EMAIL` / `ADMIN_PASSWORD`)
- [ ] Volumes persistants montés sur `/app/storage/app` et `/app/storage/logs`
- [ ] Domaine + certificat HTTPS actifs
- [ ] Health check Coolify sur `/` en 200
- [ ] Connexion admin OK, `/screen` OK depuis une TV du réseau

## Journal du ticket

- 2026-08-18 — création, en attente de la validation locale (IFO-001).
