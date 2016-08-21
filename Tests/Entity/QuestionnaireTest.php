<?php

namespace Fgms\Bundle\SurveyBundle\Tests\Entity;

class QuestionnaireTest extends \PHPUnit_Framework_TestCase
{
    private $q;
    private $td;

    protected function setUp()
    {
        $this->q = new \Fgms\Bundle\SurveyBundle\Entity\Questionnaire();
        $r = new \ReflectionClass($this->q);
        $this->td = $r->getProperty('testimonialData');
        $this->td->setAccessible(true);
    }

    public function testGetTestimonialDataBadJson()
    {
        $this->td->setValue($this->q,'foo');
        $this->expectException(\RuntimeException::class);
        $this->q->getTestimonialData();
    }

    public function testGetTestimonialDataNonArray()
    {
        $this->td->setValue($this->q,'{}');
        $this->expectException(\RuntimeException::class);
        $this->q->getTestimonialData();
    }

    public function testGetTestimonialDataNull()
    {
        $this->assertSame(null,$this->q->getTestimonialData());
    }

    public function testGetTestimonialDataArray()
    {
        $this->td->setValue($this->q,'[]');
        $val = $this->q->getTestimonialData();
        $this->assertTrue(is_array($val));
        $this->assertSame(0,count($val));
    }

    public function testSetTestimonialDataNull()
    {
        $this->td->setValue($this->q,'[]');
        $this->q->setTestimonialData(null);
        $this->assertSame(null,$this->td->getValue($this->q));
    }

    public function testSetTestimonialDataArray()
    {
        $this->q->setTestimonialData([]);
        $this->assertSame('[]',$this->td->getValue($this->q));
    }

    public function testSetTestimonialDataString()
    {
        $this->q->setTestimonialData('[]');
        $this->assertSame('[]',$this->td->getValue($this->q));
    }

    public function testSetTestimonialDataStringBad()
    {
        $this->expectException(\RuntimeException::class);
        $this->q->setTestimonialData('foo');
    }

    public function testSetTestimonialDataWrongType()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->q->setTestimonialData(new \stdClass());
    }
}