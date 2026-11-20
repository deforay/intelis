---
description: Enregistrer le congélateur, le portoir, la boîte et la position des échantillons de charge virale, un par un ou par import, et retrouver un échantillon stocké.
audience: [lab-staff, lab-supervisor]
module: [vl]
type: how-to
reviewed: 2026-11-20
reviewed_against: 5.8.10
---
# Enregistrer le stockage d'un échantillon

Le stockage des échantillons s'enregistre par le laboratoire d'analyse, sur les
installations dont le formulaire de demande prévoit le stockage des
échantillons. Les autres formulaires de demande n'affichent pas le bouton **Stockage des
échantillons**.

Une position enregistrée dans le congélateur permet de retrouver le tube plus
tard, sans ouvrir chaque boîte. Cela compte surtout pour les échantillons
conservés en vue d'un retest, d'un test de confirmation ou d'une revue qualité.

## Avant de commencer

- Des échantillons enregistrés dans InteLIS
- Le congélateur, le portoir, la boîte et la position de chaque tube
- La permission de consulter les demandes de test
- Le congélateur configuré sous **ADMIN → Paramètres → Stockage en
  laboratoire**. S'il manque, demander à l'administrateur de l'ajouter.

## Enregistrer une position

**Choisir comment les tubes sont rangés, puis suivre les étapes de haut en
bas.**

Enregistrer la position au moment où le tube entre dans le congélateur. Une
position notée plus tard, de mémoire, est celle où le tube devait aller, pas
toujours celle où il se trouve.

=== "Un échantillon à la fois"

    1. Aller à **CHARGE VIRALE DU VIH → Gestion des demandes → Afficher les
       demandes de test**.
    2. Sélectionner **Stockage des échantillons** en haut à droite de la liste.
    3. Choisir le laboratoire dans **Nom du laboratoire**. Sur l'InteLIS propre
       à un laboratoire (LIS), ce champ est masqué et déjà réglé sur le
       laboratoire.
    4. Filtrer les échantillons à stocker, avec **Date de prélèvement de
       l'échantillon**, **Date de réception de l'échantillon au laboratoire** ou **Nom de la
       structure**.
    5. Sélectionner **Obtenir des échantillons**.

        ??? failure "Si un échantillon manque dans la liste"

            La **Date de prélèvement de l'échantillon** est préremplie sur les
            28 derniers jours. L'élargir pour des échantillons plus anciens, puis
            sélectionner de nouveau **Obtenir des échantillons**. Les
            échantillons annulés ne sont jamais listés.

    6. Sur la ligne de chaque échantillon, renseigner les informations de
       stockage :

        | Champ | À saisir |
        | --- | --- |
        | Volume (ml) | Le volume stocké. Obligatoire, et supérieur à zéro |
        | Laboratoire | Le laboratoire qui détient le congélateur. Obligatoire |
        | Congélateur | Le congélateur. Obligatoire |
        | Portoir | Le portoir dans le congélateur. Obligatoire |
        | Boîte | La boîte dans le portoir. Obligatoire |
        | Position | La position dans la boîte. Obligatoire |
        | Commentaires | Tout ce qui aide à retrouver ou interpréter l'échantillon. Facultatif |

        !!! warning "Une ligne à laquelle manque un champ obligatoire n'est pas enregistrée"

            InteLIS ignore cette ligne sans le signaler. La page affiche quand
            même `Échantillon ajouté au congélateur avec succès`, et le tube n'a
            aucune position enregistrée. L'étape 9 permet de le repérer.

    7. Laisser **Date de sortie** vide. Ce champ n'enregistre pas la sortie de
       l'échantillon.
    8. Sélectionner **Enregistrer**. InteLIS affiche
       `Échantillon ajouté au congélateur avec succès`.
    9. Vérifier chaque échantillon :

        1. Aller à **CHARGE VIRALE DU VIH → Gestion → Rapports sur les
           congélateurs et le stockage**.
        2. Sélectionner l'onglet **Historique du stockage des échantillons**.
        3. Choisir le **Laboratoire d'analyse**, saisir l'**ID de
           l'échantillon**, puis sélectionner **Rechercher**.
        4. La ligne affiche le congélateur, le **Portoir**, la **Boîte** et la
           **Position** saisis à l'étape 6, avec le statut `Added`.

        ??? info "La page Stockage des échantillons affiche aussi la position"

            Après **Obtenir des échantillons**, l'échantillon a une ligne par
            enregistrement de stockage. **Stockage actuel** affiche le
            congélateur, le portoir, la boîte, la position et le volume de cet
            enregistrement.

