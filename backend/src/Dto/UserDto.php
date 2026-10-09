<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\User;

/**
 * What the API tells a user about their own account. Deliberately a short
 * allow-list: the password hash and the list of roles never leave the server
 * this way. Whether the user is an administrator does, so that the interface
 * can show them the way to the administration; the API checks the role itself.
 */
final readonly class UserDto implements \JsonSerializable
{
    public function __construct(
        public string $id,
        public string $email,
        public bool $isAdmin,
    ) {
    }

    public static function fromEntity(User $user): self
    {
        return new self(
            id: (string) $user->getId(),
            email: $user->getEmail(),
            isAdmin: \in_array(User::ROLE_ADMIN, $user->getRoles(), true),
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
            'isAdmin' => $this->isAdmin,
        ];
    }
}
