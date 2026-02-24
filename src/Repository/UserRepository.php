<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Utilisé pour mettre à jour automatiquement le hachage du mot de passe.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    // --- TES MÉTHODES DE STATISTIQUES (HEAD) ---

    public function countAllUsers(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('count(u.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countActiveUsers(bool $isActive): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('count(u.id)')
            ->where('u.isActive = :status')
            ->setParameter('status', $isActive)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByGender(): array
    {
        $results = $this->createQueryBuilder('u')
            ->select('u.sexe as label, count(u.id) as value')
            ->groupBy('u.sexe')
            ->getQuery()
            ->getResult();

        $stats = ['homme' => 0, 'femme' => 0];
        foreach ($results as $res) {
            $label = strtolower($res['label'] ?? '');
            if (isset($stats[$label])) {
                $stats[$label] = (int) $res['value'];
            }
        }
        return $stats;
    }

    public function countBannedUsers(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('count(u.id)')
            ->innerJoin('u.playerProfile', 'p')
            ->where('p.status = :status')
            ->setParameter('status', 'Banned')
            ->getQuery()
            ->getSingleScalarResult();
    }

    // --- MÉTHODES DES COLLÈGUES (MAIN) ---

    /**
     * @return User[]
     */
    public function findUsersWithRole(string $role): array
    {
        $users = $this->createQueryBuilder('u')
            ->orderBy('u.id', 'ASC')
            ->getQuery()
            ->getResult();
        return array_filter($users, fn (User $u) => \in_array($role, $u->getRoles(), true));
    }
}