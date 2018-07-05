<?php

namespace Magenta\Bundle\SWarrantyAdminBundle\Admin\Customer;

use Doctrine\ORM\EntityRepository;
use FOS\CKEditorBundle\Form\Type\CKEditorType;
use Magenta\Bundle\SWarrantyAdminBundle\Admin\AccessControl\ACLAdmin;
use Magenta\Bundle\SWarrantyAdminBundle\Admin\BaseAdmin;
use Magenta\Bundle\SWarrantyAdminBundle\Admin\Product\ProductAdmin;
use Magenta\Bundle\SWarrantyAdminBundle\Form\Type\ManyToManyThingType;

use Magenta\Bundle\SWarrantyAdminBundle\Form\Type\ProductDetailType;
use Magenta\Bundle\SWarrantyModelBundle\Entity\Customer\CaseAppointment;
use Magenta\Bundle\SWarrantyModelBundle\Entity\Customer\Customer;
use Magenta\Bundle\SWarrantyModelBundle\Entity\Customer\ServiceSheet;
use Magenta\Bundle\SWarrantyModelBundle\Entity\Customer\Warranty;
use Magenta\Bundle\SWarrantyModelBundle\Entity\Customer\WarrantyCase;
use Magenta\Bundle\SWarrantyModelBundle\Entity\Media\Media;
use Magenta\Bundle\SWarrantyModelBundle\Entity\Organisation\Organisation;
use Magenta\Bundle\SWarrantyModelBundle\Entity\Person\Person;
use Magenta\Bundle\SWarrantyModelBundle\Entity\Product\Brand;
use Magenta\Bundle\SWarrantyModelBundle\Entity\Product\Dealer;
use Magenta\Bundle\SWarrantyModelBundle\Entity\Product\Product;
use Magenta\Bundle\SWarrantyModelBundle\Entity\Product\ServiceZone;
use Magenta\Bundle\SWarrantyModelBundle\Entity\System\DecisionMakingInterface;
use Magenta\Bundle\SWarrantyModelBundle\Entity\User\User;
use Magenta\Bundle\SWarrantyModelBundle\Service\User\UserService;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use Magenta\Bundle\SWarrantyModelBundle\Service\User\UserManager;
use Magenta\Bundle\SWarrantyModelBundle\Service\User\UserManagerInterface;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;

use Sonata\AdminBundle\Form\Type\ModelAutocompleteType;
use Sonata\AdminBundle\Form\Type\ModelType;
use Sonata\AdminBundle\Route\RouteCollection;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\CoreBundle\Form\Type\CollectionType;
use Sonata\CoreBundle\Form\Type\DatePickerType;
use Sonata\CoreBundle\Form\Type\DateTimePickerType;
use Sonata\DoctrineORMAdminBundle\Admin\FieldDescription;
use Sonata\DoctrineORMAdminBundle\Datagrid\ProxyQuery;
use Sonata\AdminBundle\Datagrid\ProxyQueryInterface;
use Sonata\MediaBundle\Form\Type\MediaType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Validator\Constraints\Valid;

class WarrantyCaseAdmin extends BaseAdmin {
	
	const CHILDREN = [
		WarrantyChildCaseAdmin::class => 'parent'
	];
	
	protected $action;
	
	protected $datagridValues = array(
		// display the first page (default = 1)
//        '_page' => 1,
		// reverse order (default = 'ASC')
		'_sort_order' => 'DESC',
		// name of the ordered field (default = the model's id field, if any)
		'_sort_by'    => 'updatedAt',
	);
	
	protected function filterQueryByOrganisation(ProxyQuery $query, Organisation $organisation) {
		$pool      = $this->getConfigurationPool();
		$request   = $this->getRequest();
		$container = $pool->getContainer();
		/** @var Expr $expr */
		$expr          = $query->getQueryBuilder()->expr();
		$warrantyAlias = $query->entityJoin([ [ 'fieldName' => 'warranty' ] ]);
		
		/** @var QueryBuilder $qb */
		$qb = $query->getQueryBuilder();
		$qb->join($warrantyAlias . '.customer', 'customer')
		   ->join('customer.organisation', 'organisation');
		
		
		return $query->andWhere($expr->eq('organisation.id', $organisation->getId()));
	}
	
