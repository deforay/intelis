# Saisir les résultats de charge virale

Une fois le batch passé sur l'automate, les résultats doivent parvenir à
InteLIS. Il existe trois façons de le faire. Utiliser la plus haute de cette
liste que le laboratoire et l'automate permettent.

| Méthode | Quand l'utiliser |
|---|---|
| [Outil d'interface](#methode-1-outil-dinterface) | L'automate est relié à l'outil d'interface |
| [Import de fichier](#methode-2-import-de-fichier) | L'automate ne peut pas se connecter mais peut exporter un fichier |
| [Saisie manuelle](#methode-3-saisie-manuelle) | Aucune des deux précédentes n'est possible |

La saisie manuelle est une solution de repli. Tout résultat saisi à la main peut
comporter une erreur de frappe, il exige donc toujours l'approbation d'une
seconde personne.

## Avant de commencer

- Un batch dont la série est terminée sur l'automate
- La permission d'enregistrer des résultats

---

## Méthode 1 : outil d'interface

L'outil d'interface s'exécute sur un ordinateur du laboratoire, écoute
l'automate et transmet les résultats à InteLIS. Personne ne saisit de résultat
et personne ne téléverse de fichier.

### Vérifier que l'outil d'interface est prêt

1. Confirmer que l'outil d'interface est installé et en cours d'exécution sur
   l'ordinateur du laboratoire.
2. Confirmer que l'outil est à jour.
3. Ouvrir l'outil d'interface et vérifier que l'automate est indiqué comme
   connecté.

Certains automates n'ouvrent la connexion que lorsqu'ils ont quelque chose à
envoyer. Un outil qui n'indique pas la connexion entre deux séries n'est pas
forcément en panne. Vérifier à nouveau pendant que l'automate libère les
résultats.

### Libérer les résultats

Certains automates retiennent les résultats jusqu'à ce qu'un opérateur les
libère. Lorsque l'automate propose une libération manuelle, libérer la série une
fois que l'opérateur l'a vérifiée.

Sinon, l'automate libère les résultats selon sa propre logique. Aucune action
n'est requise sur l'automate.

### Attendre l'arrivée des résultats

Les résultats parviennent à InteLIS d'eux-mêmes une fois libérés par l'automate.
Il n'y a pas d'étape d'import et aucun bouton à actionner.

Aller à **CHARGE VIRALE DU VIH → Gestion des demandes → Afficher les demandes de
test** et rechercher le code du batch. Les échantillons arrivés portent un
résultat.

Si le laboratoire a activé l'approbation automatique des résultats d'interface,
ces résultats sont prêts à imprimer. Sinon, ils attendent une approbation. Voir
[Vérifier et approuver les résultats](approve-results.md).

### Si les résultats n'arrivent pas

Procéder dans cet ordre.

1. Vérifier que l'automate a effectivement libéré la série.
2. Vérifier que l'outil d'interface fonctionne et indique l'automate comme
   connecté.
3. Vérifier que les ID d'échantillon sur l'automate correspondent à ceux
   d'InteLIS. Un résultat portant un ID non reconnu ne se rattache à aucun
   échantillon.
4. Demander à l'administrateur de vérifier la connexion de l'outil d'interface
   du laboratoire sous **ADMIN → Structures sanitaires**, puis le laboratoire de
   test, puis **Connexions des outils d'interface** en bas de page.
   L'installation affiche un statut et une **Dernière connexion**.

Si les résultats n'arrivent toujours pas, utiliser l'import de fichier pour
cette série et signaler le problème de connexion à l'administrateur.

---

## Méthode 2 : import de fichier

À utiliser lorsque l'automate ne peut pas joindre l'outil d'interface mais peut
écrire un fichier de résultats.

### Exporter le fichier depuis l'automate

Exporter les résultats de la série depuis l'automate. Le fichier doit porter les
ID d'échantillon d'InteLIS, ceux que le PDF du batch a placés sur l'automate.
Les types de fichiers acceptés sont xls, xlsx, csv et txt.

### Téléverser le fichier

1. Aller à **CHARGE VIRALE DU VIH → Gestion des résultats des tests → Importer
   les résultats d'un fichier**.
2. Choisir le **Nom de l'instrument/plateforme**.
3. Choisir le **Specific Machine Name/Code**.
4. Choisir le **Nom du laboratoire de test**.
5. Sélectionner le fichier exporté sous **Fichier**.
6. Sélectionner **Envoyer**.

Choisir l'automate avec attention. Chaque automate écrit son fichier
différemment, et InteLIS lit le fichier selon l'automate sélectionné ici. Un
mauvais choix produit un import illisible, ou aucun import.

Si le format de date du fichier n'est pas reconnu, coller une date copiée depuis
le fichier dans le champ de format de date. InteLIS en déduit le format.

### Vérifier ce qui a été importé

InteLIS liste chaque ligne lue dans le fichier, avec une mention **Sample
source** sur chacune.

| Mention | Signification | Que faire |
|---|---|---|
| Result for Sample ID from VLSM | L'ID correspond à un échantillon enregistré | Accepter |
| Sample ID not from VLSM | L'ID ne correspond à aucun échantillon enregistré | Ne pas accepter. Chercher pourquoi l'ID diffère |
| Result already exists for this sample | L'échantillon a déjà un résultat | N'écraser que si le nouveau résultat est le bon |
| Test date ~1+ month from collection | La date du test est postérieure d'un mois ou plus au prélèvement | Vérifier la date |
| Test date ~1+ year from collection | La date du test est postérieure d'un an ou plus au prélèvement | Vérifier la date. Un écart d'un an est presque toujours une faute de frappe |

Renseigner un **Statut** sur chaque ligne. Renseigner **Tested By**, **Reviewed
By** et **Approved By**.

Pour renseigner toutes les lignes en une action, sélectionner **Accepter tous les
échantillons**. Cette action ne touche que les lignes sans statut, celles déjà
marquées comme rejetées le restent.

InteLIS refuse l'envoi tant qu'une ligne n'a pas de date de test.

Selon la configuration du laboratoire, InteLIS avertit ou refuse lorsque la même
personne est à la fois réviseur et approbateur.

7. Sélectionner **Sauvegarder**.

---

## Méthode 3 : saisie manuelle

À utiliser uniquement lorsque l'automate ne peut ni se connecter ni exporter de
fichier.

1. Aller à **CHARGE VIRALE DU VIH → Gestion des résultats des tests → Saisir le
   résultat manuellement**.
2. Filtrer pour trouver l'échantillon. Régler **Statut** sur **Résultats non
   enregistrés** pour n'afficher que les échantillons en attente.
3. Sélectionner **Saisir le résultat** sur la ligne de l'échantillon.
4. Remplir la section laboratoire du formulaire.

| Champ | À saisir |
|---|---|
| Date de réception de l'échantillon au labo | La date d'arrivée de l'échantillon au laboratoire |
| Sample Testing Date | La date de passage sur l'automate |
| Plateforme de test | L'automate utilisé |
| Résultat de la charge virale (copies/mL) | Le résultat tel que rendu par l'automate |
| Réviseur, Tester, Approbateur | Le personnel responsable |
| Lab Tech. Comments | Tout ce que le rapport doit porter |

5. Sélectionner **Sauvegarder**.

Relire le résultat à l'écran par rapport au tirage de l'automate avant
d'enregistrer.

Un résultat saisi manuellement n'est pas diffusé tant qu'il n'est pas approuvé.
Voir [Vérifier et approuver les résultats](approve-results.md).

---

## Si l'échantillon a été rejeté

Lorsque l'échantillon ne peut pas être testé, enregistrer le rejet au lieu d'un
résultat. Régler **Is Sample Rejected?** sur le formulaire, choisir un **Motif de
rejet** et renseigner la date de rejet.

Un échantillon rejeté transmet le motif au rapport et au rapport de rejet
d'échantillons.

## Si le test a échoué

Lorsque l'automate rend un échec ou une lecture invalide, enregistrer l'échec et
indiquer la **Raison de l'échec**. Les échantillons en échec sont regroupés sur
leur propre page en vue d'un retest. Voir
[Gérer les échecs et les échantillons en attente](failed-and-held-samples.md).

## Vérifier que tout fonctionne

Aller à **CHARGE VIRALE DU VIH → Gestion des demandes → Afficher les demandes de
test** et rechercher le code du batch.

Chaque échantillon de la série porte soit un résultat, soit un rejet, soit un
échec. Les échantillons encore sans résultat ne sont pas parvenus à InteLIS.
Vérifier leurs ID par rapport à l'automate.

## Suite

[Vérifier et approuver les résultats](approve-results.md).
