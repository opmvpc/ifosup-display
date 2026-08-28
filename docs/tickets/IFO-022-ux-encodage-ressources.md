---
id: IFO-022
titre: UX d'encodage — hiérarchie des boutons et année de cours effaçable
statut: terminé
priorité: normale
dépend-de: [IFO-021]
créé: 2026-08-28
mis-à-jour: 2026-08-28
---

## Contexte

Retours UX de Thibault (2026-08-28), après le premier encodage réel :

1. Sur les formulaires de création de ressources, « Enregistrer » était le bouton
   noir (principal) et « Créer et créer un autre » un bouton contour — alors que
   l'encodage se fait en série : l'action la plus fréquente n'était pas mise en
   avant, et le libellé « Créer et créer un autre » était maladroit.
2. Le select « Année » d'un cours (1ère/2e/3e année, ajouté par IFO-018) ne
   pouvait plus être vidé une fois une valeur choisie par inadvertance — alors
   que `year` est nullable en base et en validation.

## Correctifs

- `ResourceFormLayout.vue` : à la création, « Enregistrer et créer un autre »
  (nouveau libellé) devient l'action principale (noire) et « Enregistrer » passe
  en contour. En édition, « Enregistrer » est seul et reste l'action principale.
- `Combobox.vue` : nouvelle prop `clearable` — croix pour vider la sélection en
  mode simple (le mode multiple a déjà une croix par badge). Quand la croix est
  disponible, le champ caché est toujours émis (vide le cas échéant), sans quoi
  une mise à jour ne pourrait jamais effacer la valeur côté serveur (la clé
  absente de la requête laisse `update()` intact).
- `clearable` activé sur le select « Année » de la création et de l'édition d'un
  cours. Pas sur « Enseignant » : `teacher_id` n'est pas nullable en base.
- Les sections n'ont rien nécessité : chaque badge a sa croix et le contrôleur
  fait `sync([])` quand la clé `groups` est absente (comportement déjà testé).

## Critères d'acceptation

- [x] Création d'une ressource : « Enregistrer et créer un autre » en noir,
      « Enregistrer » en contour (vérifié dans le navigateur : fonds
      rgb(23,23,23) / rgb(255,255,255))
- [x] Édition : « Enregistrer » reste l'action principale
- [x] La croix du select « Année » vide la sélection et l'enregistrement met bien
      `year` à NULL en base (vérifié de bout en bout dans la stack dev)
- [x] Test Feature : `year=''` soumis à la mise à jour ⇒ NULL en base
- [x] Suite complète verte, lint et typecheck OK

## Journal du ticket

- 2026-08-28 — création, correctifs, vérification navigateur, clôture.
