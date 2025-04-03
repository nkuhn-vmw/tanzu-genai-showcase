<?php

namespace App\Form;

use App\Entity\ResearchReport;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ResearchReportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Report Title',
                'attr' => [
                    'placeholder' => 'Enter report title',
                    'class' => 'form-control'
                ],
            ])
            ->add('reportType', ChoiceType::class, [
                'label' => 'Report Type',
                'choices' => [
                    'Comprehensive' => 'Comprehensive',
                    'Investment' => 'Investment',
                    'Industry' => 'Industry',
                    'Competitive' => 'Competitive',
                    'Financial' => 'Financial',
                    'Strategic' => 'Strategic',
                ],
                'attr' => [
                    'class' => 'form-select'
                ],
            ])
            ->add('executiveSummary', TextareaType::class, [
                'label' => 'Executive Summary',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Enter executive summary (will be generated if using AI)',
                    'class' => 'form-control',
                    'rows' => 3
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ResearchReport::class,
        ]);
    }
}
