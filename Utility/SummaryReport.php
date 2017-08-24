<?php
namespace Fgms\Bundle\SurveyBundle\Utility;

class SummaryReport
{
  var $em, $param, $slug, $group, $timezone;
  /**
  * @param $slug string
  * @param $group string
  **/
  public function __construct ($em, $param, $slug, $group)
  {
    // the controller is used for doctrine, but needs to be fixed using a better way.
    $this->em = $em;
    $this->param = $param;
    $this->slug = $slug;
    $this->group = $group;
    $this->timezone = empty($param['property']) ? 'America/Halifax' : $this->getValue('timezone',$param['property'],'America/Halifax');
  }

  /**
  * Gets stats, depending on $dayFlag either gets email ($dayFlag == true) or gets 7, 30 and year to date stats.
  * @param $dayFlag boolean
  */
  public function get_stats($dayFlag=false)
  {
      $statsCount = array();
      if ($dayFlag){
          $survey24Hours =  $this->getSurveyRollup('24 HOUR');
          $statsCount = array('day'=>$this->getValue('count',$survey24Hours));
      }
      else {
          $survey7    = $this->getSurveyRollup('7 DAY');
          $survey30   = $this->getSurveyRollup('30 DAY');
          $surveyYear = $this->getSurveyRollup('1 YEAR');
          $statsCount = [
            'last7'       => $this->getValue('count',$survey7),
            'last30'      => $this->getValue('count',$survey30),
            'yeartodate'  => $this->getValue('count',$surveyYear)
          ];
      }
      $stats = array();
      // setting up stats.
      foreach($this->param['questions'] as $allquestion){
          $active = isset($allquestion['active']) ? $allquestion['active'] : true;
          $field = $allquestion['field'];
          $emailtrigger = 'none';
          if ($this->getValue('email',$allquestion) !== false){
              $emailtrigger = $this->getValue('trigger', $allquestion['email'], 'none');
          }
          $type = $allquestion['type'];
          $array = [
            'field'         => str_replace('question','Q',$field),
            'active'        => $active,
            'type'          => $type,
            'question'      => strip_tags($allquestion['title']),
            'negative'      => $this->getValue('negative',$allquestion,false),
            'show'          => (strlen($field) < 4),
            'trigger'       => $this->getValue('trigger',$allquestion),
            'emailtrigger'  => $emailtrigger
          ];

          // add last 24 hour stat
          if ($dayFlag){
              $array['day'] = $this->getValue($field.'M',$survey24Hours);
          }
          // add last 7 , last 30 and year to date stats
          else {
              $array = array_merge($array,[
                  'last7'       => $this->getValue($field.'M',$survey7),
                  'last30'      => $this->getValue($field.'M',$survey30),
                  'yeartodate'  => $this->getValue($field.'M',$surveyYear),
              ]);
          }
          $stats[] = $array;
      }
      return [
        'stats'       => $stats,
        'statCounts'  => $statsCount
      ];

  }


  /*
   * Gets Survey Rollup with specific interval
   *
   * @param string $property this is property slug
   * @param string $timeInterval is the time interval for the last x time
   * @param array $allquestions is an array of all the questions
   */
  private function getSurveyRollup($timeInterval="7 DAY")
  {
    $slug = $this->slug;
    $group = $this->group;

    $time = new \DateTime('now', new \DateTimeZone($this->timezone));
    $timezoneOffset = intval($time->format('O'))/100;

    //$timeInterval .= " $timezoneOffset HOURS";

    $sql = "SELECT createDate, count(*) as `count`,  ";
    $s = array();
    foreach ($this->param['questions'] as $question){
        $type = strtolower($this->getValue('type',$question,'open'));
        $field = $question['field'];
        if ($type == 'rating'){
            $s[] = "AVG(s.{$field}) as `{$field}M`";
        }
        else if ($type == 'polar'){
            $negativeFlag =  $this->getValue('negative',$question,false);
            $yesText = $negativeFlag ? 'No' : 'Yes';
            $noText = $negativeFlag ? 'Yes' : 'No' ;
            $s[] = "(sum(if(s.{$field}='{$yesText}',1,0))/(sum(if(s.{$field}='{$yesText}',1,0))+sum(if(s.{$field}='{$noText}',1,0)))*100 ) as `{$field}M`";
        }
        else if ($type == 'open'){
            $s[] = "(sum(IF (LENGTH(s.{$field}) > 2, 1,0))) as `{$field}M`";
        }
    }
    $sql .= implode(', ',$s) .' ';
    $sql .= "FROM questionnaire s  WHERE s.createDate > (NOW()  - INTERVAL {$timeInterval}) AND s.slug = '{$slug}'";
    if ($group != false){
        $sql .= " AND s.sluggroup = '{$group}'";
    }
    return $this->em->getConnection()->fetchAssoc($sql);
  }

  /**
   *
   * @param string $key
   * @param array $array
   * @param mixed $default
   * @return mixed
   */
  private function getValue($key, $array=array(), $default=false)
  {
      if (isset($array)){
          if (isset($array[$key])){
              return $array[$key];
          }
      }
      return $default;
  }

}
