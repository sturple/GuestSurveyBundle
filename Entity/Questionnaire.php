<?php

namespace Fgms\Bundle\SurveyBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Questionnaire
 *
 * @ORM\Table()
 * @ORM\Entity
 */
class Questionnaire
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="createDate", type="datetime")
     */
    private $createDate;

    /**
     * @var string
     *
     * @ORM\Column(name="slug", type="string", length=255)
     */
    private $slug;
    
    /**
     * @var string
     *
     * @ORM\Column(name="sluggroup", type="string", length=255, nullable=true)
     */
    private $sluggroup;
    /**
     * @var string
     *
     * @ORM\Column(name="roomNumber", type="string", length=50)
     */
    private $roomNumber;

    /**
     * @var string
     *
     * @ORM\Column(name="question1", type="string", length=1000, nullable=true)
     */
    private $question1;

    /**
     * @var string
     *
     * @ORM\Column(name="question2", type="string", length=1000, nullable=true)
     */
    private $question2;

    /**
     * @var string
     *
     * @ORM\Column(name="question3", type="string", length=1000, nullable=true)
     */
    private $question3;

    /**
     * @var string
     *
     * @ORM\Column(name="question4", type="string", length=1000, nullable=true)
     */
    private $question4;

    /**
     * @var string
     *
     * @ORM\Column(name="question5", type="string", length=1000, nullable=true)
     */
    private $question5;

    /**
     * @var string
     *
     * @ORM\Column(name="question6", type="string", length=1000, nullable=true)
     */
    private $question6;

    /**
     * @var string
     *
     * @ORM\Column(name="question7", type="string", length=1000, nullable=true)
     */
    private $question7;

    /**
     * @var string
     *
     * @ORM\Column(name="question8", type="string", length=1000, nullable=true)
     */
    private $question8;

    /**
     * @var string
     *
     * @ORM\Column(name="question9", type="string", length=1000, nullable=true)
     */
    private $question9;

    /**
     * @var string
     *
     * @ORM\Column(name="question10", type="string", length=1000, nullable=true)
     */
    private $question10;
    
     /**
     * @var string
     *
     * @ORM\Column(name="question11", type="string", length=1000, nullable=true)
     */
    private $question11;
    
     /**
     * @var string
     *
     * @ORM\Column(name="question12", type="string", length=1000, nullable=true)
     */
    private $question12;	   
  
    /**
     * @var string
     *
     * @ORM\Column(name="question13", type="string", length=1000, nullable=true)
     */
    private $question13;
    /**
     * @var string
     *
     * @ORM\Column(name="question14", type="string", length=1000, nullable=true)
     */
    private $question14;
    /**
     * @var string
     *
     * @ORM\Column(name="question15", type="string", length=1000, nullable=true)
     */
    private $question15;	   

    /**
     * @var string
     *
     * @ORM\Column(name="questionSet", type="text", nullable=true)
     */
    private $questionSet;
	

	
    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set createDate
     *
     * @param \DateTime $createDate
     *
     * @return Questionnaire
     */
    public function setCreateDate()
    {
        $this->createDate =  new \DateTime("now");

        return $this;
    }

    /**
     * Get createDate
     *
     * @return \DateTime
     */
    public function getCreateDate()
    {
        return $this->createDate;
    }

    /**
     * Set property
     *
     * @param string $slug
     *
     * @return Questionnaire
     */
    public function setSlug($slug)
    {
        $this->slug = $slug;

        return $this;
    }
    


    /**
     * Get property
     *
     * @return string
     */
    public function getSlug()
    {
        return $this->slug;
    }
    
    
     /**
     * Set sluggroup
     *
     * @param string $sluggroup
     *
     * @return Questionnaire
     */    
    public function setSluggroup($sluggroup)
    {
        $this->sluggroup = $sluggroup;

        return $this;
    }

    /**
     * Get sluggroup
     *
     * @return string
     */
    public function getSluggroup()
    {
        return $this->sluggroup;
    }    

    /**
     * Set roomNumber
     *
     * @param string $roomNumber
     *
     * @return Questionnaire
     */
    public function setRoomNumber($roomNumber)
    {
        $this->roomNumber = $roomNumber;

        return $this;
    }

    /**
     * Get roomNumber
     *
     * @return string
     */
    public function getRoomNumber()
    {
        return $this->roomNumber;
    }

    /**
     * Set question1
     *
     * @param string $question1
     *
     * @return Questionnaire
     */
    public function setQuestion1($question1)
    {
        $this->question1 = $question1;

        return $this;
    }

    /**
     * Get question1
     *
     * @return string
     */
    public function getQuestion1()
    {
        return $this->question1;
    }

    /**
     * Set question2
     *
     * @param string $question2
     *
     * @return Questionnaire
     */
    public function setQuestion2($question2)
    {
        $this->question2 = $question2;

        return $this;
    }

    /**
     * Get question2
     *
     * @return string
     */
    public function getQuestion2()
    {
        return $this->question2;
    }

    /**
     * Set question3
     *
     * @param string $question3
     *
     * @return Questionnaire
     */
    public function setQuestion3($question3)
    {
        $this->question3 = $question3;

        return $this;
    }

    /**
     * Get question3
     *
     * @return string
     */
    public function getQuestion3()
    {
        return $this->question3;
    }

    /**
     * Set question4
     *
     * @param string $question4
     *
     * @return Questionnaire
     */
    public function setQuestion4($question4)
    {
        $this->question4 = $question4;

        return $this;
    }

    /**
     * Get question4
     *
     * @return string
     */
    public function getQuestion4()
    {
        return $this->question4;
    }

    /**
     * Set question5
     *
     * @param string $question5
     *
     * @return Questionnaire
     */
    public function setQuestion5($question5)
    {
        $this->question5 = $question5;

        return $this;
    }

    /**
     * Get question5
     *
     * @return string
     */
    public function getQuestion5()
    {
        return $this->question5;
    }

    /**
     * Set question6
     *
     * @param string $question6
     *
     * @return Questionnaire
     */
    public function setQuestion6($question6)
    {
        $this->question6 = $question6;

        return $this;
    }

    /**
     * Get question6
     *
     * @return string
     */
    public function getQuestion6()
    {
        return $this->question6;
    }

    /**
     * Set question7
     *
     * @param string $question7
     *
     * @return Questionnaire
     */
    public function setQuestion7($question7)
    {
        $this->question7 = $question7;

        return $this;
    }

    /**
     * Get question7
     *
     * @return string
     */
    public function getQuestion7()
    {
        return $this->question7;
    }

    /**
     * Set question8
     *
     * @param string $question8
     *
     * @return Questionnaire
     */
    public function setQuestion8($question8)
    {
        $this->question8 = $question8;

        return $this;
    }

    /**
     * Get question8
     *
     * @return string
     */
    public function getQuestion8()
    {
        return $this->question8;
    }

    /**
     * Set question9
     *
     * @param string $question9
     *
     * @return Questionnaire
     */
    public function setQuestion9($question9)
    {
        $this->question9 = $question9;

        return $this;
    }

    /**
     * Get question9
     *
     * @return string
     */
    public function getQuestion9()
    {
        return $this->question9;
    }

    /**
     * Set question10
     *
     * @param string $question10
     *
     * @return Questionnaire
     */
    public function setQuestion10($question10)
    {
        $this->question10 = $question10;

        return $this;
    }

    /**
     * Get question10
     *
     * @return string
     */
    public function getQuestion10()
    {
        return $this->question10;
    }

    /**
     * Set question11
     *
     * @param string $question11
     *
     * @return Questionnaire
     */
    public function setQuestion11($question11)
    {
        $this->question11 = $question11;

        return $this;
    }    
    
    /**
     * Get question11
     *
     * @return string
     */
    public function getQuestion11()
    {
        return $this->question11;
    }

    /**
     * Set question12
     *
     * @param string $question12
     *
     * @return Questionnaire
     */
    public function setQuestion12($question12)
    {
        $this->question12 = $question12;

        return $this;
    }

    /**
     * Get question12
     *
     * @return string
     */
    public function getQuestion12()
    {
        return $this->question12;
    }

    /**
     * Set question13
     *
     * @param string $question13
     *
     * @return Questionnaire
     */
    public function setQuestion13($question13)
    {
        $this->question13 = $question13;

        return $this;
    }

    /**
     * Get question13
     *
     * @return string
     */
    public function getQuestion13()
    {
        return $this->question13;
    }

    /**
     * Set question4
     *
     * @param string $question14
     *
     * @return Questionnaire
     */
    public function setQuestion14($question14)
    {
        $this->question14 = $question14;

        return $this;
    }

    /**
     * Get question14
     *
     * @return string
     */
    public function getQuestion14()
    {
        return $this->question14;
    }

    /**
     * Set question15
     *
     * @param string $question15
     *
     * @return Questionnaire
     */
    public function setQuestion15($question15)
    {
        $this->question15 = $question15;

        return $this;
    }

    /**
     * Get question15
     *
     * @return string
     */
    public function getQuestion15()
    {
        return $this->question15;
    }
   
    
    
    
    /**
     * Set questionSet
     *
     * @param string $questionSet
     *
     * @return Questionnaire
     */
    public function setQuestionSet($questionSet)
    {
        $this->questionSet = $questionSet;

        return $this;
    }

    /**
     * Get questionSet
     *
     * @return string
     */
    public function getQuestionSet()
    {
        return $this->questionSet;
    }
	

}
