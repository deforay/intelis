---
description: Définir un nouveau mot de passe pour un utilisateur InteLIS qui ne peut pas se connecter, y compris le seul compte administrateur, depuis un terminal sur la machine InteLIS.
audience: [system-admin, lab-admin]
module: [all]
type: how-to
platform: ubuntu
reviewed: 2026-10-29
reviewed_against: 5.8.5
---
# Mot de passe oublié

Utiliser cette page quand un utilisateur a oublié son mot de passe et qu'aucun
administrateur ne peut le réinitialiser depuis le navigateur. Elle couvre aussi
le cas où le seul administrateur est celui qui est bloqué. La réinitialisation
se fait dans un terminal sur la machine InteLIS. Elle ne demande aucun autre
compte InteLIS.

## Réinitialiser le mot de passe sur Ubuntu

1. Ouvrir un terminal sur la machine InteLIS, ou s'y connecter en SSH.
2. Lancer :

    ```bash
    sudo intelis reset-password
    ```

    S'il affiche `Command "reset-password" is not defined`, la machine a une
    commande `intelis` plus ancienne. Lancer plutôt le script directement :

    ```bash
    cd /var/www/intelis
    sudo -u www-data php bin/reset-user-password.php
    ```

    Sur les installations plus anciennes, le dossier est `/var/www/vlsm`.

3. Taper une partie de l'identifiant de connexion ou du nom pour filtrer la
   liste des utilisateurs actifs.
4. Sélectionner le compte avec les flèches et appuyer sur Entrée.
5. Vérifier l'identifiant de connexion, le nom et le rôle que le script affiche.
6. Appuyer sur Entrée à `Generate a strong password?`.
7. Noter l'identifiant de connexion et le mot de passe que le script affiche.

Pour taper plutôt un mot de passe choisi, saisir `n` à l'étape 6. Le script le
demande deux fois. Un mot de passe compte au moins 8 caractères, avec au moins
une lettre et un chiffre.

## Réinitialiser le mot de passe sur Windows

1. Ouvrir `C:\wamp64\bin\php` dans **File Explorer** (**Explorateur de
   fichiers** sur un poste en français). Noter le nom du dossier qui commence
   par `php8.4`, par exemple `php8.4.1`. Les étapes suivantes l'appellent
   `php8.4.x`.
2. Ouvrir **Command Prompt** (**Invite de commandes**).
3. Lancer le script de réinitialisation. Remplacer `php8.4.x` par le nom du
   dossier noté à l'étape 1 :

    ```bat
    cd C:\wamp64\www\vlsm
    set PATH=C:\wamp64\bin\php\php8.4.x;%PATH%
    php bin\reset-user-password.php
    ```

4. Taper le numéro du compte et appuyer sur Entrée.
5. Appuyer sur Entrée à `Generate a strong password?`.
6. Noter l'identifiant de connexion et le mot de passe que le script affiche.

Sur Windows, un mot de passe tapé s'affiche à l'écran. Utiliser le mot de passe
généré à l'étape 5.

## Variantes courantes

| Situation | Commande |
| --- | --- |
| L'identifiant de connexion est connu | `sudo intelis reset-password --login admin` |
| Le compte est inactif et n'apparaît donc pas dans la liste | `sudo intelis reset-password --login admin --activate` |
| L'utilisateur doit choisir un nouveau mot de passe après s'être connecté | Ajouter `--force-reset` à l'une des commandes ci-dessus |

Remplacer `admin` par l'identifiant de connexion du compte. Le script lancé
directement et la commande Windows acceptent les mêmes options.

!!! warning "Ne pas modifier le mot de passe dans phpMyAdmin"

    InteLIS stocke une empreinte (hash) du mot de passe, et non le mot de passe.
    Une valeur saisie dans la colonne `password` de `user_details` ne fonctionne
    pas comme mot de passe.

## Vérifier que la réinitialisation a fonctionné

Se connecter sur la page de connexion d'InteLIS avec l'identifiant de connexion
et le mot de passe que le script a affichés :

```text
Password reset successfully.
Lab Admin signs in with:

    Login ID   admin
    Password   Xk4mT9pq27RwLa83
```

Si la page de connexion affiche un code captcha, le saisir. La page en demande
un après trois tentatives échouées.

Le nouveau mot de passe reste en place après la synchronisation STS suivante. La
synchronisation STS ne modifie jamais le mot de passe d'un utilisateur du
laboratoire.

Une fois l'administrateur connecté, il réinitialise les mots de passe des autres
utilisateurs depuis **ADMIN → Utilisateurs et rôles → Utilisateurs**.
