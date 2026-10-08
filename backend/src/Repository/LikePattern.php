<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * Builds the pattern of a SQL LIKE from text typed by a user.
 */
final class LikePattern
{
    /**
     * Matches the values containing this text, whatever its letter case; the
     * column must be compared in lower case too.
     *
     * "%" and "_" mean "anything" in a LIKE. Left as they are, searching for
     * "_" would match every row instead of the names holding an underscore:
     * they are escaped with a backslash, PostgreSQL's default escape character.
     */
    public static function containing(string $text): string
    {
        return '%'.addcslashes(mb_strtolower($text), '%_\\').'%';
    }
}
