---
description: "Trouver et réparer les dossiers dans lesquels le serveur web ne peut pas écrire quand InteLIS signale \"Permission denied\"."
audience: [system-admin]
module: [all]
type: how-to
platform: ubuntu
reviewed: 2026-10-29
reviewed_against: 5.8.5
---
# Erreurs Permission denied

InteLIS signale `Permission denied` quand le serveur web ne peut pas écrire dans
l'un de ses dossiers.

1. Ouvrir un terminal sur la machine InteLIS et exécuter :

    ```bash
    intelis check
    ```

    Chaque dossier dans lequel le serveur web ne peut pas écrire apparaît comme
    une ligne en échec, par exemple `var/cache writable`. Une ligne `How to fix`
    suit, avec les commandes à exécuter.

    ??? info "Si `intelis check` n'est pas reconnu"

        L'installation est plus ancienne. Lancer la même vérification sous son
        ancien nom :

        ```bash
        intelis preflight
        ```

2. Exécuter la réparation qu'il affiche :

    ```bash
    intelis provision
    ```

    Elle crée les dossiers manquants et rétablit leur propriétaire et leurs
    droits d'accès. Elle demande le mot de passe administrateur.

    ??? failure "Si `intelis provision` échoue parce que `var/cache` appartient à root"

        `intelis provision` démarre l'application pour trouver ses dossiers. Un
        dossier `var/cache` appartenant à root l'arrête avant qu'elle puisse
        réparer quoi que ce soit. Cela suit en général une commande `composer`
        exécutée en root.

        Rendre d'abord le dossier. `intelis check` affiche ces lignes, avec les
        dossiers exacts, sous `if that cannot start` :

        ```bash
        sudo chown -R $(logname):www-data /var/www/intelis/var/cache
        sudo chmod -R g+w /var/www/intelis/var/cache
        ```

        Exécuter ensuite `intelis provision` à nouveau.

3. Relancer la vérification :

    ```bash
    intelis check
    ```

    Chaque ligne `writable` passe.

    ??? failure "Si un dossier échoue encore"

        Lancer la réparation complète des droits d'accès. Elle rétablit aussi le
        propriétaire dans toute l'installation après une copie ou une
        restauration :

        ```bash
        sudo intelis-refresh -p /var/www/intelis -m full
        ```

        Sur les installations plus anciennes, utiliser `/var/www/vlsm` à la place
        de `/var/www/intelis`.

??? warning "Ne pas élargir les droits d'accès sur /var/www"

    Une commande comme `setfacl -R -m u:www-data:rwx /var/www` donne au serveur
    web un accès en écriture à chaque fichier de chaque site de la machine. Une
    faille dans un seul site les atteint alors tous. L'erreur revient aussi
    après la mise à jour suivante, car la vraie panne est toujours là.

    Si un dossier doit être partagé avec un autre compte, accorder l'accès sur
    ce dossier uniquement.
