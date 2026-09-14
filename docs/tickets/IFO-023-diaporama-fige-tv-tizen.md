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

## Critères d'acceptation

- [x] `/screen?debug=1` affiche un journal à l'écran (changements de slide,
      hooks de transition, rafraîchissements, erreurs JS, promesses rejetées,
      `console.error`/`warn`, battement de cœur) et charge eruda (console
      DevTools dans la page). `?debug=0` désactive. Rien n'est chargé hors mode
      debug.
- [ ] Vérifié dans le navigateur (stack dev) : journal visible, eruda ouvrable,
      aucune régression du diaporama sans `?debug`.
- [ ] Lint + types au vert.
- [ ] Cause identifiée sur la TV à partir du journal (session sur place).
- [ ] Correctif livré et vérifié sur la TV.

## Journal du ticket

- 2026-09-14 — création après diagnostic (modèle de TV, moteur, navigateur de
  l'étudiante identifié : Avast Secure Browser). Module
  `resources/js/lib/kioskDebug.ts` + instrumentation de `Kiosk.vue`, branche
  `fix/ifo-023-debug-kiosk-tv`.
