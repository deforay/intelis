# Surveiller et auditer InteLIS

Ce guide couvre **ADMIN → Surveillance**, les pages qui indiquent qui a fait
quoi, si les données circulent, et comment le laboratoire se comporte.

À utiliser lorsqu'un résultat est contesté, lorsque les résultats cessent
d'arriver, ou lorsqu'un laboratoire demande pourquoi ses données manquent dans un
rapport national.

## Avant de commencer

- Un compte avec des droits d'administrateur

## Quelle page répond à quelle question

| Question | Page |
|---|---|
| Qui a modifié ce résultat, et en quoi ? | Piste d'audit |
| Quelles pages cet utilisateur a-t-il ouvertes, et quand ? | Journal d'activité de l'utilisateur |
| Cette installation a-t-elle atteint le serveur national ? | Historique de l'API |
| Cet automate émet-il encore ? | Activité des machines d'interface |
| Combien de temps ce laboratoire met-il à rendre ses résultats ? | Indicateurs de performance du laboratoire |
| D'où viennent ces demandes ? | Sources of Requests |
| Que contient la fiche de résultat brute ? | Test Results Metadata |
| Quelles structures réfèrent à quel laboratoire ? | Sample Referral Network |
| Que signale le système au support ? | Log File Viewer |

## Trouver qui a modifié une fiche

1. Aller à **ADMIN → Surveillance → Piste d'audit**.
2. Filtrer sur la fiche en question.
3. Lire l'historique des modifications.

La piste d'audit indique quel champ a changé, son ancienne valeur, sa nouvelle
valeur, et l'utilisateur qui a enregistré la modification. À utiliser dès qu'un
résultat est contesté.

Le **Journal d'activité de l'utilisateur** répond à une autre question. Il
enregistre les pages ouvertes par un utilisateur et le moment, pas ce qu'il a
modifié.

## Vérifier que les données ont atteint le serveur national

1. Aller à **ADMIN → Surveillance → Historique de l'API**.
2. Lire les lignes les plus récentes.

| Colonne | Signifie |
|---|---|
| Transaction ID | L'identifiant d'une synchronisation |
| Number of Records Synced | Le nombre de fiches transportées |
| Sync Type | Le sens et le type de données échangées |
| Test Type | Le module auquel les fiches appartiennent |
| URL | Le serveur destinataire |
| Synced On | Le moment de l'exécution |

Un laboratoire dont les données manquent au niveau national n'a soit aucune ligne
récente ici, soit des lignes portant zéro fiche.

**État de la synchronisation du laboratoire** et **Tableau de bord de l'API**
répondent à la même question depuis l'autre extrémité. Ils n'apparaissent que sur
le serveur national, et leur absence sur une installation de laboratoire est
voulue.

## Vérifier qu'un automate émet encore

1. Aller à **ADMIN → Surveillance → Activité des machines d'interface**.
2. Filtrer sur le laboratoire.
3. Lire l'événement le plus récent de cette machine.

Si rien de récent n'apparaît, ouvrir le laboratoire sous **ADMIN → Structures
sanitaires** et lire l'heure de **Last Seen** sous Connected Installations. Une
Last Seen ancienne signifie que l'outil d'interface n'atteint pas InteLIS. Voir
[Automates et interfaçage](admin-instruments.md).

## Lire le rapport de performance du laboratoire

**ADMIN → Surveillance → Indicateurs de performance du laboratoire** couvre tous
les modules de l'installation.

| Indicateur | Montre |
|---|---|
| Délai de rendu | Le temps passé par les échantillons à chaque étape |
| Volume par mode de saisie | Combien d'échantillons sont arrivés par saisie manuelle, import de fichier et outil d'interface |
| Taux d'échec | La fréquence des échecs de test |
| Taux de rejet | La fréquence des rejets d'échantillon |
| Patients répétés | Les patients testés plus d'une fois |

Les types de test personnalisés sont détaillés par type et non regroupés.

## Retrouver l'origine des demandes

**ADMIN → Surveillance → Sources of Requests** compte, par clinique et par
laboratoire, les échantillons demandés, réceptionnés au laboratoire, accusés
réception, testés et rendus.

Sélectionner d'abord la période et le type de test. La page n'affiche rien tant
que les deux ne sont pas renseignés.

À utiliser pour repérer les cliniques dont les échantillons sont demandés mais
jamais réceptionnés, et les laboratoires qui réceptionnent sans rendre de
résultats.

## Lire la fiche de résultat brute

**ADMIN → Surveillance → Test Results Metadata** montre ce qu'InteLIS conserve
derrière un résultat : les dates de collecte, de réception, de test et de
dernière modification, le résultat et son statut, si l'échantillon a été rejeté
et pour quel motif, si le résultat a été saisi manuellement, la raison
enregistrée pour toute modification, et le lien vers le fichier importé.

Rechercher par date de test, ou par ID d'échantillon ou code de batch. Exporter
vers Excel pour transmettre au support.

## Voir le réseau de référence

**ADMIN → Surveillance → Sample Referral Network** cartographie quelles
structures réfèrent des échantillons à quels laboratoires, par type de test.
Sélectionner un laboratoire ou une structure sur la carte pour n'afficher que ses
liens.

Les structures n'apparaissent sur la carte que si leur latitude et leur longitude
sont renseignées. Voir [Structures et laboratoires](admin-facilities.md).

## Lire les fichiers journaux

**ADMIN → Surveillance → Log File Viewer** affiche les messages système
enregistrés par InteLIS.

Le consulter avant de contacter le support, et joindre les entrées pertinentes à
la demande. Il signale les anomalies, pas les actions des utilisateurs.

## Vérifier que tout fonctionne

| Tâche | Contrôle |
|---|---|
| Modification retracée | La piste d'audit nomme le champ, l'ancienne valeur, la nouvelle valeur et l'utilisateur |
| Synchronisation confirmée | L'historique de l'API porte une ligne récente avec un nombre de fiches non nul |
| Automate confirmé actif | L'activité des machines d'interface porte une entrée récente, et Last Seen est récente |
| Laboratoire manquant diagnostiqué | Sources of Requests montre à quelle étape le compte chute |
