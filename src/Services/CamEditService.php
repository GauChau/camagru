<?php
declare(strict_types=1);

namespace Camagru\Services;

use Camagru\Repositories\UserRepository;

final class CamEditService
{
    public function __construct(private UserRepository $users)
    {
    }

    // public function uploadImage()
}
