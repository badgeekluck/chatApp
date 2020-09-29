<?php

namespace App\Repository;

use App\Entity\Message;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Message|null find($id, $lockMode = null, $lockVersion = null)
 * @method Message|null findOneBy(array $criteria, array $orderBy = null)
 * @method Message[]    findAll()
 * @method Message[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    /**
     * @param int|null $conversationId
     * @return int|mixed|string
     */
    public function findMessageByConversationId(?int $conversationId)
    {
        $qb = $this->createQueryBuilder('m');
        $qb
            ->where('m.conservations = :conversationId')
            ->setParameter('conversationId', $conversationId)
        ;

        return $qb->getQuery()->getResult();
    }
}
