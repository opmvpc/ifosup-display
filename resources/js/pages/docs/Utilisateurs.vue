<script setup lang="ts">
import DocsCallout from '@/components/docs/DocsCallout.vue';
import DocsLayout from '@/layouts/docs/DocsLayout.vue';
</script>

<template>
    <DocsLayout
        slug="utilisateurs"
        title="Les utilisateurs"
        description="Créer et gérer les comptes qui ont accès à l'application."
    >
        <h2 id="qui-a-acces">Qui a accès à quoi ?</h2>
        <p>
            Toute personne disposant d'un compte peut tout faire dans
            l'application : gérer les ressources, le planning, les slides et les
            autres comptes. Il n'y a pas de niveaux de droits — réservez donc
            les comptes aux personnes de confiance qui en ont réellement besoin.
        </p>
        <p>
            Il n'y a ni inscription libre, ni « mot de passe oublié » : les
            comptes se créent uniquement ici, et un mot de passe perdu se
            remplace par un collègue via ce même menu.
        </p>

        <h2 id="creer-un-compte">Créer un compte</h2>
        <p>
            Menu « Utilisateurs » > « Créer » : nom, adresse e-mail, mot de
            passe (et sa confirmation). Communiquez le mot de passe à la
            personne concernée et invitez-la à le changer dès sa première
            connexion, dans « Paramètres > Mot de passe ».
        </p>

        <h2 id="modifier-ou-depanner">Modifier un compte, dépanner</h2>
        <p>
            La fiche d'un compte permet de corriger le nom ou l'adresse e-mail,
            et de définir un <strong>nouveau mot de passe</strong>
            (laissez le champ vide pour ne pas y toucher). C'est la solution
            quand quelqu'un a oublié le sien : saisissez-en un nouveau et
            transmettez-le. La personne sera déconnectée de ses sessions en
            cours, par sécurité.
        </p>

        <h2 id="supprimer-un-compte">Supprimer un compte</h2>
        <p>
            Le bouton « Supprimer » se trouve sur la fiche du compte, dans la
            zone rouge. Deux protections existent :
        </p>
        <ul>
            <li>
                impossible de supprimer <strong>son propre compte</strong>
                depuis ce menu (cela se fait dans « Paramètres > Profil », en
                confirmant son mot de passe) ;
            </li>
            <li>
                impossible de supprimer <strong>le dernier compte</strong> :
                plus personne ne pourrait se connecter.
            </li>
        </ul>
        <DocsCallout variant="attention">
            <p>
                La suppression d'un compte est immédiate et définitive. Pensez à
                retirer le compte d'un collègue qui quitte l'école.
            </p>
        </DocsCallout>

        <h2 id="parametres-personnels">Vos paramètres personnels</h2>
        <p>
            Le menu en bas de la barre latérale (votre nom) ouvre les paramètres
            du compte connecté : modifier son nom et son adresse (« Profil »),
            changer son mot de passe, et choisir l'apparence de l'application —
            thème clair, sombre, ou celui du système.
        </p>
    </DocsLayout>
</template>