	public function getNewInstance() {
		/** @var WarrantyCase $object */
		$object = parent::getNewInstance();
		$parent = $this->getParent();
		if($parent instanceof WarrantyAdmin) {
			$object->setWarranty($parent->getSubject());
		}
		
		if(empty($w = $object->getWarranty())) {
			$object->setWarranty($w = new Warranty());
		}
		
		if(empty($w->getCustomer())) {
			$w->setCustomer(new Customer());
		}
		
		if(empty($w->getProduct())) {
			$p = new Product();
			$p->setBrand(new Brand());
			$p->setImage(new Media());
			$w->setProduct($p);
		}
		
		
		return $object;
	}
	
	protected function getAccess() {
		return array_merge(parent::getAccess(), [
			'close'  => 'DECISION_' . WarrantyCase::DECISION_CLOSE,
			'reopen' => 'DECISION_' . WarrantyCase::DECISION_REOPEN
		]);
	}
	
	/**
	 * @param string       $name
	 * @param WarrantyCase $object
	 */
	public function isGranted($name, $object = null) {
		if( ! is_array($name)) {
			$_name = strtoupper($name);
			if($_name === 'DECISION_' . DecisionMakingInterface::DECISION_APPROVE || $_name === 'DECISION_' . DecisionMakingInterface::DECISION_REJECT) {
				return false;
			}
		}
		
		return parent::isGranted($name, $object);
	}
	
	/**
	 * @param WarrantyCase $object
	 *
	 * @return string
	 */
	public function toString($object) {
		return $object instanceof WarrantyCase
			? $object->getWarranty()->getCustomer()->getName() . ' - ' . $object->getWarranty()->getProduct()->getName()
			: 'Warranty Case'; // shown in the breadcrumb on the create view
	}
	
	public function createQuery($context = 'list') {
		/** @var ProxyQueryInterface $query */
		$query = parent::createQuery($context);
		if(empty($this->getParentFieldDescription())) {
//            $this->filterQueryByPosition($query, 'position', '', '');
		}
		/** @var Expr $expr */
		$expr = $query->expr();
		/** @var QueryBuilder $qb */
		$qb           = $query->getQueryBuilder();
		$rootAlias    = $qb->getRootAliases()[0];
		$statusFilter = $this->getRequest()->query->get('statusFilter');
		switch($statusFilter) {
			case 'ALL':
				break;
			case 'NEW':
				$query->andWhere($expr->like($rootAlias . '.status', $expr->literal(WarrantyCase::STATUS_NEW)));
				break;
			case 'ASSIGNED':
				$query->andWhere($expr->like($rootAlias . '.status', $expr->literal(WarrantyCase::STATUS_ASSIGNED)));
				break;
			case 'RESPONDED':
				$query->andWhere($expr->like($rootAlias . '.status', $expr->literal(WarrantyCase::STATUS_ASSIGNED)));
				break;
			case 'COMPLETED':
//				$query->andWhere($expr->lt($rootAlias . '.expiryDate', ':today'))
//				      ->setParameter('today', new \DateTime());
				$query->andWhere($expr->like($rootAlias . '.status', $expr->literal(WarrantyCase::STATUS_COMPLETED)));
				break;
			case 'CLOSED':
				$query->andWhere($expr->like($rootAlias . '.status', $expr->literal(WarrantyCase::STATUS_CLOSED)));
				break;
		}
		
		//        $query->andWhere()
		
		
		return $query;
		
	}
	
