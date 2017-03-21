<?php

namespace Shopsys\ShopBundle\Controller\Admin;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopsys\ShopBundle\Component\ConfirmDelete\ConfirmDeleteResponseFactory;
use Shopsys\ShopBundle\Component\Controller\AdminBaseController;
use Shopsys\ShopBundle\Component\Domain\SelectedDomain;
use Shopsys\ShopBundle\Component\Router\Security\Annotation\CsrfProtection;
use Shopsys\ShopBundle\Form\Admin\Pricing\Group\PricingGroupSettingsFormType;
use Shopsys\ShopBundle\Model\Pricing\Group\Grid\PricingGroupInlineEdit;
use Shopsys\ShopBundle\Model\Pricing\Group\PricingGroupFacade;
use Shopsys\ShopBundle\Model\Pricing\Group\PricingGroupSettingFacade;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class PricingGroupController extends AdminBaseController
{
    /**
     * @var \Shopsys\ShopBundle\Model\Pricing\Group\PricingGroupSettingFacade
     */
    private $pricingGroupSettingFacade;

    /**
     * @var \Shopsys\ShopBundle\Model\Pricing\Group\PricingGroupFacade
     */
    private $pricingGroupFacade;

    /**
     * @var \Shopsys\ShopBundle\Model\Pricing\Group\Grid\PricingGroupInlineEdit
     */
    private $pricingGroupInlineEdit;

    /**
     * @var \Shopsys\ShopBundle\Component\ConfirmDelete\ConfirmDeleteResponseFactory
     */
    private $confirmDeleteResponseFactory;

    /**
     * @var \Shopsys\ShopBundle\Component\Domain\SelectedDomain
     */
    private $selectedDomain;

    public function __construct(
        PricingGroupSettingFacade $pricingGroupSettingFacade,
        PricingGroupFacade $pricingGroupFacade,
        PricingGroupInlineEdit $pricingGroupInlineEdit,
        ConfirmDeleteResponseFactory $confirmDeleteResponseFactory,
        SelectedDomain $selectedDomain
    ) {
        $this->pricingGroupSettingFacade = $pricingGroupSettingFacade;
        $this->pricingGroupFacade = $pricingGroupFacade;
        $this->pricingGroupInlineEdit = $pricingGroupInlineEdit;
        $this->confirmDeleteResponseFactory = $confirmDeleteResponseFactory;
        $this->selectedDomain = $selectedDomain;
    }

    /**
     * @Route("/pricing/group/list/")
     * @param \Symfony\Component\HttpFoundation\Request $request
     */
    public function listAction()
    {
        $grid = $this->pricingGroupInlineEdit->getGrid();

        return $this->render('@ShopsysShop/Admin/Content/Pricing/Groups/list.html.twig', [
            'gridView' => $grid->createView(),
        ]);
    }

    /**
     * @Route("/pricing/group/delete/{id}", requirements={"id" = "\d+"})
     * @CsrfProtection
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param int $id
     */
    public function deleteAction(Request $request, $id)
    {
        $newId = $request->get('newId');
        $newId = $newId !== null ? (int)$newId : null;

        try {
            $name = $this->pricingGroupFacade->getById($id)->getName();

            $this->pricingGroupFacade->delete($id, $newId);

            if ($newId === null) {
                $this->getFlashMessageSender()->addSuccessFlashTwig(
                    t('Pricing group <strong>{{ name }}</strong> deleted'),
                    [
                        'name' => $name,
                    ]
                );
            } else {
                $newPricingGroup = $this->pricingGroupFacade->getById($newId);
                $this->getFlashMessageSender()->addSuccessFlashTwig(
                    t('Pricing group <strong>{{ name }}</strong> deleted and replaced by group <strong>{{ newName }}</strong>.'),
                    [
                        'name' => $name,
                        'newName' => $newPricingGroup->getName(),
                    ]
                );
            }
        } catch (\Shopsys\ShopBundle\Model\Pricing\Group\Exception\PricingGroupNotFoundException $ex) {
            $this->getFlashMessageSender()->addErrorFlash(t('Selected pricing group doesn\'t exist.'));
        }

        return $this->redirectToRoute('admin_pricinggroup_list');
    }

    /**
     * @Route("/pricing/group/delete-confirm/{id}", requirements={"id" = "\d+"})
     * @param int $id
     */
    public function deleteConfirmAction($id)
    {
        try {
            $pricingGroup = $this->pricingGroupFacade->getById($id);

            if ($this->pricingGroupSettingFacade->isPricingGroupUsedOnSelectedDomain($pricingGroup)) {
                $message = t(
                    'For removing pricing group "%name%" you have to choose other one to be set everywhere where the existing one is used. '
                    . 'Which pricing group you want to set instead?',
                    ['%name%' => $pricingGroup->getName()]
                );

                if ($this->pricingGroupSettingFacade->isPricingGroupDefaultOnSelectedDomain($pricingGroup)) {
                    $message = t(
                        'Pricing group "%name%" set as default. For deleting it you have to choose other one to be set everywhere '
                        . 'where the existing one is used. Which pricing group you want to set instead?',
                        ['%name%' => $pricingGroup->getName()]
                    );
                }

                return $this->confirmDeleteResponseFactory->createSetNewAndDeleteResponse(
                    $message,
                    'admin_pricinggroup_delete',
                    $id,
                    $this->pricingGroupFacade->getAllExceptIdByDomainId($id, $pricingGroup->getDomainId())
                );
            } else {
                $message = t(
                    'Do you really want to remove pricing group "%name%" permanently? It is not used anywhere.',
                    ['%name%' => $pricingGroup->getName()]
                );
                return $this->confirmDeleteResponseFactory->createDeleteResponse($message, 'admin_pricinggroup_delete', $id);
            }
        } catch (\Shopsys\ShopBundle\Model\Pricing\Group\Exception\PricingGroupNotFoundException $ex) {
            return new Response(t('Selected pricing group doesn\'t exist.'));
        }
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     */
    public function settingsAction(Request $request)
    {
        $domainId = $this->selectedDomain->getId();
        $pricingGroupSettingsFormData = [
            'defaultPricingGroup' => $this->pricingGroupSettingFacade->getDefaultPricingGroupByDomainId($domainId),
        ];

        $form = $this->createForm(PricingGroupSettingsFormType::class, $pricingGroupSettingsFormData, [
            'domain_id' => $domainId,
        ]);
        $form->handleRequest($request);

        if ($form->isValid()) {
            $pricingGroupSettingsFormData = $form->getData();

            $this->pricingGroupSettingFacade->setDefaultPricingGroupForSelectedDomain($pricingGroupSettingsFormData['defaultPricingGroup']);

            $this->getFlashMessageSender()->addSuccessFlash(t('Default pricing group settings modified'));

            return $this->redirectToRoute('admin_pricinggroup_list');
        }

        return $this->render('@ShopsysShop/Admin/Content/Pricing/Groups/pricingGroupSettings.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
