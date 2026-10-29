---
description: Référence des commandes intelis, des tâches planifiées, des règles de conservation et des scripts de maintenance du serveur, avec les étapes pour libérer de l'espace disque et redémarrer les services.
audience: [system-admin]
module: [all]
type: reference
platform: ubuntu
reviewed: 2026-10-29
reviewed_against: 5.8.5
---
# Scripts et outils de maintenance

Les commandes, tâches planifiées et scripts qui maintiennent un serveur InteLIS
en fonctionnement.

Chaque commande de cette page s'exécute dans un terminal sur le serveur
InteLIS. Le dossier d'installation est `/var/www/intelis`. Sur les
installations plus anciennes, c'est `/var/www/vlsm`.

---

## Commandes `intelis`

`intelis` fonctionne depuis n'importe quel dossier. Une commande qui a besoin
des droits d'administrateur demande le mot de passe.

| Commande | Ce qu'elle fait | Quand l'utiliser |
| --- | --- | --- |
| `intelis` | Affiche un menu numéroté des commandes les plus courantes. Hors d'un terminal, affiche l'aide à la place. | Chaque fois que la commande exacte n'est pas connue. |
| `intelis help` | Affiche la liste des commandes. | Pour retrouver une commande. |
| `intelis update` | Télécharge le programme de mise à jour actuel et met à jour InteLIS vers la version actuelle. Les options sont transmises au programme de mise à jour, par exemple `-s` pour ignorer les mises à jour du système Ubuntu. | Pour installer une nouvelle version. Voir [Mettre à jour InteLIS sur Ubuntu](updating-intelis-on-ubuntu.md). |
| `intelis backup` | Enregistre une nouvelle copie de la base de données et des paramètres sur cette machine, puis l'envoie vers la destination hors de la machine. Propose de configurer une destination s'il n'en existe aucune. | Avant une mise à jour, un changement de machine ou toute modification risquée. |
| `intelis backup status` | Indique quand la dernière sauvegarde hors de la machine s'est exécutée et si elle a réussi. | Pour confirmer que les sauvegardes fonctionnent. |
| `intelis backup test` | Vérifie la destination des sauvegardes. Ne modifie rien. | Après un changement de destination, ou quand `backup status` signale un échec. |
| `intelis backup setup` | Choisit ou change la destination des sauvegardes : une autre machine Linux, un dossier partagé Windows ou une clé USB. | Une fois par machine, ou quand la destination change. |
| `intelis backup disable` | Arrête les sauvegardes planifiées hors de la machine. | Avant de mettre la destination hors ligne. |
| `intelis backup enable` | Réactive les sauvegardes planifiées hors de la machine. | Après `backup disable`. |
| `intelis restore` | Se connecte à la destination des sauvegardes, liste les laboratoires qui y sont stockés et recopie la sauvegarde choisie. Sur une installation qui fonctionne, restaure la base de données en place. `--list` liste seulement les laboratoires. | Pour se remettre d'une machine en panne ou de données perdues. Voir [Restaurer depuis une sauvegarde](restoring-from-backup.md). |
| `intelis check` | Vérifie PHP, les paramètres PHP du serveur web, `vendor/`, le fichier de configuration, les chemins accessibles en écriture et la base de données. Affiche la commande qui corrige chaque échec. `--quiet` n'affiche que les avertissements et les échecs. S'exécute aussi sous le nom `intelis preflight`. | Quand InteLIS ne démarre pas, et après toute modification du serveur. |
| `intelis health` | Vérifie l'utilisation du disque, le temps de réponse de MySQL, les chemins accessibles en écriture et les sauvegardes hors de la machine. | Pour savoir si un serveur en marche va bien en ce moment. |
| `intelis doctor` | Cherche pourquoi InteLIS ne s'ouvre pas dans le navigateur et propose chaque réparation sûre. Lance lui-même `fix-database` quand la base de données est en cause. `--check` produit seulement un rapport. `--yes` applique chaque réparation sûre sans demander. `--quiet` n'affiche que le verdict et le chemin du rapport. | Quand le site ne s'ouvre pas. Voir [Le navigateur affiche du code PHP au lieu d'InteLIS](browser-shows-php-code.md). |
| `intelis fix-database` | Cherche pourquoi MySQL est arrêté et propose chaque réparation sûre. Accepte les mêmes options que `doctor`. | Quand MySQL ne démarre pas. Voir [MySQL ne démarre pas](mysql-will-not-start.md). |
| `intelis sts-setup` | Définit l'adresse du STS et le laboratoire que sert cette machine, récupère les listes depuis le STS et obtient un jeton STS. Machines du laboratoire uniquement. | Une fois par laboratoire, ou quand l'adresse du STS ou le laboratoire change. Voir [Connecter un laboratoire au STS](connect-lab-to-sts.md). |
| `intelis sync-sts` | Lance maintenant une synchronisation complète avec le STS : jeton, listes, résultats, demandes et commandes à distance. | Pour synchroniser maintenant au lieu d'attendre la synchronisation planifiée. Voir [Les résultats n'arrivent pas au STS](sts-sync-not-working.md). |
| `intelis interface` | Importe les résultats que l'outil d'interface conserve. | Pour importer les résultats maintenant au lieu d'attendre l'import planifié. Voir [Les résultats de l'automate n'arrivent pas](interfacing-results-not-arriving.md). |
| `intelis interface setup` | Crée la base de données d'interfaçage et le compte avec lequel l'outil d'interface se connecte, puis écrit les paramètres. | Une fois, au moment de connecter l'outil d'interface. Voir [Connecter un automate à InteLIS](setting-up-interfacing-tool.md). |
| `intelis interface migrate` | Met à jour la base de données d'interfaçage avec les migrations propres à l'outil d'interface. | Quand `intelis check` ou le support le demande. |
| `intelis reset-password` | Définit un nouveau mot de passe pour un utilisateur qui ne peut pas se connecter, y compris le seul administrateur. Liste les utilisateurs actifs pour faire un choix. Accepte les options de `bin/reset-user-password.php`. S'exécute aussi sous le nom `intelis password`. | Quand personne ne peut réinitialiser le mot de passe depuis le navigateur. Voir [Mot de passe oublié](forgotten-password.md). |
| `intelis provision` | Crée les dossiers dans lesquels InteLIS écrit et corrige leur propriétaire et leurs droits d'accès. | Quand `intelis check` signale un chemin absent ou non accessible en écriture. |
| `intelis migrate` | Exécute les migrations de la base de données. | Quand `intelis check` signale des migrations en attente. |
| `intelis install` | Installe les dépendances PHP dans `vendor/`. | Quand `intelis check` signale que `vendor/` est absent ou pas à jour. |
| `intelis purge-cache` | Vide le cache de l'application. | Après une modification de configuration qui n'apparaît pas. |
| `intelis token` | Génère ou renouvelle le jeton d'API du STS. Ne fait rien sur un STS. | Quand `intelis check` signale un jeton absent. |
| `intelis audit-triggers-install` | Installe ou régénère les déclencheurs d'audit. | Quand `intelis check` signale des déclencheurs d'audit absents. |
| `intelis db:collation` | Convertit les tables et les colonnes de la base de données vers la collation `utf8mb4`. | Quand `intelis check` signale un problème de collation. Voir [Corriger les erreurs de collation illégale ou incohérente](fix-collation-issue.md). |
| `intelis composer <args>` | Exécute Composer avec les arguments donnés dans le dossier d'installation. | Pour les commandes propres à Composer, comme `intelis composer update`. |
| `intelis <script>` | Exécute tout autre script Composer, par exemple `intelis housekeeping` ou `intelis scan`. | Pour exécuter un script listé dans `composer.json`. |

