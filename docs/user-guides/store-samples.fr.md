# Enregistrer le stockage d'un échantillon

Enregistrer la position d'un échantillon dans le congélateur permet de retrouver
le tube plus tard, sans ouvrir chaque boîte. Cela compte surtout pour les
échantillons conservés en vue d'un retest, d'un test de confirmation ou d'une
revue qualité.

## Avant de commencer

- Des échantillons enregistrés dans InteLIS
- Le congélateur, le coffret, la boîte et la position où le tube est placé
- La permission de consulter les demandes de test

Les noms de congélateurs proviennent d'une liste tenue par l'administrateur. Si
un congélateur est absent de la liste, demander à l'administrateur de l'ajouter
sous **ADMIN → Configuration du système → Stockage en laboratoire**.

## Ouvrir la page de stockage

1. Aller à **CHARGE VIRALE DU VIH → Gestion des demandes → Afficher les demandes
   de test**.
2. Sélectionner **Stockage des échantillons** en haut à droite de la liste.

## Enregistrer une position

1. Choisir le laboratoire et le **Congélateur**.
2. Filtrer les échantillons à stocker, avec **Date de prélèvement de
   l'échantillon**, **Date de réception de l'échantillon au labo** ou **Nom de la
   structure**.
3. Sélectionner **Obtenir des échantillons**.
4. Pour chaque échantillon, renseigner les informations de stockage.

| Champ | À saisir |
|---|---|
| Coffret | Le coffret dans le congélateur. Obligatoire |
| Boîte | La boîte dans le coffret. Obligatoire |
| Position | La position dans la boîte. Obligatoire |
| Volume (ml) | Le volume stocké. Obligatoire, et supérieur à zéro |
| Date de sortie | La date de sortie de l'échantillon, renseignée au retrait |
| Commentaires | Tout ce qui aide à retrouver ou interpréter l'échantillon |

5. Sélectionner **Sauvegarder**.

!!! warning "Chaque champ obligatoire doit être rempli, sur chaque ligne"
    Une ligne n'est enregistrée que si le congélateur, le coffret, la boîte, la
    position et un volume supérieur à zéro sont tous renseignés. Une ligne à
    laquelle il en manque un est ignorée sans avertissement : la page annonce
    quand même la réussite, et le tube se retrouve sans position enregistrée.
    Après l'enregistrement, vérifier que chaque échantillon affiche bien sa
    position.

Enregistrer la position au moment où le tube entre dans le congélateur. Une
position notée plus tard, de mémoire, est celle où le tube devait aller, pas
nécessairement celle où il se trouve.

## Enregistrer plusieurs échantillons à la fois

Lorsqu'une boîte entière est rangée en une fois, utiliser **Stockage
Téléchargement en masse**. Le bouton n'apparaît que sur le formulaire de demande
de la RDC ; les laboratoires utilisant un autre formulaire pays enregistrent les
positions ligne par ligne avec les étapes ci-dessus.

1. Sélectionner **Stockage Téléchargement en masse**.
2. Sélectionner **Télécharger le format Excel** et remplir le fichier obtenu.
   Conserver ses colonnes telles quelles, y compris la colonne du volume, qui est
   obligatoire au même titre que sur le formulaire.
3. Sélectionner **Charger le fichier**, choisir le fichier rempli, puis
   sélectionner **Envoyer**.
4. Vérifier que les échantillons affichent bien leur position avant de remettre
   la boîte au congélateur.

## Enregistrer la sortie d'un échantillon

1. Retrouver la ligne de l'échantillon dans **Congélateur/Stockage**.
2. Sélectionner **Supprimer** sur cette ligne.
3. Choisir le motif du retrait.
4. Confirmer.

Le statut de l'échantillon devient Retiré et il quitte le stockage courant, tout
en conservant son historique : une recherche ultérieure indique donc où il était
et quand il en est sorti.

Renseigner **Date de sortie** puis enregistrer ne retire pas l'échantillon. Cela
ajoute une ligne d'historique de stockage et laisse l'échantillon à sa position,
si bien que le plan du congélateur continue d'indiquer le tube comme présent.

## Retrouver un échantillon stocké

1. Aller à **CHARGE VIRALE DU VIH → Gestion → Rapports sur les congélateurs et le
   stockage**.
2. Filtrer par congélateur, par date ou par structure.

Le rapport donne la position actuelle de chaque échantillon et son historique de
stockage. L'exporter vers un tableur pour un inventaire par rapport au
congélateur physique.

## Vérifier que tout fonctionne

Rechercher l'échantillon sur la page Stockage des échantillons. **Current
Storage** affiche le congélateur, le coffret, la boîte et la position
enregistrés.
