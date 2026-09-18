# Documentation InteLIS

InteLIS est un système d'information de laboratoire libre pour la charge virale
du VIH, le diagnostic précoce du nourrisson, la tuberculose, les hépatites, la
COVID-19, les CD4 et les tests personnalisés.

InteLIS s'appelait auparavant VLSM. Certains chemins et la base de données
conservent l'ancien nom, et les guides le précisent là où cela compte.

<div class="grid cards" markdown>

-   :material-flask-outline:{ .lg .middle } __Utiliser InteLIS__

    ---

    Le travail quotidien au laboratoire : enregistrer les demandes, réceptionner
    et envoyer les manifestes, créer les batchs, saisir les résultats, les
    vérifier et les diffuser.

    [:octicons-arrow-right-24: Le parcours d'un échantillon](user-guides/index.md)

    [:octicons-arrow-right-24: Enregistrer une demande de
    test](user-guides/register-a-request.md)

-   :material-server:{ .lg .middle } __Administrer la machine__

    ---

    Installation, mise à jour, sauvegarde, restauration et les scripts de
    maintenance qui gardent une machine de laboratoire en bonne santé. Ces
    guides sont disponibles en anglais uniquement.

    [:octicons-arrow-right-24: Mettre à jour
    InteLIS](guides/updating-intelis-on-ubuntu.md)

    [:octicons-arrow-right-24: Installer sur
    Ubuntu](guides/installing-intelis-on-ubuntu.md)

    [:octicons-arrow-right-24: Déménager un laboratoire ou remonter une
    machine perdue](guides/migrating-ubuntu-machines.md)

-   :material-lifebuoy:{ .lg .middle } __Quelque chose ne va pas__

    ---

    Commencer sur la machine par `intelis check`, ou `intelis doctor` quand le
    site ne s'ouvre pas. Ces pages traitent les problèmes qu'elles signalent.
    Elles sont disponibles en anglais uniquement.

    [:octicons-arrow-right-24: MySQL ne démarre
    pas](guides/mysql-will-not-start.md)

    [:octicons-arrow-right-24: Le navigateur affiche du code
    PHP](guides/browser-shows-php-code.md)

    [:octicons-arrow-right-24: Corriger une erreur de
    permission](guides/permission-denied-issue.md)

    [:octicons-arrow-right-24: Corriger une incohérence de
    collation](guides/fix-collation-issue.md)

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
