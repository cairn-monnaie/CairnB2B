<?php

namespace Cairn\UserBundle\Repository;
use Cairn\UserBundle\Entity\User;

use Doctrine\ORM\QueryBuilder;                                                 

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
            ->orderBy('p.identifier','ASC')
            ;

        return $pb->getQuery()->getOneOrNullResult();
    }

    public function findAllPros()
    {
        $pb = $this->createQueryBuilder('p');                  

        $pb->join('p.smsData','s')
            ->join('s.user','u')
            ->andWhere('u.roles LIKE :roles') 
            ->setParameter('roles','%"ROLE_PRO"%')
            ->orderBy('p.identifier','ASC');
            ;

        return $pb->getQuery()->getResult();
    }

    public function whereIdentifier(QueryBuilder $pb, $identifier)
    {
        $pb->andWhere($pb->expr()->like('p.identifier', $pb->expr()->literal($identifier.'%')) );
        return $this;
    }

    public function wherePhoneNumber(QueryBuilder $pb, $phoneNumber)
    {
        $pb->andWhere($pb->expr()->like('p.phoneNumber', $pb->expr()->literal($phoneNumber.'%')) );
        return $this;
    }

    public function whereUser(QueryBuilder $pb, User $user)
    {
        $pb->join('p.smsData','s')
            ->andWhere('s.user = :owner') 
            ->setParameter('owner',$user);
        return $this;
    }

}
