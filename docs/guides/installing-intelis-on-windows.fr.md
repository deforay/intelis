---
description: Installer InteLIS sur une machine Windows avec WampServer, des paramètres PHP et MySQL jusqu'aux tâches planifiées.
audience: [system-admin]
module: [all]
type: how-to
platform: windows
reviewed: 2026-10-29
reviewed_against: 5.8.5
---
# Installer InteLIS sur une machine Windows

Installer InteLIS sur une machine Windows avec WampServer.

La machine a besoin d'une connexion internet et d'un compte doté des droits
d'administrateur. L'installation se place dans `C:\wamp64\www\vlsm` et s'ouvre
à <http://vlsm>.

InteLIS exige **PHP 8.4+ (minimum 8.4.1)**, pour WampServer comme pour la
ligne de commande. PHP 8.2, 8.3 et les versions plus anciennes ne sont pas
pris en charge. Ce guide utilise les chemins de PHP 8.4. Avec PHP 8.5,
remplacer par le nom de son dossier installé.

??? info "Pourquoi le dossier s'appelle `vlsm`"

    InteLIS s'appelait auparavant VLSM. Le dossier d'installation Windows, le
    nom d'hôte du site et la base de données portent encore ce nom, pour
    correspondre aux installations existantes.

**Suivre les étapes de haut en bas. Chaque partie se termine par une
vérification. Ne pas continuer tant que la vérification n'est pas réussie.**

## Préparer Windows

1. Installer toutes les mises à jour Windows en attente.
2. Installer un éditeur de texte : Notepad++ ou Microsoft VS Code.
3. Télécharger les paquets Visual C++ :
   <https://wampserver.aviatechno.net/files/vcpackages/all_vc_redist_x86_x64.zip>
4. Extraire le fichier zip et installer les paquets qu'il contient. Sur
   Windows 64 bits, les installer tous. Sur Windows 32 bits, installer
   uniquement les paquets x86.
5. Redémarrer la machine.
6. Télécharger WampServer depuis <https://www.wampserver.com/en/>. Choisir le
   programme d'installation 64 bits sur Windows 64 bits.
7. Installer WampServer dans `C:\wamp64`.
8. Démarrer WampServer.

**Vérification :** l'icône de WampServer dans la zone de notification devient
verte.

## Configurer PHP

9. Télécharger `cacert.pem` depuis <https://curl.se/docs/caextract.html> et
   l'enregistrer sous `C:\wamp64\cacert.pem`.
10. Sélectionner l'icône de WampServer dans la zone de notification, puis
    **PHP → Version**. Choisir la version la plus récente qui commence par
    `8.4`.
11. Ouvrir `C:\wamp64\bin\php` dans **File Explorer** (**Explorateur de
    fichiers** sur un poste en français). Noter le nom du dossier qui
    commence par `php8.4`, par exemple `php8.4.1`. Les étapes suivantes
    l'appellent `php8.4.x`.
12. Sélectionner l'icône de WampServer dans la zone de notification, puis
    **PHP → php.ini**. Modifier ces paramètres, puis enregistrer le fichier :

    | Paramètre | Nouvelle valeur |
    | --- | --- |
    | `memory_limit` | `2G`, ou plus si la machine le permet |
    | `post_max_size` | `500M` |
    | `upload_max_filesize` | `500M` |
    | `max_execution_time` | `1200` |
    | `error_reporting` | `E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED & ~E_WARNING` |
    | `;openssl.cafile=` | `openssl.cafile='C:\wamp64\cacert.pem'` (retirer le `;` du début) |
    | `;curl.cainfo =` | `curl.cainfo ='C:\wamp64\cacert.pem'` (retirer le `;` du début) |

13. Faire les mêmes modifications dans
    `C:\wamp64\bin\php\php8.4.x\php.ini`. La ligne de commande utilise ce
    fichier.
14. Activer les extensions PHP dont InteLIS a besoin : `bcmath`, `curl`,
    `fileinfo`, `gd`, `gettext`, `intl`, `json`, `mbstring`, `openssl`,
    `pdo`, `pdo_mysql`, `sodium`, `zip` et `zlib`.

    - Sélectionner l'icône de WampServer dans la zone de notification, puis
      **PHP → PHP extensions** (**PHP → Extensions PHP** sur un poste en
      français). Sélectionner chacune de ces extensions qui n'est pas cochée.
      Une extension absente de la liste est intégrée à PHP.
    - Dans `C:\wamp64\bin\php\php8.4.x\php.ini`, retirer le `;` du début de
      la ligne `extension=` de chacune de ces extensions, par exemple
      `;extension=intl`. Enregistrer le fichier.

