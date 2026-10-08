# Import du catalogue

Ce document décrit comment les cartes entrent dans le catalogue. Le modèle de données est dans [`data-model.md`](./data-model.md).

## Vue d'ensemble

L'import sépare deux choses : **aller chercher les données** et **les écrire en base**. Entre les deux, un format unique, appelé ici format pivot.

```
Sources                     Format pivot                 Base
───────────────────         ─────────────────            ─────────────────
Fichier JSON Lines ─┐
Fichier CSV        ─┼─► lecteur ─► fiches ─► validation ─► CardImporter ─► PostgreSQL
API publique       ─┤             (une par carte)
Scraper            ─┘
```

L'import ne sait jamais d'où vient une carte. Ajouter une source, c'est écrire un lecteur ; rien d'autre ne change. Tout script externe, dans n'importe quel langage, peut alimenter l'application en produisant un fichier au format pivot.

**État actuel :** les lecteurs JSON Lines et CSV, la commande d'import et une première source en ligne (TCGdex) existent. La page d'administration et le scraper sont prévus.

## Lancer un import

```bash
# Vérifier un fichier et voir ce qu'il changerait, sans rien écrire
docker compose exec php php bin/console app:import chemin/vers/cartes.jsonl --dry-run

# Importer
docker compose exec php php bin/console app:import chemin/vers/cartes.jsonl
```

Le chemin est lu **dans le conteneur**. Le dossier `backend/var/import/` du poste y est visible sous `var/import/` et n'est jamais commité : c'est l'endroit où déposer un fichier.

Le format est choisi d'après l'extension du fichier : `.jsonl` ou `.ndjson`, `.csv`. Le même exemple, entièrement fictif, est fourni dans les deux formats : [`examples/import-sample.jsonl`](./examples/import-sample.jsonl) et [`examples/import-sample.csv`](./examples/import-sample.csv).

La commande affiche le nombre de cartes créées, mises à jour, inchangées et rejetées, avec les premières lignes rejetées et leur raison. Elle échoue (code de sortie non nul) si le fichier est illisible ou si l'import s'arrête sur une erreur ; des lignes rejetées ne sont pas un échec.

## Le format pivot

Un fichier **JSON Lines** (`.jsonl` ou `.ndjson`) : un objet JSON par ligne, encodé en UTF-8, une ligne par carte.

```json
{"game":{"slug":"emberfall","name":"Emberfall","identityLabel":"Créatures"},"set":{"code":"EF1","name":"Premières Braises","releaseDate":"2025-03-01"},"number":"001","name":"Wyrm de braise","rarity":"Rare","externalId":"ef1-001","attributes":{"element":"Feu"},"identities":[{"externalId":"wyrm","name":"Wyrm","sortOrder":1}]}
```

| Champ | Obligatoire | Description |
|---|---|---|
| `game.slug` | oui | Identifie le jeu : minuscules, chiffres et tirets (100 caractères au plus) |
| `game.name` | oui | Nom affiché du jeu (100) |
| `game.identityLabel` | non | Nom que le jeu donne à ses identités, par exemple « Créatures » (50) |
| `set.code` | oui | Identifie l'extension dans son jeu (50) |
| `set.name` | oui | Nom de l'extension (150) |
| `set.releaseDate` | non | Date de sortie, `AAAA-MM-JJ` |
| `number` | oui | Numéro de la carte dans l'extension, **en texte** : `"001"`, `"TG01"` (20) |
| `name` | oui | Nom de la carte (200) |
| `rarity` | non | Nom de la rareté (100) |
| `externalId` | non | Identifiant de la carte dans la source (100) |
| `imageUrl` | non | Adresse `https` d'une image de la carte, servie par un tiers (255) |
| `largeImageUrl` | non | La même image en plus grand, pour la fiche de la carte (255) |
| `attributes` | non | Objet libre de caractéristiques propres au jeu |
| `identities` | non | Liste de ce que la carte représente (voir la vue regroupée) |
| `identities[].externalId` | oui | Identifie l'identité dans son jeu (100) |
| `identities[].name` | oui | Nom de l'identité (200) |
| `identities[].sortOrder` | non | Numéro d'ordre, entier |

Un champ inconnu fait rejeter la ligne : une faute de frappe dans un nom de champ est signalée au lieu d'être ignorée en silence.

**Pourquoi JSON Lines plutôt qu'un tableau JSON :** le fichier se lit ligne par ligne. La mémoire utilisée ne dépend pas de sa taille, et une ligne invalide est rejetée sans perdre les autres.

