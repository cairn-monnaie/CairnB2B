<?php 

// src/Cairn/UserBundle/Controller/ApiController.php

namespace Cairn\UserBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

use Symfony\Component\Form\FormBuilderInterface;                               
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;                   
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;


use Cairn\UserBundle\Entity\Operation;
use Cairn\UserBundle\Entity\OnlinePayment;
use Cairn\UserBundle\Entity\User;
use Cairn\UserBundle\Entity\Address;
use Cairn\UserBundle\Entity\ZipCity;
use Cairn\UserBundle\Entity\ProCategory;


/**
 * This class contains actions related to other applications as webhooks and specific API functions 
 */
class ApiController extends BaseController
{
    public function proCategoriesAction(Request $request)
    {
        $categories = $this->getDoctrine()->getManager()->getRepository('CairnUserBundle:ProCategory')->findAll();
        return $this->getRenderResponse('',[],$categories, Response::HTTP_OK);
    }

    public function syncProCategoriesAction(Request $request)
    {
        if($request->isMethod('POST')){
            $em = $this->getDoctrine()->getManager();
            $pcRepo = $em->getRepository('CairnUserBundle:ProCategory');

            $jsonRequest = json_decode(htmlspecialchars($request->getContent(),ENT_NOQUOTES), true);

            $action = strtoupper($jsonRequest['action']);

            if(! preg_match('/^[a-z][\_\-a-z0-9]*$/', $jsonRequest['slug'])){
                return $this->getErrorsResponse(['key'=>'invalid_format_value','args'=>['slug']], [] ,Response::HTTP_BAD_REQUEST);
            }
            if(! preg_match('/^[a-z][\_\-a-z0-9]*$/', $jsonRequest['new_slug'])){
                return $this->getErrorsResponse(['key'=>'invalid_format_value','args'=>['slug']], [] ,Response::HTTP_BAD_REQUEST);
            }

            //if action = add, slug = new_slug
            $newProCategory = $pcRepo->findOneBySlug($jsonRequest['new_slug']);
            $formerProCategory = $pcRepo->findOneBySlug($jsonRequest['slug']);

            if(strtoupper($action) == 'ADD'){
                if($newProCategory){
                    return $this->getErrorsResponse(['key'=>'already_in_use'], [] ,Response::HTTP_BAD_REQUEST);
                }
                $newProCategory = new ProCategory(strtolower($jsonRequest['new_slug']),$jsonRequest['name']);
                $em->persist($newProCategory);
                $em->flush();
                return $this->getRenderResponse('',[],$newProCategory, Response::HTTP_CREATED);
            }else{
                if(strtoupper($action) == 'UPDATE'){
                    if(! $formerProCategory){
                        $formerProCategory = new ProCategory(strtolower($jsonRequest['slug']),$jsonRequest['name']);
                        $em->persist($formerProCategory);
                    }
                    if($newProCategory){
                        return $this->getErrorsResponse(['key'=>'already_in_use'], [] ,Response::HTTP_BAD_REQUEST);
                    }

                    $formerProCategory->setSlug($jsonRequest['new_slug']);
                    $formerProCategory->setName($jsonRequest['name']);

                    $em->flush();
                    return $this->getRenderResponse('',[],$formerProCategory, Response::HTTP_CREATED);
                }elseif($action == 'DELETE'){
                    if(! $formerProCategory){
                        return $this->getRenderResponse('',['key'=>'data_not_found'],[], Response::HTTP_OK);
                    }
                    $em->remove($formerProCategory);
                    $em->flush();
                    return $this->getRenderResponse('',[],[], Response::HTTP_CREATED);
                }
            }

            return $this->getErrorsResponse(['key'=>'invalid_field_value','args'=>['action']], [] ,Response::HTTP_BAD_REQUEST);
        }else{
            throw new NotFoundHttpException('POST Method required !');
        }

    }

    public function phonesAction(Request $request)
    {
        $user = $this->getUser();
        $phones = $user->getPhones(); 
        $phones = is_array($phones) ? $phones : $phones->getValues();

        return $this->getRenderResponse('', [], $phones, Response::HTTP_OK);
    }

