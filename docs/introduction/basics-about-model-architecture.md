# Basics About Model Architecture

In this article you will learn about [entities](#entity), [facades](#facade), [repositories](#repository) and their mutual relations.

## Basics about the model

Model architecture of Shopsys Framework is inspired by Domain Driven Design (DDD). Let us define some terms first:
- **Domain** in DDD is a sphere of knowledge or activity we build application logic around. The domain of Shopsys Framework is e-commerce.
- **Domain model** is a system of abstractions that describes selected aspects of the domain.
- **Domain logic** or **business logic** is the higher level rules for how objects of the domain model interact with one another.

Domain model of Shopsys Framework is located in [`FrameworkBundle/Model`](https://github.com/shopsys/framework/tree/master/src/Model). Its concept is to separate behavior and properties of objects from its persistence. This separation is suitable for code reusability, easier testing and it fulfills the Single Responsibility Principle.

Code belonging to the same feature is grouped together (eg. `Cart` and `CartItem`). Names of classes and methods are based on real world vocabulary to be more intuitive (eg. `OrderHashGenerator` or `getSellableProductsInCategory()`).

Model is divided into three parts: Entity, Repository and Facade. There is `EntityManager` to access the database.

![model architecture schema](img/model-architecture.png 'model architecture schema')

## Entity
Is class encapsulating data. All entities are persisted by Doctrine ORM. One entity class usually represents one table in the database and one instance of the entity represents one row in the table. The entity is composed of fields, which can be mapped to columns in the table. Doctrine ORM annotations are used to define the details about the database mapping (types of columns, relations, etc.).

Entities are inspired by Rich Domain Model. That means entity is the place where domain logic belongs (e.g. `Product::changeVat()` sets vat and marks product for price recalculation). The entity cannot depend on any other class.

Entities can be used by all layers of the model and even outside of model (eg. controller or templates).

You'll find more about our entities specialities in a [detailed article](entities.md).

### Example
```php
// FrameworkBundle/Model/Product/Product.php

namespace Shopsys\FrameworkBundle\Model\Product;

use Doctrine\ORM\Mapping as ORM;

// ...

/**
 * @ORM\Table(
 *     name="products"
 * )
 * @ORM\Entity
 */
class Product
{

    // ...

    /**
     * @var \Shopsys\FrameworkBundle\Model\Pricing\Vat\Vat
     *
     * @ORM\ManyToOne(targetEntity="Shopsys\FrameworkBundle\Model\Pricing\Vat\Vat")
     * @ORM\JoinColumn(nullable=false)
     */
    protected $vat;

    /**
     * @param \Shopsys\FrameworkBundle\Model\Pricing\Vat\Vat $vat
     */
    public function changeVat(Vat $vat)
    {
        $this->vat = $vat;
        $this->recalculatePrice = true;
    }

    // ...

}
```

## Repository
Is a class used to provide access to all entities of its scope. Repository enables code reuse of retrieving logic. Thanks to repositories, there is no need to use DQL/SQL in controllers or facades.

Repository methods have easily readable names and clear return types so IDE auto-completion works great.

In Shopsys Framework repository is mostly used to retrieve entities from the database using Doctrine but can be used to access any other data storage.

Repositories should be used only by facade so you should avoid using them in any other part of the application.

### Example
```php
// FrameworkBundle/Model/Cart/Item/CartItemRepository.php

namespace Shopsys\FrameworkBundle\Model\Cart\Item;

// ...

class CartItemRepository
{

    // ...

    /**
     * @param \Shopsys\FrameworkBundle\Model\Customer\CustomerIdentifier $customerIdentifier
     * @return \Shopsys\FrameworkBundle\Model\Cart\Item\CartItem[]
     */
    public function getAllByCustomerIdentifier(CustomerIdentifier $customerIdentifier)
    {
        $criteria = [];
        if ($customerIdentifier->getUser() !== null) {
            $criteria['user'] = $customerIdentifier->getUser()->getId();
        } else {
            $criteria['cartIdentifier'] = $customerIdentifier->getCartIdentifier();
        }

        return $this->getCartItemRepository()->findBy($criteria, ['id' => 'desc']);
    }

    /**
     * @param int $daysLimit
     */
    public function deleteOldCartsForUnregisteredCustomers($daysLimit)
    {
        $nativeQuery = $this->em->createNativeQuery(
            'DELETE FROM cart_items WHERE cart_identifier NOT IN (
                SELECT CI.cart_identifier
                FROM cart_items CI
                WHERE CI.added_at > :timeLimit
            ) AND user_id IS NULL',
            new ResultSetMapping()
        );

        $nativeQuery->execute([
            'timeLimit' => new DateTime('-' . $daysLimit . ' days'),
        ]);
    }

    /**
     * @return \Doctrine\ORM\EntityRepository
     */
    protected function getCartItemRepository()
    {
        return $this->em->getRepository(CartItem::class);
    }

    // ...

}
```
*Note: Repositories in Shopsys Framework wrap Doctrine repositories. This is done in order to provide only useful methods with understandable names instead of generic API of Doctrine repositories.*

## Facade
Facades are a single entry-point into the model. That means you can use the same method in your controller, CLI command, REST API, etc. with the same results. All methods in facade should have single responsibility without any complex logic. Every method has a single use case and does not contain any business logic only sequence of calls of entities and repositories methods.

Facades as entry-point of the model can be used anywhere outside of the model.

### Example
```php
// FrameworkBundle/Model/Cart/CartFacade.php

namespace Shopsys\FrameworkBundle\Model\Cart;

// ...

class CartFacade
{

    // ...

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $em;

    /**
     * @var \Shopsys\FrameworkBundle\Model\Cart\CartFactory
     */
    protected $cartFactory;

    /**
     * @var \Shopsys\FrameworkBundle\Model\Customer\CustomerIdentifierFactory
     */
    protected $customerIdentifierFactory;

    /**
     * @var \Shopsys\FrameworkBundle\Component\Domain\Domain
     */
    protected $domain;

    /**
     * @var \Shopsys\FrameworkBundle\Model\Customer\CurrentCustomer
     */
    protected $currentCustomer;

    /**
     * @var \Shopsys\FrameworkBundle\Model\Product\Pricing\ProductPriceCalculationForUser
     */
    protected $productPriceCalculation;

    /**
     * @var \Shopsys\FrameworkBundle\Model\Cart\Item\CartItemFactoryInterface
     */
    protected $cartItemFactory;

    // ...

    /**
     * @param int $productId
     * @param int $quantity
     * @return \Shopsys\FrameworkBundle\Model\Cart\AddProductResult
     */
    public function addProductToCart($productId, $quantity)
    {
        $product = $this->productRepository->getSellableById(
            $productId,
            $this->domain->getId(),
            $this->currentCustomer->getPricingGroup()
        );
        $customerIdentifier = $this->customerIdentifierFactory->get();
        $cart = $this->cartFactory->get($customerIdentifier);

        $result = $cart->addProduct($customerIdentifier, $product, $quantity, $this->productPriceCalculation, $this->cartItemFactory);

        $this->em->persist($result->getCartItem());
        $this->em->flush();

        return $result;
    }

    // ...

}
```

## Cooperation of layers
The controller handles the request (eg. saved data from form) and passes data to the facade.
The facade receives data from the controller and requests appropriate entities from the repository.
Entities and supporting classes (like recalculators, schedulers) processes data and returns output to the facade, that persist it by entity manager.