	public function getPersistentParameters() {
		$parameters = parent::getPersistentParameters();
		if( ! $this->hasRequest()) {
			return $parameters;
		}
		
		if( ! empty($this->subject) && ! empty($caseId = $this->subject->getId())) {
			$parameters = array_merge($parameters, [ 'case' => $caseId ]);
		}
		
		if(empty($org = $this->getCurrentOrganisation(true))) {
			return $parameters;
		}
		
		return array_merge($parameters, array(
			'organisation' => $org->getId()
		));
	}
	
	public function configureRoutes(RouteCollection $collection) {
		parent::configureRoutes($collection);
//		$collection->add('show_user_profile', $this->getRouterIdParameter() . '/show-user-profile');
	
	}
	
	public function setTemplate($name, $template) {
		$_name = strtoupper($name);
//		if($_name === 'BASE_LIST_FIELD') {
//			$template = '@MagentaSWarrantyAdmin/Admin/Customer/WarrantyCase/CRUD/list_field.html.twig';
//		}
//		if($_name === 'EDIT') {
//			$template = '@MagentaSWarrantyAdmin/Admin/Customer/WarrantyCase/CRUD/edit.html.twig';
//		}
//
		parent::setTemplate($name, $template);
	}
	
	protected function configureShowFields(ShowMapper $showMapper) {
		$showMapper
			->with('form_group.case_details', [ 'class' => 'col-md-6' ])
			->add('serviceZone.name')
			->add('assignee.person.name')
			->end();
		
		$showMapper
			->with('form_group.service_images', [ 'class' => 'col-md-6' ])
//			->add('receiptImages', 'image', [ 'label' => 'form.label_reference_number' ])
			->end();
		
		$showMapper->with('form_group.warranty_details', [ 'class' => 'col-md-6' ])
		           ->add('code', null, [ 'label' => 'form.label_reference_number' ])
		           ->add('warranty.product.brand', null, [
			           'label'               => 'form.label_brand',
			           'associated_property' => 'name'
		           ])
		           ->add('warranty.product.category', null, [
			           'label'               => 'form.label_category',
			           'associated_property' => 'name'
		           ])
		           ->add('warranty.product.subCategory', null, [
			           'label'               => 'form.label_subcategory',
			           'associated_property' => 'name'
		           ])
		           ->add('warranty.product.name', null, [ 'label' => 'form.label_model_name' ])
		           ->add('warranty.product.modelNumber', null, [ 'label' => 'form.label_model_number' ])
		           ->add('warranty.product.image', 'image', [ 'label' => 'form.label_model_image' ])
		           ->add('warranty.purchaseDate', null, [
			           'label'  => 'form.label_purchase_date',
			           'format' => 'd - m - Y'
		           ])
		           ->add('warranty.createdAt', null, [
			           'label'  => 'form.label_warranty_submission_date',
			           'format' => 'd - m - Y'
		           ])
		           ->add('warranty.product.warrantyPeriod', null, [
			           'editable' => true,
			           'label'    => 'form.label_default_warranty_period'
		           ])
		           ->add('warranty.product.extendedWarrantyPeriod', null, [ 'label' => 'form.label_extended_warranty_period' ])
		           ->add('warranty.expiryDate', null, [
			           'label'  => 'form.label_warranty_expiry',
			           'format' => 'd - m - Y'
		           ])
		           ->add('warranty.dealer.name', null, [ 'label' => 'form.label_dealer' ])
		           ->end();
		
		$showMapper->with('form_group.customer_details', [ 'class' => 'col-md-6' ])
		           ->add('warranty.customer.name', null, [ 'label' => 'form.label_name' ])
		           ->add('warranty.customer.telephone', null, [ 'label' => 'form.label_telephone' ])
		           ->add('warranty.customer.email', null, [ 'label' => 'form.label_email' ])
		           ->add('warranty.customer.homeAddress', null, [ 'label' => 'form.label_address' ])
		           ->add('warranty.customer.homePostalCode', null, [ 'label' => 'form.label_postal_code' ])
		           ->end();
	}
	
