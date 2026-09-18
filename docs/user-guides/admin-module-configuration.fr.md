# Entretenir les listes du formulaire de demande

Ajouter, corriger et retirer les options des listes déroulantes des
formulaires de demande. Chaque module a ses propres listes. Quelques listes sont
partagées par tous les modules.

Une option introuvable sur le formulaire de demande est presque toujours
inactive, ou a été ajoutée sous un autre module.

## Avant de commencer

- Un compte avec des droits d'administrateur

## Où se trouve chaque liste

Chaque module actif sur l'installation a sa propre section de configuration sous
**ADMIN**.

| Section de configuration | Listes |
| --- | --- |
| Configuration CV | Régime ART, Motifs de rejet, Type d'échantillon, Résultats, Motifs de test, Raisons de l'échec des tests, Mesures correctives recommandées |
| Configuration EID | Motifs de rejet, Type d'échantillon, Motifs de test, Résultats |
| Configuration Covid-19 | Co-morbidités, Motifs de rejet, Type d'échantillon, Symptomes, Motifs de test, Résultats, Kits de test QC, Mesures correctives recommandées |
| Configuration Hépatite | Co-morbidités, Facteurs de risque, Motifs de rejet, Type d'échantillon, Résultats, Motifs de test |
| Tuberculose-Configuration | Motifs de rejet, Type d'échantillon, Motifs de test, Résultats |
| Configuration CD4 | Type d'échantillon, Motifs de test, Motifs de rejet |
| Autres tests de laboratoire Config | Types d'échantillons, Raisons des tests, Raisons de l'échec des tests, Symptomes, Motifs de rejet des échantillons, Unités de résultat du test, Méthodes de test, Catégories de tests, Configuration du type de test |
| Configuration du système | Divisions géographiques, Partenaires, Sources de financement, Stockage en laboratoire. Elles servent tous les modules |

Un type d'échantillon ajouté sous Configuration CV n'atteint pas le formulaire
EID. L'ajouter sous chaque module qui en a besoin.

## Ajouter une entrée

**Choisir le type d'installation, puis suivre ses étapes de haut en bas.**

