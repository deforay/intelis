# Enregistrer le stockage d'un échantillon

Le stockage des échantillons s'enregistre sur l'InteLIS propre au laboratoire
(LIS), pour les laboratoires qui utilisent le formulaire de demande de la RDC.
Les autres formulaires de demande n'affichent pas le bouton **Stockage des
échantillons**.

Une position enregistrée dans le congélateur permet de retrouver le tube plus
tard, sans ouvrir chaque boîte. Cela compte surtout pour les échantillons
conservés en vue d'un retest, d'un test de confirmation ou d'une revue qualité.

## Avant de commencer

- Des échantillons enregistrés dans InteLIS
- Le congélateur, le coffret, la boîte et la position de chaque tube
- La permission de consulter les demandes de test
- Le congélateur configuré sous **ADMIN → Configuration du système → Stockage en
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
    3. Choisir le laboratoire dans **Nom du laboratoire**.
    4. Filtrer les échantillons à stocker, avec **Date de prélèvement de
       l'échantillon**, **Échantillon reçu au laboratoire Date** ou **Nom de la
       structure**.
    5. Sélectionner **Obtenir des échantillons**.

        ??? failure "Si un échantillon manque dans la liste"

            Sans **Date de prélèvement de l'échantillon**, la liste ne montre que
            les échantillons prélevés dans les 30 derniers jours. Régler la
            période et sélectionner de nouveau **Obtenir des échantillons**. Si
            un échantillon jamais stocké manque encore, contacter le support :
            certaines installations de laboratoire ne listent que les
            échantillons qui ont déjà une position.

    6. Sur la ligne de chaque échantillon, renseigner les informations de
       stockage :

        | Champ | À saisir |
        | --- | --- |
        | Volume (ml) | Le volume stocké. Obligatoire, et supérieur à zéro |
        | Labo | Le laboratoire qui détient le congélateur. Obligatoire |
        | Congélateur | Le congélateur. Obligatoire |
        | Coffret | Le coffret dans le congélateur. Obligatoire |
        | Boîte | La boîte dans le coffret. Obligatoire |
        | Position | La position dans la boîte. Obligatoire |
        | Commentaires | Tout ce qui aide à retrouver ou interpréter l'échantillon. Facultatif |

        !!! warning "Une ligne à laquelle manque un champ obligatoire n'est pas enregistrée"

            InteLIS ignore cette ligne sans le signaler. La page affiche quand
            même `Échantillon ajouté au congélateur avec succès`, et le tube n'a
            aucune position enregistrée. L'étape 9 permet de le repérer.

    7. Laisser **Date de sortie** vide. Ce champ n'enregistre pas la sortie de
       l'échantillon.
    8. Sélectionner **Sauvegarder**. InteLIS affiche
       `Échantillon ajouté au congélateur avec succès`.
    9. Vérifier chaque échantillon :

        1. Aller à **CHARGE VIRALE DU VIH → Gestion → Rapports sur les
           congélateurs et le stockage**.
        2. Sélectionner l'onglet **Historique du stockage des échantillons**.
        3. Choisir le **Laboratoire d'analyse**, saisir l'**ID de
           l'échantillon**, puis sélectionner **Rechercher**.
        4. La ligne affiche le congélateur, le **Coffret**, la **Boîte** et la
           **Position** saisis à l'étape 6, avec le statut `Added`.

        ??? info "La colonne Stockage actuel reste vide"

            Sur la page Stockage des échantillons, **Stockage actuel** n'affiche
            pas la position enregistrée dans la version actuelle. Vérifier les
            positions dans Rapports sur les congélateurs et le stockage.

