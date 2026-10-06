<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken as BaseRefreshToken;

/**
 * A session a user can renew their access token with. The columns (hashed
 * token, user identifier, expiry) are inherited from the bundle's mapping.
 */
#[ORM\Entity]
#[ORM\Table(name: 'refresh_token')]
// Listing or revoking a user's sessions looks tokens up by user.
#[ORM\Index(name: 'refresh_token_username_idx', columns: ['username'])]
class RefreshToken extends BaseRefreshToken
{
}
