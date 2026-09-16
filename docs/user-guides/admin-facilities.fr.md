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
2. Sélectionner **Ajouter installations**.
3. Renseigner les informations.

| Champ | Ce qu'il faut saisir |
|---|---|
| Nom de la structure | Le nom que le personnel recherchera. Il ne doit pas déjà être utilisé |
| Code de la structure | Le code national unique |
| Autre/code externe | Un second code, lorsqu'un autre système utilise le sien |
| Type d'installation | Structure sanitaire, ou laboratoire de test |
| Type de test | Chaque type de test auquel la structure participe |
| Point(s) de contrôle | Les points de service, par exemple CDV ou PTME |
| Province/State, District/County | La localisation |
| Address, Latitude, Longitude | Où se trouve la structure. La latitude et la longitude la placent sur la carte du réseau de référence |
| Email(s) | Les adresses pour l'envoi des résultats, séparées par des virgules |
| Lab Manager, Phone Number | La personne de contact |
| Linked Hub Name | Le hub par lequel transitent les échantillons, le cas échéant |
| Statut | Actif ou inactif |

4. Sélectionner **Envoyer**.

Renseigner le **Type de test**, et cocher chaque type de test auquel la
structure
participe. Une structure non rattachée à un type de test n'apparaît pas dans la
liste des structures du formulaire de demande de ce type de test. Une structure
cochée pour un seul type de test reste absente du formulaire de tous les autres.
C'est la raison habituelle d'une structure « absente ».

## Configurer un laboratoire de test

Un laboratoire de test est une structure dont **Type d'installation** vaut
laboratoire
de test. Il porte des réglages supplémentaires qu'une structure sanitaire n'a
pas.

| Réglage | Contrôle |
|---|---|
| Plates-formes disponibles | Les automates de ce laboratoire, par exemple Xpert, Microscopy ou Lam |
| Objectif mensuel | L'objectif mensuel de test du laboratoire, utilisé par les rapports |
| Cible mensuelle de suppression virale | L'objectif de suppression virologique |
| Autoriser le téléchargement de fichiers de résultats | Si ce laboratoire peut importer des fichiers de résultats |
| Image du logo | Le logo des PDF de résultats de ce laboratoire. 80 sur 80 pixels |
| Report Format For VL, EID, TB, Covid-19, Hepatitis | La mise en page du PDF de résultat par type de test |
| Télécharger le modèle de rapport | Un modèle PDF, lorsque la mise en page par défaut ne convient pas |

## Ajouter des signataires aux PDF de résultats

Les signataires sont les noms, fonctions et signatures imprimés sur les PDF de
résultats émis par un laboratoire.

1. Ouvrir le laboratoire de test sous **ADMIN → Structures sanitaires**.
2. Repérer la section des signataires.
3. Pour chaque signataire, saisir **Nom du signataire** et **Désignation**,
   renseigner **Ordre d'affichage**, sélectionner chaque **Type de test**
   applicable, et téléverser l'image de signature en jpg ou png.
4. Sélectionner **Envoyer**.

Un signataire n'est imprimé que sur les modules sélectionnés dans **Type de
test**. Un signataire enregistré sans aucun type de test est conservé et
n'apparaît sur aucun PDF de résultat, ce qui donne l'impression que le bloc de
signatures a été désactivé. Après l'enregistrement, générer un PDF de résultat
pour chaque module et vérifier que les noms attendus y figurent.

| Réglage | Contrôle |
|---|---|
| Afficher le tableau de signatures | Si le bloc de signatures s'imprime |
| Header Text, Header Margin, Report Top Margin | L'en-tête du rapport et ses marges |
| Emplacement du texte en bas de page | Au-dessus du pied de page, ou sous le nom de la plateforme |
| Afficher le numéro de page dans le pied de page | Si les pages sont numérotées |

## Charger ou mettre à jour de nombreuses structures en une fois

1. Aller à **ADMIN → Structures sanitaires**.
2. Sélectionner **Chargement groupé**.
3. Télécharger le format Excel depuis le lien de la page, ou utiliser
   **Exporter** sur la page des structures. Les deux ont les mêmes colonnes : un
   export peut être modifié puis téléversé tel quel.
4. Remplir ou modifier la feuille. **Type de structure** vaut 1 (structure
   sanitaire), 2 (laboratoire de test) ou 3 (site de prélèvement). **Statut**
   vaut `active` ou `inactive`.
5. Choisir une option de chargement, joindre le fichier et sélectionner
   **Vérifier le chargement**.

