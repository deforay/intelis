# Statuts des échantillons

Chaque échantillon dans InteLIS porte un statut. Cette page les liste tous,
indique ce qui attribue chacun d'eux et comment chacun compte dans les rapports.
Dans la colonne **Statut** des listes, InteLIS affiche le nom anglais indiqué
entre parenthèses.

## Les statuts

| Statut | Signification |
| --- | --- |
| Échantillon enregistré au centre de santé (`Sample Currently Registered at Health Center`) | Enregistré dans une structure sanitaire. Le laboratoire ne l'a pas reçu |
| Échantillon enregistré au laboratoire d'analyse (`Sample Registered at Testing Lab`) | Reçu par le laboratoire et en attente de test |
| Échantillon envoyé à un autre laboratoire (`Sample Referred to another Lab`) | Transmis à un autre laboratoire pour analyse |
| En attente d'approbation (`Awaiting Approval`) | Un résultat est enregistré et attend son approbation |
| Accepté (`Accepted`) | Le résultat est approuvé et prêt à être diffusé |
| Rejeté (`Rejected`) | L'échantillon n'était pas propre au test. Un motif de rejet est enregistré, et le rejet est diffusé à la structure demandeuse afin qu'un nouveau prélèvement soit effectué |
| Échec/Invalidité (`Failed/Invalid`) | Le test a eu lieu et n'a pas produit de résultat exploitable |
| En attente (`Hold`) | Suspendu dans l'attente d'une décision |
| Échantillon réorganisé (`Sample Reordered`) | Statut hérité, conservé pour que les anciens enregistrements restent lisibles |
| Perdu (`Lost`) | L'échantillon est introuvable et ne sera pas testé |
| Expiré (`Expired`) | L'échantillon est resté sans résultat plus longtemps que l'installation ne l'autorise |
| Aucun résultats (`No Result`) | Le test a eu lieu et n'a rapporté aucun résultat |
| Annulée (`Cancelled`) | Le test ne sera pas réalisé. La demande subsiste mais aucun test n'a lieu |

## Ce qui attribue chaque statut

| Statut | Attribué par |
| --- | --- |
| Échantillon enregistré au centre de santé | L'enregistrement d'une demande dans une structure sanitaire |
| Échantillon enregistré au laboratoire d'analyse | L'enregistrement d'une demande au laboratoire, l'activation d'un manifeste, ou l'envoi au retest |
| Échantillon envoyé à un autre laboratoire | La référence d'un échantillon à un autre laboratoire |
| En attente d'approbation | L'enregistrement d'un résultat, ou sa correction sur le formulaire de résultat |
| Accepté | L'approbation d'un résultat dans **Gérer le statut des résultats** ou sur l'écran **Résultats importés**, l'approbation automatique des résultats transmis par l'outil d'interface si le laboratoire est configuré ainsi, ou la récupération d'un résultat sur la page **Échec/Echantillons en attente** |
| Rejeté | L'enregistrement d'un rejet sur le formulaire de résultat, l'application de **Rejeté** dans **Gérer le statut des résultats**, ou le choix de **Rejected** sur l'écran **Résultats importés** |
| Échec/Invalidité | L'enregistrement d'un résultat qui correspond à un échec, le choix de **Failed** sur l'écran **Résultats importés**, ou l'application de **Accepté** à un résultat qui correspond à un échec |
| En attente | Aucun écran de charge virale ne l'attribue. Choisir **Hold** sur l'écran **Résultats importés** met de côté le résultat de cette ligne et laisse le statut de l'échantillon inchangé |
| Échantillon réorganisé | Aucun flux actuel. Retester un échantillon le ramène à Échantillon enregistré au laboratoire d'analyse. La case **Échantillon réorganisé** du formulaire de demande enregistre un indicateur distinct, pas ce statut |
| Perdu | L'application de **Perdu** dans **Gérer le statut des résultats** |
| Expiré | La mise à jour nocturne des statuts. Un échantillon encore En attente, Échantillon réorganisé, Échantillon enregistré au centre de santé ou Échantillon enregistré au laboratoire d'analyse expire dès qu'il dépasse **Jours d'expiration de l'échantillon** sous **ADMIN → Configuration du système → Configuration générale**. L'âge se compte depuis la date de prélèvement, ou depuis la date de la demande si aucune date de prélèvement n'est enregistrée. La valeur par défaut est de 365 jours |
| Aucun résultats | La saisie de `no result` comme résultat |
| Annulée | L'application de **Annulée** dans **Gérer le statut des résultats**, avec la saisie de `CANCEL` pour confirmer |

## Statuts qui enregistrent un motif

| Statut | Motif enregistré |
| --- | --- |
| Rejeté | Un motif de rejet issu de la liste sous **ADMIN → Configuration CV → Motifs de rejet** |
| Échec/Invalidité | Un motif d'échec issu de la liste sous **ADMIN → Configuration CV → Raisons de l'échec des tests** |

## Effet des statuts dans les rapports

| Statut | Effet |
| --- | --- |
| Accepté | Peut être imprimé et envoyé par courriel à la structure demandeuse |
| Rejeté | Peut être imprimé pour la structure demandeuse. Un échantillon rejeté ne porte aucun résultat, et le courriel n'envoie que les échantillons qui ont un résultat. Figure dans le rapport de rejet d'échantillons avec son motif |
| Échec/Invalidité | Reste dans le taux d'échec. Envoyer un échantillon en échec au retest conserve la tentative en échec, les deux sont donc comptés |
| Annulée | Compté comme jamais testé. Exclu des volumes de test et du délai de rendu |

## Guides associés

- [Vérifier et approuver les résultats](approve-results.md)
- [Gérer les échecs et les échantillons en attente](failed-and-held-samples.md)
- [Diffuser les résultats à la structure demandeuse](release-results.md)
