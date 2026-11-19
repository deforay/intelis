---
description: Point d'entrée de la documentation InteLIS pour le laboratoire, la machine, le dépannage, les aide-mémoires et l'API.
audience: [lab-staff, lab-supervisor, lab-admin, system-admin, requesting-facility, developer]
module: [all]
type: reference
reviewed: 2026-11-19
reviewed_against: 5.8.8
---
# Documentation InteLIS

InteLIS est un système d'information de laboratoire libre pour la charge virale
du VIH, le diagnostic précoce du nourrisson, la tuberculose, les hépatites, la
COVID-19, les CD4 et les tests personnalisés.

InteLIS s'appelait auparavant VLSM. Certains chemins et la base de données
conservent l'ancien nom, et les guides le précisent là où cela compte.

<div class="grid cards" markdown>

-   :material-lifebuoy:{ .lg .middle } __Quelque chose ne va pas__

    ---

    Trouver le problème dans un tableau, au laboratoire ou sur la machine, et
    suivre la page indiquée.

    [:octicons-arrow-right-24: Par où commencer](guides/troubleshooting.md)

    [:octicons-arrow-right-24: Problèmes en utilisant
    InteLIS](guides/troubleshooting.md#problemes-en-utilisant-intelis)

    [:octicons-arrow-right-24: Le site ne s'ouvre
    pas](guides/site-will-not-open.md)

    [:octicons-arrow-right-24: Les mots de ces guides](user-guides/glossary.md)

-   :material-flask-outline:{ .lg .middle } __Utiliser InteLIS__

    ---

    Le travail quotidien au laboratoire : enregistrer les demandes, réceptionner
    et envoyer les manifestes, créer les lots, saisir les résultats, les
    vérifier et les diffuser.

    [:octicons-arrow-right-24: Le parcours d'un échantillon](user-guides/index.md)

    [:octicons-arrow-right-24: Enregistrer une demande de
    test](user-guides/register-a-request.md)

    [:octicons-arrow-right-24: Trouver un
    échantillon](user-guides/signing-in.md#trouver-un-echantillon-qui-nest-ni-dans-un-lot-ni-sur-un-manifeste)

-   :material-server:{ .lg .middle } __Administrer la machine__

    ---

    Installation, mise à jour, sauvegarde, restauration et les scripts de
    maintenance qui gardent une machine de laboratoire en bonne santé.

    [:octicons-arrow-right-24: Mettre à jour
    InteLIS](guides/updating-intelis-on-ubuntu.md)

    [:octicons-arrow-right-24: Installer sur
    Ubuntu](guides/installing-intelis-on-ubuntu.md)

    [:octicons-arrow-right-24: Déménager un laboratoire ou remonter une
    machine perdue](guides/migrating-ubuntu-machines.md)

    [:octicons-arrow-right-24: Connecter un
    instrument](guides/setting-up-interfacing-tool.md)

    [:octicons-arrow-right-24: Configurer les sauvegardes hors
    machine](guides/setting-up-off-machine-backups.md)

-   :material-printer-outline:{ .lg .middle } __Aide-mémoires imprimables__

    ---

    Dix-huit fiches d'une page à imprimer et à afficher : sept pour le poste de
    travail, cinq pour l'administrateur, et six pour la personne qui administre
    la machine.

    [:octicons-arrow-right-24: Fiches pour le
    laboratoire](job-aids/index.md#pour-le-laboratoire)

    [:octicons-arrow-right-24: Fiches pour
    l'administrateur](job-aids/index.md#pour-ladministrateur)

    [:octicons-arrow-right-24: Fiches pour la
    machine](job-aids/index.md#pour-la-machine)

</div>

## Fréquemment consultés

- [Restaurer depuis une sauvegarde](guides/restoring-from-backup.md): remettre les données en place sur une machine où InteLIS fonctionne encore
- [Statuts des échantillons](user-guides/sample-statuses.md): chaque statut et sa signification
- [Scripts de maintenance](guides/maintenance.md): surveillance des services, ressources, db-tools, nettoyage et tâches planifiées

Pour les développeurs : [Architecture](ARCHITECTURE.md), [Standards
d'ingénierie](engineering-standards.md) et la [référence API](api/).
