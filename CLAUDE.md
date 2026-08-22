# IFOSUP Display — contexte agent

Backoffice Laravel 12 + Inertia/Vue 3 qui pilote l'affichage dynamique sur les
télévisions de l'école (annonces, plannings de cours, actualités). Projet réalisé
en stage par un étudiant BES Web Developer, repris par l'école.

## Reprise de session (dans cet ordre)

1. `docs/STATUS.md` — état courant et prochaine action
2. `docs/tickets/` — un fichier par chantier
3. `docs/journal/` — le journal du jour le plus récent
4. `docs/decisions/` — les ADR (le « pourquoi »)

## Règles de travail

- **Bureaucratie** : toute étape franchie ⇒ mise à jour de `docs/STATUS.md`, du journal
  du jour et du frontmatter `mis-à-jour` des tickets touchés. Commits `docs:`.
- **Git** : l'orchestrateur commite, jamais les sous-agents. Branche par chantier.
- **Délégation** : voir le skill global `delegation`. Les sous-agents écrivent dans
  `docs/research/`, jamais dans `docs/tickets/` ni `docs/STATUS.md`.
- **Code de l'étudiant** : ne pas réécrire en masse. Corriger ciblé, ticket par ticket,
  en gardant le style existant (Pint + ESLint + Prettier font foi).

## Commandes usuelles

```bash
# Stack Docker prod-like (image identique à Coolify)  -> http://localhost:8080
docker compose up -d --build
docker compose logs -f app

# Stack Docker de dev (hot-reload Vite)               -> http://localhost:8001
docker compose -f docker-compose.dev.yml up --build

# Qualité (dans le container dev)
docker compose -f docker-compose.dev.yml exec app php artisan test
docker compose -f docker-compose.dev.yml exec app composer lint
docker compose -f docker-compose.dev.yml exec app pnpm types:check
```

## Points d'attention connus

- `AppServiceProvider::boot()` force le schéma HTTPS dès que `APP_ENV !== local` :
  garder `APP_ENV=local` pour la stack Docker locale en HTTP.
- Wayfinder génère `resources/js/{actions,routes,wayfinder}` (gitignorés) au build.
  Un build front sans PHP fonctionnel échoue.
- `GEMINI.md` et `LOGBOOK.md` sont des documents de l'étudiant (rapport de stage) :
  informatifs, pas normatifs.
