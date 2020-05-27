<?php
/**
 * Created by PhpStorm.
 * User: Grigor
 * Date: 5/27/20
 * Time: 4:17 PM
 */

namespace App\Command;

use App\Entity\AccountContentUnit;
use App\Entity\AccountFile;
use App\Entity\ContentUnit;
use App\Entity\File;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ConvertToMultiAuthorCommand extends Command
{
    use LockableTrait;

    protected static $defaultName = 'state:convert-to-multi-author';

    /** @var EntityManager $em */
    private $em;

    /** @var SymfonyStyle $io */
    private $io;


    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct();

        $this->em = $em;
    }

    protected function configure()
    {
        $this->setDescription('Replace OneToMany relation with ManyToMany for account owned files');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);

        // DISABLE SQL LOGGING, CAUSE IT CAUSES MEMORY SHORTAGE on large inserts
        $this->em->getConnection()->getConfiguration()->setSQLLogger(null);
        $this->io = new SymfonyStyle($input, $output);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$this->lock()) {
            $output->writeln('The command is already running in another process.');

            return 0;
        }

        /**
         * @var File[] $files
         */
        $files = $this->em->getRepository(File::class)->findAll();
        if ($files) {
            foreach ($files as $file) {
                $fileAuthor = $file->getAuthor();

                $accountFile = new AccountFile();
                $accountFile->setFile($file);
                $accountFile->setAccount($fileAuthor);
                $accountFile->setSigned(true);
                $this->em->persist($accountFile);
            }

            $this->em->flush();
        }

        /**
         * @var ContentUnit[] $contentUnits
         */
        $contentUnits = $this->em->getRepository(ContentUnit::class)->findAll();
        if ($contentUnits) {
            foreach ($contentUnits as $contentUnit) {
                $contentUnitAuthor = $contentUnit->getAuthor();

                $accountContentUnit = new AccountContentUnit();
                $accountContentUnit->setContentUnit($contentUnit);
                $accountContentUnit->setAccount($contentUnitAuthor);
                $accountContentUnit->setSigned(true);
                $this->em->persist($accountContentUnit);
            }

            $this->em->flush();
        }

        $this->io->success('Done');
        $this->release();

        return null;
    }
}