`doctor` répond aussi aux noms `fix-site`, `fix-web`, `site`, `web`, `apache` et
`php`. `fix-database` répond aussi aux noms `fix-db`, `database`, `db`, `mysql`
et `mysql-doctor`.

`doctor` et `fix-database` écrivent un rapport complet dans
`/var/log/intelis-doctor-<timestamp>.log` et
`/var/log/intelis-mysql-doctor-<timestamp>.log`. Quand un utilisateur ordinaire
les lance avec `sudo`, ils copient aussi le rapport dans le dossier personnel de
cet utilisateur, sous le nom `site-report.txt` ou `mysql-report.txt`. Les mots de
passe sont retirés de la copie.

Sur une machine sans la commande `intelis`, lancer l'outil de diagnostic (`intelis-doctor.sh`)
directement depuis GitHub :

```bash
sudo bash -c "$(wget -qO- https://raw.githubusercontent.com/deforay/intelis/master/scripts/intelis-doctor.sh)"
```

---

## Libérer de l'espace disque

Suivre ces étapes quand le disque est plein ou quand `intelis health` signale
une forte utilisation du disque.

1. Vérifier quel disque est plein :

    ```bash
    df -h
    ```

2. Aller dans le dossier d'installation :

    ```bash
    cd /var/www/intelis
    ```

