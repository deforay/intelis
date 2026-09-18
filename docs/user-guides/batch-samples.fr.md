# Créer un batch pour le test

Regrouper des échantillons enregistrés dans un batch pour une série sur un
automate, et imprimer le PDF du batch qui porte leurs ID jusqu'à l'automate.

## Avant de commencer

- Des échantillons enregistrés dans InteLIS, soit
  [enregistrés directement](register-a-request.md), soit
  [activés depuis un manifeste](receive-referred-samples.md)
- L'automate sur lequel la série sera passée
- La permission de gérer les batchs

## Créer le batch

1. Aller à **CHARGE VIRALE DU VIH → Gestion des demandes → Gérer le batch**.
2. Sélectionner **Créer un nouveau batch**.
3. Choisir l'automate dans **Plateforme de test**. La liste des échantillons en
   attente de batch s'affiche, avec le nombre maximum d'échantillons accepté par
   l'automate.
4. Sélectionner **Afficher la recherche avancée**.
5. Choisir la numérotation des **Positions**, **Numérique** ou
   **Alphanumérique**, selon l'étiquetage des positions sur l'automate.
6. Choisir **Trier par** et **Type de tri** pour fixer l'ordre des échantillons.
   L'écran des positions, après l'enregistrement, reprend cet ordre.
7. Renseigner les filtres utiles pour restreindre la liste.

    | Filtre | Restreint la liste à |
    |---|---|
    | Établissement | Les échantillons des structures sanitaires choisies |
    | Échantillons saisis ou modifiés par | Les échantillons traités par un utilisateur |
    | Date de prélèvement de l'échantillon | Une période de prélèvement |
    | Date de réception de l'échantillon au laboratoire | Une période de réception au laboratoire |
    | Dernière modification | Une période de dernière modification |
    | Type d'échantillon | Un seul type de prélèvement |
    | Source de financement | Les échantillons d'un seul bailleur |

8. Sélectionner **Filtrer les échantillons**.

    ??? failure "Si InteLIS demande de choisir une plateforme de test pour procéder"

        Aucun automate n'est sélectionné. En choisir un dans **Plateforme de
        test**, puis sélectionner à nouveau **Filtrer les échantillons**.

9. Vérifier le **Code de batch**. InteLIS le remplit et il ne peut pas être
   modifié.
10. Sélectionner les échantillons de la série. Au choix :

    - Sélectionner **Sélection automatique des échantillons pour le lot**. Les
      échantillons du haut de la liste passent dans le batch, jusqu'au maximum
      de l'automate.
    - Sélectionner des échantillons dans la liste de gauche, puis sélectionner
      la flèche simple vers la droite pour les passer dans le batch à droite.

11. Sélectionner **Sauvegarder et Suivant**. L'écran **Ajouter une position de
    contrôle de lot** s'ouvre.

    ??? failure "Si InteLIS signale un nombre d'échantillons supérieur à celui autorisé"

        Le batch contient plus d'échantillons que l'automate n'en accepte.
        Renvoyer des échantillons dans la liste de gauche avec la flèche simple
        vers la gauche, puis sélectionner à nouveau **Sauvegarder et Suivant**.

    ??? failure "Si InteLIS demande de sélectionner au moins un échantillon"

        Le batch à droite est vide. Y passer des échantillons, puis sélectionner
        à nouveau **Sauvegarder et Suivant**.

12. Faire glisser les échantillons et les contrôles dans l'ordre de passage sur
    l'automate.
13. Sélectionner **Save**. Le batch apparaît dans la liste **Gérer le batch**.

## Imprimer le PDF du batch

14. Sur la ligne du batch dans **Gérer le batch**, sélectionner **PDF par lots**
    ou **PDF par lots compacts**.

    | Option | Présentation |
    |---|---|
    | PDF par lots | Une zone par échantillon, avec un code-barres pour chaque ID |
    | PDF par lots compacts | La même liste sur moins de pages |

    ??? info "Si PDF par lots n'apparaît pas sur la ligne"

        Le laboratoire est configuré pour la seule version compacte. Utiliser
        **PDF par lots compacts**.

15. Imprimer le PDF.

Charger les échantillons sur l'automate avec les ID du PDF imprimé. Puis
[saisir les résultats](capture-results.md).

## Modifier ou supprimer un batch

Chaque ligne de **Gérer le batch** propose ces actions.

| Action | Effet |
|---|---|
| **Modifier** | Modifier le batch et ses échantillons |
| **Modifier le poste** | Modifier la position de chaque échantillon |
| **PDF par lots**, **PDF par lots compacts** | Réimprimer le PDF du batch |
| **Supprimer** | Supprimer le batch et renvoyer ses échantillons dans la liste en attente de batch. Affiché uniquement tant qu'aucun échantillon du batch n'a de résultat |

Pour retester des échantillons d'un batch qui a déjà des résultats, voir
[Gérer les échecs et les échantillons en attente](failed-and-held-samples.md).

## Vérifier que tout fonctionne

Le batch apparaît dans **Gérer le batch** avec le bon nombre dans **Nombre
d'échantillons**. Une fois les résultats saisis, **Nombre d'échantillons
testés** augmente jusqu'à correspondre.
