# Envoyer des échantillons à un laboratoire avec un manifeste

Un manifeste est la liste de colisage d'un lot d'échantillons expédié vers un
laboratoire de test. Il enregistre les ID d'échantillon contenus dans le colis,
ce qui permet au laboratoire destinataire d'enregistrer le colis entier en
saisissant un seul code.

Utiliser ce guide dans une structure sanitaire qui envoie des échantillons pour
analyse, ou dans un laboratoire qui réfère des échantillons à un autre
laboratoire.

## Avant de commencer

- Les demandes de test enregistrées dans InteLIS, une par échantillon du colis.
  Voir [Enregistrer une demande de test de charge virale](register-a-request.md)
- Le laboratoire de test destinataire
- La permission de gérer les manifestes

Enregistrer les demandes avant de constituer le manifeste. Le manifeste se
compose à partir de demandes qui existent déjà.

## Créer le manifeste

1. Aller à **CHARGE VIRALE DU VIH → Gestion des demandes → Manifeste VL**.
2. Sélectionner **Ajouter un manifeste d'envoi d'échantillons**.
3. Choisir le **Labo de test** destinataire.
4. Saisir ou accepter le **Code du manifeste**.
5. Renseigner l'**Opérateur/Technicien** qui prépare le colis.
6. Renseigner le **Point de prélèvement de l'échantillon** si la structure
   prélève sur plusieurs points.

## Ajouter les échantillons

1. Filtrer la liste par **Type d'échantillon** et **Date de prélèvement de
   l'échantillon**.
2. Sélectionner **Rechercher**.
3. Cocher chaque échantillon placé dans le colis.
4. Sélectionner **Sauvegarder**.

Ne cocher que les échantillons physiquement présents dans le colis. Un manifeste
qui liste un échantillon absent de la boîte conduit le laboratoire destinataire
à enregistrer un échantillon qu'il n'a jamais reçu.

## Imprimer le manifeste

1. Aller à **CHARGE VIRALE DU VIH → Gestion des demandes → Manifeste VL**.
2. Trouver le manifeste.
3. Sélectionner **Imprimer le manifeste PDF**.

Placer le manifeste imprimé dans le colis. En conserver une copie sur le site
expéditeur.

Le laboratoire destinataire a besoin du code du manifeste figurant sur cette
fiche pour enregistrer le colis. Voir
[Réceptionner des échantillons envoyés avec un manifeste](receive-referred-samples.md).

## Modifier un manifeste avant l'expédition

Sélectionner **Modifier** sur la ligne du manifeste pour ajouter ou retirer des
échantillons.

Une fois le manifeste expédié, **Modifier** est désactivé. Un manifeste expédié
est la trace de ce qui a physiquement quitté le site, il ne change donc plus
ensuite.

Si un manifeste expédié comporte une erreur, prévenir le laboratoire
destinataire. Il peut rejeter ou mettre en attente les échantillons concernés à
l'arrivée.

## Rediriger des manifestes vers un autre laboratoire

Lorsqu'un laboratoire de test est hors service, les manifestes qui lui ont déjà
été adressés peuvent être réaffectés.

1. Aller à **CHARGE VIRALE DU VIH → Gestion des demandes → Manifeste VL**.
2. Sélectionner **Déplacer le manifeste**.
3. Renseigner **Manifest From Testing Lab** et une période pour trouver les
   manifestes.
4. Choisir la destination dans **Assign to Testing Lab**.
5. Saisir le motif du déplacement.
6. Enregistrer.

Saisir un motif qui explique le déplacement. C'est la seule trace de la raison
pour laquelle les échantillons sont partis vers un autre laboratoire que celui
choisi initialement.

Déplacer également les colis physiques. Réaffecter le manifeste ne modifie que
les enregistrements.

## Vérifier que tout fonctionne

Le manifeste apparaît dans la liste avec le bon nombre d'échantillons et le bon
laboratoire de test.

Après activation par le laboratoire destinataire, les échantillons portent un ID
du laboratoire en plus de la référence propre à la structure.
