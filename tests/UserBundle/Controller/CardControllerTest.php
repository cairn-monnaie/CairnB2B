<?php

namespace Tests\UserBundle\Controller;

use Tests\UserBundle\Controller\BaseControllerTest;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Cairn\UserCyclosBundle\Entity\UserManager;
use Cairn\UserBundle\Entity\User;
use Cairn\UserBundle\Entity\Card;
use Cairn\UserBundle\Entity\Address;

use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Cyclos;

class CardControllerTest extends BaseControllerTest
{

    public function __construct($name = NULL, array $data = array(), $dataName = ''){
        parent::__construct($name, $data, $dataName);
    }

    /**
     * @TODO : tester directement la présence des urls
     *_$referent wants card action for himself :
     *                          .installed superadmin : revoke / associate / download
     *                          .otherwise : revoke / associate 
     *_$referent wants card action for $target :
     *                          .isGranted ROLE_ADMIN : 
     *                              *if $referent is referent of $target : revoke / download / associate
     *                              *otherwise : nothing(should not even see card_operations) 
     * @dataProvider provideCardOperationsData
     */
    public function testCardOperations($referent,$target,$isReferent,$nbLinks)
    {
        $crawler = $this->login($referent, '@@bbccdd');

        $currentUser = $this->em->getRepository('CairnUserBundle:User')->findOneBy(array('username'=>$referent));
        $targetUser  = $this->em->getRepository('CairnUserBundle:User')->findOneBy(array('username'=>$target));

        $crawler = $this->client->request('GET','/card/home/'.$targetUser->getUsername());

        if($currentUser !== $targetUser && !$isReferent){
            $this->assertEquals(403, $this->client->getResponse()->getStatusCode());
        }else{
            $this->assertSame($nbLinks,$crawler->filter('a')->count());
        }

    }

    public function provideCardOperationsData()
    {
        $adminUsername = $this->testAdmin;
        return array(
            'superadmin with card for himself'=>array('referent'=>$adminUsername,'target'=>$adminUsername,'isReferent'=>true,1),
            'referent for user with no card'=>array('referent'=>$adminUsername,'target'=>'episol','isReferent'=>true,2),
            'non referent'=> array('referent'=>$adminUsername,'target'=>'vie_integrative','isReferent'=>false,0),
            'pro with card'=> array('referent'=>'vie_integrative','target'=>'vie_integrative','isReferent'=>false,1),
            'pro without card'=>array('referent'=>'episol','target'=>'episol','isReferent'=>false,3),
            'non referent'=>array('referent'=>'vie_integrative','target'=>'DrDBrew','isReferent'=>false,0),
        );
    }

    /**
     *
     *@dataProvider provideDataForOrderCard
     */
    public function testOrderCard($login, $isAdherent, $hasCard,$type, $expectMessage )
    {
        $crawler = $this->login($login, '@@bbccdd');

        $currentUser = $this->em->getRepository('CairnUserBundle:User')->findOneBy(array('username'=>$login));

        $this->client->enableProfiler();

        $url = '/card/order/'.$type;
        $crawler = $this->client->request('GET',$url );

        if(! $isAdherent){
            $this->assertTrue($this->client->getResponse()->isRedirect('/user/profile/view/'.$currentUser->getUsername()));
            $crawler = $this->client->followRedirect();
            $this->assertContains($expectMessage,$this->client->getResponse()->getContent());
            return;
        }

        if( $hasCard){
            $this->assertTrue($this->client->getResponse()->isRedirect('/user/profile/view/'.$currentUser->getUsername()));
            $crawler = $this->client->followRedirect();
            $this->assertContains($expectMessage,$this->client->getResponse()->getContent());
            return;
        }

        $mailCollector = $this->client->getProfile()->getCollector('swiftmailer');

        //assert email
        $this->assertSame(2, $mailCollector->getMessageCount());
        $messages = $mailCollector->getMessages();

        foreach($messages as $message){
            $this->assertInstanceOf('Swift_Message', $message);
            $this->assertContains('Envoi postal', $message->getSubject());
            $this->assertContains('par voie postale', $message->getBody());
            $this->assertContains($currentUser->getCity(), $message->getBody());
            $this->assertContains($currentUser->getAddress()->getStreet1(), $message->getBody());
            $this->assertContains($currentUser->getAddress()->getZipCity()->getZipCode(), $message->getBody());
            $this->assertSame($this->container->getParameter('cairn_email_noreply'), key($message->getFrom()));
            
            $to = key($message->getTo());
            $this->assertTrue( ( $to == $currentUser->getEmail()) || ($to == $this->container->getParameter('cairn_email_management') ));
        }
    }

