---
id: IFO-010
titre: Contraintes d'unicité manquantes en base
statut: terminé
priorité: haute
dépend-de: []
créé: 2026-08-20
mis-à-jour: 2026-08-20
---

## Contexte

Aucune contrainte d'unicité n'existait sur les noms de locaux, de sections et
d'enseignants, ni sur le créneau d'une attribution. Audit complet :
[`../research/audit-schema-db.md`](../research/audit-schema-db.md).

La conséquence la plus concrète concerne l'import Excel. `SchedulerImportController`
résout les locaux par `Room::whereIn('name', ...)->pluck('id', 'name')` : `pluck` ne
retient **qu'un identifiant par nom**, choisi arbitrairement. Avec deux locaux « 106 »
en base, un import pouvait donc rattacher tout un planning à l'homonyme sans
historique, sans le moindre message.

Ce n'est pas théorique : deux locaux « 106 » et deux enseignants « Dupont Marie » ont
été créés en quelques minutes de recette manuelle, sans que l'utilisateur s'en aperçoive.

Le trio (date, période, local) définit par ailleurs un créneau. Le code le traite
partout comme unique — `slotIsOccupied()`, `bulkStore()`, l'import — mais par des
vérifications « lire puis écrire » qui ne protègent pas de deux écritures concurrentes.

## Critères d'acceptation

- [x] `rooms.name` unique, doublons fusionnés vers le local le plus ancien en
      reportant ses attributions
- [x] `groups.name` unique, liens pivot reportés sans créer de doublon
- [x] `teachers.name` unique, cours reportés
- [x] `assignments(date, période, local)` unique, remplaçant l'index `(date, room_id)`
- [x] Chaque migration a un `down()` fonctionnel
- [x] Les migrations passent sur MySQL **avec des doublons réels** : vérifié sur la
      base locale, le local « 106 » a conservé ses 18 attributions
- [x] Les factories `Room`, `Group` et `Teacher` génèrent des noms uniques
- [x] La suite de tests reste verte

## Décision consignée : `teachers.name`

L'unicité du nom d'enseignant a été **demandée par l'école** le 2026-08-20, malgré la
réserve suivante : deux enseignants peuvent légitimement être homonymes, et la
contrainte empêchera alors d'encoder le second. Si le cas se présente, la bonne réponse
n'est pas de retirer la contrainte mais d'ajouter un discriminant métier (matricule,
initiales) et de l'inclure dans l'unicité. La réserve est aussi écrite dans l'en-tête de
la migration, pour qui la relira sans ce ticket sous les yeux.

## Écarté

- **`assignments.room_id` en `nullOnDelete`** : proposé, non retenu. Supprimer un local
  continue donc d'effacer tout son historique de planning (`cascadeOnDelete`),
  contrairement à `courses.teacher_id` qui préserve les cours. Incohérence assumée.
- **`unique` sur `screen_slides.position`** : à ne pas faire. `reorder()` réassigne les
  positions une par une, ce qui entrerait en collision — MySQL n'a pas de contrainte
  différée.

## Journal du ticket

- 2026-08-20 — création et réalisation. Quatre migrations avec dédoublonnage préalable.
  Piège évité au passage : `RoomFactory` ne tirait que 400 noms possibles
  (`Room 100`–`Room 499`), ce qui aurait rendu la suite instable une fois l'unicité en
  place — les trois factories passent en `unique()`.
- 2026-08-20 — `tests/Feature/Database/SlotUniquenessTest.php` ajouté : il fige la
  garantie de créneau, y compris les cas qui doivent rester autorisés (mêmes date et
  local sur des périodes différentes, attributions sans local). Un test existant de
  `UpdateAssignmentStatusTest` mettait délibérément deux attributions sur le même
  créneau pour démontrer que `updateStatus` ne contrôle pas l'occupation : cette mise
  en scène est désormais impossible, le test a été réécrit et la garantie déplacée
  vers le nouveau fichier.
