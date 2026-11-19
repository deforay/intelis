---
description: Liste les rapports EID, TB, hépatite, CD4, tests personnalisés et COVID-19, avec l'emplacement de chacun et ce qu'il affiche.
audience: [lab-staff, lab-supervisor, lab-admin]
module: [eid, tb, hepatitis, cd4, custom-tests, covid19]
type: reference
reviewed: 2026-11-19
reviewed_against: 5.8.9
---

# Rapports des autres tests

Les rapports charge virale sont décrits dans
[Rapports charge virale](reports.md). Cette page liste les rapports du menu
**Gestion** de chaque autre test, et l'onglet du test sur le tableau de bord.

S'applique à InteLIS 5.8.9.

Toutes les pages de rapport utilisent les mêmes commandes. Régler les filtres,
sélectionner **Rechercher**, et utiliser la commande d'export lorsqu'elle est
proposée. Voir [Se connecter et naviguer dans InteLIS](signing-in.md) pour ces
commandes.

Une entrée de menu n'apparaît que si le test est activé sur l'installation et
si le rôle dispose de la permission pour ce rapport.

## Diagnostic précoce du nourrisson (EID)

### Tableau de bord

**Emplacement :** **TABLEAU DE BORD**, onglet **Diagnostic précoce chez le
nourrisson**