    public function provideDataForOrderCard()
    {
        return array(
            'is admin' => array('gl_grenoble',false,true,'remote','réservée aux adhérents'),
            'has card' => array('benoit_perso',true,true,'remote','déjà une carte'),
            'valid : adherent has no card' => array('episol',true,false,'remote',''),

        );

    }

    /**
     *Tests the card association using a key
     *@dataProvider provideUsersForCardAssociation
     */
    public function testAssociateCard($referent,$target,$code, $expectForm, $expectValid)
    {
        $crawler = $this->login($referent, '@@bbccdd');

        $currentUser = $this->em->getRepository('CairnUserBundle:User')->findOneBy(array('username'=>$referent));
        $targetUser = $this->em->getRepository('CairnUserBundle:User')->findOneBy(array('username'=>$target));

        $url = '/card/associate/'.$targetUser->getUsername();
        $crawler = $this->client->request('GET',$url );

        if($currentUser !== $targetUser){
            $this->assertTrue($this->client->getResponse()->isRedirect('/security/card/?url='.$url));
            $crawler = $this->client->followRedirect();
            $crawler = $this->inputCardKey($crawler, '1111');
            $crawler = $this->client->followRedirect();
        }

        if(!$expectForm){
            if(! ($targetUser->hasReferent($currentUser) || $targetUser === $currentUser)){
                //access denied exception
                $this->assertEquals(403, $this->client->getResponse()->getStatusCode());
            }else{
                $this->assertTrue($this->client->getResponse()->isRedirect('/user/profile/view/'.$targetUser->getUsername()));
                $crawler = $this->client->followRedirect();
            }
        }else{

            $form = $crawler->selectButton('form_add')->form();
            $form['form[code]']->setValue($this->container->get('cairn_user.security')->vigenereEncode($code));
            $crawler = $this->client->submit($form);

            $this->em->refresh($currentUser);
            $this->em->refresh($targetUser);

            if(!$expectValid){
                $this->assertTrue($this->client->getResponse()->isRedirect($url));
                $crawler = $this->client->followRedirect();

                $this->assertSame(1,$currentUser->getCardAssociationTries());
                $this->assertEquals($targetUser->getCard(), NULL);

            }else{
                $crawler = $this->client->followRedirect();
                $this->assertSame(0,$crawler->filter('html:contains("Associer")')->count());

                $this->assertSame(0,$currentUser->getCardAssociationTries());
                $this->assertNotEquals($targetUser->getCard(), NULL);
                $this->assertEquals($targetUser->getCard()->getExpirationDate(), NULL);
            }
        }
    }

    public function provideUsersForCardAssociation()
    {
        $adminUsername = $this->testAdmin;

        $availableCode = 'SINGLE0';
        $proCode =  'PRO_CODE'; 
        $personCode = 'PERSO_CODE';

        return array(
            'valid user + invalid code'   => array('referent'=>$adminUsername,'target'=>'episol','code'=>'aaaaa',
                                    'expectForm'=>true,'expectValidKey'=>false),
            'referent + valid user + valid code'     => array('referent'=>$adminUsername,'target'=>'episol','code'=>$availableCode,
                                    'expectForm'=>true,'expectValidKey'=>true),
            'valid user + valid code'     => array('referent'=>'NaturaVie','target'=>'NaturaVie','code'=>$availableCode,
                                    'expectForm'=>true,'expectValidKey'=>true),
            'non referent + valid user' => array('referent'=>$adminUsername,'target'=>'NaturaVie','code'=>$availableCode,
                                    'expectForm'=>false,'expectValidKey'=>true),
            'invalid : card already assoc to pro' => array('referent'=>'episol','target'=>'episol','code'=>$proCode,
                                    'expectForm'=>true,'expectValidKey'=>false),
            'invalid : card already assoc to person' => array('referent'=>'speedy_andrew','target'=>'speedy_andrew','code'=>$personCode,
                                    'expectForm'=>true,'expectValidKey'=>false),
            'valid : pro card assoc to person' => array('referent'=>'speedy_andrew','target'=>'speedy_andrew','code'=>$proCode,
                                    'expectForm'=>true,'expectValidKey'=>true),
            'valid : person card assoc to pro' => array('referent'=>'episol','target'=>'episol','code'=>$personCode,
                                    'expectForm'=>true,'expectValidKey'=>true),
        );
    }

