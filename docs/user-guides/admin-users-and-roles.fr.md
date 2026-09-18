# Gérer les utilisateurs et les rôles

Créer un identifiant pour chaque personne, et décider de ce que chaque
identifiant peut atteindre. Les deux se trouvent sous **ADMIN → Contrôle
d'accès**.

InteLIS n'a pas d'auto-inscription. Un administrateur crée chaque identifiant.

## Avant de commencer

- Un compte avec des droits d'administrateur
- Le rôle dont le nouvel utilisateur a besoin. Pour en créer un, voir
  [Ajouter ou modifier un rôle](#ajouter-ou-modifier-un-role)

## Ajouter un utilisateur

**Choisir le type d'installation, puis suivre ses étapes de haut en bas.**

=== "STS"

    1. Aller à **ADMIN → Contrôle d'accès → Utilisateurs**.
    2. Sélectionner **Ajouter un utilisateur**.
    3. Saisir **Nom complet**, **Courriel** et **Numéro de téléphone**. Le nom
       figure sur les rapports et dans le journal d'activité.
    4. Renseigner **Rôle**.
    5. Si le type d'accès du rôle est Laboratoire d'analyse, régler
       **Laboratoire d'analyse** sur le laboratoire où travaille l'utilisateur.
       Le champ apparaît dès qu'un tel rôle est choisi. Il limite ce que
       l'utilisateur voit au travail de ce laboratoire.
    6. Renseigner les champs facultatifs utiles à l'utilisateur :

        | Champ | Ce qu'il faut saisir |
        | --- | --- |
        | Province, District | La localisation de l'utilisateur |
        | Accès à l'application mobile | **Oui** si l'utilisateur se connecte à l'application mobile |
        | Nom d'utilisateur de l'interface (de votre machine de test moléculaire) | Le nom que cette personne utilise sur l'automate. Séparer plusieurs noms par des virgules |
        | Signature | Une image de signature pour toute personne qui approuve des résultats. Elle s'imprime sur les PDF de résultats |

    7. Saisir l'**Identifiant de connexion**.
    8. Saisir **Mot de passe** et **Confirmer le mot de passe**, ou
       sélectionner **Générer**.
    9. Sélectionner **Envoyer**.
    10. Remettre l'identifiant de connexion et le mot de passe à l'utilisateur
        en main propre. InteLIS lui demande de changer le mot de passe à la
        première connexion.

    ??? info "Règles de l'identifiant de connexion et du mot de passe"

        L'identifiant de connexion accepte les lettres minuscules, les
        chiffres, les traits d'union (-) et les tirets bas (_). Il n'accepte ni
        espaces ni majuscules.

        Le mot de passe compte au moins 8 caractères, dont au moins un chiffre
        et une lettre. Les caractères spéciaux sont autorisés.

    ??? info "Pour limiter un utilisateur de structure à ses propres structures"

        Le formulaire d'ajout n'a pas de sélecteur de structure. Une fois
        l'utilisateur enregistré, suivre
        [Limiter un utilisateur à certaines structures](#limiter-un-utilisateur-a-certaines-structures).

=== "LIS ou autonome"

    1. Aller à **ADMIN → Contrôle d'accès → Utilisateurs**.
    2. Sélectionner **Ajouter un utilisateur**.
    3. Saisir **Nom complet**, **Courriel** et **Numéro de téléphone**. Le nom
       figure sur les rapports et dans le journal d'activité.
    4. Renseigner **Rôle**. Il n'y a pas de champ **Laboratoire d'analyse**.
       InteLIS rattache chaque utilisateur au laboratoire de cette
       installation.
    5. Renseigner les champs facultatifs utiles à l'utilisateur :

        | Champ | Ce qu'il faut saisir |
        | --- | --- |
        | Province, District | La localisation de l'utilisateur |
        | Accès à l'application mobile | **Oui** si l'utilisateur se connecte à l'application mobile |
        | Nom d'utilisateur de l'interface (de votre machine de test moléculaire) | Le nom que cette personne utilise sur l'automate. Séparer plusieurs noms par des virgules |
        | Signature | Une image de signature pour toute personne qui approuve des résultats. Elle s'imprime sur les PDF de résultats |

    6. Saisir l'**Identifiant de connexion**.
    7. Saisir **Mot de passe** et **Confirmer le mot de passe**, ou
       sélectionner **Générer**.
    8. Sélectionner **Envoyer**.
    9. Remettre l'identifiant de connexion et le mot de passe à l'utilisateur
       en main propre. InteLIS lui demande de changer le mot de passe à la
       première connexion.

    ??? info "Règles de l'identifiant de connexion et du mot de passe"

        L'identifiant de connexion accepte les lettres minuscules, les
        chiffres, les traits d'union (-) et les tirets bas (_). Il n'accepte ni
        espaces ni majuscules.

        Le mot de passe compte au moins 8 caractères, dont au moins un chiffre
        et une lettre. Les caractères spéciaux sont autorisés.

=== "Cloud"

    À utiliser lors d'une connexion au STS avec un rôle de laboratoire
    d'analyse autre que le super administrateur.

    1. Aller à **ADMIN → Contrôle d'accès → Utilisateurs**.
    2. Sélectionner **Ajouter un utilisateur**.
    3. Saisir **Nom complet**, **Courriel** et **Numéro de téléphone**. Le nom
       figure sur les rapports et dans le journal d'activité.
    4. Renseigner **Rôle**. La liste ne propose que des rôles de laboratoire
       d'analyse. Elle exclut le rôle de super administrateur et le rôle API.
    5. Renseigner **Laboratoire d'analyse**. La liste ne propose que le
       laboratoire de l'administrateur.
    6. Renseigner les champs facultatifs utiles à l'utilisateur :

        | Champ | Ce qu'il faut saisir |
        | --- | --- |
        | Province, District | La localisation de l'utilisateur |
        | Accès à l'application mobile | **Oui** si l'utilisateur se connecte à l'application mobile |
        | Nom d'utilisateur de l'interface (de votre machine de test moléculaire) | Le nom que cette personne utilise sur l'automate. Séparer plusieurs noms par des virgules |
        | Signature | Une image de signature pour toute personne qui approuve des résultats. Elle s'imprime sur les PDF de résultats |

    7. Saisir l'**Identifiant de connexion**.
    8. Saisir **Mot de passe** et **Confirmer le mot de passe**, ou
       sélectionner **Générer**.
    9. Sélectionner **Envoyer**.
    10. Remettre l'identifiant de connexion et le mot de passe à l'utilisateur
        en main propre. InteLIS lui demande de changer le mot de passe à la
        première connexion.

    ??? info "Règles de l'identifiant de connexion et du mot de passe"

        L'identifiant de connexion accepte les lettres minuscules, les
        chiffres, les traits d'union (-) et les tirets bas (_). Il n'accepte ni
        espaces ni majuscules.

        Le mot de passe compte au moins 8 caractères, dont au moins un chiffre
        et une lettre. Les caractères spéciaux sont autorisés.

## Limiter un utilisateur à certaines structures

Ceci ne concerne que le STS. À utiliser pour le personnel des structures qui
enregistre ses propres demandes, afin que chacun ne voie que sa structure.

1. Aller à **ADMIN → Contrôle d'accès → Utilisateurs**.
2. Sélectionner **Modifier** sur l'utilisateur.
3. Sous **Carte de l'utilisateur vers les installations sélectionnées
   (facultatif)**, faire passer les structures dans la liste sélectionnée.
4. Sélectionner **Envoyer**.
5. Demander à l'utilisateur d'ouvrir le formulaire de demande. Seules les
   structures rattachées sont proposées.

??? info "Correspondance vide"

    Une correspondance vide signifie aucune limite de structure. La laisser vide
    pour le personnel de laboratoire qui doit voir toutes les structures. Le
    Laboratoire d'analyse s'applique toujours.

## Réinitialiser le mot de passe d'un utilisateur

1. Aller à **ADMIN → Contrôle d'accès → Utilisateurs**.
2. Sélectionner **Modifier** sur l'utilisateur.
3. Saisir **Mot de passe** et **Confirmer le mot de passe**, ou sélectionner
   **Générer**.
4. Sélectionner **Envoyer**.
5. Remettre le nouveau mot de passe à l'utilisateur en main propre. InteLIS lui
   demande de le changer à la connexion suivante.

## Donner un jeton d'API à un utilisateur

Les systèmes qui se connectent par l'API utilisent un jeton au lieu d'un mot de
passe.

1. Aller à **ADMIN → Contrôle d'accès → Utilisateurs**.
2. Sélectionner **Modifier** sur l'utilisateur.
3. Régler **Rôle** sur le rôle API. Le champ **AuthToken** apparaît.
4. Sélectionner **Générer un autre jeton**.
5. Sélectionner **Envoyer**.
6. Copier le jeton du champ **AuthToken** dans le système qui se connecte.

??? warning "Un nouveau jeton arrête aussitôt l'ancien"

    Tout ce qui utilise encore le jeton précédent cesse de fonctionner dès
    l'enregistrement du nouveau.

??? info "Sur une instance cloud"

    La liste des rôles d'un administrateur de laboratoire exclut le rôle API.
    L'administrateur national crée les comptes API.

## Désactiver un utilisateur qui part

1. Aller à **ADMIN → Contrôle d'accès → Utilisateurs**.
2. Sélectionner **Modifier** sur l'utilisateur.
3. Régler **Statut de l'utilisateur** sur **Inactif**.
4. Sélectionner **Envoyer**.

**Statut de l'utilisateur** n'existe que sur la page Modifier l'utilisateur. Un
nouvel utilisateur est toujours enregistré comme actif.

??? info "Effet de la désactivation"

    L'identifiant de connexion ne permet plus de se connecter. InteLIS efface
    aussi le mot de passe et tout jeton d'API. Pour réactiver l'utilisateur,
    régler **Statut de l'utilisateur** sur **Actif** et lui remettre un nouveau
    mot de passe.

    Ne pas supprimer le compte, et ne pas donner l'identifiant de connexion à
    quelqu'un d'autre. Les fiches créées par l'utilisateur restent rattachées à
    son nom.

## Ajouter ou modifier un rôle

Un rôle est un ensemble nommé de privilèges. Les utilisateurs ne tiennent leurs
privilèges que de leur rôle.

1. Aller à **ADMIN → Contrôle d'accès → Les rôles**.
2. Sélectionner **Ajouter rôle**, ou **Modifier** sur un rôle existant.
3. Saisir **Nom du rôle** et **Code de rôle**. Le code doit être unique.
4. Renseigner **Page de destination**. Les utilisateurs de ce rôle arrivent sur
   cette page après la connexion.
5. Régler **Statut** sur **Actif**.
6. Renseigner **Type d'Accès** : **Laboratoire d'analyse** pour le personnel du
   laboratoire, **Site de prélèvement** pour le personnel des structures. Le
   renseigner avant les privilèges. Il masque les pages qui ne relèvent pas de
   ce type, et InteLIS refuse les pages masquées à l'enregistrement.
7. Sous **Privilèges**, ouvrir le panneau de chaque module et activer chaque page
   dont ce rôle a besoin. Utiliser **Rechercher les permissions...** pour
   trouver une page.
8. Sélectionner **Envoyer**.

??? info "Quels rôles portent un privilège"

    Sur la page des rôles, ouvrir **Recherche avancée** et renseigner
    **Permission**. La liste ne montre que les rôles qui le portent.

??? info "Le rôle de super administrateur"

    Le premier rôle porte tous les privilèges, quoi qu'affiche sa liste de
    privilèges. Son accès ne peut pas être restreint.

??? warning "Séparer la saisie et l'approbation"

    L'approbation est le contrôle de la qualité des résultats. Un rôle qui peut
    à la fois saisir et approuver des résultats permet à une personne de valider
    son propre travail.

## Vérifier que tout fonctionne

| Modification | Contrôle |
| --- | --- |
| Nouvel utilisateur | L'utilisateur se connecte, change son mot de passe et voit le menu attendu |
| Laboratoire d'analyse renseigné | L'utilisateur voit les échantillons de son laboratoire et d'aucun autre |
| Correspondance de structures | Le formulaire de demande ne propose que les structures rattachées |
| Rôle nouveau ou modifié | Un utilisateur de ce rôle voit les pages attendues |
| Utilisateur désactivé | L'identifiant de connexion ne permet plus de se connecter |
