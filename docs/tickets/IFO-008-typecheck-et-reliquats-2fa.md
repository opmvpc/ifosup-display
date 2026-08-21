---
id: IFO-008
titre: Ajouter types:check à la CI
statut: terminé
priorité: normale
dépend-de: [IFO-007]
créé: 2026-08-18
mis-à-jour: 2026-08-18
---

## Contexte

`npm run types:check` (`vue-tsc --noEmit`) n'est appelé par aucun workflow. L'ajouter
ferait échouer la CI, pour deux raisons distinctes.

**1. Reliquats 2FA — bloquant, non résolu.**
`resources/js/composables/useTwoFactorAuth.ts` importe `@/routes/two-factor`, un module
que Wayfinder ne génère pas puisque les routes correspondantes n'existent plus :
`config/fortify.php` déclare `'features' => []` et `routes/settings.php` ne route plus
le contrôleur 2FA. Le code concerné n'a aucun point d'entrée :

| Fichier | État |
|---|---|
| `app/Http/Controllers/Settings/TwoFactorAuthenticationController.php` | non routé ; rend `settings/TwoFactor` qui n'existe pas |
| `app/Http/Requests/Settings/TwoFactorAuthenticationRequest.php` | utilisé uniquement par le contrôleur ci-dessus |
| `resources/js/composables/useTwoFactorAuth.ts` | casse `vue-tsc` |
| `resources/js/components/TwoFactorRecoveryCodes.vue` | aucun composant ne l'importe |
| `resources/js/components/TwoFactorSetupModal.vue` | aucun composant ne l'importe |
| `tests/Feature/Auth/TwoFactorChallengeTest.php` | ignoré à chaque exécution |
| `tests/Feature/Settings/TwoFactorAuthenticationTest.php` | ignoré à chaque exécution |

La suppression a été proposée le 2026-08-18 et **non retenue** : ces fichiers restent en
place. Le trait `TwoFactorAuthenticatable` sur `User` et les colonnes en base sont
inoffensifs et à conserver dans tous les cas.

**2. Typage des slides de l'écran — à faire.**
`ImageSlide.vue`, `ScheduleSlide.vue`, `VideoSlide.vue` et `Welcome.vue` sont passés en
`<script setup lang="ts">` pour satisfaire la règle ESLint `vue/block-lang`. Écrits à
l'origine en JavaScript, ils produisent 24 erreurs `vue-tsc` : `any` implicites sur les
refs de timers et d'éléments DOM, `offsetHeight` sur des refs non typées, paramètres de
callbacks non typés. Rien de fonctionnellement cassé — le build Vite passe — mais il
faut typer les refs (`ref<HTMLElement | null>(null)`, `ref<number | null>(null)`…).

## Critères d'acceptation

- [x] Reliquats 2FA **conservés** : l'import `@/routes/two-factor` est remplacé par
      les endpoints Fortify déclarés dans `useTwoFactorAuth.ts`. Réactiver la
      fonctionnalité suffit à les rendre opérants, sans rien réécrire.
- [x] Les 4 composants de slides typés, plus `Combobox.vue` : `vue-tsc --noEmit` sans erreur les concernant
- [x] `pnpm types:check` sort en succès sur l'ensemble du projet
- [x] Étape `pnpm types:check` ajoutée à `.github/workflows/lint.yml`, après la
      génération Wayfinder (déjà présente dans le workflow)
- [x] Pint, Prettier, ESLint et Pest restent verts après le typage

## Journal du ticket

- 2026-08-18 — création. `types:check` volontairement laissé hors de la CI : l'ajouter
  aujourd'hui la rendrait rouge. Les autres vérifications (Pint, Prettier, ESLint,
  tests) sont, elles, passées en mode strict sous [[IFO-007-ci-au-vert]].
- 2026-08-20 — résolu sans supprimer un seul fichier. `useTwoFactorAuth.ts` expose
  désormais un objet `twoFactorRoutes` avec les chemins Fortify (`/user/two-factor-qr-code`,
  `/user/two-factor-secret-key`, `/user/two-factor-recovery-codes`,
  `/user/confirmed-two-factor-authentication`) ; `TwoFactorRecoveryCodes.vue` et
  `TwoFactorSetupModal.vue` s'y branchent au lieu du module généré absent.
- 2026-08-20 — 36 erreurs de typage corrigées sans toucher au comportement : timers en
  `ReturnType<typeof setTimeout|setInterval>`, refs DOM typées (`HTMLElement`,
  `HTMLVideoElement`), paramètres de callbacks explicités. La machine à états
  `requestAnimationFrame` de `ScheduleSlide.vue` n'a pas été modifiée.
- 2026-08-20 — deux imprécisions de typage relevées mais **non corrigées**, hors
  périmètre : `AssignmentRow` dans `Kiosk.vue` ne déclare pas `status` alors que
  `ScheduleSlide.vue` s'en sert pour trois états visuels, et le type `Assignment`
  (`resources/js/types/resources/assignment.d.ts`) n'a pas de champ `id` alors que le
  template fait `row.id ?? index`. À traiter si l'on veut typer les slides par prop
  plutôt qu'en interne — cela suppose de discriminer sur `slide.type` dans `Kiosk.vue`.
