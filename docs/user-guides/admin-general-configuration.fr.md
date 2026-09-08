# Modifier la configuration générale

Ce guide couvre **ADMIN → Configuration du système → Configuration générale**,
les paramètres qui modifient le comportement d'InteLIS sur toute l'installation.

Chaque paramètre de cette page s'applique d'un coup à tous les utilisateurs.
Modifier un paramètre à la fois et en vérifier l'effet avant d'en modifier un
autre.

## Avant de commencer

- Un compte avec des droits d'administrateur
- L'accord de l'équipe nationale pour les paramètres listés sous
  [Modifications à faire
  valider](administer-intelis.md#modifications-a-faire-valider-avant-de-les-appliquer)

La page est organisée en panneaux. Utiliser le champ de recherche en haut pour
trouver un paramètre plutôt que de faire défiler.

## Instance Settings

| Paramètre | Contrôle |
|---|---|
| Format de date | L'affichage des dates dans InteLIS. `DD-MMM-YYYY` ou `DD-MM-YYYY` |
| Afficher l'option de cryptage des IIP | Si l'option de chiffrement des données identifiantes est proposée |

## Global Settings

| Paramètre | Contrôle |
|---|---|
| Pays d'installation | La mise en page du formulaire de demande. Chaque pays a son formulaire |
| Fuseau horaire par défaut | Le fuseau horaire inscrit sur chaque fiche |
| Paramètres régionaux du système | La langue de l'interface |
| En-tête | L'en-tête imprimé sur les rapports |
| Image du logo | Le logo imprimé sur les rapports |
| Permettre aux utilisateurs de modifier leur profil | Si les utilisateurs peuvent modifier leurs propres informations |
| Mode de formation | Marque l'installation comme un entraînement, et affiche le texte saisi à côté |
| Format du code-barres | `C39`, `C39+`, `C128` ou `QRCODE` |
| Impression d'étiquettes code-barres des échantillons | `off`, `zebra-printer` ou `dymo-labelwriter-450` |
| Le même utilisateur peut réviser et approuver | Si une même personne peut réviser et approuver un résultat |
| Autoriser les échantillons ne correspondant pas aux ID d'échantillons System lors de l'importation manuelle des résultats | Si un import manuel peut introduire des lignes dont l'ID d'échantillon est inconnu d'InteLIS |
| Email du support | L'adresse indiquée aux utilisateurs qui demandent de l'aide |
| Version minimale de l'application mobile | La version la plus ancienne d'InteLIS Mobile autorisée à se connecter, par exemple `1.5.0`. Vide autorise toutes les versions |
| CSV Delimiter, CSV Enclosure | Le séparateur et le guillemet des fichiers CSV exportés |
| Préfixe téléphonique par défaut | L'indicatif téléphonique du pays |
| Minimum Length of Phone Number, Maximum Length of Phone Number | Les longueurs de numéro acceptées |
| Mise en page PDF par lots | `standard` ou `compact` |
| Jours de verrouillage des échantillons | Le nombre de jours avant qu'un échantillon cesse d'accepter les modifications |
| Jours d'expiration de l'échantillon | Le nombre de jours avant péremption d'un échantillon |

**Pays d'installation** sélectionne le formulaire de demande. En changer
change le formulaire vu par tous, et le nouveau formulaire peut ne pas porter
les
champs de l'ancien.

**Mode de formation** ne convient qu'à une installation d'entraînement. Ne jamais
l'activer sur une installation contenant de vraies fiches patients.

## Paramètres par module

Chaque module actif porte son propre panneau. Les paramètres se répètent par
module, donc une modification sous Viral Load Settings n'atteint pas TB
Settings.

| Paramètre | Contrôle |
|---|---|
| Format et préfixe des ID d'échantillon | La construction des ID de ce module. Voir plus bas |
| Longueur minimale de l'identifiant du patient | L'identifiant patient le plus court accepté par le formulaire |
| Demande de copie sur les formulaires Enregistrer et Suivant | Si Save and Next reporte les valeurs de la demande précédente |
| Auto Approve API Results | Si les résultats arrivant par l'API sont approuvés sans contrôle humain |
| Show Participant Name in Manifest | Si le nom du participant s'imprime sur le manifeste de ce module |
| Jours d'expiration de l'échantillon | Une péremption propre à ce module, lorsqu'elle diffère de la globale |

Viral Load Settings en porte cinq de plus.

| Paramètre | Contrôle |
|---|---|
| Limite du seuil de charge virale | La valeur au-delà de laquelle un résultat est élevé |
| Cible de suppression VL | L'objectif de suppression utilisé par les rapports |
| VL Objectif mensuel | L'objectif mensuel de test utilisé par les rapports |
| Interpréter et convertir les résultats de la LV | Si InteLIS convertit et interprète les valeurs de charge virale importées |
| Format d'exportation de la charge virale | La disposition des colonnes de l'export charge virale |

**Auto Approve API Results** diffuse sans contrôle humain les résultats arrivant
par l'API. C'est sûr lorsque l'automate est fiable et que le circuit des batchs
est respecté. Ce ne l'est pas lorsque les ID d'échantillon sont saisis à la main
sur l'automate.

!!! warning "L'outil d'interface a son propre réglage, activé par défaut"
    Les résultats arrivant par l'outil d'interface dépendent de **Auto Approve
    Interface Results**, et non des réglages Auto Approve API Results par module
    ci-dessus. Il est livré à `yes` : sur une installation par défaut, les
    résultats de l'interface sont donc acceptés sans contrôle même si tous les
    réglages par module ont été désactivés.

    Un laboratoire qui exige un contrôle humain des résultats de l'interface doit
    régler ce paramètre sur `no` également, puis vérifier que les résultats
    arrivant d'un automate se retrouvent dans la file d'approbation et non en
    Accepté.

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
échantillons déjà enregistrés gardent l'ancien format. Le laboratoire porte
alors
deux schémas à la fois, et aucun n'est faux.

## Mobile App Settings

| Paramètre | Contrôle |
|---|---|
| Nom du menu de l'APP mobile | Le nom sous lequel l'application mobile désigne cette installation |

## Connect

| Paramètre | Contrôle |
|---|---|
| URL du tableau de bord national | Le tableau de bord vers lequel cette installation renvoie |

## Viral Load Result PDF Settings

| Paramètre | Contrôle |
|---|---|
| Afficher l'émoticône/l'émoticône | Si le PDF de résultat porte un smiley pour un résultat supprimé |
| Afficher le résultat du journal VL | Si la valeur logarithmique s'imprime à côté des copies par millilitre |
| Message sur la charge virale élevée | Le message imprimé sur un résultat élevé |
| Message de faible charge virale | Le message imprimé sur un résultat bas |
| Format du nom du patient | `flname` pour prénom et nom, `fullname` pour le nom complet, `hidename` pour n'imprimer aucun nom |

Régler **Format du nom du patient** sur `hidename` lorsque les PDF de résultats
circulent par une voie qui ne doit pas porter de noms de patients.

## Vérifier que tout fonctionne

| Modification | Contrôle |
|---|---|
| Date Format, Header, Logo | Ouvrir un rapport et le lire |
| Format des ID d'échantillon | Enregistrer une demande et lire l'ID délivré |
| Format du code-barres | Imprimer un PDF de batch et scanner un code-barres |
| Le même utilisateur peut réviser et approuver | Se connecter comme réviseur et tenter d'approuver le résultat qui vient d'être révisé |
| Auto Approve API Results | Envoyer un résultat par l'API et lire son statut |
| Réglages du PDF de résultat | Imprimer un PDF de résultat |
| Jours de verrouillage des échantillons | Ouvrir un échantillon plus ancien que la limite et tenter de le modifier |
