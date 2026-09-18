# Réceptionner des échantillons envoyés avec un manifeste

Enregistrer tous les échantillons d'un colis arrivé avec un manifeste, en
saisissant une seule fois le code du manifeste.

Le menu **Ajouter des échantillons à partir du manifeste** apparaît sur
l'installation InteLIS propre au laboratoire (LIS). Sur le système central
(STS), il n'apparaît que pour les utilisateurs dont le rôle a le type d'accès
**Laboratoire d'analyse**. Les utilisateurs des structures sanitaires ne le
voient jamais.

Avant de commencer :

- Le colis et le manifeste imprimé qu'il contient
- La date et l'heure d'arrivée du colis au laboratoire

Les étapes portent sur la charge virale. Pour un autre test, ouvrir **Ajouter
des échantillons à partir du manifeste** dans le menu **Gestion des demandes**
de ce test.

**Choisir la situation qui correspond, puis suivre ses étapes de haut en bas.**

=== "Tous les tubes sont arrivés"

    1. Compter les tubes par rapport au manifeste imprimé.
    2. Aller à **CHARGE VIRALE DU VIH → Gestion des demandes → Ajouter des
       échantillons à partir du manifeste**.
    3. Saisir ou scanner le code du manifeste dans **Entrer le code manifeste
       d'échantillons**.
    4. Sélectionner **Envoyer**. Les échantillons du manifeste apparaissent dans
       le tableau.

        ??? failure "Si un message apparaît à la place des échantillons"

            | Message | Cause | Que faire |
            | --- | --- | --- |
            | Veuillez saisir l'exemple de code de manifeste | Le champ du code est vide | Saisir le code, puis sélectionner **Envoyer** |
            | Aucun manifeste n'a été trouvé avec ce code … | Aucun manifeste portant ce code n'a été envoyé à ce laboratoire | Vérifier chaque caractère du code. Demander à l'expéditeur quel laboratoire indique le manifeste |
            | Manifeste … est enregistré auprès d'un autre laboratoire d'analyse et ne peut pas être activé ici | Le manifeste indique un autre laboratoire | Demander à l'expéditeur de déplacer le manifeste vers ce laboratoire. Voir [Déplacer des manifestes vers un autre laboratoire](send-samples-on-a-manifest.md) |
            | Impossible de récupérer les échantillons pour le manifeste … Veuillez réessayer ou contacter le service d'assistance | Le LIS n'a pas pu récupérer le manifeste depuis le STS | Vérifier la connexion internet, puis sélectionner de nouveau **Envoyer** |
            | Impossible de synchroniser le manifeste … | Le STS n'a pas répondu | Sélectionner de nouveau **Envoyer**. Si le message se répète, contacter l'assistance |

            Le tableau affiche **Veuillez saisir un code manifeste valide pour
            l'activation** tant qu'aucun manifeste n'est chargé. Ce n'est pas
            une erreur.

    5. Vérifier que le nombre de lignes correspond aux tubes présents sur la
       paillasse.
    6. Régler **Date de réception de l'échantillon au labo** sur la date et
       l'heure d'arrivée du colis.
    7. Sélectionner **Activer les échantillons**. Le message **Des échantillons
       de ce manifeste ont été activés** apparaît.

        ??? warning "Activer un manifeste une seule fois"

            Activer de nouveau le même manifeste écrase la date de réception de
            tous ses échantillons, et efface la date de test des échantillons
            déjà testés.

    8. Ajouter les échantillons à un batch. Voir
       [Créer un batch pour le test](batch-samples.md).

