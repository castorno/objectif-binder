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

**État actuel :** le lecteur JSON Lines et la commande existent. Le lecteur CSV, la source API, la page d'administration et le scraper sont prévus.

## Lancer un import

```bash
# Vérifier un fichier et voir ce qu'il changerait, sans rien écrire
docker compose exec php php bin/console app:import chemin/vers/cartes.jsonl --dry-run

# Importer
docker compose exec php php bin/console app:import chemin/vers/cartes.jsonl
```

Le chemin est lu **dans le conteneur**. Le dossier `backend/var/import/` du poste y est visible sous `var/import/` et n'est jamais commité : c'est l'endroit où déposer un fichier.

Un exemple entièrement fictif est fourni dans [`examples/import-sample.jsonl`](./examples/import-sample.jsonl).

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
| `attributes` | non | Objet libre de caractéristiques propres au jeu |
| `identities` | non | Liste de ce que la carte représente (voir la vue regroupée) |
| `identities[].externalId` | oui | Identifie l'identité dans son jeu (100) |
| `identities[].name` | oui | Nom de l'identité (200) |
| `identities[].sortOrder` | non | Numéro d'ordre, entier |

Un champ inconnu fait rejeter la ligne : une faute de frappe dans un nom de champ est signalée au lieu d'être ignorée en silence.

**Pourquoi JSON Lines plutôt qu'un tableau JSON :** le fichier se lit ligne par ligne. La mémoire utilisée ne dépend pas de sa taille, et une ligne invalide est rejetée sans perdre les autres.

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
- Une source n'est utilisée que si ses conditions d'utilisation le permettent.
- Les images de cartes ne sont pas importées.

## Limites connues

- **Une requête par carte** pour savoir si elle existe : 20 000 cartes fictives sont traitées en une trentaine de secondes sur un poste de développement. Suffisant pour un import occasionnel ; lire les cartes d'un lot en une seule requête serait la première optimisation.
- **Pas de suppression.** Une carte retirée de la source reste dans le catalogue.
- **Taux d'obtention non importés.** Aucune source envisagée ne les fournit.
- **Un nom par carte.** Les noms dans plusieurs langues ne sont pas gérés.

## Où regarder dans le code

| Sujet | Fichier |
|---|---|
| Commande | `backend/src/Command/ImportCardsCommand.php` |
| Déroulement d'un import | `backend/src/Import/ImportRunner.php` |
| Format d'une fiche et validation | `backend/src/Import/ImportedCardFactory.php` |
| Création et mise à jour | `backend/src/Import/CardImporter.php` |
| Lecture d'un fichier | `backend/src/Import/Reader/` |
| Historique | `backend/src/Entity/ImportRun.php` |
| Tests | `backend/tests/Import/`, `backend/tests/Command/ImportCardsCommandTest.php` |
