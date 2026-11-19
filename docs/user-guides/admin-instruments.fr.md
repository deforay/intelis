---
description: Enregistrer un automate, ses machines, son format de date, ses limites et ses contrôles pour qu'InteLIS importe ses résultats.
audience: [lab-admin, system-admin]
module: [all]
type: how-to
reviewed: 2026-11-19
reviewed_against: 5.8.8
---
# Configurer un instrument

Enregistrer un automate sous **ADMIN → Paramètres →
Instruments**, pour qu'InteLIS puisse lire ses résultats. Un instrument non
enregistré ne peut pas être choisi pour un lot, et ses fichiers de résultats
ne peuvent pas être importés.

Deux tâches voisines ont leur propre guide :

- Pour envoyer les résultats directement depuis l'automate, voir
  [Connecter un automate à InteLIS](../guides/setting-up-interfacing-tool.md).
- Pour relier une installation de l'outil d'interface au laboratoire, voir
  [Connexions de l'outil d'interface](admin-interface-tool-connections.md).

## Avant de commencer

- Un compte administrateur, ou sur une instance cloud, un compte de
  laboratoire autorisé à gérer les instruments (son propre laboratoire
  uniquement)
- Le laboratoire d'analyse, créé sous **ADMIN → Structures sanitaires**
- Une date copiée exactement telle que l'automate l'écrit dans ses fichiers de
  résultats

## Ajouter un instrument

1. Aller à **ADMIN → Paramètres → Instruments**.
2. Sélectionner **Ajouter un instrument**.
3. Saisir **Nom de l'instrument**, le nom que le laboratoire donne à cet
   automate, par exemple Cobas 6800 NRL. Chaque instrument doit avoir son
   propre nom.
4. Renseigner **Make** avec le fabricant de l'automate, par exemple Roche ou
   Abbott. Si le fabricant n'est pas dans la liste, choisir **Autre**.

    ??? info "À quoi sert Make"

        **ADMIN → Rapports → Activité des instruments** regroupe les tests de
        **Tests par instrument** par fabricant sous **Par type d'instrument**.
        Laissé vide, le rapport déduit le fabricant du **Fichier des
        instruments** et du **Nom de l'instrument**. Sur la page Modifier
        l'instrument, **Make** affiche cette déduction tant qu'aucun fabricant
        n'est enregistré.

5. Renseigner **Laboratoire d'analyse** avec le laboratoire où se trouve
   l'automate.

    ??? info "Laboratoire d'analyse sur un LIS ou une instance cloud"

        Un LIS n'affiche pas de champ **Laboratoire d'analyse**. InteLIS
        utilise le laboratoire de l'installation. Sur une instance cloud, un
        utilisateur de laboratoire sans le rôle Admin intégré ne voit que son
        propre laboratoire.

6. Sous **Tests pris en charge**, choisir chaque type de test réalisé par
   l'automate.
7. Renseigner **Fichier des instruments**. Il indique à InteLIS comment lire les
   fichiers de résultats de cet automate. Sans lui, l'import de fichiers
   échoue. Si l'automate n'a pas de fichier dans la liste, un développeur doit
   l'écrire avant que l'import de fichiers fonctionne.

    ??? failure "Si l'instrument n'apparaît pas dans Importer les résultats d'un fichier"

        **Nom de l'instrument/plateforme** sur **Importer les résultats d'un
        fichier** ne propose un instrument que si les trois conditions sont
        remplies :

        - Son **Statut** est **Actif**.
        - Le type de test figure parmi ses **Tests pris en charge**.
        - Son **Fichier des instruments** existe pour ce type de test et n'est
          pas vide.

        Corriger la condition manquante sur la page Modifier l'instrument. Si le
        fichier manque ou est vide pour ce type de test, un développeur doit
        l'écrire.

