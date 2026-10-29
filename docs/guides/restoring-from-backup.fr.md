---
description: Restaurer la base de données d'InteLIS, une sauvegarde datée plus ancienne ou les fichiers téléversés sur une machine où InteLIS fonctionne déjà.
audience: [system-admin]
module: [all]
type: how-to
platform: ubuntu
reviewed: 2026-10-29
reviewed_against: 5.8.5
---
# Restaurer depuis une sauvegarde

Remettre la base de données en place à partir d'une sauvegarde, sur une machine
où InteLIS fonctionne déjà. Une restauration depuis la sauvegarde hors de la
machine remet aussi en place les paramètres du laboratoire, les signatures, les
modèles de rapport et les logos. Les fichiers téléversés et les pièces jointes
peuvent aussi être récupérés.

La restauration remplace tout le contenu de la base de données. Les
enregistrements saisis après la création de la sauvegarde sont perdus.

!!! info "Jusqu'où remontent les sauvegardes"

    Les sauvegardes les plus récentes de la base de données, environ les 2
    derniers jours, se trouvent sur cette machine et dans le miroir hors de la
    machine. La sauvegarde hors de la machine conserve aussi une sauvegarde de
    la base de données par jour pendant 7 jours et une par semaine pendant 4
    semaines de plus, soit environ 5 semaines au total. Pour restaurer l'une de
    celles-ci, suivre l'onglet **Date plus ancienne**.

**Choisir la situation qui correspond, puis suivre ses étapes de haut en bas.**

=== "Sauvegarde sur cette machine"

    Utiliser cet onglet quand la sauvegarde de la base de données se trouve
    encore sur cette machine, par exemple pour annuler la suppression
    accidentelle d'enregistrements.

    ### Restaurer la base de données

    1. Ouvrir un terminal et aller dans le dossier InteLIS :

        ```bash
        cd /var/www/intelis
        ```

        Sur les installations plus anciennes, taper `cd /var/www/vlsm` à la place.

    2. Lancer la restauration :

        ```bash
        sudo -u www-data php vendor/bin/db-tools restore
        ```

    3. La liste des sauvegardes présentes sur cette machine s'affiche. Choisir
       le fichier le plus récent commençant par `vlsm-` qui a été créé avant le
       problème. S'y déplacer avec les touches fléchées et appuyer sur Entrée.
       Si la liste est numérotée, taper son numéro et appuyer sur Entrée.

        Le nom donne la date et l'heure de la sauvegarde. Par exemple,
        `vlsm-20260903-100002-…` a été créé le 3 septembre 2026 à 10:00.

        Ne pas choisir un fichier commençant par `interfacing-` ou `pre-restore-`.

        ??? failure "S'il indique `No backup files found`"

            Il n'y a aucune sauvegarde de la base de données sur cette machine.
            Suivre l'onglet **Seulement la base de données** pour en récupérer
            une à l'endroit où les sauvegardes sont envoyées.

    4. Attendre `Restore completed to vlsm`. La restauration enregistre d'abord
       une copie de sécurité de la base de données actuelle, dans un fichier
       commençant par `pre-restore-vlsm-`.

        ??? failure "S'il se termine par `Restore failed`"

            La base de données est peut-être vide maintenant. Remettre en place
            la copie de sécurité. Refaire l'étape 2 et choisir le fichier le
            plus récent commençant par `pre-restore-vlsm-`. Contacter ensuite
            le support avec le message affiché.

    5. Appliquer les mises à jour de la base de données pour cette version
       d'InteLIS :

        ```bash
        intelis migrate
        ```

    ### Vérifier le laboratoire

    6. Ouvrir InteLIS dans le navigateur.
    7. Se connecter avec un compte administrateur qui existait au moment de la
       sauvegarde.
    8. Vérifier les paramètres du laboratoire sous **ADMIN → Paramètres → Configuration générale**.
    9. Ouvrir une demande saisie peu avant la sauvegarde, et vérifier que ses
       résultats sont présents.

