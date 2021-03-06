<?php

namespace Cairn\UserBundle\Repository;

use Cairn\UserBundle\Entity\BaseNotification;
use Cairn\UserBundle\Entity\NotificationData;

use Doctrine\ORM\QueryBuilder;                                                 

/**
 * NotificationDataRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class NotificationDataRepository extends \Doctrine\ORM\EntityRepository
{

    public function findByDeviceTokens(array $deviceTokens = [])
    {
        $nb = $this->createQueryBuilder('n');
        $this->whereDeviceTokens($nb,$deviceTokens);

        return $nb->getQuery()->getResult();
    }

    public function whereDeviceTokens(QueryBuilder $nb,$deviceTokens)
    {
        $conditions = array();
        foreach($deviceTokens as $deviceToken){
            $conditions[] = "n.androidDeviceTokens LIKE '%".$deviceToken."%' OR n.iosDeviceTokens LIKE '%".$deviceToken."%'  ";
        }

        $orX = $nb->expr()->orX();
        $orX->addMultiple($conditions);
        $nb->andWhere($orX);

        return $this;
    }

    public function whereNotificationDataIsIn(QueryBuilder $nb,array $notificationDataIds)
    {
        $nb->andWhere('n.id IN (:ids)')
            ->setParameter('ids',$notificationDataIds);

        return $this;
    }

    public function selectTokensWhereAppPushEnabled(QueryBuilder $nb)
    {
        $nb->select('n.deviceTokens')->andWhere('b.appPushEnabled = true');

        return $this;
    }

    public function selectTokensWhereWebPushEnabled(QueryBuilder $nb)
    {
        $nb->addSelect('n.webPushSubscriptions')->andWhere('b.webPushEnabled = true');

        return $this;
    }

    public function selectEmailsEnabled(QueryBuilder $nb)
    {
        $nb->addSelect('u.email')->andWhere('b.emailEnabled = true');

        return $this;
    }


    public function whereKeyword(QueryBuilder $nb, $keyword)
    {
        $nb->andWhere('b INSTANCE OF '.BaseNotification::mapKeywordToClass($keyword));

        return $this;
    }

    public function whereTypes(QueryBuilder $nb, array $types)
    {
        $conditions = array();
        foreach($types as $type){
            $conditions[] = "b.types LIKE '%i:".$type.";%'";
        }

        $orX = $nb->expr()->orX();
        $orX->addMultiple($conditions);
        $nb->andWhere($orX);

        return $this;
    }

    public function whereMinAmountLowerThan(QueryBuilder $nb, $amount)
    {
        $nb->andWhere('b.minAmount <= :minAmount')
            ->setParameter('minAmount',$amount);

        return $this;
    }
    
}
