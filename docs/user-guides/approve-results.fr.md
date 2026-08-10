# Vérifier et approuver les résultats

Un résultat n'est pas diffusé tant qu'il n'est pas approuvé. L'approbation est le
contrôle qui garantit que le résultat enregistré dans InteLIS est bien celui
rendu par l'automate, et qu'il appartient au bon échantillon.

Les résultats saisis via l'outil d'interface peuvent être approuvés
automatiquement, si le laboratoire est configuré ainsi. Les résultats importés
depuis un fichier ou saisis à la main passent toujours par cette page.

## Avant de commencer

- Des résultats saisis dans InteLIS. Voir
  [Saisir les résultats de charge virale](capture-results.md)
- La permission de gérer le statut des résultats
- Le tirage de l'automate ou la liste de travail de la série

## Trouver les résultats en attente

1. Aller à **CHARGE VIRALE DU VIH → Gestion des résultats des tests → Gérer le
   statut des résultats**.
2. Régler **Afficher les échantillons qui sont** sur **Non approuvé/rejeté**.
3. Restreindre avec les filtres si nécessaire.

| Filtre | Usage |
|---|---|
| Code de batch | Une seule série d'automate |
| Date du test de l'échantillon | Tout ce qui a été testé à une date |
| Nom de la structure | Les échantillons d'une seule structure |
| Date de prélèvement de l'échantillon | Une période de prélèvement |
| Type d'échantillon | Un seul type de prélèvement |
| Code du manifeste | Les échantillons d'un colis reçu |

4. Sélectionner **Rechercher**.

Filtrer par code de batch est la méthode la plus fiable. Elle affiche une seule
série d'automate à l'écran, qui se vérifie contre un seul tirage.

## Contrôler chaque résultat

Pour chaque ligne, contrôler trois points par rapport au tirage de l'automate.

1. L'ID de l'échantillon correspond.
2. La valeur du résultat correspond.
3. Le patient affiché est bien celui attendu pour cet ID.

Une discordance entre l'ID de l'échantillon et le patient signifie que
l'échantillon a été enregistré sur le mauvais patient, ou chargé dans la
mauvaise position de l'automate. Ne pas l'approuver. Mettre l'échantillon en
attente et investiguer.

## Approuver

1. Cocher les échantillons à approuver.
2. Dans **Actions groupées**, régler **Statut** sur **Accepté**.
3. Renseigner **Approbateur**, ainsi que **Tester** et **Réviseur** si le
   laboratoire les enregistre.
4. Sélectionner **Appliquer**.

InteLIS demande confirmation avant d'appliquer. Confirmer pour poursuivre.

| Réglage | Effet |
|---|---|
| **Remplacer l'existant** | Écrase les noms déjà enregistrés sur ces échantillons. Laisser désactivé pour ne remplir que les champs vides |

Lorsque la même personne est choisie pour plusieurs des rôles approbateur,
testeur et réviseur, InteLIS avertit ou refuse selon la configuration du
laboratoire. En cas d'avertissement, ne confirmer que si le laboratoire autorise
une personne à cumuler ces rôles.

## Rejeter un échantillon

Utiliser le rejet lorsque l'échantillon lui-même n'était pas propre au test, par
exemple un prélèvement hémolysé ou en quantité insuffisante.

1. Cocher les échantillons.
2. Régler **Statut** sur **Rejeté**.
3. Choisir un **Motif de rejet**.
4. Sélectionner **Appliquer**.

Le motif figure sur le rapport renvoyé à la structure, et dans le rapport de
rejet d'échantillons. Choisir le motif qui indique à la structure ce qu'elle
doit faire différemment la prochaine fois.

## Marquer un échantillon perdu

Utiliser **Perdu** lorsque l'échantillon est introuvable et ne sera pas testé.

1. Cocher les échantillons.
2. Régler **Statut** sur **Perdu**.
3. Sélectionner **Appliquer**.

## Annuler un échantillon

Utiliser l'annulation uniquement lorsque le test n'aura pas lieu du tout, par
exemple une demande saisie deux fois ou retirée par le clinicien.

1. Régler **Afficher les échantillons qui sont** sur **Peut être annulé**.
2. Sélectionner **Rechercher**.
3. Cocher les échantillons.
4. Régler **Statut** sur **Annulée**.
5. Sélectionner **Appliquer**.

InteLIS demande de saisir un mot de confirmation avant d'annuler. C'est
volontaire. L'annulation enregistre que l'échantillon n'a jamais été testé, il
est donc exclu des volumes de test et du délai de rendu.

Ne pas annuler un échantillon qui a été testé et a échoué. Échec et annulation
n'ont pas le même sens dans les rapports. Voir
[Gérer les échecs et les échantillons en attente](failed-and-held-samples.md).

## Corriger un résultat approuvé

1. Régler **Afficher les échantillons qui sont** sur **Déjà approuvé/rejeté**.
2. Sélectionner **Rechercher** et trouver l'échantillon.
3. Appliquer le statut corrigé via **Actions groupées**.

InteLIS demande confirmation avant d'écraser un résultat existant. Ne confirmer
que si le remplacement est le bon résultat.

Les échantillons se verrouillent après un nombre de jours défini par
l'administrateur. Un échantillon verrouillé ne peut plus être modifié ici.
S'adresser à l'administrateur.

## Vérifier que tout fonctionne

Régler **Afficher les échantillons qui sont** sur **Déjà approuvé/rejeté** et
rechercher le code du batch. Tous les échantillons de la série apparaissent avec
leur statut final.

Les échantillons encore listés sous **Non approuvé/rejeté** n'ont pas été
traités.

## Suite

[Diffuser les résultats à la structure demandeuse](release-results.md).
