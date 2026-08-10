# Diffuser les résultats à la structure demandeuse

Les résultats approuvés parviennent à la structure sous forme de rapport imprimé
ou de pièce jointe à un courriel. Ce guide couvre les deux.

## Avant de commencer

- Des résultats approuvés. Voir
  [Vérifier et approuver les résultats](approve-results.md)
- La permission d'imprimer ou d'envoyer les résultats

Seuls les résultats approuvés peuvent être diffusés. Un résultat encore en
attente d'approbation n'apparaît sur aucune des deux pages.

## Imprimer les résultats

1. Aller à **CHARGE VIRALE DU VIH → Gestion → Imprimer le résultat**.
2. Rester sur l'onglet **Résultats pas encore imprimés**.
3. Filtrer les résultats à imprimer.

| Filtre | Usage |
|---|---|
| Nom de la structure | Les résultats d'une structure, prêts à envoyer ensemble |
| Date du test de l'échantillon | Tout ce qui a été testé à une date |
| Code de batch | Une seule série d'automate |
| ID Patient ou Nom du patient | Un seul patient |
| Province et District | Une région |

4. Sélectionner **Rechercher**.
5. Cocher les résultats à imprimer.
6. Sélectionner **Imprimer les résultats sélectionnés PDF**.

InteLIS produit un seul PDF contenant tous les rapports sélectionnés.

La limite est de 1000 résultats à la fois. Au-delà, l'impression est bloquée.
Répartir le travail sur plusieurs impressions.

### Réimprimer un résultat

Passer sur l'onglet **Résultats déjà imprimés**, trouver le résultat et
l'imprimer à nouveau. Les deux onglets existent pour permettre d'imprimer une
série de rapports sans réimprimer ceux déjà envoyés.

## Envoyer les résultats par courriel

1. Aller à **CHARGE VIRALE DU VIH → Gestion des résultats des tests → Envoyer le
   résultat du test par courriel**.
2. Choisir la structure dans **Facility Name (To)**.
3. Saisir un objet et un message.
4. Filtrer les résultats à envoyer.

Régler **Mail Sent Status** sur **Samples Not yet Mailed** pour écarter ce qui a
déjà été envoyé.

5. Sélectionner **Rechercher**.
6. Cocher les résultats, ou utiliser **Tout sélectionner**.
7. Sélectionner **Suivante** et confirmer.

La limite est de 100 échantillons par courriel. Au-delà, l'envoi est bloqué.

Les résultats partent vers les adresses enregistrées pour la structure. Si une
structure n'a pas d'adresse enregistrée, demander à l'administrateur d'en
ajouter une sous **ADMIN → Structures sanitaires**.

## Exporter les résultats vers un tableur

Lorsqu'une structure ou un programme demande les données plutôt que les
rapports, les exporter.

1. Aller à **CHARGE VIRALE DU VIH → Gestion → Exporter les résultats**.
2. Régler les filtres.
3. Sélectionner l'option d'export.

L'export produit un tableur, pas des rapports patients. Envoyer les rapports
patients sous forme de PDF.

## Vérifier que tout fonctionne

Pour l'impression, les résultats passent de **Résultats pas encore imprimés** à
**Résultats déjà imprimés**.

Pour le courriel, régler **Mail Sent Status** sur **Already Mailed Samples** et
rechercher. Les résultats y apparaissent.
