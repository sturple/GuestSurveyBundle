<?php

namespace Fgms\Bundle\SurveyBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table()
 */
class Testimonial
{
    /**
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;
    /**
     * @ORM\ManyToOne(targetEntity="Questionnaire",inversedBy="testimonials")
     */
    private $questionnaire;
    /**
     * @ORM\Column(type="integer", nullable=false)
     */
    private $question;
    /**
     * @ORM\Column(type="boolean", nullable=false)
     */
    private $approved;
    /**
     * @ORM\Column(type="text", nullable=false)
     */
    private $text;
    /**
     * @ORM\Column(type="string", length=128, nullable=true)
     */
    private $key;

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
     * Set question
     *
     * @param integer $question
     *
     * @return Testimonial
     */
    public function setQuestion($question)
    {
        $this->question = $question;

        return $this;
    }

    /**
     * Get question
     *
     * @return integer
     */
    public function getQuestion()
    {
        return $this->question;
    }

    /**
     * Set approved
     *
     * @param boolean $approved
     *
     * @return Testimonial
     */
    public function setApproved($approved)
    {
        $this->approved = $approved;

        return $this;
    }

    /**
     * Get approved
     *
     * @return boolean
     */
    public function getApproved()
    {
        return $this->approved;
    }

    /**
     * Set text
     *
     * @param string $text
     *
     * @return Testimonial
     */
    public function setText($text)
    {
        $this->text = $text;

        return $this;
    }

    /**
     * Get text
     *
     * @return string
     */
    public function getText()
    {
        return $this->text;
    }

    /**
     * Set key
     *
     * @param string $key
     *
     * @return Testimonial
     */
    public function setKey($key)
    {
        $this->key = $key;

        return $this;
    }

    /**
     * Get key
     *
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * Set questionnaire
     *
     * @param \Fgms\Bundle\SurveyBundle\Entity\Questionnaire $questionnaire
     *
     * @return Testimonial
     */
    public function setQuestionnaire(\Fgms\Bundle\SurveyBundle\Entity\Questionnaire $questionnaire = null)
    {
        $this->questionnaire = $questionnaire;

        return $this;
    }

    /**
     * Get questionnaire
     *
     * @return \Fgms\Bundle\SurveyBundle\Entity\Questionnaire
     */
    public function getQuestionnaire()
    {
        return $this->questionnaire;
    }
}
