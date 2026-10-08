<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\User;

/**
 * What the API tells a user about their own account. Deliberately a short
 * allow-list: the password hash and roles never leave the server this way.
 */
final readonly class UserDto implements \JsonSerializable
{
    public function __construct(
        public string $id,
        public string $email,
    ) {
    }

    public static function fromEntity(User $user): self
    {
        return new self(
            id: (string) $user->getId(),
            email: $user->getEmail(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
        ];
    }
}
