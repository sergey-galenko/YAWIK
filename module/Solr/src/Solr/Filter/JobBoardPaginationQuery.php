<?php
/**
 * YAWIK
 *
 * @copyright (c) 2013 - 2016 Cross Solution (http://cross-solution.de)
 * @license   MIT
 */

namespace Solr\Filter;


use Solr\Bridge\JobImportTrait;
use Jobs\Entity\Job;
use Organizations\Entity\Organization;
use Organizations\Entity\OrganizationImage;
use Organizations\Entity\OrganizationName;

class JobBoardPaginationQuery extends AbstractPaginationQuery
{
    /**
     * @var array
     */
    protected $sortPropertiesMap = [
        'company' => 'companyName',
        'date'    => 'dateCreated',
    ];

    protected $propertiesMap = [
        'organizationName' => 'convertOrganizationName',
        'companyLogo'      => 'convertCompanyLogo',
    ];

    /**
     * @inheritdoc
     */
    public function createQuery(array $params, $query)
    {
        $search = isset($params['search']) ? $params['search']:'';

        if(!empty($search)){
            $q = 'title:'.$search.' OR organizationName:'.$search;
        }else{
            $q = '*:*';
        }

        $query->setQuery($q);
        
        if(isset($params['sort'])){
            $sorts = $this->filterSort($params['sort']);
            foreach($sorts as $field=>$order){
                $query->addSortField($field,$order);
            }
        }

        if(isset($params['location'])){
            // @TODO: implement location search
        }

        return $query;
    }

    /**
     * @inheritdoc
     */
    public function getEntityClass()
    {
        return Job::class;
    }

    /**
     * Convert organizationName result
     * @param Job       $ob
     * @param string    $value
     */
    public function convertOrganizationName($ob,$value)
    {
        if(!is_object($ob->getOrganization())){
            $ob->setOrganization(new Organization());
        }
        $orgName = new OrganizationName($value);
        $ob->getOrganization()->setOrganizationName($orgName);
    }

    /**
     * Convert companyLogo result
     * @param   Job     $ob
     * @param   mixed   $value
     */
    public function convertCompanyLogo($ob,$value)
    {
        if(!is_object($ob->getOrganization())){
            $ob->setOrganization(new Organization());
        }
        $exp    = explode('/',$value);
        $id     = $exp[3];
        $name   = $exp[4];
        $image = new OrganizationImage();
        $image->setId($id);
        $image->setName($name);
        $ob->getOrganization()->setImage($image);
    }
}