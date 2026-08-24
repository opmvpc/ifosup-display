<script setup lang="ts">
import FluxImportExcel from '@/components/docs/diagrams/FluxImportExcel.vue';
import DocsCallout from '@/components/docs/DocsCallout.vue';
import DocsImage from '@/components/docs/DocsImage.vue';
import DocsLayout from '@/layouts/docs/DocsLayout.vue';
</script>

<template>
    <DocsLayout
        slug="import-excel"
        title="L'import Excel"
        description="Charger un horaire complet depuis un classeur Excel, en toute sécurité."
    >
        <h2 id="a-quoi-ca-sert">À quoi ça sert ?</h2>
        <p>
            Plutôt que d'encoder des centaines de créneaux à la main, l'import
            Excel lit un classeur d'horaire (.xlsx ou .xls, 20 Mo maximum) et
            remplit le planning en une fois. Rien n'est importé sans votre
            validation : entre le dépôt du fichier et l'import réel, un écran de
            vérification détaille tout ce qui va se passer.
        </p>
        <FluxImportExcel class="my-6" />

        <DocsImage
            src="/docs-img/import-excel.png"
            alt="L'écran d'import Excel avec la zone de dépôt du fichier et le choix de l'année scolaire"
            caption="L'écran de dépôt du fichier d'horaire."
        />

        <h2 id="deroulement">Le déroulement</h2>
        <ol>
            <li>
                <strong>Déposer le fichier</strong> — glissez le classeur dans
                la zone prévue (ou cliquez pour le choisir) et indiquez l'année
                scolaire de départ ;
            </li>
            <li>
                <strong>Vérifier le résumé</strong> — l'application analyse le
                fichier et présente : le nombre de créneaux détectés, la plage
                de dates couverte, les locaux (les nouveaux seront créés
                automatiquement), les cours reconnus (cochez ceux à importer) et
                les cours ignorés ;
            </li>
            <li>
                <strong>Confirmer</strong> — le bouton « Importer N
                attribution(s) » applique le tout, ou « Annuler » abandonne sans
                rien toucher.
            </li>
        </ol>
        <p>
            Trois compteurs résument l'impact avant de confirmer :
            <strong>Créations</strong>, <strong>Remplacements</strong> et
            <strong>Ignorées</strong>.
        </p>

        <h2 id="structure-du-fichier">La structure du fichier</h2>
        <p>
            Pas besoin de titres de colonnes particuliers : l'application repère
            elle-même les blocs d'horaire dans chaque feuille du classeur. Elle
            cherche une cellule contenant le mot
            <strong>« Matin »</strong>, <strong>« Midi »</strong> (= après-midi)
            ou <strong>« Soir »</strong>, puis lit autour de cette cellule :
        </p>
        <ul>
            <li>
                <strong>la colonne juste à gauche</strong> du mot-clé contient
                les <strong>noms des locaux</strong>, un par ligne ;
            </li>
            <li>
                <strong>la ligne juste en dessous</strong> contient les
                <strong>dates</strong> : la première au format « jour/mois »
                (ex. 15/09), et chaque colonne suivante vaut automatiquement
                <strong>une semaine de plus</strong> — inutile de toutes les
                écrire correctement, seule la première compte. L'année vient du
                champ « année scolaire » choisi au moment du dépôt ;
            </li>
            <li>
                la ligne sous les dates est <strong>libre</strong> (elle est
                ignorée — vous pouvez y mettre ce que vous voulez) ;
            </li>
            <li>
                ensuite, chaque cellule contient le
                <strong>code du cours</strong> donné dans ce local à cette date.
                Une cellule vide = pas de cours. Deux lignes vides d'affilée
                terminent le bloc.
            </li>
        </ul>
        <p>
            Concrètement, un bloc « Matin » ressemble à ceci (les couleurs sont
            décoratives, seul le contenu compte) :
        </p>
        <table>
            <thead>
                <tr>
                    <th>Local</th>
                    <th>Matin</th>
                    <th></th>
                    <th></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td></td>
                    <td><strong>15/09</strong></td>
                    <td><strong>22/09</strong></td>
                    <td><strong>29/09</strong></td>
                    <td><strong>06/10</strong></td>
                </tr>
                <tr>
                    <td colspan="5">
                        <em>(ligne libre, ignorée par l'import)</em>
                    </td>
                </tr>
                <tr>
                    <td>104</td>
                    <td>5IPRO</td>
                    <td>5IPRO</td>
                    <td>5IPRO</td>
                    <td>5IPRO</td>
                </tr>
                <tr>
                    <td>105</td>
                    <td>ANG-UE1</td>
                    <td>ANG-UE1</td>
                    <td></td>
                    <td>ANG-UE1</td>
                </tr>
                <tr>
                    <td>- 103</td>
                    <td>5XENV</td>
                    <td>5XENV</td>
                    <td>5XENV</td>
                    <td>5XENV</td>
                </tr>
            </tbody>
        </table>
        <p>
            Une même feuille peut enchaîner plusieurs blocs (Matin, puis Midi,
            puis Soir), et le classeur peut contenir plusieurs feuilles — par
            exemple une par jour de la semaine (« Lundi », « Mardi »…) : toutes
            sont lues.
        </p>
        <DocsCallout variant="astuce" title="Modèle prêt à l'emploi">
            <p>
                <a href="/docs-img/modele-import-planning.xlsx" download
                    >Téléchargez le modèle Excel</a
                >
                : il contient deux feuilles d'exemple (Lundi et Mardi) avec la
                structure attendue — remplacez simplement les dates, les locaux
                et les codes de cours par les vôtres.
            </p>
        </DocsCallout>

        <h2 id="cours-reconnus-ou-ignores">Cours reconnus ou ignorés</h2>
        <p>
            L'application fait le lien entre le fichier et la base grâce au
            <strong>code du cours</strong> (et au nom du local), sans se soucier
            des majuscules ni des espaces en trop. Un code présent dans le
            fichier mais absent de la base rend le cours
            <strong>« ignoré »</strong> : il ne sera jamais importé tout seul.
        </p>
        <DocsCallout variant="astuce">
            <p>
                Des cours ignorés ? Créez-les d'abord dans « Ressources > Cours
                » avec le code exact du fichier, puis revenez ici et cliquez «
                Re-analyser » : ils passeront dans les cours reconnus.
            </p>
        </DocsCallout>

        <h2 id="conflits-et-purge">Conflits et purge</h2>
        <p>Deux situations demandent votre attention avant de confirmer :</p>
        <ul>
            <li>
                <strong>Les conflits</strong> — un créneau du fichier (date,
                période, local) est déjà occupé par un autre cours : l'import le
                <strong>remplacera</strong>. La liste exacte des créneaux
                concernés est affichée ;
            </li>
            <li>
                <strong>La purge</strong> — une case à cocher, dans une zone
                rouge bien identifiée, vide
                <strong>tout le planning existant</strong> sur la plage de dates
                du fichier avant d'importer. Utile pour repartir de zéro sur un
                semestre, dangereuse sinon.
            </li>
        </ul>
        <DocsCallout variant="attention">
            <p>
                Remplacements et purge sont
                <strong>définitifs, sans retour en arrière</strong>. La purge
                supprime aussi les créneaux ajoutés à la main dans la période.
                Dans le doute, importez sans purge et examinez la liste des
                conflits.
            </p>
        </DocsCallout>
        <p>
            Bon à savoir : l'import est « tout ou rien ». Si une erreur survient
            en cours de traitement, rien n'est modifié — le planning reste
            exactement comme avant.
        </p>

        <h2 id="fichier-en-attente">Un fichier resté en attente ?</h2>
        <p>
            Si un fichier a été déposé puis jamais importé (par vous ou un
            collègue), l'écran le propose au retour : « Re-analyser » pour
            reprendre où c'en était, ou « Ignorer et uploader un autre fichier »
            pour repartir d'un nouveau classeur.
        </p>
    </DocsLayout>
</template>