15. Ouvrir **Command Prompt** (**Invite de commandes**) et exécuter :

    ```bat
    set PATH=C:\wamp64\bin\php\php8.4.x;%PATH%
    php -v
    php -i | findstr memory_limit
    php -m
    ```

**Vérification :** `php -v` indique PHP 8.4.1 ou plus récent, la deuxième
commande affiche `memory_limit => 2G`, et `php -m` liste toutes les extensions
de l'étape 14.

## Configurer MySQL

16. Sélectionner l'icône de WampServer dans la zone de notification, puis
    **MySQL → my.ini**.
17. Trouver la ligne `sql_mode` et placer `;` à son début.
18. Ajouter ces lignes en dessous :

    ```ini
    sql_mode =
    innodb_strict_mode = 0
    ```

19. Trouver `innodb_default_row_format=compact` et le remplacer par
    `innodb_default_row_format=dynamic`. Si la ligne est absente, ajouter
    `innodb_default_row_format=dynamic`.
20. Enregistrer le fichier.
21. Sélectionner l'icône de WampServer dans la zone de notification, puis
    **MySQL → MySQL Console** (**MySQL → Console MySQL** sur un poste en
    français). Se connecter en tant que `root` avec un mot de passe vide.
22. Définir un mot de passe root. Remplacer `PASSWORD` par un nouveau mot de
    passe, et le noter :

    ```sql
    ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY 'PASSWORD';
    FLUSH PRIVILEGES;
    exit;
    ```

    ??? failure "Si MySQL refuse `mysql_native_password`"

        Les versions récentes de MySQL ne chargent pas
        `mysql_native_password`, et répondent
        `Plugin 'mysql_native_password' is not loaded`. Utiliser plutôt la
        méthode par défaut de MySQL. PHP se connecte avec elle.

        ```sql
        ALTER USER 'root'@'localhost' IDENTIFIED BY 'PASSWORD';
        FLUSH PRIVILEGES;
        exit;
        ```

23. Sélectionner l'icône de WampServer dans la zone de notification, puis
    **Restart All Services** (**Redémarrer les services** sur un poste en
    français).

**Vérification :** l'icône de la zone de notification redevient verte, et
<http://localhost/phpmyadmin> accepte `root` avec le nouveau mot de passe.

## Obtenir InteLIS

24. Télécharger la version actuelle :
    <https://github.com/deforay/intelis/archive/refs/heads/stable.zip>
25. Extraire le fichier zip. Il contient un seul dossier, `intelis-stable`.
26. Créer le dossier `C:\wamp64\www\vlsm`.
27. Copier tout le contenu de `intelis-stable` dans `C:\wamp64\www\vlsm`.
28. Télécharger `composer.phar` depuis
    <https://getcomposer.org/download/latest-stable/composer.phar> et
    l'enregistrer dans `C:\wamp64\www\vlsm`.
29. Dans Command Prompt, installer les paquets :

    ```bat
    cd C:\wamp64\www\vlsm
    set PATH=C:\wamp64\bin\php\php8.4.x;%PATH%
    php composer.phar install --no-dev --no-scripts
    php composer.phar dump-autoload -o
    ```

**Vérification :** le fichier `C:\wamp64\www\vlsm\vendor\autoload.php` existe.

## Créer la base de données

30. Ouvrir <http://localhost/phpmyadmin> et se connecter en tant que `root`.
31. Sélectionner **SQL**, exécuter ceci, et sélectionner **Go** (**Exécuter**
    sur un poste en français) :

    ```sql
    CREATE DATABASE `vlsm` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
    ```

32. Sélectionner la base de données `vlsm` dans le panneau de gauche.
33. Sélectionner **Import** (**Importer** sur un poste en français), choisir
    `C:\wamp64\www\vlsm\sql\init.sql`, et sélectionner **Import** ou **Go** en
    bas de la page.

**Vérification :** la base de données `vlsm` du panneau de gauche liste ses
tables.

## Configurer InteLIS

34. Copier `C:\wamp64\www\vlsm\configs\config.production.dist.php` vers
    `C:\wamp64\www\vlsm\configs\config.production.php`.
