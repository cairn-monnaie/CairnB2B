<?php
// src/Cairn/UserBundle/Validator/UserValidator.php

namespace Cairn\UserBundle\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

use Cairn\UserCyclosBundle\Entity\UserManager;
use Cairn\UserCyclosBundle\Service\ChannelInfo;
use Cairn\UserCyclosBundle\Service\GroupInfo;
use Cairn\UserCyclosBundle\Service\NetworkInfo;

class UserValidator extends ConstraintValidator
{
    protected $networkName;    
    protected $groupName;    
    protected $channelInfo;
    protected $groupInfo;
    protected $networkInfo;
    protected $cyclosUserManager;

    public function __construct($networkName, $groupName, ChannelInfo $channelInfo, GroupInfo $groupInfo, NetworkInfo $networkInfo)
    {
        $this->networkName = $networkName;
        $this->groupName = $groupName;
        $this->channelInfo = $channelInfo;
        $this->groupInfo = $groupInfo;
        $this->networkInfo = $networkInfo;
        $this->cyclosUserManager = new UserManager();
    }

    /**
     * Validates the provided user information
     *
     * This function first validates user data on Symfony side according to our custom constraints. Once this phase is successfully
     * achieved, we take advantage of the Cyclos algorithm to validate passwords.
     * A validation error on Cyclos side necessarily means that its inner validation algorithm detected it as too obvious or too 
     * repetitive. If so, a violation is added.
     * In Cyclos 4, the algorithm is not accessible, but Cyclos3 is open-source. We can see that the algorithm depends on profile fields
     * name / username and email. For this reason, it is pertinent to make this class a class constraint validator (and not simply a 
     * password property validator)
     *
     */
    public function validate($user, Constraint $constraint)
    {
        if($user->getUsername()){
            if(strlen($user->getUsername()) < 3){
                $this->context->buildViolation('Login trop court ! 3 caractères minimum')
                    ->setCode('too_few_chars')
                    ->setInvalidValue($user->getUsername())
                    ->atPath('username')
                    ->addViolation();
            }

            if(preg_match('#[^a-zA-Z0-9]$#',$user->getUsername())){
                $this->context->buildViolation('Le pseudo contient uniquement des caractères alphanumériques, tirets de soulignements ou point')
                    ->setCode('invalid_format_value')
                    ->setInvalidValue($user->getUsername())
                    ->atPath('username')
                    ->addViolation();
            }
            if(strlen($user->getUsername()) > 16){
                $this->context->buildViolation('Le pseudo doit contenir moins de 16 caractères.')
                    ->setCode('too_long')
                    ->setInvalidValue($user->getUsername())
                    ->atPath('username')
                    ->addViolation();
            }
        }
        if(strlen($user->getName()) < 3){
            $this->context->buildViolation('Le nom doit contenir au minimum 3 caractères.')
                ->setCode('too_few_chars')
                ->setInvalidValue($user->getName())
                ->atPath('name')
                ->addViolation();
        }

        if(strlen($user->getName()) > 50){
            $this->context->buildViolation('Le nom doit contenir moins de 50 caractères.')
                ->setCode('too_many_chars')
                ->setInvalidValue('Le nom')
                ->atPath('name')
                ->addViolation();
        }

        $limit = ($user->hasRole('ROLE_PRO')) ? 3000 : 150;
        if(strlen($user->getDescription()) > $limit){
            $this->context->buildViolation('La description doit contenir moins de '.$limit.' caractères.')
                ->setCode('too_many_chars')
                ->setInvalidValue('La description')
                ->atPath('description')
                ->addViolation();
        }

        // ------------ Validate Password ---------------
        //Cyclos 4.11.2 bug reported : character '<' provoks validation error. For this reason, we disable it here
        if($user->getPlainPassword()){
//            if(preg_match('#[<>\\\\]#',$user->getPlainPassword())){
//                $this->context->buildViolation('Les caractères spéciaux <> et \ ne sont pas autorisés.')
//                    ->atPath('plainPassword')
//                    ->addViolation();
//            }else{
                if(! preg_match('<[`@!"#$%&\'()*+,-./:;=?\[\]\>\<^_{}~]>', $user->getPlainPassword()) ){
                    $this->context->buildViolation('pwd.special_char')
                        ->setInvalidValue('password')
                        ->atPath('plainPassword')
                        ->addViolation();
                }
                if( preg_match('<[^a-zA-Z0-9`@!"#$%&\>\<\'()*+,-./:;=?\[\]^_{}~]>u',$user->getPlainPassword(),$matches)){
                    $list = '';
                    foreach($matches as $match){
                        $list .= $match;
                    }
                    $this->context->buildViolation('Les caractères suivants ne sont pas autorisés : '.$list)
                        ->setCode('pwd.invalid_special_char')
                        ->setInvalidValue($list)
                        ->atPath('plainPassword')
                        ->addViolation();
                }
            //}

            if($username = $user->getUsername()){
                if(preg_match('<'.$user->getUsername().'>',$user->getPlainPassword())){
                    $this->context->buildViolation('pwd.pseudo_in')
                        ->setInvalidValue('password')
                        ->atPath('plainPassword')
                        ->addViolation();
                }
            }

            if(strlen($user->getPlainPassword()) > 25){
                $this->context->buildViolation('Le mot de passe doit contenir moins de 25 caractères.')
                    ->setCode('too_many_chars')
                    ->setInvalidValue('Mot de passe')
                    ->atPath('plainPassword')
                    ->addViolation();
            }
            if(strlen($user->getPlainPassword()) < 8){
                $this->context->buildViolation('Le mot de passe doit contenir plus de 8 caractères.')
                    ->setCode('too_low_chars')
                    ->setInvalidValue('Mot de passe')
                    ->atPath('plainPassword')
                    ->addViolation();
            }
        }

        if(!preg_match('#^[a-z0-9._-]+@[a-z0-9._-]{2,}\.[a-z]{2,4}$#',strtolower($user->getEmail()))){
            $this->context->buildViolation("email.invalid_format")
                ->setInvalidValue($user->getEmail())
                ->atPath('email')
                ->addViolation();
        }
    }

}

        //WARNING with commented code below : creates duplicated users in cyclos database. It is a problem for network and global admins if they want to change their password
        //if no violation, it necessarily means that a validation error would come from password
