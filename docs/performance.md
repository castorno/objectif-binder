# Performance

Ce document garde la trace d'une mesure faite sur le catalogue réel, de ce qu'elle a appris et de ce qu'elle a permis de ne pas faire. La règle suivie : **mesurer avant d'optimiser**.

## Ce qui a été mesuré

Le 10 octobre 2026, sur un poste de développement (Docker sous WSL, PostgreSQL 16, Symfony en mode `dev`) :

- le catalogue français complet : 20 030 cartes, 184 extensions, 1 025 espèces ;
- un compte possédant 400 cartes, pour les routes de la collection ;
- chaque route appelée trois fois ; le chiffre retenu est la médiane.

Les temps de réponse sont pessimistes : le mode `dev` ajoute le profileur et ne met pas le conteneur en cache. Les temps SQL, eux, ne dépendent pas du mode.

### Méthode

1. Faire journaliser chaque requête SQL avec sa durée, le temps de la mesure (`log_min_duration_statement = 0`), puis remettre le réglage d'origine.
2. Appeler les routes une à une, en posant un repère dans le journal avant chacune, pour rattacher les requêtes à leur route.
3. Pour les requêtes les plus lentes, demander son plan à la base (`EXPLAIN (ANALYZE, BUFFERS)`).

Aucun outil à installer : tout vient de PostgreSQL et de `curl`.

## Résultats

Après la correction décrite plus bas :

| Route | Réponse | Dont SQL | Requêtes SQL |
|---|---|---|---|
| Catalogue, page 1 | 33 ms | 22 ms | 2 |
| Catalogue, page 500 | 51 ms | 41 ms | 2 |
| Recherche par nom (« pika », 206 cartes) | 17 ms | 10 ms | 2 |
| Recherche très large (« a », 13 418 cartes) | 28 ms | 20 ms | 2 |
| Filtre par extension | 8 ms | 2 ms | 2 |
| Filtre par rareté | 15 ms | 9 ms | 2 |
| Cartes d'une espèce | 11 ms | 6 ms | 2 |
| Fiche d'une carte | 6 ms | 1 ms | 6 |
| Vue regroupée | 27 ms | 20 ms | 5 |
| Complétion d'une recherche | 30 ms | 20 ms | 5 |
| Cartes manquantes, page 1 | 29 ms | 19 ms | 3 |

Le nombre de requêtes par page est fixe : il ne dépend ni de la taille de la page ni de celle du catalogue (un test le vérifie pour le catalogue, voir [`collection.md`](./collection.md)).

## Ce que la mesure a appris

### L'optimisation attendue n'était pas nécessaire

La recherche par nom utilise `LIKE '%texte%'`, qu'un index ordinaire ne sait pas servir. L'idée de départ était d'ajouter un index trigramme (`pg_trgm`).

La mesure : PostgreSQL parcourt les 20 000 cartes en 4 ms. Une première mesure, le 8 octobre 2026, l'avait déjà constaté (voir les limites connues de [`collection.md`](./collection.md)) ; celle-ci le confirme. L'index n'a pas été ajouté. Il deviendrait utile avec un catalogue dix à cent fois plus grand ; d'ici là, ce serait une extension de plus à installer et un index de plus à maintenir, pour rien de mesurable.

### Le vrai coût était ailleurs : compter

Chaque page du catalogue affiche le nombre total de cartes trouvées. Pour l'obtenir, l'outil de pagination de Doctrine enveloppait par défaut la requête entière, tri compris :

```sql
SELECT COUNT(*) FROM (SELECT DISTINCT id FROM (… la requête de la page, avec son ORDER BY …))
```

La base triait donc tout le catalogue, puis le dédoublonnait, pour seulement le compter : 35 à 45 ms, soit plus de la moitié du temps SQL de la page. Rien dans le code ne le laissait deviner, la requête étant générée.

La correction tient en une ligne (`Paginator::setUseOutputWalkers(false)`), qui fait compter directement :

```sql
SELECT count(DISTINCT c.id) FROM card c … WHERE …
```

| Route | Avant | Après |
|---|---|---|
| Catalogue, page 1 | 57 ms | 33 ms |
| Catalogue, page 500 | 88 ms | 51 ms |
| Cartes manquantes, page 1 | 64 ms | 29 ms |
| Le comptage seul | 35 ms | 4 ms |

Les totaux affichés sont identiques avant et après.

## Ce qui reste, et pourquoi on n'y touche pas

- **Les pages lointaines coûtent leur tri.** À la page 500, la base trie les 20 000 lignes complètes, et le tri déborde sur le disque (5 Mo). 40 ms, pour une page que personne n'atteint en feuilletant. La parade connue est de trier les identifiants seuls, puis de charger les vingt cartes : à faire si le catalogue grossit.
- **L'ordre du catalogue ne peut pas s'appuyer sur un index** : il porte sur deux tables (date de sortie de l'extension, puis numéro de la carte dans un ordre « naturel »). Le rendre indexable demanderait de recopier la date de l'extension sur chaque carte.
- **Le décompte des cartes sans identité** de la vue regroupée prend 14 ms ; il pourrait s'écrire avec un `NOT EXISTS`.
- **Sept index font double emploi** : Doctrine en crée un par clé étrangère, alors qu'un index unique commençant par la même colonne le couvre déjà. Sans effet mesurable à cette taille, et les retirer reviendrait à lutter contre l'outil à chaque migration.

## Quand refaire la mesure

- le catalogue dépasse quelques centaines de milliers de cartes (un second jeu importé, par exemple) ;
- une route nouvelle trie ou compte sur tout le catalogue ;
- une page se met à répondre en plus de 200 ms.
