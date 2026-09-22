---
description: Constituer un manifeste d'envoi d'échantillons sur le STS, l'imprimer pour le colis, puis le modifier ou le déplacer vers un autre laboratoire.
audience: [requesting-facility, lab-staff]
module: [vl]
type: how-to
reviewed: 2026-09-22
reviewed_against: 5.7.74
---
# Envoyer des échantillons à un laboratoire avec un manifeste

Lister les échantillons d'un colis sur un manifeste, afin que le laboratoire
d'analyse enregistre le colis entier en saisissant un seul code.

Le menu des manifestes n'existe que sur le système central (STS). Un
laboratoire qui dispose de sa propre installation InteLIS (LIS) ne le voit
jamais. Utiliser ce guide sur le STS, dans une structure sanitaire qui envoie
des échantillons ou dans un laboratoire qui réfère des échantillons.

Avant de commencer :

- Enregistrer une demande de test pour chaque échantillon du colis. Voir
  [Enregistrer une demande de test de charge virale](register-a-request.md). Le
  manifeste se compose à partir de demandes qui existent déjà.
- Renseigner sur chaque demande le laboratoire d'analyse destinataire du
  colis.

Les étapes portent sur la charge virale. Pour un autre test, ouvrir le menu du
manifeste dans le groupe de ce test.

| Groupe de menu | Menu du manifeste |
| --- | --- |
| **CHARGE VIRALE DU VIH** | **Manifeste VL** |
| **DIAGNOSTIC PRÉCOCE DU NOURRISSON (EID)** | **Manifeste de l'EID** |
| **TUBERCULOSE** | **Manifeste TB** |
| **COVID-19** | **Manifeste Covid-19** |
| **HEPATITE** | **Manifeste Hépatite** |
| **CD4** | **Manifeste CD4** |
| **AUTRES EXAMENS DE LABORATOIRE** | **Manifeste de test de laboratoire** |

**Choisir la situation qui correspond, puis suivre ses étapes de haut en bas.**

=== "Nouveau colis"

    ### Constituer le manifeste

    1. Aller à **CHARGE VIRALE DU VIH → Gestion des demandes → Manifeste VL**.
    2. Sélectionner **Ajouter un manifeste d'envoi d'échantillons**. Le **Code
       du manifeste** est déjà rempli et ne peut pas être modifié.
    3. Choisir le **Laboratoire d'analyse** destinataire du colis.
    4. Choisir l'**Opérateur/Technicien** qui prépare le colis.
    5. Pour réduire la liste, renseigner **Point de prélèvement de
       l'échantillon**, **Type d'échantillon** ou **Date de prélèvement de
       l'échantillon**.
    6. Sélectionner **Rechercher**. Les échantillons en attente d'envoi
       apparaissent dans la liste de gauche.

        ??? question "Si un échantillon manque dans la liste"

            La liste n'affiche que les demandes qui :

            - ont été enregistrées sur le STS
            - indiquent le laboratoire d'analyse choisi
            - ne figurent pas sur un autre manifeste
            - ne sont pas annulées
            - ont été prélevés dans la période **Date de prélèvement de
              l'échantillon**, préremplie sur les 28 derniers jours. L'élargir
              pour des échantillons plus anciens.

            Ouvrir la demande et vérifier son laboratoire d'analyse. Puis
            sélectionner de nouveau **Rechercher**.

    7. Passer dans la liste de droite chaque échantillon présent dans le colis.
       Utiliser les boutons fléchés situés entre les deux listes. Le champ de
       recherche au-dessus de chaque liste retrouve un ID d'échantillon.
    8. Vérifier que **Nombre d'échantillons sélectionnés** correspond aux tubes
       du colis.

        Ne sélectionner que les échantillons physiquement présents dans le
        colis. Le laboratoire destinataire enregistre comme reçu chaque
        échantillon listé.

    9. Sélectionner **Sauvegarder**. La liste des manifestes s'ouvre.

    ### Imprimer le manifeste

    10. Trouver le manifeste dans la liste. Vérifier son **Laboratoire
        d'analyse** et son **Nombre d'échantillons**.
    11. Sélectionner **Imprimer le manifeste PDF**.
    12. Placer le manifeste imprimé dans le colis. En conserver une copie sur le
        site expéditeur.

    Le laboratoire destinataire saisit le code du manifeste figurant sur cette
    fiche. Voir
    [Réceptionner des échantillons envoyés avec un manifeste](receive-referred-samples.md).

