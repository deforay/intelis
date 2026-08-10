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

Renseigner le **Test Type**. Une structure non rattachée à la charge virale
n'apparaît pas dans la liste des structures du formulaire de demande de charge
virale. C'est la raison habituelle d'une structure « absente » du formulaire.

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

## Maintenir les listes de la charge virale

Les listes déroulantes du formulaire de demande de charge virale proviennent des
listes situées sous **ADMIN → Configuration CV**.

| Liste | Contrôle |
|---|---|
| Régime ART | Les choix de régime sur le formulaire |
| Motifs de rejet | Les motifs proposés lors du rejet d'un échantillon |
| Type d'échantillon | Les types de prélèvement |
| Résultats | Les valeurs de résultat pour le rendu qualitatif |
| Motifs de test | Les indications de test |
| Raisons de l'échec des tests | Les motifs proposés en cas d'échec |
| Mesures correctives recommandées | Les actions suggérées sur les résultats de charge virale élevée |

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

Deux paramètres demandent de l'attention.

**Same user can Review and Approve.** L'activer permet à une personne de saisir
et de valider son propre résultat. Le désactiver partout où les effectifs le
permettent.

**Auto Approve Interface Results.** L'activer diffuse les résultats de l'automate
sans contrôle humain. C'est sûr lorsque l'automate est fiable et que le circuit
des batchs est respecté. Ce ne l'est pas lorsque les ID d'échantillon sont
saisis à la main sur l'automate.

## Vérifier qui a fait quoi

| Page | Répond à |
|---|---|
| **ADMIN → Surveillance → Journal d'activité de l'utilisateur** | Quelles pages un utilisateur a ouvertes, et quand |
| **ADMIN → Surveillance → Piste d'audit** | Quel champ a changé, de quoi à quoi, par qui |
| **ADMIN → Surveillance → Log File Viewer** | Les messages système, pour les demandes de support |

Utiliser la piste d'audit lorsqu'un résultat est contesté. Elle donne
l'historique des modifications de la fiche.

## Vérifier que tout fonctionne

| Modification | Contrôle |
|---|---|
| Nouvel utilisateur | L'utilisateur se connecte et voit le menu attendu |
| Modification de rôle | Se connecter avec un utilisateur de ce rôle, ou utiliser le filtre Permission sur la page des rôles |
| Nouvelle structure | La structure apparaît dans la liste du formulaire de demande de charge virale |
| Nouvel automate | L'automate apparaît dans Plateforme de test à la création d'un batch |
| Connexion de l'outil d'interface | L'installation figure sous Installations connectées avec une Dernière connexion récente |
| Entrée de liste | L'entrée apparaît dans sa liste déroulante sur le formulaire |