=== "Une boîte entière"

    !!! warning "Ces positions vont sur le formulaire de demande, pas dans les enregistrements de stockage"

        L'import remplit les champs **Congélateur**, **Portoir**, **Boîte**,
        **Position** et **Volume (ml)** du formulaire de demande de chaque
        échantillon. Il n'ajoute pas les échantillons aux Rapports sur les
        congélateurs et le stockage.

    1. Aller à **CHARGE VIRALE DU VIH → Gestion des demandes → Afficher les
       demandes de test**.
    2. Sélectionner **Stockage des échantillons** en haut à droite de la liste.
    3. Sélectionner **Import en masse du stockage**.
    4. Saisir le code du lot ou du manifeste de la boîte dans **Code du lot
       (ou) Code du manifeste**.
    5. Sélectionner **Télécharger le format Excel**. Le fichier se télécharge
       avec les ID des échantillons et des patients de ce lot ou de ce
       manifeste déjà remplis.
    6. Remplir le fichier. Conserver ses colonnes telles quelles. Leurs en-têtes
       sont en anglais :

        | Colonne | À saisir |
        | --- | --- |
        | Sample Code | Déjà rempli. Obligatoire |
        | Patient ID | Déjà rempli |
        | Location/Freezer Code | Le code du congélateur, tel que configuré sous Stockage en laboratoire. Obligatoire |
        | Rack | Le portoir. Obligatoire |
        | Box | La boîte. Obligatoire |
        | Position | La position. Obligatoire |
        | Volume (ml) | Le volume. Obligatoire |

        ??? failure "Si le code du congélateur est mal saisi"

            Un code qui ne correspond à aucun congélateur existant crée un
            nouveau congélateur avec ce code. Vérifier les codes avant l'import.

    7. Sélectionner **Charger le fichier** et choisir le fichier rempli.
    8. Sélectionner **Envoyer**. InteLIS affiche le **Nombre total
       d'enregistrements dans le fichier**, le **Nombre de stockages de
       laboratoire ajoutés** et le **Nombre de stockages non ajoutés**.

        ??? failure "Si certaines lignes n'ont pas été ajoutées"

            Une ligne n'est pas ajoutée lorsqu'une colonne obligatoire est
            vide, lorsque son **Sample Code** ne correspond à aucun échantillon
            visible par ce compte, ou lorsque l'échantillon a déjà une position
            sur son formulaire de demande. InteLIS liste les lignes qu'il n'a
            pas pu ajouter et les propose sous forme de tableur à télécharger.

    9. Vérifier quelques échantillons avant de remettre la boîte au
       congélateur. Dans **Afficher les demandes de test**, rechercher
       l'échantillon et sélectionner **Modifier**. Les champs **Congélateur**,
       **Portoir**, **Boîte**, **Position** et **Volume (ml)** affichent les
       valeurs importées.

## Enregistrer la sortie d'un échantillon

1. Sur la page Stockage des échantillons, sélectionner **Obtenir des
   échantillons** pour lister l'échantillon.
2. Sélectionner **Supprimer** sur sa ligne. Ce bouton ne s'affiche que sur le
   dernier enregistrement de stockage de l'échantillon, tant que le tube est
   encore dans un congélateur.
3. Choisir le motif dans la liste qui apparaît. InteLIS retire l'échantillon
   aussitôt, sans confirmation, et affiche
   `Sample is removed from this freezer`, en anglais.

Choisir **Autre** enregistre le motif `other`. Choisir un motif de la liste
lorsqu'il convient. L'historique de stockage conserve l'endroit où se trouvait
le tube et la date de sa sortie.

!!! warning "Date de sortie ne retire pas l'échantillon"

    Renseigner **Date de sortie** puis enregistrer ajoute un autre
    enregistrement de stockage, et le tube reste affiché comme présent.
    Utiliser plutôt **Supprimer**.

## Retrouver un échantillon stocké

1. Aller à **CHARGE VIRALE DU VIH → Gestion → Rapports sur les congélateurs et le
   stockage**.
2. Choisir l'onglet :

    | Onglet | Filtres | Affiche |
    | --- | --- | --- |
    | Rapport sur les congélateurs et le stockage | **Laboratoire d'analyse**, **Congélateur/Stockage** | Tous les échantillons d'un congélateur, avec portoir, boîte, position et volume |
    | Historique du stockage des échantillons | **Laboratoire d'analyse**, **ID de l'échantillon** | Tous les enregistrements de stockage d'un échantillon |

3. Régler les filtres et sélectionner **Rechercher**.
4. Pour vérifier le congélateur à la main, sélectionner **Exporter vers Excel**
   et comparer le fichier avec les tubes.

Les échantillons enregistrés par **Import en masse du stockage**
n'apparaissent pas dans ce rapport.
