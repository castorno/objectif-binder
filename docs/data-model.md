# Modèle de données

Ce document décrit le schéma de base de données initial d'Objectif Binder (Phase 2 — MVP) et les décisions de conception associées.

## Vue d'ensemble

```
Game ──┬── CardSet ──┬── Card ──┬── OwnedCard ──── User
       │             │          │
       ├── Rarity ───┴──────────┴── Favorite ────── User
       │     │
       │     └── PullRate ── CardSet
       │
       └── CardIdentity ══ Card   (plusieurs à plusieurs)
```

- **Game** : un jeu de cartes (Pokémon, Magic, Yu-Gi-Oh...). Racine de la généricité multi-jeux du projet.
- **CardSet** : une extension/set au sein d'un jeu. Nommée `CardSet` (table `card_set`) plutôt que `Set` pour éviter le mot réservé SQL.
- **Rarity** : une rareté, propre à un jeu (les raretés ne sont pas standardisées entre jeux).
- **Card** : une carte, entrée de "checklist" indépendante de la langue d'impression (voir plus bas).
- **CardIdentity** : ce qu'une carte représente au-delà d'une impression donnée (une créature qui revient d'extension en extension, une carte de règles partagée par plusieurs impressions). Sert à regrouper les cartes d'un jeu.
- **PullRate** : probabilité d'obtenir une carte d'une rareté donnée dans un booster d'une extension donnée.
- **User** : un utilisateur de l'application.
- **OwnedCard** : une carte possédée par un utilisateur, trackée par langue.
- **Favorite** : une carte mise en favori/wishlist par un utilisateur (indépendant de la possession).
- **ImportRun** : la trace d'un import du catalogue (fichier, dates, statut, compteurs, premières lignes rejetées). Sans lien avec les autres tables : voir [`import.md`](./import.md).

## Décisions de conception

### Clés primaires : UUID v7

Toutes les entités avec une identité propre (`Game`, `CardSet`, `Rarity`, `Card`, `CardIdentity`, `PullRate`, `User`, `OwnedCard`) utilisent un UUID v7 (`Symfony\Component\Uid\Uuid::v7()`) comme clé primaire, généré côté PHP à la construction de l'objet.

**Pourquoi UUID plutôt qu'un entier auto-incrémenté :**
- Pas d'énumération possible via l'API publique (`/api/cards/1`, `/api/cards/2`...).
- Pas de collision si des données sont fusionnées depuis plusieurs sources d'import (Phase 3).

**Pourquoi v7 plutôt que v4 (aléatoire) :**
- UUIDv7 encode un timestamp dans ses premiers bits, ce qui le rend partiellement ordonné dans le temps : les insertions restent localisées dans l'index B-tree de Postgres, contrairement à un UUIDv4 purement aléatoire qui fragmente l'index.

`Favorite` fait exception : c'est une pure table de liaison sans attribut métier propre, elle utilise une clé composite `(user_id, card_id)`.

### `attributes` en JSON sur `Card`

Les attributs spécifiques à un jeu (types Pokémon, coût de mana Magic...) sont stockés dans une colonne `attributes` (JSON) plutôt que d'avoir une colonne dédiée par attribut possible tous jeux confondus. Évite un schéma qui s'alourdit à chaque nouveau jeu ajouté, au prix de requêtes moins typées sur ces champs (acceptable : ces attributs servent à l'affichage, pas au filtrage principal, qui passe par `Rarity`/`CardSet`/`Game`).

### Une extension peut sortir dans les boosters d'une autre

Certaines extensions n'ont pas de boosters à elles : une galerie de dresseurs ou une collection classique sort dans les boosters d'une extension principale. Les sources les listent pourtant à part, sans dire qu'elles vont ensemble (TCGdex ne donne que leur série commune).

`CardSet.parent` relie une telle extension à sa principale. Un seul niveau : une principale n'a pas elle-même de parente, et les deux sont du même jeu.

**Pourquoi un lien plutôt qu'une fusion à l'import :** les deux extensions numérotent leurs cartes chacune de son côté. « 30ᵉ Anniversaire » et sa collection classique ont toutes deux une carte n° 001, et le numéro doit rester unique dans une extension.

**Pourquoi saisi à la main :** deviner le lien d'après le code de l'extension (`tg`, `gg`, `-c`) reposerait sur une convention de nommage, pas sur une donnée. Il y a une poignée de cas ; un administrateur les renseigne depuis la page « Taux de drop », et l'import n'y touche jamais.

Ce que le lien change, sans code dédié ailleurs :

- filtrer le catalogue sur l'extension principale montre aussi les cartes de ses sous-extensions (la condition est dans `createSearchQueryBuilder`, dont partent la liste, la collection et la complétion) ; la sous-extension reste consultable seule ;
- le filtre des raretés de la principale propose aussi celles de ses sous-extensions ;
- les taux de drop se saisissent sur la principale et couvrent toute la famille : tout sort du même booster. L'API refuse (409) des taux sur une sous-extension ;
- la liste des extensions donne `parentCode`. Le filtre du catalogue ne propose plus les sous-extensions à part, puisque leurs cartes viennent avec la principale ; la page d'administration les liste toujours, en retrait sous leur principale ;
- la fiche d'une carte de sous-extension nomme l'extension principale et y renvoie (`mainSetName`, `mainSetCode`) : c'est de ses boosters que la carte sort.

### Une sous-extension peut donner une seule rareté à toutes ses cartes

Les cartes d'une sous-extension sortent des boosters de la principale à un taux qui leur est propre. Or elles portent souvent les mêmes raretés que les cartes de la principale (« Rare », « Ultra Rare »), et un taux se saisit par rareté : les deux groupes se retrouveraient sur la même ligne.

`CardSet.forcedRarity` nomme la rareté que toutes les cartes de la sous-extension reçoivent, au choix de l'administrateur (« Reprint », « Galerie de Dresseurs »). Elle a alors sa propre ligne dans les taux de la principale, et apparaît telle quelle dans le filtre du catalogue.

- **La rareté est écrite sur les cartes**, pas calculée à la lecture : tout ce qui lit la rareté d'une carte (recherche, filtres, taux) continue de fonctionner sans rien savoir de cette règle.
- **L'import la respecte** : pour une extension qui en a une, il donne cette rareté aux cartes au lieu de celle de la source. Sans cela, chaque réimport déferait le choix.
- **Vider le champ rend la main à la source** : les cartes retrouvent leur rareté d'origine au prochain import de l'extension, pas avant. La rareté d'origine n'est pas gardée ailleurs.
- **Réservé aux sous-extensions** : sur une extension qui a ses propres boosters, une rareté unique effacerait ce qui distingue ses cartes.

Compromis assumé : dans une galerie, les cartes n'ont pas toutes le même taux entre elles. Leur donner une seule rareté les traite comme un seul groupe ; laisser le champ vide garde les raretés de la source.

### `finishes` : une colonne, pas une clé d'`attributes`

Les finitions d'une carte (normale, holographique, reverse) sont dans une colonne `finishes` (liste JSON, valeurs de l'enum `CardFinish`), et non parmi les `attributes`. La différence : `attributes` n'est lu que pour être affiché, alors qu'une règle du code dépend des finitions (le prix d'une version brillante n'est montré que si elle existe, voir [`import.md`](./import.md)). Une règle écrite pour tous les jeux ne doit pas aller chercher une clé qu'une seule source connaît.

`null` veut dire « inconnu », une liste vide « aucune connue » : la règle de prix n'écarte rien quand elle ne sait pas.

Limite : l'enum porte le vocabulaire d'un jeu. Un autre jeu demandera d'y ajouter ses finitions (« foil », par exemple). La finition d'un exemplaire **possédé** n'est pas enregistrée : c'est un sujet à part, lié à la collection.

### Le numéro d'une carte se trie comme on le lit

`number_in_set` est du texte : « 4a », « TG02 », « SWSH001 » existent. Mais trié comme du texte, 10 et 100 passent avant 2.

La colonne porte donc une règle de tri de la base (une *collation* ICU, `natural_sort`) qui compare comme des nombres les chiffres contenus dans un texte : 1, 2, 4a, 9, 10, 100, TG02, TG10. Toute requête qui trie sur cette colonne en profite sans le demander, et aucune colonne de tri supplémentaire n'est à tenir à jour.

La règle ne change que l'ordre, pas l'égalité : « 1 » et « 01 » restent deux numéros distincts pour l'index unique.

Deux conséquences : la base doit être un PostgreSQL compilé avec ICU (c'est le cas des images officielles), et la migration `Version20261008132931` contient une ligne écrite à la main, la création de la collation, que Doctrine ne sait pas générer.

### `Rarity` est une table, pas un enum

Les raretés varient par jeu et doivent rester triables (`sortOrder`) et sans doublons/typos lors d'imports depuis plusieurs sources. Voir aussi `PullRate` ci-dessous, qui s'appuie sur cette normalisation.

### `PullRate` : probabilité de pull, calculée et non stockée

`PullRate` associe un `CardSet` et une `Rarity` à une fréquence : **`cardCount` cartes pour `boosterCount` boosters**.

| Saisie | Signification |
|---|---|
| 4 cartes pour 1 booster | quatre cartes de cette rareté dans chaque booster |
| 1 carte pour 8 boosters | « 1 chance sur 8 » |
| 2 cartes pour 11 boosters | « 1 chance sur 5,5 » |

**Pourquoi pas « 1 chance sur N » :** un booster contient plusieurs communes et peu communes. « 1 sur N » ne sait pas dire plus d'une carte par booster.

**Pourquoi deux entiers plutôt qu'un nombre à virgule :** ce qui est saisi est gardé tel quel. 1 pour 51 ne devient pas 0,0196, et l'écran réaffiche exactement ce qui a été entré.

**Fonctionnalité produit** : permettre de calculer la probabilité d'obtenir une carte précise (que l'utilisateur ne possède pas) en ouvrant un booster d'une extension donnée.

La probabilité pour une carte *spécifique* ne doit **jamais être stockée** : elle est dérivée à la volée par :

```
une chance sur = boosterCount × count(Card WHERE cardSet = X AND rarity = Y) ÷ cardCount
```

- Une rareté « Gold » à 1 pour 51, avec 3 cartes Gold dans l'extension : 1 chance sur 153 pour une carte Gold précise.
- 4 communes par booster, 66 communes dans l'extension : 1 chance sur 16,5 pour une commune précise.
- Plus de cartes par booster que l'extension n'en compte dans la rareté : la carte est dans chaque booster, et le résultat est borné à 1.

Si on stockait cette valeur directement sur `Card`, elle se désynchroniserait dès qu'une carte de la rareté serait ajoutée à l'extension — c'est le même principe de normalisation que pour `Rarity`.

Le calcul suppose qu'un booster ne contient jamais deux fois la même carte, ce qui est la façon dont ils sont composés.

**D'où viennent les taux** : aucune source ne les sert. Les éditeurs publient rarement leurs taux (pour les boosters physiques de Pokémon, jamais), et ce qui circule sont des estimations faites en ouvrant beaucoup de boosters. Un administrateur les saisit donc à la main, extension par extension, depuis la page « Taux de drop » (voir [`authentication.md`](./authentication.md) pour le rôle).

- `source` dit d'où vient le chiffre, `updatedAt` quand il a été saisi : la fiche d'une carte présente la probabilité comme une estimation et cite la source.
- `PUT /api/admin/sets/{id}/pull-rates` reçoit la liste complète des taux de l'extension : ce qui y figure est créé ou modifié, ce qui n'y figure pas est supprimé. Envoyer deux fois la même liste ne change rien.
- Seules les raretés portées par des cartes de l'extension sont proposées, avec le nombre de cartes : c'est lui qui transforme le taux d'une rareté en chance de trouver une carte précise.

Le nombre total de cartes d'un booster n'est pas stocké : c'est la somme des taux de ses raretés, que la page affiche pendant la saisie pour vérifier que les chiffres tombent juste. Les cartes sans rareté dans l'extension (souvent les Énergies de base) n'entrent pas dans ce total.

Limites : le taux ne distingue ni les produits (booster, coffret) ni les emplacements d'un booster (la carte « reverse », qui peut être de n'importe quelle rareté, n'est pas comptée), et le calcul par carte suppose que les cartes d'une rareté sortent aussi souvent les unes que les autres.

### Langue sur `OwnedCard`, pas sur `Card`

`Card` reste agnostique de la langue d'impression (une seule entrée catalogue par numéro dans l'extension). La langue (`language`, code ISO 639-1) est un attribut de **l'exemplaire possédé** (`OwnedCard`), avec une contrainte d'unicité `(user_id, card_id, language)` : un utilisateur peut posséder la même carte en plusieurs langues, chacune avec sa propre quantité.

