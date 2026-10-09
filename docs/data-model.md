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

`PullRate` associe un `CardSet` et une `Rarity` à une probabilité `oddsOneIn` ("1 chance sur N" d'obtenir une carte de cette rareté dans un booster de cette extension).

**Fonctionnalité produit** : permettre de calculer la probabilité d'obtenir une carte précise (que l'utilisateur ne possède pas) en ouvrant un booster d'une extension donnée.

La probabilité pour une carte *spécifique* ne doit **jamais être stockée** : elle est dérivée à la volée par :

```
odds_carte_specifique = pullRate.oddsOneIn × count(Card WHERE cardSet = X AND rarity = Y)
```

Exemple : une rareté "Gold" à 1/51 par booster, avec 3 cartes Gold différentes dans l'extension → 1/(51×3) = 1/153 pour une carte Gold précise. Si on stockait cette valeur directement sur `Card`, elle se désynchroniserait dès qu'une 4ᵉ carte Gold serait ajoutée à l'extension — c'est le même principe de normalisation que pour `Rarity`.

Vérifié de bout en bout (création Game/CardSet/Rarity/3×Card/PullRate + calcul) lors de la mise en place du schéma initial.

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

La migration `Version20261008131725` ajoute à `card` les colonnes `image_url` et `large_image_url` : l'adresse d'une image servie par un tiers, jamais l'image elle-même (voir [`import.md`](./import.md)).

La migration `Version20261008154521` ajoute le groupe d'une identité (`card_identity.group_name`, `group_order`) et le nom que le jeu donne à ces groupes (`game.identity_group_label`) : voir [`collection.md`](./collection.md).

La migration `Version20261008155042` ajoute `card_price` : le dernier prix estimé connu d'une carte, une ligne par carte, montants en centimes (voir [`import.md`](./import.md)).
