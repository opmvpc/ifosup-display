---
id: IFO-009
titre: L'import de planning purge et réinsère hors transaction
statut: terminé
priorité: haute
dépend-de: []
créé: 2026-08-20
mis-à-jour: 2026-08-20
---

## Contexte

`SchedulerImportController::executeImport()` enchaîne, sans transaction :

1. une suppression en masse si `purge_period` est coché —
   `Assignment::whereBetween('date', [$from, $to])->delete()` ;
2. une boucle de création/mise à jour des attributions issues du fichier ;
3. la suppression du fichier téléversé.

`Assignment` n'utilise pas `SoftDeletes` : la suppression est définitive. Si l'étape 2
échoue à mi-parcours — fichier mal formé sur une ligne tardive, dépassement du temps
d'exécution, coupure réseau, plantage du conteneur — la purge reste appliquée et la
réinsertion est partielle. **Les attributions supprimées sont irrécupérables**, et rien
n'indique à l'utilisateur dans quel état se trouve la base.

L'exposition est réelle : un import couvrant une année scolaire complète peut supprimer
plusieurs milliers de lignes avant de commencer à réinsérer.

À noter : le comportement de purge lui-même n'est **pas** en cause. Il est délibéré et
correctement annoncé — encadré rouge « danger zone », mention « sans retour en arrière
possible », et compteur du nombre exact d'enregistrements concernés. Ce compteur
(`assignments_in_range`) utilise le même `whereBetween` que la suppression : ce qui est
annoncé correspond à ce qui est supprimé. Seule l'absence d'atomicité pose problème.

## Critères d'acceptation

- [x] La purge et la boucle de réinsertion sont enveloppées dans un `DB::transaction()`
- [x] La suppression du fichier téléversé et le nettoyage de session ont lieu **après**
      le commit : en cas d'échec, le fichier reste disponible pour retenter
- [x] Deux tests couvrent le rollback : une attribution de la plage purgée survit à
      l'échec, et le fichier téléversé est conservé. Vérifié qu'ils échouent tous deux
      si l'on retire la transaction — sans quoi ils ne prouveraient rien.
- [x] La réponse 500 indique explicitement que le planning est intact ; l'exception
      part dans les logs via `report()`

## Pistes complémentaires (à arbitrer, hors périmètre minimal)

- Ajouter `SoftDeletes` à `Assignment` donnerait un filet de sécurité en cas de purge
  déclenchée par erreur, au prix d'un `whereNull('deleted_at')` implicite partout.
- Un import volumineux gagnerait à traiter les lignes par lots plutôt qu'une requête
  par attribution.

## Journal du ticket

- 2026-08-20 — création. Identifié en analysant `purge_period` à la demande de
  Thibault. La purge était initialement présentée comme un point d'arbitrage métier ;
  la lecture du contrôleur et de `SchedulerImport.vue` montre que la fonctionnalité est
  assumée et bien signalée, et que le vrai défaut est l'absence de transaction.
- 2026-08-20 — corrigé. `executeImport()` exécute la purge et la réinsertion dans un
  `DB::transaction()`. Suite complète : 255 tests verts.
