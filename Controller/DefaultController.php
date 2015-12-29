<?php

namespace Fgms\Bundle\SurveyBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use \Fgms\Bundle\SurveyBundle\Entity\Questionnaire;
use Doctrine\ORM\Query\ResultSetMapping;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Config\Loader\FileLoader;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Parser;

class DefaultController extends Controller
{
	
	var $config = array();
	var $logger = false;
	
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
	 *
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
	 *
	 */
    public function surveyAction($slug,$group=false)
    {
        $room = $this->get('request')->query->has('room') ? $this->get('request')->query->get('room') : 'none';
        $param = $this->getConfiguration($slug,$group);		
        $form = $this->getForm($slug,$group,$room);        
		$form->handleRequest($this->get('request'));
		if ($form->isValid()){			
			$em = $this->getDoctrine()->getManager();
			$em->persist($form->getData());
			$this->sendEmails($slug,$group,$form,$param,$room);
			$em->flush();          
			return $this->redirect("/". $this->fullSlug. "/finish/");
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
		$param = $this->getConfiguration($slug,$group);
		$showResultsFlag = false;
		$key = $this->get('request')->query->has('key') ? $this->get('request')->query->get('key') : '--no key--';
		
		if ($this->getValue('reporting',$param,false) != false){
			$this->logger->error('Auth::level1');
			if ($this->getValue('group',$param['reporting'],false) != false){
				$this->logger->error('Auth::level2');
				if ($this->getValue('properties',$param['reporting']['group'],false) != false){
					$this->logger->error('Auth::level3 '.print_R($param['reporting']['group']['properties'],true));
					foreach ($this->getValue('properties',$param['reporting']['group'],array() ) as $adminAuth){
						$this->logger->error('AUTH::: '.$key.':'.$adminAuth['token']);
						if ($key == $adminAuth['token']){
							$showResultsFlag = true;
							break;
						}
					}
				}
			}
		}
        if ($showResultsFlag === false ){
			$error_str = 'Authentication is not correct';
			throw $this->createNotFoundException($error_str);
			return;
		}
        // getting each interval
        $survey7 = $this->getSurveyRollup($slug,$group,'7 DAY', $param['questions']);
        $survey30 = $this->getSurveyRollup($slug,$group,'30 DAY', $param['questions']);
        $surveyYear = $this->getSurveyRollup($slug,$group,'1 YEAR', $param['questions']);              
        
        $stats = array();
        // setting up stats.
        foreach($param['questions'] as $allquestion){           
            $active = 'Yes';
            $field = str_replace('question','Q',$allquestion['field']);
			$emailtrigger = 'none';
			if ($this->getValue('email',$allquestion) !== false){
				$emailtrigger = $this->getValue('trigger', $allquestion['email'], 'none');
			}
            $stats[] = array('field'=>$field,
                             'active'=>$active,
                             'question'=>strip_tags($allquestion['title']),
							 'negative'=>$this->getValue('negative',$allquestion,false),
                             'show'=>(strlen($field) < 4) ,
                             'last7'=>$this->getValue($field,$survey7),
                             'last30'=>$this->getValue($field,$survey30),
                             'yeartodate'=>$this->getValue($field,$surveyYear),
                             'trigger'=>$this->getValue('trigger',$allquestion),
							 'emailtrigger'=>$emailtrigger
                            );                            
            
        }
        $param['stats'] = $stats;
        return $this->render('FgmsSurveyBundle:Default:results.html.twig',$param ); 
    }
    
	/**
	 *
	 * route to download CSV file
	 * @param string $slug
	 * @param mixed $group
	 *
	 */
    public function downloadcsvAction($slug,$group=false)
    {
		$param = $this->getConfiguration($slug, $group);		
        $repository = $this->getDoctrine()->getRepository('FgmsSurveyBundle:Questionnaire');
		$sluggroup = ($group != false) ? $group : '';
		$query = $repository->createQueryBuilder('q')		
			->where('q.slug = :slug AND q.sluggroup = :sluggroup')
			->setParameters(array('slug' =>$slug, 'sluggroup' =>$sluggroup))
			->orderBy('q.createDate','ASC')
			->getQuery();		
		$this->logger->error('SQL: ' . $query->getSQL());
		$response = new StreamedResponse();		
	
		$response->setCallback(function() use ($query){				
			$handle = fopen('php://output', 'w+');
			fputcsv($handle, array('Date', 'Time','Group','Property','Room','Q1','Q2','Q3','Q4','Q5','Q6','Q7','Q8','Q9','Q10','Comment','id'),',');			
			foreach ($query->getResult() as $item){					
				fputcsv($handle, array($item->getCreateDate()->format('F j, Y'),
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
									   $item->getQuestionComment(),
									   $item->getId()						
						));		
			}
            fclose($handle);
        });
		
		$response->setStatusCode(200);
		$response->headers->set('Content-Type', 'application/force-download');
		$response->headers->set('Content-Type', 'text/csv; charset=utf-8');
		$response->headers->set('Content-Disposition', $response->headers->makeDisposition( ResponseHeaderBag::DISPOSITION_ATTACHMENT, $slug. '-export.csv'));
		$this->get('logger')->error(print_r($response->getStatusCode(),true));
		$response->prepare($this->get('request'));        
        return $response;    
    } 

    
	/**
	 * route to preview email
	 * @param string $slug
	 * @param mixed $group
	 */
	public function emailpreviewAction($slug,$group=false)
	{
		$param = $this->getConfiguration($slug,$group);
		$this->sendEmails($slug, $group,false,$param,'testroom');
		return $this->render('FgmsSurveyBundle:Email:email-base.html.twig',$param ); 
	}    
	
	/**
	 *
	 * @param string $slug
	 * @param string $group
	 * @param form $form
	 * @param array $param
	 * @param string $room
	 *
	 */
    private function sendEmails($slug, $group, $form,$param,$room)
	{
        $l = $this->get('logger');
		$param = $this->getConfiguration($slug,$group);
        foreach ($param['questions'] as $item){
			$emailFlag = false;
            $field = $item['field'];
            $data = array();       
			if ($form === false){
				$data = '10';
			}
			else {
				$data = $form->get($field)->getData();    
			}
			if ($this->getValue('email',$item,false) == false){
				continue;
			}
            $trigger = $this->getValue('trigger',$item['email']);
			$triggerType = intval($data);
			//comparison type
			if (($triggerType > 0) && ($trigger != null)){				
				if ($triggerType > 0 ){	$emailFlag = (intval($trigger) >= intval($data) );}
				// equates type
				else { $emailFlag = ($trigger == $data);}
								
			}
			if ( ($item['type'] == 'comment') && (strlen($data) > 0) ){
				$emailFlag = true;
			}
            
			// this means that an email trigger has been tripped need to send email.
            if ($emailFlag){
				$item['answer'] = $data;
				$item['roomNumber'] = $room;
				$item['title'] = strip_tags($item['title']);
				$item['email']['recipient']['bcc'] = array_merge($item['email']['recipient']['bcc'],array('webmaster@fifthgeardev.com'));
				
				$emailSubject = sprintf($item['email']['subject'], $room) .' ('.$param['property']['name'].')';          
				$l->error('trigger '.$trigger. ' value ' . $data . $emailSubject);
				$combined_array = array_merge($param, array('item'=>$item));					
				$message = \Swift_Message::newInstance()
                    ->setSubject($emailSubject)
                    ->setFrom('guest.response@guestfeedback.net');
				// setting up recipients
				if ($this->getValue('recipient',$item['email']) != false){
					if ($this->getValue('to',$item['email']['recipient']) != false){
						$message  = $message->setTo($item['email']['recipient']['to']);						
					}
					if ($this->getValue('cc',$item['email']['recipient']) != false){						
						$message  = $message->setCc($item['email']['recipient']['cc']);
						
					}
					if ($this->getValue('bcc',$item['email']['recipient']) != false){						
						$message  = $message->setBcc($item['email']['recipient']['bcc']);						
					}					
				}
				$message = $message                   
                    ->setBody($this->renderView('FgmsSurveyBundle:Email:email-base.html.twig', $combined_array ),'text/html')
					->addPart($this->renderView('FgmsSurveyBundle:Email:email-base.txt.twig', $combined_array ),'text/plain');
                $this->get('mailer')->send($message);	
            }  
        } 
    }
	
	/*
	 * gets configurations for property and sets the images path 
	 *
	 * @param string $slug this is the slug or permalink of property
	 * @param string/boolean $groupslug if no option passed just uses slug otherwise uses it as a folder name
	 *
	 */
	private function getConfiguration($slug, $group=false)
	{
		//setting up defaults
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
				$param['key'] = $this->get('request')->query->has('key') ? $this->get('request')->query->get('key') : false;
				
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
		return array_merge($param,$this->config);	
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
        $em = $this->getDoctrine()->getManager();
        $sql = "SELECT createDate,   ";
        $count = 1;
        $limit = 9;  
            
        $s = array();
        while ($count < $limit){
            // this logic is required for a negative question response
            $negativeFlag = false;
            foreach ($allquestions as $question){
                if ($question['field'] == 'question'.$count){
                    $negativeFlag = (isset($question['negative']) ) ? $question['negative'] : false;
                }
            }           
            $yesText = $negativeFlag ? 'No' : 'Yes';
            $noText = $negativeFlag ? 'Yes' : 'No' ;             
            $s[] = "IF ( (s.question{$count} REGEXP '[0-9]') = 1 , AVG(s.question{$count}), (sum(if(s.question{$count}='{$yesText}',1,0))/(sum(if(s.question{$count}='{$yesText}',1,0))+sum(if(s.question{$count}='{$noText}',1,0)))*100)) as `Q{$count}`" ;
            ++$count;
        }
        $sql .= implode(', ',$s) .' ';
        $sql .= "FROM questionnaire s  WHERE s.createDate > (NOW() - INTERVAL {$timeInterval}) AND s.slug = '{$slug}'";
		if ($group != false){
			$sql .= " AND s.sluggroup = '{$group}'";
		}
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
    private function getForm($slug,$group, $roomNumber='none')
	{         
        $questionObject = new Questionnaire();
        $questionObject->setCreateDate();
        $questionObject->setSlug($slug);
		$questionObject->setSluggroup($group);
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
			->add('questionComment','hidden')
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
		if (isset($array[$key])){
			return $array[$key];
		}
		return $default;
	}
}
