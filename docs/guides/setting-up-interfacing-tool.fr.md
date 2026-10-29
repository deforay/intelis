---
description: Installer l'outil d'interface, connecter un automate, et faire importer ses résultats automatiquement par InteLIS.
audience: [system-admin, lab-admin]
module: [vl, eid, covid19, hepatitis, tb, cd4, custom-tests]
type: how-to
platform: ubuntu
reviewed: 2026-10-29
reviewed_against: 5.8.5
---
# Connecter un automate à InteLIS

Envoyer les résultats d'un automate dans InteLIS par l'outil d'interface, pour
que personne n'ait à les saisir.

InteLIS doit déjà être installé sur la machine Ubuntu du laboratoire.
L'automate doit être connecté au réseau du laboratoire.

**Choisir la situation qui correspond, puis suivre ses étapes de haut en bas.**

=== "Outil sur cette machine"

    Utiliser cet onglet quand l'outil d'interface s'exécute sur la machine
    InteLIS elle-même.

    ### Installer l'outil d'interface

    1. Ouvrir un terminal sur la machine InteLIS et télécharger le programme
       d'installation :

        ```bash
        cd ~ && wget -O install-interfacing.sh "https://raw.githubusercontent.com/deforay/intelis-interfacing/master/scripts/install.sh?v=$(date +%s)"
        ```

    2. L'exécuter, et saisir le mot de passe quand `sudo` le demande :

        ```bash
        bash install-interfacing.sh
        ```

        ??? info "Pour installer une version précise"

            Ajouter la version à la fin :

            ```bash
            bash install-interfacing.sh --tag v4.2.1
            ```

    ### Ajouter l'automate dans l'outil

    3. Ouvrir l'outil d'interface depuis le menu des applications.
    4. Se connecter avec le Login ID `admin` et le mot de passe `admin`. La
       première connexion ouvre **Settings**.
    5. Sous **System**, régler **Auto-connect on startup** sur **Yes**. Avec
       **No**, l'outil cesse d'écouter après chaque redémarrage, jusqu'à ce que
       quelqu'un se connecte.
    6. Sous **Instruments**, sélectionner **+ Add Instrument** et remplir ces
       champs :

        | Champ | Valeur |
        | --- | --- |
        | Connection Mode | **TCP Server** si l'automate se connecte à cette machine. **TCP Client** si cette machine se connecte à l'automate. Le manuel de l'automate indique lequel. |
        | Communication Protocol | **ASTM**, **ASTM (with checksum)** ou **HL7**, comme réglé sur l'automate. |
        | IP Address | TCP Server : l'adresse de cette machine sur le réseau du laboratoire. TCP Client : l'adresse de l'automate. |
        | Port Number | Le port réglé sur l'automate. |
        | Analyzer Type | Le modèle de l'automate. |
        | Instrument Name/Code | Un nom pour cet automate. L'étape 15 donne le même nom à InteLIS. |

        ??? info "Si le laboratoire a plusieurs automates"

            Ajouter chacun ici avec son propre port. Un seul outil d'interface
            les sert tous.

    7. Sélectionner **Save Settings**.
    8. Passer un échantillon sur l'automate. Vérifier qu'il apparaît parmi les
       résultats reçus dans la console de l'outil.

        ??? failure "Si le résultat n'apparaît pas dans l'outil"

            Lire le journal dans l'onglet de l'automate.
            `Server bound and listening on` signifie que l'outil attend et que
            l'automate ne s'est pas connecté. Suivre le
            [guide de dépannage](https://deforay.github.io/intelis-interfacing/guide/troubleshooting/)
            de l'outil.

            Ne pas continuer tant que l'outil n'affiche pas le résultat. InteLIS
            ne peut importer que ce que détient l'outil.

    ### Connecter InteLIS à l'outil

    9. Dans le terminal, exécuter :

        ```bash
        sudo intelis interface setup
        ```

        ??? info "S'il affiche **What is configured now**"

            L'interfaçage est déjà configuré. Pour le remplacer, répondre
            **Yes** à `Set interfacing up again, replacing this?`. Pour le
            laisser tel quel, appuyer sur Entrée.

        ??? failure "S'il s'arrête avec `The InteLIS database is not reachable`"

            Exécuter `sudo intelis fix-database`. Répéter ensuite l'étape 9.

        ??? failure "Si `intelis` n'est pas reconnu"

            L'installation est plus ancienne. Soit
            [mettre à jour InteLIS](updating-intelis-on-ubuntu.md) et répéter
            l'étape 9, soit faire la configuration à la main :

            1. Ouvrir MySQL :

                ```bash
                sudo mysql
                ```

            2. Créer la base de données et le compte. Choisir un mot de passe
               long à la place de `A-LONG-PASSWORD` :

                ```sql
                CREATE DATABASE IF NOT EXISTS interfacing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
                CREATE USER 'interfacing'@'localhost' IDENTIFIED BY 'A-LONG-PASSWORD';
                CREATE USER 'interfacing'@'127.0.0.1' IDENTIFIED BY 'A-LONG-PASSWORD';
                ALTER USER 'interfacing'@'localhost' IDENTIFIED WITH mysql_native_password BY 'A-LONG-PASSWORD';
                ALTER USER 'interfacing'@'127.0.0.1' IDENTIFIED WITH mysql_native_password BY 'A-LONG-PASSWORD';
                GRANT ALL PRIVILEGES ON interfacing.* TO 'interfacing'@'localhost';
                GRANT ALL PRIVILEGES ON interfacing.* TO 'interfacing'@'127.0.0.1';
                EXIT;
                ```

               MySQL 8.4 et les versions suivantes rejettent les lignes
               `ALTER USER` avec `Plugin 'mysql_native_password' is not loaded`.
               Sur ces serveurs, les omettre.

            3. Ouvrir le fichier de paramètres d'InteLIS. Sur les installations
               plus anciennes, le dossier est `/var/www/vlsm` :

                ```bash
                sudo nano /var/www/intelis/configs/config.production.php
                ```

            4. Régler ces lignes, avec le même mot de passe :

                ```php
                $systemConfig['interfacing']['enabled'] = true;
                $systemConfig['interfacing']['database']['host'] = '127.0.0.1';
                $systemConfig['interfacing']['database']['username'] = 'interfacing';
                $systemConfig['interfacing']['database']['password'] = 'A-LONG-PASSWORD';
                $systemConfig['interfacing']['database']['db'] = 'interfacing';
                $systemConfig['interfacing']['database']['port'] = 3306;
                ```

            5. Enregistrer avec **Ctrl+O**, puis fermer avec **Ctrl+X**.

            Passer à l'étape 12, et saisir l'hôte `127.0.0.1`, le port `3306`,
            la base de données `interfacing`, le nom d'utilisateur
            `interfacing` et ce mot de passe. Ouvrir l'outil une fois pour qu'il
            crée ses tables, puis exécuter `intelis interface-migrate`.

    10. Répondre aux questions :

        | Question | Réponse |
        | --- | --- |
        | How does the Interfacing Tool store the results InteLIS should read? | Appuyer sur Entrée. **MySQL on this machine** est déjà sélectionné. |
        | MySQL port | Appuyer sur Entrée (`3306`). |
        | Database name | Appuyer sur Entrée (`interfacing`). |
        | Where is the Interfacing Tool installed? | Appuyer sur Entrée. **On this machine** est déjà sélectionné. |
        | Enter a MySQL administrator account to use? | Posée seulement quand la configuration ne trouve aucun compte qui lui soit propre. Appuyer sur Entrée (Yes), puis saisir le nom d'utilisateur `root` de MySQL et son mot de passe. |
        | Name for the account the tool and InteLIS will use | Appuyer sur Entrée (`interfacing`). |
        | Reuse it, and set its password to the one chosen next? | Posée seulement quand le compte existe déjà. Répondre **Yes** si une configuration précédente de l'interfaçage l'a créé. Sinon, appuyer sur Entrée (No) et choisir un autre nom. |
        | Password for that account | Appuyer sur Entrée. **Use a generated password** est déjà sélectionné. |

        ??? info "Pour faire lire à InteLIS le fichier SQLite de l'outil"

            C'est la voie la plus simple, mais elle ne transporte que les
            résultats. L'activité des automates et le rapport d'utilisation
            quotidienne nécessitent MySQL.

            1. À la première question, choisir **The tool's own SQLite file on this machine**.
            2. À `Which file?`, appuyer sur Entrée. Le fichier de l'outil est
               déjà sélectionné. Si aucun fichier n'est trouvé, ouvrir l'outil
               une fois, puis répéter l'étape 9.
            3. Si la question `Grant www-data just enough access to read it?`
               est posée, appuyer sur Entrée (Yes).

            Sur cette voie, la configuration ne crée ni base de données ni
            compte. Faire l'étape 11, puis passer les étapes 12 à 14 et aller à
            l'étape 15.

        ??? info "Si la base de données de l'outil d'interface est sur un autre serveur"

            La configuration ne crée rien sur un autre serveur. La base de
            données et son compte doivent déjà y exister.

            1. À la première question, choisir **MySQL on another server**.
            2. Saisir l'adresse du serveur, le port MySQL et le nom de la base
               de données.
            3. Saisir le nom d'utilisateur et le mot de passe du compte avec
               lequel InteLIS se connecte.

            La configuration vérifie la connexion et cherche la table `orders`
            de l'outil. À l'étape 12, saisir les informations de ce serveur, et
            non les valeurs ci-dessous.

    11. Attendre `interfacing is now switched on`. La configuration affiche
        ensuite un tableau sous **Enter these in the Interfacing Tool**. Garder
        le terminal ouvert.

        ??? failure "S'il affiche **Still to do**"

            Exécuter chaque commande de la liste, dans l'ordre. S'il indique
            `This MySQL no longer offers mysql_native_password`, l'outil risque
            de ne pas pouvoir se connecter à l'étape 13. Contacter le support
            avec la version de l'outil.

    ### Saisir la base de données dans l'outil

    12. Dans l'outil, ouvrir **Settings**, puis **MySQL**. Saisir les valeurs
        affichées par la configuration :

        | Paramètre affiché | Champ de l'outil |
        | --- | --- |
        | Host | **MySQL Host** |
        | Port | **MySQL Port** |
        | Database | **Database Name** |
        | Username | **Database User** |
        | Password | **Database Password** |

    13. Sélectionner **Test Connection**.

        ??? failure "Si l'outil ne peut pas se connecter"

            Comparer chaque valeur avec le tableau affiché. Le mot de passe est
            aussi enregistré dans
            `/var/www/intelis/configs/config.production.php`, sur la ligne qui
            contient `['interfacing']['database']['password']`.

    14. Sélectionner **Save Settings**.

    ### Enregistrer l'automate dans InteLIS

    15. Ajouter l'automate dans InteLIS en suivant
        [Configurer un instrument](../user-guides/admin-instruments.md). Saisir
        comme **Nom de la machine** la valeur **Instrument Name/Code** de
        l'outil.

    ### Vérifier qu'un résultat arrive

    16. Passer un échantillon sur l'automate, ou renvoyer un résultat terminé
        depuis l'écran de l'automate.
    17. Dans le terminal, l'importer tout de suite au lieu d'attendre une
        minute :

        ```bash
        sudo intelis interface
        ```

        Le résultat doit être compté :

        ```text
        Connected to MySQL
        # of records from MySQL : 1
        Processing 1 filtered results from Interface Tool
        ```

        Sur la voie SQLite, les deux premières lignes sont
        `Connected to sqlite` et `# of records from SQLITE3 : 1`.

        ??? failure "S'il compte `0` enregistrement"

            Le résultat n'a pas atteint la base de données que lit InteLIS.

            - Vérifier la colonne **Sync Status** de l'outil. Si chaque résultat
              est `Pending`, répéter l'étape 13.
            - Seuls les résultats définitifs et les passages en échec sont lus.
              Un résultat que l'automate n'a pas finalisé reste dans l'outil.

        ??? failure "S'il affiche `Error while syncing interface results`"

            Ouvrir le fichier le plus récent dans `/var/www/intelis/var/logs` et
            y chercher l'erreur. L'envoyer au support si elle n'indique pas de
            correction.

    18. Ouvrir l'échantillon dans InteLIS. Vérifier que le résultat s'y trouve.

        ??? failure "Si le résultat est compté mais absent d'InteLIS"

            L'ID de l'échantillon sur l'automate doit correspondre au code
            d'échantillon d'une demande enregistrée dans InteLIS, caractère pour
            caractère. Enregistrer la demande, ou corriger l'ID sur l'automate,
            puis répéter les étapes 16 à 18.

    19. Laisser l'outil en marche. InteLIS importe désormais les résultats
        chaque minute.

        ??? failure "Si les résultats arrivent à la main mais pas d'eux-mêmes"

            Le planificateur ne fonctionne pas. Vérifier qu'il est installé :

            ```bash
            sudo crontab -l | grep cron.sh
            ```

            Cette ligne doit apparaître :

            ```text
            * * * * * cd /var/www/intelis && ./cron.sh
            ```

            Si la ligne manque, ou appelle `crunz` directement, voir
            [Scripts de maintenance](maintenance.md). Si la ligne est là,
            vérifier que l'heure de ce fichier date de moins d'une minute :

            ```bash
            ls -l /var/www/intelis/var/.cron_heartbeat
            ```

=== "Outil sur un autre ordinateur"

    Utiliser cet onglet quand l'outil d'interface s'exécute sur un autre
    ordinateur qu'InteLIS, par exemple un ordinateur Windows à côté de
    l'automate.

    ### Installer l'outil d'interface

    1. Sur l'ordinateur de l'outil, ouvrir un terminal et télécharger le
       programme d'installation :

        ```bash
        cd ~ && wget -O install-interfacing.sh "https://raw.githubusercontent.com/deforay/intelis-interfacing/master/scripts/install.sh?v=$(date +%s)"
        ```

        ??? info "Si l'ordinateur de l'outil est sous Windows"

            Télécharger le fichier qui se termine par `-setup.exe` depuis la
            [page des versions](https://github.com/deforay/intelis-interfacing/releases)
            et l'exécuter. Passer ensuite à l'étape 3.

    2. L'exécuter, et saisir le mot de passe quand `sudo` le demande :

        ```bash
        bash install-interfacing.sh
        ```

        ??? info "Pour installer une version précise"

            Ajouter la version à la fin :

            ```bash
            bash install-interfacing.sh --tag v4.2.1
            ```

    ### Ajouter l'automate dans l'outil

    3. Ouvrir l'outil d'interface depuis le menu des applications.
    4. Se connecter avec le Login ID `admin` et le mot de passe `admin`. La
       première connexion ouvre **Settings**.
    5. Sous **System**, régler **Auto-connect on startup** sur **Yes**. Avec
       **No**, l'outil cesse d'écouter après chaque redémarrage, jusqu'à ce que
       quelqu'un se connecte.
    6. Sous **Instruments**, sélectionner **+ Add Instrument** et remplir ces
       champs :

        | Champ | Valeur |
        | --- | --- |
        | Connection Mode | **TCP Server** si l'automate se connecte à cet ordinateur. **TCP Client** si cet ordinateur se connecte à l'automate. Le manuel de l'automate indique lequel. |
        | Communication Protocol | **ASTM**, **ASTM (with checksum)** ou **HL7**, comme réglé sur l'automate. |
        | IP Address | TCP Server : l'adresse de cet ordinateur sur le réseau du laboratoire. TCP Client : l'adresse de l'automate. |
        | Port Number | Le port réglé sur l'automate. |
        | Analyzer Type | Le modèle de l'automate. |
        | Instrument Name/Code | Un nom pour cet automate. L'étape 22 donne le même nom à InteLIS. |

        ??? info "Si le laboratoire a plusieurs automates"

            Ajouter chacun ici avec son propre port. Un seul outil d'interface
            les sert tous.

    7. Sélectionner **Save Settings**.
    8. Passer un échantillon sur l'automate. Vérifier qu'il apparaît parmi les
       résultats reçus dans la console de l'outil.

        ??? failure "Si le résultat n'apparaît pas dans l'outil"

            Lire le journal dans l'onglet de l'automate.
            `Server bound and listening on` signifie que l'outil attend et que
            l'automate ne s'est pas connecté. Suivre le
            [guide de dépannage](https://deforay.github.io/intelis-interfacing/guide/troubleshooting/)
            de l'outil.

            Ne pas continuer tant que l'outil n'affiche pas le résultat. InteLIS
            ne peut importer que ce que détient l'outil.

    9. Trouver l'adresse de cet ordinateur sur le réseau du laboratoire, et la
       noter. Sous Ubuntu, exécuter `hostname -I` et prendre la première
       adresse. Sous Windows, exécuter `ipconfig` et prendre l'**IPv4
       Address** (**Adresse IPv4** sur un poste en français).

        ??? info "Si l'adresse de cet ordinateur change après un redémarrage"

            Demander d'abord à l'administrateur du réseau de lui attribuer une
            adresse fixe. InteLIS n'admet l'outil que depuis cette seule
            adresse. Une nouvelle adresse oblige à répéter les étapes 10 à 21.

    ### Connecter InteLIS à l'outil

    10. Sur la machine InteLIS, ouvrir un terminal et exécuter :

        ```bash
        sudo intelis interface setup
        ```

        ??? info "S'il affiche **What is configured now**"

            L'interfaçage est déjà configuré. Pour le remplacer, répondre
            **Yes** à `Set interfacing up again, replacing this?`. Pour le
            laisser tel quel, appuyer sur Entrée.

        ??? failure "S'il s'arrête avec `The InteLIS database is not reachable`"

            Exécuter `sudo intelis fix-database`. Répéter ensuite l'étape 10.

        ??? failure "Si `intelis` n'est pas reconnu"

            L'installation est plus ancienne. Soit
            [mettre à jour InteLIS](updating-intelis-on-ubuntu.md) et répéter
            l'étape 10, soit faire la configuration à la main :

            1. Ouvrir MySQL :

                ```bash
                sudo mysql
                ```

            2. Créer la base de données et le compte. Mettre l'adresse de
               l'étape 9 à la place de `TOOL_IP`, et un mot de passe long à la
               place de `A-LONG-PASSWORD` :

                ```sql
                CREATE DATABASE IF NOT EXISTS interfacing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
                CREATE USER 'interfacing'@'localhost' IDENTIFIED BY 'A-LONG-PASSWORD';
                CREATE USER 'interfacing'@'127.0.0.1' IDENTIFIED BY 'A-LONG-PASSWORD';
                CREATE USER 'interfacing'@'TOOL_IP' IDENTIFIED BY 'A-LONG-PASSWORD';
                ALTER USER 'interfacing'@'localhost' IDENTIFIED WITH mysql_native_password BY 'A-LONG-PASSWORD';
                ALTER USER 'interfacing'@'127.0.0.1' IDENTIFIED WITH mysql_native_password BY 'A-LONG-PASSWORD';
                ALTER USER 'interfacing'@'TOOL_IP' IDENTIFIED WITH mysql_native_password BY 'A-LONG-PASSWORD';
                GRANT ALL PRIVILEGES ON interfacing.* TO 'interfacing'@'localhost';
                GRANT ALL PRIVILEGES ON interfacing.* TO 'interfacing'@'127.0.0.1';
                GRANT ALL PRIVILEGES ON interfacing.* TO 'interfacing'@'TOOL_IP';
                EXIT;
                ```

               MySQL 8.4 et les versions suivantes rejettent les lignes
               `ALTER USER` avec `Plugin 'mysql_native_password' is not loaded`.
               Sur ces serveurs, les omettre.

            3. Ouvrir le fichier de paramètres d'InteLIS. Sur les installations
               plus anciennes, le dossier est `/var/www/vlsm` :

                ```bash
                sudo nano /var/www/intelis/configs/config.production.php
                ```

            4. Régler ces lignes, avec le même mot de passe :

                ```php
                $systemConfig['interfacing']['enabled'] = true;
                $systemConfig['interfacing']['database']['host'] = '127.0.0.1';
                $systemConfig['interfacing']['database']['username'] = 'interfacing';
                $systemConfig['interfacing']['database']['password'] = 'A-LONG-PASSWORD';
                $systemConfig['interfacing']['database']['db'] = 'interfacing';
                $systemConfig['interfacing']['database']['port'] = 3306;
                ```

            5. Enregistrer avec **Ctrl+O**, puis fermer avec **Ctrl+X**.
            6. Ouvrir le pare-feu à l'ordinateur de l'outil uniquement :

                ```bash
                sudo ufw allow from TOOL_IP to any port 3306 proto tcp
                ```

            Faire ensuite les étapes 14 à 17. Pour le `bind-address`, utiliser
            la première adresse de cette machine donnée par `hostname -I`. À
            l'étape 19, saisir cette adresse comme hôte, le port `3306`, la base
            de données `interfacing`, le nom d'utilisateur `interfacing` et ce
            mot de passe. Ouvrir l'outil une fois pour qu'il crée ses tables,
            puis exécuter `intelis interface-migrate`.

    11. Répondre aux questions :

        | Question | Réponse |
        | --- | --- |
        | How does the Interfacing Tool store the results InteLIS should read? | Appuyer sur Entrée. **MySQL on this machine** est déjà sélectionné. |
        | MySQL port | Appuyer sur Entrée (`3306`). |
        | Database name | Appuyer sur Entrée (`interfacing`). |
        | Where is the Interfacing Tool installed? | **On another computer on the lab network**. |
        | Address of the computer running the tool | L'adresse de l'étape 9. Ne pas saisir `%`, qui admet tous les ordinateurs. |
        | Enter a MySQL administrator account to use? | Posée seulement quand la configuration ne trouve aucun compte qui lui soit propre. Appuyer sur Entrée (Yes), puis saisir le nom d'utilisateur `root` de MySQL et son mot de passe. |
        | Name for the account the tool and InteLIS will use | Appuyer sur Entrée (`interfacing`). |
        | Reuse it, and set its password to the one chosen next? | Posée seulement quand le compte existe déjà. Répondre **Yes** si une configuration précédente de l'interfaçage l'a créé. Sinon, appuyer sur Entrée (No) et choisir un autre nom. |
        | Password for that account | Appuyer sur Entrée. **Use a generated password** est déjà sélectionné. |
        | Open port 3306 to the tool's address? | Posée seulement quand le pare-feu est actif et que le port est fermé. Appuyer sur Entrée (Yes). |

        ??? info "Si la base de données de l'outil d'interface est sur un autre serveur"

            La configuration ne crée rien sur un autre serveur. La base de
            données et son compte doivent déjà y exister, et doivent admettre
            l'ordinateur de l'outil.

            1. À la première question, choisir **MySQL on another server**.
            2. Saisir l'adresse du serveur, le port MySQL et le nom de la base
               de données.
            3. Saisir le nom d'utilisateur et le mot de passe du compte avec
               lequel InteLIS se connecte.

            La configuration vérifie la connexion et cherche la table `orders`
            de l'outil. Passer les étapes 13 à 18. À l'étape 19, saisir les
            informations de ce serveur, et non les valeurs ci-dessous.

    12. Attendre `interfacing is now switched on`. La configuration affiche
        ensuite un tableau sous **Enter these in the Interfacing Tool**. Noter
        les cinq valeurs. L'hôte est l'adresse de cette machine sur le réseau
        du laboratoire.

    ### Permettre à l'ordinateur de l'outil de joindre MySQL

    13. Lire la liste **Still to do** sous le tableau. Si elle ne contient
        aucune ligne commençant par `Set bind-address`, passer à l'étape 18.
    14. Ouvrir le fichier de paramètres de MySQL :

        ```bash
        sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf
        ```

    15. Remplacer la ligne `bind-address` par l'adresse indiquée dans la note,
        puis enregistrer avec **Ctrl+O** et fermer avec **Ctrl+X**. Par
        exemple :

        ```ini
        bind-address = 192.168.1.10
        ```

        Ne pas utiliser `0.0.0.0`. Cette valeur ouvre la base de données à tous
        les réseaux auxquels la machine est reliée.

    16. Vérifier le fichier, puis redémarrer MySQL :

        ```bash
        sudo mysqld --validate-config && sudo systemctl restart mysql
        ```

        ??? failure "Si `--validate-config` affiche une erreur"

            MySQL n'a pas été redémarré. Rouvrir le fichier, corriger la ligne
            indiquée par l'erreur, et répéter l'étape 16.

        ??? failure "Si MySQL ne démarre pas"

            Suivre [MySQL ne démarre pas](mysql-will-not-start.md), puis revenir
            à l'étape 17.

    17. Vérifier que MySQL écoute sur l'adresse du laboratoire :

        ```bash
        sudo ss -lntp | grep 3306
        ```

        L'adresse de l'étape 15 doit apparaître, et non `127.0.0.1`.

    18. Si **Still to do** indique que le port 3306 est fermé, exécuter la
        commande `sudo ufw allow` qu'il affiche.

    ### Saisir la base de données dans l'outil

    19. Sur l'ordinateur de l'outil, ouvrir **Settings**, puis **MySQL**.
        Saisir les valeurs affichées par la configuration :

        | Paramètre affiché | Champ de l'outil |
        | --- | --- |
        | Host | **MySQL Host** |
        | Port | **MySQL Port** |
        | Database | **Database Name** |
        | Username | **Database User** |
        | Password | **Database Password** |

    20. Sélectionner **Test Connection**.

        ??? failure "Si l'outil ne peut pas se connecter"

            - Comparer chaque valeur avec le tableau affiché. Le mot de passe
              est aussi enregistré sur la machine InteLIS dans
              `/var/www/intelis/configs/config.production.php`, sur la ligne
              qui contient `['interfacing']['database']['password']`.
            - Sur la machine InteLIS, répéter la vérification de l'étape 17.
            - Sur la machine InteLIS, exécuter `sudo ufw status`. Le port 3306
              doit être autorisé depuis l'adresse de l'étape 9.
            - Refaire l'étape 9 sur l'ordinateur de l'outil. Si l'adresse a
              changé, répéter les étapes 10 à 20 avec la nouvelle adresse.
            - Si **Still to do** indiquait `This MySQL no longer offers
              mysql_native_password`, contacter le support avec la version de
              l'outil.

    21. Sélectionner **Save Settings**.

    ### Enregistrer l'automate dans InteLIS

    22. Ajouter l'automate dans InteLIS en suivant
        [Configurer un instrument](../user-guides/admin-instruments.md). Saisir
        comme **Nom de la machine** la valeur **Instrument Name/Code** de
        l'outil.

    ### Vérifier qu'un résultat arrive

    23. Passer un échantillon sur l'automate, ou renvoyer un résultat terminé
        depuis l'écran de l'automate.
    24. Sur la machine InteLIS, l'importer tout de suite au lieu d'attendre une
        minute :

        ```bash
        sudo intelis interface
        ```

        Le résultat doit être compté :

        ```text
        Connected to MySQL
        # of records from MySQL : 1
        Processing 1 filtered results from Interface Tool
        ```

        ??? failure "S'il compte `0` enregistrement"

            Le résultat n'a pas atteint la base de données que lit InteLIS.

            - Vérifier la colonne **Sync Status** de l'outil. Si chaque résultat
              est `Pending`, répéter l'étape 20.
            - Seuls les résultats définitifs et les passages en échec sont lus.
              Un résultat que l'automate n'a pas finalisé reste dans l'outil.

        ??? failure "S'il affiche `Error while syncing interface results`"

            Ouvrir le fichier le plus récent dans `/var/www/intelis/var/logs` et
            y chercher l'erreur. L'envoyer au support si elle n'indique pas de
            correction.

    25. Ouvrir l'échantillon dans InteLIS. Vérifier que le résultat s'y trouve.

        ??? failure "Si le résultat est compté mais absent d'InteLIS"

            L'ID de l'échantillon sur l'automate doit correspondre au code
            d'échantillon d'une demande enregistrée dans InteLIS, caractère pour
            caractère. Enregistrer la demande, ou corriger l'ID sur l'automate,
            puis répéter les étapes 23 à 25.

    26. Laisser l'outil en marche. InteLIS importe désormais les résultats
        chaque minute.

        ??? failure "Si les résultats arrivent à la main mais pas d'eux-mêmes"

            Le planificateur ne fonctionne pas. Vérifier qu'il est installé :

            ```bash
            sudo crontab -l | grep cron.sh
            ```

            Cette ligne doit apparaître :

            ```text
            * * * * * cd /var/www/intelis && ./cron.sh
            ```

            Si la ligne manque, ou appelle `crunz` directement, voir
            [Scripts de maintenance](maintenance.md). Si la ligne est là,
            vérifier que l'heure de ce fichier date de moins d'une minute :

            ```bash
            ls -l /var/www/intelis/var/.cron_heartbeat
            ```

        ??? info "Pour ajouter un deuxième ordinateur pour l'outil"

            1. Sur le premier ordinateur de l'outil, ouvrir **Settings**, puis
               **Backup & Restore**, et sélectionner **Export Settings**.
               Choisir **Settings and Credentials**.
            2. Installer l'outil sur le deuxième ordinateur, comme aux étapes 1
               à 4.
            3. Sur le deuxième ordinateur, sélectionner **Import Settings** et
               ouvrir le fichier exporté.
            4. Modifier les noms des automates et les ports pour qu'ils
               correspondent aux automates placés à côté du deuxième ordinateur.
            5. Sur la machine InteLIS, répéter les étapes 10 à 12 avec l'adresse
               du deuxième ordinateur. Répondre **Yes** pour remplacer la
               configuration actuelle, et **Yes** pour réutiliser le compte. Le
               premier ordinateur garde son propre mot de passe et continue de
               fonctionner.
            6. Ouvrir le pare-feu au deuxième ordinateur. La configuration ne le
               propose pas une fois le port 3306 ouvert au premier :

                ```bash
                sudo ufw allow from SECOND_TOOL_IP to any port 3306 proto tcp
                ```

            7. Sur le deuxième ordinateur, saisir les nouvelles valeurs
               affichées, comme aux étapes 19 à 21.
