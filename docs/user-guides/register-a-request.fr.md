---
description: Étapes pour enregistrer une demande de test de charge virale à partir d'une fiche papier, au laboratoire ou dans une structure sanitaire sur le STS.
audience: [lab-staff, requesting-facility]
module: [vl]
type: how-to
reviewed: 2026-09-22
reviewed_against: 5.7.74
---
# Enregistrer une demande de test de charge virale

Enregistrer un échantillon qui arrive avec une fiche papier et sans ID
d'échantillon, pour lui attribuer un ID et le placer dans la file de test.

Pour les échantillons qui arrivent dans un colis avec manifeste, ne pas les
enregistrer un par un. Voir
[Réceptionner des échantillons envoyés avec un manifeste](receive-referred-samples.md).

## Avant de commencer

- Une fiche papier de demande remplie
- La permission d'ajouter des demandes de test

## Formulaires par type de test

Chaque type de test a son propre formulaire **Ajouter une nouvelle demande**.

| Type de test | Menu |
|---|---|
| Charge virale du VIH | **CHARGE VIRALE DU VIH → Gestion des demandes → Ajouter une nouvelle demande** |
| Diagnostic précoce du nourrisson | **DIAGNOSTIC PRÉCOCE DU NOURRISSON (EID) → Gestion des demandes → Ajouter une nouvelle demande** |
| Tuberculose | **TUBERCULOSE → Gestion des demandes → Ajouter une nouvelle demande** |
| Tests personnalisés | **AUTRES EXAMENS DE LABORATOIRE → Gestion des demandes → Ajouter une nouvelle demande** |

Les étapes ci-dessous suivent le formulaire de charge virale d'un pays. Les
formulaires des autres pays utilisent d'autres libellés et champs. Les autres
types de test ont leurs propres formulaires. Par exemple, le formulaire EID
identifie l'enfant par le **Code du nourrisson**, et le formulaire des Tests
personnalisés commence par le **Type de test**. Les champs marqués d'un
astérisque rouge sont obligatoires.

**Choisir le lieu d'enregistrement de la demande, puis suivre ses étapes de haut en bas.**

