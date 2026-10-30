---
description: Mettre en file d'attente des renvois, des rafraîchissements de cache, des mises à niveau et des retours arrière sur un laboratoire connecté depuis le STS, et vérifier ou désactiver les commandes à distance.
audience: [system-admin]
module: [all]
type: how-to
platform: ubuntu
reviewed: 2026-10-29
reviewed_against: 5.8.5
---
# Lancer des commandes sur un laboratoire depuis le STS

Renvoyer des données, rafraîchir, mettre à niveau ou revenir en arrière sur un
laboratoire connecté depuis le STS, sans personne devant la machine du
laboratoire.

Pour le fonctionnement du plan de commande et ses raisons, voir la
[conception du plan de commande à distance](../remote-command-plane.md).

**Avant de commencer, vérifier ces deux points :**

- Le rôle du compte STS porte **Mettre une commande de laboratoire en file
  d'attente**, **Annuler la commande de laboratoire** et **Historique des
  commandes du laboratoire** sous **Surveillance**. Les accorder dans
  **ADMIN → Utilisateurs et rôles → Les rôles**. Sans **Mettre une commande de
  laboratoire en file d'attente**, le bouton **File d'attente** n'apparaît pas.
- Le laboratoire est en ligne et se synchronise avec le STS.

## Mettre une commande en file d'attente

1. Aller à **ADMIN → Surveillance → État de la synchronisation du
   laboratoire**.
