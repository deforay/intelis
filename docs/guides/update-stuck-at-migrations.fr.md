---
description: Débloquer une mise à jour d'InteLIS qui s'arrête à "Running database migrations...", et terminer une mise à jour qui a échoué en cours de route.
audience: [system-admin]
module: [all]
type: how-to
platform: ubuntu
reviewed: 2026-10-29
reviewed_against: 5.8.5
---
# Mise à jour bloquée aux migrations de la base de données

Une mise à jour affiche `Running database migrations...` puis plus rien. Dans
presque tous les cas, l'étape de la base de données attend la fin d'une autre
requête. Les données ne risquent rien. Rien n'est modifié pendant cette
attente.

**Choisir la situation qui correspond, puis suivre ses étapes de haut en bas.**

=== "La mise à jour attend encore"

    Utiliser cette situation quand la mise à jour affiche
    `Running database migrations...` depuis plus de 10 minutes, et que le
    terminal est encore ouvert.

    Laisser la mise à jour tourner. Ne pas appuyer sur Ctrl+C.

    1. Ouvrir un second terminal sur la machine InteLIS.
    2. Lister ce que fait MySQL :

        ```bash
        sudo mysql -e "SHOW FULL PROCESSLIST"
        ```

    3. Repérer les lignes qui portent `Waiting for table metadata lock` dans la
       colonne `State`. Ce sont la mise à jour et tout ce qui attend derrière
       elle. Ne pas les arrêter.
    4. Repérer la requête qu'elles attendent. Elle porte sur la même table,
       n'attend pas elle-même, et a le plus grand nombre dans la colonne
       `Time` (en secondes). Noter son `Id`.

        ??? info "Si aucune requête en cours ne se distingue"

            Une transaction ouverte peut bloquer la table alors que sa
            connexion affiche `Sleep`. Lister les transactions ouvertes, de la
            plus ancienne à la plus récente :

            ```bash
            sudo mysql -e "SELECT trx_mysql_thread_id AS Id, trx_started, trx_query FROM information_schema.innodb_trx ORDER BY trx_started"
            ```

            La première ligne est presque toujours celle qui bloque la table.
            Noter son `Id`.

    5. Vérifier ce que fait la requête de l'étape 4, dans la colonne `Info` :
        - Si elle commence par `SELECT`, elle ne fait que lire. L'arrêter ne
          fait rien perdre. Passer à l'étape 6.
        - Si elle modifie des données (`INSERT`, `UPDATE`, `DELETE`, `ALTER`),
          attendre qu'elle se termine. Si elle tourne encore au bout d'une
          heure, contacter le support avec la sortie de l'étape 2.
    6. Arrêter la requête, avec l'`Id` de l'étape 4 à la place de `12345` :

        ```bash
        sudo mysql -e "KILL 12345"
        ```

    7. Revenir au premier terminal. La mise à jour reprend d'elle-même en
       moins d'une minute.

        ??? failure "S'il affiche `still waiting on a lock after 30s`"

            La mise à jour réessaie. Elle fait trois tentatives, à 30 secondes
            d'intervalle. Refaire les étapes 2 à 6 pendant les tentatives.

    8. Attendre la fin de la mise à jour. Puis la vérifier, comme dans
       [Vérifier la mise à jour](updating-intelis-on-ubuntu.md).

=== "La mise à jour a échoué ou a été arrêtée"

    Utiliser cette situation quand la mise à jour s'est terminée par
    `Failed to update`, ou a été arrêtée par Ctrl+C ou par la fermeture du
    terminal.

    1. Lire les lignes au-dessus de `Failed to update`.

        ??? failure "Si elles se terminent par `another connection has held a lock on ... for over 90s`"

            Une longue requête bloquait une table dont la mise à jour avait
            besoin. L'arrêter comme dans la situation **La mise à jour attend
            encore**, étapes 2 à 6. Puis continuer avec l'étape 2 ci-dessous.

    2. Relancer la mise à jour. La relancer ne présente aucun risque. Chaque
       étape déjà terminée est sautée :

        ```bash
        sudo intelis update
        ```

    3. Vérifier le résultat :

        ```bash
        sudo intelis check
        ```

        Les lignes `Schema version` et `Audit triggers` doivent afficher `OK`.

    Si la mise à jour échoue de nouveau au même endroit, utiliser la situation
    **Bloqué sur une ancienne version**.

=== "Bloqué sur une ancienne version"

    Utiliser cette situation quand chaque mise à jour échoue à l'étape de la
    base de données, ou que `intelis check` signale que la base de données est
    en retard. Le pied de page affiche aussi un avertissement rouge `DB ver.`
    avec `(database not fully migrated)`.

    1. Confirmer que la base de données est en retard :

        ```bash
        sudo intelis check
        ```

        La ligne `Schema version` indique
        `database is at 5.6.2, migrations go to 5.7.78`, avec les versions de
        cette machine.

    2. Lancer les migrations seules :

        ```bash
        sudo intelis migrate
        ```

    3. Lire la sortie.
        - Si elle affiche `Halting further migrations after`, la migration
          nommée à cet endroit contient une erreur qu'une nouvelle exécution
          ne corrige pas. Contacter le support. Envoyer toute la sortie de
          l'étape 2.
        - Sinon, vérifier la ligne `DB version` du tableau
          `Migration summary`. Elle se termine à la version la plus récente.
          Passer à l'étape 4.
    4. Remettre en place les déclencheurs d'audit :

        ```bash
        sudo intelis audit-triggers-install
        ```

    5. Exécuter de nouveau `sudo intelis check`. `Schema version` et
       `Audit triggers` doivent afficher `OK`.

    !!! warning "Ne pas modifier à la main le numéro de version dans la base de données"

        La version enregistre les migrations déjà exécutées. La fixer à la
        main saute ou répète des migrations, et laisse la base de données dans
        un état qu'aucune mise à jour ne peut réparer.

## Après une mise à jour qui a échoué

??? failure "Les imports, les synchronisations et les sauvegardes se sont arrêtés après l'échec de la mise à jour"

    La mise à jour suspend les tâches planifiées pendant l'étape de la base de
    données. La suspension se lève d'elle-même au bout de 30 minutes. Pour la
    lever tout de suite, suivre l'étape 3 de
    [Vérifier que les tâches planifiées s'exécutent](maintenance.md#verifier-que-les-taches-planifiees-sexecutent).

??? failure "`intelis check` signale `no audit trigger on:`"

    La mise à jour s'est arrêtée entre la suppression et la remise en place
    des déclencheurs d'audit. Les modifications de ces tables ne sont pas
    enregistrées tant que les déclencheurs ne sont pas revenus :

    ```bash
    sudo intelis audit-triggers-install
    ```

??? info "Où se trouve le journal de la mise à jour"

    Chaque mise à jour écrit un journal dans
    `/tmp/intelis-upgrade-<date>-<time>.log`. Envoyer le plus récent en
    contactant le support.