    /**
     *Tests if card intermediate step is reached for a  sensible operation
     *@dataProvider provideUsersWithValidatedCard
     */
    public function testCardSecurityLayer($login,$expectValid)
    {
        $crawler = $this->login($login, '@@bbccdd');

        $currentUser = $this->em->getRepository('CairnUserBundle:User')->findOneBy(array('username'=>$login));

        //any sensible operation (see Cairn/UserBundle/Event/SecurityEvents.php)
        $url = '/user/beneficiaries/add';
        $crawler = $this->client->request('GET', $url);
        $this->assertTrue($this->client->getResponse()->isRedirect('/security/card/?url='.$url));

        $crawler = $this->client->followRedirect();

        $card = $currentUser->getCard();
        if(!$expectValid){
            $this->assertTrue($this->client->getResponse()->isRedirect('/user/profile/view/'.$currentUser->getUsername()));
            $crawler = $this->client->followRedirect();

        }else{
            $crawler = $this->inputCardKey($crawler,'1111');
            $this->assertTrue($this->client->getResponse()->isRedirect($url));
        }
    }

    public function provideUsersWithValidatedCard()
    {
        $adminUsername = $this->testAdmin;

        return array(
            'user with card'     => array('login'=>$adminUsername, 'expectValid'=>true),
            'user with no card' => array('login'=>'episol','expectValid'=>false),
        );
    }

    /**
     * if $current is installed super admin : can download a card for himself
     * if $current is referent of $target : can download card for $target
     * if $current is not referent of $target : code 403
     * if $current does not have card : redirection
     * if $target has a  card : redirection
     *
     *@dataProvider provideUsersForCardDownload
     */
    public function testDownloadAndAssociateCard($current, $target,$expectConfirm,$expectMessage)
    {
        $crawler = $this->login($current, '@@bbccdd');

        $currentUser = $this->em->getRepository('CairnUserBundle:User')->findOneBy(array('username'=>$current));
        $targetUser  = $this->em->getRepository('CairnUserBundle:User')->findOneBy(array('username'=>$target));

        //sensible operation
        $crawler = $this->client->request('GET','/card/download/'.$targetUser->getUsername());

        if(! ($targetUser->hasRole('ROLE_SUPER_ADMIN') && $targetUser === $currentUser)){
            $crawler = $this->client->followRedirect();
            $crawler = $this->inputCardKey($crawler, '1111');
            $crawler = $this->client->followRedirect();
        }

        if(!$targetUser->hasReferent($currentUser)){
            //access denied exception
            $this->assertEquals(403, $this->client->getResponse()->getStatusCode());
            $this->assertContains($expectMessage,$this->client->getResponse()->getContent());
        }else{
            if(!$expectConfirm){
                $this->assertTrue($this->client->getResponse()->isRedirect('/user/profile/view/'.$targetUser->getUsername()));
                $crawler = $this->client->followRedirect();
                $this->assertContains($expectMessage,$this->client->getResponse()->getContent());
            }else{
                $form = $crawler->selectButton('confirmation_save')->form();
                $crawler =  $this->client->submit($form);

                $this->em->refresh($targetUser);
                $this->assertNotEquals($targetUser->getCard(),NULL);
                $this->assertEquals($targetUser->getCard()->getExpirationDate(), NULL);
                $this->assertTrue($this->client->getResponse()->headers->contains(
                    'Content-Type',
                    'application/pdf'));
            }
        }
    }

    public function provideUsersForCardDownload()
    {
        $adminUsername = $this->testAdmin;

        return array(
            'user with card' => array('current'=>'labonnepioche','target'=>'labonnepioche','expectConfirm'=>false,
            'expectMessage'=>'déjà une carte'),             
            'valid user with no card' => array('current'=>$adminUsername,'target'=>'episol','expectConfirm'=>true,
            'expectMessage'=>'xxx'),             
            'not referent'          =>array('current'=>$adminUsername,'target'=>'vie_integrative','expectConfirm'=>false,
            'expectMessage'=>'pas référent'),             

        );
    }