8. Saisir les limites de résultat :

    | Champ | Ce qu'il faut saisir |
    | --- | --- |
    | Limite inférieure | La plus petite valeur rendue par l'automate, par exemple 20 |
    | Limite supérieure | La plus grande valeur rendue par l'automate, par exemple 10000000 |
    | Nombre maximal d'échantillons dans un lot | Le nombre d'échantillons d'une série |
    | Faible VL Texte de résultat | Chaque texte que l'automate écrit pour un résultat indétectable, séparés par des virgules, par exemple `Target Not Detected, TND, < 20, < 40`. Affiché seulement si VL ou Hépatite figure parmi les **Tests pris en charge** |

    ??? warning "Une formulation absente de Faible VL Texte de résultat"

        Un résultat écrit dans une formulation absente de la liste est importé
        comme résultat non reconnu, et non comme indétectable.

9. Sous **Noms des machines**, saisir le **Nom de la machine** du premier
   automate de ce modèle.
10. Dans la cellule **Format de date** de cette ligne, coller la date copiée
   d'un fichier de résultats, par exemple `06.19.2025 11:19 AM`. Choisir le
   format proposé par InteLIS.

    ??? failure "Si aucun format n'est proposé"

        Saisir le format à la main, par exemple `d/m/Y H:i`. Un mauvais format
        de date rend chaque date importée fausse ou vide.

11. Dans **Nom du fichier de l'instrument** sur cette ligne, choisir le
    fichier de cet automate. Laissé vide, la ligne utilise le **Fichier des
    instruments** défini plus haut.
12. Si l'automate est un dispositif de biologie délocalisée, cocher **S'agit-il
    d'un dispositif POC ?** et saisir sa **Latitude** et sa **Longitude**. Les
    deux coordonnées sont nécessaires. Sans elles, l'automate n'est pas
    enregistré comme POC.
13. Pour ajouter un autre automate du même modèle, sélectionner **+** sur la
    ligne, puis répéter les étapes 9 à 12 sur la nouvelle ligne.
14. Pour chaque type de test, saisir le nombre de contrôles. Une ligne apparaît
    pour chaque type de test choisi sous **Tests pris en charge**. Le nombre
    indique à InteLIS combien de positions d'une série ne sont pas des
    échantillons de patients :

    | Champ | Ce qu'il faut saisir |
    | --- | --- |
    | Nombre de contrôles internes | Les positions de contrôle interne par série |
    | Nombre de contrôles du fabricant | Les positions de contrôle du fabricant par série |
    | Nombre d'étalonneurs | Les positions d'étalonneur par série |

15. Si les mêmes personnes valident toujours les résultats de cet automate,
    renseigner **Réviseur par défaut** et **Approbateur par défaut** pour chaque
    type de test. Laissés vides, chaque résultat enregistre la personne qui l'a
    réellement révisé et approuvé.
16. Pour imprimer une mention de méthode fixe sur chaque résultat de cet
    automate, la saisir sous **Description/Commentaire à ajouter dans le
    résultat du test**.
17. Sélectionner **Envoyer**.

## Retirer un instrument

1. Aller à **ADMIN → Paramètres → Instruments**.
2. Sélectionner **Modifier** sur l'instrument.
3. Régler **Statut** sur **Inactif**.
4. Sélectionner **Envoyer**.

**Statut** n'existe que sur la page Modifier l'instrument. Un nouvel instrument
est enregistré comme actif.

## Vérifier que tout fonctionne

| Modification | Contrôle |
| --- | --- |
| Nouvel instrument | Il est proposé comme plateforme de test à la création d'un lot |
| Make | **Par type d'instrument** dans **Activité des instruments** compte les tests de l'instrument sous ce fabricant |
| Fichier des instruments | Importer un fichier de résultats de l'automate et lire les lignes importées |
| Format de date | Les dates de test importées correspondent à celles de l'automate |
| Faible VL Texte de résultat | Un résultat indétectable est importé comme indétectable |
| Nombre de contrôles | Le nombre de positions du lot correspond à la série |
