# Ajouter ou mettre à jour plusieurs structures à la fois

Charger une liste de structures depuis un seul fichier Excel, ou corriger de
nombreuses structures existantes en une fois. Rien n'est enregistré tant que la
revue n'est pas acceptée.

## Avant de commencer

- Un compte avec des droits d'administrateur sur le STS, ou sur une
  installation autonome. Un LIS n'a pas de bouton **Chargement groupé**
- Les structures dans un fichier `.xlsx`, 20 000 lignes au plus

**Choisir la situation, puis suivre ses étapes de haut en bas.**

=== "Ajouter de nouvelles structures"

    ### Préparer le fichier

    1. Aller à **ADMIN → Structures sanitaires**.
    2. Sélectionner **Chargement groupé**.
    3. Sélectionner **Download blank template**.
    4. Remplir une structure par ligne. Garder les colonnes dans leur ordre, et
       laisser la ligne d'en-tête en place.

        | Colonne | Ce qu'il faut saisir |
        | --- | --- |
        | Nom de la structure | Obligatoire |
        | Code de la structure | Le code national unique. Laissé vide pour un laboratoire d'analyse, InteLIS en génère un |
        | Code d'établissement externe | Un second code utilisé par un autre système |
        | Province, District | Obligatoires. Un nom qu'InteLIS ne connaît pas est ajouté comme nouvelle province ou nouveau district |
        | Type d'installation | Obligatoire. `1` Etablissement de santé, `2` Laboratoire d'analyse, `3` Site de prélèvement |
        | Adresse, Courriel, Numéro de téléphone | Facultatifs |
        | Latitude, Longitude | Facultatives. Latitude entre -90 et 90, longitude entre -180 et 180 |
        | Statut | `active` ou `inactive` |

    ### Charger et vérifier

    5. Sous **How should existing facilities be handled?**, garder **Add new
       only**. Les lignes qui correspondent à une structure existante sont
       ignorées.
    6. Déposer le fichier sur la page, ou sélectionner **browse** et le choisir.
    7. Sélectionner **Review Upload**. Rien n'est encore enregistré.
    8. Lire le **Résultat** de chaque ligne :

        | Résultat | Signification |
        | --- | --- |
        | Nouveau | La ligne est ajoutée |
        | Skipped | Une structure de ce nom ou de ce code existe déjà |
        | Erreur | La ligne ne peut pas être enregistrée. **Détails** en donne la raison |

    9. Régler **Filter rows** sur **Avertissements**. Lire chaque avertissement.
       Les lignes avec un avertissement commencent décochées.

        ??? warning "Avertissements sur les nouvelles lignes"

            | Avertissement | Risque |
            | --- | --- |
            | Name is almost the same as existing facility | La structure est ajoutée deux fois |
            | Name is close to a facility in the same district | La structure est ajoutée deux fois |
            | Name looks like the facility in another row | Le fichier liste la structure deux fois |
            | Coordinates are the same as existing facility | La structure est ajoutée deux fois |

    10. Cocher chaque ligne avec avertissement qui est bien une nouvelle
        structure.
    11. Sélectionner **Import ticked rows**. Pour abandonner le chargement,
        sélectionner **Annuler**.

    ### Terminer

    12. Lire le bilan. **Not saved** doit valoir 0.

        ??? failure "Si des lignes n'ont pas été enregistrées"

            Sélectionner **Download rows not saved**. Corriger les lignes, puis
            charger ce fichier à partir de l'étape 5.

    13. Rattacher les nouvelles structures à leurs types de test. Le fichier ne
        porte aucun type de test : les nouvelles structures ne figurent donc
        encore sur aucun formulaire de demande. Voir
        [Rattacher plusieurs structures à un type de test](admin-facilities.md#rattacher-plusieurs-structures-a-un-type-de-test).

=== "Mettre à jour des structures existantes"

    ### Préparer le fichier

    1. Aller à **ADMIN → Structures sanitaires**.
    2. Sélectionner **Chargement groupé**.
    3. Sélectionner **Export existing facilities**. L'export a la mise en page du
       chargement.
    4. Modifier les lignes à changer. Supprimer celles qui n'ont besoin d'aucun
       changement. Une cellule facultative vide garde la valeur déjà
       enregistrée.

    ### Charger et vérifier

    5. Sous **How should existing facilities be handled?**, choisir comment une
       ligne trouve sa structure :

        | Option | Effet |
        | --- | --- |
        | Match by name and code | Met à jour seulement lorsque le nom et le code appartiennent à la même structure. La mise à jour la plus sûre |
        | Match by code | Met à jour la structure qui porte le même code. À utiliser pour renommer des structures |
        | Match by name | Met à jour la structure qui porte le même nom |

        Les lignes qui ne correspondent à rien sont ajoutées comme nouvelles
        structures.

    6. Déposer le fichier sur la page, ou sélectionner **browse** et le choisir.
    7. Sélectionner **Review Upload**. Rien n'est encore enregistré.
    8. Lire le **Résultat** de chaque ligne :

        | Résultat | Signification |
        | --- | --- |
        | Update | La ligne modifie la structure. **Détails** liste chaque champ modifié |
        | No change | La ligne correspond à la structure enregistrée |
        | Nouveau | La ligne ne correspond à aucune structure et est ajoutée |
        | Skipped | La ligne ne peut pas être rapprochée avec l'option choisie |
        | Erreur | La ligne ne peut pas être enregistrée. **Détails** en donne la raison |

    9. Régler **Filter rows** sur **Avertissements**. Lire chaque avertissement.
       Les lignes avec un avertissement commencent décochées.

        ??? warning "Avertissements sur les lignes mises à jour"

            | Avertissement | Risque |
            | --- | --- |
            | Facility Name changes a lot | La ligne a été rapprochée de la mauvaise structure |
            | Facility moves to a different Province/State | La ligne a été rapprochée de la mauvaise structure |
            | Coordinates move the facility a long way | La ligne a été rapprochée de la mauvaise structure |
            | Facility Type changes | Les formulaires et listes qui dépendent du type changent |
            | Facility Code of a testing lab changes | Le code entre dans les codes d'échantillon générés par le laboratoire |
            | External Facility Code changes | Les autres systèmes qui s'appuient sur l'ancien code ne trouvent plus la structure |
            | Facility will be made inactive | La structure quitte toutes les listes actives |

    10. Cocher chaque ligne avec avertissement dont le changement est voulu.
    11. Sélectionner **Import ticked rows**. Pour abandonner le chargement,
        sélectionner **Annuler**.

    ### Terminer

    12. Lire le bilan. **Not saved** doit valoir 0.

        ??? failure "Si des lignes n'ont pas été enregistrées"

            Une ligne n'est pas enregistrée lorsque sa structure a été modifiée
            par quelqu'un d'autre après la revue. Sélectionner **Download rows
            not saved**, corriger les lignes, puis charger ce fichier à partir de
            l'étape 5.

## Vérifier que tout fonctionne

| Contrôle | Attendu |
| --- | --- |
| Bilan | **Not saved** vaut 0, et **Added** plus **Updated** correspond aux lignes cochées |
| Liste des structures | Un échantillon des structures modifiées montre les nouvelles valeurs |
| Formulaire de demande | Les nouvelles structures apparaissent une fois rattachées à leurs types de test |
