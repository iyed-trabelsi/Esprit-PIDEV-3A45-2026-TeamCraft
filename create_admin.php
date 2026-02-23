<?php
use App\Entity\User;
use App\Entity\Admin;
use Symfony\Component\Dotenv\Dotenv;

require __DIR__.'/vendor/autoload.php';
(new Dotenv())->bootEnv(__DIR__.'/.env');

$kernel = new App\Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();
$container = $kernel->getContainer();

$em = $container->get('doctrine.orm.entity_manager');
$hasher = $container->get('security.user_password_hasher');

// 1. Créer le compte User de base
$user = new User();
$user->setEmail('admin@teamcraft.com');
$user->setUsername('superadmin');
$user->setRoles(['ROLE_ADMIN']);
$user->setPassword($hasher->hashPassword($user, 'Admin123!'));

// 2. Créer le profil Admin lié
$adminProfile = new Admin();
$adminProfile->setUser($user);

$em->persist($user);
$em->persist($adminProfile);
$em->flush();

echo "Compte User et profil Admin créés avec succès !";