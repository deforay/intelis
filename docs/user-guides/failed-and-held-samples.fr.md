# Gérer les échecs et les échantillons en attente

Les échantillons en échec sur l'automate, mis en attente ou perdus sont regroupés
sur une seule page. Elle sert à les renvoyer au test, ou à récupérer des
résultats marqués en échec par erreur.

## Avant de commencer

- La permission de consulter les échantillons en échec et en attente
- La permission de modifier les demandes de test, pour les boutons de ligne et
  pour la récupération

## Déterminer ce dont l'échantillon a besoin

| Situation | Action | Où |
| --- | --- | --- |
| Le test a échoué, l'échantillon est encore exploitable et le volume restant suffit | Retester | Étapes **Retester** ci-dessous |
| Un import a marqué en échec une série valable | Récupérer | Étapes **Récupérer une série marquée en échec par erreur** ci-dessous |
| L'échantillon n'est pas propre au test, la structure doit refaire le prélèvement | Rejeter avec un motif | [Rejeter](approve-results.md) |
| L'échantillon est introuvable | Marquer perdu | [Marquer perdu](approve-results.md) |
| La demande a été saisie deux fois, ou retirée | Annuler | [Annuler](approve-results.md) |

Ne pas annuler un échantillon pour effacer un échec. Un échantillon annulé compte
comme jamais testé et sort des volumes de test et du délai de rendu. Un
échantillon en échec reste dans le taux d'échec, qui est l'indicateur de qualité
dont le laboratoire a besoin.

## Ce que la page liste

| Statut | Signification |
| --- | --- |
| Echec | L'automate a rendu un échec ou une lecture invalide |
| En attente | L'échantillon est suspendu dans l'attente d'une décision |
| Perdu | L'échantillon est introuvable |

??? info "Comment un échantillon passe En attente"

    Aucun écran de charge virale d'InteLIS n'attribue En attente à un
    échantillon. Sur l'écran **Résultats importés** qui suit **Importer les
    résultats d'un fichier**, choisir **En attente** pour une ligne met le
    résultat de cette ligne de côté. Le résultat n'est pas enregistré sur
    l'échantillon, et l'échantillon reste en attente d'un résultat. Un
    échantillon En attente sur cette page portait déjà ce statut avant d'y
    arriver.

Pour tous les statuts, voir [Statuts des échantillons](sample-statuses.md).

## Suivre les étapes

**Choisir la situation, puis suivre ses étapes de haut en bas.**

=== "Retester"

    1. Aller à **CHARGE VIRALE DU VIH → Gestion des résultats des tests →
       Échec/Echantillons en attente**.
    2. Vérifier **Résultat Statut**. **Echec** et **En attente** sont
       sélectionnés par défaut. Ajouter **Perdu** si nécessaire.
    3. Restreindre avec les autres filtres si nécessaire, par exemple **Nom de la
       structure**, **Date de prélèvement de l'échantillon** ou **Code du
       manifeste**.
    4. Sélectionner **Rechercher**.
    5. Cocher les échantillons à retester. Le bouton **Retester les échantillons
       sélectionnés** apparaît.

        ??? info "Pour retester un seul échantillon"

            Sélectionner plutôt **Retester** sur la ligne de cet échantillon,
            puis passer à l'étape 7.

    6. Sélectionner **Retester les échantillons sélectionnés**.
    7. InteLIS affiche `Le nouveau test a été soumis.` Les échantillons quittent
       cette liste.

        ??? info "Ce que fait le retest"

            Le résultat est effacé et l'échantillon quitte son batch. Son statut
            revient à **Échantillon enregistré au laboratoire d'analyse**.
            InteLIS conserve la tentative en échec, de sorte que les rapports de
            performance du laboratoire comptent à la fois l'échec et le retest.

    8. Aller à **CHARGE VIRALE DU VIH → Gestion des demandes → Afficher les
       demandes de test** et rechercher l'échantillon. La colonne **Statut**
       affiche `Sample Registered at Testing Lab`, et le résultat est vide.
    9. Ajouter l'échantillon à un nouveau batch. Voir
       [Créer un batch pour le test](batch-samples.md).

    ??? info "Si l'étiquette code-barres du tube est abîmée"

        La réimprimer depuis **CHARGE VIRALE DU VIH → Gestion des demandes →
        Afficher les demandes de test**. Rechercher l'échantillon et
        sélectionner **Code barre** sur sa ligne. Le bouton n'apparaît que si
        **Impression d'étiquettes code-barres des échantillons** sous **ADMIN →
        Configuration du système → Configuration générale** n'est pas réglé sur
        **Désactivé**. Si aucune imprimante n'est proposée, sélectionner
        **Modifier/Réessayer** pour en choisir une.

=== "Récupérer une série marquée en échec par erreur"

    Un import peut marquer toute une série en échec alors que les résultats
    étaient valables. La récupération fait passer ces échantillons directement à
    **Accepté**, sans nouvelle étape d'approbation. Vérifier d'abord chaque
    résultat sur le tirage de l'automate.

    1. Aller à **CHARGE VIRALE DU VIH → Gestion des résultats des tests →
       Échec/Echantillons en attente**.
    2. Régler **Résultat Statut** sur **Echec** uniquement.
    3. Restreindre avec les autres filtres si nécessaire.
    4. Sélectionner **Rechercher**.
    5. Cocher les échantillons concernés. Le bouton **Déplacer la sélection vers
       « Accepté »** apparaît.

        ??? info "Pour récupérer un seul échantillon"

            Sélectionner plutôt **Accepter** sur la ligne de cet échantillon, puis
            passer à l'étape 7. Le bouton n'apparaît que sur les lignes Echec qui
            portent un résultat exploitable.

    6. Sélectionner **Déplacer la sélection vers « Accepté »**.
    7. Sélectionner **OK** pour confirmer. InteLIS indique combien
       d'échantillons ont été déplacés, par exemple
       `3 échantillon(s) déplacé(s) vers la catégorie « Acceptés »`.

        ??? failure "Si InteLIS affiche `Aucun échantillon n'a été déplacé`"

            Les échantillons portent un véritable résultat d'échec, comme Failed,
            Error ou Invalid, ou sont déjà acceptés. Les véritables échecs sont
            toujours ignorés. Les retester plutôt.

    8. Rechercher de nouveau avec **Résultat Statut** réglé sur **Echec**. Les
       échantillons récupérés n'apparaissent plus.

    Suite : [diffuser les résultats à la structure demandeuse](release-results.md).
