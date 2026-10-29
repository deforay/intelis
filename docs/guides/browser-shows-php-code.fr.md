---
description: Réparer un serveur InteLIS qui affiche le code source PHP ou propose de télécharger un fichier au lieu de la page de connexion.
audience: [system-admin]
module: [all]
type: how-to
platform: ubuntu
reviewed: 2026-10-29
reviewed_against: 5.8.5
---
# Le navigateur affiche du code PHP au lieu d'InteLIS

Le navigateur affiche un texte qui commence par `<?php declare(strict_types=1);`,
ou propose de télécharger un fichier, au lieu de la page de connexion d'InteLIS.

1. Ouvrir un terminal sur la machine InteLIS et lancer :

    ```bash
    sudo intelis doctor
    ```

    ??? info "Si `intelis` n'est pas reconnu, ou si `doctor` n'existe pas"

        L'installation est plus ancienne. Lancer le même outil directement
        depuis internet :

        ```bash
        sudo bash -c "$(wget -qO- https://raw.githubusercontent.com/deforay/intelis/master/scripts/intelis-doctor.sh)"
        ```

        La taper exactement comme indiqué. Rediriger le téléchargement vers
        `bash` par un tube fournit au script son propre texte comme réponses à
        ses questions.

    ??? tip "Pour examiner sans rien modifier"

        Ajouter `--check`. Le doctor se contente alors de signaler ce qu'il
        trouve.

        ```bash
        sudo intelis doctor --check
        ```

2. Répondre à ses questions. Il demande avant chaque réparation.
   [Le site ne s'ouvre pas](site-will-not-open.md) couvre tous les autres
   symptômes.
3. Recharger le navigateur quand le doctor affiche `InteLIS is working again.`

    ??? failure "Si le site ne s'ouvre toujours pas"

        Le doctor dépose `site-report.txt` sur le **Desktop** (**Bureau** sur un
        poste en français), ou dans le dossier personnel quand il n'y a pas de
        **Desktop**. Envoyer ce fichier pour demander de l'aide. Les mots de
        passe en sont retirés. Quand le doctor a aussi lancé le doctor de la
        base de données, envoyer plutôt `mysql-report.txt`. Il se trouve au même
        endroit.

4. Lancer une mise à jour une fois la page de connexion affichée :

    ```bash
    intelis update
    ```

    Une installation ou une mise à niveau de version interrompue peut
    aussi laisser inachevés le propriétaire des fichiers et les migrations de la
    base de données. La mise à jour règle les deux. Voir
    [Mettre à jour InteLIS sur Ubuntu](updating-intelis-on-ubuntu.md).

## Si le site s'ouvre mais affiche une erreur

Le serveur web fonctionne et le problème vient de l'application, le plus souvent
de la base de données. Le doctor le détecte et propose de lancer le doctor de la
base de données. Pour y aller directement, lancer :

```bash
sudo intelis fix-database
```

Voir [MySQL ne démarre pas](mysql-will-not-start.md).

??? info "Ce que le doctor corrige"

    Apache sert `index.php` comme un fichier au lieu de l'exécuter. Cela suit en
    général une mise à niveau de version d'Ubuntu, un `apt upgrade` qui a changé
    la version de PHP, ou une installation interrompue. La base de données et
    les fichiers téléversés ne sont pas touchés.

    Trois pannes donnent la même page :

    - **Apache n'a pas de module PHP.** Le module PHP ne fonctionne qu'avec le
      worker `prefork`. Ubuntu active par défaut le worker `event`.
    - **Les paquets PHP ne peuvent pas être installés.** Une mise à niveau de
      version d'Ubuntu désactive le dépôt PHP, et apt n'a donc rien à installer.
    - **Apache a les bons paramètres mais ne les a jamais chargés.** Chaque
      vérification des paramètres réussit. Seul un redémarrage d'Apache corrige
      le problème.

    Le doctor demande une page au site et lit la réponse. Il trouve donc aussi
    la troisième panne. Il installe PHP 8.4, ou 8.5 sur Ubuntu 26.04 et
    versions suivantes.
