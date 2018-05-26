<?php

namespace Magenta\Bundle\SWarrantyModelBundle\Entity\Module;


use Doctrine\ORM\Mapping as ORM;
use Magenta\Bundle\SWarrantyModelBundle\Entity\AccessControl\ACEntry;
use Magenta\Bundle\SWarrantyModelBundle\Entity\AccessControl\ACModuleInterface;
use Magenta\Bundle\SWarrantyModelBundle\Entity\System\SystemModule;

/**
 * @ORM\Entity()
 * @ORM\Table(name="module__dealer")
 */
class DealerModule extends SystemModule implements ACModuleInterface {
	
	public function getSupportedModuleActions(): array {
		return ACEntry::getSupportedActions();
	}
	
	public function getModuleName(): string {
		return 'Dealer Management';
	}
	
	public function getModuleCode(): string {
		return 'DEALER';
	}
}