# Statuts des échantillons

Chaque échantillon dans InteLIS porte un statut. Cette page les liste tous.

S'applique à InteLIS 5.6.2.

## Les statuts

| Statut | Signification |
|---|---|
| Échantillon actuellement enregistré au centre de santé | Enregistré dans une structure sanitaire. Le laboratoire ne l'a pas reçu |
| Échantillon enregistré au laboratoire de test | Reçu par le laboratoire et en attente de test |
| Échantillon envoyé à un autre laboratoire | Transmis à un autre laboratoire pour analyse |
| En attente d'approbation | Un résultat est enregistré et attend son approbation |
| Accepté | Le résultat est approuvé et disponible pour diffusion |
| Rejeté | L'échantillon n'était pas propre au test. Un motif de rejet est enregistré |
| Échec/Invalidité | Le test a eu lieu et n'a pas produit de résultat exploitable |
| En attente | Suspendu dans l'attente d'une décision |
| Échantillon réorganisé | Renvoyé au test |
| Perdu | L'échantillon est introuvable et ne sera pas testé |
| Expiré | L'échantillon a dépassé la durée de conservation autorisée par l'installation |
| Aucun résultats | Aucun résultat n'est enregistré pour l'échantillon |
| Annulée | Le test ne sera pas réalisé. La demande subsiste mais aucun test n'a lieu |

## Où chaque statut est attribué

| Statut | Attribué par |
|---|---|
| Échantillon actuellement enregistré au centre de santé | L'enregistrement d'une demande dans une structure sanitaire |
| Échantillon enregistré au laboratoire de test | L'enregistrement d'une demande au laboratoire, l'activation d'un manifeste, ou l'envoi au retest |
| Échantillon envoyé à un autre laboratoire | La référence d'un échantillon à un autre laboratoire |
| En attente d'approbation | L'enregistrement d'un résultat |
| Accepté | L'approbation d'un résultat, ou sa récupération depuis la page Échec/Echantillons en attente |
| Rejeté | L'enregistrement d'un rejet sur le formulaire de résultat, ou l'application de Rejeté dans Gérer le statut des résultats |
| Échec/Invalidité | L'enregistrement d'un échec de test, ou un import qui marque la ligne en échec |
| En attente | L'application de En attente à un échantillon |
| Échantillon réorganisé | La remise au test d'un échantillon |
| Perdu | L'application de Perdu dans Gérer le statut des résultats |
| Expiré | L'écoulement de la période de péremption définie pour l'installation |
| Annulée | L'application de Annulée dans Gérer le statut des résultats, avec saisie d'une confirmation |

## Statuts associés à un motif

| Statut | Motif enregistré |
|---|---|
| Rejeté | Un motif de rejet issu de la liste sous **ADMIN → Configuration CV → Motifs de rejet** |
| Échec/Invalidité | Un motif d'échec issu de la liste sous **ADMIN → Configuration CV → Raisons de l'échec des tests** |
| Annulée | Une confirmation saisie au moment de l'annulation |

## Effet des statuts sur les rapports

Les échantillons **Annulée** sont considérés comme jamais testés. Ils sont exclus
des volumes de test et du délai de rendu.

Les échantillons **Échec/Invalidité** restent dans le taux d'échec. Envoyer un
échantillon en échec au retest conserve la tentative en échec, les deux sont donc
comptés.

Les échantillons **Rejeté** figurent dans le rapport de rejet d'échantillons avec
leur motif.

Les échantillons **Accepté** sont les seuls disponibles à l'impression et à
l'envoi par courriel.

## Guides associés

- [Vérifier et approuver les résultats](approve-results.md)
- [Gérer les échecs et les échantillons en attente](failed-and-held-samples.md)
- [Diffuser les résultats à la structure demandeuse](release-results.md)
