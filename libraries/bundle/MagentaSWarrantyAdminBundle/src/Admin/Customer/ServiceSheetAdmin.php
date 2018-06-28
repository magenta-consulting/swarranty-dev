<?php

namespace Magenta\Bundle\SWarrantyAdminBundle\Admin\Customer;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Expr;
use Magenta\Bundle\SWarrantyAdminBundle\Admin\BaseAdmin;
use Magenta\Bundle\SWarrantyAdminBundle\Admin\Organisation\OrganisationAdmin;
use Magenta\Bundle\SWarrantyModelBundle\Entity\Customer\ServiceSheet;
use Magenta\Bundle\SWarrantyModelBundle\Entity\Media\Media;
use Magenta\Bundle\SWarrantyModelBundle\Entity\Organisation\Organisation;
use Magenta\Bundle\SWarrantyModelBundle\Entity\Organisation\OrganisationMember;
use Magenta\Bundle\SWarrantyModelBundle\Entity\System\DecisionMakingInterface;
use Magenta\Bundle\SWarrantyModelBundle\Entity\System\FullTextSearchInterface;
use Magenta\Bundle\SWarrantyModelBundle\Entity\System\SystemModule;
use Magenta\Bundle\SWarrantyModelBundle\Entity\System\Thing;
use Magenta\Bundle\SWarrantyModelBundle\Service\User\UserService;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelAutocompleteType;
use Sonata\AdminBundle\Form\Type\ModelType;
use Sonata\AdminBundle\Route\RouteCollection;

use Sonata\DoctrineORMAdminBundle\Admin\FieldDescription;
use Sonata\DoctrineORMAdminBundle\Datagrid\ProxyQuery;
use Sonata\MediaBundle\Form\Type\MediaType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ServiceSheetAdmin extends BaseAdmin {
	
	protected function configureDatagridFilters(DatagridMapper $filter) {
		parent::configureDatagridFilters($filter);
	}
	
	protected function configureRoutes(RouteCollection $collection) {
		parent::configureRoutes($collection);
	}
	
	public
	function toString(
		$object
	) {
		return $object instanceof ServiceSheet
			? $object->getId() . ' ' . $object->getCreatedAt()->format('d-m-Y')
			: parent::toString($object); // shown in the breadcrumb on the create view
	}
	
	public function getNewInstance() {
		/** @var ServiceSheet $object */
		$object = parent::getNewInstance();
		if($object->getImages()->count() === 0) {
			$object->addImage(new Media());
		}
		
		return $object;
	}
	
	public
	function isGranted(
		$name, $object = null
	) {
		return parent::isGranted($name, $object);
	}
	
	public
	function createQuery(
		$context = 'list'
	) {
		$query    = parent::createQuery($context);
		$parentFD = $this->getParentFieldDescription();
		
		return $query;
//        $query->andWhere()
	}
	
	public function getPersistentParameters() {
		$parameters = parent::getPersistentParameters();
		if( ! $this->hasRequest()) {
			return $parameters;
		}
		
		if(empty($org = $this->getCurrentOrganisation(false))) {
			return $parameters;
		}
		
		return array_merge($parameters, array(
			'organisation' => $org->getId()
		));
	}
	
	protected function configureFormFields(FormMapper $formMapper) {
		$c = $this->getConfigurationPool()->getContainer();
		
		$formMapper
			->add('images', CollectionType::class,
				[
					// each entry in the array will be an "media" field
					'entry_type'    => MediaType::class,
					'allow_add'     => true,
					'allow_delete'  => true,
//					'source'        => $c->getParameter('MEDIA_API_BASE_URL') . $c->getParameter('MEDIA_API_PREFIX'),
					// these options are passed to each "media" type
					'entry_options' => array(
						'new_on_update' => false,
						'attr'          => array( 'class' => 'receipt-image' ),
						'context'       => 'service_sheet',
						'provider'      => 'sonata.media.provider.image'
					),
					
					'label' => false,
//					'class'         => Media::class
				]);
		
		$formMapper->add('appointment', ModelType::class, [
			'required'    => false,
			'placeholder' => 'Select Appointment',
			'property'    => 'searchText'
		]);
	}
	
	protected
	function verifyDirectParent(
		$parent
	) {
	
	}
	
	/**
	 * @param ServiceSheet $object
	 */
	public
	function preValidate(
		$object
	) {
	
	}
}