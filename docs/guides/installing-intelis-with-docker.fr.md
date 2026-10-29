---
description: Exécuter InteLIS dans des conteneurs Docker sur un serveur de laboratoire ou une machine de développement, y compris les mises à jour et la réinitialisation de la base de données.
audience: [system-admin, developer]
module: [all]
type: how-to
platform: docker
reviewed: 2026-10-29
reviewed_against: 5.8.5
---
# Installer InteLIS avec Docker

Exécuter InteLIS dans des conteneurs Docker, sur un serveur de laboratoire ou
sur une machine de développement.

La machine a besoin de [Docker](https://docs.docker.com/get-docker/) avec le
plugin [Docker Compose](https://docs.docker.com/compose/install/), de `git` et
d'une connexion internet. Les étapes du serveur de laboratoire supposent un
hôte Ubuntu.

Le conteneur doit exécuter **PHP 8.4+ (minimum 8.4.1)**. PHP 8.2, 8.3 et les
versions plus anciennes ne sont pas pris en charge. L'image par défaut utilise
PHP 8.4.

**Choisir la situation qui correspond, puis suivre ses étapes de haut en bas.**

=== "Serveur de laboratoire"

    ### Installer

    1. Ouvrir un terminal et télécharger InteLIS :

        ```bash
        cd ~ && git clone https://github.com/deforay/intelis.git
        ```

    2. Créer le fichier de paramètres :

        ```bash
        cd ~/intelis && cp .env.example .env
        ```

    3. Ouvrir le fichier de paramètres :

        ```bash
        nano .env
        ```

    4. Définir un nouveau mot de passe root MySQL sur la ligne
       `MYSQL_ROOT_PASSWORD=`. Le noter. Ne pas le modifier après le premier
       démarrage.

        ```ini
        MYSQL_ROOT_PASSWORD=choose-a-strong-password
        ```

        Si la ligne reste vide, la base de données utilise le mot de passe
        `root_password`.

        ??? info "Si le port 80 ou 3306 est déjà utilisé sur cette machine"

            Modifier `APACHE_PORT` ou `MYSQL_PORT`, par exemple en `8080` ou
            `3307`. Ce sont les ports de cette machine uniquement. Dans les
            conteneurs, Apache écoute toujours sur le port 80 et MySQL sur le
            port 3306.

        ??? info "Tous les paramètres de `.env`"

            | Paramètre | Valeur par défaut | Ce qu'il définit |
            | --- | --- | --- |
            | `DOMAIN` | `intelis` | Le nom auquel Apache répond. Toute autre adresse qui atteint le serveur fonctionne aussi. |
            | `APACHE_PORT` | `80` | Le port du serveur web sur cette machine. |
            | `UBUNTU_VERSION` | `24.04` | La version d'Ubuntu sur laquelle l'image du conteneur est construite. |
            | `PHP_VERSION` | `8.4` | La version de PHP installée dans l'image du conteneur. Utiliser `8.4` ou `8.5`. |
            | `MYSQL_ROOT_PASSWORD` | `root_password` si vide | Le mot de passe root MySQL. |
            | `MYSQL_PORT` | `3306` | Le port de MySQL sur cette machine. |
            | `MYSQL_DATABASE` | `vlsm` | Le nom de la base de données principale. Le laisser à `vlsm`. |
            | `INTERFACING_ENABLED` | `true` | Crée la base de données d'interfaçage. |
            | `INTERFACE_DB_HOST` | `intelis-db` | L'hôte de la base de données d'interfaçage. |
            | `INTERFACE_DB_PORT` | `3306` | Le port de la base de données d'interfaçage. |
            | `INTERFACE_DB_USER` | `root` | L'utilisateur de la base de données d'interfaçage. |
            | `INTERFACE_DB_PASSWORD` | `MYSQL_ROOT_PASSWORD` si vide | Le mot de passe de la base de données d'interfaçage. |
            | `INTERFACE_DB_NAME` | `interfacing` | Le nom de la base de données d'interfaçage. |

            Une modification de `UBUNTU_VERSION` ou de `PHP_VERSION` ne prend
            effet qu'après `docker compose up -d --build`.

    5. Enregistrer le fichier avec **Ctrl+O**, appuyer sur Entrée, puis le
       fermer avec **Ctrl+X**.
    6. Démarrer InteLIS :

        ```bash
        docker compose up -d
        ```

        ??? failure "S'il indique `permission denied` pour `docker.sock`"

            Le compte n'a pas le droit d'exécuter Docker. Placer `sudo` devant
            chaque commande `docker compose` de cette page.

    7. Suivre le premier démarrage :

        ```bash
        docker compose logs -f intelis
        ```

        Le premier démarrage construit l'image du conteneur, crée les bases
        de données et exécute toutes les migrations. Il prend plusieurs
        minutes. Attendre une ligne qui commence par `Run-once:`, puis appuyer
        sur **Ctrl+C** pour arrêter le suivi. Les conteneurs continuent de
        tourner. Remonter et vérifier qu'aucune ligne au-dessus ne signale
        d'erreur. La commande `intelis check` de l'étape 8 le confirme.

        ??? failure "Si le journal affiche `Access denied for user 'root'`"

            Le mot de passe de `.env` diffère de celui utilisé au premier
            démarrage de la base de données. La base de données garde son
            premier mot de passe. Remettre ce mot de passe dans `.env`, puis
            exécuter de nouveau `docker compose up -d`.

    8. Vérifier l'installation :

        ```bash
        docker compose exec intelis intelis check
        ```

        Chaque ligne commence par l'un de ces mots :

        | Mot | Signification |
        | --- | --- |
        | `PASS` | La vérification a réussi. |
        | `WARN` | InteLIS fonctionne, mais le réglage ne convient pas à un laboratoire. La ligne indique ce qu'il faut modifier. |
        | `FAIL` | InteLIS ne peut pas fonctionner correctement tant que ce point n'est pas corrigé. La ligne donne la commande qui le corrige. |
        | `SKIP` | La vérification ne s'applique pas à cette machine. |

        Exécuter chaque commande `intelis` indiquée dans le conteneur, sous la
        forme `docker compose exec intelis intelis <command>`. Puis vérifier
        de nouveau.

    ### Configurer dans le navigateur


    L'assistant de configuration s'affiche en anglais, car la langue n'est
    pas encore choisie. Les libellés ci-dessous sont ceux de l'écran.

    9. Depuis un ordinateur du réseau, ouvrir `http://` suivi de l'adresse IP
       du serveur, par exemple `http://192.168.1.20/`. Sur le serveur
       lui-même, ouvrir <http://localhost/>. Si `APACHE_PORT` n'est pas 80,
       l'ajouter après l'adresse, par exemple `http://192.168.1.20:8080/`. La
       page **Database Setup** (configuration de la base de données) s'ouvre.
    10. Les informations de la base de données sont déjà renseignées.
        Sélectionner **Next** (suivant).
    11. Sur **Instance Setup** (configuration de l'instance), renseigner :

        | Champ | Réponse |
        | --- | --- |
        | Instance type | **LIS with Remote Ordering Enabled** (LIS avec commande à distance). Si le laboratoire n'a pas de STS, choisir **Standalone (no Remote Ordering)** (autonome, sans commande à distance). |
        | STS URL | L'adresse du STS, par exemple `https://sts.example.org`. La demander au programme national. |
        | Testing lab | Le laboratoire que dessert ce serveur. Sélectionner le bouton d'actualisation à côté de l'URL STS pour charger la liste depuis le STS. |
        | Modules to enable | Les tests que réalise ce laboratoire. |
        | Country of installation | Le formulaire de demande du pays. |
        | Timezone | Le fuseau horaire du laboratoire. |
        | System language | La langue des écrans. |

    12. Sélectionner **Next**.
    13. Sur **Admin Setup** (configuration de l'administrateur),
        saisir l'adresse e-mail (**Email ID**), le nom complet, l'identifiant de
        connexion et le mot de passe du premier administrateur. Le mot de
        passe doit compter au moins 8 caractères, dont au moins une lettre et
        un chiffre.
    14. Sélectionner **Finish** (terminer). La page de connexion s'ouvre.

    ### Configurer les sauvegardes

    Le conteneur enregistre une sauvegarde de la base de données toutes les
    six heures dans `~/intelis/backups/db` sur le serveur. La copie hors du
    serveur s'exécute sur le serveur lui-même, en dehors des conteneurs.

    15. Dans un terminal sur le serveur, lancer la configuration des
        sauvegardes :

        ```bash
        cd ~/intelis && sudo bash scripts/remote-backup.sh
        ```

    16. À `InteLIS folder path` (chemin du dossier InteLIS), saisir le chemin
        complet du dossier `intelis`, par exemple `/home/labadmin/intelis`. La
        commande `echo ~/intelis` l'affiche.
    17. Répondre aux autres questions. [Configurer les sauvegardes hors de la machine](setting-up-off-machine-backups.md)
        les détaille pour chaque destination.

    ### Vérifier le laboratoire

    18. Se connecter avec l'identifiant de connexion et le mot de passe de
        l'étape 13.
    19. Vérifier les paramètres du laboratoire sous **ADMIN → Paramètres →
        Configuration générale**.
    20. Dans un terminal sur le serveur, confirmer que les sauvegardes hors du
        serveur fonctionnent :

        ```bash
        sudo /usr/local/bin/intelis-backup.sh --status
        ```

    !!! danger "Ne jamais exécuter `docker compose down -v` sur un serveur de laboratoire"

        Le `-v` supprime le volume de la base de données et tous les
        enregistrements qu'il contient, sans demande de confirmation et sans
        retour possible.

=== "Mettre à jour un serveur de laboratoire"

    Suivre ces étapes sur un serveur de laboratoire installé avec Docker.

    1. Ouvrir un terminal et aller dans le dossier InteLIS :

        ```bash
        cd ~/intelis
        ```

    2. Faire une nouvelle sauvegarde :

        ```bash
        docker compose exec intelis intelis backup
        ```

        Attendre `Database and settings saved on this machine`. Quand la
        commande demande ensuite `Set up off-machine backups now?`, appuyer
        sur Entrée (No). La copie hors du serveur s'exécute en dehors des
        conteneurs.

    3. Lancer la mise à jour :

        ```bash
        sudo ./scripts/docker-upgrade.sh -b
        ```

        `-b` saute la question de sauvegarde du script, car l'étape 2 a déjà
        fait une sauvegarde. Le script :

        - télécharge le code le plus récent de la branche `master` de
          `github.com/deforay/intelis` par-dessus ce dossier
        - ne touche pas à `.env`, `configs/config.production.php`,
          `docker-compose.override.yml`, `public/uploads/`, `public/temporary/`,
          `var/` et `backups/`
        - actualise les dépendances Composer uniquement si `composer.json` ou
          `composer.lock` a changé
        - redémarre les conteneurs, ce qui exécute les migrations et les
          scripts run-once

    4. Attendre `Upgrade complete!`.
    5. Suivre le redémarrage :

        ```bash
        docker compose logs -f intelis
        ```

        Attendre une ligne qui commence par `Run-once:`, puis appuyer sur
        **Ctrl+C**. Remonter et vérifier qu'aucune ligne au-dessus ne signale
        d'erreur. La commande `intelis check` de l'étape 6 le confirme.

        ??? failure "Si la mise à jour s'est arrêtée en cours de route"

            Redémarrer les conteneurs et exécuter de nouveau les migrations,
            sans télécharger le code une seconde fois :

            ```bash
            sudo ./scripts/docker-upgrade.sh -b -s
            ```

            Si elle échoue encore, enregistrer le journal et l'envoyer au
            support :

            ```bash
            docker compose logs intelis > update-log.txt
            ```

    6. Vérifier l'installation :

        ```bash
        docker compose exec intelis intelis check
        ```

        Une ligne `FAIL` donne la commande qui corrige le problème.
        L'exécuter sous la forme
        `docker compose exec intelis intelis <command>`.

    7. Se connecter à InteLIS dans le navigateur.

=== "Machine de développement"

    ### Démarrer

    1. Télécharger le code :

        ```bash
        git clone https://github.com/deforay/intelis.git
        cd intelis
        ```

    2. Créer le fichier de paramètres :

        ```bash
        cp .env.example .env
        ```

    3. Définir `MYSQL_ROOT_PASSWORD` dans `.env`. Si la valeur reste vide, la
       base de données utilise le mot de passe `root_password`.
    4. Si le port 80 ou 3306 est déjà utilisé, modifier `APACHE_PORT` ou
       `MYSQL_PORT` dans `.env`. Ce sont les ports de l'hôte uniquement. Dans
       les conteneurs, Apache écoute toujours sur le port 80 et MySQL sur le
       port 3306.

        | Paramètre | Valeur par défaut | Ce qu'il définit |
        | --- | --- | --- |
        | `DOMAIN` | `intelis` | Le nom auquel Apache répond. `localhost` fonctionne aussi. |
        | `APACHE_PORT` | `80` | Le port de l'hôte pour le serveur web. |
        | `UBUNTU_VERSION` | `24.04` | La version d'Ubuntu sur laquelle l'image du conteneur est construite. |
        | `PHP_VERSION` | `8.4` | La version de PHP installée dans l'image du conteneur. Utiliser `8.4` ou `8.5`. |
        | `MYSQL_ROOT_PASSWORD` | `root_password` si vide | Le mot de passe root MySQL. |
        | `MYSQL_PORT` | `3306` | Le port de l'hôte pour MySQL. |
        | `MYSQL_DATABASE` | `vlsm` | Le nom de la base de données principale. Le laisser à `vlsm`. |
        | `INTERFACING_ENABLED` | `true` | Crée la base de données d'interfaçage. |
        | `INTERFACE_DB_HOST` | `intelis-db` | L'hôte de la base de données d'interfaçage. |
        | `INTERFACE_DB_PORT` | `3306` | Le port de la base de données d'interfaçage. |
        | `INTERFACE_DB_USER` | `root` | L'utilisateur de la base de données d'interfaçage. |
        | `INTERFACE_DB_PASSWORD` | `MYSQL_ROOT_PASSWORD` si vide | Le mot de passe de la base de données d'interfaçage. |
        | `INTERFACE_DB_NAME` | `interfacing` | Le nom de la base de données d'interfaçage. |

    5. Démarrer les conteneurs :

        ```bash
        docker compose up -d
        ```

        Cette commande démarre deux services : `intelis` (Ubuntu avec PHP et
        Apache) et `intelis-db` (MySQL 8.4).

    6. Suivre le premier démarrage :

        ```bash
        docker compose logs -f intelis
        ```

        Attendre une ligne qui commence par `Run-once:`, puis appuyer sur
        **Ctrl+C**. Sur un clone neuf, le premier démarrage installe aussi les
        dépendances Composer dans la copie de travail.

    7. Ouvrir <http://localhost/>, ou `http://localhost:<APACHE_PORT>/` si
       `APACHE_PORT` n'est pas 80.
    8. Terminer la configuration dans le navigateur. Sur **Database Setup**,
       sélectionner **Next**. Sur **Instance Setup**, choisir
       **Standalone (no Remote Ordering)**, sauf si un STS est disponible.
       Sur **Admin Setup**, créer l'administrateur, puis sélectionner
       **Finish**.

    ### Travailler sur le code

    9. Modifier les fichiers dans la copie de travail. Le fichier compose la
       monte dans le conteneur à `/var/www/html`. Un fichier PHP modifié
       prend effet à la requête suivante.

        `docker-compose.yml` monte `docker/php-apache/dev-php.ini` sur toutes
        les installations Docker, serveurs de laboratoire compris. Ce fichier
        active la revalidation d'OPcache et affiche les erreurs PHP à l'écran.

    10. Exécuter les commandes `intelis` dans le conteneur :

        ```bash
        docker compose exec intelis intelis migrate
        ```

    11. Ouvrir un shell ou le client MySQL au besoin :

        ```bash
        docker compose exec intelis bash
        docker compose exec intelis-db mysql -u root -p vlsm
        ```

    12. Après un `git pull`, redémarrer le conteneur de l'application pour
        qu'il exécute les nouvelles migrations :

        ```bash
        docker compose restart intelis
        ```

    13. Après une modification du `Dockerfile`, de `UBUNTU_VERSION` ou de
        `PHP_VERSION`, reconstruire l'image :

        ```bash
        docker compose up -d --build
        ```

    14. Arrêter les conteneurs :

        ```bash
        docker compose down
        ```

    ### Repartir avec une base de données vide

    15. Lister le volume de la base de données et les dernières sauvegardes,
        et confirmer que rien de ce qu'ils contiennent n'est nécessaire :

        ```bash
        docker volume ls | grep intelis_db_data
        ls -lt backups/db | head
        ```

    16. Supprimer les conteneurs et le volume de la base de données :

        ```bash
        docker compose down -v
        ```

        !!! danger "Cette commande supprime la base de données"

            `-v` supprime le volume de la base de données et tous les
            enregistrements qu'il contient, sans demande de confirmation et
            sans retour possible.

    17. Démarrer de nouveau. Le premier démarrage crée une base de données
        neuve :

        ```bash
        docker compose up -d
        ```
