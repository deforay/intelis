# Utiliser InteLIS dans une structure demandeuse

Le personnel d'une structure sanitaire utilise InteLIS pour enregistrer les
échantillons prélevés, les préparer pour le laboratoire de test et consulter les
résultats. Ce guide couvre ce travail.

Un compte de structure atteint moins de pages qu'un compte de laboratoire. La
saisie des résultats, leur approbation et la constitution des batchs se font au
laboratoire de test.

## Avant de commencer

- Un compte fourni par l'administrateur
- La permission d'ajouter des demandes de test

Voir [Se connecter et naviguer dans InteLIS](signing-in.md).

## Le travail dans l'ordre

1. Enregistrer une demande de test pour chaque échantillon prélevé.
2. Préparer le colis et constituer un manifeste qui liste les échantillons.
3. Imprimer le manifeste et le joindre au colis.
4. Suivre les échantillons jusqu'au retour des résultats.
5. Consulter ou imprimer les résultats.

## Enregistrer une demande

Aller à **CHARGE VIRALE DU VIH → Gestion des demandes → Ajouter une nouvelle
demande**.

Remplir les sections structure, patient, échantillon et traitement. Laisser la
section laboratoire vide. Le laboratoire de test la remplit.

Utiliser **Sauvegarder et Suivant** pour passer à l'échantillon suivant sans
revenir à la liste.

Pour le détail champ par champ, voir
[Enregistrer une demande de test de charge virale](register-a-request.md).

Enregistrer la demande dans la structure, plutôt que de laisser le laboratoire
la saisir plus tard depuis la fiche papier, a deux effets. Le laboratoire
réceptionne le colis en saisissant un seul code, et les informations cliniques
sont saisies par ceux qui les détiennent.

## Constituer le manifeste

Une fois le colis prêt à partir, lister ses échantillons sur un manifeste.

Aller à **CHARGE VIRALE DU VIH → Gestion des demandes → Manifeste VL** et suivre
[Envoyer des échantillons à un laboratoire avec un manifeste](send-samples-on-a-manifest.md).

Imprimer le manifeste et le placer dans le colis.

## Suivre les échantillons

Aller à **CHARGE VIRALE DU VIH → Gestion des demandes → Afficher les demandes de
test** et rechercher le code du manifeste, l'identifiant du patient ou la date
de prélèvement.

La colonne de statut indique où se trouve chaque échantillon.

| Statut | Signification |
|---|---|
| Échantillon actuellement enregistré au centre de santé | Enregistré ici, pas encore réceptionné par le laboratoire |
| Échantillon enregistré au laboratoire de test | Le laboratoire l'a reçu et il attend d'être testé |
| En attente d'approbation | Testé, en attente de validation du résultat par le laboratoire |
| Accepté | Le résultat est approuvé et disponible |
| Rejeté | Le laboratoire n'a pas pu tester l'échantillon. Le motif figure sur la fiche |
| Échec/Invalidité | Le test n'a pas produit de résultat exploitable. Le laboratoire décide d'un retest |

La liste complète figure sur la page [statuts des échantillons](sample-statuses.md).

Un échantillon qui reste à **Échantillon actuellement enregistré au centre de
santé** longtemps après le départ du colis signifie généralement que le
laboratoire n'a pas encore activé le manifeste. Contacter le laboratoire avec le
code du manifeste.

## Récupérer les résultats

Les résultats approuvés parviennent à la structure de trois façons, selon
l'organisation du laboratoire.

| Voie | Que faire |
|---|---|
| Envoyés par courriel par le laboratoire | Surveiller l'adresse électronique de la structure |
| Imprimés par le laboratoire | Récupérer les rapports imprimés |
| Disponibles dans InteLIS | Les imprimer depuis la structure |

Pour imprimer depuis la structure, aller à **CHARGE VIRALE DU VIH → Gestion →
Imprimer le résultat**, filtrer sur la structure et imprimer. Voir
[Diffuser les résultats à la structure demandeuse](release-results.md).

Si les résultats envoyés par courriel n'arrivent pas, demander à
l'administrateur de vérifier les adresses enregistrées pour la structure.

## Consulter l'historique d'un patient

Aller à **CHARGE VIRALE DU VIH → Gestion → Rapports cliniques** et ouvrir
l'onglet du rapport d'historique des tests du patient. Rechercher l'identifiant
du patient.

## Rapports disponibles dans une structure

| Rapport | Contenu |
|---|---|
| Rapport sur le statut des échantillons | Graphiques de statut, de suppression virale et de délai de rendu |
| Exporter les résultats | Un tableur des résultats correspondant aux filtres |
| Imprimer le résultat | Les PDF de rapports patients |
| Rapports cliniques | Charge virale élevée, rejets, résultats non disponibles, historique patient |
| Rapport de rejet d'échantillons | Les échantillons rejetés avec leur motif |

Voir [Rapports charge virale](reports.md) pour le contenu de chacun.

## Que faire en cas de problème

| Problème | Action |
|---|---|
| Structure absente du formulaire de demande | Demander à l'administrateur de rattacher la structure au test de charge virale |
| Une demande a été saisie deux fois | Demander au laboratoire d'annuler le doublon |
| Échantillon rejeté | Lire le motif de rejet sur la fiche, puis prélever et renvoyer |
| Le résultat semble incohérent avec le patient | Contacter le laboratoire avec l'ID de l'échantillon. Ne pas agir cliniquement avant |
| Colis envoyé mais échantillons toujours affichés au centre de santé | Contacter le laboratoire avec le code du manifeste |
