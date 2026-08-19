# Gérer les structures et les laboratoires

Ce guide entretient les structures sanitaires et les laboratoires de test sous
**ADMIN → Structures sanitaires**. Chaque échantillon est rattaché à l'un et à
l'autre.

La page contient aussi les modèles de rapport, les signataires qui apparaissent
sur les PDF de résultats, et les connexions de l'outil d'interface de chaque
laboratoire.

## Avant de commencer

- Un compte avec des droits d'administrateur
- La province et le district de la structure, déjà créés sous **ADMIN →
  Configuration du système → Divisions géographiques**

## Ajouter une structure

1. Aller à **ADMIN → Structures sanitaires**.
2. Sélectionner **Add Facility**.
3. Renseigner les informations.

| Champ | Ce qu'il faut saisir |
|---|---|
| Facility Name | Le nom que le personnel recherchera. Il ne doit pas déjà être utilisé |
| Facility Code | Le code national unique |
| Other/External Code | Un second code, lorsqu'un autre système utilise le sien |
| Facility Type | Structure sanitaire, ou laboratoire de test |
| Test Type | Chaque type de test auquel la structure participe |
| Testing Point(s) | Les points de service, par exemple CDV ou PTME |
| Province/State, District/County | La localisation |
| Address, Latitude, Longitude | Où se trouve la structure. La latitude et la longitude la placent sur la carte du réseau de référence |
| Email(s) | Les adresses pour l'envoi des résultats, séparées par des virgules |
| Lab Manager, Phone Number | La personne de contact |
| Linked Hub Name | Le hub par lequel transitent les échantillons, le cas échéant |
| Status | Actif ou inactif |

4. Sélectionner **Submit**.

Renseigner le **Test Type**, et cocher chaque type de test auquel la structure
participe. Une structure non rattachée à un type de test n'apparaît pas dans la
liste des structures du formulaire de demande de ce type de test. Une structure
cochée pour un seul type de test reste absente du formulaire de tous les autres.
C'est la raison habituelle d'une structure « absente ».

## Configurer un laboratoire de test

Un laboratoire de test est une structure dont **Facility Type** vaut laboratoire
de test. Il porte des réglages supplémentaires qu'une structure sanitaire n'a
pas.

| Réglage | Contrôle |
|---|---|
| Available Platforms | Les automates de ce laboratoire, par exemple Xpert, Microscopy ou Lam |
| Monthly Target | L'objectif mensuel de test du laboratoire, utilisé par les rapports |
| Suppressed Monthly Target | L'objectif de suppression virologique |
| Allow Results File Upload | Si ce laboratoire peut importer des fichiers de résultats |
| Logo Image | Le logo des PDF de résultats de ce laboratoire. 80 sur 80 pixels |
| Report Format For VL, EID, TB, Covid-19, Hepatitis | La mise en page du PDF de résultat par type de test |
| Upload Report Template | Un modèle PDF, lorsque la mise en page par défaut ne convient pas |

## Ajouter des signataires aux PDF de résultats

Les signataires sont les noms, fonctions et signatures imprimés sur les PDF de
résultats émis par un laboratoire.

1. Ouvrir le laboratoire de test sous **ADMIN → Structures sanitaires**.
2. Repérer la section des signataires.
3. Pour chaque signataire, saisir **Name of Signatory** et **Designation**,
   renseigner **Display Order**, et téléverser l'image de signature en jpg ou png.
4. Sélectionner **Submit**.

| Réglage | Contrôle |
|---|---|
| Display Signature Table | Si le bloc de signatures s'imprime |
| Header Text, Header Margin, Report Top Margin | L'en-tête du rapport et ses marges |
| Bottom Text Location | Au-dessus du pied de page, ou sous le nom de la plateforme |
| Display Page Number in Footer | Si les pages sont numérotées |

## Charger de nombreuses structures en une fois

1. Aller à **ADMIN → Structures sanitaires**.
2. Sélectionner **Bulk Upload**.
3. Télécharger le format Excel depuis le lien de la page.
4. Le remplir et le téléverser.
5. Choisir une option de chargement.

| Option | Effet |
|---|---|
| Don't update duplicates | Ajoute les nouvelles structures. Laisse les existantes intactes. C'est la valeur par défaut |
| Update if Facility Code matches | Écrase la structure portant ce code |
| Update if Facility Name matches | Écrase la structure portant ce nom |
| Update if Facility Name and Facility Code match | N'écrase que si les deux correspondent |

La page indique le nombre total de fiches du fichier, le nombre ajouté et le
nombre non ajouté. Lire les trois. Un fichier qui ajoute moins de structures
qu'il n'en contient a des lignes en échec.

Toujours utiliser le format téléchargé. Un fichier aux colonnes différentes
échoue à l'import.

## Repérer les structures au comportement anormal

Activer **Show Orphaned Facilities** sur la page des structures. Cela liste les
structures dont la province ou le district est absent, inactif, ou non rattaché à
sa province.

Ces structures se comportent de façon imprévisible dans les filtres géographiques
de tous les rapports tant que la province et le district ne sont pas corrigés.

**Show Only Active** masque les structures retirées. **Export** écrit la liste
filtrée courante dans un fichier Excel.

## Connecter l'outil d'interface

L'outil d'interface transmet les résultats d'un automate à InteLIS sans saisie.
Chaque installation de l'outil se connecte une fois à InteLIS.

1. Aller à **ADMIN → Structures sanitaires**.
2. Ouvrir le laboratoire de test.
3. Descendre jusqu'à **Interface Tool Connections**.
4. Sélectionner **Generate Connection Code**.
5. Saisir les trois groupes du code, et l'URL InteLIS affichée au-dessus, dans
   l'outil d'interface sur l'ordinateur du laboratoire.

Le code expire. La page indique le temps restant. S'il expire, en générer un
autre.

Un seul code peut être en attente à la fois. Pour recommencer, annuler d'abord le
code courant.

Une fois connectée, l'installation figure sous **Connected Installations** avec un
statut et une heure de **Last Seen**. Utiliser **Last Seen** quand les résultats
cessent d'arriver.

| Action | Quand l'utiliser |
|---|---|
| Reconnect / Reinstall | L'ordinateur du laboratoire est réinstallé, ou l'outil est réinstallé |
| Revoke | L'ordinateur est retiré ou perdu. Les autres installations ne sont pas affectées |

## Vérifier que tout fonctionne

| Modification | Contrôle |
|---|---|
| Nouvelle structure | La structure apparaît sur le formulaire de demande de chaque type de test coché |
| Laboratoire de test | Le laboratoire apparaît dans la liste Testing Lab du formulaire |
| Signataires | Imprimer un PDF de résultat de ce laboratoire et lire le bloc de signatures |
| Chargement en masse | Le nombre ajouté correspond au nombre total de fiches du fichier |
| Structure orpheline corrigée | Elle n'apparaît plus sous Show Orphaned Facilities |
| Connexion de l'outil d'interface | L'installation figure sous Connected Installations avec une Last Seen récente |
