<?php

namespace Cairn\UserBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;

use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;

use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

use Cairn\UserBundle\Repository\UserRepository;

class MandateType extends AbstractType
{
    private $userRepo;

    public function __construct(UserRepository $userRepo)
    {
        $this->userRepo = $userRepo;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('contractor', TextType::class, array('label' => 'Compte','attr'=>array('placeholder'=>'email ou nom'),'mapped'=>false))
            ->add('amount', NumberType::class, array('label'=>'Montant','scale'=>2,'attr'=>array()))
            ->add('beginAt', DateType::class, array('label'=> 'Début','widget' => 'single_text','format' => 'yyyy-MM-dd',"attr"=>array('class'=>'datepicker_cairn')))
            ->add('endAt', DateType::class, array('label'=> 'Fin','widget' => 'single_text','format' => 'yyyy-MM-dd',"attr"=>array('class'=>'datepicker_cairn')))
            ->add('mandateDocuments', CollectionType::class, array(
                'entry_type'   => MandateDocumentType::class,
                'error_bubbling' => false,
                'prototype' => true,
                'allow_add'    => true,
                'allow_delete' => true,
                'by_reference' => false,
                'label' => 'Documents'
            ))
            ->add('forward', SubmitType::class, array('label' => 'Déclarer'));

        $builder->addEventListener( // change options depending on if mandate is created or edited
            FormEvents::POST_SET_DATA,
            function (FormEvent $event) {
                $mandate = $event->getData();
                $form = $event->getForm();
                if(null === $mandate){
                    return;
                }
                if($mandate->getID()){

                    $form->add('contractor',  EntityType::class, array(
                        'class'        => 'CairnUserBundle:User',
                        'choice_label' => 'autocompleteLabel',
                        'multiple'     => false,
                    ));

                    $disabledFields = array('contractor','beginAt');

                    foreach($disabledFields as $fieldName){
                        $myField = $form->get($fieldName)->getConfig();
                        $fieldOptions = $myField->getOptions();
                        // Retrieve the FormType. That is the part that is different.
                        $fieldType = get_class($myField->getType()->getInnerType());
                        $fieldOptions['disabled'] = true;
                        // I can obviously put the name 'my_field' directly here
                        $form->add($myField->getName(), $fieldType, $fieldOptions);
                    }

                    $docField = 'mandateDocuments';
                    $myField = $form->get($docField)->getConfig();

                    $fieldOptions = $myField->getOptions();
                    // Retrieve the FormType. That is the part that is different.
                    $fieldType = get_class($myField->getType()->getInnerType());
                    $fieldOptions['required'] = false;
                    // I can obviously put the name 'my_field' directly here
                    $form->add($myField->getName(), $fieldType, $fieldOptions);

                }

            }
        );

        $builder->addEventListener(
            FormEvents::SUBMIT,
            function (FormEvent $event) {
                $mandate = $event->getData();
                $form = $event->getForm();
                if(null === $mandate){
                    return;
                }
                if(! $mandate->getID()){
                    $autocompleteName = $form['contractor']->getData();
                    preg_match('#\((.*)\)$#',$autocompleteName,$matches_email);

                    if(! $matches_email){
                        $contractor = NULL;
                    }else{
                        $contractor = $this->userRepo->findOneByEmail($matches_email[1]);
                    }

                    $mandate->setContractor($contractor);
                }
            }
        );

    }
    
    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'Cairn\UserBundle\Entity\Mandate'
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'cairn_userbundle_mandate';
    }


}