    /**
     * Sync pro from dolibarr data
     *
     */
    public function syncProAction(Request $request)
    {
        if($request->isMethod('POST')){
            $em = $this->getDoctrine()->getManager();

            $jsonRequest = json_decode(htmlspecialchars($request->getContent(),ENT_NOQUOTES), true);
            $errors = [];

            if(! ($jsonRequest['morphy']=='mor' && $jsonRequest['typeid']=='2')){
                return $this->getErrorsResponse(['key'=>'not_pro','args'=>[$jsonRequest['nom_comm']]], [] ,Response::HTTP_BAD_REQUEST);
            }

            $userRepository = $em->getRepository('CairnUserBundle:User');

            $doctrineUser = $userRepository->findOneByDolibarrID($jsonRequest['login']);

            if(! $doctrineUser){
                $doctrineUser = new User();
           
                $doctrineUser->setUsername(preg_replace('/[^a-zA-Z0-9]/','',trim($jsonRequest['new_login'])) );
                $doctrineUser->setEmail(trim($jsonRequest['email']));
                $doctrineUser->addRole('ROLE_PRO');
                
                $doctrineUser->setPlainPassword(User::randomPassword());
                $doctrineUser->setMainICC(null);

                $address = new Address();
                $doctrineUser->setAddress($address);
            }

            $doctrineUser->setDolibarrID(trim($jsonRequest['new_login']));
            
            $doctrineUser->setExcerpt(htmlspecialchars($jsonRequest['short_desc'],ENT_QUOTES));
            $doctrineUser->setDescription(htmlspecialchars($jsonRequest['long_desc'],ENT_QUOTES));

            $doctrineUser->setUrl($jsonRequest['url']);
            $doctrineUser->setName(trim($jsonRequest['nom_comm'])); 

            $hasAccountOpened = ($doctrineUser->getID() && $doctrineUser->getMainICC());
            if(! $jsonRequest['publish']){
                if($hasAccountOpened && $doctrineUser->isEnabled()){
                    $this->get('cairn_user.access_platform')->disable([$doctrineUser],'ELSE');
                }else{
                    $doctrineUser->setEnabled(false);
                }
            }else{
                if($hasAccountOpened && (! $doctrineUser->isEnabled()) ){
                    $this->get('cairn_user.access_platform')->enable([$doctrineUser]);
                }
                //ne pas forcer enable = true ici. La réouverture de compte doit être faite manuellement
            }
            $doctrineUser->setPublish($jsonRequest['publish']);

            //DEAL WITH CATEGORIES
            $pcCategory = $em->getRepository('CairnUserBundle:ProCategory');

            ///newCats: [3,5,2] formerCats[3,4]
            $newCategories = $pcCategory->findBySlug($jsonRequest['categories']);
            $formerIds = $pcCategory->findUserCategoriesIds($doctrineUser);
            $newIds = [];
            foreach($newCategories as $category){
                $newIds[] = $category->getID();
                if(! in_array($category->getID(),$formerIds)){//for instance 5,2
                    $doctrineUser->addProCategory($category);
                }
            }

            $toRemoveIds = array_diff($formerIds,$newIds);
            $toRemoveCategories = $pcCategory->findById($toRemoveIds);
            foreach($toRemoveCategories as $category){
                $doctrineUser->removeProCategory($category);
            }

            $address = $doctrineUser->getAddress();
            $address->setStreet1($jsonRequest['address']);
            
            //find correct zipcity
            $zipRepository = $em->getRepository('CairnUserBundle:ZipCity');
            $zip = $zipRepository->findCorrectZipCity($jsonRequest['zipcode'],$jsonRequest['town']);
            if(! $zip){
                $errors[] = ['key'=>'invalid_zipcode','args'=>[$jsonRequest['zipcode'].'/'.$jsonRequest['town']]];
                return $this->getErrorsResponse($errors,[],Response::HTTP_OK);
            }
            
            $address->setZipCity($zip);
            $this->get('cairn_user.security')->assignDefaultReferents($doctrineUser);

            $listErrors = $this->get('validator')->validate($doctrineUser); 

            $apiService = $this->get('cairn_user.api');

            if(count($listErrors) > 0){
                foreach($listErrors as $error){
                    $code = $error->getCode();
                    //code is NULL or symfony format(e.g 6e5212ed-a197-4339-99aa-5654798a4854 )
                    if((!$code) || (preg_match('#^(\w+\-){4,}#',$code))){
                        $errors[] = array('key'=>$error->getMessageTemplate(),'message'=>$error->getMessage(),'args'=>[$error->getInvalidValue()]);
                    }else{
                        $tmp = array('key'=>$code,'args'=>[$error->getInvalidValue()]);
                        if($error->getMessage()){
                            $tmp['message'] = $error->getMessage();
                        }
                        $errors[] = $tmp;
                    }
                }
                return $this->getErrorsResponse($errors,[],Response::HTTP_OK);
            }else{
                $em->persist($doctrineUser);
                $em->flush();

                return $this->getRenderResponse(
                    '',
                    [],
                    $doctrineUser,
                    Response::HTTP_CREATED
                );
            }
            
            
        }else{
            throw new NotFoundHttpException('POST Method required !');
        }
    }

