<?php

namespace Cairn\UserBundle\Repository;

use Cairn\UserBundle\Entity\User;

/**
 * SmsDataRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class SmsDataRepository extends \Doctrine\ORM\EntityRepository
{

    public function findByUser(User $user, $phoneNumber)
    {
        $sb = $this->createQueryBuilder('s');                  

        $sb->andWhere('s.user = :owner') 
            ->andWhere('s.phoneNumber = :phoneNumber')
            ->setParameter('owner',$user)
            ->setParameter('phoneNumber',$phoneNumber)
            ;

        return $sb->getQuery()->getOneOrNullResult();
    }

}