    /**
     * $target wants to revoke his card
     *_$current = $target || $current is referent of $target
     *                          . no card || card revoked : redirection to card home page
     *                          . card not revoked : password confirmation
     *                                                      . success password : session message with key "success" + card exists
     *                                                      . wrong password : try again
     *@dataProvider provideUsersForCardRevocation 
     */
    public function testRevokeCard($current, $target,$expectForm,$expectMessage,$emailSent)
    {
        $crawler = $this->login($current, '@@bbccdd');

        $currentUser = $this->em->getRepository('CairnUserBundle:User')->findOneBy(array('username'=>$current));
        $targetUser  = $this->em->getRepository('CairnUserBundle:User')->findOneBy(array('username'=>$target));

        //sensible operation
        $url = '/card/revoke/'.$targetUser->getUsername();
        $crawler = $this->client->request('GET',$url);
        if($currentUser !== $targetUser){
            $this->assertTrue($this->client->getResponse()->isRedirect('/security/card/?url='.$url));
            $crawler = $this->client->followRedirect();
            $crawler = $this->inputCardKey($crawler, '1111');
            $crawler = $this->client->followRedirect();
        }

        if(! ($targetUser->hasReferent($currentUser) || $targetUser === $currentUser)){
            //access denied exception
            $this->assertEquals(403, $this->client->getResponse()->getStatusCode());
            $this->assertContains($expectMessage,$this->client->getResponse()->getContent());
        }else{
            $targetCard = $targetUser->getCard();

            if(!$expectForm){
                $this->assertTrue($this->client->getResponse()->isRedirect('/user/profile/view/'.$targetUser->getUsername()));
                $crawler = $this->client->followRedirect();
                $this->assertContains($expectMessage,$this->client->getResponse()->getContent());

            }else{
                $cardOwners = $targetCard->getUsers();

                $this->client->enableProfiler();

                $form = $crawler->selectButton('confirmation_save')->form();
                $form['confirmation[current_password]']->setValue('@@bbccdd');
                $crawler =  $this->client->submit($form);
                $this->assertTrue($this->client->getResponse()->isRedirect('/user/profile/view/'.$targetUser->getUsername()));

                //assert user has no card and card has been removed
// remove if test pass                $this->em->refresh($targetUser);

                $targetCard = $this->em->getRepository('CairnUserBundle:Card')->findOneById($targetCard->getID());

                foreach($cardOwners as $owner){
                    $this->assertEquals($owner->getCard(),NULL);
                }
                $this->assertEquals($targetCard, NULL);

                //assert SMS operations disabled

                $phones = $targetUser->getPhones();
                foreach($phones as $phone){
                    $this->assertFalse($phone->isPaymentEnabled());
                }

                $mailCollector = $this->client->getProfile()->getCollector('swiftmailer');

                if($emailSent){
                    //assert email
                    $this->assertSame(1, $mailCollector->getMessageCount());
                    $message = $mailCollector->getMessages()[0];
                    $this->assertInstanceOf('Swift_Message', $message);
                    $this->assertContains('Révocation', $message->getSubject());
                    $this->assertContains('révocation', $message->getBody());
                    $this->assertContains($currentUser->getName(), $message->getBody());
                    $this->assertSame($this->container->getParameter('cairn_email_noreply'), key($message->getFrom()));
                    $this->assertSame($targetUser->getEmail(), key($message->getTo()));
                }else{
                    $this->assertSame(0, $mailCollector->getMessageCount());
                }
                $crawler = $this->client->followRedirect();

            }
        }
    }

    public function provideUsersForCardRevocation()
    {
        $adminUsername = $this->testAdmin;

        return array(
            'revocation from ref'=> array('current'=>$adminUsername,'target'=>'labonnepioche','expectForm'=>true,'expectMessage'=>'xxx',
            'emailSent'=>true), 

            'self revocation'=> array('current'=>'labonnepioche','target'=>'labonnepioche','expectForm'=>true,'expectMessage'=>'xxx',
            'emailSent'=>false),             

            'card assoc to 2 accounts'=> array('current'=>'nico_faus_perso','target'=>'nico_faus_perso','expectForm'=>true,
            'expectMessage'=>'xxx', 'emailSent'=>false),             

            'self revocation with phone number'=> array('current'=>'maltobar','target'=>'maltobar','expectForm'=>true,
                                                        'expectMessage'=>'xxx','emailSent'=>false),             

            'revoc from non ref'=>array('current'=>$adminUsername,'target'=>'vie_integrative','expectForm'=>false,
                                        'expectMessage'=>'pas référent','emailSent'=>false),

            'no card to revoke'=>array('current'=>'episol','target'=>'episol','expectForm'=>false,
                                      'expectMessage'=>'déjà été révoquée','emailSent'=>false),
        );
    }


