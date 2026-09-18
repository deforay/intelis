# Rapports charge virale

Cette page décrit chaque rapport situé sous **CHARGE VIRALE DU VIH → Gestion**,
le contenu charge virale du tableau de bord, et le rapport d'ancienneté des
échantillons.

S'applique à InteLIS 5.7.72.

Toutes les pages de rapport utilisent les mêmes commandes. Régler les filtres,
sélectionner **Rechercher**, et utiliser la commande d'export lorsqu'elle est
proposée. Voir [Se connecter et naviguer dans InteLIS](signing-in.md) pour ces
commandes.

Les rapports situés sous **ADMIN → Surveillance** sont décrits dans
[Surveiller et auditer InteLIS](admin-monitoring.md).

## Tableau de bord

**Emplacement :** **TABLEAU DE BORD**

Affiche le nombre d'échantillons enregistrés, testés, rejetés et sans résultat,
ainsi que la performance par structure. Un onglet par type de test activé sur
l'installation.

S'ouvre sur les 29 derniers jours, aujourd'hui compris. La commande de période
en haut de page modifie l'intervalle, et son préréglage **30 derniers jours**
couvre 30 jours.

Lorsque **VL Objectif mensuel** est activé, l'onglet charge virale affiche
aussi les objectifs de test et de suppression virale. Voir
[CV-Rapport d'objectif de test](#cv-rapport-dobjectif-de-test).

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
| **Charge virale élevée** | Les patients dont le résultat dépasse le seuil de charge virale défini par l'administrateur |
| **VL élevée et échec virologique** | Les résultats élevés accompagnés de l'évaluation d'échec virologique |
| **Rejet de l'échantillon** | Les échantillons rejetés avec leur motif |
| **Résultats non disponibles** | Les échantillons enregistrés sans résultat |
| **Vérification de la qualité des données** | Les fiches comportant des données manquantes ou incohérentes |
| **Test d'échantillons** | Les échantillons testés sur la période sélectionnée |
| **Historique des tests du patient** | Tous les tests enregistrés pour un patient |

L'onglet **Charge virale élevée** consigne le suivi effectué auprès de la
structure. Son filtre **Statut des contacts** sépare les patients dont le
contact est terminé des autres.

## Rapport hebdomadaire du labo VL

**Emplacement :** **CHARGE VIRALE DU VIH → Gestion → Rapport hebdomadaire du
labo VL**

Deux rapports sur une même page.

| Rapport | Contenu |
|---|---|
| Rapport hebdomadaire du labo VL | L'activité de test sur la période sélectionnée, par défaut les 7 derniers jours |
| Rapport hebdomadaire du labo VL, femmes | La même activité pour les patientes, ventilée par âge |

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

## CV-Rapport d'objectif de test

**Emplacement :** **CHARGE VIRALE DU VIH → Gestion → CV-Rapport d'objectif de
test**

Compare les échantillons testés à l'objectif mensuel de chaque laboratoire
d'analyse.

Les objectifs se définissent par laboratoire d'analyse, sur la fiche du
laboratoire sous **ADMIN → Structures sanitaires**.

| Champ | Contenu |
|---|---|
| **Objectif mensuel** | Le nombre d'échantillons que le laboratoire doit tester chaque mois |
| **Cible mensuelle de suppression virale** | Le nombre de résultats supprimés attendu chaque mois. Charge virale uniquement |

**ADMIN → Configuration du système → Configuration générale** ne contient que
l'interrupteur **VL Objectif mensuel**. Il affiche ou masque les graphiques
d'objectif sur le tableau de bord.

## Rapports sur les congélateurs et le stockage

**Emplacement :** **CHARGE VIRALE DU VIH → Gestion → Rapports sur les
congélateurs et le stockage**

Donne la position actuelle de chaque échantillon et son historique de stockage.
S'exporte vers un tableur.

Voir [Enregistrer le stockage d'un échantillon](store-samples.md).

## Rapport d'ancienneté des échantillons

**Emplacement :** absent du menu. Ouvrir `/reports/sample-ageing.php` à
l'adresse web d'InteLIS. Le rôle doit disposer de la permission **Rapport
d'ancienneté des échantillons**.

Indique depuis combien de temps les échantillons attendent à chaque étape,
afin de retrouver les échantillons bloqués avant leur expiration.

| Étape | Contenu |
|---|---|
| À l'établissement | Enregistré au point de prélèvement. Aucun laboratoire n'a enregistré sa réception |
| Au laboratoire, en attente d'analyse | Un laboratoire a l'échantillon mais ne l'a pas encore testé. Inclut les échecs, les mises en attente et les nouveaux prélèvements demandés |
| Testé, en attente d'approbation | Testé. Le résultat attend une approbation |
| Approuvé, en attente de transmission | Le résultat est prêt, mais n'a été ni imprimé, ni envoyé, ni téléchargé |
| Transmis | Le résultat a été imprimé, envoyé à la structure, ou téléchargé par le système de la structure |

La ventilation regroupe les échantillons par **Établissement de prélèvement**,
**Laboratoire d'analyse** ou **Partenaire**.
