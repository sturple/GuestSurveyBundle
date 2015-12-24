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

define('CONFIG_DIR', __DIR__. '/../Resources/config/');

class DefaultController extends Controller
{
	
	
    public function indexAction()
    {
        return $this->render('FgmsSurveyBundle:Default:index.html.twig', array());
    }
    
    public function startAction($property)
    {
		$param = $this->getConfiguration($property);       	
        return $this->render('FgmsSurveyBundle:Default:start.html.twig', $param);   
    }

    public function finishAction($property)
    {
		$param = $this->getConfiguration($property);        
		return $this->render('FgmsSurveyBundle:Default:finish.html.twig', $param);   
    }
    
    public function downloadcsvAction($property)
    {
      
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
    
    
    public function resultsAction($property) 
    {
      
		$param = $this->getConfiguration($property);	
		
        
        // getting each interval
        $survey7 = $this->getSurveyRollup($property,'7 DAY', $param['questions']);
        $survey30 = $this->getSurveyRollup($property,'30 DAY', $param['questions']);
        $surveyYear = $this->getSurveyRollup($property,'1 YEAR', $param['questions']);
              
        
        $stats = array();
        // setting up stats.
        foreach($param['questions'] as $allquestion){           
            $active = 'Yes';
            $field = str_replace('question','Q',$allquestion['field']);
            $stats[] = array('field'=>$field,
                             'active'=>$active,
                             'question'=>strip_tags($allquestion['title']),
							 'negative'=>$allquestion['negative'],
                             'show'=>(strlen($field) < 4) ,
                             'last7'=>$survey7[$field],
                             'last30'=>$survey30[$field],
                             'yeartodate'=>$surveyYear[$field],
                             'trigger'=>$allquestion['trigger'],
							 'emailtrigger'=>isset($allquestion['email']['trigger']) ? $allquestion['email']['trigger'] : 'none'
                            );
                             
            
        }
        $param['stats'] = $stats;
        return $this->render('FgmsSurveyBundle:Default:results.html.twig',$param ); 
    }
    
    
    
    public function surveyAction($property)
    {
        $room = $this->get('request')->query->has('room') ? $this->get('request')->query->get('room') : 'none';

        $param = $this->getConfiguration($property);		
        $form = $this->getForm($property, $room, $param );
        
		$form->handleRequest($this->get('request'));
		//$this->sendEmails($property,$form,$param,$room);  
		if ($form->isValid()){			
			$em = $this->getDoctrine()->getManager();
			$em->persist($form->getData());
			$this->sendEmails($property,$form,$param,$room);
			$em->flush();
          
			return $this->redirect("/{$property}/finish/");
		}        
        $param['form'] = $form->createView();       
        return $this->render('FgmsSurveyBundle:Default:survey.html.twig', $param );
    }
    
	public function emailpreviewAction($property){
		$param = $this->getConfiguration($property);
		$this->sendEmails($property,false,$param,'testroom');
		return $this->render('FgmsSurveyBundle:Email:email-base.html.twig',$param ); 
	}
    
    private function sendEmails($slug,$form,$param,$room){
        $l = $this->get('logger');
		$param = $this->getConfiguration($slug);
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
			
            $trigger = $item['email']['trigger'];
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
                $message = \Swift_SmtpTransport::newInstance()
                    ->setSubject($emailSubject)
                    ->setFrom('postmaster@fifthgeardev.net')
                    ->setTo('shawn@turple.ca')
                    ->setBody(
                        $this->renderView('FgmsSurveyBundle:Email:email-base.html.twig', $combined_array )             
                      
                        ,
                        'text/html'
                    );
                $this->get('mailer')->send($message);
				
				
            }             
            
        }        
        
      
    }
    

	private function getConfiguration($slug){		
        $yaml = new Parser();
		$file = CONFIG_DIR ."{$slug}.yml";
		$param = array();
		$property = array();
		try { $param = $yaml->parse(file_get_contents($file));} catch (ParseException $e) {	$this->get('logger')->error('parse error '.print_R($e->getMessage(),true));	}
		return $param;
		$this->get('logger')->error('file: '. $file. ' param ' . print_R($param,true));
        $a = array('property'=>$param['property']['slug'],
                   'name'=>$param['name'],
                   'questions'=>$param['questions'],
                   'allquestions'=>$param['questions'],
                   'room'=>$this->get('request')->query->has('room') ? $this->get('request')->query->get('room') : 'none',
                   'setting'=>$param['config'],
				   'all'=>$param);
                    
        return $a;		
	}
	
    private function getSurveyRollup($property, $timeInterval="7 DAY", $allquestions)
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
        $sql .= "FROM questionnaire s  WHERE s.createDate > (NOW() - INTERVAL {$timeInterval}) AND s.property = '{$property}'";        
        return $em->getConnection()->fetchAssoc($sql);


    }
     
    
    
    private function getForm($property, $roomNumber='none', $param){      
        
        $questionObject = new Questionnaire();
        $questionObject->setCreateDate();
        $questionObject->setProperty($property);
		if ($questionObject->getRoomNumber() == null){   
            $questionObject->setRoomNumber($roomNumber);
		}
        if ($questionObject->getQuestionSet() == null){
            $questionObject->setQuestionSet(strip_tags(json_encode($param['questions'])));
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
    

    
}