=== "STS ou autonome"

    1. Aller à **ADMIN**, puis à la section de configuration du module, puis à
       la liste. Par exemple, **ADMIN → Configuration CV → Type d'échantillon**.
    2. Sélectionner le bouton d'ajout en haut à droite de la liste. Il porte le
       nom de la liste, par exemple **Ajouter un type d'échantillon VL**.

        ??? info "Bouton d'ajout de chaque liste"

            | Liste | Bouton |
            | --- | --- |
            | Listes de Configuration CV | **Ajouter un type d'échantillon VL**, **Ajouter les raisons du rejet de l'échantillon VL**, **Ajouter les raisons du test VL**, **Ajouter les résultats VL**, **Ajouter un régime ART VL**, **Ajouter le motif du test VL** (sur Raisons de l'échec des tests), **Ajouter les actions correctives recommandées** |
            | Listes de Configuration EID | **Ajouter un type d'échantillon EID**, **Ajouter les motifs de rejet de l'échantillon de l'EID**, **Ajouter les raisons du test EID**, **Ajouter des résultats EID** |
            | Listes de Configuration Covid-19 | **Ajouter un type d'échantillon Covid-19**, **Ajouter les raisons du rejet de l'échantillon Covid-19**, **Ajouter des raisons de test Covid-19**, **Ajouter les résultats de Covid-19**, **Ajouter les symptômes de Covid-19**, **Ajouter les comorbidités Covid-19**, **Ajouter un nouveau kit de test Covid-19 QC** |
            | Listes de Configuration Hépatite | **Ajouter un type d'échantillon d'hépatite**, **Ajouter les raisons du rejet de l'échantillon d'hépatite**, **Ajouter les raisons du test de l'hépatite**, **Ajouter les résultats concernant l'hépatite**, **Ajouter les comorbidités de l'hépatite**, **Ajouter les facteurs de risque de l'hépatite** |
            | Listes de Tuberculose-Configuration | **Ajouter un type d'échantillon de tuberculose**, **Ajouter les raisons du rejet de l'échantillon de tuberculose**, **Ajouter les motifs du test de dépistage de la tuberculose**, **Ajouter les résultats de la tuberculose** |
            | Listes de Configuration CD4 | **Ajouter un type d'échantillon CD4**, **Ajouter les raisons du rejet de l'échantillon de CD4**, **Ajouter les raisons du test CD4** |
            | Listes de Autres tests de laboratoire Config | **Ajouter un type d'échantillon**, **Ajouter un motif de test**, **Ajouter la raison de l'échec du test**, **Ajouter des symptômes**, **Ajouter les raisons du rejet de l'échantillon**, **Ajouter des unités de résultat du test**, **Ajouter des méthodes de test**, **Ajouter des catégories de test**, **Ajouter un type de test** |
            | Listes de Configuration du système | **Ajouter de nouvelles divisions géographiques**, **Ajouter des partenaires de mise en œuvre**, **Ajouter des sources de financement** |

    3. Saisir le nom de l'entrée, et son code lorsque le formulaire le demande.
    4. Régler le statut sur **Actif**.
    5. Sélectionner **Envoyer**.
    6. Ouvrir le formulaire de demande. L'entrée apparaît dans sa liste
       déroulante.

    ??? info "Ajouter un district"

        Sur **Divisions géographiques**, laisser **Division géographique de la
        société mère** vide lors de l'ajout d'une province. Le régler sur la
        province lors de l'ajout d'un district. Un district sans parent
        n'apparaît sous aucune province sur le formulaire de demande.

=== "LIS"

    Un LIS affiche ces listes sans bouton d'ajout. Les listes viennent du STS.

    1. Demander à l'administrateur du STS d'ajouter l'entrée sur le STS.
    2. Une fois l'entrée ajoutée, sélectionner **Forcer la synchronisation à
       distance** en bas à droite de n'importe quelle page du LIS.
    3. Ouvrir le formulaire de demande. L'entrée apparaît dans sa liste
       déroulante.

    ??? info "Le Stockage en laboratoire se tient sur le LIS"

        **ADMIN → Configuration du système → Stockage en laboratoire** relève du
        laboratoire. Y sélectionner **Ajout d'un congélateur/stockage de
        laboratoire** pour ajouter un congélateur.

## Retirer une entrée

Passer les entrées en inactif au lieu de les supprimer. Une entrée inactive
quitte le formulaire et reste lisible sur les fiches qui l'utilisent déjà.

1. Ouvrir la liste, comme à l'étape 1 de [Ajouter une entrée](#ajouter-une-entree).
2. Sur la ligne de l'entrée, régler le statut sur **Inactif**.
3. Sélectionner **OK** pour confirmer.
4. Ouvrir le formulaire de demande. L'entrée n'est plus proposée.

??? info "Si la ligne n'a pas de liste de statut"

    Sélectionner **Modifier** sur la ligne, régler le statut sur **Inactif**,
    puis sélectionner **Envoyer**. Sur un LIS, le statut ne peut pas être
    modifié. Retirer l'entrée sur le STS.

??? warning "Renommer ou supprimer une province ou un district"

    Les structures rattachées perdent leur lien, et les filtres de localisation
    de tous les rapports cessent de correspondre. Faire valider la modification
    par l'équipe nationale d'abord.

## Configurer un test personnalisé

Un test personnalisé (Custom Test) est un type de test qui n'est pas l'un des
modules intégrés. Il se définit sous **ADMIN → Autres tests de laboratoire Config
→ Configuration du type de test**.

1. Ajouter les entrées dont le test a besoin sous **Unités de résultat du
   test**, **Méthodes de test** et **Catégories de tests**.
2. Ajouter les types d'échantillons, raisons des tests et motifs de rejet dont
   il a besoin, sous les listes correspondantes de Autres tests de laboratoire
   Config.
3. Aller à **ADMIN → Autres tests de laboratoire Config → Configuration du type
   de test**.
4. Sélectionner **Ajouter un type de test** et définir le test.

## Vérifier que tout fonctionne

| Modification | Contrôle |
| --- | --- |
| Nouvelle entrée | Elle apparaît dans sa liste déroulante sur le formulaire de demande |
| Entrée retirée | Elle quitte le formulaire et reste lisible sur une fiche existante |
| Nouveau district | Il apparaît sous sa province sur le formulaire de demande |
| Nouveau test personnalisé | Il apparaît sur le formulaire de demande des autres tests de laboratoire |
