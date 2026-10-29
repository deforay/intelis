---
description: "Réparer les erreurs \"Illegal mix of collations\" en convertissant les bases de données InteLIS et d'interfaçage vers une seule collation."
audience: [system-admin]
module: [all]
type: how-to
platform: ubuntu
reviewed: 2026-10-29
reviewed_against: 5.8.5
---
# Corriger les erreurs de collation illégale ou incohérente

InteLIS affiche une erreur `Illegal mix of collations`, en général après la
restauration d'une base de données venant d'un autre serveur MySQL ou MariaDB.

1. Ouvrir un terminal sur la machine InteLIS et faire une sauvegarde. La
   réparation réécrit chaque table qui utilise une autre collation.

    ```bash
    intelis backup
    ```

2. Lancer la réparation de la collation :

    ```bash
    intelis db:collation
    ```

    Elle convertit la base de données InteLIS, et la base de données
    d'interfaçage quand l'interfaçage est activé. Elle s'exécute avec le compte
    du serveur web et peut d'abord demander le mot de passe administrateur.

    ??? info "Si `intelis` n'est pas reconnu"

        L'installation est plus ancienne. Lancer directement la réparation :

        ```bash
        cd /var/www/intelis && sudo -u www-data php vendor/bin/db-tools collation --all
        ```

        Sur les installations plus anciennes, utiliser `/var/www/vlsm` à la
        place de `/var/www/intelis`.

3. Vérifier qu'il ne reste rien à convertir :

    ```bash
    intelis db:collation -- --dry-run
    ```

    Chaque base de données indique `0 need conversion`.

    ??? warning "Garder le `--` avant `--dry-run`"

        Sans lui, l'option `--dry-run` est perdue et la commande convertit les
        tables au lieu de seulement produire un rapport.

`intelis update` lance la même réparation à chaque mise à jour.

??? info "Convertir étape par étape avec db-tools"

    Pour examiner les modifications avant de les appliquer, lancer db-tools
    directement depuis le dossier d'InteLIS :

    ```bash
    cd /var/www/intelis
    ```

    Lister ce qui changerait, sans rien modifier :

    ```bash
    sudo -u www-data php vendor/bin/db-tools collation --all --dry-run
    ```

    Appliquer la conversion :

    ```bash
    sudo -u www-data php vendor/bin/db-tools collation --all
    ```

    `--all` couvre les deux bases de données. Sans cette option, seule la base
    de données InteLIS est convertie.

??? info "Ce que cela corrige"

    L'erreur apparaît quand une requête compare des colonnes de texte stockées
    avec des collations différentes. La réparation choisit la collation d'après
    la version du serveur et convertit chaque table et chaque colonne vers
    celle-ci. MySQL 8 reçoit `utf8mb4_0900_ai_ci`. MariaDB et MySQL 5.7
    reçoivent `utf8mb4_unicode_ci`.

    Ne pas passer les tables à `utf8mb4_general_ci` à la main, par exemple dans
    phpMyAdmin. Les tables que les mises à jour suivantes créent ou modifient
    reçoivent la collation par défaut du serveur. L'erreur revient donc après la
    mise à jour suivante.
