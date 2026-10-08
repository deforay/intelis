---
description: Relie chaque tâche du menu ADMIN à son guide et liste les modifications globales à faire valider avant de les appliquer.
audience: [lab-admin, system-admin]
module: [all]
type: reference
reviewed: 2026-09-22
reviewed_against: 5.7.74
---
# Administrer InteLIS

Cette page relie chaque tâche du menu **ADMIN** à son guide. Elle liste aussi
les modifications à faire valider avant de les appliquer.

L'installation, la mise à jour et la sauvegarde d'InteLIS sont des tâches
serveur. Elles sont traitées dans les guides
d'[installation](../guides/installing-intelis-on-ubuntu.md) et de
[maintenance](../guides/maintenance.md).

## Où se trouve chaque tâche

| Menu | Contient | Guide |
| --- | --- | --- |
| Utilisateurs et rôles → Utilisateurs, Les rôles | Les identifiants et ce que chacun peut atteindre | [Utilisateurs et rôles](admin-users-and-roles.md) |
| Structures sanitaires | Structures, laboratoires d'analyse, objectifs des laboratoires, signataires | [Structures et laboratoires](admin-facilities.md) |
| Structures sanitaires → Chargement groupé | Plusieurs structures ajoutées ou mises à jour depuis un seul fichier Excel | [Ajouter ou mettre à jour plusieurs structures](admin-facilities-bulk-upload.md) |
| Structures sanitaires → un laboratoire d'analyse → Connexions des outils d'interface | Les codes de connexion de l'outil d'interface | [Connexions de l'outil d'interface](admin-interface-tool-connections.md) |
| Paramètres → Instruments | Les automates et la lecture de leurs fichiers de résultats | [Instruments](admin-instruments.md) |
| Paramètres des tests, un onglet par test | Les listes déroulantes du formulaire de demande de chaque module | [Listes du formulaire de demande](admin-module-configuration.md) |
| Listes de référence → Divisions géographiques, Partenaires, Sources de financement | Les listes partagées par tous les modules | [Listes du formulaire de demande](admin-module-configuration.md) |
| Paramètres → Stockage en laboratoire | Les congélateurs et autres lieux de stockage des échantillons du laboratoire | [Listes du formulaire de demande](admin-module-configuration.md) |
| Paramètres → Configuration générale | Les paramètres qui s'appliquent à toute l'installation | [Configuration générale](admin-general-configuration.md) |
| Surveillance | Piste d'audit, activité, utilisation des pages, synchronisation, fichiers journaux | [Surveillance et audit](admin-monitoring.md) |
| Rapports | Indicateurs de performance du laboratoire, Activité des instruments, Réseau de référencement des échantillons | [Surveillance et audit](admin-monitoring.md) |

**Paramètres des tests** ne montre un onglet que pour les tests actifs sur
l'installation.

Un second espace d'administration, **System Admin**, se trouve hors de ce menu, à
`/system-admin`, avec sa propre connexion. Voir
[Espace System Admin](admin-system-administration.md).

??? info "Sur un LIS, les structures et les listes sont en lecture seule"

    Un LIS affiche les Structures sanitaires et les listes du formulaire de
    demande sans bouton d'ajout ni de modification. Elles sont tenues sur le STS
    et parviennent au LIS lors de sa synchronisation. Les instruments, les
    utilisateurs et le Stockage en laboratoire restent tenus sur le LIS
    lui-même.

## Deux niveaux d'administrateur

| Niveau | A la charge de |
| --- | --- |
| Administrateur de laboratoire | Utilisateurs, instruments, connexions de l'outil d'interface, consultation de la piste d'audit |
| Administrateur national | Tout ce qui précède, plus les rôles, les structures, les listes du formulaire de demande, la Configuration générale et les Divisions géographiques |

**Choisir le type d'installation.**

=== "Autonome ou LIS"

    InteLIS n'impose la séparation que par les privilèges de chaque rôle. Il en
    va de même pour le personnel national connecté au STS.

    1. Donner aux administrateurs de laboratoire un rôle sans les pages de
       niveau national.
    2. Réserver **Les rôles**, **Configuration générale** et **Divisions
       géographiques** au rôle de l'administrateur national.

    La plupart des problèmes sur le terrain viennent de paramètres de niveau
    national modifiés par du personnel de niveau laboratoire.

=== "Cloud"

    Le personnel du laboratoire se connecte au STS avec un rôle de laboratoire
    d'analyse. InteLIS impose la séparation à chacun de ces utilisateurs, sauf à
    ceux qui ont le rôle Admin intégré.

    1. S'attendre à ces cinq pages ADMIN, et à aucune autre : **Utilisateurs**,
       **Instruments**, **Piste d’audit**, **Journal d’activité de
       l’utilisateur** et **Visualisateur de fichiers journaux**. Les privilèges
       du rôle décident lesquelles des cinq apparaissent.
    2. Adresser toute autre modification à l'administrateur national sur le STS.

    Les utilisateurs et les instruments restent limités au laboratoire de
    l'utilisateur. La Piste d'audit n'ouvre que les échantillons de ce
    laboratoire.

## Modifications à faire valider

Chaque modification ci-dessous s'applique à toute l'installation dès
l'enregistrement. Revenir en arrière n'annule pas son effet sur les fiches déjà
créées.

| Modification | Emplacement | Effet |
| --- | --- | --- |
| Format ou préfixe de l'ID de l'échantillon | Configuration générale, par module | Les nouveaux échantillons prennent le nouveau format. Les échantillons existants gardent l'ancien |
| Jours de verrouillage des échantillons, Jours d'expiration de l'échantillon | Configuration générale → Paramètres globaux | Décident quand une fiche cesse d'accepter les modifications |
| Le même utilisateur peut réviser et approuver | Configuration générale → Paramètres globaux | Permet à une même personne de réviser et d'approuver le même résultat |
| Approbation automatique des résultats de l'API (CV, EID, COVID-19 ou TB, libellé exact selon le module) | Configuration générale, par module | Les résultats reçus par l'API sont approuvés sans contrôle humain |
| Pays d'installation | Configuration générale → Paramètres globaux | Change le formulaire de demande vu par tous |
| Mode de formation | Configuration générale → Paramètres globaux | Marque l'installation comme un entraînement |
| Privilèges d'un rôle | Utilisateurs et rôles → Les rôles | S'appliquent aussitôt à tous les utilisateurs de ce rôle |
| Suppression d'une entrée de liste | Tout onglet de Paramètres des tests ou toute page de Listes de référence | Les fiches qui utilisaient l'entrée deviennent illisibles. La passer en inactif à la place |
| Renommage ou suppression d'une province ou d'un district | Listes de référence → Divisions géographiques | Les structures rattachées perdent leur lien, et les filtres des rapports cessent de correspondre |

## Règles valables partout

- **Un identifiant par personne.** Le journal d'activité et les noms du
  technicien, du réviseur et de l'approbateur enregistrent la personne
  connectée.
- **Retirer, jamais supprimer.** Une entrée inactive quitte le formulaire et
  reste lisible sur les fiches qui l'utilisent.
- **Ne jamais donner un ancien identifiant à une nouvelle personne.** Les
  anciennes fiches restent rattachées à l'ancien nom.
- **Modifier un paramètre à la fois.** En vérifier l'effet avant de modifier le
  suivant.