35. Ouvrir `config.production.php` dans l'éditeur de texte et définir les
    informations de la base de données :

    ```php
    $systemConfig['database']['host']       = 'localhost';
    $systemConfig['database']['username']   = 'root';
    $systemConfig['database']['password']   = 'PASSWORD';
    $systemConfig['database']['db']         = 'vlsm';
    ```

    Remplacer `PASSWORD` par le mot de passe root de l'étape 22.

36. Si le laboratoire envoie les résultats à un STS, définir son adresse dans
    le même fichier :

    ```php
    $systemConfig['remoteURL'] = 'https://sts.example.org';
    ```

37. Enregistrer le fichier.

## Configurer le serveur web

38. Ouvrir Notepad (Bloc-notes) en tant qu'administrateur. Ouvrir
    `C:\Windows\System32\drivers\etc\hosts` et ajouter cette ligne à la fin :

    ```text
    127.0.0.1 vlsm
    ```

39. Ouvrir `C:\wamp64\bin\apache`. Dans le dossier qui commence par
    `apache2.4`, ouvrir `conf\extra\httpd-vhosts.conf` et ajouter :

    ```apache
    <VirtualHost *:80>
      ServerName localhost
      ServerAlias vlsm
      DocumentRoot "${INSTALL_DIR}/www/vlsm/public"
      <Directory "${INSTALL_DIR}/www/vlsm/public/">
        AddDefaultCharset UTF-8
        Options +Indexes +Includes +FollowSymLinks +MultiViews
        AllowOverride All
        Require local
      </Directory>
    </VirtualHost>
    ```

    ??? info "Si d'autres ordinateurs du laboratoire ouvrent InteLIS sur cette machine"

        `Require local` n'accepte que cette machine. Le remplacer par la plage
        d'adresses du réseau du laboratoire, par exemple :

        ```apache
        Require ip 192.168.1
        ```

        Utiliser `Require all granted` uniquement sur une machine
        inaccessible depuis l'extérieur du réseau du laboratoire. Après la
        modification, ouvrir InteLIS depuis un second ordinateur pour
        confirmer.

40. Sélectionner l'icône de WampServer dans la zone de notification, puis
    **Restart All Services**.

## Initialiser InteLIS

41. Dans Command Prompt, exécuter :

    ```bat
    cd C:\wamp64\www\vlsm
    set PATH=C:\wamp64\bin\php\php8.4.x;%PATH%
    php composer.phar post-install
    ```

42. Répondre aux questions sur le STS à la fin :

    | Question | Réponse |
    | --- | --- |
    | Is this STS URL correct? | Appuyer sur Entrée (Yes). |
    | Enter STS URL (or press Enter to skip) | L'adresse du STS. Appuyer sur Entrée si le laboratoire n'a pas de STS. |
    | Number to select, text to filter | Saisir le numéro de ce laboratoire, puis appuyer sur Entrée. Saisir une partie du nom du laboratoire pour raccourcir la liste. |

    InteLIS ne pose que les questions qui s'appliquent. Si le STS n'a qu'un
    seul laboratoire, il le sélectionne sans demander.

43. Attendre `STS setup complete!`.

    ??? info "Si le laboratoire n'a pas de STS"

        Après Entrée à la question sur le STS, l'exécution se termine par
        `Setup complete. Configure STS URL before proceeding.` et Composer
        signale un code d'erreur pour `sts-setup`. Toutes les parties
        précédentes de `post-install` sont terminées. Continuer à l'étape 44.

    ??? failure "S'il affiche `Cannot connect to STS at this URL`"

        Vérifier l'adresse et la connexion internet, puis saisir de nouveau
        l'adresse. Si l'échec persiste, exécuter plus tard
        `php composer.phar sts-setup` pour reprendre uniquement les questions
        sur le STS.

    ??? failure "S'il affiche `Metadata refresh failed`"

        Le STS n'a pas envoyé la liste des laboratoires. Vérifier la
        connexion internet, puis exécuter `php composer.phar sts-setup`.

## Terminer la configuration dans le navigateur

44. Ouvrir <http://vlsm>. La page de configuration s'ouvre.
45. Sous **Configuration de la base de données**, vérifier que les valeurs
    correspondent à `config.production.php`. Sélectionner **Suivante**.
