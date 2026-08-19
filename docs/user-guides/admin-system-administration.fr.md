# Utiliser l'espace System Admin

Ce guide couvre `/system-admin`, un second espace d'administration situé hors du
menu ADMIN et doté de sa propre connexion.

Il contient les paramètres qui déterminent la nature de l'installation : la base
de données à laquelle elle se connecte, s'il s'agit d'un système de laboratoire
ou du serveur national, et les modules qu'elle exécute.

## Avant de commencer

- Une connexion System Admin. Ce n'est pas un compte utilisateur InteLIS, et un
  administrateur InteLIS n'en dispose pas automatiquement
- Une sauvegarde de la base de données, avant toute modification sur la page Edit
  System Configuration

L'espace se trouve à `/system-admin` sur l'adresse de l'installation.

La plupart des laboratoires n'ouvrent jamais cet espace. Ces paramètres sont
définis une fois à l'installation. Les modifier sur une installation en service
peut l'empêcher de fonctionner.

## Ce que contient l'espace

| Page | Contient |
|---|---|
| Manage System Config | Connexion à la base, type d'instance, URL STS, modules activés, pays, fuseau horaire, réglages SMTP |
| System Instance Overview | L'identifiant de l'instance, son type, et la dernière synchronisation de chaque module |
| API Stats | Le trafic API traité par cette installation |
| Manage User Login History | Chaque tentative de connexion, avec adresse IP, navigateur et système d'exploitation |

## Lire l'aperçu de l'instance

1. Se connecter à `/system-admin`.
2. Ouvrir **System Instance Overview**.

| Champ | Signifie |
|---|---|
| Instance Id | L'identifiant sous lequel le serveur national connaît cette installation |
| Instance Type | LIS, STS ou Standalone |
| Lab Name | Le laboratoire auquel appartient cette installation |
| Last Sync, par module | La dernière fois que chaque module a échangé des données |

Un module dont la dernière synchronisation est ancienne n'atteint pas le serveur
national. Le confirmer avec **ADMIN → Surveillance → Historique de l'API**. Voir
[Surveillance et audit](admin-monitoring.md).

## Comprendre le type d'instance

| Type | Signifie |
|---|---|
| LIS | Un système d'information de laboratoire. Il tourne dans un laboratoire et se synchronise vers le serveur national |
| STS | Le système de suivi des échantillons. Le serveur national vers lequel les laboratoires se synchronisent |
| Standalone | Ni l'un ni l'autre. Il ne se synchronise nulle part |

Le type d'instance détermine quelles pages apparaissent. État de la
synchronisation du laboratoire et Tableau de bord de l'API n'apparaissent que sur
une instance STS.

Changer le type d'instance sur une installation en service change la destination
de ses données. Le faire valider avec l'équipe nationale.

## Vérifier qui s'est connecté

1. Se connecter à `/system-admin`.
2. Ouvrir **Manage User Login History**.

Chaque ligne porte le Login Id, la date et l'heure de la tentative, l'adresse IP,
le navigateur et le système d'exploitation.

À utiliser lorsqu'un compte est soupçonné d'être partagé ou utilisé par
quelqu'un d'autre. Les lignes montrent si un même Login Id se connecte depuis
plusieurs endroits.

## Modifier la configuration système

**Manage System Config** contient les identifiants de la base de données, le type
d'instance, l'URL STS, les modules activés, le pays d'installation, le fuseau
horaire et les réglages SMTP.

Prendre d'abord une sauvegarde de la base. Un identifiant de base erroné rend
InteLIS inaccessible à tous les utilisateurs jusqu'à correction.

Activer un module ajoute sa section de menu et sa section de configuration. En
désactiver un les masque. Les fiches déjà créées restent dans la base.

## Vérifier que tout fonctionne

| Modification | Contrôle |
|---|---|
| Module activé | Sa section apparaît dans le menu principal et sous ADMIN |
| Type d'instance | Les pages propres à ce type apparaissent |
| URL STS | L'historique de l'API enregistre une synchronisation réussie vers la nouvelle adresse |
| Réglages SMTP | Envoyer un résultat par e-mail et confirmer sa réception |
