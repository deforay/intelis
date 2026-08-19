# Administrer InteLIS

Ce guide couvre les tâches d'un administrateur au sein d'InteLIS : créer les
comptes, définir ce que chaque rôle peut atteindre, tenir à jour les structures
et les automates, et maintenir correctes les listes déroulantes du formulaire de
demande.

Il ne couvre pas l'installation, la mise à jour ni la sauvegarde d'InteLIS. Ce
sont des tâches serveur, traitées dans les guides d'installation et de
maintenance.

## Avant de commencer

- Un compte disposant des droits d'administrateur

Tout ce qui suit se trouve sous **ADMIN** dans le menu latéral.

## Ajouter un utilisateur

InteLIS ne permet pas la création de compte par l'utilisateur. Chaque compte est
créé par un administrateur.

1. Aller à **ADMIN → Contrôle d'accès → Utilisateurs**.
2. Sélectionner **Ajouter un utilisateur**.
3. Renseigner les informations.

| Champ | À saisir |
|---|---|
| Nom complet | Le nom tel qu'il apparaît sur les rapports et dans le journal d'activité |
| Courriel | L'adresse de l'utilisateur |
| Numéro de téléphone | Le numéro de l'utilisateur |
| Rôle | Le rôle qui définit ce que l'utilisateur peut atteindre |
| Labo de test | Le laboratoire dans lequel l'utilisateur travaille |
| Mobile App Access | Si le compte peut utiliser l'application mobile |
| Signature | Une image de signature, utilisée sur les PDF de résultats. 100 sur 100 pixels |
| Identifiant de connexion | L'identifiant de connexion |
| Mot de passe et confirmation | Le mot de passe initial |

4. Sélectionner **Envoyer**.
5. Communiquer l'identifiant et le mot de passe directement à l'utilisateur.

Renseigner le **Labo de test** sur chaque utilisateur. Ce champ restreint ce que
l'utilisateur voit au travail de son propre laboratoire.

Ne jamais partager un compte entre plusieurs personnes. Le journal d'activité et
les noms de testeur, réviseur et approbateur sur les rapports enregistrent la
personne connectée. Un compte partagé rend ces enregistrements inutilisables.

Pour désactiver un utilisateur qui part, modifier le compte et le passer en
inactif. Ne pas réattribuer l'identifiant à quelqu'un d'autre. Les anciens
enregistrements restent rattachés au nom.

## Ajouter ou modifier un rôle

Un rôle est un ensemble nommé de permissions. Les utilisateurs tiennent leurs
permissions de leur rôle, jamais individuellement.

1. Aller à **ADMIN → Contrôle d'accès → Les rôles**.
2. Sélectionner **Ajouter rôle**, ou **Modifier** sur un rôle existant.
3. Renseigner les informations.

| Champ | À saisir |
|---|---|
| Role Name | Un nom que le personnel reconnaît, par exemple Technicien de laboratoire |
| Role Code | Un code court unique |
| Landing Page | La page ouverte après connexion pour ce rôle |
| Access Type | **Testing Lab** pour le personnel de laboratoire, **Collection Site** pour le personnel de structure |
| Status | Actif ou inactif |
| Privileges | Cocher chaque page accessible à ce rôle |

4. Sélectionner **Envoyer**.

Régler la **Landing Page** sur la page la plus utilisée par le rôle. Le personnel
de saisie ouvre directement le formulaire de demande.

Utiliser le champ de recherche des permissions plutôt que de parcourir la liste.

Donner à chaque rôle les permissions nécessaires à son travail, et rien de plus.
L'approbation est le contrôle qualité des résultats. Un rôle qui peut à la fois
saisir et approuver ses propres résultats supprime ce contrôle.

Pour savoir quels rôles détiennent une permission donnée, utiliser le filtre
**Permission** sur la page des rôles.

## Ajouter une structure

