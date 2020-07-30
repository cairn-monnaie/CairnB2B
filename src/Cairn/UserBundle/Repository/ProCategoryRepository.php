<?php

namespace Cairn\UserBundle\Repository;

/**
 * ProCategoryRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class ProCategoryRepository extends \Doctrine\ORM\EntityRepository
{
    public function findUserCategoriesIds($user)
    {
        $query = $this->_em->createQuery(sprintf('SELECT pc.id FROM CairnUserBundle:User u INNER JOIN u.proCategories pc WHERE u.id=%d',$user->getID()));
        $results = $query->getResult();

        return array_column($results,'id');
    }
}