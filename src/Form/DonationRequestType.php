<?php

namespace AppBundle\Form;

use AppBundle\Donation\DonationRequest;
use AppBundle\Donation\DonationRequestUtils;
use AppBundle\Entity\Adherent;
use AppBundle\Form\DataTransformer\FloatToStringTransformer;
use AppBundle\Membership\MembershipUtils;
use Doctrine\ORM\EntityManagerInterface;
use Misd\PhoneNumberBundle\Form\Type\PhoneNumberType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class DonationRequestType extends AbstractType
{
    /** @var DonationRequestUtils */
    private $donationRequestUtils;

    /** @var MembershipUtils */
    private $membershipUtils;

    /** @var TokenStorageInterface */
    private $tokenStorage;

    /** @var RequestStack */
    private $requestStack;

    /** @var EntityManagerInterface */
    private $entityManager;

    public function __construct(
        DonationRequestUtils $donationRequestUtils,
        MembershipUtils $membershipUtils,
        RequestStack $requestStack,
        TokenStorageInterface $tokenStorage,
        EntityManagerInterface $entityManager)
    {
        $this->membershipUtils = $membershipUtils;
        $this->donationRequestUtils = $donationRequestUtils;
        $this->requestStack = $requestStack;
        $this->tokenStorage = $tokenStorage;
        $this->entityManager = $entityManager;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add(
                $builder
                    ->create('amount', HiddenType::class)
                    ->addViewTransformer(new FloatToStringTransformer())
            )
            ->add('duration', HiddenType::class)
        ;

        if ($options['sponsor_form']) {
            $builder
                ->add('gender', GenderType::class)
                ->add('firstName', TextType::class, [
                    'filter_emojis' => true,
                ])
                ->add('lastName', TextType::class, [
                    'filter_emojis' => true,
                ])
                ->add('emailAddress', EmailType::class)
                ->add('address', TextType::class)
                ->add('postalCode', TextType::class)
                ->add('cityName', TextType::class, [
                    'required' => false,
                ])
                ->add('country', UnitedNationsCountryType::class)
                ->add('phone', PhoneNumberType::class, [
                    'required' => false,
                    'widget' => PhoneNumberType::WIDGET_COUNTRY_CHOICE,
                ])
            ;
        }

        if ($options['submit_button']) {
            $builder->add('submit', SubmitType::class, [
                'label' => $options['submit_label'] ?? 'Je donne',
            ]);
        }

        $builder->addEventListener(FormEvents::PRE_SET_DATA, [$this, 'createInitialDonationRequest']);
    }

    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        if ($options['sponsor_form']) {
            $view->vars['sponsor_form'] = true;
        }
    }

    public function createInitialDonationRequest(FormEvent $formEvent): void
    {
        if (!$formEvent->getData()) {
            $user = $this->tokenStorage->getToken()->getUser();

            // The user come from the registration process
            if ($uuid = $this->membershipUtils->getSessionNewAdherentUuid()) {
                $user = $this->entityManager->getRepository(Adherent::class)->findByUuid($uuid);
            }

            if (!$user instanceof Adherent) {
                $user = null;
            }

            $formEvent->setData($this->donationRequestUtils->createFromRequest($this->requestStack->getCurrentRequest(), $user));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver
            ->setDefaults([
                'locale' => 'fr',
                'sponsor_form' => true,
                'submit_button' => true,
                'data_class' => DonationRequest::class,
                'translation_domain' => false,
            ])
            ->setDefined('submit_label')
            ->setAllowedTypes('locale', ['null', 'string'])
            ->setAllowedTypes('sponsor_form', 'bool')
            ->setAllowedTypes('submit_button', 'bool')
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'app_donation';
    }
}
