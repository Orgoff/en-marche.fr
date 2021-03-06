<?php

namespace Tests\AppBundle\Controller\EnMarche;

use AppBundle\DataFixtures\ORM\LoadAdherentData;
use AppBundle\DataFixtures\ORM\LoadEventCategoryData;
use AppBundle\DataFixtures\ORM\LoadHomeBlockData;
use AppBundle\DataFixtures\ORM\LoadNewsletterSubscriptionData;
use AppBundle\DataFixtures\ORM\LoadReferentManagedUserData;
use AppBundle\Entity\Event;
use AppBundle\Entity\ReferentManagedUsersMessage;
use AppBundle\Mailer\Message\EventRegistrationConfirmationMessage;
use AppBundle\Repository\ReferentManagedUsersMessageRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\AppBundle\Controller\ControllerTestTrait;
use Tests\AppBundle\MysqlWebTestCase;

/**
 * @group functional
 * @group referent
 */
class ReferentControllerTest extends MysqlWebTestCase
{
    use ControllerTestTrait;

    /**
     * @var ReferentManagedUsersMessageRepository
     */
    private $referentMessageRepository;

    /**
     * @dataProvider providePages
     */
    public function testReferentBackendIsForbiddenAsAnonymous($path)
    {
        $this->client->request(Request::METHOD_GET, $path);
        $this->assertClientIsRedirectedTo('http://'.$this->hosts['app'].'/connexion', $this->client);
    }

    /**
     * @dataProvider providePages
     */
    public function testReferentBackendIsForbiddenAsAdherentNotReferent($path)
    {
        $this->authenticateAsAdherent($this->client, 'carl999@example.fr');

        $this->client->request(Request::METHOD_GET, $path);
        $this->assertStatusCode(Response::HTTP_FORBIDDEN, $this->client);
    }

    /**
     * @dataProvider providePages
     */
    public function testReferentBackendIsAccessibleAsReferent($path)
    {
        $this->authenticateAsAdherent($this->client, 'referent@en-marche-dev.fr');

        $this->client->request(Request::METHOD_GET, $path);
        $this->assertStatusCode(Response::HTTP_OK, $this->client);
    }

    public function testCreateEventFailed()
    {
        $this->authenticateAsAdherent($this->client, 'referent@en-marche-dev.fr');

        $this->client->request(Request::METHOD_GET, '/espace-referent/evenements/creer');

        $data = [];

        $this->client->submit($this->client->getCrawler()->selectButton('Créer cet événement')->form(), $data);
        $this->assertSame(4, $this->client->getCrawler()->filter('.form__errors')->count());

        $this->assertSame('Cette valeur ne doit pas être vide.',
            $this->client->getCrawler()->filter('#committee-event-name-field > .form__errors > li')->text());
        $this->assertSame('Cette valeur ne doit pas être vide.',
            $this->client->getCrawler()->filter('#committee-event-description-field > .form__errors > li')->text());
        $this->assertSame('L\'adresse est obligatoire.',
            $this->client->getCrawler()->filter('#committee-event-address-address-field > .form__errors > li')->text());
        $this->assertSame('Votre adresse n\'est pas reconnue. Vérifiez qu\'elle soit correcte.',
            $this->client->getCrawler()->filter('#committee-event-address > .form__errors > li')->text());
    }

    public function testCreateEventSuccessful()
    {
        $this->authenticateAsAdherent($this->client, 'referent@en-marche-dev.fr');

        $this->client->request(Request::METHOD_GET, '/espace-referent/evenements/creer');

        $data = [];
        $data['committee_event']['name'] = 'premier événement';
        $data['committee_event']['category'] = $this->getEventCategoryIdForName('Événement innovant');
        $data['committee_event']['beginAt']['date']['day'] = 14;
        $data['committee_event']['beginAt']['date']['month'] = 6;
        $data['committee_event']['beginAt']['date']['year'] = date('Y');
        $data['committee_event']['beginAt']['time']['hour'] = preg_replace('/0/', '', date('H'), 1);
        $data['committee_event']['beginAt']['time']['minute'] = 0;
        $data['committee_event']['finishAt']['date']['day'] = 15;
        $data['committee_event']['finishAt']['date']['month'] = 6;
        $data['committee_event']['finishAt']['date']['year'] = date('Y');
        $data['committee_event']['finishAt']['time']['hour'] = 23;
        $data['committee_event']['finishAt']['time']['minute'] = 0;
        $data['committee_event']['address']['address'] = 'Pilgerweg 58';
        $data['committee_event']['address']['cityName'] = 'Kilchberg';
        $data['committee_event']['address']['postalCode'] = '8802';
        $data['committee_event']['address']['country'] = 'CH';
        $data['committee_event']['description'] = 'Premier événement en Suisse';
        $data['committee_event']['capacity'] = 100;

        $this->client->submit($this->client->getCrawler()->selectButton('Créer cet événement')->form(), $data);

        /** @var Event $event */
        $event = $this->getEventRepository()->findOneBy(['name' => 'Premier événement']);

        $this->assertInstanceOf(Event::class, $event);
        $this->assertClientIsRedirectedTo('/evenements/'.$event->getSlug(), $this->client);

        $this->client->followRedirect();
        $this->assertResponseStatusCode(Response::HTTP_OK, $this->client->getResponse());

        $this->assertSame($this->formatEventDate($event->getBeginAt()), $this->client->getCrawler()->filter('span.committee-event-date')->text());
        $this->assertSame('Pilgerweg 58, 8802 Kilchberg, Suisse', $this->client->getCrawler()->filter('span.committee-event-address')->text());
        $this->assertSame('Premier événement en Suisse', $this->client->getCrawler()->filter('div.committee-event-description')->text());
        $this->assertContains('1 inscrit', $this->client->getCrawler()->filter('div.committee-event-attendees')->html());

        $this->assertCountMails(1, EventRegistrationConfirmationMessage::class, 'referent@en-marche-dev.fr');
    }

