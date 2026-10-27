<?php
declare(strict_types=1);

namespace Camagru\Services;

use Camagru\Repositories\UserRepository;

final class ActivationService
{
    public function __construct(private UserRepository $users)
    {
    }

	
    // TODO : vérifier le hash du jeton et son expiration, activer le compte
    // puis rendre le jeton inutilisable.
}
