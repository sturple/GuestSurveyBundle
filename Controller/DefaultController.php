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
	
    public function indexAction()
    {
        return $this->render('FgmsSurveyBundle:Default:index.html.twig', array());
    }
    
    public function startAction($slug,$group=false)
    {
		$param = $this->getConfiguration($slug,$group);       	
        return $this->render('FgmsSurveyBundle:Default:start.html.twig', $param);   
    }

    public function finishAction($slug,$group=false)
    {
		$param = $this->getConfiguration($slug, $group);        
		return $this->render('FgmsSurveyBundle:Default:finish.html.twig', $param);   
    }
    
    public function downloadcsvAction($slug,$group=false)
    {
		$param = $this->getConfiguration($slug, $group); 
        $em = $this->getDoctrine()->getManager();
        $sql = "SELECT
				s.createDate,
				s.slug,
				s.sluggroup
		";
 
 
        $sql .= "FROM questionnaire s WHERE s.slug = '{$slug}'";
		if ($group != false){
			$sql .= " AND s.sluggroup = '{$group}'";
		}
		$this->logger->error($sql);
        $rows =  $em->getConnection()->fetchAssoc($sql);
		$this->logger->error('results:: '.print_R($rows));
       // $repository = $this->getDoctrine()->getRepository('FgmsSurveyBundle:Questionnaire'); 
       // $data = $repository->findAll();		
        
		$response = new StreamedResponse();		
	
		$response->setCallback(function(){
			$handle = fopen('php://output', 'w+');
			fputcsv($handle, array('Email', 'Last Name'),',');			
           // fclose($handle);
        });
        
        
        //$response = $this->render('FgmsSurveyBundle:Default:csv.html.twig',array('data'=>$data));  
		//$this->logger->notice('ORDERS '.print_R($orders,true));
		$response->setStatusCode(200);
		$response->headers->set('Content-Type', 'application/force-download');
		$response->headers->set('Content-Type', 'text/plain; charset=utf-8');
		;
		$response->headers->set('Content-Disposition', $response->headers->makeDisposition( ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'export.txt'));
		//$response->headers->set('Content-Disposition','attachment; filename=export.txt');
		$this->get('logger')->error(print_r($response->getStatusCode(),true));
		$response->prepare($this->get('request'));
      
       // $response->headers->set('Content-Disposition', 'attachment; filename="export.csv"');        
        
/*
		$response = new StreamedResponse();	
		$response->setCallback(function(){
			$handle = fopen('php://output', 'w+');
			fputcsv($handle, array('Email', 'Last Name','First Name', 'Date','Total Spent'),',');			

			foreach ($data as $item){					
				fputcsv($handle, array($item->question1));					
				
			}
            fclose($handle);
			
		});	
		
		$response->headers->set('Content-Type', 'application/force-download');
		$response->headers->set('Content-Disposition','attachment; filename="export.csv"');*/
        
        return $response;
    
    
    
    }
    
    
    public function resultsAction($slug,$group=false)     {
      
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
    
	public function emailpreviewAction($slug,$group=false){
		$param = $this->getConfiguration($slug,$group);
		$this->sendEmails($slug, $group,false,$param,'testroom');
		return $this->render('FgmsSurveyBundle:Email:email-base.html.twig',$param ); 
	}
    
    private function sendEmails($slug, $group, $form,$param,$room){
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
			
            $trigger = isset($item['email']['trigger']) ? $item['email']['trigger'] : false;
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
            
			
            if ($emailFlag){
				$item['answer'] = $data;
				$item['roomNumber'] = $room;
				$emailSubject = sprintf($item['email']['subject'],  str_replace('question','Q',$item['field']), $room);          
				$l->error('trigger '.$trigger. ' value ' . $data . $emailSubject);
				$combined_array = array_merge($param, array('item'=>$item));					
				$message = \Swift_Message::newInstance()
                    ->setSubject($emailSubject)
                    ->setFrom('postmaster@fifthgeardev.net')
                    ->setTo('shawn@turple.ca')
                    ->setBody($this->renderView('FgmsSurveyBundle:Email:email-base.html.twig', $combined_array ),'text/html');
                //$this->get('mailer')->send($message);	  
				
				
            }            
            
        }        
        
      
    }
    
	/*
	 * gets configurations for property and sets the images path 
	 *
	 *@param string $slug this is the slug or permalink of property
	 *@param string/boolean $groupslug if no option passed just uses slug otherwise uses it as a folder name
	 *
	 */
	private function getConfiguration($slug, $group=false){
		if ($this->logger === false){
			$this->logger = $this->get('logger');
		}
		$this->fullSlug = ($group !== false) ? $group.'/': '';
		$this->fullSlug .= $slug;
		$config_defaults = array('images_path'=>'/web/assets/images/',
								 'property_config_dir'=>$this->get('kernel')->getRootDir().'/config/property/');
        $yaml = new Parser();
		$config_file = $this->get('kernel')->getRootDir().'/config/survey.yml';
		try {
			$this->config = $yaml->parse(file_get_contents($config_file));
			if (count($this->config) > 0){
				$this->config['property_config_dir'] = $this->get('kernel')->getRootDir().$this->getValue('property_config_dir', $this->config,'/config/property/');				
				$this->config['images_path'] = $this->getValue('images_path', $this->config,'/web/assets/images/').$this->fullSlug.'/';
			}			
		}
		catch (ParseException $e){	$this->get('logger')->error('parse error '.print_R($e->getMessage(),true));}
		$this->config = array_merge($config_defaults, $this->config);		
		$param = array();
		if (!is_dir($this->config['property_config_dir'])){
			$error_str = 'Property Config Directory "' . $this->config['property_config_dir'] .'" does not exists';
			throw $this->createNotFoundException($error_str);
		return array('error'=>$error_str );
		}

		$property_file = $this->config['property_config_dir'] .$this->fullSlug.".yml";
		
		if (!file_exists($property_file)){
			$error_str = 'Property Config file "' . $property_file .'" does not exists';
			throw $this->createNotFoundException($error_str);
			return array('error'=>$error_str );
		}
		try {
			$param = $yaml->parse(file_get_contents($property_file));
		}catch (ParseException $e) {
			$this->get('logger')->error('parse error '.print_R($e->getMessage(),true));
		}
		$this->logger->error('CONFIG File '.$property_file);
		$param['fullslug'] = $this->fullSlug;
		return array_merge($param,$this->config);

	
	}
	

	/*
	 * Gets Survey Rollup with specific interval
	 *
	 * @param string $property this is property slug
	 * @param string $timeInterval is the time interval for the last x time
	 * @param array $allquestions is an array of all the questions
	 *
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
     
    
    /*
	 *
	 * Gets the form to display for survey
	 *
	 * @param string $property is the property slug
	 * @param string $roomNumber is the room number
	 * @param array $questions this is an array of all the questions 
	 *
	 *
	 */
    private function getForm($slug,$group, $roomNumber='none'){      
        
        $questionObject = new Questionnaire();
        $questionObject->setCreateDate();
        $questionObject->setSlug($slug);
		$questionObject->setSluggroup($group);
		if ($questionObject->getRoomNumber() == null){   
            $questionObject->setRoomNumber($roomNumber);
		}
		/*
        if ($questionObject->getQuestionSet() == null){
            $questionObject->setQuestionSet(strip_tags(json_encode($questions)));
        }
        */
		
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
	
	private function getValue($key, $array=array(), $default=false){
		if (isset($array[$key])){
			return $array[$key];
		}
		return $default;
	}
}
