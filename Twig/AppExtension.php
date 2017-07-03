<?php
namespace EMS\CoreBundle\Twig;

use EMS\CoreBundle\Form\DataField\DateFieldType;
use EMS\CoreBundle\Form\DataField\TimeFieldType;
use EMS\CoreBundle\Service\UserService;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use EMS\CoreBundle\Service\ContentTypeService;
use Elasticsearch\Client;
use Symfony\Component\Routing\Router;
use EMS\CoreBundle\Form\Factory\ObjectChoiceListFactory;
use Symfony\Component\Form\FormError;
use EMS\CoreBundle\Repository\I18nRepository;
use EMS\CoreBundle\Entity\I18n;
use EMS\CoreBundle\Entity\User;
use EMS\CoreBundle\Form\DataField\DateRangeFieldType;
use EMS\CoreBundle\Service\EnvironmentService;
use Monolog\Logger;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Form\FormFactory;
use EMS\CoreBundle\Entity\FieldType;
use EMS\CoreBundle\Entity\DataField;
use Elasticsearch\Common\Exceptions\Missing404Exception;

class AppExtension extends \Twig_Extension
{
	private $doctrine;
	private $userService;
	private $authorizationChecker;
	/**@var ContentTypeService $contentTypeService*/
	private $contentTypeService;
	/**@var Client $client */
	private $client;
	/**@var Router $router*/
	private $router;
	/**@var \Twig_Environment $twig*/
	private $twig;
	/**@var ObjectChoiceListFactory $objectChoiceListFactory*/
	private $objectChoiceListFactory;
	/** @var EnvironmentService */
	private $environmentService;
	/** @var Logger */
	private $logger;
	/**@var FormFactory*/
	protected $formFactory;
	
	public function __construct(Registry $doctrine, AuthorizationCheckerInterface $authorizationChecker, UserService $userService, ContentTypeService $contentTypeService, Client $client, Router $router, $twig, ObjectChoiceListFactory $objectChoiceListFactory, EnvironmentService $environmentService, Logger $logger, FormFactory $formFactory)
	{
		$this->doctrine = $doctrine;
		$this->authorizationChecker = $authorizationChecker;
		$this->userService = $userService;
		$this->contentTypeService = $contentTypeService;
		$this->client = $client;
		$this->router = $router;
		$this->twig = $twig;
		$this->objectChoiceListFactory = $objectChoiceListFactory;
		$this->environmentService = $environmentService;
		$this->logger = $logger;
		$this->formFactory = $formFactory;
		
		//$this->twig->getExtension('Twig_Extension_Core')->setEscaper('csv', array($this, 'csvEscaper'));
	}
	
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see Twig_Extension::getFunctions()
	 */
	public function getFunctions(){
		return [
				new \Twig_SimpleFunction('get_content_types', array($this, 'getContentTypes')),
				new \Twig_SimpleFunction('get_default_environments', array($this, 'getDefaultEnvironments')),
		];
	}
	
	/**
	 * 
	 * {@inheritDoc}
	 * @see Twig_Extension::getFilters()
	 */
	public function getFilters()
	{
		
		
		return array(
				new \Twig_SimpleFilter('searches', array($this, 'searchesList')),
				new \Twig_SimpleFilter('dump', array($this, 'dump')),
				new \Twig_SimpleFilter('data', array($this, 'data')),
				new \Twig_SimpleFilter('inArray', array($this, 'inArray')),
				new \Twig_SimpleFilter('firstInArray', array($this, 'firstInArray')),
				new \Twig_SimpleFilter('md5', array($this, 'md5')),
				new \Twig_SimpleFilter('convertJavaDateFormat', array($this, 'convertJavaDateFormat')),
				new \Twig_SimpleFilter('convertJavascriptDateFormat', array($this, 'convertJavascriptDateFormat')),
				new \Twig_SimpleFilter('convertJavascriptDateRangeFormat', array($this, 'convertJavascriptDateRangeFormat')),
				new \Twig_SimpleFilter('getTimeFieldTimeFormat', array($this, 'getTimeFieldTimeFormat')),
				new \Twig_SimpleFilter('soapRequest', array($this, 'soapRequest')),
				new \Twig_SimpleFilter('luma', array($this, 'relativeluminance')),
				new \Twig_SimpleFilter('contrastratio', array($this, 'contrastratio')),
				new \Twig_SimpleFilter('all_granted', array($this, 'all_granted')),
				new \Twig_SimpleFilter('one_granted', array($this, 'one_granted')),
				new \Twig_SimpleFilter('in_my_circles', array($this, 'inMyCircles')),
				new \Twig_SimpleFilter('data_link', array($this, 'dataLink')),
				new \Twig_SimpleFilter('get_content_type', array($this, 'getContentType')),
				new \Twig_SimpleFilter('get_environment', array($this, 'getEnvironment')),
				new \Twig_SimpleFilter('generate_from_template', array($this, 'generateFromTemplate')),
				new \Twig_SimpleFilter('objectChoiceLoader', array($this, 'objectChoiceLoader')),
				new \Twig_SimpleFilter('groupedObjectLoader', array($this, 'groupedObjectLoader')),		
				new \Twig_SimpleFilter('propertyPath', array($this, 'propertyPath')),			
				new \Twig_SimpleFilter('is_super', array($this, 'is_super')),					
				new \Twig_SimpleFilter('i18n', array($this, 'i18n')),						
				new \Twig_SimpleFilter('internal_links', array($this, 'internalLinks')),		
				new \Twig_SimpleFilter('get_user', array($this, 'getUser')),			
				new \Twig_SimpleFilter('displayname', array($this, 'displayname')),			
				new \Twig_SimpleFilter('date_difference', array($this, 'dateDifference')),	
				new \Twig_SimpleFilter('debug', array($this, 'debug')),
				new \Twig_SimpleFilter('search', array($this, 'search')),
				new \Twig_SimpleFilter('call_user_func', array($this, 'call_user_func')),
				new \Twig_SimpleFilter('macro_fct', array($this, 'macroFct')),
				new \Twig_SimpleFilter('url_generator', array($this, 'toAscii')),	
				
				
		);
	}
	
