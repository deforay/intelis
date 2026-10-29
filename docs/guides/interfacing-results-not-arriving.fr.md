---
description: Connecter InteLIS à l'outil d'interface en ligne de commande, et importer les résultats des automates tout de suite au lieu d'attendre l'import planifié.
audience: [system-admin, lab-admin]
module: [vl, eid, covid19, hepatitis, tb, cd4, custom-tests]
type: how-to
platform: ubuntu
reviewed: 2026-10-29
reviewed_against: 5.8.5
---
# Les résultats des automates n'arrivent pas

InteLIS importe chaque minute les résultats que détient l'outil d'interface.
Une commande connecte les deux, et la même commande lance l'import à la
demande :

```bash
sudo intelis interface
```

**Choisir la situation qui correspond, puis suivre ses étapes de haut en bas.**

=== "Pas encore connecté"

    Utiliser cette situation quand la commande indique
    `Interfacing is not enabled.` ou
    `Interfacing is enabled, but no analyzer database is configured.`

    1. Ouvrir un terminal sur la machine InteLIS et exécuter :

        ```bash
        sudo intelis interface
        ```

    2. À `Set interfacing up now?`, appuyer sur Entrée pour répondre **Yes**
       (oui).
    3. Répondre aux questions de configuration. Elles demandent où s'exécute
       l'outil d'interface et dans quelle base de données il écrit.
       [Connecter un automate à InteLIS](setting-up-interfacing-tool.md)
       explique chaque réponse.
    4. Attendre la fin de la configuration. La commande lance ensuite le
       premier import d'elle-même. Les résultats comptés doivent s'afficher :

        ```text
        Connected to MySQL
        # of records from MySQL : 3
        Processing 3 filtered results from Interface Tool
        ```

        ??? failure "S'il s'arrête avec `The InteLIS database is not reachable`"

            Exécuter `sudo intelis fix-database`. Puis refaire l'étape 1.

        ??? failure "Si `intelis` n'est pas reconnu"

            L'installation est plus ancienne.
            [Mettre à jour InteLIS](updating-intelis-on-ubuntu.md), puis
            refaire l'étape 1.

        ??? info "Si l'outil d'interface s'exécute sur un autre ordinateur"

            La configuration affiche les paramètres de la base de données à
            saisir dans l'outil. Suivre
            [Saisir la base de données dans l'outil](setting-up-interfacing-tool.md)
            avant d'attendre le moindre résultat.

    Ensuite, l'import planifié s'exécute chaque minute. Les étapes ci-dessus
    ne sont jamais à refaire, sauf si l'outil passe sur un autre ordinateur.
    Pour modifier la connexion plus tard, exécuter
    `sudo intelis interface setup`.

=== "Résultats en retard"

    Utiliser cette situation quand l'interfaçage est connecté, qu'un résultat
    se trouve dans l'outil et qu'il n'est pas encore dans InteLIS.

    1. Exécuter :

        ```bash
        sudo intelis interface
        ```

    2. Vérifier le nombre sur la ligne `# of records from MySQL`.

        ??? failure "S'il affiche `Another instance of the script : interface.php is already running.`"

            L'import planifié est en cours. Attendre une minute, puis refaire
            l'étape 1. Si le message se répète pendant plus de 15 minutes, une
            exécution est bloquée. Forcer une nouvelle exécution :

            ```bash
            sudo intelis interface force
            ```

        ??? failure "S'il compte `0` enregistrement"

            Le résultat n'a pas atteint la base de données que lit InteLIS.

            - Dans l'outil, vérifier la colonne **Sync Status**. Si tous les
              résultats sont `Pending`, l'outil n'écrit pas dans la base de
              données. Refaire **Test Connection** dans les **Settings** de
              l'outil, sous **MySQL**.
            - Seuls les résultats définitifs et les analyses en échec sont
              importés. Un résultat que l'automate n'a pas finalisé reste dans
              l'outil.
            - Le résultat a déjà été lu une fois. Utiliser la situation
              **Anciens résultats manquants**.

        ??? failure "S'il affiche `Error while syncing interface results`"

            Ouvrir le fichier le plus récent de `/var/www/intelis/var/logs` et
            y rechercher l'erreur. L'envoyer au support si elle n'indique pas
            de correction.

    3. Ouvrir l'échantillon dans InteLIS. Vérifier que le résultat s'y trouve.

        ??? failure "Si le résultat est compté mais absent d'InteLIS"

            L'ID de l'échantillon sur l'automate doit correspondre, caractère
            pour caractère, au code d'échantillon d'une demande enregistrée
            dans InteLIS. Enregistrer la demande, ou corriger l'ID sur
            l'automate. Puis utiliser la situation **Anciens résultats
            manquants**.

    4. Si les résultats arrivent désormais à la main mais toujours pas d'eux-mêmes,
       vérifier le planificateur. L'import planifié fait partie de `cron.sh` :

        ```bash
        sudo crontab -l | grep cron.sh
        ```

        Cette ligne doit apparaître :

        ```text
        * * * * * cd /var/www/intelis && ./cron.sh
        ```

        Si la ligne manque, voir [Scripts de maintenance](maintenance.md).

=== "Anciens résultats manquants"

    Utiliser cette situation quand des résultats ont atteint l'outil avant
    l'enregistrement de la demande, ou avant la correction de l'ID de
    l'échantillon. Chaque import ne lit que ce qui est arrivé depuis le
    précédent. Un résultat qui n'a trouvé aucun échantillon correspondant
    n'est donc pas relu.

    1. Choisir jusqu'où remonter. Indiquer un nombre de jours, ou une date au
       format `YYYY-MM-DD` :

        ```bash
        sudo intelis interface 7
        ```

        ```bash
        sudo intelis interface 2026-09-01
        ```

    2. Vérifier le nombre sur la ligne `# of records from MySQL`. Il inclut
       tous les résultats de cette période, pas seulement les résultats
       manquants. Les résultats déjà présents dans InteLIS restent tels quels.
    3. Ouvrir l'échantillon dans InteLIS. Vérifier que le résultat s'y trouve.

        ??? failure "Si les résultats de l'échantillon sont verrouillés"

            Un import normal ne touche pas à un échantillon verrouillé.
            Utiliser la situation **Échantillon verrouillé**.

    !!! warning "Garder la période courte"

        Si un résultat a été corrigé à la main dans InteLIS après son envoi
        par l'automate, relire cette période remet le résultat de l'automate.
        InteLIS garde le résultat remplacé dans l'historique de l'échantillon.

=== "Échantillon verrouillé"

    Utiliser cette situation quand l'automate a analysé de nouveau un
    échantillon dont les résultats sont déjà verrouillés dans InteLIS, et que
    le nouveau résultat doit remplacer le résultat verrouillé.

    1. Confirmer que le laboratoire veut remplacer le résultat verrouillé. Un
       import normal ne modifie jamais un échantillon verrouillé.
    2. Lancer l'import avec `force`, et le nombre de jours à lire :

        ```bash
        sudo intelis interface 7force
        ```

        `force` seul ne lit que ce qui est arrivé depuis le dernier import.
        Placer les jours devant pour remonter plus loin.

    3. Ouvrir l'échantillon dans InteLIS. Vérifier que le nouveau résultat s'y
       trouve.

## Options de la commande

Les options se placent après `sudo intelis interface`, séparées par des
espaces. Utiliser une seule option de période au plus. Elles se combinent,
comme dans `sudo intelis interface 7 silent`.

| Option | Exemple | Effet |
| --- | --- | --- |
| Aucune | `sudo intelis interface` | Importe ce qui est arrivé depuis le dernier import. Propose la configuration si l'interfaçage n'est pas connecté. |
| `setup` | `sudo intelis interface setup` | Lance la configuration, que l'interfaçage soit connecté ou non. Affiche d'abord les paramètres actuels. |
| Jours | `sudo intelis interface 7` | Lit tout ce qui date des 7 derniers jours, plus tout ce qui n'a jamais été importé. |
| Date | `sudo intelis interface 2026-09-01` | Lit tout ce qui date de cette date ou après, plus tout ce qui n'a jamais été importé. |
| `force` | `sudo intelis interface force` | Importe aussi dans les échantillons verrouillés. S'exécute même quand un autre import est encore en cours. |
| Jours avec `force` | `sudo intelis interface 7force` | Lit les 7 derniers jours et importe dans les échantillons verrouillés. |
| `silent` | `sudo intelis interface silent` | Importe sans modifier l'heure de dernière modification de chaque échantillon. Les listes d'échantillons gardent ainsi leur ordre. |
