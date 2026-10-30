---
description: Regrouper des échantillons de charge virale enregistrés dans un lot pour une série sur automate et imprimer le PDF du lot.
audience: [lab-staff]
module: [vl]
type: how-to
reviewed: 2026-09-22
reviewed_against: 5.7.74
---
# Créer un lot pour le test

Regrouper des échantillons enregistrés dans un lot pour une série sur un
automate, et imprimer le PDF du lot qui porte leurs ID jusqu'à l'automate.

## Avant de commencer

- Des échantillons enregistrés dans InteLIS, soit
  [enregistrés directement](register-a-request.md), soit
  [activés depuis un manifeste](receive-referred-samples.md)
- L'automate sur lequel la série sera passée
- La permission de gérer les lots

## Créer le lot

1. Aller à **CHARGE VIRALE DU VIH → Gestion des demandes → Gérer le lot**.
2. Sélectionner **Créer un nouveau lot**.
3. Choisir l'automate dans **Plateforme de test**. La liste des échantillons en
   attente de lot s'affiche, avec le nombre maximum d'échantillons accepté par
   l'automate.
4. Sélectionner **Afficher la recherche avancée**.
5. Choisir la numérotation des **Positions**, **Numérique** ou
   **Alphanumérique**, selon l'étiquetage des positions sur l'automate.
6. Choisir **Trier par** et **Type de tri** pour fixer l'ordre des échantillons.
   L'écran des positions, après l'enregistrement, reprend cet ordre.
7. Renseigner les filtres utiles pour restreindre la liste.

    | Filtre | Restreint la liste à |
    |---|---|
    | Structure | Les échantillons des structures sanitaires choisies |
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

    ??? question "Si un échantillon manque dans la liste"

        La liste ne montre que les échantillons qui ont un ID d'échantillon,
        sont à **Sample Registered at Testing Lab** (échantillon enregistré au
        laboratoire d'analyse) ou **Sample Reordered** (échantillon redemandé),
        n'ont pas de résultat, ne sont pas rejetés et ne sont pas dans un autre
        lot.

9. Vérifier le **Code du lot**. InteLIS le remplit et il ne peut pas être
   modifié.
10. Sélectionner les échantillons de la série. Au choix :

    - Sélectionner **Sélection automatique des échantillons pour le lot**. Les
      échantillons du haut de la liste passent dans le lot, jusqu'au maximum
      de l'automate.
    - Sélectionner des échantillons dans la liste de gauche, puis sélectionner
      la flèche simple vers la droite pour les passer dans le lot à droite.

11. Sélectionner **Enregistrer et suivant**. L'écran **Ajouter une position de
    contrôle de lot** s'ouvre.

    ??? failure "Si InteLIS signale un nombre d'échantillons supérieur à celui autorisé"

        Le lot contient plus d'échantillons que l'automate n'en accepte.
        Renvoyer des échantillons dans la liste de gauche avec la flèche simple
        vers la gauche, puis sélectionner à nouveau **Enregistrer et suivant**.

    ??? failure "Si InteLIS demande de sélectionner au moins un échantillon"

        Le lot à droite est vide. Y passer des échantillons, puis sélectionner
        à nouveau **Enregistrer et suivant**.

12. Faire glisser les échantillons et les contrôles dans l'ordre de passage sur
    l'automate.
13. Sélectionner **Save**. Le lot apparaît dans la liste **Gérer le lot**.

## Imprimer le PDF du lot

14. Sur la ligne du lot dans **Gérer le lot**, sélectionner **PDF du lot**
    ou **PDF compact du lot**.

    | Option | Présentation |
    |---|---|
    | PDF par lots | Une zone par échantillon, avec un code-barres pour chaque ID |
    | PDF par lots compacts | La même liste sur moins de pages |

    ??? info "Si PDF par lots n'apparaît pas sur la ligne"

        Le laboratoire est configuré pour la seule version compacte. Utiliser
        **PDF compact du lot**.

15. Imprimer le PDF.

Charger les échantillons sur l'automate avec les ID du PDF imprimé. Puis
[saisir les résultats](capture-results.md).

## Modifier ou supprimer un lot

Chaque ligne de **Gérer le lot** propose ces actions.

| Action | Effet |
|---|---|
| **Modifier** | Modifier le lot et ses échantillons |
| **Modifier la position** | Modifier la position de chaque échantillon |
| **PDF du lot**, **PDF compact du lot** | Réimprimer le PDF du lot |
| **Supprimer** | Supprimer le lot et renvoyer ses échantillons dans la liste en attente de lot. Affiché uniquement tant qu'aucun échantillon du lot n'a de résultat ni de rejet |

Pour retester des échantillons d'un lot qui a déjà des résultats, voir
[Gérer les échecs et les échantillons en attente](failed-and-held-samples.md).

## Vérifier que tout fonctionne

Le lot apparaît dans **Gérer le lot** avec le bon nombre dans **Nombre
d'échantillons**. Une fois les résultats saisis, **Nombre d'échantillons
testés** augmente jusqu'à correspondre.
