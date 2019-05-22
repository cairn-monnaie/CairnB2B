<?php

namespace Cairn\UserBundle\Repository;
use Cairn\UserBundle\Entity\User;

/**
 * FileRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class PhoneRepository extends \Doctrine\ORM\EntityRepository
{
    public function findByUser(User $user, $phoneNumber)
    {
        $pb = $this->createQueryBuilder('p');                  

        $pb->join('p.smsData','s')
            ->andWhere('s.user = :owner') 
            ->andWhere('p.phoneNumber = :phoneNumber')
            ->setParameter('owner',$user)
            ->setParameter('phoneNumber',$phoneNumber)
            ;

        return $pb->getQuery()->getOneOrNullResult();
    }

}