=== "Une boîte entière"

    !!! warning "Ces positions vont sur le formulaire de demande, pas dans les enregistrements de stockage"

        L'import remplit les champs **Congélateur**, **Coffret**, **Boîte**,
        **Position** et **Volume (ml)** du formulaire de demande de chaque
        échantillon. Il n'ajoute pas les échantillons aux Rapports sur les
        congélateurs et le stockage.

    1. Aller à **CHARGE VIRALE DU VIH → Gestion des demandes → Afficher les
       demandes de test**.
    2. Sélectionner **Stockage des échantillons** en haut à droite de la liste.
    3. Sélectionner **Stockage Téléchargement en masse**.
    4. Saisir le code du batch ou du manifeste de la boîte dans **Code du lot
       (ou) Code du manifeste**.
    5. Sélectionner **Télécharger le format Excel**. Le fichier se télécharge
       avec les ID des échantillons et des patients de ce batch ou de ce
       manifeste déjà remplis.
    6. Remplir le fichier. Conserver ses colonnes telles quelles. Leurs en-têtes
       sont en anglais :

        | Colonne | À saisir |
        | --- | --- |
        | Sample Code | Déjà rempli. Obligatoire |
        | Patient ID | Déjà rempli |
        | Location/Freezer Code | Le code du congélateur, tel que configuré sous Stockage en laboratoire. Obligatoire |
        | Rack | Le coffret. Obligatoire |
        | Box | La boîte. Obligatoire |
        | Position | La position. Obligatoire |
        | Volume (ml) | Le volume. Obligatoire |

        ??? failure "Si le code du congélateur est mal saisi"

            Un code qui ne correspond à aucun congélateur existant crée un
            nouveau congélateur avec ce code. Vérifier les codes avant l'import.

    7. Sélectionner **Charger le fichier** et choisir le fichier rempli.
    8. Sélectionner **Envoyer**. InteLIS affiche le **Nombre total
       d'enregistrements dans le fichier**, le **Nombre d'entrepôts de
       laboratoire ajoutés** et le **Nombre de stockages non ajoutés**.

        ??? failure "Si certaines lignes n'ont pas été ajoutées"

            Un échantillon qui a déjà une position sur son formulaire de demande
            n'est pas modifié. InteLIS liste les lignes qu'il n'a pas pu ajouter
            et les propose sous forme de tableur à télécharger.

    9. Vérifier quelques échantillons avant de remettre la boîte au
       congélateur. Dans **Afficher les demandes de test**, rechercher
       l'échantillon et sélectionner **Modifier**. Les champs **Congélateur**,
       **Coffret**, **Boîte**, **Position** et **Volume (ml)** affichent les
       valeurs importées.

## Enregistrer la sortie d'un échantillon

!!! failure "La sortie ne peut pas être enregistrée dans la version actuelle"

    Le bouton **Remove** de la page Stockage des échantillons n'apparaît pas, si
    bien qu'un échantillon ne peut pas être marqué comme retiré. Renseigner
    **Date de sortie** puis enregistrer ne le retire pas non plus : cela ajoute
    un autre enregistrement de stockage et le tube reste affiché comme présent.
    Signaler les sorties à l'administrateur jusqu'à la correction.

??? info "Étapes lorsque le bouton Remove sera de nouveau affiché"

    1. Sur la page Stockage des échantillons, sélectionner **Obtenir des
       échantillons** pour lister l'échantillon.
    2. Sélectionner **Remove** sur sa ligne. Ce bouton s'affiche en anglais.
    3. Choisir le motif dans la liste qui apparaît. InteLIS retire
       l'échantillon aussitôt, sans confirmation, et affiche
       `Sample is removed from this freezer`, en anglais.

    Choisir **Other** enregistre le motif `other`. Choisir un motif de la liste
    lorsqu'il convient. L'historique de stockage conserve l'endroit où se
    trouvait le tube et la date de sa sortie.

## Retrouver un échantillon stocké

1. Aller à **CHARGE VIRALE DU VIH → Gestion → Rapports sur les congélateurs et le
   stockage**.
2. Choisir l'onglet :

    | Onglet | Filtres | Affiche |
    | --- | --- | --- |
    | Rapport sur les congélateurs et le stockage | **Laboratoire d'analyse**, **Congélateur/Stockage** | Tous les échantillons d'un congélateur, avec coffret, boîte, position et volume |
    | Historique du stockage des échantillons | **Laboratoire d'analyse**, **ID de l'échantillon** | Tous les enregistrements de stockage d'un échantillon |

3. Régler les filtres et sélectionner **Rechercher**.
4. Pour vérifier le congélateur à la main, sélectionner **Exporter vers Excel**
   et comparer le fichier avec les tubes.

Les échantillons enregistrés par **Stockage Téléchargement en masse**
n'apparaissent pas dans ce rapport.
