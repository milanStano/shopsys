<?php

namespace Shopsys\ShopBundle\Model\AdvancedSearch\Filter;

use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Shopsys\ShopBundle\Model\AdvancedSearch\AdvancedSearchFilterInterface;
use Shopsys\ShopBundle\Model\Product\Availability\AvailabilityFacade;
use Shopsys\ShopBundle\Model\Product\Product;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class ProductAvailabilityFilter implements AdvancedSearchFilterInterface
{
    /**
     * @var \Shopsys\ShopBundle\Model\Product\Availability\AvailabilityFacade
     */
    private $availabilityFacade;

    public function __construct(AvailabilityFacade $availabilityFacade)
    {
        $this->availabilityFacade = $availabilityFacade;
    }

    /**
     * {@inheritdoc}
     */
    public function getAllowedOperators()
    {
        return [
            self::OPERATOR_IS,
            self::OPERATOR_IS_NOT,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'productAvailability';
    }

    /**
     * {@inheritdoc}
     */
    public function getValueFormOptions()
    {
        return [
            'expanded' => false,
            'multiple' => false,
            'choices' => $this->availabilityFacade->getAll(),
            'choice_label' => 'name',
            'choice_value' => 'id',
            'choices_as_values' => true, // Switches to Symfony 3 choice mode, remove after upgrade from 2.8
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getValueFormType()
    {
        return ChoiceType::class;
    }

    /**
     * {@inheritdoc}
     */
    public function extendQueryBuilder(QueryBuilder $queryBuilder, $rulesData)
    {
        $isNotAvailabilities = [];

        foreach ($rulesData as $index => $ruleData) {
            /* @var $ruleData \Shopsys\ShopBundle\Model\AdvancedSearch\AdvancedSearchRuleData */
            if ($ruleData->operator === self::OPERATOR_IS) {
                $tableAlias = 'a' . $index;
                $availabilityParameter = 'availability' . $index;
                $queryBuilder->join('p.calculatedAvailability', $tableAlias, Join::WITH, $tableAlias . '.id = :' . $availabilityParameter);
                $queryBuilder->setParameter($availabilityParameter, $ruleData->value);
            } elseif ($ruleData->operator === self::OPERATOR_IS_NOT) {
                $isNotAvailabilities[] = $ruleData->value;
            }
        }

        if (count($isNotAvailabilities) > 0) {
            $subQuery = 'SELECT availability_p.id FROM ' . Product::class . ' availability_p
                JOIN availability_p.calculatedAvailability _a WITH _a.id IN (:isNotAvailabilities)';
            $queryBuilder->andWhere('p.id NOT IN (' . $subQuery . ')');
            $queryBuilder->setParameter('isNotAvailabilities', $isNotAvailabilities);
        }
    }
}