=== "Seulement la base de données"

    Utiliser cet onglet quand les sauvegardes sont envoyées vers une autre
    machine Linux, un dossier partagé Windows ou une clé USB, et que seules
    les données sont à récupérer.

    ### Récupérer la sauvegarde

    1. Ouvrir un terminal et aller dans le dossier InteLIS :

        ```bash
        cd /var/www/intelis
        ```

        Sur les installations plus anciennes, taper `cd /var/www/vlsm` à la place.

    2. Lancer la restauration :

        ```bash
        intelis restore
        ```

        La commande vérifie qu'elle dispose du script de restauration actuel
        avant de le lancer. Si la machine est hors ligne, elle utilise le
        script installé.

    3. À **Fetch the backup from there?** (récupérer la sauvegarde à cet
       endroit), choisir **Yes**. Le script utilise les paramètres de
       sauvegarde enregistrés sur cette machine.

        ??? info "S'il demande **Where is the backup stored?** à la place"

            Cette machine n'a aucun paramètre de sauvegarde enregistré. Choisir
            l'endroit où se trouvent les sauvegardes, puis répondre aux
            questions correspondantes :

            | Choix | Questions |
            | --- | --- |
            | **On another Linux machine** | Le nom d'utilisateur, le nom d'hôte ou l'adresse IP, et le port SSH (appuyer sur Entrée pour `22`). Saisir ensuite le mot de passe de cet utilisateur quand il est demandé. |
            | **In a shared folder on a Windows machine** | Le nom d'hôte ou l'adresse IP, le nom du dossier partagé, le nom d'utilisateur Windows et son mot de passe. |
            | **On a USB or external drive plugged into this machine** | Choisir le disque dans la liste. Le script trouve son dossier `backups`, puis demande quel laboratoire restaurer. Si la sauvegarde se trouve ailleurs, choisir **Browse folders inside a drive**. Ouvrir les dossiers jusqu'à la sauvegarde, puis choisir **Use this folder**. |

        ??? failure "S'il indique `Could not connect`"

            Vérifier que la machine ou le disque est allumé et connecté au
            réseau, et que les informations sont exactes. Choisir **Yes** à
            **Try different details?** (ou **Try again?**) et les saisir de
            nouveau.

    4. À **Which lab should be restored?** (quel laboratoire restaurer),
       choisir ce laboratoire. Chaque ligne commence par le nom de
       laboratoire donné lors de la configuration des sauvegardes, et affiche
       la sauvegarde la plus récente de la base de données.

        ??? failure "S'il indique `There are no backups in …`"

            Le script a atteint un emplacement qui ne contient aucune
            sauvegarde InteLIS. Sur la machine qui envoie les sauvegardes,
            lancer `intelis backup status`. La ligne `Backing up to` indique
            où elles vont.

    5. À **What should be copied back?** (que faut-il recopier), choisir
       **Just the database backups**.
    6. À **Where should the restore folder be created?** (où créer le dossier
       de restauration), appuyer sur Entrée pour utiliser
       `/var/intelis-restore`. Noter le nouveau dossier de restauration
       affiché ensuite. Il ressemble à ceci :

        ```text
        /var/intelis-restore/centrallab-12345678-20260928-120000.ABCDEF
        ```

        Chaque restauration utilise un nouveau dossier. Les fichiers récupérés
        par une restauration précédente ne peuvent pas être sélectionnés par
        erreur.

    7. Attendre la fin de la copie. Le script vérifie ensuite chaque
       sauvegarde de la base de données. Les fichiers signalés comme chiffrés
       ne sont pas vérifiés. Ils sont ouverts pendant la restauration.

        ??? failure "Si un fichier est signalé comme endommagé"

            Vérifier qu'une autre sauvegarde est signalée comme lisible. À la
            confirmation, taper le nom de la sauvegarde. Le script ignore les
            fichiers endommagés et restaure la sauvegarde lisible la plus
            récente.

    ### Restaurer la base de données

    8. Vérifier le fichier de sauvegarde et l'installation cible affichés. À
       **Type … to confirm that this is the backup to restore** (taper … pour
       confirmer la sauvegarde à restaurer), taper le nom du laboratoire
       exactement comme il est affiché.
    9. Attendre `Database restored`. Le script enregistre d'abord une copie de
       sécurité de la base de données actuelle. Il restaure ensuite la
       sauvegarde la plus récente commençant par `vlsm-`, puis applique les
       mises à jour de la base de données.

        ??? failure "S'il indique `The restore did not finish`"

            Si la ligne suivante indique `No safety copy was taken`, la base de
            données n'a pas été modifiée. Contacter le support avec le message
            affiché.

            Sinon, la base de données est peut-être vide maintenant. Remettre
            en place la copie de sécurité. Le script affiche la commande à
            utiliser. Remplacer `<pre-restore-file>` par le nom du fichier
            commençant par `pre-restore-vlsm-` dans
            `/var/www/intelis/backups/db` (`/var/www/vlsm/backups/db` sur les
            installations plus anciennes). Lancer la commande, puis contacter
            le support avec le message affiché.

        ??? failure "S'il indique `The database is restored, but its migrations did not finish`"

            Les données sont revenues. Appliquer les mises à jour à la main :

            ```bash
            intelis migrate
            ```

    10. Attendre `Signatures, report templates and logos restored`. Le script
        remet en place les paramètres du laboratoire depuis la sauvegarde des
        paramètres la plus récente, par exemple l'adresse du STS et les
        modules activés. Il conserve le mot de passe de la base de données de
        cette machine. Il remet ensuite en place les signatures, les modèles
        de rapport et les logos. Les paramètres que cette machine avait sont
        enregistrés dans `configs/config.production.php.pre-restore`.

        ??? warning "S'il indique `This machine keeps its own settings`"

            La sauvegarde des paramètres n'a pas pu être utilisée. Les
            paramètres du laboratoire n'ont donc pas changé. Vérifier l'adresse
            du STS et les modules activés dans **Configuration du système**.
            Voir [Utiliser l'espace System Admin](../user-guides/admin-system-administration.md).

        ??? warning "S'il indique `holds no signatures, report templates or logos` ou `did not bring back`"

            La sauvegarde des paramètres a été créée par une version plus
            ancienne d'InteLIS, ou elle est absente. Suivre l'onglet
            **Base de données et fichiers téléversés** pour les recopier
            depuis la sauvegarde.

    ### Vérifier le laboratoire

    11. Ouvrir InteLIS dans le navigateur.
    12. Se connecter avec un compte administrateur qui existait au moment de la
        sauvegarde.
    13. Vérifier les paramètres du laboratoire sous **ADMIN → Paramètres → Configuration générale**.
    14. Ouvrir une demande saisie peu avant la sauvegarde, et vérifier que ses
        résultats sont présents.
    15. Ouvrir le PDF de résultats de cette demande, et vérifier que ses
        signatures et son logo apparaissent.
    16. Si le laboratoire utilise l'outil d'interface, restaurer aussi sa base
        de données. Taper cette commande, suivie d'une espace. Ne pas encore
        appuyer sur Entrée :

        ```bash
        sudo -u www-data php vendor/bin/db-tools restore --profile=interfacing
        ```

        Ouvrir l'application **Files** (**Fichiers** sur un poste en français)
        et appuyer sur **Ctrl+L**. Taper le dossier de restauration de l'étape
        6, suivi de `/db`, et appuyer sur Entrée. Faire glisser le fichier le
        plus récent commençant par `interfacing-` sur la fenêtre du terminal,
        et appuyer sur Entrée.

        Toujours garder `--profile=interfacing` dans cette commande. Sans lui,
        le fichier est restauré par-dessus la base de données principale.

    17. Quand le laboratoire fonctionne, lancer exactement la commande de
        nettoyage affichée par le script. Elle supprime uniquement cette copie
        récupérée, qui contient le mot de passe de la base de données.

=== "Date plus ancienne"

    Utiliser cet onglet pour remettre la base de données dans l'état d'un jour
    précédent, par exemple quand une erreur est remarquée plus de 2 jours
    après. La sauvegarde hors de la machine conserve une sauvegarde de la base
    de données par jour pendant 7 jours et une par semaine pendant 4 semaines
    de plus.

    ### Récupérer la sauvegarde

    1. Ouvrir un terminal et aller dans le dossier InteLIS :

        ```bash
        cd /var/www/intelis
        ```

        Sur les installations plus anciennes, taper `cd /var/www/vlsm` à la place.

    2. Lancer la restauration :

        ```bash
        intelis restore
        ```

        La commande vérifie qu'elle dispose du script de restauration actuel
        avant de le lancer. Si la machine est hors ligne, elle utilise le
        script installé.

    3. À **Fetch the backup from there?**, choisir **Yes**. Le script utilise
       les paramètres de sauvegarde enregistrés sur cette machine.

        ??? info "S'il demande **Where is the backup stored?** à la place"

            Cette machine n'a aucun paramètre de sauvegarde enregistré. Choisir
            l'endroit où se trouvent les sauvegardes, puis répondre aux
            questions correspondantes :

            | Choix | Questions |
            | --- | --- |
            | **On another Linux machine** | Le nom d'utilisateur, le nom d'hôte ou l'adresse IP, et le port SSH (appuyer sur Entrée pour `22`). Saisir ensuite le mot de passe de cet utilisateur quand il est demandé. |
            | **In a shared folder on a Windows machine** | Le nom d'hôte ou l'adresse IP, le nom du dossier partagé, le nom d'utilisateur Windows et son mot de passe. |
            | **On a USB or external drive plugged into this machine** | Choisir le disque dans la liste. Le script trouve son dossier `backups`, puis demande quel laboratoire restaurer. Si la sauvegarde se trouve ailleurs, choisir **Browse folders inside a drive**. Ouvrir les dossiers jusqu'à la sauvegarde, puis choisir **Use this folder**. |

        ??? failure "S'il indique `Could not connect`"

            Vérifier que la machine ou le disque est allumé et connecté au
            réseau, et que les informations sont exactes. Choisir **Yes** à
            **Try different details?** (ou **Try again?**) et les saisir de
            nouveau.

    4. À **Which lab should be restored?**, choisir ce laboratoire. Chaque
       ligne commence par le nom de laboratoire donné lors de la configuration
       des sauvegardes, et affiche la sauvegarde la plus récente de la base de
       données.

        ??? failure "S'il indique `There are no backups in …`"

            Le script a atteint un emplacement qui ne contient aucune
            sauvegarde InteLIS. Sur la machine qui envoie les sauvegardes,
            lancer `intelis backup status`. La ligne `Backing up to` indique
            où elles vont.

    5. À **What should be copied back?**, choisir
       **An older database backup (choose a date)**.
    6. À **Which date should be restored?** (quelle date restaurer), choisir
       le jour. Chaque ligne affiche la date et l'heure de création de la
       sauvegarde.

        ??? failure "S'il indique `There is no dated history in this backup yet`"

            L'historique commence avec la première sauvegarde créée après la
            mise à jour d'InteLIS. Jusque-là, seules les sauvegardes les plus
            récentes existent. Suivre plutôt l'onglet
            **Seulement la base de données**.

    7. À **Where should the restore folder be created?**, appuyer sur Entrée
       pour utiliser `/var/intelis-restore`. Noter le nouveau dossier de
       restauration affiché ensuite. Il ressemble à ceci :

        ```text
        /var/intelis-restore/centrallab-12345678-20260928-120000.ABCDEF
        ```

        Chaque restauration utilise un nouveau dossier. Les fichiers récupérés
        par une restauration précédente ne peuvent pas être sélectionnés par
        erreur.

    8. Attendre la fin de la copie. Le script vérifie ensuite la sauvegarde.
       Un fichier signalé comme chiffré n'est pas vérifié. Il est ouvert
       pendant la restauration.

        ??? failure "Si le fichier est signalé comme endommagé"

            Ne pas taper le nom de la sauvegarde à la confirmation. Relancer
            `intelis restore` et choisir une autre date.

    ### Restaurer la base de données

    9. Vérifier le fichier de sauvegarde et l'installation cible affichés. À
       **Type … to confirm that this is the backup to restore**, taper le nom
       du laboratoire exactement comme il est affiché.
    10. Attendre `Database restored`. Le script enregistre d'abord une copie
        de sécurité de la base de données actuelle. Il restaure ensuite la
        sauvegarde choisie, puis applique les mises à jour de la base de
        données.

        ??? failure "S'il indique `The restore did not finish`"

            Si la ligne suivante indique `No safety copy was taken`, la base de
            données n'a pas été modifiée. Contacter le support avec le message
            affiché.

            Sinon, la base de données est peut-être vide maintenant. Remettre
            en place la copie de sécurité. Le script affiche la commande à
            utiliser. Remplacer `<pre-restore-file>` par le nom du fichier
            commençant par `pre-restore-vlsm-` dans
            `/var/www/intelis/backups/db` (`/var/www/vlsm/backups/db` sur les
            installations plus anciennes). Lancer la commande, puis contacter
            le support avec le message affiché.

        ??? failure "S'il indique `The database is restored, but its migrations did not finish`"

            Les données sont revenues. Appliquer les mises à jour à la main :

            ```bash
            intelis migrate
            ```

    11. Attendre `Signatures, report templates and logos restored`. Le script
        remet en place les paramètres du laboratoire depuis la sauvegarde des
        paramètres la plus récente, par exemple l'adresse du STS et les
        modules activés. Il conserve le mot de passe de la base de données de
        cette machine. Il remet ensuite en place les signatures, les modèles
        de rapport et les logos. Les paramètres que cette machine avait sont
        enregistrés dans `configs/config.production.php.pre-restore`.

        ??? warning "S'il indique `This machine keeps its own settings`"

            La sauvegarde des paramètres n'a pas pu être utilisée. Les
            paramètres du laboratoire n'ont donc pas changé. Vérifier l'adresse
            du STS et les modules activés dans **Configuration du système**.
            Voir [Utiliser l'espace System Admin](../user-guides/admin-system-administration.md).

        ??? warning "S'il indique `holds no signatures, report templates or logos` ou `did not bring back`"

            La sauvegarde des paramètres a été créée par une version plus
            ancienne d'InteLIS, ou elle est absente. Suivre l'onglet
            **Base de données et fichiers téléversés** pour les recopier
            depuis la sauvegarde.

    ### Vérifier le laboratoire

    12. Ouvrir InteLIS dans le navigateur.
    13. Se connecter avec un compte administrateur qui existait au moment de la
        sauvegarde.
    14. Vérifier les paramètres du laboratoire sous **ADMIN → Paramètres → Configuration générale**.
    15. Ouvrir une demande saisie peu avant la sauvegarde, et vérifier que ses
        résultats sont présents.
    16. Ouvrir le PDF de résultats de cette demande, et vérifier que ses
        signatures et son logo apparaissent.
    17. Si le laboratoire utilise l'outil d'interface, sa base de données reste
        telle quelle. Pour la remettre aussi au même jour, contacter le
        support.

    18. Quand le laboratoire fonctionne, lancer exactement la commande de
        nettoyage affichée par le script. Elle supprime uniquement cette copie
        récupérée, qui contient le mot de passe de la base de données.

=== "Base de données et fichiers téléversés"

    Utiliser cet onglet quand les sauvegardes sont envoyées vers une autre
    machine Linux, un dossier partagé Windows ou une clé USB, et que des
    fichiers téléversés et des pièces jointes manquent en plus des données.

    ### Récupérer la sauvegarde

    1. Ouvrir un terminal et aller dans le dossier InteLIS :

        ```bash
        cd /var/www/intelis
        ```

        Sur les installations plus anciennes, taper `cd /var/www/vlsm` à la place.

    2. Lancer la restauration :

        ```bash
        intelis restore
        ```

        La commande vérifie qu'elle dispose du script de restauration actuel
        avant de le lancer. Si la machine est hors ligne, elle utilise le
        script installé.

    3. À **Fetch the backup from there?**, choisir **Yes**. Le script utilise
       les paramètres de sauvegarde enregistrés sur cette machine.

        ??? info "S'il demande **Where is the backup stored?** à la place"

            Cette machine n'a aucun paramètre de sauvegarde enregistré. Choisir
            l'endroit où se trouvent les sauvegardes, puis répondre aux
            questions correspondantes :

            | Choix | Questions |
            | --- | --- |
            | **On another Linux machine** | Le nom d'utilisateur, le nom d'hôte ou l'adresse IP, et le port SSH (appuyer sur Entrée pour `22`). Saisir ensuite le mot de passe de cet utilisateur quand il est demandé. |
            | **In a shared folder on a Windows machine** | Le nom d'hôte ou l'adresse IP, le nom du dossier partagé, le nom d'utilisateur Windows et son mot de passe. |
            | **On a USB or external drive plugged into this machine** | Choisir le disque dans la liste. Le script trouve son dossier `backups`, puis demande quel laboratoire restaurer. Si la sauvegarde se trouve ailleurs, choisir **Browse folders inside a drive**. Ouvrir les dossiers jusqu'à la sauvegarde, puis choisir **Use this folder**. |

        ??? failure "S'il indique `Could not connect`"

            Vérifier que la machine ou le disque est allumé et connecté au
            réseau, et que les informations sont exactes. Choisir **Yes** à
            **Try different details?** (ou **Try again?**) et les saisir de
            nouveau.

    4. À **Which lab should be restored?**, choisir ce laboratoire. Chaque
       ligne commence par le nom de laboratoire donné lors de la configuration
       des sauvegardes, et affiche la sauvegarde la plus récente de la base de
       données.

        ??? failure "S'il indique `There are no backups in …`"

            Le script a atteint un emplacement qui ne contient aucune
            sauvegarde InteLIS. Sur la machine qui envoie les sauvegardes,
            lancer `intelis backup status`. La ligne `Backing up to` indique
            où elles vont.

    5. À **What should be copied back?**, choisir
       **Everything, including uploaded files and attachments**.
    6. À **Where should the restore folder be created?**, appuyer sur Entrée
       pour utiliser `/var/intelis-restore`. Noter le nouveau dossier de
       restauration affiché ensuite. Il ressemble à ceci :

        ```text
        /var/intelis-restore/centrallab-12345678-20260928-120000.ABCDEF
        ```

        Chaque restauration utilise un nouveau dossier. Les fichiers récupérés
        par une restauration précédente ne peuvent pas être sélectionnés par
        erreur.

    7. Attendre la fin de la copie. Elle copie tout le dossier InteLIS et peut
       durer des heures sur un réseau. Le script vérifie ensuite chaque
       sauvegarde de la base de données. Les fichiers signalés comme chiffrés
       ne sont pas vérifiés. Ils sont ouverts pendant la restauration.

        ??? failure "Si un fichier est signalé comme endommagé"

            Vérifier qu'une autre sauvegarde est signalée comme lisible. À la
            confirmation, taper le nom de la sauvegarde. Le script ignore les
            fichiers endommagés et restaure la sauvegarde lisible la plus
            récente.

    ### Restaurer la base de données

    8. Vérifier le fichier de sauvegarde et l'installation cible affichés. À
       **Type … to confirm that this is the backup to restore**, taper le nom
       du laboratoire exactement comme il est affiché.
    9. Attendre `Database restored`. Le script enregistre d'abord une copie de
       sécurité de la base de données actuelle. Il restaure ensuite la
       sauvegarde la plus récente commençant par `vlsm-`, puis applique les
       mises à jour de la base de données.

        ??? failure "S'il indique `The restore did not finish`"

            Si la ligne suivante indique `No safety copy was taken`, la base de
            données n'a pas été modifiée. Contacter le support avec le message
            affiché.

            Sinon, la base de données est peut-être vide maintenant. Remettre
            en place la copie de sécurité. Le script affiche la commande à
            utiliser. Remplacer `<pre-restore-file>` par le nom du fichier
            commençant par `pre-restore-vlsm-` dans
            `/var/www/intelis/backups/db` (`/var/www/vlsm/backups/db` sur les
            installations plus anciennes). Lancer la commande, puis contacter
            le support avec le message affiché.

        ??? failure "S'il indique `The database is restored, but its migrations did not finish`"

            Les données sont revenues. Appliquer les mises à jour à la main :

            ```bash
            intelis migrate
            ```

    10. Attendre `Signatures, report templates and logos restored`. Le script
        remet en place les paramètres du laboratoire depuis la sauvegarde des
        paramètres la plus récente, par exemple l'adresse du STS et les
        modules activés. Il conserve le mot de passe de la base de données de
        cette machine. Il remet ensuite en place les signatures, les modèles
        de rapport et les logos. Les paramètres que cette machine avait sont
        enregistrés dans `configs/config.production.php.pre-restore`.

        ??? warning "S'il indique `This machine keeps its own settings`"

            La sauvegarde des paramètres n'a pas pu être utilisée. Les
            paramètres du laboratoire n'ont donc pas changé. Vérifier l'adresse
            du STS et les modules activés dans **Configuration du système**.
            Voir [Utiliser l'espace System Admin](../user-guides/admin-system-administration.md).

        ??? warning "S'il indique `holds no signatures, report templates or logos` ou `did not bring back`"

            La sauvegarde des paramètres a été créée par une version plus
            ancienne d'InteLIS, ou elle est absente. Les étapes suivantes les
            recopient aussi depuis la sauvegarde.

    ### Remettre en place les fichiers téléversés

    11. Enregistrer le dossier de restauration de l'étape 6 dans le terminal.
        Remplacer l'exemple par le chemin affiché par le script :

        ```bash
        restore_dir="/var/intelis-restore/centrallab-12345678-20260928-120000.ABCDEF"
        ```

    12. Vérifier le contenu de la copie :

        ```bash
        ls "$restore_dir/public/uploads"
        ```

    13. Lister ce qui serait copié, sans rien modifier :

        ```bash
        sudo rsync -a --dry-run --itemize-changes "$restore_dir/public/uploads/" /var/www/intelis/public/uploads/
        ```

        Chaque ligne correspond à un fichier absent ou différent sur cette
        machine.

    14. Copier les fichiers :

        ```bash
        sudo rsync -a "$restore_dir/public/uploads/" /var/www/intelis/public/uploads/
        ```

        Sur les installations plus anciennes, taper `/var/www/vlsm/public/uploads/`
        comme dernier chemin. Ne pas ajouter `--delete`. Les fichiers ajoutés
        depuis la sauvegarde seraient supprimés.

        Recopier ensuite la piste d'audit, qui contient l'historique des
        modifications des échantillons plus anciens :

        ```bash
        sudo rsync -a "$restore_dir/var/audit-trail/" /var/www/intelis/var/audit-trail/
        ```

        Sur les installations plus anciennes, taper `/var/www/vlsm/var/audit-trail/`
        comme dernier chemin.

    15. Réparer le propriétaire des fichiers, pour que le serveur web puisse
        lire les fichiers restaurés :

        ```bash
        sudo intelis provision
        ```

    ### Vérifier le laboratoire

    16. Ouvrir InteLIS dans le navigateur.
    17. Se connecter avec un compte administrateur qui existait au moment de la
        sauvegarde.
    18. Vérifier les paramètres du laboratoire sous **ADMIN → Paramètres → Configuration générale**.
    19. Ouvrir une demande saisie peu avant la sauvegarde, et vérifier que ses
        résultats sont présents.
    20. Ouvrir un PDF de résultats, et vérifier que ses signatures et son logo
        apparaissent.
    21. Ouvrir une pièce jointe, et vérifier qu'elle s'affiche.
    22. Si le laboratoire utilise l'outil d'interface, restaurer aussi sa base
        de données. Taper cette commande, suivie d'une espace. Ne pas encore
        appuyer sur Entrée :

        ```bash
        sudo -u www-data php vendor/bin/db-tools restore --profile=interfacing
        ```

        Ouvrir l'application **Files** et appuyer sur **Ctrl+L**. Taper le
        dossier de restauration de l'étape 6, suivi de `/backups/db`, et
        appuyer sur Entrée. Faire glisser le fichier le plus récent commençant
        par `interfacing-` sur la fenêtre du terminal, et appuyer sur Entrée.

        Toujours garder `--profile=interfacing` dans cette commande. Sans lui,
        le fichier est restauré par-dessus la base de données principale.

    23. Quand le laboratoire fonctionne, lancer exactement la commande de
        nettoyage affichée par le script. Elle supprime uniquement cette copie
        récupérée, qui contient le mot de passe de la base de données.
