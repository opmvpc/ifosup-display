# IFO-019 — Inventaire des fonctionnalites visibles par l'utilisateur

> Synthese factuelle des ecrans du backoffice IFOSUP Display, destinee a rediger une documentation utilisateur en francais. Basee sur une lecture du code source (Vue/Inertia + controleurs Laravel) au 25/08/2026.

## Sommaire

1. [Planning (Schedule / Scheduler)](#1-planning-schedule--scheduler)
2. [Import Excel du planning](#2-import-excel-du-planning)
3. [Slides ecran (ScreenSlides)](#3-slides-ecran-screenslides)
4. [Ecran TV public (Screen)](#4-ecran-tv-public-screen)
5. [Gestion des comptes utilisateurs (Admin)](#5-gestion-des-comptes-utilisateurs-admin)
6. [Ressources : Cours, Enseignants, Locaux, Sections](#6-ressources--cours-enseignants-locaux-sections)
7. [Parametres du compte (Profil, Mot de passe, Apparence)](#7-parametres-du-compte-profil-mot-de-passe-apparence)
8. [Connexion](#8-connexion)

---

## 1. Planning (Schedule / Scheduler)

**Fichiers** : `resources/js/pages/Schedule.vue`, `resources/js/components/Scheduler.vue`, `resources/js/components/SchedulerAssignmentCard.vue`

### A quoi ca sert

Ecran principal de gestion du planning des cours : une grille avec les **locaux en lignes** et les **dates x periodes (Matin / Apres-midi / Soir) en colonnes**. Chaque case peut contenir un cours attribue (« attribution »).

### Actions possibles

- **Glisser-deposer un cours depuis la bibliotheque des cours** (panneau lateral droit, bouton « Afficher les cours ») vers une case vide de la grille -> cree une nouvelle attribution.
- **Glisser-deposer une attribution existante** d'une case vers une autre case **vide** -> deplace le cours (la case source est liberee). Un deplacement vers une case deja occupee est refuse (le curseur l'indique, aucune action au depot).
- **Rechercher un cours** dans la bibliotheque par code ou nom (recherche insensible aux accents/majuscules).
- **Redimensionner** le panneau « Bibliotheque des cours » par glissement de sa bordure (largeur entre 264 et 560 px) ; l'etat ouvert/ferme et la largeur sont memorises (cookies scheduler_drawer_open).
- **Zoom** avant/arriere sur la grille (4 niveaux : x0.5, x1, x1.5, x2), memorise en cookie (scheduler_zoom).
- **Plein ecran** (bouton dedie).
- **Changer la periode affichee** via un selecteur de plage de dates (« du » / « au »), avec rechargement automatique (leger delai de 250 ms) des donnees pour la nouvelle plage.
- Sur chaque carte de cours, un menu « Actions » (icone engrenage, visible au survol) permet de :
  - **Marquer comme annule** (badge rouge « Annule », carte en opacite reduite) ;
  - **Signaler comme en retard** (badge orange « En retard », contour orange) ;
  - **Replanifier** (retour au statut « Planifie », sans badge) ;
  - **Supprimer** l'attribution (suppression immediate, sans confirmation autre que l'action elle-meme).
- **Insertion en masse** (bouton « Insertion en masse ») : permet de programmer un meme cours dans un meme local, un jour de la semaine fixe, une periode fixe, sur une plage de semaines (semaine ISO de debut/fin). En deux etapes :
  1. **Configuration** : choix du cours, du local par defaut, du jour de la semaine, de la periode, et des semaines de debut/fin (la semaine de fin doit etre >= semaine de debut).
  2. **Apercu et confirmation** : liste de toutes les dates generees, chacune avec une case a cocher (toutes cochees par defaut) et un selecteur de local modifiable ligne par ligne. Le systeme signale les conflits (case deja occupee par un **autre** cours, affichee en orange, « sera remplace ») et les cas ou le meme cours est deja present (affiche en gris, pas de remplacement). Le bouton final s'intitule dynamiquement « Inserer (N) », « Remplacer (N) », ou les deux, et devient orange s'il y a des remplacements. **Piege : cette operation ecrase silencieusement (sans corbeille) toute attribution existante en conflit dans les cases selectionnees.**

### Regles metier

- Une case (local x date x periode) ne peut contenir qu'**une seule** attribution a la fois.
- Statuts d'une attribution : planned (Planifie, par defaut, pas de badge), cancelled (Annule), late (En retard).
- Les 3 periodes journalieres sont : **Matin**, **Apres-midi**, **Soir** (voir horaires exacts au point 4, Ecran TV).
- Toute action (deplacement, creation, suppression, changement de statut) est envoyee immediatement au serveur ; en cas d'echec, l'interface revient a l'etat precedent et affiche un message d'erreur (fenetre d'alerte navigateur).

### Pieges a connaitre

- La suppression d'une attribution depuis le menu Actions est **definitive et immediate**, sans boite de confirmation.
- L'insertion en masse peut **remplacer** des cours deja planifies sans possibilite d'annulation apres confirmation.
- Le glisser-deposer refuse silencieusement un depot sur une case deja occupee (aucune erreur explicite, juste une absence de reaction visuelle de type « drop »).

---

## 2. Import Excel du planning

**Fichiers** : `resources/js/pages/SchedulerImport.vue`, `app/Http/Controllers/SchedulerImportController.php`, `app/Services/SchedulerSheetParser.php`

### A quoi ca sert

Permet d'importer en masse des attributions de cours depuis un classeur Excel exporte par un autre systeme (planning source).

### Etapes du parcours

1. **Upload** : depot (glisser-deposer ou selection) d'un fichier .xlsx ou .xls, avec selection de l'**annee scolaire** de depart (ex. « 2026-2027 »). Contraintes serveur : fichier requis, format Excel uniquement, **taille maximale 20 Mo**, annee entre 2000 et 2100.
2. **Fichier en attente** : si un fichier a deja ete televerse lors d'une session precedente sans etre traite, l'ecran propose de le **re-analyser** ou de **l'ignorer** (bouton « Ignorer et uploader un autre fichier », qui supprime le fichier en attente).
3. **Analyse (parsing)** : lecture automatique du fichier cote serveur, avec un ecran de chargement.
4. **Resume (summary)**, ecran central de verification avant import, affichant :
   - le nombre total d'attributions detectees et la plage de dates couverte ;
   - la liste des **locaux** detectes (existants en base + nouveaux, qui seront crees automatiquement) ;
   - la liste des **cours reconnus** (correspondant a un code existant en base), avec case a cocher individuelle et « Tout cocher », seuls les cours coches seront importes ;
   - la liste des **cours ignores** (codes du fichier qui n'existent pas dans la base, non importables, affichage repliable au-dela de 10 lignes) ;
   - le detail des **conflits** : creneaux (date, periode, local) deja occupes par un cours different, ces creneaux seront **remplaces**, sauf activation de la purge ;
   - trois compteurs d'impact : **Creations**, **Remplacements**, **Ignorees**.
   - **Option « Purger la periode avant l'import »** (case a cocher dans une zone rouge) : supprime **toutes** les attributions existantes sur la plage de dates du fichier avant de reinserer les donnees selectionnees. Zone presentee comme une « zone de danger » avec avertissement explicite : « sans retour en arriere possible ».
5. **Import (execution)** : bouton « Importer N attribution(s) » (libelle « + purge » si l'option est activee), avec un ecran de chargement pendant le traitement.
6. **Succes** : recapitulatif du nombre d'enregistrements purges / crees / remplaces, avec liens « Voir le planning » ou « Importer un autre fichier ».
7. **Abandon** : a tout moment (bouton « Annuler » sur l'ecran de resume), le fichier en attente peut etre supprime sans import.

### Correspondance des noms (regle importante)

Les noms de locaux et codes de cours sont compares **sans tenir compte de la casse ni des espaces superflus** (ex. « Salle A » = « SALLE A »). Les nouveaux locaux sont crees avec la casse telle qu'ecrite dans le fichier Excel.

### Gestion des erreurs

- Fichier illisible (classeur corrompu, cellule de date malformee, format inattendu) -> message clair invitant a verifier le format ou a reexporter depuis Excel, plutot qu'une erreur technique brute.
- L'import est **atomique** (transaction) : en cas d'echec en cours de traitement, aucune modification n'est appliquee et le planning reste intact, un message l'indique explicitement.
- Une fois l'import reussi, le fichier temporaire est supprime du serveur.

### Pieges a connaitre

- **La purge de la periode est definitive** : elle supprime tout ce qui existe dans la plage de dates du fichier, y compris des cours saisis manuellement ailleurs, sans historique ni corbeille.
- **Sans purge**, tout creneau du fichier qui coincide avec une attribution existante (autre cours) **ecrase** cette derniere de facon definitive des la validation de l'import.
- Les cours dont le code n'existe pas encore dans la base (« Cours ignores ») ne sont **jamais** importes automatiquement : il faut d'abord creer le cours via l'ecran Ressources > Cours, puis relancer l'import (bouton « Re-analyser »).
- Taille de fichier limitee a 20 Mo.

---

## 3. Slides ecran (ScreenSlides)

**Fichiers** : `resources/js/pages/ScreenSlides.vue`, `app/Http/Controllers/ScreenSlideController.php`, `app/Models/ScreenSlide.php`

### A quoi ca sert

Configure le diaporama diffuse sur l'ecran TV public : ajout, modification, suppression et reordonnancement des slides (planning, images, videos, message d'accueil).

### Types de slides

| Type | Description | Comportement par defaut |
|---|---|---|
| **Bienvenue** (welcome) | Toujours le **premier** slide, obligatoire, ne peut pas etre supprime. Affiche un « mot du jour » optionnel (280 caracteres max). | Cree automatiquement a la premiere visite si aucun slide n'existe. Verrouille en premiere position. |
| **Planning** (schedule) | Genere automatiquement l'affichage du planning du jour selon l'heure. Non personnalisable (ni mise en page ni duree), la duree s'adapte au contenu. | Cree automatiquement en 2e position par defaut. |
| **Image** | Affiche une image fixe pendant une duree definie. | Duree par defaut 5000 ms si non precisee. |
| **Video** | Affiche une video jusqu'a sa fin. | -- |

### Actions possibles

- **Ajouter un slide** (bouton « Ajouter ») : choix du type (Planning / Image / Video) via une boite de dialogue. Le slide Planning est cree directement en un clic ; Image et Video ouvrent un formulaire dedie.
- **Personnaliser** (icone reglages) : ouvre une boite de dialogue de modification selon le type :
  - Bienvenue : edition du message du jour (0-280 caracteres, compteur affiche).
  - Image : changement de la duree (en millisecondes, min. 1000, pas de 500) et/ou remplacement du fichier image.
  - Video : remplacement du fichier video (pas d'option de duree modifiable manuellement, duree detectee automatiquement cote navigateur pour affichage informatif uniquement).
  - Planning : dialogue informatif seulement (pas de champ modifiable), bouton « Ok ».
- **Reordonner** : boutons fleche gauche/droite sur chaque slide (sauf le slide Bienvenue, verrouille). Le slide Bienvenue reste toujours force en premiere position meme apres reordonnancement.
- **Supprimer** un slide (icone corbeille, avec confirmation navigateur « Supprimer ce slide ? »). Le slide Bienvenue **ne peut jamais etre supprime** (bouton absent).

### Contraintes de fichiers

- **Image** : formats image standards (JPG, PNG, GIF, WebP...), **10 Mo maximum**.
- **Video** : formats **MP4, WebM ou MOV** uniquement, **300 Mo maximum**.
- **Duree** (slides image/video) : entre 1 000 ms et 120 000 ms (2 minutes) si definie.

### Regles metier

- Le remplacement d'une image/video supprime automatiquement l'ancien fichier du stockage.
- Un slide Image sans image (creation) ou une mise a jour sans image existante est refuse avec message explicite. Idem pour un slide Video.

### Pieges a connaitre

- La suppression d'un fichier (image/video remplace ou slide supprime) est **definitive**, aucune corbeille.
- Le slide « Bienvenue » est verrouille en premiere position : toute tentative de reordonnancement qui le deplacerait est rejetee cote serveur.

---

## 4. Ecran TV public (Screen)

**Fichiers** : `app/Http/Controllers/ScreenController.php` (endpoint public /screen/data)

### A quoi ca sert

Fournit les donnees consommees par la page d'affichage TV (diaporama en boucle : bienvenue, planning du jour, images, videos). Cet ecran n'est pas un formulaire d'administration mais determine **ce que voient les usagers** sur l'ecran physique.

### Logique des periodes horaires

Le fuseau horaire utilise est configurable (Europe/Brussels par defaut). Trois periodes fixes :

| Periode | Horaire | Libelle affiche |
|---|---|---|
| Matin | 00:00:00 -> 12:30:00 | « Cours du matin » |
| Apres-midi | 12:30:00 -> 17:30:00 | « Cours de l'apres-midi » |
| Soir | 17:30:00 -> 23:59:59 | « Cours du soir » |

### Ce qui s'affiche selon l'heure actuelle

L'ecran ne montre jamais tout le planning du jour d'un coup : il affiche la periode en cours **et la suivante** (pour anticiper), sauf en soiree ou seule la periode du soir est montree :

- Pendant le **Matin** -> slides « Cours du matin » **et** « Cours de l'apres-midi ».
- Pendant l'**Apres-midi** -> slides « Cours de l'apres-midi » **et** « Cours du soir ».
- Pendant le **Soir** -> uniquement « Cours du soir ».

Chaque ligne de planning affichee comprend : code et nom du cours, annee (1re/2e/3e le cas echeant), nom de l'enseignant, groupes/sections concernes, local, et statut (planifie/annule/en retard, porte a l'ecran TV).

---

## 5. Gestion des comptes utilisateurs (Admin)

**Fichiers** : `resources/js/pages/admin/users/*.vue`, `app/Http/Controllers/Admin/UserController.php`

### A quoi ca sert

CRUD des comptes ayant acces au backoffice.

### Actions possibles

- **Liste** (Index.vue) : recherche par nom ou email, affichage nom/email de chaque compte.
- **Creer** (Create.vue) : nom, adresse email, mot de passe + confirmation (tous requis). Un bouton « creer et ajouter un autre » existe via le layout generique de formulaire.
- **Voir la fiche** (Show.vue) : id, email, date de creation. Bouton « Supprimer » en zone de danger.
- **Modifier** (Edit.vue) : nom, email, mot de passe (laisser vide pour ne pas le changer).

### Garde-fous de suppression (regles metier importantes)

Un compte **ne peut pas etre supprime** dans deux cas, avec message explicite affiche a la place du bouton « Supprimer » :

1. **On ne peut pas supprimer son propre compte** depuis cette page administrateur (« Vous ne pouvez pas supprimer votre propre compte depuis cette page. Passez par les parametres du profil. »), la suppression de son propre compte se fait via Parametres > Profil (voir section 7).
2. **On ne peut pas supprimer le dernier compte existant** (« Impossible de supprimer le dernier compte : plus personne ne pourrait se connecter. »), car l'inscription libre et la reinitialisation de mot de passe par email sont desactivees, supprimer le dernier compte verrouillerait definitivement l'acces au backoffice.

### Pieges a connaitre

- Si un administrateur change **son propre** mot de passe depuis cette page, il n'est **pas deconnecte** (comportement volontaire different d'un changement de mot de passe d'un autre compte, qui deconnecte les sessions actives de ce compte).
- La suppression d'un compte (quand autorisee) est immediate apres confirmation, sans corbeille.

---

## 6. Ressources : Cours, Enseignants, Locaux, Sections

**Fichiers** : `resources/js/pages/resources/{courses,teachers,rooms,groups}/*.vue`, controleurs CourseController.php, TeacherController.php, RoomController.php, GroupController.php

Ces quatre ecrans suivent tous le meme schema CRUD (Index -> Create/Edit -> Show), avec une mise en page commune.

### 6.1 Cours

- **Champs** : Code (obligatoire, unique, 50 caracteres max), Nom (obligatoire, 255 caracteres max), **Annee** (nouveau champ, 1re / 2e / 3e annee, optionnel), Enseignant (liste deroulante, optionnel), Sections (selection multiple parmi les sections existantes, optionnel).
- **Liste** : recherche par code ou nom, avatar genere automatiquement a partir des initiales du code, badge d'annee affiche si renseignee.
- **Fiche** : affiche l'enseignant assigne et les sections associees, chacun cliquable vers sa propre fiche.
- **Edition** : memes champs ; les sections associees sont resynchronisees entierement a chaque sauvegarde (une section decochee est retiree du cours).

### 6.2 Enseignants

- **Champs** : Nom uniquement (obligatoire).
- **Fiche** : liste des cours assignes a cet enseignant.

### 6.3 Locaux (Rooms)

- **Champs** : Nom uniquement (obligatoire). Utilises comme lignes dans le planning.

### 6.4 Sections (Groups)

- **Champs** : Nom uniquement (obligatoire).
- **Fiche** : liste des cours rattaches a cette section.

### Regles communes a ces quatre ecrans

- **Suppression** : bouton « Supprimer » en « Zone de danger » sur chaque fiche, avec confirmation navigateur (« Etes-vous sur de vouloir supprimer cette ressource ? Cette action est irreversible. »). **Contrairement aux comptes utilisateurs, aucun garde-fou metier n'empeche la suppression d'un cours, enseignant, local ou section meme s'il est reference ailleurs** (ex. un cours utilise dans des attributions de planning), la suppression est immediate et definitive des confirmation.
- Recherche disponible sur les listes Cours et Utilisateurs (par nom/code/email).
- Chaque formulaire de creation propose la possibilite d'enchainer la creation d'une autre ressource du meme type sans revenir a la liste.

### Pieges a connaitre

- Supprimer un **cours**, un **enseignant**, un **local** ou une **section** encore utilise dans le planning ou ailleurs **n'est pas bloque** par l'application : a faire avec prudence, en verifiant d'abord les usages (fiche du cours affichant l'enseignant/les sections, fiche de l'enseignant/section affichant les cours lies).
- Le champ **Annee** du cours (1re/2e/3e) est optionnel : un cours sans annee renseignee n'affichera pas de badge d'annee, ni sur la fiche, ni sur l'ecran TV.

---

## 7. Parametres du compte (Profil, Mot de passe, Apparence)

**Fichiers** : `resources/js/pages/settings/{Profile,Password,Appearance}.vue`, `resources/js/components/DeleteUser.vue`, `resources/js/components/AppearanceTabs.vue`

### 7.1 Profil

- Modification du **nom** et de l'**adresse email** du compte connecte.
- **Suppression du compte** (section separee, en bas de page, encadre rouge « Attention ») : necessite la saisie du **mot de passe actuel** pour confirmer, dans une boite de dialogue de confirmation. Action **irreversible**, supprime le compte et toutes ses ressources associees. C'est la seule voie pour un utilisateur de supprimer son propre compte (le bouton equivalent est bloque cote admin, voir section 5).

### 7.2 Mot de passe

- Formulaire : mot de passe actuel (requis pour verification), nouveau mot de passe, confirmation du nouveau mot de passe.
- Apres succes, les champs sont reinitialises automatiquement ; en cas d'erreur, seuls les champs mot de passe sont reinitialises.

### 7.3 Apparence

- Trois modes au choix : **Clair**, **Sombre**, **Systeme** (suit le theme du systeme d'exploitation), appliques immediatement au clic.

---

## 8. Connexion

**Fichier** : `resources/js/pages/auth/Login.vue`

- Ecran de connexion classique : adresse email, mot de passe, case « Se souvenir de moi ».
- Pas d'auto-inscription ni de lien « mot de passe oublie » visibles dans ce composant : la creation de comptes se fait exclusivement via l'ecran Admin > Utilisateurs (section 5), et il n'existe pas de reinitialisation de mot de passe par email dans l'application (coherent avec les garde-fous de suppression du dernier compte, section 5).
- Un message de statut (ex. confirmation) peut s'afficher en haut du formulaire selon le contexte de redirection.

---

## Points transverses a retenir pour la documentation utilisateur

- **Aucune corbeille / historique de restauration** n'existe dans l'application : toute suppression (attribution de planning, slide, image/video, compte, cours/enseignant/local/section) est definitive.
- Les operations de **masse** (insertion en masse dans le planning, import Excel avec purge) sont les points les plus a risque de perte de donnees et meritent un avertissement appuye dans la documentation utilisateur.
- Les statuts de cours dans le planning (**Planifie**, **Annule**, **En retard**) sont visibles a la fois dans le backoffice (planning) et sur l'ecran TV public.
- Les horaires des trois periodes (Matin/Apres-midi/Soir) sont **fixes et codes en dur** (00:00-12:30 / 12:30-17:30 / 17:30-23:59:59), pas configurables depuis l'interface.