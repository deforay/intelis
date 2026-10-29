---
description: Diagnostiquer et réparer un serveur MySQL qui ne démarre pas, avec le doctor de la base de données d'InteLIS ou à la main.
audience: [system-admin]
module: [all]
type: how-to
platform: ubuntu
reviewed: 2026-10-29
reviewed_against: 5.8.5
---
# MySQL ne démarre pas

MySQL refuse de démarrer. InteLIS ne peut donc pas s'ouvrir et rien ne peut
être sauvegardé.

1. Ouvrir un terminal sur la machine InteLIS et lancer le doctor :

    ```bash
    sudo intelis doctor
    ```

    Il vérifie d'abord le serveur web, puis la base de données. Pour ne
    vérifier que la base de données, lancer plutôt `sudo intelis fix-database`.

    ??? info "Si `intelis` n'est pas reconnu, ou si `doctor` n'existe pas"

        L'installation est ancienne. Lancer le même outil directement depuis
        Internet :

        ```bash
        sudo bash -c "$(wget -qO- https://raw.githubusercontent.com/deforay/intelis/master/scripts/intelis-doctor.sh)"
        ```

        Saisir la commande exactement comme indiqué. Rediriger le
        téléchargement vers `bash` donne au script son propre texte comme
        réponses à ses questions.

2. Répondre `y` à chaque question `Do this now?` sous **Repairs**. Quand
   aucune des causes connues ne correspond, le doctor propose
   `Start the database again.` Ce démarrage est la réparation habituelle après
   un plantage ou une coupure de courant.
3. Attendre `InteLIS is working again.`

    ??? failure "S'il affiche `The database still will not start.`"

        Le doctor affiche les dernières erreurs écrites par MySQL, sous
        **Last errors the database wrote**.

        Le doctor place `mysql-report.txt` sur le **Desktop** (**Bureau** sur
        un poste en français), ou dans le dossier personnel quand il n'y a pas
        de Desktop. Envoyer ce fichier en demandant de l'aide. Il contient le
        journal du service, le journal d'erreurs de la base de données, la
        mémoire, le disque et les réglages. Les mots de passe en sont retirés.

!!! warning "Ne pas réinstaller ni reformater la machine"

    Les données sont presque toujours intactes. Une réinstallation est la
    seule étape qui peut transformer une panne réparable en perte de données.

## Causes vérifiées par le doctor

Le doctor vérifie chacune des causes ci-dessous. En ouvrir une pour la réparer
à la main.

??? info "Le disque est plein"

    `df -h /` indique moins de 500 Mo libres. MySQL s'arrête quand il ne peut
    pas écrire. Le doctor propose lui-même de réduire les journaux système. Il
    signale aussi un disque qui a encore de l'espace mais plus de place pour
    de nouveaux fichiers : `df -i /` indique 95 % ou plus.

    1. Réduire les journaux système :

        ```bash
        sudo journalctl --vacuum-size=100M
        ```

    2. Démarrer MySQL :

        ```bash
        sudo systemctl start mysql
        ```

    Les anciennes sauvegardes de la base de données dans
    `/var/www/intelis/backups/db` sont en général les plus gros fichiers du
    disque. Elles se terminent par `.sql.zst.gpg`, `.sql.zst` ou `.sql.gz`.
    Les copier sur une clé USB avant d'en supprimer, et garder la plus récente
    sur la machine.

??? info "La machine a manqué de mémoire"

    Le système a arrêté MySQL pour libérer de la mémoire. `sudo journalctl -k`
    mentionne `Out of memory` ou `oom-kill`. Le doctor vérifie aussi
    `innodb_buffer_pool_size`. Si ce réglage dépasse 70 % de la mémoire de la
    machine, le doctor propose de le désactiver, ce qui ramène MySQL à sa
    taille par défaut.

    Fermer les autres programmes, puis démarrer MySQL :

    ```bash
    sudo systemctl start mysql
    ```

    Si le problème se reproduit, la machine a besoin de plus de mémoire.

??? info "Un fichier de configuration a été modifié"

    MySQL rejette un réglage dans un fichier sous `/etc/mysql`. Le doctor cite
    la ligne rejetée. S'il existe une copie antérieure de la configuration qui
    fonctionnait, le doctor propose de la remettre en place. Sinon, annuler la
    modification à la main, puis démarrer MySQL :

    ```bash
    sudo systemctl start mysql
    ```

??? info "Le dossier des données a le mauvais propriétaire"

    Cela fait en général suite à une copie ou à une restauration lancée en
    tant qu'administrateur. MySQL ne peut pas ouvrir les fichiers dont il
    n'est pas propriétaire. `ls -ld /var/lib/mysql` affiche un propriétaire
    autre que `mysql`.

    1. Rendre les fichiers à MySQL :

        ```bash
        sudo chown -R mysql:mysql /var/lib/mysql
        ```

    2. Démarrer MySQL :

        ```bash
        sudo systemctl start mysql
        ```

??? info "Les fichiers de données sont endommagés"

    Cela fait en général suite à une coupure de courant ou à un arrêt
    brutal. Le journal d'erreurs mentionne une corruption, une somme de
    contrôle (checksum) ou une récupération InnoDB. Le doctor ne répare pas ce
    problème, car un mauvais réglage rend les dégâts définitifs.

    Envoyer `mysql-report.txt` et demander de l'aide. Ne pas modifier
    `innodb_force_recovery`. Si `/var/www/intelis/backups/db` contient une
    sauvegarde récente, la restaurer est en général plus rapide et plus sûr
    que réparer les fichiers. Voir
    [Restaurer depuis une sauvegarde](restoring-from-backup.md).

Le doctor vérifie aussi un service MySQL bloqué, un dossier de connexion
manquant, des réglages placés sous le mauvais en-tête dans un fichier de
configuration MySQL (le doctor les désactive), un autre programme sur le port
de MySQL, et un mauvais mot de passe de base de données enregistré dans
InteLIS.

## Dernier recours : copier le disque

Si MySQL ne peut pas du tout démarrer, les données peuvent encore être
récupérées sur une autre machine. Éteindre la machine et copier ces dossiers
depuis son disque :

- `/var/lib/mysql` (les fichiers de la base de données)
- `/etc/mysql` (les réglages de MySQL)
- `/var/www/intelis` (l'installation d'InteLIS)

Faire la copie avant que quiconque réinstalle le système d'exploitation.
