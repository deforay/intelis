---
description: Mettre à jour une installation InteLIS sur une machine Windows avec WampServer en remplaçant le code et en appliquant les modifications de la base de données.
audience: [system-admin]
module: [all]
type: how-to
platform: windows
reviewed: 2026-10-29
reviewed_against: 5.8.5
---
# Mettre à jour InteLIS sur une machine Windows

Mettre à jour une installation InteLIS sur une machine Windows qui exécute
WampServer.

L'installation se trouve dans `C:\wamp64\www\vlsm` et s'ouvre à
<http://vlsm>. WampServer et PHP en ligne de commande doivent exécuter
**PHP 8.4+ (minimum 8.4.1)**. PHP 8.2, 8.3 et les versions plus anciennes ne
sont pas pris en charge. Ce guide utilise les chemins de PHP 8.4. Avec
PHP 8.5, remplacer par le nom de son dossier installé.

??? info "Pourquoi le dossier s'appelle `vlsm`"

    InteLIS s'appelait auparavant VLSM. Le dossier d'installation Windows, le
    nom d'hôte du site et la base de données portent encore ce nom.

**Suivre les étapes de haut en bas.**

## Sauvegarder

1. Ouvrir `C:\wamp64\bin\php` dans **File Explorer** (**Explorateur de
   fichiers** sur un poste en français). Noter le nom du dossier qui commence
   par `php8.4`, par exemple `php8.4.1`. Les étapes suivantes l'appellent
   `php8.4.x`.
2. Ouvrir **Command Prompt** (**Invite de commandes**).
3. Faire une sauvegarde. Remplacer `php8.4.x` par le nom du dossier de
   l'étape 1 :

    ```bat
    cd C:\wamp64\www\vlsm
    set PATH=C:\wamp64\bin\php\php8.4.x;%PATH%
    php composer.phar backup
    ```

4. Lister les fichiers de sauvegarde, du plus récent au plus ancien :

    ```bat
    dir /o-d C:\wamp64\www\vlsm\backups\db
    ```

    Le premier fichier porte la date du jour dans son nom, par exemple
    `20260918`.

    ??? failure "Si la sauvegarde s'arrête sur une erreur, ou si aucun fichier ne porte la date du jour"

        Exporter plutôt les bases de données depuis phpMyAdmin :

        1. Ouvrir <http://localhost/phpmyadmin> et se connecter en tant que
           `root`.
        2. Sélectionner la base de données `vlsm` dans le panneau de gauche.
        3. Sélectionner **Export** (**Exporter** sur un poste en français),
           puis **Export** ou **Go** (**Exécuter**) en bas de la page.
        4. Enregistrer le fichier `.sql`.
        5. Si le laboratoire utilise l'outil d'interface, répéter les étapes
           2 à 4 pour la base de données `interfacing`.
        6. Copier le dossier `C:\wamp64\www\vlsm\configs` à côté des fichiers
           exportés.

        Envoyer l'erreur de l'étape 3 au support.

5. Copier le dossier `C:\wamp64\www\vlsm\backups` sur une clé USB ou un
   partage réseau. Inclure les exports phpMyAdmin si l'étape 4 les a rendus
   nécessaires.

## Remplacer les fichiers

6. Télécharger la version actuelle :
   <https://github.com/deforay/intelis/archive/refs/heads/stable.zip>
7. Extraire le fichier zip. Il contient un seul dossier, `intelis-stable`.
8. Ouvrir `intelis-stable`. Appuyer sur **Ctrl+A**, puis sur **Ctrl+C** pour
   copier tout son contenu.
9. Ouvrir `C:\wamp64\www\vlsm`. Appuyer sur **Ctrl+V**, puis choisir
   **Replace the files in the destination** (**Remplacer les fichiers dans la
   destination** sur un poste en français).

    Ne pas supprimer ni renommer `C:\wamp64\www\vlsm` au préalable. Ce dossier
    contient la configuration, les fichiers téléversés et les sauvegardes, et
    le fichier zip ne les contient pas.

## Mettre à jour

10. Dans la fenêtre Command Prompt, installer les paquets :

    ```bat
    cd C:\wamp64\www\vlsm
    set PATH=C:\wamp64\bin\php\php8.4.x;%PATH%
    php composer.phar install --no-dev --no-scripts
    php composer.phar dump-autoload -o
    ```

    ??? failure "Si composer signale que la version de PHP ne satisfait pas une exigence"

        La ligne `set PATH` pointe vers un PHP antérieur à 8.4.1. Vérifier le
        nom du dossier de l'étape 1, puis répéter l'étape 10.

11. Appliquer les modifications de la base de données :

    ```bat
    php composer.phar post-update
    ```

    Attendre le retour de l'invite de commande.

## Vérifier la mise à jour

12. Ouvrir <http://vlsm> dans le navigateur et se connecter.
13. Vérifier le pied de page. Il affiche la version, par exemple `v5.7.72`. Le
    numéro correspond à la ligne `"version"` de
    `C:\wamp64\www\vlsm\composer.json`.

    ??? failure "Si le pied de page affiche un avertissement rouge `DB ver.`"

        Les modifications de la base de données ne sont pas terminées.
        Exécuter de nouveau l'étape 11 et envoyer toute sa sortie au support.
