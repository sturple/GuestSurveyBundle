<?php

namespace Fgms\Bundle\SurveyBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use \Fgms\Bundle\SurveyBundle\Entity\Questionnaire;
use \Fgms\Bundle\SurveyBundle\Entity\Feedback;
use \Fgms\Bundle\SurveyBundle\Form\FeedbackType;
use Doctrine\ORM\Query\ResultSetMapping;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Config\Loader\FileLoader;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
class DefaultController extends Controller
{
  var $config = array();
  var $logger = false;
  var $param = array();
  var $request = null;
  var $summary_report =null;
  var $charting = null;
  var $feedback = null;

  /**
   * route to show home page
   *
   */
  public function indexAction()
  {
      return $this->render('FgmsSurveyBundle:Default:index.html.twig', array());
  }

  /**
   * route to start survey
   * @param string $slug
   * @param mixed $group
   */
  public function startAction($slug,$group=false)
  {
      $param = $this->getConfiguration($slug,$group);
      //config.survey.text.start
      $default = 'FgmsSurveyBundle:Default:start.html.twig';
      $template = (empty($param['config']['survey']['templates']['start'])) ? $default : $param['config']['survey']['templates']['start'];
      if ( (!empty($param['config']['survey']['form'])) and ($param['config']['survey']['form'] == 'feedback') ){
        $feedback_entity = new Feedback();
        $feedback_entity->setCreateDate();
        $form = $this->createForm(FeedbackType::class,$feedback_entity);
        $param['form'] = $form->createView();
        $form->handleRequest(Request::createFromGlobals());
        if ($form->isValid()){
          $em = $this->getDoctrine()->getManager();
          $data = $form->getData();
          $em->persist($data);
          $em->flush();
          $param['feedback_id'] = $data->getId();
        }
      }
      $template = ((strlen($template) > 10) and ($this->get('templating')->exists($template))) ? $template : $default;
      return $this->render($template, $param);
  }

  /**
   * route for the survey
   * @param string $slug
   * @param mixed $group
   */
  public function surveyAction($slug,$group=false)
  {

      //  TODO: This should be injected and the number of bits
      //  should be configurable
      $token = new \Fgms\Bundle\SurveyBundle\Utility\RandomTokenGenerator(128);
      $param = $this->getConfiguration($slug,$group);
      $room = $this->request->query->has('room') ? $this->request->query->get('room') : 'none';
      $form = $this->getForm($slug,$group,$room);
      $form->handleRequest($this->request);
      if ($form->isValid()){
          $em = $this->getDoctrine()->getManager();
          $q = $form->getData();
          //  Create Testimonial objects from responses user
          //  identified as being testimonials
          $ts = $q->getTestimonialData();
          $tests = [];
          if (!is_null($ts)) {
              foreach ($ts as $t) {
                  //	As far as we know each of these $t items
                  //	is raw data from the user, we expect an
                  //	object with a "field" property which is
                  //	a string, but we don't know that the item
                  //	actually has that form
                  if (!(is_object($t) && isset($t->field) && is_string($t->field))) $this->invalidTestimonialData($t);
                  if (!preg_match('/^question(\d+)$/u',$t->field,$matches)) $this->invalidTestimonialData($t);
                  $num = intval($matches[1]);
                  $test = new \Fgms\Bundle\SurveyBundle\Entity\Testimonial();
                  $test->setQuestion($num);
                  $test->setApproved(false);
                  $test->setText($q->getQuestion($num));
                  $test->setQuestionnaire($q);
                  $test->setToken($token->generate());
                  $tests[] = $test;
              }
          }
          $em->persist($q);
          foreach ($tests as $t) $em->persist($t);
          foreach ($tests as $t) $this->sendTestimonialEmail($t);
          $this->checkSurveyResults($slug,$group,$form,$room);
          $em->flush();
          $conditional = $this->getConditionalFinish($form) ? '?conditional' : '';
          return $this->redirect("/". $this->fullSlug. "/finish/".$conditional);
      }
      $param['form'] = $form->createView();
      return $this->render('FgmsSurveyBundle:Default:survey.html.twig', $param );
  }

  /**
   * route to finish survey
   * @param string $slug
   * @param mixed $group
   *
   */
  public function finishAction($slug,$group=false)
  {
      $param = $this->getConfiguration($slug, $group);
      $param['conditionalFinish'] = $this->request->query->has('conditional');
      return $this->render('FgmsSurveyBundle:Default:finish.html.twig', $param);
  }

  /**
   * route to get results this is the admin section requires valid key which is setup in yaml file
   *
   * @param string $slug
   * @param mixed $group
   *
   */
  public function resultsAction($slug,$group=false)
  {
      $this->getConfiguration($slug,$group);
      $showResultsFlag = false;
      // throws error if not authenticated
      $this->checkIfAuthenticated();
      $this->param['questions_presentation'] = array_map(function (array $q) {
          $q['number'] = intval(preg_replace('/^question/u','',$q['field']));
          $q['title'] = htmlspecialchars_decode(strip_tags($q['title']));
          return $q;
      },$this->param['questions']);
      usort($this->param['questions_presentation'],function (array $a, array $b) {
          return $a['number'] - $b['number'];
      });
      $this->summary_report = new \Fgms\Bundle\SurveyBundle\Utility\SummaryReport($this->getDoctrine()->getManager(), $this->param, $slug, $group);
      //$stats = $this->getStats($slug,$group);
      $stats = $this->summary_report->get_stats();
      $enables = [
        'enables' =>[
          'performance_charting'  => false,
          'suvery_configuration'  => false,
          'guest_feedback'        => false
        ]
      ];
      return $this->render('FgmsSurveyBundle:Default:results.html.twig',array_merge($this->param,$stats,$enables) );
  }

  private function invalidTestimonialData($obj)
  {
      //	TODO: Improve error handling/reporting
      throw new \RuntimeException('Invalid testimonial data');
  }