	/**
	 * Convert a tring into an url frendly string
	 * @param string $str
	 * @return string
	 */
	function toAscii(string $str) {
		$clean = iconv('UTF-8', 'ASCII//TRANSLIT', $str);
		$clean = preg_replace("/[^a-zA-Z0-9/_| -]/", '', $clean);
		$clean = strtolower(trim($clean, '-'));
		$clean = preg_replace("/[/_| -]+/", '-', $clean);
		
		return $clean;
	}
	
	
	function macroFct($tempate, $block, $context) {
		return $tempate->{'get'.$block}($context);
	}
	
	function call_user_func($function){
		return call_user_func($function);
	}
	
	function search(array $params){
		return $this->client->search($params);
	}

	function debug($message, array $context=[]){
		$context['twig'] = 'twig';
		$this->logger->addDebug($message, $context);
	}
	
	function dateDifference($date1, $date2, $detailed=false){
		$datetime1 = date_create($date1);
		$datetime2 = date_create($date2);
		$interval = date_diff($datetime1, $datetime2);
		if($detailed){
			return $interval->format('%R%a days %h hours %i minutes');			
		}
		return (intval($interval->format('%R%a'))+1).' days';	
	}
	
	function getUser($username){
		return $this->userService->getUser($username);
	}
	
	function displayname($username){
		/**@var User $user*/
		$user = $this->userService->getUser($username);
		if(!empty($user)){
			return $user->getDisplayName();
		}
		return $username;
	}

	function internalLinks($input){
		$url = $this->router->generate('data.link', ['key'=>'object:'], UrlGeneratorInterface::ABSOLUTE_PATH);
		$out = preg_replace('/ems:\/\/object:/i', $url, $input);
		
		$path = $this->router->generate('ems_file_view', ['sha1' => '__SHA1__'], UrlGeneratorInterface::ABSOLUTE_PATH );
		$path = substr($path, 0, strlen($path)-8);
		$out= preg_replace_callback(
			'/(ems:\/\/asset:)([^\n\r"\'\?]*)/i',
			function ($matches) use ($path) {
					return $path.$matches[2];
			},
			$out
		); 
		
		return $out;
	}
	
	
	function i18n($key, $locale=null){

		if(empty($locale)) {
			$locale = $this->router->getContext()->getParameter('_locale');
		}
		/**@var I18nRepository $repo */
		$repo = $this->doctrine->getManager()->getRepository('EMSCoreBundle:I18n');
		/**@var I18n $result*/
		$result = $repo->findOneBy([
				'identifier' => $key,
		]);

		if(empty($result)){
			return $key;
		}
		
		return $result->getContentTextforLocale($locale);
	}
	
	private function superizer($role){
		if(strpos($role, '_SUPER_')){
			return $role;
		}
		return str_replace('ROLE_', 'ROLE_SUPER_', $role);
	}
	
	function is_super($empty) {
		foreach($this->userService->getCurrentUser()->getRoles() as $role){
			if(strpos($role, '_SUPER_')){
				return true;
			}
		}
		return false;
	}
	
	function all_granted($roles, $super=false){
		foreach ($roles as $role){
			if(!$this->authorizationChecker->isGranted($super?$this->superizer($role):$role)){
				return false;
			}
		}
		return true;
	}
	
