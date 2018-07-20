<?php

namespace Magenta\Bundle\SWarrantyApiBundle\EventSubscriber\Customer;

use ApiPlatform\Core\EventListener\EventPriorities;
use Magenta\Bundle\SWarrantyModelBundle\Entity\Customer\Customer;
use Magenta\Bundle\SWarrantyModelBundle\Entity\Customer\Registration;
use Magenta\Bundle\SWarrantyModelBundle\Entity\Messaging\MessageTemplate;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

class RegistrationSubscriber implements EventSubscriberInterface {
	
	private $registry;
	private $mailer;
	
	public function __construct(RegistryInterface $registry, \Swift_Mailer $mailer) {
		$this->registry = $registry;
		$this->mailer   = $mailer;
	}
	
	public static function getSubscribedEvents() {
		return [
			KernelEvents::REQUEST => [ 'onKernelRequest', EventPriorities::PRE_READ ],
			KernelEvents::VIEW    => [ 'onKernelView', EventPriorities::PRE_WRITE ],
		
		];
	}
	
	/**
	 * Calls the data provider and sets the data attribute.
	 *
	 * @param GetResponseEvent $event
	 *
	 * @throws NotFoundHttpException
	 */
	public function onKernelRequest(GetResponseEvent $event) {
		$request    = $event->getRequest();
		$class      = $request->attributes->get('_api_resource_class');
		$controller = $request->attributes->get('_controller');
		if($request->isMethod('get') && $class === Registration::class && $controller === 'api_platform.action.get_item') {
		
		}
	}
	
	public function onKernelView(GetResponseForControllerResultEvent $event) {
		$reg    = $event->getControllerResult();
		$method = $event->getRequest()->getMethod();
		
		if( ! $reg instanceof Registration || Request::METHOD_POST !== $method) {
			return;
		}
		
		$nr = $this->registry->getRepository(Registration::class);
		
		if( ! $reg->isVerified() && ! empty($c = $reg->getCustomer()) && ! empty($c->getEmail())) {
			$msg     = $reg->prepareEmailVerificationMessage();
			$email   = $msg['recipient'];
			$message = (new \Swift_Message($msg['subject']))
				->setFrom('no-reply@magenta-wellness.com')
				->setTo($email)
				->setBody(
					$msg['body'],
					'text/html'
				)/*
				 * If you also want to include a plaintext version of the message
				->addPart(
					$this->renderView(
						'emails/registration.txt.twig',
						array('name' => $name)
					),
					'text/plain'
				)
				*/
			;
			
			$this->mailer->send($message);
		}
	}
}