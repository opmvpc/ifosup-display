<script setup lang="ts">
import DocsCallout from '@/components/docs/DocsCallout.vue';
import DocsImage from '@/components/docs/DocsImage.vue';
import DocsLayout from '@/layouts/docs/DocsLayout.vue';
</script>

<template>
    <DocsLayout
        slug="planning"
        title="Le planning"
        description="La grille de la semaine : placer, déplacer et annoter les cours."
    >
        <h2 id="la-grille">La grille</h2>
        <p>
            Le planning est une grille : les <strong>locaux en lignes</strong>,
            les <strong>jours en colonnes</strong>, chaque jour découpé en trois
            périodes — Matin, Après-midi, Soir. Une case correspond donc à « tel
            local, tel jour, telle période », et ne peut contenir qu'un seul
            cours à la fois.
        </p>
        <DocsImage
            src="/docs-img/planning.png"
            alt="La grille du planning : locaux en lignes, jours et périodes en colonnes, avec des cartes de cours colorées"
            caption="La grille du planning — un cours « En retard » est entouré d'orange."
        />
        <p>Quelques commodités :</p>
        <ul>
            <li>
                le sélecteur « du / au » en haut change la plage de dates
                affichée ;
            </li>
            <li>
                les boutons de <strong>zoom</strong> (4 niveaux) et le
                <strong>plein écran</strong> aident à voir large ou précis ;
            </li>
            <li>
                vos réglages (zoom, panneau ouvert ou fermé) sont mémorisés
                d'une visite à l'autre.
            </li>
        </ul>

        <h2 id="placer-un-cours">Placer un cours</h2>
        <p>
            Ouvrez la <strong>bibliothèque des cours</strong> (bouton « Afficher
            les cours », panneau à droite), retrouvez le cours avec la recherche
            (par code ou par nom, sans se soucier des accents), puis
            <strong>glissez-le</strong> vers une case vide de la grille. C'est
            enregistré immédiatement.
        </p>
        <p>
            Pour <strong>déplacer</strong> un cours déjà placé, glissez sa carte
            vers une autre case vide. Si la case visée est déjà occupée, le
            dépôt est refusé — rien ne se passe, c'est voulu : libérez d'abord
            la case.
        </p>

        <h2 id="annuler-retard-supprimer">
            Annuler, signaler un retard, supprimer
        </h2>
        <p>
            Au survol d'une carte de cours, une petite icône d'engrenage ouvre
            le menu « Actions » :
        </p>
        <ul>
            <li>
                <strong>Marquer comme annulé</strong> — la carte passe en rouge,
                et la télévision affiche « Annulé » à la place du local ;
            </li>
            <li>
                <strong>Signaler en retard</strong> — badge orange, également
                relayé sur la télévision à côté du nom du professeur ;
            </li>
            <li>
                <strong>Replanifier</strong> — retire le statut annulé/en retard
                ;
            </li>
            <li><strong>Supprimer</strong> — retire le cours de la case.</li>
        </ul>
        <DocsCallout variant="attention">
            <p>
                La suppression est <strong>immédiate et définitive</strong> : il
                n'y a ni boîte de confirmation, ni corbeille. Pour un cours qui
                n'a pas lieu, préférez « Marquer comme annulé » : les étudiants
                voient l'information sur les écrans, et vous pouvez revenir en
                arrière.
            </p>
        </DocsCallout>

        <h2 id="insertion-en-masse">L'insertion en masse</h2>
        <p>
            Pour un cours qui revient chaque semaine (tous les mardis soir en
            104, par exemple), le bouton
            <strong>« Insertion en masse »</strong> évite de le glisser semaine
            par semaine. En deux étapes :
        </p>
        <ol>
            <li>
                <strong>Configuration</strong> — choisissez le cours, le local,
                le jour de la semaine, la période, et la plage de semaines ;
            </li>
            <li>
                <strong>Aperçu</strong> — toutes les dates générées s'affichent,
                chacune avec sa case à cocher et son local modifiable ligne par
                ligne. Les conflits sont signalés : une ligne orange indique
                qu'un <strong>autre</strong> cours occupe déjà la case et « sera
                remplacé » ; une ligne grise indique que ce cours y est déjà
                (rien ne change).
            </li>
        </ol>
        <p>
            Le bouton final annonce exactement ce qui va se passer : « Insérer
            (N) », « Remplacer (N) », ou les deux.
        </p>
        <DocsCallout variant="attention">
            <p>
                Un <strong>remplacement écrase définitivement</strong> le cours
                qui occupait la case. Relisez les lignes orange avant de
                confirmer — décochez celles que vous voulez préserver.
            </p>
        </DocsCallout>
    </DocsLayout>
</template>
