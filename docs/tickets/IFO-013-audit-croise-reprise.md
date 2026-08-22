---
id: IFO-013
titre: Audit croisé de la reprise (multi-modèles)
statut: terminé
priorité: haute
dépend-de: []
créé: 2026-08-22
mis-à-jour: 2026-08-22
---

## Contexte

La reprise du projet (commits `fdfc3f2..095edae`) a été réalisée par un agent Opus.
Thibault demande une contre-vérification par des modèles plus récents avant de
considérer la mise en production comme fiable : le site tourne sur le Coolify de
l'école mais n'a pas été entièrement testé.

Quatre audits indépendants, périmètres disjoints :

1. **Sécurité applicative** (agent Fable) → `docs/research/audit-securite-fable.md`
2. **Review des commits de la reprise** (agent Fable) → `docs/research/audit-commits-opus-fable.md`
3. **Infra Docker/Coolify/CI** (agent Opus) → `docs/research/audit-deploiement-infra.md`
4. **Review croisée externe** (Codex GPT-5.6 Sol, read-only) → rapport intégré après relecture

Les points déjà arbitrés par l'école (absence de rôles, `purge_period`, `bulkStore`,
`sync([])`) sont exclus du périmètre.

## Critères d'acceptation

- [x] Les quatre rapports sont rendus et relus par l'orchestrateur
- [x] Chaque problème confirmé est soit corrigé, soit consigné avec un arbitrage
- [x] Verdict global sur la fiabilité du travail d'Opus consigné dans STATUS

## Verdict

**La reprise d'Opus est fiable.** Les quatre auditeurs convergent : chaque fix
revendiqué est réel et vérifié dans le code à HEAD, la CI est réellement verte
(274 tests, 1094 assertions), aucune régression majeure non rattrapée. Les
correctifs les plus risqués (migrations de dédoublonnage, transaction d'import,
plages horaires) ont en outre été contre-vérifiés par l'orchestrateur.

Trois vraies prises, aucune bloquante pour la production actuelle (base créée
neuve à HEAD) :

1. **Sol** : la migration renommée par `073e159` casse le chemin de mise à niveau
   des bases migrées avant le renommage (la table `recurring_assignments`
   ressusciterait) — corrigé par un garde sur l'ancien nom.
2. **Sol** : la fusion de 3+ sections homonymes pouvait créer des pivots
   `course_group` dupliqués — corrigé (réaffectation par insertion). À noter :
   l'agent Fable avait validé cette même migration ; la contre-lecture
   orchestrateur a tranché en faveur de Sol.
3. **Sol** : l'import Excel est sensible à la casse là où MySQL ne l'est pas
   (500 sur local homonyme, lignes de cours ignorées en silence) — ouvert
   en [IFO-014](IFO-014-import-sensible-casse.md).

Corrections immédiates issues des audits Fable (sécurité) et Opus (infra) :
trusted proxies + cookie `Secure`, garde-fous de suppression de comptes,
`php.ini` de l'image (uploads plafonnés à 2 Mo sinon), `LOG_CHANNEL=stderr`,
healthcheck sur `/up`, ports MySQL locaux sur loopback, corrections du guide.
Le reste est consigné dans [IFO-015](IFO-015-durcissements-differes.md).

## Journal du ticket

- 2026-08-22 — création ; lancement des quatre audits en parallèle.
- 2026-08-22 — rapport sécurité rendu : rien de bloquant ; deux points importants
  (cookie de session sans flag `Secure` + trusted proxies absents ; suppression du
  dernier admin possible → lockout).
- 2026-08-22 — les trois autres rapports rendus ; contre-vérification des points
  importants par l'orchestrateur ; correctifs appliqués ; IFO-014 et IFO-015
  ouverts ; ticket clos.
