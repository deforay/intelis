---
description: Mettre à jour une machine InteLIS sous Ubuntu vers la version actuelle avec la commande intelis.
audience: [system-admin]
module: [all]
type: how-to
platform: ubuntu
reviewed: 2026-10-29
reviewed_against: 5.8.5
---
# Mettre à jour InteLIS sur Ubuntu

Mettre à jour une machine de laboratoire InteLIS vers la version actuelle.

La machine doit fonctionner sous **Ubuntu 22.04 LTS ou plus récent**, disposer
d'une connexion Internet et d'un compte avec les droits `sudo`. Une machine
encore sous 22.04 doit passer à 24.04 LTS avec
[Migrer d'une machine Ubuntu à une autre](migrating-ubuntu-machines.md).

La version actuelle exige **PHP 8.4+ (au minimum 8.4.1)**. PHP 8.2, 8.3 et les
versions plus anciennes ne sont pas pris en charge. Le PHP d'Apache et le PHP
en ligne de commande doivent tous deux respecter cette exigence. La mise à jour
fait passer PHP à 8.4 (8.5 sur Ubuntu 26.04 et plus récent) automatiquement.

**Choisir la situation qui correspond, puis suivre ses étapes de haut en bas.**
Pour savoir laquelle correspond, ouvrir un terminal, saisir `intelis` et
appuyer sur Entrée :

- Un menu intitulé **InteLIS** avec huit options numérotées correspond à la
  première situation. Saisir `8` et appuyer sur Entrée pour quitter le menu.
- `command not found`, ou une longue liste de commandes Composer, correspond à
  la seconde.

=== "`intelis` affiche un menu"

    ### Avant la mise à jour

    1. Prévenir le laboratoire qu'InteLIS peut ne pas répondre pendant quelques
       minutes.
    2. Ouvrir un terminal sur la machine InteLIS et faire une nouvelle
       sauvegarde :

        ```bash
        intelis backup
        ```

        Saisir le mot de passe du compte s'il est demandé. Attendre la ligne
        `Database and settings saved on this machine`.

        ??? info "S'il demande `Set up off-machine backups now?`"

            Appuyer sur Entrée pour répondre No et poursuivre la mise à jour.
            Configurer plus tard les sauvegardes hors de la machine avec
            `intelis backup setup`.

        ??? failure "S'il affiche `The local backup did not finish`"

            Ne pas mettre à jour. Si MySQL est arrêté, suivre
            [MySQL ne démarre pas](mysql-will-not-start.md), puis refaire
            l'étape 2. Sinon, envoyer toute la sortie au support.

    ### Mise à jour

    3. Lancer la mise à jour :

        ```bash
        intelis update
        ```

        Saisir le mot de passe du compte s'il est demandé.

        ??? info "S'il demande le mot de passe root de MySQL"

            Le fichier de configuration ne contient aucun mot de passe MySQL.
            Saisir le mot de passe root de MySQL de cette machine.

            S'il demande le mot de passe deux fois, puis le **Remote STS URL**,
            le fichier de configuration manquait ou était endommagé, et la mise
            à jour le reconstruit. Saisir deux fois le mot de passe root de
            MySQL. Saisir ensuite l'adresse du STS qu'utilise le laboratoire, ou
            appuyer sur Entrée si le laboratoire n'a pas de STS.

    4. Attendre. Garder la fenêtre du terminal ouverte jusqu'à ce que la
       dernière ligne indique `Total time:`.

        ??? info "S'il demande `Do you want to run maintenance scripts?`"

            Sur certaines machines anciennes, la mise à jour pose cette
            question. Appuyer sur Entrée pour répondre No. Après 30 secondes
            sans réponse, la mise à jour continue avec No.

        ??? failure "Si le résumé affiche `Failed to update`"

            La mise à jour rétablit le code précédent quand elle le peut. Ne pas
            réparer la machine à la main.

            1. Lancer `intelis check`.
            2. Envoyer toute sa sortie au support, avec le fichier
               `/tmp/intelis-upgrade-….log` le plus récent.

            Relancer `intelis update` est sans risque.

    ### Vérifier la mise à jour

    5. Lire le **Post-Upgrade Check** vers la fin de la sortie. Sa ligne de
       résumé (`… passed, … warning(s), … failed, … skipped`) doit indiquer
       `0 failed`.

        ??? failure "Si une ligne commence par `WARN` ou `FAIL`"

            Chaque ligne affiche la commande qui corrige le problème. Saisir
            cette commande exactement comme affichée, puis lancer
            `intelis check`. Si une ligne `FAIL` reste, envoyer toute la sortie
            de `intelis check` au support.

    6. Ouvrir InteLIS dans le navigateur et se connecter.
    7. Vérifier le pied de page. Il affiche la version suivie d'un court code
       de commit entre parenthèses, par exemple `v5.7.72 (22928fe)`. Ce code
       correspond au début du second code de la ligne `Updated commit … -> …`
       de la sortie de la mise à jour.

        Si la mise à jour a affiché `Already at commit`, la machine était déjà
        à jour et le pied de page n'a pas changé.

=== "`intelis` introuvable, ou affiche Composer"

    ### Avant la mise à jour

    1. Prévenir le laboratoire qu'InteLIS peut ne pas répondre pendant quelques
       minutes.
    2. Ouvrir un terminal sur la machine InteLIS et installer les commandes
       `intelis` et `intelis-update` actuelles :

        ```bash
        sudo bash -c "$(curl -fsSL https://raw.githubusercontent.com/deforay/intelis/master/scripts/bootstrap.sh)"
        ```

        Saisir la commande exactement comme indiqué, avec `bash -c` et les
        guillemets. Attendre `Done. This machine's commands are current.`

        ??? info "Si `curl` n'est pas installé"

            Utiliser `wget` à la place :

            ```bash
            sudo bash -c "$(wget -qO- https://raw.githubusercontent.com/deforay/intelis/master/scripts/bootstrap.sh)"
            ```

        ??? failure "S'il affiche `Could not download` ou `is not a script`"

            La machine ne peut pas joindre GitHub, ou un portail réseau a
            intercepté le téléchargement. Rétablir la connexion Internet, puis
            refaire l'étape 2.

    3. Faire une nouvelle sauvegarde :

        ```bash
        intelis backup
        ```

        Saisir le mot de passe du compte s'il est demandé. Attendre la ligne
        `Database and settings saved on this machine`.

        ??? info "S'il demande `Set up off-machine backups now?`"

            Appuyer sur Entrée pour répondre No et poursuivre la mise à jour.
            Configurer plus tard les sauvegardes hors de la machine avec
            `intelis backup setup`.

        ??? failure "S'il affiche `The local backup did not finish`"

            Si MySQL est arrêté, suivre [MySQL ne démarre pas](mysql-will-not-start.md),
            puis refaire l'étape 3.

            Sur une installation trop ancienne pour avoir la commande de
            sauvegarde, exporter plutôt la base de données avec ceci :

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
              `These databases were NOT backed up`, ne pas mettre à jour.

    ### Mise à jour

    4. Lancer la mise à jour :

        ```bash
        intelis update
        ```

        Saisir le mot de passe du compte s'il est demandé.

        ??? info "S'il demande le mot de passe root de MySQL"

            Le fichier de configuration ne contient aucun mot de passe MySQL.
            Saisir le mot de passe root de MySQL de cette machine.

            S'il demande le mot de passe deux fois, puis le **Remote STS URL**,
            le fichier de configuration manquait ou était endommagé, et la mise
            à jour le reconstruit. Saisir deux fois le mot de passe root de
            MySQL. Saisir ensuite l'adresse du STS qu'utilise le laboratoire, ou
            appuyer sur Entrée si le laboratoire n'a pas de STS.

    5. Attendre. Garder la fenêtre du terminal ouverte jusqu'à ce que la
       dernière ligne indique `Total time:`.

        ??? info "S'il demande `Do you want to run maintenance scripts?`"

            Sur certaines machines anciennes, la mise à jour pose cette
            question. Appuyer sur Entrée pour répondre No. Après 30 secondes
            sans réponse, la mise à jour continue avec No.

        ??? failure "Si le résumé affiche `Failed to update`"

            La mise à jour rétablit le code précédent quand elle le peut. Ne pas
            réparer la machine à la main.

            1. Lancer `intelis check`.
            2. Envoyer toute sa sortie au support, avec le fichier
               `/tmp/intelis-upgrade-….log` le plus récent.

            Relancer `intelis update` est sans risque.

    ### Vérifier la mise à jour

    6. Lire le **Post-Upgrade Check** vers la fin de la sortie. Sa ligne de
       résumé (`… passed, … warning(s), … failed, … skipped`) doit indiquer
       `0 failed`.

        ??? failure "Si une ligne commence par `WARN` ou `FAIL`"

            Chaque ligne affiche la commande qui corrige le problème. Saisir
            cette commande exactement comme affichée, puis lancer
            `intelis check`. Si une ligne `FAIL` reste, envoyer toute la sortie
            de `intelis check` au support.

    7. Ouvrir InteLIS dans le navigateur et se connecter.
    8. Vérifier le pied de page. Il affiche la version suivie d'un court code
       de commit entre parenthèses, par exemple `v5.7.72 (22928fe)`. Ce code
       correspond au début du second code de la ligne `Updated commit … -> …`
       de la sortie de la mise à jour.

        Si la mise à jour a affiché `Already at commit`, la machine était déjà
        à jour et le pied de page n'a pas changé.

    9. Saisir `intelis` et appuyer sur Entrée. Le menu intitulé **InteLIS**
       apparaît. Désormais, suivre la première situation de cette page.

La version de code que reçoit un laboratoire, et sa publication, sont décrites
dans [Canaux de publication](../release-tracks.md).
