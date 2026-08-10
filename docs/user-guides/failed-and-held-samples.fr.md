# Gérer les échecs et les échantillons en attente

Les échantillons en échec sur l'automate, mis en attente, perdus ou périmés sont
regroupés sur une seule page. Elle sert à les renvoyer au test, ou à récupérer
des résultats marqués en échec par erreur.

## Avant de commencer

- La permission de consulter les échantillons en échec et en attente

## Les trouver

1. Aller à **CHARGE VIRALE DU VIH → Gestion des résultats des tests →
   Échec/Echantillons en attente**.
2. Régler **Résultat Statut** sur le groupe à traiter.

| Statut | Signification |
|---|---|
| Échec | L'automate a rendu un échec ou une lecture invalide |
| En attente | Quelqu'un a suspendu l'échantillon dans l'attente d'une décision |
| Perdu | L'échantillon est introuvable |
| Expiré | L'échantillon a dépassé la durée de conservation autorisée |

3. Restreindre avec les autres filtres si nécessaire, puis sélectionner
   **Rechercher**.

## Renvoyer des échantillons au test

À utiliser lorsque l'échantillon est encore exploitable et que le laboratoire
dispose d'un volume suffisant.

1. Cocher les échantillons à retester.
2. Sélectionner **Retester les échantillons sélectionnés**.

InteLIS confirme que le retest a été enregistré.

Le retest efface le résultat et renvoie l'échantillon dans la file des non
testés avec le statut **Échantillon enregistré au laboratoire de test**.
L'échantillon peut alors être ajouté à un nouveau batch. Voir
[Créer un batch pour le test](batch-samples.md).

La tentative en échec n'est pas effacée. InteLIS la conserve, de sorte que les
rapports de performance du laboratoire comptent à la fois la série en échec et
le retest. Le taux d'échec reste exact.

## Récupérer des résultats marqués en échec par erreur

Un import peut marquer toute une série en échec alors que les résultats étaient
valables. Cette action corrige ces échantillons.

1. Cocher les échantillons concernés.
2. Sélectionner **Déplacer la sélection vers « Accepté »**.
3. Confirmer.

InteLIS ne déplace que les échantillons dont le résultat enregistré est
exploitable. Les échantillons réellement en échec sur l'automate sont ignorés,
et InteLIS indique combien ont été déplacés.

Si rien n'est déplacé, les échantillons sont soit de véritables échecs, soit
déjà acceptés. Ceux-là nécessitent un retest, pas une récupération.

## Réimprimer une étiquette code-barres

Lorsque l'étiquette d'un tube est abîmée ou absente, la réimprimer depuis cette
page.

1. Trouver l'échantillon.
2. Utiliser l'option d'impression sur la ligne.

Si aucune imprimante n'est proposée, sélectionner **Change/Retry** pour en
choisir une.

## Choisir entre retest et annulation

| Situation | Action | Où |
|---|---|---|
| Échantillon exploitable, volume suffisant | Retest | Cette page |
| Échantillon non exploitable, la structure doit renvoyer | Rejeter avec un motif | [Gérer le statut des résultats](approve-results.md) |
| Échantillon perdu | Marquer Perdu | [Gérer le statut des résultats](approve-results.md) |
| Demande saisie deux fois, ou retirée | Annuler | [Gérer le statut des résultats](approve-results.md) |

Annulation et échec ne sont pas comptés de la même manière. Un échantillon
annulé est considéré comme jamais testé et sort des volumes de test et du délai
de rendu. Un échantillon en échec reste dans le taux d'échec. Utiliser
l'annulation pour effacer des échecs masque un indicateur réel de qualité.

## Vérifier que tout fonctionne

Après un retest, rechercher l'échantillon sous **CHARGE VIRALE DU VIH → Gestion
des demandes → Afficher les demandes de test**. Il affiche le statut
**Échantillon enregistré au laboratoire de test** et aucun résultat.

Après une récupération, l'échantillon n'apparaît plus sur la page
Échec/Echantillons en attente sous **Échec**, et il porte son résultat.