2. Trouver la ligne du laboratoire. Dans la colonne **Plan de commande**,
   vérifier que la puce **courier** (**coursier** dans l'interface
   en français) affiche une heure récente. La mise à niveau, le retour arrière
   et les autres commandes root demandent aussi une puce **runner**
   (**exécuteur** dans l'interface en français) récente.

    ??? info "Si la colonne n'affiche qu'un tiret"

        Le laboratoire n'a jamais signalé le plan de commande. Soit sa version
        est trop ancienne, soit les commandes à distance sont désactivées sur le
        laboratoire. Voir
        [Activer ou désactiver les commandes à distance d'un laboratoire](#activer-ou-desactiver-les-commandes-a-distance-dun-laboratoire).

3. Sélectionner **File d'attente** dans la colonne **Actions**.

    ??? failure "Si **File d'attente** est grisé"

        Le laboratoire n'a pas interrogé le STS pour ses commandes au cours des
        dernières 24 heures. La mise en file d'attente reste désactivée tant
        qu'il ne l'a pas fait. Vérifier que la machine du laboratoire est
        allumée, en ligne, et que les commandes à distance y sont activées.

    ??? warning "Si la boîte de dialogue avertit au sujet des installations"

        Plus d'une installation s'est signalée récemment pour ce laboratoire.
        Une commande en file d'attente va à celle qui interroge le STS en
        premier, et l'autre ne la voit jamais. Déterminer quelle machine est en
        service avant de mettre en file d'attente une mise à niveau ou un retour
        arrière.

4. Choisir la **Commande**. Voir [Commandes](#commandes) pour le rôle de chacune.
   Les commandes grisées sont celles que le laboratoire n'a pas signalé pouvoir
   exécuter.

    ??? info "Si les commandes de mise à niveau et de retour arrière sont grisées"

        Le laboratoire ne propose que les commandes de base. L'un de ces cas
        s'applique :

        - **Allow Remote Upgrade** est désactivé sur le laboratoire. Voir
          [Activer ou désactiver les commandes à distance d'un laboratoire](#activer-ou-desactiver-les-commandes-a-distance-dun-laboratoire).
        - La base de données du laboratoire fonctionne sur une autre machine, ou
          la machine du laboratoire n'a pas systemd. Mettre ce laboratoire à
          niveau de la manière dont il a été installé.
        - Le laboratoire n'a pas encore envoyé de rapport complet de ses
          capacités. Attendre une synchronisation, puis rouvrir la boîte de
          dialogue.

5. Remplir les champs que la commande affiche :

    | Champ | Affiché pour | Valeur à saisir |
    | --- | --- | --- |
    | Module (facultatif) | Renvoyer les résultats, Renvoyer les demandes | Un module, ou laisser **Tous les modules activés**. |
    | Renvoyer les données des N derniers jours | Renvoyer les résultats, Renvoyer les demandes | Un nombre de jours de 1 à 3650. Laisser vide pour n'envoyer que les enregistrements pas encore synchronisés. **Renvoyer les demandes** ignore ce champ. |
    | Only these sample statuses (optional) | Renvoyer les résultats | Les statuts à envoyer, par exemple **Rejeté**. Tout laisser décoché pour envoyer tous les statuts. Un laboratoire sur une version plus ancienne ne peut pas utiliser ce champ avant d'être mis à jour. |
    | Préparation de la mise en scène à mettre en œuvre | Appliquer une mise à jour préparée | La version préparée à installer. |
    | Pas avant (facultatif) | Mise à niveau (préparation + application automatique), Préparer la mise à niveau uniquement, Appliquer une mise à jour préparée | L'heure la plus tôt à laquelle le laboratoire peut commencer, selon l'horloge du STS. Laisser vide pour commencer à la synchronisation suivante. |
    | Afficher la page de maintenance aux utilisateurs pendant l'application | Mise à niveau (préparation + application automatique), Appliquer une mise à jour préparée | Le cocher quand la version exécute des migrations de la base de données ou modifie les dépendances composer. |

6. Sélectionner **Mettre la commande en file d'attente**. Le message **Commande mise en
   file d'attente.** affiche l'ID de la commande. Un badge jaune comme
   `upgrade: pending` apparaît sur la ligne du laboratoire.

    ??? failure "S'il indique qu'une commande identique est déjà en cours"

        La même commande attend déjà ou s'exécute déjà pour ce laboratoire.
        Attendre qu'elle se termine, ou l'annuler tant qu'elle est encore
        `pending`.

7. Attendre la synchronisation suivante du laboratoire. Le laboratoire se
   synchronise environ toutes les 5 minutes. Le badge passe à `picked` ou
   `running`, puis disparaît quand la commande se termine.

    ??? tip "Pour annuler avant que le laboratoire ne la prenne"

        Sélectionner **×** sur le badge. Cela ne fonctionne que tant que le
        statut est `pending`. Une fois la commande prise par le laboratoire,
        elle s'exécute jusqu'au bout.

Pour voir comment elle s'est terminée, suivre
[Vérifier le résultat d'une commande](#verifier-le-resultat-dune-commande).

### Commandes

| Commande | Ce qu'elle fait | Demande le runner |
| --- | --- | --- |
| Ping (autotest, sans effets secondaires) | Confirme que le laboratoire prend et signale les commandes. Ne modifie rien. | Non |
| Renvoyer les résultats | Envoie de nouveau les résultats au STS. | Non |
| Renvoyer les demandes | Récupère de nouveau les demandes de test depuis le STS. | Non |
| Resynchronisation des métadonnées (forcée) | Récupère toutes les données de référence depuis le STS, sans tenir compte de l'heure de la dernière synchronisation, et envoie les métadonnées du laboratoire. | Non |
| Rafraîchir le cache | Vide le cache de fichiers du laboratoire. | Non |
| Renouveler le jeton STS | Abandonne le jeton STS du laboratoire et en obtient un nouveau. | Non |
| Actualiser les autorisations | Rétablit le propriétaire et les droits d'accès des fichiers de l'installation. | Oui |
| Redémarrer Apache | Redémarre le serveur web en douceur. | Oui |
| Mise à niveau (préparation + application automatique) | Télécharge la version actuelle et l'installe en une seule fois. | Oui |
| Préparer la mise à niveau uniquement | Télécharge et vérifie la version actuelle. Ne l'installe pas. | Oui |
| Appliquer une mise à jour préparée | Installe une version préparée par **Préparer la mise à niveau uniquement**. | Oui |
| Revenir à l'instantané antérieur à la mise à niveau (code uniquement) | Restaure le code d'avant la dernière mise à niveau. La base de données reste telle quelle. | Oui |

## Mettre à niveau un laboratoire

Choisir une méthode, puis suivre ses étapes de haut en bas.

=== "En une étape (mise à niveau)"

    Utiliser cette méthode pour une version courante sur un ou quelques
    laboratoires.

    ### Mettre la mise à niveau en file d'attente

    1. Aller à **ADMIN → Surveillance → État de la synchronisation du
       laboratoire**.
    2. Sur la ligne du laboratoire, vérifier que la puce **runner** de la colonne
       **Plan de commande** affiche une heure récente.
    3. Sélectionner **File d'attente**.
    4. Choisir **Mise à niveau (préparation + application automatique)**.
    5. Pour commencer à une heure fixée, la saisir dans **Pas avant**. Choisir
       une heure où le laboratoire est calme.
    6. Si la version exécute des migrations de la base de données ou modifie les
       dépendances composer, cocher **Afficher la page de maintenance aux
       utilisateurs pendant l'application**. Les utilisateurs voient alors une
       page "mise à niveau en cours" pendant l'installation au lieu
       d'erreurs.
    7. Sélectionner **Mettre la commande en file d'attente**.
    8. Attendre. Les utilisateurs continuent à travailler pendant le
       téléchargement de la version. L'installation elle-même est courte.

    ### Vérifier la mise à niveau

    9. Quand le badge `upgrade` disparaît, vérifier que la colonne **Version**
       affiche la nouvelle version.
    10. Sélectionner **Historique des commandes du laboratoire**. Trouver la
        ligne `upgrade` du laboratoire et sélectionner **Détails**. **Code de
        sortie** affiche `0` et **succeeded**.

        ??? failure "Si le statut est `failed`"

            Lire la sortie dans **Détails**.

            - Si le contrôle de fonctionnement a échoué, le programme de mise à
              jour a déjà restauré le code précédent. Le laboratoire fait
              tourner l'ancienne version sur la base de données migrée.
            - Si les migrations de la base de données ont échoué, le nouveau
              code reste en place et n'est pas retiré. Avec la page de
              maintenance cochée, le laboratoire continue de l'afficher.
              Quelqu'un devant la machine du laboratoire doit corriger la cause
              et relancer `intelis update`.

=== "Par étapes (préparer, tester, appliquer)"

    Utiliser cette méthode pour une version risquée ou pour de nombreux
    laboratoires. Chaque laboratoire télécharge d'abord la version, et ne
    l'installe que lorsqu'une application est mise en file d'attente.

    ### Préparer la version

    1. Aller à **ADMIN → Surveillance → État de la synchronisation du
       laboratoire**.
    2. Sur la ligne de chaque laboratoire, vérifier que la puce **runner** de la
       colonne **Plan de commande** affiche une heure récente.
    3. Sur la ligne de chaque laboratoire, sélectionner **File d'attente**.
    4. Choisir **Préparer la mise à niveau uniquement**.
    5. Pour commencer à une heure fixée, la saisir dans **Pas avant**.
    6. Sélectionner **Mettre la commande en file d'attente**.
    7. Attendre un badge bleu **Préparée: vX.Y.Z** sur la ligne de chaque
       laboratoire. Les utilisateurs ne sont pas gênés pendant qu'un
       laboratoire prépare.

        ??? info "Si un laboratoire affiche deux badges **Préparée**"

            Chaque préparation remplace la préparation plus ancienne sur la
            machine du laboratoire. Appliquer la plus récente. La plus ancienne
            échoue avec `staging dir invalid or READY sentinel missing`.

    ### Appliquer sur les laboratoires pilotes

    8. Choisir deux ou trois laboratoires pilotes.
    9. Sur la ligne d'un laboratoire pilote, sélectionner **File d'attente**.
    10. Choisir **Appliquer une mise à niveau préparée**.
    11. Dans **Mise à niveau préparée à appliquer**, choisir la
        version préparée.
    12. Pour commencer à une heure fixée, la saisir dans **Pas avant**.
    13. Si la version exécute des migrations de la base de données ou modifie
        les dépendances composer, cocher **Afficher la page de maintenance aux
        utilisateurs pendant l'application**.
    14. Sélectionner **Mettre la commande en file d'attente**.
    15. Quand le badge `upgrade-apply` disparaît, vérifier que la colonne
        **Version** affiche la nouvelle version.
    16. Sélectionner **Historique des commandes du laboratoire**. Trouver la
        ligne `upgrade-apply` du laboratoire et sélectionner **Détails**.
        **Code de sortie** affiche `0` et **succeeded**.

        ??? failure "Si le statut est `failed`"

            Lire la sortie dans **Détails**.

            - Si le contrôle de fonctionnement a échoué, le programme de mise à
              jour a déjà restauré le code précédent. Le laboratoire fait
              tourner l'ancienne version sur la base de données migrée.
            - Si les migrations de la base de données ont échoué, le nouveau
              code reste en place et n'est pas retiré. Avec la page de
              maintenance cochée, le laboratoire continue de l'afficher.
              Quelqu'un devant la machine du laboratoire doit corriger la cause
              et relancer `intelis update`.

        ??? info "Si le badge **Préparée** reste après une application réussie"

            Le badge ne disparaît pas tout seul. Se fier à la colonne
            **Version**. Ne pas appliquer de nouveau la même préparation. Elle
            échoue avec `no prepared record`.

    17. Refaire les étapes 9 à 16 pour les autres laboratoires pilotes.
    18. Surveiller les pilotes pendant un jour ou deux.

    ### Appliquer sur les autres laboratoires

    19. Si les pilotes fonctionnent bien, refaire les étapes 9 à 16 pour chaque
        laboratoire restant.

## Revenir en arrière sur un laboratoire

Revenir en arrière quand une mise à niveau s'est bien passée mais que le
laboratoire fonctionne mal avec la nouvelle version. Un retour arrière restaure
le code depuis l'instantané pris immédiatement avant la dernière mise à niveau. Il ne
touche pas à la base de données. Les migrations ne vont que vers l'avant. La
version précédente fonctionne donc ensuite sur la base de données plus récente.

Choisir une méthode, puis suivre ses étapes de haut en bas.

=== "Depuis le STS"

    ### Mettre le retour arrière en file d'attente

    1. Aller à **ADMIN → Surveillance → État de la synchronisation du
       laboratoire**.
    2. Sur la ligne du laboratoire, vérifier que la puce **runner** de la colonne
       **Plan de commande** affiche une heure récente.
    3. Sélectionner **File d'attente**.
    4. Choisir **Revenir à l'instantané antérieur à la mise à niveau (code
       uniquement)**. Un avertissement explique que la base de données n'est pas
       ramenée en arrière.

        ??? info "Si la commande de retour arrière est grisée"

            Le laboratoire n'a pas signalé pouvoir exécuter les retours arrière.
            Son courier est plus ancien que la commande de retour arrière, ou
            **Allow Remote Upgrade** est désactivé sur le laboratoire. Utiliser
            plutôt l'onglet **Sur la machine du laboratoire**.

    5. Sélectionner **Mettre la commande en file d'attente**.

        ??? failure "S'il signale `Unknown command`"

            Un STS plus ancien que cette version n'accepte pas les retours
            arrière depuis la file d'attente. Utiliser plutôt l'onglet **Sur la
            machine du laboratoire**.

    6. Attendre la synchronisation suivante du laboratoire, environ 5 minutes.
       Le badge `rollback` disparaît quand la commande se termine.

    ### Vérifier le retour arrière

    7. Sélectionner **Historique des commandes du laboratoire**. Trouver la
       ligne `rollback` du laboratoire et sélectionner **Détails**.
    8. Vérifier que **Code de sortie** affiche `0` et que la sortie se termine
       par `Rolled back` et le chemin de l'installation. La sortie nomme aussi
       l'instantané utilisé, après `Most recent snapshot:`.

        ??? failure "Si la sortie indique `No rollback snapshot found`"

            Il n'y a rien vers quoi revenir. La dernière mise à niveau s'est
            faite sans instantané, ou aucun n'a jamais été pris sur cette
            machine.

        ??? failure "Si la sortie indique `composer install failed during rollback`"

            Le code est restauré mais `vendor/` est absent, donc le laboratoire
            ne s'ouvre pas. La machine du laboratoire a besoin d'un accès à
            internet. Quelqu'un devant la machine du laboratoire relance
            `intelis update --rollback`.

    9. Après la synchronisation suivante, vérifier que la colonne **Version**
       affiche la version précédente.
    10. Demander au laboratoire d'ouvrir InteLIS et de confirmer que les pages
        qu'il utilise fonctionnent.

=== "Sur la machine du laboratoire"

    Cette méthode fonctionne toujours, même quand le laboratoire ne peut pas
    joindre le STS.

    ### Lancer le retour arrière

    1. Ouvrir un terminal sur la machine du laboratoire.
    2. Lancer :

        ```bash
        intelis update --rollback
        ```

        Saisir le mot de passe quand il est demandé.

        ??? info "Si `intelis` n'est pas reconnu"

            L'installation est plus ancienne. Lancer directement le programme de
            mise à jour :

            ```bash
            sudo intelis-update -p /var/www/intelis --rollback
            ```

            Sur les installations plus anciennes, utiliser `/var/www/vlsm` à la
            place de `/var/www/intelis`.

    3. Lire la ligne qui commence par `Most recent snapshot:`. Elle nomme
       l'instantané en cours de restauration.
    4. Attendre `Rolled back` et le chemin de l'installation.

        ??? failure "S'il indique `No rollback snapshot found`"

            Il n'y a rien vers quoi revenir. La dernière mise à niveau s'est
            faite sans instantané, ou aucun n'a jamais été pris sur cette
            machine.

        ??? failure "S'il indique `composer install failed during rollback`"

            Le code est restauré mais `vendor/` est absent, donc le laboratoire
            ne s'ouvre pas. Connecter la machine à internet et relancer la même
            commande.

    ### Vérifier le retour arrière

    5. Ouvrir InteLIS dans le navigateur.
    6. Confirmer que les pages utilisées par le laboratoire fonctionnent.

    !!! warning "Ne pas recopier l'instantané à la main"

        L'instantané laisse de côté les fichiers téléversés, les données
        d'exécution et `vendor/`. Le copier sur l'installation avec
        `rsync --delete` supprime les fichiers téléversés du laboratoire et ne
        laisse aucun `vendor/`. La commande de retour arrière saute les mêmes
        dossiers et réinstalle `vendor/`.

Après un retour arrière, corriger la cause, puis refaire la mise à niveau avec
une version corrigée.

## Vérifier le résultat d'une commande

1. Aller à **ADMIN → Surveillance → État de la synchronisation du
   laboratoire**.
2. Sélectionner **Historique des commandes du laboratoire**. La page s'ouvre
   dans un nouvel onglet et liste les 200 commandes les plus récentes.
3. Pour trouver une commande plus ancienne, filtrer la liste par **Laboratoire**,
   **Commande**, **Statut** ou **Plage de dates**, puis sélectionner
   **Rechercher**.
4. Sur la ligne de la commande, sélectionner **Détails**.
5. Lire **Statut**. Pour une commande terminée, lire aussi **Code de sortie** et
   la sortie en dessous.

| Statut | Signification |
| --- | --- |
| `pending` | En file d'attente. Le laboratoire ne l'a pas encore prise. |
| `picked` | Le laboratoire l'a prise. |
| `running` | La machine du laboratoire l'exécute. |
| `prepared` | Une **Préparer la mise à niveau uniquement** est terminée. La version attend une application. |
| `completed` | Terminée. **Code de sortie** vaut `0`. |
| `failed` | Terminée avec une erreur. Lire la sortie. |
| `expired` | A dépassé son délai avant que le laboratoire ne l'exécute. |
| `cancelled` | Annulée pendant qu'elle était `pending`. |

Pour relancer une commande terminée avec les mêmes réglages, sélectionner
**Rejouer** sur sa ligne.

??? failure "Si une commande reste `pending`"

    Le laboratoire n'interroge pas le STS. Vérifier la puce **courier** sur
    **État de la synchronisation du laboratoire**. Si elle est ancienne ou
    absente, le laboratoire est hors ligne ou ses commandes à distance sont
    désactivées. Une commande avec **Pas avant** reste aussi `pending` jusqu'à
    cette heure.

??? failure "Si une commande reste `picked` ou `running`"

    Le laboratoire l'a prise mais n'a pas rendu compte. Sur la machine du
    laboratoire, lire le journal du runner pour les commandes root :

    ```bash
    sudo tail -n 100 /var/log/intelis-runner/runner-$(date +%Y%m%d).log
    systemctl status intelis-runner.timer
    ```

    Pour les autres commandes, lire `/var/log/apache2/error.log` et les journaux
    de l'application.

??? failure "Si elle a échoué avec `Stale: no status report received within 2 hours of pick-up`"

    Le STS a cessé d'attendre au bout de 2 heures. La commande s'est peut-être
    quand même terminée sur le laboratoire. Pour une mise à niveau, vérifier la
    colonne **Version** avant de la remettre en file d'attente.

??? failure "Si le résultat indique `runner disabled on this instance`"

    **Allow Remote Upgrade** est désactivé sur le laboratoire, ou la base de
    données du laboratoire est sur une autre machine, ou la machine n'a pas
    systemd. Le runner refuse alors les commandes root. Voir
    [Activer ou désactiver les commandes à distance d'un laboratoire](#activer-ou-desactiver-les-commandes-a-distance-dun-laboratoire).

## Activer ou désactiver les commandes à distance d'un laboratoire

Deux paramètres du laboratoire contrôlent les commandes à distance. Les deux
sont activés par défaut.

| Paramètre | Quand il est désactivé |
| --- | --- |
| `remote_commands_enabled` | Le laboratoire ignore toutes les commandes à distance. Les commandes en file d'attente restent `pending`. |
| `allow_remote_upgrade` | Le laboratoire refuse les commandes root : mises à niveau, retour arrière, **Actualiser les autorisations** et **Redémarrer Apache**. Les autres commandes s'exécutent toujours. |

Aucun écran ne modifie ces paramètres. Les modifier dans la base de données du
laboratoire, sur la machine du laboratoire.

1. Ouvrir un terminal sur la machine du laboratoire.
2. Ouvrir la base de données :

    ```bash
    sudo mysql vlsm
    ```

    Si la base de données porte un autre nom, utiliser le nom indiqué dans
    `configs/config.production.php`.

3. Exécuter l'instruction correspondant à la modification voulue :

    | Pour | Exécuter |
    | --- | --- |
    | Désactiver toutes les commandes à distance | `UPDATE global_config SET value = 'no' WHERE name = 'remote_commands_enabled';` |
    | Désactiver seulement les commandes root | `UPDATE global_config SET value = 'no' WHERE name = 'allow_remote_upgrade';` |
    | Réactiver toutes les commandes à distance | `UPDATE global_config SET value = 'yes' WHERE name = 'remote_commands_enabled';` |
    | Réactiver les commandes root | `UPDATE global_config SET value = 'yes' WHERE name = 'allow_remote_upgrade';` |

4. Vérifier le résultat :

    ```sql
    SELECT name, value FROM global_config
     WHERE name IN ('remote_commands_enabled', 'allow_remote_upgrade');
    ```

5. Taper `exit` pour quitter la base de données.
6. Vider le cache pour que le laboratoire lise la nouvelle valeur maintenant, et
   non dans l'heure :

    ```bash
    intelis purge-cache
    ```

7. Attendre la synchronisation suivante du laboratoire, environ 5 minutes.

    ??? warning "Une synchronisation forcée des métadonnées réactive un paramètre"

        Les deux paramètres descendent du STS avec les données de référence du
        laboratoire. Une synchronisation forcée des métadonnées, y compris la
        commande **Resynchronisation des métadonnées (forcée)**, copie la valeur
        du STS par-dessus celle du laboratoire. Vérifier de nouveau le paramètre
        après une telle synchronisation.

### Vérifier la modification

8. Sur **État de la synchronisation du laboratoire**, ouvrir la boîte de
   dialogue **File d'attente** du laboratoire.

    - Avec les commandes root désactivées, les commandes de mise à niveau et de
      retour arrière sont grisées.
    - Avec toutes les commandes à distance désactivées, la puce **courier**
      cesse de se mettre à jour. Au bout de 24 heures, le bouton **File
      d'attente** est grisé.
    - Une fois les commandes réactivées, elles sont de nouveau proposées après la
      synchronisation suivante.