    public function testSearchUserToSendMail()
    {
        $this->authenticateAsAdherent($this->client, 'referent@en-marche-dev.fr');

        $this->client->request(Request::METHOD_GET, '/espace-referent/utilisateurs');
        $this->assertSame(4, $this->client->getCrawler()->filter('tbody tr.referent__item')->count());

        $data = [
            'anc' => 1,
            'aic' => 1,
            'h' => 1,
            'ac' => 77,
        ];
        $this->client->submit($this->client->getCrawler()->selectButton('Filtrer')->form(), $data);
        $this->assertSame(1, $this->client->getCrawler()->filter('tbody tr.referent__item')->count());

        $this->client->request(Request::METHOD_GET, '/espace-referent/utilisateurs');
        $this->assertSame(4, $this->client->getCrawler()->filter('tbody tr.referent__item')->count());

        $data = [
            'anc' => 1,
            'aic' => 1,
            'h' => 1,
            's' => 1,
            'city' => 'Zürich, Kilchberg',
        ];
        $this->client->submit($this->client->getCrawler()->selectButton('Filtrer')->form(), $data);
        $this->assertSame(2, $this->client->getCrawler()->filter('tbody tr.referent__item')->count());

        $data = [
            'anc' => 1,
            'aic' => 1,
            'h' => 1,
            'ac' => 'CH',
        ];
        $this->client->submit($this->client->getCrawler()->selectButton('Filtrer')->form(), $data);
        $this->assertSame(2, $this->client->getCrawler()->filter('tbody tr.referent__item')->count());

        $data = [
            'anc' => 1,
            'aic' => 1,
            'h' => 1,
            'ac' => 13,
        ];
        $this->client->submit($this->client->getCrawler()->selectButton('Filtrer')->form(), $data);
        $this->assertSame(0, $this->client->getCrawler()->filter('tbody tr.referent__item')->count());

        $data = [
            'anc' => 1,
            'aic' => 1,
            'h' => 1,
            'ac' => 91,
        ];
        $this->client->submit($this->client->getCrawler()->selectButton('Filtrer')->form(), $data);
        $this->assertSame(0, $this->client->getCrawler()->filter('tbody tr.referent__item')->count());
    }

    public function testCancelSendMail()
    {
        $this->authenticateAsAdherent($this->client, 'referent@en-marche-dev.fr');

        $this->client->request(Request::METHOD_GET, '/espace-referent/utilisateurs');
        $data = [
            'anc' => 1,
            'aic' => 1,
            'h' => 1,
            's' => 1,
        ];
        $this->client->submit($this->client->getCrawler()->selectButton('Filtrer')->form(), $data);
        $this->client->click($this->client->getCrawler()->selectLink('Leur envoyer un message')->link());
        $this->assertResponseStatusCode(Response::HTTP_OK, $this->client->getResponse());
        $this->assertContains('http://'.$this->hosts['app'].'/espace-referent/utilisateurs/message', $this->client->getRequest()->getUri());

        $this->client->click($this->client->getCrawler()->selectLink('Annuler')->link());
        $this->assertResponseStatusCode(Response::HTTP_OK, $this->client->getResponse());
        $this->assertEquals('http://'.$this->hosts['app'].'/espace-referent/utilisateurs', $this->client->getRequest()->getUri());
    }

