<?php

namespace AppBundle\Form;

use AppBundle\Committee\CommitteeManager;
use AppBundle\Entity\Committee;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CommitteeAroundAdherentType extends AbstractType
{
    /** @var CommitteeManager $manager */
    private $manager;

    public function __construct(CommitteeManager $manager)
    {
        $this->manager = $manager;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $committees = $this->manager->getCommitteesAndMembersNearByCoordinates($options['coordinates']);

        $builder
            ->add('committees', ChoiceType::class, [
                'choices' => self::getChoices($committees),
                'choice_name' => function ($choice) {
                    return $choice;
                },
                'multiple' => true,
                'expanded' => true,
            ])
            ->setAttribute('committees', $committees)
        ;
    }

    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        $committees = $form->getConfig()->getAttribute('committees');

        foreach ($committees as $name => $committee) {
            $view->vars['committees_views_data'][$name] = [
                'slug' => $committee['committee']->getSlug(),
                'members_count' => $committee['members_count'],
            ];
        }
    }

    public function getBlockPrefix()
    {
        return 'app_membership_choose_committees_around_adherent';
    }

    private static function getChoices(array $data): array
    {
        foreach ($data as $row) {
            /** @var Committee $committee */
            $committee = $row['committee'];
            $choices[$committee->getName()] = $committee->getUuid()->toString();
        }

        return $choices ?? [];
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'coordinates' => null,
        ));
    }
}