Affiche les mêmes décomptes et graphiques que l'onglet charge virale. Voir
[Tableau de bord](reports.md#tableau-de-bord).

Lorsque **Objectif mensuel VL** est activé, l'onglet affiche aussi une ligne
indiquant combien de laboratoires EID ont manqué l'objectif de test
mensuel lors d'un mois terminé de la plage de dates. Son lien **plus** ouvre le
[Rapport sur les objectifs de test EID](#rapport-sur-les-objectifs-de-test-eid).

### Rapport sur le statut des échantillons

**Emplacement :** **DIAGNOSTIC PRÉCOCE DU NOURRISSON (EID) → Gestion → Rapport
sur le statut des échantillons**

Fonctionne comme la version charge virale, avec un graphique **Résultats EID**
à la place de la suppression virologique. Voir
[Rapport sur le statut des échantillons](reports.md#rapport-sur-le-statut-des-echantillons).

### Exporter les résultats

**Emplacement :** **DIAGNOSTIC PRÉCOCE DU NOURRISSON (EID) → Gestion →
Exporter les résultats**

Fonctionne comme la version charge virale. Voir
[Exporter les résultats](reports.md#exporter-les-resultats).

### Imprimer le résultat

**Emplacement :** **DIAGNOSTIC PRÉCOCE DU NOURRISSON (EID) → Gestion →
Imprimer le résultat**

Fonctionne comme la version charge virale. Voir
[Imprimer le résultat](reports.md#imprimer-le-resultat).

### Rapport de rejet d'échantillons

**Emplacement :** **DIAGNOSTIC PRÉCOCE DU NOURRISSON (EID) → Gestion →
Rapport de rejet d'échantillons**

Fonctionne comme la version charge virale. Voir
[Rapport de rejet d'échantillons](reports.md#rapport-de-rejet-dechantillons).

### Rapports cliniques

**Emplacement :** **DIAGNOSTIC PRÉCOCE DU NOURRISSON (EID) → Gestion →
Rapports cliniques**

Fonctionne comme la version charge virale. Voir
[Rapports cliniques](reports.md#rapports-cliniques). Les onglets sont listés
dans [Onglets des rapports cliniques par test](#onglets-des-rapports-cliniques-par-test).

### Rapport sur les objectifs de test EID

**Emplacement :** **DIAGNOSTIC PRÉCOCE DU NOURRISSON (EID) → Gestion →
Rapport sur les objectifs de test EID**

Compare les échantillons testés par chaque laboratoire d'analyse au cours d'un
mois à l'objectif mensuel du laboratoire.

L'objectif est l'**Objectif mensuel** défini pour l'EID sur la fiche du
laboratoire d'analyse sous **ADMIN → Structures sanitaires**. Voir
[Configurer un laboratoire d'analyse](admin-facilities.md#configurer-un-laboratoire-danalyse).

| Filtre | Contenu |
|---|---|
| **Plage de dates** | Les mois à afficher. Chaque mois compte en entier, du premier au dernier jour. S'ouvre sur les 12 derniers mois, celui-ci compris |
| **Laboratoire d'analyse** | Un ou plusieurs laboratoires d'analyse |
| **Type de cible** | **Objectifs mensuels non atteints**, **Objectifs mensuels atteints** ou **Tout afficher** |

Chaque ligne correspond à un laboratoire d'analyse pour un mois. Un laboratoire
qui a un objectif figure pour chaque mois de la plage, à zéro les mois sans
activité.

| Colonne | Contenu |
|---|---|
| **Laboratoire d'analyse** | Le laboratoire d'analyse |
| **Mois** | Le mois que compte la ligne |
| **Nombre d'échantillons reçus** | Les échantillons reçus au laboratoire ce mois-là |
| **Nombre d'échantillons rejetés** | Les échantillons reçus au laboratoire ce mois-là puis rejetés |
| **Nombre d'échantillons testés** | Les échantillons testés ce mois-là qui n'ont pas été rejetés. **Type de cible** compare ce nombre à l'objectif |
| **Objectif de test mensuel** | L'objectif mensuel du laboratoire. Vide lorsque le laboratoire n'en a pas |
| **Objectif atteint** | **Oui** lorsque le **Nombre d'échantillons testés** atteint l'objectif, **Non** sinon. Vide lorsque le laboratoire n'a pas d'objectif |

Les échantillons annulés ne sont pas comptés. Sur le STS, un utilisateur limité
à certaines structures ne voit que les échantillons de ces structures.
**Exporter vers Excel** télécharge les lignes.

### Rapport en cascade de la PTME

**Emplacement :** **DIAGNOSTIC PRÉCOCE DU NOURRISSON (EID) → Gestion →
Rapport en cascade de la PTME**

Suit les enfants exposés au VIH et leurs mères. Relie chaque demande EID aux
demandes charge virale de la mère, puis indique combien de mères ont une charge
virale élevée et combien d'enfants ont un test positif.

Un enfant est apparié à une mère lorsque l'**ID de la mère** de la demande EID
est identique au numéro ART d'une demande charge virale. Chaque enfant compte
une fois, même avec plusieurs tests EID. Les échantillons annulés sont exclus.

| Filtre | Contenu |
|---|---|
| **Date de prélèvement de l'échantillon EID** | La période, selon la date de prélèvement de l'échantillon EID. S'ouvre sur les 12 derniers mois |
| **Province** | La province de la demande EID |
| **Laboratoire d'analyse** | Le laboratoire d'analyse EID |
| **Statut de correspondance** | **Tous les enfants**, **Apparié (la mère a un test VL)** ou **Pas de correspondance**. Ne modifie que le tableau **Enregistrements détaillés** |

Les tests charge virale de la mère sont lus à toute date, pas seulement dans la
période choisie. Les tests antérieurs à la naissance sont en général hors de la
période.

Une charge virale élevée vaut 1000 cp/mL ou plus. Supprimée signifie moins de
1000 cp/mL. Le rapport utilise le dernier résultat charge virale de la mère.

L'**Aperçu de la PTME** s'ouvre sur quatre cartes.

| Carte | Contenu |
|---|---|
| **Suppression virale chez la mère** | Les mères appariées dont le dernier résultat est supprimé, en part des mères ayant un résultat |
| **Mères à charge virale élevée (dernière ≥1000 cp/mL)** | Les mères appariées dont le dernier résultat est élevé. **Voir les mères** les liste |
| **Enfants séropositifs** | Les enfants appariés avec un résultat EID positif dans la période. **Voir les enfants** les liste |
| **Enfants positifs : charge virale de la mère élevée pendant la grossesse** | Les enfants positifs dont la mère a eu un résultat élevé dans les 9 mois précédant la date de naissance de l'enfant |

Deux rangées de cases suivent les cartes. Lire chaque rangée de gauche à
droite. Le pourcentage sous une case est sa part de la case située à sa gauche.

| Rangée | Cases |
|---|---|
| **Risque de charge virale chez la mère** | **Enfants avec ID de la mère**, **Mères appariées**, **Mères présentant un résultat VL**, **Mères présentant une charge virale élevée ≥ 1 000** |
| **Résultats de l'EID chez les nourrissons** | **Enfants appariés**, **Avec les résultats de l'EID**, **Séropositif** |

**Mères appariées** n'a pas de pourcentage, car plusieurs enfants peuvent avoir
la même mère. Les notes sous les rangées comptent les enfants non appariés à une
mère, et les mères à charge virale élevée enceintes ou allaitantes.

Le tableau **Enregistrements détaillés** liste les enregistrements derrière les
chiffres. Un test EID apparaît une fois pour chaque test charge virale de la
mère.

**Exporter vers Excel** télécharge un classeur de quatre feuilles : **Résumé**,
**Données EID**, **Données VL** et **Historique des enfants positifs**.

La page explique ses termes sous **Comment lire ce rapport**.

### Rapport d'ancienneté des échantillons

**Emplacement :** **DIAGNOSTIC PRÉCOCE DU NOURRISSON (EID) → Gestion →
Rapport d'ancienneté des échantillons**

S'ouvre avec l'EID sélectionné. Voir
[Rapport d'ancienneté des échantillons](reports.md#rapport-danciennete-des-echantillons).

## Tuberculose (TB)

### Tableau de bord

**Emplacement :** **TABLEAU DE BORD**, onglet **Tuberculose**

Affiche les mêmes décomptes et graphiques que l'onglet charge virale. Voir
[Tableau de bord](reports.md#tableau-de-bord). L'onglet contient aussi
l'[Entonnoir en cascade](#entonnoir-en-cascade).

Lorsque **Objectif mensuel VL** est activé, l'onglet affiche aussi une ligne
indiquant combien de laboratoires TB ont manqué l'objectif de test
mensuel lors d'un mois terminé de la plage de dates. Son lien **plus** ouvre le
[Rapport sur l'objectif de test TB](#rapport-sur-lobjectif-de-test-tb).

### Entonnoir en cascade

**Emplacement :** **TABLEAU DE BORD**, onglet **Tuberculose**

Affiche l'entonnoir et la branche de transfert du
[Rapport sur la cascade de la tuberculose](#rapport-sur-la-cascade-de-la-tuberculose),
pour la période choisie sur l'onglet.

**Rapport complet de la cascade** ouvre le Rapport sur la cascade de la
tuberculose.

### Rapport sur le statut des échantillons

**Emplacement :** **TUBERCULOSE → Gestion → Rapport sur le statut des
échantillons**

Fonctionne comme la version charge virale, avec un graphique **Nombre de
résultats finaux TB** à la place de la suppression virologique. Voir
[Rapport sur le statut des échantillons](reports.md#rapport-sur-le-statut-des-echantillons).

### Imprimer le résultat

**Emplacement :** **TUBERCULOSE → Gestion → Imprimer le résultat**

Fonctionne comme la version charge virale. Voir
[Imprimer le résultat](reports.md#imprimer-le-resultat).

### Exporter les résultats

**Emplacement :** **TUBERCULOSE → Gestion → Exporter les résultats**

Fonctionne comme la version charge virale. Voir
[Exporter les résultats](reports.md#exporter-les-resultats).

### Rapport de rejet d'échantillons

**Emplacement :** **TUBERCULOSE → Gestion → Rapport de rejet d'échantillons**

Fonctionne comme la version charge virale. Voir
[Rapport de rejet d'échantillons](reports.md#rapport-de-rejet-dechantillons).

### Rapports cliniques

**Emplacement :** **TUBERCULOSE → Gestion → Rapports cliniques**

Fonctionne comme la version charge virale. Voir
[Rapports cliniques](reports.md#rapports-cliniques). Les onglets sont listés
dans [Onglets des rapports cliniques par test](#onglets-des-rapports-cliniques-par-test).

### Rapport sur la cascade de la tuberculose

**Emplacement :** **TUBERCULOSE → Gestion → Rapport sur la cascade de la
tuberculose**

Indique jusqu'où les échantillons TB sont allés, de l'enregistrement à un
résultat expédié ou imprimé, y compris les échantillons transférés à un autre
laboratoire.

| Filtre | Contenu |
|---|---|
| **Date de prélèvement de l'échantillon** | La période, selon la date de prélèvement. S'ouvre sur les 12 derniers mois |
| **Province** | La province de la demande |
| **Laboratoire d'analyse** | Le laboratoire d'analyse |
| **Site de prélèvement** | La structure qui a prélevé l'échantillon |
| **Type de test** | Un type de test TB, ou **-- N'importe lequel --** |
| **Résultat final saisi** | **Oui**, **Non** ou les deux |

La page comporte quatre parties.

| Partie | Contenu |
|---|---|
| Cartes | **Total des échantillons**, puis leur position actuelle : **En cours de réalisation**, **Accepté** et **Refusé / Échec** |
| Ligne de statistiques | Le nombre moyen de jours du prélèvement à la réception et de la réception au test, et les résultats expédiés ou imprimés |
| **Entonnoir en cascade** | Les échantillons à chaque étape, à lire de gauche à droite |
| Branche de transfert | Les échantillons transférés à un autre laboratoire, puis reçus, testés et acceptés là-bas |

Le pourcentage sous une case de l'entonnoir est sa part de la case située à sa
gauche. Une forte baisse entre deux cases montre où les échantillons
s'arrêtent.

| Étape | Contenu |
|---|---|
| **Enregistré** | Les échantillons enregistrés dans la période |
| **Reçu au laboratoire** | L'échantillon a une date de réception au laboratoire, ou a été testé |
| **Testé** | L'échantillon a une date de test, un résultat, ou un résultat sur l'un de ses tests. Les échantillons rejetés ne sont pas testés |
| **Résultat final saisi** | Le résultat final est saisi, ou le résultat est marqué final |
| **Accepté** | Le résultat final est approuvé |
| **Expédié / Imprimé** | Le résultat accepté a été expédié ou imprimé |

La branche de transfert comporte les étapes **Référé**, **Reçu au laboratoire
destinataire**, **Testé au laboratoire destinataire** et **Accepté au
laboratoire destinataire**.

**En cours de réalisation** compte chaque échantillon à un seul endroit : au
site de prélèvement, au laboratoire et non testé, en attente d'approbation, ou
transféré et pas encore reçu.

Sous **À découvrir :**, trois boutons ouvrent des tableaux qui utilisent les
mêmes filtres.

| Bouton | Contenu |
|---|---|
| **Répartition par laboratoire** | Les totaux par laboratoire. Les échantillons d'origine ont été enregistrés au laboratoire. Les échantillons reçus en transfert venaient d'un autre laboratoire |
| **Paires de transfert** | Les échantillons par laboratoire expéditeur et laboratoire destinataire, avec l'étape atteinte |
| **Détail des échantillons** | Une ligne par échantillon, test et laboratoire |

La page explique ses termes sous **Comment lire ce rapport**.

### Rapport sur l'objectif de test TB

**Emplacement :** **TUBERCULOSE → Gestion → Rapport sur l'objectif de test TB**

Compare les échantillons testés par chaque laboratoire d'analyse au cours d'un
mois à l'objectif mensuel du laboratoire. Ajouté dans InteLIS 5.8.5.

Présente les mêmes filtres et colonnes que le
[Rapport sur les objectifs de test EID](#rapport-sur-les-objectifs-de-test-eid).
L'objectif est l'**Objectif mensuel** défini pour la TB sur la fiche du
laboratoire d'analyse sous **ADMIN → Structures sanitaires**.

Un rôle qui pouvait ouvrir les **Rapports cliniques** TB a reçu ce rapport lors
de la mise à jour vers 5.8.5.

### Rapport d'ancienneté des échantillons

**Emplacement :** **TUBERCULOSE → Gestion → Rapport d'ancienneté des
échantillons**

S'ouvre avec la TB sélectionnée. Voir
[Rapport d'ancienneté des échantillons](reports.md#rapport-danciennete-des-echantillons).

## Hépatite

### Tableau de bord

**Emplacement :** **TABLEAU DE BORD**, onglet **Hépatite**

Affiche les mêmes décomptes et graphiques que l'onglet charge virale. Voir
[Tableau de bord](reports.md#tableau-de-bord).

Lorsque **Objectif mensuel VL** est activé, l'onglet affiche aussi une ligne
indiquant combien de laboratoires hépatite ont manqué l'objectif de test
mensuel lors d'un mois terminé de la plage de dates. Son lien **plus** ouvre le
[Hépatite-Rapport d'objectif de dépistage](#hepatite-rapport-dobjectif-de-depistage).

### Rapport sur le statut des échantillons

**Emplacement :** **HEPATITE → Gestion → Rapport sur le statut des
échantillons**

Fonctionne comme la version charge virale. Voir
[Rapport sur le statut des échantillons](reports.md#rapport-sur-le-statut-des-echantillons).

### Exporter les résultats

**Emplacement :** **HEPATITE → Gestion → Exporter les résultats**

Fonctionne comme la version charge virale. Voir
[Exporter les résultats](reports.md#exporter-les-resultats).

### Imprimer le résultat

**Emplacement :** **HEPATITE → Gestion → Imprimer le résultat**

Fonctionne comme la version charge virale. Voir
[Imprimer le résultat](reports.md#imprimer-le-resultat).

### Rapport de rejet d'échantillons

**Emplacement :** **HEPATITE → Gestion → Rapport de rejet d'échantillons**

Fonctionne comme la version charge virale. Voir
[Rapport de rejet d'échantillons](reports.md#rapport-de-rejet-dechantillons).

### Rapports cliniques

**Emplacement :** **HEPATITE → Gestion → Rapports cliniques**

Fonctionne comme la version charge virale. Voir
[Rapports cliniques](reports.md#rapports-cliniques). Les onglets sont listés
dans [Onglets des rapports cliniques par test](#onglets-des-rapports-cliniques-par-test).

### Hépatite-Rapport d'objectif de dépistage

**Emplacement :** **HEPATITE → Gestion → Hépatite-Rapport d'objectif de
dépistage**

Présente les mêmes filtres et colonnes que le
[Rapport sur les objectifs de test EID](#rapport-sur-les-objectifs-de-test-eid),
pour les échantillons hépatite.

### Rapport d'ancienneté des échantillons

**Emplacement :** **HEPATITE → Gestion → Rapport d'ancienneté des
échantillons**

S'ouvre avec l'hépatite sélectionnée. Voir
[Rapport d'ancienneté des échantillons](reports.md#rapport-danciennete-des-echantillons).

## CD4

### Tableau de bord

**Emplacement :** **TABLEAU DE BORD**, onglet **CD4**

Affiche les mêmes décomptes et graphiques que l'onglet charge virale. Voir
[Tableau de bord](reports.md#tableau-de-bord).

Lorsque **Objectif mensuel VL** est activé, l'onglet affiche aussi une ligne
indiquant combien de laboratoires CD4 ont manqué l'objectif de test
mensuel lors d'un mois terminé de la plage de dates. Son lien **plus** ouvre le
[Rapport sur l'objectif du test CD4](#rapport-sur-lobjectif-du-test-cd4).

### Rapport sur le statut des échantillons

**Emplacement :** **CD4 → Gestion → Rapport sur le statut des échantillons**

Fonctionne comme la version charge virale. Voir
[Rapport sur le statut des échantillons](reports.md#rapport-sur-le-statut-des-echantillons).

### Exporter les résultats

**Emplacement :** **CD4 → Gestion → Exporter les résultats**

Fonctionne comme la version charge virale. Voir
[Exporter les résultats](reports.md#exporter-les-resultats).

### Imprimer le résultat

**Emplacement :** **CD4 → Gestion → Imprimer le résultat**

Fonctionne comme la version charge virale. Voir
[Imprimer le résultat](reports.md#imprimer-le-resultat).

### Rapport de rejet d'échantillons

**Emplacement :** **CD4 → Gestion → Rapport de rejet d'échantillons**

Fonctionne comme la version charge virale. Voir
[Rapport de rejet d'échantillons](reports.md#rapport-de-rejet-dechantillons).

### Rapport clinique

**Emplacement :** **CD4 → Gestion → Rapport clinique**

Fonctionne comme les **Rapports cliniques** charge virale. Voir
[Rapports cliniques](reports.md#rapports-cliniques). Les onglets sont listés
dans [Onglets des rapports cliniques par test](#onglets-des-rapports-cliniques-par-test).

### Rapport sur l'objectif du test CD4

**Emplacement :** **CD4 → Gestion → Rapport sur l'objectif du test CD4**

Présente les mêmes filtres et colonnes que le
[Rapport sur les objectifs de test EID](#rapport-sur-les-objectifs-de-test-eid),
pour les échantillons CD4.

### Rapport d'ancienneté des échantillons

**Emplacement :** **CD4 → Gestion → Rapport d'ancienneté des échantillons**

S'ouvre avec le CD4 sélectionné. Voir
[Rapport d'ancienneté des échantillons](reports.md#rapport-danciennete-des-echantillons).

## Tests personnalisés

### Tableau de bord

**Emplacement :** **TABLEAU DE BORD**, onglet **Autres tests**

Affiche les mêmes décomptes et graphiques que l'onglet charge virale. Voir
[Tableau de bord](reports.md#tableau-de-bord).

### Rapport sur le statut des échantillons

**Emplacement :** **AUTRES EXAMENS DE LABORATOIRE → Gestion → Rapport sur le
statut des échantillons**

Fonctionne comme la version charge virale. Voir
[Rapport sur le statut des échantillons](reports.md#rapport-sur-le-statut-des-echantillons).

### Exporter les résultats

**Emplacement :** **AUTRES EXAMENS DE LABORATOIRE → Gestion → Exporter les
résultats**

Fonctionne comme la version charge virale. Voir
[Exporter les résultats](reports.md#exporter-les-resultats).

### Imprimer le résultat

**Emplacement :** **AUTRES EXAMENS DE LABORATOIRE → Gestion → Imprimer le
résultat**

Fonctionne comme la version charge virale. Voir
[Imprimer le résultat](reports.md#imprimer-le-resultat).

### Rapport de rejet d'échantillons

**Emplacement :** **AUTRES EXAMENS DE LABORATOIRE → Gestion → Rapport de rejet
d'échantillons**

Fonctionne comme la version charge virale. Voir
[Rapport de rejet d'échantillons](reports.md#rapport-de-rejet-dechantillons).

### Rapports cliniques

**Emplacement :** **AUTRES EXAMENS DE LABORATOIRE → Gestion → Rapports
cliniques**

Fonctionne comme la version charge virale, avec moins d'onglets. Voir
[Rapports cliniques](reports.md#rapports-cliniques). Les onglets sont listés
dans [Onglets des rapports cliniques par test](#onglets-des-rapports-cliniques-par-test).

### Rapport d'ancienneté des échantillons

**Emplacement :** **AUTRES EXAMENS DE LABORATOIRE → Gestion → Rapport
d'ancienneté des échantillons**

S'ouvre avec les tests personnalisés sélectionnés. Voir
[Rapport d'ancienneté des échantillons](reports.md#rapport-danciennete-des-echantillons).

## COVID-19

| Rapport | Emplacement |
|---|---|
| Tableau de bord | **TABLEAU DE BORD**, onglet **COVID-19** |
| Rapport sur le statut des échantillons | **COVID-19 → Gestion → Rapport sur le statut des échantillons** |
| Exporter les résultats | **COVID-19 → Gestion → Exporter les résultats** |
| Imprimer le résultat | **COVID-19 → Gestion → Imprimer le résultat** |
| Rapport de rejet d'échantillons | **COVID-19 → Gestion → Rapport de rejet d'échantillons** |
| Rapports cliniques | **COVID-19 → Gestion → Rapports cliniques** |
| Rapport sur les objectifs de test COVID-19 | **COVID-19 → Gestion → Rapport sur les objectifs de test COVID-19** |
| Rapport d'ancienneté des échantillons | **COVID-19 → Gestion → Rapport d'ancienneté des échantillons** |

## Onglets des rapports cliniques par test

Les onglets fonctionnent comme décrit dans
[Rapports cliniques](reports.md#rapports-cliniques). **Positivité** remplace
**Charge virale élevée**.

| Test | Onglets |
|---|---|
| EID, TB, hépatite, CD4, COVID-19 | **Positivité**, **Rejet de l'échantillon**, **Résultats non disponibles**, **Vérification de la qualité des données**, **Test d'échantillons**, **Historique des tests du patient** |
| Tests personnalisés | **Test d'échantillons**, **Historique des tests du patient**, **Vérification de la qualité des données** |