    public function setFirstLoginAction(Request $request)
    {
        $this->getUser()->setFirstLogin(true);
        $this->getDoctrine()->getManager()->flush();

        return $this->getRenderResponse(
            '',
            [],
            $this->getUser(),
            Response::HTTP_OK
        );
    }

    public function usersAction(Request $request)
    {
        if($request->isMethod('POST')){

            $jsonRequest = json_decode(htmlspecialchars($request->getContent(),ENT_NOQUOTES), true);

            $em = $this->getDoctrine()->getManager();
            $userRepo = $em->getRepository(User::class);

            $ub = $userRepo->createQueryBuilder('u')
                ->setMaxResults(abs($jsonRequest['limit']))
                ->setFirstResult(abs($jsonRequest['offset']));

            if($jsonRequest['orderBy']['key']){
                $ub->orderBy('u.'.trim($jsonRequest['orderBy']['key']),$jsonRequest['orderBy']['order']);
            }else{
                $ub->orderBy('u.name','ASC');
            }

            $matchEmail = false;
            $matchICC = false;
            if($jsonRequest['name']){

                $matchEmail = preg_match('#^[a-z0-9._-]+@[a-z0-9._-]{2,}\.[a-z]{2,4}$#',$jsonRequest['name']);
                $matchICC = preg_match('#^\d{9}$#',$jsonRequest['name']);

                $ub->andWhere(
                    $ub->expr()->orX(
                        $ub->expr()->like('u.name', $ub->expr()->literal('%'.$jsonRequest['name'].'%'))
                        ,
                        $ub->expr()->like('u.username', $ub->expr()->literal('%'.$jsonRequest['name'].'%'))
                        ,
                        $ub->expr()->like('u.email', $ub->expr()->literal('%'.$jsonRequest['name'].'%'))
                        ,
                        "u.mainICC = :name"
                    )
                )
                ->setParameter('name',$jsonRequest['name'])
                ;
            }

            if(isset($jsonRequest['keywords']) && $jsonRequest['keywords']  && (! empty($jsonRequest['keywords']))){
                $userRepo->whereKeywords($ub,$jsonRequest['keywords']);
            }

            if(isset($jsonRequest['payment_context']) && ($jsonRequest['payment_context'] == true)){
                $userRepo->whereConfirmed($ub);
            }else{
                $userRepo->wherePublish($ub,true);
            }

            if(isset($jsonRequest['categories']) && is_array($jsonRequest['categories']) && (! empty($jsonRequest['categories']))){
                $userRepo->whereProCategoriesSlugs($ub,$jsonRequest['categories']);
            }

            $userRepo->whereAdherent($ub);

            $currentUser =  $this->get('cairn_user.security')->getCurrentUser();

            if(! $currentUser){ //logout request
                $userRepo->whereRole($ub,'ROLE_PRO');
            }else{
                if(! $currentUser->isAdmin()){

                    if($matchEmail || $matchICC){//mail exact ou n° de compte exact
                        $userRepo->whereRoles($ub,array_values($jsonRequest['roles']));
                    }else{
                        $userRepo->whereRole($ub,'ROLE_PRO');
                    } 

                }else{// let admin choose according to POST sent
                    if(empty(array_values($jsonRequest['roles']))){
                        $userRepo->whereAdherent($ub);
                    }else{
                        $userRepo->whereRoles($ub,array_values($jsonRequest['roles']));
                    }
                }
            }

            $boundingValues = array_values($jsonRequest['bounding_box']);
            if( (! in_array('', $boundingValues)) && !empty($boundingValues) ){
                $ub->join('u.address','a')
                    ->andWhere('a.longitude > :minLon')
                    ->andWhere('a.longitude < :maxLon')
                    ->andWhere('a.latitude > :minLat')
                    ->andWhere('a.latitude < :maxLat')
                    ->setParameter('minLon',$jsonRequest['bounding_box']['minLon'])
                    ->setParameter('maxLon',$jsonRequest['bounding_box']['maxLon'])
                    ->setParameter('minLat',$jsonRequest['bounding_box']['minLat'])
                    ->setParameter('maxLat',$jsonRequest['bounding_box']['maxLat'])
                    ;
            }

            $users = $ub->getQuery()->getResult();

            if( ($matchEmail || $matchICC) && (count($users) == 1) && $users[0]->hasRole('ROLE_PERSON')){
                $users = [
                    'name' => $users[0]->getName(),
                    'account_number' => $users[0]->getMainICC()
                ];
            }

            return $this->getRenderResponse(
                '',
                [],
                $users,
                Response::HTTP_OK
            );

        }else{
            throw new NotFoundHttpException('POST Method required !');
        }
    }


