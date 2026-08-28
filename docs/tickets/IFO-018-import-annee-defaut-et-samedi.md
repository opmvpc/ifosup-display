---
id: IFO-018
titre: Import Excel — année par défaut fausse en août (cours créés en 2025) et feuille SAMEDI ignorée
statut: terminé
priorité: haute
dépend-de: []
créé: 2026-08-28
mis-à-jour: 2026-08-28
---

## Contexte

Signalé par Thibault le 2026-08-28 : l'import du planning « LOCAUX XAVIER 26-27.xlsx »
en production a annoncé un succès mais rien n'apparaissait dans le planning. Confirmé :
**les 353 attributions ont été créées du 24/08/2025 au 24/06/2026**, un an dans le passé.

Trois causes emboîtées :

1. **Défaut d'année scolaire faux en août** — `SchedulerImport.vue` :
   `new Date().getMonth() >= 8` (mois indexés à 0, août = 7). Le commentaire dit
   « past August » mais le code veut dire « à partir de septembre » : tout le mois
   d'août, le sélecteur proposait l'année scolaire *précédente* (2025–2026 fin août
   2026). Personne n'a touché au menu → import dans le passé.
2. **Le parser ignorait les années présentes dans le fichier** —
   `SchedulerSheetParser::mapDates()` ne lisait que la *première* cellule de la ligne
   de dates (texte « 24/08 »), y collait l'année du formulaire, puis ajoutait une
   semaine par colonne. Or les cellules suivantes du planning réel sont de **vraies
   dates Excel avec l'année 2026 dedans** : le fichier savait, le parser n'écoutait pas.
3. **Aucun garde-fou** — l'aperçu affichait bien « Année scolaire 2025–2026 » et la
   plage « 24/08/2025 → 24/06/2026 », mais sans alerte, et l'import concluait
   « Import terminé ! ».

Trouvé au passage : la feuille **SAMEDI** est ignorée en silence — son en-tête ne
contient ni « matin », ni « midi », ni « soir ». Aujourd'hui elle ne contient aucun
cours (seulement des locaux), donc aucune perte, mais dès qu'elle sera remplie elle
disparaîtrait de l'import. Confirmé par Thibault : les cours du samedi ont lieu **le
matin (8h30–13h)**.

## Correctifs

- `SchedulerImport.vue` : défaut `getMonth() >= 7` (août bascule sur l'année en cours).
- `SchedulerSheetParser::mapDates()` : quand une cellule de la ligne de dates est une
  vraie date Excel, elle est utilisée telle quelle (l'année vient du fichier et prime
  sur le formulaire). Les cellules texte qui précèdent une date ancrée sont recalées
  dessus (−1 semaine par colonne) ; celles qui suivent continuent en +1 semaine.
  L'année du formulaire ne sert plus que de repli pour les fichiers 100 % texte.
- `SchedulerImport.vue` : alerte rouge dans l'aperçu quand toute la plage du fichier
  est antérieure à aujourd'hui (« Cette période est déjà passée ! »), avec rappel de
  l'année choisie.
- `SchedulerSheetParser::PERIOD_MAP` : « samedi » reconnu comme en-tête de bloc,
  mappé sur `morning` (en dernier dans la table : « SAMEDI MATIN » reste attrapé
  par « matin »).

## Critères d'acceptation

- [x] Le planning réel importé avec « 2025 » sélectionné produit quand même des dates
      2026–2027 (l'année des cellules Excel prime) — vérifié sur le vrai fichier
- [x] En août, le sélecteur propose l'année scolaire en cours par défaut
- [x] L'aperçu alerte visiblement si toute la période est dans le passé
- [x] Une feuille « SAMEDI » est parsée comme bloc du matin
- [x] Tests unitaires du parser : dates réelles prioritaires, recalage de la première
      cellule texte, +7 jours après une date réelle, en-tête SAMEDI (4 nouveaux tests)
- [x] Suite complète verte

## Reste à faire côté prod (manuel, par Thibault)

- [ ] Purger les attributions fantômes du 24/08/2025 au 24/06/2026 (attention : la
      purge intégrée à l'import ne couvre que la plage du fichier ré-importé, donc
      2026–2027 — les fantômes 2025 doivent être supprimés à part)
- [ ] Ré-importer le fichier après déploiement du correctif

## Journal du ticket

- 2026-08-28 — création : diagnostic sur le fichier réel (simulation du parser en
  Python : 353 attributions, 9 locaux, 23 cours ; plage 2025 avec l'année par défaut),
  confirmation de Thibault (cours bien créés en 2025 en prod), correctifs + tests.
