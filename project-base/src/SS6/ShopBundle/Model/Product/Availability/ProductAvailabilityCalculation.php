<?php

namespace SS6\ShopBundle\Model\Product\Availability;

use SS6\ShopBundle\Model\Product\Availability\AvailabilityFacade;
use SS6\ShopBundle\Model\Product\Product;

class ProductAvailabilityCalculation {

	/**
	 * @var \SS6\ShopBundle\Model\Product\Availability\AvailabilityFacade
	 */
	private $availabilityFacade;

	public function __construct(AvailabilityFacade $availabilityFacade) {
		$this->availabilityFacade = $availabilityFacade;
	}

	/**
	 * @param \SS6\ShopBundle\Model\Product\Product $product
	 * @return \SS6\ShopBundle\Model\Product\Availability\Availability
	 */
	public function getCalculatedAvailability(Product $product) {
		if ($product->isUsingStock()) {
			if ($product->getStockQuantity() > 0) {
				return $this->availabilityFacade->getDefaultInStockAvailability();
			} else {
				return $product->getOutOfStockAvailability();
			}
		} else {
			return $product->getAvailability();
		}
	}

}