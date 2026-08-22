---
id: IFO-016
titre: Favicon de marque, carte de partage OG, nom d'application en dur
statut: terminé
priorité: normale
dépend-de: []
créé: 2026-08-22
mis-à-jour: 2026-08-22
---

## Contexte

Demandes de Thibault du 2026-08-22 :

- les favicons étaient encore ceux du starter kit Laravel, alors que l'application
  a son logo (la flèche jaune, `public/IFO_Gimmick_SUPERIEUR.png`) ;
- un lien partagé (Teams, WhatsApp…) n'avait aucune carte : ajouter des balises
  Open Graph et un visuel. Pas de SEO — application interne ;
- **le nom de l'application est codé en dur** (`config/app.php`) : l'interface de
  Coolify refuse les valeurs d'environnement contenant des espaces, `APP_NAME`
  y est donc inutilisable.

## Critères d'acceptation

- [x] `favicon.svg` (vectoriel), `favicon.ico` (32 px) et `apple-touch-icon.png`
      (180 px) régénérés depuis le logo jaune sur le fond navy de la marque
      (`#1e2d55`), via `scripts/generate-favicons.php` (rejouable)
- [x] `public/og-image.png` (1200×630) généré via `scripts/generate-og-image.php`
      (les polices ne sont pas committées : `--font-dir` pointe vers un dossier
      local contenant Arial)
- [x] Balises `og:*` + `theme-color` dans `app.blade.php`, en français, URL
      absolues via `url()`
- [x] `'name' => 'IFOSUP Display'` en dur, ligne `APP_NAME` retirée du compose
      Coolify
- [x] Vérification visuelle sur la stack locale : favicon rendu (flèche jaune sur carré arrondi navy), balises og:* servies avec URL absolues, og-image.png en 200

## Journal du ticket

- 2026-08-22 — création ; assets générés, blade et config mis à jour.