46. Renseigner **Configuration de l'instance**, puis sélectionner
    **Suivante** :

    | Champ | Valeur |
    | --- | --- |
    | Type d'instance | **LIS avec commande à distance activée** si le laboratoire envoie les résultats à un STS. **Autonome (pas de commande à distance)** dans le cas contraire. |
    | URL STS | L'adresse du STS. Elle est reprise de `config.production.php`. |
    | Laboratoire d'analyse | Le laboratoire choisi à l'étape 42. Si la liste est vide, sélectionner le bouton d'actualisation à côté de l'URL STS. |
    | Choisir les modules à activer | Les tests que réalise ce laboratoire. |
    | Pays d'installation | Le pays où se trouve le laboratoire. |
    | Fuseau horaire | Le fuseau horaire du laboratoire. |
    | Choisir la langue du système | La langue des écrans. |

47. Renseigner **Configuration de l'administrateur** avec l'email, le nom
    complet, l'identifiant de connexion et le mot de passe du premier
    administrateur du laboratoire. Sélectionner **Terminer**.
48. Se connecter avec ce compte administrateur.

**Vérification :** le tableau de bord s'ouvre, et le pied de page affiche la
version, par exemple `v5.7.72`.

## Enregistrer l'administrateur système

49. Ouvrir <http://vlsm/system-admin>. La page **Enregistrer un nouvel
    administrateur de système** s'ouvre.
50. Ouvrir `C:\wamp64\www\vlsm\var\secret-key.txt` dans l'éditeur de texte.
    Copier la clé.
51. Coller la clé dans **Clé secrète**. Renseigner le nom d'utilisateur,
    l'email, l'identifiant de connexion et le mot de passe. Sélectionner
    **Envoyer**.
52. Se déconnecter de l'espace d'administration système.

## Planifier les tâches de fond

53. Ouvrir **Task Scheduler** (**Planificateur de tâches**) et sélectionner
    **Create Task** (**Créer une tâche**). La nommer `InteLIS Task`.
54. Dans l'onglet **General** (**Général**), sélectionner **Run whether user
    is logged on or not** (**Exécuter même si l'utilisateur n'est pas
    connecté**).
55. Dans l'onglet **Triggers** (**Déclencheurs**), créer un déclencheur :

    - Sélectionner **Daily** (**Tous les jours**).
    - Sélectionner **Repeat task every** (**Répéter la tâche toutes les**) et
      régler sur 1 minute, pour une durée **Indefinitely**
      (**Indéfiniment**).
    - Sélectionner **Stop task if it runs longer than** (**Arrêter la tâche
      si elle s'exécute plus de**) et garder 3 jours.

56. Dans l'onglet **Actions** (**Actions**), créer une action :

    | Champ | Valeur |
    | --- | --- |
    | Program/script (Programme/script) | `C:\wamp64\bin\php\php8.4.x\php.exe` |
    | Add arguments (Ajouter des arguments) | `C:\wamp64\www\vlsm\vendor\bin\crunz schedule:run` |
    | Start in (Commencer dans) | `C:\wamp64\www\vlsm` |

    **Start in** est obligatoire. Sans lui, la tâche s'exécute mais ne fait
    rien : pas de sauvegardes, pas de synchronisation et pas d'import de
    résultats.

57. Sélectionner **OK** et saisir le mot de passe Windows à la demande.

**Vérification :** au bout de deux minutes, le fichier
`C:\wamp64\www\vlsm\var\.cron_heartbeat` affiche une heure de modification
datant de la dernière minute ou des deux dernières minutes.

## Connecter des automates

58. Si le laboratoire connecte des automates, suivre
    [Connecter un automate](setting-up-interfacing-tool.md).

    ??? info "Différences sous Windows"

        - `intelis interface setup` n'existe pas sous Windows. Là où le guide
          le propose, suivre ses étapes pour faire le même travail à la main.
        - Créer la base de données `interfacing` en important
          `C:\wamp64\www\vlsm\sql\interface-init.sql` dans phpMyAdmin.
        - Exécuter le SQL du guide dans l'onglet **SQL** de phpMyAdmin.
        - Modifier `C:\wamp64\www\vlsm\configs\config.production.php` au lieu
          du chemin Ubuntu.
        - Passer les commandes propres à Ubuntu : `nano`, `setfacl`, `ufw` et
          `systemctl`.
