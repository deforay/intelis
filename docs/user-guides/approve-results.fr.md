# Vérifier et approuver les résultats

Un résultat ne parvient à la structure demandeuse qu'après son approbation.
L'approbation confirme que le résultat enregistré dans InteLIS est bien celui
rendu par l'automate, pour le bon échantillon.

Les résultats importés depuis un fichier ou saisis à la main passent toujours
par cette page. Les résultats transmis par l'outil d'interface peuvent être
approuvés automatiquement, si le laboratoire est configuré ainsi.

## Avant de commencer

- Des résultats saisis dans InteLIS. Voir
  [Saisir les résultats de charge virale](capture-results.md)
- La permission de gérer le statut des résultats
- Le tirage de l'automate ou la liste de travail de la série

## Changer le statut d'un échantillon

**Choisir l'action, puis suivre ses étapes de haut en bas.**

=== "Accepter"

    À utiliser pour approuver les résultats conformes au tirage de l'automate.

    1. Aller à **CHARGE VIRALE DU VIH → Gestion des résultats des tests → Gérer le
       statut des résultats**.
    2. Régler **Afficher les échantillons qui sont** sur **Non approuvé/rejeté**.
       Cette liste montre les échantillons qui ont un résultat et attendent
       l'approbation.
    3. Régler **Code de batch** sur la série de l'automate. Une seule série à
       l'écran se vérifie contre un seul tirage.

        ??? info "Autres filtres"

            | Filtre | Usage |
            | --- | --- |
            | Date du test de l'échantillon | Tout ce qui a été testé à une date |
            | Nom de la structure | Les échantillons d'une seule structure |
            | Date de prélèvement de l'échantillon | Une période de prélèvement |
            | Type d'échantillon | Un seul type de prélèvement |
            | Code du manifeste | Les échantillons d'un colis reçu |

    4. Sélectionner **Rechercher**.
    5. Pour chaque ligne, vérifier sur le tirage que l'**ID de l'échantillon**
       correspond, que le **Résultat** correspond, et que le patient est bien
       celui attendu pour cet ID.

        ??? failure "Si l'ID de l'échantillon et le patient ne correspondent pas"

            L'échantillon a été enregistré sur le mauvais patient, ou chargé dans
            la mauvaise position de l'automate. Ne pas cocher la ligne. Elle reste
            sous **Non approuvé/rejeté** et n'est pas diffusée. Corriger
            l'enregistrement ou le résultat, puis revenir à l'étape 1.

        ??? warning "Tests personnalisés : contrôler d'abord les fiches de test"

            Pour les Tests personnalisés, aller à **AUTRES EXAMENS DE LABORATOIRE
            → Gestion des résultats des tests → Gérer le statut des résultats**.
            Les étapes sont les mêmes. La liste n'affiche que l'interprétation
            finale de l'échantillon, pas les fiches de test qui la fondent.
            Ouvrir l'écran de résultat de l'échantillon et contrôler chaque fiche
            de test avant de cocher la ligne.

    6. Cocher les lignes conformes.
    7. Dans **Actions groupées**, régler **Statut** sur **Accepté**.
    8. Renseigner **Approbateur**. Renseigner **Tester** et **Réviseur** si le
       laboratoire les enregistre.

        ??? info "Noms déjà enregistrés sur l'échantillon"

            Un nom déjà présent sur l'échantillon est conservé. Pour l'écraser,
            cocher **Remplacer l'existant** sous ce champ.

        ??? info "Si la même personne est choisie pour deux rôles"

            InteLIS demande confirmation. Sélectionner **OK** uniquement si le
            laboratoire autorise une personne à cumuler ces rôles.

    9. Sélectionner **Appliquer**.
    10. Sélectionner **OK** pour confirmer. InteLIS affiche
        `Mis à jour avec succès.`

        ??? failure "Si le message liste des échantillons non acceptés"

            `Non accepté car aucun résultat n'est enregistré` nomme les
            échantillons sans résultat. Ils gardent leur statut. Saisir d'abord
            le résultat. Voir
            [Saisir les résultats de charge virale](capture-results.md).

        ??? info "Si un échantillon accepté affiche Échec/Invalidité"

            Un résultat qui correspond à un échec ou à une série invalide ne peut
            pas être accepté. InteLIS attribue alors le statut
            **Échec/Invalidité**. Voir
            [Gérer les échecs et les échantillons en attente](failed-and-held-samples.md).

    11. Régler **Afficher les échantillons qui sont** sur **Déjà approuvé/rejeté**,
        puis sélectionner **Rechercher**. La colonne **Statut** des échantillons
        acceptés affiche `Accepted`.

    Suite : [diffuser les résultats à la structure demandeuse](release-results.md).

