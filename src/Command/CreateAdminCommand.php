<?php

namespace App\Command;

use App\Entity\User;
use App\Entity\Admin;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:create-admin')]
class CreateAdminCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // 1. Créer le User
        $user = new User();
        $user->setEmail('admin@teamcraft.com');
        $user->setUsername('superadmin');
        $user->setPseudo('superadmin');
        $user->setUserType('admin');

        $user->setName('Admin TeamCraft');
        $user->setIsActive(true);
        $user->setRoles(['ROLE_ADMIN']);
        $user->setPassword($this->passwordHasher->hashPassword($user, 'Admin123!'));

        // 2. Créer le profil Admin lié
        $adminProfile = new Admin();
        $adminProfile->setUser($user);

        $this->entityManager->persist($user);
        $this->entityManager->persist($adminProfile);
        $this->entityManager->flush();

        $output->writeln('Compte User et profil Admin créés avec succès !');

        return Command::SUCCESS;
    }
}