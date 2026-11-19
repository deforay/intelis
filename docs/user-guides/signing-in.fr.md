---
description: Étapes pour se connecter, lire l'écran d'InteLIS, changer le mot de passe, filtrer les pages de liste et se déconnecter.
audience: [lab-staff, lab-supervisor, requesting-facility]
module: [all]
type: how-to
reviewed: 2026-09-22
reviewed_against: 5.7.74
---
# Se connecter et naviguer dans InteLIS

Un nouvel utilisateur a besoin d'un compte et d'une présentation rapide de
l'écran avant de pouvoir travailler. Ce guide couvre les deux.

## Avant de commencer

- Un identifiant de connexion et un mot de passe fournis par l'administrateur
- L'adresse web d'InteLIS pour le laboratoire

InteLIS ne permet pas la création de compte par l'utilisateur. Seul un
administrateur crée les comptes. En l'absence de compte, s'adresser à
l'administrateur.

## Se connecter

1. Ouvrir l'adresse web d'InteLIS dans un navigateur.
2. Saisir l'**Identifiant de connexion** et le **Mot de passe**.

    ??? question "Si une image avec du texte apparaît sous le mot de passe"

        L'image apparaît lorsque l'identifiant de connexion compte au moins 3
        tentatives de connexion échouées dans les 15 dernières minutes. Saisir
        le texte de l'image dans le champ situé en dessous. Pour obtenir une
        image plus lisible, sélectionner **Obtenir une nouvelle image**.

3. Sélectionner **Connexion**.

    ??? failure "Si la connexion échoue"

        | Message | Cause | Que faire |
        | --- | --- | --- |
        | Veuillez vérifier vos identifiants de connexion | L'identifiant ou le mot de passe est erroné, ou le compte est inactif | Vérifier les deux, puis réessayer. Après 3 échecs, s'adresser à l'administrateur |
        | Le texte que vous avez saisi dans l'image est incorrect. Veuillez réessayer | Le texte ne correspond pas à l'image | Saisir le texte de la nouvelle image |
        | Vous avez épuisé le nombre maximum de tentatives de connexion. Veuillez réessayer après un certain temps. | Le texte de l'image est absent ou erroné | Saisir de nouveau l'identifiant de connexion, puis le texte de l'image |

InteLIS ouvre la page définie pour le rôle. Pour la plupart des utilisateurs de
laboratoire, il s'agit du tableau de bord.

À la première connexion, et après une réinitialisation du mot de passe par
l'administrateur, InteLIS ouvre **Modifier le profil** avec le message
**Veuillez modifier votre mot de passe pour continuer.** Définir un nouveau mot
de passe avant de continuer.

## Lire l'écran

L'écran comporte trois parties.

| Partie | Contenu |
|---|---|
| Menu latéral gauche | Le menu, groupé par type de test et par fonction d'administration |
| Barre supérieure | Le menu du compte à droite, et le bouton d'affichage du menu à gauche |
| Zone centrale | La page elle-même |

Le travail de charge virale se trouve sous **CHARGE VIRALE DU VIH** dans le menu
latéral. Ce groupe comporte trois sections.

| Section | Objet |
|---|---|
| Gestion des demandes | Enregistrer les demandes, activer les manifestes, constituer les lots |
| Gestion des résultats des tests | Importer, saisir, approuver et envoyer les résultats |
| Gestion | Rapports, exports et impression |

Le menu n'affiche que ce que le rôle autorise. Une entrée absente du menu
signifie que le rôle ne l'autorise pas, et non que la fonction n'existe pas.

## Vérifier la bannière de formation

Certaines installations disposent d'une copie de formation séparée. Une copie de
formation affiche une bannière défilante en haut de l'écran. Son libellé est
défini par l'administrateur et porte la mention **TRAINING SERVER** tant qu'il
n'a pas été modifié : considérer toute bannière comme la marque d'une copie de
formation plutôt que de chercher ces deux mots.

Le travail effectué sur une copie de formation n'atteint jamais de vrais
patients. Le travail effectué sur le système réel, si. Vérifier la bannière
avant de saisir de vrais résultats.

## Changer le mot de passe

1. Sélectionner le nom du compte en haut à droite.
2. Sélectionner **Modifier le profil**.
3. Saisir le nouveau mot de passe dans **Mot de passe** et **Confirmer le mot
   de passe**. Il doit comporter au moins 8 caractères, dont au moins un
   chiffre et une lettre.
4. Sélectionner **Envoyer**.

Si le menu du compte ne comporte pas d'entrée **Modifier le profil**,
l'administrateur a désactivé la modification du profil. Demander à
l'administrateur de changer le mot de passe.

## Utiliser les filtres d'une page de liste

Toutes les pages de liste d'InteLIS fonctionnent de la même manière.

1. Si les filtres sont repliés, sélectionner **Filtres** en haut de la page
   pour les ouvrir.
2. Régler un ou plusieurs filtres.
3. Sélectionner **Rechercher**.
4. Pour effacer un filtre, sélectionner le **×** sur son étiquette au-dessus
   de la liste. Pour effacer tous les filtres, sélectionner **Réinitialiser**.

Certaines pages de liste proposent aussi **Gérer les colonnes**, qui masque ou
affiche les colonnes du tableau. Le choix est perdu au rechargement de la
page.

## Trouver un échantillon qui n'est ni dans un lot ni sur un manifeste

À utiliser lorsqu'un échantillon n'apparaît pas là où il est attendu, par
exemple pendant la création d'un lot ou d'un manifeste.

1. Aller à **Afficher les demandes de test** dans le menu **Gestion des
   demandes** du test. Pour la charge virale : **CHARGE VIRALE DU VIH →
   Gestion des demandes → Afficher les demandes de test**.
2. Ouvrir **Filtres**.
3. Dans **Batch / Manifest**, choisir **Not in a batch** ou **Not on a
   manifest**.
4. Sélectionner **Rechercher**.

Sous chaque **ID de l'échantillon**, la liste affiche le **Code du lot** et le
**Code du manifeste** de l'échantillon lorsqu'il en a. Un échantillon qui a un
code de lot est déjà dans un lot. Il ne peut pas être ajouté à un autre.

## Agir sur l'encadré À vérifier

Sur les pages **Afficher les demandes de test** de la charge virale et de
l'EID, un encadré **À vérifier** peut apparaître au-dessus de la liste. Il
s'affiche pour les utilisateurs qui peuvent modifier les demandes, et seulement
lorsque quelque chose ne va pas. Il porte sur les échantillons des 90 derniers
jours.

1. Lire chaque ligne. Elle donne le nombre d'échantillons, le problème et la
   façon de le corriger.
2. Sélectionner **Les afficher** sur une ligne. La liste ne montre plus que ces
   échantillons.
3. Ouvrir chaque échantillon et le corriger comme la ligne l'indique.
4. Pour masquer l'encadré, sélectionner le **×** dans son coin. L'encadré
   revient lorsque les nombres changent.

## Se déconnecter

1. Sélectionner le nom du compte en haut à droite.
2. Sélectionner **Déconnexion**.

Se déconnecter en fin de poste. Laisser une session ouverte sur un ordinateur
partagé permet à la personne suivante de travailler sous un autre nom, qui
apparaît alors dans le journal d'activité.

## Vérifier que tout fonctionne

Après connexion, le nom du compte apparaît en haut à droite et le menu latéral
affiche au moins un groupe. Si le menu latéral est vide, aucune permission n'est
attribuée au rôle. S'adresser à l'administrateur.