=== "Tubes manquants ou endommagés"

    1. Compter les tubes par rapport au manifeste imprimé. Noter les ID
       d'échantillon des tubes manquants ou endommagés.
    2. Aller à **CHARGE VIRALE DU VIH → Gestion des demandes → Ajouter des
       échantillons à partir du manifeste**.
    3. Saisir ou scanner le code du manifeste dans **Entrer le code manifeste
       d'échantillons**.
    4. Sélectionner **Envoyer**. Les échantillons du manifeste apparaissent dans
       le tableau.

        ??? failure "Si un message apparaît à la place des échantillons"

            | Message | Cause | Que faire |
            | --- | --- | --- |
            | Veuillez saisir l'exemple de code de manifeste | Le champ du code est vide | Saisir le code, puis sélectionner **Envoyer** |
            | Aucun manifeste n'a été trouvé avec ce code … | Aucun manifeste portant ce code n'a été envoyé à ce laboratoire | Vérifier chaque caractère du code. Demander à l'expéditeur quel laboratoire indique le manifeste |
            | Manifeste … est enregistré auprès d'un autre laboratoire d'analyse et ne peut pas être activé ici | Le manifeste indique un autre laboratoire | Demander à l'expéditeur de déplacer le manifeste vers ce laboratoire. Voir [Déplacer des manifestes vers un autre laboratoire](send-samples-on-a-manifest.md) |
            | Impossible de récupérer les échantillons pour le manifeste … Veuillez réessayer ou contacter le service d'assistance | Le LIS n'a pas pu récupérer le manifeste depuis le STS | Vérifier la connexion internet, puis sélectionner de nouveau **Envoyer** |
            | Impossible de synchroniser le manifeste … | Le STS n'a pas répondu | Sélectionner de nouveau **Envoyer**. Si le message se répète, contacter l'assistance |

            Le tableau affiche **Veuillez saisir un code manifeste valide pour
            l'activation** tant qu'aucun manifeste n'est chargé. Ce n'est pas
            une erreur.

    5. Régler **Date de réception de l'échantillon au labo** sur la date et
       l'heure d'arrivée du colis.
    6. Sélectionner **Activer les échantillons**. L'activation couvre tous les
       échantillons du manifeste, y compris les manquants. Le message **Des
       échantillons de ce manifeste ont été activés** apparaît.

        ??? warning "Activer un manifeste une seule fois"

            Activer de nouveau le même manifeste écrase la date de réception de
            tous ses échantillons, et efface la date de test des échantillons
            déjà testés.

    7. Rejeter chaque échantillon manquant ou endommagé sur sa propre fiche.
       Voir [Gérer les échecs et les échantillons en attente](failed-and-held-samples.md).
    8. Indiquer à l'expéditeur les échantillons rejetés, afin qu'il prélève de
       nouveau.
    9. Ajouter les autres échantillons à un batch. Voir
       [Créer un batch pour le test](batch-samples.md).

## Vérifier que tout fonctionne

1. Aller à **CHARGE VIRALE DU VIH → Gestion des demandes → Afficher les
   demandes de test**.
2. Filtrer sur le code du manifeste.

Chaque échantillon du colis a un ID d'échantillon et le statut **Échantillon
enregistré au laboratoire d'analyse**.

??? info "Ce que fait l'activation"

    L'activation attribue un ID d'échantillon du laboratoire à chaque
    échantillon du manifeste qui n'en a pas encore. Elle enregistre la date de
    réception et fait passer chaque échantillon au statut **Échantillon
    enregistré au laboratoire d'analyse**. Jusque-là, les échantillons ne
    portent que l'identifiant utilisé par la structure expéditrice et ne sont
    pas prêts pour le test.

    | Colonne | Signification |
    | --- | --- |
    | **ID de l'échantillon** | L'identifiant attribué par ce laboratoire, utilisé sur l'automate et sur le rapport |
    | **ID de l'échantillon à distance** | L'identifiant utilisé par la structure expéditrice, conservé pour lui permettre de suivre l'échantillon |

    Sur un LIS, **Envoyer** récupère le manifeste depuis le STS lorsqu'il n'est
    pas encore parvenu au laboratoire. Cela nécessite une connexion internet.