=== "Rejeter"

    À utiliser lorsque l'échantillon n'était pas propre au test, par exemple un
    prélèvement hémolysé ou en quantité insuffisante.

    1. Aller à **CHARGE VIRALE DU VIH → Gestion des résultats des tests → Gérer le
       statut des résultats**.
    2. Régler **Afficher les échantillons qui sont** sur **Non approuvé/rejeté**.

        ??? info "Si l'échantillon n'a pas encore de résultat"

            **Non approuvé/rejeté** ne liste que les échantillons qui ont un
            résultat. Régler plutôt **Afficher les échantillons qui sont** sur
            **Peut être annulé**. Cette liste montre tous les échantillons qui ne
            sont pas déjà Expiré ou Annulée.

    3. Filtrer jusqu'à l'échantillon, par exemple par **Code de batch** ou **Nom
       de la structure**.
    4. Sélectionner **Rechercher**.
    5. Cocher les échantillons à rejeter.
    6. Dans **Actions groupées**, régler **Statut** sur **Rejeté**.
    7. Choisir un **Motif de rejet**. Choisir le motif qui indique à la structure
       ce qu'elle doit changer la prochaine fois. Il figure sur le rapport envoyé
       à la structure et dans le rapport de rejet d'échantillons.

        !!! warning "Le rejet efface le résultat"

            Un résultat déjà enregistré est effacé de l'échantillon. InteLIS
            conserve le résultat effacé dans l'historique des tests de
            l'échantillon.

    8. Sélectionner **Appliquer**.
    9. Sélectionner **OK** pour confirmer. InteLIS affiche
       `Mis à jour avec succès.`
    10. Aller à **CHARGE VIRALE DU VIH → Gestion des demandes → Afficher les
        demandes de test** et rechercher l'échantillon. La colonne **Statut** affiche `Rejected`.

    Suite : [diffuser le rejet à la structure demandeuse](release-results.md),
    pour qu'elle puisse effectuer un nouveau prélèvement.

=== "Marquer perdu"

    À utiliser lorsque l'échantillon est introuvable et ne sera pas testé.

    1. Aller à **CHARGE VIRALE DU VIH → Gestion des résultats des tests → Gérer le
       statut des résultats**.
    2. Régler **Afficher les échantillons qui sont** sur **Peut être annulé**.
       Cette liste montre tous les échantillons qui ne sont pas déjà Expiré ou
       Annulée, avec ou sans résultat.
    3. Filtrer jusqu'à l'échantillon, par exemple par **Nom de la structure** ou
       **Date de prélèvement de l'échantillon**.
    4. Sélectionner **Rechercher**.
    5. Cocher les échantillons.
    6. Dans **Actions groupées**, régler **Statut** sur **Perdu**.
    7. Sélectionner **Appliquer**.
    8. Sélectionner **OK** pour confirmer. InteLIS affiche
       `Mis à jour avec succès.`
    9. Aller à **CHARGE VIRALE DU VIH → Gestion des demandes → Afficher les
       demandes de test** et rechercher l'échantillon. La colonne **Statut** affiche `Lost`.

=== "Annuler"

    À utiliser uniquement lorsque le test n'aura pas lieu du tout, par exemple
    une demande saisie deux fois ou retirée par le clinicien.

    !!! warning "Ne pas annuler un échantillon en échec"

        Un échantillon annulé compte comme jamais testé. Il sort des volumes de
        test et du délai de rendu. Un échantillon en échec reste dans le taux
        d'échec. Pour un échantillon en échec, voir
        [Gérer les échecs et les échantillons en attente](failed-and-held-samples.md).

    1. Aller à **CHARGE VIRALE DU VIH → Gestion des résultats des tests → Gérer le
       statut des résultats**.
    2. Régler **Afficher les échantillons qui sont** sur **Peut être annulé**.
    3. Filtrer jusqu'à l'échantillon, par exemple par **Nom de la structure** ou
       **Date de prélèvement de l'échantillon**.
    4. Sélectionner **Rechercher**.
    5. Cocher les échantillons.
    6. Dans **Actions groupées**, régler **Statut** sur **Annulée**.
    7. Sélectionner **Appliquer**. La fenêtre **Confirmer l'annulation** s'ouvre.
    8. Saisir `CANCEL` dans la case.
    9. Sélectionner **Confirmer l'annulation**. InteLIS affiche
       `Mis à jour avec succès.`
    10. Aller à **CHARGE VIRALE DU VIH → Gestion des demandes → Afficher les
        demandes de test** et rechercher l'échantillon. La colonne **Statut** affiche `Cancelled`.

## Corriger un résultat approuvé

La page Gérer le statut des résultats ne modifie que le statut et les noms du
personnel. Elle ne modifie jamais la valeur du résultat. Pour corriger une
valeur fausse :

1. Aller à **CHARGE VIRALE DU VIH → Gestion des résultats des tests → Saisir le
   résultat manuellement**.
2. Régler le filtre de la liste sur **Résultats enregistrés**.
3. Trouver l'échantillon et sélectionner **Saisir le résultat**.

    ??? failure "Si la ligne affiche Verrouillé"

        Les échantillons se verrouillent après le nombre de jours défini dans
        **Jours de verrouillage des échantillons** sous **ADMIN → Configuration du
        système → Configuration générale**. Demander à l'administrateur de
        corriger un échantillon verrouillé.

4. Saisir le bon résultat.
5. Indiquer la raison de la modification du résultat.
6. Enregistrer le formulaire. L'échantillon revient à **En attente
   d'approbation**.
7. L'approuver de nouveau avec les étapes **Accepter** ci-dessus.

Si le résultat faux avait déjà été imprimé ou envoyé par courriel, diffuser de
nouveau le résultat corrigé. Voir
[Diffuser les résultats à la structure demandeuse](release-results.md).