    /**
     *@dataProvider provideDataForCardDestruction
     */
    public function testDestructCard($isValid, $code)
    {
        $crawler = $this->login('admin_network', '@@bbccdd');

        $card = $this->em->getRepository('CairnUserBundle:Card')->findOneByCode($code);

        //sensible operation
        $url = '/card/destruct/'.$card->getID();
        $crawler = $this->client->request('GET',$url);

        $this->assertTrue($this->client->getResponse()->isRedirect('/security/card/?url='.$url));
        $crawler = $this->client->followRedirect();
        $crawler = $this->inputCardKey($crawler, '1111');
        $crawler = $this->client->followRedirect();

        if($isValid){
            $form = $crawler->selectButton('confirmation_save')->form();
            $form['confirmation[current_password]']->setValue('@@bbccdd');
            $crawler =  $this->client->submit($form);
            $this->assertTrue($this->client->getResponse()->isRedirect('/admin/cards/dashboard'));
        }else{
            $this->assertEquals(403, $this->client->getResponse()->getStatusCode());
        }

    }

    public function provideDataForCardDestruction()
    {
        return array(
            'card associated with user' => array('isValid'=>false,'card'=>'PRO_CODE'),
            'valid card to destruct'    => array('isValid'=>true ,'code'=>'SINGLE0'),
        );
    }

    public function testCardsDashboard()
    {
        $crawler = $this->login('admin_network', '@@bbccdd');

        //sensible operation
        $url = '/admin/cards/dashboard';
        $crawler = $this->client->request('GET',$url);

        $this->assertTrue($this->client->getResponse()->isRedirect('/security/card/?url='.$url));
        $crawler = $this->client->followRedirect();
        $crawler = $this->inputCardKey($crawler, '1111');
        $crawler = $this->client->followRedirect();

    }

    /**
     *@dataProvider provideDataForSetGeneration
     */
    public function testGenerateSetOfCards($isExceeded)
    {
        $crawler = $this->login('admin_network', '@@bbccdd');

        $nbPrintableCards = $this->container->getParameter('max_printable_cards') - count($this->em->getRepository('CairnUserBundle:Card')->findAvailableCards()) ;
        if($isExceeded){
            $nbRequestedCards = $nbPrintableCards + 1;
        }else{
            $nbRequestedCards = $nbPrintableCards -1;
        }

        $nbAvailableCardsBefore = count($this->em->getRepository('CairnUserBundle:Card')->findAvailableCards());

        //sensible operation
        $url = '/card/generate-set';
        $crawler = $this->client->request('GET',$url);

        $this->assertTrue($this->client->getResponse()->isRedirect('/security/card/?url='.$url));
        $crawler = $this->client->followRedirect();
        $crawler = $this->inputCardKey($crawler, '1111');
        $crawler = $this->client->followRedirect();

        $form = $crawler->selectButton('form_confirm')->form();
        $form['form[number]']->setValue($nbRequestedCards);
        $crawler =  $this->client->submit($form);

        $nbAvailableCardsAfter = count($this->em->getRepository('CairnUserBundle:Card')->findAvailableCards());

        if($isExceeded){
            $this->assertEquals($nbAvailableCardsAfter,$this->container->getParameter('max_printable_cards'));
        }else{
            $this->assertEquals($nbAvailableCardsAfter,$nbAvailableCardsBefore + $nbRequestedCards);
        }

        $this->assertTrue($this->client->getResponse()->headers->contains(
            'Content-Type',
            'application/pdf'));

    }

    public function provideDataForSetGeneration()
    {
        
        return array(
            'number of printable cards exceeded'=> array('isExceeded'=>true),
            'correct number of printable cards'=> array('isExceeded'=>false),
        );
    }       

}

