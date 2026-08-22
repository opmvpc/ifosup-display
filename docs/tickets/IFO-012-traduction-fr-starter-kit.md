---
id: IFO-012
titre: Traduire en français les pages du starter kit Laravel
statut: terminé
priorité: normale
dépend-de: []
créé: 2026-08-22
mis-à-jour: 2026-08-22
---

## Contexte

Le métier de l'application est en français, mais les pages héritées du starter kit
Laravel (Vue starter kit) étaient restées en anglais : paramètres du profil, mot de
passe, apparence, suppression de compte, confirmation de mot de passe, menu
utilisateur. Demande de Thibault du 2026-08-22 (capture d'écran de la page
« Profile settings » à l'appui).

## Critères d'acceptation

- [x] `settings/Profile.vue`, `settings/Password.vue`, `settings/Appearance.vue`
      entièrement en français (titres, labels, placeholders, boutons, « Saved. »)
- [x] `DeleteUser.vue` (encadré danger + modal de confirmation) en français
- [x] `auth/ConfirmPassword.vue` en français
- [x] `layouts/settings/Layout.vue` (nav Profil / Mot de passe / Apparence) en français
- [x] `UserMenuContent.vue` (« Settings », « Log out ») en français
- [x] `AppearanceTabs.vue` (Light/Dark/System → Clair/Sombre/Système)
- [x] Chaînes d'accessibilité sr-only des composants ui (Close, More, Toggle
      sidebar, Sidebar, Navigation menu) en français
- [x] `format:check`, `lint:check`, `types:check` et `php artisan test` verts
      (277 passés, 1114 assertions)
- [x] Vérification visuelle dans le navigateur (stack dev) : profil, mot de passe,
      apparence, modal de suppression, menu utilisateur — tout en français

## Hors périmètre

- Le « Welcome » de la miniature de slide dans `ScreenSlides.vue` : il reflète le
  rendu réel du slide TV (choix graphique de l'étudiant), pas le starter kit.
- Les composants 2FA morts (`TwoFactorRecoveryCodes.vue`, `TwoFactorSetupModal.vue`) :
  non montés, reliquats suivis par IFO-008.

## Journal du ticket

- 2026-08-22 — création ; traductions appliquées sur les 8 fichiers + 6 composants
  ui (sr-only) ; suite qualité verte ; vérification visuelle faite ; clos.
