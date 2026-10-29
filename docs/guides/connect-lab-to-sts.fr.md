---
description: Connecter l'InteLIS d'un laboratoire au STS, ou réparer la connexion, avec sudo intelis sts-setup.
audience: [system-admin]
module: [all]
type: how-to
platform: ubuntu
reviewed: 2026-10-29
reviewed_against: 5.8.5
---
# Connecter un laboratoire au STS

L'InteLIS d'un laboratoire envoie les résultats au STS et en reçoit les
demandes. Les deux exigent trois éléments : l'adresse du STS, le laboratoire
que sert cette machine, et un jeton STS. Une seule commande les règle ou les
répare tous les trois :

```bash
sudo intelis sts-setup
```

L'exécuter sur la machine InteLIS du laboratoire, jamais sur le STS lui-même.

**Choisir la situation qui correspond, puis suivre ses étapes de haut en bas.**

=== "Première connexion"

    Utiliser cette situation quand le laboratoire n'a jamais été connecté au
    STS, ou a été installé avec l'adresse du STS laissée vide.

    1. Obtenir l'adresse du STS auprès du programme national, par exemple
       `https://sts.example.org`.
    2. Ouvrir un terminal sur la machine InteLIS et exécuter :

        ```bash
        sudo intelis sts-setup
        ```

    3. À `Enter STS URL (or press Enter to skip)`, saisir l'adresse du STS.
       La configuration vérifie que le STS répond avant d'enregistrer
       l'adresse.

        ??? failure "S'il affiche `Cannot connect to STS at this URL`"

            La configuration essaie d'abord `https://`, puis `http://`. Aucun
            des deux n'a atteint le STS.

            - Vérifier que l'adresse ne contient pas de faute de frappe.
            - Ouvrir l'adresse dans un navigateur sur cette machine. Si elle
              ne s'affiche pas, la machine n'a pas d'accès au STS. Vérifier la
              connexion internet ou le VPN.
            - Saisir de nouveau l'adresse. La configuration autorise cinq
              tentatives.

        ??? warning "S'il avertit `This STS URL uses plain HTTP`"

            Le jeton et chaque résultat synchronisé circulent sans
            chiffrement. Utiliser l'adresse `https://` dès que le STS dispose
            d'un certificat, et refaire ces étapes.

    4. Attendre `Refreshing Database Metadata`. La configuration télécharge
       les structures, les laboratoires et les listes depuis le STS. Le
       premier téléchargement peut durer plusieurs minutes sans rien
       afficher.
    5. À **Select lab** (sélectionner le laboratoire), saisir une partie du
       nom du laboratoire, puis appuyer sur Entrée sur le bon. Si le STS ne
       liste qu'un seul laboratoire, la configuration le choisit sans
       demander.

        ??? failure "S'il affiche `No active testing labs found`"

            Le STS ne contient aucun laboratoire d'analyse actif. Demander à
            l'administrateur du STS d'ajouter le laboratoire, ou de le marquer
            actif. Puis refaire l'étape 2.

    6. Vérifier les dernières lignes. Les deux doivent apparaître :

        ```text
        [OK] STS setup complete!
        [OK] Token generated: ...
        ```

        ??? failure "S'il affiche `Failed to generate token`"

            Le STS a refusé cette machine. Envoyer le message complet à
            l'administrateur du STS. Une fois le problème corrigé, obtenir un
            nouveau jeton seul :

            ```bash
            sudo intelis token
            ```

    7. Lancer la première synchronisation. Utiliser la situation **Vérifier la
       connexion**.

=== "Mauvais STS ou mauvais laboratoire"

    Utiliser cette situation quand l'adresse du STS a changé, ou que la
    machine est réglée sur le mauvais laboratoire.

    1. Exécuter :

        ```bash
        sudo intelis sts-setup
        ```

    2. La configuration affiche `Current STS URL`. À
       `Is this STS URL correct?` :
        - Pour la garder, appuyer sur Entrée.
        - Pour la changer, répondre **no** (non), puis saisir la nouvelle
          adresse.
    3. La configuration affiche le laboratoire actuel. À
       `Is this the correct lab?` :
        - Pour le garder, appuyer sur Entrée. Puis à `Do you want to refresh the
          metadata?`, appuyer sur Entrée pour télécharger les dernières listes
          depuis le STS.
        - Pour le changer, répondre **no**. La configuration télécharge les
          listes, puis demande le laboratoire. Le choisir comme dans
          **Première connexion**, étape 5.

        !!! warning "Changer le laboratoire seulement pour corriger une erreur"

            Les résultats et les demandes suivent le laboratoire réglé ici. Le
            changer seulement quand la machine a été réglée sur le mauvais
            laboratoire.

    4. Vérifier que les dernières lignes contiennent `STS setup complete!` et
       `Token generated`.
    5. Lancer une synchronisation. Utiliser la situation **Vérifier la
       connexion**.

=== "Vérifier la connexion"

    Utiliser cette situation après la configuration, ou pour confirmer qu'un
    laboratoire est toujours connecté.

    1. Lancer tout de suite une synchronisation complète, au lieu d'attendre
       la synchronisation planifiée :

        ```bash
        sudo intelis sync-sts
        ```

        Elle renouvelle le jeton, puis échange les listes, les résultats, les
        demandes et les commandes à distance avec le STS.

    2. Lire la sortie à la recherche d'erreurs. Si une étape échoue, voir
       [Résultats qui n'atteignent pas le STS](sts-sync-not-working.md).

## Quand la configuration s'arrête tôt

??? failure "`STS setup can only be run for LIS instances.`"

    La machine n'est pas configurée comme laboratoire. Son type d'instance est
    **Mode autonome** ou **STS**. Pour le changer, voir l'avertissement
    **Type d'instance** dans
    [Utiliser l'espace System Admin](../user-guides/admin-system-administration.md).
    Un laboratoire en mode autonome n'a pas de STS. Il n'a donc besoin
    d'aucune connexion.

??? failure "`No STS URL configured; skipping metadata refresh`"

    L'adresse du STS a été sautée. Refaire les étapes avec l'adresse sous la
    main.

??? failure "`Metadata refresh failed`"

    Le STS a répondu à la vérification de l'adresse mais a échoué pendant le
    téléchargement. Relancer le téléchargement seul pour voir l'erreur :

    ```bash
    sudo intelis metadata-sync
    ```

    Puis exécuter de nouveau `sudo intelis sts-setup`.

??? failure "`intelis` n'est pas reconnu"

    L'installation est plus ancienne.
    [Mettre à jour InteLIS](updating-intelis-on-ubuntu.md), puis refaire les
    étapes.