| Option | Effet |
|---|---|
| Don't update duplicates | Ajoute les nouvelles structures. Ignore toute ligne dont le nom ou le code existe déjà. C'est la valeur par défaut |
| Mettre à jour si le code de l'installation correspond | Met à jour la structure portant ce code. Ajoute les lignes sans correspondance |
| Mettre à jour si le nom de l'installation correspond | Met à jour la structure portant ce nom. Ajoute les lignes sans correspondance |
| Mettre à jour si le nom de l'établissement et le code de l'établissement correspondent | Met à jour seulement si les deux désignent la même structure. Ajoute les lignes sans correspondance |

Rien n'est encore enregistré. La page de vérification classe chaque ligne en
**Nouvelle**, **Mise à jour**, **Aucun changement**, **Ignorée** ou **Erreur**,
avec les champs modifiés par chaque mise à jour. Une cellule facultative vide
conserve la valeur déjà enregistrée.

Certaines lignes portent un avertissement. Elles sont surlignées et décochées :

| Avertissement | Pourquoi c'est important |
|---|---|
| Le nom de la structure change beaucoup | La ligne correspond peut-être à la mauvaise structure |
| Nom presque identique à une structure existante, ou à une autre ligne | La structure risque d'être ajoutée deux fois |
| Coordonnées identiques à une structure existante | La structure risque d'être ajoutée deux fois |
| Le type de structure change | Les listes et formulaires qui dépendent du type changent |
| Le code d'un laboratoire de test change | Le code fait partie des codes d'échantillon générés par le laboratoire |
| Le code externe change | Les autres systèmes qui utilisent l'ancien code ne retrouvent plus la structure |
| Changement de province, ou coordonnées très éloignées | La ligne correspond peut-être à la mauvaise structure |
| La structure devient inactive | La structure disparaît des listes actives |

6. Examiner chaque avertissement. Cocher les lignes à importer.
7. Sélectionner **Importer les lignes cochées**, ou **Annuler** pour abandonner
   le chargement.

Le résultat indique le nombre de structures ajoutées, mises à jour, inchangées,
laissées de côté et non enregistrées. Une ligne dont la structure a été modifiée
par quelqu'un d'autre après la vérification n'est pas enregistrée. Télécharger
les lignes non enregistrées, les corriger et les téléverser à nouveau.

## Repérer les structures au comportement anormal

Activer **Afficher les installations orphelines** sur la page des structures.
Cela liste les
structures dont la province ou le district est absent, inactif, ou non rattaché
à
sa province.

Ces structures se comportent de façon imprévisible dans les filtres
géographiques
de tous les rapports tant que la province et le district ne sont pas corrigés.

**Afficher uniquement les actifs** masque les structures retirées. **Exporter** écrit la liste
filtrée courante dans un fichier Excel.

## Connecter l'outil d'interface

L'outil d'interface transmet les résultats d'un automate à InteLIS sans saisie.
Chaque installation de l'outil se connecte une fois à InteLIS.

**Avant de commencer :** le panneau **Connexions des outils d'interface**
n'apparaît que si le réglage global **Interface API Enabled** vaut `yes`. Il est
livré à `no`, donc sur une installation par défaut ce panneau est absent de la
page. Un administrateur national l'active sous **ADMIN → Configuration
générale**. Le panneau n'apparaît par ailleurs que sur une structure qui est un
laboratoire d'analyse.

1. Aller à **ADMIN → Structures sanitaires**.
2. Ouvrir le laboratoire de test.
3. Descendre jusqu'à **Connexions des outils d'interface**.
4. Sélectionner **Générer un code de connexion**.
5. Saisir les trois groupes du code, et l'URL InteLIS affichée au-dessus, dans
   l'outil d'interface sur l'ordinateur du laboratoire.

Le code expire. La page indique le temps restant. S'il expire, en générer un
autre.

Un seul code peut être en attente à la fois. Pour recommencer, annuler d'abord
le
code courant.

Une fois connectée, l'installation figure sous **Installations connectées** avec
un
statut et une heure de **Dernière connexion**. Utiliser **Dernière connexion**
quand les résultats
cessent d'arriver.

| Action | Quand l'utiliser |
|---|---|
| Se reconnecter / Réinstaller | L'ordinateur du laboratoire est réinstallé, ou l'outil est réinstallé |
| Révoquer | L'ordinateur est retiré ou perdu. Les autres installations ne sont pas affectées |

## Vérifier que tout fonctionne

| Modification | Contrôle |
|---|---|
| Nouvelle structure | La structure apparaît sur le formulaire de demande de chaque type de test coché |
| Laboratoire de test | Le laboratoire apparaît dans la liste Testing Lab du formulaire |
| Signataires | Imprimer un PDF de résultat de ce laboratoire et lire le bloc de signatures |
| Chargement en masse | Non enregistrées vaut 0, et ajoutées plus mises à jour correspond aux lignes cochées |
| Structure orpheline corrigée | Elle n'apparaît plus sous Show Orphaned Facilities |
| Connexion de l'outil d'interface | L'installation figure sous Connected Installations avec une Last Seen récente |
