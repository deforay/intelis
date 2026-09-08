# Gérer les utilisateurs et les rôles

Ce guide crée les identifiants et définit ce que chacun peut atteindre. Les deux
se trouvent sous **ADMIN → Contrôle d'accès**.

InteLIS n'a pas d'auto-inscription. Chaque identifiant est créé par un
administrateur.

## Avant de commencer

- Un compte avec des droits d'administrateur
- Le rôle dont le nouvel utilisateur a besoin, déjà créé

## Ajouter un utilisateur

1. Aller à **ADMIN → Contrôle d'accès → Utilisateurs**.
2. Sélectionner **Ajouter un utilisateur**.
3. Renseigner les informations.

| Champ | Ce qu'il faut saisir |
|---|---|
| Nom complet | Le nom tel qu'il figure sur les rapports et dans le journal d'activité |
| Courriel | L'adresse de l'utilisateur. Elle ne doit pas déjà être utilisée |
| Numéro de téléphone | Le numéro de l'utilisateur |
| Rôle | Le rôle qui définit ce que cet utilisateur peut atteindre |
| Laboratoire d'analyse | Le laboratoire au titre duquel cet utilisateur travaille. Affiché sur STS uniquement, et seulement après le choix d'un rôle de laboratoire d'analyse |
| Province, District | La localisation de l'utilisateur |
| Accès à l'application mobile | Si le compte peut utiliser l'application mobile |
| Nom de l'utilisateur de l'interface | Le nom d'utilisateur de cette personne sur l'automate moléculaire. Il rattache les résultats de l'automate à une personne |
| Signature | Une image de signature pour toute personne qui approuve des résultats. 100 sur 100 pixels |
| ID de connexion | L'identifiant de connexion |
| Mot de passe, Confirmer le mot de passe | Le mot de passe initial |
| Statut de l'utilisateur | Actif ou inactif |

4. Sélectionner **Envoyer**.
5. Remettre le Login Id et le mot de passe à l'utilisateur en main propre.

Le Login Id accepte les lettres minuscules, les chiffres, les traits d'union et
les tirets bas. Il n'accepte ni espaces ni majuscules.

Le mot de passe doit compter au moins 8 caractères et comporter au moins un
chiffre et au moins une lettre. Les caractères spéciaux sont autorisés.

### Le Laboratoire d'analyse dépend du type d'instance

- **Sur STS**, renseigner le **Laboratoire d'analyse** sur chaque utilisateur de
  laboratoire. Le champ apparaît dès qu'un rôle de ce type d'accès est choisi, et
  limite ce que l'utilisateur voit au travail de son propre laboratoire.
- **Sur une installation LIS ou autonome**, le champ n'est pas affiché. Chaque
  utilisateur est rattaché automatiquement au laboratoire de cette installation :
  il n'y a donc rien à renseigner.
- **Sur une instance cloud**, les utilisateurs créés par un administrateur de
  laboratoire sont rattachés au laboratoire de cet administrateur.

## Limiter un utilisateur à certaines structures

La correspondance avec des structures restreint un utilisateur au-delà du
laboratoire. À utiliser pour le personnel des structures qui enregistre ses
propres demandes, afin que chacun ne voie que sa structure.

**Le formulaire d'ajout d'utilisateur ne comporte aucun sélecteur de structure.**
Les contrôles de correspondance n'existent que sur la page de modification : un
utilisateur créé puis laissé tel quel n'a donc aucune restriction de structure,
quelle qu'ait été l'intention au moment de la création.

1. Créer l'utilisateur comme ci-dessus et sélectionner **Envoyer**.
2. Rouvrir ce même utilisateur sous **ADMIN → Contrôle d'accès → Utilisateurs**.
3. Utiliser **Carte de l'utilisateur vers les installations sélectionnées
   (facultatif)** pour faire passer les structures voulues dans la liste
   sélectionnée.
4. Sélectionner **Envoyer**.
5. Se connecter avec ce compte, ou consulter sa liste de demandes, et vérifier
   que seules les structures voulues apparaissent.