**Compromis assumé** : `condition` (état de la carte) est partagé pour tous les exemplaires d'une même langue — on ne distingue pas l'état de deux copies FR de la même carte possédées en quantité 2. Modéliser chaque exemplaire physique individuellement serait plus précis mais disproportionné pour le MVP.

### `CardIdentity` : regrouper les cartes par identité

Un catalogue peut afficher une entrée par « identité » plutôt que toutes ses impressions : toutes les cartes d'une même créature, toutes les impressions d'une même carte de règles. `CardIdentity` porte ce regroupement : un jeu, un nom, un identifiant externe unique par jeu (`(game_id, external_id)`, pour dédoublonner à l'import) et un numéro d'ordre optionnel (`sortOrder`) quand le jeu numérote ses identités.

**Pourquoi une entité et pas un regroupement par nom :** deux cartes du même personnage portent souvent des noms différents (« X », « X V », « X ex »). Le regroupement est une donnée fournie par la source d'import, pas une déduction sur le texte.

**Pourquoi une relation plusieurs à plusieurs (table `card_identity_link`) :** certaines cartes représentent plusieurs identités à la fois, et les sources de données le reflètent en fournissant une liste. Une telle carte appartient à chacune de ses identités. Un jeu où chaque carte n'en a qu'une rentre dans le même modèle. Une carte peut aussi n'en avoir aucune (cartes de soutien, ressources).

**Vocabulaire générique :** rien dans le code ne nomme une licence. Ce que le jeu appelle ses identités vient des données, dans `Game.identityLabel` (nul pour un jeu qui n'en a pas).

**Filtrage sans jointure :** la recherche de cartes accepte `identity=<id>` (ou `identity=none` pour les cartes sans identité). La condition est un `EXISTS` (`MEMBER OF` en DQL) et non une jointure : une carte à deux identités sortirait sinon en double, ce qui fausserait la pagination et les comptages. Comme c'est un filtre de la recherche, les cartes possédées, les cartes manquantes et le taux de complétion le suivent sans code supplémentaire.

Une carte ne peut recevoir qu'une identité de son propre jeu (`Card::addIdentity()` le vérifie).

### `condition` : un enum, pas une table

L'état d'un exemplaire possédé (`OwnedCard.condition`) est un enum PHP, `App\Enum\CardCondition`, stocké sous forme de chaîne dans la colonne existante. Il suit l'échelle à sept niveaux du principal marché européen de cartes, du meilleur au pire : `mint`, `near_mint`, `excellent`, `good`, `light_played`, `played`, `poor`. `NULL` signifie « non précisé ».

**Pourquoi un enum alors que `Rarity` est une table :** les raretés varient d'un jeu à l'autre et arrivent par l'import, ce sont des données. L'échelle d'état est fixe, identique pour tous les jeux et connue du code : la faire évoluer est une décision de développement, pas une donnée à importer. Les libellés affichés sont du ressort du frontend.

### Suppression d'un utilisateur

Les clés étrangères `owned_card.user_id` et `favorite.user_id` sont en `ON DELETE CASCADE` : supprimer un compte supprime sa collection et ses favoris, au niveau de la base. Les clés vers `card` restent sans cascade, volontairement : retirer une carte du catalogue ne doit pas effacer silencieusement les collections qui la contiennent.

## Tables

| Table | Clé primaire | Contraintes d'unicité notables |
|---|---|---|
| `game` | UUID | `name`, `slug` |
| `card_set` | UUID | `(game_id, code)` |
| `rarity` | UUID | `(game_id, name)` |
| `card` | UUID | `(card_set_id, number_in_set)` |
| `pull_rate` | UUID | `(card_set_id, rarity_id)` |
| `app_user` | UUID | `email` |
| `owned_card` | UUID | `(user_id, card_id, language)` |
| `favorite` | composite `(user_id, card_id)` | — |
| `card_identity` | UUID | `(game_id, external_id)` |
| `card_identity_link` | composite `(card_id, card_identity_id)` | — |
| `import_run` | UUID | — (index sur `started_at`) |
| `card_price` | UUID | `card_id` |

La migration `Version20261005133546` crée les huit premières tables ; les deux dernières viennent de `Version20261008094943`.

(`User` est mappée sur la table `app_user`, et non `user`, car `USER` est un mot réservé en SQL.)

La migration `Version20261006150255` ajoute la table `refresh_token` (sessions de connexion). Elle est gérée par le paquet de jetons de rafraîchissement, garde un identifiant entier et ne fait pas partie du modèle métier : voir [`authentication.md`](./authentication.md).

La migration `Version20261008082437` passe en `ON DELETE CASCADE` les clés étrangères de `owned_card` et `favorite` vers `app_user`.

La migration `Version20261008094943` ajoute `card_identity`, la table de liaison `card_identity_link` (clé composite `(card_id, card_identity_id)`, suppression en cascade des deux côtés) et la colonne `game.identity_label`.

La migration `Version20261008123842` ajoute `import_run`, l'historique des imports.

La migration `Version20261009160001` ajoute la colonne `card.finishes`.

La migration `Version20261009172452` ajoute `pull_rate.source` et `pull_rate.updated_at` ; une ligne y est écrite à la main, pour dater les taux déjà présents.

La migration `Version20261009181500`, écrite à la main, renomme `pull_rate.odds_one_in` en `booster_count` et ajoute `card_count` (1 pour les taux existants) : un renommage garde les valeurs, là où une migration générée aurait supprimé puis recréé la colonne.

La migration `Version20261009183000` ajoute `card_set.parent_id` (clé vers `card_set`, mise à vide si la parente est supprimée).

La migration `Version20261010082435` ajoute `card_set.forced_rarity_id` (clé vers `rarity`, mise à vide si la rareté est supprimée).

La migration `Version20261008131725` ajoute à `card` les colonnes `image_url` et `large_image_url` : l'adresse d'une image servie par un tiers, jamais l'image elle-même (voir [`import.md`](./import.md)).

La migration `Version20261008154521` ajoute le groupe d'une identité (`card_identity.group_name`, `group_order`) et le nom que le jeu donne à ces groupes (`game.identity_group_label`) : voir [`collection.md`](./collection.md).

La migration `Version20261008155042` ajoute `card_price` : le dernier prix estimé connu d'une carte, une ligne par carte, montants en centimes (voir [`import.md`](./import.md)).