	function inMyCircles($circles){
		
		if(!$circles){
			return true;
		}
		else if ($this->authorizationChecker->isGranted('ROLE_ADMIN')){
			return true;
		}
		else if (is_array($circles)){
			if(count($circles) > 0){
				$user = $this->userService->getCurrentUser();
				return count(array_intersect($circles, $user->getCircles())) > 0;
			}
			else {
				return true;
			}
		}
		else if(is_string($circles)){
			return in_array($circles, $user->getCircles());
		}
		
		
		return false;
	}
	
	function objectChoiceLoader($contentTypeName) {
		return $this->objectChoiceListFactory->createLoader($contentTypeName, true)->loadAll();
	}
	
	function groupedObjectLoader($contentTypeName) {
		$choices = $this->objectChoiceListFactory->createLoader($contentTypeName, true)->loadAll();
		$out = [];
		foreach ($choices as $choice){
			if(!isset($out[$choice->getGroup()])){
				$out[$choice->getGroup()] = [];
			}
			$out[$choice->getGroup()][] = $choice;
		}
		return $out;
	}
	
	function generateFromTemplate($template, array $params){
		if(empty($template)){
			return NULL;
		}
		try {
			$out = $this->twig->createTemplate($template)->render($params);
		}
		catch (\Exception $e) {
			$out = "Error in template: ".$e->getMessage();
		}
		return $out;
	}
	
	function dataLink($key, $revisionId=false){
		$out = $key;
		$splitted = explode(':', $key);
		if($splitted && count($splitted) == 2 && strlen($splitted[0]) > 0 && strlen($splitted[1]) > 0 ){
			$type = $splitted[0];
			$ouuid =  $splitted[1];
			
			$addAttribute = "";
			
			/**@var \EMS\CoreBundle\Entity\ContentType $contentType*/
			$contentType = $this->contentTypeService->getByName($type);
			if($contentType) {
				if($contentType->getIcon()){
					
					$icon = '<i class="'.$contentType->getIcon().'"></i>&nbsp;';
				}
				else{
					$icon = '<i class="fa fa-book"></i>&nbsp;';
				}
				
				try {
					$result = $this->client->get([
							'id' => $ouuid,
							'index' => $contentType->getEnvironment()->getAlias(),
							'type' => $type,
					]);
					
					if($contentType->getLabelField()){
						$label = $result['_source'][$contentType->getLabelField()];
						if($label && strlen($label) > 0){
							$out = $label;
						}
					}
					$out = $icon.$out;
					
					if($contentType->getColorField() && $result['_source'][$contentType->getColorField()]){
						$color = $result['_source'][$contentType->getColorField()];
						$contrasted = $this->contrastratio($color, '#000000') > $this->contrastratio($color, '#ffffff')?'#000000':'#ffffff';
						
						$out = '<span class="" style="color:'.$contrasted.';">'.$out.'</span>';
						$addAttribute = ' style="background-color: '.$result['_source'][$contentType->getColorField()].';border-color: '.$result['_source'][$contentType->getColorField()].';"';
						
					}					
				}
				catch(\Exception $e) {
					
				}
				
			}
			$out = '<a class="btn btn-primary btn-sm" href="'.$this->router->generate('data.revisions', [
					'type' =>$type,
					'ouuid' => $ouuid,
					'revisionId' => $revisionId,
			], UrlGeneratorInterface::RELATIVE_PATH).'" '.$addAttribute.' >'.$out.'</a>';
		}
		return $out;
	}
	
	function propertyPath(FormError $error) {
		$parent = $error->getOrigin();
		$out = '';
		while($parent) {
			$out = $parent->getName().$out;
			$parent = $parent->getParent();
			if($parent) {
				$out = '_'.$out;
			}
		}
		return $out;
	}
	
	function data($key){
		$out = $key;
		$splitted = explode(':', $key);
		if($splitted && count($splitted) == 2){
			$type = $splitted[0];
			$ouuid =  $splitted[1];
				
			$addAttribute = "";
			
			/**@var \EMS\CoreBundle\Entity\ContentType $contentType*/
			$contentType = $this->contentTypeService->getByName($type);
			if($contentType) {
				try {
					$result = $this->client->get([
							'id' => $ouuid,
							'index' => $contentType->getEnvironment()->getAlias(),
							'type' => $type,
					]);
					
					return $result['_source'];					
				}
				catch (Missing404Exception $e){
					return false;
				}
			}
		}
		return false;
	}
	
	function one_granted($roles, $super=false){
		foreach ($roles as $role){
			if($this->authorizationChecker->isGranted($super?$this->superizer($role):$role)){
				return true;
			}
		}
		return false;
	}
	
