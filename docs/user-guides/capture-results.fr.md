---
description: Faire entrer les résultats de charge virale dans InteLIS par l'outil d'interface, un fichier de l'automate ou la saisie manuelle.
audience: [lab-staff]
module: [vl, custom-tests]
type: how-to
reviewed: 2026-09-22
reviewed_against: 5.7.74
---
# Saisir les résultats de charge virale

Faire parvenir à InteLIS les résultats d'une série terminée sur l'automate.

## Avant de commencer

- Un batch dont la série est terminée sur l'automate
- La permission d'enregistrer des résultats

**Choisir la façon dont les résultats parviennent à InteLIS, puis suivre ses étapes de haut en bas.**

=== "Outil d'interface"

    À utiliser lorsque l'automate est relié à l'outil d'interface. L'outil
    d'interface transmet les résultats de charge virale du VIH, d'EID et
    d'hépatite. Il ne transmet pas les résultats de TB ni de Tests personnalisés.

    1. Sur l'automate, libérer la série. Sauter cette étape si l'automate libère
       les résultats de lui-même.
    2. Sur l'ordinateur du laboratoire, ouvrir l'outil d'interface.
    3. Vérifier que l'automate est indiqué comme **Connected**.

        ??? info "Si l'automate n'est pas indiqué comme Connected entre deux séries"

            Certains automates n'ouvrent la connexion que lorsqu'ils ont des
            résultats à envoyer. Vérifier à nouveau pendant que l'automate libère
            la série.

    4. Dans InteLIS, aller à **CHARGE VIRALE DU VIH → Gestion des demandes →
       Afficher les demandes de test**.
    5. Rechercher le code du batch. Chaque échantillon arrivé porte un résultat.

        ??? failure "Si les résultats n'arrivent pas"

            Procéder dans cet ordre.

            1. Vérifier que l'automate a libéré la série.
            2. Vérifier que l'outil d'interface fonctionne et indique l'automate
               comme **Connected**.
            3. Vérifier que les ID d'échantillon sur l'automate correspondent à
               ceux d'InteLIS. Un résultat portant un ID inconnu ne se rattache à
               aucun échantillon.
            4. Demander à l'administrateur d'ouvrir **ADMIN → Structures
               sanitaires**, de modifier le laboratoire de test et de vérifier le
               panneau **Connexions des outils d'interface** en bas de page.
               Chaque installation connectée affiche un **Statut** et une
               **Dernière connexion**. Le panneau n'apparaît que sur les
               installations où le contact support du laboratoire a activé les
               connexions de l'outil d'interface. Sans le panneau, demander au
               contact support de vérifier la connexion.

            Si les résultats n'arrivent toujours pas, saisir la série par
            **Import de fichier**.

    6. Vérifier le statut des résultats. Les résultats de l'interface arrivent
       approuvés, avec le statut **Accepted** (accepté), prêts à imprimer.

        ??? info "Si les résultats affichent Awaiting Approval"

            Le contact support du laboratoire a désactivé l'approbation
            automatique des résultats de l'interface sur cette installation. Les
            approuver. Voir
            [Vérifier et approuver les résultats](approve-results.md).

=== "Import de fichier"

    À utiliser lorsque l'automate ne peut pas joindre l'outil d'interface mais
    peut exporter un fichier de résultats.

    1. Sur l'automate, exporter les résultats de la série en fichier xls, xlsx,
       csv ou txt. Le fichier porte les ID d'échantillon du PDF du batch.
    2. Dans InteLIS, aller à **CHARGE VIRALE DU VIH → Gestion des résultats des
       tests → Importer les résultats d'un fichier**.
    3. Choisir l'automate qui a passé le batch dans **Nom de
       l'instrument/plateforme**.

        ??? failure "Si l'import est illisible ou vide"

            InteLIS lit le fichier selon la présentation de l'automate choisi ici.
            Recommencer et choisir l'automate qui a produit le fichier.

    4. Choisir le **Nom/code de la machine spécifique**.
    5. Vérifier le **Format de date**. Si l'automate a un format préconfiguré, il
       est déjà rempli. Sinon, coller une date copiée depuis le fichier. InteLIS
       en déduit le format.
    6. Choisir le **Nom du laboratoire d'analyse**.
    7. Sélectionner le fichier sous **Téléverser Charge virale du VIH Fichier**.
    8. Sélectionner **Envoyer**. InteLIS liste chaque ligne lue. La couleur de
       la case ID de chaque ligne correspond à une entrée **Source de
       l'échantillon** de la légende située au-dessus de la liste. Une marque
       colorée sur la date du test correspond à une entrée **Écart de date**.
    9. Vérifier chaque ligne par rapport à la légende.

        | Entrée de la légende | Signification | Action |
        |---|---|---|
        | Résultat pour l'ID de l'échantillon du VLSM | L'ID correspond à un échantillon enregistré | Accepter |
        | ID de l'échantillon/ID ne provenant pas du VLSM | L'ID ne correspond à aucun échantillon enregistré | Ne pas accepter. Chercher pourquoi l'ID diffère |
        | Le résultat existe déjà pour cet échantillon | L'échantillon a déjà un résultat | N'écraser que si le nouveau résultat est le bon |
        | Date du test : environ 1 mois après le prélèvement | La date du test est postérieure d'un mois ou plus au prélèvement | Vérifier la date |
        | Date du test : environ 1 an après le prélèvement | La date du test est postérieure d'un an ou plus au prélèvement | Vérifier la date. Un écart d'un an est le plus souvent une faute de frappe |

    10. Régler le **Statut** de chaque ligne : **Accepted**, **Hold**,
        **Rejected** ou **Failed**. Pour une ligne réglée sur **Rejected**,
        choisir le **Motif de rejet**.

        ??? info "Pour accepter toutes les lignes en une fois"

            Sélectionner **Accepter tous les échantillons**. Les lignes dont le
            résultat indique un échec passent à **Failed**. Les autres lignes
            sans statut passent à **Accepted** uniquement si le fichier leur a
            donné un résultat. Les lignes réglées sur **Hold** ou **Rejected**
            gardent leur statut, sauf si le résultat indique un échec. InteLIS
            nomme chaque ligne non acceptée faute de résultat lu. Régler ces
            lignes à la main.

    11. Choisir **Examiné par**, **Révisé par** et **Approuvé par**.

        ??? failure "Si InteLIS signale que la même personne examine et approuve le résultat"

            La configuration du laboratoire décide de la suite. Soit InteLIS
            demande une confirmation, soit il refuse. En cas de refus, choisir une
            autre personne pour **Approuvé par**.

    12. Sélectionner **Sauvegarder**.

        ??? failure "Si InteLIS signale qu'un ou plusieurs échantillons n'ont pas de date de test"

            Saisir la date de test manquante sur chaque ligne qui n'en a pas,
            puis sélectionner à nouveau **Sauvegarder**.

