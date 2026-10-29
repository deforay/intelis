---
description: Déplacer un laboratoire InteLIS vers une nouvelle machine Ubuntu, ou reconstruire une machine hors service, en restaurant ses sauvegardes.
audience: [system-admin]
module: [all]
type: how-to
platform: ubuntu
reviewed: 2026-10-29
reviewed_against: 5.8.5
---
# Migrer d'une machine Ubuntu à une autre

Déplacer un laboratoire vers une nouvelle machine Ubuntu, ou reconstruire une
machine hors service, à partir des sauvegardes de l'ancienne machine.

La nouvelle machine doit exécuter **Ubuntu 24.04 LTS ou une version plus
récente** et être connectée à Internet.

**Choisir la situation qui correspond, puis suivre ses étapes de haut en bas.**

=== "L'ancienne machine fonctionne encore"

    ### Sur l'ancienne machine

    1. Ouvrir un terminal et faire une nouvelle sauvegarde, pour ne rien perdre
       de ce qui a été saisi depuis la dernière sauvegarde automatique :

        ```bash
        intelis backup
        ```

        S'il demande `Set up off-machine backups now?`, appuyer sur Entrée (No).

        ??? info "Si `intelis` n'est pas reconnu"

            L'installation est plus ancienne. Exporter la base de données avec
            ceci à la place :

            ```bash
            cd ~ && wget -O db-backup.sh https://raw.githubusercontent.com/deforay/intelis/master/scripts/db-backup.sh
            sudo bash db-backup.sh
            ```

            - Saisir le nom d'utilisateur et le mot de passe MySQL.
            - Choisir la base de données `vlsm`, et `interfacing` si le
              laboratoire utilise l'outil d'interface.
            - Quand l'emplacement est demandé, saisir
              `/var/www/intelis/backups/db`. Sur les installations plus
              anciennes, saisir `/var/www/vlsm/backups/db`.
            - Attendre `Script completed.` S'il affiche
              `These databases were NOT backed up`, s'arrêter.

        ??? failure "Si MySQL ne démarre pas"

            Rien ne peut être sauvegardé tant que MySQL ne fonctionne pas.
            Suivre [MySQL ne démarre pas](mysql-will-not-start.md), puis revenir
            à l'étape 1.

    2. Brancher une clé USB sur l'ancienne machine.
    3. Dans le terminal, taper ceci, suivi d'un espace. Ne pas encore appuyer
       sur Entrée :

        ```bash
        sudo cp -r /var/www/intelis/backups
        ```

        Sur les installations plus anciennes, taper `/var/www/vlsm/backups` à la
        place de `/var/www/intelis/backups`.

    4. Ouvrir l'application **Files** (**Fichiers** sur un poste en français).
       Faire glisser la clé USB depuis la barre latérale gauche sur la fenêtre
       du terminal. Son chemin apparaît après la commande.
    5. Appuyer sur Entrée. Le dossier `backups` est copié sur la clé USB.
    6. Débrancher la clé USB.

    ### Sur la nouvelle machine

    7. Brancher la clé USB.
    8. Ouvrir un terminal et télécharger le programme d'installation :

        ```bash
        cd ~ && wget -O setup.sh "https://raw.githubusercontent.com/deforay/intelis/master/scripts/setup.sh?v=$(date +%s)"
        ```

    9. Taper ceci, suivi d'un espace. Ne pas encore appuyer sur Entrée :

        ```bash
        sudo bash setup.sh --restore-from-backup-folder
        ```

    10. Ouvrir la clé USB dans l'application **Files**. Faire glisser le dossier
        `backups` sur la fenêtre du terminal. Son chemin apparaît après la
        commande.

        Au lieu de faire glisser, cliquer une fois sur le dossier et appuyer sur
        **Ctrl+C**. Cliquer ensuite dans le terminal et appuyer sur
        **Ctrl+Shift+V**. Si le chemin contient des espaces et n'est pas entre
        guillemets, ajouter `'` aux deux extrémités.

    11. Appuyer sur Entrée, puis répondre aux questions du programme
        d'installation :

        | Question | Réponse |
        | --- | --- |
        | Installation directory | Appuyer sur Entrée. |
        | Which backup should be restored? | Appuyer sur Entrée. La sauvegarde la plus récente est en haut et déjà sélectionnée. |
        | What is this machine? | **Lab machine (LIS)**. |
        | Remote STS URL | L'adresse du STS utilisée par l'ancienne machine. Laisser vide si le laboratoire n'a pas de STS. |
        | New MySQL root password | Un nouveau mot de passe pour cette machine, saisi deux fois. Le noter. |
        | Is this correct? | Vérifier le récapitulatif, puis appuyer sur Entrée (Yes). |

    12. Attendre 10 à 20 minutes. Le programme d'installation se termine par
        `Setup complete`.

        Vers la fin, il affiche `Signatures, report templates and logos restored`.
        Les paramètres du laboratoire, comme l'adresse du STS et les modules
        activés, ainsi que les signatures, les modèles de rapport et les logos
        sont alors revenus depuis la sauvegarde des paramètres dans
        `backups/config`.

        ??? warning "S'il indique `Setup did not restore the settings, signatures, report templates or logos`"

            Le dossier `backups/config` est absent, ou a été créé par une
            version plus ancienne d'InteLIS. La base de données revient quand
            même. Après l'installation, vérifier l'adresse du STS et les modules
            activés dans **Configuration du système**. Voir
            [Utiliser l'espace System Admin](../user-guides/admin-system-administration.md).
            Pour recopier les fichiers téléversés depuis une sauvegarde hors de
            la machine, suivre l'onglet **Base de données et fichiers
            téléversés** de [Restaurer depuis une sauvegarde](restoring-from-backup.md).

        ??? warning "S'il indique `This machine keeps its own settings`"

            La sauvegarde des paramètres n'a pas pu être utilisée. Après
            l'installation, vérifier l'adresse du STS et les modules activés
            dans **Configuration du système**. Voir
            [Utiliser l'espace System Admin](../user-guides/admin-system-administration.md).

        ??? failure "S'il s'arrête avec `Failed to decrypt`"

            Le programme d'installation n'a pas pu ouvrir la sauvegarde avec les
            paramètres de l'ancienne machine dans `backups/config`. Demander à
            l'administrateur du STS un jeton de récupération à usage unique. Il
            exécute ceci sur le STS :

            ```bash
            cd /var/www/intelis && sudo -u www-data php bin/backup-key-admin.php approve --lab <lab-id>
            ```

            Répéter ensuite les étapes 9 à 11, en ajoutant l'adresse du STS et le
            jeton après le chemin du dossier :

            ```bash
            sudo bash setup.sh --restore-from-backup-folder '<folder>' --sts-url https://sts.example.org --recovery-token ABCD-EFGH-JKMN-PQRS
            ```

            La deuxième exécution demande `Reuse your previous setup answers and skip the prompts?`.
            Appuyer sur Entrée (Yes) pour réutiliser les réponses. S'il indique
            `An existing database was found`, choisir **Keep a copy, then start
            fresh** (garder une copie, puis repartir de zéro).

    ### Vérifier le laboratoire

    13. Ouvrir InteLIS dans le navigateur.
    14. Se connecter avec un compte administrateur de l'ancienne machine. Ne pas
        en créer un nouveau. La base de données restaurée contient déjà les
        utilisateurs, les paramètres du laboratoire et toutes les données.
    15. Vérifier les paramètres du laboratoire sous **ADMIN → Paramètres →
        Configuration générale**.
    16. Ouvrir le PDF de résultats d'une demande récente, et vérifier que ses
        signatures et son logo apparaissent.
    17. Si le laboratoire utilise l'outil d'interface, restaurer aussi sa base
        de données.

        1. Dans un terminal, taper `sudo cp` suivi d'un espace. Ne pas encore
           appuyer sur Entrée.
        2. Faire glisser sur le terminal le fichier le plus récent commençant
           par `interfacing-`, depuis le dossier `db`. Taper ensuite ` /tmp/` et
           appuyer sur Entrée.
        3. Le restaurer dans la base de données de l'outil d'interface :

            ```bash
            cd /var/www/intelis && sudo -u www-data php vendor/bin/db-tools restore --profile=interfacing /tmp/interfacing-*
            ```

        4. Supprimer la copie :

            ```bash
            sudo rm /tmp/interfacing-*
            ```

        Si l'étape 3 signale que le profil `interfacing` n'existe pas,
        configurer d'abord l'outil d'interface avec
        [Connecter un automate à InteLIS](setting-up-interfacing-tool.md), puis
        répéter l'étape 3. Si elle signale qu'elle ne peut pas ouvrir un fichier
        chiffré, contacter le support avec le nom du fichier.

=== "L'ancienne machine est hors service"

    Utiliser le dossier `backups` copié depuis l'ancienne machine.

    ### Vérifier le dossier copié

    1. Brancher sur la nouvelle machine la clé USB qui contient le dossier
       copié.
    2. Ouvrir le dossier `backups` dans l'application **Files** (**Fichiers**
       sur un poste en français). Vérifier qu'il contient ces deux dossiers :

        | Dossier | Contenu |
        | --- | --- |
        | `db` | Les sauvegardes de la base de données. |
        | `config` | Les paramètres, les signatures, les modèles de rapport et les logos de l'ancienne machine. La clé des sauvegardes de la base de données est lue ici. |

        Si `config` est absent, continuer. La base de données revient quand
        même. L'étape 8 indique quoi faire si la sauvegarde ne s'ouvre pas.

    3. Ouvrir `db` et trouver le fichier le plus récent commençant par `vlsm-`.
       Son nom donne la date et l'heure de la sauvegarde. Par exemple,
       `vlsm-20260903-100002-…` a été créé le 3 septembre 2026 à 10:00. Le
       laboratoire revient à ce moment-là.

    Ne renommer aucun fichier. Le nom de chaque fichier fait partie de sa clé.

    ### Installer et restaurer

    4. Ouvrir un terminal et télécharger le programme d'installation :

        ```bash
        cd ~ && wget -O setup.sh "https://raw.githubusercontent.com/deforay/intelis/master/scripts/setup.sh?v=$(date +%s)"
        ```

    5. Taper ceci, suivi d'un espace. Ne pas encore appuyer sur Entrée :

        ```bash
        sudo bash setup.sh --restore-from-backup-folder
        ```

    6. Faire glisser le dossier `backups` depuis l'application **Files** sur la
       fenêtre du terminal. Son chemin apparaît après la commande.

        Au lieu de faire glisser, cliquer une fois sur le dossier et appuyer sur
        **Ctrl+C**. Cliquer ensuite dans le terminal et appuyer sur
        **Ctrl+Shift+V**. Si le chemin contient des espaces et n'est pas entre
        guillemets, ajouter `'` aux deux extrémités.

    7. Appuyer sur Entrée, puis répondre aux questions du programme
       d'installation :

        | Question | Réponse |
        | --- | --- |
        | Installation directory | Appuyer sur Entrée. |
        | Which backup should be restored? | Appuyer sur Entrée. La sauvegarde la plus récente est en haut et déjà sélectionnée. |
        | What is this machine? | **Lab machine (LIS)**. |
        | Remote STS URL | L'adresse du STS utilisée par l'ancienne machine. Laisser vide si le laboratoire n'a pas de STS. |
        | New MySQL root password | Un nouveau mot de passe pour cette machine, saisi deux fois. Le noter. |
        | Is this correct? | Vérifier le récapitulatif, puis appuyer sur Entrée (Yes). |

    8. Attendre 10 à 20 minutes. Le programme d'installation se termine par
       `Setup complete`.

        Vers la fin, il affiche `Signatures, report templates and logos restored`.
        Les paramètres du laboratoire, comme l'adresse du STS et les modules
        activés, ainsi que les signatures, les modèles de rapport et les logos
        sont alors revenus depuis la sauvegarde des paramètres dans
        `backups/config`.

        ??? warning "S'il indique `Setup did not restore the settings, signatures, report templates or logos`"

            Le dossier `backups/config` est absent, ou a été créé par une
            version plus ancienne d'InteLIS. La base de données revient quand
            même. Après l'installation, vérifier l'adresse du STS et les modules
            activés dans **Configuration du système**. Voir
            [Utiliser l'espace System Admin](../user-guides/admin-system-administration.md).
            Pour recopier les fichiers téléversés depuis une sauvegarde hors de
            la machine, suivre l'onglet **Base de données et fichiers
            téléversés** de [Restaurer depuis une sauvegarde](restoring-from-backup.md).

        ??? warning "S'il indique `This machine keeps its own settings`"

            La sauvegarde des paramètres n'a pas pu être utilisée. Après
            l'installation, vérifier l'adresse du STS et les modules activés
            dans **Configuration du système**. Voir
            [Utiliser l'espace System Admin](../user-guides/admin-system-administration.md).

        ??? failure "S'il s'arrête avec `Failed to decrypt`"

            Le programme d'installation n'a pas trouvé la clé. Soit le dossier
            `config` n'a pas été copié, soit la sauvegarde utilise une clé
            détenue par le STS. Demander à l'administrateur du STS un jeton de
            récupération à usage unique. Il exécute ceci sur le STS :

            ```bash
            cd /var/www/intelis && sudo -u www-data php bin/backup-key-admin.php approve --lab <lab-id>
            ```

            Répéter ensuite les étapes 5 à 7, en ajoutant l'adresse du STS et le
            jeton après le chemin du dossier :

            ```bash
            sudo bash setup.sh --restore-from-backup-folder '<folder>' --sts-url https://sts.example.org --recovery-token ABCD-EFGH-JKMN-PQRS
            ```

            La deuxième exécution demande `Reuse your previous setup answers and skip the prompts?`.
            Appuyer sur Entrée (Yes) pour réutiliser les réponses. S'il indique
            `An existing database was found`, choisir **Keep a copy, then start
            fresh** (garder une copie, puis repartir de zéro).

            Si cette machine ne peut pas joindre le STS, demander plutôt le code
            de récupération à l'administrateur du STS. Il l'obtient en exécutant
            ceci sur le STS :

            ```bash
            cd /var/www/intelis && sudo -u www-data php bin/backup-key-admin.php show-code --lab <lab-id>
            ```

            Exécuter ensuite :

            ```bash
            sudo bash setup.sh --restore-from-backup-folder '<folder>' --encryption-password '<recovery-code>'
            ```

    ### Vérifier le laboratoire

    9. Ouvrir InteLIS dans le navigateur.
    10. Se connecter avec un compte administrateur de l'ancienne machine. Ne pas
        en créer un nouveau. La base de données restaurée contient déjà les
        utilisateurs, les paramètres du laboratoire et toutes les données.
    11. Vérifier les paramètres du laboratoire sous **ADMIN → Paramètres →
        Configuration générale**.
    12. Ouvrir le PDF de résultats d'une demande récente, et vérifier que ses
        signatures et son logo apparaissent.
    13. Si le laboratoire utilise l'outil d'interface, restaurer aussi sa base
        de données.

        1. Dans un terminal, taper `sudo cp` suivi d'un espace. Ne pas encore
           appuyer sur Entrée.
        2. Faire glisser sur le terminal le fichier le plus récent commençant
           par `interfacing-`, depuis le dossier `db`. Taper ensuite ` /tmp/` et
           appuyer sur Entrée.
        3. Le restaurer dans la base de données de l'outil d'interface :

            ```bash
            cd /var/www/intelis && sudo -u www-data php vendor/bin/db-tools restore --profile=interfacing /tmp/interfacing-*
            ```

        4. Supprimer la copie :

            ```bash
            sudo rm /tmp/interfacing-*
            ```

        Si l'étape 3 signale que le profil `interfacing` n'existe pas,
        configurer d'abord l'outil d'interface avec
        [Connecter un automate à InteLIS](setting-up-interfacing-tool.md), puis
        répéter l'étape 3. Si elle signale qu'elle ne peut pas ouvrir un fichier
        chiffré, contacter le support avec le nom du fichier.

=== "Sauvegardes sur un serveur ou un partage"

    Utiliser cet onglet quand l'ancienne machine envoyait ses sauvegardes vers
    une autre machine Linux ou un dossier partagé Windows.

    ### Récupérer les sauvegardes

    1. Sur la nouvelle machine, ouvrir un terminal et exécuter :

        ```bash
        cd ~ && wget -O restore-backup.sh https://raw.githubusercontent.com/deforay/intelis/master/scripts/restore-backup.sh
        sudo bash restore-backup.sh
        ```

    2. Indiquer où les sauvegardes sont stockées, et se connecter quand c'est
       demandé.
    3. Choisir le laboratoire dans la liste.
    4. Choisir **Just the database backups** (seulement les sauvegardes de la
       base de données).
    5. Appuyer sur Entrée pour accepter le dossier proposé.
    6. Attendre la fin de la copie. Vers la fin, il affiche une commande sur
       deux lignes qui commence par `cd ~ && wget -O setup.sh`. Sélectionner les
       deux lignes et appuyer sur **Ctrl+Shift+C** pour les copier.

    ### Installer et restaurer

    7. Appuyer sur **Ctrl+Shift+V** pour coller la commande, puis appuyer sur
       Entrée.
    8. Répondre aux questions du programme d'installation :

        | Question | Réponse |
        | --- | --- |
        | Installation directory | Appuyer sur Entrée. |
        | Which backup should be restored? | Appuyer sur Entrée. La sauvegarde la plus récente est en haut et déjà sélectionnée. |
        | What is this machine? | **Lab machine (LIS)**. |
        | Remote STS URL | L'adresse du STS utilisée par l'ancienne machine. Laisser vide si le laboratoire n'a pas de STS. |
        | New MySQL root password | Un nouveau mot de passe pour cette machine, saisi deux fois. Le noter. |
        | Is this correct? | Vérifier le récapitulatif, puis appuyer sur Entrée (Yes). |

    9. Attendre 10 à 20 minutes. Le programme d'installation se termine par
       `Setup complete`.

        Vers la fin, il affiche `Signatures, report templates and logos restored`.
        Les paramètres du laboratoire, comme l'adresse du STS et les modules
        activés, ainsi que les signatures, les modèles de rapport et les logos
        sont alors revenus depuis la sauvegarde des paramètres dans
        `backups/config`.

        ??? warning "S'il indique `Setup did not restore the settings, signatures, report templates or logos`"

            Le dossier `backups/config` est absent, ou a été créé par une
            version plus ancienne d'InteLIS. La base de données revient quand
            même. Après l'installation, vérifier l'adresse du STS et les modules
            activés dans **Configuration du système**. Voir
            [Utiliser l'espace System Admin](../user-guides/admin-system-administration.md).
            Pour recopier les fichiers téléversés depuis une sauvegarde hors de
            la machine, suivre l'onglet **Base de données et fichiers
            téléversés** de [Restaurer depuis une sauvegarde](restoring-from-backup.md).

        ??? warning "S'il indique `This machine keeps its own settings`"

            La sauvegarde des paramètres n'a pas pu être utilisée. Après
            l'installation, vérifier l'adresse du STS et les modules activés
            dans **Configuration du système**. Voir
            [Utiliser l'espace System Admin](../user-guides/admin-system-administration.md).

        ??? failure "S'il s'arrête avec `Failed to decrypt`"

            La sauvegarde utilise une clé détenue par le STS. Demander à
            l'administrateur du STS un jeton de récupération à usage unique. Il
            exécute ceci sur le STS :

            ```bash
            cd /var/www/intelis && sudo -u www-data php bin/backup-key-admin.php approve --lab <lab-id>
            ```

            Coller ensuite à nouveau la commande de l'étape 6. Avant d'appuyer
            sur Entrée, ajouter à la fin l'adresse du STS et le jeton :

            ```bash
            --sts-url https://sts.example.org --recovery-token ABCD-EFGH-JKMN-PQRS
            ```

            La deuxième exécution demande `Reuse your previous setup answers and skip the prompts?`.
            Appuyer sur Entrée (Yes) pour réutiliser les réponses. S'il indique
            `An existing database was found`, choisir **Keep a copy, then start
            fresh** (garder une copie, puis repartir de zéro).

    ### Vérifier le laboratoire

    10. Ouvrir InteLIS dans le navigateur.
    11. Se connecter avec un compte administrateur de l'ancienne machine. Ne pas
        en créer un nouveau. La base de données restaurée contient déjà les
        utilisateurs, les paramètres du laboratoire et toutes les données.
    12. Vérifier les paramètres du laboratoire sous **ADMIN → Paramètres →
        Configuration générale**.
    13. Ouvrir le PDF de résultats d'une demande récente, et vérifier que ses
        signatures et son logo apparaissent.
    14. Si le laboratoire utilise l'outil d'interface, restaurer aussi sa base
        de données.

        1. Copier la sauvegarde la plus récente de l'outil d'interface hors du
           dossier récupéré :

            ```bash
            sudo bash -c 'cp "$(ls -t /var/intelis-restore/*/db/interfacing-* | head -1)" /tmp/'
            ```

        2. La restaurer dans la base de données de l'outil d'interface :

            ```bash
            cd /var/www/intelis && sudo -u www-data php vendor/bin/db-tools restore --profile=interfacing /tmp/interfacing-*
            ```

        3. Supprimer la copie :

            ```bash
            sudo rm /tmp/interfacing-*
            ```

        Si l'étape 2 signale que le profil `interfacing` n'existe pas,
        configurer d'abord l'outil d'interface avec
        [Connecter un automate à InteLIS](setting-up-interfacing-tool.md), puis
        répéter l'étape 2. Si elle signale qu'elle ne peut pas ouvrir un fichier
        chiffré, contacter le support avec le nom du fichier.