=== "Modifier un manifeste"

    1. Aller à **CHARGE VIRALE DU VIH → Gestion des demandes → Manifeste VL**.
    2. Sélectionner **Edit** sur la ligne du manifeste. Les échantillons
       déjà présents sur le manifeste apparaissent dans la liste de droite.

        ??? failure "Si Edit est grisé"

            Le laboratoire d'analyse a déjà reçu le colis, donc le manifeste ne
            peut plus changer. S'il comporte une erreur, prévenir le laboratoire
            d'analyse. Le laboratoire peut rejeter les échantillons concernés.

        Si un échantillon a été annulé après son ajout au manifeste, l'écran de
        modification l'affiche sous **Les échantillons annulés seront retirés
        de ce manifeste**. L'enregistrement le retire.

        Le **Laboratoire d'analyse** ne peut plus être modifié sur cet écran une
        fois renseigné.

    3. Pour ajouter des échantillons, les passer de la liste de gauche à la
       liste de droite. Pour réduire la liste de gauche, modifier les filtres et
       sélectionner **Rechercher**.
    4. Pour retirer des échantillons, les renvoyer dans la liste de gauche.
    5. Vérifier que **Nombre d'échantillons sélectionnés** correspond aux tubes
       du colis.
    6. Saisir la **Raison du changement de manifeste**. Ce champ est
       obligatoire.
    7. Sélectionner **Submit**.
    8. Sélectionner **Imprimer le manifeste PDF** sur la ligne du manifeste.
    9. Remplacer l'ancien manifeste imprimé du colis par le nouveau.

    ??? info "À propos du champ Statut du manifeste"

        Le **Statut du manifeste** change tout seul et est verrouillé sur cet
        écran :

        | Statut | Réglé quand |
        | --- | --- |
        | **En attente** | Le manifeste est créé. |
        | **Expédié** | Le manifeste est imprimé pour la première fois. |
        | **Reçu** | Le laboratoire d'analyse réceptionne le colis. Le manifeste ne peut plus être modifié ensuite. |

=== "Déplacer des manifestes vers un autre laboratoire"

    À utiliser lorsqu'un laboratoire d'analyse ne peut pas prendre en charge les
    colis qui lui ont déjà été envoyés. **Déplacer le manifeste** dépend d'une
    permission distincte. Si le bouton est absent, s'adresser à
    l'administrateur.

    1. Aller à **CHARGE VIRALE DU VIH → Gestion des demandes → Manifeste VL**.
    2. Sélectionner **Déplacer le manifeste**.
    3. Choisir le laboratoire auquel les manifestes ont été envoyés dans
       **Manifeste du laboratoire d'analyse**.
    4. Pour réduire la liste, renseigner une **Plage de dates**. Elle filtre sur
       la date de création de chaque manifeste.
    5. Sélectionner **Search Manifests**.
    6. Passer dans la liste de droite chaque manifeste à réaffecter.
    7. Choisir le nouveau laboratoire dans **Affectation au laboratoire
       d'analyse**.
    8. Saisir le **Motif du déménagement Manifeste(s)**. C'est la seule trace de
       la raison pour laquelle les échantillons sont partis vers un autre
       laboratoire.
    9. Sélectionner **Save Changes**. La liste des manifestes s'ouvre.
    10. Envoyer les colis physiques au nouveau laboratoire. Le déplacement ne
        modifie que les enregistrements.

    ??? info "Si le premier laboratoire a déjà activé le manifeste"

        Le déplacement efface les ID d'échantillon attribués par le premier
        laboratoire. Le nouveau laboratoire active le manifeste et attribue ses
        propres ID d'échantillon. L'identifiant utilisé par la structure
        expéditrice reste le même.

## Vérifier que tout fonctionne

Le manifeste apparaît dans la liste avec le bon **Laboratoire d'analyse** et le
bon **Nombre d'échantillons**.

Après activation par le laboratoire destinataire, chaque échantillon porte un
ID d'échantillon du laboratoire à côté de l'identifiant utilisé par la
structure.