=== "Saisie manuelle"

    À utiliser uniquement lorsque l'automate ne peut ni se connecter ni exporter
    de fichier.

    1. Aller à **CHARGE VIRALE DU VIH → Gestion des résultats des tests → Saisir
       le résultat manuellement**.
    2. Dans la liste déroulante au-dessus de la liste, choisir **Résultats non
       enregistrés**. La liste n'affiche plus que les échantillons en attente de
       résultat.
    3. Sélectionner **Saisir le résultat** sur la ligne de l'échantillon.
    4. Remplir la section **Informations sur le laboratoire**.

        | Champ | À saisir |
        |---|---|
        | Date de réception de l'échantillon au laboratoire d'analyse | La date d'arrivée de l'échantillon au laboratoire |
        | Date de l'analyse de l'échantillon | La date de passage sur l'automate |
        | Plate-forme de test VL | L'automate utilisé |
        | Résultat de la charge virale (copies/mL) | Le résultat tel qu'imprimé par l'automate |
        | Révisé par, Examiné par, Approuvé par | Le personnel responsable |
        | Commentaires de Lab Technicien | Tout commentaire que le rapport doit porter |

        Les formulaires nationaux diffèrent par endroits. Par exemple,
        certains formulaires nomment le champ de l'automate **Testing
        Platform**.

        ??? failure "Si l'échantillon a été rejeté"

            Enregistrer le rejet au lieu d'un résultat.

            1. Régler **L'échantillon est-il rejeté ?** sur **Oui**.
            2. Choisir le **Motif de rejet**.
            3. Renseigner la **Date de rejet**.

            Le motif figure sur le rapport de résultat et dans le rapport de rejet
            d'échantillons.

        ??? failure "Si l'automate a rendu un échec"

            1. Saisir `Failed` comme **Résultat de la charge virale (copies/mL)**.
            2. Choisir la **Raison de l'échec**.

            L'échantillon passe dans **Échec/Echantillons en attente** pour un
            retest. Voir
            [Gérer les échecs et les échantillons en attente](failed-and-held-samples.md).

    5. Relire le résultat à l'écran par rapport au tirage de l'automate.
    6. Sélectionner **Sauvegarder**. Le résultat passe à **Awaiting Approval**
       (en attente d'approbation). Voir
       [Vérifier et approuver les résultats](approve-results.md).

=== "Tests personnalisés"

    Les Tests personnalisés n'ont ni outil d'interface ni import de fichier.
    Leurs résultats se saisissent toujours à la main, une fiche de test par test.

    1. Aller à **AUTRES EXAMENS DE LABORATOIRE → Gestion des résultats des tests
       → Saisir le résultat manuellement**.
    2. Sélectionner **Saisir le résultat** sur la ligne de l'échantillon.
    3. Sélectionner **Ajouter Test** pour un test effectué sur l'échantillon.
    4. Enregistrer le résultat de ce test sur sa fiche. Une fiche enregistre un
       test réalisé dans ce laboratoire ou un test confié à un autre laboratoire.
    5. Répéter les étapes 3 et 4 pour chaque test effectué sur l'échantillon.
    6. Régler **Passer à l'interprétation finale ?** sur **Oui**.

        ??? warning "Enregistrer d'abord toutes les fiches de test"

            La saisie de l'interprétation finale verrouille l'ajout de tests et
            les références sur l'échantillon.

    7. Saisir l'**Interprétation finale**.

        ??? failure "Si l'échantillon reste à Sample Registered at Testing Lab"

            L'interprétation finale manque. Sans elle, l'échantillon reste à
            **Sample Registered at Testing Lab** (échantillon enregistré au
            laboratoire d'analyse) quel que soit le nombre de fiches enregistrées, et il n'atteint jamais la file
            d'approbation. Rouvrir l'échantillon et refaire les étapes 6 et 7.

    8. Sélectionner **Sauvegarder**.

## Vérifier que tout fonctionne

1. Aller à **Afficher les demandes de test** sous **Gestion des demandes** pour
   le type de test.
2. Rechercher le code du batch.

Chaque échantillon de la série porte un résultat, un rejet ou un échec. Un
échantillon sans aucun des trois n'est pas parvenu à InteLIS. Vérifier son ID
par rapport à l'automate.

## Suite

[Vérifier et approuver les résultats](approve-results.md).