=== "Au laboratoire de test"

    1. Aller à **CHARGE VIRALE DU VIH → Gestion des demandes → Ajouter une
       nouvelle demande**.
    2. Si le laboratoire imprime des étiquettes code-barres, laisser **Imprimer
       une étiquette de code-barres** cochée.

    ### Informations sur la clinique

    3. Choisir l'**État/Province**.
    4. Choisir le **District**. La liste n'affiche que les districts de la
       province choisie.
    5. Choisir la **Clinique/Centre de santé** qui a prélevé l'échantillon.

        ??? failure "Si la structure est absente de la liste"

            La structure n'est pas encore créée, ou elle n'est pas rattachée au
            test de charge virale. Demander à l'administrateur de l'ajouter. Voir
            [Administrer InteLIS](administer-intelis.md).

    6. Choisir le **Partenaire** et la **Source de financement**, si la fiche
       papier les indique.
    7. Choisir le **Laboratoire d'analyse**.

    ### Information du patient

    8. Saisir l'**ART (TRACNET) No.** exactement tel qu'il figure sur la fiche
       papier. InteLIS affiche les demandes antérieures du patient : **Nombre de
       fois où le test a été demandé pour ce patient**, **Dernière demande
       envoyée par LIS/STS** et **Date de prélèvement de l'échantillon pour la
       dernière demande**.

        ??? warning "Si la dernière demande a la même date de prélèvement que la fiche papier"

            L'échantillon est probablement déjà enregistré. Ne pas enregistrer de
            seconde demande. Rechercher d'abord le patient dans **Afficher les
            demandes de test**.

    9. Saisir la **Date de naissance**.

        ??? info "Si la fiche papier n'indique pas de date de naissance"

            Saisir **Si la date de naissance est inconnue, l'âge en années**. Pour
            un patient de moins d'un an, saisir **Si âge < 1, âge en mois**.

    10. Saisir le **Nom du patient (prénom, nom)**.
    11. Choisir le **Sexe**.

    ### Informations sur les échantillons

    12. Saisir la **Date de prélèvement de l'échantillon** figurant sur la fiche
        papier, et non la date de saisie. InteLIS remplit l'**ID de
        l'échantillon**. Il ne peut pas être saisi.
    13. Saisir la date **Échantillon envoyé le**.
    14. Choisir le **Type d'échantillon**.
    15. Saisir la **Date de réception de l'échantillon au laboratoire
        d'analyse**.

    ### Traitement et indication

    16. Remplir les **Informations sur le traitement** d'après la fiche papier :
        **Date de début du traitement**, **Régime actuel**, **Date
        d'instauration du régime actuel** et **Adhésion aux ARV**.
    17. Choisir l'**Indications pour l'analyse de la charge virale** : **Routine
        Monitoring**, **Repeat VL test after suspected treatment failure
        adherence counselling** ou **Suspect Treatment Failure**.
    18. Laisser les **Informations sur le laboratoire** vides. Elles sont
        remplies à la saisie du résultat. Voir
        [Saisir les résultats de charge virale](capture-results.md).

    ### Enregistrer

    19. Sélectionner **Sauvegarder** pour revenir à la liste des demandes, ou
        **Sauvegarder et Suivant** pour ouvrir un nouveau formulaire pour la
        fiche papier suivante.

        ??? info "Si Sauvegarder et Suivant reporte des informations sur le nouveau formulaire"

            Le laboratoire est configuré pour copier la demande dans le
            formulaire suivant. Vérifier chaque champ reporté par rapport à la
            fiche papier suivante avant d'enregistrer.

        ??? failure "Si aucune imprimante code-barres n'est proposée"

            Sélectionner **Modifier/Réessayer** pour choisir l'imprimante.

    20. Aller à **CHARGE VIRALE DU VIH → Gestion des demandes → Afficher les
        demandes de test**.
    21. Rechercher l'identifiant du patient ou l'ID de l'échantillon. La demande
        porte le statut **Sample Registered at Testing Lab** (échantillon enregistré
        au laboratoire d'analyse).

        Pour corriger une erreur, sélectionner **Modifier** sur la ligne.

    Ensuite, ajouter l'échantillon à un batch. Voir
    [Créer un batch pour le test](batch-samples.md).

=== "Dans une structure sanitaire (STS)"

    1. Aller à **CHARGE VIRALE DU VIH → Gestion des demandes → Ajouter une
       nouvelle demande**.
    2. Si la structure imprime des étiquettes code-barres, laisser **Imprimer une
       étiquette de code-barres** cochée.

    ### Informations sur la clinique

    3. Choisir l'**État/Province**.
    4. Choisir le **District**. La liste n'affiche que les districts de la
       province choisie.
    5. Choisir la **Clinique/Centre de santé** qui a prélevé l'échantillon.

        ??? failure "Si la structure est absente de la liste"

            La structure n'est pas encore créée, ou elle n'est pas rattachée au
            test de charge virale. Demander à l'administrateur de l'ajouter. Voir
            [Administrer InteLIS](administer-intelis.md).

    6. Choisir le **Partenaire** et la **Source de financement**, si la fiche
       papier les indique.
    7. Choisir le **Laboratoire d'analyse** auquel l'échantillon est destiné.

    ### Information du patient

    8. Saisir l'**ART (TRACNET) No.** exactement tel qu'il figure sur la fiche
       papier. InteLIS affiche les demandes antérieures du patient : **Nombre de
       fois où le test a été demandé pour ce patient**, **Dernière demande
       envoyée par LIS/STS** et **Date de prélèvement de l'échantillon pour la
       dernière demande**.

        ??? warning "Si la dernière demande a la même date de prélèvement que la fiche papier"

            L'échantillon est probablement déjà enregistré. Ne pas enregistrer de
            seconde demande. Rechercher d'abord le patient dans **Afficher les
            demandes de test**.

    9. Saisir la **Date de naissance**.

        ??? info "Si la fiche papier n'indique pas de date de naissance"

            Saisir **Si la date de naissance est inconnue, l'âge en années**. Pour
            un patient de moins d'un an, saisir **Si âge < 1, âge en mois**.

    10. Saisir le **Nom du patient (prénom, nom)**.
    11. Choisir le **Sexe**.

    ### Informations sur les échantillons

    12. Saisir la **Date de prélèvement de l'échantillon** figurant sur la fiche
        papier, et non la date de saisie. InteLIS remplit l'**ID de
        l'échantillon**. Il ne peut pas être saisi.
    13. Saisir la date **Échantillon envoyé le**.
    14. Choisir le **Type d'échantillon**.
    15. Laisser vide la **Date de réception de l'échantillon au laboratoire
        d'analyse**. Le laboratoire de test la remplit.

    ### Traitement et indication

    16. Remplir les **Informations sur le traitement** d'après la fiche papier :
        **Date de début du traitement**, **Régime actuel**, **Date
        d'instauration du régime actuel** et **Adhésion aux ARV**.
    17. Choisir l'**Indications pour l'analyse de la charge virale** : **Routine
        Monitoring**, **Repeat VL test after suspected treatment failure
        adherence counselling** ou **Suspect Treatment Failure**.

    ### Enregistrer

    18. Sélectionner **Sauvegarder** pour revenir à la liste des demandes, ou
        **Sauvegarder et Suivant** pour ouvrir un nouveau formulaire pour la
        fiche papier suivante.

        ??? info "Si Sauvegarder et Suivant reporte des informations sur le nouveau formulaire"

            L'installation est configurée pour copier la demande dans le
            formulaire suivant. Vérifier chaque champ reporté par rapport à la
            fiche papier suivante avant d'enregistrer.

        ??? failure "Si aucune imprimante code-barres n'est proposée"

            Sélectionner **Modifier/Réessayer** pour choisir l'imprimante.

    19. Aller à **CHARGE VIRALE DU VIH → Gestion des demandes → Afficher les
        demandes de test**.
    20. Rechercher l'identifiant du patient ou l'ID de l'échantillon. La demande
        porte le statut **Sample Currently Registered at Health Center**
        (échantillon enregistré au centre de santé).

        Pour corriger une erreur, sélectionner **Modifier** sur la ligne.

    Ensuite, envoyer les échantillons au laboratoire de test. Voir
    [Envoyer des échantillons à un laboratoire avec un manifeste](send-samples-on-a-manifest.md).
