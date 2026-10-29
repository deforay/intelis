---
description: Envoyer les sauvegardes d'InteLIS toutes les 8 heures vers une autre machine Linux, un dossier partagé Windows ou une clé USB, et vérifier qu'elles fonctionnent.
audience: [system-admin]
module: [all]
type: how-to
platform: ubuntu
reviewed: 2026-10-29
reviewed_against: 5.8.5
---
# Configurer des sauvegardes hors de la machine

Envoyer une copie d'InteLIS vers une autre machine ou un autre disque,
automatiquement, toutes les 8 heures et après chaque redémarrage. La copie
survit si la machine InteLIS tombe en panne.

!!! info "Espace de stockage nécessaire"

    Utiliser une machine de sauvegarde ou un disque d'au moins 1 To. Cela
    couvre un STS et jusqu'à environ 30 machines LIS, avec 5 semaines
    d'historique de la base de données. Pour un réseau plus grand, prévoir
    50 Go par STS et 20 Go par machine LIS, plus 30 Go pour le système
    d'exploitation. Garder un quart du disque libre.

Une autre machine Linux est l'emplacement recommandé pour les sauvegardes. Une
seule machine de sauvegarde reçoit les sauvegardes de tous les laboratoires.
La préparer une fois, puis lancer la configuration sur la machine InteLIS de
chaque laboratoire. N'utiliser une clé USB que s'il n'existe aucune autre
machine.

**Choisir où envoyer les sauvegardes, puis suivre ses étapes de haut en bas.**

