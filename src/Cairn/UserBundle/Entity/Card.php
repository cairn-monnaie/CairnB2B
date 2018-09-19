<?php

namespace Cairn\UserBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Card
 *
 * @ORM\Table(name="card")
 * @ORM\Entity(repositoryClass="Cairn\UserBundle\Repository\CardRepository")
 * @ORM\HasLifecycleCallbacks()
 */
class Card
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var int
     *
     * @ORM\Column(name="number", type="smallint")
     */
    private $number;

    /**
     * @var text
     *
     * @ORM\Column(name="fields", type="text", length=400,nullable=true)
     */
    private $fields;

    /**
     * @var int
     *
     * @ORM\Column(name="rows", type="smallint")
     */
    private $rows;

    /**
     * @var int
     *
     * @ORM\Column(name="cols", type="smallint")
     */
    private $cols;

    /**
     * @var boolean
     *
     * @ORM\Column(name="enabled", type="boolean")
     */
    private $enabled;

    /**
     *@ORM\OneToOne(targetEntity="Cairn\UserBundle\Entity\User", inversedBy="card")
     *@ORM\JoinColumn(nullable=false)
     */
    private $user;

    /**
     * @ORM\Column(name="creation_date", type="datetime", unique=false, nullable=true)
     */
    private $creationDate;

    /**
     * @var boolean
     *
     * @ORM\Column(name="is_generated", type="boolean")
     */
    private $generated;


    public function __construct($user,$rows,$cols)
    {
        $this->setUser($user);
        $this->setRows($rows);
        $this->setCols($cols);
        $nbCards = $this->getUser()->getNbCards();
        $this->setNumber($nbCards + 1);
        $this->setEnabled(false);
        $this->setGenerated(false);
        $this->creationDate = new \Datetime();
    }

    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set number
     *
     * @param integer $number
     *
     * @return Card
     */
    private function setNumber($number)
    {
        $this->number = $number;

        return $this;
    }

    /**
     * Get number
     *
     * @return int
     */
    public function getNumber()
    {
        return $this->number;
    }

    /**
     * Generates a card with dimensions defined as global parameter
     *
     * Using random values is legit for production environment, but is impossible to use for automatic testing.
     * For this reason, the card keys will depend on the environment
     */
    public function generateCard($env)
    {
//        $this->setCreationDate(new \Datetime());
        $card = array();
        for($row = 0; $row < $this->getRows(); $row++){
            $line = array();
            for($col =0; $col < $this->getCols();$col++){
                if($env == 'test'){
                    $line[] = 1111;
                }
                else{
                    $line[] =  rand(1000,9999);
                }
            }
            $card[] = $line;
        }

        $this->setFields(serialize($card));

    }

    /**
     * Set fields
     *
     * @param string $fields
     *
     * @return Card
     */
    public function setFields($fields)
    {

        $this->fields = $fields;

        return $this;
    }

    /**
     * Get fields
     *
     * @return string
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * Set user
     *
     * @param \Cairn\UserBundle\Entity\User $user
     *
     * @return Card
     */
    public function setUser(\Cairn\UserBundle\Entity\User $user = null)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get user
     *
     * @return \Cairn\UserBundle\Entity\User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Set enabled
     *
     * @param boolean $enabled
     *
     * @return Card
     */
    public function setEnabled($enabled)
    {
        $this->enabled = $enabled;

        return $this;
    }

    /**
     * Get enabled
     *
     * @return boolean
     */
    public function isEnabled()
    {
        return $this->enabled;
    }

    /**
     * Set rows
     *
     * @param integer $rows
     *
     * @return Card
     */
    public function setRows($rows)
    {
        $this->rows = $rows;

        return $this;
    }

    /**
     * Get rows
     *
     * @return integer
     */
    public function getRows()
    {
        return $this->rows;
    }

    /**
     * Set cols
     *
     * @param integer $cols
     *
     * @return Card
     */
    public function setCols($cols)
    {
        $this->cols = $cols;

        return $this;
    }

    /**
     * Get cols
     *
     * @return integer
     */
    public function getCols()
    {
        return $this->cols;
    }

    /**
     * Get creationDate
     *
     * @return \DateTime
     */
    public function getCreationDate()
    {
        return $this->creationDate;
    }

    /**
     * Set creationDate
     *
     * @param \DateTime $creationDate
     *
     * @return Card
     */
    public function setCreationDate($creationDate)
    {
        $this->creationDate = $creationDate;

        return $this;
    }

    /**
     * Get enabled
     *
     * @return boolean
     */
    public function getEnabled()
    {
        return $this->enabled;
    }

    /**
     * Set generated
     *
     * @param boolean $generated
     *
     * @return Card
     */
    public function setGenerated($generated)
    {
        $this->generated = $generated;

        return $this;
    }

    /**
     * Get generated
     *
     * @return boolean
     */
    public function isGenerated()
    {
        return $this->generated;
    }
}
