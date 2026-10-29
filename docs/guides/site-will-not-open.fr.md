---
description: Utiliser intelis doctor pour trouver pourquoi le site InteLIS ne s'ouvre pas, le réparer, et collecter un rapport quand la réparation échoue.
audience: [system-admin, lab-admin]
module: [all]
type: how-to
platform: ubuntu
reviewed: 2026-10-29
reviewed_against: 5.8.5
---
# Le site ne s'ouvre pas : utiliser le doctor

`intelis doctor` trouve pourquoi le site InteLIS ne s'ouvre pas dans le
navigateur. Il explique ce qu'il a trouvé et demande avant chaque réparation.
Quand la base de données est en cause, il lance lui-même le doctor de la base
de données.

Le lancer sur la machine InteLIS, dans un terminal. Il ne fonctionne pas depuis
un autre ordinateur.

**Choisir ce qu'affiche le navigateur, puis suivre ses étapes de haut en bas.**

=== "Une page d'erreur"

    Le navigateur affiche une page blanche, ou l'un de ces messages :

    - `The InteLIS database is not responding`
    - `This server's PHP cannot reach the database`
    - `System Temporarily Unavailable`, sur les anciennes versions
    - `Internal Server Error`

    phpMyAdmin peut afficher `The mysqli extension is missing`.

    1. Ouvrir un terminal sur la machine InteLIS et lancer :

        ```bash
        sudo intelis doctor
        ```

        ??? info "Si `intelis` n'est pas reconnu, ou si `doctor` n'existe pas"

            L'installation est ancienne. Lancer le même outil directement depuis
            Internet :

            ```bash
            sudo bash -c "$(wget -qO- https://raw.githubusercontent.com/deforay/intelis/master/scripts/intelis-doctor.sh)"
            ```

            Saisir la commande exactement comme indiqué. Rediriger le
            téléchargement vers `bash` donne au script son propre texte comme
            réponses à ses questions.

    2. Saisir le mot de passe du compte quand il est demandé.
    3. Lire l'encadré en haut. Il nomme le problème. La liste en dessous
       affiche chaque vérification avec ✔ ou ✘.
    4. Répondre `y` à chaque question `Do this now?` sous **Repairs**.

        ??? info "S'il indique `PHP is missing parts InteLIS needs`"

            Le PHP du serveur web n'a pas chargé une extension qu'InteLIS
            utilise. Sans `mysqli`, ni InteLIS ni phpMyAdmin ne peuvent joindre
            la base de données, même si la base de données fonctionne bien. Le
            doctor installe les extensions manquantes, les active et redémarre
            le serveur web.

        ??? info "S'il indique `PHP was updated, but the web server is still running the old copy`"

            PHP a été mis à jour avec `apt upgrade` et le serveur web n'a pas
            été redémarré. Le doctor le redémarre. S'il affiche ensuite
            `Run sudo intelis doctor again to repair that.`, lancer le doctor
            une seconde fois.

        ??? info "S'il indique `This PHP was built without parts InteLIS needs`"

            Le doctor ne peut pas réparer ce problème. Réinstaller PHP avec la
            commande qu'il affiche, par exemple :

            ```bash
            sudo switch-php 8.4 --force
            ```

        ??? info "Si l'encadré indique `The database has stopped`"

            Le doctor vérifie ensuite la base de données, dans une section
            intitulée **Database**. Quand aucune des causes connues ne
            correspond, il propose `Start the database again.` Ce démarrage est
            la réparation habituelle après un plantage ou une coupure de
            courant. Si la base de données refuse de démarrer, le doctor
            affiche les dernières erreurs qu'elle a écrites. Voir
            [MySQL ne démarre pas](mysql-will-not-start.md).

        ??? info "S'il propose `Update InteLIS to the current release.`"

            Le serveur web, PHP et la base de données fonctionnent tous, et
            InteLIS renvoie encore une erreur. Cette machine est aussi en
            retard sur la version actuelle, et une mise à jour corrige souvent
            les erreurs internes à InteLIS. Répondre `y` pour lancer
            `intelis update`. Le doctor vérifie de nouveau le site ensuite.

    5. Recharger le navigateur quand le doctor affiche `InteLIS is working again.`

        ??? failure "Si le site ne s'ouvre toujours pas"

            Envoyer le rapport. Voir [Envoyer le rapport](#envoyer-le-rapport).

=== "Code PHP"

    Le navigateur affiche un texte qui commence par `<?php`, ou propose de
    télécharger un fichier, au lieu de la page de connexion d'InteLIS.

    1. Ouvrir un terminal sur la machine InteLIS et lancer :

        ```bash
        sudo intelis doctor
        ```

        ??? info "Si `intelis` n'est pas reconnu, ou si `doctor` n'existe pas"

            L'installation est ancienne. Lancer le même outil directement depuis
            Internet :

            ```bash
            sudo bash -c "$(wget -qO- https://raw.githubusercontent.com/deforay/intelis/master/scripts/intelis-doctor.sh)"
            ```

            Saisir la commande exactement comme indiqué. Rediriger le
            téléchargement vers `bash` donne au script son propre texte comme
            réponses à ses questions.

    2. Saisir le mot de passe du compte quand il est demandé.
    3. Lire l'encadré en haut. Il nomme le problème. La liste en dessous
       affiche chaque vérification avec ✔ ou ✘.
    4. Répondre `y` à chaque question `Do this now?` sous **Repairs**.

        Le doctor installe le module PHP du serveur web, rétablit le dépôt de
        paquets PHP quand une mise à niveau d'Ubuntu l'a supprimé, et
        redémarre le serveur web.
        [Le navigateur affiche du code PHP](browser-shows-php-code.md) explique
        chaque cause.

    5. Recharger le navigateur quand le doctor affiche `InteLIS is working again.`

        ??? failure "Si le site ne s'ouvre toujours pas"

            Envoyer le rapport. Voir [Envoyer le rapport](#envoyer-le-rapport).

    6. Lancer une mise à jour une fois la page de connexion affichée :

        ```bash
        intelis update
        ```

        Une installation ou une mise à niveau de version interrompue peut
        aussi laisser inachevés les propriétaires des fichiers et les
        migrations de la base de données. La mise à jour règle les deux.

=== "La page de mise à jour reste affichée"

    Le navigateur affiche `InteLIS is being updated`, ou `Upgrade in progress`
    sur les anciennes versions, plus de 30 minutes après l'heure de début
    indiquée sur la page.

    1. Ouvrir un terminal sur la machine InteLIS et lancer :

        ```bash
        sudo intelis doctor
        ```

        ??? info "Si `intelis` n'est pas reconnu, ou si `doctor` n'existe pas"

            L'installation est ancienne. Lancer le même outil directement depuis
            Internet :

            ```bash
            sudo bash -c "$(wget -qO- https://raw.githubusercontent.com/deforay/intelis/master/scripts/intelis-doctor.sh)"
            ```

            Saisir la commande exactement comme indiqué. Rediriger le
            téléchargement vers `bash` donne au script son propre texte comme
            réponses à ses questions.

    2. Saisir le mot de passe du compte quand il est demandé.
    3. Répondre `y` à `Take the site out of update mode.`
    4. Recharger le navigateur.
    5. Relancer la mise à jour, car la précédente ne s'est pas terminée :

        ```bash
        intelis update
        ```

        ??? failure "Si la mise à jour s'arrête à `Running database migrations...`"

            Voir [Mise à jour bloquée aux migrations de la base de données](update-stuck-at-migrations.md).

=== "Rien ne s'affiche"

    Le navigateur indique que le site est inaccessible, que la connexion a été
    refusée, ou il continue de charger.

    1. Ouvrir un terminal sur la machine InteLIS et lancer :

        ```bash
        sudo intelis doctor
        ```

        ??? info "Si `intelis` n'est pas reconnu, ou si `doctor` n'existe pas"

            L'installation est ancienne. Lancer le même outil directement depuis
            Internet :

            ```bash
            sudo bash -c "$(wget -qO- https://raw.githubusercontent.com/deforay/intelis/master/scripts/intelis-doctor.sh)"
            ```

            Saisir la commande exactement comme indiqué. Rediriger le
            téléchargement vers `bash` donne au script son propre texte comme
            réponses à ses questions.

    2. Saisir le mot de passe du compte quand il est demandé.
    3. Lire l'encadré en haut. Il nomme le problème. La liste en dessous
       affiche chaque vérification avec ✔ ou ✘.
    4. Répondre `y` à chaque question `Do this now?` sous **Repairs**.

        ??? info "S'il indique `Another program has taken the address the site is served on`"

            Un second serveur web occupe le port 80. S'il a été installé par
            erreur, répondre `y` pour l'arrêter. S'il a été installé
            volontairement, répondre `n` et demander de l'aide, car l'arrêter
            casse ce qui l'utilise.

        ??? info "S'il indique `The web server's configuration has an error in it`"

            Le doctor ne modifie pas la configuration du serveur web. Il affiche
            le fichier et la ligne en cause. Envoyer le rapport.

        ??? info "S'il indique `This machine has almost no disk space left`"

            Le doctor liste les plus gros dossiers et ne supprime rien. Voir
            [Libérer de l'espace disque](maintenance.md#liberer-de-lespace-disque).

    5. Recharger le navigateur quand le doctor affiche `InteLIS is working again.`

        ??? failure "Si le navigateur d'un autre ordinateur ne joint toujours pas le site"

            Le doctor teste le site depuis la machine InteLIS elle-même. Si le
            site s'ouvre sur cette machine et pas sur les autres ordinateurs,
            vérifier le câble réseau, l'adresse IP de la machine et le
            pare-feu.

## Envoyer le rapport

Chaque exécution écrit un rapport. Les mots de passe en sont retirés.

1. Ouvrir l'application **Files** (**Fichiers** sur un poste en français) sur
   la machine InteLIS.
2. Trouver `site-report.txt` sur le **Desktop** (**Bureau** sur un poste en
   français), ou dans le dossier personnel quand il n'y a pas de Desktop.
3. Le joindre au message de demande d'aide.

Quand le doctor a aussi vérifié la base de données, envoyer aussi
`mysql-report.txt`. Il se trouve au même endroit.

??? info "Où sont conservés les rapports complets"

    Le doctor ne conserve que son dernier rapport :
    `/var/log/intelis-doctor-<timestamp>.log`. Le doctor de la base de données
    écrit `/var/log/intelis-mysql-doctor-<timestamp>.log`.

## Lancer le doctor sans réparations

Pour regarder sans rien modifier, ajouter `--check` :

```bash
sudo intelis doctor --check
```

Pour appliquer toutes les réparations sûres sans questions, par exemple depuis
une session à distance, ajouter `--yes` :

```bash
sudo intelis doctor --yes
```

`--yes` n'arrête jamais un autre programme qui occupe le port 80, et n'installe
jamais de mise à jour. Ces décisions demandent une personne.

??? info "Ce que vérifie le doctor"

    | Vérification | Ce que fait le doctor |
    | --- | --- |
    | Le serveur web est installé et fonctionne | Le démarre |
    | La configuration du serveur web est valide | Affiche le fichier et la ligne, ne modifie rien |
    | Un autre programme occupe le port 80 | L'arrête, après avoir demandé |
    | Le dépôt de paquets PHP est disponible | Le rétablit |
    | Le serveur web peut exécuter PHP | Installe et active le module PHP |
    | Le serveur web exécute le PHP installé | Redémarre le serveur web |
    | PHP dispose de toutes les extensions dont InteLIS a besoin | Installe et active celles qui manquent |
    | Une mise à jour a laissé la page de mise à jour affichée | La retire |
    | Le serveur web peut lire et écrire dans les dossiers d'InteLIS | Répare le propriétaire de `var` et `public/uploads` |
    | Le disque a encore de l'espace | Liste les plus gros dossiers, ne supprime rien |
    | La base de données fonctionne et répond | Cherche pourquoi elle s'est arrêtée, puis la démarre |
    | Le site répond sans erreur | Cherche la cause dans la base de données |
    | InteLIS est à la version actuelle | Propose une mise à jour, seulement quand tout le reste fonctionne et que le site échoue encore |

    Le doctor demande une page au site et lit la réponse. Il détecte donc aussi
    un serveur web qui a les bons réglages mais ne les a jamais chargés.