	/**
	 * {@inheritdoc}
	 */
	protected function configureListFields(ListMapper $listMapper) {
		$listMapper->add('_action', 'actions', [
				'actions' => array(
//					'show_case' => array( 'template' => '@MagentaSWarrantyAdmin/Admin/Customer/WarrantyCase/Action/list__action__show_case.html.twig' ),
					'edit'   => array(),
					'delete' => array(),
//					'send_evoucher' => array( 'template' => '::admin/employer/employee/list__action_send_evoucher.html.twig' )

//                ,
//                    'view_description' => array('template' => '::admin/product/description.html.twig')
//                ,
//                    'view_tos' => array('template' => '::admin/product/tos.html.twig')
				),
				'label'   => 'form.label_action'
			]
		);
		
		$listMapper
			->add('priority', 'choice', [
				'editable' => true,
				'label'    => 'form.label_priority',
				'choices'  => [
					WarrantyCase::PRIORITY_LOW    => WarrantyCase::PRIORITY_LOW,
					WarrantyCase::PRIORITY_NORMAL => WarrantyCase::PRIORITY_NORMAL,
					WarrantyCase::PRIORITY_HIGH   => WarrantyCase::PRIORITY_HIGH
				]
			])
			->add('warranty.product', 'product', [
				'label'               => 'form.label_product',
				'associated_property' => 'warranty.product.name'
			])
			->add('description', 'html', [
//				'editable' => true,
				'label' => 'form.label_case_detail'
			])
			->add('serviceNotes', null, [
				'label'               => 'form.label_service_notes',
				'associated_property' => 'description'
			])
			->add('assigneeHistory', null, [
				'label'               => 'form.label_assignee_history',
				'associated_property' => 'assigneeName'
			])
			->add('serviceZone.name', null, [ 'label' => 'form.label_service_zone' ]);
		
		$listMapper
			->add('warranty.customer', 'customer', [ 'label' => 'form.label_customer' ])
			->add('status', null, [ 'label' => 'form.label_status' ])
			->add('appointmentAt', null, [
				'label'  => 'form.label_appointment_at',
				'format' => 'd-m-Y'
			]);

//		$listMapper->add('warranty.receiptImages', 'image', [
//			'editable' => true,
//			'label'    => 'form.label_receipt_images'
//		])
		;

//		$listMapper->add('positions', null, [ 'template' => '::admin/user/list__field_positions.html.twig' ]);
	}
	
	/**
	 * {@inheritdoc}
	 *
	 */
	public function getExportFormats() {
		return [
			'json',
			'xml',
			'csv',
			'xls',
			'html'
		];
	}
	
	protected function getAutocompleteRouteParameters() {
		return [ 'organisation' => $this->getCurrentOrganisation()->getId() ];
	}
	