Chaque échantillon est rattaché à une structure sanitaire et à un laboratoire de
test. Les deux sont des fiches de la page Structures sanitaires.

1. Aller à **ADMIN → Structures sanitaires**.
2. Sélectionner **Ajouter installations**.
3. Renseigner les informations.

| Champ | À saisir |
|---|---|
| Facility Name | Le nom que le personnel recherchera |
| Facility Code | Le code national unique |
| Facility Type | Structure sanitaire ou laboratoire de test |
| Email(s) | Les adresses pour l'envoi des résultats, séparées par des virgules |
| Report Email(s) | Les adresses pour la diffusion des rapports |
| Testing Point(s) | Les points de service, par exemple CDV ou PTME |
| Lab Manager | La personne de contact |
| Province et District | La localisation |
| Linked Hub Name | Le hub par lequel transitent les échantillons, le cas échéant |
| Test Type | Chaque type de test auquel la structure participe |

4. Sélectionner **Envoyer**.

Renseigner le **Test Type**, et cocher chaque type de test auquel la structure
participe. Une structure non rattachée à un type de test n'apparaît pas dans la
liste des structures du formulaire de demande de ce type de test. Une structure
cochée pour un seul type de test reste absente du formulaire de tous les autres.
C'est la raison habituelle d'une structure « absente ».

Pour un laboratoire de test, téléverser les signataires. Ils apparaissent sur les
PDF de résultats émis par ce laboratoire.

Pour repérer les structures dont la province ou le district est absent ou mal
rattaché, activer **Show Orphaned Facilities** sur la page. Ces structures se
comportent de façon imprévisible dans les filtres géographiques tant qu'elles ne
sont pas corrigées.

Pour charger de nombreuses structures en une fois, utiliser **Bulk Upload**.

## Ajouter un automate

1. Aller à **ADMIN → Configuration du système → Instruments**.
2. Sélectionner **Ajouter un instrument**.
3. Saisir le nom, les types de tests réalisés, et le fichier de configuration qui
   indique à InteLIS comment lire ses fichiers de résultats.
4. Sélectionner **Envoyer**.

Le fichier de configuration est ce qui rend l'import de fichier possible pour cet
automate. Sans lui, les résultats exportés depuis l'automate ne peuvent pas être
lus. Voir [Saisir les résultats de charge virale](capture-results.md).

## Connecter l'outil d'interface

L'outil d'interface transmet les résultats d'un automate vers InteLIS sans
aucune saisie. Chaque installation de l'outil se connecte une fois à InteLIS.

1. Aller à **ADMIN → Structures sanitaires**.
2. Ouvrir le laboratoire de test.
3. Descendre jusqu'à **Connexions des outils d'interface** en bas de page.
4. Sélectionner **Générer un code de connexion**.
5. Saisir les trois groupes du code, ainsi que l'URL d'InteLIS affichée
   au-dessus, dans l'outil d'interface sur l'ordinateur du laboratoire.

Le code expire. La page affiche le temps restant. En cas d'expiration, en
générer un autre.

Un seul code peut être en cours à la fois. Pour recommencer, annuler d'abord le
code en cours.

Une fois connectée, l'installation apparaît sous **Installations connectées**
avec un statut et une **Dernière connexion**. Utiliser cette date lorsque les
résultats cessent d'arriver.

| Action | Quand l'utiliser |
|---|---|
| **Reconnect / Reinstall** | L'ordinateur du laboratoire est réinstallé ou l'outil est réinstallé |
| **Revoke** | L'ordinateur est retiré du service ou perdu. Les autres installations ne sont pas affectées |

## Maintenir les listes du formulaire de demande

Chaque module de test tient ses propres listes. Elles alimentent les listes
déroulantes du formulaire de demande de ce module. Ajouter un type
d'échantillon sous un module ne l'ajoute pas au formulaire d'un autre module.
L'ajouter sous chaque module concerné.