## Le format CSV

Pour des données saisies dans un tableur. Le fichier décrit les mêmes cartes que le format pivot, à plat : la première ligne nomme les colonnes, chaque ligne suivante est une carte. Le lecteur reconstruit la fiche, et tout ce qui suit (validation, écriture) est commun aux deux formats.

| Colonne | Champ du format pivot |
|---|---|
| `game_slug`, `game_name` | `game.slug`, `game.name` |
| `game_identity_label` | `game.identityLabel` |
| `set_code`, `set_name` | `set.code`, `set.name` |
| `set_release_date` | `set.releaseDate` |
| `number`, `name` | `number`, `name` |
| `rarity`, `external_id` | `rarity`, `externalId` |
| `image_url`, `large_image_url` | `imageUrl`, `largeImageUrl` |
| `identity_ids`, `identity_names`, `identity_sort_orders` | `identities` : une valeur par identité, séparées par `\|` |
| `attribute:<nom>` | `attributes.<nom>`, autant de colonnes que voulu |

Règles :

- les six colonnes `game_slug`, `game_name`, `set_code`, `set_name`, `number` et `name` sont obligatoires ; l'ordre des colonnes est libre ;
- le séparateur est la virgule ou le point-virgule, reconnu d'après la première ligne ;
- le fichier doit être en UTF-8 ; une ligne dans un autre encodage est rejetée plutôt qu'importée avec des accents abîmés ;
- une cellule vide vaut « non renseigné » ;
- une carte à deux identités s'écrit `wyrm|renard` dans `identity_ids` et `Wyrm|Renard` dans `identity_names` ; les colonnes d'identités doivent lister le même nombre de valeurs ;
- une colonne inconnue, répétée ou obligatoire manquante fait refuser le fichier entier, avant toute écriture.

Limites par rapport au JSON Lines : un nom d'identité ne peut pas contenir `|`, et les caractéristiques sont toujours du texte.

**Attention aux tableurs :** ils transforment volontiers `001` en `1`. La colonne `number` doit être formatée en texte.

## Source en ligne : TCGdex

