---
description: Retracer les modifications, vérifier la synchronisation et les automates, et lire les rapports de performance, de référence et d'utilisation des pages.
audience: [lab-admin, system-admin, lab-supervisor]
module: [all]
type: how-to
reviewed: 2026-09-22
reviewed_against: 5.7.74
---
# Surveiller et auditer InteLIS

Trouver qui a modifié une fiche, vérifier que les données et les résultats des
automates circulent, et lire la performance du laboratoire. Les pages se
trouvent sous **ADMIN → Surveillance**.

## Avant de commencer

- Un compte dont le rôle porte les pages de Surveillance nécessaires. Le rôle
  Admin intégré les voit toutes
- Sur la page des rôles, **Indicateurs de performance du laboratoire**,
  **Activité des machines d'interface** et **Exemple de réseau de
  recommandation** s'accordent sous **Rapports**, et non sous Surveillance

??? info "Quelles pages apparaissent"

    | Installation | Pages |
    | --- | --- |
    | Autonome ou LIS | Toutes les pages sauf **État de la synchronisation du laboratoire** et **Tableau de bord de l’API** |
    | STS | Toutes les pages |
    | Cloud | Pour les utilisateurs sans le rôle Admin intégré : **Journal d’activité de l’utilisateur**, **Piste d’audit** et **Visualisateur de fichiers journaux** uniquement |

    **Utilisation des pages** n'apparaît que pour le rôle Admin intégré, et
    pour les rôles qui ont reçu le privilège **Utilisation des pages** sur la
    page des rôles. Aucun
    autre rôle ne le porte par défaut.

## Quelle page répond à quelle question

| Question | Page |
| --- | --- |
| Qui a modifié cet échantillon, et en quoi ? | Piste d’audit |
| Qu'a fait cet utilisateur, et quand s'est-il connecté ? | Journal d’activité de l’utilisateur |
| Quelles pages les utilisateurs ouvrent-ils, et combien de temps ? | Utilisation des pages |
| Les données ont-elles atteint le STS ? | Historique de l’API sur un LIS. État de la synchronisation du laboratoire sur le STS |
| D'où viennent les demandes du STS, et les résultats sont-ils repartis ? | Tableau de bord de l’API |
| Cet automate envoie-t-il toujours ? | Activité des machines d'interface |
| Combien de temps le laboratoire met-il, et à quelle fréquence les tests échouent-ils ? | Indicateurs de performance du laboratoire |
| Où les échantillons se perdent-ils entre la demande et le résultat ? | Source des demandes |
| Que contient InteLIS derrière un résultat ? | Métadonnées des résultats des tests |
| Quelles structures envoient des échantillons à quel laboratoire ? | Exemple de réseau de recommandation |
| Quelles erreurs le système a-t-il consignées ? | Visualisateur de fichiers journaux |

## Trouver qui a modifié un échantillon

1. Aller à **ADMIN → Surveillance → Piste d’audit**.
2. Renseigner **Type de test**.
3. Saisir l'**ID de l'échantillon ou ID de l'échantillon à distance**.
4. Sélectionner **Envoyer**.
5. Lire l'**Affichage Tableau** : une ligne par révision, avec l'utilisateur qui
   l'a enregistrée et le moment. Ouvrir **Changements uniquement** pour voir
   chaque champ modifié avec son **Ancienne Valeur** et sa **Nouvelle
   Valeur**.

    ??? info "Autres vues"

        | Vue | Montre |
        | --- | --- |
        | Vue de la Chronologie | Chaque révision comme une entrée d'une chronologie |
        | Comparer les versions | Deux révisions choisies côte à côte |
        | Exporter au format CSV | L'historique en fichier, à envoyer au support ou à un auditeur |

    ??? failure "Si la page indique que l'échantillon n'appartient pas au laboratoire"

        Sur une instance cloud, la Piste d'audit n'ouvre que les échantillons du
        laboratoire de l'utilisateur.

## Voir ce qu'a fait un utilisateur

1. Aller à **ADMIN → Surveillance → Journal d’activité de l’utilisateur**.
2. Sous **Afficher**, choisir **Tout**, **Actions** ou **Connexions**.
3. Renseigner **Plage de dates** et **Utilisateur**.
4. Lire les entrées. Chacune porte l'utilisateur, l'action, l'adresse IP et le
   navigateur.
5. Pour suivre une connexion du début à la fin, sélectionner **Filtrer par cette
   session** sur l'une de ses entrées.

Le Journal d'activité de l'utilisateur enregistre ce que les utilisateurs ont
fait. Pour voir les anciennes et nouvelles valeurs d'un échantillon, utiliser la
Piste d'audit.

## Voir quelles pages sont utilisées

