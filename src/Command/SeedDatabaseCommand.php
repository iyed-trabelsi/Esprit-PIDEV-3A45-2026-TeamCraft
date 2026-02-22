<?php

namespace App\Command;

use App\Entity\Admin;
use App\Entity\Comment;
use App\Entity\CompetitiveRank;
use App\Entity\Manager;
use App\Entity\Offer;
use App\Entity\Player;
use App\Entity\Post;
use App\Entity\Postulation;
use App\Entity\Rubrique;
use App\Entity\Team;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:seed', description: 'Seeds the database with test data.')]
class SeedDatabaseCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Seeding Users and Profiles...');

        // 1. Admin
        $adminUser = new User();
        $adminUser->setEmail('admin@teamcraft.com')
            ->setUsername('superadmin')
            ->setPseudo('TheAdmin')
            ->setName('System Administrator')
            ->setUserType('admin')
            ->setIsActive(true)
            ->setPassword($this->passwordHasher->hashPassword($adminUser, 'Admin123!'))
            ->setRoles(['ROLE_ADMIN']);
        $adminProfile = new Admin();
        $adminProfile->setUser($adminUser);
        $this->entityManager->persist($adminUser);
        $this->entityManager->persist($adminProfile);

        // 2. Managers
        $managers = [];
        for ($i = 1; $i <= 3; $i++) {
            $user = new User();
            $user->setEmail("manager$i@teamcraft.com")
                ->setUsername("manager$i")
                ->setPseudo("Mgr_$i")
                ->setName("Manager Number $i")
                ->setUserType('manager')
                ->setIsActive(true)
                ->setPassword($this->passwordHasher->hashPassword($user, 'Password123!'))
                ->setRoles(['ROLE_USER']);
            
            $managerProfile = new Manager();
            $managerProfile->setUser($user)
                ->setOrganizationName("Pro Team $i Org");
            
            $this->entityManager->persist($user);
            $this->entityManager->persist($managerProfile);
            $managers[] = $user;
        }

        // 3. Players
        $players = [];
        $games_list = ['League of Legends', 'Valorant', 'Counter-Strike 2', 'Overwatch 2'];
        $ranks_list = ['Gold', 'Platinum', 'Diamond', 'Master', 'Challenger'];

        for ($i = 1; $i <= 10; $i++) {
            $user = new User();
            $user->setEmail("player$i@teamcraft.com")
                ->setUsername("player$i")
                ->setPseudo("ProPlayer_$i")
                ->setName("Player Name $i")
                ->setUserType('player')
                ->setIsActive(true)
                ->setPassword($this->passwordHasher->hashPassword($user, 'Password123!'))
                ->setRoles(['ROLE_USER']);
            
            $playerProfile = new Player();
            $playerProfile->setUser($user)
                ->setGame($games_list[array_rand($games_list)])
                ->setGameRank($ranks_list[array_rand($ranks_list)])
                ->setRole('Fullstack Player')
                ->setRegion('Europe')
                ->setStatus('Looking for Team')
                ->setSelectedGames([$games_list[0], $games_list[1]]);
            
            // Add a competitive rank
            $compRank = new CompetitiveRank();
            $compRank->setPlayer($playerProfile)
                ->setGame($playerProfile->getGame())
                ->setPrincipalRole('Captain')
                ->setSkillLevel($playerProfile->getGameRank())
                ->setExperience('3 years')
                ->setAvailability('Every evening')
                ->setRegion('EU West');
            
            $this->entityManager->persist($user);
            $this->entityManager->persist($playerProfile);
            $this->entityManager->persist($compRank);
            $players[] = $user;
        }

        // 4. Teams and Offers
        $output->writeln('Seeding Teams and Offers...');
        $teams = [];
        foreach ($managers as $index => $manager) {
            $team = new Team();
            $team->setName("Team Alpha " . ($index + 1))
                ->setLogo("https://via.placeholder.com/150")
                ->setGames(['League of Legends', 'Valorant'])
                ->setOwner($manager)
                ->setCreatedAt(new \DateTimeImmutable());
            
            $this->entityManager->persist($team);
            $teams[] = $team;

            // Create 2 offers per team
            for ($j = 1; $j <= 2; $j++) {
                $offer = new Offer();
                $offer->setTitle("Recruiting " . $games_list[array_rand($games_list)] . " Player")
                    ->setDateCreation(new \DateTime())
                    ->setDateExpiration((new \DateTime())->modify('+30 days'))
                    ->setDescription("We are looking for a dedicated player to join our squad. Requirements: " . $ranks_list[array_rand($ranks_list)] . " rank.")
                    ->setGame($games_list[array_rand($games_list)])
                    ->setRole("Strategic Leader")
                    ->setRank($ranks_list[array_rand($ranks_list)])
                    ->setNbPlayerRecruited(0)
                    ->setTeam($team);
                
                $this->entityManager->persist($offer);
                
                // Random postulations from players
                $numPostulations = rand(1, 3);
                $shuffledPlayers = $players;
                shuffle($shuffledPlayers);
                for ($p = 0; $p < $numPostulations; $p++) {
                    $postulation = new Postulation();
                    $postulation->setUser($shuffledPlayers[$p])
                        ->setOffer($offer)
                        ->setStatus('pending')
                        ->setMessage("Hi, I am interested in this position. Put me in coach!")
                        ->setCreatedAt(new \DateTimeImmutable());
                    $this->entityManager->persist($postulation);
                }
            }
        }

        // 5. Forum (Rubriques, Posts, Comments)
        $output->writeln('Seeding Forum data...');
        $rubriques_names = ['General Discussion', 'Game Strategy', 'Team Recruitment', 'Technical Support'];
        foreach ($rubriques_names as $rName) {
            $rubrique = new Rubrique();
            $rubrique->setNomRubrique($rName)
                ->setDescription("This is the $rName section of our community.")
                ->setTopic($rName)
                ->setAuteur($adminUser)
                ->setEtat('active')
                ->setNbPosts(2);
            
            $this->entityManager->persist($rubrique);

            for ($k = 1; $k <= 2; $k++) {
                $post = new Post();
                $post->setTitre("Interesting topic about $rName #$k")
                    ->setContenu("Detailed content for the post in $rName. This is where users share their thoughts.")
                    ->setTypePost('discussion')
                    ->setAuteur($players[array_rand($players)])
                    ->setRubrique($rubrique)
                    ->setNbVues(rand(10, 100))
                    ->setNbLikes(rand(1, 20))
                    ->setStatut('published');
                
                $this->entityManager->persist($post);

                // Add comments
                for ($c = 1; $c <= 3; $c++) {
                    $comment = new Comment();
                    $comment->setContenu("Comment text $c for the post above.")
                        ->setAuteur($players[array_rand($players)])
                        ->setPost($post)
                        ->setNbLikes(rand(0, 5));
                    $this->entityManager->persist($comment);
                }
            }
        }

        $output->writeln('Flushing to database...');
        $this->entityManager->flush();

        $output->writeln('Database seeded successfully!');
        $output->writeln('Admin: admin@teamcraft.com / Admin123!');
        $output->writeln('Manager: manager1@teamcraft.com / Password123!');
        $output->writeln('Player: player1@teamcraft.com / Password123!');

        return Command::SUCCESS;
    }
}