    public function createOnlinePaymentAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $userRepo = $em->getRepository('CairnUserBundle:User');
        $securityService = $this->get('cairn_user.security');
        $apiService = $this->get('cairn_user.api');

        //if no user found linked to the domain name

        $creditorUser = $this->getUser();
        if(! $creditorUser ){
            return $this->getErrorsResponse(['key'=>'data_not_found'],[],Response::HTTP_FORBIDDEN);
        }

        if(! ($request->headers->get('Content-Type') == 'application/json')){
            return $this->getErrorsResponse(['key'=>'invalid_field_value','args'=>['Content-Type']],[],Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
        }

        //no possible code injection
        $postParameters = json_decode( htmlspecialchars($request->getContent(),ENT_NOQUOTES),true );

        $postAccountNumber = $postParameters['account_number'];


        if($creditorUser->getMainICC() != $postAccountNumber ){
            return $this->getErrorsResponse(['key'=>'data_not_found'],[],Response::HTTP_NOT_FOUND);
        }

        if(! $creditorUser->hasRole('ROLE_PRO')){
            return $this->getErrorsResponse(['key'=>'not_pro','args'=>[$creditorUser->getName()]],[],Response::HTTP_FORBIDDEN);
        }

        if(! $creditorUser->getApiClient()){
            return $this->getErrorsResponse(['key'=>'missing_value','args'=>['apiClient']],[],Response::HTTP_PRECONDITION_FAILED);
        }

        if(! $creditorUser->getApiClient()->getWebhook()){
            return $this->getErrorsResponse(['key'=>'missing_value','args'=>['webhook']],[],Response::HTTP_PRECONDITION_FAILED);
        }

        $oPRepo = $em->getRepository('CairnUserBundle:OnlinePayment');

        $onlinePayment = $oPRepo->findOneByInvoiceID($postParameters['invoice_id']);

        if($onlinePayment){
            $suffix = $onlinePayment->getUrlValidationSuffix();
        }else{
            $onlinePayment = new OnlinePayment();
            $suffix = preg_replace('#[^a-zA-Z0-9]#','@',$securityService->generateToken());
            $onlinePayment->setUrlValidationSuffix($suffix);
            $onlinePayment->setInvoiceID($postParameters['invoice_id']);
        }

        //validate POST content
        if( (! is_numeric($postParameters['amount']))   ){
            return $apiService->getErrorsResponse(['key'=>'invalid_field_value','args'=>[$postParameters['amount']]], [] ,Response::HTTP_BAD_REQUEST);
        }

        $numericalAmount = floatval($postParameters['amount']);
        $numericalAmount = round($numericalAmount,2); 

        if( $numericalAmount < 0.01  ){
            return $apiService->getErrorsResponse(['key'=>'invalid_field_value','args'=>[$numericalAmount]], [] ,Response::HTTP_BAD_REQUEST);
        }

        if(! preg_match('#^(http|https):\/\/#',$postParameters['return_url_success'])){
            return $apiService->getErrorsResponse(['key'=>'invalid_field_value','args'=>[$postParameters['return_url_success']]], [] ,Response::HTTP_BAD_REQUEST);

        }

        if(! preg_match('#^(http|https):\/\/#',$postParameters['return_url_failure'])){
            return $apiService->getErrorsResponse(['key'=>'invalid_field_value','args'=>[$postParameters['return_url_failure']]], [] ,Response::HTTP_BAD_REQUEST);
        }

        if( strlen($postParameters['reason']) > 35){                                  
            return $apiService->getErrorsResponse(['key'=>'too_many_chars','args'=>['reason']], [] ,Response::HTTP_BAD_REQUEST);
        } 

        //finally register new onlinePayment data
        $onlinePayment->setUrlSuccess($postParameters['return_url_success']);
        $onlinePayment->setUrlFailure($postParameters['return_url_failure']);
        $onlinePayment->setAmount($numericalAmount);
        $onlinePayment->setAccountNumber($postParameters['account_number']);
        $onlinePayment->setReason($postParameters['reason']);

        $em->persist($onlinePayment);
        $em->flush();

        $payload = array(
            'invoice_id' => $postParameters['invoice_id'],
            'redirect_url' => $this->generateUrl('cairn_user_online_payment_execute',array('suffix'=>$suffix),UrlGeneratorInterface::ABSOLUTE_URL)
        );

        return $this->getRenderResponse(
            '',
            [],
            $payload,
            Response::HTTP_CREATED,
            ['key'=>'registered_operation']
        );
    }

}
