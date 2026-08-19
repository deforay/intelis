# Administrer InteLIS

Ce guide est le point d'entrée de tout ce qui se trouve sous **ADMIN**. Il décrit
ce dont l'administrateur a la charge, les modifications à faire valider avant de
les appliquer, et où chaque tâche est documentée.

Il ne couvre ni l'installation, ni la mise à jour, ni la sauvegarde d'InteLIS.
Ce sont des tâches serveur, traitées dans les guides d'installation et de
maintenance.

## Avant de commencer

- Un compte avec des droits d'administrateur

## Le menu ADMIN

| Section | Contient | Guide |
|---|---|---|
| Contrôle d'accès | Les rôles et les utilisateurs | [Utilisateurs et rôles](admin-users-and-roles.md) |
| Structures sanitaires | Structures, laboratoires de test, modèles de rapport, signataires, connexions de l'outil d'interface | [Structures et laboratoires](admin-facilities.md) |
| Configuration du système → Instruments | Les automates et le format de leurs fichiers de résultats | [Automates et interfaçage](admin-instruments.md) |
| Configuration CV, Configuration EID, Tuberculose-Configuration et les autres sections de module | Les listes déroulantes du formulaire de demande de chaque module | [Configuration des modules](admin-module-configuration.md) |
| Configuration du système → Configuration générale | Les paramètres qui s'appliquent à toute l'installation | [Configuration générale](admin-general-configuration.md) |
| Configuration du système → Divisions géographiques, Partenaires, Sources de financement, Stockage en laboratoire | Les listes partagées par tous les modules | [Configuration des modules](admin-module-configuration.md) |
| Surveillance | Journal d'activité, piste d'audit, historique de synchronisation, activité des automates, performance du laboratoire | [Surveillance et audit](admin-monitoring.md) |

Le menu ADMIN ne porte une section de configuration que pour les modules actifs
sur l'installation. Une installation qui n'exécute qu'un module ne porte qu'une
section.

Un second espace d'administration se trouve hors de ce menu, à `/system-admin`,
avec sa propre connexion. Voir [Espace System Admin](admin-system-administration.md).

## Deux niveaux d'administrateur

Tous les administrateurs n'ont pas besoin de toutes les pages. InteLIS n'impose
pas cette séparation. Elle se construit dans les rôles.

| Niveau | A la charge de |
|---|---|
| Administrateur de laboratoire | Utilisateurs, structures, automates, listes de configuration des modules, connexions de l'outil d'interface, consultation de la piste d'audit |
| Administrateur national | Tout ce qui précède, plus les rôles et permissions, la Configuration générale et les Divisions géographiques |

La plupart des problèmes sur le terrain viennent de paramètres de niveau national
modifiés par du personnel de niveau laboratoire.

## Modifications à faire valider avant de les appliquer

Les modifications ci-dessous prennent effet sur toute l'installation dès
l'enregistrement. Revenir en arrière n'annule pas leur effet sur les fiches déjà
créées.

| Modification | Emplacement | Pourquoi la faire valider |
|---|---|---|
| Format ou préfixe des ID d'échantillon | Configuration générale, par module | Tout échantillon enregistré ensuite porte le nouveau format. Les échantillons déjà enregistrés gardent l'ancien, ce qui laisse deux schémas au laboratoire |
| Sample Lock Days et Sample Expiry Days | Configuration générale → Global Settings | Détermine quand une fiche cesse d'accepter les modifications. Trop court, le laboratoire ne peut plus corriger un résultat. Trop long, les résultats restent modifiables après diffusion |
| Same user can Review and Approve | Configuration générale → Global Settings | Permet à une personne de réviser et d'approuver son propre résultat. L'approbation est le seul contrôle sur la qualité des résultats |
| Auto Approve API Results | Configuration générale, par module | Diffuse les résultats de l'automate sans contrôle humain. Pas sûr lorsque les ID d'échantillon sont saisis à la main sur l'automate |
| Country of Installation | Configuration générale → Global Settings | Sélectionne la mise en page du formulaire de demande. En changer change le formulaire vu par tous |
| Training Mode | Configuration générale → Global Settings | Marque l'installation comme un entraînement. Ne jamais l'activer sur une installation réelle |
| Permissions d'un rôle | Contrôle d'accès → Les rôles | S'applique aussitôt à tous les utilisateurs portant ce rôle |
| Suppression d'une entrée de liste | Toute page de configuration de module | Passer l'entrée en inactif à la place. La supprimer rend illisibles les fiches qui l'utilisaient |
| Renommage ou suppression d'une province ou d'un district | Configuration du système → Divisions géographiques | Les structures rattachées perdent leur lien, et les filtres géographiques de tous les rapports cessent de correspondre |

## Règles valables partout

**Ne jamais partager un identifiant.** Le journal d'activité, ainsi que les noms
du technicien, du réviseur et de l'approbateur sur chaque rapport, enregistrent
la personne connectée. Un identifiant partagé rend ces enregistrements sans
valeur.

**Retirer, jamais supprimer.** Passer les entrées de liste et les utilisateurs
partants en inactif. Une entrée inactive disparaît du formulaire et reste lisible
sur les fiches qui l'utilisent déjà.

**Ne jamais réattribuer un identifiant à une autre personne.** Les anciennes
fiches restent rattachées à l'ancien nom.

**Modifier un paramètre à la fois**, puis en vérifier l'effet avant d'en modifier
un autre.

## Vérifier qu'une modification a fonctionné

| Modification | Contrôle |
|---|---|
| Nouvel utilisateur | L'utilisateur se connecte et voit le menu attendu |
| Modification de rôle | Se connecter avec un utilisateur de ce rôle, ou utiliser le filtre Permission sur la page des rôles |
| Nouvelle structure | La structure apparaît sur le formulaire de demande de chaque type de test coché |
| Nouvel automate | L'automate apparaît dans Plateforme de test à la création d'un batch |
| Connexion de l'outil d'interface | L'installation figure sous Connected Installations avec une Last Seen récente |
| Entrée de liste | L'entrée apparaît dans sa liste déroulante sur le formulaire |
| Configuration générale | Ouvrir la page concernée par le paramètre et en lire le résultat |
