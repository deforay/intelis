---
description: Relier ce qui ne fonctionne pas, au laboratoire ou sur la machine InteLIS, à la page qui le corrige.
audience: [lab-staff, lab-supervisor, lab-admin, system-admin]
module: [all]
type: how-to
platform: ubuntu
reviewed: 2026-11-20
reviewed_against: 5.8.10
---
# Par où commencer

Trouver le problème dans l'un des deux tableaux ci-dessous. Chaque ligne mène
à la page qui le corrige.

- [Problèmes en utilisant InteLIS](#problemes-en-utilisant-intelis) : un
  échantillon, un résultat, un manifeste ou un formulaire dans le navigateur.
- [Problèmes sur la machine](#problemes-sur-la-machine) : le site ne s'ouvre
  pas, MySQL, une mise à jour, les sauvegardes ou la connexion de l'automate.

## Problèmes en utilisant InteLIS

| Problème | Lire |
| --- | --- |
| La connexion échoue, ou demande le texte d'une image | **Si la connexion échoue** dans [Se connecter et naviguer dans InteLIS](../user-guides/signing-in.md) |
| Un mot de passe est oublié | Demander à l'administrateur d'en définir un nouveau. Si aucun administrateur ne peut se connecter, voir [Mot de passe oublié](forgotten-password.md) |
| La barre latérale est vide, ou un menu ou un bouton manque | Le rôle n'a pas la permission. S'adresser à l'administrateur. Voir [Gérer les utilisateurs et les rôles](../user-guides/admin-users-and-roles.md) |
| Une structure manque dans le formulaire de demande | **Si la structure est absente de la liste** dans [Enregistrer une demande de test de charge virale](../user-guides/register-a-request.md) |
| Un échantillon est introuvable | [Trouver un échantillon qui n'est ni dans un lot ni sur un manifeste](../user-guides/signing-in.md#trouver-un-echantillon-qui-nest-ni-dans-un-lot-ni-sur-un-manifeste) |
| Un encadré **À vérifier** s'affiche au-dessus de la liste des demandes | [Agir sur l'encadré À vérifier](../user-guides/signing-in.md#agir-sur-lencadre-a-verifier) |
| Un échantillon manque dans la liste lors de la création d'un lot | **Si un échantillon manque dans la liste** dans [Créer un lot pour le test](../user-guides/batch-samples.md) |
| Un échantillon manque dans la liste lors de la création d'un manifeste | **Si un échantillon manque dans la liste** dans [Envoyer des échantillons à un laboratoire avec un manifeste](../user-guides/send-samples-on-a-manifest.md) |
| **Modifier** est grisé sur un manifeste | **Si Modifier est grisé** dans [Envoyer des échantillons à un laboratoire avec un manifeste](../user-guides/send-samples-on-a-manifest.md) |
| Un message s'affiche à la place des échantillons à la réception d'un manifeste | **Si un message apparaît à la place des échantillons** dans [Réceptionner des échantillons envoyés avec un manifeste](../user-guides/receive-referred-samples.md) |
| Les résultats de l'automate n'apparaissent pas dans InteLIS | **Si les résultats n'arrivent pas** dans [Saisir les résultats de charge virale](../user-guides/capture-results.md), puis [Les résultats des automates n'arrivent pas](interfacing-results-not-arriving.md) |
| Les résultats importés affichent **En attente d'approbation** | **Si les résultats affichent Awaiting Approval** dans [Saisir les résultats de charge virale](../user-guides/capture-results.md) |
| Un échantillon a échoué sur l'automate, ou est en attente | [Gérer les échecs et les échantillons en attente](../user-guides/failed-and-held-samples.md) |
| Un résultat affiche **Verrouillé** et ne peut pas être corrigé | **Si la ligne affiche Verrouillé** dans [Vérifier et approuver les résultats](../user-guides/approve-results.md) |
| Un échantillon rejeté manque dans la liste d'envoi par e-mail | **Si un échantillon rejeté manque dans la liste** dans [Diffuser les résultats à la structure demandeuse](../user-guides/release-results.md) |
| La structure ne voit pas un résultat approuvé | [Résultats qui n'atteignent pas le STS](sts-sync-not-working.md) |
| Le nom d'un statut n'est pas clair | [Statuts des échantillons](../user-guides/sample-statuses.md) |
| Un mot de ces guides n'est pas clair | [Les mots de ces guides](../user-guides/glossary.md) |

## Problèmes sur la machine

Exécuter la commande sur la machine InteLIS, puis suivre la page indiquée.
Chaque commande indique ce qu'elle a trouvé et affiche la commande suivante à
exécuter.

| Symptôme | Exécuter d'abord | Puis lire |
| --- | --- | --- |
| Le site ne s'ouvre pas dans le navigateur | `sudo intelis doctor` | [Le site ne s'ouvre pas](site-will-not-open.md) |
| Le navigateur affiche `The InteLIS database is not responding`, ou `System Temporarily Unavailable` sur les versions plus anciennes | `sudo intelis doctor` | [Le site ne s'ouvre pas](site-will-not-open.md) |
| Le navigateur affiche `This server's PHP cannot reach the database` | `sudo intelis doctor` | [Le site ne s'ouvre pas](site-will-not-open.md) |
| Le navigateur affiche `InteLIS is being updated` depuis plus de 30 minutes | `sudo intelis doctor` | [Le site ne s'ouvre pas](site-will-not-open.md) |
| phpMyAdmin affiche `The mysqli extension is missing` | `sudo intelis doctor` | [Le site ne s'ouvre pas](site-will-not-open.md) |
| Le navigateur affiche du code PHP au lieu d'InteLIS | `sudo intelis doctor` | [Le navigateur affiche du code PHP au lieu d'InteLIS](browser-shows-php-code.md) |
| Un utilisateur, ou le seul administrateur, ne peut pas se connecter car le mot de passe est oublié | `sudo intelis reset-password` | [Mot de passe oublié](forgotten-password.md) |
| MySQL est arrêté, ou le site signale une erreur de base de données | `sudo intelis fix-database` | [MySQL ne démarre pas](mysql-will-not-start.md) |
| Une mise à jour s'arrête à `Running database migrations...` | Rien pour l'instant. Lire d'abord la page. | [Mise à jour bloquée aux migrations de la base de données](update-stuck-at-migrations.md) |
| InteLIS ne démarre pas après une mise à jour ou une modification du serveur | `sudo intelis check` | La correction qu'il affiche pour chaque échec |
| `Permission denied` dans le navigateur ou dans un journal | `sudo intelis check` | [Erreurs Permission denied](permission-denied-issue.md) |
| `Illegal mix of collations` dans le navigateur ou dans un journal | `sudo intelis check` | [Corriger les erreurs de collation illégale ou incohérente](fix-collation-issue.md) |
| Le laboratoire n'a jamais été connecté au STS, ou l'adresse du STS a changé | `sudo intelis sts-setup` | [Connecter un laboratoire au STS](connect-lab-to-sts.md) |
| Les résultats ou les demandes n'arrivent pas au STS | `sudo intelis check` | [Résultats qui n'atteignent pas le STS](sts-sync-not-working.md) |
| Les résultats des automates n'arrivent pas dans InteLIS | `sudo intelis interface` | [Les résultats des automates n'arrivent pas](interfacing-results-not-arriving.md) |
| Le site est lent, ou le disque est presque plein | `sudo intelis health` | [Libérer de l'espace disque](maintenance.md#liberer-de-lespace-disque) |
| Des données ou un fichier ont été perdus ou supprimés par erreur | `sudo intelis restore` | [Restaurer depuis une sauvegarde](restoring-from-backup.md) |
| Les sauvegardes ne s'exécutent pas, ou sont anciennes | `sudo intelis backup status` | [Configurer des sauvegardes hors de la machine](setting-up-off-machine-backups.md) |
| Le travail planifié s'est arrêté : aucune importation, aucune synchronisation, aucune sauvegarde | `sudo crontab -l \| grep cron.sh` | [Vérifier que les tâches planifiées s'exécutent](maintenance.md#verifier-que-les-taches-planifiees-sexecutent) |

!!! warning "Ne pas réinstaller ni reformater la machine"

    Les données sont presque toujours intactes. Une réinstallation est la seule
    étape qui peut transformer une panne réparable en perte de données.

## Ce que vérifie chaque commande

Les quatre commandes de diagnostic ne modifient rien sans demander d'abord.

| Commande | Ce qu'elle vérifie | Quand l'utiliser |
| --- | --- | --- |
| `sudo intelis check` | PHP, les paramètres PHP du serveur web, `vendor/`, le fichier de configuration, les chemins accessibles en écriture, la base de données et les migrations en attente. Affiche la commande qui corrige chaque échec. | InteLIS ne démarre pas, ou après toute modification du serveur. |
| `sudo intelis health` | L'utilisation du disque, le temps de réponse de MySQL, les chemins accessibles en écriture et les sauvegardes hors de la machine. | InteLIS fonctionne, mais quelque chose semble anormal. |
| `sudo intelis doctor` | Pourquoi le site ne s'ouvre pas : le serveur web, PHP et ses extensions, les fichiers servis et qui peut les lire. Propose chaque réparation sans risque. Lance lui-même `fix-database` quand MySQL est en cause. | Le site ne s'ouvre pas. |
| `sudo intelis fix-database` | Pourquoi MySQL ne démarre pas : l'espace disque, la mémoire, les fichiers de configuration modifiés, le propriétaire du dossier de données et les fichiers de données endommagés. Propose chaque réparation sans risque. | MySQL est arrêté. |

`doctor` et `fix-database` acceptent `--check` pour faire le rapport sans
réparer. [Scripts de maintenance](maintenance.md#commandes-intelis) liste
toutes les commandes `intelis`.

## Avant de demander de l'aide

Rassembler ces éléments et les envoyer ensemble :

1. La sortie de `sudo intelis check`.
2. La sortie de `sudo intelis health`.
3. Le rapport enregistré par le doctor, s'il a été lancé. Il s'agit de
   `site-report.txt` ou de `mysql-report.txt` sur le bureau (**Desktop**), ou
   dans le dossier personnel quand il n'y a pas de bureau. Les mots de passe en sont retirés.
4. Le fichier le plus récent dans `/var/www/intelis/var/logs`.