[TCGdex](https://tcgdex.dev) est une base de données ouverte des cartes Pokémon, tenue par des bénévoles et publiée sous licence MIT. C'est la première source de données réelles du projet.

Le téléchargement et l'import sont deux commandes distinctes. La première a besoin du réseau et n'écrit aucune carte ; la seconde écrit les cartes et n'a pas besoin du réseau.

```bash
# 1. Télécharger des extensions : un fichier au format pivot par extension
docker compose exec php php bin/console app:import:fetch-tcgdex --set=base1 --set=swsh3
docker compose exec php php bin/console app:import:fetch-tcgdex --all

# 2. Importer un fichier téléchargé
docker compose exec php php bin/console app:import var/import/tcgdex/swsh3.jsonl
```

Les fichiers arrivent dans `backend/var/import/tcgdex/`. Une extension déjà téléchargée est sautée, sauf avec `--refresh` : relancer la commande après une interruption ne redemande que ce qui manque.

### Ce qui est importé

| Format pivot | Valeur TCGdex |
|---|---|
| `game` | `pokemon`, « Pokémon », identités nommées « Pokédex » |
| `set` | identifiant, nom et date de sortie de l'extension |
| `number`, `name`, `rarity` | `localId`, `name`, `rarity`, en français |
| `externalId` | identifiant de la carte (`swsh3-136`) |
| `attributes` | catégorie, types, points de vie, stade |
| `identities` | une par numéro d'espèce (`dexId`) ; le numéro sert d'ordre |

Les cartes sans numéro d'espèce (Dresseur, Énergie) n'ont pas d'identité et apparaissent sous « Autres cartes » dans la vue regroupée.

### Les images : absentes par défaut

Par défaut, aucune image n'est téléchargée ni référencée, et chaque carte garde son visuel généré.

```bash
docker compose exec php php bin/console app:import:fetch-tcgdex --set=swsh3 --refresh --with-images
```

Avec `--with-images`, chaque fiche reçoit l'**adresse** de l'image de la carte sur les serveurs de TCGdex, en deux tailles. L'application n'en garde que l'adresse : le navigateur du visiteur charge l'image directement chez TCGdex. Rien n'est copié, stocké ni redistribué par le projet, et rien n'entre dans le dépôt.

**À lire avant de l'activer :**

- Les illustrations sont des œuvres de l'éditeur du jeu et de ses illustrateurs. La licence MIT de TCGdex couvre sa base de données ; sa documentation ne dit rien des droits sur les images ni de leur affichage depuis un autre site.
- Chaque image affichée est une requête de plus vers un service bénévole.
- L'option est pensée pour un usage personnel et local. Avant de l'activer sur une instance publique, demander à TCGdex.

Pour les retirer : retélécharger sans l'option (`--refresh`) et réimporter. La source fait foi, les adresses sont effacées.

Côté écran, l'image n'est chargée que lorsqu'elle approche de la zone visible, dans sa petite taille pour les listes. Si elle ne répond pas, le visuel généré reprend sa place.

### Peser le moins possible sur le service

L'API est gratuite et coûte à ceux qui la font tourner. Tout le téléchargement est conçu pour lui envoyer peu de requêtes :

- **Deux requêtes par extension, pas une par carte.** L'API REST ne donne le détail d'une carte qu'une carte à la fois : une extension de 200 cartes coûterait 200 requêtes. L'API GraphQL les renvoie par pages de mille. Le catalogue français entier (environ 200 extensions, 22 000 cartes) demande quelques centaines de requêtes au lieu de 23 000.
- **Une requête à la fois**, avec une pause entre deux : quatre par seconde au plus.
- **Nouvelles tentatives espacées** (1 s, 2 s, 4 s) sur une erreur passagère, ou après le délai que le serveur demande.
- **Arrêt à la première extension en échec**, plutôt que d'insister sur un service en difficulté.
- **Un en-tête `User-Agent`** qui nomme le projet et son dépôt.
- **Rien n'est redemandé** : extensions et noms d'espèces sont gardés sur disque.

Aucune limite de débit n'est documentée par TCGdex ; ces règles sont une précaution, pas une réponse à une contrainte connue.

### Le nom des espèces est déduit

TCGdex donne à une carte le numéro de son espèce, pas le nom de l'espèce. Or le nom d'une carte en dit souvent plus : « Dracaufeu », « Dracaufeu V » et « Dracaufeu VMAX » sont la même espèce.

Le nom retenu pour une espèce est **le plus court parmi les cartes qui ne montrent qu'elle**, toutes extensions confondues. La carte au nom simple existe presque toujours. Cet index (1 025 espèces) est construit une fois, en une vingtaine de requêtes, et gardé dans `_species.json`.

C'est une règle empirique. Une dizaine d'espèces qui n'ont jamais eu de carte au nom simple gardent un suffixe, par exemple « Ixon de Galar ». Le nom se corrige en base, mais un nouvel import le remettra.

### Trois particularités de l'API, constatées et contournées

- Demandées à travers leur extension, les cartes reviennent sans leur détail : elles sont demandées par la liste des cartes.
- Cette liste n'a pas de filtre par extension, seulement sur un fragment de l'identifiant de la carte. Les cartes d'une autre extension que le filtre laisserait passer sont écartées.
- Une réponse GraphQL en erreur porte le code HTTP 200 : le contenu est vérifié, pas seulement le code.

Le nombre de cartes reçues est comparé à celui que TCGdex annonce pour l'extension ; un écart est signalé.

## Décisions de conception

### Créer ou mettre à jour, par clé naturelle

Chaque élément est retrouvé par la clé que la base garantit déjà unique :

| Élément | Clé |
|---|---|
| Jeu | `slug` |
| Extension | jeu + `code` |
| Rareté | jeu + nom |
| Identité | jeu + `externalId` |
| Carte | extension + `number` |

S'il existe, il est mis à jour ; sinon il est créé. Importer deux fois le même fichier ne change donc rien la seconde fois (idempotence).

**Conséquence : la reprise après interruption ne demande aucun mécanisme dédié.** Il suffit de relancer le même fichier. Ce qui est déjà en base est reconnu, le reste est créé.

### La source fait foi pour le catalogue

Le nom, la rareté, les caractéristiques et les identités d'une carte sont remplacés par ceux du fichier. Une identité que le fichier ne cite plus est retirée de la carte.

Deux exceptions, pour les champs facultatifs qui décrivent le jeu ou l'identité et non la carte : `game.identityLabel` et `identities[].sortOrder` ne sont modifiés que s'ils sont présents. Les omettre conserve la valeur en base.

L'import ne supprime jamais de carte : une carte absente du fichier reste dans le catalogue, et les collections qui la contiennent ne sont pas touchées.

### Une ligne invalide ne coûte que cette ligne

Chaque ligne est validée avant toute écriture. Une ligne illisible ou invalide est comptée, écrite dans le journal avec son numéro et ses raisons, puis ignorée. Tous les problèmes d'une ligne sont signalés d'un coup.

Une ligne de plus d'un mégaoctet est rejetée sans être chargée en mémoire.

### Écriture par lots

Les cartes sont envoyées à la base par lots de 200, puis Doctrine oublie les objets du lot. Sans cela, il garderait en mémoire chaque carte lue ou créée jusqu'à la fin.

Entre deux lots, l'import oublie aussi les jeux et extensions qu'il connaissait : il les relit en base au lot suivant. Un test vérifie qu'un catalogue réparti sur plusieurs lots ne crée pas l'extension deux fois.

Si l'import s'arrête sur une erreur, les lots déjà écrits restent et le lot en cours est abandonné.

### Un seul import à la fois

Deux imports simultanés chercheraient la même extension, ne la trouveraient ni l'un ni l'autre, et la créeraient tous les deux. Un verrou (`symfony/lock`) refuse le second.

Le verrou est un fichier local (`LOCK_DSN=flock`) : il protège une machine. Avec plusieurs serveurs, il faudra un verrou partagé, par exemple celui de PostgreSQL.

### L'essai à blanc exécute tout, puis annule

`--dry-run` ne se contente pas de valider le fichier : il fait l'import complet dans une transaction, puis l'annule. Le rapport dit donc exactement ce qu'un vrai import créerait et modifierait.

### Les raretés nouvelles sont classées à la suite

Une source nomme les raretés sans les ordonner. Une rareté inconnue reçoit le rang suivant celles du jeu, dans l'ordre d'apparition. L'ordre peut être corrigé ensuite en base.

### Historique et journal

Chaque import laisse une ligne dans `import_run` : fichier, début, fin, statut, compteurs, cinquante premières lignes rejetées, cause de l'arrêt en cas d'échec. Un essai à blanc n'en laisse pas.

Le détail va dans le journal, sur un canal dédié `import` : `backend/var/log/dev.log` en développement, la sortie d'erreur au format JSON en production.

## Données externes

- Le dépôt ne contient aucune donnée de carte réelle. Les fichiers importés vont dans `var/import/`, ignoré par Git.
- Une source n'est utilisée que si ses conditions d'utilisation le permettent. TCGdex publie sa base sous licence MIT.
- Les noms de jeux, de cartes et d'espèces sont des marques de leurs propriétaires. Ce projet n'est ni produit ni approuvé par eux, pas plus que TCGdex.
- Aucune image de carte n'est copiée ni stockée. Sur demande explicite, l'import en garde l'adresse chez un tiers (voir « Les images »).
- Une adresse d'image doit être en `https` : elle finit dans une balise d'image du navigateur, où rien d'autre n'a sa place.

## Limites connues

- **Une requête par carte** pour savoir si elle existe : 20 000 cartes fictives sont traitées en une trentaine de secondes sur un poste de développement. Suffisant pour un import occasionnel ; lire les cartes d'un lot en une seule requête serait la première optimisation.
- **Pas de suppression.** Une carte retirée de la source reste dans le catalogue.
- **Taux d'obtention non importés.** Aucune source envisagée ne les fournit.
- **Une seule langue.** Les cartes TCGdex sont importées en français ; une carte jamais sortie en français est absente.
- **Tests sans réseau.** Le téléchargement est testé sur des réponses simulées : un changement de l'API de TCGdex ne sera vu qu'en lançant la commande.
- **Un nom par carte.** Les noms dans plusieurs langues ne sont pas gérés.

## Où regarder dans le code

| Sujet | Fichier |
|---|---|
| Commande | `backend/src/Command/ImportCardsCommand.php` |
| Déroulement d'un import | `backend/src/Import/ImportRunner.php` |
| Format d'une fiche et validation | `backend/src/Import/ImportedCardFactory.php` |
| Création et mise à jour | `backend/src/Import/CardImporter.php` |
| Lecture d'un fichier, JSON Lines et CSV | `backend/src/Import/Reader/` |
| Téléchargement depuis TCGdex | `backend/src/Import/Source/Tcgdex/`, `backend/src/Command/FetchTcgdexCommand.php` |
| Réglages réseau (délais, tentatives) | `backend/config/packages/http_client.yaml` |
| Historique | `backend/src/Entity/ImportRun.php` |
| Tests | `backend/tests/Import/`, `backend/tests/Command/ImportCardsCommandTest.php` |
