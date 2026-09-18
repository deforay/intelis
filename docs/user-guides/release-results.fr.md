# Diffuser les résultats à la structure demandeuse

Les résultats approuvés parviennent à la structure demandeuse sous forme de
rapport PDF imprimé, de pièce jointe à un courriel ou d'export vers un tableur.

## Avant de commencer

- Des résultats approuvés. Voir
  [Vérifier et approuver les résultats](approve-results.md)
- La permission d'imprimer, d'envoyer ou d'exporter les résultats

Deux types d'échantillon peuvent être diffusés :

- Les échantillons **Accepté** qui ont un résultat.
- Les échantillons **Rejeté**, qui ne portent aucun résultat. Les diffuser
  indique à la structure qu'elle doit refaire le prélèvement. Les imprimer : le
  courriel n'envoie que les échantillons qui ont un résultat.

Un échantillon encore en attente d'approbation n'est jamais proposé à
l'impression ni au courriel.

## Diffuser les résultats

**Choisir le mode de diffusion, puis suivre ses étapes de haut en bas.**

=== "Imprimer"

    1. Aller à **CHARGE VIRALE DU VIH → Gestion → Imprimer le résultat**.
    2. Rester sur l'onglet **Résultats pas encore imprimés**.

        ??? info "Pour réimprimer un résultat"

            Passer sur l'onglet **Résultats déjà imprimés** et y suivre les
            mêmes étapes.

    3. Filtrer les résultats à imprimer, par exemple par **Nom de la structure**
       pour imprimer ensemble les rapports d'une structure.

        ??? info "Autres filtres"

            | Filtre | Usage |
            | --- | --- |
            | Date du test de l'échantillon | Tout ce qui a été testé à une date |
            | Code de batch | Une seule série d'automate |
            | ID Patient ou Nom du patient | Un seul patient |
            | Province et District | Une région |

    4. Sélectionner **Rechercher**.
    5. Cocher les résultats à imprimer. Le bouton **Imprimer les résultats
       sélectionnés PDF** apparaît.

        ??? info "Pour imprimer un seul résultat"

            Sélectionner plutôt **Imprimer** sur la ligne de ce résultat, puis
            passer à l'étape 7.

    6. Sélectionner **Imprimer les résultats sélectionnés PDF**.

        ??? failure "Si InteLIS refuse plus de 1000 résultats"

            Un PDF contient au plus 1000 résultats. Cocher moins de lignes, les
            imprimer, puis imprimer le reste.

    7. Le PDF s'ouvre dans un nouvel onglet du navigateur. L'imprimer depuis cet
       onglet.
    8. Sélectionner l'onglet **Résultats déjà imprimés** et rechercher de
       nouveau. Les résultats imprimés y figurent.

=== "Courriel"

    1. Aller à **CHARGE VIRALE DU VIH → Gestion des résultats des tests → Envoyer
       le résultat du test par courriel**.
    2. Choisir la structure dans **Nom de l'installation (à)**. InteLIS règle le
       filtre **Nom de la structure** sur la même structure, liste ses
       échantillons et affiche l'adresse à laquelle part le courriel.

        ??? failure "Si InteLIS affiche `Aucune adresse électronique valide n'est disponible`"

            La structure n'a pas d'adresse électronique enregistrée, et
            **Suivante** reste désactivé. Demander à l'administrateur d'en
            ajouter une sous **ADMIN → Structures sanitaires**.

    3. Vérifier le **Sujet** et le **Message**. Les deux sont déjà remplis.
    4. Laisser **Statut du courrier envoyé** sur **Échantillons non encore
       envoyés**, pour écarter les résultats déjà envoyés.
    5. Garder **Nom de la structure** réglé sur cette seule structure.
       Restreindre avec les autres filtres si nécessaire, puis sélectionner
       **Rechercher**.
    6. Sous **Choisir le(s) échantillon(s)**, sélectionner chaque échantillon à
       envoyer, ou sélectionner **Tout sélectionner**. Les échantillons
       sélectionnés passent dans la liste de droite.

        ??? failure "Si InteLIS refuse la sélection"

            Un courriel contient au plus 100 échantillons. Envoyer le reste dans
            un second courriel.

    7. Sélectionner **Suivante**. La page suivante liste les échantillons
       envoyés.

        ??? info "Si un échantillon rejeté manque dans la liste"

            Le courriel n'envoie que les échantillons qui ont un résultat.
            Imprimer plutôt le rapport de l'échantillon rejeté.

    8. Sélectionner **Send**. Ce bouton s'affiche en anglais.
    9. Revenir à **Envoyer le résultat du test par courriel**, régler **Statut du
       courrier envoyé** sur **Échantillons déjà envoyés** et rechercher. Les
       résultats envoyés y figurent.

=== "Exporter"

    À utiliser lorsqu'une structure ou un programme demande les données plutôt
    que les rapports patients. Envoyer les rapports patients sous forme de PDF
    imprimés.

    1. Aller à **CHARGE VIRALE DU VIH → Gestion → Exporter les résultats**.
    2. Régler **Statut** sur **Accepté**, et ajouter **Rejeté** si les rejets
       sont demandés aussi.
    3. Régler les autres filtres, par exemple **Nom de la structure** ou **Date
       du test de l'échantillon**.
    4. Sélectionner **Rechercher** et vérifier les lignes listées.
    5. Sélectionner **Télécharger**. Le tableur s'ouvre dans un nouvel onglet du
       navigateur sous forme de téléchargement.