=== "Une autre machine Linux"

    Utiliser cette option s'il existe une seconde machine Linux sur le même
    réseau. C'est le choix recommandé.

    ### Préparer la machine de sauvegarde

    Faire cette préparation une seule fois. Chaque laboratoire envoie ensuite
    ses sauvegardes à la même machine.

    1. Sur la machine de sauvegarde, ouvrir un terminal et installer le serveur SSH :

        ```bash
        sudo apt install openssh-server
        ```

    2. Trouver l'adresse IP de la machine de sauvegarde :

        ```bash
        hostname -I
        ```

        Noter la première adresse, par exemple `192.168.1.60`.

    3. Noter le nom d'utilisateur et le mot de passe qui servent à administrer
       la machine de sauvegarde. La configuration de chaque laboratoire s'en
       sert une fois pour se connecter, et crée elle-même le compte de
       sauvegarde. Rien d'autre n'est à créer sur la machine de sauvegarde.

        C'est le compte qui sert à ouvrir une session sur le bureau de la
        machine de sauvegarde, créé à l'installation d'Ubuntu. Pour voir son
        nom d'utilisateur, lancer cette commande dans le même terminal :

        ```bash
        whoami
        ```

        ??? info "Vérifier que le compte peut administrer la machine"

            Lancer :

            ```bash
            groups
            ```

            La liste doit contenir `sudo`. Sinon, se connecter avec le compte
            créé à l'installation d'Ubuntu et utiliser celui-ci.

        ??? info "Ajouter un utilisateur sur Ubuntu"

            Faire cette opération une seule fois, sur la machine de
            sauvegarde. Tous les laboratoires utilisent ensuite le même compte
            `lisbackup`. Elle n'est donc jamais répétée pour les laboratoires
            suivants.

            La configuration crée `lisbackup` d'elle-même quand elle se
            connecte en tant qu'administrateur. N'ajouter ce compte à la main
            que si le mot de passe de l'administrateur ne peut pas être saisi
            pendant la configuration.

            1. Sur la machine de sauvegarde, ouvrir un terminal et lancer :

                ```bash
                sudo adduser lisbackup
                ```

            2. Si un mot de passe est d'abord demandé, saisir le mot de passe
               de connexion à la machine de sauvegarde. Celui-ci sert à `sudo`.
            3. À `New password`, saisir un mot de passe fort pour `lisbackup`
               et appuyer sur Entrée. Le saisir de nouveau à `Retype new password`.
               Rien ne s'affiche à l'écran pendant la saisie. Noter le mot de passe.
            4. À `Full Name`, `Room Number` et aux autres questions, appuyer
               sur Entrée pour les laisser vides.
            5. À `Is the information correct?`, taper `Y` et appuyer sur Entrée.

            Pendant la configuration sur la machine InteLIS de chaque
            laboratoire, choisir **Type the lisbackup password** (saisir le
            mot de passe de lisbackup) à **How should it be given?** (comment
            donner l'accès), puis saisir ce mot de passe.

    ### Configurer la sauvegarde sur la machine InteLIS

    4. Sur la machine InteLIS, ouvrir un terminal et lancer :

        ```bash
        intelis backup setup
        ```

        La commande vérifie qu'elle dispose du script de configuration de
        sauvegarde actuel avant de le lancer. Si la machine est hors ligne,
        elle utilise le script installé.

    5. Répondre aux questions :

        | Question | Réponse |
        | --- | --- |
        | Lab name or lab code | Un nom court pour ce laboratoire, par exemple `kigali-central`. |
        | Where should the backup be sent? | **Another Linux machine on the network (recommended)**. |
        | Address of the backup machine | L'adresse de l'étape 2. |
        | This machine needs access to … How should it be given? | **Log in as the backup machine's administrator (recommended)**. |
        | Administrator account on the backup machine | Le nom d'utilisateur de l'étape 3. |

        ??? failure "S'il indique `Cannot reach …`"

            Vérifier que la machine de sauvegarde est allumée et sur le même
            réseau. Vérifier l'adresse avec `ping 192.168.1.60`, en utilisant
            l'adresse de l'étape 2. Vérifier que le serveur SSH de l'étape 1
            est installé. Choisir ensuite **Yes** à **Try again?** (réessayer).

        ??? info "Si la machine de sauvegarde utilise un autre port SSH ou un autre compte"

            Les inscrire dans l'adresse : `192.168.1.60:2222` pour le port
            2222, ou `backup@192.168.1.60` pour le compte `backup`. Sans eux,
            la configuration utilise le port 22 et le compte `lisbackup`.

    6. Quand un mot de passe est demandé, saisir le mot de passe de l'étape 3
       et appuyer sur Entrée. Il peut être demandé deux fois : une fois pour
       se connecter, et une autre fois pour `sudo`. Le premier laboratoire
       crée le compte `lisbackup` sur la machine de sauvegarde. Chaque
       laboratoire suivant ajoute son propre accès à ce compte. Les
       sauvegardes suivantes ne demandent aucun mot de passe.

        ??? failure "S'il indique `Could not log in as … either`"

            Le nom d'utilisateur ou le mot de passe est faux, ou la machine de
            sauvegarde refuse le mot de passe de ce compte. Vérifier les deux
            sur la machine de sauvegarde. La configuration repose la question
            **How should it be given?**.

        ??? failure "S'il indique `adding the key failed`"

            Le compte s'est connecté mais n'a pas le droit d'utiliser `sudo`.
            Vérifier le compte comme à l'étape 3 : `groups` sur la machine de
            sauvegarde doit afficher `sudo`. Choisir de nouveau **Log in as
            the backup machine's administrator** et saisir le compte créé à
            l'installation d'Ubuntu.

        ??? info "Sans le mot de passe de l'administrateur"

            À **How should it be given?**, choisir une option :

            | Choix | À utiliser quand |
            | --- | --- |
            | **Type the lisbackup password** | `lisbackup` existe déjà sur la machine de sauvegarde avec un mot de passe. Pour le créer, voir **Ajouter un utilisateur sur Ubuntu** à l'étape 3. |
            | **Add it by hand on the backup machine** | Une autre personne administre la machine de sauvegarde. Le script affiche quatre commandes. La première crée le compte `lisbackup` s'il n'existe pas encore. Les envoyer à cette personne. Quand elle les a lancées, choisir **Yes** à **Has it been added? Check now?** (l'accès est-il ajouté, vérifier maintenant). |

        ??? tip "Se connecter à la machine de sauvegarde à la main"

            La configuration ajoute la machine de sauvegarde à
            `/root/.ssh/config`. Ainsi, `sudo ssh lisbackup@192.168.1.60`
            depuis la machine InteLIS utilise la clé de sauvegarde. Utiliser
            l'adresse de l'étape 2.

    7. Attendre la fin de la première sauvegarde. Elle peut durer une heure ou
       plus. Le script se termine par `Backups are set up and the first one completed`.

        [Configuration des sauvegardes vers une autre machine Linux, de `intelis backup setup` jusqu'à la première sauvegarde et à `intelis backup status`. Les longues attentes sont raccourcies.](../assets/casts/backup-setup-linux.cast){ .asciicast }

        ??? failure "S'il se termine par `Setup finished, but the first backup failed`"

            Les paramètres sont enregistrés. Lire l'erreur au-dessus du
            message, corriger la cause, puis relancer la sauvegarde :

            ```bash
            intelis backup
            ```

    ### Vérifier que la sauvegarde fonctionne

    8. Lancer :

        ```bash
        intelis backup status
        ```

        Une sauvegarde qui fonctionne ressemble à ceci :

        ```text
        Lab            : kigali-central (kigali-central-3f9a2b1c)
        Backing up to  : lisbackup@192.168.1.60:/home/lisbackup/backups/kigali-central-3f9a2b1c
        Last good backup: 2026-08-07T09:14:22Z (12 minutes ago)
        Size on backup  : 4.2G
        History         : 2026-08-01 to 2026-08-07 (7 days)
        Last attempt    : succeeded in 47s
        Schedule        : every 8 hours and after every restart
        ```

        Vérifier ces lignes :

        | Ligne | Doit afficher |
        | --- | --- |
        | Last good backup | Une date et une heure de moins de 8 heures. |
        | Last attempt | `succeeded`. |
        | Schedule | `every 8 hours and after every restart`. |

        ??? failure "Si `Last attempt` affiche `FAILED`"

            La ligne `Reason` en dessous donne la cause. La corriger, puis
            lancer `intelis backup`. Le journal complet se trouve dans
            `/var/log/intelis-backup.log`.

        ??? failure "Si `Schedule` affiche `OFF`"

            Réactiver les sauvegardes planifiées :

            ```bash
            intelis backup enable
            ```

    Une fois par semaine, vérifier l'état de la même façon, puis lancer :

    ```bash
    intelis health
    ```

    La ligne de la sauvegarde ne doit afficher aucun avertissement. Si elle
    mentionne `newest DB dump`, les sauvegardes de la base de données se sont
    arrêtées. Voir l'avertissement sous **Emplacement de la sauvegarde**. Tous
    les trois mois, restaurer la sauvegarde la plus récente sur une machine de
    réserve ou de test en suivant
    [Restaurer depuis une sauvegarde](restoring-from-backup.md). Une sauvegarde
    qui n'a jamais été restaurée n'a pas encore prouvé qu'elle fonctionne.

    ### Emplacement de la sauvegarde

    La sauvegarde se trouve sur la machine de sauvegarde, dans un dossier qui
    porte le nom du laboratoire et un code propre à cette machine InteLIS :

    ```text
    /home/lisbackup/backups/kigali-central-3f9a2b1c/
    ```

    Il contient tout le dossier InteLIS. Les sauvegardes de la base de données
    se trouvent dans `backups/db` et les sauvegardes des paramètres dans
    `backups/config`. Une sauvegarde des paramètres contient les paramètres du
    laboratoire, les signatures, les modèles de rapport et les logos. La
    sauvegarde exclut les fichiers reconstruits à l'installation : `vendor/`,
    `node_modules/`, les caches, les journaux, les fichiers temporaires et les
    dossiers de gestion de versions.

    Le dossier est un miroir de cette machine. Il ne contient donc que les
    sauvegardes les plus récentes de la base de données, environ les 2 derniers
    jours. Les fichiers supprimés sur cette machine sont supprimés du miroir à
    l'exécution suivante. Les sauvegardes plus anciennes de la base de données
    sont conservées à côté, dans `.history/` :

    | Conservé dans `.history/` | Combien |
    | --- | --- |
    | Une sauvegarde de la base de données par jour | Les 7 jours les plus récents |
    | Une sauvegarde de la base de données par semaine, ensuite | 4 semaines de plus |
    | Une sauvegarde des paramètres par jour, puis une par semaine | Les mêmes 7 jours et 4 semaines |

    Cela fait environ 5 semaines au total. L'effacement ou la réinstallation de
    cette machine ne touche pas `.history/`. Pour en conserver davantage,
    modifier `HISTORY_DAYS` et `HISTORY_WEEKS` dans `/etc/intelis/backup.conf`.

    La sauvegarde contient aussi les paramètres, qui incluent le mot de passe
    de la base de données. Le dossier de sauvegarde ne doit être lisible que
    par les personnes qui administrent InteLIS.

    Deux laboratoires portant le même nom reçoivent quand même des dossiers
    distincts. Un laboratoire n'écrase jamais la sauvegarde d'un autre.

    ??? failure "Si une sauvegarde indique que la sauvegarde la plus récente de la base de données est ancienne"

        Le planificateur d'InteLIS s'est arrêté. Aucune nouvelle sauvegarde de
        la base de données n'est donc créée. La sauvegarde s'arrête avant de
        modifier la destination. Vérifier le planificateur en suivant
        [Vérifier que les tâches planifiées s'exécutent](maintenance.md#verifier-que-les-taches-planifiees-sexecutent).
        Lancer ensuite `intelis backup`.

    ### Autres commandes

    | Tâche | Commande |
    | --- | --- |
    | Sauvegarder maintenant | `intelis backup` |
    | Vérifier la connexion sans copier | `intelis backup test` |
    | Suivre une sauvegarde en cours | `tail -f /var/log/intelis-backup.log` |
    | Arrêter les sauvegardes planifiées | `intelis backup disable` |
    | Les réactiver | `intelis backup enable` |
    | Changer la destination des sauvegardes | Lancer `intelis backup setup`, puis choisir **Change where backups go** (changer la destination). Les réponses enregistrées sont proposées. Appuyer sur Entrée pour en garder une. |

    Pour récupérer les données, voir [Restaurer depuis une sauvegarde](restoring-from-backup.md).

=== "Dossier partagé Windows"

    Utiliser cette option s'il existe un ordinateur Windows sur le même réseau.
    Rien n'est installé sur Windows.

    ### Préparer l'ordinateur Windows

    1. Sur l'ordinateur Windows, créer ce dossier :

        ```text
        C:\InteLIS-Backups
        ```

    2. Appuyer sur **Win+R**, taper `lusrmgr.msc` et appuyer sur Entrée.

        ??? failure "Si Windows indique ne pas trouver `lusrmgr.msc`"

            Les éditions Famille de Windows ne l'ont pas. Ouvrir
            **Settings → Accounts → Other users** (**Paramètres → Comptes →
            Autres utilisateurs** sur un poste en français) et sélectionner
            **Add account** (**Ajouter un compte**). Sélectionner
            **I don't have this person's sign-in information** (**Je ne
            dispose pas des informations de connexion de cette personne**),
            puis **Add a user without a Microsoft account** (**Ajouter un
            utilisateur sans compte Microsoft**). Y créer l'utilisateur de
            l'étape 3.

            Empêcher ensuite l'expiration de son mot de passe. Sinon, la
            sauvegarde cesse de fonctionner à son expiration. Faire un clic
            droit sur **Start** (**Démarrer**), sélectionner
            **Terminal (Admin)** (**Terminal (administrateur)**) et exécuter :

            ```powershell
            Set-LocalUser -Name lisbackup -PasswordNeverExpires $true
            ```

            Reprendre à l'étape 5.

    3. Faire un clic droit sur **Users** (**Utilisateurs**) et sélectionner
       **New User** (**Nouvel utilisateur**). Définir le nom d'utilisateur
       `lisbackup` et un mot de passe fort. Noter le mot de passe.
    4. Décocher **User must change password at next logon** (**L'utilisateur
       doit changer le mot de passe à la prochaine ouverture de session**).
       Cocher **Password never expires** (**Le mot de passe n'expire
       jamais**). Sélectionner **Create** (**Créer**).
    5. Faire un clic droit sur le dossier `C:\InteLIS-Backups` et sélectionner
       **Properties** (**Propriétés**). Ouvrir l'onglet **Sharing**
       (**Partage**) et sélectionner **Advanced Sharing** (**Partage
       avancé**).
    6. Cocher **Share this folder** (**Partager ce dossier**). Définir
       **Share name** (**Nom du partage**) à `InteLIS-Backups`. Le nom ne doit
       pas contenir d'espaces.
    7. Sélectionner **Permissions** (**Autorisations**). Ajouter `lisbackup`,
       puis cocher **Change** (**Modifier**) et **Read** (**Lecture**) sous
       **Allow** (**Autoriser**). Sélectionner **OK** dans chaque fenêtre.
    8. Donner une adresse IP fixe à l'ordinateur Windows. Soit définir une
       adresse statique sur l'ordinateur, soit réserver son adresse dans le
       routeur. Noter l'adresse, par exemple `192.168.1.50`.
    9. Ouvrir **Control Panel → Windows Defender Firewall → Allow an app or
       feature through Windows Defender Firewall** (**Panneau de configuration
       → Pare-feu Windows Defender → Autoriser une application ou une
       fonctionnalité via le Pare-feu Windows Defender**). Vérifier que
       **File and Printer Sharing** (**Partage de fichiers et
       d'imprimantes**) est coché sous **Private** (**Privé**).

    ### Configurer la sauvegarde sur la machine InteLIS

    10. Sur la machine InteLIS, ouvrir un terminal et lancer :

        ```bash
        intelis backup setup
        ```

        La commande vérifie qu'elle dispose du script de configuration de
        sauvegarde actuel avant de le lancer. Si la machine est hors ligne,
        elle utilise le script installé.

    11. Répondre aux questions :

        | Question | Réponse |
        | --- | --- |
        | Lab name or lab code | Un nom court pour ce laboratoire, par exemple `centrallab`. |
        | Where should the backup be sent? | **A shared folder on a Windows machine**. |
        | Windows hostname or IP | L'adresse de l'étape 8. |
        | Name of the shared folder | `InteLIS-Backups` |
        | Windows username | `lisbackup` |
        | Windows password for lisbackup | Le mot de passe de l'étape 3. |

        ??? failure "S'il indique `Could not connect to //…`"

            Vérifier que l'ordinateur Windows est allumé et pas en veille.
            Vérifier l'adresse de l'étape 8, le nom du partage, le nom
            d'utilisateur et le mot de passe. Vérifier l'étape 9. Choisir
            ensuite **Yes** à **Try again?**.

        ??? failure "S'il indique `Connected, but the folder is read-only`"

            Sur l'ordinateur Windows, refaire l'étape 7 et vérifier que
            **Change** est coché. Choisir ensuite **Yes** à **Try again?**.

        ??? failure "S'il indique `The share name contains a space`"

            Sur l'ordinateur Windows, refaire les étapes 5 et 6 avec un nom de
            partage sans espaces, par exemple `InteLIS-Backups`. Le script
            repose les questions.

    12. Attendre la fin de la première sauvegarde. Elle peut durer une heure ou
        plus. Le script se termine par `Backups are set up and the first one completed`.

        ??? failure "S'il se termine par `Setup finished, but the first backup failed`"

            Les paramètres sont enregistrés. Lire l'erreur au-dessus du
            message, corriger la cause, puis relancer la sauvegarde :

            ```bash
            intelis backup
            ```

    ### Vérifier que la sauvegarde fonctionne

    13. Lancer :

        ```bash
        intelis backup status
        ```

        Une sauvegarde qui fonctionne ressemble à ceci :

        ```text
        Lab            : centrallab (centrallab-3f9a2b1c)
        Backing up to  : //192.168.1.50/InteLIS-Backups -> /mnt/intelis-backup/backups/centrallab-3f9a2b1c
        Last good backup: 2026-08-07T09:14:22Z (12 minutes ago)
        Size on backup  : not measured
        History         : 2026-08-01 to 2026-08-07 (7 days)
        Last attempt    : succeeded in 96s
        Schedule        : every 8 hours and after every restart
        ```

        Vérifier ces lignes :

        | Ligne | Doit afficher |
        | --- | --- |
        | Last good backup | Une date et une heure de moins de 8 heures. |
        | Last attempt | `succeeded`. |
        | Schedule | `every 8 hours and after every restart`. |

        ??? failure "Si `Last attempt` affiche `FAILED`"

            La ligne `Reason` en dessous donne la cause. La cause habituelle
            est un ordinateur Windows éteint ou en veille. La corriger, puis
            lancer `intelis backup`. Le journal complet se trouve dans
            `/var/log/intelis-backup.log`.

        ??? failure "Si `Schedule` affiche `OFF`"

            Réactiver les sauvegardes planifiées :

            ```bash
            intelis backup enable
            ```

    Une fois par semaine, vérifier l'état de la même façon, puis lancer :

    ```bash
    intelis health
    ```

    La ligne de la sauvegarde ne doit afficher aucun avertissement. Si elle
    mentionne `newest DB dump`, les sauvegardes de la base de données se sont
    arrêtées. Voir l'avertissement sous **Emplacement de la sauvegarde**. Tous
    les trois mois, restaurer la sauvegarde la plus récente sur une machine de
    réserve ou de test en suivant
    [Restaurer depuis une sauvegarde](restoring-from-backup.md). Une sauvegarde
    qui n'a jamais été restaurée n'a pas encore prouvé qu'elle fonctionne.

    ### Emplacement de la sauvegarde

    La sauvegarde se trouve sur l'ordinateur Windows, dans un dossier qui porte
    le nom du laboratoire et un code propre à cette machine InteLIS :

    ```text
    C:\InteLIS-Backups\backups\centrallab-3f9a2b1c\
    ```

    Il contient tout le dossier InteLIS. Les sauvegardes de la base de données
    se trouvent dans `backups\db` et les sauvegardes des paramètres dans
    `backups\config`. Une sauvegarde des paramètres contient les paramètres du
    laboratoire, les signatures, les modèles de rapport et les logos. La
    sauvegarde exclut les fichiers reconstruits à l'installation : `vendor/`,
    `node_modules/`, les caches, les journaux, les fichiers temporaires et les
    dossiers de gestion de versions.

    Le dossier est un miroir de cette machine. Il ne contient donc que les
    sauvegardes les plus récentes de la base de données, environ les 2 derniers
    jours. Les fichiers supprimés sur cette machine sont supprimés du miroir à
    l'exécution suivante. Les sauvegardes plus anciennes de la base de données
    sont conservées à côté, dans `.history/` :

    | Conservé dans `.history/` | Combien |
    | --- | --- |
    | Une sauvegarde de la base de données par jour | Les 7 jours les plus récents |
    | Une sauvegarde de la base de données par semaine, ensuite | 4 semaines de plus |
    | Une sauvegarde des paramètres par jour, puis une par semaine | Les mêmes 7 jours et 4 semaines |

    Cela fait environ 5 semaines au total. L'effacement ou la réinstallation de
    cette machine ne touche pas `.history/`. Pour en conserver davantage,
    modifier `HISTORY_DAYS` et `HISTORY_WEEKS` dans `/etc/intelis/backup.conf`.

    La sauvegarde contient aussi les paramètres, qui incluent le mot de passe
    de la base de données. Le dossier de sauvegarde ne doit être lisible que
    par les personnes qui administrent InteLIS.

    Deux laboratoires portant le même nom reçoivent quand même des dossiers
    distincts. Un laboratoire n'écrase jamais la sauvegarde d'un autre.

    ??? failure "Si une sauvegarde indique que la sauvegarde la plus récente de la base de données est ancienne"

        Le planificateur d'InteLIS s'est arrêté. Aucune nouvelle sauvegarde de
        la base de données n'est donc créée. La sauvegarde s'arrête avant de
        modifier la destination. Vérifier le planificateur en suivant
        [Vérifier que les tâches planifiées s'exécutent](maintenance.md#verifier-que-les-taches-planifiees-sexecutent).
        Lancer ensuite `intelis backup`.

    ### Autres commandes

    | Tâche | Commande |
    | --- | --- |
    | Sauvegarder maintenant | `intelis backup` |
    | Vérifier la connexion sans copier | `intelis backup test` |
    | Suivre une sauvegarde en cours | `tail -f /var/log/intelis-backup.log` |
    | Arrêter les sauvegardes planifiées | `intelis backup disable` |
    | Les réactiver | `intelis backup enable` |
    | Changer la destination des sauvegardes | Lancer `intelis backup setup`, puis choisir **Change where backups go**. Les réponses enregistrées sont proposées. Appuyer sur Entrée pour en garder une. |

    Pour récupérer les données, voir [Restaurer depuis une sauvegarde](restoring-from-backup.md).

=== "Clé USB ou disque externe"

    Utiliser cette option s'il n'existe aucune autre machine où envoyer les
    sauvegardes. Le disque doit rester branché sur la machine InteLIS.

    ### Configurer la sauvegarde

    1. Brancher le disque sur la machine InteLIS.
    2. Ouvrir un terminal et lancer :

        ```bash
        intelis backup setup
        ```

        La commande vérifie qu'elle dispose du script de configuration de
        sauvegarde actuel avant de le lancer. Si la machine est hors ligne,
        elle utilise le script installé.

    3. Répondre aux premières questions :

        | Question | Réponse |
        | --- | --- |
        | Lab name or lab code | Un nom court pour ce laboratoire, par exemple `centrallab`. |
        | Where should the backup be sent? | **A USB or external drive plugged into this machine**. |

    4. À **Which drive should the backups go to?** (vers quel disque envoyer
       les sauvegardes), choisir la clé USB. Chaque ligne affiche le nom et la
       taille du disque. Le script connecte le disque, et le reconnecte
       de lui-même après chaque redémarrage.

        ??? failure "S'il indique `No drive was found apart from this machine's own disk`"

            Le disque n'est pas branché, ou n'est pas reconnu. Le brancher,
            attendre quelques secondes, puis choisir **Look again** (chercher
            de nouveau).

        ??? failure "S'il indique `This drive is formatted as FAT32`"

            FAT32 ne peut pas contenir un fichier de 4 Go ou plus, et les
            sauvegardes de la base de données dépassent cette taille.
            Reformater le disque en exFAT ou en ext4. Cette opération efface
            tout son contenu. Ouvrir l'application **Disks** (**Disques** sur
            un poste en français), sélectionner le disque, puis sélectionner
            **Format Partition** (**Formater la partition**). Choisir ensuite
            **Look again**.

        ??? failure "S'il indique `The drive is open at …`"

            Une fenêtre affiche les fichiers du disque. La fermer, puis
            choisir **Yes** à **Choose again?** (choisir de nouveau) et
            choisir de nouveau le disque.

    5. Attendre la fin de la première sauvegarde. Elle peut durer une heure ou
       plus. Le script se termine par `Backups are set up and the first one completed`.

        ??? failure "S'il se termine par `Setup finished, but the first backup failed`"

            Les paramètres sont enregistrés. Lire l'erreur au-dessus du
            message, corriger la cause, puis relancer la sauvegarde :

            ```bash
            intelis backup
            ```

    ### Vérifier que la sauvegarde fonctionne

    6. Lancer :

        ```bash
        intelis backup status
        ```

        Une sauvegarde qui fonctionne ressemble à ceci :

        ```text
        Lab            : centrallab (centrallab-3f9a2b1c)
        Backing up to  : /mnt/intelis-usb/backups/centrallab-3f9a2b1c
        Last good backup: 2026-08-07T09:14:22Z (12 minutes ago)
        Size on backup  : 4.2G
        History         : 2026-08-01 to 2026-08-07 (7 days)
        Last attempt    : succeeded in 52s
        Schedule        : every 8 hours and after every restart
        ```

        Vérifier ces lignes :

        | Ligne | Doit afficher |
        | --- | --- |
        | Last good backup | Une date et une heure de moins de 8 heures. |
        | Last attempt | `succeeded`. |
        | Schedule | `every 8 hours and after every restart`. |

        ??? failure "Si la raison est `The backup drive is not plugged in`"

            Brancher le disque, puis lancer `intelis backup`. La sauvegarde
            connecte le disque d'elle-même.

        ??? failure "Si la raison est `The backup drive at … is not there`"

            Le disque a été configuré par une version plus ancienne, qui
            dépendait de son ouverture dans l'application **Files**
            (**Fichiers** sur un poste en français). Lancer
            `intelis backup setup`, choisir **Change where backups go**, et
            choisir le disque dans la liste.

        ??? failure "Si `Last attempt` affiche `FAILED` pour une autre raison"

            La ligne `Reason` en dessous donne la cause. La corriger, puis
            lancer `intelis backup`. Le journal complet se trouve dans
            `/var/log/intelis-backup.log`.

        ??? failure "Si `Schedule` affiche `OFF`"

            Réactiver les sauvegardes planifiées :

            ```bash
            intelis backup enable
            ```

    Une fois par semaine, vérifier l'état de la même façon, puis lancer :

    ```bash
    intelis health
    ```

    La ligne de la sauvegarde ne doit afficher aucun avertissement. Si elle
    mentionne `newest DB dump`, les sauvegardes de la base de données se sont
    arrêtées. Voir l'avertissement sous **Emplacement de la sauvegarde**. Tous
    les trois mois, restaurer la sauvegarde la plus récente sur une machine de
    réserve ou de test en suivant
    [Restaurer depuis une sauvegarde](restoring-from-backup.md). Une sauvegarde
    qui n'a jamais été restaurée n'a pas encore prouvé qu'elle fonctionne.

    ### Emplacement de la sauvegarde

    La sauvegarde se trouve sur le disque, dans un dossier qui porte le nom du
    laboratoire et un code propre à cette machine InteLIS :

    ```text
    /mnt/intelis-usb/backups/centrallab-3f9a2b1c/
    ```

    Il contient tout le dossier InteLIS. Les sauvegardes de la base de données
    se trouvent dans `backups/db` et les sauvegardes des paramètres dans
    `backups/config`. Une sauvegarde des paramètres contient les paramètres du
    laboratoire, les signatures, les modèles de rapport et les logos. La
    sauvegarde exclut les fichiers reconstruits à l'installation : `vendor/`,
    `node_modules/`, les caches, les journaux, les fichiers temporaires et les
    dossiers de gestion de versions.

    Le dossier est un miroir de cette machine. Il ne contient donc que les
    sauvegardes les plus récentes de la base de données, environ les 2 derniers
    jours. Les fichiers supprimés sur cette machine sont supprimés du miroir à
    l'exécution suivante. Les sauvegardes plus anciennes de la base de données
    sont conservées à côté, dans `.history/` :

    | Conservé dans `.history/` | Combien |
    | --- | --- |
    | Une sauvegarde de la base de données par jour | Les 7 jours les plus récents |
    | Une sauvegarde de la base de données par semaine, ensuite | 4 semaines de plus |
    | Une sauvegarde des paramètres par jour, puis une par semaine | Les mêmes 7 jours et 4 semaines |

    Cela fait environ 5 semaines au total. L'effacement ou la réinstallation de
    cette machine ne touche pas `.history/`. Pour en conserver davantage,
    modifier `HISTORY_DAYS` et `HISTORY_WEEKS` dans `/etc/intelis/backup.conf`.

    La sauvegarde contient aussi les paramètres, qui incluent le mot de passe
    de la base de données. Le dossier de sauvegarde ne doit être lisible que
    par les personnes qui administrent InteLIS.

    Deux laboratoires portant le même nom reçoivent quand même des dossiers
    distincts. Un laboratoire n'écrase jamais la sauvegarde d'un autre.

    ??? failure "Si une sauvegarde indique que la sauvegarde la plus récente de la base de données est ancienne"

        Le planificateur d'InteLIS s'est arrêté. Aucune nouvelle sauvegarde de
        la base de données n'est donc créée. La sauvegarde s'arrête avant de
        modifier la destination. Vérifier le planificateur en suivant
        [Vérifier que les tâches planifiées s'exécutent](maintenance.md#verifier-que-les-taches-planifiees-sexecutent).
        Lancer ensuite `intelis backup`.

    ### Autres commandes

    | Tâche | Commande |
    | --- | --- |
    | Sauvegarder maintenant | `intelis backup` |
    | Vérifier le disque sans copier | `intelis backup test` |
    | Suivre une sauvegarde en cours | `tail -f /var/log/intelis-backup.log` |
    | Arrêter les sauvegardes planifiées | `intelis backup disable` |
    | Les réactiver | `intelis backup enable` |
    | Changer la destination des sauvegardes | Lancer `intelis backup setup`, puis choisir **Change where backups go**. Les réponses enregistrées sont proposées. Appuyer sur Entrée pour en garder une. |

    Pour récupérer les données, voir [Restaurer depuis une sauvegarde](restoring-from-backup.md).

Une sauvegarde placée dans la même pièce que la machine InteLIS ne survit pas
à un incendie ni à un vol. Pour garder une seconde copie ailleurs, voir
[Sauvegarder sur Google Drive avec Rclone](backing-up-to-google-drive-with-rclone.md).
