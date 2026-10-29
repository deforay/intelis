---
description: Copier les sauvegardes de la base de données et des paramètres d'InteLIS vers Google Drive toutes les 6 heures avec Rclone, et les télécharger sur une machine de remplacement.
audience: [system-admin]
module: [all]
type: how-to
platform: ubuntu
reviewed: 2026-10-29
reviewed_against: 5.8.5
---
# Sauvegarder vers Google Drive avec Rclone sous Ubuntu

Envoyer les sauvegardes d'InteLIS vers Google Drive toutes les 6 heures. Ainsi,
une copie survit à un incendie ou à un vol au laboratoire.

`intelis restore` ne peut pas récupérer de fichiers depuis Google Drive. Les
fichiers se téléchargent à la main, comme indiqué dans
[Récupérer les sauvegardes](#recuperer-les-sauvegardes).

!!! warning "Garder le compte Google privé"
    Les sauvegardes des paramètres envoyées contiennent le mot de passe de la
    base de données d'InteLIS. Utiliser un compte Google réservé à cet usage.
    Activer la validation en deux étapes sur ce compte. Ne partager le dossier
    de sauvegarde avec personne.

## Configurer l'envoi

1. Ouvrir un terminal sur la machine InteLIS et installer Rclone :

    ```bash
    sudo apt install rclone
    ```

2. Lancer la configuration de Rclone :

    ```bash
    sudo rclone config
    ```

    Utiliser `sudo`. L'envoi s'exécute sous le compte administrateur, et lit
    les paramètres de Rclone enregistrés par cette étape.

3. Répondre aux questions :

    | Question | Réponse |
    | --- | --- |
    | `n/s/q>` ou `e/n/d/r/c/s/q>` | `n` (nouveau remote) |
    | `name>` | `gdrive` |
    | `Storage>` | `drive` |
    | `client_id>` | Appuyer sur Entrée. |
    | `client_secret>` | Appuyer sur Entrée. |
    | `scope>` | `1` (accès complet à tous les fichiers) |
    | `service_account_file>` | Appuyer sur Entrée. |
    | Edit advanced config? | `n` |
    | Use web browser to automatically authenticate rclone with remote? | `y` |
    | Configure this as a Shared Drive (Team Drive)? | `n` |
    | Keep this "gdrive" remote? | `y` |
    | `e/n/d/r/c/s/q>` | `q` (quitter) |

    Après la question sur le navigateur, une fenêtre de navigateur s'ouvre. Se
    connecter au compte Google réservé aux sauvegardes, et autoriser l'accès à
    Rclone.

    ??? failure "Si aucune fenêtre de navigateur ne s'ouvre"

        Rclone affiche un lien qui commence par `http://127.0.0.1:53682/`.
        Ouvrir ce lien dans un navigateur sur la même machine.

4. Choisir un nom de dossier pour cette machine sur Google Drive, par exemple
   `intelis/centrallab-01`. Nommer la machine en plus du laboratoire. Deux
   machines qui envoient vers le même dossier écrasent les sauvegardes l'une de
   l'autre.
5. Vérifier que le dossier est vide ou nouveau :

    ```bash
    sudo rclone lsf gdrive:intelis/centrallab-01
    ```

    La commande ne doit rien afficher. Si elle liste des fichiers, une autre
    machine utilise ce nom. Choisir un autre nom à l'étape 4.

6. Créer le script d'envoi :

    ```bash
    sudo nano /usr/local/bin/intelis-gdrive-backup.sh
    ```

7. Coller ceci dans l'éditeur. Remplacer `intelis/centrallab-01` par le nom de
   dossier de l'étape 4. Sur les installations plus anciennes, remplacer
   `/var/www/intelis` par `/var/www/vlsm`.

    ```bash
    #!/bin/bash
    set -Eeuo pipefail

    source_dir="/var/www/intelis"
    remote_name="gdrive"
    remote_dir="intelis/centrallab-01"

    # Take a fresh database and settings backup first.
    # The full path is needed because cron runs with a minimal PATH.
    /usr/local/bin/intelis backup

    # copy, not sync: sync deletes files on Google Drive that are gone from
    # this machine, including older backups.
    rclone copy "$source_dir/backups" "$remote_name:$remote_dir/backups"
    ```

8. Appuyer sur **Ctrl+X**, puis **Y**, puis Entrée, pour enregistrer et fermer.
9. Rendre le script exécutable :

    ```bash
    sudo chmod +x /usr/local/bin/intelis-gdrive-backup.sh
    ```

10. L'exécuter une fois :

    ```bash
    sudo /usr/local/bin/intelis-gdrive-backup.sh
    ```

    ??? info "S'il demande `Set up off-machine backups now?`"

        Appuyer sur Entrée pour répondre No. L'envoi vers Google Drive
        continue. Pour envoyer aussi les sauvegardes vers une autre machine ou
        un disque du réseau, voir
        [Configurer les sauvegardes hors de la machine](setting-up-off-machine-backups.md).

    ??? failure "S'il s'arrête avec une erreur après `Step 2 of 2`"

        Cette machine envoie aussi ses sauvegardes vers une autre machine ou un
        autre disque, et cette sauvegarde a échoué. Rien n'a été envoyé vers
        Google Drive. Exécuter `intelis backup status` pour en connaître la
        cause, la corriger, puis refaire l'étape 10.

11. Vérifier qu'une sauvegarde de la base de données du jour est arrivée :

    ```bash
    sudo rclone lsl gdrive:intelis/centrallab-01/backups/db
    ```

    La liste doit contenir un fichier qui commence par `vlsm-` et porte la date
    du jour (UTC).

### L'exécuter automatiquement

12. Ajouter l'envoi au planning, en gardant toute autre tâche planifiée de la
    machine :

    ```bash
    sudo crontab -l 2>/dev/null | grep -Fv intelis-gdrive-backup.sh > /tmp/intelis-cron
    printf '@reboot /usr/local/bin/intelis-gdrive-backup.sh\n0 */6 * * * /usr/local/bin/intelis-gdrive-backup.sh\n' >> /tmp/intelis-cron
    sudo crontab /tmp/intelis-cron
    rm -f /tmp/intelis-cron
    ```

13. Vérifier le planning :

    ```bash
    sudo crontab -l
    ```

    La liste doit contenir ces deux lignes, sous les lignes qui s'y trouvaient
    déjà :

    ```text
    @reboot /usr/local/bin/intelis-gdrive-backup.sh
    0 */6 * * * /usr/local/bin/intelis-gdrive-backup.sh
    ```

    L'envoi s'exécute désormais toutes les 6 heures et après chaque
    redémarrage.

## Récupérer les sauvegardes

Utiliser cette section quand la machine InteLIS est hors service et qu'une
nouvelle machine est en cours d'installation.

1. Sur la nouvelle machine, refaire les étapes 1 à 3 de
   [Configurer l'envoi](#configurer-lenvoi).
2. Télécharger tout le dossier `backups`, avec le nom de dossier de l'étape 4 :

    ```bash
    sudo rclone copy gdrive:intelis/centrallab-01/backups ~/backups
    ```

    Télécharger tout le dossier, pas seulement la sauvegarde de la base de
    données la plus récente. Les sauvegardes de la base de données sont
    chiffrées, et la clé se lit dans les sauvegardes des paramètres de
    `backups/config`.

    ??? info "Si le téléchargement est trop volumineux"

        Télécharger seulement les 14 derniers jours :

        ```bash
        sudo rclone copy --max-age 14d gdrive:intelis/centrallab-01/backups ~/backups
        ```

3. Vérifier que le téléchargement contient les deux dossiers `db` et `config` :

    ```bash
    ls ~/backups
    ```

4. Suivre l'onglet **L'ancienne machine est hors service** de
   [Migrer d'une machine Ubuntu à une autre](migrating-ubuntu-machines.md).
   Utiliser le dossier `backups` du dossier personnel à la place de celui de la
   clé USB. Dans l'application **Files** (**Fichiers** sur un poste en
   français), il se trouve sous **Home** (**Dossier personnel** sur un poste en
   français).
