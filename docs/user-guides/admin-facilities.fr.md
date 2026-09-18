# Gérer les structures et les laboratoires d'analyse

Ajouter et entretenir les structures sanitaires et les laboratoires d'analyse
sous **ADMIN → Structures sanitaires**. Chaque échantillon est rattaché à l'un et
à l'autre.

D'autres tâches de la même page ont leur propre guide :

- [Ajouter ou mettre à jour plusieurs structures à la fois](admin-facilities-bulk-upload.md)
- [Connecter l'outil d'interface à un laboratoire d'analyse](admin-interface-tool-connections.md)

## Avant de commencer

- Un compte avec des droits d'administrateur sur le STS, ou sur une
  installation autonome
- La province et le district de la structure, créés sous **ADMIN →
  Configuration du système → Divisions géographiques**

??? info "Sur un LIS, la page Structures sanitaires est en lecture seule"

    Un LIS liste les structures sans bouton **Ajouter installations**,
    **Modifier** ni **Chargement groupé**. Faire la modification sur le STS.
    Pour l'amener aussitôt sur le LIS, sélectionner **Forcer la synchronisation
    à distance** en bas à droite de n'importe quelle page du LIS.

## Ajouter une structure

1. Aller à **ADMIN → Structures sanitaires**.
2. Sélectionner **Ajouter installations**.
3. Saisir **Nom de la structure**. Il ne doit pas déjà être utilisé.
4. Saisir **Code de la structure**, le code national unique. Il accepte les
   lettres, les chiffres et les traits d'union.
5. Renseigner **Type d'installation** : **Etablissement de santé**,
   **Laboratoire d'analyse** ou **Site de prélèvement**.
6. Renseigner **Province** et **District**.
7. Sous **Type de test**, cocher chaque type de test auquel la structure
   participe.

    ??? warning "Une structure absente d'un formulaire de demande"

        Le formulaire de demande d'un type de test ne propose que les
        structures cochées pour ce type de test. Une structure cochée pour la
        charge virale seulement est absente du formulaire EID.

8. Renseigner les champs facultatifs utiles à la structure :

    | Champ | Ce qu'il faut saisir |
    | --- | --- |
    | Autre/code externe | Un second code, lorsqu'un autre système utilise le sien |
    | Point(s) de contrôle | Les points de service, par exemple CDV ou PTME |
    | Adresse, Latitude, Longitude | L'emplacement de la structure. La latitude et la longitude la placent sur la carte Exemple de réseau de recommandation |
    | Email(s) | Les adresses d'envoi des résultats, séparées par des virgules |
    | Gestionnaire du laboratoire, Numéro de téléphone | La personne de contact |
    | Nom du Hub lié (le cas échéant) | Le hub par lequel transitent les échantillons de la structure |

9. Si la structure est un laboratoire d'analyse, renseigner les réglages du
   laboratoire. Voir
   [Configurer un laboratoire d'analyse](#configurer-un-laboratoire-danalyse),
   étapes 5 à 9.
10. Sélectionner **Envoyer**.

## Rattacher plusieurs structures à un type de test

À utiliser lorsque plusieurs structures manquent au formulaire de demande d'un
type de test, par exemple après un chargement groupé.

1. Aller à **ADMIN → Structures sanitaires**.
2. Sélectionner **Établissements de santé**, ou **Laboratoire d'analyse** pour
   les laboratoires.
3. Renseigner **Type de test**.
4. Faire passer les structures qui participent dans la liste sélectionnée.
5. Sélectionner **Envoyer**.
6. Ouvrir le formulaire de demande de ce type de test. Les structures sont
   proposées.

## Configurer un laboratoire d'analyse

Un laboratoire d'analyse est une structure dont le **Type d'installation** est
**Laboratoire d'analyse**. Il porte des réglages qu'une structure sanitaire n'a
pas.

1. Aller à **ADMIN → Structures sanitaires**.
2. Sélectionner **Modifier** sur le laboratoire.
3. Vérifier que **Type d'installation** vaut **Laboratoire d'analyse**, et que
   **Type de test** contient chaque test réalisé par le laboratoire.
4. Dans le tableau des objectifs, saisir l'**Objectif mensuel** de chaque type
   de test. Pour la charge virale, saisir aussi la **Cible mensuelle de
   suppression virale**. Le tableau de bord compare le travail du laboratoire à
   ces objectifs lorsque **VL Objectif mensuel** est activé dans la
   [Configuration générale](admin-general-configuration.md).

    ??? info "Pas de tableau des objectifs"

        Le tableau n'existe que sur **Modifier l’installation**. Enregistrer
        d'abord le nouveau laboratoire, puis le modifier.

5. Régler **Autoriser le téléchargement de fichiers de résultats** sur **Oui**
   si le laboratoire importe des fichiers de résultats.
6. Pour la TB, renseigner **Plates-formes disponibles** avec les méthodes du
   laboratoire : **Microscopie**, **Xpert** ou **Lam**.

    ??? info "Plates-formes disponibles n'apparaît pas"

        Le champ n'apparaît que tant que la TB est le seul type de test coché.

7. Charger l'**Image du logo** imprimée sur les PDF de résultats de ce
   laboratoire. Elle doit mesurer 80 sur 80 pixels.
8. Choisir la mise en page du PDF de résultats de chaque type de test sous
   **Format de rapport pour la CV**, **Format de rapport pour l'EID** et les
   champs correspondants des autres modules.
9. Régler l'en-tête et le pied de page du rapport :

    | Réglage | Contrôle |
    | --- | --- |
    | Texte du header | Le titre imprimé sur le rapport |
    | Afficher le numéro de page dans le pied de page | La numérotation des pages |
    | Afficher le tableau de signatures | L'impression du bloc de signatures |
    | Rapport sur la marge supérieure | L'espace au-dessus du rapport |
    | Emplacement du texte en bas de page | **Au-dessus du pied de page** ou **Nom de la plate-forme inférieure** |
    | Télécharger le modèle de rapport | Un modèle PDF par type de test, avec sa **Marge de l'en-tête**, lorsque la mise en page par défaut ne convient pas |

10. Sélectionner **Envoyer**.

## Ajouter des signataires aux PDF de résultats

Les signataires sont les noms, désignations et signatures imprimés sur les PDF
de résultats d'un laboratoire.

1. Aller à **ADMIN → Structures sanitaires**.
2. Sélectionner **Modifier** sur le laboratoire d'analyse.
3. Dans le tableau des signataires, saisir le **Nom du signataire** et la
   **Désignation**.
4. Charger la signature sous **Télécharger la signature (jpg, png)**.
5. Sous **Types de tests**, choisir chaque module dont le PDF de résultats porte
   ce signataire.

    ??? warning "Un signataire sans type de test ne s'imprime jamais"

        InteLIS enregistre la ligne, mais ne l'imprime sur aucun PDF de
        résultats. Le bloc de signatures paraît alors désactivé.

6. Renseigner l'**Ordre d'affichage** et le **Statut actuel**.
7. Répéter les étapes 3 à 6 sur une nouvelle ligne pour chaque autre
   signataire.
8. Sélectionner **Envoyer**.
9. Imprimer un PDF de résultats par module et lire le bloc de signatures.

## Trouver les structures mal localisées

1. Aller à **ADMIN → Structures sanitaires**.
2. Ouvrir **Recherche avancée**.
3. Cocher **Afficher les installations orphelines**.
4. Sélectionner **Search**. La liste montre les structures dont la province ou
   le district manque, est inactif ou n'est pas rattaché à sa province.
5. Sélectionner **Modifier** sur chacune, renseigner une **Province** et un
   **District** valides, puis sélectionner **Envoyer**.

Ces structures sortent des filtres de localisation des rapports tant qu'elles
ne sont pas corrigées.

## Vérifier que tout fonctionne

| Modification | Contrôle |
| --- | --- |
| Nouvelle structure | Elle apparaît sur le formulaire de demande de chaque type de test coché |
| Laboratoire d'analyse | Il apparaît dans la liste **Laboratoire d'analyse** du formulaire de demande |
| Objectifs | Les graphiques d'objectifs du tableau de bord montrent les chiffres du laboratoire |
| Signataires | Un PDF de résultats imprimé porte les noms attendus |
| Structure orpheline corrigée | Elle n'apparaît plus sous **Afficher les installations orphelines** |
