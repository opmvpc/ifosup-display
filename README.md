# 📺 IFOSUP Display

Ce projet est un **Backoffice de gestion d'affichage dynamique** réalisé dans le cadre de mon **stage**.

Il permet de piloter à distance les informations diffusées sur les télévisions de l'école (annonces, plannings des cours et actualités).

### 🛠️ Technologies

- **Framework :** Laravel + Inertia.js (Vue.js 3)
- **Style :** Tailwind CSS
- **Package Manager :** pnpm

---

### 🚀 Installation rapide

1. **Installer les dépendances PHP :**

```bash
composer install

```

2. **Installer les dépendances JS :**

```bash
pnpm install
pnpm approve-builds

```

3. **Préparer l'environnement :**

- Copier le fichier `.env.example` en `.env`
- Configurer la base de données dans le `.env`
- Lancer : `php artisan key:generate` et `php artisan migrate`

---

### 🐳 Lancer le projet avec Docker (recommandé)

Aucun prérequis hormis Docker Desktop :

```bash
docker compose up -d --build          # image de production  -> http://localhost:8080
docker compose -f docker-compose.dev.yml up --build   # dev, hot-reload -> http://localhost:8000
```

Détails, seeders et dépannage : [`docs/installation-locale.md`](docs/installation-locale.md).
Mise en ligne : [`docs/deploiement-coolify.md`](docs/deploiement-coolify.md).
État du projet et chantiers en cours : [`docs/STATUS.md`](docs/STATUS.md).

---

support@ifosup.wavre.be