	protected function configureFormFields(FormMapper $formMapper) {
		$parent = $this->getParent();
//		$link_parameters = $this->getParentFieldDescription()->getOption('link_parameters', array());
		$c = $this->getConfigurationPool()->getContainer();
		
		$formMapper
			->with('form_group.service_images', [ 'class' => 'col-md-6' ]);
		$formMapper->add('serviceSheets', CollectionType::class,
			array(
				'required'    => false,
				'constraints' => new Valid(),
				'label'       => false,
//					'btn_catalogue' => 'InterviewQuestionSetAdmin'
			), array(
				'edit'            => 'inline',
				'inline'          => 'table',
				//						'sortable' => 'position',
				'link_parameters' => $this->getPersistentParameters(),
				'admin_code'      => ServiceSheetAdmin::class,
				'delete'          => null,
			)
		);
		$formMapper->end();
		
		
		if( ! $this->isAppendFormElement()) {
			if(true || ! $parent instanceof WarrantyAdmin) {
				$formMapper
					->with('form_group.selected_warranty', [ 'class' => 'col-md-6' ]);

//				$formMapper
//					->with('form_group.select_warranty', [ 'class' => 'col-md-12' ]);
				$formMapper->add('warranty', ModelAutocompleteType::class, [
					'required'           => true,
					'label'              => false,
					'route'              => [
						'name'       => 'sonata_admin_retrieve_autocomplete_items',
						'parameters' => $this->getAutocompleteRouteParameters()
					],
//			'query'    => $this->getFilterByOrganisationQueryForModel(Product::class),
					'property'           => 'fullText',
//			'btn_add'  => false,
					'to_string_callback' => function(Warranty $entity) {
//				$entity->generateSearchText();
						
						return $entity->getSearchText();
					},
					'callback'           => function(WarrantyAdmin $admin, $property, $field) {
						
						return true;
					},
				]);
				$formMapper->end();
				$formMapper
					->with('form_group.product_details', [ 'class' => 'col-md-6 col-md-offset-6' ]);
				$formMapper->add('warranty.product.image', ProductDetailType::class, [
					'detail_route'     => 'admin_magenta_swarrantymodel_customer_warranty_detail',
					'product_property' => 'warranty',
					'required'         => false,
					'label'            => 'form.label_product_image',
					'appended_value'   => 'months',
					'type'             => 'image',
					'class'            => Media::class
				]);
				$formMapper->add('warranty.product.brand.name', ProductDetailType::class, [
					'detail_route'     => 'admin_magenta_swarrantymodel_customer_warranty_detail',
					'product_property' => 'warranty',
					'required'         => false,
					'label'            => 'form.label_brand',
					'type'             => 'brand',
					'class'            => null
				]);
				$formMapper->add('warranty.product.name', ProductDetailType::class, [
					'detail_route'     => 'admin_magenta_swarrantymodel_customer_warranty_detail',
					'product_property' => 'warranty',
					'required'         => false,
					'label'            => 'form.label_model_name',
					'type'             => 'model_name',
					'class'            => null
				]);
				$formMapper->add('warranty.product.modelNumber', ProductDetailType::class, [
					'detail_route'     => 'admin_magenta_swarrantymodel_customer_warranty_detail',
					'product_property' => 'warranty',
					'required'         => false,
					'label'            => 'form.label_model_number',
					'type'             => 'model_number',
					'class'            => null
				]);

				$formMapper->end();
				$formMapper
					->with('form_group.customer_details', [ 'class' => 'col-md-6 col-md-offset-6' ]);
				$formMapper->add('warranty.customer.name', ProductDetailType::class, [
					'product_property' => 'warranty',
					'required'         => false,
					'detail_route'     => 'admin_magenta_swarrantymodel_customer_warranty_detail',
					'label'            => 'form.label_name',
//			'appended_value' => 'months',
					'type'             => 'customer_name',
//			'class'          => null
				]);
				$formMapper->add('warranty.customer.telephone', ProductDetailType::class, [
					'product_property' => 'warranty',
					'required'         => false,
					'detail_route'     => 'admin_magenta_swarrantymodel_customer_warranty_detail',
					'label'            => 'form.label_telephone',
//			'appended_value' => 'months',
					'type'             => 'customer_phone',
//			'class'          => null
				]);
				$formMapper->add('warranty.customer.email', ProductDetailType::class, [
					'product_property' => 'warranty',
					'required'         => false,
					'detail_route'     => 'admin_magenta_swarrantymodel_customer_warranty_detail',
					'label'            => 'form.label_email',
//			'appended_value' => 'months',
					'type'             => 'customer_email',
//			'class'          => null
				]);
				$formMapper->add('warranty.customer.homeAddress', ProductDetailType::class, [
					'product_property' => 'warranty',
					'required'         => false,
					'detail_route'     => 'admin_magenta_swarrantymodel_customer_warranty_detail',
					'label'            => 'form.label_address',
//			'appended_value' => 'months',
					'type'             => 'customer_address',
//			'class'          => null
				]);
				$formMapper->add('warranty.customer.homePostalCode', ProductDetailType::class, [
					'product_property' => 'warranty',
					'required'         => false,
					'detail_route'     => 'admin_magenta_swarrantymodel_customer_warranty_detail',
					'label'            => 'form.label_postal_code',
//			'appended_value' => 'months',
					'type'             => 'customer_postal',
//			'class'          => null
				]);
				$formMapper->end();

				$formMapper
					->with('form_group.customer_notes', [ 'class' => 'col-md-6 col-md-offset-6' ]);
				
				$formMapper
					->add('description', CKEditorType::class, [
						'required' => false,
						'label'    => false, //'form.label_case_detail'
					]);
				$formMapper->end();
			} else {
			
				
			}
		}


		$formMapper
			->with('form_group.case_details', [ 'class' => 'col-md-6 col-md-offset-6' ]);
		$formMapper->add('serviceZone', ModelType::class, [
			'placeholder' => 'Select a Zone',
			'property'    => 'name'
		]);
		if(empty($this->subject->getAssignee())) {
			$formMapper
				->add('assignee', ModelType::class, [
					'required'    => false,
					'placeholder' => 'Select a Technician',
					'label'       => 'form.label_assign_technician',
					'property'    => 'person.name',
					'btn_add'     => false
				])
				->add('appointmentAt', DateTimePickerType::class, [
					'required'              => false,
					'format'                => 'dd-MM-yyyy, H:m',
					'placeholder'           => 'dd-mm-yyyy, hour:minutes',
					'datepicker_use_button' => false,
				]);
		} else {
			$formMapper->add('appointments', CollectionType::class,
				array(
					'required'    => true,
					'constraints' => new Valid(),
					'label'       => 'form.label_appointments',
//					'btn_catalogue'appendFormFieldElementAction => 'InterviewQuestionSetAdmin'
				), array(
					'edit'            => 'inline',
					'inline'          => 'table',
					//						'sortable' => 'position',
					'link_parameters' => $this->getPersistentParameters(),
					'admin_code'      => CaseAppointmentAdmin::class,
					'delete'          => null,
				)
			);
		}
		$formMapper->add('serviceNotes', CollectionType::class,
			array(
				'required'    => false,
				'constraints' => new Valid(),
//				'label'       => false,
//					'btn_catalogue' => 'InterviewQuestionSetAdmin'
			), array(
				'edit'            => 'inline',
				'inline'          => 'table',
				//						'sortable' => 'position',
				'link_parameters' => $this->getPersistentParameters(),
				'admin_code'      => ServiceNoteAdmin::class,
				'delete'          => null,
			)
		);
		$formMapper->end();
	}
	
