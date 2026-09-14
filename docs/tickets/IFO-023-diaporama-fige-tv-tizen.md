---
id: IFO-023
titre: Diaporama figé au premier changement de slide sur la TV Samsung (Tizen)
statut: en-cours
priorité: haute
dépend-de: []
créé: 2026-09-14
mis-à-jour: 2026-09-14
---

## Contexte

Signalé par Thibault le 2026-09-14 : sur la télévision de l'école (Samsung
UE58U8070, navigateur intégré « Tizen browser 8.1.07220 », firmware à jour),
`/screen` affiche la slide Bienvenue puis **se fige au passage à la 2e slide**
(un Planning). Même symptôme observé par une étudiante sur son PC, sous Avast
Secure Browser (Chromium). Chrome, Firefox, Edge, et Avast Secure Browser sur le
PC de Thibault ne reproduisent pas.

Ce qui est exclu : le moteur de la TV est un Chromium récent (M108 sur Tizen 8,
M120 sur Tizen 9 — cf. [Web Engine Specifications](https://developer.samsung.com/smarttv/develop/specifications/web-engine-specifications.html)),
donc ni la syntaxe ES2020 du code, ni `inset`, ni Tailwind 4 (Chromium 111+)
ne sont en cause. Aucune API exotique dans les composants du kiosque.

Le navigateur de la TV n'a pas de DevTools : impossible de lire l'erreur. D'où
ce ticket en deux temps : **instrumenter**, puis corriger.

Pistes ouvertes (à départager par le mode debug) :

- transition Vue `slide` (deux couches plein écran animées en `transform`,
  `position: absolute; inset: 0` sur celle qui sort) qui ne se termine pas ;
- boucle `requestAnimationFrame` du `ScheduleSlide` bloquée ou throttlée ;
- blocage du thread principal (mémoire, rendu) — le battement de cœur du mode
  debug le distingue d'un simple événement manquant ;
- cache média (`window.caches`, URL `blob:`) restreint par le navigateur.

## Diagnostic (2026-09-14, 17h19, sur la TV)

Journal du mode debug (UA : `SMART-TV; Linux; Tizen 9.0 … SamsungBrowser/8.0
Chrome/120.0.6099.5`) : battement de cœur qui continue (thread principal
vivant), `next demandé` → `refresh: fetch /screen/data`, puis plus rien. Et
surtout **aucun `refresh: fetch` au montage** : `onMounted` reste suspendu avant,
dans `applyPayload(cachedPayload)` → `hydrateSlides` → `resolveCachedMediaSource`
→ `window.caches.open()` / `cache.match()`. Sur ce navigateur, `caches` existe
mais ses promesses ne se résolvent jamais. Le refresh déclenché par `next`
bloque au même endroit après son fetch ; le Welcome n'émet qu'une fois : figé.

Correctif, en deux temps : d'abord des timeouts de 3 s autour de l'API Cache
(commit `5fd613e`), puis, `/screen` n'étant affiché que sur cette TV, décision
de Thibault de **retirer complètement le cache média** (URL directes, cache
HTTP du navigateur). Le payload reste mémorisé en localStorage.

## Critères d'acceptation

- [x] `/screen?debug=1` affiche un journal à l'écran (changements de slide,
      hooks de transition, rafraîchissements, erreurs JS, promesses rejetées,
      `console.error`/`warn`, battement de cœur) et charge eruda (console
      DevTools dans la page). `?debug=0` désactive. Rien n'est chargé hors mode
      debug.
- [x] Vérifié sur la TV par Thibault : journal visible, eruda chargé (la
      vérification en stack dev a été sautée, trop lente : « act fast »).
- [x] Lint + types au vert sur le mode debug.
- [x] Cause identifiée sur la TV à partir du journal : API Cache qui ne
      répond jamais.
- [ ] Correctif (cache média retiré) vérifié sur la TV :
      le diaporama enchaîne Bienvenue → Planning → images.

## Journal du ticket

- 2026-09-14 — création après diagnostic (modèle de TV, moteur, navigateur de
  l'étudiante identifié : Avast Secure Browser). Module
  `resources/js/lib/kioskDebug.ts` + instrumentation de `Kiosk.vue`, branche
  `fix/ifo-023-debug-kiosk-tv`. Mergé (PR #8) et déployé ; photos du journal
  sur la TV → cause trouvée (API Cache suspendue) ; correctif poussé
  directement sur `main` (nouvelle règle de travail : push direct pour les
  correctifs).
