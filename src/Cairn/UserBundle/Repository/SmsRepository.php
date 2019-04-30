<?php

namespace Cairn\UserBundle\Repository;

use Cairn\UserBundle\Entity\Sms;
use Doctrine\ORM\QueryBuilder;                                                 

/**
 * SmsRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class SmsRepository extends \Doctrine\ORM\EntityRepository
{

    public function wherePhoneNumbers(QueryBuilder $sb, $phoneNumbers)
    {
        $sb->andWhere( $sb->expr()->in('s.phoneNumber', $phoneNumbers));
        return $this;
    }

    public function whereState(QueryBuilder $sb, $state)
    {
        $sb->andWhere('s.state = :state')                                           
            ->setParameter('state',$state);
        return $this;
    }

    public function whereOlderThan(QueryBuilder $sb, $date)
    {
        $sb->andWhere('s.sentAt < :beforeDate')
            ->setParameter('beforeDate',$date);
        return $this;
    }

    public function whereMoreRecentThan(QueryBuilder $sb, $date)
    {
        $sb->andWhere('s.sentAt > :afterDate')
            ->setParameter('afterDate',$date);
        return $this;
    }

    public function whereContentContains(QueryBuilder $sb, $content)
    {
        $sb->andWhere($sb->expr()->like('s.content', $sb->expr()->literal($content.'%')) );
        return $this;
    }

    public function whereCurrentDay(QueryBuilder $sb)
    {
        $sb->andWhere('s.sentAt BETWEEN :start AND :end')
            ->setParameter('start', new \Datetime(date('Y-m-d'))) // 00:00:00
            ->setParameter('end', new \Datetime()) //now
            ;
        return $this;
    }

    public function getNumberOfSmsToday($phoneNumber,$state,$content = NULL)
    {
        $sb = $this->createQueryBuilder('s');
        $this->whereCurrentDay($sb)
            ->wherePhoneNumbers($sb,$phoneNumber)
            ->whereState($sb, $state);
        if($content){
            $this->whereContentContains($sb,$content);
        }

        return $sb->select('count(s.id)')->getQuery()->getSingleScalarResult();
    }

}