Le menu ADMIN porte une section de configuration par module actif sur
l'installation. Une installation qui n'exécute qu'un module ne porte qu'une
section.

| Section de configuration | Listes qu'elle contient |
|---|---|
| Configuration CV | Type d'échantillon, Motifs de rejet, Motifs de test, Résultats, Régime ART, Raisons de l'échec des tests, Mesures correctives recommandées |
| Configuration EID | Type d'échantillon, Motifs de rejet, Motifs de test, Résultats |
| Tuberculose-Configuration | Type d'échantillon, Motifs de rejet, Motifs de test, Résultats |
| Configuration CD4 | Type d'échantillon, Motifs de rejet, Motifs de test |
| Configuration Covid-19 | Type d'échantillon, Motifs de rejet, Motifs de test, Résultats, Symptomes, Co-morbidités, Mesures correctives recommandées, Kits de test QC |
| Configuration Hépatite | Type d'échantillon, Motifs de rejet, Motifs de test, Résultats, Co-morbidités, Facteurs de risque |
| Autres tests de laboratoire Config | Types d'échantillons, Raisons des tests, Motifs de rejet des échantillons, Raisons de l'échec des tests, Symptomes, Unités de résultat du test, Méthodes de test, Catégories de tests, Configuration du type de test |

Ce que contrôle chaque liste :

| Liste | Contrôle |
|---|---|
| Type d'échantillon | Les types de prélèvement proposés sur le formulaire |
| Motifs de rejet | Les motifs proposés lors du rejet d'un échantillon |
| Motifs de test | Les indications de test |
| Résultats | Les valeurs de résultat pour le rendu qualitatif |
| Raisons de l'échec des tests | Les motifs proposés en cas d'échec |
| Symptomes, Co-morbidités, Facteurs de risque | Les listes cliniques à cocher sur le formulaire |
| Régime ART | Les choix de régime sur le formulaire de charge virale |
| Mesures correctives recommandées | Les actions suggérées sur les résultats de charge virale élevée |
| Unités de résultat du test, Méthodes de test, Catégories de tests | Les propriétés disponibles pour un type de test personnalisé |

Chaque liste fonctionne de la même façon. Ouvrir la page, sélectionner l'option
d'ajout, saisir le libellé et enregistrer. Pour retirer une entrée, la modifier
et la passer en inactif.

Passer les entrées en inactif plutôt que les supprimer. Une entrée inactive
disparaît du formulaire mais reste lisible sur les fiches qui l'utilisent déjà.

## Maintenir la géographie, les partenaires, les bailleurs et les congélateurs

| Page | Contrôle |
|---|---|
| **ADMIN → Configuration du système → Divisions géographiques** | Provinces et districts |
| **ADMIN → Configuration du système → Partenaires** | La liste des partenaires du formulaire |
| **ADMIN → Configuration du système → Sources de financement** | La liste des bailleurs du formulaire |
| **ADMIN → Configuration du système → Stockage en laboratoire** | Les congélateurs proposés sur la page de stockage |

Pour les divisions géographiques, laisser le parent vide en ajoutant une
province. Renseigner une province comme parent en ajoutant un district. Un
district ajouté sans parent n'apparaît sous aucune province dans le formulaire.

## Modifier la configuration générale

**ADMIN → Configuration du système → Configuration générale** contient les
paramètres qui modifient le comportement d'InteLIS sur toute l'installation.

| Groupe | Exemples |
|---|---|
| Apparence | L'en-tête et le logo des rapports |
| Formats | Fuseau horaire, format de date, format de code-barres |
| ID d'échantillon | Le format et le préfixe des ID, par type de test |
| Résultats | Mise en page du PDF, seuil de charge virale, objectif mensuel de test |
| Verrouillage | Le nombre de jours avant verrouillage d'un échantillon, et avant péremption |
| Approbation | Si les résultats d'interface sont approuvés automatiquement, et si une même personne peut réviser et approuver |

