<?php
/**
 * YAWIK
 *
 * @copyright (c) 2013 - 2016 Cross Solution (http://cross-solution.de)
 * @license   MIT
 */

namespace SolrTest\Event;


use CoreTestUtils\TestCase\FunctionalTestCase;
use Cv\Entity\Cv;
use Doctrine\ODM\MongoDB\Event\LifecycleEventArgs;
use Doctrine\ODM\MongoDB\Events;
use Jobs\Entity\CoordinatesInterface;
use Jobs\Entity\Job;
use Jobs\Entity\Location;
use Organizations\Entity\Organization;
use Organizations\Entity\OrganizationImage;
use Organizations\Entity\OrganizationName;
use Solr\Bridge\Manager;
use Solr\Event\Listener\JobEventSubscriber;

class JobEventSubscriberTest extends FunctionalTestCase
{
    /**
     * @var JobEventSubscriber
     */
    protected $target;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $managerMock;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $clientMock;

    public function setUp()
    {
        parent::setUp();
        $sl = $this->getApplicationServiceLocator();

        $managerMock = $this->getMockBuilder(Manager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $clientMock = $this->getMockBuilder(\SolrClient::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;
        $sl->setService('Solr/Manager', $managerMock);
        $managerMock->method('getClient')->willReturn($clientMock);
        $this->target = new JobEventSubscriber(
            $managerMock
        );
        $this->managerMock = $managerMock;
        $this->clientMock = $clientMock;
    }

    public function testShouldSubscribeToDoctrineEvent()
    {
        $subscribedEvents = $this->target->getSubscribedEvents();

        $this->assertContains(Events::postUpdate, $subscribedEvents);
        $this->assertContains(Events::postPersist, $subscribedEvents);
    }

    public function testPostPersistShouldNotProcessNonJobDocument()
    {
        $cv = new Cv();
        $mock = $this->getMockBuilder(LifecycleEventArgs::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mock->expects($this->once())
            ->method('getDocument')
            ->willReturn($cv);
        $this->clientMock
            ->expects($this->never())
            ->method('addDocument');
        $this->target->postPersist($mock);
    }

    public function testShouldProcessOnPersistEvent()
    {
        $job = new Job();
        
        $orgName = new OrganizationName();
        $orgName->setName('some-name');
        $org = new Organization();
        $org->setOrganizationName($orgName);
        
        $job->setOrganization($org);
        
        $mock = $this->getMockBuilder(LifecycleEventArgs::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mock->expects($this->once())
            ->method('getDocument')
            ->willReturn($job);
        $this->clientMock
            ->expects($this->once())
            ->method('addDocument')
            ->with($this->isInstanceOf(\SolrInputDocument::class));
        $this->clientMock
            ->expects($this->once())
            ->method('commit');
        $this->clientMock
            ->expects($this->once())
            ->method('optimize');
        $this->target->postPersist($mock);
    }

    public function testPostUpdateShouldNotProcessNonJobDocument()
    {
        $cv = new Cv();
        $mock = $this->getMockBuilder(LifecycleEventArgs::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mock->expects($this->once())
            ->method('getDocument')
            ->willReturn($cv);
        $this->clientMock
            ->expects($this->never())
            ->method('addDocument');
        $this->target->postUpdate($mock);
    }

    public function testShouldProcessOnPostUpdateEvent()
    {
        $job = new Job();
        $mock = $this->getMockBuilder(LifecycleEventArgs::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mock->expects($this->once())
            ->method('getDocument')
            ->willReturn($job);
        $this->clientMock
            ->expects($this->once())
            ->method('addDocument')
            ->with($this->isInstanceOf(\SolrInputDocument::class));
        $this->clientMock
            ->expects($this->once())
            ->method('commit');
        $this->clientMock
            ->expects($this->once())
            ->method('optimize');
        $this->target->postUpdate($mock);
    }

    public function testGenerateInputDocument()
    {
        $date = new \DateTime();
        $dateStr = $date->setTimezone(new \DateTimeZone('UTC'))->format(Manager::SOLR_DATE_FORMAT);

        $job = new Job();
        $job
            ->setId('some-id')
            ->setTitle('some-title')
            ->setContactEmail('contact-email')
            ->setDateCreated($date)
            ->setDateModified($date)
            ->setDatePublishStart($date)
            ->setDatePublishEnd($date)
            ->setLanguage('some-language')
        ;


        $document = $this->getMockBuilder(\stdClass::class)
            ->setMethods(['addField'])
            ->getMock()
        ;

        $document->expects($this->any())
            ->method('addField')
            ->withConsecutive(
                ['id','some-id'],
                ['title','some-title'],
                ['applicationEmail','contact-email'],
                ['dateCreated',$dateStr],
                ['dateModified',$dateStr],
                ['datePublishStart',$dateStr],
                ['datePublishEnd',$dateStr],
                ['isActive',false],
                ['lang','some-language']
            )
        ;
        $this->target->generateInputDocument($job,$document);
    }

    public function testProcessOrganization()
    {
        $job = $this->getMockBuilder(Job::class)
            ->getMock()
        ;
        $org = $this->getMockBuilder(Organization::class)
            ->getMock()
        ;
        $orgName = $this->getMockBuilder(OrganizationName::class)
            ->getMock()
        ;
        $orgImage = $this->getMockBuilder(OrganizationImage::class)
            ->getMock()
        ;

        $job->method('getOrganization')->willReturn($org);
        $org->method('getOrganizationName')->willReturn($orgName);
        $org->method('getImage')->willReturn($orgImage);

        $orgName->expects($this->once())
            ->method('getName')
            ->willReturn('some-name')
        ;
        $orgImage->expects($this->once())
            ->method('getUri')
            ->willReturn('some-uri')
        ;

        $document = $this->getMockBuilder(\stdClass::class)
            ->setMethods(['addField'])
            ->getMock()
        ;
        $document
            ->expects($this->exactly(3))
            ->method('addField')
            ->withConsecutive(
                ['companyLogo','some-uri'],
                ['organizationName','some-name'],
                ['organizationId','some-id']
            )
        ;
        $this->target->processOrganization($job,$document);
    }

    public function testProcessLocation()
    {
        $job = $this->getMockBuilder(Job::class)->getMock();
        $location = $this->getMockBuilder(Location::class)->getMock();
        $coordinates = $this->getMockBuilder(CoordinatesInterface::class)->getMock();

        $job->expects($this->once())
            ->method('getLocations')
            ->willReturn([$location]);
        $location->expects($this->once())
            ->method('getCoordinates')
            ->willReturn($coordinates)
        ;
        $location->expects($this->once())
            ->method('getPostalCode')
            ->willReturn('postal-code')
        ;
        $location->expects($this->once())
            ->method('getRegion')
            ->willReturn('region-text')
        ;
        $coordinates->expects($this->once())
            ->method('getCoordinates')
            ->willReturn([1.2,2.1])
        ;
        $document = $this->getMockBuilder(\stdClass::class)
            ->setMethods(['addField'])
            ->getMock()
        ;
        $document->expects($this->exactly(4))
            ->method('addField')
            ->withConsecutive(
                ['latLon','1.2,2.1'],
                ['postCode','postal-code'],
                ['regionText','region-text']
            )
        ;

        $this->target->processLocation($job,$document);
    }
}
