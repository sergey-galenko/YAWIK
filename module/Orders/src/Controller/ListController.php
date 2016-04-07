<?php
/**
 * YAWIK
 *
 * @filesource
 * @license MIT
 * @copyright  2013 - 2016 Cross Solution <http://cross-solution.de>
 */
  
/** */
namespace Orders\Controller;

use Orders\Entity\InvoiceAddress;
use Zend\Mvc\Controller\AbstractActionController;

/**
 * ${CARET}
 * 
 * @author Mathias Gelhausen <gelhausen@cross-solution.de>
 * @todo write test 
 */
class ListController extends AbstractActionController
{

    public function indexAction()
    {
        $form = $this->getServiceLocator()->get('forms')->get('Core/TextSearch');
        $orders = $this->paginator('Orders', [ 'sort' => 'date' ]);

        return [
            'form' => $form,
            'orders' => $orders
        ];
    }

    public function viewAction()
    {
        $id = $this->params()->fromQuery('id');

        if (!$id) {
            throw new \UnexpectedValueException('No order id given. Please provide the order id in the "id" parameter.');
        }

        $services = $this->getServiceLocator();
        $repositories = $services->get('repositories');
        $repository = $repositories->get('Orders');
        $order = $repository->find($id);

        if (!$order) {
            throw new \InvalidArgumentException('No order with id "' . $id . '" found.');
        }

        return [
            'order' => $order,
        ];
    }
    
}