    public function testSendMailFailed()
    {
        $this->authenticateAsAdherent($this->client, 'referent@en-marche-dev.fr');

        $this->client->request(Request::METHOD_GET, '/espace-referent/utilisateurs');
        $data = [
            'anc' => 1,
            'aic' => 1,
            'h' => 1,
            's' => 1,
        ];
        $this->client->submit($this->client->getCrawler()->selectButton('Filtrer')->form(), $data);
        $this->client->click($this->client->getCrawler()->selectLink('Leur envoyer un message')->link());

        $data = [];
        $this->client->submit($this->client->getCrawler()->selectButton('Envoyer le message')->form(), $data);

        $this->assertSame(2, $this->client->getCrawler()->filter('.form__errors')->count());
        $this->assertSame('Cette valeur ne doit pas être vide.',
            $this->client->getCrawler()->filter('label[for=referent_message_subject] + .form__errors > li')->text());
        $this->assertSame('Cette valeur ne doit pas être vide.',
            $this->client->getCrawler()->filter('label[for=referent_message_content] + .form__errors > li')->text());
    }

    public function testSendMailSuccessful()
    {
        $this->authenticateAsAdherent($this->client, 'referent@en-marche-dev.fr');

        $this->client->request(Request::METHOD_GET, '/espace-referent/utilisateurs');
        $data = [
            'anc' => 1,
            'aic' => 1,
            'h' => 1,
            's' => 1,
        ];
        $this->client->submit($this->client->getCrawler()->selectButton('Filtrer')->form(), $data);
        $this->client->click($this->client->getCrawler()->selectLink('Leur envoyer un message')->link());
        $this->assertContains('Referent Referent', $this->client->getCrawler()->filter('form')->html());
        $this->assertContains('4 marcheurs(s)', $this->client->getCrawler()->filter('form')->html());

        $data = [];
        $data['referent_message']['subject'] = 'Event reminder';
        $data['referent_message']['content'] = 'One event is planned.';
        $this->client->submit($this->client->getCrawler()->selectButton('Envoyer le message')->form(), $data);

        $this->assertResponseStatusCode(Response::HTTP_FOUND, $this->client->getResponse());
        $this->client->followRedirect();

        $this->assertResponseStatusCode(Response::HTTP_OK, $this->client->getResponse());
        $this->assertContains('http://'.$this->hosts['app'].'/espace-referent/utilisateurs?', $this->client->getRequest()->getUri());

        $referentMessages = $this
            ->referentMessageRepository
            ->createQueryBuilder('m')
            ->innerJoin('m.from', 'a')
            ->addSelect('a')
            ->getQuery()
            ->getResult()
        ;

        $this->assertCount(1, $referentMessages);

        /* @var ReferentManagedUsersMessage */
        $message = reset($referentMessages);

        $this->assertSame('referent@en-marche-dev.fr', $message->getFrom()->getEmailAddress());
        $this->assertSame('Event reminder', $message->getSubject());
        $this->assertSame('One event is planned.', $message->getContent());
        $this->assertTrue($message->includeAdherentsNoCommittee());
        $this->assertTrue($message->includeAdherentsInCommittee());
        $this->assertTrue($message->includeHosts());
        $this->assertTrue($message->includeSupevisors());
        $this->assertEmpty($message->getQueryAreaCode());
        $this->assertEmpty($message->getQueryCity());
        $this->assertEmpty($message->getQueryId());
        $this->assertSame(0, $message->getOffset());
    }

