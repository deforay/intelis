# Documentation InteLIS

InteLIS est un système d'information de laboratoire libre pour la charge virale
du VIH, le diagnostic précoce du nourrisson, la tuberculose, les hépatites, la
COVID-19, les CD4 et les tests personnalisés. Utiliser le menu latéral pour
naviguer.

InteLIS s'appelait auparavant VLSM. Certains chemins et la base de données
conservent l'ancien nom, et les guides le précisent là où cela compte.

## Utiliser InteLIS

Guides destinés aux personnes qui utilisent InteLIS au quotidien. Ils couvrent
le circuit de la charge virale.

- [Le parcours d'un échantillon dans InteLIS](user-guides/index.md) — le circuit complet, du prélèvement au résultat
- [Se connecter et naviguer dans InteLIS](user-guides/signing-in.md)
- [Enregistrer une demande de test](user-guides/register-a-request.md) — pour les échantillons reçus avec une fiche papier
- [Réceptionner un colis avec manifeste](user-guides/receive-referred-samples.md) — enregistrer un colis entier en une seule action
- [Envoyer des échantillons avec un manifeste](user-guides/send-samples-on-a-manifest.md) — préparer un colis pour un laboratoire de test
- [Créer un batch pour le test](user-guides/batch-samples.md) — constituer un batch et charger l'automate
- [Saisir les résultats](user-guides/capture-results.md) — l'outil d'interface, l'import de fichier et la saisie manuelle
- [Vérifier et approuver les résultats](user-guides/approve-results.md)
- [Gérer les échecs et les échantillons en attente](user-guides/failed-and-held-samples.md) — retest et récupération
- [Diffuser les résultats](user-guides/release-results.md) — impression, courriel et export
- [Enregistrer le stockage d'un échantillon](user-guides/store-samples.md)
- [Administrer InteLIS](user-guides/administer-intelis.md) — utilisateurs, rôles, structures, automates et paramètres des formulaires
- [Pour les structures demandeuses](user-guides/for-requesting-facilities.md)
- [Statuts des échantillons](user-guides/sample-statuses.md) — chaque statut et sa signification
- [Rapports charge virale](user-guides/reports.md) — le contenu de chaque rapport

## Aide-mémoires imprimables

- [Aide-mémoires](job-aids/index.md) — sept fiches d'une page à imprimer et à afficher au poste de travail

## Installation

Les guides d'installation et de maintenance ci-dessous s'adressent aux
techniciens qui administrent le serveur. Ils sont disponibles en anglais
uniquement.

- [Installer InteLIS avec Docker](guides/installing-intelis-with-docker.md) — la voie la plus rapide, et celle recommandée
- [Installer InteLIS sur Ubuntu](guides/installing-intelis-on-ubuntu.md) — Ubuntu 22.04 LTS ou plus récent
- [Installer InteLIS sur Windows](guides/installing-intelis-on-windows.md) — WampServer, PHP, MySQL et l'interfaçage

## Mises à jour et migration

- [Mettre à jour InteLIS sur Ubuntu](guides/updating-intelis-on-ubuntu.md)
- [Mettre à jour InteLIS sur Windows](guides/updating-intelis-on-windows.md)
- [Migrer entre machines Ubuntu](guides/migrating-ubuntu-machines.md)

## Sauvegarde

- [Sauvegarder vers Google Drive avec Rclone](guides/backing-up-to-google-drive-with-rclone.md)
- [Sauvegarder vers une autre machine Linux](guides/backing-up-to-remote-server.md) — via SSH
- [Sauvegarder vers une machine Windows](guides/backing-up-to-windows-machine.md) — sur le réseau local
- [Restaurer depuis une sauvegarde](guides/restoring-from-backup.md) — récupérer une sauvegarde et remettre les données en place

## Maintenance

- [Scripts de maintenance](guides/maintenance.md) — surveillance des services, ressources, db-tools, nettoyage, scanner et tâches planifiées

## Administration à distance

- [Procédure du plan de commande à distance](guides/remote-command-plane.md) — mettre des commandes en file depuis le STS et les suivre

## Dépannage

- [Corriger une incohérence de collation](guides/fix-collation-issue.md)
- [Corriger une erreur de permission](guides/permission-denied-issue.md)
- [Configurer l'outil d'interfaçage](guides/setting-up-interfacing-tool.md)

## Référence

- [Architecture](ARCHITECTURE.md) — le trajet d'une requête dans le code
- [Standards d'ingénierie](engineering-standards.md) — le niveau exigé d'une modification, l'étape de revue et les invariants
- [Référence API](api/) — la documentation OpenAPI interactive
