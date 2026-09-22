---
description: Ajouter ou mettre à jour de nombreuses structures depuis un fichier Excel, avec revue de chaque ligne avant tout enregistrement.
audience: [system-admin, lab-admin]
module: [all]
type: how-to
reviewed: 2026-09-22
reviewed_against: 5.7.74
---
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
    3. Sélectionner **Télécharger le modèle vierge**.
    4. Remplir une structure par ligne. Garder les colonnes dans leur ordre, et
       laisser la ligne d'en-tête en place.

        | Colonne | Ce qu'il faut saisir |
        | --- | --- |
        | Nom de la structure | Obligatoire |
        | Code de la structure | Le code national unique. Laissé vide pour un laboratoire d'analyse, InteLIS en génère un. Enregistré en majuscules. La revue montre toute modification |
        | Code d'établissement externe | Un second code utilisé par un autre système |
        | Province, District | Obligatoires. Un nom qu'InteLIS ne connaît pas est ajouté comme nouvelle province ou nouveau district |
        | Type d'installation | Obligatoire. `1` Etablissement de santé, `2` Laboratoire d'analyse, `3` Site de prélèvement. Le nom du type en anglais, par exemple `Testing Lab`, est aussi accepté |
        | Adresse, Courriel, Numéro de téléphone | Facultatifs |
        | Latitude, Longitude | Facultatives. Latitude entre -90 et 90, longitude entre -180 et 180 |
        | Statut | `active` ou `inactive`. Vide, la structure est ajoutée comme active |

    ### Charger et vérifier

    5. Sous **Comment traiter les structures existantes ?**, garder **Ajouter
       les nouvelles uniquement**. Les lignes qui correspondent à une structure existante sont
       ignorées.
    6. Déposer le fichier sur la page, ou sélectionner **parcourir** et le choisir.
    7. Sélectionner **Vérifier le téléversement**. Rien n'est encore
       enregistré. La revue expire après 24 heures. Passé ce délai, charger à
       nouveau le fichier.
    8. Lire le **Résultat** de chaque ligne :

        | Résultat | Signification |
        | --- | --- |
        | Nouveau | La ligne est ajoutée |
        | Ignorée | Une structure de ce nom ou de ce code existe déjà |
        | Erreur | La ligne ne peut pas être enregistrée. **Détails** en donne la raison |

    9. Sélectionner la tuile **Avertissements** au-dessus du tableau. Lire
       chaque avertissement. Les lignes avec un avertissement commencent
       décochées.

        ??? warning "Avertissements sur les nouvelles lignes"

            | Avertissement | Risque |
            | --- | --- |
            | Le nom est presque identique à celui de la structure existante … | La structure est ajoutée deux fois |
            | Le nom est proche de … dans le même district | La structure est ajoutée deux fois |
            | Le nom ressemble à la structure de la ligne … | Le fichier liste la structure deux fois |
            | Les coordonnées sont identiques à celles de la structure existante … | La structure est ajoutée deux fois |

    10. Cocher chaque ligne avec avertissement qui est bien une nouvelle
        structure.
    11. Sélectionner **Importer les lignes cochées**. Pour abandonner le
        chargement, sélectionner **Annuler**.
    12. Confirmer l'invite concernant les lignes avec avertissements, quand
        elle apparaît.

    ### Terminer

    13. Lire le bilan. **Non enregistrées** doit valoir 0.

        ??? failure "Si des lignes n'ont pas été enregistrées"

            Une ligne n'est pas enregistrée lorsque son nom, son code ou son
            code externe est déjà utilisé par une autre structure. Sélectionner
            **Télécharger les lignes non enregistrées**. Corriger les lignes,
            puis charger ce fichier à partir de l'étape 5.

    14. Rattacher les nouvelles structures à leurs types de test. Le fichier ne
        porte aucun type de test : les nouvelles structures ne figurent donc
        encore sur aucun formulaire de demande. Voir
        [Rattacher plusieurs structures à un type de test](admin-facilities.md#rattacher-plusieurs-structures-a-un-type-de-test).

=== "Mettre à jour des structures existantes"

    ### Préparer le fichier

    1. Aller à **ADMIN → Structures sanitaires**.
    2. Sélectionner **Chargement groupé**.
    3. Sélectionner **Exporter les structures existantes**. L'export a la mise en page du
       chargement.
    4. Modifier les lignes à changer. Supprimer celles qui n'ont besoin d'aucun
       changement. Une cellule facultative vide garde la valeur déjà
       enregistrée.

    ### Charger et vérifier

    5. Sous **Comment traiter les structures existantes ?**, choisir comment une
       ligne trouve sa structure :

        | Option | Effet |
        | --- | --- |
        | Rapprocher par nom et code | Met à jour seulement lorsque le nom et le code appartiennent à la même structure. La mise à jour la plus sûre |
        | Rapprocher par code | Met à jour la structure qui porte le même code. À utiliser pour renommer des structures |
        | Rapprocher par nom | Met à jour la structure qui porte le même nom |

        Les lignes qui ne correspondent à rien sont ajoutées comme nouvelles
        structures.

    6. Déposer le fichier sur la page, ou sélectionner **parcourir** et le choisir.
    7. Sélectionner **Vérifier le téléversement**. Rien n'est encore
       enregistré. La revue expire après 24 heures. Passé ce délai, charger à
       nouveau le fichier.
    8. Lire le **Résultat** de chaque ligne :

        | Résultat | Signification |
        | --- | --- |
        | Mettre à jour | La ligne modifie la structure. **Détails** liste chaque champ modifié |
        | Aucun changement | La ligne correspond à la structure enregistrée |
        | Nouveau | La ligne ne correspond à aucune structure et est ajoutée |
        | Ignorée | La ligne ne peut pas être rapprochée avec l'option choisie |
        | Erreur | La ligne ne peut pas être enregistrée. **Détails** en donne la raison |

    9. Sélectionner la tuile **Avertissements** au-dessus du tableau. Lire
       chaque avertissement. Les lignes avec un avertissement commencent
       décochées.

        ??? warning "Avertissements sur les lignes mises à jour"

            | Avertissement | Risque |
            | --- | --- |
            | Le nom de la structure change beaucoup … | La ligne a été rapprochée de la mauvaise structure |
            | La structure passe dans une autre province | La ligne a été rapprochée de la mauvaise structure |
            | Les coordonnées déplacent la structure d'environ … km | La ligne a été rapprochée de la mauvaise structure |
            | Le type de structure passe de … à … | Les formulaires et listes qui dépendent du type changent |
            | Le code de la structure d'un laboratoire d'analyse change | Le code entre dans les codes d'échantillon générés par le laboratoire |
            | Le code externe de la structure change | Les autres systèmes qui s'appuient sur l'ancien code ne trouvent plus la structure |
            | La structure sera rendue inactive | La structure quitte toutes les listes actives |

    10. Cocher chaque ligne avec avertissement dont le changement est voulu.
    11. Sélectionner **Importer les lignes cochées**. Pour abandonner le
        chargement, sélectionner **Annuler**.
    12. Confirmer l'invite concernant les lignes avec avertissements, quand
        elle apparaît.

    ### Terminer

    13. Lire le bilan. **Non enregistrées** doit valoir 0.

        ??? failure "Si des lignes n'ont pas été enregistrées"

            Une ligne n'est pas enregistrée pour l'une de deux raisons :

            - Sa structure a été modifiée par quelqu'un d'autre après la revue.
            - Son nom, son code ou son code externe est déjà utilisé par une
              autre structure.

            Sélectionner **Télécharger les lignes non enregistrées**, corriger
            les lignes, puis charger ce fichier à partir de l'étape 5.

## Vérifier que tout fonctionne

| Contrôle | Attendu |
| --- | --- |
| Bilan | **Non enregistrées** vaut 0, et **Ajoutées** plus **Mises à jour** correspond aux lignes cochées |
| Liste des structures | Un échantillon des structures modifiées montre les nouvelles valeurs |
| Formulaire de demande | Les nouvelles structures apparaissent une fois rattachées à leurs types de test |
