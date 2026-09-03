<?php
declare(strict_types=1);

namespace App\Model\Security;

use App\Model\Repositories\UserRepository;
use Nette\Security\AuthenticationException;
use Nette\Security\Authenticator;
use Nette\Security\Passwords;
use Nette\Security\SimpleIdentity;

final readonly class UserAuthenticator implements Authenticator
{
    private const INVALID_CREDENTIALS_MESSAGE = 'Špatně zadaný e-mail nebo heslo.';

    public function __construct(
        private UserRepository $userRepository,
        private Passwords $passwords,
    ) {
    }

    public function authenticate(string $email, string $password): SimpleIdentity
    {
        $normalizedEmail = strtolower(trim($email));
        $user = $this->userRepository->findByEmail($normalizedEmail);
        if ($user === null || !$user->active) {
            throw new AuthenticationException(self::INVALID_CREDENTIALS_MESSAGE);
        }

        if (!$this->passwords->verify($password, $user->passwordHash)) {
            throw new AuthenticationException(self::INVALID_CREDENTIALS_MESSAGE);
        }

        return new SimpleIdentity(
            id: $user->id,
            roles: [$user->role],
            data: [
                'name' => $user->name,
                'email' => $user->email,
                'publicRole' => $user->role,
                'faculty' => $user->faculty,
                'tags' => $user->tags,
                'attendedEventIds' => $user->attendedEventIds,
                'admin' => $user->admin,
            ],
        );
    }
}
