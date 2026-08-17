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

-   :material-server:{ .lg .middle } __Administrer la machine__

    ---

    Installation, mise à jour, sauvegarde, restauration et les scripts de
    maintenance qui gardent une machine de laboratoire en bonne santé. Ces
    guides sont disponibles en anglais uniquement.

    [:octicons-arrow-right-24: Installer sur Ubuntu](guides/installing-intelis-on-ubuntu.md)

-   :material-printer-outline:{ .lg .middle } __Aide-mémoires imprimables__

    ---

    Treize fiches d'une page à imprimer et à afficher : sept pour le poste de
    travail, six pour la personne qui administre la machine.

    [:octicons-arrow-right-24: Ouvrir les aide-mémoires](job-aids/index.md)

-   :material-code-braces:{ .lg .middle } __Référence__

    ---

    Le trajet d'une requête dans le code, le niveau exigé d'une modification et
    la documentation API interactive.

    [:octicons-arrow-right-24: Architecture](ARCHITECTURE.md)

</div>

## Fréquemment consultés

- [Mettre à jour InteLIS](guides/updating-intelis-on-ubuntu.md) — une seule commande, sur une machine déjà en service
- [Restaurer depuis une sauvegarde](guides/restoring-from-backup.md) — remettre les données en place, ou reconstruire une machine hors service
- [Statuts des échantillons](user-guides/sample-statuses.md) — chaque statut et sa signification
- [Scripts de maintenance](guides/maintenance.md) — surveillance des services, ressources, db-tools, nettoyage et tâches planifiées
- [Référence API](api/) — la documentation OpenAPI interactive