	/**
	 * @param WarrantyCase $object
	 */
	public function preValidate($object) {
		parent::preValidate($object);
		$apmts = $object->getAppointments();
		
		/** @var CaseAppointment $apmt */
		foreach($apmts as $apmt) {
			if( ! empty($apmt)) {
				$object->addAppointment($apmt);
			}
		}
		
		$sss = $object->getServiceSheets();
		/** @var ServiceSheet $ss */
		foreach($sss as $ss) {
			if( ! empty($ss)) {
				$object->addServiceSheet($ss);
				$images = $ss->getImages();
				/** @var Media $ri */
				foreach($images as $m) {
					if( ! empty($m)) {
						$ss->addImage($m);
					}
				}
				
			}
		}
	}
	
	/**
	 * @param WarrantyCase $object
	 */
	public function prePersist($object) {
		parent::prePersist($object);
//		if( ! $object->isEnabled()) {
//			$object->setEnabled(true);
//		}
	}
	
	/**
	 * @param WarrantyCase $object
	 */
	public function preUpdate($object) {
		parent::preUpdate($object);
	}
	
	///////////////////////////////////
	///
	///
	///
	///////////////////////////////////
	
	/**
	 * {@inheritdoc}
	 */
	protected function configureDatagridFilters(DatagridMapper $filterMapper) {
		$filterMapper
			->add('id')
			->add('warranty.customer.name')//			->add('locked')
		;
		parent::configureDatagridFilters($filterMapper);
	}
	
	
}