//        if(count($this->context->getViolations()) == 0){
//
//            $this->networkInfo->switchToNetwork($this->networkName);
//
//            $groupName = $this->groupName;
//            $groupVO = $this->groupInfo->getGroupVO($groupName);
//
//            //if the webServices channel is not added, it will be impossible to update/remove the cyclos user entity from third application
//            $webServicesChannelVO = $this->channelInfo->getChannelVO('webServices');
//
//            //add an equivalent user in cyclos if no other error to make sure a possible validation error comes from Cyclos algorithm  
//
//            $userDTO = new \stdClass();                                    
//            $userDTO->name = $user->getName();                             
//            $userDTO->username = $user->getUsername();                     
//            $userDTO->internalName = $user->getUsername();                 
//            $userDTO->login = $user->getUsername();                        
//            $userDTO->email = $user->getEmail();                           
//
//
//            $password = new \stdClass();                                   
//            $password->assign = true;                                      
//            $password->type = 'login';//in Cyclos : System -> User config -> password types -> click on login Password
//            $password->value = $user->getPlainPassword();                  
//            $password->confirmationValue = $user->getPlainPassword();//control already done in Symfony
//            $userDTO->passwords = $password;                               
//
//            try{                                                                   
//                $newUserCyclosID = $this->cyclosUserManager->addUser($userDTO,$groupVO,$webServicesChannelVO);
//                $params = new \stdClass();                                             
//                $params->status = 'REMOVED';
//                $params->user = $newUserCyclosID;
//                $this->cyclosUserManager->changeStatusUser($params);
//            }catch(\Exception $e){                                                 
//                if($e->errorCode == 'VALIDATION'){                                 
//                    $this->context->buildViolation('Mot de passe trop simple')
//                        ->atPath('plainPassword')
//                        ->addViolation();
//                }else{
//                    throw $e;
//                }
//            }
//        }
//
//    }
//}
