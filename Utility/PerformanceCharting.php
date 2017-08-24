<?php
namespace Fgms\Bundle\SurveyBundle\Utility;

class PerformanceCharting
{
  public function __construct ()
  {

  }
  private function daysToRange($days)
  {
      $now = new \DateTime('now',$this->getTimezone());
      if ($days === 'yeartodate') {
        $days = 900;
        /*
          $end = $this->getEndOfDay($now);
          $now->setDate(intval($now->format('Y')),1,1);
          return [$this->getBeginningOfDay($now),$end];*/
      }
      if (!is_int($days)) {

        /*
          // This logic needs updating need to remove not found exception with something more gracefull.
          if (!is_numeric($days)) throw $this->createNotFoundException(
              sprintf(
                  '"%s" is not numeric',
                  $days
              )
          );
          $i = intval($days);
          if ($i != floatval($days)) throw $this->createNotFoundException(
              sprintf(
                  '"%s" is not integer',
                  $days
              )
          );
          if ($i < 1) throw $this->createNotFoundException(
              sprintf(
                  'Must retrieve at least one day, %d requested',
                  $i
              )
          );*/
          $days = $i;
          return [];
      }
      return [$this->getBeginningOfPastDay($now,$days),$this->getEndOfDay($now)];
  }
}
