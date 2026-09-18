# Utiliser l'espace System Admin

System Admin est un second espace d'administration, à `/system-admin` sur
l'adresse de l'installation. Il contient les paramètres qui définissent la
nature de l'installation : sa base de données, son type d'instance, son STS et
ses modules.

La plupart des laboratoires ne l'ouvrent jamais. Ces paramètres sont réglés une
fois, à l'installation.

## Avant de commencer

- Un identifiant System Admin. Il est distinct des comptes utilisateurs
  d'InteLIS. Un administrateur InteLIS n'en détient pas automatiquement
- Une sauvegarde récente de la base de données avant de modifier la
  **Configuration du système**. Voir [Maintenance](../guides/maintenance.md)

## La barre latérale

| Élément | Contient |
| --- | --- |
| Configuration du système | Connexion à la base de données, type d'instance, URL STS, laboratoire, modules activés, pays, fuseau horaire, paramétrage SMTP |
| Vue d'ensemble de l'instance | L'ID d'instance et la dernière synchronisation de chaque flux de données |
| Statistiques API | Les requêtes API traitées par cette installation |
| Historique de connexion de l’utilisateur | Chaque tentative de connexion à InteLIS, avec l'adresse IP, le navigateur et le système d'exploitation |
| Réinitialiser le mot de passe | Un nouveau mot de passe pour tout utilisateur InteLIS |
| Déconnexion | Ferme la session System Admin |

## Se connecter

1. Ouvrir `/system-admin` sur l'adresse de l'installation, par exemple
   `https://lab.example.org/system-admin`.
2. Saisir le **Nom d'utilisateur** et le **Mot de passe** System Admin.
3. Sélectionner **Connexion**. La **Configuration du système** s'ouvre.

??? info "S'il n'existe encore aucun identifiant System Admin"

    `/system-admin` ouvre **Enregistrer un nouvel administrateur de système** à
    la place. Le formulaire demande une **Clé secrète**. Elle se trouve dans le
    fichier `var/secret-key.txt` du dossier de l'installation, par exemple
    `/var/www/intelis/var/secret-key.txt`. Sur les installations plus
    anciennes, le dossier est `/var/www/vlsm`. Remplir le formulaire et
    sélectionner **Envoyer**.

## Modifier la configuration du système

1. Faire une sauvegarde de la base de données.
2. Se connecter à System Admin.
3. Sélectionner **Configuration du système** dans la barre latérale. La page
   s'intitule **Modifier la configuration du système**.
4. Modifier le paramètre :

    | Section | Paramètres |
    | --- | --- |
    | Paramètres système | **Nom de l'hôte de la base de données**, **Nom d’utilisateur de la base de données**, **Mot de passe de la base de données**, **Nom de la base de données**, **Port de la base de données** |
    | Réglages de l’instance | **Type d'instance**, **URL STS**, **Nom du Labo**, **Modules activés**, **Pays d'installation**, **Fuseau horaire** |
    | Paramétrage SMTP | Le serveur de messagerie utilisé pour envoyer les courriels |

5. Sélectionner **Envoyer**.
6. Ouvrir InteLIS et vérifier la modification. Voir
   [Vérifier que tout fonctionne](#verifier-que-tout-fonctionne).

??? warning "Un mauvais paramètre de base de données arrête InteLIS pour tous"

    Un mauvais **Mot de passe de la base de données**, ou toute autre valeur de base de données
    erronée, rend InteLIS inaccessible à tous les utilisateurs jusqu'à sa
    correction. Un **Mot de passe de la base de données** vide ne reste pas vide : InteLIS
    enregistre un mot de passe par défaut à sa place.

??? warning "Type d'instance"

    | Type d'instance | Signifie |
    | --- | --- |
    | LIS - SYSTÈME D'INFORMATION DE LABORATOIRE | Fonctionne dans un laboratoire et se synchronise avec le STS |
    | STS - SYSTÈME DE SUIVI DES ÉCHANTILLONS | Le serveur central avec lequel les laboratoires se synchronisent |
    | Mode autonome | Ne se synchronise nulle part |

    Changer le type d'instance d'une installation en service change la
    destination de ses données, et les pages qui apparaissent. Le faire valider
    par l'équipe nationale d'abord.

??? info "Modules activés"

    Activer un module ajoute son menu et sa section de configuration. En
    désactiver un les masque. Les fiches déjà créées restent dans la base de
    données.

## Lire la vue d'ensemble de l'instance

1. Se connecter à System Admin.
2. Sélectionner **Vue d'ensemble de l'instance** dans la barre latérale.
3. Lire les lignes :

    | Champ | Signifie |
    | --- | --- |
    | ID d'instance | L'identifiant sous lequel cette installation est connue |
    | Ajouté, Mis à jour le | L'enregistrement de l'instance et sa dernière modification |
    | VL Dernière synchronisation, Dernière synchronisation EID, Dernière synchronisation Covid-19 | Le dernier envoi des données de chaque module vers le tableau de bord |
    | Demande à distance Dernière synchronisation | La dernière réception des demandes de test depuis le STS |
    | Résultats à distance Dernière synchronisation | Le dernier envoi des résultats vers le STS |
    | Référence à distance Dernière synchronisation | La dernière réception des listes et des structures depuis le STS |

Une ancienne date de synchronisation à distance signifie que l'installation
n'atteint pas le STS. Le confirmer sous **ADMIN → Surveillance → Historique de
l’API**. Voir
[Surveillance et audit](admin-monitoring.md#verifier-que-les-donnees-ont-atteint-le-sts).

Le bouton crayon modifie ces dates. Ne les changer qu'à la demande du support
InteLIS.

## Vérifier qui s'est connecté

1. Se connecter à System Admin.
2. Sélectionner **Historique de connexion de l’utilisateur** dans la barre
   latérale.
3. Renseigner **Date** et **Identifiant de connexion**, puis sélectionner
   **Rechercher**.
4. Lire les lignes : **Date de la tentative**, **Adresse IP**, **Navigateur**,
   **Système d'exploitation** et **Statut**.

Un même identifiant de connexion utilisé depuis plusieurs endroits à la fois
signale un compte partagé ou détourné.

## Réinitialiser le mot de passe d'un utilisateur

À utiliser lorsqu'aucun administrateur InteLIS ne peut se connecter pour le
faire.

1. Se connecter à System Admin.
2. Sélectionner **Réinitialiser le mot de passe** dans la barre latérale.
3. Choisir l'**Utilisateur**.
4. Saisir **Mot de passe** et **Confirmer le mot de passe**, ou sélectionner
   **Générer**.
5. Régler **Statut** sur **Actif**.
6. Sélectionner **Envoyer**.
7. Remettre le nouveau mot de passe à l'utilisateur en main propre.

## Vérifier que tout fonctionne

| Modification | Contrôle |
| --- | --- |
| Module activé | Sa section apparaît dans le menu principal et sous ADMIN |
| Type d'instance | Les pages de ce type apparaissent |
| URL STS | L'Historique de l'API enregistre une synchronisation réussie vers la nouvelle adresse |
| Paramétrage SMTP | Envoyer un résultat par courriel et vérifier qu'il arrive |
| Mot de passe réinitialisé | L'utilisateur se connecte avec le nouveau mot de passe |
