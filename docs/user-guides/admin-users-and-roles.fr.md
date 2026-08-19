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
2. Sélectionner **Add User**.
3. Renseigner les informations.

| Champ | Ce qu'il faut saisir |
|---|---|
| User Name | Le nom tel qu'il figure sur les rapports et dans le journal d'activité |
| Email | L'adresse de l'utilisateur. Elle ne doit pas déjà être utilisée |
| Phone Number | Le numéro de l'utilisateur |
| Role | Le rôle qui définit ce que cet utilisateur peut atteindre |
| Testing Lab | Le laboratoire au titre duquel cet utilisateur travaille |
| Province/State, District/County | La localisation de l'utilisateur |
| Map User to Selected Facilities | Les structures auxquelles cet utilisateur est limité. Laisser vide pour aucune limite |
| Mobile App Access | Si le compte peut utiliser l'application mobile |
| Interface User Name | Le nom d'utilisateur de cette personne sur l'automate moléculaire. Il rattache les résultats de l'automate à une personne |
| Signature | Une image de signature pour toute personne qui approuve des résultats. 100 sur 100 pixels |
| Login Id | L'identifiant de connexion |
| Password, Confirm Password | Le mot de passe initial |
| User Status | Actif ou inactif |

4. Sélectionner **Submit**.
5. Remettre le Login Id et le mot de passe à l'utilisateur en main propre.

Le Login Id accepte les lettres minuscules, les chiffres, les traits d'union et
les tirets bas. Il n'accepte ni espaces ni majuscules.

Le mot de passe doit compter au moins 8 caractères et comporter au moins un
chiffre et au moins une lettre. Les caractères spéciaux sont autorisés.

Renseigner le **Testing Lab** sur chaque utilisateur. Cela limite ce que
l'utilisateur voit au travail de son propre laboratoire. Un utilisateur sans
laboratoire renseigné voit les échantillons de tous les laboratoires.

## Limiter un utilisateur à certaines structures

**Map User to Selected Facilities** restreint un utilisateur au-delà du
laboratoire. À utiliser pour le personnel des structures qui enregistre ses
propres demandes, afin que chacun ne voie que sa structure.

Laisser vide pour le personnel de laboratoire. Une correspondance vide signifie
aucune limite de structure, et le laboratoire de test s'applique toujours.

## Donner un jeton d'API à un utilisateur

Les utilisateurs qui se connectent par l'API ont besoin d'un jeton et non d'un
mot de passe.

1. Ouvrir l'utilisateur sous **ADMIN → Contrôle d'accès → Utilisateurs**.
2. Repérer **AuthToken**.
3. Sélectionner **Generate**, ou **Generate Another Token** pour remplacer le
   jeton actuel.

Générer un autre jeton invalide aussitôt le précédent. Tout ce qui utilise encore
l'ancien jeton cesse de fonctionner.

## Désactiver un utilisateur qui part

1. Ouvrir l'utilisateur.
2. Passer **User Status** en inactif.
3. Sélectionner **Submit**.

Ne pas supprimer le compte, et ne pas réattribuer le Login Id à quelqu'un
d'autre. Les fiches créées par l'utilisateur restent rattachées à son nom.

## Ajouter ou modifier un rôle

Un rôle est un ensemble nommé de permissions. Les utilisateurs tiennent leurs
permissions de leur rôle, jamais individuellement.

1. Aller à **ADMIN → Contrôle d'accès → Les rôles**.
2. Sélectionner **Add Role**, ou **Edit** sur un rôle existant.
3. Renseigner les informations.

| Champ | Ce qu'il faut saisir |
|---|---|
| Role Name | Un nom que le personnel reconnaît, par exemple Technicien de laboratoire |
| Role Code | Un code court et unique |
| Access Type | **Testing Lab** pour le personnel du laboratoire. **Collection Site** pour le personnel des structures |
| Status | Actif ou inactif |
| Privileges | Cocher chaque page accessible à ce rôle |

4. Sélectionner **Submit**.

## Fonctionnement de la liste des permissions

La liste des permissions est un panneau dépliant par module. Chaque panneau
contient les pages de ce module, et chaque page porte un interrupteur oui ou non.

**Access Type** filtre la liste. Une page qui relève du travail de laboratoire
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