Laisser la correspondance vide pour le personnel de laboratoire qui doit voir
toutes les structures. Une correspondance vide signifie aucune limite de
structure, et le laboratoire d'analyse s'applique toujours.

## Donner un jeton d'API à un utilisateur

Les utilisateurs qui se connectent par l'API ont besoin d'un jeton et non d'un
mot de passe.

Le champ **AuthToken** est masqué tant que le rôle du compte n'est pas le rôle
API, ou que le compte ne détient pas déjà un jeton. Ouvrir un utilisateur
ordinaire ne le fait pas apparaître.

1. Ouvrir l'utilisateur sous **ADMIN → Contrôle d'accès → Utilisateurs**.
2. Régler **Rôle** sur le rôle API. Le champ **AuthToken** apparaît.
3. Sélectionner **Générer**, ou **Générer un autre jeton** pour remplacer le
   jeton actuel.
4. Sélectionner **Envoyer**.

Sur une instance cloud, un administrateur de laboratoire ne peut pas attribuer
le
rôle API : ces comptes sont créés par un administrateur complet.

Générer un autre jeton invalide aussitôt le précédent. Tout ce qui utilise
encore
l'ancien jeton cesse de fonctionner.

## Désactiver un utilisateur qui part

1. Ouvrir l'utilisateur.
2. Passer **Statut de l'utilisateur** en inactif.
3. Sélectionner **Envoyer**.

Ne pas supprimer le compte, et ne pas réattribuer le Login Id à quelqu'un
d'autre. Les fiches créées par l'utilisateur restent rattachées à son nom.

## Ajouter ou modifier un rôle

Un rôle est un ensemble nommé de permissions. Les utilisateurs tiennent leurs
permissions de leur rôle, jamais individuellement.

1. Aller à **ADMIN → Contrôle d'accès → Les rôles**.
2. Sélectionner **Ajouter rôle**, ou **Modifier** sur un rôle existant.
3. Renseigner les informations.

| Champ | Ce qu'il faut saisir |
|---|---|
| Nom du rôle | Un nom que le personnel reconnaît, par exemple Technicien de laboratoire |
| Code de rôle | Un code court et unique |
| Type d'Accès | **Laboratoire d'analyse** pour le personnel du laboratoire. **Site de prélèvement** pour le personnel des structures |
| Statut | Actif ou inactif |
| Privilèges | Cocher chaque page accessible à ce rôle |

4. Sélectionner **Envoyer**.

## Fonctionnement de la liste des permissions

La liste des permissions est un panneau dépliant par module. Chaque panneau
contient les pages de ce module, et chaque page porte un interrupteur oui ou
non.

**Type d'Accès** filtre la liste. Une page qui relève du travail de laboratoire
disparaît lorsque Access Type vaut Collection Site, et inversement. Les pages
masquées sont forcées en refus, et le serveur l'impose à l'enregistrement.
Renseigner Access Type d'abord, puis les permissions.

Utiliser le champ de recherche pour trouver une page. Ne pas parcourir la liste.

Donner à chaque rôle les permissions nécessaires à son travail, et pas
davantage. L'approbation est le contrôle sur la qualité des résultats. Un rôle
qui peut à la fois saisir et approuver ses propres résultats supprime ce
contrôle.

Pour savoir quels rôles portent une permission donnée, utiliser le filtre
**Permission** sur la page des rôles.

Le premier rôle est le super administrateur. Il porte toutes les permissions, et
ses permissions ne peuvent pas être retirées.

## Vérifier que tout fonctionne

| Modification | Contrôle |
|---|---|
| Nouvel utilisateur | L'utilisateur se connecte et voit le menu attendu |
| Testing Lab renseigné | L'utilisateur voit les échantillons de son laboratoire et d'aucun autre |
| Correspondance de structures | L'utilisateur ne voit que les structures rattachées sur le formulaire |
| Rôle nouveau ou modifié | Se connecter avec un utilisateur de ce rôle, ou utiliser le filtre Permission |
| Utilisateur désactivé | Le Login Id ne permet plus de se connecter |