  private function sendTestimonialEmail(\Fgms\Bundle\SurveyBundle\Entity\Testimonial $t)
  {
      $param = $this->getBasicEmailParam();
      $param['subject'] = 'New Testimonial';
      $param['recipient'] = ['to' => $this->param['config']['testimonials']['recipient']['to']];
      $data = ['token' => $t->getToken()];
      $this->sendEmail($param,$data,'email-testimonial',true);
  }


    /*
    * checks to see if uses alternative or conditional finish , ie send to trip advisor
    * @param array $param
    *
    */
    private function getConditionalFinish($form = false){
        $conditionalFlag = false;
        if ( $form !== false){
            // check to make sure param is there
            if (isset($this->param['config']['survey']['text']['finish']['conditional'])) {

                // getting field, trigger and active
                $cResult  = $this->param['config']['survey']['text']['finish']['conditional'];
                $questionField = $this->getValue('field', $cResult['question'],false);
                $questionTrigger = $this->getValue('trigger', $cResult['question'],false);
                $active = $this->getValue('active', $cResult, false);

                // makes sure has active, a question field, and a trigger
                if ( ($active !== false) && ($questionField !== false) && ($questionTrigger !== false)){
                    $data = $form->get($questionField)->getData();
                    foreach ($this->param['activequestions'] as $item){
                        if ($item['field'] == $questionField){
                            $conditionalFlag = $this->check_trigger($data,$questionTrigger, false);
                            break;
                        }
                    }
                }
            }
        }
        return $conditionalFlag;
    }



