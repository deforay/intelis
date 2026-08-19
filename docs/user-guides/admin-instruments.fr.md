# Configurer les automates et l'interfaçage

Ce guide enregistre un automate sous **ADMIN → Configuration du système →
Instruments** pour qu'InteLIS puisse lire ses résultats.

Un automate non enregistré ne peut pas être choisi comme plateforme de test, et
ses fichiers de résultats ne peuvent pas être importés.

## Avant de commencer

- Un compte avec des droits d'administrateur
- Le laboratoire de test déjà créé sous **ADMIN → Structures sanitaires**
- Un fichier de résultats exporté depuis l'automate

## Ajouter un automate

1. Aller à **ADMIN → Configuration du système → Instruments**.
2. Sélectionner **Add Instrument**.
3. Renseigner les informations.

| Champ | Ce qu'il faut saisir |
|---|---|
| Instrument Name | Le fabricant ou la plateforme, par exemple Roche ou Abbott |
| Machine Name | Le nom de cette machine en particulier |
| Testing Lab | Le laboratoire où se trouve la machine |
| Supported Tests | Chaque type de test exécuté par cette machine |
| Instrument File | Le fichier de configuration qui indique à InteLIS comment lire les fichiers de résultats de cette machine |
| Maximum No. of Samples In a Batch | Le nombre d'échantillons d'une série |
| Is this a POC Device? | S'il s'agit d'un appareil délocalisé |
| Latitude, Longitude | Où se trouve la machine, pour la carte du réseau de référence |
| Status | Actif ou inactif |

4. Sélectionner **Submit**.

L'**Instrument File** est ce qui fait fonctionner l'import de fichiers. Sans lui,
les résultats exportés de l'automate ne peuvent pas être lus. Voir
[Saisir les résultats de charge virale](capture-results.md).

## Régler les limites de résultat

Les limites déterminent comment un résultat numérique est affiché et interprété.

| Champ | Ce qu'il faut saisir |
|---|---|
| Lower Limit | La plus petite valeur rendue par la machine, par exemple 20 |
| Higher Limit | La plus grande valeur rendue par la machine, par exemple 10000000 |
| Low VL Result Text | Le texte exact écrit par la machine pour un résultat indétectable, par exemple `Target Not Detected, TND, < 20, < 40`. Séparer les variantes par des virgules |

Saisir dans **Low VL Result Text** toutes les formulations employées par la
machine. Une formulation absente de la liste est importée comme un résultat non
reconnu et non comme indétectable.

## Laisser InteLIS détecter le format de date de la machine

Les fichiers de résultats portent des dates au format propre à la machine.
InteLIS déduit ce format d'un exemple.

1. Repérer **Date Format**.
2. Coller une date exactement telle que la machine l'écrit, par exemple
   `06.19.2025 11:19 AM`.
3. InteLIS en déduit le format.

Un format de date non détecté rend fausse ou vide chaque date importée.

## Régler les compteurs de contrôle qualité

Chaque type de test exécuté par la machine porte ses propres compteurs de
contrôle. Ils indiquent à InteLIS combien de positions d'une série ne sont pas
des échantillons de patients.

| Champ | Ce qu'il faut saisir |
|---|---|
| No. Of Calibrators | Le nombre de positions de calibrateurs de ce type de test |
| Number of Manufacturer Controls | Le nombre de positions de contrôles du fabricant |
| Number of In-House Controls | Le nombre de positions de contrôles internes |

Renseigner ces valeurs par type de test. Une machine exécutant la charge virale
et la tuberculose porte un jeu pour chacun.

## Définir le réviseur et l'approbateur par défaut

**Default Reviewer** et **Default Approver** pré-remplissent les noms du réviseur
et de l'approbateur sur les résultats venant de cette machine.

Ne les renseigner que lorsque les mêmes personnes valident toujours les résultats
de cette machine. Les laisser vides fait enregistrer sur chaque résultat la
personne qui l'a réellement validé.

## Ajouter un commentaire aux résultats de cette machine

**Description/Comment to add in Test Result** ajoute un commentaire fixe à chaque
résultat de cette machine. À utiliser pour une mention de méthode qui doit
figurer sur tous les rapports de cette plateforme.

## Vérifier que tout fonctionne

| Modification | Contrôle |
|---|---|
| Nouvel automate | Il apparaît dans Plateforme de test à la création d'un batch |
| Instrument File | Importer un fichier de résultats de la machine et lire les lignes importées |
| Format de date | Les dates Sample Tested On importées correspondent à celles de la machine |
| Low VL Result Text | Un résultat indétectable s'importe comme indétectable et non comme non reconnu |
| Compteurs de contrôle | Le nombre de positions du batch correspond à la série |
