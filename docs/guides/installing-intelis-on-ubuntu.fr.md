---
description: Installer InteLIS sur une nouvelle machine Ubuntu LTS comme machine du laboratoire ou comme serveur central STS.
audience: [system-admin]
module: [all]
type: how-to
platform: ubuntu
reviewed: 2026-10-29
reviewed_against: 5.8.5
---
# Installer InteLIS sur Ubuntu 24.04 ou ultérieur (Ubuntu LTS uniquement)

Installer InteLIS sur une nouvelle machine Ubuntu, comme machine du
laboratoire ou comme serveur central.

La machine doit exécuter **Ubuntu 24.04 LTS ou une version LTS ultérieure** et
être connectée à internet. Le compte utilisé a besoin des droits `sudo`.

InteLIS exige **PHP 8.4+ (minimum 8.4.1)**. PHP 8.2, 8.3 et les versions plus
anciennes ne sont pas pris en charge. Le programme d'installation choisit
PHP 8.4, ou 8.5 sur Ubuntu 26.04 et ultérieur.

Pour déplacer un laboratoire existant vers une nouvelle machine avec ses
données, suivre plutôt
[Migrer d'une machine Ubuntu à une autre](migrating-ubuntu-machines.md).

**Choisir la situation qui correspond, puis suivre ses étapes de haut en bas.**

=== "Machine du laboratoire (LIS)"

    ### Installer

    1. Ouvrir un terminal et télécharger le programme d'installation :

        ```bash
        cd ~ && wget -O setup.sh "https://raw.githubusercontent.com/deforay/intelis/master/scripts/setup.sh?v=$(date +%s)"
        ```

    2. Lancer le programme d'installation :

        ```bash
        sudo bash setup.sh
        ```

        ??? failure "S'il s'arrête avec `This script requires an Ubuntu LTS release` ou `requires Ubuntu 22.04 or newer`"

            La machine exécute une version d'Ubuntu non LTS ou trop ancienne.
            Réinstaller la machine avec Ubuntu 24.04 LTS ou ultérieur, puis
            recommencer à l'étape 1.

    3. Répondre aux questions du programme d'installation :

        | Question | Réponse |
        | --- | --- |
        | Installation directory | Appuyer sur Entrée. |
        | What is this machine? | **Lab machine (LIS)** (machine du laboratoire). |
        | Remote STS URL | L'adresse du STS, par exemple `https://sts.example.org`. La demander au programme national. Laisser vide si le laboratoire n'a pas de STS. |
        | New MySQL root password | Un nouveau mot de passe pour cette machine, saisi deux fois. Le noter. |
        | Is this correct? | Vérifier le récapitulatif, puis appuyer sur Entrée (Yes). |

        ??? failure "S'il indique `Failed to validate the provided STS URL`"

            Le programme d'installation n'a pas pu joindre
            `<STS address>/api/version.php`. Vérifier l'adresse et corriger
            les fautes de frappe, puis la saisir de nouveau. Après trois
            essais échoués, le programme d'installation continue sans STS.
            Saisir l'adresse plus tard, à l'étape 8.

        ??? info "S'il demande `Reuse your previous setup answers and skip the prompts?`"

            Une exécution précédente sur cette machine s'est arrêtée en cours
            de route. Appuyer sur Entrée (Yes) pour réutiliser les mêmes
            réponses.

        ??? info "S'il ne demande pas de mot de passe MySQL"

            MySQL est déjà configuré sur cette machine. Le programme
            d'installation lit son mot de passe dans `/root/.my.cnf`.

        ??? info "S'il indique `An existing database was found`"

            L'installation est relancée sur une machine qui contient déjà des
            données du laboratoire. Choisir **Keep a copy, then start fresh**
            (garder une copie, puis repartir de zéro) pour mettre les
            anciennes données de côté sans les perdre.

    4. Attendre la fin du programme d'installation. Il vérifie la nouvelle
       installation, puis se termine par `Setup complete`.
    5. Lire la liste `Install Check` affichée au-dessus de `Setup complete`.
       Chaque ligne commence par l'un de ces mots :

        | Mot | Signification |
        | --- | --- |
        | `PASS` | La vérification a réussi. |
        | `WARN` | InteLIS fonctionne, mais le réglage ne convient pas à un laboratoire. La ligne indique ce qu'il faut modifier, et la plupart donnent la commande qui le modifie. |
        | `FAIL` | InteLIS ne peut pas fonctionner correctement tant que ce point n'est pas corrigé. La ligne donne la commande qui le corrige. |
        | `SKIP` | La vérification ne s'applique pas à cette machine. |

        Exécuter chaque commande indiquée dans la liste, puis vérifier de
        nouveau :

        ```bash
        intelis check
        ```

    ### Configurer dans le navigateur


    L'assistant de configuration s'affiche en anglais, car la langue n'est
    pas encore choisie. Les libellés ci-dessous sont ceux de l'écran.

    6. Ouvrir <http://intelis/> dans un navigateur sur cette machine. Depuis
       un autre ordinateur du réseau, utiliser plutôt l'adresse IP de cette
       machine. La page **Database Setup** (configuration de la base de données) s'ouvre.
    7. Les informations de la base de données sont déjà renseignées.
       Sélectionner **Next** (suivant).
    8. Sur **Instance Setup** (configuration de l'instance), renseigner :

        | Champ | Réponse |
        | --- | --- |
        | Instance type | **LIS with Remote Ordering Enabled** (LIS avec commande à distance). Si le laboratoire n'a pas de STS, choisir **Standalone (no Remote Ordering)** (autonome, sans commande à distance). |
        | STS URL | Déjà renseignée à l'étape 3. Si elle est vide, saisir l'adresse du STS. |
        | Testing lab | Le laboratoire que dessert cette machine. La liste vient du STS. Si elle est vide, sélectionner le bouton d'actualisation à côté de l'URL STS. |
        | Modules to enable | Les tests que réalise ce laboratoire. |
        | Country of installation | Le formulaire de demande du pays. |
        | Timezone | Le fuseau horaire du laboratoire. |
        | System language | La langue des écrans. |

    9. Sélectionner **Next**.
    10. Sur **Admin Setup** (configuration de l'administrateur),
        saisir l'adresse e-mail (**Email ID**), le nom complet, l'identifiant de
        connexion et le mot de passe du premier administrateur. Le mot de
        passe doit compter au moins 8 caractères, dont au moins une lettre et
        un chiffre.
    11. Sélectionner **Finish** (terminer). La page de connexion s'ouvre.

    ### Configurer les sauvegardes

    12. Dans un terminal, choisir où envoyer les sauvegardes :

        ```bash
        intelis backup setup
        ```

        L'exécuter sans `sudo`. La commande demande le mot de passe
        administrateur quand elle en a besoin. Elle propose une autre machine
        Linux, un dossier partagé Windows ou une clé USB.
        [Configurer les sauvegardes hors de la machine](setting-up-off-machine-backups.md)
        détaille les questions pour chaque destination.

    ### Vérifier le laboratoire

    13. Se connecter avec l'identifiant de connexion et le mot de passe de
        l'étape 10.
    14. Vérifier les paramètres du laboratoire sous **ADMIN → Paramètres →
        Configuration générale**.
    15. Dans un terminal, confirmer que les sauvegardes fonctionnent :

        ```bash
        intelis backup status
        ```

    Pour mettre à jour cette machine plus tard, suivre
    [Mettre à jour InteLIS sur Ubuntu](updating-intelis-on-ubuntu.md).

=== "Serveur central (STS)"

    ### Installer

    1. Faire pointer l'adresse web du serveur vers cette machine dans le DNS,
       par exemple `sts.example.org`. Les laboratoires utilisent cette
       adresse pour joindre le STS. Pour utiliser plutôt l'adresse IP de la
       machine, passer cette étape.
    2. Ouvrir un terminal et télécharger le programme d'installation :

        ```bash
        cd ~ && wget -O setup.sh "https://raw.githubusercontent.com/deforay/intelis/master/scripts/setup.sh?v=$(date +%s)"
        ```

    3. Lancer le programme d'installation :

        ```bash
        sudo bash setup.sh
        ```

        ??? failure "S'il s'arrête avec `This script requires an Ubuntu LTS release` ou `requires Ubuntu 22.04 or newer`"

            La machine exécute une version d'Ubuntu non LTS ou trop ancienne.
            Réinstaller la machine avec Ubuntu 24.04 LTS ou ultérieur, puis
            recommencer à l'étape 2.

    4. Répondre aux questions du programme d'installation :

        | Question | Réponse |
        | --- | --- |
        | Installation directory | Appuyer sur Entrée. |
        | What is this machine? | **Central server (STS)** (serveur central). |
        | Web address of this server | L'adresse de l'étape 1, sans `http://`. Appuyer sur Entrée si les laboratoires utilisent l'adresse IP. |
        | New MySQL root password | Un nouveau mot de passe pour cette machine, saisi deux fois. Le noter. |
        | Is this correct? | Vérifier le récapitulatif, puis appuyer sur Entrée (Yes). |

        ??? failure "S'il indique que l'adresse `already resolves to` une autre adresse"

            Le DNS fait pointer l'adresse web vers une autre machine. Répondre
            No à `Use … anyway?`. Puis saisir l'adresse correcte, ou appuyer
            sur Entrée pour utiliser l'adresse IP.

        ??? info "S'il demande `Reuse your previous setup answers and skip the prompts?`"

            Une exécution précédente sur cette machine s'est arrêtée en cours
            de route. Appuyer sur Entrée (Yes) pour réutiliser les mêmes
            réponses.

        ??? info "S'il ne demande pas de mot de passe MySQL"

            MySQL est déjà configuré sur cette machine. Le programme
            d'installation lit son mot de passe dans `/root/.my.cnf`.

        ??? info "S'il indique `An existing database was found`"

            L'installation est relancée sur une machine qui contient déjà des
            données. Choisir **Keep a copy, then start fresh** (garder une
            copie, puis repartir de zéro) pour mettre les anciennes données de
            côté sans les perdre.

    5. Attendre la fin du programme d'installation. Il vérifie la nouvelle
       installation, puis se termine par `Setup complete`.
    6. Lire la liste `Install Check` affichée au-dessus de `Setup complete`.
       Chaque ligne commence par l'un de ces mots :

        | Mot | Signification |
        | --- | --- |
        | `PASS` | La vérification a réussi. |
        | `WARN` | InteLIS fonctionne, mais le réglage ne convient pas à un serveur. La ligne indique ce qu'il faut modifier, et la plupart donnent la commande qui le modifie. |
        | `FAIL` | InteLIS ne peut pas fonctionner correctement tant que ce point n'est pas corrigé. La ligne donne la commande qui le corrige. |
        | `SKIP` | La vérification ne s'applique pas à cette machine. |

        Exécuter chaque commande indiquée dans la liste, puis vérifier de
        nouveau :

        ```bash
        intelis check
        ```

    ### Configurer dans le navigateur


    L'assistant de configuration s'affiche en anglais, car la langue n'est
    pas encore choisie. Les libellés ci-dessous sont ceux de l'écran.

    7. Ouvrir l'adresse web de l'étape 4 dans un navigateur, par exemple
       `http://sts.example.org/`. Si les laboratoires utilisent l'adresse
       IP, ouvrir `http://` suivi de l'adresse IP. La page **Configuration de
       la base de données** s'ouvre.
    8. Les informations de la base de données sont déjà renseignées.
       Sélectionner **Next**.
    9. Sur **Instance Setup**, renseigner :

        | Champ | Réponse |
        | --- | --- |
        | Instance type | **Sample Tracking System(STS)** (système de suivi des échantillons). |
        | Modules to enable | Les tests que réalisent les laboratoires. |
        | Country of installation | Le formulaire de demande du pays. |
        | Timezone | Le fuseau horaire du pays. |
        | System language | La langue des écrans. |

    10. Sélectionner **Next**.
    11. Sur **Admin Setup**, saisir
        l'adresse e-mail (**Email ID**), le nom complet, l'identifiant de
        connexion et le mot de passe du premier administrateur. Le mot de
        passe doit compter au moins 8 caractères, dont au moins une lettre et
        un chiffre.
    12. Sélectionner **Finish**. La page de connexion s'ouvre.

    ### Configurer les sauvegardes

    13. Dans un terminal, choisir où envoyer les sauvegardes :

        ```bash
        intelis backup setup
        ```

        L'exécuter sans `sudo`. La commande demande le mot de passe
        administrateur quand elle en a besoin. Elle propose une autre machine
        Linux, un dossier partagé Windows ou une clé USB.
        [Configurer les sauvegardes hors de la machine](setting-up-off-machine-backups.md)
        détaille les questions pour chaque destination.

    ### Vérifier le serveur

    14. Se connecter avec l'identifiant de connexion et le mot de passe de
        l'étape 11.
    15. Vérifier les paramètres sous **ADMIN → Paramètres → Configuration
        générale**.
    16. Depuis un ordinateur d'un laboratoire, ouvrir l'adresse web de
        l'étape 4. La page de connexion d'InteLIS s'affiche.
    17. Dans un terminal, confirmer que les sauvegardes fonctionnent :

        ```bash
        intelis backup status
        ```

    Pour mettre à jour cette machine plus tard, suivre
    [Mettre à jour InteLIS sur Ubuntu](updating-intelis-on-ubuntu.md).
