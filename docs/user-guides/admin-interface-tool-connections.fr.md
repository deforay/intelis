---
description: Générer un code de connexion à usage unique pour relier, reconnecter ou révoquer une installation de l'outil d'interface d'un laboratoire.
audience: [lab-admin, system-admin]
module: [all]
type: how-to
reviewed: 2026-09-22
reviewed_against: 5.7.74
---
# Connecter l'outil d'interface avec un code de connexion

Relier une installation de l'outil d'interface à un laboratoire d'analyse, pour
que les résultats de ses automates parviennent à InteLIS. Chaque ordinateur de
laboratoire qui fait tourner l'outil se connecte une fois.

Pour installer l'outil et y ajouter l'automate, voir
[Connecter un instrument à InteLIS](../guides/setting-up-interfacing-tool.md).

## Avant de commencer

- Sur le STS, un compte avec le rôle Admin intégré ou un compte auquel le
  laboratoire d'analyse est rattaché. Sur une installation autonome, un compte
  auquel le laboratoire d'analyse est rattaché
- Le laboratoire d'analyse créé sous **ADMIN → Structures sanitaires**
- L'outil d'interface installé sur l'ordinateur du laboratoire
- Le paramètre **Interface API Enabled** activé. Aucune page n'affiche ce
  paramètre. Le support InteLIS l'active. D'ici là, le panneau **Connexions des
  outils d'interface** manque sur tous les laboratoires

??? info "Sur un LIS"

    La liste des structures d'un LIS n'a pas de bouton **Modifier** : le panneau
    n'est donc pas accessible depuis le menu. Contacter le support InteLIS.

**Choisir la situation, puis suivre ses étapes de haut en bas.**

=== "Première connexion"

    1. Aller à **ADMIN → Structures sanitaires**.
    2. Sélectionner **Modifier** sur le laboratoire d'analyse.
    3. Descendre jusqu'à **Connexions des outils d'interface**.
    4. Sélectionner **Générer un code de connexion**.
    5. Dans l'outil d'interface de l'ordinateur du laboratoire, saisir l'**URL
       InteLIS** affichée sur la page. **Copier** place la valeur dans le
       presse-papiers.
    6. Saisir les trois groupes du **Code de connexion** dans l'outil
       d'interface.

        ??? info "Le code ne sert qu'une fois"

            Le code ne s'affiche qu'une fois et ne sert qu'une fois. Il expire
            après 30 minutes, quand le compte à rebours **Expire dans** atteint
            00:00. S'il expire, sélectionner de nouveau **Générer un code de
            connexion**.

            La page ne garde qu'un code à la fois. Pour en créer un autre avant
            l'expiration, sélectionner d'abord **Code d'annulation**. Recharger
            la page masque le code sans l'annuler.

    7. Recharger la page. L'installation apparaît sous **Installations
       connectées**.

=== "Reconnecter ou réinstaller"

    À utiliser lorsque l'ordinateur du laboratoire est reconstruit, ou que
    l'outil est réinstallé.

    1. Aller à **ADMIN → Structures sanitaires**.
    2. Sélectionner **Modifier** sur le laboratoire d'analyse.
    3. Descendre jusqu'à **Connexions des outils d'interface**.
    4. Sous **Installations connectées**, trouver l'installation par son **Nom
       d'affichage**.
    5. Sélectionner **Se reconnecter / Réinstaller**. Un nouveau code apparaît.
    6. Dans l'outil d'interface de l'ordinateur du laboratoire, saisir l'**URL
       InteLIS** et les trois groupes du code.
    7. Recharger la page. L'installation montre une **Dernière connexion**
       récente.

    ??? info "Connecter cet outil"

        Une installation marquée **Signalement via l'importateur** envoie des
        résultats sans être encore connectée. Son bouton s'intitule **Connecter
        cet outil**. Suivre les mêmes étapes avec ce bouton.

=== "Révoquer"

    À utiliser lorsqu'un ordinateur du laboratoire est retiré ou perdu.

    1. Aller à **ADMIN → Structures sanitaires**.
    2. Sélectionner **Modifier** sur le laboratoire d'analyse.
    3. Descendre jusqu'à **Connexions des outils d'interface**.
    4. Sous **Installations connectées**, trouver l'installation par son **Nom
       d'affichage**.
    5. Sélectionner **Révoquer**.
    6. Confirmer l'invite.

    L'installation révoquée ne peut plus envoyer de résultats. Les autres
    installations du laboratoire ne sont pas touchées. Pour rétablir une
    installation révoquée, utiliser **Se reconnecter / Réinstaller** sur sa
    ligne.

## Vérifier que tout fonctionne

| Contrôle | Attendu |
| --- | --- |
| Installations connectées | L'installation est listée, avec une **Dernière connexion** récente |
| Résultats | Un résultat passé sur l'automate apparaît dans InteLIS |

Lorsque les résultats cessent d'arriver, une **Dernière connexion** ancienne
signifie que l'outil n'atteint pas InteLIS. Voir aussi **Activité des machines
d'interface** dans
[Surveillance et audit](admin-monitoring.md#verifier-quun-automate-envoie-toujours).
