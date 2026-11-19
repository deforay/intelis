---
description: Les mots qu'utilisent InteLIS et ces guides, chacun avec son sens en une ligne et la page qui en traite.
audience: [lab-staff, lab-supervisor, lab-admin, system-admin, requesting-facility]
module: [all]
type: reference
reviewed: 2026-11-19
reviewed_against: 5.8.8
---
# Les mots de ces guides

Chaque mot ci-dessous a un seul sens dans InteLIS et dans ces guides.

## Où InteLIS fonctionne

| Mot | Sens |
| --- | --- |
| InteLIS | Le système d'information de laboratoire décrit par ces guides. Il s'appelait VLSM auparavant, et certains dossiers ainsi que la base de données gardent ce nom. |
| STS | Sample Tracking System (système de suivi des échantillons). Le serveur InteLIS central auquel se connectent les laboratoires et les structures de santé. Les structures y enregistrent les demandes, et les laboratoires y envoient leurs résultats. |
| LIS | Lab Information System (système d'information de laboratoire). InteLIS installé sur la machine d'un laboratoire. Il reçoit les demandes du STS et lui renvoie les résultats. |
| Autonome | InteLIS qui ne se connecte à aucun STS. Le laboratoire y saisit et y conserve toutes ses données. |
| Synchronisation | L'échange régulier des demandes et des résultats entre un LIS et le STS. Elle a lieu toutes les 5 minutes tant que le réseau fonctionne. Voir [Résultats qui n'atteignent pas le STS](../guides/sts-sync-not-working.md). |
| Machine InteLIS | L'ordinateur qui fait tourner InteLIS pour un laboratoire ou pour le STS. Les pages des rubriques **Installation**, **Mises à jour**, **Sauvegarde et restauration** et **Dépannage** portent sur cette machine. |

## Lieux et personnes

| Mot | Sens |
| --- | --- |
| Structure | La structure de santé qui prélève l'échantillon et demande le test. Aussi appelée structure demandeuse ou clinique. Voir [Pour les structures demandeuses](for-requesting-facilities.md). |
| Laboratoire d'analyse | Le laboratoire qui teste l'échantillon. Voir [Gérer les structures et les laboratoires d'analyse](admin-facilities.md). |
| Rôle | Un ensemble de permissions donné à un utilisateur. Un menu ou un bouton absent signifie le plus souvent que le rôle n'a pas cette permission. Voir [Gérer les utilisateurs et les rôles](admin-users-and-roles.md). |
| Administrateur | Un utilisateur dont le rôle permet de gérer les utilisateurs, les structures et les paramètres sous **ADMIN**. Voir [Administrer InteLIS](administer-intelis.md). |

## Les échantillons et leur parcours

| Mot | Sens |
| --- | --- |
| Demande de test | L'enregistrement d'un échantillon et du test demandé pour lui. Voir [Enregistrer une demande de test de charge virale](register-a-request.md). |
| ID de l'échantillon | Le code que le laboratoire d'analyse donne à un échantillon. Le code propre à la structure reste sur l'enregistrement à côté de lui. |
| Manifeste | La liste des échantillons d'un colis envoyé par une structure à un laboratoire d'analyse. Il a un code de manifeste. Voir [Envoyer des échantillons à un laboratoire avec un manifeste](send-samples-on-a-manifest.md). |
| Activer un manifeste | Réceptionner au laboratoire d'analyse tous les échantillons d'un manifeste en une seule action, avec son code. Voir [Réceptionner des échantillons envoyés avec un manifeste](receive-referred-samples.md). |
| Lot | Un groupe d'échantillons testés ensemble dans une même série sur l'automate. Il a un code de lot. Voir [Créer un lot pour le test](batch-samples.md). |
| Statut | L'étape où se trouve un échantillon, par exemple **En attente d'approbation** ou **Accepté**. Voir [Statuts des échantillons](sample-statuses.md). |
| En attente | Un statut pour un échantillon suspendu jusqu'à ce que quelqu'un décide de la suite. Voir [Gérer les échecs et les échantillons en attente](failed-and-held-samples.md). |
| Approuver | Confirmer qu'un résultat est correct avant sa diffusion. Voir [Vérifier et approuver les résultats](approve-results.md). |
| Diffuser | Envoyer un résultat approuvé à la structure, par impression, e-mail ou export. Voir [Diffuser les résultats à la structure demandeuse](release-results.md). |
| Verrouillé | Un échantillon accepté ou rejeté qui n'a pas changé depuis le nombre de jours de **Jours de verrouillage des échantillons**. Seuls les utilisateurs autorisés à modifier les échantillons verrouillés peuvent le changer. Voir [Vérifier et approuver les résultats](approve-results.md). |
| Rejeter | Refuser un échantillon qui ne peut pas être testé, avec un motif que la structure peut lire. |

## Les automates

| Mot | Sens |
| --- | --- |
| Automate | L'appareil qui teste les échantillons. Les menus d'InteLIS l'appellent **Instrument**. Voir [Configurer un instrument](admin-instruments.md). |
| Outil d'interface | Le programme installé sur un ordinateur du laboratoire qui transmet les résultats de l'automate à InteLIS. Voir [Connecter un automate à InteLIS](../guides/setting-up-interfacing-tool.md). |
| Importer des résultats | Charger les résultats d'un fichier exporté par l'automate, lorsqu'il n'y a pas d'outil d'interface. Voir [Saisir les résultats de charge virale](capture-results.md). |

## L'entretien de la machine

| Mot | Sens |
| --- | --- |
| Commande `intelis` | La commande tapée dans un terminal sur la machine InteLIS pour la vérifier, la mettre à jour, la sauvegarder et la réparer. Voir [Scripts et outils de maintenance](../guides/maintenance.md). |
| Sauvegarde hors de la machine | Une copie des données du laboratoire conservée sur un autre ordinateur, un dossier partagé ou une clé USB. Voir [Configurer des sauvegardes hors de la machine](../guides/setting-up-off-machine-backups.md). |
| Aide-mémoire | Une fiche d'une page à imprimer et à afficher près du poste de travail. Voir [Aide-mémoires imprimables](../job-aids/index.md). |
