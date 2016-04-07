<?php

namespace Fgms\Bundle\SurveyBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use \Fgms\Bundle\SurveyBundle\Entity\Questionnaire;
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

        return $this->render('FgmsSurveyBundle:Default:start.html.twig', $param);
    }

    /**
	 * route for the survey
	 * @param string $slug
	 * @param mixed $group
	 */
    public function surveyAction($slug,$group=false)
    {

		$param = $this->getConfiguration($slug,$group);
        $room = $this->request->query->has('room') ? $this->request->query->get('room') : 'none';


		if ($this->has('request')){
			$form = $this->getForm($slug,$group,$room);
		}
		else {
			$form = $this->getFormSymfony3($slug,$group,$room);
		}
		$form->handleRequest($this->request);
		if ($form->isValid()){
			$em = $this->getDoctrine()->getManager();
			$em->persist($form->getData());
			$this->checkSurveyResults($slug,$group,$form,$room);
			$em->flush();
			$conditional = $this->getConditionalFinish($form) ? '?conditional' : '';
			return $this->redirect("/". $this->fullSlug. "/finish/".$conditional);
		}
        $param['form'] = $form->createView();
        return $this->render('FgmsSurveyBundle:Default:survey.html.twig', $param );
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
	 * route to finish survey
	 * @param string $slug
	 * @param mixed $group
	 *
	 */
    public function finishAction($slug,$group=false)
    {
		$param = $this->getConfiguration($slug, $group);
		$param['conditionalFinish'] = $this->get('request')->query->has('conditional');
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
        return $this->render('FgmsSurveyBundle:Default:results.html.twig',array_merge($this->param,$this->getStats($slug,$group)) );
    }

	/**
	* Gets stats, depending on $dayFlag either gets email ($dayFlag == true) or gets 7, 30 and year to date stats.
	* @param $slug string
	* @param $group string
	* @param $dayFlag boolean
	*/
	private function getStats($slug, $group, $dayFlag=false)
	{
		$questions = $this->param['questions'];
		$statsCount = array();
		if ($dayFlag){
			$survey24Hours =  $this->getSurveyRollup($slug,$group,'24 HOUR', $questions);
			$statsCount = array('day'=>$this->getValue('count',$survey24Hours));
		}
		else {
			$survey7 = $this->getSurveyRollup($slug,$group,'7 DAY', $questions);
	        $survey30 = $this->getSurveyRollup($slug,$group,'30 DAY', $questions);
	        $surveyYear = $this->getSurveyRollup($slug,$group,'1 YEAR', $questions);
			$statsCount = array('last7' =>$this->getValue('count',$survey7),
								'last30'=>$this->getValue('count',$survey30),
								'yeartodate'=>$this->getValue('count',$surveyYear)
							);
		}
		$stats = array();
        // setting up stats.
        foreach($questions as $allquestion){
            $active = isset($allquestion['active']) ? $allquestion['active'] : true;
            //$field = str_replace('question','Q',$allquestion['field']);
			//$field = str_replace('Comment','O',$allquestion['field']);
			$field = $allquestion['field'];
			$emailtrigger = 'none';
			if ($this->getValue('email',$allquestion) !== false){
				$emailtrigger = $this->getValue('trigger', $allquestion['email'], 'none');
			}
			$type = $allquestion['type'];
            $array = array(  'field'=>str_replace('question','Q',$field),
                             'active'=>$active,
							 'type'=>$type,
                             'question'=>strip_tags($allquestion['title']),
							 'negative'=>$this->getValue('negative',$allquestion,false),
                             'show'=>(strlen($field) < 4),
                             'trigger'=>$this->getValue('trigger',$allquestion),
							 'emailtrigger'=>$emailtrigger
                            );

			// add last 24 hour stat
			if ($dayFlag){
				$array['day'] = $this->getValue($field.'M',$survey24Hours);
			}
			// add last 7 , last 30 and year to date stats
			else {
				$array = array_merge($array,array(
					'last7'=>$this->getValue($field.'M',$survey7),
					'last30'=>$this->getValue($field.'M',$survey30),
					'yeartodate'=>$this->getValue($field.'M',$surveyYear),
				));
			}
			$stats[] = $array;
        }
		return array('stats'=>$stats,
					 'statCounts'=>$statsCount
				 );

	}
	/**
	 * route to download CSV file
	 * @param string $slug
	 * @param mixed $group
	 */
    public function downloadcsvAction($slug,$group=false)
    {
		//$param = $this->getConfiguration($slug, $group);
        $repository = $this->getDoctrine()->getRepository('FgmsSurveyBundle:Questionnaire');
		$sluggroup = ($group != false) ? $group : '';
		$query = $repository->createQueryBuilder('q')
			->where('q.slug = :slug AND q.sluggroup = :sluggroup' )
			->setParameters(array('slug' =>$slug, 'sluggroup' =>$sluggroup))
			->orderBy('q.createDate','ASC')
			->getQuery();
		$response = new StreamedResponse();
		$response->setCallback(function() use ($query){
			$handle = fopen('php://output', 'w+');
			fputcsv($handle, array('Survey#','Date', 'Time','Group','Property','Room','Q1','Q2','Q3','Q4','Q5','Q6','Q7','Q8','Q9','Q10','Q11','Q12','Q13','Q14','Q15'),',');
			foreach ($query->getResult() as $item){
				fputcsv($handle, array($item->getId(),
									   $item->getCreateDate()->format('F j, Y'),
									   $item->getCreateDate()->format('h:i A'),
									   $item->getSluggroup(),
									   $item->getSlug(),
									   $item->getRoomNumber(),
									   $item->getQuestion1(),
									   $item->getQuestion2(),
									   $item->getQuestion3(),
									   $item->getQuestion4(),
									   $item->getQuestion5(),
									   $item->getQuestion6(),
									   $item->getQuestion7(),
									   $item->getQuestion8(),
									   $item->getQuestion9(),
									   $item->getQuestion10(),
									   $item->getQuestion11(),
									   $item->getQuestion12(),
									   $item->getQuestion13(),
									   $item->getQuestion14(),
									   $item->getQuestion15()
						));
			}
            fclose($handle);
        });

		$response->setStatusCode(200);
		$response->headers->set('Content-Type', 'application/force-download');
		$response->headers->set('Content-Type', 'text/csv; charset=utf-8');
		$response->headers->set('Content-Disposition', $response->headers->makeDisposition( ResponseHeaderBag::DISPOSITION_ATTACHMENT, $slug. '-export.csv'));
		$response->prepare($this->get('request'));
        return $response;
    }

	/**
	* This is to access all via a cron job an array of groups and slugs.
	*/
	public function crontriggerAction()
	{
		$key = $this->get('request')->query->has('key') ? $this->get('request')->query->get('key') : '';
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
		$template = $this->get('request')->query->has('template') ? $this->get('request')->query->get('template') : 'email-notification';
		$surveytest = $this->get('request')->query->has('survey') ? $this->get('request')->query->get('survey') : false;
		$emailFlag = $this->get('request')->query->has('email') ? true : false;
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
		$emailParam['fromEmail'] = array($this->getValue('address',$this->config['email']['from'],'webmaster@fifthgeardev.com')=>$this->getValue('name',$this->config['email']['from'],'Webmaster'));
		$emailParam['subject'] = sprintf($this->param['config']['rollup']['subject'], $this->param['property']['name']) ;
		if ($testFlag){
			$emailParam['recipient']['to'] = array('webmaster@fifthgeardev.com');
		}
		else {
			$emailParam['recipient'] = $this->param['config']['rollup']['recipient'];
		}

		$this->sendEmail($emailParam, $this->param,'email-rollup');
	}

	/**
	 *
	 * @param string $slug
	 * @param string $group
	 * @param form $form
	 * @param string $room
	 *
	 */
	private function checkSurveyResults($slug,$group,$form,$room)
	{
		$emailParam = array();
		$emailParam['fromEmail'] = array($this->getValue('address',$this->config['email']['from'],'webmaster@fifthgeardev.com')=>$this->getValue('name',$this->config['email']['from'],'Webmaster'));
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
				$item['roomNumber'] = $room;
				$item['title'] = strip_tags($item['title']);
				// adds webmaster to bcc
				$item['email']['recipient']['bcc'] = isset($item['email']['recipient']['bcc']) ? array_merge($item['email']['recipient']['bcc'],array('webmaster@fifthgeardev.com')) : array('webmaster@fifthgeardev.com');
				$emailParam['recipient'] = $item['email']['recipient'];
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

	/*
	 * Gets Survey Rollup with specific interval
	 *
	 * @param string $property this is property slug
	 * @param string $timeInterval is the time interval for the last x time
	 * @param array $allquestions is an array of all the questions
	 */
    private function getSurveyRollup($slug, $group, $timeInterval="7 DAY", $allquestions)
    {
		$timezone = $this->getValue('timezone',$this->param['property'],'America/Vancouver');
		$time = new \DateTime('now', new \DateTimeZone($timezone));
		$timezoneOffset = intval($time->format('O'))/100;
		$this->logger->info('Timezone Offset:: '.$timezoneOffset);
		//$timeInterval .= " $timezoneOffset HOURS";
        $em = $this->getDoctrine()->getManager();
        $sql = "SELECT createDate, count(*) as `count`,  ";
        $s = array();

		foreach ($allquestions as $question){
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
			else {

			}
		}
        $sql .= implode(', ',$s) .' ';
        $sql .= "FROM questionnaire s  WHERE s.createDate > (NOW()  - INTERVAL {$timeInterval}) AND s.slug = '{$slug}'";
		if ($group != false){
			$sql .= " AND s.sluggroup = '{$group}'";
		}
		//$this->logger->info('sql:: '. $sql);
        return $em->getConnection()->fetchAssoc($sql);
    }

    /**
	 * Gets the form to display for survey
	 *
	 * @param string $property is the property slug
	 * @param string $roomNumber is the room number
	 * @param array $questions this is an array of all the questions
	 * @return form
	 */
    private function getForm($slug,$group, $roomNumber='none', $set="Default")
	{
		$questionObject = new Questionnaire();
		$questionObject->setCreateDate();
        $questionObject->setSlug($slug);
		$questionObject->setSluggroup($group);
		$questionObject->setQuestionSet($set);
		if ($questionObject->getRoomNumber() == null){
            $questionObject->setRoomNumber($roomNumber);
		}
		$form = $this->createFormBuilder($questionObject, array('csrf_protection' => true))
			->add('question1','hidden')
			->add('question2','hidden')
			->add('question3','hidden')
			->add('question4','hidden')
			->add('question5','hidden')
			->add('question6','hidden')
			->add('question7','hidden')
			->add('question8','hidden')
			->add('question9','hidden')
			->add('question10','hidden')
			->add('question11','hidden')
			->add('question12','hidden')
			->add('question13','hidden')
			->add('question14','hidden')
			->add('question15','hidden')
			->getForm();
		return $form;
	}

	private function getFormSymfony3($slug,$group, $roomNumber='none', $set="Default")
	{
		$questionObject = new Questionnaire();
		$questionObject->setCreateDate();
        $questionObject->setSlug($slug);
		$questionObject->setSluggroup($group);
		$questionObject->setQuestionSet($set);
		if ($questionObject->getRoomNumber() == null){
            $questionObject->setRoomNumber($roomNumber);
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

}