    /**
     * route to download CSV file
     * @param string $slug
     * @param mixed $group
     */
    public function downloadcsvAction($slug,$group=false)
    {
        $sluggroup = ($group != false) ? $group : '';
        $em  = $this->getDoctrine()->getManager();
        $query = $em->createQueryBuilder()
          ->from('FgmsSurveyBundle:Questionnaire', 'q')
          ->select("q")
          ->addselect("f")
          ->leftJoin('FgmsSurveyBundle:Feedback', 'f', 'WITH', "q.feedbackId=f.id")
          ->where('q.slug = :slug AND q.sluggroup = :sluggroup' )
          ->setParameters(array('slug' =>$slug, 'sluggroup' =>$sluggroup))
          ->orderBy('q.createDate','ASC')
          ->getQuery();

        $response = new StreamedResponse();
        $response->setCallback(function() use ($query){
            $handle = fopen('php://output', 'w+');
            fputcsv($handle, array('Survey#','Date', 'Time','Group','Property','Room','Name','Email','Address','Message','Q1','Q2','Q3','Q4','Q5','Q6','Q7','Q8','Q9','Q10','Q11','Q12','Q13','Q14','Q15'),',');
            foreach ($query->getResult(\Doctrine\ORM\Query::HYDRATE_SCALAR) as $item){
                $room = ( preg_match('/_([0-9]+)_/', $item['q_roomNumber'], $matches) ) ? '' : $item['q_roomNumber'];
                fputcsv($handle, array($item['q_id'],
                                       $item['q_createDate']->format('F j, Y'),
                                       $item['q_createDate']->format('h:i A'),
                                       $item['q_sluggroup'],
                                       $item['q_slug'],
                                       $room,
                                       $item['f_name'],
                                       $item['f_email'],
                                       $item['f_address'],
                                       $item['f_message'],
                                       $item['q_question1'],
                                       $item['q_question2'],
                                       $item['q_question3'],
                                       $item['q_question4'],
                                       $item['q_question5'],
                                       $item['q_question6'],
                                       $item['q_question7'],
                                       $item['q_question8'],
                                       $item['q_question9'],
                                       $item['q_question10'],
                                       $item['q_question11'],
                                       $item['q_question12'],
                                       $item['q_question13'],
                                       $item['q_question14'],
                                       $item['q_question15']
                        ));
            }
            fclose($handle);
        });

        $response->setStatusCode(200);
        $response->headers->set('Content-Type', 'application/force-download');
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition( ResponseHeaderBag::DISPOSITION_ATTACHMENT, $slug. '-export.csv'));
        $response->prepare(Request::createFromGlobals());
        return $response;

    }

    /**
    * This is to access all via a cron job an array of groups and slugs.
    */
    public function crontriggerAction()
    {
        $key = $this->request->query->has('key') ? $this->request->query->get('key') : '';
        $checkKey = 'A3CCEBA83235DC95F750108D22C14731';
        // lets add simple key just to prevent crons being accidently triggered.
        if ($key === $checkKey){
            $crons = array(
                array('slug'=>'thepalmsturksandcaicos', 'group'=>'thehartlinggroup'),
                array('slug'=>'thesandsatgracebay', 'group'=>'thehartlinggroup'),
            );

            foreach ($crons as $cron){
                $this->getConfiguration($cron['slug'],$cron['group']);
                $this->param = array_merge($this->param, $this->getStats($cron['slug'],$cron['group'],true));
                $this->emailRollup();
            }

            // this gets the daily stats
            return new Response("cron success");
        }
        else {
            return new Response("cron failed");
        }

    }

    /**
    * This is to access directly
    */
    public function cronAction($slug,$group)
    {
        $this->checkIfAuthenticated();
        $this->getConfiguration($slug,$group);
        $this->param = array_merge($this->param, $this->getStats($slug,$group,true));
        $this->emailRollup();
        return new Response("cron success");
    }

    /**
     * route to preview email
     * @param string $slug
     * @param mixed $group
     */
    public function emailpreviewAction($slug,$group=false)
    {
        $this->getConfiguration($slug,$group);
        // throws error if not authenticated
        $this->checkIfAuthenticated();
        $template = $this->request->query->has('template') ? $this->request->query->get('template') : 'email-notification';
        $surveytest = $this->request->query->has('survey') ? $this->request->query->get('survey') : false;
        $emailFlag = $this->request->query->has('email') ? true : false;
        if ($surveytest == 'true'){
            $this->checkSurveyResults($slug, $group,false,'000');
        }
        else {
            $this->param = array_merge($this->param, $this->getStats($slug,$group,true));
        }
        if ($emailFlag){
            $this->emailRollup(true);
        }
        return $this->render('FgmsSurveyBundle:Email:'. $template .'.html.twig',$this->param );
    }

    private function emailRollup($testFlag=false)
    {
        $emailParam = array();
        $emailParam['fromEmail'] = [
          $this->getValue('address',$this->config['email']['from'],'webmaster@fifthgeardev.com') => $this->getValue('name',$this->config['email']['from'],'Webmaster')
        ];
        $emailParam['subject'] = sprintf($this->param['config']['rollup']['subject'], $this->param['property']['name']) ;
        if ($testFlag){
            $emailParam['recipient']['to'] = array('webmaster@fifthgeardev.com');
        }
        else {
            $emailParam['recipient'] = $this->param['config']['rollup']['recipient'];
        }

        $this->sendEmail($emailParam, $this->param,'email-rollup');
    }

    private function getBasicEmailParam()
    {
        return [
            'fromEmail' => [
                $this->getValue('address',$this->config['email']['from'],'webmaster@fifthgeardev.com') => $this->getValue('name',$this->config['email']['from'],'Webmaster')
            ]
        ];
    }

    /**
     *
     * @param string $slug
     * @param string $group
     * @param form $form
     * @param string $room
     *
     */
    private function checkSurveyResults($slug,$group,$form,$room)  {
        $emailParam = $this->getBasicEmailParam();
        foreach ($this->param['activequestions'] as $item){
            $emailFlag = false;
            $field = $item['field'];
            $data = array();
            // this means it is a test so we are going to fail all items to get emails
            if ($form === false){
                $data = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Duis volutpat ex eget massa fermentum, sit amet venenatis massa fringilla. Nunc ut mi elementum, hendrerit augue a, pellentesque turpis. Nulla ultricies nisl volutpat, dignissim ipsum nec, iaculis lacus. Proin eleifend id nisl et molestie';
                $type = $this->getValue('type',$item,'rating');
                if ( $type == 'rating'){
                    $data = '1';
                }
                else if ($type == 'boolean'){
                    $data = ($this->getValue('negative',$item)) ? 'yes': 'no';
                }
            }
            else {
                $data = $form->get($field)->getData();
            }
            //doesnt have an email trigger.
            if ($this->getValue('email',$item,false) == false){
                continue;
            }
            $trigger = $this->getValue('trigger',$item['email']);
            $emailFlag = $this->check_trigger($data,$trigger);
            if ( ($item['type'] == 'comment') && (strlen($data) > 0) ){
                $emailFlag = true;
            }
            $emailParam['subject'] = sprintf($item['email']['subject'], $room) .' ('.$this->param['property']['name'].')';
            $emailParam['subject'] = ($form == false) ? 'Test - ' .$emailParam['subject'] : $emailParam['subject'];
            if ($emailFlag){
                $item['answer'] = $data;

                $matches = null;
                if ( preg_match('/_([0-9]+)_/', $room, $matches) ){
                  if (!empty($matches[1])){
                    $feedback_id = intval($matches[1]);
                    if ($feedback_id > 0){
                      $feedback_repo = $this->getDoctrine()->getRepository('FgmsSurveyBundle:Feedback');
                      $row = $feedback_repo->findOneById($feedback_id);
                      $item['client_email'] = $row->getEmail();
                      $item['client_name'] = $row->getName();
                    }

                  }
                }
                else {
                  $item['roomNumber'] = $room;
                }

                $item['title'] = strip_tags($item['title']);
                // adds webmaster to bcc
                $item['email']['recipient']['bcc'] = isset($item['email']['recipient']['bcc']) ? array_merge($item['email']['recipient']['bcc'],array('webmaster@fifthgeardev.com')) : array('webmaster@fifthgeardev.com');
                $emailParam['recipient'] = $item['email']['recipient'];

                $this->logger->warning('SENDEMAIL:: '. print_R($item,true));
                $combined_array = array_merge($this->param, array('item'=>$item));
                $this->sendEmail($emailParam, $combined_array,'email-notification');
            }
        }
    }

    /**
     * @param string $emailParam
     * @param string $data
     * @param form $template
     */
    private function sendEmail($emailParam = array(),$data= array(),$template='email-notification')
    {
        $message = \Swift_Message::newInstance()
            ->setSubject($emailParam['subject'])
            ->setFrom($emailParam['fromEmail']);
        // setting up recipients
        if ($this->getValue('recipient',$emailParam,false) != false){
            if ($this->getValue('to',$emailParam['recipient']) != false){
                $message  = $message->setTo($emailParam['recipient']['to']);
            }
            if ($this->getValue('cc',$emailParam['recipient']) != false){
                $message  = $message->setCc($emailParam['recipient']['cc']);
            }
            if ($this->getValue('bcc',$emailParam['recipient']) != false){
                $message  = $message->setBcc($emailParam['recipient']['bcc']);
            }
        }
        $message = $message
            ->setBody($this->renderView('FgmsSurveyBundle:Email:'.$template.'.html.twig', $data ),'text/html')
            ->addPart($this->renderView('FgmsSurveyBundle:Email:'.$template.'.txt.twig', $data ),'text/plain');
        $this->get('mailer')->send($message);
        //$this->logger->info('Mailer:: Sent Message Header ' .print_R($message->getHeaders()->toString(),true));
    }

    /**
     *
     */
    private function checkIfAuthenticated()
    {
        if ($this->getValue('reporting',$this->param,false) != false){
            if ($this->getValue('group',$this->param['reporting'],false) != false){
                if ($this->getValue('properties',$this->param['reporting']['group'],false) != false){
                    foreach ($this->getValue('properties',$this->param['reporting']['group'],array() ) as $adminAuth){
                        if ($this->getValue('key',$this->param) == $this->getValue('token',$adminAuth)){
                            $slug  = $this->getValue('slug',$adminAuth);
                            // this means it is specific for a slug
                            if ($slug != false){
                                if ($slug == $this->getValue('fullslug',$this->param)){
                                    return true;
                                }
                            }
                            else {
                                return true;
                            }
                        }
                    }
                }
            }
        }
        $error_str = 'Authentication is not correct';
        throw $this->createNotFoundException($error_str);
        return false;
    }

    /*
     * gets configurations for property and sets the images path
     * @param string $slug this is the slug or permalink of property
     * @param string/boolean $groupslug if no option passed just uses slug otherwise uses it as a folder name
     */
    private function getConfiguration($slug, $group=false)
    {
        //setting up defaults
        if ( $this->has('request')){
            $this->request = $this->get('request');
        }
        else {
            $this->request = $this->container->get('request_stack')->getCurrentRequest();
        }
        if ($this->logger === false){	$this->logger = $this->get('logger');	}
        $this->fullSlug = ($group !== false) ? $group.'/': '';
        $this->fullSlug .= $slug;
        $config_defaults = array('images_path'=>'/web/assets/images/',
                                 'property_config_dir'=>$this->get('kernel')->getRootDir().'/config/property/');

        // getting system survey.yml config
        $this->config = array_merge($config_defaults, $this->getYaml($this->get('kernel')->getRootDir().'/config/survey.yml'));
        if (count($this->config) > 0){
            $this->config['property_config_dir'] = $this->get('kernel')->getRootDir().$this->getValue('property_config_dir', $this->config,'/config/property/');
            $this->config['images_path'] = $this->getValue('images_path', $this->config,'/web/assets/images/').$this->fullSlug.'/';
        }

        //checks to make sure directory is valid
        if (!is_dir($this->config['property_config_dir'])){
            $error_str = 'Property Config Directory "' . $this->config['property_config_dir'] .'" does not exists';
            throw $this->createNotFoundException($error_str);
        return array('error'=>$error_str );
        }

        // gets property yaml
        $param = $this->getYaml($this->config['property_config_dir'] .$this->fullSlug.".yml");
        // testing to make sure is a valid property yaml
        $error_str = 'Yaml File is not a valid Property.. Could be an Admin file';
        if ($this->getValue('property',$param) !== false){
            if ($this->getValue('name',$param['property']) !== false){
                //adds missing params
                $param['fullslug'] = $this->fullSlug;
                $param['key'] = $this->request->query->has('key') ? $this->request->query->get('key') : false;
                $param['admin'] = $this->request->query->has('admin');
                $param['activequestions'] = array();
                foreach($param['questions'] as $questions){
                    if ($this->getValue('active',$questions,true)) {
                        $param['activequestions'][] = $questions;
                    }
                }
                // means this is admin lets load admin file
                if ($this->getValue('key',$param) !== false){
                    if (isset($param['config']['admin']['yamlfile']) ){
                        // gets admin yaml file and merges with any existing file
                        $adminYamlFile = $this->config['property_config_dir'] . $param['config']['admin']['yamlfile'];
                        $param['reporting'] = array_merge($this->getValue('reporting',$param,array()),$this->getYaml($adminYamlFile));
                    }
                }
            }
            else {
                $this->logger->error($error_str);
                throw $this->createNotFoundException($error_str);
            }
        }
        else {
            $this->logger->error($error_str);
            throw $this->createNotFoundException($error_str);
        }
        $this->param = array_merge($param,$this->config);
        // need to update this logic, to remove $param and start using $this->param
        return 	$this->param;
    }

    /**
     * reads yaml files
     *
     * @param string filename
     */
    private function getYaml($filename)
    {
        $yaml = new Parser();
        $param = array();
        if (!file_exists($filename)){
            $error_str = 'Yaml Config file "' . $filename .'" does not exists';
            throw $this->createNotFoundException($error_str);
            return array();
        }
        try {
            $param = $yaml->parse(file_get_contents($filename));
        }catch (ParseException $e) {
            $this->get('logger')->error('Yaml Parse error '.print_R($e->getMessage(),true));
            return array();
        }
        $this->logger->info('Success Loading Yaml file '.$filename );
        return $param;

    }

    private function getForm($slug,$group, $roomNumber='none', $set="Default")
    {
        $questionObject = new Questionnaire();
        $questionObject->setCreateDate();
        $questionObject->setSlug($slug);
        $questionObject->setSluggroup($group);
        $questionObject->setQuestionSet($set);
        if ($questionObject->getRoomNumber() == null){
            $questionObject->setRoomNumber($roomNumber);
            $matches = null;
            if ( preg_match('/_([0-9]+)_/', $roomNumber, $matches) ) {
              if (!empty($matches[1])){
                $questionObject->setFeedbackId(intval($matches[1]));
              }
            }
        }
        $form = $this->createFormBuilder($questionObject, array('csrf_protection' => true))
            ->add('question1',HiddenType::class)
            ->add('question2',HiddenType::class)
            ->add('question3',HiddenType::class)
            ->add('question4',HiddenType::class)
            ->add('question5',HiddenType::class)
            ->add('question6',HiddenType::class)
            ->add('question7',HiddenType::class)
            ->add('question8',HiddenType::class)
            ->add('question9',HiddenType::class)
            ->add('question10',HiddenType::class)
            ->add('question11',HiddenType::class)
            ->add('question12',HiddenType::class)
            ->add('question13',HiddenType::class)
            ->add('question14',HiddenType::class)
            ->add('question15',HiddenType::class)
            ->add('testimonialData',HiddenType::class)
            ->getForm();
        return $form;
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


    /**
    * compares value and trigger.
    * @param mixed $value
    * @param mixed $trigger
    * @param boolean $triggerIsGreaterActive normal should be true except on a positive trigger like tripadvisor output screen
    **/
    private function check_trigger($value, $trigger, $triggerIsGreaterActive = true)
    {
        $valueAsInt = intval($value);
        $triggerAsInt = intval($trigger);
        $triggerFlag = false;
        //comparison type
        if (($valueAsInt >= 0) && ($trigger != null)){
            if ($valueAsInt > 0 ){
                $triggerFlag = $triggerIsGreaterActive ? ($triggerAsInt >= $valueAsInt ) : ($triggerAsInt <= $valueAsInt );
            }
            // equates type
            else {
                $triggerFlag = ($trigger == $value);
            }
        }
        return $triggerFlag;
    }

    private function getBeginningOfDay(\DateTime $when)
    {
        $retr = clone $when;
        $retr->setTime(0,0,0);
        return $retr;
    }

    private function getEndOfDay(\DateTime $when)
    {
        $retr = clone $when;
        $retr->setTime(23,59,59);
        return $retr;
    }

    private function getBeginningOfPastDay(\DateTime $when, $days)
    {
        if ($days <= 0) throw new \InvalidArgumentException(sprintf('Expected strictly positive integer, got %d',$days));
        $when = clone $when;
        if ($days > 1) {
            $interval = new \DateInterval(sprintf('P%dD',$days-1));
            $when->sub($interval);
        }
        return $this->getBeginningOfDay($when);
    }

    private function getEndOfPastDay(\DateTime $when, $days)
    {
        $retr = $this->getBeginningOfPastDay($when,$days);
        return $this->getEndOfDay($retr);
    }

    private function getTimezone()
    {
        $tz = $this->getValue('timezone',$this->param['property'],'America/Vancouver');
        return new \DateTimeZone($tz);
    }



    private function getDateRange (\DateTime $begin, \DateTime $end, $slug, $group = false)
    {
        $utc = new \DateTimeZone('UTC');
        $begin->setTimezone($utc);
        $end->setTimezone($utc);
        $from = $begin->format('Y-m-d H:i:s');
        $to = $end->format('Y-m-d H:i:s');
        $repo = $this->getDoctrine()->getRepository(\Fgms\Bundle\SurveyBundle\Entity\Questionnaire::class);
        $qb = $repo->createQueryBuilder('q')
            ->andWhere('q.createDate BETWEEN :from AND :to')
            ->andWhere('q.slug = :slug')
            ->andWhere('q.sluggroup = :sluggroup')
            ->setParameter('from',$from)
            ->setParameter('to',$to)
            ->setParameter('slug',$slug)
            //	I'm not so sure empty string should be used instead of
            //	NULL here, but it's done that way above so I'm just
            //	continuing the practice
            ->setParameter('sluggroup',($group===false) ? '' : $group)
        ;
        $q = $qb->getQuery();
        $retr = $q->getResult();
        if (!is_array($retr)) $retr = [$retr];
        return $retr;
    }

    private function sortByDate(array &$data, $asc = true)
    {
        usort($data,function (\Fgms\Bundle\SurveyBundle\Entity\Questionnaire $a, \Fgms\Bundle\SurveyBundle\Entity\Questionnaire $b) use ($asc) {
            $a = $a->getCreateDate();
            $b = $b->getCreateDate();
            $retr = $a->getTimestamp() - $b->getTimestamp();
            if (!$asc) $retr *= -1;
            return $retr;
        });
    }

    private function getDateRangeByDay(\DateTime $begin, \DateTime $end, array $arr)
    {
        $this->sortByDate($arr);
        $jump = new \DateInterval('P1D');
        //  Clone to avoid mutating the referred to objects
        $begin = clone $begin;
        //  Next array index to consider
        $i = 0;
        //	This function generates all the Questionnaire objects
        //	in the currently considered day
        $func = function () use ($begin, $arr, &$i) {
            while (
                ($i !== count($arr)) &&
                ($arr[$i]->getCreateDate()->getTimestamp() < $begin->getTimestamp())
            ) yield $arr[$i++];
        };
        while ($begin->getTimestamp() <= $end->getTimestamp()) {
            $b = clone $begin;
            $begin->add($jump);
            yield (object)[
                'begin' => $b,
                'end' => $this->getEndOfDay($b),
                'results' => $func()
            ];
        }
    }

    private function toRating($str)
    {
        if (!is_numeric($str)) return null;
        $i = intval($str);
        if (floatval($str) != $i) return null;
        if (($i < 1) || ($i > 5)) return null;
        return $i;
    }

    private function ratingToChart($question, $days)
    {
        foreach ($days as $day) {
            $sum = 0;
            $count = 0;
            foreach ($day->results as $result) {
                $rating = $result->getQuestion($question);
                $i = $this->toRating($rating);
                //  Do not count invalid entries
                if (is_null($i)) continue;
                $sum += $i;
                ++$count;
            }
            unset($day->results);
            $day->value = ($count === 0) ? null : ((float)$sum / (float)$count);
            $day->count = $count;
            yield $day;
        }
    }

    private function percentageToChart($callable, $days)
    {
        foreach ($days as $day) {
            $good = 0;
            $count = 0;
            foreach ($day->results as $result) {
                $result = $callable($result);
                //  Do not count invalid entries
                if (is_null($result)) continue;
                if ($result) ++$good;
                ++$count;
            }
            unset($day->results);
            $day->value = ($count === 0) ? null : (((float)$good / (float)$count)*100.0);
            $day->count = $count;
            yield $day;
        }
    }

    private function getPolarCallable($question, $negative)
    {
        return function (\Fgms\Bundle\SurveyBundle\Entity\Questionnaire $q) use ($question, $negative) {
            $text = $q->getQuestion($question);
            if ($text === 'Yes') return !$negative;
            if ($text === 'No') return $negative;
            //  Invalid, do not count
            return null;
        };
    }

    private function polarToChart($question, $negative, $days)
    {
        return $this->percentageToChart($this->getPolarCallable($question,$negative),$days);
    }

    private function getOpenCallable($question)
    {
        return function (\Fgms\Bundle\SurveyBundle\Entity\Questionnaire $q) use ($question) {
            $text = $q->getQuestion($question);
            //  Apparently no comment is stored as NULL
            if (!is_string($text)) return false;
            $text = preg_replace('/^\\s+|\\s+$/u','',$text);
            return $text !== '';
        };
    }

    private function openToChart($question, $days)
    {
        return $this->percentageToChart($this->getOpenCallable($question),$days);
    }

    private function getQuestion($question)
    {
        //  Questions are not indexed per se, instead we have
        //  to search and match against "field"
        $search = sprintf('question%d',$question);
        foreach ($this->param['questions'] as $q) {
            if ($q['field'] === $search) return $q;
        }
        throw $this->createNotFoundException(
            sprintf('No question %d',$question)
        );
    }

    private function getQuestionType($question)
    {
        if (!is_array($question)) $question = $this->getQuestion($question);
        return strtolower($this->getValue('type',$question,'open'));
    }

    private function aggregateQuestion($question, $data)
    {
        $q = $this->getQuestion($question);
        $type = $this->getQuestionType($q);
        $result = null;
        if ($type === 'rating') {
            return $this->ratingToChart($question,$data);
        } elseif ($type === 'polar') {
            $negative = $this->getValue('negative',$q,false);
            return $this->polarToChart($question,$negative,$data);
        } elseif ($type === 'open') {
            return $this->openToChart($question,$data);
        }
        throw new \RuntimeException(
            sprintf(
                'Unrecognized question type "%s" (%s question %d)',
                $type,
                ($group === false) ? $slug : sprintf('%s/%s',$group,$slug),
                $question
            )
        );
    }

    private function summarizeRating($question, $threshold, $data)
    {
        $retr = (object)[
            'values' => [0,0,0,0,0],
            'total' => 0
        ];
        foreach ($data as $datum) {
            $rating = $datum->getQuestion($question);
            $i = $this->toRating($rating);
            //  Ignore invalid entries
            if (is_null($i)) continue;
            ++$retr->total;
            ++$retr->values[$i-1];
        }
        return $retr;
    }

    private function summarizeBinary($callable, $data)
    {
        $retr = (object)[
            'total' => 0,
            'good' => 0,
            'bad' => 0
        ];
        foreach ($data as $datum) {
            $val = $callable($datum);
            //  Skip invalid data
            if (is_null($val)) continue;
            ++$retr->total;
            if ($val) ++$retr->good;
            else ++$retr->bad;
        }
        return $retr;
    }

    private function summarizePolar($question, $negative, $data)
    {
        return $this->summarizeBinary($this->getPolarCallable($question,$negative),$data);
    }

    private function summarizeOpen($question, $data)
    {
        return $this->summarizeBinary($this->getOpenCallable($question),$data);
    }

    private function summarizeQuestion($question, $data)
    {
        $q = $this->getQuestion($question);
        $type = $this->getQuestionType($q);
        $threshold = $this->getValue('trigger',$q,null);
        if ($type === 'rating') {
            return $this->summarizeRating($question,$threshold,$data);
        }
        if ($type === 'polar') {
            return $this->summarizePolar($question,$this->getValue('negative',$q,false),$data);
        }
        if ($type === 'open') {
            return $this->summarizeOpen($question,$data);
        }
        throw new \RuntimeException(
            sprintf(
                'Unrecognized question type "%s" (%s question %d)',
                $type,
                ($group === false) ? $slug : sprintf('%s/%s',$group,$slug),
                $question
            )
        );
    }

    private function createChartResponse($question, \DateTime $begin, \DateTime $end, $slug, $group = false)
    {
        $q = $this->getQuestion($question);
        $type = $this->getQuestionType($q);
        $data = $this->getDateRange($begin,$end,$slug,$group);
        $arr = iterator_to_array(
            $this->aggregateQuestion(
                $question,
                $this->getDateRangeByDay(
                    $begin,
                    $end,
                    $data
                )
            )
        );
        $summary = $this->summarizeQuestion($question,$data);
        $retr = (object)[
            'min' => ($type === 'rating') ? 1 : 0,
            'max' => ($type === 'rating') ? 5 : 100,
            'group' => ($group === false) ? null : $group,
            'slug' => $slug,
            'question' => $question,
            'results' => $arr,
            'timezone' => $this->getTimezone(),
            'type' => $type,
            'threshold' => $this->getValue('trigger',$q,null),
            'title' => htmlspecialchars_decode(strip_tags($q['title'])),
            'summary' => $summary
        ];
        if ($type === 'polar') {
            $retr->negative = $this->getValue('negative',$q,false);
            $retr->positive_description = $this->getValue('positive_description',$q,$retr->negative ? 'No' : 'Yes');
            $retr->negative_description = $this->getValue('negative_description',$q,$retr->negative ? 'Yes' : 'No');
        }
        return $retr;
    }

    private function sanitizeToJson($obj)
    {
        if ($obj instanceof \stdClass) {
            $retr = new \stdClass();
            foreach ($obj as $key => $value) $retr->$key = $this->sanitizeToJson($value);
            return $retr;
        }
        if (is_array($obj)) {
            return array_map(function ($obj) {
                return $this->sanitizeToJson($obj);
            },$obj);
        }
        if ($obj instanceof \DateTime) {
            return $obj->getTimestamp();
        }
        if ($obj instanceof \DateTimeZone) {
            return $obj->getName();
        }
        return $obj;
    }

    private function setAdminInit($slug, $group=false){
      $this->getConfiguration($slug,$group);
      $this->checkIfAuthenticated();
    }

    private function chartInit($slug, $group = false)
    {
        $this->getConfiguration($slug,$group);
        $this->checkIfAuthenticated();
    }

    private function checkQuestion($question, $slug, $group = false)
    {
        $question = intval($question);
        if (($question < 1) || ($question > 15)) throw $this->createNotFoundException(
            sprintf(
                'Question number (%d) out of range',
                $question
            )
        );
        $c = count($this->param['questions']);
        if ($question > $c) throw $this->createNotFoundException(
            sprintf(
                '%s has only %d questions but question number %d requested',
                ($group === false) ? $slug : sprintf('%s/%s',$group,$slug),
                $c,
                $question
            )
        );
        return $question;
    }

    private function chartImpl($question, \DateTime $from, \DateTime $to, $slug, $group = false)
    {
        $question = $this->checkQuestion($question,$slug,$group);
        $obj = $this->createChartResponse($question,$from,$to,$slug,$group);
        $res = new \Symfony\Component\HttpFoundation\Response();
        $res->setCharset('UTF-8');
        $res->setContent(json_encode($this->sanitizeToJson($obj)));
        $res->headers->set('Content-Type','application/json');
        return $res;
    }

    private function getCsvFilename($days, $slug, $group = false)
    {
        return sprintf(
            '%s-%s.csv',
            ($group === false) ? $slug : sprintf('%s-%s',$group,$slug),
            $days
        );
    }

    private function startCsv()
    {
        //  The UTF-8 BOM, forces Excel to recognize
        //  Unicode characters in the CSV
        return chr(0xEF).chr(0xBB).chr(0xBF);
    }

    private function csvEscape($str)
    {
        //  "Each field may or may not be enclosed in double quotes [...]"
        //
        //  "If double-quotes are used to enclose fields, then a double-quote
        //   appearing inside a field must be escaped by preceding it with
        //   another double quote."
        return sprintf('"%s"',preg_replace('/"/u','""',$str));
    }

    private function getCsvRow(array $data)
    {
        $retr = '';
        $first = true;
        foreach ($data as $datum) {
            if ($first) $first = false;
            else $retr .= ',';
            $retr .= $this->csvEscape($datum);
        }
        //  "Each record is located on a separate line, delimited by a line
        //   break (CRLF)."
        //
        //  "The last record in the file may or may not have an ending line
        //   break."
        $retr .= "\r\n";
        return $retr;
    }

    private function getCsvHeader(array $qs)
    {
        $retr = ['Date'];
        foreach ($qs as $q) {
            $explain = '';
            if ($q['type'] === 'rating') $explain = 'Average Rating';
            elseif ($q['type'] === 'open') $explain = '% Responding';
            //  Must be polar
            else $explain = '% Positive';
            $retr[] = sprintf(
                'Q%d (%s)',
                $this->extractField($q['field']),
                $explain
            );
        }
        $retr[] = 'Number of Entries';
        return $this->getCsvRow($retr);
    }

    private function dateToCsv(\DateTime $date)
    {
        $tz = $this->getTimezone();
        $date = clone $date;
        $date->setTimezone($tz);
        return $date->format('M j, Y');
    }

    public function chartAction($question, $days, $slug, $group = false)
    {
        $this->charting = new \Fgms\Bundle\SurveyBundle\Utility\PerformanceCharting();
        $this->setAdminInit($slug,$group);
        $range = $this->daysToRange($days);
        return $this->chartImpl($question,$range[0],$range[1],$slug,$group);
    }

    private function extractField($field)
    {
        $field = preg_replace('/^question/u','',$field);
        return intval($field);
    }

    public function chartCsvAction($days, $slug, $group = false)
    {
        $this->chartInit($slug,$group);
        list($begin,$end) = $this->daysToRange($days);
        $entries = $this->getDateRange($begin,$end,$slug,$group);
        $qs = $this->param['questions'];
        usort($qs,function (array $a, array $b) {
            return $this->extractField($a['field']) - $this->extractField($b['field']);
        });
        $csv = $this->startCsv() . $this->getCsvHeader($qs);
        $gs = [];
        foreach ($qs as $q) {
            $curr = $this->aggregateQuestion(
                $this->extractField($q['field']),
                $this->getDateRangeByDay($begin,$end,$entries)
            );
            //  Not sure how required this is but foreach loops
            //  do this apparently
            $curr->rewind();
            $gs[] = $curr;
        }
        if (count($gs) === 0) throw new \RuntimeException('No questions');
        while ($gs[0]->valid()) {
            $num = 0;
            $row = [$this->dateToCsv($gs[0]->current()->begin)];
            foreach ($gs as $g) {
                $curr = $g->current();
                if ($curr->count > $num) $num = $curr->count;
                $row[] = round($curr->value,2);
                $g->next();
            }
            $row[] = $num;
            $csv .= $this->getCsvRow($row);
        }
        $res = new \Symfony\Component\HttpFoundation\Response();
        $res->setCharset('UTF-8');
        $res->headers->set('Content-Type','text/csv');
        $res->headers->set(
            'Content-Disposition',
            sprintf('attachment; filename=%s',$this->getCsvFilename($days,$slug,$group))
        );
        $res->setContent($csv);
        return $res;
    }

    public function chartRangeAction($fromday, $frommonth, $fromyear, $today, $tomonth, $toyear, $question, $slug, $group = false)
    {
        $this->chartInit($slug,$group);
        $from = \DateTime::createFromFormat('d-m-Y',sprintf('%s-%s-%s',$fromday,$frommonth,$fromyear));
        $to = \DateTime::createFromFormat('d-m-Y',sprintf('%s-%s-%s',$today,$tomonth,$toyear));
        return $this->chartImpl($question,$from,$to,$slug,$group);
    }

    public function getFeedbackData($question, $days, $slug, $group = false)
    {
        $this->chartInit($slug,$group);
        $range = $this->daysToRange($days);
        $data = $this->getDateRange($range[0],$range[1],$slug,$group);
        $question = $this->checkQuestion($question,$slug,$group);
        $q = $this->getQuestion($question);
        $type = $this->getValue($q,'type','open');
        if ($type !== 'open') throw $this->createNotFoundException(
            sprintf(
                'Question %d is not of type "open"',
                $question
            )
        );
        $data = array_filter($data,function (\Fgms\Bundle\SurveyBundle\Entity\Questionnaire $q) use ($question) {
            $val = $q->getQuestion($question);
            if (is_null($val)) return false;
            $val = preg_replace('/^\\s+|\\s+$/u','',$val);
            return $val !== '';
        });
        $this->sortByDate($data,false);
        return array_map(function (\Fgms\Bundle\SurveyBundle\Entity\Questionnaire $q) use ($question) {
            $t = null;
            foreach ($q->getTestimonials() as $curr) {
                if ($curr->getQuestion() === $question) {
                    $t = $curr;
                    break;
                }
            }
            if (!is_null($t)) {
                $t = (object)[
                    'text' => $t->getText(),
                    'approved' => $t->getApproved(),
                    'token' => $t->getToken()
                ];
            }
            return (object)[
                'date' => $q->getCreateDate(),
                'room' => $q->getRoomNumber(),
                'feedback' => $q->getQuestion($question),
                'testimonial' => $t
            ];
        },$data);
    }

    public function feedbackAction($question, $days, $slug, $group = false)
    {
        $results = $this->getFeedbackData($question,$days,$slug,$group);
        $res = new \Symfony\Component\HttpFoundation\Response();
        $res->setCharset('UTF-8');
        $res->headers->set('Content-Type','application/json');
        $res->setContent(
            json_encode(
                $this->sanitizeToJson(
                    (object)[
                        'timezone' => $this->getTimezone(),
                        'results' => $results
                    ]
                )
            )
        );
        return $res;
    }

    public function feedbackCsvAction($question, $days, $slug, $group = false)
    {
        $results = $this->getFeedbackData($question,$days,$slug,$group);
        $csv = $this->startCsv() . $this->getCsvRow(['Date','Room Number','Feedback']);
        foreach ($results as $result) {
            $csv .= $this->getCsvRow([$this->dateToCsv($result->date),$result->room,$result->feedback]);
        }
        $res = new \Symfony\Component\HttpFoundation\Response();
        $res->setCharset('UTF-8');
        $res->headers->set('Content-Type','text/csv');
        $res->headers->set(
            'Content-Disposition',
            sprintf(
                'attachment; filename=%s-%s-%s-feedback.csv',
                ($group === false) ? $slug : sprintf('%s-%s',$group,$slug),
                $question,
                $days
            )
        );
        $res->setContent($csv);
        return $res;
    }

    private function getTestimonialsQueryBuilder($count, $slug, $group = false)
    {
        $repo = $this->getDoctrine()->getRepository(\Fgms\Bundle\SurveyBundle\Entity\Testimonial::class);
        return $repo->createQueryBuilder('t')
            ->innerJoin('t.questionnaire','q')
            ->andWhere('q.slug = :slug')
            ->setParameter('slug',$slug)
            ->andWhere('q.sluggroup = :sluggroup')
            ->setParameter('sluggroup',($group === false) ? '' : $group)
            ->andWhere('t.approved = 1')
            ->setMaxResults($count);
    }

    private function getLatestTestimonialsQuery($count, $slug, $group = false)
    {
        $qb = $this->getTestimonialsQueryBuilder($count,$slug,$group)
            ->orderBy('q.createDate','DESC');
        return $qb->getQuery();
    }

    private function getRandomTestimonialsQuery($count, $slug, $group = false)
    {
        $qb = $this->getTestimonialsQueryBuilder($count,$slug,$group)
            ->addSelect('RAND() as HIDDEN rand')
            ->orderBy('rand');
        return $qb->getQuery();
    }

    private function getTestimonials($order, $count, $slug, $group = false)
    {
        $count = intval($count);
        if ($count === 0) throw new \LogicException('Invalid count');
        if ($count > 50) throw new \LogicException(
            sprintf(
                'Count %d exceeds maximum of 50',
                $count
            )
        );
        $q = ($order === 'random') ? $this->getRandomTestimonialsQuery($count,$slug,$group) : $this->getLatestTestimonialsQuery($count,$slug,$group);
        $results = $q->getResult();
        if (!is_array($results)) $results = [$results];
        return $results;
    }

    public function testimonialsAction($order, $count, $slug, $group = false)
    {
        $results = $this->getTestimonials($order,$count,$slug,$group);
        $arr = [];
        foreach ($results as $r) {
            $obj = new \stdClass();
            $obj->text = $r->getText();
            $obj->date = $r->getQuestionnaire()->getCreateDate()->format('F j, Y');
            $arr[] = $obj;
        }
        $params = ['testimonials' => $arr];
        $response = $this->render('FgmsSurveyBundle:Default:testimonials.js.twig',$params);
        $response->headers->set('Content-Type','text/javascript');
        $response->setCharset('UTF-8');
        return $response;
    }

    private function getTestimonial($token)
    {
        $repo = $this->getDoctrine()->getRepository(\Fgms\Bundle\SurveyBundle\Entity\Testimonial::class);
        $qb = $repo->createQueryBuilder('t')
            ->andWhere('t.token = :token')
            ->setParameter('token',$token)
            ->setMaxResults(1);
        $result = $qb->getQuery()->getResult();
        if (!is_array($result)) $result = [$result];
        if (count($result) === 0) return null;
        return $result[0];
    }

    public function testimonialAction($token)
    {
        $t = $this->getTestimonial($token);
        if (is_null($t)) throw new \LogicException(
            sprintf(
                'No testimonial with token %s',
                $token
            )
        );
        $q = $t->getQuestionnaire();
        $slug = $q->getSlug();
        $group = $q->getSluggroup();
        if (!$group) $group = false;
        $this->getConfiguration($slug,$group);
        $form = $this->createFormBuilder($t)
            ->add('text',\Symfony\Component\Form\Extension\Core\Type\TextareaType::class)
            ->add('approved',\Symfony\Component\Form\Extension\Core\Type\CheckboxType::class,['required' => false])
            ->add('save',\Symfony\Component\Form\Extension\Core\Type\SubmitType::class,['label' => 'Save'])
            ->getForm();
        $form->handleRequest($this->request);
        if ($form->isValid()) {
            $t = $form->getData();
            $em = $this->getDoctrine()->getManager();
            $em->persist($t);
            $em->flush();
        }
        return $this->render('FgmsSurveyBundle:Default:testimonial.html.twig',['form' => $form->createView()]);
    }

    public function testimonialsExampleAction($order, $count, $slug, $group = false)
    {
        return $this->render('FgmsSurveyBundle:Default:testimonials.html.twig',[
            'order' => $order,
            'count' => intval($count),
            'slug' => $slug,
            'group' => $group
        ]);
    }

}
