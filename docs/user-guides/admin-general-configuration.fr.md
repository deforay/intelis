---
description: Référence de chaque paramètre de la page Configuration générale, de son effet et de la façon de vérifier une modification.
audience: [system-admin, lab-admin]
module: [all]
type: reference
reviewed: 2026-09-22
reviewed_against: 5.7.74
---

# Paramètres de la configuration générale

Référence de **ADMIN → Configuration du système → Configuration générale**.
Chaque paramètre s'applique aussitôt à tous les utilisateurs de l'installation.

Les paramètres listés sous
[Modifications à faire valider](administer-intelis.md#modifications-a-faire-valider)
demandent d'abord l'accord de l'équipe nationale.

## Modifier un paramètre

1. Aller à **ADMIN → Configuration du système → Configuration générale**. La
   page, intitulée **Configuration du système**, s'ouvre en lecture seule.
2. Sélectionner **Modifier la configuration du système**.
3. Trouver le paramètre avec **Rechercher les paramètres par mot-clé**, ou
   choisir son panneau sous **Aller à une section**.
4. Modifier le paramètre.
5. Sélectionner **Sauvegarder**.
6. Vérifier l'effet avec la ligne correspondante de
   [Vérifier une modification](#verifier-une-modification).

Les panneaux ci-dessous suivent l'ordre de la page. Le panneau d'un module
n'apparaît que si l'installation exécute ce module.

## Réglages de l’instance

| Paramètre | Contrôle |
| --- | --- |
| Format de date | L'affichage des dates dans InteLIS : **DD-MMM-YYYY** (22-Sep-2026) ou **DD-MM-YYYY** (22-09-2026) |
| Afficher l'option de cryptage des IIP | Si l'option de chiffrement des informations d'identification personnelle est proposée |

## Paramètres globaux

| Paramètre | Contrôle |
| --- | --- |
| Pays d'installation | La mise en page du formulaire de demande. Chaque pays a son formulaire |
| Fuseau horaire par défaut | Le fuseau horaire porté par chaque fiche |
| Paramètres régionaux du système | La langue de l'interface |
| En-tête | Le titre imprimé sur les rapports |
| Image du logo | Le logo imprimé sur les rapports |
| Permettre aux utilisateurs de modifier leur profil | Si les utilisateurs peuvent modifier leurs propres informations |
| Suivre l'utilisation des pages | Si InteLIS enregistre les pages ouvertes par chaque utilisateur. **Oui** par défaut. Alimente la page Utilisation des pages |
| Mode de formation | Marque l'installation comme un entraînement, et affiche le texte saisi à côté |
| Format du code-barres | `C39`, `C39+`, `C128` ou `QRCODE` |
| Le même utilisateur peut réviser et approuver | Si une même personne peut réviser et approuver un résultat |
| Impression d'étiquettes code-barres des échantillons | **Désactivé**, **Imprimante Zebra** ou **Dymo LabelWriter 450**. Zebra et Dymo ajoutent une zone obligatoire **Format des étiquettes Zebra** ou **Format des étiquettes DYMO** contenant le modèle d'étiquette |
| Autoriser les échantillons ne correspondant pas aux ID d'échantillons System lors de l'importation manuelle des résultats | Si un import manuel accepte des lignes dont l'ID de l'échantillon est inconnu d'InteLIS |
| Email du support | L'adresse affichée aux utilisateurs qui demandent de l'aide |
| Version minimale de l'application mobile | La plus ancienne version de l'application mobile autorisée à se connecter, par exemple `1.5.0`. Vide, toutes les versions sont autorisées |
| Séparateur CSV, CSV Enveloppe | Le séparateur et les guillemets des fichiers CSV exportés |
| Préfixe téléphonique par défaut | L'indicatif téléphonique du pays |
| Longueur minimale du numéro de téléphone, Longueur maximale du numéro de téléphone | Les longueurs de numéro acceptées |
| Mise en page PDF par lots | **Standard** ou **Compact** |
| Jours d'expiration de l'échantillon | Nombre de jours après le prélèvement avant qu'un échantillon encore sans résultat passe à Expiré. Obligatoire, au moins 90 |
| Jours de verrouillage des échantillons | Nombre de jours après la dernière modification avant qu'un échantillon Accepté ou Rejeté soit verrouillé. Obligatoire, au moins 7 |
| Modèles de type de test | Un modèle PDF par type de test, avec sa **Marge de l'en-tête**, pour les rapports de résultats |

??? warning "Pays d'installation et Mode de formation"

    Changer le **Pays d'installation** change le formulaire vu par tous les
    utilisateurs. Le nouveau formulaire peut ne pas porter les champs de
    l'ancien.

    Le **Mode de formation** est réservé à une installation d'entraînement. Ne
    jamais l'activer sur une installation qui contient de vraies fiches de
    patients.

## Panneaux des modules

Chaque module a son panneau : **Réglages de la charge virale**, **Paramètres
EID**, **Paramètres Covid-19**, **Paramètres de l'hépatite**, **Réglages TB**,
**Paramètres CD4** et **Paramètres des autres tests de laboratoire**. Une
modification dans un panneau n'atteint pas les autres.

| Paramètre | Contrôle | Panneaux |
| --- | --- | --- |
| ID de l'échantillon | La construction des ID d'échantillon de ce module. Voir [Formats d'ID d'échantillon](#formats-did-dechantillon) | Tous |
| Longueur minimale de l'identifiant du patient | Le plus court identifiant de patient accepté par le formulaire | Tous |
| Demande de copie sur les formulaires Enregistrer et Suivant | Si **Sauvegarder et Suivant** reporte les valeurs de la demande précédente | Tous, sur le formulaire du Cameroun uniquement |
| VL Auto Approve API Results (Approbation automatique des résultats de l'API), Approbation automatique des résultats de l'API par l'EID, COVID-19 Approbation automatique des résultats de l'API, Approbation automatique des résultats de l'API TB | Si les résultats reçus par l'API sont approuvés sans contrôle humain | Charge virale, EID, Covid-19, TB |
| Afficher le nom du participant dans le manifeste VL / EID / COVID-19 / TB / CD4, Afficher le nom du participant dans Hepatitis Manifest, Afficher le nom du participant dans le manifeste des tests de laboratoire personnalisés | Si le nom du participant s'imprime sur le manifeste de ce module | Tous |
| Tests de confirmation positifs Covid-19 requis | Si un résultat COVID-19 positif demande un test de confirmation | Covid-19 |

Les **Réglages de la charge virale** portent en plus :

| Paramètre | Contrôle |
| --- | --- |
| Limite du seuil de charge virale | La valeur à partir de laquelle un résultat est élevé |
| VL Objectif mensuel | **Activer** ou **Désactiver**. Activé, le tableau de bord montre le travail de chaque laboratoire face à ses objectifs. Les objectifs eux-mêmes se règlent par laboratoire d'analyse, sous [Configurer un laboratoire d'analyse](admin-facilities.md#configurer-un-laboratoire-danalyse) |
| Interpréter et convertir les résultats de la LV | Si InteLIS convertit et interprète les valeurs de charge virale importées |
| Format d'exportation de la charge virale | **Format par défaut** ou **Format CRESAR**. Sur le formulaire du Cameroun uniquement |

??? warning "Approbation automatique des résultats de l'API"

    Les résultats reçus par l'API sont diffusés sans contrôle humain. C'est sûr
    lorsque l'automate est fiable et que le circuit des batchs est respecté. Ce
    n'est pas sûr lorsque les ID d'échantillon sont saisis à la main sur
    l'automate.

## Formats d'ID d'échantillon

Chaque module a son format et son préfixe. Le numéro courant compte au moins
quatre chiffres et repart à zéro chaque année.

| Format | Construit | Exemple avec le préfixe `VL` |
| --- | --- | --- |
| AA | Préfixe, année sur 2 chiffres, numéro | `VL260001` |
| MMYY | Préfixe, mois, année sur 2 chiffres, numéro | `VL08260001` |
| Auto | Code de province, date au format AAMMJJ, numéro | `122608190001` |
| Auto 2 | Année sur 2 chiffres, code de province, préfixe, numéro. Sur le formulaire PNG uniquement | `2612VL0001` |
| Numérique, Alphanumérique | Préfixe, numéro. Sans date | `VL0001` |

Les échantillons enregistrés sur le STS portent un `R` initial. Sur certains
formulaires de pays, chaque ID d'échantillon porte un `R` initial
supplémentaire, et **Auto** s'appelle **Auto 1**. Lorsqu'un code
de laboratoire est ajouté, un trait d'union le sépare du numéro courant, comme
dans `VL0826-NMC-0019`.

Un nouveau format ou préfixe s'applique aux échantillons enregistrés à partir de
ce moment. Les échantillons existants gardent l'ancien : le laboratoire a alors
deux schémas en même temps.

## Paramètres de l'application mobile

| Paramètre | Contrôle |
| --- | --- |
| Nom du menu de l'APP mobile | Le nom que l'application mobile affiche pour cette installation |

## Connecter

| Paramètre | Contrôle |
| --- | --- |
| URL du tableau de bord national | Le tableau de bord vers lequel pointe cette installation |

## Paramètres PDF des résultats de charge virale

| Paramètre | Contrôle |
| --- | --- |
| Afficher l'émoticône/l'émoticône | Si le PDF de résultats porte un smiley pour un résultat supprimé |
| Afficher le résultat du journal VL | Si la valeur logarithmique s'imprime à côté des copies par millilitre |
| Message sur la charge virale élevée | Le message imprimé sur un résultat égal ou supérieur au seuil |
| Message de faible charge virale | Le message imprimé sur un résultat inférieur au seuil |
| Format du nom du patient | **Prénom + Nom**, **Nom complet** ou **Cacher le nom du patient** |

Régler **Format du nom du patient** sur **Cacher le nom du patient** lorsque les
PDF de résultats passent par un circuit qui ne doit pas porter de noms de
patients.

## Paramètres absents de cette page

| Paramètre | Par défaut | Contrôle |
| --- | --- | --- |
| Auto Approve Interface Results | `yes` | Si les résultats reçus par l'outil d'interface sont approuvés sans contrôle humain. Distinct des paramètres API de chaque module |
| Interface API Enabled | `no` | Si l'API de l'outil d'interface est ouverte, et si le panneau **Connexions des outils d'interface** apparaît sur les laboratoires d'analyse |

Le support InteLIS modifie ces deux paramètres sur demande.

!!! warning "Les résultats d'interface sont approuvés automatiquement par défaut"

    Avec la valeur par défaut `yes`, les résultats de l'outil d'interface sont
    acceptés sans revue, même lorsque chaque paramètre d'approbation
    automatique des résultats de l'API est désactivé. Un laboratoire qui exige
    une revue humaine des résultats d'interface demande au support de régler
    Auto Approve Interface Results sur `no`. Vérifier ensuite que le résultat
    suivant de l'automate arrive dans la file d'approbation, et non comme
    Accepté.

## Vérifier une modification

| Modification | Contrôle |
| --- | --- |
| Format de date, En-tête, Image du logo | Ouvrir un rapport et le lire |
| ID de l'échantillon | Enregistrer une demande et lire l'ID de l'échantillon attribué |
| Format du code-barres | Imprimer un PDF de batch et scanner un code-barres |
| Le même utilisateur peut réviser et approuver | Réviser un résultat, puis tenter d'approuver ce même résultat |
| Approbation automatique des résultats de l'API | Envoyer un résultat par l'API et lire son statut |
| VL Objectif mensuel | Ouvrir le tableau de bord et trouver les graphiques d'objectifs |
| Paramètres PDF des résultats | Imprimer un PDF de résultats |
| Jours de verrouillage des échantillons | Ouvrir un échantillon Accepté ou Rejeté non modifié depuis plus longtemps que la limite et tenter de le modifier |
