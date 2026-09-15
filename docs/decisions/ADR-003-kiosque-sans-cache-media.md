---
id: ADR-003
titre: Kiosque TV sans cache média, avec un mode debug intégré
statut: accepté
date: 2026-09-14
---

## Contexte

`/screen` est affiché sur une seule télévision Samsung (UE58U8070, Tizen 9,
navigateur intégré basé sur Chromium 120). Le 2026-09-14, le diaporama se
figeait au premier changement de slide : le kiosque hydratait ses slides via
l'API Cache du navigateur (`window.caches.open()`, `cache.match()`), et sur ce
navigateur ces promesses ne se résolvent jamais (`caches` existe pourtant).
Le navigateur de la TV n'a pas de DevTools ; le diagnostic a été fait avec un
journal affiché à l'écran ([IFO-023](../tickets/IFO-023-diaporama-fige-tv-tizen.md)).

## Décision

- **Le kiosque n'utilise plus l'API Cache.** Images et vidéos des slides sont
  chargées par leur URL directe ; le cache HTTP du navigateur suffit. Seul le
  payload du planning reste mémorisé en `localStorage` pour un affichage
  immédiat au chargement.
- **Un mode debug reste embarqué** : `/screen?debug=1` affiche un bandeau
  (changements de slide, hooks de transition, rafraîchissements, erreurs JS,
  battement de cœur du thread principal) et charge eruda depuis jsDelivr ;
  `?debug=0` l'éteint. Hors mode debug, le module ne charge rien.
- **Cible unique** : on n'a pas à supporter d'autres navigateurs que celui de
  cette TV, plus ceux des postes d'administration (Chrome/Firefox/Edge).

## Alternatives écartées

- **Borner l'API Cache par des timeouts** (3 s) avec repli sur les URL
  directes : livré en premier (commit `5fd613e`), puis retiré le même jour —
  garder du code qui ne sert qu'à échouer proprement sur la seule cible n'a
  pas de sens.
- **Émulateur Tizen Studio ou vieux Chromium portable** pour reproduire sur
  PC : inutile, le moteur de la TV est récent ; le problème n'est pas un
  manque de support mais une API cassée.

## Conséquences

- Sans réseau, la TV n'affiche plus les médias en cache ; le planning reste
  visible grâce au `localStorage`. Acceptable : la TV est câblée à l'école.
- Le mode debug est le premier réflexe pour tout futur bug d'affichage sur la
  TV : photo du bandeau, puis correctif.
- Règle de travail actée le même jour : les correctifs sont commités et
  poussés directement sur `main` (Coolify déploie `main`) ; la recette locale
  est facultative quand la stack Docker est trop lente.
