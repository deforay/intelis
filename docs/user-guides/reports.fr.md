---
description: Liste chaque rapport charge virale, le tableau de bord et le rapport d'ancienneté des échantillons, avec ce que chacun compte et où le trouver.
audience: [lab-staff, lab-supervisor, requesting-facility]
module: [vl]
type: reference
reviewed: 2026-09-22
reviewed_against: 5.7.74
---

# Rapports charge virale

Cette page décrit chaque rapport situé sous **CHARGE VIRALE DU VIH → Gestion**,
le contenu charge virale du tableau de bord, et le rapport d'ancienneté des
échantillons.

S'applique à InteLIS 5.7.74.

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
l'installation. Les échantillons annulés sont exclus de tous les décomptes.

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
| Aperçu du statut des échantillons | La répartition des échantillons par statut |
| Suppression virologique | La part des résultats supprimés par rapport aux non supprimés |
| Délai d'exécution du laboratoire | Le temps écoulé entre les étapes du test |

Chaque graphique s'exporte depuis la commande située en haut à droite.

Sélectionner une part du graphique des statuts ouvre, sur une page distincte,
les échantillons de ce statut. Cette page s'exporte vers un tableur. Le tableau
des échantillons sous les graphiques s'exporte avec **Exporter vers Excel**.

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

Sept rapports sur une même page, un par onglet. Les filtres sont repliés.
Sélectionner **Filtres** pour les ouvrir. Charge virale élevée, Rejet de
l'échantillon, Résultats non disponibles et Vérification de la qualité des
données s'exportent vers un tableur. **VL élevée et échec virologique** ne
s'affiche pas à l'écran. Régler les filtres puis sélectionner **Générer le
rapport** pour le télécharger.

| Onglet | Contenu |
|---|---|
| **Charge virale élevée** | Les patients dont le résultat dépasse le seuil de charge virale défini par l'administrateur |
| **VL élevée et échec virologique** | Un classeur téléchargé : chaque résultat non supprimé, et une feuille d'échec virologique qui liste les patients qui en ont plus d'un, avec le nombre de jours entre les prélèvements |
| **Rejet de l'échantillon** | Les échantillons rejetés avec leur motif |
| **Résultats non disponibles** | Les échantillons encore sans résultat, hors échantillons rejetés, avec la date de leur réception au laboratoire. Régler **Inclure les échantillons expirés** sur **Non** pour exclure les échantillons expirés |
| **Vérification de la qualité des données** | La part des échantillons auxquels manque chaque champ clé, par champ et par structure. Sélectionner un nombre pour lister les échantillons |
| **Test d'échantillons** | Les échantillons prélevés sur la période, par structure, avec le nombre de tests réalisés et l'endroit où se trouvent les autres |
| **Historique des tests du patient** | Rechercher un patient par ID ou par nom et voir tous les tests enregistrés pour ce patient, tous types de test confondus, avec les tendances et un lien vers le PDF de chaque résultat |

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
| Rapport hebdomadaire du laboratoire VL - Femmes | La même activité pour les patientes, ventilée par âge |

Les deux s'exportent vers un tableur.

## Rapport de rejet d'échantillons

**Emplacement :** **CHARGE VIRALE DU VIH → Gestion → Rapport de rejet
d'échantillons**

Compte les échantillons rejetés par laboratoire, par structure et par motif de
rejet, y compris les échantillons rejetés sans motif enregistré. S'exporte vers
un tableur.

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
| Sorties : Rejeté, Expiré, Perdu ou manquant, Annulée | Les échantillons sortis sans résultat transmis, listés pour que les totaux concordent |

La période se base sur la date de prélèvement, ou sur la date de la demande si
aucune date de prélèvement n'est enregistrée. Le sélecteur **Test** couvre
chaque type de test activé.

La ventilation regroupe les échantillons par **Établissement de prélèvement**,
**Laboratoire d'analyse** ou **Partenaire**.
