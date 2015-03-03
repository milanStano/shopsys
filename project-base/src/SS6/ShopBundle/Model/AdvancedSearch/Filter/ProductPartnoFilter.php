<?php

namespace SS6\ShopBundle\Model\AdvancedSearch\Filter;

use Doctrine\ORM\QueryBuilder;
use SS6\ShopBundle\Component\String\DatabaseSearching;
use SS6\ShopBundle\Model\AdvancedSearch\AdvancedSearchFilterInterface;

class ProductPartnoFilter implements AdvancedSearchFilterInterface {

	/**
	 * {@inheritdoc}
	 */
	public function getName() {
		return 'productPartno';
	}

	/**
	 * {@inheritdoc}
	 */
	public function getAllowedOperators() {
		return [
			self::OPERATOR_CONTAINS,
			self::OPERATOR_NOT_CONTAINS,
			self::OPERATOR_NOT_SET,
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function getValueFormType() {
		return 'text';
	}

	/**
	 * {@inheritdoc}
	 */
	public function getValueFormOptions() {
		return [];
	}

	/**
	 * {@inheritdoc}
	 */
	public function extendQueryBuilder(QueryBuilder $queryBuilder, $rulesData) {
		foreach ($rulesData as $index => $ruleData) {
			if ($ruleData->operator === self::OPERATOR_NOT_SET) {
				$queryBuilder->andWhere('p.partno IS NULL');
			} elseif ($ruleData->operator === self::OPERATOR_CONTAINS || $ruleData->operator === self::OPERATOR_NOT_CONTAINS) {
				if ($ruleData->value === null) {
					$searchValue = '%';
				} else {
					$searchValue = '%' . DatabaseSearching::getLikeSearchString($ruleData->value) . '%';
				}

				$dqlOperator = $this->getContainsDqlOperator($ruleData->operator);
				$parameterName = 'productPartno_' . $index;
				$queryBuilder->andWhere('NORMALIZE(p.partno) ' . $dqlOperator . ' NORMALIZE(:' . $parameterName . ')');
				$queryBuilder->setParameter($parameterName, $searchValue);
			}
		}
	}

	/**
	 * @param string $operator
	 * @return string
	 */
	private function getContainsDqlOperator($operator) {
		switch ($operator) {
			case self::OPERATOR_CONTAINS:
				return 'LIKE';
			case self::OPERATOR_NOT_CONTAINS:
				return 'NOT LIKE';
		}
	}

}