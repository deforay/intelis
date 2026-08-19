# Maintenir les listes du formulaire de demande

Ce guide entretient les listes déroulantes des formulaires de demande. Chaque
module de test tient ses propres listes, et quelques listes sont partagées par
tous les modules.

Une option qu'un utilisateur ne trouve pas sur le formulaire est presque toujours
une entrée inactive, ou une entrée ajoutée sous un autre module.

## Avant de commencer

- Un compte avec des droits d'administrateur

## Une section de configuration par module

Le menu ADMIN porte une section de configuration par module actif sur
l'installation. Une installation qui n'exécute qu'un module ne porte qu'une
section.

| Section de configuration | Listes qu'elle contient |
|---|---|
| Configuration CV | Type d'échantillon, Motifs de rejet, Motifs de test, Résultats, Régime ART, Raisons de l'échec des tests, Mesures correctives recommandées |
| Configuration EID | Type d'échantillon, Motifs de rejet, Motifs de test, Résultats |
| Tuberculose-Configuration | Type d'échantillon, Motifs de rejet, Motifs de test, Résultats |
| Configuration CD4 | Type d'échantillon, Motifs de rejet, Motifs de test |
| Configuration Covid-19 | Type d'échantillon, Motifs de rejet, Motifs de test, Résultats, Symptomes, Co-morbidités, Mesures correctives recommandées, Kits de test QC |
| Configuration Hépatite | Type d'échantillon, Motifs de rejet, Motifs de test, Résultats, Co-morbidités, Facteurs de risque |
| Autres tests de laboratoire Config | Types d'échantillons, Raisons des tests, Motifs de rejet des échantillons, Raisons de l'échec des tests, Symptomes, Unités de résultat du test, Méthodes de test, Catégories de tests, Configuration du type de test |

Ajouter un type d'échantillon sous un module ne l'ajoute pas au formulaire d'un
autre module. L'ajouter sous chaque module concerné.

## Ce que contrôle chaque liste

| Liste | Contrôle |
|---|---|
| Type d'échantillon | Les types de prélèvement proposés sur le formulaire |
| Motifs de rejet | Les motifs proposés lors du rejet d'un échantillon |
| Motifs de test | Les indications de test |
| Résultats | Les valeurs de résultat pour le rendu qualitatif |
| Raisons de l'échec des tests | Les motifs proposés en cas d'échec |
| Symptomes, Co-morbidités, Facteurs de risque | Les listes cliniques à cocher sur le formulaire |
| Régime ART | Les choix de régime sur le formulaire de charge virale |
| Mesures correctives recommandées | Les actions suggérées sur les résultats de charge virale élevée |
| Kits de test QC | Les kits proposés pour les fiches de contrôle qualité |
| Unités de résultat du test, Méthodes de test, Catégories de tests | Les propriétés disponibles pour un type de test personnalisé |

## Ajouter une entrée à une liste

1. Ouvrir la page de la liste dans la section de configuration de son module.
2. Sélectionner l'option d'ajout.
3. Saisir le libellé.
4. Enregistrer.
5. Ouvrir le formulaire de demande et vérifier que l'entrée figure dans sa liste
   déroulante.

Chaque liste fonctionne de la même façon.

## Retirer une entrée

1. Ouvrir la page de la liste.
2. Modifier l'entrée.
3. La passer en inactif.
4. Enregistrer.

Passer les entrées en inactif plutôt que les supprimer. Une entrée inactive
disparaît du formulaire et reste lisible sur les fiches qui l'utilisent déjà. La
supprimer rend ces fiches illisibles.

## Maintenir les listes partagées

Quatre listes se trouvent sous **ADMIN → Configuration du système** et servent
tous les modules.

| Page | Contrôle |
|---|---|
| Divisions géographiques | Provinces et districts |
| Partenaires | La liste des partenaires du formulaire |
| Sources de financement | La liste des bailleurs du formulaire |
| Stockage en laboratoire | Les congélateurs proposés sur la page de stockage |

Pour les divisions géographiques, laisser le parent vide en ajoutant une
province. Renseigner une province comme parent en ajoutant un district. Un
district ajouté sans parent n'apparaît sous aucune province dans le formulaire.

Renommer ou supprimer une province ou un district casse les structures
rattachées, et les filtres géographiques de tous les rapports cessent de
correspondre. Faire valider ces modifications avec l'équipe nationale.

## Configurer un type de test personnalisé

**ADMIN → Autres tests de laboratoire Config → Configuration du type de test**
définit un type de test qui n'est pas l'un des modules intégrés.

Les autres listes de cette section fournissent ce que ce type de test peut
utiliser : ses unités de résultat, sa méthode de test et sa catégorie de test.
Créer ces entrées avant de créer le type de test qui s'y réfère.

## Vérifier que tout fonctionne

| Modification | Contrôle |
|---|---|
| Nouvelle entrée | L'entrée apparaît dans sa liste déroulante sur le formulaire |
| Entrée retirée | L'entrée quitte le formulaire et reste lisible sur une fiche existante |
| Nouveau district | Il apparaît sous sa province dans le formulaire |
| Nouveau type de test personnalisé | Il apparaît dans le formulaire Autres tests de laboratoire |
