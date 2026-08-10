# Réceptionner des échantillons envoyés avec un manifeste

Utiliser ce guide lorsqu'un colis d'échantillons arrive d'une structure
sanitaire ou d'un autre laboratoire avec un manifeste. L'activation du manifeste
enregistre tous les échantillons du colis en une seule action, sans ressaisie.

## Avant de commencer

- Le colis, vérifié physiquement par rapport au manifeste
- Le code du manifeste, imprimé sur la fiche jointe au colis
- La date d'arrivée du colis au laboratoire
- La permission d'ajouter des échantillons à partir d'un manifeste

## Vérifier d'abord le colis

Compter les tubes par rapport au manifeste avant de toucher à InteLIS.
L'activation marque tous les échantillons du manifeste comme reçus au
laboratoire. Activer un manifeste pour un colis incomplet laisse des
échantillons enregistrés comme reçus alors qu'ils ne sont pas dans le bâtiment.

Si des tubes manquent ou sont endommagés, activer quand même le manifeste, puis
rejeter individuellement les échantillons concernés. Voir
[Gérer les échecs et les échantillons en attente](failed-and-held-samples.md).

## Activer le manifeste

1. Aller à **CHARGE VIRALE DU VIH → Gestion des demandes → Ajouter des
   échantillons à partir du manifeste**.
2. Saisir le code du manifeste dans **Code manifeste de l'échantillon**.
3. Sélectionner **Envoyer**.

InteLIS liste tous les échantillons du manifeste.

4. Comparer le nombre affiché aux tubes présents sur la paillasse.
5. Régler **Date de réception de l'échantillon au labo** sur la date d'arrivée du
   colis.
6. Sélectionner **Activer les échantillons**.

InteLIS confirme par un message indiquant que les échantillons du manifeste ont
été activés.

## Ce que fait l'activation

L'activation attribue un ID d'échantillon du laboratoire à chaque échantillon du
manifeste qui n'en a pas encore, et enregistre la date de réception saisie à
l'étape 5.

Tant que le manifeste n'est pas activé, les échantillons ne sont pas disponibles
pour le test. La structure sanitaire les a déjà enregistrés, mais ils portent sa
propre référence et non un ID du laboratoire.

La liste affiche deux colonnes pour cette raison.

| Colonne | Signification |
|---|---|
| ID de l'échantillon | L'identifiant attribué par ce laboratoire, utilisé sur l'automate et sur le rapport |
| Remote Sample ID | L'identifiant utilisé par la structure expéditrice, conservé pour lui permettre de suivre l'échantillon |

## Si le code est refusé

| Message | Cause | Que faire |
|---|---|---|
| Saisir un code de manifeste valide | Le code ne correspond à aucun manifeste | Vérifier qu'aucun caractère n'est erroné. Confirmer que le manifeste a bien été envoyé à ce laboratoire |
| Indiquer la date de réception des échantillons | La date de réception est vide | Renseigner la date de réception, puis activer à nouveau |
| Aucun échantillon listé | Le manifeste a déjà été activé | Rechercher l'un de ses ID d'échantillon dans Afficher les demandes de test |

Un manifeste créé sur le système central parvient au laboratoire selon une
fréquence définie. Un manifeste généré il y a quelques minutes peut ne pas
encore être arrivé. Attendre, puis réessayer.

## Vérifier que tout fonctionne

Aller à **CHARGE VIRALE DU VIH → Gestion des demandes → Afficher les demandes de
test** et rechercher le code du manifeste.

Tous les échantillons du colis apparaissent avec un ID d'échantillon et le
statut **Échantillon enregistré au laboratoire de test**.

## Suite

Ajouter les échantillons activés à un batch. Voir
[Créer un batch pour le test](batch-samples.md).