    public function testFilterAdherents()
    {
        $this->authenticateAsAdherent($this->client, 'referent@en-marche-dev.fr');

        $this->client->request(Request::METHOD_GET, '/espace-referent/utilisateurs');

        $this->assertCount(4, $this->client->getCrawler()->filter('tbody tr.referent__item'));

        // filter hosts
        $data = [
            'h' => true,
            's' => false,
            'anc' => false,
            'aic' => false,
        ];

        $this->client->submit($this->client->getCrawler()->selectButton('Filtrer')->form(), $data);

        $this->assertContains('1 contact(s) trouvés', $this->client->getCrawler()->filter('.referent__filters__count')->text());
        $this->assertCount(1, $this->client->getCrawler()->filter('tbody tr.referent__item'));
        $this->assertCount(1, $this->client->getCrawler()->filter('tbody tr.referent__item--host'));
        $this->assertContains('Gisele', $this->client->getCrawler()->filter('tbody tr.referent__item')->text());
        $this->assertContains('Berthoux', $this->client->getCrawler()->filter('tbody tr.referent__item')->text());

        // filter supervisors
        $data = [
            'h' => false,
            's' => true,
            'anc' => false,
            'aic' => false,
        ];

        $this->client->submit($this->client->getCrawler()->selectButton('Filtrer')->form(), $data);

        $this->assertContains('1 contact(s) trouvés', $this->client->getCrawler()->filter('.referent__filters__count')->text());
        $this->assertCount(1, $this->client->getCrawler()->filter('tbody tr.referent__item'));
        $this->assertCount(1, $this->client->getCrawler()->filter('tbody tr.referent__item--host'));
        $this->assertContains('Francis', $this->client->getCrawler()->filter('tbody tr.referent__item')->text());
        $this->assertContains('Brioul', $this->client->getCrawler()->filter('tbody tr.referent__item')->text());

        // filter newsletter subscriptions
        $data = [
            'h' => false,
            's' => false,
            'anc' => false,
            'aic' => false,
        ];

        $this->client->submit($this->client->getCrawler()->selectButton('Filtrer')->form(), $data);

        $this->assertContains('4 contact(s) trouvés', $this->client->getCrawler()->filter('.referent__filters__count')->text());
        $this->assertCount(4, $this->client->getCrawler()->filter('tbody tr.referent__item'));
        $this->assertContains('77000', $this->client->getCrawler()->filter('tbody tr.referent__item')->first()->text());
        $this->assertContains('8802', $this->client->getCrawler()->filter('tbody tr.referent__item')->eq(1)->text());

        // filter adherents in no committee
        $data = [
            'h' => false,
            's' => false,
            'anc' => true,
            'aic' => false,
        ];

        $this->client->submit($this->client->getCrawler()->selectButton('Filtrer')->form(), $data);

        $this->assertContains('3 contact(s) trouvés', $this->client->getCrawler()->filter('.referent__filters__count')->text());
        $this->assertCount(3, $this->client->getCrawler()->filter('tbody tr.referent__item'));
        $this->assertCount(2, $this->client->getCrawler()->filter('tbody tr.referent__item--host'));
        $this->assertCount(1, $this->client->getCrawler()->filter('tbody tr.referent__item--adherent'));
        $this->assertContains('Francis', $this->client->getCrawler()->filter('tbody tr.referent__item')->first()->text());
        $this->assertContains('Brioul', $this->client->getCrawler()->filter('tbody tr.referent__item')->first()->text());
        $this->assertContains('Gisele', $this->client->getCrawler()->filter('tbody tr.referent__item')->eq(1)->text());
        $this->assertContains('Berthoux', $this->client->getCrawler()->filter('tbody tr.referent__item')->eq(1)->text());
        $this->assertContains('Michelle', $this->client->getCrawler()->filter('tbody tr.referent__item')->eq(2)->text());
        $this->assertContains('D.', $this->client->getCrawler()->filter('tbody tr.referent__item')->eq(2)->text());

        // filter adherents in committees
        $data = [
            'h' => false,
            's' => false,
            'anc' => false,
            'aic' => true,
        ];

        $this->client->submit($this->client->getCrawler()->selectButton('Filtrer')->form(), $data);

        $this->assertContains('1 contact(s) trouvés', $this->client->getCrawler()->filter('.referent__filters__count')->text());
        $this->assertCount(1, $this->client->getCrawler()->filter('tbody tr.referent__item'));
        $this->assertCount(1, $this->client->getCrawler()->filter('tbody tr.referent__item--adherent'));
        $this->assertContains('Michel', $this->client->getCrawler()->filter('tbody tr.referent__item')->text());
        $this->assertContains('V.', $this->client->getCrawler()->filter('tbody tr.referent__item')->text());
    }

    public function providePages()
    {
        return [
            ['/espace-referent/utilisateurs'],
            ['/espace-referent/evenements'],
            ['/espace-referent/comites'],
            ['/espace-referent/evenements/creer'],
        ];
    }

    /**
     * @return string Date in the format "Jeudi 14 juin 2018, 9h00"
     */
    private function formatEventDate(\DateTime $date): string
    {
        $formatter = new \IntlDateFormatter(
            'fr_FR',
            \IntlDateFormatter::NONE,
            \IntlDateFormatter::NONE,
            null,
            \IntlDateFormatter::GREGORIAN,
            'EEEE d LLLL Y, H');

        return ucfirst(strtolower($formatter->format($date).'h00'));
    }

    protected function setUp()
    {
        parent::setUp();

        $this->init([
            LoadAdherentData::class,
            LoadEventCategoryData::class,
            LoadHomeBlockData::class,
            LoadNewsletterSubscriptionData::class,
            LoadReferentManagedUserData::class,
        ]);

        $this->referentMessageRepository = $this->manager->getRepository(ReferentManagedUsersMessage::class);
    }

    protected function tearDown()
    {
        $this->kill();

        $this->referentMessageRepository = null;

        parent::tearDown();
    }
}
