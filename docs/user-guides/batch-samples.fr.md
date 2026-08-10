# Créer un batch pour le test

Un batch est l'ensemble des échantillons passés ensemble sur un automate.
Constituer le batch dans InteLIS d'abord, puis imprimer le PDF du batch, est ce
qui garantit que les ID d'échantillon sur l'automate sont identiques à ceux
d'InteLIS.

Sauter cette étape est la cause la plus fréquente de résultats qui reviennent
sans correspondre à aucun échantillon.

## Avant de commencer

- Des échantillons enregistrés dans InteLIS, soit
  [enregistrés directement](register-a-request.md), soit
  [activés depuis un manifeste](receive-referred-samples.md)
- L'automate sur lequel la série sera passée
- La permission de gérer les batchs

## Créer le batch

1. Aller à **CHARGE VIRALE DU VIH → Gestion des demandes → Gérer le batch**.
2. Sélectionner **Créer un nouveau batch**.
3. Choisir l'automate dans **Plateforme de test**.
4. Saisir un **Code de batch**.
5. Choisir la numérotation des **Positions**, **Numeric** ou **Alpha Numeric**,
   pour correspondre à l'étiquetage des positions sur l'automate.

Choisir l'automate en premier. InteLIS limite le nombre d'échantillons d'un
batch selon l'automate choisi, et refuse d'aller plus loin sans ce choix.

Les codes de batch sont uniques. Si le code est déjà utilisé, InteLIS le signale
et le batch ne peut pas être enregistré tant que le code n'est pas modifié.
Utiliser la convention de nommage du laboratoire pour pouvoir retrouver le batch
plus tard.

## Trouver les échantillons

La liste sous le formulaire affiche les échantillons en attente de test. Pour la
restreindre, sélectionner **Show Advanced Search Options** et filtrer.

| Filtre | Usage |
|---|---|
| Structure | Les échantillons d'une seule structure sanitaire |
| Samples Entered or Modified By | Les échantillons traités par un utilisateur |
| Date de prélèvement de l'échantillon | Une période de prélèvement |
| Date de réception de l'échantillon au labo | Une période de réception |
| Type d'échantillon | Un seul type de prélèvement |
| Sources de financement | Les échantillons d'un seul bailleur |

Régler **Sort By** et **Sort Type** pour définir l'ordre d'affichage des
échantillons. Cet ordre devient l'ordre du PDF du batch, il faut donc le régler
selon l'ordre de chargement de la série.

Sélectionner **Filter Samples** pour appliquer. Sélectionner **Reset Filters**
pour effacer.

## Sélectionner les échantillons

Cocher les échantillons de la série.

Pour remplir le batch jusqu'à la capacité de l'automate en une action, utiliser
**Automatically select samples for Batch**. La sélection se fait dans la liste
filtrée, selon l'ordre de tri choisi.

InteLIS bloque l'enregistrement dans trois cas.

| Message | Signification |
|---|---|
| Choose a testing platform to proceed | Aucun automate sélectionné |
| Select at least one sample | Aucun échantillon coché |
| More than the allowed number of samples for this platform | Trop d'échantillons cochés pour cet automate |

## Enregistrer

Sélectionner **Sauvegarder et Suivant**. Le batch est créé et apparaît dans la
liste des batchs.

## Imprimer le PDF du batch

1. Aller à **CHARGE VIRALE DU VIH → Gestion des demandes → Gérer le batch**.
2. Trouver le batch.
3. Sélectionner **PDF par lots** ou **PDF par lots compacts** sur la ligne.

| Option | Contenu |
|---|---|
| PDF par lots | Une zone par échantillon, avec un code-barres pour chaque ID |
| PDF par lots compacts | La même liste condensée sur moins de pages |

Certains laboratoires sont configurés pour ne proposer que la version compacte.
Dans ce cas, **PDF par lots** n'apparaît pas sur la ligne.

Imprimer le PDF et l'emporter à l'automate.

## Enregistrer les échantillons sur l'automate

Utiliser le PDF du batch imprimé à l'automate. Scanner ou saisir l'ID de
l'échantillon depuis le PDF pour chaque position.

Les ID d'échantillon sur l'automate doivent correspondre exactement à ceux
d'InteLIS. Un résultat portant un ID qu'InteLIS ne reconnaît pas ne se rattache
à aucun échantillon, et l'échantillon reste dans la file des non testés.

Ne pas saisir les ID depuis la fiche papier, depuis une liste de travail tenue
en dehors d'InteLIS, ni de mémoire.

## Lancer le test

Passer le batch sur l'automate normalement. Puis saisir les résultats. Voir
[Saisir les résultats de charge virale](capture-results.md).

## Modifier ou supprimer un batch

La liste des batchs propose ces actions par ligne.

| Action | Effet | Disponibilité |
|---|---|---|
| **Modifier** | Modifier les informations du batch et ses échantillons | Toujours |
| **Edit Position** | Modifier la position de chaque échantillon | Toujours |
| **PDF par lots** | Réimprimer la planche de codes-barres complète | Sauf si le laboratoire n'utilise que la version compacte |
| **PDF par lots compacts** | Réimprimer la planche condensée | Toujours |
| **Supprimer** | Supprimer le batch et libérer ses échantillons | Uniquement tant qu'aucun échantillon du batch n'a été testé |

Supprimer un batch ne supprime pas ses échantillons. Ils retournent dans la file
des non testés et peuvent être ajoutés à un autre batch.

Dès qu'un échantillon du batch a un résultat, **Supprimer** disparaît de la
ligne. Pour retester ces échantillons, utiliser l'action de retest. Voir
[Gérer les échecs et les échantillons en attente](failed-and-held-samples.md).

## Vérifier que tout fonctionne

Le batch apparaît dans **Gérer le batch** avec le bon nombre d'échantillons dans
**No. of Samples**. Après la série et après la saisie des résultats, **No. of
Samples Tested** augmente jusqu'à correspondre.
