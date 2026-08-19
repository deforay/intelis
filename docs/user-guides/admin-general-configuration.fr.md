# Modifier la configuration générale

Ce guide couvre **ADMIN → Configuration du système → Configuration générale**,
les paramètres qui modifient le comportement d'InteLIS sur toute l'installation.

Chaque paramètre de cette page s'applique d'un coup à tous les utilisateurs.
Modifier un paramètre à la fois et en vérifier l'effet avant d'en modifier un
autre.

## Avant de commencer

- Un compte avec des droits d'administrateur
- L'accord de l'équipe nationale pour les paramètres listés sous
  [Modifications à faire valider](administer-intelis.md#modifications-a-faire-valider-avant-de-les-appliquer)

La page est organisée en panneaux. Utiliser le champ de recherche en haut pour
trouver un paramètre plutôt que de faire défiler.

## Instance Settings

| Paramètre | Contrôle |
|---|---|
| Date Format | L'affichage des dates dans InteLIS. `DD-MMM-YYYY` ou `DD-MM-YYYY` |
| Display Encrypt PII Option | Si l'option de chiffrement des données identifiantes est proposée |

## Global Settings

| Paramètre | Contrôle |
|---|---|
| Country of Installation | La mise en page du formulaire de demande. Chaque pays a son formulaire |
| Default Time Zone | Le fuseau horaire inscrit sur chaque fiche |
| System Locale | La langue de l'interface |
| Header | L'en-tête imprimé sur les rapports |
| Logo Image | Le logo imprimé sur les rapports |
| Allow users to Edit Profile | Si les utilisateurs peuvent modifier leurs propres informations |
| Training Mode | Marque l'installation comme un entraînement, et affiche le texte saisi à côté |
| Barcode Format | `C39`, `C39+`, `C128` ou `QRCODE` |
| Sample ID Barcode Label Printing | `off`, `zebra-printer` ou `dymo-labelwriter-450` |
| Same user can Review and Approve | Si une même personne peut réviser et approuver un résultat |
| Allow Samples not matching the System Sample IDs while importing results manually | Si un import manuel peut introduire des lignes dont l'ID d'échantillon est inconnu d'InteLIS |
| Support Email | L'adresse indiquée aux utilisateurs qui demandent de l'aide |
| CSV Delimiter, CSV Enclosure | Le séparateur et le guillemet des fichiers CSV exportés |
| Default Phone Prefix | L'indicatif téléphonique du pays |
| Minimum Length of Phone Number, Maximum Length of Phone Number | Les longueurs de numéro acceptées |
| Batch PDF Layout | `standard` ou `compact` |
| Sample Lock Days | Le nombre de jours avant qu'un échantillon cesse d'accepter les modifications |
| Sample Expiry Days | Le nombre de jours avant péremption d'un échantillon |

**Country of Installation** sélectionne le formulaire de demande. En changer
change le formulaire vu par tous, et le nouveau formulaire peut ne pas porter les
champs de l'ancien.

**Training Mode** ne convient qu'à une installation d'entraînement. Ne jamais
l'activer sur une installation contenant de vraies fiches patients.

## Paramètres par module

Chaque module actif porte son propre panneau. Les paramètres se répètent par
module, donc une modification sous Viral Load Settings n'atteint pas TB Settings.

| Paramètre | Contrôle |
|---|---|
| Format et préfixe des ID d'échantillon | La construction des ID de ce module. Voir plus bas |
| Minimum Patient ID Length | L'identifiant patient le plus court accepté par le formulaire |
| Copy Request On Save and Next Form | Si Save and Next reporte les valeurs de la demande précédente |
| Auto Approve API Results | Si les résultats arrivant par l'API sont approuvés sans contrôle humain |
| Show Participant Name in Manifest | Si le nom du participant s'imprime sur le manifeste de ce module |
| Sample Expiry Days | Une péremption propre à ce module, lorsqu'elle diffère de la globale |

Viral Load Settings en porte cinq de plus.

| Paramètre | Contrôle |
|---|---|
| Viral Load Threshold Limit | La valeur au-delà de laquelle un résultat est élevé |
| VL Suppression Target | L'objectif de suppression utilisé par les rapports |
| VL Monthly Target | L'objectif mensuel de test utilisé par les rapports |
| Interpret and Convert VL Results | Si InteLIS convertit et interprète les valeurs de charge virale importées |
| Viral Load Export Format | La disposition des colonnes de l'export charge virale |

**Auto Approve API Results** diffuse les résultats de l'automate sans contrôle
humain. C'est sûr lorsque l'automate est fiable et que le circuit des batchs est
respecté. Ce ne l'est pas lorsque les ID d'échantillon sont saisis à la main sur
l'automate.

## Formats des ID d'échantillon

Chaque module porte son propre format et son propre préfixe. Le numéro courant
compte quatre chiffres et repart à chaque année.

| Format | Produit | Exemple avec le préfixe `VL` |
|---|---|---|
| YY | préfixe, année sur 2 chiffres, numéro | `VL260001` |
| MMYY | préfixe, mois, année sur 2 chiffres, numéro | `VL08260001` |
| alphanumeric | préfixe, numéro. Sans date | `VL0001` |
| auto | code province, date en AAMMJJ, numéro | `122608190001` |
| auto2 | année sur 2 chiffres, code province, préfixe, numéro | `2612VL0001` |

Les échantillons créés sur le serveur national portent un `R` en tête. Lorsqu'un
code de laboratoire est ajouté, un trait d'union le sépare du numéro courant,
comme dans `VL0826-NMC-0019`.

Changer le format ou le préfixe change tout échantillon enregistré ensuite. Les
échantillons déjà enregistrés gardent l'ancien format. Le laboratoire porte alors
deux schémas à la fois, et aucun n'est faux.

## Mobile App Settings

| Paramètre | Contrôle |
|---|---|
| Mobile APP Menu Name | Le nom sous lequel l'application mobile désigne cette installation |

## Connect

| Paramètre | Contrôle |
|---|---|
| National Dashboard URL | Le tableau de bord vers lequel cette installation renvoie |

## Viral Load Result PDF Settings

| Paramètre | Contrôle |
|---|---|
| Show Emoticon/Smiley | Si le PDF de résultat porte un smiley pour un résultat supprimé |
| Display VL Log Result | Si la valeur logarithmique s'imprime à côté des copies par millilitre |
| High Viral Load Message | Le message imprimé sur un résultat élevé |
| Low Viral Load Message | Le message imprimé sur un résultat bas |
| Patient Name Format | `flname` pour prénom et nom, `fullname` pour le nom complet, `hidename` pour n'imprimer aucun nom |

Régler **Patient Name Format** sur `hidename` lorsque les PDF de résultats
circulent par une voie qui ne doit pas porter de noms de patients.

## Vérifier que tout fonctionne

| Modification | Contrôle |
|---|---|
| Date Format, Header, Logo | Ouvrir un rapport et le lire |
| Format des ID d'échantillon | Enregistrer une demande et lire l'ID délivré |
| Barcode Format | Imprimer un PDF de batch et scanner un code-barres |
| Same user can Review and Approve | Se connecter comme réviseur et tenter d'approuver le résultat qui vient d'être révisé |
| Auto Approve API Results | Envoyer un résultat par l'API et lire son statut |
| Réglages du PDF de résultat | Imprimer un PDF de résultat |
| Sample Lock Days | Ouvrir un échantillon plus ancien que la limite et tenter de le modifier |
