<script setup lang="ts">
import DocsCallout from '@/components/docs/DocsCallout.vue';
import DocsImage from '@/components/docs/DocsImage.vue';
import DocsLayout from '@/layouts/docs/DocsLayout.vue';
</script>

<template>
    <DocsLayout
        slug="slides"
        title="Les slides de l'écran"
        description="Composer le diaporama diffusé sur les télévisions : planning, affiches, vidéos, message d'accueil."
    >
        <h2 id="les-types-de-slides">Les types de slides</h2>
        <p>
            La page « Slides écran » montre le diaporama sous forme de
            vignettes, dans l'ordre de passage à l'écran. Quatre types existent
            :
        </p>
        <DocsImage
            src="/docs-img/slides.png"
            alt="La page Slides écran : vignettes des slides dans l'ordre de passage, avec leurs durées"
            caption="Les vignettes du diaporama, dans l'ordre de passage sur les écrans."
        />
        <ul>
            <li>
                <strong>Bienvenue</strong> — toujours en première position,
                impossible à supprimer ou à déplacer. Il porte le « mot du jour
                », un message libre de 280 caractères maximum ;
            </li>
            <li>
                <strong>Planning</strong> — affiche automatiquement le planning
                du jour, adapté à l'heure (voir
                <a href="/docs/ecran-tv">La télévision</a>). Rien à configurer :
                sa durée s'adapte au contenu ;
            </li>
            <li>
                <strong>Image</strong> — une affiche (JPG, PNG, GIF, WebP…, 10
                Mo max) montrée pendant une durée réglable ;
            </li>
            <li>
                <strong>Vidéo</strong> — un fichier MP4, WebM ou MOV (300 Mo
                max), joué jusqu'à la fin, sans le son.
            </li>
        </ul>
        <DocsCallout variant="astuce">
            <p>
                Les images et vidéos gardent leurs proportions à l'écran : elles
                s'affichent entières, sur un fond bleu foncé, sans être étirées
                ni recadrées. Pour occuper tout l'écran, préparez vos visuels au
                format 16:9 (par exemple 1920 × 1080).
            </p>
        </DocsCallout>

        <h2 id="ajouter-modifier-reordonner">Ajouter, modifier, réordonner</h2>
        <ul>
            <li>
                <strong>Ajouter</strong> — la grande case « Ajouter » en fin de
                liste propose le type (Planning, Image ou Vidéo), puis le
                formulaire correspondant ;
            </li>
            <li>
                <strong>Personnaliser</strong> — l'icône de réglages sur une
                vignette ouvre sa configuration : le mot du jour pour Bienvenue,
                la durée (1 seconde minimum, 2 minutes maximum) et/ou le
                remplacement du fichier pour une image, le remplacement du
                fichier pour une vidéo ;
            </li>
            <li>
                <strong>Réordonner</strong> — les flèches gauche/droite
                déplacent la vignette dans l'ordre de passage (sauf Bienvenue,
                verrouillé en tête) ;
            </li>
            <li>
                <strong>Supprimer</strong> — l'icône corbeille, avec
                confirmation.
            </li>
        </ul>
        <p>
            Chaque changement est visible sur les télévisions en une à deux
            minutes, sans autre manipulation.
        </p>

        <h2 id="bon-a-savoir">Bon à savoir</h2>
        <ul>
            <li>
                remplacer l'image ou la vidéo d'un slide supprime l'ancien
                fichier : il n'est pas conservé ;
            </li>
            <li>la suppression d'un slide est définitive, fichier compris ;</li>
            <li>
                le slide Planning peut apparaître plusieurs fois à l'écran (une
                fois par période visible : par exemple matin puis après-midi) —
                c'est normal.
            </li>
        </ul>
    </DocsLayout>
</template>
