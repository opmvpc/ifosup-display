<script setup lang="ts">
import PlanningVersTv from '@/components/docs/diagrams/PlanningVersTv.vue';
import DocsCallout from '@/components/docs/DocsCallout.vue';
import DocsLayout from '@/layouts/docs/DocsLayout.vue';
</script>

<template>
    <DocsLayout
        slug="introduction"
        title="Bien démarrer"
        description="À quoi sert l'application et comment s'y retrouver."
    >
        <h2 id="a-quoi-sert-l-application">À quoi sert l'application ?</h2>
        <p>
            IFOSUP Display pilote ce qui s'affiche sur les téléviseurs de
            l'école : le planning des cours du jour (local par local), des
            affiches (images), des vidéos et un écran de bienvenue. Vous
            préparez le contenu ici, dans le back-office ; les télévisions se
            mettent à jour toutes seules, sans aucune manipulation sur place.
        </p>
        <PlanningVersTv class="my-6" />
        <p>Le principe tient en trois étapes :</p>
        <ol>
            <li>
                <strong>Encoder les ressources</strong> — la liste des
                enseignants, des locaux, des sections (groupes d'étudiants) et
                des cours. C'est la matière première du planning.
            </li>
            <li>
                <strong>Remplir le planning</strong> — en plaçant les cours dans
                la grille, à la main ou via l'import d'un fichier Excel.
            </li>
            <li>
                <strong>Laisser faire la télévision</strong> — elle affiche
                automatiquement le planning du jour, au bon moment de la
                journée, en alternance avec vos images et vidéos.
            </li>
        </ol>

        <h2 id="se-connecter">Se connecter</h2>
        <p>
            L'application est réservée au personnel de l'école. Rendez-vous sur
            la page de connexion, saisissez votre adresse e-mail et votre mot de
            passe, puis cliquez sur « Se connecter ». La case « Se souvenir de
            moi » évite de retaper votre mot de passe à chaque visite sur cet
            ordinateur.
        </p>
        <DocsCallout variant="astuce">
            <p>
                Votre compte est créé par un collègue déjà inscrit, depuis le
                menu « Utilisateurs ». Pas de compte ? Voyez la page
                <a href="/docs/utilisateurs">Les utilisateurs</a>.
            </p>
        </DocsCallout>

        <h2 id="tour-de-l-interface">Le tour de l'interface</h2>
        <p>
            La barre latérale gauche regroupe tous les menus, en trois familles
            :
        </p>
        <ul>
            <li>
                <strong>Ressources</strong> — Enseignants, Locaux, Sections et
                Cours : les listes de base que le planning utilise.
            </li>
            <li>
                <strong>Affichage</strong> — Planning (la grille de la semaine),
                Import Excel (pour charger un horaire complet) et Slides écran
                (le contenu diffusé sur les télévisions).
            </li>
            <li>
                <strong>Administration</strong> — Utilisateurs : les comptes qui
                ont accès à cette application.
            </li>
        </ul>
        <p>
            Tout en bas, « Écran TV » ouvre l'affichage tel que le voient les
            télévisions — pratique pour vérifier le résultat — et «
            Documentation » ramène ici.
        </p>

        <h2 id="par-ou-commencer">Par où commencer ?</h2>
        <p>La première fois, suivez simplement l'ordre des chapitres :</p>
        <ol>
            <li>
                Créez vos <a href="/docs/ressources">ressources</a> (ou vérifiez
                celles déjà en place) ;
            </li>
            <li>
                Remplissez le <a href="/docs/planning">planning</a> de la
                semaine, à la main ou par
                <a href="/docs/import-excel">import Excel</a> ;
            </li>
            <li>
                Ajustez les <a href="/docs/slides">slides de l'écran</a>
                (affiches, vidéos, message d'accueil) ;
            </li>
            <li>
                Contrôlez le résultat sur
                <a href="/docs/ecran-tv">la télévision</a>.
            </li>
        </ol>
    </DocsLayout>
</template>