	/**
	 * Calculate relative luminance in sRGB colour space for use in WCAG 2.0 compliance
	 * @link http://www.w3.org/TR/WCAG20/#relativeluminancedef
	 * @param string $col A 3 or 6-digit hex colour string
	 * @return float
	 * @author Marcus Bointon <marcus@synchromedia.co.uk>
	 */
	function relativeluminance($col) {
		//Remove any leading #
		$col = trim($col, '#');
		//Convert 3-digit to 6-digit
		if (strlen($col) == 3) {
			$col = $col[0] . $col[0] . $col[1] . $col[1] . $col[2] . $col[2];
		}
		//Convert hex to 0-1 scale
		$components = array(
				'r' => hexdec(substr($col, 0, 2)) / 255,
				'g' => hexdec(substr($col, 2, 2)) / 255,
				'b' => hexdec(substr($col, 4, 2)) / 255
		);
		//Correct for sRGB
		foreach($components as $c => $v) {
			if ($v <= 0.03928) {
				$components[$c] = $v / 12.92;
			} else {
				$components[$c] = pow((($v + 0.055) / 1.055), 2.4);
			}
		}
		//Calculate relative luminance using ITU-R BT. 709 coefficients
		return ($components['r'] * 0.2126) + ($components['g'] * 0.7152) + ($components['b'] * 0.0722);
	}
	
	/**
	 * Calculate contrast ratio acording to WCAG 2.0 formula
	 * Will return a value between 1 (no contrast) and 21 (max contrast)
	 * @link http://www.w3.org/TR/WCAG20/#contrast-ratiodef
	 * @param string $c1 A 3 or 6-digit hex colour string
	 * @param string $c2 A 3 or 6-digit hex colour string
	 * @return float
	 * @author Marcus Bointon <marcus@synchromedia.co.uk>
	 */
	function contrastratio($c1, $c2) {
		$y1 = $this->relativeluminance($c1);
		$y2 = $this->relativeluminance($c2);
		//Arrange so $y1 is lightest
		if ($y1 < $y2) {
			$y3 = $y1;
			$y1 = $y2;
			$y2 = $y3;
		}
		return ($y1 + 0.05) / ($y2 + 0.05);
	}
	
	public function md5($value)
	{
    	return md5($value);
	}

	public function searchesList($username)
	{
		$searchRepository = $this->doctrine->getRepository('EMSCoreBundle:Form\Search');
    	$searches = $searchRepository->findBy([
    		'user' => $username
    	]);
    	return $searches;
	}

	public function dump($object) {
		if(function_exists('dump')){
    		dump($object);
		}
	}

	public function convertJavaDateFormat($format)
	{
		return DateFieldType::convertJavaDateFormat($format);
	}

	public function convertJavascriptDateFormat($format)
	{
    	return DateFieldType::convertJavascriptDateFormat($format);
	}

	public function convertJavascriptDateRangeFormat($format)
	{
    	return DateRangeFieldType::convertJavascriptDateRangeFormat($format);
	}

	public function getTimeFieldTimeFormat($options)
	{
    	return TimeFieldType::getFormat($options);
	}

	public function inArray($needle, $haystack)
	{
		return is_int(array_search($needle, $haystack));
	}

	public function firstInArray($needle, $haystack)
	{
		return array_search($needle, $haystack) === 0;
	}
	
	public function getContentType($name){
		return $this->contentTypeService->getByName($name);
	}
	
	public function getContentTypes(){
		return $this->contentTypeService->getAll();
	}
	
	/**
	 * @deprecated  since ems 1.6
	 * @return NULL[]
	 */
	public function getDefaultEnvironments(){
		$defaultEnvironments = [];
		foreach ($this->contentTypeService->getAll()as $contentType){
			$defaultEnvironments[] = $contentType->getName();
		}
		return $defaultEnvironments;
	}
	
	public function getEnvironment($name){
		return $this->environmentService->getAliasByName($name);
	}

	
	/*
	 * $arguments should contain 'function' key. Optionally 'options' and/or 'parameters'
	 */
	public function soapRequest($wsdl, $arguments = null)
	{
		/** @var \SoapClient $soapClient */
		$soapClient = null;
		if ($arguments && array_key_exists('options', $arguments)){
			$soapClient = new \SoapClient($wsdl, $arguments['options']);
		} else {
			$soapClient = new \SoapClient($wsdl);
		}
		
		$function = null;
		if ($arguments && array_key_exists('function', $arguments)){
			$function = $arguments['function'];
		} else {
			//TODO: throw error "argument 'function' is obligator"
		}
		
		$response = null;
		if ($arguments && array_key_exists('parameters', $arguments)){
			$response = $soapClient->$function($arguments['parameters']);
		}else{
			$response = $soapClient->$function();
		}
		
		return $response;
		
	}
	
	public function csvEscaper($twig, $name, $charset) {
		return $name;
	}

	public function getName()
	{
		return 'app_extension';
	}
}