3. Lancer le nettoyage automatique maintenant :

    ```bash
    sudo -u www-data php bin/housekeeping.php 30
    ```

    Le nettoyage automatique s'exécute en arrière-plan et écrit sa progression
    dans `var/logs/housekeeping-<date>-<time>.log`. Pour conserver moins, donner
    un nombre de jours plus petit, par exemple `7`. Ce nombre s'applique aux
    sauvegardes de la base de données et aux journaux de l'application. Voir
    [Durées de conservation du nettoyage automatique](#durees-de-conservation-du-nettoyage-automatique).

4. Supprimer les journaux de l'application de plus de 7 jours :

    ```bash
    sudo -u www-data php bin/clear-logs.php --days=7
    ```

5. Supprimer les journaux binaires MySQL de plus de 3 jours :

    ```bash
    sudo -u www-data php vendor/bin/db-tools purge-binlogs --days=3
    ```

6. Supprimer les entrées du journal système de plus de 7 jours :

    ```bash
    sudo journalctl --vacuum-time=7d
    ```

7. Supprimer les fichiers de paquets téléchargés :

    ```bash
    sudo apt-get clean
    ```

8. Vérifier de nouveau le disque :

    ```bash
    df -h
    ```

    La valeur `Use%` du disque plein est maintenant plus basse. Si elle dépasse
    encore 90 %, lister les plus gros dossiers et envoyer le résultat au
    support :

    ```bash
    sudo du -xh --max-depth=2 /var | sort -h | tail -20
    ```

---

## Redémarrer les services bloqués

Suivre ces étapes quand les pages d'InteLIS restent bloquées ou affichent une
erreur de connexion à la base de données.

1. Redémarrer Apache :

    ```bash
    sudo systemctl restart apache2
    ```

2. Redémarrer MySQL :

    ```bash
    sudo systemctl restart mysql
    ```

    Sur un serveur MariaDB, utiliser `mariadb` à la place de `mysql`.

3. Vérifier que les deux services fonctionnent :

    ```bash
    systemctl is-active apache2 mysql
    ```

    Le résultat est `active` deux fois. Si MySQL ne démarre pas, lancer
    `sudo intelis fix-database`. Si les deux sont actifs et que le site ne
    s'ouvre toujours pas, lancer `sudo intelis doctor`.

---

## Vérifier que les tâches planifiées s'exécutent

Suivre ces étapes quand les codes d'échantillon ne sont plus générés, ou quand
la synchronisation et les sauvegardes s'arrêtent.

1. Vérifier le fichier de pulsation :

    ```bash
    ls -l /var/www/intelis/var/.cron_heartbeat
    ```

    Le planificateur met ce fichier à jour chaque minute. Si son heure date des
    deux dernières minutes, le planificateur fonctionne et rien d'autre n'est
    nécessaire.

2. Vérifier l'entrée du planificateur dans la crontab de root :

    ```bash
    sudo crontab -l | grep cron.sh
    ```

    Le résultat est :

    ```text
    * * * * * cd /var/www/intelis && ./cron.sh
    ```

    Si la ligne est absente ou commence par `#`, lancer `intelis update`. La
    mise à jour rajoute la ligne.

3. Rechercher un marqueur de pause :

    ```bash
    ls -l /var/www/intelis/var/cron-paused
    ```

    `cron.sh` saute chaque exécution tant que ce fichier existe.
    `intelis update` le crée pendant les migrations de la base de données et le
    supprime ensuite. Le fichier expire au bout de 30 minutes. Si aucune mise à
    jour n'est en cours, le supprimer :

    ```bash
    sudo rm /var/www/intelis/var/cron-paused
    ```

4. Attendre une minute, puis refaire l'étape 1. L'heure de la pulsation est
   maintenant actuelle.

---

## Restaurer une sauvegarde de la base de données sur la même machine

Suivre ces étapes pour ramener la base de données de cette machine à une
sauvegarde locale plus ancienne. Pour restaurer sur une nouvelle machine, voir
[Migrer d'une machine Ubuntu à une autre](migrating-ubuntu-machines.md).

1. Aller dans le dossier d'installation :

    ```bash
    cd /var/www/intelis
    ```

2. Lancer la restauration :

    ```bash
    sudo -u www-data php vendor/bin/db-tools restore
    ```

3. Choisir la sauvegarde dans la liste. La plus récente est en premier.

    Pour restaurer un fichier connu à la place, donner son chemin :

    ```bash
    sudo -u www-data php vendor/bin/db-tools restore backups/db/vlsm-20260908-060002-<32 characters>.sql.zst.gpg
    ```

    Avant de restaurer, db-tools sauvegarde la base de données actuelle dans
    `backups/db/`. Le nom de cette sauvegarde commence par `pre-restore-`.

4. Appliquer les mises à jour de la base de données :

    ```bash
    intelis migrate
    ```

5. Se connecter à InteLIS et vérifier que les échantillons les plus récents sont
   ceux attendus.

Ne pas renommer un fichier de sauvegarde. Les 32 caractères de son nom font
partie de sa clé de déchiffrement. La date et l'heure du nom sont en UTC. Quand
le chiffrement des sauvegardes avec une clé détenue par le STS est activé, le nom
n'a pas de partie aléatoire et la clé vient du STS.

---

## Référence

### Tâches planifiées

Le planificateur s'exécute chaque minute depuis la crontab de root, par
`cron.sh`, qui lance [Crunz](https://github.com/crunzphp/crunz). Les tâches sont
définies dans `sys/cron/ScheduledTasks.php`. Les heures sont dans le fuseau
horaire de l'instance. Une tâche ne démarre pas tant que son exécution
précédente est encore en cours.

| Tâche | Commande | Planification | S'exécute quand |
| --- | --- | --- | --- |
| Archivage des tables d'audit | `app/tasks/archive-audit-tables.php` | Toutes les 6 heures (00:00, 06:00, 12:00, 18:00) | Toujours |
| Regroupement de la piste d'audit | `app/tasks/bundle-audit-trail.php` | Chaque jour à 05:15 | Toujours |
| Génération des codes d'échantillon | `bin/sample-code-generator.php` | Chaque minute | Toujours |
| Sauvegarde de la base de données | `db-tools backup --all` | Toutes les 6 heures (00:00, 06:00, 12:00, 18:00) | Toujours |
| Purge des journaux binaires | `db-tools purge-binlogs --days=7` | Chaque jour à 04:05 | Toujours |
| Maintenance hebdomadaire de la base de données | `db-tools maintain --all --days=7` | Le dimanche à 03:00 | Toujours |
| Optimisation mensuelle de la base de données | `db-tools maintain --all --optimize --days=7` | Le 1er du mois à 04:00 | Toujours |
| Sauvegarde des paramètres (paramètres, signatures, modèles de rapport, logos) | `bin/backup-configs.php` | Chaque jour à 03:30 | Toujours |
| Nettoyage automatique | `bin/housekeeping.php 30` | Chaque jour à 00:45 | Toujours |
| Contrôles de santé | `bin/health.php` | Toutes les heures, à l'heure pile | Toujours |
| Mise à jour du statut des échantillons | `bin/update-sample-status.php` | Chaque jour à 00:05 | Toujours |
| Signalement des problèmes de données | `bin/flag-data-issues.php` | Chaque jour à 00:25 | Toujours |
| Interprétation des résultats VL | `bin/update-vl-suppression.php` | Chaque minute | Toujours |
| Élagage des commandes à distance | `bin/prune-remote-commands.php` | Chaque jour à 03:15 | Toujours |
| Synchronisation SQLite vers MySQL de l'interface | `bin/sync-interface-sqlite-mysql.php` | Toutes les 5 minutes | Interfaçage activé |
| Import de l'interface | `bin/interface.php` | Chaque minute | Interfaçage activé |
| Synchronisation STS | `composer run sync-sts` | Toutes les 5 minutes | LIS uniquement, avec une URL STS définie |
| Métadonnées SmartConnect | `bin/smart-connect/sync.php metadata` | Toutes les 20 minutes | URL SmartConnect définie |
| SmartConnect VL | `bin/smart-connect/sync.php vl` | Aux minutes 0, 25 et 50 | URL SmartConnect définie, module VL (charge virale) activé |
| SmartConnect EID | `bin/smart-connect/sync.php eid` | Toutes les 30 minutes | URL SmartConnect définie, module EID activé |
| SmartConnect COVID-19 | `bin/smart-connect/sync.php covid19` | Aux minutes 0 et 35 | URL SmartConnect définie, module COVID-19 activé |
| Références entre laboratoires | `bin/referrals.php` | Chaque minute | STS uniquement |
| Application de la correspondance des motifs de rejet | `bin/apply-rejection-reason-map.php --quiet` | Chaque jour à 03:40 | STS uniquement |

`bin/health.php` ne déclenche une alerte administrateur que lorsqu'un contrôle
change d'état.

Le planificateur met à jour `var/.cron_heartbeat` à chaque exécution. Tant que
`var/cron-paused` existe et date de moins de 30 minutes, `cron.sh` n'exécute
aucune tâche.

### Autres minuteries

Celles-ci s'exécutent en dehors du planificateur.

| Minuterie | Planification | Installée par |
| --- | --- | --- |
| Sauvegarde hors de la machine (`/usr/local/bin/intelis-backup.sh`, crontab de root) | Toutes les 8 heures (00:00, 08:00, 16:00) et au démarrage | `intelis backup setup` |
| Exécuteur des commandes à distance (`intelis-runner.timer`) | Toutes les 60 secondes | `intelis update` |
| Garde des services (`service-guard.timer`) | Toutes les 60 secondes | `scripts/service-guard.sh` |
| Moniteur de ressources (`resource-monitor.timer`) | Toutes les 120 secondes | `scripts/resource-monitor.sh` |

### Durées de conservation du nettoyage automatique

`bin/housekeeping.php [DAYS]` s'exécute chaque jour à 00:45 avec `DAYS` fixé à
30.

| Données | Emplacement | Conservées |
| --- | --- | --- |
| Sauvegardes de la base de données | `backups/db/` | `DAYS` jours. La variable d'environnement `INTELIS_DB_BACKUP_DAYS` remplace ce nombre de jours. `INTELIS_DB_BACKUP_KEEP` conserve plutôt un nombre de sauvegardes. |
| Journaux de l'application | `var/logs/` | `DAYS` jours. Au-delà de 1 000 Mo, les fichiers les plus anciens sont supprimés jusqu'à ce que le dossier revienne à 80 % de la limite. |
| Fichiers temporaires | `public/temporary/` | 3 jours. Au-delà de 500 Mo, les fichiers les plus anciens sont supprimés jusqu'à ce que le dossier revienne à 80 % de la limite. |
| Fichiers temporaires | `var/temporary/` | 3 jours. Au-delà de 500 Mo, les fichiers les plus anciens sont supprimés jusqu'à ce que le dossier revienne à 80 % de la limite. |
| Corps des requêtes et réponses de l'API | `var/track-api/requests/`, `var/track-api/responses/` | 30 jours |
| Journal d'activité | table `activity_log` | 365 jours |
| Historique de connexion | table `user_login_history` | 365 jours |
| Enregistrements des requêtes API | table `track_api_requests` | 90 jours. Les pages de statistiques API et d'historique de synchronisation n'affichent pas d'appels plus anciens. |
| Utilisation des pages | table `user_page_usage` | 365 jours |
| Entrées terminées de la file de génération des codes d'échantillon | table `queue_sample_code_generation` | 90 jours. Les entrées non traitées ne sont jamais supprimées. |

Le nettoyage automatique ne supprime jamais `.htaccess`, `index.php`,
`.gitkeep` ni `.hgkeep`.

Autres règles de conservation :

| Données | Conservées | Définie par |
| --- | --- | --- |
| Sauvegardes de la base de données | Les 7 plus récentes par base de données, appliqué après chaque sauvegarde | `retention` dans `db-tools.php` |
| Commandes à distance terminées (`s_lis_remote_commands`) | 90 jours | `remote_command_retention_days` dans la configuration globale, élaguées chaque jour à 03:15 |

### Scripts

Lancer chaque script depuis le dossier d'installation avec `sudo`.

| Script | Ce qu'il fait | Commande |
| --- | --- | --- |
| `scripts/service-guard.sh` | Installe une minuterie systemd qui redémarre Apache et MySQL quand ils ne répondent plus. `--uninstall` la retire. | `sudo bash scripts/service-guard.sh` |
| `scripts/resource-monitor.sh` | Installe une minuterie systemd qui vérifie la mémoire, le disque, le processeur et la charge, et supprime des fichiers quand une limite est critique. `--uninstall` la retire. | `sudo bash scripts/resource-monitor.sh` |
| `scripts/cleanup-logs.sh` | Tronque sur place les gros fichiers journaux et supprime les plus anciens quand le total est trop grand. | `sudo bash scripts/cleanup-logs.sh -n` |
| `scripts/intelis-doctor.sh` | S'exécute derrière `intelis doctor`. | `sudo intelis doctor` |
| `scripts/mysql-doctor.sh` | S'exécute derrière `intelis fix-database`. | `sudo intelis fix-database` |
| `scripts/remote-backup.sh` | S'exécute derrière `intelis backup setup`. | `intelis backup setup` |
| `scripts/restore-backup.sh` | S'exécute derrière `intelis restore`. | `intelis restore` |
| `scripts/upgrade.sh` | S'exécute derrière `intelis update`. | `intelis update` |
| `scripts/bootstrap.sh` | Installe depuis GitHub la commande `intelis` et le programme de mise à jour actuels. À lancer une fois sur une machine où `intelis update` ne fonctionne pas. | `sudo bash scripts/bootstrap.sh` |
| `scripts/default-host-setup.sh` | Fait pointer le site par défaut d'Apache vers l'installation. `-p PATH` définit l'installation, `-n NAME` le nom du serveur. | `sudo bash scripts/default-host-setup.sh -p /var/www/intelis` |

Les scripts de maintenance PHP s'exécutent en tant que `www-data` :

| Script | Ce qu'il fait | Commande |
| --- | --- | --- |
| `bin/scan.php` | Affiche le type d'instance, la version, la configuration avec les secrets masqués, l'état de la synchronisation et le rapport de `health.php`. | `sudo -u www-data php bin/scan.php` |
| `bin/health.php` | Vérifie le disque, le temps de réponse de MySQL, les chemins accessibles en écriture et les sauvegardes hors de la machine. Se termine avec un code non nul en cas de résultat critique. | `sudo -u www-data php bin/health.php` |
| `bin/preflight.php` | S'exécute derrière `intelis check`. | `sudo -u www-data php bin/preflight.php` |
| `bin/housekeeping.php` | Supprime les anciennes sauvegardes, les anciens fichiers et les anciennes lignes de la base de données. Voir [Durées de conservation du nettoyage automatique](#durees-de-conservation-du-nettoyage-automatique). | `sudo -u www-data php bin/housekeeping.php 30` |
| `bin/clear-logs.php` | Supprime des fichiers journaux de `var/logs/`. `--keep=N` conserve les N plus récents, `--days=N` conserve les N derniers jours, `--all` supprime tout, `--dry-run` produit seulement un rapport. | `sudo -u www-data php bin/clear-logs.php --days=7` |
| `bin/reset-user-password.php` | Définit un nouveau mot de passe pour un utilisateur qui ne peut pas se connecter. Liste les utilisateurs actifs pour faire un choix. `--login` désigne l'utilisateur, `--generate` affiche un mot de passe aléatoire, `--activate` active aussi le compte, `--force-reset` demande un nouveau mot de passe à la connexion suivante. `intelis reset-password` le lance. | `sudo -u www-data php bin/reset-user-password.php` |
| `bin/duplicate-indexes.php` | Liste les index qui doublent un autre index de la même table. `--fix` supprime les doublons. | `sudo -u www-data php bin/duplicate-indexes.php` |

Toutes les commandes ci-dessus supposent d'abord `cd /var/www/intelis`.

### Moniteur de ressources

| Ressource | Avertissement | Critique |
| --- | --- | --- |
| Mémoire | 80 % | 90 % |
| Disque | 85 % | 95 % |
| Charge | | Au-dessus de 2,0 par cœur de processeur |

Le moniteur vérifie `/`, `/var`, `/tmp` et `/home` quand chacun est un point de
montage distinct. Il consigne les processus au-dessus de 50 % du processeur. Les
avertissements et les alertes vont dans le journal système.

À un niveau critique, il supprime ces éléments sans demander :

| Déclencheur | Action |
| --- | --- |
| Mémoire à 90 % | Vide les caches du système |
| Disque `/` à 95 % | Supprime les entrées du journal de plus de 7 jours et le cache des paquets |
| Disque `/var` à 95 % | Supprime sous `/var/log` les fichiers `*.log.*.gz` de plus de 30 jours et les fichiers `*.log.*` de plus de 7 jours |
| Disque `/tmp` à 95 % | Supprime sous `/tmp` les fichiers de plus de 3 jours |

Ne pas installer le moniteur sur une machine qui conserve des fichiers de valeur
sous `/var/log` ou `/tmp`.

```bash
systemctl status resource-monitor.timer
journalctl -u resource-monitor.service -n 50 --no-pager
```

### Garde des services

Toutes les 60 secondes, la garde :

- démarre `apache2`, `httpd`, `mysql` ou `mariadb` quand il est installé et ne fonctionne pas
- redémarre Apache quand `apachectl -t` échoue ou quand `http://127.0.0.1/` ne répond pas en 3 secondes
- redémarre MySQL quand `mysqladmin ping` échoue

Elle configure aussi Apache et MySQL pour qu'ils redémarrent chaque fois qu'ils
s'arrêtent, jusqu'à 10 fois en 2 minutes.

```bash
systemctl status service-guard.timer
journalctl -u service-guard.service -n 100 --no-pager
```

### Options de cleanup-logs.sh

| Option | Description | Par défaut |
| --- | --- | --- |
| `--dir DIR` | Dossier à nettoyer | `var/logs/` |
| `--pattern GLOB` | Noms de fichiers à cibler | `*-logfile.log` |
| `--max-size MB` | Tronquer les fichiers plus gros que cette taille | `500` |
| `--keep-tail MB` | Conserver les N derniers Mo d'un fichier tronqué. `0` le vide. | `5` |
| `--max-total MB` | Supprimer les fichiers les plus anciens quand le total dépasse cette taille | `10240` |
| `--keep N` | Toujours conserver les N fichiers les plus récents | `30` |
| `-n`, `--dry-run` | Rapport uniquement. Ne rien modifier. | Désactivé |
| `-v`, `--verbose` | Consigner chaque fichier | Désactivé |

Le script écrit sa sortie dans `/var/log/intelis-log-cleanup.log`, et non à
l'écran.

### Commandes db-tools

À lancer depuis le dossier d'installation :

```bash
sudo -u www-data php vendor/bin/db-tools <command> [options]
```

| Commande | Description |
| --- | --- |
| `backup [database]` | Crée une sauvegarde chiffrée. `--all` couvre chaque profil. `--no-encrypt` saute le chiffrement. |
| `restore [file]` | Restaure une sauvegarde. Sans fichier, liste les sauvegardes pour faire un choix. Enregistre d'abord une sauvegarde `pre-restore-`. |
| `export <output> [database]` | Exporte la base de données en SQL brut. Le fichier de sortie vient en premier. |
| `import <file> [database]` | Importe un fichier `.sql`, `.sql.gz`, `.sql.zst`, `.sql.gpg` ou `.zip`. Le fichier vient en premier. |
| `show` | Liste les sauvegardes. |
| `verify <file or folder>` | Vérifie qu'une sauvegarde, ou chaque sauvegarde d'un dossier, peut être lue. |
| `clean` | Supprime les anciennes sauvegardes. `--keep=N` conserve les N plus récentes. `--days=N` supprime celles de plus de N jours. |
| `size [database]` | Affiche la taille de la base de données et les plus grosses tables. `--top=N` fixe le nombre de tables. |
| `maintain [database]` | Lance `mysqlcheck` et purge les journaux binaires. `--optimize` optimise aussi les tables. `--all` couvre chaque profil. |
| `purge-binlogs` | Supprime les journaux binaires MySQL de plus de `--days=N` jours. Par défaut : 7. |
| `collation` | Convertit les tables et les colonnes vers la collation `utf8mb4`. |

Les profils sont `intelis` (par défaut) et `interfacing`. `interfacing` n'existe
que lorsque l'interfaçage est activé. `--all` est le seul moyen d'agir sur les
deux. Un nom de profil donné là où un nom de base de données est attendu est lu
comme un nom de base de données.

Les sauvegardes sont enregistrées dans `backups/db/` sous les noms
`vlsm-YYYYMMDD-HHMMSS-<32 characters>.sql.zst.gpg` et
`interfacing-YYYYMMDD-HHMMSS-<32 characters>.sql.zst.gpg`. Quand le chiffrement
des sauvegardes avec une clé détenue par le STS est activé, le nom n'a pas de
partie aléatoire et la clé vient du STS.

```bash
cd /var/www/intelis
sudo -u www-data php vendor/bin/db-tools backup --all
sudo -u www-data php vendor/bin/db-tools size
sudo -u www-data php vendor/bin/db-tools clean --days=30
```
