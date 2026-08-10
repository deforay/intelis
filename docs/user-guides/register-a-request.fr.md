# Enregistrer une demande de test de charge virale

Utiliser ce guide lorsqu'un échantillon arrive au laboratoire avec une fiche
papier et sans ID d'échantillon. L'enregistrement de la demande attribue un ID à
l'échantillon et le place dans la file d'attente de test.

Pour les échantillons qui arrivent dans un colis avec manifeste, ne pas les
enregistrer un par un. Voir
[Réceptionner des échantillons envoyés avec un manifeste](receive-referred-samples.md).

## Avant de commencer

- Une fiche papier de demande remplie
- La permission d'ajouter des demandes de test

## Ouvrir le formulaire

Aller à **CHARGE VIRALE DU VIH → Gestion des demandes → Ajouter une nouvelle
demande**.

Le formulaire est paramétré pour chaque pays, les champs exacts diffèrent donc
d'une installation à l'autre. Les sections ci-dessous figurent sur la plupart
des formulaires. Les champs marqués d'un astérisque rouge sont obligatoires.
InteLIS refuse d'enregistrer tant que l'un d'eux est vide.

Renseigner aussi les champs facultatifs lorsque la fiche papier contient
l'information. Les rapports ne comptent que ce qui a été saisi.

## Renseigner les informations sur la structure

Cette section indique la provenance de l'échantillon et sa destination.

| Champ | À saisir |
|---|---|
| Province | La province de la structure demandeuse |
| District | Le district, filtré selon la province choisie |
| Clinique/Centre de santé | La structure qui a prélevé l'échantillon |
| Partenaires | Le partenaire qui appuie la structure, si le laboratoire le suit |
| Sources de financement | Le bailleur qui finance le test, si le laboratoire le suit |
| Labo de test | Le laboratoire qui réalise le test |

Choisir d'abord la province. La liste des districts n'affiche que les districts
de cette province, et la liste des structures n'affiche que les structures de ce
district.

Si la structure est absente de la liste, c'est qu'elle n'a pas encore été créée,
ou qu'elle n'est pas rattachée au test de charge virale. Demander à
l'administrateur de l'ajouter. Voir [Administrer InteLIS](administer-intelis.md).

## Renseigner les informations sur le patient

Saisir l'identifiant du patient exactement tel qu'il figure sur la fiche papier.
Sur la plupart des formulaires nationaux, il s'agit du numéro ARV.

Dès que l'identifiant est saisi, InteLIS recherche les demandes antérieures pour
le même patient et affiche ce qu'il trouve :

- le nombre de tests déjà demandés pour ce patient
- la date d'ajout de la dernière demande
- la date de prélèvement de la dernière demande

Utiliser cette information pour repérer un doublon avant d'enregistrer. Une
seconde demande pour un échantillon déjà enregistré crée deux fiches pour un
seul échantillon.

Saisir la date de naissance si la fiche papier la contient. Sinon, saisir l'âge
en années, ou l'âge en mois pour un patient de moins d'un an.

## Renseigner les informations sur l'échantillon

| Champ | À saisir |
|---|---|
| Date de prélèvement de l'échantillon | La date du prélèvement |
| Échantillon envoyé le | La date de départ de l'échantillon de la structure |
| Type d'échantillon | Le type de prélèvement, plasma ou goutte de sang séché par exemple |

La date de prélèvement alimente les rapports de délai de rendu et le contrôle de
péremption des échantillons. Saisir la date figurant sur la fiche papier, et non
la date de saisie.

## Renseigner le traitement et l'indication

Ces sections décrivent le traitement du patient et le motif de la demande. Sur
la plupart des formulaires nationaux, l'indication est un choix unique dans une
liste : suivi de routine, test de contrôle après conseil à l'observance, ou
suspicion d'échec thérapeutique.

L'indication alimente les rapports cliniques. Une demande enregistrée sans
indication se teste normalement, mais elle disparaît de ces rapports.

## Laisser la section laboratoire vide

La section laboratoire contient la date du test, l'automate, le résultat et les
signatures. La laisser vide à l'enregistrement. Elle est remplie après le test
de l'échantillon. Voir
[Saisir les résultats de charge virale](capture-results.md).

## Enregistrer

Deux boutons enregistrent la demande.

| Bouton | Effet |
|---|---|
| **Sauvegarder** | Enregistre la demande et revient à la liste des demandes |
| **Sauvegarder et Suivant** | Enregistre la demande et ouvre un formulaire vierge pour l'échantillon suivant |

Utiliser **Sauvegarder et Suivant** pour traiter une pile de fiches papier.
Certains laboratoires le paramètrent pour reporter les informations de la
structure sur le formulaire suivant. Dans ce cas, vérifier les champs reportés
par rapport à la fiche papier suivante avant d'enregistrer.

InteLIS génère l'ID de l'échantillon à l'enregistrement de la demande. Ne pas
tenter d'en saisir un.

## Imprimer l'étiquette code-barres

Lorsque le laboratoire utilise des étiquettes code-barres, le formulaire propose
l'option **Imprimer une étiquette de code-barres**. La régler avant
d'enregistrer.

Si aucune imprimante n'est proposée, sélectionner **Change/Retry** pour en
choisir une. L'étiquette porte l'ID de l'échantillon sous forme de code-barres.
La coller sur le tube.

## Vérifier que tout fonctionne

Aller à **CHARGE VIRALE DU VIH → Gestion des demandes → Afficher les demandes de
test** et rechercher l'identifiant du patient ou l'ID de l'échantillon.

La demande apparaît avec le statut **Échantillon enregistré au laboratoire de
test**. Ce statut signifie que l'échantillon est enregistré et en attente de
test.

Pour corriger une erreur, sélectionner **Modifier** sur la ligne. Les demandes se
verrouillent après un nombre de jours défini par l'administrateur. Une demande
verrouillée ne peut plus être modifiée.

## Suite

Ajouter les échantillons enregistrés à un batch. Voir
[Créer un batch pour le test](batch-samples.md).
