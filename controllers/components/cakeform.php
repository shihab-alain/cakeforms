<?php
class CakeformComponent extends Object{
    
        public $components = array('RequestHandler', 'Email');

/**
 * Holds the data required to build the form
 *
 * @var array
 * @access public
 */
        public $formData;
        
/**
 * Holds the path to the view variable  in $this->Controller->viewVars
 * which contains the {cform_ID} tag
 *
 * $this->viewVar = 'page/Page/content'
 * would be the path for a view variable $page['Page']['content']
 *
 * Overridden with the controller beforeFilter() or Component init
 *
 * @var string
 * @access public
 */        
        public $viewVar = null;
        
        
/**
 * Which action/views the component will look for
 * the {cform_ID} tag and replace it with the form,
 * where ID is the id of the form to show
 *
 * Overridden with the controller beforeFilter() or Component init
 *
 * @var string
 * @access public
 */        
        public $actions = array();
        
        
/**
 * Pointer to view variable which contains content to check for
 * {cform_ID} tag
 *
 * @var array
 * @access public
 */        
        public $content;

/**
 * Whether or not the form has been successfuly submitted
 *
 * @var boolean
 * @access public
 */
        public $submitted = false;
        
/**
 * Sets Controller values, loads Cform.Form model
 *
 * @param string $content Content to render
 * @return array Email ready to be sent
 * @access public
 */        
        function initialize(&$controller, $settings = array()) {
                $this->Controller =& $controller;
                $this->Form = ClassRegistry::init('Cforms.Form');;
                
                if(!empty($settings['email'])){
                        foreach($settings['email'] as $key => $setting){
                                $this->Email->{$key} = $setting;
                        }
                }
                
                if(!empty($settings['viewVar'])){
                        $this->viewVar = $settings['viewVar'];
                }
                
                if(!empty($settings['actions'])){
                        if(is_string($settings['actions'])){
                                $settings['actions'] = array($settings['actions']);
                        }
                        $this->actions = $settings['actions'];
                }
        }
 
/**
 * Loads Cform helper.
 * Checks for form submission, if so, calls $this->submit() to process it
 *
 * @access public
 */         
        function startup(&$controller){          
            $this->Controller = &$controller; 
            $this->Controller->helpers[] = "Cforms.Cakeform";
        
            if(!empty($this->Controller->data['Cform']['submitHere']) && $this->Controller->data['Cform']['id']){
                   $this->submit();
            }
        }     
 
/**
 * If autoParse is set to true, gets view variable content
 * and replaces it with rendered content
 *
 * @access public
 */          
        function beforeRender(&$controller){          
            $this->Controller = &$controller;
            
            if(!empty($this->viewVar) && in_array($this->Controller->action, $this->actions)){
                        if($this->getContent()){
                                $this->content = $this->insertForm($this->content);
                        }
                }
        }
        
  
/**
 * sets $this->content to the content of the view variable
 *
 * @access public
 */        
        function getContent(){
                $content_to_replace = '';
                $keys = explode('/', $this->viewVar);
                $this->content =& $this->Controller->viewVars;
                
                foreach($keys as $key){
                    $this->content =& $this->content[$key];
                }
                
                if(!empty($this->content) && is_string($this->content)){
                        return true;
                } else {
                       return false; 
                }
        }

/**
 * parses $this->content and replaces {cform_ID} with the form
 *
 * @param string $content The content to parse
 *
 * @access public
 */                
        function insertForm($content){
                $newcontent = '';
                //$pattern = '/({cform_)([0-9])*[}]/';

                $start = strpos($content, '{cform_');
                $end = strpos($content, '}', $start);
                $replace = substr($content, $start, $end + 1 - $start);
                
                if(strlen($replace) > 8){ #make sure it at least the length of {cform_1}
                        $length = strlen($replace) - 2;
                        
                        $formId = substr($replace, 1, $length);
                        $formId = explode('_', $formId);
                        $formId = $formId[1];
                        
                        $formData = $this->loadForm($formId);
                        
                        if(!empty($formData)){
                                $newcontent = $this->__renderForm($formData);
                        }

                        if(!empty($newcontent)){
                                $content = str_replace($replace, $newcontent, $content);
                        }                        
                }
                
                return $content;
        }  
        
/**
 * Render the form
 *
 * @param string $formData Data used to build form
 * 
 * @return string The rendered form
 * @access private
 */
	function __renderForm($formData) {
                $content = '';
                
		$viewClass = $this->Controller->view;
		if ($viewClass != 'View') {
			if (strpos($viewClass, '.') !== false) {
				list($plugin, $viewClass) = explode('.', $viewClass);
			}
			$viewClass = $viewClass . 'View';
			App::import('View', $this->Controller->view);
		}
		
                $View = new $viewClass($this->Controller);
                $View->plugin = 'cforms';
                $content = $View->element('form', array('formData' => $formData), true);
                
                ClassRegistry::removeObject('view');
                
                return $content;
	}
        
/**
 * Loads the data to create the form, calls model to build
 * schema and validation
 *
 * @return array Data used to build form
 * @access public
 */                
        function loadForm($id){
            if(empty($this->formData) || $this->formData['Cform']['id'] != $id){
                $this->formData = $this->Form->buildSchema($id);
            }
            
            $this->formData['Cform']['submitted'] = $this->submitted;
            
            return $this->formData;
        }

/**
 * Processes form
 *
 * @return bool true if form is successfuly saved to db
 * @access public
 */        
        function submit(){
            $id = $this->Controller->data['Cform']['id'];
    
            $this->loadForm($id);
                
            $validate = $this->Controller->data;
            foreach($validate['Form'] as &$field){
                if(is_array($field)){
                        $field = implode("\n", $field);
                }
            }

            $this->Form->set($validate);
            if($this->Form->validates()){
                    $this->submitted = true;
                    $this->formData['Cform']['submitted'] = true;
                    if(!empty($this->formData['Cform']['next'])){
                            $this->Session->write('Cform.form.' .  $id, $this->Controller->data['Cform']);
                    } else {
                            if(!empty($this->Controller->data['Form']['email'])){
                                    $this->Controller->data['Submission']['email'] = $this->Controller->data['Form']['email'];
                            }
                            $this->Controller->data['Submission']['cform_id'] = $id;
                            
                            App::import('Model', 'Cforms.Submission');
                            $this->Submission = new Submission;                            
                            
                            $this->Submission->Cform->id = $id;
                            $formName = $this->Submission->Cform->field('name');
                            
                            $this->Controller->data['Submission']['name'] = $formName;
                            $this->Controller->data['Submission']['ip'] = ip2long($this->RequestHandler->getClientIP());
                            $this->Controller->data['Submission']['page'] = $this->Controller->here;
                            unset($this->Controller->data['Cform']['id']);
                            unset($this->Controller->data['Cform']['submitHere']);
                            
                            if($this->Submission->submit($this->Controller->data)){
                                $this->send($this->formData['Cform'], $this->Controller->data);
                                
                                if(!empty($this->formData['Cform']['redirect'])){
                                        $this->redirect($this->formData['Cform']['redirect']);
                                }
                                return true;
                            } else {
                                return false;
                            }
                    }
            }
        }

/**
 * Emails form
 * 
 * @todo allow configuration
 * 
 * @return bool true if form is successfuly sent
 * @access public
 */           
	function send($formData, $response){
                if(!empty($formData['recipient'])){
                        $this->Email->to = $formData['recipient'];
                }
                
                if(empty($this->Email->from)){
                        $this->Email->from = $this->Email->to;
                }
                
		$this->Email->subject = "New '{$formData['name']}' Submission";
		$this->Email->sendAs = 'both';

                $plugin = $this->Controller->plugin;
                $this->Controller->plugin = 'cforms';
		$this->Email->template = 'submission';
                
                $this->Controller->set(compact('formData', 'response'));
		$success = $this->Email->send();
                $this->Controller->plugin = $plugin;
                
                return $success;
	}
        
    }
    ?>