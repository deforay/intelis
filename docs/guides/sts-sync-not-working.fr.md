---
description: Trouver pourquoi les résultats ou les demandes d'un laboratoire ne circulent pas entre InteLIS et le STS, et les envoyer immédiatement.
audience: [system-admin]
module: [all]
type: how-to
platform: ubuntu
reviewed: 2026-10-29
reviewed_against: 5.8.5
---
# Résultats qui n'atteignent pas le STS

L'InteLIS d'un laboratoire se synchronise avec le STS toutes les 5 minutes.
Chaque synchronisation envoie les résultats et les informations du laboratoire,
et ramène les demandes et les listes. Quand des résultats d'un laboratoire
manquent sur le STS, ou que des demandes manquent au laboratoire, la panne se
trouve presque toujours sur la machine du laboratoire.

Le laboratoire doit déjà être connecté au STS. S'il ne l'a jamais été, suivre
d'abord [Connecter un laboratoire au STS](connect-lab-to-sts.md).

**Choisir la situation qui correspond, puis suivre ses étapes de haut en bas.**

=== "Trouver l'étape qui bloque"

    Commencer par cet onglet, quel que soit ce qui manque.

    1. Ouvrir un terminal sur la machine InteLIS du laboratoire et vérifier les
       paramètres de connexion :

        ```bash
        sudo intelis check
        ```

    2. Lire les lignes `STS URL`, `STS hosts entry`, `Instance registered` et
       `STS token`. Corriger toute ligne qui n'est pas `OK`, à l'aide de
       [Messages et corrections](#messages-et-corrections) plus bas.
    3. Lancer une synchronisation complète immédiatement :

        ```bash
        sudo intelis sync-sts
        ```

        Elle obtient un jeton, puis échange les listes, les résultats, les
        demandes et les commandes à distance avec le STS. L'exécution dure
        jusqu'à quelques minutes.

    4. Lire la sortie de haut en bas. Chercher chaque message qui signale une
       erreur dans [Messages et corrections](#messages-et-corrections).

        `Metadata Synced` s'affiche même quand les listes ont échoué. Lire les
        lignes au-dessus.

    5. Vérifier que la synchronisation a atteint le STS. Suivre l'onglet
       **LIS** de
       [Vérifier que les données ont atteint le STS](../user-guides/admin-monitoring.md#verifier-que-les-donnees-ont-atteint-le-sts).

        ??? failure "Si une synchronisation manuelle fonctionne, mais que rien ne se synchronise seul"

            Le planificateur ne fonctionne pas. Suivre
            [Vérifier que les tâches planifiées s'exécutent](maintenance.md#verifier-que-les-taches-planifiees-sexecutent).

=== "Résultats absents du STS"

    Utiliser cet onglet quand la synchronisation s'exécute sans erreur, mais
    que des résultats d'une période manquent sur le STS.

    Une synchronisation normale n'envoie que les résultats jamais envoyés. Un
    résultat que le STS a perdu, ou qui a été envoyé avant que le laboratoire
    soit correctement configuré, n'est pas renvoyé de lui-même.

    1. Voir ce qui serait envoyé, sans rien envoyer. Remplacer `vl` par le
       module, et `7` par le nombre de jours à remonter :

        ```bash
        sudo -u www-data php /var/www/intelis/app/tasks/remote/results-sender.php vl 7 --dry-run
        ```

    2. Vérifier le nombre de résultats qu'il enverrait.
    3. Les envoyer :

        ```bash
        sudo -u www-data php /var/www/intelis/app/tasks/remote/results-sender.php vl 7
        ```

        ??? info "Noms des modules et autres formes"

            | Module | Nom |
            | --- | --- |
            | Charge virale | `vl` |
            | Diagnostic précoce chez le nourrisson | `eid` |
            | COVID-19 | `covid19` |
            | Hépatite | `hepatitis` |
            | Tuberculose | `tb` |
            | CD4 | `cd4` |
            | Tests personnalisés | `generic-tests` |

            - Omettre le module pour envoyer tous les modules.
            - Donner une date au format `YYYY-MM-DD` à la place des jours, par
              exemple `2026-09-01`.
            - Ajouter `--status=` pour n'envoyer que certains statuts
              d'échantillon, par exemple `--status=rejected` ou
              `--status=accepted,rejected`. Les noms sont `on-hold`, `lost`,
              `reordered`, `rejected`, `failed`, `received`, `accepted`,
              `pending-approval`, `expired`, `no-result`, `cancelled` et
              `referred`.
            - La même commande s'exécute sous la forme `composer send-results`
              depuis `/var/www/intelis`. Placer `--` avant les arguments, par
              exemple `composer send-results -- vl 400 --status=rejected`. Sans
              `--`, composer ignore toutes les options, et la synchronisation
              envoie tous les statuts.
            - Quand un filtre de statut est utilisé, la sortie affiche
              `Sending only statuses:` sous `Syncing results since:`. Si cette
              ligne manque, le filtre n'a pas été appliqué. Appuyer sur Ctrl+C
              et relancer la commande.

        ??? failure "S'il affiche `Another results sync is already running. Exiting.`"

            Une autre synchronisation envoie en ce moment. Il peut s'agir de la
            synchronisation planifiée, d'un renvoi manuel précédent, ou du
            bouton **Synchroniser** d'une liste de demandes.

            1. Ajouter `--wait-for-lock=3600` à la commande **Les envoyer**. La
               commande attend alors jusqu'à une heure que l'autre
               synchronisation se termine, et démarre après elle.
            2. Si elle ne démarre toujours pas, trouver le processus qui détient
               le verrou :

                ```bash
                sudo lsof /var/www/intelis/var/results-sender.lock
                ```

            3. Si le processus est `apache2`, redémarrer le serveur web :

                ```bash
                sudo systemctl restart apache2
                ```

                Pour tout autre processus, l'arrêter avec `sudo kill` et
                l'identifiant de processus de la colonne `PID`.

            4. Relancer la commande **Les envoyer**. Arrêter une
               synchronisation en cours de route ne fait rien perdre. La
               synchronisation suivante envoie les résultats que la
               synchronisation arrêtée n'a pas terminés.

    4. Vérifier que les résultats sont arrivés. Suivre l'onglet **LIS** de
       [Vérifier que les données ont atteint le STS](../user-guides/admin-monitoring.md#verifier-que-les-donnees-ont-atteint-le-sts).

=== "Demandes absentes au laboratoire"

    Utiliser cet onglet quand le STS détient des demandes pour le laboratoire
    qui n'apparaissent pas dans l'InteLIS du laboratoire.

    1. Récupérer de nouveau les demandes. Remplacer `vl` par le module, et `7`
       par le nombre de jours à remonter :

        ```bash
        sudo -u www-data php /var/www/intelis/app/tasks/remote/requests-receiver.php -t vl 7
        ```

        ??? info "Pour récupérer les demandes d'un manifeste"

            Ajouter `-m` et le code du manifeste, et omettre les jours :

            ```bash
            sudo -u www-data php /var/www/intelis/app/tasks/remote/requests-receiver.php -t vl -m MANIFEST-CODE
            ```

    2. Ouvrir la liste des demandes dans InteLIS. Vérifier que les demandes
       manquantes s'y trouvent.

        ??? failure "Si une demande n'arrive toujours pas"

            Le laboratoire a refusé de l'enregistrer. Sur le STS, ouvrir
            **ADMIN → Surveillance → État de la synchronisation du
            laboratoire** et sélectionner le laboratoire. **Demandes que le
            laboratoire n'a pas pu enregistrer** donne la raison de chacune.

=== "Informations du laboratoire absentes après une mise à jour"

    Utiliser cet onglet quand des utilisateurs, des lieux de stockage ou des
    automates modifiés au laboratoire manquent sur le STS, et que le
    laboratoire a été mis à jour depuis une version antérieure à 5.7.22.

    Les anciennes versions indiquaient les informations du laboratoire comme
    envoyées même quand le STS les refusait. Ces informations ne sont pas
    renvoyées d'elles-mêmes.

    1. Ramener les listes depuis le STS, sans tenir compte de l'heure de la
       dernière synchronisation :

        ```bash
        sudo -u www-data php /var/www/intelis/app/tasks/remote/sts-metadata-receiver.php -f
        ```

    2. Envoyer les informations du laboratoire, sans tenir compte de l'heure
       de la dernière synchronisation :

        ```bash
        sudo -u www-data php /var/www/intelis/app/tasks/remote/lab-metadata-sender.php -f
        ```

    3. Attendre `Sync complete.` à la fin.

    L'administrateur du STS peut faire la même chose depuis le STS, avec la
    commande **Resynchronisation des métadonnées (forcée)** du
    [Plan de commande à distance](remote-command-plane.md).

    !!! warning "Ne pas utiliser `intelis reset-metadata` pour cela"

        Cette commande vide les listes des structures et des laboratoires avant
        de les ramener. Ne l'utiliser que si le support le demande.

## Messages et corrections

??? failure "`STS URL` : `not set`, ou `STS URL is not set`, ou `Testing Lab ID is not set in System Config`"

    Le laboratoire n'est pas connecté. Suivre
    [Connecter un laboratoire au STS](connect-lab-to-sts.md).

??? failure "`This instance is not configured as LIS. Exiting.`"

    La machine est configurée en mode autonome ou comme STS. Seule une machine
    du laboratoire se synchronise. Voir l'avertissement **Type d'instance**
    dans [Utiliser l'espace System Admin](../user-guides/admin-system-administration.md).

??? failure "`STS hosts entry` : `/etc/hosts maps ... to ...`"

    Une ligne de `/etc/hosts` envoie l'adresse du STS vers un autre serveur.
    Rien n'atteint donc le STS.

    1. Ouvrir le fichier :

        ```bash
        sudo nano /etc/hosts
        ```

    2. Supprimer la ligne qui contient le nom d'hôte du STS.
    3. Enregistrer avec Ctrl+O, puis Entrée. Fermer avec Ctrl+X.
    4. Relancer `sudo intelis check`.

??? failure "`No internet connectivity while trying remote sync.`, ou `Checking connectivity... FAILED`"

    La machine ne peut pas joindre le STS. Le point dans l'en-tête de page
    d'InteLIS affiche aussi **Le serveur STS est inaccessible**.

    - Ouvrir l'adresse du STS dans un navigateur sur cette machine. Si elle ne
      se charge pas, vérifier la connexion Internet ou le VPN.
    - Si l'adresse se charge, lancer `sudo intelis check` et lire la ligne
      `STS URL`. L'adresse enregistrée au laboratoire est peut-être fausse. La
      corriger avec [Connecter un laboratoire au STS](connect-lab-to-sts.md).

??? failure "`HTTP 401`, ou `Unauthorized Access on ... sync`, ou `STS token` : `no STS token is stored`"

    Le STS n'a pas accepté le jeton du laboratoire. La raison suit
    `Unauthorized Access on ... sync:`. Elle figure aussi dans le journal du
    STS lui-même.

    1. Obtenir le jeton actuel auprès du STS :

        ```bash
        sudo intelis token
        ```

    2. Attendre `Token generated`.
    3. Relancer `sudo intelis sync-sts`.

    Si l'erreur 401 revient, demander à l'administrateur du STS d'envoyer
    **Renouveler le jeton STS** depuis le
    [Plan de commande à distance](remote-command-plane.md). Lui envoyer la
    raison affichée après `Unauthorized Access`.

??? failure "`Instance registered` : `not registered, but an STS is configured`"

    La configuration dans le navigateur n'a jamais été terminée. Le STS ne
    peut donc pas savoir quel laboratoire se synchronise. Ouvrir
    `/setup/index.php` sur l'adresse du laboratoire, terminer le formulaire,
    puis relancer `sudo intelis check`.

??? failure "`Could not deliver the receipt for ...; it will be sent next run.`"

    Rien d'anormal. Le laboratoire a enregistré les demandes, et le signale au
    STS à la synchronisation suivante.

??? info "Où se trouvent les journaux de synchronisation"

    Les erreurs vont dans `/var/www/intelis/var/logs`, un fichier par jour, par
    exemple `2026-09-22-logfile.log`. **ADMIN → Surveillance → Visualisateur de
    fichiers journaux** affiche les mêmes fichiers. Envoyer le plus récent en
    contactant le support.
