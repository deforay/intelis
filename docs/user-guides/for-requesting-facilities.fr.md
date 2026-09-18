# Pour les structures demandeuses

Le personnel des structures sanitaires utilise InteLIS pour enregistrer les
échantillons prélevés, les envoyer à un laboratoire d'analyse et récupérer les
résultats. Cette page liste ce travail et renvoie vers les étapes de chaque
tâche.

## Ce que voit un compte de structure

- Le personnel des structures travaille sur le système central (STS). Le
  **Type d'Accès** du rôle est **Site de prélèvement**.
- Le compte ne voit que les demandes des structures qui lui sont rattachées.
- Le menu n'affiche que ce que le rôle autorise. La réception des manifestes,
  la saisie des résultats et leur approbation se font au laboratoire
  d'analyse.

## Le travail dans l'ordre

1. Se connecter. Voir [Se connecter et naviguer dans InteLIS](signing-in.md).
2. Enregistrer une demande de test pour chaque échantillon prélevé. Voir
   [Enregistrer une demande de test de charge virale](register-a-request.md).
3. Lister les échantillons sur un manifeste, l'imprimer et le placer dans le
   colis. Voir
   [Envoyer des échantillons à un laboratoire avec un manifeste](send-samples-on-a-manifest.md).
4. Suivre chaque échantillon sous **CHARGE VIRALE DU VIH → Gestion des
   demandes → Afficher les demandes de test**. Voir
   [Statuts des échantillons](sample-statuses.md) pour la signification de
   chaque statut.
5. Récupérer les résultats. Voir
   [Diffuser les résultats à la structure demandeuse](release-results.md).
6. Consulter les résultats antérieurs d'un patient sous **CHARGE VIRALE DU VIH
   → Gestion → Rapports cliniques**, dans l'onglet **Historique des tests du
   patient**. Voir [Rapports charge virale](reports.md).

## En cas de problème

| Problème | Action |
| --- | --- |
| La structure est absente du formulaire de demande | Demander à l'administrateur de rattacher la structure au compte et au test |
| Un échantillon reste à **Échantillon enregistré au centre de santé** après le départ du colis | Le laboratoire n'a pas encore activé le manifeste, ou n'a pas synchronisé depuis. Contacter le laboratoire avec le code du manifeste |
| Une demande a été saisie deux fois | Demander au laboratoire d'analyse d'annuler le doublon |
| Un échantillon a été rejeté | Lire le motif de rejet sur la demande, puis prélever et envoyer de nouveau |
| Un résultat semble incohérent pour le patient | Contacter le laboratoire avec l'ID de l'échantillon avant d'agir |