Modifier un paramètre à la fois et en vérifier l'effet. Ces paramètres
s'appliquent d'un coup à tous les utilisateurs de l'installation.

## Modifications à faire valider avant de les appliquer

Les modifications ci-dessous prennent effet sur toute l'installation dès
l'enregistrement. Revenir en arrière n'annule pas leur effet sur les fiches déjà
créées. Les valider d'abord avec l'équipe nationale.

| Modification | Emplacement | Pourquoi la faire valider |
|---|---|---|
| Format ou préfixe des ID d'échantillon | Configuration générale → ID d'échantillon | Tout échantillon enregistré ensuite porte le nouveau format. Les échantillons déjà enregistrés gardent l'ancien, ce qui laisse deux schémas au laboratoire |
| Jours de verrouillage et de péremption | Configuration générale → Verrouillage | Détermine quand une fiche cesse d'accepter les modifications. Trop court, le laboratoire ne peut plus corriger un résultat. Trop long, les résultats restent modifiables après diffusion |
| Same user can Review and Approve | Configuration générale → Approbation | Permet à une personne de saisir et de valider son propre résultat. L'approbation est le seul contrôle sur la qualité des résultats. Le désactiver partout où les effectifs le permettent |
| Auto Approve Interface Results | Configuration générale → Approbation | Diffuse les résultats de l'automate sans contrôle humain. Sûr lorsque l'automate est fiable et que le circuit des batchs est respecté. Pas sûr lorsque les ID d'échantillon sont saisis à la main sur l'automate |
| Permissions d'un rôle | Contrôle d'accès → Les rôles | S'applique aussitôt à tous les utilisateurs portant ce rôle |
| Suppression d'une entrée de liste | Toute page de configuration de module | Passer l'entrée en inactif à la place. La supprimer rend illisibles les fiches qui l'utilisaient |
| Renommage ou suppression d'une province ou d'un district | Configuration du système → Divisions géographiques | Les structures rattachées perdent leur lien, et les filtres géographiques de tous les rapports cessent de correspondre |

## Vérifier qui a fait quoi

| Page | Répond à |
|---|---|
| **ADMIN → Surveillance → Journal d'activité de l'utilisateur** | Quelles pages un utilisateur a ouvertes, et quand |
| **ADMIN → Surveillance → Piste d'audit** | Quel champ a changé, de quoi à quoi, par qui |
| **ADMIN → Surveillance → Historique de l'API** | Si cette installation a atteint le serveur national, et combien de fiches sont parties |
| **ADMIN → Surveillance → Activité des machines d'interface** | Si chaque automate connecté émet encore |
| **ADMIN → Surveillance → Indicateurs de performance du laboratoire** | Délai de rendu, volumes par mode de saisie, taux d'échec et de rejet |
| **ADMIN → Surveillance → Log File Viewer** | Les messages système, pour les demandes de support |

Utiliser la piste d'audit lorsqu'un résultat est contesté. Elle donne
l'historique des modifications de la fiche.

**État de la synchronisation du laboratoire** et **Tableau de bord de l'API**
n'apparaissent que sur le serveur national. Leur absence sur une installation de
laboratoire est voulue.

## Vérifier que tout fonctionne

| Modification | Contrôle |
|---|---|
| Nouvel utilisateur | L'utilisateur se connecte et voit le menu attendu |
| Modification de rôle | Se connecter avec un utilisateur de ce rôle, ou utiliser le filtre Permission sur la page des rôles |
| Nouvelle structure | La structure apparaît sur le formulaire de demande de chaque type de test coché |
| Nouvel automate | L'automate apparaît dans Plateforme de test à la création d'un batch |
| Connexion de l'outil d'interface | L'installation figure sous Installations connectées avec une Dernière connexion récente |
| Entrée de liste | L'entrée apparaît dans sa liste déroulante sur le formulaire |
