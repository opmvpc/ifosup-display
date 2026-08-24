<script setup lang="ts">
import CycleSlides from '@/components/docs/diagrams/CycleSlides.vue';
import DocsCallout from '@/components/docs/DocsCallout.vue';
import DocsImage from '@/components/docs/DocsImage.vue';
import DocsLayout from '@/layouts/docs/DocsLayout.vue';
</script>

<template>
    <DocsLayout
        slug="ecran-tv"
        title="La télévision"
        description="Ce que les écrans affichent, et quand."
    >
        <h2 id="comment-ca-marche">Comment ça marche ?</h2>
        <p>
            Chaque télévision de l'école affiche la page « Écran TV » de
            l'application, en plein écran. Elle interroge le serveur en continu
            : toute modification faite dans le back-office (un cours annulé, une
            nouvelle affiche…) apparaît sur les écrans en une à deux minutes,
            sans intervention.
        </p>
        <p>
            L'écran enchaîne les slides en boucle, dans l'ordre défini sur la
            page « Slides écran » :
        </p>
        <CycleSlides class="my-6" />

        <h2 id="les-periodes-de-la-journee">Les périodes de la journée</h2>
        <p>
            Le slide « Planning » ne montre pas tout l'horaire : il s'adapte à
            l'heure qu'il est, pour n'afficher que ce qui est utile aux
            personnes présentes dans le bâtiment :
        </p>
        <ul>
            <li>
                <strong>Le matin</strong> (jusqu'à 12h30) — les cours du matin
                et ceux de l'après-midi ;
            </li>
            <li>
                <strong>L'après-midi</strong> (12h30 à 17h30) — les cours de
                l'après-midi et ceux du soir ;
            </li>
            <li>
                <strong>Le soir</strong> (après 17h30) — uniquement les cours du
                soir.
            </li>
        </ul>
        <p>
            Chaque période s'affiche comme un tableau : le cours (avec son code
            et son année), le professeur, les sections concernées et le local.
            Si la liste est plus longue que l'écran, elle défile doucement vers
            le bas, puis passe au slide suivant.
        </p>

        <DocsImage
            src="/docs-img/ecran-planning.png"
            alt="Le slide planning sur la télévision : cours, professeur, sections et local, avec un professeur signalé en retard"
            caption="Le planning tel qu'affiché sur les téléviseurs — année sous le nom du cours, retard signalé en orange."
        />

        <h2 id="statuts-annule-et-en-retard">
            Cours annulé, professeur en retard
        </h2>
        <p>
            Les statuts posés dans le planning se voient immédiatement sur les
            écrans : une ligne <strong>annulée</strong> passe en rouge avec la
            mention « Annulé » à la place du local ; un professeur
            <strong>en retard</strong> est signalé par une pastille orange à
            côté de son nom.
        </p>

        <h2 id="verifier-le-resultat">Vérifier le résultat</h2>
        <p>
            Le lien « Écran TV », en bas de la barre latérale, ouvre exactement
            ce que diffusent les télévisions. C'est le bon réflexe après une
            modification importante : vous voyez le résultat sans vous déplacer.
        </p>
        <DocsCallout variant="astuce">
            <p>
                Cette page est publique (les téléviseurs ne se connectent pas
                avec un compte), mais elle ne révèle que le planning du jour et
                les slides — rien d'autre.
            </p>
        </DocsCallout>
    </DocsLayout>
</template>
