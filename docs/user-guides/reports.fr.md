# Rapports charge virale

Cette page décrit chaque rapport situé sous **CHARGE VIRALE DU VIH → Gestion**,
ainsi que le contenu charge virale du tableau de bord et des pages de
surveillance de l'administration.

S'applique à InteLIS 5.6.2.

Toutes les pages de rapport utilisent les mêmes commandes. Régler les filtres,
sélectionner **Rechercher**, et utiliser la commande d'export lorsqu'elle est
proposée. Voir [Se connecter et naviguer dans InteLIS](signing-in.md) pour ces
commandes.

## Tableau de bord

**Emplacement :** **TABLEAU DE BORD**

Affiche le nombre d'échantillons enregistrés, testés, rejetés et sans résultat,
ainsi que la performance par structure. Un onglet par type de test activé sur
l'installation.

Couvre les 30 derniers jours par défaut. La commande de période en haut de page
modifie l'intervalle.

## Rapport sur le statut des échantillons

**Emplacement :** **CHARGE VIRALE DU VIH → Gestion → Rapport sur le statut des
échantillons**

Affiche trois graphiques.

| Graphique | Contenu |
|---|---|
| Statut des échantillons | La répartition des échantillons par statut |
| Suppression virale | La part des résultats supprimés par rapport aux non supprimés |
| Délai de rendu du laboratoire | Le temps écoulé entre les étapes du test |

Chaque graphique s'exporte depuis la commande située en haut à droite.

## Rapport de contrôle

**Emplacement :** **CHARGE VIRALE DU VIH → Gestion → Rapport de contrôle**

Représente la performance des contrôles passés avec les échantillons patients.

Les résultats des contrôles se chargent depuis un fichier. La page accepte le
téléversement.

## Exporter les résultats

**Emplacement :** **CHARGE VIRALE DU VIH → Gestion → Exporter les résultats**

Produit un tableur des résultats correspondant aux filtres. Contient des lignes
de données, pas des rapports patients.

## Imprimer le résultat

**Emplacement :** **CHARGE VIRALE DU VIH → Gestion → Imprimer le résultat**

Produit les PDF de rapports patients. Réparti sur deux onglets, **Résultats pas
encore imprimés** et **Résultats déjà imprimés**. La limite est de 1000
résultats par impression.

Voir [Diffuser les résultats à la structure demandeuse](release-results.md).

## Rapports cliniques

**Emplacement :** **CHARGE VIRALE DU VIH → Gestion → Rapports cliniques**

Sept rapports sous forme de tableaux sur une même page. Chacun s'exporte vers un
tableur.

| Onglet | Contenu |
|---|---|
| Charge virale élevée | Les patients dont le résultat dépasse le seuil défini par l'administrateur |
| Charge virale élevée et échec virologique | Les résultats élevés accompagnés de l'évaluation d'échec virologique |
| Rejet d'échantillons | Les échantillons rejetés avec leur motif |
| Résultats non disponibles | Les échantillons enregistrés sans résultat |
| Contrôle qualité des données | Les fiches comportant des données manquantes ou incohérentes |
| Tests réalisés | Les échantillons testés sur la période sélectionnée |
| Historique des tests du patient | Tous les tests enregistrés pour un patient |

Le rapport de charge virale élevée accepte des notes de contact. Un utilisateur
consigne le suivi effectué auprès de la structure et marque le contact comme
terminé.

## Rapport hebdomadaire du laboratoire

**Emplacement :** **CHARGE VIRALE DU VIH → Gestion → VL Lab Weekly Report**

Deux rapports sur une même page.

| Rapport | Contenu |
|---|---|
| Rapport hebdomadaire | L'activité de test sur la période, par défaut les 7 derniers jours |
| Rapport hebdomadaire, femmes | La même activité pour les patientes, ventilée par âge |

Les deux s'exportent vers un tableur.

## Rapport de rejet d'échantillons

**Emplacement :** **CHARGE VIRALE DU VIH → Gestion → Rapport de rejet
d'échantillons**

Liste les échantillons rejetés avec leur motif. S'exporte vers un tableur.

## Rapport de surveillance d'échantillons

**Emplacement :** **CHARGE VIRALE DU VIH → Gestion → Rapport de surveillance
d'échantillons**

Rend compte de la performance du laboratoire sur une période, souvent
trimestrielle. S'exporte vers un tableur.

## Rapport d'objectif de test

**Emplacement :** **CHARGE VIRALE DU VIH → Gestion → CV-Rapport d'objectif de
test**

Compare les échantillons testés à l'objectif mensuel. L'administrateur définit
l'objectif sous **ADMIN → Configuration du système → Configuration générale**.

## Rapports sur les congélateurs et le stockage

**Emplacement :** **CHARGE VIRALE DU VIH → Gestion → Rapports sur les
congélateurs et le stockage**

Donne la position actuelle de chaque échantillon et son historique de stockage.
S'exporte vers un tableur.

Voir [Enregistrer le stockage d'un échantillon](store-samples.md).

## Indicateurs de performance du laboratoire

**Emplacement :** **ADMIN → Surveillance → Lab Performance Indicators**

Rend compte du délai de rendu, du volume par mode de saisie, du taux d'échec, du
taux de rejet et des patients répétés, pour tous les types de test de
l'installation.

Le taux d'échec compte les événements de test. Un échantillon testé deux fois
compte pour deux événements, un retest après échec ne masque donc pas l'échec
initial.

## Source des demandes

**Emplacement :** **ADMIN → Surveillance → Source des demandes**

Indique par quelle voie les demandes sont entrées dans le système, en
distinguant les demandes saisies dans InteLIS de celles reçues d'autres systèmes.

## Rapports situés ailleurs

| Rapport | Emplacement | Contenu |
|---|---|---|
| Journal d'activité de l'utilisateur | **ADMIN → Surveillance → Journal d'activité de l'utilisateur** | Les pages ouvertes par chaque utilisateur et les actions menées |
| Piste d'audit | **ADMIN → Surveillance → Piste d'audit** | Le détail des modifications de données, champ par champ |
| Historique API | **ADMIN → Surveillance → API History** | Les échanges avec les systèmes connectés |
| État de la synchronisation du laboratoire | **ADMIN → Surveillance → État de la synchronisation du laboratoire** | Si les données de chaque laboratoire sont parvenues au système central |
| Métadonnées des résultats | **ADMIN → Surveillance → Test Results Metadata** | Le détail enregistré avec chaque résultat |
