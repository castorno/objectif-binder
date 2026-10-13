<?php

declare(strict_types=1);

namespace App\Doctrine;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

/**
 * JSON_CONTAINS_TEXT(list, text): whether a JSON list of texts holds one.
 * The ORM's query language knows nothing about JSON; this hands the question
 * to PostgreSQL. In a condition, compare it to TRUE.
 *
 * Reads every row it is asked about: fine for a catalog of tens of thousands
 * of cards, and a JSONB column with a GIN index is the next step if not.
 */
final class JsonContainsText extends FunctionNode
{
    private Node $list;

    private Node $text;

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);
        $this->list = $parser->StringPrimary();
        $parser->match(TokenType::T_COMMA);
        $this->text = $parser->StringPrimary();
        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker): string
    {
        // jsonb_exists() is what the "?" operator calls: written this way,
        // the question mark is not mistaken for a parameter.
        return sprintf('jsonb_exists(CAST(%s AS jsonb), %s)', $this->list->dispatch($sqlWalker), $this->text->dispatch($sqlWalker));
    }
}
