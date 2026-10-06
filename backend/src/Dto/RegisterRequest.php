<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body of POST /api/auth/register. Only these two fields exist: anything else
 * a client sends (roles, id...) has nowhere to land.
 */
final readonly class RegisterRequest
{
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    public string $email;

    public function __construct(
        string $email,
        // No composition rules (digits, symbols...): length and estimated
        // strength say more about a password than its character classes.
        // The upper bound keeps hashing cost predictable.
        #[Assert\NotBlank]
        #[Assert\Length(min: 10, max: 128)]
        #[Assert\PasswordStrength(minScore: Assert\PasswordStrength::STRENGTH_MEDIUM)]
        #[\SensitiveParameter]
        public string $password,
    ) {
        // An e-mail identifies one account whatever its letter case.
        $this->email = mb_strtolower(trim($email));
    }
}
