<script setup lang="ts">
import DocsCallout from '@/components/docs/DocsCallout.vue';
import DocsImage from '@/components/docs/DocsImage.vue';
import DocsLayout from '@/layouts/docs/DocsLayout.vue';
</script>

<template>
    <DocsLayout
        slug="ressources"
        title="Les ressources"
        description="Enseignants, locaux, sections et cours : la matière première du planning."
    >
        <h2 id="le-principe">Le principe</h2>
        <p>
            Avant de remplir le planning, l'application doit connaître qui
            enseigne quoi, où, et à qui. C'est le rôle des quatre listes du menu
            « Ressources » :
        </p>
        <ul>
            <li><strong>Enseignants</strong> — les professeurs, par nom ;</li>
            <li>
                <strong>Locaux</strong> — les salles, nommées comme dans l'école
                (« 104 », « 004 », « - 103 » pour le sous-sol…) ;
            </li>
            <li>
                <strong>Sections</strong> — les groupes d'étudiants, en général
                une formation (« Bachelier en Informatique », « Anglais »…) ;
            </li>
            <li>
                <strong>Cours</strong> — chaque cours, avec son code officiel,
                son intitulé, son année, son enseignant et ses sections.
            </li>
        </ul>
        <p>
            Les quatre listes fonctionnent de la même façon : un champ de
            recherche en haut, un bouton « Créer » à droite, et un clic sur une
            ligne ouvre la fiche, d'où l'on peut modifier ou supprimer.
        </p>

        <DocsImage
            src="/docs-img/cours-liste.png"
            alt="La liste des cours avec la recherche, les codes, les intitulés et les badges d'année"
            caption="La liste des cours : recherche en haut, année affichée quand elle est renseignée."
        />

        <h2 id="creer-un-cours">Créer un cours</h2>
        <p>Le formulaire d'un cours comporte cinq champs :</p>
        <ul>
            <li>
                <strong>Code</strong> — l'identifiant officiel (ex. « 5IPRO »).
                Il doit être unique : deux cours ne peuvent pas porter le même
                code ;
            </li>
            <li><strong>Nom</strong> — l'intitulé complet du cours ;</li>
            <li>
                <strong>Année</strong> — 1ère, 2e ou 3e année, facultatif.
                L'année s'affiche sur la télévision, sous le nom du cours ;
            </li>
            <li>
                <strong>Enseignant</strong> — à choisir dans la liste
                (facultatif : un cours peut rester sans titulaire) ;
            </li>
            <li>
                <strong>Sections</strong> — une ou plusieurs sections qui
                suivent ce cours.
            </li>
        </ul>
        <DocsCallout variant="astuce">
            <p>
                Le bouton « Créer et créer un autre » enregistre puis rouvre un
                formulaire vide : pratique pour encoder une série de cours à la
                suite.
            </p>
        </DocsCallout>

        <DocsImage
            src="/docs-img/cours-formulaire.png"
            alt="Le formulaire de création d'un cours : code, nom, année, enseignant, sections"
            caption="Le formulaire d'un cours, avec le sélecteur d'année."
        />

        <h2 id="supprimer-une-ressource">Supprimer une ressource</h2>
        <p>
            La suppression se fait depuis la fiche de la ressource. Quelques
            conséquences à connaître :
        </p>
        <ul>
            <li>
                supprimer un <strong>enseignant</strong> ne supprime pas ses
                cours : ils restent, simplement sans titulaire ;
            </li>
            <li>
                supprimer un <strong>cours</strong> retire aussi ses apparitions
                dans le planning ;
            </li>
            <li>
                supprimer un <strong>local</strong> supprime aussi les lignes du
                planning qui s'y trouvaient — vérifiez d'abord la grille ;
            </li>
            <li>
                supprimer une <strong>section</strong> la détache simplement des
                cours qui la mentionnaient.
            </li>
        </ul>
        <DocsCallout variant="attention">
            <p>
                Une suppression est définitive : il n'y a pas de corbeille. En
                cas de doute, modifiez plutôt que de supprimer.
            </p>
        </DocsCallout>

        <h2 id="doublons-et-orthographe">Doublons et orthographe</h2>
        <p>
            Les noms de locaux, de sections et les codes de cours doivent être
            uniques : l'application refuse un doublon avec un message sous le
            champ concerné. Soignez l'orthographe dès la création — c'est le
            même libellé qui apparaîtra sur les télévisions, et l'import Excel
            s'appuie sur ces noms pour reconnaître les locaux et les cours.
        </p>
    </DocsLayout>
</template>