1. Aller à **ADMIN → Surveillance → Utilisation des pages**.
2. Renseigner **Plage de dates**. Renseigner **Utilisateur** pour se limiter à
   une personne.
3. Lire les cartes : **Utilisateurs**, **Sessions**, **Pages utilisées**,
   **Ouvertures de pages** et **Temps passé sur les pages**.
4. Lire **Pages les plus utilisées** et **Utilisateurs les plus actifs**.
5. Sous **Par utilisateur et par page**, sélectionner une session pour n'afficher
   qu'elle, puis sélectionner **Ouvrir dans le journal d'activité** dans le
   filtre au-dessus des cartes.

Le temps ne compte que lorsque la page est l'onglet affiché devant
l'utilisateur. Ce n'est pas la durée de la connexion.

??? info "Utilisation des pages est vide"

    L'enregistrement dépend de **Suivre l'utilisation des pages** dans la
    [Configuration générale](admin-general-configuration.md#parametres-globaux).
    Il est activé par défaut.

## Vérifier que les données ont atteint le STS

**Choisir l'installation, puis suivre ses étapes de haut en bas.**

=== "LIS"

    1. Aller à **ADMIN → Surveillance → Historique de l’API**.
    2. Renseigner **Plage de dates**. Renseigner **Type de test** pour se
       limiter à un module.
    3. Lire les lignes les plus récentes :

        | Colonne | Signifie |
        | --- | --- |
        | ID de transaction | L'identifiant d'une synchronisation |
        | Nombre d'enregistrements synchronisés | Le nombre de fiches transportées |
        | Type de synchronisation | Le sens et le type de données transférées |
        | URL | Le serveur visé par la synchronisation |
        | Synchronisé sur | Le moment de l'exécution |

    4. Chercher une ligne récente avec un **Nombre d'enregistrements
       synchronisés** différent de zéro.

    Un laboratoire dont les données manquent au niveau national n'a pas de ligne
    récente, ou des lignes à zéro fiche.

=== "STS"

    1. Aller à **ADMIN → Surveillance → État de la synchronisation du
       laboratoire**.
    2. Renseigner **Province**, **District** ou **Nom du Labo** pour réduire la
       liste, puis sélectionner **Rechercher**.
    3. Lire la ligne du laboratoire. Sa couleur suit son activité la plus
       récente :

        | Statut | Signifie |
        | --- | --- |
        | Actif | Synchronisé dans les 2 semaines |
        | En retard | Synchronisé il y a 2 à 4 semaines |
        | Arrêté | Synchronisé auparavant, mais pas depuis 4 semaines ou plus |
        | Jamais synchronisé | Enregistré, jamais mis en service |

    4. Comparer **Synchronisation des derniers résultats du laboratoire** et
       **Dernière demande de synchronisation du STS**. Un retard sur la première
       signifie que des résultats restent sur la machine du laboratoire. Un
       retard sur la seconde signifie que le laboratoire ne voit pas les
       nouvelles demandes.
    5. Pour voir quelles structures sont en retard, sélectionner la ligne du
       laboratoire. **Détails de la synchronisation des laboratoires** s'ouvre
       dans un nouvel onglet avec **Demandes envoyées au laboratoire** et
       **Résultats reçus du laboratoire** par structure.

    Pour envoyer une commande à un laboratoire depuis cette page, voir
    [Plan de commande à distance](../guides/remote-command-plane.md).

    ??? info "Tableau de bord de l’API"

        **ADMIN → Surveillance → Tableau de bord de l’API** suit les demandes
        reçues d'un DME ou d'un autre système par l'API. Il montre combien ont
        été reçues au laboratoire, testées et renvoyées, et signale les patients
        possiblement en double. Renseigner les filtres et sélectionner
        **Rafraîchir le tableau de bord**.

## Vérifier qu'un automate envoie toujours

1. Aller à **ADMIN → Surveillance → Activité des machines d'interface**.
2. Lire **Événements (7 derniers jours)**, **Échecs (7 derniers jours)** et
   **Dernier événement**.
3. Renseigner **Instrument** avec l'automate, puis sélectionner **Rechercher**.
4. Lire la **Date de l'événement** la plus récente. Un événement en échec porte
   un **Code d'échec**.
5. Si rien de récent n'apparaît, ouvrir les **Connexions des outils
   d'interface** du laboratoire d'analyse et lire la **Dernière connexion**.
   Voir [Connexions de l'outil d'interface](admin-interface-tool-connections.md).

Une **Dernière connexion** ancienne signifie que l'outil d'interface n'atteint
pas InteLIS.

## Lire le rapport de performance du laboratoire

1. Aller à **ADMIN → Surveillance → Indicateurs de performance du laboratoire**.
2. Renseigner **Test**, **Plage de dates**, **Afficher par** et **Labo**.
   **Labo** n'apparaît que si l'instance compte des laboratoires d'analyse.
3. Sélectionner **Appliquer**.
4. Ouvrir l'onglet utile. **Aperçu** apparaît lorsque **Test** vaut **Tous les
   tests (Aperçu)**. Les autres onglets apparaissent lorsqu'un seul test est
   choisi.

    | Onglet | Montre |
    | --- | --- |
    | Aperçu | Échantillons enregistrés et testés, résultats disponibles et en attente |
    | Délai d'exécution | Le nombre moyen de jours entre prélèvement, réception au laboratoire, test et diffusion |
    | Volume d'analyses | Les résultats par mode de saisie : **Saisie manuelle**, **Interface de l'analyseur**, **Importation de fichiers**. Les anciens résultats apparaissent comme **Non classifié** |
    | Échecs | Les tests en échec, le taux d'échec et le taux de reprise d'analyse |
    | Rejets | Les échantillons rejetés, le taux de rejet et les principaux motifs |
    | Patients avec tests répétés | Les patients testés plus d'une fois, et les changements de résultat |

5. Pour garder une copie, sélectionner **Exporter**.

**Comment ces chiffres sont-ils calculés ?** sur la page explique chaque
chiffre. Un échantillon testé deux fois compte pour deux tests : une reprise ne
masque donc pas un échec.

## Trouver où se perdent les échantillons

1. Aller à **ADMIN → Surveillance → Source des demandes**.
2. Renseigner **Plage de dates** et **Type de test**. La page ne montre rien
   tant que les deux ne sont pas renseignés.
3. Réduire au besoin avec **Province**, **Nom de la clinique**, **Nom du
   laboratoire d'analyse** ou **Source de la demande**.
4. Sélectionner **Rechercher**.
5. Comparer les chiffres de chaque ligne : **Nombre d'échantillons demandés**,
   **ayant fait l'objet d'un accusé de réception**, **reçus au laboratoire
   d'analyse**, **testés**, et **Nombre de résultats renvoyés**. Le chiffre qui
   baisse montre où les échantillons s'arrêtent.

## Lire la fiche derrière un résultat

1. Aller à **ADMIN → Surveillance → Métadonnées des résultats des tests**.
2. Renseigner **Type de test**.
3. Renseigner **Date du test de l'échantillon**, ou saisir un **ID de
   l'échantillon/Code du lot**.
4. Sélectionner **Rechercher**.
5. Lire la ligne. Elle contient les dates de prélèvement, de réception et de
   test, le résultat et son statut, qui l'a testé et sur quel instrument, s'il
   a été saisi à la main, les détails d'un rejet, toute modification avec sa
   raison, et un lien vers le fichier importé.
6. Pour l'envoyer au support, sélectionner **Exporter vers Excel**.

## Voir le réseau de référence

1. Aller à **ADMIN → Surveillance → Exemple de réseau de recommandation**.
2. Renseigner **Plage de dates** et **Type de test**.
3. Renseigner **Date basée sur** : **Date de prélèvement de l'échantillon**,
   **Date d'enregistrement de l'échantillon** ou **Date de test de
   l'échantillon**. Compter par date de test correspond aux chiffres de test du
   laboratoire.
4. Sélectionner **Rechercher**.
5. Sélectionner un laboratoire ou une structure sur la carte pour n'afficher
   que ses liens. Le tableau **Références par laboratoire et par type
   d'examen** liste tous les liens.

Une structure sans latitude ni longitude est absente de la carte mais comptée
dans le tableau. Pour ajouter des coordonnées, voir
[Structures et laboratoires](admin-facilities.md#ajouter-une-structure).

## Lire les fichiers journaux

1. Aller à **ADMIN → Surveillance → Visualisateur de fichiers journaux**.
2. Renseigner **Date** et **Type de journal** : **Journaux d'erreurs système**
   ou **Journaux d'erreurs PHP**.
3. Filtrer par niveau, ou rechercher dans le texte.
4. Sélectionner **Exporter le fichier journal** et envoyer le fichier au
   support avec la demande.

Le journal consigne les défauts, pas les actions des utilisateurs.

## Vérifier que tout fonctionne

| Tâche | Contrôle |
| --- | --- |
| Modification retracée | La Piste d'audit nomme le champ, l'ancienne valeur, la nouvelle valeur et l'utilisateur |
| Synchronisation confirmée | L'Historique de l'API ou l'État de la synchronisation du laboratoire montre une synchronisation récente avec des fiches |
| Automate confirmé actif | L'Activité des machines d'interface contient un événement récent, et la **Dernière connexion** est récente |
| Échantillon manquant trouvé | La Source des demandes montre l'étape où le chiffre baisse |
